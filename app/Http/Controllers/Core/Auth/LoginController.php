<?php

namespace App\Http\Controllers\Core\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Authenticate;
use App\Models\Core\AppUser;
use App\Services\Access\AccessService;
use App\Services\Audit\ActivityLogger;
use App\Services\Core\InsightMirror;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewContract;
use Throwable;

/**
 * ล็อกอินด้วย รหัสพนักงาน + รหัสผ่าน
 *
 * บัญชีทั้งหมดมาจากมิเรอร์ของ Insight (ดู App\Services\Core\InsightMirror):
 * - เทียบรหัสผ่านแบบ plaintext ด้วย hash_equals() ตาม decision D-007 ของ Insight
 *   (SBMS ไม่ได้ตัดสินใจเรื่องนี้เอง แค่ต้องเข้ากันได้กับบัญชีชุดเดียวกัน)
 * - รหัสผ่านเริ่มต้นของพนักงาน = เลขบัตรประชาชน · เปลี่ยนรหัสได้ที่ Insight ที่เดียว
 * - system admin ใช้ employee_code = Admin
 * - คนที่ทำงานข้ามบริษัท พิมพ์รหัสของบริษัทไหนก็เข้าได้ (resolve จาก map `companies`)
 * - พนักงานลาออกจะไม่มีบัญชีใน Insight -> มิเรอร์ก็ไม่มี -> ล็อกอินไม่ได้เอง
 *
 * ถ้าหารหัสในมิเรอร์ไม่เจอ จะลองถาม Insight สดอีกครั้งแล้วดึงบัญชีนั้นเข้ามาทันที
 * พนักงานที่เพิ่งเข้าใหม่จึงล็อกอินได้เลย ไม่ต้องรอรอบซิงค์
 */
class LoginController extends Controller
{
    public function show(Request $request): ViewContract|RedirectResponse
    {
        if ($request->session()->get(Authenticate::SESSION_KEY)) {
            return redirect()->route('profile');
        }

        return View::make('core.auth.login');
    }

    public function login(Request $request, InsightMirror $mirror, AccessService $access, ActivityLogger $log): RedirectResponse
    {
        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $code = trim($data['employee_code']);
        $user = $this->resolve($code) ?? $this->resolveFromInsight($mirror, $code);

        // แยกข้อความให้ชัด: ไม่พบรหัสพนักงาน vs รหัสผ่านผิด — ผู้ใช้จะได้รู้ว่าต้องแก้ช่องไหน
        if (! $user) {
            $log->loginFailed($code, 'ไม่พบรหัสพนักงานนี้', 'employee ID not found');

            return $this->fail($request, 'employee_code', [
                'th' => 'ไม่พบรหัสพนักงานนี้ในระบบ',
                'en' => 'Employee ID not found',
            ]);
        }

        if (! hash_equals((string) $user->password, (string) $data['password'])) {
            $log->loginFailed($code, 'รหัสผ่านไม่ถูกต้อง', 'incorrect password');

            return $this->fail($request, 'password', [
                'th' => 'รหัสผ่านไม่ถูกต้อง',
                'en' => 'Incorrect password',
            ]);
        }

        // ตำแหน่งที่ถูกปิดไว้ที่หน้า "สิทธิ์การเข้าถึงระบบ SBMS" ห้ามเข้า — เช็คหลังรหัสผ่านถูกแล้ว
        // เพื่อไม่ให้คนเดารหัสรู้ว่าตำแหน่งไหนเปิด/ปิดจากข้อความที่ต่างกัน
        if (! $access->canLogin($user)) {
            $log->loginFailed($code, 'ตำแหน่งนี้ไม่ได้รับสิทธิ์เข้าระบบ', 'position not allowed');

            return $this->fail($request, 'employee_code', [
                'th' => 'ตำแหน่งของคุณยังไม่ได้รับสิทธิ์เข้าใช้งานระบบ SBMS กรุณาติดต่อผู้ดูแลระบบ',
                'en' => 'Your position is not allowed to access SBMS. Please contact the administrator.',
            ]);
        }

        $request->session()->regenerate();   // กัน session fixation
        $request->session()->put(Authenticate::SESSION_KEY, $user->id);

        $log->record('login', [
            'th' => 'เข้าสู่ระบบสำเร็จ',
            'en' => 'Signed in successfully',
        ], $user->employee_code, $user);

        if ($request->boolean('remember')) {
            // จำรหัสพนักงานไว้เติมให้ในช่องครั้งหน้า — ไม่ได้จำรหัสผ่าน
            cookie()->queue(cookie()->forever('bms_last_code', $code));
        }

        /*
          🔴 ล็อกอินแล้วเข้าหน้าข้อมูลส่วนตัว "เสมอ" (เจ้าของสั่ง 2026-09-15 · ย้ำอีกครั้ง 2026-09-17)
             ห้ามใช้ dashboard เพราะมีด่านสิทธิ์ bms.fn — คนที่ไม่ได้สิทธิ์จะเจอ 403 ทันทีที่ล็อกอิน

             เลิกใช้ intended() แล้ว — ของเดิมพากลับไปหน้าที่เปิดค้างไว้ก่อนล็อกอิน
             (เช่นเปิด http://…/ ตอนยังไม่ล็อกอิน จะไปจบที่หน้าคู่มือ ไม่ใช่ข้อมูลส่วนตัว)
             ล้างค่าที่จำไว้ทิ้งด้วย ไม่งั้นค้างอยู่ใน session ไปโผล่ตอนอื่น
        */
        $request->session()->forget('url.intended');

        return redirect()->route('profile');
    }

    public function logout(Request $request, ActivityLogger $log): RedirectResponse
    {
        /*
          🔴 route นี้อยู่นอกกลุ่ม bms.auth (ต้องกดออกได้แม้ session หมดอายุ)
             ตัวแปร current_user จึงยังไม่ถูกผูก — ต้องหาคนจาก session เองแล้วส่งให้ตัวบันทึก
             ถ้าไม่ทำ ประวัติจะขึ้นว่า "ออกจากระบบ" โดยไม่รู้ว่าใครออก
        */
        $id = $request->session()->get(Authenticate::SESSION_KEY);
        $user = $id ? AppUser::find($id) : null;

        if ($user) {
            // บันทึกก่อนล้าง session เสมอ
            $log->record('logout', [
                'th' => 'ออกจากระบบ',
                'en' => 'Signed out',
            ], $user->employee_code, $user);
        }

        $request->session()->forget(Authenticate::SESSION_KEY);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('flash_success', [
            'th' => 'ออกจากระบบแล้ว',
            'en' => 'Signed out',
        ]);
    }

    /**
     * หาบัญชีในมิเรอร์: รหัสหลักก่อน แล้วค่อยหาในรหัสของบริษัทอื่น
     *
     * แยกเป็น 2 คิวรีโดยตั้งใจ — ทางแรกใช้ index ตรงๆ เร็วกว่า และคนส่วนใหญ่จบที่ทางแรก
     * ส่วน JSON_SEARCH เป็นฟังก์ชันเฉพาะของ MySQL จึงยิงเฉพาะตอนจำเป็น
     */
    private function resolve(string $code): ?AppUser
    {
        $user = AppUser::where('employee_code', $code)->first();

        if ($user) {
            return $user;
        }

        try {
            return AppUser::whereRaw("JSON_SEARCH(companies, 'one', ?) IS NOT NULL", [$code])->first();
        } catch (Throwable) {
            return null;   // ฐานที่ไม่มี JSON_SEARCH (เช่น SQLite ตอนรันเทสต์)
        }
    }

    /** ยังไม่มีในมิเรอร์ -> ถาม Insight สด (Insight ล่มก็แค่คืน null ไม่ทำให้หน้าพัง) */
    private function resolveFromInsight(InsightMirror $mirror, string $code): ?AppUser
    {
        try {
            return $mirror->pullUserByCode($code);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param  array{th:string,en:string}  $message */
    private function fail(Request $request, string $field, array $message): RedirectResponse
    {
        return back()
            ->withInput($request->only('employee_code'))
            ->withErrors([$field => $message['th']])
            ->with('field_error', ['field' => $field] + $message);
    }
}
