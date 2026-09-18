<?php

use App\Http\Controllers\Access\ModuleAccessController;
use App\Http\Controllers\Access\SystemAccessController;
use Illuminate\Support\Facades\Route;

/*
| Module: สิทธิ์การเข้าถึง (Access)
|
| ทุกหน้าในไฟล์นี้เป็นของผู้ดูแลระบบเท่านั้น
| โครงไฟล์ของโมดูลนี้ (ดูกฎใน CLAUDE.md หัวข้อ "โครงสร้างโค้ด")
|   app/Http/Controllers/Access/   app/Models/Access/   app/Services/Access/
|   resources/views/access/        database/migrations/access/
*/

Route::middleware(['bms.auth', 'bms.log', 'bms.admin'])->prefix('access')->name('access.')->group(function () {
    // ── สิทธิ์การเข้าถึงระบบ SBMS ──────────────────────────────
    Route::get('/system', [SystemAccessController::class, 'index'])->name('system');
    Route::get('/system/search-users', [SystemAccessController::class, 'searchUsers'])->name('system.search');
    Route::post('/system/admins', [SystemAccessController::class, 'addAdmin'])->name('system.admin.add');
    Route::delete('/system/admins/{admin}', [SystemAccessController::class, 'removeAdmin'])->name('system.admin.remove');
    Route::post('/system/positions', [SystemAccessController::class, 'savePositions'])->name('system.positions');

    // ── สิทธิ์การเข้าถึงโมดูล ─────────────────────────────────
    Route::get('/modules', [ModuleAccessController::class, 'index'])->name('modules');
    // 🔴 มีทางเดียวในการให้สิทธิ์ — รายหัวข้อย่อย (เจ้าของสั่งยุบสิทธิ์ระดับโมดูลทิ้ง 2026-09-03)
    Route::post('/modules/function', [ModuleAccessController::class, 'saveFunction'])->name('modules.function');
});
