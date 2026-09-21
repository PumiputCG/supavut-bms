<?php

namespace Tests\Feature\Budget;

use App\Http\Middleware\Authenticate;
use App\Models\Access\FunctionUser;
use App\Models\Budget\DocGroup;
use App\Models\Budget\Invest;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Services\Budget\BudgetAccess;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * หน้า "ดูเอกสาร" ของของบประมาณ (/budget/invest/{id}/edit โหมดดู)
 *
 * 🔴 เจ้าของแจ้ง 2026-09-21: ส่งเอกสารแล้วต้องดาวน์โหลด PDF ได้ "เพื่อเขาจะได้ดาวน์โหลดและติดตาม"
 *    และ **บรรทัดในเอกสารต้องเท่ากับหน้าดูเอกสารหน้าอื่น**
 *    ของเดิมหน้านี้เขียนตัวเอกสารซ้ำเป็นสำเนาของตัวเอง แล้วหลุดจากกันไปจริง
 */
class InvestViewTest extends TestCase
{
    use RefreshModuleDatabase;

    private DocGroup $docGroup;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bms.final_approver' => '']);
        // ให้ available() คืนค่าจริงแบบคาดเดาได้ ไม่ขึ้นกับว่าเครื่องที่รันเทสต์มีเบราว์เซอร์ไหม
        config(['bms.pdf.browser' => base_path('artisan')]);

        $this->docGroup = DocGroup::create([
            'code' => 'NM', 'name_th' => 'โมเดลใหม่', 'name_en' => 'New Model', 'sort' => 1,
        ]);
    }

    public function test_a_submitted_document_offers_a_pdf_download(): void
    {
        [$acc, $invest] = $this->submitted();

        $html = $this->signIn($acc)->get('/budget/invest/'.$invest->id.'/edit')->assertOk()->getContent();

        /*
          🔴 วัดที่ "ลิงก์จริงของปุ่ม" ไม่ใช่ที่ชื่อ attribute
             `data-download` โผล่ในสคริปต์ท้ายหน้าของทุกหน้าอยู่แล้ว ใช้เป็นตัวชี้วัดไม่ได้
             (บทเรียนซ้ำรอบที่ 5 — เคยพลาดแบบเดียวกันกับ `cv-kind` · `data-dash-card` · `cv-partial`)
          🔴 และต้องมี download คู่กับ data-download ไม่งั้นจอ "กำลังโหลด" ค้าง (บทเรียน 2026-09-16)
        */
        $button = 'download data-download href="'.route('budget.doc.pdf', $invest).'"';

        /*
          🔴 ใช้ assertTrue + str_contains ไม่ใช่ assertStringContainsString
             เพราะหน้านี้ใหญ่ ~400 KB — เวลาเทสต์ตก PHPUnit จะไปนั่งทำ diff ของสตริงทั้งก้อน
             กินซีพียูเป็นนาทีจนดูเหมือนเทสต์ค้าง (เจอจริงตอนเขียนเทสต์นี้)
        */
        $this->assertTrue(str_contains($html, $button), 'ไม่พบปุ่มดาวน์โหลด PDF — มองหา: '.$button);
    }

    /** ร่างยังไม่มีเลขที่ ไม่มี QR และเนื้อหายังแก้ได้ทุกบรรทัด — ยังไม่ควรมีไฟล์ให้ถือออกไป */
    public function test_a_draft_has_no_pdf_button(): void
    {
        $acc = $this->user('V_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('V_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->draft($acc, [$signer->employee_code]);
        $invest = Invest::orderByDesc('id')->firstOrFail();

        $this->assertFalse($invest->hasNumber(), 'ร่างต้องยังไม่มีเลขที่');

        $html = $this->signIn($acc)->get('/budget/invest/'.$invest->id.'/edit')->assertOk()->getContent();

        // วัดที่ลิงก์จริงเช่นกัน — ชื่อ attribute มีอยู่ในสคริปต์ของทุกหน้า
        $this->assertFalse(
            str_contains($html, route('budget.doc.pdf', $invest)),
            'ร่างไม่ควรมีลิงก์ดาวน์โหลด PDF',
        );
    }

    /**
     * 🔴 หัวใจของรอบนี้: บรรทัดในเอกสารต้องเหมือนหน้า /budget/doc/{id} เป๊ะ
     *
     * เทียบ "มาร์กอัปจริง" ของบล็อกบรรทัดเอกสาร ไม่ใช่เทียบแค่ชื่อคลาส
     * (บทเรียนซ้ำ: ชื่อคลาสโผล่ในบล็อก style ด้วย ใช้เป็นตัวชี้วัดไม่ได้)
     */
    public function test_the_document_rows_match_the_other_document_page(): void
    {
        [$acc, $invest] = $this->submitted();

        $mine = $this->rows($this->signIn($acc)->get('/budget/invest/'.$invest->id.'/edit')->assertOk()->getContent());
        $theirs = $this->rows($this->signIn($acc)->get('/budget/doc/'.$invest->id)->assertOk()->getContent());

        $this->assertNotSame('', $mine, 'หาบล็อกบรรทัดเอกสารในหน้าของบประมาณไม่เจอ');
        $this->assertSame($theirs, $mine, 'บรรทัดในเอกสาร 2 หน้าไม่ตรงกัน — แปลว่ามีใครเขียนเนื้อเอกสารซ้ำอีกชุด');
    }

    // ── ตัวช่วย ────────────────────────────────────────────────

    /** ดึงเฉพาะบล็อก <dl class="paper-rows"> … </dl> ออกมาเทียบ */
    private function rows(string $html): string
    {
        preg_match('/<dl class="paper-rows">(.*?)<\/dl>/s', $html, $m);

        return trim($m[1] ?? '');
    }

    /** @return array{0:AppUser,1:Invest} */
    private function submitted(): array
    {
        $acc = $this->user('V2_ACC', ['fn' => BudgetAccess::FN_PROPOSE]);
        $signer = $this->user('V2_SIGN', ['fn' => BudgetAccess::FN_INBOX]);

        $this->draft($acc, [$signer->employee_code]);
        $invest = Invest::orderByDesc('id')->firstOrFail();

        $this->signIn($acc)->post('/budget/invest/'.$invest->id.'/submit', [
            'approvers' => [$signer->employee_code],
            'roles' => ['approve'],
        ])->assertRedirect();

        return [$acc, $invest->refresh()];
    }

    /** @param  array<int,string>  $approvers */
    private function draft(AppUser $acc, array $approvers): void
    {
        $this->signIn($acc)->post('/budget/invest', [
            'group_id' => $this->docGroup->id,
            'fiscal_year' => 2569,
            'proposer_code' => $acc->employee_code,
            'dept_code' => 'D01',
            'title' => 'งบทดสอบหน้าดูเอกสาร',
            'amount' => 25000,
            'approvers' => $approvers,
            'roles' => array_fill(0, count($approvers), 'approve'),
        ]);
    }

    /** @param  array{fn?:string|array<int,string>}  $attributes */
    private function user(string $code, array $attributes = []): AppUser
    {
        $this->seq++;

        Employee::create([
            'insight_id' => 9000 + $this->seq,
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
            'insight_id' => 9000 + $this->seq,
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
}
