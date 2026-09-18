<?php

namespace Tests\Feature\Core;

use App\Http\Middleware\Authenticate;
use App\Models\Access\FunctionUser;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Services\Access\AccessService;
use App\Services\Erp\ErpDashboard;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\Fixtures\ErpDashboardFixture as Fixture;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshModuleDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ตัวกรองปีค่าเริ่มต้น = ปีปัจจุบัน — ล็อกวันที่ให้ตรงกับข้อมูลทดสอบ (งบ BG26) เทสต์จะไม่พังเมื่อข้ามปี
        Carbon::setTestNow('2026-09-14 10:00:00');
    }

    private function user(bool $budget = true, bool $dashboard = true): AppUser
    {
        FunctionUser::whereIn('function_key', ['fn.budget.approved', 'fn.overview.dashboard'])->delete();
        if ($budget) {
            FunctionUser::create(['module_id' => 'budget', 'function_key' => 'fn.budget.approved', 'employee_code' => AccessService::EVERYONE]);
        }
        if ($dashboard) {
            FunctionUser::create(['module_id' => 'dashboard', 'function_key' => 'fn.overview.dashboard', 'employee_code' => AccessService::EVERYONE]);
        }
        Employee::create(['insight_id' => 9901, 'company' => 'TEST', 'employee_code' => 'TEST01', 'name_th' => 'ทดสอบ',
            'surname_th' => 'Dashboard', 'dept_code' => 'D01', 'job_code' => 'S1', 'emp_status' => '1']);
        $user = AppUser::create(['insight_id' => 9901, 'company' => 'TEST', 'employee_code' => 'TEST01',
            'password' => 'secret', 'role' => 'user', 'full_name_th' => 'ทดสอบ Dashboard', 'dept_code' => 'D01']);
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        return $user;
    }

    private function source(?array $rows = null): MockInterface
    {
        $rows ??= Fixture::rows();
        $mock = Mockery::mock(ErpDashboard::class);
        $mock->shouldReceive('snapshot')->andReturn($rows);
        // ตัวเลือกตัวกรองอ่านแยกจาก ERP แบบรวมกลุ่ม — จำลองจากถังชุดเดียวกัน (ไม่มียอดเงิน/ชื่องบ)
        $mock->shouldReceive('scopes')->andReturn(array_map(
            fn ($r) => array_intersect_key($r, array_flip(['company', 'dept', 'model', 'purpose', 'currency', 'year', 'quarter', 'dept_key', 'dept_name', 'dept_group'])) + ['rows' => 1],
            $rows,
        ));
        $this->instance(ErpDashboard::class, $mock);

        return $mock;
    }

    public function test_guests_cannot_read_dashboard_or_details(): void
    {
        $mock = Mockery::mock(ErpDashboard::class);
        $mock->shouldNotReceive('snapshot');
        $this->instance(ErpDashboard::class, $mock);
        $this->get('/dashboard')->assertRedirect();
        $this->getJson('/dashboard/spend?dept=si5%7CSPV-02')->assertRedirect();
    }

    public function test_negative_budget_alerts_find_the_correct_page_without_netting_departments_or_currencies(): void
    {
        $this->user();
        $rows = [];
        for ($i = 0; $i < 27; $i++) {
            $row = Fixture::rows()[0];
            $row['id'] = (string) (1000 + $i);
            $row['budget_no'] = 'ALERT-'.$i;
            $row['title'] = 'Budget '.$i;
            $row['budget'] = 10000000 - $i * 100;
            $row['available'] = $i === 26 ? -500000 : 3000000;
            $row['group'] = ErpDashboard::group_key($row);
            $rows[] = $row;
        }
        $fx = $rows[26];
        $fx['id'] = '2000';
        $fx['currency'] = 'JPP';
        $fx['available'] = -20000;
        $rows[] = $fx;
        $this->source($rows);
        $overview = $this->get('/dashboard?year=2026')->assertOk()->assertSee('Negative budget balances');
        $item = $overview->viewData('chart')['items'][0];
        $this->assertGreaterThan(0, $item['v']['available']);
        $this->assertCount(2, $item['alerts']);
        $this->assertSame(['THB', 'JPY'], array_column($item['alerts'], 'currency'));
        foreach ($item['alerts'] as $alert) {
            $target = $this->get($alert['href'])->assertOk()->assertSee('Budget 26');
            $this->assertStringContainsString('id="'.parse_url($alert['href'], PHP_URL_FRAGMENT).'"', $target->getContent());
            $target->assertDontSee('>Budget 0<', false);
            $this->assertSame('2026', $target->viewData('filters')['year']);
        }
        if (getenv('DASHBOARD_VISUAL_PREVIEW')) {
            file_put_contents(storage_path('framework/testing/dashboard-preview/alerts.html'), $overview->getContent());
            file_put_contents(storage_path('framework/testing/dashboard-preview/alert-target.html'), $target->getContent());
        }
    }

    public function test_alert_link_marks_all_negative_budgets_across_pages_without_expanding_overview(): void
    {
        $this->user();
        $rows = [];
        for ($i = 0; $i < 32; $i++) {
            $row = Fixture::rows()[0];
            $row['id'] = (string) (3000 + $i);
            $row['budget_no'] = 'NEG-'.$i;
            $row['title'] = 'Negative test '.$i;
            $row['budget'] = 10000000 - $i;
            $row['available'] = $i < 2 ? 100000000 : -100;
            $row['group'] = ErpDashboard::group_key($row);
            $rows[] = $row;
        }
        $this->source($rows);
        $overview = $this->get('/dashboard?year=2026')->assertOk()->assertDontSee('data-alert-panels', false);
        $item = $overview->viewData('chart')['items'][0];
        $this->assertCount(30, $item['alerts']);
        $target = $this->get($item['alerts_href'])->assertOk();
        $this->assertSame(25, substr_count($target->getContent(), 'class="bd-negative-row"'));
        $this->assertSame('2026', $target->viewData('filters')['year']);
        $next = $this->get(str_replace('#negative-budgets', '&page=2', $item['alerts_href']))->assertOk();
        $this->assertSame(5, substr_count($next->getContent(), 'class="bd-negative-row"'));
        $next->assertDontSee('>Negative test 0<', false)->assertDontSee('>Negative test 1<', false);
        $this->assertCount(30, $target->viewData('groups'));
        $target->assertSee('All budgets')->assertSee('Negative balance budgets');
        $all_url = str_replace('highlight=negative', 'highlight=', explode('#', $item['alerts_href'])[0]);
        $all = $this->get($all_url)->assertOk();
        $this->assertCount(32, $all->viewData('groups'));
        $this->assertSame(23, substr_count($all->getContent(), 'class="bd-negative-row"'));
    }

    public function test_budget_subset_identifies_scope_and_links_back_to_department(): void
    {
        $this->user();
        $source = $this->source();
        $selected = Fixture::rows()[0];
        $source->shouldReceive('activity')->once()->withArgs(fn ($rows, $kind, $page) => array_column($rows, 'id') === ['101', '102'] && $kind === 'actual' && $page === 1
        )->andReturn(Fixture::activity());
        $query = ['dept' => $selected['dept_group'], 'year' => '2026', 'budget' => $selected['group'], 'view' => 'activity', 'kind' => 'actual'];
        $page = $this->get('/dashboard?'.http_build_query($query))->assertOk()
            ->assertSee('Training expense')->assertSee('Budget reference')->assertSee('BG26Q1')
            ->assertDontSee('Back to budgets')->assertDontSee('View source allocations and periods');
        $this->assertSame(['101', '102'], array_column($page->viewData('rows'), 'id'));
        $this->get('/dashboard?'.http_build_query(['dept' => $selected['dept_group'], 'year' => '2026', 'view' => 'budgets']))
            ->assertOk()->assertSee('Training expense')->assertDontSee('Department / Model')->assertDontSee('budget allocations');
    }

    public function test_both_views_require_the_dashboard_permission(): void
    {
        $this->user(true, false);
        $mock = Mockery::mock(ErpDashboard::class);
        $mock->shouldNotReceive('snapshot');
        $this->instance(ErpDashboard::class, $mock);
        $this->get('/dashboard')->assertForbidden();
        $this->getJson('/dashboard/spend?dept=si5%7CSPV-02')->assertForbidden();
    }

    public function test_erp_department_label_is_used_in_filters_heading_and_chart(): void
    {
        $this->user();
        $this->source();
        $page = $this->get('/dashboard?year=2026&dept=si5%7CSPV-02&view=budgets')->assertOk()
            // หัวเรื่องกลายเป็นแถบแท็บ (เจ้าของสั่ง 2026-09-17) ชื่อแผนกย้ายไปอยู่ชิดขวาบนเส้นเดียวกัน
            ->assertSee('class="bd-head-dept"', false)->assertSee('แผนก HR : Human Resource')->assertDontSee('HRM');
        $this->assertSame('HR : Human Resource', $page->viewData('deptOptions')['si5|SPV-02']['th']);
        $this->assertSame('HR : Human Resource', $page->viewData('deptOptions')['si5|SPV-02']['en']);
        $this->assertSame('HR : Human Resource', $page->viewData('chart')['items'][0]['name_th']);
    }

    public function test_department_options_only_include_budgets_in_the_selected_year(): void
    {
        $this->user();
        $this->source();
        $current = $this->get('/dashboard?company=si5&year=2026')->assertOk();
        $this->assertSame(['si5|SPV-02'], array_keys($current->viewData('deptOptions')));
        $unclassified = $this->get('/dashboard?company=si5&year=unassigned')->assertOk();
        $this->assertSame(['si5|SPV-06'], array_keys($unclassified->viewData('deptOptions')));
        $all = $this->get('/dashboard?company=si5&year=all')->assertOk();
        $this->assertCount(2, $all->viewData('deptOptions'));
        $stale = $this->get('/dashboard?company=si5&year=2026&dept=si5%7CSPV-06&view=activity')->assertRedirect();
        parse_str(parse_url($stale->headers->get('Location'), PHP_URL_QUERY), $target);
        $this->assertArrayNotHasKey('dept', $target);
        $this->assertSame('2026', $target['year']);
        $this->assertSame('overview', $target['view']);
    }

    /**
     * ตัวกรองไตรมาส (เจ้าของสั่ง 2026-09-16)
     *
     * 🔴 คุม 2 อย่างคู่กันเสมอ — "กรองได้จริง" กับ "ยอดไม่หาย"
     *    บวกทุกไตรมาสรวม "ไม่ระบุไตรมาส" แล้วต้องเท่ากับตอนไม่กรองเป๊ะ
     *    ถ้าคุมแค่ว่ากรองได้ วันหนึ่งงบโปรเจคที่ไม่มีรหัสไตรมาสจะหายเงียบๆ โดยไม่มีใครจับได้
     */
    public function test_quarter_filter_narrows_the_page_without_losing_any_budget(): void
    {
        $this->user();
        $this->source();

        // ปี 2026 มีไตรมาส 1 กับ 2 เท่านั้น และไม่มีงบที่ไม่ระบุไตรมาสเลย
        $year = $this->get('/dashboard?year=2026')->assertOk();
        $this->assertSame([1, 2], $year->viewData('quarters'));
        $this->assertFalse($year->viewData('hasUnassignedQuarter'));

        $whole = $year->viewData('total');
        $parts = ['count' => 0, 'budget' => 0];
        foreach (['1', '2'] as $quarter) {
            $total = $this->get('/dashboard?year=2026&quarter='.$quarter)->assertOk()->viewData('total');
            $parts['count'] += $total['count'];
            $parts['budget'] += $total['budget'];
        }
        $this->assertSame($whole['count'], $parts['count'], 'บวกทุกไตรมาสแล้วจำนวนรายการต้องเท่าเดิม');
        $this->assertSame($whole['budget'], $parts['budget'], 'บวกทุกไตรมาสแล้วยอดตั้งงบต้องเท่าเดิม');

        // งบโปรเจคที่ไม่มีรหัสไตรมาสต้องยังมีที่อยู่ ไม่ถูกกลืนหาย
        $unassigned = $this->get('/dashboard?year=unassigned')->assertOk();
        $this->assertSame([], $unassigned->viewData('quarters'));
        $this->assertTrue($unassigned->viewData('hasUnassignedQuarter'));
        $this->assertSame(
            $unassigned->viewData('total')['count'],
            $this->get('/dashboard?year=unassigned&quarter=unassigned')->assertOk()->viewData('total')['count'],
        );
    }

    /**
     * 🔴 ไตรมาสเป็นชั้นในสุด ต้องแคบตามแผนกที่เลือกด้วย (เจ้าของสั่ง 2026-09-16)
     *    "แผนกนั้นยังไม่มี Q4 ให้ไม่ต้องขึ้นในตัวกรอง" — ไม่ใช่ให้เลือกได้แล้วเจอจอว่าง
     */
    public function test_quarter_options_narrow_down_to_the_selected_department(): void
    {
        $this->user();
        $this->source();

        // ทั้งบริษัท si5 ปี 2026 มี Q1 กับ Q2
        $this->assertSame([1, 2], $this->get('/dashboard?company=si5&year=2026')->assertOk()->viewData('quarters'));

        // แต่แผนก SPV-06 (งบโปรเจค) ไม่มีไตรมาสเลย — ช่องไตรมาสต้องว่าง เหลือแค่ "ไม่ระบุไตรมาส"
        $project = $this->get('/dashboard?company=si5&year=all&dept=si5%7CSPV-06')->assertOk();
        $this->assertSame([], $project->viewData('quarters'));
        $this->assertTrue($project->viewData('hasUnassignedQuarter'));

        // เลือกไตรมาสไว้แล้วสลับไปแผนกที่ไม่มีไตรมาสนั้น -> ล้างเฉพาะไตรมาส แผนกต้องอยู่ต่อ
        $stale = $this->get('/dashboard?company=si5&year=all&quarter=2&dept=si5%7CSPV-06');
        $stale->assertRedirect();
        parse_str(parse_url($stale->headers->get('Location'), PHP_URL_QUERY), $target);
        $this->assertArrayNotHasKey('quarter', $target);
        $this->assertSame('si5|SPV-06', $target['dept'], 'ห้ามล้างแผนกทิ้ง ผู้ใช้เพิ่งเลือกเอง');
    }

    /** กดจากกราฟ/ตารางเข้าไปดูรายการงบ แล้วตัวกรองที่เลือกไว้ต้องติดไปด้วยทั้งชุด */
    public function test_drilling_into_budgets_keeps_every_filter(): void
    {
        $this->user();
        $this->source();
        $html = $this->get('/dashboard?company=si5&year=2026&quarter=1')->assertOk()->getContent();
        $this->assertStringContainsString('quarter=1', $html, 'ลิงก์ในหน้าต้องพาไตรมาสที่เลือกไปด้วย');

        $drill = $this->get('/dashboard?company=si5&year=2026&quarter=1&dept=si5%7CSPV-02&view=budgets')->assertOk();
        $this->assertSame('1', $drill->viewData('filters')['quarter']);
        $this->assertSame('si5|SPV-02', $drill->viewData('filters')['dept']);
        // และข้อมูลที่เห็นต้องถูกกรองจริง ไม่ใช่แค่ค่าค้างอยู่ใน URL
        foreach ($drill->viewData('rows') as $row) {
            $this->assertSame(1, $row['quarter']);
        }
    }

    /**
     * ปรับตัวกรองข้างบนแล้วต้อง "อยู่หน้าเดิม" (เจ้าของสั่ง 2026-09-16)
     *
     * 🔴 ของเดิมช่องซ่อนตั้งค่าตายตัวเป็น view=budgets และไม่พา kind ไปด้วย
     *    อยู่หน้า "ประวัติสั่งซื้อ" แล้วเปลี่ยนไตรมาส เลยเด้งไปหน้า "รายการงบ" ทุกที
     */
    public function test_changing_filters_keeps_the_tab_you_are_on(): void
    {
        $this->user();
        $this->source();

        foreach ([['budgets', null], ['activity', 'actual'], ['activity', 'purchases']] as [$view, $kind]) {
            $url = '/dashboard?year=2026&dept=si5%7CSPV-02&quarter=1&view='.$view.($kind ? '&kind='.$kind : '');
            $html = $this->get($url)->assertOk()->getContent();
            // ช่องซ่อนในฟอร์มตัวกรองต้องส่งหน้าที่เปิดอยู่จริงกลับไป ไม่ใช่ค่าตายตัว
            $this->assertStringContainsString('name="view" value="'.$view.'"', $html);
            if ($kind) {
                $this->assertStringContainsString('name="kind" value="'.$kind.'"', $html);
            }
        }

        // อยู่หน้าภาพรวม หรือยังไม่เลือกแผนก ต้องไม่ส่ง view ไปด้วย
        $this->assertStringNotContainsString(
            'name="view"',
            $this->get('/dashboard?year=2026')->assertOk()->getContent(),
        );
    }

    /** อยู่ในงบก้อนหนึ่งแล้วเปลี่ยนตัวกรองจนก้อนนั้นหลุดขอบเขต ต้องถอยออกมาแค่ระดับแผนก */
    public function test_leaving_a_budget_out_of_scope_steps_back_one_level_only(): void
    {
        $this->user();
        $this->source();
        $budget = Fixture::rows()[5]['group'];   // งบ BG25 — ไม่มีอยู่ในปี 2026

        $page = $this->get('/dashboard?year=2026&dept=si5%7CSPV-02&view=activity&kind=actual&budget='.$budget);
        $page->assertRedirect();
        parse_str(parse_url($page->headers->get('Location'), PHP_URL_QUERY), $target);

        $this->assertArrayNotHasKey('budget', $target, 'งบที่หลุดขอบเขตต้องถูกปลดออก');
        $this->assertSame('si5|SPV-02', $target['dept'], 'แผนกต้องอยู่ต่อ ผู้ใช้เลือกมาเอง');
        $this->assertSame('activity', $target['view'], 'ต้องอยู่แท็บเดิม');
        $this->assertSame('actual', $target['kind']);
    }

    /**
     * ปุ่มเปรียบเทียบ 2 ขอบเขต (เจ้าของสั่ง 2026-09-16)
     *
     * 🔴 ฐานคือ "ชุดที่กำลังดูอยู่" เสมอ · เพิ่มขึ้นเขียว ลดลงแดง
     *    และฐานเป็น 0 ต้องคืน null ห้ามแทนด้วย 0% หรือ 100% ซึ่งโกหกคนอ่านทั้งคู่
     */
    public function test_compare_measures_the_other_scope_against_what_you_are_viewing(): void
    {
        $this->user();
        $this->source();

        // ยังไม่ได้สั่งเทียบ = ไม่มีผลเทียบ
        $this->assertNull($this->get('/dashboard?year=2026')->assertOk()->viewData('compare'));

        $page = $this->get('/dashboard?year=2026&'.http_build_query(['cmp' => ['year' => '2025']]))->assertOk();
        $compare = $page->viewData('compare');
        $this->assertNotNull($compare);

        $mine = ErpDashboard::totals(ErpDashboard::filter(Fixture::rows(), ['year' => '2026']));
        $other = ErpDashboard::totals(ErpDashboard::filter(Fixture::rows(), ['year' => '2025']));

        foreach (['budget', 'actual', 'reserve', 'available'] as $key) {
            $this->assertSame($mine[$key], $compare['fields'][$key]['a'], 'ชุด ก. ต้องเป็นขอบเขตที่กำลังดูอยู่');
            $this->assertSame($other[$key], $compare['fields'][$key]['b']);
            $this->assertSame($other[$key] - $mine[$key], $compare['fields'][$key]['diff']);
        }

        // เปอร์เซ็นต์คิดจากชุด ก. เป็นฐาน
        $budget = $compare['fields']['budget'];
        $this->assertEqualsWithDelta(
            ($budget['b'] - $budget['a']) / abs($budget['a']) * 100,
            $budget['pct'],
            0.0001,
        );
    }

    /** 🔴 ฐานเป็น 0 คิดเปอร์เซ็นต์ไม่ได้ — ต้องคืน null ให้หน้าจอขึ้นขีดกลาง */
    public function test_compare_reports_no_percentage_when_the_base_is_zero(): void
    {
        $this->user();
        $this->source();

        // ปีที่ไม่มีงบเลย = ฐานเป็น 0 ทุกช่อง
        $compare = $this->get('/dashboard?year=2019&'.http_build_query(['cmp' => ['year' => '2026']]))
            ->assertOk()->viewData('compare');

        $this->assertSame(0, $compare['fields']['budget']['a']);
        $this->assertNull($compare['fields']['budget']['pct'], 'ห้ามแทนด้วย 0% หรือ 100%');
    }

    /**
     * ป้าย YoY / QoQ (เจ้าของสั่ง 2026-09-16)
     *
     * 🔴 คิดจาก "ขอบเขตจริงของ 2 ชุด" ไม่ใช่รับธงมาจาก URL
     *    ถ้ารับธงมา ใครแก้ URL ก็ทำให้ป้ายโกหกได้ — ป้ายที่บอกผิดแย่กว่าไม่มีป้าย
     */
    /**
     * 🐛 บั๊กจริง 2026-09-17 (เจ้าของแจ้ง "ซูม 90% แล้วจัดระเบียบกันไม่สวย")
     *
     *    ช่องตัวกรองเป็น `width: auto` ความกว้างจึงยืด/หดตาม **ข้อความของตัวเลือกที่เลือกอยู่**
     *    เลือก "WD:Welding" กับ "ทุกแผนก" ได้คนละความกว้าง แถวเลยเบี้ยวและขยับทุกครั้งที่เปลี่ยนค่า
     *
     *    และการ์ดยอดเป็น `flex: 0 1 720px` ซึ่งโตไม่ได้ พอตัวกรองกว้างขึ้นจึงถูกบีบจนตัวเลขล้นช่อง
     */
    public function test_the_filter_row_and_summary_card_keep_a_fixed_layout(): void
    {
        $this->user();
        $this->source();

        $css = $this->get('/dashboard?year=2026')->assertOk()->getContent();

        // ช่องตัวกรองต้องล็อกความกว้าง ห้ามกลับไปยืดตามข้อความที่เลือก
        $this->assertMatchesRegularExpression('~\.bd-filter select \{[^}]*flex: 0 0 \d+px~s', $css);
        $this->assertDoesNotMatchRegularExpression('~\.bd-filter select \{[^}]*width: auto~s', $css);

        // การ์ดยอดต้องโตได้ และมีความกว้างขั้นต่ำกันตัวเลขล้น
        $this->assertMatchesRegularExpression('~\.bd-stats \{[^}]*flex: 1 1 \d+px~s', $css);
        $this->assertMatchesRegularExpression('~\.bd-stats \{[^}]*min-width: \d{3}px~s', $css);

        // 🔴 จอแคบต้องปลด min-width ไม่งั้นหน้าเลื่อนแนวนอนบนมือถือ
        $this->assertMatchesRegularExpression('~@media \(max-width: 820px\)[^@]*\.bd-stats \{[^}]*min-width: 0~s', $css);
    }

    /**
     * สรุปการใช้งบ = ใช้ไป ÷ ตั้งงบ (เจ้าของสั่ง 2026-09-17)
     *
     * 🔴 มีเพราะยอดบาทของ 2 ฝั่งคนละขนาด เทียบกันแล้ว % ไม่เท่ากันทั้ง 2 ทาง จนสรุปไม่ได้ว่าใครดีกว่า
     *    อัตราการใช้งบไม่ขึ้นกับขนาดของงบ จึงเทียบกันตรงๆ ได้
     * 🔴 ผลต่างเป็นหน่วย "จุด" (percentage point) ไม่ใช่ "%"
     */
    public function test_the_utilisation_summary_compares_rates_not_amounts(): void
    {
        $this->user();

        /*
          🔴 ข้อมูลตั้งต้นของเทสต์ทุกแถวมีสัดส่วน ใช้ไป ÷ ตั้งงบ เท่ากันหมด (60%)
             แบ่งปีอย่างไรอัตราก็เท่ากัน ผลต่างเป็น 0 และ "ไม่มีสีให้วัด"
             จึงดันฝั่งปี 2025 ให้ใช้งบ 90% เพื่อให้มีฝั่งใช้มาก/ใช้น้อยจริง
        */
        $rows = Fixture::rows();
        foreach ($rows as $i => $row) {
            if ((int) $row['year'] === 2025) {
                $rows[$i]['actual'] = 9000000;
                $rows[$i]['reserve'] = 500000;
                $rows[$i]['available'] = 500000;
            }
        }
        $this->source($rows);

        $page = $this->get('/dashboard?year=2026&'.http_build_query([
            'cmpa' => ['year' => '2026', 'on' => '1'],
            'cmp' => ['year' => '2025', 'on' => '1'],
        ]))->assertOk();

        $c = $page->viewData('compare');

        // อัตราของแต่ละฝั่งต้องเป็น ใช้ไป ÷ ตั้งงบ ของฝั่งนั้นเอง
        foreach (['a', 'b'] as $side) {
            $this->assertEqualsWithDelta(
                $c[$side]['actual'] / $c[$side]['budget'] * 100,
                $c['usage'][$side]['rate'],
                0.0001,
                'ฝั่ง '.$side.' ต้องคิดจากยอดของตัวเอง',
            );
        }

        // ผลต่างเป็น "จุด" และกลับด้านกันพอดี
        $this->assertEqualsWithDelta(
            $c['usage']['a']['rate'] - $c['usage']['b']['rate'],
            $c['usage']['a']['gap'],
            0.0001,
        );
        $this->assertEqualsWithDelta(-$c['usage']['a']['gap'], $c['usage']['b']['gap'], 0.0001);

        $html = $page->getContent();

        // 🔴 บรรทัดสรุปอยู่ใต้ชื่อรายงาน "ที่เดียวทั้งหน้า" ไม่ใส่ซ้ำในการ์ดทั้ง 2 ฝั่ง
        $this->assertStringContainsString('data-loc-th="สรุปการใช้งบ:"', $html);
        $this->assertSame(1, substr_count($html, 'class="cv-usage"'), 'บรรทัดสรุปต้องมีที่เดียวทั้งหน้า');

        // แถว "สรุป" ในตารางมีฝั่งละ 1 แถว และบอกว่าสรุปอะไร (เจ้าของสั่ง 2026-09-17)
        $this->assertSame(2, substr_count($html, 'class="cv-sum-row"'), 'แถวสรุปต้องมีฝั่งละ 1 แถว');
        $this->assertSame(2, substr_count($html, 'data-loc-th="สรุปอัตราการใช้งบ"'), 'แถวสรุปต้องบอกว่าสรุปอะไร');
        $this->assertSame(
            $c['usage']['a']['rate'] < $c['usage']['b']['rate'] ? ['good', 'bad'] : ['bad', 'good'],
            (preg_match_all('~class="cv-sum-rate (good|bad)"~', $html, $sum) === 2 ? $sum[1] : []),
            'อัตราในแถวสรุปต้องระบายสีด้วยกฎเดียวกับบรรทัดใต้ชื่อรายงาน',
        );

        // ผลต่างของ 2 อัตราเขียนด้วย % ทศนิยม 2 ตำแหน่ง พร้อมสีบอกว่าฝั่งไหนใช้งบน้อยกว่า
        $this->assertMatchesRegularExpression('~cv-dir (good|bad)">[▲▼] ?\+?[\d,]+\.\d{2}%</span>~u', $html);

        /*
          🔴 อัตราการใช้งบ "ระบายสี" แล้ว — เจ้าของสั่งกลับกติกาเมื่อ 2026-09-17
             ฝั่งที่ใช้งบน้อยกว่า = เขียว · มากกว่า = แดง (กลับด้านกับแถวยอดเงิน)
             เงื่อนไขนี้ทำให้ต้องมีทั้งเขียวและแดงในบรรทัดเดียว ฝั่งละสี ห้ามสีเดียวกันทั้งคู่
        */
        $this->assertNotSame(
            round($c['usage']['a']['rate'], 2),
            round($c['usage']['b']['rate'], 2),
            'ข้อมูลทดสอบต้องมี 2 อัตราที่ไม่เท่ากัน ไม่งั้นเทสต์นี้ไม่ได้วัดสีเลย',
        );
        $this->assertSame(2, preg_match_all('~class="cv-usage-rate (good|bad)~', $html, $m));
        $this->assertSame(
            $c['usage']['a']['rate'] < $c['usage']['b']['rate'] ? ['good', 'bad'] : ['bad', 'good'],
            $m[1],
            'ฝั่งที่ใช้งบน้อยกว่าต้องเป็นเขียว อีกฝั่งเป็นแดง',
        );

        // 🔴 เกิน 100% = ทะลุกรอบงบ ต้องแดงเสมอ แม้จะน้อยกว่าอีกฝั่งก็ตาม
        foreach (['a' => 'me', 'b' => 'it'] as $side => $who) {
            if ($c['usage'][$side]['rate'] > 100) {
                $this->assertStringContainsString('cv-usage-rate bad is-over', $html);
            }
        }
    }

    /** 🔴 ตั้งงบเป็น 0 คิดอัตราไม่ได้ ต้องคืน null ให้หน้าจอขึ้นขีดกลาง ห้ามเดาเป็น 0% */
    public function test_the_utilisation_summary_reports_nothing_when_there_is_no_budget(): void
    {
        $this->user();
        $this->source();

        $c = $this->get('/dashboard?year=2019&'.http_build_query([
            'cmpa' => ['year' => '2019', 'on' => '1'],
            'cmp' => ['year' => '2026', 'on' => '1'],
        ]))->assertOk()->viewData('compare');

        $this->assertSame(0, $c['a']['budget'], 'ปีที่ไม่มีงบเลย ใช้เป็นฐานทดสอบ');
        $this->assertNull($c['usage']['a']['rate']);
        $this->assertNull($c['usage']['a']['gap']);
        $this->assertNull($c['usage']['b']['gap'], 'อีกฝั่งก็คิดผลต่างไม่ได้เหมือนกัน');
    }

    /**
     * แถบเตือน "ช่วงที่ยังไม่จบ" (เสนอไว้ที่ DECISIONS 50.9.7 · เจ้าของสั่งทำ 2026-09-18)
     *
     * 🔴 ปี 2026 มีข้อมูลถึงวันนี้เท่านั้น เทียบกับ 2025 ที่ครบปีแล้ว ตัวเลขจะดู "ลดลง" เกินจริง
     * 🔴 เตือนเฉพาะตอน "ฝั่งเดียวยังไม่จบ" — ยังไม่จบทั้งคู่ หรือจบทั้งคู่ ถือว่าเทียบกันได้ตามปกติ
     * 🔴 ไม่แก้ตัวเลขให้ เพราะขอบเขตเป็นสิ่งที่ผู้ใช้เลือกมาเอง แถบนี้แค่บอกว่ามีเวลาที่ยังไม่ถึงปนอยู่
     */
    public function test_compare_warns_when_only_one_side_is_still_in_progress(): void
    {
        $this->user();
        $this->expenseSource();
        $compare = function (array $a, array $b, string $tab = 'period') {
            $query = http_build_query(['tab' => $tab, 'year' => $a['year'] ?? '2026',
                'cmpa' => $a + ['on' => '1'], 'cmp' => $b + ['on' => '1']]);

            return $this->get('/dashboard?'.$query)->assertOk();
        };

        // ── แท็บงวดงบ ──
        $page = $compare(['year' => '2026'], ['year' => '2025']);
        $partial = $page->viewData('compare')['partial'];
        $this->assertSame('a', $partial['side'], 'ฝั่งที่ยังไม่จบคือ 2026');
        $this->assertStringContainsString('2026', $partial['th']);
        $this->assertStringContainsString('2026', $partial['en']);
        $page->assertSee('<p class="cv-empty cv-partial">', false)->assertSee('ยังไม่จบ');

        // สลับข้างแล้วต้องเตือนอีกฝั่ง ไม่ใช่ค้างที่ฝั่งซ้ายเสมอ
        $this->assertSame('b', $compare(['year' => '2025'], ['year' => '2026'])->viewData('compare')['partial']['side']);

        // จบทั้งคู่ = ไม่เตือน
        $done = $compare(['year' => '2025'], ['year' => '2024']);
        $this->assertNull($done->viewData('compare')['partial']);
        $this->assertStringNotContainsString('cv-empty cv-partial', $done->getContent(), 'วัดที่มาร์กอัป ไม่ใช่ชื่อคลาสที่อยู่ในบล็อก style ด้วย');

        // ยังไม่จบทั้งคู่ = ไม่เตือน (ปีเต็ม 2026 เทียบงวด BG26Q3 ซึ่งยังไม่ปิด)
        $this->assertNull($compare(['year' => '2026'], ['year' => '2026', 'quarter' => '3'])->viewData('compare')['partial']);

        // 🔴 งวดงบคิดจากไตรมาส ไม่ใช่ทั้งปี — BG26Q1 ปิดแล้ว แต่ BG26Q3 ยังไม่ปิด
        $quarter = $compare(['year' => '2026', 'quarter' => '1'], ['year' => '2026', 'quarter' => '3'])->viewData('compare');
        $this->assertSame('b', $quarter['partial']['side']);

        // ── แท็บค่าใช้จ่าย: คิดจากช่วงวันที่จ่ายที่เลือก ──
        $year = $compare(['year' => '2026'], ['year' => '2025'], 'expense')->viewData('compare');
        $this->assertSame('a', $year['partial']['side']);
        // เดือนที่ผ่านไปแล้วทั้ง 2 ฝั่ง = ไม่เตือน แม้จะอยู่ในปีที่ยังไม่จบ
        $months = $compare(['year' => '2026', 'paid_m' => '2026-05'], ['year' => '2026', 'paid_m' => '2026-04'], 'expense');
        $this->assertNull($months->viewData('compare')['partial']);
    }

    public function test_compare_names_the_kind_of_comparison_from_the_two_scopes(): void
    {
        $this->user();
        $this->source();

        $mode = function (string $query) {
            return $this->get('/dashboard?'.$query)->assertOk()->viewData('compare')['mode'] ?? null;
        };

        /*
          🔴 กติกาที่เจ้าของกำหนด (2026-09-16): ดูจาก "ผู้ใช้เลือกอะไร" ไม่ใช่ช่วงเวลาติดกันไหม
             เลือกปีอย่างเดียว -> YoY · เลือกไตรมาสด้วย -> QoQ
        */

        // เลือกแค่ปี = YoY — ห่างกันกี่ปีก็ยังเป็น YoY
        $this->assertSame('yoy', $mode('year=2026&'.http_build_query(['cmp' => ['year' => '2025']])));
        $this->assertSame('yoy', $mode('year=2026&'.http_build_query(['cmp' => ['year' => '2024']])));

        // เลือกไตรมาสด้วย = QoQ — ไม่ว่าจะข้ามปีหรือไม่ และไม่ต้องเป็นไตรมาสติดกัน
        $this->assertSame('qoq', $mode('year=2026&quarter=2&'.http_build_query(['cmp' => ['year' => '2026', 'quarter' => '1']])));
        $this->assertSame('qoq', $mode('year=2026&quarter=1&'.http_build_query(['cmp' => ['year' => '2025', 'quarter' => '1']])));
        $this->assertSame('qoq', $mode('year=2026&quarter=1&'.http_build_query(['cmp' => ['year' => '2026', 'quarter' => '3']])));

        /*
          🔴 ไม่สนใจบริษัทกับแผนก (เจ้าของสั่งชัด 2026-09-16)
             "ยุ่งกับปีงบอย่างเดียว ให้ขึ้น YoY ไม่ว่าจะเลือกแผนกใดหรือบริษัทใดก็ตาม"
             ปีเดียวกันทั้ง 2 ฝั่ง แต่คนละแผนก = ยังเป็น YoY
        */
        $this->assertSame('yoy', $mode('year=2026&dept=si5|SPV-02&'.http_build_query([
            'cmpa' => ['year' => '2026', 'dept' => 'si5|SPV-02', 'on' => '1'],
            'cmp' => ['year' => '2026', 'dept' => 'si5|SPV-03', 'on' => '1'],
        ])));
        $this->assertSame('yoy', $mode('year=2026&dept=si5|SPV-02&'.http_build_query(['cmp' => ['year' => '2025']])));

        // ไม่มีป้ายก็ต่อเมื่อไม่ได้ระบุปีเป็นตัวเลข — เลือกทุกปี หรืองบที่ยังไม่จัดปี
        $this->assertNull($mode('year=all&'.http_build_query(['cmp' => ['year' => '2025']])));
        $this->assertNull($mode('year=2026&'.http_build_query(['cmp' => ['year' => 'all']])));
    }

    /**
     * 🐛 บั๊กจริง 2026-09-16 (เจ้าของแจ้ง "กดที่สัญลักษณ์เปรียบเทียบ ไม่เห็นมีอะไรเกิดขึ้นเลย")
     *
     *    โค้ดของ modal (หน้าตา + ตัวรับการกด) เคยฝังอยู่ใน access/partials/picker-assets
     *    ซึ่งเป็นชิ้นส่วนของ "โมดูลสิทธิ์" แดชบอร์ดไม่ได้ include เลยกดแล้วเงียบสนิท
     *    🔴 ไม่มี error ไม่มีอะไรเตือน เพราะตัวรับการกดไม่ได้ถูกโหลดมาตั้งแต่แรก
     *
     *    ตอนนี้ยกไปไว้ที่ layouts/partials/modal แล้ว include ที่ layout กลาง
     */
    public function test_the_compare_button_is_actually_wired_up(): void
    {
        $this->user();
        $this->source();

        $html = $this->get('/dashboard?year=2026')->assertOk()->getContent();

        // ปุ่มกับกล่องต้องอ้างชื่อเดียวกัน
        $this->assertStringContainsString('data-modal-open="cmp-pick"', $html);
        $this->assertStringContainsString('data-modal="cmp-pick"', $html);

        // 🔴 คลาสต้องเป็น .modal-wrap เท่านั้น — ตัวปิดค้นหาด้วยคลาสนี้
        $this->assertMatchesRegularExpression(
            '~<div class="modal-wrap"[^>]*data-modal="cmp-pick"~',
            $html,
            'กล่องเปรียบเทียบต้องใช้คลาส .modal-wrap ไม่งั้นเปิดได้แต่ปิดไม่ได้',
        );

        // ตัวรับการกดกับหน้าตาของ modal ต้องมาถึงหน้านี้จริง ไม่ใช่แค่มี HTML
        $this->assertStringContainsString("closest('[data-modal-open]')", $html, 'ตัวรับการกด modal ไม่ได้ถูกโหลดมาที่หน้านี้');
        $this->assertStringContainsString('.modal-wrap {', $html, 'หน้าตาของ modal ไม่ได้ถูกโหลดมาที่หน้านี้');
    }

    /**
     * ฝั่งซ้ายเลือกเองได้แล้ว (เจ้าของสั่งรื้อใหม่ 2026-09-16)
     *
     * 🔴 ของเดิมฝั่งซ้ายล็อกเป็นตัวกรองของหน้า แก้ไม่ได้
     *    ตอนนี้ส่ง cmpa[...] มาทับได้ · ไม่ส่งมา = ถอยไปใช้ตัวกรองของหน้าเหมือนเดิม (ลิงก์เก่ายังใช้ได้)
     */
    public function test_both_sides_of_the_comparison_can_be_chosen(): void
    {
        $this->user();
        $this->source();

        $query = http_build_query([
            'cmpa' => ['year' => '2025', 'on' => '1'],
            'cmp' => ['year' => '2026', 'on' => '1'],
        ]);
        $compare = $this->get('/dashboard?year=2026&'.$query)->assertOk()->viewData('compare');

        $expected = ErpDashboard::totals(ErpDashboard::filter(Fixture::rows(), ['year' => '2025']));

        // ฝั่งซ้ายต้องเป็น 2025 ตามที่ส่งมา ไม่ใช่ 2026 ของตัวกรองหน้า
        $this->assertSame('2025', $compare['left']['year']);
        $this->assertSame($expected['budget'], $compare['fields']['budget']['a']);

        // ไม่ส่ง cmpa = ใช้ตัวกรองของหน้า (ลิงก์เก่าต้องไม่พัง)
        $old = $this->get('/dashboard?year=2026&'.http_build_query(['cmp' => ['year' => '2025']]))
            ->assertOk()->viewData('compare');
        $this->assertSame('2026', $old['left']['year']);
    }

    /**
     * 🔴 "เลือกทุกช่องเป็นทั้งหมด" ต้องต่างจาก "ไม่ได้ส่งมาเลย"
     *    ทั้งคู่ทำให้ขอบเขตว่างเหมือนกัน แต่ความหมายคนละเรื่อง
     *    ไม่มีธง on ผู้ใช้จะเลือกดูภาพรวมทั้งหมดไม่ได้เลย
     */
    public function test_choosing_everything_is_not_the_same_as_choosing_nothing(): void
    {
        $this->user();
        $this->source();

        $all = ErpDashboard::totals(ErpDashboard::filter(Fixture::rows(), []));

        $compare = $this->get('/dashboard?year=2026&'.http_build_query([
            'cmpa' => ['year' => '2026', 'on' => '1'],
            'cmp' => ['on' => '1'],
        ]))->assertOk()->viewData('compare');

        $this->assertNotNull($compare, 'เลือกทั้งหมดทุกช่อง ต้องนับว่าสั่งเทียบแล้ว');
        $this->assertSame($all['budget'], $compare['fields']['budget']['b'], 'ฝั่งขวาต้องเป็นข้อมูลทั้งหมด');

        // ไม่ส่งอะไรมาเลย = ยังไม่ได้สั่งเทียบ
        $this->assertNull($this->get('/dashboard?year=2026')->assertOk()->viewData('compare'));
    }

    /**
     * โหมดเปรียบเทียบกินทั้งหน้า + มีปุ่มย้อนกลับ + ป้ายบอกขอบเขตจริง (เจ้าของสั่ง 2026-09-16)
     *
     * 🔴 เลิกใช้คำว่า "ที่กำลังดูอยู่ / ที่ดูอยู่ / ที่เทียบ" ซึ่งอ่านแล้วไม่รู้ว่าหมายถึงอะไร
     */
    public function test_compare_mode_takes_over_the_page_and_names_each_scope(): void
    {
        $this->user();
        $this->source();

        $html = $this->get('/dashboard?year=2026&'.http_build_query([
            'cmpa' => ['year' => '2026', 'quarter' => '1', 'on' => '1'],
            'cmp' => ['year' => '2025', 'on' => '1'],
        ]))->assertOk()->getContent();

        // ปุ่มย้อนกลับมุมซ้ายบน
        $this->assertStringContainsString('class="cv-back"', $html, 'ต้องมีปุ่มย้อนกลับ');

        /*
          ขอบเขตของแต่ละฝั่งต้องเขียนเป็นคู่ "หัวข้อ: ค่า" ตามลำดับที่เจ้าของกำหนด
          บริษัท → ปี → แผนก → รอบ
        */
        foreach (['บริษัท', 'ปี', 'แผนก', 'รอบ'] as $label) {
            $this->assertMatchesRegularExpression(
                '~<dt>(<span[^>]*>)?'.preg_quote($label, '~').'~u',
                $html,
                'ต้องมีหัวข้อ "'.$label.'" นำหน้าค่า',
            );
        }

        $this->assertStringContainsString('2026', $html);
        $this->assertStringContainsString('Q1', $html);
        $this->assertStringContainsString('ทุกแผนก', $html, 'ช่องที่ไม่ได้เลือกต้องเขียนว่า "ทั้งหมด" ให้เห็น');

        // 🔴 เจ้าของสั่งเอากราฟออก (2026-09-16 รอบ 2)
        $this->assertStringNotContainsString('cv-chart', $html, 'กราฟถูกสั่งเอาออกแล้ว');

        /*
          🔴 ผังที่เจ้าของสั่งรอบ 3: ยอดของแต่ละฝั่งอยู่ในการ์ดของตัวเอง
             การ์ดล่างจึงเหลือแค่ "ผลต่าง" กับ "%" ไม่ซ้ำยอดที่อยู่ข้างบนแล้ว
        */
        $this->assertSame(2, substr_count($html, 'data-loc-th="จำนวนเงิน (บาท)"'), 'ต้องมีตารางฝั่งละ 1 ตาราง');
        $this->assertSame(2, substr_count($html, 'data-loc-th="ผลต่าง (บาท)"'), 'คอลัมน์ผลต่างต้องอยู่ในตารางของแต่ละฝั่ง');

        // 🔴 ไม่มีการ์ด "สรุปผลต่าง" แยกข้างล่างแล้ว — ยุบเข้าไปในการ์ดของแต่ละฝั่ง
        $this->assertStringNotContainsString('สรุปผลต่าง', $html);
        $this->assertStringNotContainsString('cv-table', $html);
    }

    /**
     * 🔴 % เขียนต่อท้ายจำนวนเงินในช่องเดียวกัน และคิด "2 ทิศทาง" (เจ้าของสั่ง 2026-09-16)
     *    ฝั่งที่มากกว่าขึ้นเขียว ▲ · ฝั่งที่น้อยกว่าขึ้นแดง ▼ — การ์ดแต่ละฝั่งอ่านจบในตัวเอง
     */
    public function test_each_side_reports_its_own_variance_in_both_directions(): void
    {
        $this->user();
        $this->source();

        $page = $this->get('/dashboard?year=2026&'.http_build_query([
            'cmpa' => ['year' => '2026', 'on' => '1'],
            'cmp' => ['year' => '2025', 'on' => '1'],
        ]))->assertOk();

        $budget = $page->viewData('compare')['fields']['budget'];

        // ผลต่างต้องกลับด้านกันพอดี และเปอร์เซ็นต์ต้องคิดจากฐานคนละตัว
        $this->assertSame(-$budget['side']['b']['diff'], $budget['side']['a']['diff']);
        $this->assertEqualsWithDelta(
            ($budget['a'] - $budget['b']) / abs($budget['b']) * 100,
            $budget['side']['a']['pct'],
            0.0001,
            'ฝั่งซ้ายต้องใช้ฝั่งขวาเป็นฐาน',
        );

        // ฝั่งหนึ่งมากกว่าอีกฝั่ง = ต้องมีทั้งลูกศรขึ้นและลง อยู่ในหน้าเดียวกัน
        $html = $page->getContent();
        $this->assertStringContainsString('▲ +', $html);
        $this->assertStringContainsString('▼ ', $html);

        /*
          🔴 สีของแต่ละแถวคิดจาก "ทิศทางที่ดีของแถวนั้น" ไม่ใช่กฎเหมา "ขึ้น = เขียว"
             (เจ้าของสั่ง 2026-09-17 หลังเห็นว่า "ใช้ไปลดลง 56%" ขึ้นแดง ซึ่งอ่านผิดความจริง)
             งบประมาณ/คงเหลือ : มากขึ้น = เขียว · ใช้ไป/รอตัดจ่าย : น้อยลง = เขียว
        */
        $goodDirs = ['budget' => 'up', 'actual' => 'down', 'reserve' => 'down', 'available' => 'up'];
        $quiet = ['reserve' => true];
        $seen = [];

        foreach ($goodDirs as $key => $goodDir) {
            foreach (['a', 'b'] as $side) {
                $pct = $page->viewData('compare')['fields'][$key]['side'][$side]['pct'];
                if ($pct === null || round($pct, 2) == 0.0) {
                    continue;
                }

                // 🔴 รอตัดจ่ายไม่เขียน % และไม่ระบายสี — ตรวจแยกข้างล่าง
                if (! empty($quiet[$key])) {
                    continue;
                }

                $dir = round($pct, 2) > 0 ? 'up' : 'down';
                $tone = $dir === $goodDir ? 'good' : 'bad';
                $seen[$dir.'-'.$tone] = true;

                $this->assertStringContainsString(
                    'cv-pct '.$tone.'">'.($dir === 'up' ? '▲ +' : '▼ ').number_format($pct, 2).'%',
                    $html,
                    'แถว '.$key.' ฝั่ง '.$side.' ควรเป็น '.$tone,
                );
            }
        }

        /*
          🔴 แถว "รอตัดจ่าย" เงียบทั้งแถว (เจ้าของสั่ง 2026-09-17)
             ฐานเล็กและแกว่งมาก % จึงพุ่งเป็นพันเปอร์เซ็นต์จากเงินแค่แสนบาท และช่องนี้ยังมี
             ERP Error ยอดติดลบอยู่ (ข้อ 50.2) จึงเหลือยอดกับผลต่างเป็นบาท ตัวอักษรสีดำ
        */
        preg_match_all('~<tr>[\s\S]*?</tr>~u', $html, $trs);
        $reserveRows = array_values(array_filter(
            $trs[0],
            static fn (string $r): bool => str_contains($r, 'data-loc-th="รอตัดจ่าย"'),
        ));

        $this->assertCount(2, $reserveRows, 'แถวรอตัดจ่ายต้องมีฝั่งละ 1 แถว');
        foreach ($reserveRows as $row) {
            $this->assertStringNotContainsString('%', $row, 'แถวรอตัดจ่ายต้องไม่เขียนเปอร์เซ็นต์');
            $this->assertStringContainsString('class="cv-pct plain"', $row, 'ผลต่างของรอตัดจ่ายต้องเป็นตัวอักษรสีดำ');
        }

        // 🔴 กันเทสต์ผ่านด้วยกฎเหมา — ต้องมีอย่างน้อย 1 แถวที่ "ลดลงแล้วเขียว" หรือ "เพิ่มแล้วแดง"
        $this->assertTrue(
            isset($seen['down-good']) || isset($seen['up-bad']),
            'ข้อมูลทดสอบต้องมีแถวที่สีกลับด้านกับกฎ "ขึ้น = เขียว" ไม่งั้นเทสต์นี้ไม่ได้วัดอะไร',
        );
    }

    /**
     * ชื่อรายงานต้องบอกชนิดการเทียบเต็มๆ ในบรรทัดเดียวกัน (เจ้าของสั่ง 2026-09-16)
     * เลือกแค่ปี → Year-on-Year (YoY) · เลือกไตรมาสด้วย → Quarter on Quarter (QoQ)
     */
    public function test_the_report_title_names_the_kind_of_comparison(): void
    {
        $this->user();
        $this->source();

        $title = function (array $a, array $b) {
            return $this->get('/dashboard?year=2026&'.http_build_query([
                'cmpa' => $a + ['on' => '1'],
                'cmp' => $b + ['on' => '1'],
            ]))->assertOk()->getContent();
        };

        $yoy = $title(['year' => '2026'], ['year' => '2025']);
        $this->assertStringContainsString('รายงานเปรียบเทียบงบประมาณ', $yoy);
        // 🔴 ชนิดการเทียบไม่ตัวหนา — อยู่ใน span ของตัวเอง (เจ้าของสั่ง)
        $this->assertStringContainsString('<span class="cv-kind">: Year-on-Year (YoY)</span>', $yoy);

        $qoq = $title(['year' => '2026', 'quarter' => '2'], ['year' => '2026', 'quarter' => '1']);
        $this->assertStringContainsString('<span class="cv-kind">: Quarter on Quarter (QoQ)</span>', $qoq);

        // ไม่ได้ระบุปีเป็นตัวเลข (เลือกทุกปี) = ไม่ต่อท้ายอะไร
        $plain = $title(['year' => 'all'], ['year' => '2025']);
        $this->assertStringContainsString('รายงานเปรียบเทียบงบประมาณ<', $plain);
        $this->assertStringNotContainsString('<span class="cv-kind">', $plain);

        // 🔴 หัวเรื่องอยู่ในการ์ดเดียวกับปุ่มถอยกลับ · เอาป้ายย่อยออกแล้ว (เจ้าของสั่ง)
        $this->assertStringContainsString('<header class="card cv-head">', $yoy);
        $this->assertStringNotContainsString('cv-mode', $yoy);
        $this->assertStringNotContainsString('เทียบปีต่อปี', $yoy);

        // 🔴 หัวการ์ดของทั้ง 2 ฝั่งต้องเป็นพื้นน้ำเงินตัวอักษรขาว
        $this->assertStringContainsString('.cv-panel .card-head {', $yoy);

        // เนื้อหาปกติของแดชบอร์ดต้องไม่ถูกวาดพร้อมกัน
        $this->assertStringNotContainsString('<section class="bd-summary">', $yoy, 'โหมดเปรียบเทียบต้องแทนเนื้อหาปกติ');

        // 🔴 ปุ่มลัด YoY/QoQ เจ้าของสั่งเอาออกแล้ว
        $this->assertStringNotContainsString('cmp-preset', $yoy);
    }

    /**
     * 🔴 บั๊กจริง 2026-09-16 (เจ้าของแจ้ง): ลิงก์ที่ "กราฟ" สร้างเองใน controller
     *    เคยไล่พิมพ์ชื่อพารามิเตอร์ทีละตัว พอเพิ่มตัวกรองไตรมาสเลยทิ้ง quarter ไปเงียบๆ
     *    กดชิ้นกราฟแล้วตัวกรองหาย · เทสต์นี้กันไม่ให้ลิงก์ชุดนี้ลืมตัวกรองอีก
     */
    public function test_chart_links_carry_the_selected_scope(): void
    {
        $this->user();
        $this->source();
        $chart = $this->get('/dashboard?company=si5&year=2026&quarter=1')->assertOk()->viewData('chart');
        $this->assertNotEmpty($chart['items']);

        foreach ($chart['items'] as $item) {
            foreach (array_merge([$item['href'], $item['alerts_href']], array_column($item['alerts'], 'href')) as $link) {
                parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
                $this->assertSame('1', $query['quarter'] ?? null, 'ลิงก์ของกราฟต้องพาไตรมาสไปด้วย: '.$link);
                $this->assertSame('2026', $query['year'] ?? null, 'ลิงก์ของกราฟต้องพาปีไปด้วย: '.$link);
            }
        }
    }

    /** 🔴 ห้ามเดาไตรมาสจากวันที่ — STARTDATE คือวันที่ตั้งงบ ตั้งล่วงหน้าข้ามปีได้ */
    public function test_quarter_comes_from_the_propose_code_never_from_dates(): void
    {
        $this->assertSame(1, ErpDashboard::budget_quarter('BG26', 'BG26Q1'));
        $this->assertSame(4, ErpDashboard::budget_quarter('BG25', 'BG25Q4'));
        // งบโปรเจค/รุ่นรถ ไม่มีไตรมาสจริงๆ — ต้องคืน null ไม่ใช่เดาให้
        $this->assertNull(ErpDashboard::budget_quarter('AC-26-001', 'NON'));
        $this->assertNull(ErpDashboard::budget_quarter('MY21', 'HARD TRIM'));
        $this->assertNull(ErpDashboard::budget_quarter('BG24', 'AU-21-001'));
        // Model กับ Propose คนละปี = จับคู่ไม่ได้ ห้ามเดา (กติกาเดียวกับ budget_year)
        $this->assertNull(ErpDashboard::budget_quarter('BG26', 'BG25Q3'));
    }

    public function test_old_mapped_budget_link_resolves_to_its_exact_erp_department(): void
    {
        $this->user();
        $this->source();
        $query = ['year' => '2026', 'dept' => 'si5|g:old-map', 'budget' => Fixture::rows()[0]['group'], 'view' => 'activity', 'kind' => 'purchases'];
        $page = $this->get('/dashboard?'.http_build_query($query))->assertRedirect();
        parse_str(parse_url($page->headers->get('Location'), PHP_URL_QUERY), $target);
        $this->assertSame('si5|SPV-02', $target['dept']);
        $this->assertSame($query['budget'], $target['budget']);
        $this->assertSame('purchases', $target['kind']);
        $this->get('/dashboard?year=2026&dept=si5%7Cg%3Aold-map&view=budgets')
            ->assertRedirect()->assertSessionHas('dashboard_department_reset', true);
        $this->getJson('/dashboard/spend?year=2026&dept=si5%7Cg%3Aold-map')->assertUnprocessable();
    }

    public function test_sort_filter_reaches_service_and_start_dates_are_visible(): void
    {
        $this->user();
        $source = $this->source();
        $source->shouldReceive('activity')->once()->withArgs(fn ($rows, $kind, $page, $sort) => count($rows) === 2 && $kind === 'actual' && $page === 2 && $sort === 'amount_desc'
        )->andReturn(Fixture::activity());
        $this->get('/dashboard?year=2026&dept=si5%7CSPV-02&view=activity&sort=amount_desc&page=2')
            ->assertOk()->assertSee('data-activity-sort', false)->assertSee('Amount high to low');
        $this->get('/dashboard?year=2026&dept=si5%7CSPV-02&view=budgets')->assertOk()
            ->assertSee('Budget start date')->assertSee('2025-10-01')->assertSee('2026-01-01');
        $this->getJson('/dashboard?sort=invalid')->assertUnprocessable();
    }

    public function test_history_reference_opens_budget_in_the_same_tab(): void
    {
        $this->user();
        $source = $this->source();
        $selected = Fixture::rows()[0];
        foreach (['actual', 'purchases'] as $kind) {
            $source->shouldReceive('activity')->once()->withArgs(fn ($rows, $k, $page) => array_column($rows, 'id') === ['101', '102'] && $k === $kind && $page === 1
            )->andReturn(Fixture::activity($kind));
            $query = ['year' => '2026', 'dept' => $selected['dept_group'], 'view' => 'activity', 'kind' => $kind, 'page' => 1];
            $history = $this->get('/dashboard?'.http_build_query($query))->assertOk();
            if ($kind === 'purchases') {
                $history->assertSee('Supplier')->assertSee('Test Supplier Ltd.');
            } else {
                $history->assertDontSee('Test Supplier Ltd.');
            }
            preg_match('/data-budget-reference href="([^"]+)"/', $history->getContent(), $match);
            $this->assertNotEmpty($match);
            $url = html_entity_decode($match[1], ENT_QUOTES);
            parse_str(parse_url($url, PHP_URL_QUERY), $target);
            $this->assertSame($kind, $target['kind']);
            $this->assertSame($selected['group'], $target['budget']);
            $this->assertSame('2026', $target['year']);
            $this->assertArrayNotHasKey('record', $target);
            $source->shouldReceive('activity')->once()->withArgs(fn ($rows, $k, $page) => array_column($rows, 'id') === ['101', '102'] && $k === $kind && $page === 1
            )->andReturn(Fixture::activity($kind));
            $detail = $this->get($url)->assertOk()->assertSee('Training expense')->assertDontSee('Back to budgets')
                ->assertDontSee('View source allocations and periods');
            if ($kind === 'purchases') {
                $detail->assertSee('Test Supplier Ltd.');
            }
        }
    }

    public function test_dashboard_permission_is_checked_before_loading_erp(): void
    {
        // ไม่มีสิทธิ์ "ภาพรวมระบบ → แดชบอร์ด" = ถูกกันตั้งแต่ middleware ยังไม่ได้แตะ ERP เลย
        $this->user(true, false);
        $mock = Mockery::mock(ErpDashboard::class);
        $mock->shouldNotReceive('snapshot');
        $this->instance(ErpDashboard::class, $mock);
        $this->get('/dashboard')->assertForbidden();
        $this->getJson('/dashboard/spend?dept=si5%7CSPV-02')->assertForbidden();
    }

    public function test_dashboard_permission_alone_is_enough_to_see_budget_figures(): void
    {
        /*
          🔴 เจ้าของสั่ง 2026-09-15: ตั้งที่ "ภาพรวมระบบ → แดชบอร์ด" ที่เดียวต้องพอ
             ไม่ต้องไปติ๊ก "งบประมาณ → ลงทะเบียน" ให้ซ้ำอีกที่ (สิทธิ์คนละเรื่องกัน)
        */
        $this->user(false, true);
        $this->source();
        $this->get('/dashboard?company=si5&year=2026')->assertOk()
            ->assertViewHas('canSeeBudget', true)
            ->assertDontSee('Budget access is unavailable')
            ->assertSee('200,000.00');
    }

    public function test_year_uses_budget_period_and_keeps_unclassified_projects_visible(): void
    {
        $this->user();
        $this->source();
        $page = $this->get('/dashboard?company=si5&year=2026')->assertOk();
        $this->assertSame(['101', '102'], array_column($page->viewData('rows'), 'id'));
        $this->assertSame(20000000, $page->viewData('total')['budget']);
        // แถบ "ยังมีงบที่ไม่ระบุรอบปี" เอาออกแล้ว (เจ้าของสั่ง 2026-09-14) — ดูผ่านตัวเลือกในตัวกรองปีแทน
        $page->assertDontSee('Budgets without a classified period');
        $page->assertSee('200,000.00')->assertSee('120,000.00')->assertSee('Unclassified year')
            ->assertDontSee('Payroll budgets excluded')->assertSee('data-loc-en', false);
    }

    public function test_filters_apply_on_change_without_model_filter_or_submit_button(): void
    {
        $this->user();
        $this->source();
        // ค่า model ที่ค้างมากับลิงก์เก่าต้องไม่กรองอะไรแล้ว (เจ้าของสั่งเอาตัวกรอง Model ออก 2026-09-14)
        $page = $this->get('/dashboard?company=si5&year=2026&model=BG99')->assertOk()
            ->assertSee('data-auto-submit', false)->assertDontSee('name="model"', false)->assertDontSee('Apply filters');
        $this->assertSame(['101', '102'], array_column($page->viewData('rows'), 'id'));
        $this->assertArrayNotHasKey('model', $page->viewData('filters'));
    }

    public function test_year_defaults_to_current_year_and_all_years_is_explicit(): void
    {
        $this->user();
        $this->source();
        $page = $this->get('/dashboard?company=si5')->assertOk()
            ->assertSee('value="2026" selected', false)->assertSee('value="all"', false)
            ->assertDontSee('Years come from BGxx')->assertDontSee('JPY amounts are yen');
        $this->assertSame('2026', $page->viewData('filters')['year']);
        $this->assertSame(['101', '102'], array_column($page->viewData('rows'), 'id'));

        $all = $this->get('/dashboard?company=si5&year=all')->assertOk()->assertSee('value="all" selected', false);
        $this->assertSame(['101', '102', '103', '104', '106'], array_column($all->viewData('rows'), 'id'));
        // ลิงก์ในหน้า "ทุกปี" ต้องพา year=all ไปด้วย ไม่งั้นกดแล้วเด้งกลับปีปัจจุบัน
        $this->assertStringContainsString('year=all', $all->viewData('chart')['items'][0]['href']);
    }

    public function test_chart_carries_actual_reserved_and_available_per_department(): void
    {
        $this->user();
        $this->source();
        $chart = $this->get('/dashboard?company=si5&year=2026')->assertOk()
            ->assertSee('data-dash-chart', false)->assertSee('dash-chart-data', false)->viewData('chart');
        $this->assertCount(1, $chart['items']);
        $item = $chart['items'][0];
        $this->assertSame(['budget' => '200,000.00', 'actual' => '120,000.00', 'reserve' => '20,000.00', 'available' => '60,000.00'], $item['text']);
        $this->assertSame(['actual' => '60.0', 'reserve' => '10.0', 'available' => '30.0'], $item['pct']);
        $this->assertStringContainsString('view=budgets', $item['href']);
        $this->assertSame($chart['total']['text'], $item['text']);
    }

    public function test_table_is_a_view_of_the_chart_card_and_formula_banner_is_gone(): void
    {
        $this->user();
        $rows = Fixture::rows();
        $rows[0]['available'] += 600000000; // ERP ยอดคงเหลือไม่เท่าสูตร
        $this->source($rows);
        $html = $this->get('/dashboard?company=si5&year=2026')->assertOk()
            ->assertSee('data-kind="table"', false)->assertSee('data-dash-table', false)->assertSee('data-dash-stats', false)
            ->assertDontSee('ERP availability differs')->assertDontSee('ERP data retrieved')->getContent();
        // การ์ดสรุป = ตัวกรอง (ซ้าย) + ยอด 4 ช่อง (ขวา) อยู่เหนือการ์ดกราฟ · ตารางรายแผนกอยู่ในการ์ดกราฟ
        $this->assertLessThan(strpos($html, 'data-dash-stats'), strpos($html, 'data-dashboard-filter'));
        $this->assertLessThan(strpos($html, 'data-dash-chart'), strpos($html, 'data-dash-stats'));
        $this->assertStringContainsString('data-dash-full', $html);
        // ปุ่มเรียงลำดับ — ค่าเริ่มต้นสูงไปต่ำ · แถวตารางมีวงเงินให้ JS เรียง
        $this->assertMatchesRegularExpression('/class="bd-kbtn is-on" data-sort="desc"/', $html);
        $this->assertStringContainsString('data-sort="asc"', $html);
        $this->assertStringContainsString('data-dept-row data-budget=', $html);
        $this->assertGreaterThan(strpos($html, 'data-dash-chart'), strpos($html, 'data-dash-table'));
        $this->assertLessThan(strpos($html, 'data-dash-hint'), strpos($html, 'data-dash-table'));
    }

    public function test_future_budget_is_visible_despite_its_earlier_start_date(): void
    {
        $this->user();
        $this->source();
        $page = $this->get('/dashboard?year=2027')->assertOk();
        $this->assertSame(['103'], array_column($page->viewData('rows'), 'id'));
    }

    public function test_company_and_department_codes_do_not_cross_match(): void
    {
        $this->user();
        $this->source();
        $page = $this->get('/dashboard?year=2026&company=pd&dept=pd%7CSPV-02')->assertOk();
        $this->assertSame(['105'], array_column($page->viewData('rows'), 'id'));
        $this->assertCount(1, $page->viewData('departments'));
    }

    public function test_unclassified_filter_does_not_guess_year_from_project_number(): void
    {
        $this->user();
        $this->source();
        $page = $this->get('/dashboard?year=unassigned&view=budgets')->assertOk()->assertSee('Printer');
        $this->assertSame(['104'], array_column($page->viewData('rows'), 'id'));
        $this->assertSame(10000000, $page->viewData('total')['budget']);
    }

    public function test_empty_scope_preserves_the_requested_filter_in_the_form(): void
    {
        $this->user();
        $this->source();
        $this->get('/dashboard?year=2099&dept=si5%7CUNKNOWN')->assertRedirect();
        $page = $this->get('/dashboard?year=2099')->assertOk()->assertSee('No budgets match these filters');
        $this->assertContains(2099, $page->viewData('years'));
        $this->assertSame([], $page->viewData('deptOptions'));
        $this->assertSame(0, $page->viewData('total')['count']);
    }

    public function test_budget_group_retains_all_allocations(): void
    {
        $this->user();
        $this->source();
        $page = $this->get('/dashboard?year=2026&company=si5&view=budgets')->assertOk()->assertSee('Training expense');
        $this->assertCount(1, $page->viewData('groups'));
        $this->assertCount(2, $page->viewData('groups')[0]['rows']);
    }

    public function test_actual_uses_exact_allocation_scope_and_reports_a_real_difference(): void
    {
        $this->user();
        $this->source()->shouldReceive('activity')->once()->withArgs(function ($rows, $kind, $page) {
            return array_column($rows, 'id') === ['101', '102'] && $kind === 'actual' && $page === 1;
        })->andReturn(Fixture::activity());
        $key = Fixture::rows()[0]['group'];
        $this->get('/dashboard?year=2026&dept=si5%7CSPV-02&view=activity&budget='.$key)
            ->assertOk()->assertSee('120,000.00')->assertSee('115,000.00')->assertSee('PO26-TEST')->assertSee('page=2')
          // กล่องกระทบยอด แถบเส้นทาง และข้อความขอบเขตรายการ เจ้าของสั่งเอาออก 2026-09-14
            ->assertDontSee('The detail does not fully explain Actual')->assertDontSee('Matched entries total')
            ->assertDontSee('Entry scope')->assertDontSee('Budget navigation')
          // ตัวแบ่งหน้าเป็นปุ่มสัญลักษณ์ (เจ้าของสั่ง 2026-09-14) — ไม่มีคำ "ก่อนหน้า/ถัดไป" แล้ว
            ->assertSee('data-i18n-aria="pager.next"', false)->assertSee('data-i18n-aria="pager.last"', false)
            ->assertSee('1 / 2')->assertDontSee('← ก่อนหน้า')->assertDontSee('ถัดไป →');
    }

    public function test_selected_record_and_page_are_passed_to_the_service(): void
    {
        $this->user();
        $this->source()->shouldReceive('activity')->once()->withArgs(fn ($rows, $kind, $page) => array_column($rows, 'id') === ['102'] && $kind === 'actual' && $page === 2)->andReturn(Fixture::activity());
        $this->getJson('/dashboard/spend?year=2026&dept=si5%7CSPV-02&record=102&page=2')->assertOk()->assertJsonPath('total', 11500000);
    }

    public function test_department_table_filters_in_browser_and_budget_list_filters_every_page_on_server(): void
    {
        $this->user();
        $this->source();
        $overview = $this->get('/dashboard?company=si5&year=2026')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<table class="tbl bd-tbl" data-filterable>\s*<thead><tr>\s*<th class="col-seq" data-no-filter>/', $overview);

        // กลุ่มงบของ HRM ทุกปี = 3 กลุ่ม (BG25 · BG26 · BG27) กรองวันที่เริ่มงบแล้วเหลือ BG27 กลุ่มเดียว
        $page = $this->get('/dashboard?year=all&dept=si5%7CSPV-02&view=budgets&f%5Bstart%5D=2026-08-31')->assertOk()
            ->assertSee('data-filter-server', false)->assertSee('data-filter-key="start"', false)->assertSee('Clear column filters');
        $this->assertSame(3, $page->viewData('groupsTotal'));
        $this->assertCount(1, $page->viewData('groups'));
        $this->assertSame(['103'], array_column($page->viewData('groups')[0]['rows'], 'id'));
        $this->assertSame(['start' => ['2026-08-31']], $page->viewData('filterPicked'));
        $this->assertSame(['2025-10-01', '2026-08-31'], array_column($page->viewData('filterOptions')['start'], 'v'));
        // ลิงก์เปลี่ยนมุมมอง (แท็บ) ต้องไม่พาตัวกรองคอลัมน์ไปด้วย
        $this->assertStringNotContainsString('f%5Bstart%5D', $page->getContent());
        // คีย์ที่ไม่รู้จักถูกเมิน ไม่ทำให้ตารางว่าง
        $this->assertCount(3, $this->get('/dashboard?year=all&dept=si5%7CSPV-02&view=budgets&f%5Bbogus%5D=x')->assertOk()->viewData('groups'));
    }

    public function test_activity_header_filters_reach_sql_and_survive_paging(): void
    {
        $this->user();
        $source = $this->source();
        $source->shouldReceive('activity')->once()->withArgs(fn ($rows, $kind, $page, $sort, $filters) => $kind === 'actual' && $page === 1 && $sort === 'date_desc' && $filters === ['title' => ['Training workshop']]
        )->andReturn(Fixture::activity());
        /*
          🔴 ค่าที่เลือกได้ของหัวคอลัมน์ย้ายไปโหลดตอนผู้ใช้กดปุ่มกรอง (เจ้าของแจ้งว่าหน้าโหลดนาน 2026-09-17)
             เปิดหน้าเฉยๆ ต้องไม่ยิงคิวรีนั้น — แต่ปุ่มกรองต้องยังขึ้นครบ
        */
        $source->shouldNotReceive('activity_options');
        $page = $this->get('/dashboard?year=2026&dept=si5%7CSPV-02&view=activity&f%5Btitle%5D=Training%20workshop')->assertOk()
            ->assertSee('data-filter-key="title"', false)->assertSee('data-filter-key="amount"', false)->assertDontSee('data-filter-key="supplier"', false)
            ->assertSee('Clear column filters');
        $html = $page->getContent();
        $this->assertSame([], $page->viewData('filterOptions'));
        $this->assertContains('title', $page->viewData('filterKeys'));
        $this->assertStringContainsString('dashboard/filter-options', $page->viewData('filterOptionsUrl'));
        // ปุ่มไปหน้า 2 ต้องพาตัวกรองไปด้วย ไม่งั้นหน้าถัดไปกลับเป็นรายการทั้งหมด
        $this->assertMatchesRegularExpression('/href="[^"]*page=2[^"]*f%5Btitle%5D=Training%20workshop|href="[^"]*f%5Btitle%5D=Training%20workshop[^"]*page=2/', $html);
    }

    /** ที่อยู่ที่หน้าให้มา ต้องคืนค่าที่เลือกได้จริง ด้วยขอบเขตและด่านสิทธิ์ชุดเดียวกับหน้าแดชบอร์ด */
    public function test_column_filter_options_load_on_demand_through_their_own_route(): void
    {
        $this->user();
        $source = $this->source();
        $source->shouldReceive('activity_options')->once()
            ->withArgs(fn ($rows, $kind, $window) => array_column($rows, 'id') === ['101', '102'] && $kind === 'actual' && $window['key'] === '2026')
            ->andReturn(['title' => [['v' => 'Training workshop', 't' => 'Training workshop', 'e' => 'Training workshop']]]);

        $this->getJson('/dashboard/filter-options?tab=expense&year=2026&dept=si5%7CSPV-02&view=entries')
            ->assertOk()->assertJsonPath('options.title.0.v', 'Training workshop');

        // ไม่ระบุแผนก = ขอบเขตกว้างเกินไป ไม่ยอมอ่าน
        $this->getJson('/dashboard/filter-options?year=2026')->assertStatus(422);
    }

    public function test_purchases_show_requester_and_status_separately_from_actual(): void
    {
        $this->user();
        $this->source()->shouldReceive('activity')->andReturn(Fixture::activity('purchases'));
        $this->get('/dashboard?year=2026&dept=si5%7CSPV-02&view=activity&kind=purchases')
            ->assertOk()->assertSee('Test Requester')->assertSee('TEST123')->assertSee('Test Creator')
            ->assertSee('Open order')->assertDontSee('PO values are separate from Actual')->assertDontSee('Matched entries total')
            ->assertDontSee('Entry scope')
          // มูลค่าสั่งซื้อเป็นสีแดงเหมือนยอดใช้ไป (เจ้าของสั่ง 2026-09-14)
            ->assertSee('<td class="num bd-used">115,000.00', false);
    }

    public function test_source_failure_is_not_shown_as_a_zero_balance(): void
    {
        $this->user();
        $mock = Mockery::mock(ErpDashboard::class);
        $mock->shouldReceive('snapshot')->andThrow(new \RuntimeException('private connection details'));
        $this->instance(ErpDashboard::class, $mock);
        $this->get('/dashboard')->assertOk()->assertViewHas('erpReady', false)->assertSee('ERP data could not be retrieved')
            ->assertDontSee('private connection details')->assertDontSee('Adjusted budget');
        $this->getJson('/dashboard/spend?dept=si5%7CSPV-02')->assertStatus(503)->assertDontSee('private connection details');
    }

    public function test_failed_details_do_not_claim_reconciliation(): void
    {
        $this->user();
        $this->source()->shouldReceive('activity')->andThrow(new \RuntimeException('unavailable'));
        $this->get('/dashboard?year=2026&dept=si5%7CSPV-02&view=activity')->assertOk()
            ->assertViewHas('activityError', true)->assertSee('Entries could not be loaded')->assertDontSee('Entries reconcile with Actual');
    }

    public function test_untrusted_filter_values_are_rejected_before_queries(): void
    {
        $this->user();
        $mock = Mockery::mock(ErpDashboard::class);
        $mock->shouldNotReceive('snapshot');
        $this->instance(ErpDashboard::class, $mock);
        $this->getJson('/dashboard/spend?year=2026%27')->assertUnprocessable();
        $this->getJson('/dashboard/spend?dept=si5%7CSPV-02&record=1%20OR%201=1')->assertUnprocessable();
        $this->getJson('/dashboard/spend?company=other')->assertNotFound();
        $this->getJson('/dashboard/spend')->assertUnprocessable();
    }

    public function test_erp_descriptions_are_escaped(): void
    {
        $this->user();
        $rows = Fixture::rows();
        $rows[0]['title'] = '<script>alert(1)</script>';
        $this->source($rows);
        $this->get('/dashboard?view=budgets')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_all_dashboard_states_render_with_bilingual_content(): void
    {
        $this->user();
        $this->source()->shouldReceive('activity')->andReturnUsing(fn ($rows, $kind) => Fixture::activity($kind));
        $key = Fixture::rows()[0]['group'];
        $urls = [
            'overview' => '/dashboard?year=2026',
            'budgets' => '/dashboard?year=2026&dept=si5%7CSPV-02&view=budgets',
            'actual' => '/dashboard?year=2026&dept=si5%7CSPV-02&view=activity&budget='.$key,
            'purchases' => '/dashboard?year=2026&dept=si5%7CSPV-02&view=activity&kind=purchases&budget='.$key,
        ];
        foreach ($urls as $name => $url) {
            $response = $this->get($url)->assertOk()->assertSee('data-budget-dashboard', false)->assertSee('data-loc-en', false);
            if (getenv('DASHBOARD_VISUAL_PREVIEW') === '1') {
                $directory = storage_path('framework/testing/dashboard-preview');
                if (! is_dir($directory)) {
                    mkdir($directory, 0775, true);
                }
                file_put_contents($directory.'/'.$name.'.html', $response->getContent());
            }
        }
    }

    public function test_yen_budget_is_shown_in_its_department_without_polluting_thb_totals(): void
    {
        $this->user();
        $rows = Fixture::rows();
        $rows[0]['currency'] = 'JPP';
        $this->source($rows);
        $page = $this->get('/dashboard?company=si5&year=2026')->assertOk()
            ->assertSee('+ 100,000.00 JPY')->assertSee('Not included in THB')->assertDontSee('JPY amounts are yen');
        $this->assertSame(10000000, $page->viewData('total')['budget']);
        $this->assertSame(10000000, $page->viewData('foreign')['JPP']['budget']);
        $this->assertSame(['102'], array_column($page->viewData('rows'), 'id'));
        $department = $page->viewData('departments')[0];
        $this->assertSame(10000000, $department['total']['budget']);
        $this->assertSame(10000000, $department['foreign']['JPP']['budget']);
        $this->assertSame([['code' => 'JPY', 'budget' => '100,000.00']], $page->viewData('chart')['items'][0]['fx']);
        // กลุ่มงบเงินเยนแยกแถวในรายการงบ ไม่รวมกับกลุ่มเงินบาท
        $groups = $this->get('/dashboard?company=si5&year=2026&dept=si5%7CSPV-02&view=budgets')->assertOk()->viewData('groups');
        $this->assertSame(['THB', 'JPP'], array_column($groups, 'currency'));
        $this->getJson('/dashboard/spend?dept=si5%7CSPV-02&record=101')->assertUnprocessable();
    }

    /*
      ═══════════ แท็บ "ค่าใช้จ่ายตามวันที่" (เจ้าของสั่ง 2026-09-17 · DECISIONS 50.11) ═══════════
      ข้อมูลทดสอบ: ถังปีงบ 2026 = 101 (si5 BG26Q1) · 102 (si5 BG26Q2) · 105 (pd BG26Q1) ใช้ไปถังละ 60,000.00
      รายการจ่าย (สตางค์): si5 15 ม.ค. 40,000 · 3 เม.ย. 30,000 · 2 พ.ค. 50,000 · 20 พ.ค. คืน −10,000
                          pd 2 พ.ค. 60,000   → รวม 170,000.00 · ถัง BG26Q2 ต่างจากรายการ 10,000.00
    */
    private function spendingFixture(): array
    {
        $day = fn (string $co, string $date, int $n, int $amount) => ['company' => $co, 'dept' => 'SPV-02',
            'dept_key' => $co.'|SPV-02', 'date' => $date, 'n' => $n, 'amount' => $amount];

        return [
            'days' => [
                $day('si5', '2026-01-15', 2, 4000000),
                $day('si5', '2026-04-03', 1, 3000000),
                $day('si5', '2026-05-02', 3, 5000000),
                $day('si5', '2026-05-20', 1, -1000000),
                $day('pd', '2026-05-02', 4, 6000000),
                // 🔴 ถังปีงบ 2026 แต่จ่ายจริงปี 2027 — เจ้าของสั่งว่า "ปี 2026 ต้องมีแค่ 2026" จึงต้องไม่ถูกนับ
                $day('si5', '2027-02-05', 2, 9000000),
            ],
            'ambiguous' => 0,
        ];
    }

    private function expenseSource(): MockInterface
    {
        $mock = $this->source();
        $mock->shouldReceive('spending')->andReturn($this->spendingFixture());

        return $mock;
    }

    /** แถบแท็บ 2 มุมมองแทนหัวเรื่อง · ลิงก์พาเฉพาะ บริษัท/ปี/แผนก · ป้ายงวดงบเป็นรหัสถังจริง */
    public function test_tabs_switch_between_budget_period_and_expense_views(): void
    {
        $this->user();
        $mock = $this->source();
        $mock->shouldNotReceive('spending');

        $html = $this->get('/dashboard?year=2026&quarter=1')->assertOk()->getContent();
        $this->assertStringContainsString('data-dash-tab="period"', $html);
        $this->assertMatchesRegularExpression('~data-dash-tab="period"\s+aria-current="page"~', $html);
        $this->assertStringContainsString('href="'.e(route('dashboard', ['year' => '2026', 'tab' => 'expense'])).'"', $html);
        // ไตรมาสของแท็บงวดงบ (ถัง) ต้องไม่ติดไปแท็บค่าใช้จ่าย เพราะความหมายคนละแบบ
        $this->assertStringNotContainsString('quarter=1&amp;tab=expense', $html);
        $this->assertStringContainsString('img/nav/tab-period.png', $html);
        $this->assertStringContainsString('img/nav/tab-expense.png', $html);

        // 🔴 ป้ายงวดงบ = BG26Q1 ไม่ใช่ Q1 (เจ้าของสั่ง) · ค่าใน URL ยังเป็น 1–4 เหมือนเดิม
        $this->assertStringContainsString('<option value="1" selected>BG26Q1</option>', $html);
        $this->assertStringContainsString('>BG26Q2</option>', $html);
        $this->assertStringNotContainsString('>Q1</option>', $html);

        // ทุกปี = BGxx
        $this->assertStringContainsString('>BGxxQ1</option>', $this->get('/dashboard?year=all')->assertOk()->getContent());
    }

    /** 🔴 แท็บค่าใช้จ่ายต้องเลือกปีงบเสมอ — "ทุกปี" เกินเวลารอของ ERP (เจ้าของเลือก 2026-09-17) */
    public function test_expense_tab_always_needs_a_budget_year(): void
    {
        $this->user();
        $mock = $this->source();
        $mock->shouldNotReceive('spending');

        foreach (['all', 'unassigned'] as $year) {
            $target = $this->get('/dashboard?tab=expense&dept=si5%7CSPV-02&paid_m=2025-05&year='.$year)->assertRedirect()->headers->get('Location');
            parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
            $this->assertSame('2026', $query['year']);
            $this->assertSame('expense', $query['tab']);
            $this->assertSame('si5|SPV-02', $query['dept'], 'แผนกที่เลือกไว้ต้องอยู่ต่อ');
            $this->assertArrayNotHasKey('paid_m', $query, 'ช่วงวันที่ของปีอื่นต้องถูกล้าง');
        }
    }

    /** ยอดทั้งปีนับจากถังชุดเดียวกับแท็บงวดงบ **แต่เฉพาะที่จ่ายในปีนั้น** · ไม่มีช่องงบ/รอตัดจ่าย/คงเหลือ */
    public function test_expense_tab_counts_the_same_budgets_by_payment_date(): void
    {
        $this->user();
        $mock = $this->source();
        $mock->shouldReceive('spending')->once()
            ->withArgs(function (array $rows) {
                $ids = array_column($rows, 'id');
                sort($ids);

                // 🔴 ถังปีงบ 2026 ที่เป็นเงินบาท — ชุดเดียวกับที่แท็บงวดงบใช้คิด "ใช้ไป"
                return $ids === ['101', '102', '105'];
            })
            ->andReturn($this->spendingFixture());

        $page = $this->get('/dashboard?tab=expense&year=2026')->assertOk();
        $html = $page->getContent();
        $expense = $page->viewData('expense');
        // 🔴 รายการที่จ่ายปี 2027 (2 รายการ 90,000.00) ไม่ถูกนับ แม้จะตัดถังปีงบ 2026
        $this->assertSame(['n' => 11, 'amount' => 17000000], $expense['total']);
        $this->assertSame(['level' => 'year', 'key' => '2026', 'from' => '2026-01-01', 'to' => '2027-01-01'], $expense['window']);
        $this->assertNull($expense['previous'], 'ระดับทั้งปีไม่เทียบบนการ์ด — ปีก่อนเป็นถังคนละปี');

        // การ์ดเดียว "ค่าใช้จ่าย" — ไม่มีช่องงบประมาณ/รอตัดจ่าย/คงเหลือ
        $this->assertStringContainsString('bd-stats is-single', $html);
        $this->assertStringContainsString('170,000.00', $html);
        // 🔴 วัดที่ป้ายของการ์ด ไม่ใช่ชื่อคลาสเปล่าๆ — สคริปต์วาดกราฟมี bd-dot budget อยู่ในทุกหน้า
        $this->assertStringNotContainsString('<div class="bd-stat-label"><i class="bd-dot reserve">', $html);
        $this->assertStringNotContainsString('<div class="bd-stat-label"><i class="bd-dot budget">', $html);
        $this->assertStringContainsString('<div class="bd-stat-label"><i class="bd-dot used">', $html);

        // กราฟโหมด spend — แผนกละแท่ง ลิงก์เข้าไปหน้ารายการพาช่วง/ปี/แท็บไปด้วย
        $chart = $page->viewData('chart');
        $this->assertSame('spend', $chart['mode']);
        $items = array_column($chart['items'], null, 'dept_key');
        $this->assertEquals(110000, $items['si5|SPV-02']['v']['budget']);
        $this->assertEquals(60000, $items['pd|SPV-02']['v']['budget']);
        $this->assertSame('110,000.00', $items['si5|SPV-02']['text']['budget']);
        $this->assertSame(7, $items['si5|SPV-02']['count']);
        parse_str((string) parse_url($items['si5|SPV-02']['href'], PHP_URL_QUERY), $link);
        $this->assertSame(['year' => '2026', 'tab' => 'expense', 'dept' => 'si5|SPV-02', 'view' => 'entries'], $link);
        // ไม่มีกราฟวงกลม — แท่งเดียวแบ่งสัดส่วนไม่ได้
        $this->assertStringNotContainsString('data-kind="pie"', $html);

        // 🔴 เจ้าของสั่งเอาแถบกระทบยอดกับแท็บงวดงบออก 2026-09-17 — ห้ามเอากลับมาเอง
        $this->assertStringNotContainsString('ex-recon', $html);
        $this->assertArrayNotHasKey('mismatch', $expense);

        // 🔴 ช่องวันมีเสมอ แม้ยังไม่ได้เลือกเดือน (เจ้าของสั่ง 2026-09-17)
        $this->assertStringContainsString('name="paid_d"', $html);
        $this->assertSame(['2026-01-15', '2026-04-03', '2026-05-02', '2026-05-20'], $expense['periods']['days']);

        // ไม่มี "ทุกปี" ให้เลือกในแท็บนี้
        $this->assertStringNotContainsString('<option value="all"', $html);
        // ตัวเลือกช่วงวันที่จ่าย = ที่มีรายการจริง
        $this->assertSame(['2026-1', '2026-2'], $expense['periods']['quarters'], 'ไม่มีไตรมาสของปี 2027 ให้เลือก');
        $this->assertSame(['2026-01', '2026-04', '2026-05'], $expense['periods']['months']);
    }

    /** เลือกเดือน → ยอดเฉพาะเดือนนั้น + MoM เทียบเดือนก่อน · วันเลือกได้เฉพาะวันที่มีรายการ */
    public function test_expense_month_filter_narrows_and_compares_with_the_previous_month(): void
    {
        $this->user();
        $this->expenseSource();

        // เลือกเดือนมาอย่างเดียว → เติมไตรมาสให้ตรงแล้วพาไปที่อยู่ใหม่
        $target = $this->get('/dashboard?tab=expense&year=2026&paid_m=2026-05')->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
        $this->assertSame('2026-2', $query['paid_q']);
        $this->assertSame('2026-05', $query['paid_m']);

        $page = $this->get('/dashboard?tab=expense&year=2026&paid_q=2026-2&paid_m=2026-05')->assertOk();
        $expense = $page->viewData('expense');
        $this->assertSame(['n' => 8, 'amount' => 10000000], $expense['total']);
        $this->assertSame(['2026-04', '2026-05'], $expense['periods']['months'], 'เดือนแคบตามไตรมาสที่เลือก');
        $this->assertSame(['2026-05-02', '2026-05-20'], $expense['periods']['days'], 'วันแคบตามเดือนที่เลือก');
        $this->assertSame('month', $expense['previous']['window']['level']);
        $this->assertSame('2026-04', $expense['previous']['window']['key']);
        $this->assertEqualsWithDelta(233.33, $expense['previous']['pct'], 0.01);
        $page->assertSee('MoM')->assertSee('▲ +233.33%')->assertSee('เม.ย. 2026');
        // เลือกวันด้วย → DoD · ยอดคืนของทำให้ยอดติดลบได้
        $day = $this->get('/dashboard?tab=expense&year=2026&paid_q=2026-2&paid_m=2026-05&paid_d=2026-05-20')->assertOk();
        $this->assertSame(['n' => 1, 'amount' => -1000000], $day->viewData('expense')['total']);
        $day->assertSee('DoD');
        $chart = $day->viewData('chart');
        $this->assertEquals(-10000, $chart['items'][0]['v']['budget']);
        $this->assertSame('-10,000.00', $chart['items'][0]['text']['budget']);
    }

    /** 🔴 ฐานเป็น 0 ห้ามเดา % — ม.ค. ของถังปีนี้ ไม่มีรายการใน ธ.ค. ปีก่อน */
    public function test_expense_previous_period_without_entries_shows_no_percentage(): void
    {
        $this->user();
        $this->expenseSource();

        $page = $this->get('/dashboard?tab=expense&year=2026&paid_q=2026-1&paid_m=2026-01')->assertOk();
        $previous = $page->viewData('expense')['previous'];
        $this->assertSame('2025-12', $previous['window']['key']);
        $this->assertNull($previous['pct']);
        $page->assertSee('ธ.ค. 2025 ไม่มีรายการ');
    }

    /** ช่วงที่เลือกไม่มีรายการแล้ว → ล้างเฉพาะช่วง ห้ามล้างบริษัท/ปี/แผนก */
    public function test_stale_payment_period_is_cleared_but_the_scope_is_kept(): void
    {
        $this->user();
        $this->expenseSource();

        $target = $this->get('/dashboard?tab=expense&year=2026&dept=si5%7CSPV-02&paid_q=2026-3')->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
        $this->assertArrayNotHasKey('paid_q', $query);
        $this->assertSame('si5|SPV-02', $query['dept']);
        $this->assertSame('2026', $query['year']);
        $this->assertSame('expense', $query['tab']);

        // วันที่ไม่มีรายการ → ล้างเฉพาะวัน เดือนอยู่ต่อ
        $target = $this->get('/dashboard?tab=expense&year=2026&paid_q=2026-2&paid_m=2026-05&paid_d=2026-05-03')->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
        $this->assertArrayNotHasKey('paid_d', $query);
        $this->assertSame('2026-05', $query['paid_m']);

        // วันที่ที่ไม่มีจริงในปฏิทิน → ไม่ผ่านการตรวจตั้งแต่ต้น
        $this->get('/dashboard?tab=expense&year=2026&paid_d=2026-02-30')->assertRedirect();
        $this->get('/dashboard?tab=expense&year=2026&paid_m=2026-13')->assertRedirect();
    }

    /** รายการของแผนก: จำกัดช่วงวันที่ใน SQL · เรียงวันล่าสุด · บอกงวดงบที่ตัด และติดป้าย "ข้ามงวด" */
    public function test_expense_entries_use_the_payment_window_and_show_document_numbers(): void
    {
        $this->user();
        $mock = $this->expenseSource();
        $entry = Fixture::activity()['rows'][0];
        $rows = [
            ['date' => '2026-05-02', 'budget_period' => 'BG26Q1', 'title' => 'Paint thinner'] + $entry,
            // แถวที่ไม่มี PO (ตั้งเบิกด้วยใบสำคัญ) — ต้องบอกเลขใบสำคัญแทนขีดเปล่าๆ
            ['date' => '2026-05-20', 'budget_period' => 'BG26Q2', 'title' => 'Returned roller', 'amount' => -1000000, 'po' => ''] + $entry,
        ];
        $mock->shouldReceive('activity')->once()
            ->withArgs(function ($scoped, $kind, $page, $sort, $picked, $window) {
                return array_column($scoped, 'id') === ['101', '102'] && $kind === 'actual' && $sort === 'date_desc'
                    && $window['from'] === '2026-05-01' && $window['to'] === '2026-06-01';
            })
            ->andReturn(['rows' => $rows, 'count' => 2, 'total' => 4000000, 'page' => 1, 'pages' => 1, 'ambiguous' => 0] + Fixture::activity());
        $mock->shouldNotReceive('activity_options');

        $page = $this->get('/dashboard?tab=expense&year=2026&dept=si5%7CSPV-02&paid_q=2026-2&paid_m=2026-05&view=entries')->assertOk();
        $html = $page->getContent();
        $page->assertSee('งวดงบที่ตัด')->assertSee('Paint thinner')->assertSee('Returned roller');
        // 🔴 ไม่มี PO ต้องบอกเลขเอกสารที่มีจริง ไม่ใช่ขีดเปล่าๆ (เจ้าของถาม 2026-09-17)
        $this->assertStringContainsString('PO26-TEST', $html);
        $this->assertStringContainsString('data-loc-th="ใบสำคัญ"', $html);
        $this->assertStringContainsString('V-TEST', $html);
        // 🔴 เจ้าของสั่งเอาป้าย "ข้ามงวด" ออก 2026-09-17 — คอลัมน์บอกแค่ว่าไปตัดงวดไหน
        $this->assertStringNotContainsString('ex-cross', $html);
        // ยอดคืนเป็นสีเขียว (เงินไหลกลับ)
        $this->assertStringContainsString('<td class="num bd-left">-10,000.00</td>', $html);
        // กลับไปหน้าภาพรวมของแท็บ พาช่วงวันที่ไปด้วย แต่ไม่พาแผนก
        $this->assertStringContainsString('href="'.e(route('dashboard', ['year' => '2026', 'view' => 'overview', 'kind' => 'actual', 'page' => 1, 'sort' => 'date_desc', 'tab' => 'expense', 'paid_q' => '2026-2', 'paid_m' => '2026-05'])).'"', $html);
        // งบอ้างอิงพาไปแท็บงวดงบของถังนั้น
        $this->assertStringContainsString('view=activity', $html);
    }

    /** เปรียบเทียบค่าใช้จ่าย: DoD · MoM · QoQ · YoY คิดจากขอบเขตจริง (เจ้าของสั่ง 2026-09-17) */
    public function test_expense_compare_labels_follow_the_selected_payment_level(): void
    {
        $this->user();
        $this->expenseSource();
        $compare = function (array $a, array $b) {
            $query = http_build_query(['tab' => 'expense', 'year' => '2026',
                'cmpa' => $a + ['on' => '1'], 'cmp' => $b + ['on' => '1']]);

            return $this->get('/dashboard?'.$query)->assertOk();
        };

        $mom = $compare(['year' => '2026', 'paid_m' => '2026-05'], ['year' => '2026', 'paid_m' => '2026-04']);
        $data = $mom->viewData('compare');
        $this->assertTrue($data['expense']);
        $this->assertSame('mom', $data['mode']);
        $this->assertSame(10000000, $data['a']['amount']);
        $this->assertSame(3000000, $data['b']['amount']);
        $this->assertSame(8, $data['a']['n']);
        $this->assertSame(1250000, $data['a']['avg']);
        $this->assertEqualsWithDelta(233.33, $data['fields']['amount']['side']['a']['pct'], 0.01);
        // 🔴 แถวจำนวนรายการเงียบ (ไม่มี %) · เฉลี่ยต่อรายการระบายสีเหมือนค่าใช้จ่าย (เจ้าของสั่ง 2026-09-17)
        $html = $mom->getContent();
        $rows = [];
        preg_match_all('~<td class="txt-left">(.+?)</td>(.+?)</tr>~s', $html, $m, PREG_SET_ORDER);
        foreach ($m as $row) {
            $label = trim(strip_tags($row[1]));
            $rows[$label][] = $row[2];
        }
        $this->assertStringNotContainsString('%', $rows['จำนวนรายการ'][0] ?? '', 'จำนวนรายการต้องไม่เขียน %');
        $this->assertStringContainsString('cv-pct plain', $rows['จำนวนรายการ'][0] ?? '', 'ผลต่างของแถวนี้เป็นตัวอักษรสีดำ');
        $this->assertStringContainsString('cv-pct-slot', $rows['จำนวนรายการ'][0] ?? '', 'ต้องจองช่องไว้ให้ตัวเลขตรงตำแหน่งกับแถวอื่น');
        $this->assertStringContainsString('cv-pct good', $rows['เฉลี่ยต่อรายการ (บาท)'][0] ?? '', 'เฉลี่ยต่อรายการลดลง = เขียว');
        $this->assertStringContainsString('cv-pct bad', $rows['เฉลี่ยต่อรายการ (บาท)'][1] ?? '', 'อีกฝั่งสูงกว่า = แดง');
        // ยอดของอีกฝั่งในประโยคสรุปต้องมีสีกลับด้านกัน
        $this->assertStringContainsString('cv-usage-rate bad', $html);
        $this->assertStringContainsString('cv-usage-rate good', $html);

        $mom->assertSee('<span class="cv-kind">: Month on Month (MoM)</span>', false)
            ->assertSee('รายงานเปรียบเทียบค่าใช้จ่าย')->assertSee('เฉลี่ยต่อรายการ');
        // แถวเทียบมีแค่ค่าใช้จ่าย — ไม่มีงบประมาณ/คงเหลือ
        $mom->assertDontSee('สรุปอัตราการใช้งบ');

        $dod = $compare(['year' => '2026', 'paid_d' => '2026-05-02'], ['year' => '2026', 'paid_d' => '2026-05-20'])->viewData('compare');
        $this->assertSame('dod', $dod['mode']);
        $this->assertSame(11000000, $dod['a']['amount']);
        $this->assertSame(-1000000, $dod['b']['amount']);

        $this->assertSame('qoq', $compare(['year' => '2026', 'paid_q' => '2026-2'], ['year' => '2026', 'paid_q' => '2026-1'])->viewData('compare')['mode']);
        $this->assertSame('yoy', $compare(['year' => '2026'], ['year' => '2025'])->viewData('compare')['mode']);
        // ระดับไม่เท่ากัน (วัน เทียบ เดือน) = ไม่มีชื่อเรียกเฉพาะ
        $mixed = $compare(['year' => '2026', 'paid_d' => '2026-05-02'], ['year' => '2026', 'paid_m' => '2026-04']);
        $this->assertNull($mixed->viewData('compare')['mode']);
        $this->assertStringNotContainsString('<span class="cv-kind">', $mixed->getContent());
        // ฝั่งที่ไม่ได้เลือกปีงบ → บอกตรงๆ ไม่คิดตัวเลข
        $missing = $compare(['year' => '2026'], ['paid_m' => '2026-04']);
        $this->assertTrue($missing->viewData('compare')['b']['missing_year']);
        $missing->assertSee('ต้องเลือกปีงบทั้ง 2 ฝั่ง');
    }

    /**
     * 🔴 หน้าต่างเลือกขอบเขต: ฝั่งเทียบตั้งต้นที่ปีเดียวกับที่ดูอยู่
     *    ช่องปีของแท็บนี้ไม่มี "ทุกปี" ถ้าไม่เลือกให้ เบราว์เซอร์จะหยิบปีล่าสุดในรายการ = เทียบข้ามปีโดยไม่ได้สั่ง
     */
    public function test_expense_compare_dialog_starts_on_the_year_being_viewed(): void
    {
        $this->user();
        $this->expenseSource();

        $html = $this->get('/dashboard?tab=expense&year=2026')->assertOk()->getContent();
        $dialog = substr($html, strpos($html, 'data-modal="cmp-pick"'));
        $dialog = substr($dialog, 0, strpos($dialog, '</form>'));
        $this->assertSame(2, substr_count($dialog, '<option value="2026" selected>2026</option>'), 'ตั้งต้นปีเดียวกันทั้ง 2 ฝั่ง');
        // 🔴 ช่วงวันที่จ่ายเป็น dropdown ที่เดินตามปีของฝั่งนั้น (เจ้าของสั่ง 2026-09-17)
        $this->assertStringNotContainsString('type="month"', $dialog, 'เลิกใช้ช่องกรอกเดือนของเบราว์เซอร์');
        $this->assertStringNotContainsString('type="date"', $dialog);
        /*
          🔴 ห้ามสร้าง "ปฏิทินเปล่า" ไว้ในหน้า (เจ้าของแจ้ง 2026-09-18 ว่าเลือกวันที่ไม่มีรายการแล้วเจอจอว่าง)
             ช่องทั้ง 3 ออกมามีแค่ตัวเลือก "ทั้งหมด" แล้วสคริปต์เติมจากของจริงตอนเปิดหน้าต่าง
        */
        $this->assertStringNotContainsString('data-q=', $dialog, 'ห้ามมี Q1–Q4 ตายตัว');
        $this->assertStringNotContainsString('data-m=', $dialog, 'ห้ามมี ม.ค.–ธ.ค. ตายตัว');
        $this->assertSame(2, substr_count($dialog, 'data-cmp-q'), 'มีช่องไตรมาสทั้ง 2 ฝั่ง');
        $this->assertSame(2, substr_count($dialog, 'data-loc-th="ทุกไตรมาส"'));
        // ยังไม่เลือกเดือน = เลือกวันไม่ได้
        $this->assertSame(2, substr_count($dialog, 'data-cmp-d disabled'));
        // สคริปต์ต้องรู้ที่อยู่ของตัวอ่านช่วงวันที่จริง
        $this->assertStringContainsString('expense-periods', $html);
    }

    /**
     * 🔴 หน้าโหลดนานเพราะขนถังงบทั้งตารางข้ามแลนทุกครั้ง (เจ้าของแจ้ง 2026-09-17)
     *    ตัวเลือกตัวกรองอ่านแบบรวมกลุ่ม ส่วนข้อมูลเต็มขอเฉพาะปีที่หน้านั้นใช้จริง
     */
    public function test_only_the_years_in_play_are_read_in_full_from_erp(): void
    {
        $this->user();
        $asked = [];
        $mock = Mockery::mock(ErpDashboard::class);
        $mock->shouldReceive('scopes')->andReturn(array_map(
            fn ($r) => array_intersect_key($r, array_flip(['company', 'dept', 'model', 'purpose', 'currency', 'year', 'quarter', 'dept_key', 'dept_name', 'dept_group'])),
            Fixture::rows(),
        ));
        $mock->shouldReceive('snapshot')->andReturnUsing(function (array $years = []) use (&$asked) {
            $asked[] = $years;

            return Fixture::rows();
        });
        $this->instance(ErpDashboard::class, $mock);

        $this->get('/dashboard?year=2026')->assertOk();
        $this->assertSame([['2026']], $asked, 'ปีเดียว = ขอ ERP แค่ปีนั้น');

        // หน้าเปรียบเทียบต้องได้ข้อมูลของทั้ง 2 ฝั่ง ไม่งั้นฝั่งที่เทียบจะว่างเปล่า
        $asked = [];
        $this->get('/dashboard?'.http_build_query(['year' => '2026',
            'cmpa' => ['year' => '2026', 'on' => '1'], 'cmp' => ['year' => '2025', 'on' => '1']]))->assertOk();
        $this->assertSame([['2026', '2025']], $asked);

        // ทุกปี / งบที่ยังไม่จัดปี = ต้องอ่านทั้งหมดเหมือนเดิม
        foreach (['all', 'unassigned'] as $year) {
            $asked = [];
            $this->get('/dashboard?year='.$year)->assertOk();
            $this->assertSame([[]], $asked, $year.' ต้องอ่านทุกปี');
        }
    }

    /**
     * 🔴 หน้าต่างเปรียบเทียบต้องเสนอเฉพาะ "ช่วงที่มีรายการจริง"
     *    (เจ้าของแจ้ง 2026-09-18: เลือกวันที่ไม่มีรายการแล้วกดเปรียบเทียบ เจอ "ไม่พบข้อมูล")
     */
    public function test_expense_periods_endpoint_offers_only_ranges_that_have_entries(): void
    {
        $this->user();
        $this->expenseSource();

        $body = $this->getJson('/dashboard/expense-periods?tab=expense&year=2026')->assertOk()->json();

        // ข้อมูลทดสอบมีรายการ ม.ค. · เม.ย. · พ.ค. เท่านั้น → ห้ามมี Q3/Q4 หรือเดือนอื่นโผล่มา
        $this->assertSame(['2026-1', '2026-2'], $body['quarters']);
        $this->assertSame(['2026-01', '2026-04', '2026-05'], $body['months']);
        $this->assertSame(['2026-01-15'], $body['days']['2026-01']);
        $this->assertSame(['2026-05-02', '2026-05-20'], $body['days']['2026-05']);
        $this->assertArrayNotHasKey('2026-02', $body['days'], 'เดือนที่ไม่มีรายการต้องไม่มีวันให้เลือก');

        // ปีที่ไม่ใช่ตัวเลข = ไม่ต้องอ่าน ERP เลย
        $this->assertSame([], $this->getJson('/dashboard/expense-periods?tab=expense&year=all')->assertOk()->json('quarters'));
    }

    /** ช่วงวันที่จ่ายไม่รั่วไปแท็บงวดงบ — ทั้งตัวกรองหน้าและหน้าเปรียบเทียบ */
    public function test_payment_filters_never_leak_into_the_budget_period_tab(): void
    {
        $this->user();
        $mock = $this->source();
        $mock->shouldNotReceive('spending');

        $page = $this->get('/dashboard?year=2026&paid_m=2026-05&paid_q=2026-2')->assertOk();
        $this->assertSame('', $page->viewData('filters')['paid_m']);
        $this->assertSame('', $page->viewData('filters')['tab']);

        $query = http_build_query(['year' => '2026',
            'cmpa' => ['year' => '2026', 'on' => '1'], 'cmp' => ['year' => '2026', 'paid_m' => '2026-04', 'on' => '1']]);
        $compare = $this->get('/dashboard?'.$query)->assertOk()->viewData('compare');
        $this->assertArrayNotHasKey('paid_m', $compare['right']);
        $this->assertArrayNotHasKey('expense', $compare);
    }
}
