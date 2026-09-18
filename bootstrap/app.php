<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureFunctionAccess;
use App\Http\Middleware\RecordPageView;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Env;

// ⚠️ ห้ามลบ — Apache บนเซิร์ฟเวอร์เป็น process เดียวหลาย thread และรันหลายแอปพร้อมกัน
// (Insight + QuoteCompare + SBMS) putenv() ทำให้ค่าใน .env ของแอปนี้รั่วไปให้อีกแอปอ่านเจอ
// จนอีกแอปต่อฐานข้อมูลผิดตัว ปิดไว้เพื่อให้แต่ละแอปอ่าน .env ของตัวเองเท่านั้น
Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ใช้ session auth ของเราเอง ไม่ใช่ Auth guard ของ Laravel
        // เพราะบัญชีเป็นมิเรอร์จาก Insight และรหัสผ่านเป็น plaintext (decision D-007 ของ Insight)
        $middleware->alias([
            'bms.auth' => Authenticate::class,
            'bms.admin' => EnsureAdmin::class,
            'guest.bms' => RedirectIfAuthenticated::class,
            // บันทึกว่าใครเปิดหน้าไหน — ต้องต่อจาก bms.auth เสมอ เพราะต้องรู้ก่อนว่าใครล็อกอินอยู่
            'bms.log' => RecordPageView::class,

            // กันสิทธิ์หัวข้อย่อยจริง — ต้องต่อจาก bms.auth เพราะต้องรู้ว่าใครเข้ามา
            'bms.fn' => EnsureFunctionAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
