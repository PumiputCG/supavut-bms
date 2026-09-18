<?php

namespace Tests\Feature\Budget;

use App\Http\Middleware\Authenticate;
use App\Models\Access\FunctionUser;
use App\Models\Budget\Approval;
use App\Models\Budget\Budget;
use App\Models\Budget\DocGroup;
use App\Models\Budget\Invest;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Services\Access\AccessService;
use App\Services\Budget\BudgetAccess;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * ตารางตรวจสิทธิ์ทั้งโมดูลงบประมาณ — ใครเห็นอะไร กดอะไรได้
 *
 * เทสต์ชุดนี้ตอบคำถามเดียว: **ตั้งสิทธิ์ที่ /access/modules แล้วหน้าจอทำตามจริงไหม**
 *
 * 🔴 กติกาที่ต้องไม่หายไปไหน
 *    หัวข้อย่อยทั่วไป  ไม่ระบุใคร = เปิดให้ทุกคน
 *    สิทธิ์ย่อยปรับสถานะงบ  ไม่ระบุใคร = **ไม่มีใครทำได้** (กระทบเงินจริง)
 *    ผู้ดูแลระบบผ่านทุกด่านเสมอ
 */
class BudgetAccessMatrixTest extends TestCase
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

        /*
          🔴 ร่างต้องมีกลุ่มเอกสารเสมอ (เจ้าของสั่ง 2026-09-17)
             สร้างกลุ่มตั้งต้นไว้ให้ทุกเทสต์ — เลขที่ที่ออกจึงเป็น INV-NM-2569-xxxxxx
        */
        $this->docGroup = DocGroup::create(['code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model', 'sort' => 1]);
    }

    /** กลุ่มเอกสารตั้งต้นของเทสต์ในไฟล์นี้ */
    private DocGroup $docGroup;

    private int $seq = 0;

    /** 4 หน้าของโมดูล — ใช้ไล่ตรวจว่าใครเข้าได้บ้าง */
    private const PAGES = [
        'invest' => '/budget/invest',
        'approval' => '/budget/approval',
        'list' => '/budget/list',
        'history' => '/budget/history',
    ];

    // ── สิทธิ์เข้าหน้า ──────────────────────────────────────────────

    public function test_with_nothing_configured_nobody_can_open_any_page(): void
    {
        /*
          🔴 กติกาใหม่ 2026-09-07: ยังไม่ได้กำหนดใคร = ไม่มีใครเข้าได้
             ปลอดภัยไว้ก่อน ต้องตั้งใจให้สิทธิ์ถึงจะเข้าได้ (ผู้ดูแลระบบยังผ่านทุกด่าน)

          🔴 ยกเว้นหน้า "รับทราบ / อนุมัติ" — หัวข้อ "รับทราบ" ตั้งสิทธิ์ล่วงหน้าไม่ได้
             (ผู้เสนอเลือกผู้รับทราบเองตอนส่งแต่ละฉบับ) หน้านี้จึงกั้นด้วย **ข้อมูล** แทน
             ใครก็เปิดได้ แต่เห็นเฉพาะฉบับที่ตัวเองเกี่ยวข้อง — คนนอกเห็นตารางว่าง
        */
        $user = $this->user('OPEN1');

        foreach (self::PAGES as $name => $url) {
            $res = $this->signIn($user)->get($url);

            $name === 'approval' ? $res->assertOk() : $res->assertForbidden();
        }
    }

    public function test_a_stranger_opening_the_inbox_sees_an_empty_table(): void
    {
        // เปิดหน้าได้ก็จริง แต่ต้องไม่เห็นเอกสารของคนอื่น
        [, $invest] = $this->submitted();
        $stranger = $this->user('INBOX_X');

        $this->flushSession();
        $html = $this->signIn($stranger)->get(self::PAGES['approval'])->assertOk()->getContent();

        $this->assertStringNotContainsString($invest->doc_no, $html);
    }

    public function test_ticking_everyone_opens_the_page_for_all(): void
    {
        $user = $this->user('OPEN2');
        $this->openToEveryone(BudgetAccess::FN_PROPOSE);

        $this->signIn($user)->get(self::PAGES['invest'])->assertOk();
    }

    public function test_each_page_only_opens_for_the_people_listed_on_it(): void
    {
        // ระบุตัวคนคนละหน้า แล้วไล่ตรวจว่าเข้าได้เฉพาะหน้าของตัวเอง
        $acc = $this->user('M_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('M_SIGN', ['fn' => BudgetAccess::FN_INBOX]);
        $reader = $this->user('M_READ', ['fn' => BudgetAccess::FN_INBOX]);
        $viewer = $this->user('M_VIEW', ['fn' => BudgetAccess::FN_BUDGETS]);
        $hist = $this->user('M_HIST', ['fn' => 'fn.budget.history']);

        $matrix = [
            /*
              คน        เสนอ   รับทราบ/อนุมัติ  รายการงบ  ประวัติ
              🔴 ช่อง "รับทราบ/อนุมัติ" เป็น true หมด — หน้านั้นกั้นด้วยข้อมูล ไม่ใช่สิทธิ์
                 (ดู test_a_stranger_opening_the_inbox_sees_an_empty_table)
            */
            [$acc,    true,  true, false, false],
            [$signer, false, true, false, false],
            [$reader, false, true, false, false],
            [$viewer, false, true, true,  false],
            [$hist,   false, true, false, true],
        ];

        foreach ($matrix as [$user, $invest, $approval, $list, $history]) {
            $expect = compact('invest', 'approval', 'list', 'history');

            foreach (self::PAGES as $name => $url) {
                $res = $this->signIn($user)->get($url);

                $expect[$name]
                    ? $res->assertOk()
                    : $res->assertForbidden();
            }
        }
    }

    public function test_admin_passes_every_page_even_when_locked_to_others(): void
    {
        foreach ([BudgetAccess::FN_PROPOSE, BudgetAccess::FN_INBOX, BudgetAccess::FN_INBOX,
            BudgetAccess::FN_BUDGETS, 'fn.budget.history'] as $key) {
            $this->user('LOCK_'.$this->seq, ['fn' => $key]);
        }

        $admin = $this->user('M_ADMIN', ['role' => 'admin']);

        foreach (self::PAGES as $url) {
            $this->signIn($admin)->get($url)->assertOk();
        }
    }

    // ── เมนูซ้าย ────────────────────────────────────────────────────

    public function test_the_menu_greys_out_what_you_cannot_use(): void
    {
        // 🔴 เจ้าของสั่ง: ไม่มีสิทธิ์ = เทาลง ไม่ใช่ซ่อน (จะได้รู้ว่ามีเมนูนี้อยู่)
        $this->user('MENU_OK', ['fn' => BudgetAccess::FN_PROPOSE]);
        $outsider = $this->user('MENU_NO', ['fn' => BudgetAccess::FN_BUDGETS]);

        $html = $this->signIn($outsider)->get('/budget/list')->assertOk()->getContent();

        $this->assertStringContainsString('is-denied', $html);
        $this->assertStringContainsString('data-i18n="fn.budget.invest"', $html);   // ยังเห็นชื่อเมนู
    }

    public function test_the_two_roles_that_share_one_page_are_merged_in_the_menu(): void
    {
        /*
          🔴 แยกสิทธิ์ แต่รวมเมนู — "รับทราบ" กับ "อนุมัติ Invest" ชี้หน้าเดียวกัน
             เมนูซ้ายต้องเหลือรายการเดียว ไม่ใช่ 2 บรรทัดชี้ที่เดิม
        */
        $user = $this->user('MENU_M', ['fn' => BudgetAccess::FN_PROPOSE]);
        $html = $this->signIn($user)->get('/budget/invest')->assertOk()->getContent();

        $this->assertStringContainsString('data-i18n="fn.budget.inbox"', $html);
        $this->assertStringNotContainsString('data-i18n="fn.budget.acknowledge"', $html);
    }

    // ── สิ่งที่ทำได้ในหน้า ──────────────────────────────────────────

    public function test_only_the_listed_people_can_change_a_budget_status(): void
    {
        [$budget] = $this->approvedBudget();

        /*
          🔴 ถอดสิทธิ์ย่อย "ปรับสถานะงบประมาณ" ออกแล้ว (เจ้าของสั่ง 2026-09-09)
             ใครเห็นหน้า "ลงทะเบียน" ได้ ก็ปรับสถานะได้เลย
             คนที่ไม่มีสิทธิ์เข้าหน้า ก็ยิงตรงเข้ามาเปลี่ยนไม่ได้เหมือนเดิม
        */
        $boss = $this->user('ST_OK', ['fn' => BudgetAccess::FN_BUDGETS]);
        $outsider = $this->user('ST_NO');

        $this->signIn($outsider)->get('/budget/list')->assertForbidden();

        $this->signIn($outsider)->post('/budget/list/'.$budget->id.'/status', [
            'budget_status' => Budget::REGISTERED,
        ])->assertForbidden();

        // คนที่เห็นหน้าได้ — เห็นคอลัมน์เปลี่ยนสถานะและเปลี่ยนได้จริง
        $html = $this->signIn($boss)->get('/budget/list')->assertOk()->getContent();
        $this->assertStringContainsString('name="budget_status"', $html);

        $this->signIn($boss)->post('/budget/list/'.$budget->id.'/status', [
            'budget_status' => Budget::REGISTERED,
        ])->assertRedirect();

        $this->assertSame(Budget::REGISTERED, $budget->fresh()->budget_status);
    }

    public function test_only_people_in_the_flow_get_the_sign_buttons(): void
    {
        /*
          🔴 ถอดบทบาท "ผู้รับทราบ" ออกแล้ว (เจ้าของสั่ง 2026-09-09)
             ทุกคนในสายคือผู้ลงนาม · คนที่ไม่ได้ถูกเลือกเปิดเอกสารไม่ได้เลย
        */
        [, $invest, $outsider, $signer] = $this->submitted();

        $this->signIn($outsider)->get('/budget/doc/'.$invest->id)->assertForbidden();

        $asSigner = $this->signIn($signer)->get('/budget/doc/'.$invest->id)->assertOk()->getContent();
        $this->assertStringContainsString('data-act="ok"', $asSigner);
        $this->assertStringContainsString('data-act="no"', $asSigner);
    }

    public function test_people_outside_the_document_cannot_open_it(): void
    {
        [, $invest] = $this->submitted();

        $this->signIn($this->user('OUTSIDE'))->get('/budget/doc/'.$invest->id)->assertForbidden();
    }

    // ── หน้าประวัติงบประมาณ ─────────────────────────────────────────

    public function test_history_shows_every_document_no_matter_who_is_looking(): void
    {
        /*
          หน้าอื่นกรองตามบทบาทของผู้ดู แต่หน้าประวัติต้องเห็นครบทุกฉบับ
          เพราะเป็นหน้าไว้ตรวจย้อนหลัง
        */
        [, $invest] = $this->submitted();
        $stranger = $this->user('HIST_X', ['fn' => 'fn.budget.history']);

        $this->flushSession();
        $html = $this->signIn($stranger)->get('/budget/history')->assertOk()->getContent();

        $this->assertStringContainsString($invest->doc_no, $html);
    }

    public function test_history_counts_documents_by_status(): void
    {
        [, $invest, , $signer] = $this->submitted();
        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/approve');

        $this->flushSession();
        $html = $this->signIn($this->user('HIST_C', ['fn' => 'fn.budget.history']))->get('/budget/history')->assertOk()->getContent();

        $this->assertStringContainsString('budget.hist.approved', $html);
        $this->assertStringContainsString('budget.hist.total', $html);
    }

    // ── หน้าภาพรวมระบบ ──────────────────────────────────────────────

    public function test_the_dashboard_uses_its_own_permission_not_the_budget_one(): void
    {
        /*
          🔴 แดชบอร์ดตัดสินด้วยสิทธิ์ "ภาพรวมระบบ → แดชบอร์ด" ของตัวเอง (เจ้าของสั่ง 2026-09-15)
             เดิมตัวหน้ายืม fn.budget.approved ("ลงทะเบียน" ซึ่งเป็นคิวงานของบัญชี) มาเป็นด่านที่ 2
             คนที่ได้สิทธิ์แดชบอร์ดจึงเปิดหน้าได้ แต่เห็น "ไม่มีสิทธิ์ดูข้อมูลงบประมาณ" แทนตัวเลข
             = ต้องไปตั้งสิทธิ์ 2 ที่ถึงใช้ได้จริง ขัดกับกฎ "กำหนดสิทธิ์ที่ /access/modules ที่เดียว"

          🔴 ต้องล้างแถวที่ migration เปิดให้ "ทุกคน" ไว้ก่อน ไม่งั้นทุกคนผ่านหมดจนเทสต์ไม่วัดอะไรเลย
        */
        FunctionUser::where('function_key', 'fn.overview.dashboard')->delete();

        $viewer = $this->user('DASH_OK');
        FunctionUser::create([
            'module_id' => 'dashboard',
            'function_key' => 'fn.overview.dashboard',
            'employee_code' => 'DASH_OK',
        ]);

        // มีสิทธิ์ "ลงทะเบียน" ของโมดูลงบอย่างเดียว แต่ไม่มีสิทธิ์แดชบอร์ด = เข้าไม่ได้
        $budgetOnly = $this->user('DASH_NO', ['fn' => BudgetAccess::FN_BUDGETS]);

        $this->flushSession();
        $this->signIn($viewer)->get('/dashboard')->assertOk()->assertViewHas('canSeeBudget', true);

        $this->flushSession();
        $this->signIn($budgetOnly)->get('/dashboard')->assertForbidden();
    }
    // ── ตัวช่วย ─────────────────────────────────────────────────────

    /** @param array<string,mixed> $attributes */
    /** ติ๊ก "ทุกคน" ให้หัวข้อย่อยนี้ — กติกาใหม่ต้องเปิดอย่างชัดเจน */
    private function openToEveryone(string $functionKey): void
    {
        FunctionUser::create([
            'module_id' => BudgetAccess::MODULE,
            'function_key' => $functionKey,
            'employee_code' => AccessService::EVERYONE,
        ]);
    }

    private function user(string $code, array $attributes = []): AppUser
    {
        $this->seq++;

        Employee::create([
            'insight_id' => 5000 + $this->seq,
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
            'insight_id' => 5000 + $this->seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'password' => 'secret',
            'role' => $attributes['role'] ?? 'user',
            'full_name_th' => 'ทดสอบ '.$code,
            'dept_code' => $attributes['dept_code'] ?? 'D01',
            'signature' => 'data:image/png;base64,TEST',
        ]);

        foreach ((array) ($attributes['fn'] ?? []) as $key) {
            FunctionUser::create([
                'module_id' => BudgetAccess::MODULE,
                'function_key' => $key,
                'employee_code' => $code,
            ]);
        }

        return $user;
    }

    private ?AppUser $actor = null;

    private function signIn(AppUser $user): self
    {
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);
        $this->actor = $user;

        return $this;
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return [
            'group_id' => $this->docGroup->id,
            'fiscal_year' => 2569,
            'proposer_code' => (string) ($this->actor?->employee_code ?: 'MX_ACC'),
            'dept_code' => 'D01',
            'title' => 'งบทดสอบสิทธิ์',
            'amount' => 50000,
            /*
              🔴 ผู้ขอเลือกผู้อนุมัติเองแล้ว (เจ้าของสั่ง 2026-09-09) ระบบไม่หยิบจากสิทธิ์ให้อีก
                 เทสต์จึงใช้คนที่ถือสิทธิ์ "อนุมัติ Invest" ณ ตอนนั้นเป็นลำดับตั้งต้น
            */
            'approvers' => FunctionUser::where('function_key', BudgetAccess::FN_INBOX)
                ->pluck('employee_code')->map(strval(...))->all(),
        ];
    }

    /** @return array{0:AppUser,1:Invest,2:AppUser,3:AppUser} */
    private function submitted(): array
    {
        $acc = $this->user('MX_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        /*
          🔴 สำเนาเรียน = ผู้อนุมัติตามลำดับแล้ว (เจ้าของสั่ง 2026-09-09)
             MX_CC จึงเป็นผู้อนุมัติลำดับ 1 และ MX_SIGN เป็นลำดับสุดท้าย
        */
        // คนนอกสายเอกสาร — ใช้ทดสอบว่าคนที่ไม่ได้ถูกเลือกทำอะไรไม่ได้
        $outsider = $this->user('MX_CC', ['fn' => BudgetAccess::FN_INBOX]);
        $signer = $this->user('MX_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::orderByDesc('id')->firstOrFail();

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$signer->employee_code],
        ])->assertRedirect();

        $this->assertSame(Invest::PENDING, $invest->fresh()->approval_status);
        $this->assertSame(1, Approval::where('doc_id', $invest->id)->count());

        return [$acc, $invest->refresh(), $outsider, $signer];
    }

    /** @return array{0:Budget} */
    private function approvedBudget(): array
    {
        [, $invest, , $signer] = $this->submitted();

        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/approve');

        return [Budget::firstOrFail()];
    }
}
