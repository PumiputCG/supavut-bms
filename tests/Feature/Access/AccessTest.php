<?php

namespace Tests\Feature\Access;

use App\Http\Middleware\Authenticate;
use App\Models\Access\AccessAdmin;
use App\Models\Access\FunctionUser;
use App\Models\Access\LoginPosition;
use App\Models\Core\AppUser;
use App\Models\Core\Employee;
use App\Services\Access\AccessService;
use App\Support\NavMenu;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * สิทธิ์การเข้าถึง — ครอบกติกาที่เจ้าของสั่งไว้ 3 ข้อ
 *   🟢 มีสิทธิ์          แสดงปกติ กดได้
 *   ⚪ ไม่มีสิทธิ์        เทาลง กดไม่ได้ + tooltip
 *   🔒 เมนูผู้ดูแลระบบ    ซ่อนไปเลย
 */
class AccessTest extends TestCase
{
    use RefreshModuleDatabase;

    private function makeUser(array $attributes = []): AppUser
    {
        static $seq = 0;
        $seq++;

        $code = $attributes['employee_code'] ?? ('E'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT));

        Employee::create([
            'insight_id' => 9000 + $seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'name_th' => 'ทดสอบ',
            'surname_th' => 'ระบบ',
            'job_code' => $attributes['job_code'] ?? 'P1',
            'job_th' => 'โปรแกรมเมอร์',
            'job_en' => 'Programmer',
            'emp_status' => '1',
        ]);

        return AppUser::create([
            'insight_id' => 8000 + $seq,
            'company' => 'TEST',
            'employee_code' => $code,
            'password' => 'secret',
            'role' => $attributes['role'] ?? 'user',
            'full_name_th' => 'ทดสอบ ระบบ',
            'job_code' => $attributes['job_code'] ?? 'P1',
        ]);
    }

    /**
     * ล็อกหัวข้อย่อยของโมดูล budget ให้เป็นของคนอื่น
     *
     * 🔴 "ไม่ระบุใคร = เปิดให้ทุกคน" ดังนั้นถ้าจะทดสอบว่าปิด ต้องระบุคนอื่นให้ครบทุกข้อ
     *
     * @param  array<int,string>  $except  ข้อที่อยากเว้นไว้ให้ยังเปิดอยู่
     */
    /** ติ๊ก "ทุกคน" ให้หัวข้อย่อยนี้ — กติกาใหม่ต้องเปิดอย่างชัดเจน */
    private function openToEveryone(string $functionKey, string $moduleId = 'budget'): void
    {
        FunctionUser::create([
            'module_id' => $moduleId,
            'function_key' => $functionKey,
            'employee_code' => AccessService::EVERYONE,
        ]);
    }

    private function lockBudgetFunctions(array $except = []): void
    {
        $module = collect(NavMenu::permissionedModules())->firstWhere('id', 'budget');

        foreach ($module['functions'] as $fn) {
            if (in_array($fn['key'], $except, true)) {
                continue;
            }

            FunctionUser::create([
                'module_id' => 'budget',
                'function_key' => $fn['key'],
                'employee_code' => 'SOMEONE-ELSE',
            ]);
        }
    }

    private function signIn(AppUser $user): self
    {
        $this->withSession([Authenticate::SESSION_KEY => $user->id]);

        return $this;
    }

    // ── ผู้ดูแลระบบ ──────────────────────────────────────────────────

    public function test_admin_from_bms_table_counts_as_admin(): void
    {
        $user = $this->makeUser();
        $this->assertFalse(app(AccessService::class)->isAdmin($user));

        AccessAdmin::create(['employee_code' => $user->employee_code, 'granted_at' => now()]);

        // ต้องสร้าง service ใหม่ เพราะตัวเดิมจำรายชื่อแอดมินไว้แล้วในคำขอเดียวกัน
        $this->assertTrue((new AccessService)->isAdmin($user));
    }

    public function test_non_admin_cannot_open_access_pages(): void
    {
        $this->signIn($this->makeUser())->get('/access/system')->assertForbidden();
        $this->signIn($this->makeUser())->get('/access/modules')->assertForbidden();
    }

    public function test_admin_can_open_access_pages(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        $this->signIn($admin)->get('/access/system')->assertOk()->assertSee('ผู้ดูแลระบบ');
        $this->signIn($admin)->get('/access/modules')->assertOk();
    }

    public function test_admin_cannot_remove_their_own_rights(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $row = AccessAdmin::create(['employee_code' => $admin->employee_code, 'granted_at' => now()]);

        $this->signIn($admin)->delete('/access/system/admins/'.$row->id);

        $this->assertDatabaseHas('access_admins', ['id' => $row->id]);
    }

    // ── ตำแหน่งที่เข้าระบบได้ ─────────────────────────────────────────

    public function test_position_with_login_turned_off_cannot_sign_in(): void
    {
        $user = $this->makeUser(['job_code' => 'W1']);
        LoginPosition::create(['job_code' => 'W1', 'can_login' => false]);

        $this->post('/login', [
            'employee_code' => $user->employee_code,
            'password' => 'secret',
        ])->assertRedirect();

        $this->assertNull(session(Authenticate::SESSION_KEY));
    }

    public function test_admin_can_sign_in_even_when_their_position_is_blocked(): void
    {
        $user = $this->makeUser(['job_code' => 'W1', 'role' => 'admin']);
        LoginPosition::create(['job_code' => 'W1', 'can_login' => false]);

        $this->post('/login', [
            'employee_code' => $user->employee_code,
            'password' => 'secret',
        ])->assertRedirect('/profile');
    }

    public function test_position_not_listed_yet_can_still_sign_in(): void
    {
        // ตำแหน่งใหม่ที่ยังไม่มีในตารางสิทธิ์ ต้องไม่ถูกกันออกโดยบังเอิญ
        $user = $this->makeUser(['job_code' => 'ZZ9']);

        $this->post('/login', [
            'employee_code' => $user->employee_code,
            'password' => 'secret',
        ])->assertRedirect('/profile');
    }

    // ── สิทธิ์รายโมดูล ───────────────────────────────────────────────

    public function test_module_with_nobody_selected_is_hidden(): void
    {
        /*
          🔴 กติกาใหม่ 2026-09-07: ยังไม่ได้กำหนดใคร = ไม่มีใครเห็น
             (ของเดิมคือ "ไม่ระบุ = ทุกคนเห็น" ซึ่งอ่านไม่ออกว่าตั้งใจเปิดหรือยังไม่ได้ตั้ง)
        */
        $user = $this->makeUser(['job_code' => 'P1']);

        $this->assertNotContains('budget', (new AccessService)->visibleModuleIds($user));
    }

    public function test_module_is_visible_when_a_function_inside_is_open_to_everyone(): void
    {
        $user = $this->makeUser(['job_code' => 'P1']);
        $this->openToEveryone('fn.budget.invest');

        $this->assertContains('budget', (new AccessService)->visibleModuleIds($user));
    }

    public function test_module_is_hidden_when_every_function_inside_belongs_to_someone_else(): void
    {
        /*
          🔴 ไม่มีสิทธิ์ระดับโมดูลให้ตั้งเองแล้ว (เจ้าของสั่งยุบ 2026-09-03)
             เห็นโมดูลไหม = คำนวณจากหัวข้อย่อย — ใช้ไม่ได้เลยสักข้อ ถึงจะซ่อนทั้งโมดูล
        */
        $user = $this->makeUser(['job_code' => 'P1']);
        $this->lockBudgetFunctions();

        $ids = (new AccessService)->visibleModuleIds($user);

        $this->assertNotContains('budget', $ids);
        // 🔴 โมดูลอื่นที่ยังไม่ได้กำหนดใคร ก็ไม่เห็นเหมือนกัน (กติกาใหม่)
        $this->assertNotContains('pr', $ids);
    }

    public function test_one_open_function_is_enough_to_see_the_module(): void
    {
        $user = $this->makeUser(['job_code' => 'P1']);
        $this->lockBudgetFunctions(['fn.budget.invest']);   // เว้นข้อนี้ไว้
        $this->openToEveryone('fn.budget.invest');          // แล้วเปิดให้ทุกคนอย่างชัดเจน

        $this->assertContains('budget', (new AccessService)->visibleModuleIds($user));
    }

    public function test_admin_sees_every_module_regardless_of_the_rules(): void
    {
        $admin = $this->makeUser(['job_code' => 'P1', 'role' => 'admin']);
        $this->lockBudgetFunctions();

        $this->assertContains('budget', (new AccessService)->visibleModuleIds($admin));
    }

    // ── หน้าจอ: เทา / ซ่อน ───────────────────────────────────────────

    public function test_denied_module_is_greyed_out_not_hidden(): void
    {
        $user = $this->makeUser(['job_code' => 'P1']);
        $this->lockBudgetFunctions();

        $html = $this->signIn($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('nav-item is-denied', $html);
        $this->assertStringContainsString('data-i18n-title="nav.noAccess"', $html);
        // ยังต้องเห็นชื่อโมดูลอยู่ — เจ้าของสั่งให้เทาลง ไม่ใช่ซ่อน
        $this->assertStringContainsString('data-i18n="nav.budget"', $html);
    }

    public function test_management_group_is_hidden_from_non_admin(): void
    {
        $html = $this->signIn($this->makeUser())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-i18n="nav.manage"', $html);
    }

    public function test_management_group_is_visible_to_admin(): void
    {
        $html = $this->signIn($this->makeUser(['role' => 'admin']))->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('data-i18n="nav.manage"', $html);
    }

    // ── สิทธิ์ระดับหัวข้อย่อย ────────────────────────────────────────

    public function test_a_function_with_nobody_selected_is_closed_for_everyone(): void
    {
        // 🔴 หัวใจของกติกาใหม่ — ไม่ได้ตั้ง = ไม่มีสิทธิ์ ไม่ใช่เปิดให้ทุกคน
        $user = $this->makeUser();

        $this->assertFalse((new AccessService)->canUseFunction($user, 'budget', 'fn.budget.invest'));
    }

    public function test_ticking_everyone_opens_the_function_for_all(): void
    {
        $user = $this->makeUser();
        $this->openToEveryone('fn.budget.invest');

        $svc = new AccessService;

        $this->assertTrue($svc->canUseFunction($user, 'budget', 'fn.budget.invest'));
        $this->assertTrue($svc->isEveryone('budget', 'fn.budget.invest'));
        // ติ๊ก "ทุกคน" ไม่ใช่รายชื่อคน — รายชื่อจริงต้องยังว่าง
        $this->assertSame([], $svc->codesForFunction('budget', 'fn.budget.invest'));
    }

    public function test_a_function_limited_to_other_people_is_closed_for_this_user(): void
    {
        $user = $this->makeUser();
        FunctionUser::create([
            'module_id' => 'budget',
            'function_key' => 'fn.budget.invest',
            'employee_code' => 'SOMEONE-ELSE',
        ]);

        $svc = new AccessService;

        $this->assertFalse($svc->canUseFunction($user, 'budget', 'fn.budget.invest'));
        // 🔴 หัวข้อย่อยอื่นที่ยังไม่กำหนด ก็เข้าไม่ได้เหมือนกัน (กติกาใหม่)
        $this->assertFalse($svc->canUseFunction($user, 'budget', 'fn.budget.approved'));
    }

    public function test_a_function_left_open_stays_open_even_when_its_neighbours_are_locked(): void
    {
        /*
          🔴 แต่ละหัวข้อย่อยตัดสินด้วยตัวเอง ไม่มีชั้นโมดูลมาคร่อมแล้ว
             ล็อกข้ออื่นไปก็ไม่กระทบข้อที่ยังไม่ได้ระบุใคร
        */
        $user = $this->makeUser();
        $this->lockBudgetFunctions(['fn.budget.invest']);
        $this->openToEveryone('fn.budget.invest');

        $access = new AccessService;

        $this->assertTrue($access->canUseFunction($user, 'budget', 'fn.budget.invest'));
        $this->assertFalse($access->canUseFunction($user, 'budget', 'fn.budget.approve'));
    }

    public function test_admin_can_use_every_function(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        FunctionUser::create([
            'module_id' => 'budget',
            'function_key' => 'fn.budget.invest',
            'employee_code' => 'SOMEONE-ELSE',
        ]);

        $this->assertTrue((new AccessService)->canUseFunction($admin, 'budget', 'fn.budget.invest'));
    }

    public function test_saving_function_rights_replaces_the_whole_set(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $a = $this->makeUser();
        FunctionUser::create(['module_id' => 'budget', 'function_key' => 'fn.budget.invest', 'employee_code' => 'OLD']);

        $this->signIn($admin)->post('/access/modules/function', [
            'module_id' => 'budget',
            'function_key' => 'fn.budget.invest',
            'employee_codes' => [$a->employee_code],
        ])->assertRedirect();

        $codes = FunctionUser::where('function_key', 'fn.budget.invest')->pluck('employee_code')->all();
        $this->assertSame([$a->employee_code], $codes);
    }

    /*
      🔴 ถอดเทสต์ "สิทธิ์ย่อย" ออกเมื่อ 2026-09-09
         เทสต์ 2 ตัวเดิมใช้ fn.budget.approved.status เป็นตัวอย่าง ซึ่งเจ้าของสั่งให้ถอดออกจากระบบแล้ว
         ตอนนี้ยังไม่มีโมดูลไหนใช้กลไก extras อีก จึงไม่มีคีย์จริงให้ทดสอบ
         ถ้าโมดูลใหม่กลับมาใช้ extras เมื่อไหร่ ให้เขียนเทสต์คู่นี้กลับมาด้วย
    */

    public function test_saving_an_unknown_function_is_rejected(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        $this->signIn($admin)->post('/access/modules/function', [
            'module_id' => 'budget',
            'function_key' => 'fn.not.real',
            'employee_codes' => ['X1'],
        ])->assertRedirect();

        // 🔴 ตรวจเฉพาะคีย์ปลอม ไม่นับทั้งตาราง — ตารางมีสิทธิ์แดชบอร์ดที่ migration ใส่ไว้แล้ว
        $this->assertDatabaseMissing('access_function_users', ['function_key' => 'fn.not.real']);
    }

    // ── บันทึกค่า ────────────────────────────────────────────────────

    public function test_there_is_no_module_level_permission_endpoint_any_more(): void
    {
        // เจ้าของสั่งเอาปุ่มแก้ไขสิทธิ์ระดับโมดูลออก 2026-09-03 — เหลือทางเดียวคือรายหัวข้อย่อย
        $this->assertFalse(Route::has('access.modules.save'));
    }

    public function test_search_only_returns_safe_fields(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $target = $this->makeUser();

        $json = $this->signIn($admin)
            ->getJson('/access/system/search-users?q='.$target->employee_code)
            ->assertOk()
            ->json('results');

        $this->assertNotEmpty($json);
        // 🔴 ห้ามหลุดรหัสผ่านหรือเลขบัตรออกไปทาง JSON
        $this->assertArrayNotHasKey('password', $json[0]);
        $this->assertArrayNotHasKey('id_thai_hash', $json[0]);
    }
}
