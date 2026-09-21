<?php

namespace Tests\Feature\Core;

use App\Http\Middleware\Authenticate;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * หมดเวลาเชื่อมต่อ — เจ้าของแจ้ง 2026-09-18 ว่า "มันจะล็อคเอ้าออกเองแล้วไม่ได้บอก"
 *
 * `SESSION_LIFETIME` คือ **นาทีนับจากคำขอครั้งล่าสุด** ไม่ใช่นับตั้งแต่ล็อกอิน
 * เปิดหน้าค้างไว้นานเกินกำหนดแล้วกดอะไรสักอย่าง = เด้งไปหน้าล็อกอินโดยไม่มีคำอธิบาย
 */
class SessionTimeoutTest extends TestCase
{
    use RefreshModuleDatabase;

    private function user(): AppUser
    {
        Employee::create(['insight_id' => 9601, 'company' => 'TEST', 'employee_code' => 'TEST30', 'name_th' => 'ทดสอบ',
            'surname_th' => 'Session', 'dept_code' => 'D01', 'job_code' => 'S1', 'emp_status' => '1']);
        $user = AppUser::create(['insight_id' => 9601, 'company' => 'TEST', 'employee_code' => 'TEST30',
            'password' => 'secret', 'role' => 'user', 'full_name_th' => 'ทดสอบ Session', 'dept_code' => 'D01']);
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        return $user;
    }

    /** ตัวจับเวลาต้องเดินตามค่าจริงใน .env ไม่ใช่ตัวเลขที่เขียนค้างไว้ในหน้า */
    public function test_the_countdown_follows_the_configured_session_lifetime(): void
    {
        $this->user();
        config(['session.lifetime' => 45]);

        $html = $this->get('/profile')->assertOk()->getContent();

        $this->assertStringContainsString('var LIFETIME = 45 * 60 * 1000;', $html);
        // 45 นาที หารด้วย 60 ไม่ลงตัว จึงต้องอ่านเป็น "นาที" ไม่ใช่ปัดเป็นชั่วโมง
        $this->assertStringContainsString("var UNIT_KEY = 'common.minutes';", $html);
        $this->assertStringContainsString('var AMOUNT = 45;', $html);
    }

    /** 120 นาที ต้องอ่านว่า "2 ชั่วโมง" ไม่ใช่ "120 นาที" */
    public function test_two_hours_reads_as_hours_not_minutes(): void
    {
        $this->user();
        config(['session.lifetime' => 120]);

        $html = $this->get('/profile')->assertOk()->getContent();

        $this->assertStringContainsString('var AMOUNT = 2;', $html);
        $this->assertStringContainsString("var UNIT_KEY = 'common.hours';", $html);
    }

    /**
     * 🔴 ต้องนับด้วยเวลาจริง (Date.now) ไม่ใช่ setTimeout ยาวตัวเดียว
     *    มือถือหยุดเดินเวลาให้แท็บเบื้องหลัง และคอมที่ sleep ก็หยุด — ตัวจับเวลาจะเพี้ยนเสมอ
     * 🔴 และต้องต่ออายุเมื่อมีคำขอวิ่งออกไป ไม่งั้นเด้งทั้งที่ยังใช้งานได้อยู่
     */
    public function test_the_countdown_uses_wall_clock_and_resets_on_real_requests(): void
    {
        $this->user();

        $html = $this->get('/profile')->assertOk()->getContent();

        $this->assertStringContainsString('Date.now() >= deadline', $html);
        $this->assertStringContainsString("document.addEventListener('visibilitychange'", $html);
        // ต่ออายุตามคำขอจริง — ครอบทั้ง fetch และ XMLHttpRequest
        $this->assertStringContainsString('window.fetch = function ()', $html);
        $this->assertStringContainsString('XMLHttpRequest.prototype.send = function ()', $html);
    }

    /** หน้าต่างต้องมีทั้งปุ่มเข้าสู่ระบบใหม่และปุ่มปิด ตามที่เจ้าของสั่ง */
    public function test_the_modal_offers_sign_in_again_and_a_close_button(): void
    {
        $this->user();

        $html = $this->get('/profile')->assertOk()->getContent();

        $this->assertStringContainsString("window.BMS.t('session.expiredTitle')", $html);
        $this->assertStringContainsString("ok: window.BMS.t('session.signInAgain')", $html);
        $this->assertStringContainsString("cancel: window.BMS.t('common.close')", $html);
    }

    /** จอมือถือ: ปุ่มในหน้าต่างกลางจอเรียงลงมา ไม่ใช่บีบ 2 ปุ่มข้างกันจนตัดคำ */
    public function test_dialog_buttons_stack_on_a_phone(): void
    {
        $this->user();

        $css = $this->get('/profile')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 560px\)[^@]*\.dlg-foot \{ flex-direction: column~s',
            $css,
        );
    }

    /**
     * กดส่งฟอร์มหลัง session หมดอายุ = 419
     * 🔴 ของเดิมเป็นหน้า "Page Expired" ดิบของ Laravel ที่ไม่มีทางออกเลย
     */
    public function test_the_page_expired_screen_explains_itself_and_offers_a_way_out(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringContainsString('หมดเวลาเชื่อมต่อ', $html);
        $this->assertMatchesRegularExpression('~href="[^"]*/login"~', $html);
    }
}
