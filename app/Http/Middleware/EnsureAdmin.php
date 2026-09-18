<?php

namespace App\Http\Middleware;

use App\Services\Access\AccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * อนุญาตเฉพาะผู้ดูแลระบบ (role = admin) — ใช้ต่อจาก Authenticate เสมอ
 *
 * นับ 2 ทาง (ดู App\Services\Access\AccessService)
 *   1. role = admin ที่มิเรอร์มาจาก Insight
 *   2. อยู่ในตาราง access_admins ของ SBMS เอง (ตั้งที่หน้า "สิทธิ์การเข้าถึงระบบ SBMS")
 */
class EnsureAdmin
{
    public function __construct(private readonly AccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $me = app()->bound('current_user') ? app('current_user') : null;

        if (! $this->access->isAdmin($me)) {
            abort(403, 'เฉพาะผู้ดูแลระบบเท่านั้น / Administrators only');
        }

        return $next($request);
    }
}
