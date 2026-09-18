<?php

namespace Tests\Feature\Budget;

use App\Http\Middleware\Authenticate;
use App\Models\Access\FunctionUser;
use App\Models\Budget\Budget;
use App\Models\Budget\Invest;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Services\Access\AccessService;
use App\Support\TableFilter;
use Illuminate\Http\Request;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * ตัวกรองรายคอลัมน์ของตารางที่แบ่งหน้า
 *
 * 🔴 กติกาที่เทสต์ชุดนี้ยึดไว้ (เจ้าของแจ้ง 2026-09-04)
 *    "ค้นหาคอลัมน์แผนก IT ต้องขึ้น IT มาทุกรายการ" — ไม่ใช่เฉพาะแถวที่บังเอิญอยู่หน้าปัจจุบัน
 *    ของเดิมกรองด้วย JavaScript จากแถวที่วาดอยู่ ผลเลยผิดทันทีที่มีมากกว่า 1 หน้า
 */
class TableFilterTest extends TestCase
{
    use RefreshModuleDatabase;

    private const PER_PAGE = 20;

    /*
      🔴 ตัวกรองต้องกรองด้วย "คอลัมน์ของตารางที่เกี่ยวข้อง" ได้ (เจ้าของสั่ง 2026-09-10)
         หน้าประวัติงบประมาณกรองด้วย "การลงทะเบียน" ซึ่งอยู่ในตาราง budgets
         ไม่ใช่ budget_invests ที่เป็นตารางหลักของหน้านั้น
    */
    public function test_a_column_from_a_related_table_can_be_filtered(): void
    {
        $map = [
            'register' => [
                'column' => 'budget_status',
                'relation' => 'budget',
                'labels' => Budget::STATUS_LABELS,
            ],
        ];

        $waiting = $this->investWithBudget('รอคีย์', Budget::PENDING_REGISTER);
        $done = $this->investWithBudget('คีย์แล้ว', Budget::REGISTERED);
        $noBudget = Invest::create([
            'doc_no' => 'INV-TF-000003',
            'fiscal_year' => 2569,
            'dept_code' => 'D01',
            'dept_name' => 'D01 · ทดสอบ',
            'title' => 'ยังไม่อนุมัติ',
            'amount' => 100,
            'approval_status' => Invest::DRAFT,
            'created_by' => 'TF_X',
        ]);

        $pick = function (string $value) use ($map): array {
            $request = Request::create('/x?'.http_build_query(['f' => ['register' => $value]]), 'GET');
            $query = Invest::query();
            TableFilter::apply($query, $request, $map);

            return $query->pluck('id')->all();
        };

        $this->assertSame([$waiting->id], $pick(Budget::PENDING_REGISTER));
        $this->assertSame([$done->id], $pick(Budget::REGISTERED));

        // 🔴 "—" ของคอลัมน์แบบนี้ = ยังไม่มีแถวที่เกี่ยวข้องเลย ไม่ใช่ค่าว่างในคอลัมน์
        $this->assertSame([$noBudget->id], $pick('—'));
    }

    /** เอกสารหนึ่งใบพร้อมงบที่ผูกไว้ — ใช้เฉพาะเทสต์ตัวกรองข้ามตาราง */
    private function investWithBudget(string $title, string $status): Invest
    {
        $invest = Invest::create([
            'doc_no' => 'INV-TF-'.str_pad((string) (Invest::count() + 1), 6, '0', STR_PAD_LEFT),
            'fiscal_year' => 2569,
            'dept_code' => 'D01',
            'dept_name' => 'D01 · ทดสอบ',
            'title' => $title,
            'amount' => 500,
            'approval_status' => Invest::APPROVED,
            'created_by' => 'TF_X',
        ]);

        Budget::create([
            'doc_no' => 'BGT-TF-'.$invest->id,
            'invest_id' => $invest->id,
            'fiscal_year' => 2569,
            'dept_code' => 'D01',
            'dept_name' => 'D01 · ทดสอบ',
            'title' => $title,
            'approved_amount' => 500,
            'approval_status' => Invest::APPROVED,
            'budget_status' => $status,
            'approved_at' => now(),
            'approved_by' => 'TF_X',
        ]);

        return $invest;
    }

    public function test_filtering_a_column_reaches_rows_on_every_page(): void
    {
        // แผนก IT มี 25 ก้อน = เกิน 1 หน้า · แผนกอื่นอีก 30 ก้อนไว้เป็นตัวรบกวน
        $this->budgets('RVT002 · IT', 25, 1000);
        $this->budgets('SPV001 · ACC', 30, 7000);

        $html = $this->signIn($this->user('F_VIEW'))
            ->get('/budget/list?'.http_build_query(['f' => ['dept' => 'RVT002 · IT']]))
            ->assertOk()
            ->getContent();

        // หน้าแรกต้องเต็ม 20 แถว และเป็นของ IT ล้วน — ถ้ากรองแค่หน้าเดียวจะได้ไม่ถึง 20
        $this->assertSame(self::PER_PAGE, substr_count($html, '<td class="col-seq">'));
        $this->assertStringNotContainsString('SPV001 · ACC', $html);

        // ต้องมีหน้า 2 ให้กดต่อ และลิงก์ต้องพาตัวกรองไปด้วย
        $this->assertStringContainsString('page=2', $html);
        $this->assertStringContainsString('dept', $html);
    }

    public function test_the_second_page_stays_filtered(): void
    {
        $this->budgets('RVT002 · IT', 25, 1000);
        $this->budgets('SPV001 · ACC', 30, 7000);

        $html = $this->signIn($this->user('F_PAGE2'))
            ->get('/budget/list?'.http_build_query(['f' => ['dept' => 'RVT002 · IT'], 'page' => 2]))
            ->assertOk()
            ->getContent();

        // เหลือ 5 แถวสุดท้ายของ IT · ห้ามมีแผนกอื่นหลุดมา
        $this->assertSame(5, substr_count($html, '<td class="col-seq">'));
        $this->assertStringNotContainsString('SPV001 · ACC', $html);
    }

    public function test_the_total_follows_the_filter_across_all_pages(): void
    {
        // 🔴 ยอดรวมต้องเป็นของ "ทุกแถวที่ผ่านตัวกรอง" ไม่ใช่แค่ 20 แถวในหน้านี้
        $this->budgets('RVT002 · IT', 25, 1000);      // 25 ก้อน ก้อนละ 1,000 = 25,000
        $this->budgets('SPV001 · ACC', 30, 7000);

        $html = $this->signIn($this->user('F_SUM'))
            ->get('/budget/list?'.http_build_query(['f' => ['dept' => 'RVT002 · IT']]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('25,000.00', $html);
        $this->assertStringContainsString('budget.filteredTotal', $html);   // ป้ายต้องเปลี่ยนเป็น "ตามที่กรอง"
    }

    public function test_without_a_filter_the_total_covers_everything(): void
    {
        $this->budgets('RVT002 · IT', 25, 1000);      // 25,000
        $this->budgets('SPV001 · ACC', 30, 7000);     // 210,000  → รวม 235,000

        $html = $this->signIn($this->user('F_ALL'))->get('/budget/list')->assertOk()->getContent();

        $this->assertStringContainsString('235,000.00', $html);
        $this->assertStringContainsString('budget.allTotal', $html);
    }

    public function test_the_value_list_comes_from_the_whole_table_not_one_page(): void
    {
        /*
          🔴 แผง "เลือกค่า" ต้องมีครบทุกแผนกที่มีอยู่จริง
             ถ้าเอาค่าจากแถวในหน้าปัจจุบัน แผนกที่อยู่หน้า 2 จะเลือกไม่ได้เลย
        */
        $this->budgets('SPV001 · ACC', 25, 1000);      // กินหน้าแรกจนเต็ม
        $this->budgets('ZZZ999 · หลังสุด', 1, 500);     // อยู่หน้า 2 แน่ๆ

        $html = $this->signIn($this->user('F_OPTS'))->get('/budget/list')->assertOk()->getContent();

        $this->assertStringContainsString('data-filter-options', $html);
        $this->assertStringContainsString('ZZZ999', $html);
    }

    public function test_the_history_summary_strip_follows_the_filter(): void
    {
        /*
          🔴 แถบสรุปหัวตาราง (เอกสารทั้งหมด/อนุมัติแล้ว/วงเงินรวม) ต้องคิดจากชุดที่กรองแล้ว
             ถ้ายังเป็นตัวเลขของทั้งบริษัท ผู้ใช้จะอ่านผิดทันที (เจ้าของแจ้ง 2026-09-07)
        */
        $this->invests('RVT002 · IT', 2, 450000);
        $this->invests('SPV004 · HRM', 1, 600000);

        $user = $this->user('H_SUM');

        // ไม่กรอง — เห็นทั้งหมด 3 ฉบับ · วงเงินรวม 1,500,000
        $all = $this->signIn($user)->get('/budget/history')->assertOk()->getContent();
        $this->assertStringContainsString('1,500,000.00', $all);

        // กรองแผนก IT — เหลือ 2 ฉบับ · วงเงิน 900,000
        $one = $this->signIn($user)
            ->get('/budget/history?'.http_build_query(['f' => ['dept' => 'RVT002 · IT']]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('900,000.00', $one);
        $this->assertStringNotContainsString('1,500,000.00', $one);
        $this->assertStringNotContainsString('SPV004 · HRM', $one);
    }

    // ── ตัวช่วย ─────────────────────────────────────────────────────

    private int $seq = 0;

    private bool $opened = false;

    /**
     * เปิดหน้าที่เทสต์ชุดนี้ต้องเข้าให้ "ทุกคน"
     *
     * 🔴 ตั้งแต่ 2026-09-07 ไม่กำหนดสิทธิ์ = เข้าไม่ได้ เทสต์จึงต้องเปิดสิทธิ์เองก่อน
     *    เรียกครั้งเดียวพอ — เรียกซ้ำจะได้แถวซ้ำในตารางสิทธิ์
     */
    private function openPages(): void
    {
        if ($this->opened) {
            return;
        }

        $this->opened = true;

        foreach (['fn.budget.approved', 'fn.budget.history'] as $key) {
            FunctionUser::create([
                'module_id' => 'budget',
                'function_key' => $key,
                'employee_code' => AccessService::EVERYONE,
            ]);
        }
    }

    /** สร้างเอกสารขอตั้งงบที่อนุมัติแล้ว พร้อมก้อนงบที่ผูกกัน */
    private function invests(string $dept, int $count, float $amount): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->seq++;

            $inv = Invest::create([
                'doc_no' => 'INV-F-'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT),
                'fiscal_year' => 2569,
                'dept_code' => substr($dept, 0, 6),
                'dept_name' => $dept,
                'title' => 'เอกสารทดสอบ '.$this->seq,
                'amount' => $amount,
                'approval_status' => Invest::APPROVED,
                'created_by' => 'TESTER',
            ]);

            Budget::create([
                'doc_no' => 'BGT-F-'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT),
                'invest_id' => $inv->id,
                'fiscal_year' => 2569,
                'dept_code' => $inv->dept_code,
                'dept_name' => $dept,
                'title' => $inv->title,
                'approved_amount' => $amount,
                'approval_status' => Invest::APPROVED,
                'budget_status' => Budget::PENDING_REGISTER,
            ]);
        }
    }

    private function budgets(string $dept, int $count, float $amount): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->seq++;

            Budget::create([
                'doc_no' => 'BGT-F-'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT),
                'fiscal_year' => 2569,
                'dept_code' => substr($dept, 0, 6),
                'dept_name' => $dept,
                'title' => 'งบทดสอบ '.$this->seq,
                'approved_amount' => $amount,
                'approval_status' => 'APPROVED',
                'budget_status' => Budget::PENDING_REGISTER,
            ]);
        }
    }

    private function user(string $code): AppUser
    {
        $this->openPages();

        Employee::create([
            'insight_id' => 8000 + strlen($code) + $this->seq,
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

        return AppUser::create([
            'insight_id' => 8000 + strlen($code) + $this->seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'password' => 'secret',
            'role' => 'user',
            'full_name_th' => 'ทดสอบ '.$code,
            'dept_code' => 'D01',
        ]);

    }

    private function signIn(AppUser $user): self
    {
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        return $this;
    }
}
