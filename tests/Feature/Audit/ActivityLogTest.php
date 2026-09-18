<?php

namespace Tests\Feature\Audit;

use App\Http\Middleware\Authenticate;
use App\Models\Audit\ActivityLog;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * ประวัติการใช้งานระบบ
 *
 * ที่ต้องมีเทสต์คุม: ถ้าการบันทึกประวัติพังแล้วโยน exception ออกมา
 * จะทำให้ล็อกอินไม่ได้ทั้งระบบ — ต้องพิสูจน์ว่าบันทึกได้จริงและไม่ทำให้งานหลักล้ม
 */
class ActivityLogTest extends TestCase
{
    use RefreshModuleDatabase;

    private function makeUser(array $attributes = []): AppUser
    {
        static $seq = 0;
        $seq++;

        $code = $attributes['employee_code'] ?? ('L'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT));

        Employee::create([
            'insight_id' => 7000 + $seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'name_th' => 'ทดสอบ',
            'surname_th' => 'ประวัติ',
            'job_code' => 'P1',
            'job_th' => 'โปรแกรมเมอร์',
            'job_en' => 'Programmer',
            'dept_code' => 'IT01',
            'dept_th' => 'ไอที',
            'dept_en' => 'IT',
            'branch_code' => '01',
            'branch_th' => 'สำนักงานใหญ่',
            'branch_en' => 'Head office',
            'emp_status' => '1',
        ]);

        return AppUser::create([
            'insight_id' => 6000 + $seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'password' => 'secret',
            'role' => $attributes['role'] ?? 'user',
            'full_name_th' => 'ทดสอบ ประวัติ',
            'job_code' => 'P1',
        ]);
    }

    private function signIn(AppUser $user): self
    {
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        return $this;
    }

    // ── บันทึกการเข้า-ออกระบบ ────────────────────────────────────────

    public function test_successful_sign_in_is_recorded(): void
    {
        $user = $this->makeUser();

        $this->post('/login', ['employee_code' => $user->employee_code, 'password' => 'secret']);

        $row = ActivityLog::where('event', 'login')->first();

        $this->assertNotNull($row);
        $this->assertSame($user->employee_code, $row->employee_code);
        $this->assertNotNull($row->detail_th);
        $this->assertNotNull($row->detail_en);   // ต้องมีครบ 2 ภาษาตามกฎของโปรเจค
    }

    public function test_failed_sign_in_is_recorded_without_the_password(): void
    {
        $user = $this->makeUser();

        $this->post('/login', ['employee_code' => $user->employee_code, 'password' => 'wrong-password-here']);

        $row = ActivityLog::where('event', 'login_failed')->firstOrFail();

        // 🔴 ห้ามมีรหัสผ่านหลุดลงประวัติเด็ดขาด
        $this->assertStringNotContainsString('wrong-password-here', (string) $row->detail_th);
        $this->assertStringNotContainsString('wrong-password-here', (string) $row->detail_en);
        $this->assertStringNotContainsString('wrong-password-here', (string) $row->url);
    }

    public function test_sign_out_is_recorded_with_the_person_who_left(): void
    {
        $user = $this->makeUser();

        $this->signIn($user)->post('/logout');

        $row = ActivityLog::where('event', 'logout')->firstOrFail();
        $this->assertSame($user->employee_code, $row->employee_code);
    }

    // ── บันทึกการเปิดหน้า ───────────────────────────────────────────

    public function test_opening_a_page_is_recorded(): void
    {
        $user = $this->makeUser();

        $this->signIn($user)->get('/dashboard')->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'page_view',
            'employee_code' => $user->employee_code,
            'subject' => 'dashboard',
        ]);
    }

    public function test_json_requests_are_not_recorded(): void
    {
        // ช่องค้นหายิงทุกครั้งที่พิมพ์ ถ้าบันทึกด้วยตารางจะท่วมจนใช้งานไม่ได้
        $admin = $this->makeUser(['role' => 'admin']);

        $this->signIn($admin)->getJson('/access/system/search-users?q=ทด')->assertOk();

        $this->assertDatabaseMissing('activity_logs', ['subject' => 'access.system.search']);
    }

    // ── บันทึกการเปลี่ยนสิทธิ์ ──────────────────────────────────────

    public function test_changing_function_access_is_recorded(): void
    {
        // สิทธิ์ระดับโมดูลถูกยุบทิ้งแล้ว เหลือทางเดียวคือรายหัวข้อย่อย (เจ้าของสั่ง 2026-09-03)
        $admin = $this->makeUser(['role' => 'admin']);
        $target = $this->makeUser();

        $this->signIn($admin)->post('/access/modules/function', [
            'module_id' => 'budget',
            // 🔴 fn.budget.inbox ตั้งสิทธิ์ไม่ได้แล้ว (ผู้ขอเลือกผู้อนุมัติเอง) จึงใช้หัวข้อที่ยังตั้งได้
            'function_key' => 'fn.budget.approved',
            'employee_codes' => [$target->employee_code],
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'function_access_saved',
            'subject' => 'fn.budget.approved',
            'employee_code' => $admin->employee_code,
        ]);
    }

    // ── หน้าจอ ──────────────────────────────────────────────────────

    public function test_log_pages_are_admin_only(): void
    {
        $this->signIn($this->makeUser())->get('/activity')->assertForbidden();
        $this->signIn($this->makeUser())->get('/activity/sign-ins')->assertForbidden();
    }

    public function test_sign_in_page_shows_only_sign_in_events(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        ActivityLog::create(['event' => 'login', 'detail_th' => 'เข้าระบบทดสอบ', 'created_at' => now()]);
        ActivityLog::create(['event' => 'page_view', 'detail_th' => 'เปิดหน้าทดสอบ', 'created_at' => now()]);

        $html = $this->signIn($admin)->get('/activity/sign-ins')->assertOk()->getContent();

        $this->assertStringContainsString('เข้าระบบทดสอบ', $html);
        $this->assertStringNotContainsString('เปิดหน้าทดสอบ', $html);
    }

    public function test_each_column_filter_narrows_the_list(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        ActivityLog::create([
            'event' => 'login', 'employee_code' => 'AAA11', 'actor_name' => 'สมชาย ทดสอบ',
            'detail_th' => 'บรรทัดของสมชาย', 'ip' => '10.0.0.9', 'agent' => 'Chrome · Windows', 'created_at' => now(),
        ]);
        ActivityLog::create([
            'event' => 'login', 'employee_code' => 'BBB22', 'actor_name' => 'สมหญิง ทดสอบ',
            'detail_th' => 'บรรทัดของสมหญิง', 'ip' => '192.168.1.5', 'agent' => 'Firefox · macOS', 'created_at' => now(),
        ]);

        // กรองคอลัมน์ "ผู้ใช้" ด้วยรหัสพนักงาน
        $html = $this->signIn($admin)->get('/activity?who=AAA11')->assertOk()->getContent();
        $this->assertStringContainsString('บรรทัดของสมชาย', $html);
        $this->assertStringNotContainsString('บรรทัดของสมหญิง', $html);

        // กรองคอลัมน์ "ผู้ใช้" ด้วยชื่อ
        $html = $this->signIn($admin)->get('/activity?who='.urlencode('สมหญิง'))->assertOk()->getContent();
        $this->assertStringContainsString('บรรทัดของสมหญิง', $html);
        $this->assertStringNotContainsString('บรรทัดของสมชาย', $html);

        // กรองคอลัมน์ "รายละเอียด"
        $html = $this->signIn($admin)->get('/activity?detail='.urlencode('สมชาย'))->assertOk()->getContent();
        $this->assertStringContainsString('บรรทัดของสมชาย', $html);
        $this->assertStringNotContainsString('บรรทัดของสมหญิง', $html);

        // กรองคอลัมน์ "มาจาก" ด้วย IP
        $html = $this->signIn($admin)->get('/activity?ip=192.168')->assertOk()->getContent();
        $this->assertStringContainsString('บรรทัดของสมหญิง', $html);
        $this->assertStringNotContainsString('บรรทัดของสมชาย', $html);

        // กรองคอลัมน์ "มาจาก" ด้วยชื่อเบราว์เซอร์
        $html = $this->signIn($admin)->get('/activity?ip=Firefox')->assertOk()->getContent();
        $this->assertStringContainsString('บรรทัดของสมหญิง', $html);
        $this->assertStringNotContainsString('บรรทัดของสมชาย', $html);
    }

    public function test_wildcard_characters_are_not_treated_as_wildcards(): void
    {
        // ผู้ใช้พิมพ์ % ต้องหมายถึงตัวอักษร % จริงๆ ไม่ใช่ "อะไรก็ได้"
        $admin = $this->makeUser(['role' => 'admin']);
        ActivityLog::create(['event' => 'login', 'detail_th' => 'บรรทัดปกติ', 'created_at' => now()]);

        $html = $this->signIn($admin)->get('/activity?detail='.urlencode('%'))->assertOk()->getContent();

        $this->assertStringNotContainsString('บรรทัดปกติ', $html);
    }

    public function test_filtering_by_event_narrows_the_list(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        ActivityLog::create(['event' => 'login', 'detail_th' => 'บรรทัดเข้าระบบ', 'created_at' => now()]);
        ActivityLog::create(['event' => 'logout', 'detail_th' => 'บรรทัดออกระบบ', 'created_at' => now()]);

        $html = $this->signIn($admin)->get('/activity?event=logout')->assertOk()->getContent();

        $this->assertStringContainsString('บรรทัดออกระบบ', $html);
        $this->assertStringNotContainsString('บรรทัดเข้าระบบ', $html);
    }
}
