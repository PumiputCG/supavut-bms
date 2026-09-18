<?php

namespace Tests\Feature\Budget;

use App\Http\Middleware\Authenticate;
use App\Models\Access\FunctionUser;
use App\Models\Audit\ActivityLog;
use App\Models\Budget\Approval;
use App\Models\Budget\Budget;
use App\Models\Budget\BudgetTransaction;
use App\Models\Budget\DocGroup;
use App\Models\Budget\Invest;
use App\Models\Budget\InvestFile;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Models\Core\Notification;
use App\Services\Access\AccessService;
use App\Services\Budget\BudgetAccess;
use App\Services\Budget\BudgetLedger;
use App\Support\FinalApprover;
use App\Support\PdfPrinter;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * โมดูลงบประมาณ — เส้นทางเต็มตั้งแต่เสนอ Invest จนกลายเป็น Budget
 *
 * ที่ต้องมีเทสต์คุม (ข้อตกลงใน DECISIONS ข้อ 4)
 *   - ยอดรวมคิดจากรายการย่อยเสมอ ห้ามเชื่อค่าจากฟอร์ม
 *   - อนุมัติแบบเรียงลำดับ คนขั้นหลังเซ็นแซงคิวไม่ได้
 *   - CEO เซ็นแล้วต้องเกิด Budget + รายการตั้งงบใน ledger อัตโนมัติ
 *   - เปลี่ยนสถานะการใช้งบต้องไม่ไปแตะสถานะการอนุมัติ
 */
class BudgetFlowTest extends TestCase
{
    use RefreshModuleDatabase;

    /*
      🔴 ปิด "CEO ต่อท้ายอัตโนมัติ" ในเทสต์ชุดนี้ (config bms.final_approver)
         เพราะรหัสจริง 60004 ไม่มีบัญชีในฐานทดสอบ จะทำให้ทุกเทสต์ที่ส่งเอกสารตกหมด
         เทสต์ที่คุมพฤติกรรม CEO โดยเฉพาะจะตั้งค่านี้เองในตัวเทสต์
    */
    protected function setUp(): void
    {
        parent::setUp();

        config(['bms.final_approver' => '']);
        FinalApprover::forget();

        /*
          🔴 ร่างต้องมีกลุ่มเอกสารเสมอ (เจ้าของสั่ง 2026-09-17)
             สร้างกลุ่มตั้งต้นไว้ให้ทุกเทสต์ — เลขที่ที่ออกจึงเป็น INV-NM-2569-xxxxxx
        */
        $this->docGroup = DocGroup::create(['code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model', 'sort' => 1]);
    }

    /** กลุ่มเอกสารตั้งต้นของเทสต์ในไฟล์นี้ */
    private DocGroup $docGroup;

    private function user(string $code, array $attributes = []): AppUser
    {
        static $seq = 0;
        $seq++;

        Employee::create([
            'insight_id' => 3000 + $seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'name_th' => 'ทดสอบ',
            'surname_th' => $code,
            'dept_code' => $attributes['dept_code'] ?? 'D01',
            'dept_th' => 'แผนกทดสอบ',
            'job_code' => 'S1',
            'job_th' => 'เจ้าหน้าที่',
            'emp_status' => '1',
        ]);

        $user = AppUser::create([
            'insight_id' => 2000 + $seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'password' => 'secret',
            'role' => $attributes['role'] ?? 'user',
            'full_name_th' => 'ทดสอบ '.$code,
            'dept_code' => $attributes['dept_code'] ?? 'D01',
            'signature' => $attributes['signature'] ?? 'data:image/png;base64,TEST',
        ]);

        /*
          🔴 สิทธิ์อ่านจากหัวข้อย่อยที่ /access/modules แล้ว (เจ้าของสั่งยุบมาที่เดียว 2026-09-03)
             ตาราง budget_roles เลิกใช้ — เทสต์จึงเขียนแถวลง access_function_users แทน
             ระวัง: หัวข้อย่อยที่ "ไม่ระบุใครเลย" = เปิดให้ทุกคน จึงต้องปิดด้วยการระบุคนอื่นเสมอ
        */
        foreach ((array) ($attributes['fn'] ?? []) as $fnKey) {
            FunctionUser::create([
                'module_id' => BudgetAccess::MODULE,
                'function_key' => $fnKey,
                'employee_code' => $code,
            ]);
        }

        return $user;
    }

    /** คนที่ล็อกอินอยู่ตอนนี้ — payload() ใช้เป็นผู้เสนอตั้งต้น เหมือนที่หน้าจอทำ */
    private ?AppUser $actor = null;

    private function signIn(AppUser $user): self
    {
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);
        $this->actor = $user;

        return $this;
    }

    /** @return array<string,mixed> ข้อมูลฟอร์มข้อเสนอที่ผ่าน validation */
    private function payload(array $override = []): array
    {
        return array_merge([
            'group_id' => $this->docGroup->id,
            'fiscal_year' => 2569,
            'proposer_code' => (string) ($this->actor?->employee_code ?: 'A001'),
            'dept_code' => 'D01',
            'title' => 'งบจัดหาเครื่องคอมพิวเตอร์ฝ่ายผลิต',
            'description' => 'ทดแทนเครื่องเดิมที่หมดอายุการใช้งาน',
            'amount' => 90000,
            /*
              🔴 ผู้ขอเลือกผู้อนุมัติเองแล้ว (เจ้าของสั่ง 2026-09-09) ระบบไม่หยิบจากสิทธิ์ให้อีก
                 เทสต์จึงใช้คนที่ถือสิทธิ์ "อนุมัติ Invest" ณ ตอนนั้นเป็นลำดับตั้งต้น
            */
            'approvers' => FunctionUser::where('function_key', BudgetAccess::FN_INBOX)
                ->pluck('employee_code')->map(strval(...))->all(),
        ], $override);
    }

    public function test_someone_without_a_signature_cannot_submit(): void
    {
        /*
          🔴 ผู้ขอไม่ต้องลงนามแล้ว (เจ้าของสั่ง 2026-09-10)
             จึงส่งเอกสารได้แม้ยังไม่ได้ลงลายเซ็นไว้ที่ Insight
             (ผู้อนุมัติยังต้องมีลายเซ็น — คุมด้วยเทสต์ตัวถัดไป)
        */
        $acc = $this->user('ACC_UNSIGNED', ['fn' => BudgetAccess::FN_PROPOSE]);
        $this->user('SIGN_UNSIGNED', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        $acc->update(['signature' => null]);

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit')->assertRedirect();

        $this->assertSame(Invest::PENDING, $invest->fresh()->approval_status);
    }

    public function test_an_approver_without_a_signature_cannot_approve(): void
    {
        $acc = $this->user('ACC_NOSIGN', ['fn' => BudgetAccess::FN_PROPOSE]);
        $approver = $this->user('APP_NOSIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();
        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit')->assertRedirect();
        $approver->update(['signature' => null]);

        $this->signIn($approver)->post('/budget/doc/'.$invest->id.'/approve')->assertRedirect();

        $this->assertSame(Invest::PENDING, $invest->fresh()->approval_status);
        $this->assertSame(0, Budget::count());
    }

    public function test_only_the_creator_or_admin_can_manage_a_proposal(): void
    {
        $owner = $this->user('OWNER', ['fn' => BudgetAccess::FN_PROPOSE]);
        $other = $this->user('OTHER_OWNER', ['fn' => BudgetAccess::FN_PROPOSE]);

        $this->signIn($owner)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        $this->signIn($other)->get('/budget/invest/'.$invest->id.'/edit')->assertForbidden();
        $this->signIn($other)->put('/budget/invest/'.$invest->id, $this->payload())->assertForbidden();
        $this->signIn($other)->delete('/budget/invest/'.$invest->id)->assertForbidden();
    }

    public function test_inactive_people_and_unsafe_attachments_are_rejected(): void
    {
        Storage::fake('public');
        $acc = $this->user('ACC_VALIDATE', ['fn' => BudgetAccess::FN_PROPOSE]);
        $inactive = $this->user('INACTIVE');
        Employee::where('employee_code', $inactive->employee_code)->update(['emp_status' => '0']);

        $this->signIn($acc)->post('/budget/invest', $this->payload([
            'proposer_code' => $inactive->employee_code,
            'files' => [UploadedFile::fake()->create('script.svg', 2, 'image/svg+xml')],
        ]))->assertSessionHasErrors(['proposer_code', 'files.0']);

        $this->assertSame(0, Invest::count());
    }

    public function test_removing_an_attachment_deletes_the_file_and_writes_audit(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $acc = $this->user('ACC_FILE', ['fn' => BudgetAccess::FN_PROPOSE]);

        $this->signIn($acc)->post('/budget/invest', $this->payload([
            'files' => [UploadedFile::fake()->create('quote.pdf', 5, 'application/pdf')],
        ]));
        $file = InvestFile::firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        Storage::disk('public')->assertMissing($file->path);

        $this->signIn($acc)->delete('/budget/invest-file/'.$file->id)->assertRedirect();

        Storage::disk('local')->assertMissing($file->path);
        Storage::disk('public')->assertMissing($file->path);
        $this->assertDatabaseMissing('budget_invest_files', ['id' => $file->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'invest_file_removed']);
    }

    public function test_attachments_are_private_and_require_document_access(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->user('ATTACH_OWNER', ['fn' => BudgetAccess::FN_PROPOSE]);
        $outsider = $this->user('ATTACH_OUTSIDER');

        $this->signIn($owner)->post('/budget/invest', $this->payload([
            'files' => [UploadedFile::fake()->create('budget.pdf', 5, 'application/pdf')],
        ]));

        $file = InvestFile::firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        Storage::disk('public')->assertMissing($file->path);

        $url = route('budget.file.download', $file);
        $this->assertSame($url, $file->url());

        $this->signIn($outsider)->get($url)->assertForbidden();

        $response = $this->signIn($owner)->get($url)->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');

        Storage::disk('public')->put($file->path, 'must-not-be-served');
        Storage::disk('local')->delete($file->path);
        $this->signIn($owner)->get($url)->assertNotFound();
    }

    public function test_attachment_migration_moves_files_between_public_and_private_storage(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->user('ATTACH_MIGRATE', ['fn' => BudgetAccess::FN_PROPOSE]);

        $this->signIn($owner)->post('/budget/invest', $this->payload([
            'files' => [UploadedFile::fake()->create('legacy.pdf', 5, 'application/pdf')],
        ]));
        $file = InvestFile::firstOrFail();
        $migration = require database_path('migrations/budget/2026_09_07_112536_move_budget_attachments_to_private_storage.php');

        $migration->down();
        Storage::disk('local')->assertMissing($file->path);
        Storage::disk('public')->assertExists($file->path);

        $migration->up();
        Storage::disk('public')->assertMissing($file->path);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_updating_a_draft_writes_audit(): void
    {
        $acc = $this->user('ACC_AUDIT', ['fn' => BudgetAccess::FN_PROPOSE]);
        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        $this->signIn($acc)->put('/budget/invest/'.$invest->id, $this->payload(['title' => 'แก้ไขแล้ว']))
            ->assertRedirect();

        $this->assertSame('แก้ไขแล้ว', $invest->fresh()->title);
        $this->assertTrue(ActivityLog::where('event', 'invest_updated')->where('subject', $invest->doc_no)->exists());
    }

    public function test_an_inactive_approver_blocks_submission(): void
    {
        // เลือกคนที่ลาออกแล้วเป็นผู้อนุมัติไม่ได้ — เอกสารจะค้างเพราะไม่มีใครเซ็น
        $acc = $this->user('ACC_STALE', ['fn' => BudgetAccess::FN_PROPOSE]);
        $approver = $this->user('APP_STALE', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        Employee::where('employee_code', $approver->employee_code)->update(['emp_status' => '0']);

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit')
            ->assertRedirect()
            ->assertSessionHasErrors('approvers');

        $this->assertSame(Invest::DRAFT, $invest->fresh()->approval_status);
    }

    public function test_one_invest_cannot_create_two_budgets(): void
    {
        $budget = $this->approvedBudget();

        $this->expectException(QueryException::class);
        Budget::create([
            'doc_no' => 'BGT-2569-999999',
            'invest_id' => $budget->invest_id,
            'fiscal_year' => $budget->fiscal_year,
            'dept_code' => $budget->dept_code,
            'dept_name' => $budget->dept_name,
            'title' => 'งบซ้ำที่ต้องถูกปฏิเสธ',
            'approved_amount' => 1,
            'approval_status' => Invest::APPROVED,
            'budget_status' => Budget::PENDING_REGISTER,
        ]);
    }

    // ── สิทธิ์ ──────────────────────────────────────────────────────

    public function test_a_function_with_nobody_listed_is_closed(): void
    {
        // กติกาใหม่ 2026-09-07: ไม่ระบุใคร = ไม่มีสิทธิ์ ต้องเลือกคนหรือ ทุกคน ให้ชัดเจน
        $this->signIn($this->user('U000'))->get('/budget/invest')->assertForbidden();
    }

    public function test_only_the_listed_people_can_open_the_proposal_page(): void
    {
        // ระบุตัวคนแล้ว คนนอกรายชื่อต้องเข้าไม่ได้ — กันจริงที่ middleware ไม่ใช่แค่ทำเมนูเทา
        $acc = $this->user('A001', ['fn' => BudgetAccess::FN_PROPOSE]);
        $outsider = $this->user('U001');

        $this->signIn($acc)->get('/budget/invest')->assertOk();
        $this->signIn($outsider)->get('/budget/invest')->assertForbidden();
    }

    public function test_an_approver_without_any_permission_can_still_be_chosen(): void
    {
        /*
          🐛 บั๊กจริงที่เจ้าของเจอ 2026-09-07: กดส่งเอกสารแล้วได้ 403
             สาเหตุเดิม: หัวข้อ "รับทราบ" ตั้งสิทธิ์ล่วงหน้าไม่ได้ รายชื่อจึงว่างตลอด
             พอกติกาเป็น "ว่าง = ไม่มีใครมีสิทธิ์" ตัวตรวจเลยปัดตกทุกคน

          🔴 ตั้งแต่ 2026-09-09 ผู้ขอเลือกผู้อนุมัติได้จาก **พนักงานทุกคน**
             เทสต์นี้จงใจเลือกคนที่ **ไม่มีสิทธิ์อะไรเลย** เพื่อกันบั๊กเดิมกลับมา
        */
        $acc = $this->user('RC_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $plain = $this->user('RC_ANY');                                  // ไม่มีสิทธิ์อะไรเลย

        $this->signIn($acc)->post('/budget/invest', $this->payload(['approvers' => []]));
        $invest = Invest::firstOrFail();

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$plain->employee_code],
        ])->assertRedirect();

        $this->assertSame(Invest::PENDING, $invest->fresh()->approval_status);

        $this->assertTrue(
            Approval::where('doc_id', $invest->id)
                ->where('action', Approval::APPROVE)
                ->where('employee_code', $plain->employee_code)
                ->exists()
        );
    }

    public function test_a_recipient_can_open_the_inbox_even_without_permission(): void
    {
        /*
          คนที่ถูกขอให้รับทราบต้องเปิดหน้า "รับทราบ / อนุมัติ Invest" ได้
          🔴 หน้านี้กั้นด้วย **ข้อมูล** ไม่ใช่สิทธิ์ — เห็นเฉพาะฉบับที่ตัวเองเกี่ยวข้อง
        */
        [, $invest, , $ceo] = $this->submitted();

        $this->flushSession();
        $html = $this->signIn($ceo)->get('/budget/approval')->assertOk()->getContent();

        $this->assertStringContainsString($invest->doc_no, $html);
    }

    public function test_typing_the_url_directly_does_not_bypass_the_permission(): void
    {
        // เดิมสิทธิ์หัวข้อย่อยทำแค่ทำเมนูเทา พิมพ์ URL ตรงก็เข้าได้ — เจ้าของสั่งให้กันจริง
        $proposer = $this->user('SIGNER', ['fn' => BudgetAccess::FN_PROPOSE]);
        $outsider = $this->user('U002');

        $this->signIn($proposer)->get('/budget/invest')->assertOk();
        $this->signIn($outsider)->get('/budget/invest')->assertForbidden();
        $this->signIn($outsider)->get('/budget/list')->assertForbidden();
        $this->signIn($outsider)->get('/budget/history')->assertForbidden();
    }

    // ── สร้างข้อเสนอ ────────────────────────────────────────────────

    public function test_invest_is_a_funding_request_not_a_purchase_order(): void
    {
        // 🔴 Invest = ขอวงเงินระดับแผนก — ไม่มีรายการสินค้า/หน่วย/จำนวน (เจ้าของยืนยัน 2026-09-03)
        $acc = $this->user('A002', ['fn' => BudgetAccess::FN_PROPOSE]);

        $this->signIn($acc)->post('/budget/invest', $this->payload())->assertRedirect();

        $invest = Invest::firstOrFail();

        $this->assertEquals(90000, (float) $invest->amount);
        $this->assertSame('ทดแทนเครื่องเดิมที่หมดอายุการใช้งาน', $invest->description);
        $this->assertFalse(Schema::hasTable('budget_invest_items'));
    }

    public function test_an_invest_without_an_amount_is_rejected(): void
    {
        $acc = $this->user('A009', ['fn' => BudgetAccess::FN_PROPOSE]);

        $this->signIn($acc)->post('/budget/invest', $this->payload(['amount' => 0]))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Invest::count());
    }

    /**
     * 🔴 เลขที่ออกตอน "กดส่ง" ไม่ใช่ตอนบันทึกร่าง (เจ้าของสั่ง 2026-09-17)
     *    รูปแบบ INV-{โค้ดกลุ่ม}-{ปีงบ}-{เลขวิ่ง 6 หลัก}
     */
    public function test_document_number_uses_the_agreed_format(): void
    {
        $acc = $this->user('A003', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('A003_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload())->assertRedirect();
        $this->flushSession();
        $this->signIn($acc)->post('/budget/invest', $this->payload())->assertRedirect();

        // ร่างทั้ง 2 ใบยังไม่มีเลข
        $this->assertSame([null, null], Invest::orderBy('id')->pluck('doc_no')->all());

        foreach (Invest::orderBy('id')->get() as $invest) {
            $this->sendDraft($acc, $invest, $signer);
        }

        $this->assertSame(
            ['INV-NM-2569-000001', 'INV-NM-2569-000002'],
            Invest::orderBy('id')->pluck('doc_no')->all(),
        );
    }

    public function test_a_draft_has_no_number_until_it_is_submitted(): void
    {
        $acc = $this->user('NUM_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('NUM_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload())
            ->assertRedirect()
            ->assertSessionHas('flash_success');
        $invest = Invest::firstOrFail();

        $this->assertNull($invest->doc_no);
        $this->assertSame($this->docGroup->id, $invest->group_id);

        // 🔴 ร่างต้องขึ้น "ยังไม่ออกเลข" ไม่ใช่ช่องว่าง — ทั้งในตารางและในตัวเอกสาร
        $this->flushSession();
        $this->signIn($acc)->get('/budget/invest?group=all')->assertOk()
            ->assertSee('data-i18n="budget.noNumber"', false);
        $this->flushSession();
        // 🔴 หน้ากรอกไม่มีบรรทัดตัวอย่างเลขที่แล้ว (เจ้าของสั่งเอาออก) — เหลือแค่ป้าย "ยังไม่ออกเลข"
        $this->signIn($acc)->get('/budget/invest/'.$invest->id.'/edit')->assertOk()
            ->assertSee('data-i18n="budget.noNumber"', false)
            ->assertDontSee('INV-??-', false);

        $this->flushSession();
        $this->sendDraft($acc, $invest, $signer)->assertSessionHas('flash_success', [
            'th' => 'ส่งเรื่องเข้าสายอนุมัติแล้ว: INV-NM-2569-000001',
            'en' => 'Submitted for approval: INV-NM-2569-000001',
        ]);

        $this->assertSame('INV-NM-2569-000001', $invest->fresh()->doc_no);
    }

    /**
     * 🔴 เหตุผลของเจ้าของ: ร่างที่ได้เลขแล้วไม่ยอมส่ง จะทำให้คนที่ส่งทีหลังได้เลขข้ามกัน
     *    เลขต้องเรียงตาม "ลำดับที่ส่ง" ไม่ใช่ลำดับที่บันทึกร่าง
     */
    public function test_numbers_follow_the_order_documents_are_sent(): void
    {
        $acc = $this->user('ORD_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('ORD_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload(['title' => 'บันทึกร่างก่อน']));
        $this->flushSession();
        $this->signIn($acc)->post('/budget/invest', $this->payload(['title' => 'บันทึกร่างทีหลัง']));

        $early = Invest::where('title', 'บันทึกร่างก่อน')->firstOrFail();
        $late = Invest::where('title', 'บันทึกร่างทีหลัง')->firstOrFail();

        // ใบที่บันทึกทีหลังกดส่งก่อน
        $this->sendDraft($acc, $late, $signer);
        $this->sendDraft($acc, $early, $signer);

        $this->assertSame('INV-NM-2569-000001', $late->fresh()->doc_no);
        $this->assertSame('INV-NM-2569-000002', $early->fresh()->doc_no);
    }

    public function test_each_group_counts_its_own_numbers(): void
    {
        $acc = $this->user('GRP_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('GRP_SIGN', ['fn' => BudgetAccess::FN_INBOX]);
        $misc = DocGroup::create(['code' => 'MS', 'name_th' => 'เบ็ดเตล็ด', 'name_en' => 'Miscellaneous', 'sort' => 2]);

        foreach ([['a', $this->docGroup], ['b', $misc], ['c', $this->docGroup]] as [$title, $group]) {
            $this->flushSession();
            $this->signIn($acc)->post('/budget/invest', $this->payload(['title' => $title, 'group_id' => $group->id]));
            $this->sendDraft($acc, Invest::where('title', $title)->firstOrFail(), $signer);
        }

        $this->assertSame('INV-NM-2569-000001', Invest::where('title', 'a')->value('doc_no'));
        $this->assertSame('INV-MS-2569-000001', Invest::where('title', 'b')->value('doc_no'));
        $this->assertSame('INV-NM-2569-000002', Invest::where('title', 'c')->value('doc_no'));

        // เลขงบใช้โค้ดกลุ่มเดียวกับข้อเสนอ และนับแยกต่อกลุ่มเหมือนกัน
        $signer->update(['signature' => 'data:image/png;base64,TEST']);
        foreach (['b', 'a'] as $title) {
            $this->flushSession();
            $this->signIn($signer)->post('/budget/doc/'.Invest::where('title', $title)->value('id').'/approve')->assertRedirect();
        }

        $this->assertSame('BGT-MS-2569-000001', Budget::where('title', 'b')->value('doc_no'));
        $this->assertSame('BGT-NM-2569-000001', Budget::where('title', 'a')->value('doc_no'));
        $this->assertSame($misc->id, Budget::where('title', 'b')->value('group_id'));
    }

    /** ร่างเปลี่ยนกลุ่มได้อิสระ — เลขที่ออกตามกลุ่มที่เลือกไว้ตอนกดส่ง (เจ้าของสั่ง 2026-09-17) */
    public function test_a_draft_can_move_to_another_group_before_it_is_sent(): void
    {
        $acc = $this->user('MOV_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('MOV_SIGN', ['fn' => BudgetAccess::FN_INBOX]);
        $misc = DocGroup::create(['code' => 'MS', 'name_th' => 'เบ็ดเตล็ด', 'name_en' => 'Miscellaneous', 'sort' => 2]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        $this->flushSession();
        $this->signIn($acc)->put('/budget/invest/'.$invest->id, $this->payload(['group_id' => $misc->id]))->assertRedirect();
        $this->assertSame($misc->id, $invest->fresh()->group_id);

        $this->sendDraft($acc, $invest, $signer);
        $this->assertSame('INV-MS-2569-000001', $invest->fresh()->doc_no);

        // 🔴 ส่งแล้วแก้ไม่ได้ — กลุ่มจึงล็อกตามไปด้วย
        $this->flushSession();
        $this->signIn($acc)->put('/budget/invest/'.$invest->id, $this->payload())->assertForbidden();
        $this->assertSame($misc->id, $invest->fresh()->group_id);
    }

    /** เข้าหน้ากรอกต้องมาพร้อมกลุ่มที่เปิดใช้งานเสมอ ไม่งั้นพากลับไปหน้าเลือกกลุ่ม */
    public function test_the_create_page_needs_an_active_group(): void
    {
        $acc = $this->user('CRT_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);

        $this->signIn($acc)->get('/budget/invest/create')
            ->assertRedirect(route('budget.invest.index'))
            ->assertSessionHas('flash_error');

        $this->flushSession();
        $this->signIn($acc)->get('/budget/invest/create?group=999')
            ->assertRedirect(route('budget.invest.index'));

        // กลุ่มที่คลิกมาเป็นค่าเริ่มต้นของ dropdown
        $this->flushSession();
        $this->signIn($acc)->get('/budget/invest/create?group='.$this->docGroup->id)->assertOk()
            ->assertSee('name="group_id"', false)
            ->assertSee('value="'.$this->docGroup->id.'" data-code="NM" selected', false);

        $this->docGroup->update(['active' => false]);
        $this->flushSession();
        $this->signIn($acc)->get('/budget/invest/create?group='.$this->docGroup->id)
            ->assertRedirect(route('budget.invest.index'));
    }

    /** แอดมินปิดกลุ่มระหว่างที่ร่างค้างอยู่ — บันทึก/ส่งต้องไม่ผ่านแบบเงียบๆ */
    public function test_a_disabled_group_blocks_saving_and_sending(): void
    {
        $acc = $this->user('OFF_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('OFF_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        $this->docGroup->update(['active' => false]);

        // บันทึกไม่ผ่าน พร้อมข้อความ 2 ภาษา
        $this->flushSession();
        $this->signIn($acc)->put('/budget/invest/'.$invest->id, $this->payload(['title' => 'แก้ชื่อ']))
            ->assertRedirect()
            ->assertSessionHas('flash_error');
        $this->assertNotSame('แก้ชื่อ', $invest->fresh()->title);

        // ส่งไม่ผ่าน เลขไม่ออก สถานะยังเป็นร่าง
        $this->flushSession();
        $this->sendDraft($acc, $invest, $signer)->assertSessionHas('flash_error');
        $this->assertNull($invest->fresh()->doc_no);
        $this->assertSame(Invest::DRAFT, $invest->fresh()->approval_status);

        // หน้าแก้ไขต้องบอกให้เลือกกลุ่มใหม่ ไม่ใช่เลือกตัวแรกให้เงียบๆ
        $this->flushSession();
        $this->signIn($acc)->get('/budget/invest/'.$invest->id.'/edit')->assertOk()
            ->assertSee('data-i18n="budget.group.gone"', false)
            ->assertSee('data-i18n="budget.group.choose"', false);
    }

    /** หน้าคั่นเลือกกลุ่ม = การ์ดของกลุ่มที่เปิดใช้งาน พร้อมตัวอย่างเลขที่ทั้ง 2 ชนิด */
    public function test_the_request_page_offers_only_active_groups(): void
    {
        $acc = $this->user('PICK_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $off = DocGroup::create(['code' => 'OF', 'name_th' => 'เลิกใช้', 'name_en' => 'Retired', 'sort' => 9, 'active' => false]);
        $year = (int) now()->year + 543;

        $html = $this->signIn($acc)->get('/budget/invest')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('budget.invest.index', ['group' => $this->docGroup->id]).'"', $html);
        $this->assertStringNotContainsString('href="'.route('budget.invest.index', ['group' => $off->id]).'"', $html);
        // 🔴 การ์ดบอก "รูปแบบ" เลขวิ่งจึงเป็น xxx (เจ้าของสั่ง 2026-09-17)
        //    เขียน 000001 จะอ่านเหมือนเป็นเลขของใบถัดไป ทั้งที่เลขออกตอนกดส่ง
        // ปี 2569/2570/2571 ใช้หมวดเดียวกัน — ตัวอย่างจึงไม่ตรึงปีใดปีหนึ่ง
        $era = substr((string) $year, 0, 2);
        $this->assertStringContainsString('INV-NM-'.$era.'<span class="pick-run">xx</span>-000<span class="pick-run">xxx</span>', $html);
        $this->assertStringContainsString('BGT-NM-'.$era.'<span class="pick-run">xx</span>-000<span class="pick-run">xxx</span>', $html);
        $this->assertStringNotContainsString('INV-NM-'.$year.'-000001', $html);

        // 🔴 การ์ดไม่มีป้ายโค้ดแล้ว (เจ้าของสั่งให้กระชับ) — โค้ดอยู่ในเลขที่ตัวอย่างพอ
        $this->assertStringNotContainsString('pick-code', $html);

        // ไม่มีหมวดเปิดใช้งานเลย — คนทั่วไปได้ข้อความให้ติดต่อผู้ดูแลระบบ
        $this->docGroup->update(['active' => false]);
        $this->flushSession();
        $this->signIn($acc)->get('/budget/invest')->assertOk()
            ->assertSee('data-i18n="budget.pick.none"', false)
            ->assertSee('data-i18n="budget.pick.askAdmin"', false);
    }

    /**
     * 🔴 เมนู "ของบประมาณ" เปิดหน้าคั่นเลือกกลุ่มก่อนเสมอ (เจ้าของสั่ง 2026-09-17 รอบ 2)
     *    หน้าคั่นมีแต่การ์ดกลุ่ม · กดแล้วเข้าหน้าตาราง · ปุ่มเสนอรายการใหม่อยู่ในหน้าตาราง
     */
    public function test_the_request_menu_opens_the_group_chooser_first(): void
    {
        $acc = $this->user('CHO_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('CHO_SIGN', ['fn' => BudgetAccess::FN_INBOX]);
        $nmList = route('budget.invest.index', ['group' => $this->docGroup->id]);
        $nmCreate = route('budget.invest.create', ['group' => $this->docGroup->id]);

        // ── หน้าคั่น: มีแต่การ์ด ไม่มีตาราง ไม่มีปุ่มเสนอรายการใหม่ ──
        $chooser = $this->signIn($acc)->get('/budget/invest')->assertOk()->getContent();
        $this->assertStringContainsString('data-i18n="budget.pick.title"', $chooser);
        $this->assertStringContainsString('href="'.$nmList.'"', $chooser);
        // 🔴 วัดด้วยหัวคอลัมน์ของตารางจริง — 'data-filter-server' โผล่ในสคริปต์ตัวกรองกลางทุกหน้า วัดไม่ได้
        $this->assertStringNotContainsString('data-filter-key="doc"', $chooser, 'หน้าคั่นต้องไม่มีตาราง');
        $this->assertStringNotContainsString($nmCreate, $chooser, 'ปุ่มเสนอรายการใหม่ต้องอยู่หน้าตาราง ไม่ใช่หน้าคั่น');
        // ยังไม่มีเอกสาร — ไม่ต้องมีทางลัดดูเอกสารทั้งหมด
        $this->assertStringNotContainsString('data-i18n="budget.pick.all"', $chooser);

        // ── หน้าตารางของกลุ่ม: มีตาราง + ปุ่มเสนอรายการใหม่ของกลุ่มนั้น + ปุ่มกลับหน้าคั่น ──
        $this->flushSession();
        $list = $this->signIn($acc)->get($nmList)->assertOk()->getContent();
        $this->assertStringContainsString('data-filter-key="doc"', $list);
        $this->assertStringContainsString('href="'.$nmCreate.'"', $list);
        $this->assertStringContainsString('href="'.route('budget.invest.index').'"', $list);
        // 🔴 หน้านี้ไม่มีแถบแท็บหมวดแล้ว (เจ้าของสั่งให้กระชับ) — ใช้ตัวกรองหมวดในแถวตัวกรองแทน
        $this->assertStringNotContainsString('class="dg-tabs"', $list);
        $this->assertStringContainsString('<select class="sel" name="group"', $list);
        $this->assertStringContainsString('<option value="'.$this->docGroup->id.'" selected', $list);

        // ── ทุกหมวด: ไม่มีปุ่มเสนอรายการใหม่ (ต้องเลือกหมวดก่อน) ──
        $this->flushSession();
        $all = $this->signIn($acc)->get('/budget/invest?group=all')->assertOk()->getContent();
        $this->assertStringContainsString('data-i18n="budget.pick.allGroups"', $all);
        $this->assertStringNotContainsString('budget/invest/create', $all);
        $this->assertStringContainsString('<option value="all" selected', $all);

        // ── ส่งเอกสารแล้วกลับมาที่หน้าตารางของกลุ่มนั้น ไม่ใช่หน้าคั่น ──
        $this->flushSession();
        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $this->sendDraft($acc, Invest::firstOrFail(), $signer)->assertRedirect($nmList);

        // มีเอกสารแล้ว — หน้าคั่นมีทางลัด "ดูเอกสารทั้งหมด"
        $this->flushSession();
        $this->signIn($acc)->get('/budget/invest')->assertOk()
            ->assertSee('data-i18n="budget.pick.all"', false)
            ->assertSee(route('budget.invest.index', ['group' => 'all']), false);
    }

    /** กลุ่มที่ปิดใช้งานแล้ว — ยังเปิดดูเอกสารเก่าได้ แต่เสนอรายการใหม่ไม่ได้ */
    public function test_a_disabled_group_list_has_no_new_button(): void
    {
        $acc = $this->user('OFFL_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('OFFL_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $this->sendDraft($acc, Invest::firstOrFail(), $signer);
        $this->docGroup->update(['active' => false]);

        $this->flushSession();
        $this->signIn($acc)->get('/budget/invest?group='.$this->docGroup->id)->assertOk()
            ->assertSee('data-i18n="budget.pick.disabledHere"', false)
            ->assertSee('INV-NM-2569-000001')
            ->assertDontSee('budget/invest/create', false);

        // การ์ดหายจากหน้าคั่น แต่ยังเข้าทางลัดดูเอกสารทั้งหมดได้
        $this->flushSession();
        $this->signIn($acc)->get('/budget/invest')->assertOk()
            ->assertDontSee('href="'.route('budget.invest.index', ['group' => $this->docGroup->id]).'"', false)
            ->assertSee('data-i18n="budget.pick.all"', false);
    }

    /**
     * ตัวกรองหมวดในหน้าตาราง (เจ้าของสั่ง 2026-09-17)
     * 🔴 เลือกหมวดแล้วตารางต้องเหลือเฉพาะหมวดนั้น · ตัวเลือกต้องมีครบทุกหมวดเสมอ
     *    ไม่งั้นเลือกหมวดหนึ่งแล้วจะกลับไปหมวดอื่นไม่ได้ (หน้านี้ไม่มีแถบแท็บแล้ว)
     */
    public function test_the_category_filter_narrows_the_table(): void
    {
        $acc = $this->user('FIL_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('FIL_SIGN', ['fn' => BudgetAccess::FN_INBOX]);
        $misc = DocGroup::create(['code' => 'MS', 'name_th' => 'เบ็ดเตล็ด', 'name_en' => 'Miscellaneous']);

        foreach ([['งานโมเดลใหม่', $this->docGroup], ['งานเบ็ดเตล็ด', $misc]] as [$title, $group]) {
            $this->flushSession();
            $this->signIn($acc)->post('/budget/invest', $this->payload(['title' => $title, 'group_id' => $group->id]));
            $this->sendDraft($acc, Invest::where('title', $title)->firstOrFail(), $signer);
        }

        // ทุกหมวด — เห็นทั้ง 2 ใบ และตัวเลือกมีครบ
        $this->flushSession();
        $all = $this->signIn($acc)->get('/budget/invest?group=all')->assertOk()->getContent();
        $this->assertStringContainsString('งานโมเดลใหม่', $all);
        $this->assertStringContainsString('งานเบ็ดเตล็ด', $all);
        $this->assertStringContainsString('<option value="all" selected', $all);
        $this->assertStringContainsString('data-loc-th="เบ็ดเตล็ด"', $all);

        // เลือกหมวดเดียว — เหลือเฉพาะใบของหมวดนั้น แต่ตัวเลือกยังครบทุกหมวด
        $this->flushSession();
        $one = $this->signIn($acc)->get('/budget/invest?group='.$misc->id)->assertOk()->getContent();
        $this->assertStringContainsString('งานเบ็ดเตล็ด', $one);
        $this->assertStringNotContainsString('งานโมเดลใหม่', $one);
        $this->assertStringContainsString('<option value="'.$misc->id.'" selected', $one);
        $this->assertStringContainsString('data-loc-th="โมเดลใหม่"', $one);

        // ค่ามั่วใน URL ต้องไม่พัง — ถอยไปทุกหมวด
        $this->flushSession();
        $this->signIn($acc)->get('/budget/invest?group=abc')->assertOk()
            ->assertSee('data-i18n="budget.pick.allGroups"', false);
    }

    /** ตัวเอกสารต้องบอกหมวดงบประมาณด้วย (เจ้าของแจ้ง 2026-09-17) */
    public function test_the_document_shows_its_budget_category(): void
    {
        [, $invest, , $ceo] = $this->submitted();

        $this->flushSession();
        $this->signIn($ceo)->get('/budget/doc/'.$invest->id)->assertOk()
            ->assertSee('data-i18n="budget.docGroup"', false)
            ->assertSee('data-loc-th="โมเดลใหม่"', false)
            ->assertSee('(NM)');

        // หน้างบที่อนุมัติแล้วใช้เนื้อในเดียวกัน จึงต้องมีเหมือนกัน
        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/approve');
        $budget = Budget::firstOrFail();

        // 🔴 หน้า "ลงทะเบียน" เป็นคิวงานของบัญชี ผู้ลงนามไม่มีสิทธิ์ — ต้องใช้คนที่ถือสิทธิ์หัวข้อนั้น
        $acct = $this->user('CAT_ACCT', ['fn' => BudgetAccess::FN_BUDGETS]);

        $this->flushSession();
        $this->signIn($acct)->get('/budget/list/'.$budget->id)->assertOk()
            ->assertSee('data-i18n="budget.docGroup"', false);
    }

    /**
     * แท็บกลุ่มเอกสารครบ 4 หน้า (เจ้าของสั่ง 2026-09-17)
     * 🔴 เลือกแท็บแล้วตารางเหลือเฉพาะกลุ่มนั้น · แต่ตัวเลขบนแท็บต้องเป็นของทุกกลุ่มเสมอ
     */
    public function test_group_tabs_filter_every_budget_page(): void
    {
        $admin = $this->user('TAB_ADMIN', ['role' => 'admin']);
        $misc = DocGroup::create(['code' => 'MS', 'name_th' => 'เบ็ดเตล็ด', 'name_en' => 'Miscellaneous', 'sort' => 2]);

        $docs = [];
        foreach ([['งบกลุ่มโมเดลใหม่', $this->docGroup, 'INV-NM-2569-000001'], ['งบกลุ่มเบ็ดเตล็ด', $misc, 'INV-MS-2569-000001']] as [$title, $group, $no]) {
            $docs[$title] = $invest = Invest::create([
                'doc_no' => $no,
                'group_id' => $group->id,
                'fiscal_year' => 2569,
                'dept_code' => 'D01',
                'title' => $title,
                'amount' => 1000,
                'approval_status' => Invest::APPROVED,
                'submitted_at' => now(),
                'created_by' => $admin->employee_code,
                'created_by_name' => 'แอดมิน',
            ]);
            Budget::create([
                'doc_no' => str_replace('INV-', 'BGT-', $no),
                'group_id' => $group->id,
                'invest_id' => $invest->id,
                'fiscal_year' => 2569,
                'dept_code' => 'D01',
                'title' => $title,
                'approved_amount' => 1000,
                'approval_status' => Invest::APPROVED,
                'budget_status' => Budget::PENDING_REGISTER,
            ]);
        }

        // 🔴 หน้าของบประมาณใช้หน้าคั่น + ตัวกรองหมวดแทนแท็บ (เจ้าของสั่ง 2026-09-17) จึงไม่อยู่ในลูปนี้
        foreach (['/budget/approval', '/budget/list', '/budget/history'] as $page) {
            $this->flushSession();
            $all = $this->signIn($admin)->get($page)->assertOk()->getContent();
            $this->assertStringContainsString('งบกลุ่มโมเดลใหม่', $all, $page.' แท็บทั้งหมดต้องเห็นทุกกลุ่ม');
            $this->assertStringContainsString('งบกลุ่มเบ็ดเตล็ด', $all, $page.' แท็บทั้งหมดต้องเห็นทุกกลุ่ม');
            $this->assertMatchesRegularExpression('~class="dg-tab is-on"[^>]*>\s*<span data-i18n="budget.group.all"~', $all, $page);

            $this->flushSession();
            $one = $this->signIn($admin)->get($page.'?group='.$misc->id)->assertOk()->getContent();
            $this->assertStringContainsString('งบกลุ่มเบ็ดเตล็ด', $one, $page);
            $this->assertStringNotContainsString('title="งบกลุ่มโมเดลใหม่"', $one, $page.' เลือกแท็บแล้วต้องไม่เห็นกลุ่มอื่นในตาราง');
            $this->assertStringNotContainsString('>งบกลุ่มโมเดลใหม่<', $one, $page.' เลือกแท็บแล้วต้องไม่เห็นกลุ่มอื่นในตาราง');

            // ตัวเลขบนแท็บเป็นของทุกกลุ่มเสมอ — ไม่ใช่เหลือ 0 เพราะกรองก่อนนับ
            $this->assertMatchesRegularExpression('~<span class="dg-code">NM</span>\s*<span class="dg-n">1</span>~', $one, $page);
            $this->assertMatchesRegularExpression('~<span class="dg-code">MS</span>\s*<span class="dg-n">1</span>~', $one, $page);

            // ตัวกรองสถานะพาแท็บติดไปด้วย
            $this->assertStringContainsString('<input type="hidden" name="group" value="'.$misc->id.'">', $one, $page);
        }

        // ค่ามั่วใน URL ต้องไม่ทำให้หน้าพัง — ถอยไปแท็บทั้งหมด
        $this->flushSession();
        $this->signIn($admin)->get('/budget/list?group=abc')->assertOk();
    }

    /** ส่งร่างเข้าสายอนุมัติ — ใช้ในเทสต์เรื่องเลขที่ */
    private function sendDraft(AppUser $acc, Invest $invest, AppUser $signer)
    {
        $this->flushSession();

        return $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$signer->employee_code],
        ]);
    }

    public function test_a_submitted_proposal_can_no_longer_be_edited(): void
    {
        /*
          🔴 ส่งแล้วแก้เนื้อหาไม่ได้ — แต่ยังเปิดหน้าเอกสารได้ เพื่อกดลบ (เจ้าของสั่ง 2026-09-03)
             ตัวกันการแก้ไขจริงอยู่ที่ update() ไม่ใช่ที่หน้าจอ
        */
        [$acc, $invest] = $this->submitted();

        $this->signIn($acc)->get('/budget/invest/'.$invest->id.'/edit')->assertOk();
        $this->signIn($acc)->put('/budget/invest/'.$invest->id, $this->payload())->assertForbidden();
    }

    public function test_a_draft_or_pending_document_can_be_deleted(): void
    {
        $acc = $this->user('ACC_D', ['fn' => BudgetAccess::FN_PROPOSE]);
        $this->user('SIGN_D', ['fn' => BudgetAccess::FN_INBOX]);

        // ร่าง — ลบได้
        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $draft = Invest::firstOrFail();
        $this->signIn($acc)->delete('/budget/invest/'.$draft->id)->assertRedirect();
        $this->assertSame(0, Invest::count());

        // ส่งแล้วแต่ยังไม่มีใครตัดสิน — ลบได้
        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $sent = Invest::firstOrFail();
        $this->signIn($acc)->post('/budget/invest/'.$sent->id.'/submit', []);
        $this->assertSame(Invest::PENDING, $sent->fresh()->approval_status);

        $this->signIn($acc)->delete('/budget/invest/'.$sent->id)->assertRedirect();
        $this->assertSame(0, Invest::count());
        // แถวสายอนุมัติต้องหายไปด้วย ไม่เหลือแถวลอยที่ไม่มีเอกสารต้นทาง
        $this->assertSame(0, Approval::where('doc_id', $sent->id)->count());
    }

    public function test_a_decided_document_cannot_be_deleted(): void
    {
        // 🔴 อนุมัติแล้ว / ไม่อนุมัติแล้ว ลบไม่ได้ — ต้องเก็บไว้ตรวจย้อนได้
        [$acc, $invest, , $ceo] = $this->submitted();

        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/approve');
        $this->assertSame(Invest::APPROVED, $invest->fresh()->approval_status);

        $this->signIn($acc)->delete('/budget/invest/'.$invest->id)->assertRedirect();

        $this->assertSame(1, Invest::count());   // ยังอยู่ ไม่ถูกลบ
    }

    public function test_a_partially_signed_pending_document_cannot_be_deleted(): void
    {
        [$invest, $first] = $this->twoApprovers();
        $owner = AppUser::where('employee_code', 'ACC_M')->firstOrFail();

        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve')->assertRedirect();
        $this->assertSame(Invest::PENDING, $invest->fresh()->approval_status);

        $this->signIn($owner)->delete('/budget/invest/'.$invest->id)
            ->assertRedirect()
            ->assertSessionHas('flash_error');

        $this->assertDatabaseHas('budget_invests', ['id' => $invest->id]);
        $this->assertDatabaseHas('budget_approvals', [
            'doc_id' => $invest->id,
            'employee_code' => $first->employee_code,
            'status' => Approval::DONE,
        ]);
    }

    // ── สายอนุมัติ ──────────────────────────────────────────────────

    /**
     * เลือกบทบาทรายคนได้ — อนุมัติ (ต้องเซ็น) หรือ แจ้งให้ทราบ (ไม่ต้องเซ็น)
     * เจ้าของสั่ง 2026-09-16
     *
     * 🔴 คุม 3 อย่างคู่กัน — บทบาทตรงคน · ลำดับไม่สลับ · ผู้รับทราบไม่เข้าคิวเซ็น
     */
    public function test_each_person_can_be_set_to_approve_or_notice(): void
    {
        $acc = $this->user('ACC_R', ['fn' => BudgetAccess::FN_PROPOSE]);
        $notice = $this->user('NOTICE_ONE');
        $signer = $this->user('SIGN_R', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload(['approvers' => []]));
        $invest = Invest::firstOrFail();

        // จัดให้ "ผู้รับทราบ" อยู่ลำดับแรก เพื่อพิสูจน์ว่าลำดับที่จัดไว้ไม่ถูกสลับ
        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$notice->employee_code, $signer->employee_code],
            'roles' => ['notice', 'approve'],
        ])->assertRedirect();

        $rows = Approval::where('doc_id', $invest->id)->orderBy('step')->get();

        // ลำดับต้องเป็นตามที่จัด ไม่ใช่ดันผู้รับทราบไปกองไว้ข้างหน้าหรือข้างหลัง
        $this->assertSame(
            [$notice->employee_code, $signer->employee_code],
            $rows->where('is_final', false)->pluck('employee_code')->all(),
        );

        $ack = $rows->firstWhere('employee_code', $notice->employee_code);
        $this->assertSame(Approval::ACK, $ack->action, 'คนที่ตั้งเป็น notice ต้องไม่ใช่ผู้ลงนาม');
        $this->assertSame(Approval::NOTIFIED, $ack->status, 'ผู้รับทราบไม่ต้องรอคิว');
        // 🔴 มีสถานะเดียว "รับทราบ" ไม่มี "รอการรับทราบ" — เอกสารไม่ได้รออะไรจากเขา
        $this->assertSame('รับทราบ', $ack->outcome()['th'], 'ห้ามขึ้นว่า "รออนุมัติ" เพราะเขาไม่ต้องเซ็น');
        $this->assertSame('st-ack', $ack->outcome()['cls'], 'พื้นขาวตัวอักษรดำ ไม่ระบายสีเหมือนผลการตัดสิน');

        $this->assertSame(Approval::APPROVE, $rows->firstWhere('employee_code', $signer->employee_code)->action);

        // ผู้รับทราบกดอนุมัติไม่ได้ — ไม่มีคิวของตัวเองในเอกสาร
        $this->signIn($notice)->post('/budget/doc/'.$invest->id.'/approve');
        $this->assertSame(Approval::NOTIFIED, $ack->refresh()->status);

        /*
          🔴 เซ็นครบเฉพาะ "ฝั่งผู้อนุมัติ" แล้วเอกสารต้องจบทันที
             ต้องไม่ค้างรอผู้รับทราบ ซึ่งเป็นบั๊กที่จะเกิดถ้าตัวนับคนที่ยังไม่เซ็นไปนับแถว ACK ด้วย
             (ชุดเทสต์นี้ปิด CEO ต่อท้ายไว้ใน setUp ผู้อนุมัติจึงมีคนเดียว)
        */
        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/approve');

        $this->assertSame(Invest::APPROVED, $invest->refresh()->approval_status);
    }

    /**
     * เอกสารและเส้นทางแยกผู้อนุมัติกับผู้รับทราบออกจากกัน (เจ้าของสั่ง 2026-09-16)
     *
     * 🔴 ของเดิมรวมอยู่ก้อนเดียว ผู้ใช้แยกไม่ออกว่าใครต้องเซ็นใครแค่รับทราบ
     *    และผู้รับทราบไม่เคยโผล่ในหน้าต่าง "ดูเส้นทาง" เลย เพราะกรองเฉพาะ action = approve
     */
    public function test_the_document_separates_approvers_from_recipients(): void
    {
        $acc = $this->user('ACC_S', ['fn' => BudgetAccess::FN_PROPOSE]);
        $notice = $this->user('NOTICE_S');
        $signer = $this->user('SIGN_S', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload(['approvers' => []]));
        $invest = Invest::firstOrFail();

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$signer->employee_code, $notice->employee_code],
            'roles' => ['approve', 'notice'],
        ])->assertRedirect();

        // ── ตัวเอกสาร: ต้องมีหัวข้อแยก 2 ก้อน ──
        $doc = $this->signIn($signer)->get('/budget/doc/'.$invest->id)->assertOk()->getContent();
        $this->assertStringContainsString('data-i18n="budget.approvers"', $doc);
        $this->assertStringContainsString('data-i18n="budget.recipients"', $doc);

        // ── หน้าต่าง "ดูเส้นทาง": ผู้รับทราบต้องมีตารางของตัวเอง ──
        $rows = $this->routeRows($this->signIn($acc)->get('/budget/invest?group=all')->assertOk()->getContent());

        $ack = array_values(array_filter($rows, fn ($r) => str_contains($r, 'budget.tl.ackGroup')));
        $this->assertCount(1, $ack, 'ผู้รับทราบต้องมีตารางของตัวเองในเส้นทางเอกสาร');
        $this->assertStringContainsString($notice->displayName('th'), $ack[0]);

        // และต้องไม่ไปปนอยู่ในตารางผู้ลงนาม
        $signerRows = implode('', array_filter($rows, fn ($r) => str_contains($r, 'budget.tl.signer')));
        $this->assertStringNotContainsString($notice->displayName('th'), $signerRows);
        $this->assertStringContainsString($signer->displayName('th'), $signerRows);
    }

    /**
     * หน้า "สถานะการดำเนินการ" — คอลัมน์บทบาท + ตัวกรองบทบาท (เจ้าของสั่ง 2026-09-16)
     *
     * 🔴 สถานะของผู้รับทราบต้องเป็น "รับทราบ" ไม่ใช่ยืมสถานะเอกสารมาโชว์ว่า "รออนุมัติ"
     *    ซึ่งอ่านผิดว่าเอกสารค้างอยู่ที่เขา ทั้งที่เขาไม่มีอะไรต้องทำ
     */
    public function test_the_inbox_shows_and_filters_by_role(): void
    {
        $acc = $this->user('ACC_RF', ['fn' => BudgetAccess::FN_PROPOSE]);
        $notice = $this->user('NOTICE_RF', ['fn' => BudgetAccess::FN_INBOX]);
        $signer = $this->user('SIGN_RF', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload(['approvers' => []]));
        $invest = Invest::firstOrFail();
        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$signer->employee_code, $notice->employee_code],
            'roles' => ['approve', 'notice'],
        ])->assertRedirect();

        // ── ผู้รับทราบ: บทบาท notice · สถานะ "รับทราบ" ──
        $this->flushSession();
        $mine = $this->signIn($notice)->get('/budget/approval')->assertOk()->viewData('rows')->firstOrFail();
        $this->assertSame('notice', $mine->my_role);
        $this->assertSame('ACKNOWLEDGED', $mine->my_status, 'ห้ามยืมสถานะเอกสารมาโชว์ว่า "รออนุมัติ"');

        // ── ผู้ลงนาม: บทบาท approve · สถานะรออนุมัติตามปกติ ──
        $this->flushSession();
        $his = $this->signIn($signer)->get('/budget/approval')->assertOk()->viewData('rows')->firstOrFail();
        $this->assertSame('approve', $his->my_role);
        $this->assertSame(Invest::PENDING, $his->my_status);

        // ── ตัวกรองบทบาท ──
        $this->flushSession();
        $this->assertCount(1, $this->signIn($notice)->get('/budget/approval?role=notice')->assertOk()->viewData('rows'));
        $this->assertCount(0, $this->signIn($notice)->get('/budget/approval?role=approve')->assertOk()->viewData('rows'));

        // 🔴 ตัวเลขสรุปข้างบนต้องเป็นของทั้งกล่องเสมอ ไม่เดินตามตัวกรอง
        //    ไม่งั้นเลือกกรองแล้วช่องอื่นเป็น 0 จนกดกลับไปดูไม่ได้ (กติกาเดิม 2026-09-10)
        $page = $this->signIn($notice)->get('/budget/approval?role=approve')->assertOk();
        $this->assertSame(1, $page->viewData('summary')['total']);
    }

    /** 🔴 ตั้งเป็น "แจ้งให้ทราบ" กันหมดจนไม่เหลือคนเซ็น = ส่งไม่ได้ */
    public function test_a_document_needs_at_least_one_real_approver(): void
    {
        $acc = $this->user('ACC_N', ['fn' => BudgetAccess::FN_PROPOSE]);
        $notice = $this->user('NOTICE_TWO');

        $this->signIn($acc)->post('/budget/invest', $this->payload(['approvers' => []]));
        $invest = Invest::firstOrFail();

        // (ชุดเทสต์นี้ปิด CEO ต่อท้ายไว้ใน setUp แล้ว ไม่งั้นระบบจะต่อท้ายให้จนมีผู้อนุมัติเสมอ)
        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$notice->employee_code],
            'roles' => ['notice'],
        ])->assertSessionHas('flash_error');

        $this->assertSame(Invest::DRAFT, $invest->refresh()->approval_status);
    }

    public function test_the_approvers_come_from_the_form_in_the_order_chosen(): void
    {
        /*
          🔴 กลับด้านจากกติกาเดิม (เจ้าของสั่ง 2026-09-09)
             ผู้ขอเลือกผู้อนุมัติเองและจัดลำดับเอง ระบบไม่หยิบจากสิทธิ์ให้อีก
             ลำดับที่ส่งมาคือลำดับการเซ็น ห้ามเรียงใหม่
        */
        $acc = $this->user('ACC_P', ['fn' => BudgetAccess::FN_PROPOSE]);
        $first = $this->user('SIGN_P', ['fn' => BudgetAccess::FN_INBOX]);
        $second = $this->user('STRANGER');   // ไม่มีสิทธิ์อะไรเลย ก็ถูกเลือกได้

        $this->signIn($acc)->post('/budget/invest', $this->payload(['approvers' => []]));
        $invest = Invest::firstOrFail();

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$second->employee_code, $first->employee_code],
        ])->assertRedirect();

        $signers = Approval::where('doc_id', $invest->id)
            ->where('action', Approval::APPROVE)
            ->orderBy('step')
            ->pluck('employee_code')
            ->all();

        // ลำดับต้องตรงกับที่ส่งมาเป๊ะ ไม่ใช่เรียงตามรหัสพนักงาน
        $this->assertSame([$second->employee_code, $first->employee_code], $signers);
    }

    public function test_a_document_cannot_be_sent_when_no_approver_is_configured(): void
    {
        // ไม่มีใครถือสิทธิ์ "อนุมัติ Invest" = ส่งไม่ได้ ต้องไปตั้งก่อน
        // 🔴 ปล่อยให้ "ทุกคนเป็นผู้อนุมัติ" ไม่ได้ ไม่งั้นใครก็เซ็นอนุมัติงบได้
        $acc = $this->user('ACC_N', ['fn' => BudgetAccess::FN_PROPOSE]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [])->assertRedirect();

        $this->assertSame(Invest::DRAFT, $invest->fresh()->approval_status);
        $this->assertSame(0, Approval::where('doc_id', $invest->id)->count());
    }

    // ── จำนวนเงิน (เจ้าของสั่ง 2026-09-04) ──────────────────────────

    public function test_an_amount_typed_with_commas_is_still_saved(): void
    {
        /*
          ช่องกรอกเงินโชว์ลูกน้ำให้ระหว่างพิมพ์ ปกติ JS ส่งตัวเลขล้วนมาให้อยู่แล้ว
          🔴 แต่ฝั่งเซิร์ฟเวอร์ต้องรับค่าที่มีลูกน้ำได้ด้วย เผื่อ JS ไม่ทำงาน
        */
        $acc = $this->user('ACC_M1', ['fn' => BudgetAccess::FN_PROPOSE]);

        $this->signIn($acc)->post('/budget/invest', $this->payload(['amount' => '180,000.50']))
            ->assertRedirect();

        $this->assertSame('180000.50', Invest::firstOrFail()->amount);
    }

    public function test_the_amount_field_shows_thousand_separators(): void
    {
        // เปิดร่างเดิมขึ้นมาแก้ ต้องเห็น 180,000.00 ไม่ใช่ 180000
        $acc = $this->user('ACC_M2', ['fn' => BudgetAccess::FN_PROPOSE]);
        $this->signIn($acc)->post('/budget/invest', $this->payload(['amount' => 180000]));

        $invest = Invest::firstOrFail();

        $this->signIn($acc)->get('/budget/invest/'.$invest->id.'/edit')
            ->assertOk()
            ->assertSee('value="180,000.00"', false)
            ->assertSee('data-money-input', false);
    }

    // ── วันที่เสนอ / ผู้เสนอ (เจ้าของสั่ง 2026-09-04) ────────────────

    public function test_the_proposal_records_who_requested_it(): void
    {
        /*
          🔴 ถอดช่อง "วันที่เสนอ" ออกแล้ว (เจ้าของสั่ง 2026-09-09)
             ใช้วันที่ส่งเรื่องแทน ไม่ต้องให้คนกรอกเอง
        */
        $acc = $this->user('ACC_P1', ['fn' => BudgetAccess::FN_PROPOSE]);

        $this->signIn($acc)->post('/budget/invest', $this->payload())->assertRedirect();

        $invest = Invest::firstOrFail();

        $this->assertSame($acc->employee_code, $invest->proposer_code);
        $this->assertSame($acc->employee_code, $invest->created_by);
    }

    public function test_the_proposer_can_be_someone_other_than_the_person_typing(): void
    {
        /*
          ฝ่ายบัญชีกรอกแทนคนที่มาขอได้ — เอกสารต้องบอกว่าใครมาขอ
          แต่ created_by ต้องยังเป็นคนที่กรอกจริง ไม่งั้นตรวจย้อนไม่ได้ว่าใครทำ
        */
        $acc = $this->user('ACC_P2', ['fn' => BudgetAccess::FN_PROPOSE]);
        $acc->update(['signature' => 'data:image/png;base64,ACC']);
        $boss = $this->user('BOSS_P2');
        $boss->update(['signature' => 'data:image/png;base64,BOSS']);

        $this->signIn($acc)->post('/budget/invest', $this->payload([
            'proposer_code' => $boss->employee_code,
        ]))->assertRedirect();

        $invest = Invest::firstOrFail();

        $this->assertSame($boss->employee_code, $invest->proposer_code);
        $this->assertSame($boss->displayName('th'), $invest->proposer_name);
        $this->assertSame($acc->employee_code, $invest->created_by);

        // ส่งได้ตามปกติ — ผู้ขอไม่ต้องลงนาม (เจ้าของสั่ง 2026-09-10)
        $signer = $this->user('SIGN_P2', ['fn' => BudgetAccess::FN_INBOX]);
        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$signer->employee_code],
        ])->assertRedirect();

        $this->assertSame(Invest::PENDING, $invest->fresh()->approval_status);
    }

    public function test_the_proposer_name_comes_from_the_server_not_the_form(): void
    {
        // ยิงชื่อปลอมมากับฟอร์มต้องไม่ติด — ไม่งั้นเอกสารโกหกได้ว่าใครเป็นคนเสนอ
        $acc = $this->user('ACC_P3', ['fn' => BudgetAccess::FN_PROPOSE]);
        $boss = $this->user('BOSS_P3');

        $this->signIn($acc)->post('/budget/invest', $this->payload([
            'proposer_code' => $boss->employee_code,
            'proposer_name' => 'ชื่อปลอมที่ยิงมาเอง',
        ]))->assertRedirect();

        $this->assertSame($boss->displayName('th'), Invest::firstOrFail()->proposer_name);
    }

    public function test_a_named_proposer_can_open_the_document_even_if_someone_else_typed_it(): void
    {
        $acc = $this->user('ACC_P4', ['fn' => BudgetAccess::FN_PROPOSE]);
        $boss = $this->user('BOSS_P4');
        $signer = $this->user('SIGN_P4', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload(['proposer_code' => $boss->employee_code]));
        $invest = Invest::firstOrFail();
        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', []);

        // ผู้เสนอไม่ได้อยู่ในสายอนุมัติ แต่เป็นเจ้าของเรื่อง จึงต้องเปิดดูได้
        $this->signIn($boss)->get('/budget/doc/'.$invest->id)->assertOk();

        // คนนอกที่ไม่เกี่ยวข้องยังต้องเข้าไม่ได้เหมือนเดิม
        $this->signIn($this->user('OUT_P4'))->get('/budget/doc/'.$invest->id)->assertForbidden();
        $this->assertSame($signer->employee_code, $signer->employee_code);
    }

    public function test_old_documents_without_a_proposer_fall_back_to_the_creator(): void
    {
        // เอกสารก่อน 2026-09-04 ไม่มีคอลัมน์นี้ ต้องไม่โชว์ช่องว่างในช่องลงชื่อ
        $invest = Invest::create([
            'doc_no' => 'INV-2569-000099',
            'fiscal_year' => 2569,
            'dept_code' => 'D01',
            'title' => 'เอกสารเก่า',
            'amount' => 1000,
            'approval_status' => Invest::DRAFT,
            'created_by' => 'OLD01',
            'created_by_name' => 'คนเก่า',
        ]);

        $this->assertSame('OLD01', $invest->proposerCode());
        $this->assertSame('คนเก่า', $invest->proposerName());
        $this->assertNotNull($invest->proposedDate());
    }

    public function test_bulk_approve_signs_every_selected_document(): void
    {
        // อนุมัติหลายฉบับพร้อมกันจากหน้าตาราง (เจ้าของสั่ง 2026-09-03)
        $acc = $this->user('ACC_B', ['fn' => BudgetAccess::FN_PROPOSE]);
        $ceo = $this->user('CEO_B', ['fn' => BudgetAccess::FN_INBOX]);
        $ceo->update(['signature' => 'data:image/png;base64,CCCC']);

        $ids = [];

        for ($i = 0; $i < 2; $i++) {
            $this->signIn($acc)->post('/budget/invest', $this->payload());
            $doc = Invest::orderByDesc('id')->firstOrFail();
            $this->signIn($acc)->post('/budget/invest/'.$doc->id.'/submit', []);
            $ids[] = $doc->id;
        }

        $this->signIn($ceo)->post('/budget/approval/bulk', [
            'action' => 'approve',
            'docs' => $ids,
            'notes' => [$ids[0] => 'ผ่าน'],
        ])->assertRedirect();

        foreach ($ids as $id) {
            $this->assertSame(Invest::APPROVED, Invest::find($id)->approval_status);
            // ลายเซ็นต้องถูกประทับให้ทุกฉบับ ไม่ใช่แค่ฉบับแรก
            $this->assertSame('data:image/png;base64,CCCC', $this->stepOf(Invest::find($id), $ceo)->signature);
        }

        $this->assertSame(2, Budget::count());
        $this->assertSame('ผ่าน', $this->stepOf(Invest::find($ids[0]), $ceo)->comment);
    }

    public function test_bulk_reject_skips_documents_without_a_reason(): void
    {
        // 🔴 ปฏิเสธต้องมีเหตุผลรายฉบับ — ฉบับที่ไม่ได้กรอกต้องไม่ถูกแตะ
        $acc = $this->user('ACC_R', ['fn' => BudgetAccess::FN_PROPOSE]);
        $ceo = $this->user('CEO_R', ['fn' => BudgetAccess::FN_INBOX]);

        $ids = [];

        for ($i = 0; $i < 2; $i++) {
            $this->signIn($acc)->post('/budget/invest', $this->payload());
            $doc = Invest::orderByDesc('id')->firstOrFail();
            $this->signIn($acc)->post('/budget/invest/'.$doc->id.'/submit', []);
            $ids[] = $doc->id;
        }

        // ผู้อนุมัติคนเดียวในสาย = ผู้ลงนามปิดท้าย เพื่อให้ผลของการไม่อนุมัติจบทั้งใบ
        Approval::whereIn('doc_id', $ids)->update(['is_final' => true]);

        $this->signIn($ceo)->post('/budget/approval/bulk', [
            'action' => 'reject',
            'docs' => $ids,
            'notes' => [$ids[0] => 'งบไม่พอ'],   // ฉบับที่ 2 ไม่ได้กรอก
        ])->assertRedirect();

        $this->assertSame(Invest::REJECTED, Invest::find($ids[0])->approval_status);
        $this->assertSame(Invest::PENDING, Invest::find($ids[1])->approval_status);
    }

    /**
     * คนกลางทางไม่อนุมัติ — เอกสารยังเดินต่อถึง CEO (เจ้าของสั่ง 2026-09-16)
     *
     * 🔴 คุม 4 อย่างคู่กัน เพราะพลาดข้อเดียวเอกสารจะค้างเงียบๆ
     *    เดินต่อได้ · คนถัดไปกดได้จริง · ข้อทักท้วงยังอยู่ให้ CEO เห็น · CEO เป็นคนตัดสินจบ
     */
    public function test_a_rejection_midway_still_reaches_the_final_approver(): void
    {
        [$invest, $first, $second] = $this->twoApprovers();
        $this->stepOf($invest, $second)->update(['is_final' => true]);

        // คนที่ 1 ไม่อนุมัติ — เอกสารต้องยังเดินอยู่ ไม่จบ
        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/reject', ['reason' => 'ราคาสูงเกิน']);

        $this->assertSame(Invest::PENDING, $invest->refresh()->approval_status, 'คนกลางทางไม่อนุมัติต้องไม่จบทั้งใบ');
        $this->assertSame(Approval::REJECTED, $this->stepOf($invest, $first)->refresh()->status);
        $this->assertSame('ราคาสูงเกิน', $this->stepOf($invest, $first)->refresh()->comment, 'หมายเหตุต้องอยู่ให้ CEO เห็น');

        // 🔴 คนถัดไปต้องกดได้จริง ไม่ติดค้างเพราะขั้นก่อนหน้าไม่ใช่ "อนุมัติแล้ว"
        $this->signIn($second)->get('/budget/doc/'.$invest->id)->assertOk();
        $this->signIn($second)->post('/budget/doc/'.$invest->id.'/approve');

        // CEO ตัดสินคนสุดท้าย — อนุมัติแล้วเอกสารจบเป็น "อนุมัติ" และเกิดก้อนงบ
        $this->assertSame(Invest::APPROVED, $invest->refresh()->approval_status);
        $this->assertSame(1, Budget::count());

        // ข้อทักท้วงของคนที่ 1 ต้องยังอยู่ในประวัติ ไม่ถูกลบทิ้งตอนเอกสารผ่าน
        $this->assertSame(Approval::REJECTED, $this->stepOf($invest, $first)->refresh()->status);
    }

    /** 🔴 CEO ไม่อนุมัติ = จบทั้งใบ ผู้ขอต้องเปิดใบใหม่ */
    public function test_only_the_final_approver_can_end_the_document(): void
    {
        [$invest, $first, $second] = $this->twoApprovers();
        $this->stepOf($invest, $second)->update(['is_final' => true]);

        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve');
        $this->signIn($second)->post('/budget/doc/'.$invest->id.'/reject', ['reason' => 'ไม่เห็นด้วย']);

        $invest->refresh();
        $this->assertSame(Invest::REJECTED, $invest->approval_status);
        $this->assertSame('ไม่เห็นด้วย', $invest->reject_reason);
        $this->assertSame(0, Budget::count());
    }

    public function test_every_approver_must_sign_before_the_budget_is_created(): void
    {
        // ยังไม่ confirm ว่าบริษัทมีผู้ลงนามกี่คน — ระบบจึงรองรับหลายคน ทุกคนต้องเซ็น
        [$invest, $first, $second] = $this->twoApprovers();

        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve');
        $this->assertSame(Invest::PENDING, $invest->fresh()->approval_status);
        $this->assertSame(0, Budget::count());

        $this->signIn($second)->post('/budget/doc/'.$invest->id.'/approve');
        $this->assertSame(Invest::APPROVED, $invest->fresh()->approval_status);
        $this->assertSame(1, Budget::count());
    }

    public function test_approvers_must_sign_in_order(): void
    {
        /*
          🔴 กลับด้านจากกติกาเดิม (เจ้าของสั่ง 2026-09-09 — ยกมาจากระบบ Memo)
             ถึงคิวเมื่อ "เป็นลำดับแรก" หรือ "คนก่อนหน้าเซ็นผ่านแล้ว" เท่านั้น
        */
        [$invest, $first, $second] = $this->twoApprovers();

        // คนที่ 2 แซงคิวไม่ได้
        $this->signIn($second)->post('/budget/doc/'.$invest->id.'/approve');
        $this->assertSame(Approval::WAITING, $this->stepOf($invest, $second)->status);

        // คนที่ 1 เซ็นก่อน แล้วคนที่ 2 ถึงจะเซ็นได้
        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve');
        $this->signIn($second)->post('/budget/doc/'.$invest->id.'/approve');

        $this->assertSame(Approval::DONE, $this->stepOf($invest, $second)->status);
    }

    public function test_the_signature_is_stamped_onto_the_document(): void
    {
        [, $invest, , $ceo] = $this->submitted();

        $ceo->update(['signature' => 'data:image/png;base64,AAAA']);
        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/approve');

        // 🔴 เก็บเป็นสำเนา ณ ตอนเซ็น ไม่ join สดทีหลัง — คนลาออกแล้วเอกสารเก่าต้องยังพิมพ์ได้
        $this->assertSame('data:image/png;base64,AAAA', $this->stepOf($invest, $ceo)->signature);
    }

    /*
      🔴 ไม่อนุมัติก็ต้องประทับลายเซ็น (เจ้าของถาม 2026-09-16)
         คนกลางทางไม่อนุมัติแล้วเอกสารเดินต่อไปหา CEO พร้อมข้อทักท้วง
         ข้อทักท้วงที่ไปถึงโต๊ะ CEO ต้องรู้ว่าใครทักและเซ็นรับรองไว้จริง
    */
    public function test_the_signature_is_stamped_when_rejecting_too(): void
    {
        [, $invest, , $ceo] = $this->submitted();

        $ceo->update(['signature' => 'data:image/png;base64,NOPE']);
        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/reject', ['reason' => 'ราคาสูงเกิน']);

        $step = $this->stepOf($invest, $ceo);

        $this->assertSame(Approval::REJECTED, $step->status);
        $this->assertSame('data:image/png;base64,NOPE', $step->signature);
    }

    // ลายเซ็นเป็นเงื่อนไขของ "การตัดสิน" ไม่ใช่ของ "การอนุมัติ" — ไม่มีลายเซ็นก็ตีกลับไม่ได้
    public function test_an_approver_without_a_signature_cannot_reject(): void
    {
        [, $invest, , $ceo] = $this->submitted();

        $ceo->update(['signature' => null]);
        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/reject', ['reason' => 'ราคาสูงเกิน'])->assertRedirect();

        $this->assertSame(Invest::PENDING, $invest->fresh()->approval_status);
        $this->assertSame(Approval::WAITING, $this->stepOf($invest, $ceo)->status);
    }

    public function test_approval_creates_the_budget_and_the_opening_ledger_entry(): void
    {
        [, $invest, , $ceo] = $this->submitted();

        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/approve');

        $budget = Budget::firstOrFail();

        // 🔴 เลขงบใช้โค้ดกลุ่มเดียวกับข้อเสนอ (เจ้าของสั่ง 2026-09-17)
        $this->assertSame('BGT-NM-2569-000001', $budget->doc_no);
        $this->assertSame($this->docGroup->id, $budget->group_id);
        $this->assertSame($invest->id, $budget->invest_id);
        // 🔴 เซ็นครบแล้วเข้าคิวรอบัญชีคีย์เข้า ERP (เจ้าของสั่ง 2026-09-10)
        $this->assertSame(Budget::PENDING_REGISTER, $budget->budget_status);

        // ต้องมีรายการตั้งงบใน ledger เท่ากับวงเงินที่ขอ
        $opening = BudgetTransaction::where('budget_id', $budget->id)
            ->where('type', BudgetTransaction::INITIAL)
            ->firstOrFail();
        $this->assertEquals(90000, (float) $opening->amount);
    }

    public function test_rejecting_ends_the_document(): void
    {
        /*
          🔴 เฉพาะ "ผู้ลงนามปิดท้าย" เท่านั้นที่ไม่อนุมัติแล้วจบทั้งใบ (เจ้าของสั่ง 2026-09-16)
             คนกลางทางไม่อนุมัติ เอกสารยังเดินต่อ — มีเทสต์ของตัวเองแยกไว้ข้างล่าง
             ตัวช่วย submitted() ตั้งผู้อนุมัติไว้คนเดียว จึงต้องติดธงปิดท้ายให้เขาก่อน
        */
        [$acc, $invest, , $ceo] = $this->submitted();
        $this->stepOf($invest, $ceo)->update(['is_final' => true]);

        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/reject', ['reason' => 'งบไม่พอ']);

        $invest->refresh();
        $this->assertSame(Invest::REJECTED, $invest->approval_status);
        $this->assertSame('งบไม่พอ', $invest->reject_reason);
        $this->assertSame(0, Budget::count());

        // ประวัติว่าใครตีกลับเพราะอะไร ต้องยังอยู่
        $this->assertSame(
            Approval::REJECTED,
            $this->stepOf($invest, $ceo)->status,
        );

        // 🔴 แก้ไม่ได้ และส่งซ้ำไม่ได้ — ต้องเปิดใบใหม่เท่านั้น
        $this->signIn($acc)->post('/budget/invest/'.$invest->id, $this->payload(['title' => 'แก้ใหม่']));
        $this->assertNotSame('แก้ใหม่', $invest->fresh()->title);

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$ceo->employee_code],
        ]);
        $this->assertSame(Invest::REJECTED, $invest->fresh()->approval_status);
    }

    public function test_a_rejected_document_cannot_be_deleted(): void
    {
        // ลบได้ = ประวัติว่าใครตีกลับเพราะอะไรหายไปด้วย จึงต้องกันไว้
        [$acc, $invest, , $ceo] = $this->submitted();

        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/reject', ['reason' => 'งบไม่พอ']);

        $this->signIn($acc)->delete('/budget/invest/'.$invest->id);

        // ยังอยู่ครบ พร้อมประวัติการตีกลับ
        $this->assertNotNull($invest->fresh());
        $this->assertSame(1, Approval::where('doc_id', $invest->id)->where('status', Approval::REJECTED)->count());
    }

    public function test_people_outside_the_flow_cannot_open_the_document(): void
    {
        [, $invest] = $this->submitted();

        $this->signIn($this->user('X999'))->get('/budget/doc/'.$invest->id)->assertForbidden();
    }

    // ── ยอดเงิน ─────────────────────────────────────────────────────

    public function test_the_four_figures_come_from_the_ledger(): void
    {
        $budget = $this->approvedBudget();
        $ledger = app(BudgetLedger::class);

        $ledger->record($budget, BudgetTransaction::RESERVE, 20000);
        $ledger->record($budget, BudgetTransaction::ACTUAL, 15000);
        $ledger->record($budget, BudgetTransaction::RELEASE, -5000);

        $t = $ledger->totals($budget->fresh());

        $this->assertEquals(90000, $t['budget']);
        $this->assertEquals(15000, $t['reserved']);   // 20000 - 5000
        $this->assertEquals(15000, $t['actual']);
        $this->assertEquals(60000, $t['available']);  // 90000 - 15000 - 15000
    }

    public function test_changing_budget_status_does_not_touch_approval_status(): void
    {
        $budget = $this->approvedBudget();

        // 🔴 ถอดสิทธิ์ย่อยปรับสถานะออกแล้ว — เห็นหน้าได้ = ปรับสถานะได้ (เจ้าของสั่ง 2026-09-09)
        $boss = $this->user('STATUS_OK', ['fn' => BudgetAccess::FN_BUDGETS]);

        $this->signIn($boss)->post('/budget/list/'.$budget->id.'/status', [
            'budget_status' => Budget::REGISTERED,
            'status_note' => 'คีย์เข้า ERP แล้ว',
        ])->assertRedirect();

        $budget->refresh();

        $this->assertSame(Budget::REGISTERED, $budget->budget_status);
        $this->assertSame(Invest::APPROVED, $budget->approval_status);   // 🔴 ต้องไม่เปลี่ยน
        $this->assertTrue($budget->isRegistered());
    }

    public function test_only_the_listed_people_can_change_budget_status(): void
    {
        /*
          🔴 สิทธิ์ย่อยนี้ใช้กติกากลับกับที่อื่น: **ไม่ระบุใคร = ไม่มีใครทำได้**
             เพราะการเปลี่ยนสถานะงบกระทบเงินจริง ปล่อยให้ทุกคนทำไม่ได้
        */
        $budget = $this->approvedBudget();
        $outsider = $this->user('NO_STATUS');

        $this->signIn($outsider)->post('/budget/list/'.$budget->id.'/status', [
            'budget_status' => Budget::REGISTERED,
        ])->assertForbidden();

        $this->assertSame(Budget::PENDING_REGISTER, $budget->fresh()->budget_status);
    }

    /*
      หน้าพิมพ์ / บันทึก PDF ของงบที่อนุมัติแล้ว (เจ้าของสั่ง 2026-09-10)

      🔴 หน้านี้ไม่มีเมนูซ้าย เปิดแท็บใหม่เห็นแต่ตัวกระดาษ
         แต่ห้ามเป็นทางลัดข้ามสิทธิ์ — ต้องใช้ด่านตรวจเดียวกับหน้าปกติ
    */
    public function test_the_print_page_shows_only_the_paper(): void
    {
        $budget = $this->approvedBudget();
        $boss = $this->user('PRT_OK', ['fn' => BudgetAccess::FN_BUDGETS]);

        $this->flushSession();

        $html = $this->signIn($boss)->get('/budget/list/'.$budget->id.'/print')->assertOk()->getContent();

        // ตัวกระดาษกับปุ่มทั้ง 2 ต้องมา
        $this->assertStringContainsString('class="paper"', $html);
        $this->assertStringContainsString('budget.print', $html);
        $this->assertStringContainsString('budget.downloadPdf', $html);
        $this->assertStringContainsString($budget->doc_no, $html);

        // 🔴 ต้องไม่มีเมนูซ้าย/แถบบนของ layouts.app ติดมาด้วย
        $this->assertStringNotContainsString('class="sidebar"', $html);
    }

    /*
      🐛 บั๊กจริง 2026-09-16 (เจ้าของแจ้ง "ดาวน์โหลด PDF เหมือนมันจะค้างไปเลย")

         ไฟล์มาจริง แต่จออนิเมชัน "กำลังโหลด" ค้าง เพราะมันปิดตัวเองด้วย pageshow
         ซึ่งเกิดเมื่อ "เปลี่ยนหน้าจริง" — ลิงก์ที่ตอบเป็นไฟล์ไม่เปลี่ยนหน้า

         ปุ่มดาวน์โหลดจึงต้องมี 2 อย่างเสมอ ทั้ง 2 หน้า
           download      ตัวจับลิงก์ของจอโหลดข้ามให้ (และเป็นทางสำรองเมื่อ JS ไม่ทำงาน)
           data-download ตัวดึงไฟล์ที่เด้งหน้าต่างขีดถูกตอนโหลดเสร็จ
    */
    /*
      🔴 หน้าต่างซ้อน (modal) ย้ายจาก access/partials/picker-assets ไปไว้ตรงกลาง 2026-09-16

         หน้าที่เคย include picker-assets ต้องยังได้ครบเหมือนเดิม
         และต้อง **มาครั้งเดียว** เพราะ layout กลางก็ include ตัวเดียวกัน (กัน @once พัง)
    */
    public function test_the_modal_reaches_every_page_exactly_once(): void
    {
        $budget = $this->approvedBudget();
        $boss = $this->user('MODAL_OK', ['fn' => BudgetAccess::FN_BUDGETS]);

        $this->flushSession();

        $html = $this->signIn($boss)->get('/budget/list')->assertOk()->getContent();

        $this->assertStringContainsString('.modal-wrap {', $html, 'หน้าตาของ modal หายไปจากหน้าที่เคยมี');
        $this->assertStringContainsString("closest('[data-modal-open]')", $html, 'ตัวรับการกด modal หายไป');
        $this->assertSame(1, substr_count($html, "closest('[data-modal-open]')"), 'modal ถูกใส่ซ้ำ 2 รอบ');
    }

    public function test_the_download_button_never_strands_the_loading_veil(): void
    {
        $budget = $this->approvedBudget();
        $boss = $this->user('DL_OK', ['fn' => BudgetAccess::FN_BUDGETS]);

        $this->flushSession();

        foreach (['/print', ''] as $suffix) {
            $page = $suffix ?: '/budget/list/{id}';
            $html = $this->signIn($boss)->get('/budget/list/'.$budget->id.$suffix)->assertOk()->getContent();

            // หยิบเฉพาะแท็ก <a> ที่เป็นปุ่มดาวน์โหลด PDF ออกมาตรวจ
            $found = preg_match('~<a\b[^>]*budget\.downloadPdf~s', $html, $m)
                ? $m[0]
                : '';

            $this->assertNotSame('', $found, 'หน้า "'.$page.'" ไม่มีปุ่มดาวน์โหลด PDF');
            $this->assertStringContainsString('download', $found, 'ปุ่มดาวน์โหลดของ "'.$page.'" ขาด download — จอโหลดจะค้าง');
            $this->assertStringContainsString('data-download', $found, 'ปุ่มดาวน์โหลดของ "'.$page.'" ขาด data-download — ไม่เด้งหน้าต่างขีดถูก');
        }
    }

    /*
      🔴 กด "ดาวน์โหลด PDF" ต้องได้ไฟล์ PDF จริง (เจ้าของสั่ง 2026-09-10)
         เดิมเด้งหน้าต่างพิมพ์ให้ผู้ใช้เลือกปลายทางเอง ซึ่งเจ้าของไม่เอา

      เทสต์นี้แปลงไฟล์จริงด้วยเบราว์เซอร์ในเครื่อง — เครื่องไหนไม่มีเบราว์เซอร์ให้ข้ามไป
      (ระบบยังใช้งานได้ แค่ปุ่มดาวน์โหลดจะไม่ขึ้น เหลือปุ่มพิมพ์)
    */
    public function test_downloading_gives_a_real_pdf_file(): void
    {
        if (! PdfPrinter::available()) {
            $this->markTestSkipped('เครื่องนี้ไม่มีเบราว์เซอร์สำหรับแปลง PDF');
        }

        $budget = $this->approvedBudget();
        $boss = $this->user('PDF_OK', ['fn' => BudgetAccess::FN_BUDGETS]);

        $this->flushSession();

        $res = $this->signIn($boss)->get('/budget/list/'.$budget->id.'/pdf');

        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');

        $body = $res->streamedContent();

        // ต้องเป็นไฟล์ PDF จริง ไม่ใช่หน้า HTML
        $this->assertStringStartsWith('%PDF-', $body);

        /*
          🔴 รูปต้องติดมาด้วย (บั๊กจริง 2026-09-10: ไฟล์ที่ได้ไม่มีรูปเลย)
             เพราะเบราว์เซอร์เบื้องหลังวิ่งกลับมาโหลดรูปจากเว็บตัวเอง แล้วค้าง
             ตอนนี้ฝังรูปลงหน้าไปก่อนแปลง จึงไม่ต้องพึ่งเว็บอีก
        */
        $this->assertStringContainsString('/Image', $body, 'ไฟล์ PDF ไม่มีรูปติดมาด้วย');

        // 🔴 ฟอนต์ต้องถูกฝังมาด้วย ไม่งั้นภาษาไทยเพี้ยนตอนเปิดในเครื่องอื่น
        $this->assertTrue(
            str_contains($body, '/FontFile') || str_contains($body, '/Type0'),
            'ไฟล์ PDF ไม่ได้ฝังฟอนต์มาด้วย',
        );
    }

    public function test_the_pdf_download_is_blocked_for_outsiders(): void
    {
        $budget = $this->approvedBudget();

        $this->flushSession();

        $this->signIn($this->user('PDF_NO'))
            ->get('/budget/list/'.$budget->id.'/pdf')
            ->assertForbidden();
    }

    public function test_the_print_page_is_blocked_for_outsiders(): void
    {
        $budget = $this->approvedBudget();

        $this->flushSession();

        $this->signIn($this->user('PRT_NO'))
            ->get('/budget/list/'.$budget->id.'/print')
            ->assertForbidden();
    }

    /*
      หน้า "รับทราบ / อนุมัติ Invest" — คอลัมน์ตามที่เจ้าของสั่ง 2026-09-10
        ตัดออก : ผู้ขอ · บทบาท
        เพิ่ม  : ปีงบ · วันที่ดำเนินการ

      🔴 "วันที่ดำเนินการ" = เวลาที่ "คนดูหน้านี้" ทำอะไรกับใบนั้น ไม่ใช่เวลาของเอกสาร
         ยังไม่ได้ลงนาม = ขีดกลาง · ลงนามแล้ว = เวลาที่ลงนาม
    */
    public function test_the_approval_table_shows_my_own_action_time(): void
    {
        [, $invest, , $signer] = $this->submitted();

        $html = $this->signIn($signer)->get('/budget/approval')->assertOk()->getContent();

        $this->assertStringContainsString('budget.year', $html);
        $this->assertStringContainsString('budget.tl.actedAt', $html);

        // ยังไม่ได้ลงนาม — ต้องไม่เอาเวลาของเอกสารมาแสดงแทน
        $this->assertStringNotContainsString($invest->submitted_at->format('d/m/Y H:i'), $this->rowsOf($html));

        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/approve')->assertRedirect();

        $this->flushSession();

        $html = $this->signIn($signer)->get('/budget/approval')->assertOk()->getContent();
        $acted = $this->stepOf($invest, $signer)->acted_at;

        $this->assertStringContainsString($acted->format('d/m/Y H:i'), $this->rowsOf($html));

        // คอลัมน์ที่เจ้าของสั่งตัดออก ต้องไม่กลับมา
        $this->assertStringNotContainsString('budget.role', $html);
        $this->assertStringNotContainsString('role-tag', $html);
    }

    /*
      หน้า "เสนอ Invest" — คอลัมน์เดียวกัน แต่คนดูคือผู้ขอ
      สิ่งที่เขาทำกับเอกสารคือ "กดส่งเรื่อง" · ร่างที่ยังไม่ส่ง = ขีดกลาง
    */
    public function test_the_invest_table_shows_the_submit_time(): void
    {
        $acc = $this->user('ACC_ACT', ['fn' => BudgetAccess::FN_PROPOSE]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        // ยังเป็นร่าง — ยังไม่ได้ส่ง จึงยังไม่มีวันที่ดำเนินการ
        $html = $this->signIn($acc)->get('/budget/invest?group=all')->assertOk()->getContent();
        $this->assertNull($invest->submitted_at);
        $this->assertStringContainsString('budget.tl.actedAt', $html);

        $signer = $this->user('SIGN_ACT', ['fn' => BudgetAccess::FN_INBOX]);
        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$signer->employee_code],
        ])->assertRedirect();

        $this->flushSession();

        $html = $this->signIn($acc)->get('/budget/invest?group=all')->assertOk()->getContent();

        $this->assertStringContainsString(
            $invest->fresh()->submitted_at->format('d/m/Y H:i'),
            $this->rowsOf($html),
        );
    }

    /** เนื้อในตารางอย่างเดียว — กันไปเจอวันเวลาในหน้าต่าง "ดูเส้นทาง" แล้วเข้าใจผิด */
    private function rowsOf(string $html): string
    {
        return preg_match('~<tbody[^>]*>(.*?)</tbody>~s', $html, $m) ? $m[1] : '';
    }

    /*
      🔴 กด "อนุมัติ" = ระบบประทับลายเซ็นให้เอง (เจ้าของสั่ง 2026-09-10)
         ไม่มีปุ่มให้กดประทับเองแล้ว แต่ลายเซ็นต้องลงเอกสารเหมือนเดิม
    */
    public function test_approving_stamps_the_signature_without_a_stamp_button(): void
    {
        [, $invest, , $signer] = $this->submitted();

        $page = $this->signIn($signer)->get('/budget/doc/'.$invest->id)->assertOk()->getContent();

        $this->assertStringNotContainsString('id="sign-btn"', $page);
        $this->assertStringNotContainsString('budget.stampSign', $page);

        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/approve')->assertRedirect();

        $step = Approval::where('doc_id', $invest->id)
            ->where('employee_code', $signer->employee_code)
            ->firstOrFail();

        $this->assertSame(Approval::DONE, $step->status);
        $this->assertNotEmpty($step->signature);
    }

    /*
      ตาราง "ดูเส้นทาง" (เจ้าของสั่ง 2026-09-10)
        ข้อ 3 — ช่องหมายเหตุของผู้ขอต้องเทาเหมือนช่องลายเซ็น
                🐛 บั๊กจริง: <td> เขียน class= คู่กับ @class อันหลังถูก HTML ทิ้งเงียบๆ
        ข้อ 6 — ช่องหมายเหตุของผู้ลงนามระบายเขียวเมื่ออนุมัติ · แดงเมื่อไม่อนุมัติ
    */
    public function test_the_route_table_colours_the_remark_cell(): void
    {
        [$acc, $invest, , $signer] = $this->submitted();

        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/approve', ['comment' => 'ผ่านครับ']);

        $this->flushSession();

        $html = $this->signIn($acc)->get('/budget/invest?group=all')->assertOk()->getContent();

        // ผู้ขอ — เทาทั้งช่องลายเซ็นและช่องหมายเหตุ
        $this->assertStringContainsString('class="txt-left is-na"', $html);

        // ผู้ลงนามที่อนุมัติพร้อมความเห็น — เขียวเต็มช่อง
        $this->assertStringContainsString('class="txt-left note-ok"', $html);
    }

    public function test_rejecting_paints_the_remark_cell_red(): void
    {
        [$acc, $invest, , $signer] = $this->submitted();

        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/reject', ['reason' => 'งบสูงเกินไป']);

        $this->flushSession();

        $html = $this->signIn($acc)->get('/budget/invest?group=all')->assertOk()->getContent();

        $this->assertStringContainsString('class="txt-left note-no"', $html);
    }

    /*
      ข้อ 9 — /budget/list ตัดคอลัมน์ "ผู้ขอ" กับ "อ้างอิง Invest" ออก
              เลขที่ Invest ย้ายไปเป็นสัญลักษณ์เล็กๆ ข้างเลขที่งบ กดแล้วเปิดหน้าต่าง
    */
    public function test_the_budget_list_links_the_source_invest_in_a_modal(): void
    {
        $budget = $this->approvedBudget();
        $boss = $this->user('REF_OK', ['fn' => BudgetAccess::FN_BUDGETS]);

        $this->flushSession();

        $html = $this->signIn($boss)->get('/budget/list')->assertOk()->getContent();

        // สัญลักษณ์ + หน้าต่างของงบก้อนนี้
        $this->assertStringContainsString('data-modal-open="inv-'.$budget->id.'"', $html);
        $this->assertStringContainsString('data-modal="inv-'.$budget->id.'"', $html);
        $this->assertStringContainsString($budget->invest->doc_no, $html);

        // คอลัมน์ที่เจ้าของสั่งตัดออก ต้องไม่เหลือ
        // 🔴 เจาะจงที่ attribute ของหัวคอลัมน์ — คำแปลของทั้งระบบอยู่ในทุกหน้าอยู่แล้ว
        $this->assertStringNotContainsString('data-i18n="budget.proposer"', $html);
        $this->assertStringNotContainsString('data-modal-open="pp-', $html);
    }

    /*
      🔴 คอลัมน์ "สถานะ" ของหน้า /budget/approval = สถานะของ "คนที่เปิดดู" (เจ้าของสั่ง 2026-09-10)

      หน้านี้คือกล่องงานของแต่ละคน สิ่งที่เขาอยากรู้คือ "ฉันเซ็นไปหรือยัง"
      ไม่ใช่สถานะรวมของเอกสารที่ยังรอคนอื่นอยู่ (ภาพรวมดูได้ที่ "สถานะทั้งหมด")
    */
    public function test_the_status_column_shows_my_own_state(): void
    {
        [$invest, $first, $second] = $this->twoApprovers();

        // ยังไม่เซ็น — ของฉันคือ "รออนุมัติ"
        $this->assertSame('st-pending', $this->statusCellOf($first));

        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve')->assertRedirect();

        // 🔴 เอกสารยังรอคนที่ 2 อยู่ แต่ของ "ฉัน" ต้องขึ้นว่าอนุมัติแล้ว
        $this->assertSame(Invest::PENDING, $invest->fresh()->approval_status);
        $this->assertSame('st-approved', $this->statusCellOf($first));

        // ส่วนคนที่ 2 ที่ยังไม่ได้เซ็น ต้องยังเห็นของตัวเองเป็น "รออนุมัติ"
        $this->assertSame('st-pending', $this->statusCellOf($second));
    }

    /**
     * ชนิดของป้ายสถานะในแถวของตาราง /budget/approval ของคนคนนี้
     *
     * 🔴 ต้องอ่านเฉพาะ <tbody id="rows"> — คำว่า "อนุมัติแล้ว" มีอยู่ในพจนานุกรมคำแปล
     *    ของทุกหน้า และในหน้าต่าง "ดูเส้นทาง" ด้วย ค้นทั้งหน้าจะเจอตลอดไม่ว่าจริงหรือไม่
     */
    private function statusCellOf(AppUser $user): string
    {
        $this->flushSession();

        $html = $this->signIn($user)->get('/budget/approval')->assertOk()->getContent();

        $this->assertSame(1, preg_match('~<tbody id="rows">(.*?)</tbody>~s', $html, $body));
        $this->assertSame(1, preg_match('~<span class="st (st-[a-z]+)"~', $body[1], $cell));

        return $cell[1];
    }

    /*
      🔴 หน้า "ลงทะเบียน" = คิวงานของบัญชี (เจ้าของสั่ง 2026-09-10 · DECISIONS 37)
         เซ็นครบ → "รอลงทะเบียน" → บัญชีคีย์เข้า ERP แล้วติ๊ก → "ลงทะเบียนแล้ว"
    */
    public function test_a_new_budget_waits_for_the_accountant_to_register_it(): void
    {
        $budget = $this->approvedBudget();

        $this->assertSame(Budget::PENDING_REGISTER, $budget->budget_status);
        $this->assertFalse($budget->isRegistered());

        $acc = $this->user('ACC_REG', ['fn' => BudgetAccess::FN_BUDGETS]);

        $this->flushSession();

        $this->signIn($acc)->post('/budget/list/'.$budget->id.'/status', [
            'budget_status' => Budget::REGISTERED,
        ])->assertRedirect();

        $budget->refresh();

        $this->assertTrue($budget->isRegistered());
        // 🔴 สถานะการอนุมัติต้องไม่ถูกแตะ (กฎเดิมใน DECISIONS 4.3)
        $this->assertSame(Invest::APPROVED, $budget->approval_status);

        // ติ๊กออกได้ ถ้าคีย์ผิดใบ
        $this->signIn($acc)->post('/budget/list/'.$budget->id.'/status', [
            'budget_status' => Budget::PENDING_REGISTER,
        ])->assertRedirect();

        $this->assertFalse($budget->fresh()->isRegistered());
    }

    /** สถานะเก่าที่เลิกใช้แล้ว ต้องส่งเข้ามาไม่ได้อีก */
    public function test_the_old_spend_statuses_are_gone(): void
    {
        $budget = $this->approvedBudget();
        $acc = $this->user('ACC_OLD', ['fn' => BudgetAccess::FN_BUDGETS]);

        $this->flushSession();

        $this->signIn($acc)->post('/budget/list/'.$budget->id.'/status', [
            'budget_status' => 'ACTIVE',
        ])->assertSessionHasErrors('budget_status');

        $this->assertSame(Budget::PENDING_REGISTER, $budget->fresh()->budget_status);
    }

    /*
      🔴 ตารางเส้นทางมีขั้นสุดท้าย "ลงทะเบียน" ต่อจาก CEO (เจ้าของสั่ง 2026-09-10)
         รายชื่อมาจากสิทธิ์ "ลงทะเบียน" ที่ /access/modules
         สถานะเดินตามการติ๊กในหน้ารายการงบทันที
    */
    public function test_the_route_table_ends_with_the_registration_step(): void
    {
        $budget = $this->approvedBudget();
        // ต้องมีสิทธิ์ดูประวัติด้วย เพราะตาราง "ดูเส้นทาง" อยู่ในหน้านั้น
        $acc = $this->user('ACC_TL', ['fn' => [BudgetAccess::FN_BUDGETS, 'fn.budget.history']]);

        $this->flushSession();

        // ยังไม่ติ๊ก — ขั้นสุดท้ายต้องขึ้น "รอลงทะเบียน" และบอกว่ารอใครอยู่
        $html = $this->signIn($acc)->get('/budget/history')->assertOk()->getContent();
        $rows = $this->routeRows($html);
        $last = end($rows);

        $this->assertStringContainsString('budget.tl.registrar', $last);
        $this->assertStringContainsString('รอลงทะเบียน', $last);
        $this->assertStringContainsString($acc->displayName('th'), $last);

        // ติ๊กแล้ว — เปลี่ยนเป็น "ลงทะเบียนแล้ว" พร้อมวันเวลาที่ติ๊ก
        $this->signIn($acc)->post('/budget/list/'.$budget->id.'/status', [
            'budget_status' => Budget::REGISTERED,
        ])->assertRedirect();

        $this->flushSession();

        $html = $this->signIn($acc)->get('/budget/history')->assertOk()->getContent();
        $rows = $this->routeRows($html);
        $last = end($rows);

        $this->assertStringContainsString('ลงทะเบียนแล้ว', $last);
        $this->assertStringContainsString(
            $budget->fresh()->registered_at->format('d/m/Y H:i'),
            $last,
        );

        // 🔴 ไม่มีลายเซ็นและหมายเหตุ — ช่องต้องเป็นเทา ไม่ใช่ขีดกลาง
        $this->assertSame(2, substr_count($last, 'is-na'));
    }

    /**
     * แถวทั้งหมดของตาราง "ดูเส้นทาง" ใบแรกในหน้า
     *
     * @return array<int,string>
     */
    private function routeRows(string $html): array
    {
        /*
          🔴 เส้นทางเอกสารแยกเป็นหลายตารางตามบทบาทแล้ว (เจ้าของสั่ง 2026-09-16)
             ต้องกวาดทุกตาราง ไม่ใช่ตารางแรกตารางเดียว ไม่งั้นเทสต์เห็นแค่กลุ่มผู้ขอ
             และต้องพา "หัวข้อกลุ่ม" ติดมากับแถวด้วย เพราะชื่อบทบาทย้ายไปอยู่ตรงนั้นแล้ว
        */
        $this->assertMatchesRegularExpression('~<table class="tbl route-tbl">~', $html);
        preg_match_all('~<p class="route-group"[^>]*>.*?</table>~s', $html, $blocks);

        $rows = [];
        foreach ($blocks[0] as $block) {
            preg_match('~data-i18n="([^"]+)"~', $block, $group);
            preg_match_all('~<tr>(.*?)</tr>~s', $block, $found);
            foreach (array_slice($found[1], 1) as $row) {
                $rows[] = ($group[1] ?? '').$row;
            }
        }

        return $rows;
    }

    public function test_anyone_with_the_permission_sees_every_budget(): void
    {
        /*
          🔴 เจ้าของสั่ง 2026-09-03: เลิกกรองตามแผนก
             เดิมคนนอกแผนกได้สิทธิ์แล้วยังเจอจอว่าง — สับสนว่าสิทธิ์ไม่ทำงาน
             ใครควรเห็นบ้างกำหนดที่ /access/modules หัวข้อ "ลงทะเบียน" ที่เดียว
        */
        $this->approvedBudget();                       // งบของแผนก D01
        $outsider = $this->user('OUT1', [
            'dept_code' => 'D99',
            'fn' => BudgetAccess::FN_BUDGETS,
        ]);

        // ล้าง flash ที่ค้างจากขั้นตอนอนุมัติ ไม่งั้นเลขงบติดมากับข้อความแจ้งผลแล้วเทสต์ฟ้องผิดจุด
        $this->flushSession();

        $html = $this->signIn($outsider)->get('/budget/list')->assertOk()->getContent();

        $this->assertStringContainsString('BGT-NM-2569-000001', $html);
    }

    public function test_a_user_without_the_permission_cannot_open_the_budget_list(): void
    {
        $this->approvedBudget();

        // ระบุตัวคนในหัวข้อ "ลงทะเบียน" แล้ว คนนอกรายชื่อต้องเข้าไม่ได้
        $this->user('SEE_BGT', ['fn' => BudgetAccess::FN_BUDGETS]);
        $outsider = $this->user('OUT2');

        $this->flushSession();
        $this->signIn($outsider)->get('/budget/list')->assertForbidden();
    }

    // ── ตัวช่วย ─────────────────────────────────────────────────────

    /**
     * เอกสารที่ส่งแล้ว มีผู้อนุมัติ 2 คน — ใช้ทดสอบว่าต้องเซ็นครบทุกคน
     *
     * @return array{0:Invest,1:AppUser,2:AppUser}
     */
    private function twoApprovers(): array
    {
        $acc = $this->user('ACC_M', ['fn' => BudgetAccess::FN_PROPOSE]);
        $first = $this->user('SIGN_A', ['fn' => BudgetAccess::FN_INBOX]);
        $second = $this->user('SIGN_B', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$first->employee_code, $second->employee_code],
        ])->assertRedirect();

        return [$invest->refresh(), $first, $second];
    }

    /** ขั้นในสายอนุมัติของคนคนนี้ */
    private function stepOf(Invest $invest, AppUser $user): Approval
    {
        return Approval::where('doc_id', $invest->id)
            ->where('employee_code', $user->employee_code)
            ->firstOrFail();
    }

    /** @return array{0:AppUser,1:Invest,2:AppUser,3:AppUser} */
    private function submitted(): array
    {
        $acc = $this->user('ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        // คนนอกสายเอกสาร — ใช้ทดสอบว่าคนที่ไม่ได้ถูกเลือกเข้าถึงอะไรไม่ได้
        $outsider = $this->user('REV', ['fn' => BudgetAccess::FN_INBOX]);
        $ceo = $this->user('CEO', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        /*
          🔴 สำเนาเรียน = ผู้อนุมัติตามลำดับแล้ว (เจ้าของสั่ง 2026-09-09)
             ไม่มีบทบาท "ผู้รับทราบ" ที่ไม่ต้องเซ็นอีกต่อไป — ทุกคนในสายคือผู้ลงนาม
             ตัวช่วยนี้ตั้งสายให้มีผู้อนุมัติ "คนเดียว" เพราะเทสต์ส่วนใหญ่สนใจปลายทาง
             (เซ็นครบแล้วต้องเกิดงบ) ส่วนกติกาเรื่องลำดับมีเทสต์ของตัวเองแยกไว้
        */
        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$ceo->employee_code],
        ])->assertRedirect();

        $invest->refresh();
        $this->assertSame(Invest::PENDING, $invest->approval_status);
        $this->assertSame(1, Approval::where('doc_id', $invest->id)->count());

        return [$acc, $invest, $outsider, $ceo];
    }

    private function approvedBudget(): Budget
    {
        [, $invest, , $ceo] = $this->submitted();

        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/approve');

        return Budget::firstOrFail();
    }

    /*
      หน้าฟอร์มเสนอ Invest เป็นหน้าที่ถูกแก้บ่อยที่สุด แต่เดิมไม่มีเทสต์เปิดมันเลย
      รอบ 2026-09-09 ถอด "หมวดค่าใช้จ่าย" กับ "เหตุผลความจำเป็น" ออก
      ถ้าเผลอทิ้งตัวแปรที่ไม่มีแล้วไว้ในหน้า จะพังตอนผู้ใช้เปิดจริงเท่านั้น เทสต์ชุดอื่นจับไม่ได้
    */
    public function test_the_proposal_form_opens_without_the_removed_fields(): void
    {
        $acc = $this->user('ACC_FORM', ['fn' => BudgetAccess::FN_PROPOSE]);

        // 🔴 เข้าหน้ากรอกต้องมาพร้อมกลุ่มที่เลือก (เจ้าของสั่ง 2026-09-17)
        $page = $this->signIn($acc)->get('/budget/invest/create?group='.$this->docGroup->id);

        $page->assertOk();
        $page->assertSee('budget.investName', escape: false);

        // ช่องที่ถอดออกแล้วต้องไม่โผล่กลับมา
        $page->assertDontSee('expense_category_id', escape: false);
        $page->assertDontSee('name="reason"', escape: false);
        $page->assertDontSee('cat-select', escape: false);
        $page->assertDontSee('name="proposed_at"', escape: false);

        /*
          🔴 เคยมีข้อความหมุดของสคริปต์แก้ไฟล์หลุดลงหน้าจริงมาแล้ว (2026-09-09)
             ผู้ใช้เห็นคำว่า OLD_END_MARKER กลางหน้า — เทสต์ตัวนี้กันไม่ให้เกิดซ้ำ
        */
        $page->assertDontSee('OLD_END_MARKER', escape: false);

        /*
          🔴 เลือกผู้อนุมัติได้จากพนักงานทุกคน (เจ้าของสั่ง 2026-09-09)
             จึงต้องค้นที่เซิร์ฟเวอร์ ห้ามฝังรายชื่อลงหน้า — บริษัทมี 1,494 บัญชี
             (กฎโปรเจค: เคยยัดรหัสพนักงานลง attribute แล้วได้ attribute ยาว 9 KB)
        */
        $page->assertSee('apvSearchUrl', escape: false);
        $page->assertDontSee('var apvOptions', escape: false);
    }

    /**
     * 🐛 บั๊กจริง 2026-09-09: เปิดหน้าเอกสารแล้ว CEO หายทั้งในสำเนาเรียนและช่องลงชื่อ
     *
     * สาเหตุ: ระบบต่อ CEO ให้ตอน "กดส่ง" เท่านั้น ร่างจึงยังไม่มีแถวของเขาในฐาน
     *         หน้าเอกสารอ่านจากฐานตรงๆ เลยไม่เห็น ทั้งที่ตอนกรอกฟอร์มเห็นอยู่
     */
    public function test_the_final_approver_shows_on_the_document_even_before_sending(): void
    {
        $acc = $this->user('ACC_CEO_DOC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $picked = $this->user('SIGN_CEO_DOC', ['fn' => BudgetAccess::FN_INBOX]);
        $ceo = $this->user('BIGBOSS');

        config(['bms.final_approver' => $ceo->employee_code]);
        FinalApprover::forget();

        $this->signIn($acc)->post('/budget/invest', $this->payload(['approvers' => [$picked->employee_code]]));
        $invest = Invest::firstOrFail();

        $html = $this->signIn($acc)->get('/budget/invest/'.$invest->id.'/edit')->assertOk()->getContent();

        // ต้องเห็นทั้งคนที่เลือกเอง และ CEO ที่ระบบต่อท้ายให้
        $this->assertStringContainsString($picked->displayName('th'), $html);
        $this->assertStringContainsString($ceo->displayName('th'), $html);

        // 🔴 ผู้ขอไม่มีช่องลงชื่อแล้ว (2026-09-10) — เหลือผู้อนุมัติ 1 + CEO 1 = 2
        $this->assertStringContainsString('--sign-cols: 2', $html);

        /*
          🔴 CEO ต้องถูกล็อกไว้เป็น "แถวจริงในฐาน" ตั้งแต่บันทึกร่าง (เจ้าของสั่ง 2026-09-09)
             ไม่ใช่ให้แต่ละหน้าเติมเอง — ไม่งั้นหน้าไหนลืมเติมก็หายไปเงียบๆ (เคยเกิดมาแล้ว)
             และต้องอยู่ "ท้ายสุด" เสมอ
        */
        $rows = Approval::where('doc_id', $invest->id)->orderBy('step')->get();

        $this->assertSame(
            [$picked->employee_code, $ceo->employee_code],
            $rows->pluck('employee_code')->all(),
        );
        $this->assertFalse((bool) $rows->first()->is_final);
        $this->assertTrue((bool) $rows->last()->is_final);
    }

    /**
     * เปลี่ยนตัว CEO ระหว่างที่ร่างค้างอยู่ — ร่างเก่าต้องไม่ค้างชื่อคนเดิม
     *
     * 🔴 นี่คือเหตุผลที่ต้องมีคอลัมน์ is_final ไม่ใช่แค่ดูว่าเป็นแถวสุดท้าย
     *    ถ้าเดาจากลำดับ จะแยกไม่ออกว่าแถวท้ายเป็น CEO หรือเป็นคนที่ผู้ขอเลือกเอง
     */
    public function test_changing_the_final_approver_replaces_the_old_one_on_send(): void
    {
        $acc = $this->user('ACC_SWAP', ['fn' => BudgetAccess::FN_PROPOSE]);
        $picked = $this->user('SIGN_SWAP', ['fn' => BudgetAccess::FN_INBOX]);
        $oldCeo = $this->user('CEO_OLD');
        $newCeo = $this->user('CEO_NEW');

        // บันทึกร่างตอนที่ CEO ยังเป็นคนเก่า
        config(['bms.final_approver' => $oldCeo->employee_code]);
        FinalApprover::forget();

        $this->signIn($acc)->post('/budget/invest', $this->payload(['approvers' => [$picked->employee_code]]));
        $invest = Invest::firstOrFail();

        $this->assertTrue(
            Approval::where('doc_id', $invest->id)->where('employee_code', $oldCeo->employee_code)->exists(),
        );

        // เปลี่ยนตัว CEO แล้วค่อยกดส่ง
        config(['bms.final_approver' => $newCeo->employee_code]);
        FinalApprover::forget();

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit')->assertRedirect();

        $rows = Approval::where('doc_id', $invest->id)->orderBy('step')->get();

        // CEO คนเก่าต้องหายไปทั้งแถว ไม่ใช่ตกไปเป็นผู้อนุมัติกลางสาย
        $this->assertSame(
            [$picked->employee_code, $newCeo->employee_code],
            $rows->pluck('employee_code')->all(),
        );
        $this->assertSame(Invest::PENDING, $invest->fresh()->approval_status);

        /*
          🐛 บั๊กจริง 2026-09-09: ตอนบันทึกร่างติดธง is_final ไว้ แต่พอกดส่งจริง
             ตัวเขียนคนละตัว (InvestFlow::addStep) ไม่ได้ตั้งธง ทำให้ธงหายไป
             ผลคือป้าย "ผู้ลงนามปิดท้าย" หาย และตีกลับส่งใหม่จะแยกแถวปิดท้ายเก่าไม่ออก
        */
        $this->assertTrue((bool) $rows->last()->is_final, 'แถว CEO ต้องยังติดธงปิดท้ายหลังกดส่ง');
        $this->assertFalse((bool) $rows->first()->is_final);
    }

    /**
     * เส้นทางเอกสารเป็น "ตาราง" แบบระบบ Memo (เจ้าของสั่งรื้อใหม่ 2026-09-09)
     *
     * แถวแรกคือผู้ขอเสมอ แล้วต่อด้วยผู้ลงนามตามลำดับ และ CEO ปิดท้าย
     */
    public function test_the_document_route_is_a_table_with_every_person(): void
    {
        $acc = $this->user('ACC_ROUTE', ['fn' => BudgetAccess::FN_PROPOSE]);
        $picked = $this->user('SIGN_ROUTE', ['fn' => BudgetAccess::FN_INBOX]);
        $ceo = $this->user('CEO_ROUTE');

        config(['bms.final_approver' => $ceo->employee_code]);
        FinalApprover::forget();

        $this->signIn($acc)->post('/budget/invest', $this->payload(['approvers' => [$picked->employee_code]]));
        $invest = Invest::firstOrFail();

        $html = $this->signIn($acc)->get('/budget/invest?group=all')->assertOk()->getContent();

        /*
          หัวตาราง — 🔴 ไม่มีคอลัมน์ "บทบาท" แล้ว (เจ้าของสั่งแยกตารางตามบทบาท 2026-09-16)
             ชื่อบทบาทย้ายไปเป็นหัวข้อของแต่ละตารางแทน
        */
        foreach ([
            'budget.tl.person', 'budget.dept', 'budget.tl.position',
            'budget.status', 'budget.tl.signature', 'budget.tl.actedAt', 'budget.tl.note',
        ] as $key) {
            $this->assertStringContainsString('data-i18n="'.$key.'"', $html, $key.' หายไปจากหัวตาราง');
        }

        // แถวผู้ขอ + ผู้ลงนาม + CEO ปิดท้าย
        $this->assertStringContainsString('data-i18n="budget.requester"', $html);
        $this->assertStringContainsString('data-i18n="budget.tl.signer"', $html);
        $this->assertStringContainsString('data-i18n="budget.tl.finalSigner"', $html);
        $this->assertStringContainsString($picked->displayName('th'), $html);
        $this->assertStringContainsString($ceo->displayName('th'), $html);

        // แผนกต้องมาจากสำเนาที่เก็บไว้ในเอกสาร ไม่ใช่ join สด
        $this->assertSame(
            'D01',
            Approval::where('doc_id', $invest->id)->where('employee_code', $picked->employee_code)->value('department'),
        );

        /*
          🐛 บั๊กจริง 2026-09-09: ตำแหน่ง/แผนกของ "ผู้ขอ" ไม่ขึ้นในตารางเส้นทาง
             สาเหตุ collect($rows) บน Paginator ได้ metadata ของหน้ากระดาษ ไม่ใช่แถวเอกสาร
             รายชื่อผู้ขอจึงว่าง (บทเรียนเดิมของโปรเจค — DECISIONS ข้อ 26)
        */
        $acc->update(['position' => 'Programmer Staff']);

        $html = $this->signIn($acc)->get('/budget/invest?group=all')->assertOk()->getContent();
        $this->assertStringContainsString('Programmer Staff', $html, 'ตำแหน่งของผู้ขอต้องขึ้นในตารางเส้นทาง');
    }

    /**
     * ผู้ลงนามทุกคนเห็นเอกสาร "พร้อมกัน" แต่กดได้เฉพาะคนที่ถึงคิว
     *
     * 🔴 เจ้าของสั่งกลับกติกา 2026-09-16 (ของเดิม 2026-09-09 คือเห็นตามคิว)
     *    เหตุผล: ผู้ลงนามลำดับหลังต้องเตรียมตัวล่วงหน้าได้ แต่ห้ามลัดคิวเซ็น
     *
     * 🔴 เทสต์นี้ต้องคุม 2 อย่างคู่กันเสมอ — "เห็น" กับ "กดไม่ได้"
     *    ถ้าคุมแค่ว่าเห็น วันหนึ่งมีคนเผลอปลดตัวกันฝั่งเซิร์ฟเวอร์ออกจะไม่มีใครจับได้
     */
    public function test_every_signer_sees_the_document_at_once_but_only_the_current_one_can_sign(): void
    {
        [$invest, $first, $second] = $this->twoApprovers();

        // ── ลำดับ 2 ยังไม่ถึงคิว แต่ต้องเห็นใบนี้ในกล่องของตัวเองแล้ว ──
        $this->flushSession();
        $before = $this->signIn($second)->get('/budget/approval')->assertOk()->getContent();
        $this->assertStringContainsString($invest->doc_no, $before, 'ลำดับ 2 ต้องเห็นเอกสารตั้งแต่ยังไม่ถึงคิว');

        // และต้องรู้ว่ายังกดไม่ได้ ไม่ใช่ขึ้นเหมือนใบที่เซ็นไปแล้ว
        $this->assertStringContainsString('budget.appr.waitTurn', $before, 'ต้องบอกผู้ใช้ว่ายังไม่ถึงคิว');

        // เปิดตัวเอกสารได้ (อ่านได้) — แต่จะไม่มีปุ่มอนุมัติเพราะ myStep เป็น null
        $this->flushSession();
        $this->signIn($second)->get('/budget/doc/'.$invest->id)->assertOk();

        // ── ลัดคิวไม่ได้: ยิง POST ตรงก็ต้องไม่ถูกบันทึก ──
        $this->flushSession();
        $this->signIn($second)->post('/budget/doc/'.$invest->id.'/approve');
        $this->assertSame(
            Approval::WAITING,
            $this->stepOf($invest, $second)->refresh()->status,
            'ลำดับ 2 ต้องเซ็นไม่ได้จนกว่าลำดับ 1 จะเซ็นเสร็จ'
        );

        // ── ลำดับ 1 ถึงคิว เห็นและเซ็นได้ ──
        $this->flushSession();
        $mine = $this->signIn($first)->get('/budget/approval')->assertOk()->getContent();
        $this->assertStringContainsString($invest->doc_no, $mine);

        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve');
        $this->assertSame(Approval::DONE, $this->stepOf($invest, $first)->refresh()->status);

        // ── พอลำดับ 1 เซ็นแล้ว ลำดับ 2 ถึงจะเซ็นได้จริง ──
        $this->flushSession();
        $this->signIn($second)->post('/budget/doc/'.$invest->id.'/approve');
        $this->assertSame(Approval::DONE, $this->stepOf($invest, $second)->refresh()->status);

        // และลำดับ 1 ที่เซ็นไปแล้วยังย้อนดูได้
        $this->flushSession();
        $done = $this->signIn($first)->get('/budget/approval')->assertOk()->getContent();
        $this->assertStringContainsString($invest->doc_no, $done);
    }

    /**
     * เซ็นครบทุกคนแล้ว เอกสารเข้าคิวหน้า "ลงทะเบียน"
     * คนที่ดูแลหน้านั้นต้องได้รับแจ้งเตือน (เจ้าของสั่ง 2026-09-10)
     */
    public function test_registrars_are_notified_when_the_budget_enters_the_queue(): void
    {
        $registrar = $this->user('REG_ONE', ['fn' => BudgetAccess::FN_BUDGETS]);
        [$invest, $first, $second] = $this->twoApprovers();

        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve');

        // ยังเซ็นไม่ครบ = ยังไม่เกิดก้อนงบ ต้องไม่แจ้งใครล่วงหน้า
        $this->assertSame(0, Notification::where('event', 'budget_to_register')->count());

        $this->signIn($second)->post('/budget/doc/'.$invest->id.'/approve');

        $budget = Budget::firstOrFail();
        $note = Notification::where('event', 'budget_to_register')->get();

        $this->assertSame([$registrar->employee_code], $note->pluck('employee_code')->all());

        $card = $note->first();

        // 🔴 ใบนี้เป็นเรื่องของก้อนงบ เลขที่กับลิงก์ต้องเป็นของงบ ไม่ใช่ของใบ Invest
        $this->assertSame($budget->doc_no, $card->doc_no);
        $this->assertSame('budget.list.show', $card->route_name);
        $this->assertSame((string) $budget->id, $card->route_param);

        // จัดกลุ่มอยู่ใต้หัวข้อ "ลงทะเบียน" ในแผงกระดิ่งและเลขแดงของเมนู
        $this->assertSame(BudgetAccess::FN_BUDGETS, $card->function_key);

        // ยังตามกลับไปหาใบต้นทางได้
        $this->assertSame($invest->doc_no, $card->note_th);
    }

    /**
     * ติ๊ก "ลงทะเบียนแล้ว" = จัดการเรื่องนี้เสร็จ เลขแดงของใบนั้นต้องหาย
     * (เจ้าของแจ้ง 2026-09-10 ว่าแจ้งเตือนค้างอยู่ทั้งที่ติ๊กไปแล้ว)
     */
    public function test_ticking_registered_clears_the_notification(): void
    {
        $registrar = $this->user('REG_TICK', ['fn' => BudgetAccess::FN_BUDGETS]);
        [$invest, $first, $second] = $this->twoApprovers();

        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve');
        $this->signIn($second)->post('/budget/doc/'.$invest->id.'/approve');

        $budget = Budget::firstOrFail();

        $unread = fn () => Notification::where('employee_code', $registrar->employee_code)
            ->whereNull('read_at')->count();

        $this->assertSame(1, $unread(), 'เซ็นครบแล้วต้องมีแจ้งเตือนถึงผู้ลงทะเบียน');

        // เลขแดงต้องขึ้นที่คอลัมน์ "ลำดับ" ในตารางด้วย ไม่ต้องเปิดกระดิ่งก่อน
        $this->flushSession();
        $list = $this->signIn($registrar)->get('/budget/list')->assertOk()->getContent();

        preg_match(
            '~<span class="row-badge" data-badge-doc="'.preg_quote($budget->doc_no, '~').'[^"]*"(.*?)</span>~s',
            $list,
            $badge
        );

        $this->assertNotEmpty($badge, 'ต้องมีเลขแดงประจำแถวในหน้าลงทะเบียน');
        $this->assertStringNotContainsString('hidden', $badge[1], 'มีแจ้งเตือนค้างอยู่ เลขแดงต้องไม่ถูกซ่อน');

        /*
          🔴 เปิดดูเอกสารเฉยๆ ต้องไม่นับว่าอ่านแล้ว (เจ้าของสั่ง 2026-09-10)
             ต่างจากหน้าอื่นในระบบ เพราะแจ้งเตือนของหน้านี้แปลว่า "ยังมีงานค้าง"
             เผลอกด "ดูเอกสาร" แล้วเลขแดงหายทั้งที่ยังไม่ได้ลงทะเบียน = เสียงาน
        */
        $this->flushSession();
        $this->signIn($registrar)->get('/budget/list/'.$budget->id)->assertOk();
        $this->assertSame(1, $unread(), 'เปิดดูเอกสารแล้วแจ้งเตือนต้องยังอยู่');

        // ติ๊กลงทะเบียนจากตาราง — แจ้งเตือนต้องหายทันที ไม่ต้องเข้าไปเปิดเอกสารก่อน
        $this->flushSession();
        $this->signIn($registrar)->post('/budget/list/'.$budget->id.'/status', [
            'budget_status' => Budget::REGISTERED,
        ])->assertRedirect();

        $this->assertSame(Budget::REGISTERED, $budget->refresh()->budget_status);
        $this->assertSame(0, $unread(), 'ติ๊กแล้วแจ้งเตือนต้องหาย');
    }

    /**
     * 🔴 ตั้งหัวข้อ "ลงทะเบียน" เป็นทุกคน ต้องไม่ยิงแจ้งเตือนทั้งบริษัท
     *    แจ้งเตือนแปลว่า "งานที่ต้องลงมือทำ" ไม่ใช่ประกาศ และพันกว่าแถวต่อใบทำให้กดอนุมัติค้าง
     */
    public function test_registration_notice_is_never_broadcast_to_everyone(): void
    {
        FunctionUser::create([
            'module_id' => BudgetAccess::MODULE,
            'function_key' => BudgetAccess::FN_BUDGETS,
            'employee_code' => AccessService::EVERYONE,
        ]);

        [$invest, $first, $second] = $this->twoApprovers();

        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve');
        $this->signIn($second)->post('/budget/doc/'.$invest->id.'/approve');

        $this->assertNotNull(Budget::first(), 'ต้องสร้างงบสำเร็จตามปกติ');
        $this->assertSame(0, Notification::where('event', 'budget_to_register')->count());
    }

    /**
     * 🔴 บันทึกร่างแล้วเรื่องต้องยังไม่ถึงผู้อนุมัติ (เจ้าของแจ้งบั๊ก 2026-09-10)
     *
     * แถวสายอนุมัติถูกเขียนตั้งแต่บันทึกร่าง เพื่อให้ผู้ขอเลือกค้างไว้ได้
     * แต่ "มีชื่ออยู่ในสายเอกสาร" ยังไม่ได้แปลว่าเรื่องถึงเขาแล้ว — ต้องกด "ส่งรายงาน" ก่อน
     */
    public function test_a_saved_draft_never_reaches_the_first_approver(): void
    {
        $acc = $this->user('ACC_DRAFT', ['fn' => BudgetAccess::FN_PROPOSE]);
        $first = $this->user('SIGN_DRAFT', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload())->assertRedirect();
        $invest = Invest::firstOrFail();

        $this->assertSame(Invest::DRAFT, $invest->approval_status);
        // แถวสายอนุมัติมีตั้งแต่ตอนร่าง — นี่คือของที่ถูกต้อง ไม่ใช่บั๊ก
        $this->assertNotNull($this->stepOf($invest, $first));

        // แต่กล่องงานของผู้อนุมัติต้องยังไม่มีใบนี้
        $this->flushSession();
        $inbox = $this->signIn($first)->get('/budget/approval')->assertOk()->getContent();
        // 🔴 ร่างยังไม่มีเลขที่ (ออกตอนกดส่ง) จึงตรวจด้วยชื่อเอกสาร
        $this->assertNull($invest->doc_no);
        $this->assertStringNotContainsString($invest->title, $inbox);

        // และพิมพ์ URL ตรงก็ต้องเปิดไม่ได้ ไม่ใช่กันแค่ที่ตาราง
        $this->flushSession();
        $this->signIn($first)->get('/budget/doc/'.$invest->id)->assertForbidden();

        // ยังไม่มีแจ้งเตือนถึงใครทั้งนั้น
        $this->assertSame(0, Notification::count());

        // กด "ส่งรายงาน" แล้วถึงจะถึงเขา
        $this->flushSession();
        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$first->employee_code],
        ])->assertRedirect();

        $this->flushSession();
        $after = $this->signIn($first)->get('/budget/approval')->assertOk()->getContent();
        $invest->refresh();
        $this->assertSame('INV-NM-2569-000001', $invest->doc_no);
        $this->assertStringContainsString($invest->doc_no, $after);

        $this->flushSession();
        $this->signIn($first)->get('/budget/doc/'.$invest->id)->assertOk();
    }

    /** แจ้งเตือนต้องไปทีละคนตามคิว ไม่ใช่ยิงหาทุกคนตั้งแต่ส่ง */
    public function test_only_the_person_whose_turn_it_is_gets_notified(): void
    {
        [$invest, $first, $second] = $this->twoApprovers();

        $waiting = Notification::where('event', 'invest_to_sign')
            ->where('doc_no', $invest->doc_no)->pluck('employee_code')->all();

        $this->assertSame([$first->employee_code], $waiting, 'ตอนส่งต้องแจ้งเฉพาะคนแรก');

        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve');

        $waiting = Notification::where('event', 'invest_to_sign')
            ->where('doc_no', $invest->doc_no)->pluck('employee_code')->all();

        $this->assertContains($second->employee_code, $waiting, 'เซ็นผ่านแล้วต้องแจ้งคนถัดไป');
    }

    /** ตัวค้นหาพนักงานต้องหาเจอทุกคน ไม่ใช่เฉพาะระดับบริหาร */
    public function test_the_people_search_finds_ordinary_staff(): void
    {
        $acc = $this->user('ACC_SEARCH', ['fn' => BudgetAccess::FN_PROPOSE]);
        $staff = $this->user('WORKER1');   // ไม่ได้ให้สิทธิ์อะไรเลย และตำแหน่ง S1
        // 🔴 ห้ามใช้รหัสที่มี _ ในเทสต์ — ตัวค้นหา escape _ ไว้ ซึ่ง SQLite ไม่รู้จัก  เป็นตัว escape

        $results = $this->signIn($acc)
            ->get('/people/search?q='.$staff->employee_code)
            ->assertOk()
            ->json('results');

        $this->assertSame(
            [$staff->employee_code],
            array_column($results, 'employee_code'),
        );
    }

    /** เปิดเอกสารที่บันทึกร่างแล้ว (โหมด "ดูเอกสาร") ต้องไม่พังหลังถอดฟิลด์ออก */
    public function test_a_saved_draft_opens_in_view_mode(): void
    {
        $acc = $this->user('ACC_VIEW', ['fn' => BudgetAccess::FN_PROPOSE]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        $html = $this->signIn($acc)->get('/budget/invest/'.$invest->id.'/edit')->assertOk()->getContent();

        /*
          🐛 บั๊กจริง 2026-09-09: กฎ "ช่องลงชื่อแถวละ 5 · แถวสุดท้ายจัดกึ่งกลาง"
             ใส่ไว้เฉพาะหน้าฟอร์ม หน้าดูเอกสารจึงเรียงคนละแบบ
             ย้ายขึ้นธีมกระดาษกลางแล้ว ทุกหน้าที่เป็นเอกสารต้องส่ง --sign-cols มาด้วย
        */
        $this->assertStringContainsString('--sign-cols:', $html);

        /*
          🔴 ตัวเอกสารต้องขึ้น "สำเนาเรียน (ผู้อนุมัติตามลำดับ)" ทุกหน้า (เจ้าของสั่ง 2026-09-09)
             เรียง 1, 2, 3, … ตามลำดับสายอนุมัติจริง
        */
        $this->assertStringContainsString('apv-doc', $html);
        $this->assertStringContainsString('budget.approvers', $html);
    }
}
