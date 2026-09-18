<?php

namespace Tests\Feature\Core;

use App\Http\Middleware\Authenticate;
use App\Models\Access\FunctionUser;
use App\Models\Budget\Approval;
use App\Models\Budget\DocGroup;
use App\Models\Budget\Invest;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Models\Core\Notification;
use App\Services\Budget\BudgetAccess;
use App\Services\Budget\InvestFlow;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * กระดิ่งแจ้งเตือน — ใครควรได้แจ้งเตือนอะไร ตอนไหน
 *
 * 🔴 กติกาที่ต้องไม่หาย
 *    ส่งเอกสาร  -> ผู้รับทราบได้ "ส่งมาให้ทราบ" · ผู้อนุมัติได้ "รอคุณลงนาม"
 *    ลงนามครบ   -> ผู้เสนอได้ "อนุมัติแล้ว" พร้อมเลขที่งบ
 *    ตีกลับ     -> ผู้เสนอได้ "ไม่อนุมัติ" พร้อมเหตุผล
 *    ห้ามแจ้งซ้ำเรื่องเดิมให้คนเดิมตอนที่ยังไม่อ่าน
 */
class NotificationTest extends TestCase
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

    /*
      🐛 บั๊กจริง 2026-09-10 (เจ้าของแจ้ง): กดแจ้งเตือนแล้ว "หาเอกสารไม่เจอ"

      ของเดิมเก็บที่อยู่เต็มพร้อมชื่อโฮสต์ที่สร้างไว้ตอนส่ง เช่น http://127.0.0.1:8000/...
      เปิดเว็บจากที่อยู่อื่น (Apache ที่ /SBMS/public) ลิงก์จึงพาไปคนละเครื่อง
    */
    public function test_the_notification_link_is_built_when_shown_not_stored(): void
    {
        [, $invest, , $signer] = $this->submitted();

        $row = Notification::where('employee_code', $signer->employee_code)->firstOrFail();

        // 🔴 ห้ามมีที่อยู่เต็มค้างในฐาน — เก็บแค่ชื่อ route กับเลขเอกสาร
        $this->assertNull($row->url);
        $this->assertSame('budget.doc.show', $row->route_name);
        $this->assertSame((string) $invest->id, $row->route_param);

        // ลิงก์ที่หน้าจอใช้ ต้องตรงกับที่ route ของระบบสร้างให้ ณ ตอนนั้น
        $this->assertSame(route('budget.doc.show', $invest), $row->link());
    }

    public function test_sending_a_document_notifies_every_approver(): void
    {
        /*
          🔴 ถอดบทบาท "ผู้รับทราบ" ออกแล้ว (เจ้าของสั่ง 2026-09-09)
             ทุกคนในสายคือผู้ลงนาม แจ้งเตือนตอนส่งจึงมีแบบเดียว = "รอคุณลงนาม"
        */
        [$acc, $invest, $outsider, $signer] = $this->submitted();

        $todo = Notification::where('employee_code', $signer->employee_code)->firstOrFail();
        $this->assertSame('invest_to_sign', $todo->event);
        $this->assertSame(BudgetAccess::FN_INBOX, $todo->function_key);

        /*
          🔴 แจ้งเตือนต้องส่งครบ 4 อย่างให้แผงกระดิ่งวาดหัวข้อกำกับได้ (เจ้าของสั่ง 2026-09-04)
               เลขที่    doc_no
               <ป้าย>    subject_key (คีย์แปล) + title_th/en (ค่า)
               แจ้งเตือน  body_th/en
               วันเวลา   created_at
             โมดูลใหม่ที่จะส่งแจ้งเตือนต้องทำแบบเดียวกัน
        */
        $this->assertSame($invest->doc_no, $todo->doc_no);
        $this->assertSame('budget.dept', $todo->subject_key);
        $this->assertSame($invest->dept_name ?: $invest->dept_code, $todo->title_th);
        $this->assertNotSame('', (string) $todo->body_th);
        $this->assertNotNull($todo->created_at);

        // เลขที่กับชื่อรายการต้องไม่ปนมาในบรรทัดหัวข้อ — มันมีบรรทัดของตัวเองแล้ว
        $this->assertStringNotContainsString($invest->doc_no, $todo->title_th);
        $this->assertStringNotContainsString($invest->title, $todo->title_th);

        // ผู้เสนอยังไม่ต้องได้อะไร — เพิ่งกดส่งเอง
        $this->assertSame(0, Notification::where('employee_code', $acc->employee_code)->count());

        // คนนอกสายเอกสารต้องไม่ได้รับอะไรเลย
        $this->assertSame(0, Notification::where('employee_code', $outsider->employee_code)->count());
    }

    /*
      🔴 เจ้าของสั่ง 2026-09-10: แจ้งเตือนมีไว้บอก "คนที่ต้องลงมือทำ" เท่านั้น
         = ผู้ลงนามตามลำดับ 1, 2, 3, … · ไม่แจ้งผลกลับไปหาผู้ขออีก
         ผู้ขอตามความคืบหน้าเองได้ที่คอลัมน์สถานะและหน้าต่าง "ดูเส้นทาง"
    */
    public function test_approving_does_not_notify_the_proposer_back(): void
    {
        [$acc, $invest, , $signer] = $this->submitted();

        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/approve');

        $this->assertSame(0, Notification::where('employee_code', $acc->employee_code)
            ->whereIn('event', ['invest_done', 'invest_signed'])->count());
    }

    public function test_rejecting_does_not_notify_the_proposer_back(): void
    {
        [$acc, $invest, , $signer] = $this->submitted();

        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/reject', ['reason' => 'งบไม่พอ']);

        $this->assertSame(0, Notification::where('employee_code', $acc->employee_code)
            ->where('event', 'invest_rejected')->count());
    }

    /** คนถัดไปในสายยังต้องได้รับแจ้งตามปกติ — นี่คือแจ้งเตือนชนิดเดียวที่เหลืออยู่ */
    public function test_the_next_signer_is_still_notified_in_turn(): void
    {
        $acc = $this->user('N_SEQ_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $first = $this->user('N_SEQ_1', ['fn' => BudgetAccess::FN_INBOX]);
        $second = $this->user('N_SEQ_2', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', [
            'group_id' => $this->docGroup->id,
            'fiscal_year' => 2569,
            'proposer_code' => $acc->employee_code,
            'dept_code' => 'D01',
            'title' => 'งบทดสอบลำดับแจ้งเตือน',
            'amount' => 9000,
            'approvers' => [$first->employee_code, $second->employee_code],
        ]);

        $invest = Invest::orderByDesc('id')->firstOrFail();

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$first->employee_code, $second->employee_code],
        ])->assertRedirect();

        // ส่งแล้ว — คนแรกได้รับ · คนที่สองยังไม่ถึงคิว
        $this->assertSame(1, Notification::where('employee_code', $first->employee_code)
            ->where('event', 'invest_to_sign')->count());
        $this->assertSame(0, Notification::where('employee_code', $second->employee_code)->count());

        $this->signIn($first)->post('/budget/doc/'.$invest->id.'/approve')->assertRedirect();

        // คนแรกเซ็นแล้ว — ถึงคิวคนที่สอง
        $this->assertSame(1, Notification::where('employee_code', $second->employee_code)
            ->where('event', 'invest_to_sign')->count());

        // และผู้ขอยังต้องไม่ได้รับอะไรเลย
        $this->assertSame(0, Notification::where('employee_code', $acc->employee_code)->count());
    }

    public function test_the_same_thing_is_never_notified_twice_while_unread(): void
    {
        // ส่งเอกสารซ้ำ (เช่นแก้แล้วส่งใหม่) ต้องไม่ได้แจ้งเตือนซ้อนกัน
        [$acc, $invest, $recipient] = $this->submitted();

        $flow = app(InvestFlow::class);
        // บทบาทติดมากับรายชื่อชุดเดียว เรียงตามลำดับที่ผู้ขอจัด (เปลี่ยนกติกา 2026-09-16)
        $person = fn ($u, $action) => ['employee_code' => $u->employee_code, 'employee_name' => null, 'position' => null, 'action' => $action];

        $invest->update(['approval_status' => Invest::DRAFT]);
        $flow->submit($invest->fresh(), [
            $person($recipient, Approval::ACK),
            $person($this->signer, Approval::APPROVE),
        ]);

        $this->assertSame(1, Notification::where('employee_code', $recipient->employee_code)
            ->where('event', 'invest_cc')->count());
    }

    public function test_the_bell_shows_a_red_count_and_groups_by_module(): void
    {
        [, , , $signer] = $this->submitted();

        $html = $this->signIn($signer)->get('/dashboard')->assertOk()->getContent();

        // ตัวเลขแดงบนกระดิ่ง
        $this->assertStringContainsString('data-bell-count', $html);
        $this->assertStringContainsString('class="bell-dot"', $html);

        /*
          จัดกลุ่มเป็นโมดูล -> หัวข้อย่อย
          🔴 หัวข้อย่อยใช้ชื่อเดียวกับเมนูซ้าย — "รับทราบ" กับ "อนุมัติ" ยุบเป็น fn.budget.inbox
             กระดิ่งต้องเรียกชื่อเดียวกับเมนู ไม่งั้นผู้ใช้กดตามหาไม่เจอ
        */
        $this->assertStringContainsString('data-i18n="nav.budget"', $html);
        $this->assertStringContainsString('data-i18n="fn.budget.inbox"', $html);

        // ข้อความในเรื่องบอกชัดว่าให้ทำอะไร
        $this->assertStringContainsString('รอคุณลงนามอนุมัติ', $html);
    }

    public function test_reading_one_notification_clears_only_that_one(): void
    {
        [, , , $signer] = $this->submitted();

        $n = Notification::where('employee_code', $signer->employee_code)->firstOrFail();

        $this->signIn($signer)
            ->postJson('/notifications/read', ['id' => $n->id])
            ->assertOk()
            ->assertJson(['ok' => true, 'unread' => 0]);

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_you_cannot_mark_someone_elses_notification_as_read(): void
    {
        [, , $outsider, $signer] = $this->submitted();

        $mine = Notification::where('employee_code', $signer->employee_code)->firstOrFail();

        // คนอื่นพยายามอ่านแจ้งเตือนของผู้อนุมัติ — ต้องไม่มีผล
        $this->signIn($outsider)->postJson('/notifications/read', ['id' => $mine->id])->assertOk();

        $this->assertNull($mine->fresh()->read_at);
    }

    // ── ตัวช่วย ─────────────────────────────────────────────────────

    private AppUser $signer;

    /** @param array<string,mixed> $attributes */
    /*
      🔴 ลงนามแล้ว = เรื่องนี้ไม่ค้างที่เราอีก เลขแดงของใบนั้นต้องหายเอง (เจ้าของแจ้ง 2026-09-07)
         เดิมอ่านให้เฉพาะตอน "เปิดเอกสาร" กดอนุมัติรวดจากตารางจึงค้าง
    */
    public function test_signing_a_document_clears_its_red_badge_for_the_signer(): void
    {
        [, $invest, , $signer] = $this->submitted();

        $mine = Notification::where('employee_code', $signer->employee_code)
            ->where('doc_no', $invest->doc_no);

        $this->assertSame(1, (clone $mine)->whereNull('read_at')->count(), 'ก่อนเซ็นต้องมีเลขแดงค้างอยู่');

        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/approve');

        $this->assertSame(0, (clone $mine)->whereNull('read_at')->count());
    }

    public function test_rejecting_a_document_clears_its_red_badge_for_the_signer(): void
    {
        [, $invest, , $signer] = $this->submitted();

        $this->signIn($signer)->post('/budget/doc/'.$invest->id.'/reject', ['reason' => 'งบไม่พอ']);

        $this->assertSame(0, Notification::where('employee_code', $signer->employee_code)
            ->where('doc_no', $invest->doc_no)->whereNull('read_at')->count());
    }

    /** อนุมัติหลายฉบับรวดเดียวจากตาราง — ทางนี้ไม่เคยเปิดเอกสารเลย เลขแดงจึงเคยค้าง */
    public function test_approving_from_the_table_clears_the_red_badge_too(): void
    {
        [, $invest, , $signer] = $this->submitted();

        $this->signIn($signer)->post('/budget/approval/bulk', [
            'action' => 'approve',
            'docs' => [$invest->id],
        ])->assertRedirect();

        $this->assertSame(0, Notification::where('employee_code', $signer->employee_code)
            ->where('doc_no', $invest->doc_no)->whereNull('read_at')->count());
    }

    /*
      กันพลาดจากลำดับ: ต้องอ่านของเก่า "ก่อน" ส่งของใหม่
      ไม่งั้นคนที่เสนอเองแล้วเซ็นเอง จะโดนลบแจ้งเตือน "อนุมัติแล้ว" ที่เพิ่งส่งให้ตัวเองทิ้ง
    */
    public function test_signing_your_own_proposal_leaves_no_badge_behind(): void
    {
        $both = $this->user('N_BOTH', ['fn' => [BudgetAccess::FN_PROPOSE, BudgetAccess::FN_INBOX]]);

        $this->signIn($both)->post('/budget/invest', [
            'group_id' => $this->docGroup->id,
            'fiscal_year' => 2569,
            'proposer_code' => $both->employee_code,
            'dept_code' => 'D01',
            'title' => 'เสนอเองเซ็นเอง',
            'amount' => 5000,
            /*
              🔴 ผู้ขอเลือกผู้อนุมัติเองแล้ว (เจ้าของสั่ง 2026-09-09) ระบบไม่หยิบจากสิทธิ์ให้อีก
                 เทสต์จึงใช้คนที่ถือสิทธิ์ "อนุมัติ Invest" ณ ตอนนั้นเป็นลำดับตั้งต้น
            */
            'approvers' => FunctionUser::where('function_key', BudgetAccess::FN_INBOX)
                ->pluck('employee_code')->map(strval(...))->all(),
        ]);

        $invest = Invest::orderByDesc('id')->firstOrFail();
        $this->signIn($both)->post('/budget/invest/'.$invest->id.'/submit')->assertRedirect();
        $this->signIn($both)->post('/budget/doc/'.$invest->id.'/approve');

        /*
          🔴 เสนอเองเซ็นเอง = ไม่ควรเหลือเลขแดงค้าง (เจ้าของสั่ง 2026-09-10 เลิกแจ้งกลับผู้ขอ)
             ใบที่เคยแจ้งว่า "รอคุณลงนาม" ต้องถูกปิดให้เองตอนกดอนุมัติ
        */
        $this->assertSame(0, Notification::where('employee_code', $both->employee_code)
            ->whereNull('read_at')->count());
    }

    private function user(string $code, array $attributes = []): AppUser
    {
        $this->seq++;

        Employee::create([
            'insight_id' => 7000 + $this->seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'name_th' => 'ทดสอบ',
            'surname_th' => $code,
            'dept_code' => 'D01',
            'dept_th' => 'แผนกทดสอบ',
            'job_code' => 'S1',
            'job_th' => 'เจ้าหน้าที่',
            'emp_status' => '1',
        ]);

        $user = AppUser::create([
            'insight_id' => 7000 + $this->seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'password' => 'secret',
            'role' => 'user',
            'full_name_th' => 'ทดสอบ '.$code,
            'dept_code' => 'D01',
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

    private function signIn(AppUser $user): self
    {
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        return $this;
    }

    /** @return array{0:AppUser,1:Invest,2:AppUser,3:AppUser} */
    private function submitted(): array
    {
        $acc = $this->user('N_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        // คนนอกสายเอกสาร — ใช้ทดสอบว่าคนที่ไม่ได้ถูกเลือกไม่ได้รับแจ้งเตือน
        $outsider = $this->user('N_CC', ['fn' => BudgetAccess::FN_INBOX]);
        $this->signer = $this->user('N_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', [
            'group_id' => $this->docGroup->id,
            'fiscal_year' => 2569,
            // วันที่เสนอ + ผู้เสนอ บังคับกรอกตั้งแต่ 2026-09-04
            'proposer_code' => $acc->employee_code,
            'dept_code' => 'D01',
            'title' => 'งบทดสอบแจ้งเตือน',
            'amount' => 12000,
            'approvers' => [$this->signer->employee_code],
        ]);

        $invest = Invest::orderByDesc('id')->firstOrFail();

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit')->assertRedirect();

        return [$acc, $invest->refresh(), $outsider, $this->signer];
    }
}
