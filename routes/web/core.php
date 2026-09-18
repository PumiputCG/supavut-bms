<?php

use App\Http\Controllers\Access\SystemAccessController;
use App\Http\Controllers\Core\Auth\LoginController;
use App\Http\Controllers\Core\DashboardController;
use App\Http\Controllers\Core\NotificationController;
use App\Http\Controllers\Core\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| Module: แกนกลาง (Core)
|
| ล็อกอิน · หน้าแรก · แดชบอร์ด · ข้อมูลส่วนตัว · หน้าเฉพาะผู้ดูแลระบบ
|
| ยืนยันตัวตนด้วย session เอง (custom auth) — ดู App\Http\Middleware\Authenticate
*/

// ── ยังไม่ต้องล็อกอิน ────────────────────────────────────────
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');

// 🔴 /logout อยู่นอกกลุ่ม bms.auth — controller จึงต้องหาว่าใครออกจาก session เอง
//    (บทเรียนจริง 2026-09-03: เคยอยู่นอกกลุ่มแล้วประวัติการออกจากระบบไม่รู้ว่าใคร)
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ── ต้องล็อกอินก่อน ─────────────────────────────────────────
Route::middleware(['bms.auth', 'bms.log'])->group(function () {
    // ปุ่ม "หน้าแรก" บนแถบเครื่องมือมาที่หน้านี้ — อธิบายว่าระบบใช้ทำอะไร เริ่มยังไง
    Route::get('/', fn () => redirect()->route('guide'));
    Route::view('/guide', 'core.guide')->name('guide');

    /*
      🔴 แดชบอร์ดกำหนดสิทธิ์ได้แล้ว (เจ้าของสั่ง 2026-09-10)
         ต้องมี bms.fn ด้วย ไม่งั้นสิทธิ์กันได้แค่ทำให้เมนูหาย พิมพ์ URL ตรงยังเข้าได้
         (บทเรียนเดิมของโปรเจค 2026-09-03)
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('bms.fn')->name('dashboard');

    /*
      "ใช้เงินไปกับอะไรบ้าง" ของบรรทัดงบหนึ่งก้อน — ยิงถามตอนกดเท่านั้น
      🔴 สมุดเดินงบมี 1.35 ล้านแถว ส่งมากับหน้าไม่ได้
      🔴 ตรวจสิทธิ์ซ้ำในตัว controller ด้วย (ด่านเดียวกับหน้าแดชบอร์ด)
    */
    Route::get('/dashboard/spend', [DashboardController::class, 'spend'])
        ->middleware('bms.fn')->name('dashboard.spend');

    // ช่วงวันที่จ่ายที่มีรายการจริงของปีนั้น — ใช้เติมช่องในหน้าต่างเปรียบเทียบ
    Route::get('/dashboard/expense-periods', [DashboardController::class, 'expensePeriods'])
        ->middleware('bms.fn')->name('dashboard.expense-periods');

    // ค่าที่เลือกได้ในหัวคอลัมน์ — ยิงเมื่อผู้ใช้กดปุ่มกรองเท่านั้น (ไม่ต้องรอตอนเปิดหน้า)
    Route::get('/dashboard/filter-options', [DashboardController::class, 'filterOptions'])
        ->middleware('bms.fn')->name('dashboard.filter-options');

    // ข้อมูลส่วนตัวของตัวเอง — อ่านอย่างเดียว (แก้ที่ Insight ที่เดียว)
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');

    /*
      ค้นหาพนักงาน — ใช้ร่วมทุกโมดูลที่มีตัวเลือกคน
      🔴 ต้องอยู่นอกกลุ่ม bms.admin ไม่งั้นคนที่ไม่ใช่แอดมินค้นหาไม่เจอเลย (บั๊กจริง 2026-09-03)
         ตัวค้นหาคืนเฉพาะฟิลด์ปลอดภัย (รหัส · ชื่อ · ตำแหน่ง · แผนก · รูป) ไม่มีข้อมูลอ่อนไหว
    */
    Route::get('/people/search', [SystemAccessController::class, 'searchUsers'])->name('people.search');

    // กระดิ่งแจ้งเตือน — ทำเครื่องหมายว่าอ่านแล้ว (ยิงจากหน้าเว็บแบบ AJAX)
    Route::post('/notifications/read', [NotificationController::class, 'read'])->name('notifications.read');

    // หน้าเฉพาะผู้ดูแลระบบ
    Route::middleware('bms.admin')->group(function () {
        // บัญชีผู้ใช้ภายนอก (Supplier) — admin เป็นคนสร้างให้ แล้วส่งรหัสไปทางอีเมล
        // เจ้าของสั่งย้ายมาจากหน้าล็อกอินเมื่อ 2026-09-02 เพื่อให้ admin คุมบัญชีเองทั้งหมด
        Route::view('/admin/external-accounts', 'core.admin.external-account')->name('admin.external');
    });
});
