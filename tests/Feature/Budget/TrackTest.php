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
use App\Services\Budget\BudgetAccess;
use App\Support\FinalApprover;
use App\Support\QrCode;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * QR Code ติดตามเอกสาร (เจ้าของสั่ง 2026-09-18 · DECISIONS 50.15)
 *
 * สิ่งที่ต้องคุมไว้
 *   - กุญแจออกตอน "กดส่ง" พร้อมเลขที่เอกสาร · ร่างไม่มีกุญแจ · ส่งซ้ำไม่ออกกุญแจใหม่
 *   - ใบ BGT ใช้กุญแจของใบ INV ต้นทาง (QR ดวงเดียวต่อเรื่อง)
 *   - QR อยู่บนกระดาษทั้งบนจอและในไฟล์ PDF
 *   - ใบ INV ต้องพิมพ์/ดาวน์โหลดได้ และไฟล์ที่ได้ต้องไม่มีปุ่มอนุมัติติดไป
 *   - หน้าติดตาม 2 ชั้น: ไม่ล็อกอินเห็นเส้นทาง **แต่ไม่เห็นยอดเงินและลายเซ็น**
 *   - กุญแจมั่ว/ผิดรูป ต้องได้ 404 และตอบเหมือนกันทุกกรณี
 */
class TrackTest extends TestCase
{
    use RefreshModuleDatabase;

    private DocGroup $docGroup;

    private ?AppUser $actor = null;

    protected function setUp(): void
    {
        parent::setUp();

        // ปิด "CEO ต่อท้ายอัตโนมัติ" เหมือนเทสต์ชุดอื่นของโมดูลนี้ (รหัสจริงไม่มีบัญชีในฐานทดสอบ)
        config(['bms.final_approver' => '']);
        FinalApprover::forget();

        $this->docGroup = DocGroup::create(['code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model', 'sort' => 1]);
    }

    /*
    |---------------------------------------------------------------
    | กุญแจของ QR
    |---------------------------------------------------------------
    */

    /** 🔴 กุญแจออกตอนกดส่ง ไม่ใช่ตอนบันทึกร่าง (กติกาเดียวกับเลขที่เอกสาร) */
    public function test_the_tracking_key_is_issued_when_the_document_is_submitted(): void
    {
        [$acc, $invest, , $ceo] = $this->submitted(draftOnly: true);

        // ร่าง: ยังไม่มีเลขที่ จึงยังไม่มีกุญแจและยังไม่มี QR
        $this->assertNull($invest->doc_no);
        $this->assertNull($invest->track_token);
        $this->assertNull($invest->trackUrl());

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$ceo->employee_code],
        ])->assertRedirect();

        $invest->refresh();
        $this->assertNotNull($invest->doc_no, 'กดส่งแล้วต้องได้เลขที่เอกสาร');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', (string) $invest->track_token, 'กุญแจต้องเป็นรหัสสุ่ม 32 ตัว');

        // 🔴 ห้ามใช้เลขที่เอกสารเป็นกุญแจ — เลขที่เดาได้ ไล่ดูเอกสารทั้งบริษัทได้
        $this->assertStringNotContainsString((string) $invest->doc_no, (string) $invest->track_token);

        // ลิงก์ที่ฝังใน QR ต้องเป็นที่อยู่ของหน้าติดตาม
        $this->assertStringEndsWith('/track/'.$invest->track_token, $invest->trackUrl());
    }

    /** 🔴 เอกสารที่ถูกตีกลับแล้วส่งซ้ำ ต้องสแกนด้วย QR ใบเดิมได้ (ห้ามออกกุญแจใหม่) */
    public function test_resubmitting_keeps_the_same_tracking_key(): void
    {
        [$acc, $invest, , $ceo] = $this->submitted();
        $first = $invest->track_token;

        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/reject', ['reason' => 'ยังไม่จำเป็น'])->assertRedirect();

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$ceo->employee_code],
        ]);

        $this->assertSame($first, $invest->refresh()->track_token);
    }

    /** 🔴 ใบ BGT ไม่มีกุญแจของตัวเอง — ใช้ของใบ INV ต้นทาง (QR ดวงเดียวต่อเรื่อง) */
    public function test_the_budget_paper_shares_the_key_of_its_source_document(): void
    {
        $budget = $this->approvedBudget();
        $invest = $budget->invest;

        $this->assertNotNull($invest->track_token);
        $this->assertSame($invest->trackUrl(), $budget->trackUrl());

        // ตาราง budgets ต้องไม่มีคอลัมน์กุญแจของตัวเอง
        $this->assertFalse(
            Schema::hasColumn('budgets', 'track_token'),
            'กุญแจต้องอยู่ที่ budget_invests ตารางเดียว'
        );
    }

    /*
    |---------------------------------------------------------------
    | QR บนกระดาษ
    |---------------------------------------------------------------
    */

    /** QR ต้องอยู่บนกระดาษทั้งใบ INV และใบ BGT และอ่านค่ากลับได้ตรงลิงก์จริง */
    public function test_the_qr_is_printed_on_both_papers(): void
    {
        $budget = $this->approvedBudget();
        $invest = $budget->invest;

        /*
          🔴 ใบ BGT เปิดได้เฉพาะคนที่มีสิทธิ์ "ลงทะเบียนงบประมาณ" (คนละหัวข้อกับใบ INV)
             จึงใช้ผู้ดูแลระบบที่ผ่านทุกด่าน แทนที่จะไปเปิดสิทธิ์ให้ฝ่ายบัญชีเพิ่มในเทสต์นี้
        */
        $acc = $this->user('ADM_QR', ['role' => 'admin']);

        foreach (['/budget/doc/'.$invest->id, '/budget/list/'.$budget->id] as $url) {
            $html = $this->signIn($acc)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('<div class="paper-qr">', $html, $url.' ต้องมีบล็อก QR');
            $this->assertStringContainsString('ติดตามเอกสาร', $html);
            $this->assertStringContainsString('width="22mm" height="22mm"', $html, 'ขนาดต้องเป็นมิลลิเมตร ไม่ใช่พิกเซล');

            // 🔴 QR ต้องเป็น SVG ฝังในหน้า ไม่ใช่ไฟล์ภาพให้โหลด (ไม่งั้นติดลงไฟล์ PDF ไม่ได้)
            $this->assertStringNotContainsString('qr.png', $html);
        }

        // ลิงก์ในหน้าต้องตรงกับ QR ที่วาดจากลิงก์เดียวกัน
        $html = $this->signIn($acc)->get('/budget/doc/'.$invest->id)->getContent();
        $this->assertStringContainsString(QrCode::svg($invest->trackUrl(), 22, 'ติดตามเอกสาร / Track document'), $html);
    }

    /**
     * 🔴 ป้ายสถานะบนหัวกระดาษต้องมีสีของตัวเอง (เจ้าของแจ้ง 2026-09-18 ว่าสีหาย)
     *
     *    ตอนย้ายป้ายมาอยู่ใต้ชื่อบริษัท มันตกไปอยู่ใต้กฎเหมา `.paper-org span` (0,1,1)
     *    ซึ่งชนะ `.st-approved` (0,1,0) สีเขียวจึงถูกทับเป็นสีเทา — บทเรียน specificity เดิมของโปรเจค
     */
    public function test_the_status_pill_on_the_paper_keeps_its_colour(): void
    {
        $budget = $this->approvedBudget();
        $invest = $budget->invest;
        $admin = $this->user('ADM_ST', ['role' => 'admin']);

        $html = $this->signIn($admin)->get('/budget/doc/'.$invest->id)->assertOk()->getContent();

        // ป้ายอยู่ในแถวเลขที่เอกสาร และยังถือคลาสสีของตัวเองอยู่
        $this->assertMatchesRegularExpression('~class="paper-ids">.*?class="st st-approved"~s', $html);

        /*
          🔴 กฎเหมาที่ทับสีต้องไม่กลับมา
             ข้อนี้วัดที่ CSS โดยตรงจึงเช็คชื่อคลาสได้ (ต่างจากการวัดมาร์กอัป)
        */
        $this->assertStringNotContainsString('.paper-org span {', $html);
    }

    /** 🔴 ร่างไม่มี QR — ยังไม่มีเลขที่ และเนื้อหายังแก้ได้ทุกบรรทัด ปริ้นไปสแกนไม่มีประโยชน์ */
    public function test_a_draft_has_no_qr_and_no_print_button(): void
    {
        [$acc, $invest] = $this->submitted(draftOnly: true);

        $html = $this->signIn($acc)->get('/budget/invest/'.$invest->id.'/edit')->assertOk()->getContent();

        $this->assertStringNotContainsString('<div class="paper-qr">', $html);

        /*
          🔴 วัดที่ "ลิงก์ของหน้าพิมพ์" ไม่ใช่คำว่า "ดาวน์โหลด PDF"
             เพราะคำนั้นอยู่ในพจนานุกรมคำแปลซึ่งติดไปกับทุกหน้าอยู่แล้ว (บทเรียนเดิม)
        */
        $this->assertStringNotContainsString('/print', $this->markupOf($html));
    }

    /*
    |---------------------------------------------------------------
    | ใบ INV พิมพ์ / ดาวน์โหลดได้ (ช่องโหว่ที่เจ้าของจับได้ 2026-09-18)
    |---------------------------------------------------------------
    */

    /** หน้าพิมพ์ใบ INV ต้องมี QR แต่ต้องไม่มีปุ่มอนุมัติ/ปฏิเสธ */
    public function test_the_invest_print_page_has_the_qr_but_no_approval_buttons(): void
    {
        [$acc, $invest, , $ceo] = $this->submitted();

        $html = $this->signIn($ceo)->get('/budget/doc/'.$invest->id.'/print')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="paper-qr">', $html);
        $this->assertStringContainsString($invest->doc_no, $html);

        // 🔴 หน้าพิมพ์ไม่ใช่ที่สำหรับตัดสินเอกสาร — ปุ่มต้องไม่ถูกวาดตั้งแต่ต้น ไม่ใช่ซ่อนด้วย CSS
        $this->assertStringNotContainsString('data-act="ok"', $html);
        $this->assertStringNotContainsString('data-act="no"', $html);

        /*
          หน้าเอกสารปกติต้องมีปุ่มดาวน์โหลด PDF (ก่อนหน้านี้ไม่มีปุ่มอะไรเลย)
          🔴 เจ้าของสั่งเอาปุ่ม "พิมพ์" ออก 2026-09-18 — หน้าเอกสารต้องไม่มีลิงก์ไปหน้าพิมพ์แล้ว
             (หน้าพิมพ์ยังอยู่ เพราะเป็นทางถอยเวลาสร้างไฟล์ PDF ไม่สำเร็จ)
        */
        $doc = $this->signIn($ceo)->get('/budget/doc/'.$invest->id)->getContent();
        $this->assertStringContainsString(route('budget.doc.pdf', $invest), $doc);
        $this->assertStringNotContainsString(route('budget.doc.print', $invest), $doc);
    }

    /** 🔴 คนนอกเอกสารพิมพ์/ดาวน์โหลดไม่ได้ — ด่านเดียวกับการเปิดเอกสาร ไม่ใช่ด่านใหม่ */
    public function test_an_outsider_cannot_print_or_download_the_document(): void
    {
        [, $invest, $outsider] = $this->submitted();

        $this->signIn($outsider)->get('/budget/doc/'.$invest->id.'/print')->assertForbidden();
        $this->signIn($outsider)->get('/budget/doc/'.$invest->id.'/pdf')->assertForbidden();
    }

    /*
    |---------------------------------------------------------------
    | หน้าติดตาม — 2 ชั้น
    |---------------------------------------------------------------
    */

    /** สแกนแล้วเห็นเส้นทางทันทีโดยไม่ต้องล็อกอิน แต่ต้องไม่เห็นยอดเงินและลายเซ็น */
    public function test_a_visitor_sees_the_route_but_not_the_money_or_signatures(): void
    {
        $budget = $this->approvedBudget();
        $invest = $budget->invest;

        /*
          🔴 ต้องล้าง session ก่อน — ตัวช่วยที่สร้างข้อมูลล็อกอินเป็น CEO ไว้
             ถ้าไม่ล้าง คำขอถัดไปยังเป็น "คนที่อยู่ในเอกสาร" แล้วเทสต์จะเห็นยอดเงินได้จริง
             = เทสต์ที่คิดว่าวัดคนนอก แต่จริงๆ วัดคนใน (ผมพลาดจุดนี้รอบแรก)
        */
        $this->flushSession();

        $html = $this->get('/track/'.$invest->track_token)->assertOk()->getContent();

        // เห็นสิ่งที่ต้องเห็น
        // 🔴 เซ็นครบแล้วโชว์ "เลขที่งบ" แทนเลขที่ INV (โชว์เลขเดียว · เจ้าของกำหนด 2026-09-18)
        $this->assertStringContainsString($budget->doc_no, $html);
        $this->assertStringNotContainsString($invest->doc_no, $this->markupOf($html));
        $this->assertStringContainsString('สถานะปัจจุบัน', $html);
        $this->assertStringContainsString('เส้นทางเอกสาร', $html);
        $this->assertStringContainsString('ทดสอบ CEO', $html, 'ต้องเห็นว่าใครลงนาม');

        /*
          🔴 ห้ามหลุด: ยอดเงินและรูปลายเซ็น (เจ้าของเคาะ 2026-09-18)
             วัดที่ "มาร์กอัปจริง" ไม่ใช่ทั้งหน้า — ชื่อคลาสกับพจนานุกรมคำแปลอยู่ในบล็อก style/script
             (บทเรียนซ้ำรอบที่ 4: ชื่อคลาสเปล่าๆ ใช้เป็นตัวชี้วัดไม่ได้)
        */
        $markup = $this->markupOf($html);
        $this->assertStringNotContainsString(number_format(90000, 2), $markup, 'ยอดเงินต้องไม่หลุด');
        $this->assertStringNotContainsString('<span class="tk-sign">', $markup, 'รูปลายเซ็นต้องไม่หลุด');
        $this->assertStringNotContainsString('data:image/png;base64,TEST', $markup);

        /*
          🔴 การ์ดท้ายหน้ามีแค่ปุ่มเดียว (เจ้าของสั่ง 2026-09-18)
             เอาแถบอธิบาย "ยอดเงินและลายเซ็น…" กับบรรทัด "หน้านี้อ่านข้อมูล…" ออกทั้งคู่
        */
        $this->assertStringContainsString('เข้าสู่หน้าเว็บ', $html);
        $this->assertStringNotContainsString('หน้านี้อ่านข้อมูลจากระบบ', $html);
    }

    /** ล็อกอินแล้วและมีสิทธิ์เปิดเอกสาร → เห็นยอดเงินและลายเซ็นเพิ่ม */
    public function test_a_signed_in_owner_sees_the_money_and_signatures(): void
    {
        $budget = $this->approvedBudget();
        $invest = $budget->invest;
        $acc = AppUser::where('employee_code', 'ACC')->firstOrFail();

        $html = $this->signIn($acc)->get('/track/'.$invest->track_token)->assertOk()->getContent();
        $markup = $this->markupOf($html);

        $this->assertStringContainsString(number_format((float) $budget->approved_amount, 2), $markup);
        $this->assertStringContainsString('<span class="tk-sign">', $markup);
        $this->assertStringContainsString('เปิดเอกสารเต็ม', $html);
    }

    /** 🔴 ล็อกอินอยู่แต่ไม่ได้อยู่ในเอกสารใบนั้น = ยังไม่เห็นยอดเงิน (ใช้ด่านเดิมของเอกสาร) */
    public function test_a_signed_in_outsider_still_cannot_see_the_money(): void
    {
        $budget = $this->approvedBudget();
        $invest = $budget->invest;
        $outsider = AppUser::where('employee_code', 'REV')->firstOrFail();

        $markup = $this->markupOf(
            $this->signIn($outsider)->get('/track/'.$invest->track_token)->assertOk()->getContent()
        );

        $this->assertStringNotContainsString(number_format((float) $budget->approved_amount, 2), $markup);
        $this->assertStringNotContainsString('<span class="tk-sign">', $markup);
    }

    /** เลขที่งบขึ้นเฉพาะตอนเซ็นครบแล้ว (เจ้าของกำหนด) */
    public function test_the_budget_number_appears_only_after_the_last_signature(): void
    {
        [, $invest, , $ceo] = $this->submitted();

        $this->flushSession();
        $before = $this->get('/track/'.$invest->track_token)->assertOk()->getContent();
        /*
          🔴 ป้ายชื่อเป็น "เลขที่งบประมาณ" เสมอ เปลี่ยนแค่ค่า (เจ้าของกำหนดรูปแบบ 2026-09-18)
             ยังเซ็นไม่ครบ = โชว์เลขที่ INV · เซ็นครบแล้ว = โชว์เลขที่ BGT
             ไม่โชว์ทั้ง 2 เลขพร้อมกัน คนสแกนจะได้ไม่งงว่าต้องอ้างอิงเลขไหน
        */
        $this->assertStringContainsString($invest->doc_no, $before);
        $this->assertStringNotContainsString('BGT-', $this->markupOf($before), 'ยังเซ็นไม่ครบ ต้องยังไม่มีเลขที่งบ');
        $this->assertStringContainsString('รอ ทดสอบ CEO ลงนาม', $before, 'ต้องบอกว่าตอนนี้รอใคร');

        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/approve');
        $budget = Budget::firstOrFail();

        $this->flushSession();
        $after = $this->get('/track/'.$invest->track_token)->assertOk()->getContent();
        $this->assertStringContainsString('เลขที่งบประมาณ', $after);
        $this->assertStringContainsString($budget->doc_no, $after);
        // เซ็นครบแล้วต้องโชว์เลขที่งบแทนเลขที่ INV (ไม่ใช่โชว์คู่กัน)
        $this->assertStringNotContainsString($invest->doc_no, $this->markupOf($after));
        $this->assertStringContainsString('รอฝ่ายบัญชีลงทะเบียนเข้า ERP', $after, 'ปลายทางจริงของเรื่องคือการคีย์เข้า ERP');
    }

    /** 🔴 กุญแจมั่วหรือผิดรูปต้องได้ 404 และตอบเหมือนกันทุกกรณี (ห้ามบอกว่ากุญแจไหนเกือบถูก) */
    public function test_a_bad_key_always_answers_the_same_way(): void
    {
        $this->approvedBudget();

        $this->flushSession();
        $fake = $this->get('/track/'.str_repeat('a', 32));
        $malformed = $this->get('/track/xxx');

        $fake->assertNotFound();
        $malformed->assertNotFound();

        $this->assertStringContainsString('ไม่พบเอกสารนี้', $fake->getContent());
        $this->assertSame($fake->getContent(), $malformed->getContent(), 'ต้องตอบเหมือนกันเป๊ะ');
    }

    /*
    |---------------------------------------------------------------
    | ตัวช่วย
    |---------------------------------------------------------------
    */

    /** เอาเฉพาะมาร์กอัปจริง — ตัด <style> กับ <script> (พจนานุกรมคำแปล/ชื่อคลาสอยู่ในนั้น) */
    private function markupOf(string $html): string
    {
        $out = preg_replace('~<style[^>]*>.*?</style>~s', '', $html);

        return (string) preg_replace('~<script[^>]*>.*?</script>~s', '', (string) $out);
    }

    /** @return array{0:AppUser,1:Invest,2:AppUser,3:AppUser} */
    private function submitted(bool $draftOnly = false): array
    {
        $acc = $this->user('ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $outsider = $this->user('REV', ['fn' => BudgetAccess::FN_INBOX]);
        $ceo = $this->user('CEO', ['fn' => BudgetAccess::FN_INBOX]);

        $this->signIn($acc)->post('/budget/invest', $this->payload());
        $invest = Invest::firstOrFail();

        if (! $draftOnly) {
            $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
                'approvers' => [$ceo->employee_code],
            ])->assertRedirect();

            $invest->refresh();
            $this->assertSame(Invest::PENDING, $invest->approval_status);
        }

        return [$acc, $invest, $outsider, $ceo];
    }

    private function approvedBudget(): Budget
    {
        [, $invest, , $ceo] = $this->submitted();

        $this->signIn($ceo)->post('/budget/doc/'.$invest->id.'/approve');

        return Budget::with('invest')->firstOrFail();
    }

    private function user(string $code, array $attributes = []): AppUser
    {
        static $seq = 0;
        $seq++;

        Employee::create([
            'insight_id' => 5000 + $seq,
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
            'insight_id' => 4000 + $seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'password' => 'secret',
            'role' => $attributes['role'] ?? 'user',
            'full_name_th' => 'ทดสอบ '.$code,
            'dept_code' => 'D01',
            'signature' => 'data:image/png;base64,TEST',
        ]);

        foreach ((array) ($attributes['fn'] ?? []) as $fnKey) {
            FunctionUser::create([
                'module_id' => BudgetAccess::MODULE,
                'function_key' => $fnKey,
                'employee_code' => $code,
            ]);
        }

        return $user;
    }

    private function signIn(AppUser $user): self
    {
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);
        $this->actor = $user;

        return $this;
    }

    /** @return array<string,mixed> */
    private function payload(array $override = []): array
    {
        return array_merge([
            'group_id' => $this->docGroup->id,
            'fiscal_year' => 2569,
            'proposer_code' => (string) ($this->actor?->employee_code ?: 'ACC'),
            'dept_code' => 'D01',
            'title' => 'งบจัดหาเครื่องคอมพิวเตอร์ฝ่ายผลิต',
            'description' => 'ทดแทนเครื่องเดิมที่หมดอายุการใช้งาน',
            'amount' => 90000,
            'approvers' => FunctionUser::where('function_key', BudgetAccess::FN_INBOX)
                ->pluck('employee_code')->map(strval(...))->all(),
        ], $override);
    }

    /** กันลืม: ผู้รับทราบต้องไม่โผล่ในคิวลงนามของหน้าติดตาม */
    public function test_acknowledgers_are_listed_separately(): void
    {
        $budget = $this->approvedBudget();
        $invest = $budget->invest;

        Approval::create([
            'doc_type' => Approval::DOC_INVEST,
            'doc_id' => $invest->id,
            'round' => (int) $invest->approvals->max('round'),
            'step' => 9,
            'action' => Approval::ACK,
            'employee_code' => 'REV',
            'employee_name' => 'ทดสอบ REV',
            'position' => 'เจ้าหน้าที่',
            'department' => 'แผนกทดสอบ',
            'status' => Approval::NOTIFIED,
        ]);

        $this->flushSession();
        $html = $this->get('/track/'.$invest->track_token)->assertOk()->getContent();

        $this->assertStringContainsString('สำเนาเรียน (เพื่อทราบ)', $html);
    }
}
