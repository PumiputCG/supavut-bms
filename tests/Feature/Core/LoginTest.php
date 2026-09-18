<?php

namespace Tests\Feature\Core;

use App\Http\Middleware\Authenticate;
use App\Models\Core\AppUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshModuleDatabase;
use Tests\TestCase;

/**
 * ล็อกอินด้วยบัญชีที่มิเรอร์มาจาก Insight
 *
 * ที่ต้องมีเทสต์คุม: รหัสผ่านเทียบแบบ plaintext (สืบทอด decision D-007 จาก Insight)
 * ถ้าวันหนึ่งมีคนเผลอเปลี่ยนไปใช้ Hash::check() บัญชีทั้งบริษัทจะล็อกอินไม่ได้ทันที
 */
class LoginTest extends TestCase
{
    /*
      🔴 ต้องรัน migration ของทุก module (เจ้าของสั่งให้แดชบอร์ดกำหนดสิทธิ์ได้ 2026-09-10)
         แดชบอร์ดอยู่หลังด่าน bms.fn แล้ว ถ้าไม่มีตารางสิทธิ์ = ไม่มีใครเข้าได้
    */
    use RefreshModuleDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('app_users');
        Schema::create('app_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('insight_id')->nullable();
            $table->string('id_thai_hash')->nullable();
            $table->string('company')->nullable();
            $table->string('employee_code', 30)->nullable();
            $table->json('companies')->nullable();
            $table->string('password')->nullable();
            $table->string('role', 50)->default('user');
            $table->string('full_name_th')->nullable();
            $table->string('full_name_en')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->string('email')->nullable();
            $table->string('profile_picture')->nullable();
            $table->longText('signature')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('mirrored_at')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('employees');
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('insight_id')->nullable();
            $table->string('company', 30)->nullable();
            $table->string('employee_code', 30)->nullable();
            $table->string('emp_status', 10)->nullable();
            $table->date('resign_date')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('sync_logs');
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);
            $table->boolean('ok')->default(true);
            $table->text('message')->nullable();
            $table->json('detail')->nullable();
            $table->timestamp('ran_at')->nullable();
            $table->timestamps();
        });
    }

    private function makeUser(string $role = 'user'): AppUser
    {
        return AppUser::create([
            'insight_id' => 1,
            'employee_code' => '71100',
            'company' => 'SUPAVUT_INDUSTRY',
            'password' => '1234567890123',   // plaintext = เลขบัตรประชาชน ตามที่ Insight เก็บ
            'role' => $role,
            'full_name_th' => 'ทดสอบ ระบบ',
            'full_name_en' => 'Test User',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertOk()->assertSee('SBMS', false);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->makeUser();

        $this->post('/login', ['employee_code' => '71100', 'password' => 'ผิด'])
            ->assertSessionHasErrors('password');

        $this->assertNull(session(Authenticate::SESSION_KEY));
    }

    public function test_unknown_employee_code_is_rejected(): void
    {
        $this->post('/login', ['employee_code' => 'ไม่มีรหัสนี้', 'password' => 'x'])
            ->assertSessionHasErrors('employee_code');
    }

    public function test_correct_credentials_sign_the_user_in(): void
    {
        $user = $this->makeUser();

        // หน้าเริ่มต้นหลังล็อกอิน = ข้อมูลส่วนตัว (เจ้าของสั่ง 2026-09-15)
        // 🔴 ห้ามเป็น dashboard เพราะมีด่านสิทธิ์ คนที่ไม่ได้สิทธิ์จะเจอ 403 ทันทีที่ล็อกอิน
        $this->post('/login', ['employee_code' => '71100', 'password' => '1234567890123'])
            ->assertRedirect(route('profile'));

        $this->assertSame($user->id, session(Authenticate::SESSION_KEY));
    }

    /**
     * 🔴 ล็อกอินแล้วเข้าหน้าข้อมูลส่วนตัว "เสมอ" (เจ้าของย้ำ 2026-09-17)
     *    แม้ก่อนล็อกอินจะเปิดหน้าอื่นค้างไว้ — ของเดิมพากลับไปหน้านั้นด้วย intended()
     */
    public function test_sign_in_always_lands_on_the_profile(): void
    {
        $this->makeUser();

        foreach (['/', '/budget/invest', '/dashboard'] as $page) {
            $this->flushSession();
            $this->get($page)->assertRedirect(route('login'));

            $this->post('/login', ['employee_code' => '71100', 'password' => '1234567890123'])
                ->assertRedirect(route('profile'));

            $this->assertNull(session('url.intended'), 'ต้องล้างหน้าที่จำไว้ทิ้ง: '.$page);
        }
    }

    public function test_dashboard_loads_after_sign_in(): void
    {
        $user = $this->makeUser();

        $this->withSession([Authenticate::SESSION_KEY => $user->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Business Management System', false);
    }

    /** หน้าของผู้ดูแลระบบต้องกันคนทั่วไป — บัญชี Supplier สร้างได้เฉพาะ admin */
    public function test_admin_page_is_blocked_for_normal_user(): void
    {
        $user = $this->makeUser('user');

        $this->withSession([Authenticate::SESSION_KEY => $user->id])
            ->get('/admin/external-accounts')
            ->assertForbidden();
    }

    public function test_admin_page_opens_for_admin(): void
    {
        $admin = $this->makeUser('admin');

        $this->withSession([Authenticate::SESSION_KEY => $admin->id])
            ->get('/admin/external-accounts')
            ->assertOk();
    }

    public function test_sign_out_clears_the_session(): void
    {
        $user = $this->makeUser();

        $this->withSession([Authenticate::SESSION_KEY => $user->id])
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertNull(session(Authenticate::SESSION_KEY));
    }
}
