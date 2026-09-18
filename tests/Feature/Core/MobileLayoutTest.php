<?php

namespace Tests\Feature\Core;

use App\Http\Middleware\Authenticate;
use App\Models\Access\FunctionUser;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Services\Erp\ErpDashboard;
use Mockery;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\Fixtures\ErpDashboardFixture as Fixture;
use Tests\TestCase;

/**
 * กฎการใช้งานผ่านโทรศัพท์ — เจ้าของแจ้งบั๊ก 6 ข้อเมื่อ 2026-09-18
 *
 * 🔴 เทสต์ชุดนี้คุม "กติกา" ไม่ได้คุมหน้าตา — วัดหน้าตาจริงด้วย headless Chrome แยกต่างหาก
 *    สิ่งที่คุมคือกฎที่ถ้าหายไปแล้วบั๊กเดิมจะกลับมาทันทีโดยไม่มีอะไรฟ้อง
 */
class MobileLayoutTest extends TestCase
{
    use RefreshModuleDatabase;

    private function user(bool $dashboard = true): AppUser
    {
        // migration เปิด fn.overview.dashboard ให้ "ทุกคน" ไว้ — ต้องลบก่อน ไม่งั้นวัดการกันสิทธิ์ไม่ได้
        FunctionUser::where('function_key', 'fn.overview.dashboard')->delete();
        if ($dashboard) {
            FunctionUser::create(['module_id' => 'dashboard', 'function_key' => 'fn.overview.dashboard', 'employee_code' => '*']);
        }

        Employee::create(['insight_id' => 9701, 'company' => 'TEST', 'employee_code' => 'TEST20', 'name_th' => 'ทดสอบ',
            'surname_th' => 'Mobile', 'dept_code' => 'D01', 'job_code' => 'S1', 'emp_status' => '1']);
        $user = AppUser::create(['insight_id' => 9701, 'company' => 'TEST', 'employee_code' => 'TEST20',
            'password' => 'secret', 'role' => 'user', 'full_name_th' => 'ทดสอบ Mobile', 'dept_code' => 'D01']);
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        return $user;
    }

    /** ERP ปลอม — phpunit ตั้ง ERP_ENABLED=false ไว้ หน้าผลเปรียบเทียบจึงไม่มีข้อมูลให้วาดถ้าไม่จำลอง */
    private function erp(): void
    {
        $rows = Fixture::rows();
        $mock = Mockery::mock(ErpDashboard::class);
        $mock->shouldReceive('snapshot')->andReturn($rows);
        $mock->shouldReceive('scopes')->andReturn(array_map(
            fn ($r) => array_intersect_key($r, array_flip(['company', 'dept', 'model', 'purpose', 'currency', 'year', 'quarter', 'dept_key', 'dept_name', 'dept_group'])) + ['rows' => 1],
            $rows,
        ));
        $this->instance(ErpDashboard::class, $mock);
    }

    /**
     * 🐛 ข้อ 1 (เจ้าของแจ้ง "คนที่ไม่มีสิทธิ ... มันค้างไม่มีปุ่มให้กด และล็อคเอ้าไม่ได้")
     *
     *    หน้า error ดิบของ Laravel ไม่มีเมนู ไม่มีปุ่ม ไม่มีทางออก
     *    บนคอมยังกดย้อนกลับของเบราว์เซอร์ได้ แต่บนมือถือที่เปิดจากลิงก์ตรง = ตัน
     */
    public function test_the_no_permission_page_offers_a_way_out(): void
    {
        $this->user(dashboard: false);

        $html = $this->get('/dashboard')->assertForbidden()->getContent();

        // 🔴 วัดที่มาร์กอัปจริง ไม่ใช่คำเปล่าๆ — คำแปลทุกคำอยู่ในพจนานุกรมท้ายหน้าด้วย
        $this->assertMatchesRegularExpression('~href="[^"]*/profile"~', $html, 'ต้องมีปุ่มกลับไปหน้าข้อมูลส่วนตัว');
        $this->assertMatchesRegularExpression('~<form method="POST" action="[^"]*/logout"~', $html, 'ต้องออกจากระบบได้');

        // 🔴 ห้ามพาไป /dashboard — หน้านั้นมีด่านสิทธิ์ของตัวเอง คนที่เพิ่งโดน 403 จะวนอยู่ในหน้านี้ไม่จบ
        $this->assertDoesNotMatchRegularExpression('~href="[^"]*/dashboard"~', $html);
    }

    /** คนที่ยังไม่ล็อกอินต้องไม่ทำให้หน้า error พังเอง (ไม่มี $me ให้ใช้) */
    public function test_the_error_page_works_for_a_guest_too(): void
    {
        // ไม่มี current_user ผูกไว้ = สถานะของคนที่ยังไม่ล็อกอิน (หน้าต้องไม่พังเอง)
        $html = view('errors.403')->render();

        $this->assertMatchesRegularExpression('~href="[^"]*/login"~', $html);
        $this->assertDoesNotMatchRegularExpression('~<form method="POST" action="[^"]*/logout"~', $html);
    }

    /**
     * 🐛 ข้อ 6 (เจ้าของแจ้ง "เลื่อนดูข้อมูลล่างสุดไม่ได้ เหมือนติดขอบจอ")
     *
     *    100vh บนมือถือ = ความสูงตอนแถบที่อยู่ของเบราว์เซอร์หุบ ซึ่งสูงกว่าที่เห็นจริง
     *    โครงหน้าจึงยาวเกินจอ และ body สั่ง overflow: hidden ไว้ จึงเลื่อนตามลงไปไม่ได้
     */
    public function test_the_shell_is_measured_in_dynamic_viewport_height(): void
    {
        $this->user();

        $css = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~\.shell \{[^}]*height: 100dvh~s', $css);
        // เขียน vh ไว้ก่อนเป็นตัวสำรองของเบราว์เซอร์เก่า — ห้ามลบทิ้ง
        $this->assertMatchesRegularExpression('~\.shell \{[^}]*height: 100vh~s', $css);
    }

    /**
     * 🐛 ข้อ 2 (เจ้าของแจ้ง "แถบกระดิ่งหรือ ช่วยเหลือ หรืออื่นๆ Modal มันแสดงเกินจอ")
     *
     *    แผงพวกนี้ยึดขอบปุ่มแล้วกว้างคงที่ 190–380px ปุ่มอยู่กลางแถบบน
     *    แผงจึงยื่นเลยขอบจอแล้วถูกตัดจนอ่านไม่ครบ
     */
    public function test_topbar_panels_become_full_width_sheets_on_a_phone(): void
    {
        $this->user();

        $css = $this->get('/dashboard')->assertOk()->getContent();

        // ทุกแผงต้องมีกฎของจอ ≤560px ที่ปลดความกว้างคงที่ออก
        foreach (['\.who-menu, \.lang-menu', '\.help-pop', '\.bell-pop'] as $sel) {
            $this->assertMatchesRegularExpression(
                '~@media \(max-width: 560px\)[^@]*'.$sel.' \{[^}]*position: fixed~s',
                $css,
                'แผงนี้ยังไม่ได้กางเต็มจอบนมือถือ: '.$sel,
            );
        }
    }

    /**
     * 🐛 ตารางเปรียบเทียบ "ข้อมูลไม่ตรงหัวคอลัมน์" ในมือถือ (เจ้าของแจ้ง 2026-09-18)
     *
     *    ต้นเหตุที่ 1: `.tbl-wrap { overflow-x: auto }` เขียนไว้ใน **ชิ้นส่วนของโมดูล**
     *    (สิทธิ์ · ประวัติการใช้งาน · งบประมาณ) หน้าที่ไม่ได้ include จึงได้ `overflow-x: visible`
     *    = ส่วนที่ล้น **ถูกตัดทิ้งและเลื่อนไปดูไม่ได้** ไม่ใช่แค่ต้องเลื่อน
     *    วัดจริงที่ 390px: กรอบ 317px · ตาราง 417px · scrollLeft ค้างที่ 0 → คอลัมน์ "ผลต่าง" หายทั้งคอลัมน์
     */
    public function test_wide_tables_can_scroll_inside_their_own_frame_on_every_page(): void
    {
        $this->user();

        $css = $this->get('/dashboard')->assertOk()->getContent();

        // 🔴 ต้องมาจากธีมกลาง — ทุกหน้าที่ใช้ .tbl-wrap ต้องได้กฎนี้โดยไม่ต้อง include ชิ้นส่วนของโมดูลอื่น
        $this->assertStringContainsString('.tbl-wrap { overflow-x: auto; }', $css);
    }

    /**
     * 🐛 ตัวเดียวกัน — ต้นเหตุที่ 2: ช่อง % จองความกว้างตายตัว 8.5em (106px) ทุกแถว
     *    กินไปหนึ่งในสามของตาราง จนคอลัมน์ "ผลต่าง" หลุดกรอบบนมือถือ
     */
    public function test_the_comparison_table_drops_its_fixed_percent_slot_on_a_phone(): void
    {
        $this->user();

        $this->erp();

        // 🔴 ต้องเป็น "หน้าผลเปรียบเทียบ" — compare-style ถูก include เฉพาะหน้านี้ ไม่ใช่แดชบอร์ดเปล่า
        $css = $this->get('/dashboard?cmpa[on]=1&cmpa[year]=2026&cmp[on]=1&cmp[year]=2025')
            ->assertOk()->getContent();

        // จอกว้างยังจองช่อง % ไว้เหมือนเดิม (เจ้าของสั่งไว้ 2026-09-17 ให้ยอดเงินชิดขวาตรงกันทุกแถว)
        $this->assertStringContainsString('flex: 0 0 8.5em', $css);

        // มือถือย้าย % ลงใต้ยอดเงิน แล้วเลิกจองช่องเปล่า
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 560px\)[^@]*\.cv-panel \.cv-cell \.cv-pct-slot \{ display: none~s',
            $css,
        );
    }

    /**
     * 🐛 ข้อ 4 (เจ้าของแจ้ง "กดกระดิ่งแล้วกดเปลี่ยนภาษา มันไม่ปิดแผงเก่า")
     *
     *    แต่ละแผงมีตัวปิดของตัวเองที่ดักการกดทั้งหน้า แต่ทุกปุ่มสั่ง stopPropagation()
     *    กดปุ่มอีกอันจึงไม่มีใครได้ยิน แผงเก่าเลยค้างซ้อนกัน
     */
    public function test_every_topbar_button_closes_the_other_panels_first(): void
    {
        $this->user();

        $js = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('window.BMS.closePopovers = function (keep)', $js);

        // 4 ปุ่มต้องเรียกตัวปิดกลางก่อนเปิดของตัวเอง
        foreach (['closePopovers(helpMenu)', 'closePopovers(whoMenu)', 'closePopovers(pop)', 'closePopovers(menu)'] as $call) {
            $this->assertStringContainsString($call, $js, 'ยังมีปุ่มที่ไม่ได้ปิดแผงอื่นก่อนเปิด: '.$call);
        }
    }

    /**
     * 🐛 ข้อ 5 (เจ้าของสั่ง "เปิดแถบเมนูแล้วกดตรงกลางหน้า ให้ปิดแถบเมนูนั้นด้วย")
     *
     * 🔴 ต้องกันด้วย narrow() เสมอ — จอกว้างเมนูซ้ายเป็นคอลัมน์ถาวรของหน้า ปิดทิ้งเองไม่ได้
     */
    public function test_tapping_the_content_closes_the_menu_drawer_on_narrow_screens_only(): void
    {
        $this->user();

        $js = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            "~contentBox\.addEventListener\('click', function \(\) \{\s*if \(! narrow\(\)\) \{ return; \}~s",
            $js,
        );
        $this->assertStringContainsString("shell.classList.remove('is-drawer-open')", $js);
    }
}
