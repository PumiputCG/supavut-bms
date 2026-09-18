<?php

namespace App\Http\Middleware;

use App\Models\Core\AppUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * ยืนยันตัวตนด้วย session เอง (custom auth — สืบทอดแนวเดียวกับ Insight / QuoteCompare)
 *
 * ไม่ใช้ Auth guard ของ Laravel เพราะบัญชีเป็นมิเรอร์จาก Insight
 * และรหัสผ่านเก็บเป็น plaintext ตาม decision D-007 ของ Insight ไม่ใช่ bcrypt
 *
 * - ไม่มี session หรือบัญชีถูกลบ (พนักงานลาออก) -> เด้งไปหน้าล็อกอิน
 * - แชร์ตัวแปร $me ให้ทุก view + ผูกใน container ('current_user')
 */
class Authenticate
{
    public const SESSION_KEY = 'bms_user_id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get(self::SESSION_KEY);
        $me = $id ? AppUser::find($id) : null;

        if (! $me) {
            $request->session()->forget(self::SESSION_KEY);

            // ข้อความ flash เก็บเป็นคู่ th/en เสมอ ให้ view เลือกแสดงตามภาษาที่ผู้ใช้เลือกไว้
            $message = $id
                ? ['th' => 'บัญชีนี้ถูกปิดการใช้งานแล้ว', 'en' => 'This account is no longer active']
                : ['th' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน', 'en' => 'Please sign in to continue'];

            return redirect()->guest(route('login'))->with('flash_error', $message);
        }

        app()->instance('current_user', $me);
        View::share('me', $me);

        return $next($request);
    }
}
