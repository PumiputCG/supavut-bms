<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ล็อกอินอยู่แล้วไม่ต้องเห็นหน้าล็อกอินอีก — ส่งไปหน้าข้อมูลส่วนตัว (หน้าเริ่มต้นของระบบ)
 */
class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get(Authenticate::SESSION_KEY)) {
            return redirect()->route('profile');
        }

        return $next($request);
    }
}
