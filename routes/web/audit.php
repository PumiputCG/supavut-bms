<?php

use App\Http\Controllers\Audit\ActivityLogController;
use Illuminate\Support\Facades\Route;

/*
| Module: ประวัติการใช้งานระบบ (Audit)
|
| อ่านอย่างเดียว — ไม่มี route สำหรับแก้ไขหรือลบโดยตั้งใจ
| ประวัติที่แก้ได้ใช้ตรวจสอบไม่ได้
*/

Route::middleware(['bms.auth', 'bms.log', 'bms.admin'])->prefix('activity')->name('activity.')->group(function () {
    Route::get('/', [ActivityLogController::class, 'index'])->name('index');
    Route::get('/sign-ins', [ActivityLogController::class, 'signIns'])->name('signins');
});
