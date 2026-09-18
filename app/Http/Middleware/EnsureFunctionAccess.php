<?php

namespace App\Http\Middleware;

use App\Services\Access\AccessService;
use App\Support\NavMenu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * กันสิทธิ์หัวข้อย่อยจริงฝั่งเซิร์ฟเวอร์ — ไม่ใช่แค่ทำให้เมนูเทา
 *
 * 🔴 ก่อนหน้านี้ (จนถึง 2026-09-03) สิทธิ์หัวข้อย่อยทำแค่ทำให้เมนูเทาในหน้าเว็บ
 *    ใครพิมพ์ URL ตรงๆ ก็เข้าได้หมด — เจ้าของสั่งให้กันจริง
 *
 * วิธีทำงาน: เอาชื่อ route ปัจจุบันไปถาม NavMenu ว่าอยู่ใต้หัวข้อย่อยไหน
 * แล้วถาม AccessService ว่าคนนี้ใช้หัวข้อนั้นได้ไหม
 *
 * แปะที่ route group ครั้งเดียวได้ทั้งโมดูล — ไม่ต้องไล่เขียนทีละ controller
 * route ที่ไม่ได้ผูกกับหัวข้อย่อยไหนเลย (เช่น /dashboard) ปล่อยผ่าน
 */
class EnsureFunctionAccess
{
    public function __construct(private readonly AccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $hit = NavMenu::functionKeyFor($request->route()?->getName());

        // ไม่ได้ผูกกับหัวข้อย่อยไหน = ไม่ใช่หน้าที่ของ middleware ตัวนี้
        if (! $hit) {
            return $next($request);
        }

        $me = app()->bound('current_user') ? app('current_user') : null;

        // หน้าเดียวอาจเปิดให้หลายบทบาท — มีสิทธิ์ข้อใดข้อหนึ่งก็เข้าได้
        foreach ($hit['function_keys'] as $key) {
            if ($this->access->canUseFunction($me, $hit['module_id'], $key)) {
                return $next($request);
            }
        }

        abort(403, 'คุณไม่มีสิทธิ์เข้าใช้งานหัวข้อนี้ / You do not have access to this function');
    }
}
