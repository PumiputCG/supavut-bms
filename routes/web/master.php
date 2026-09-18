<?php

use App\Http\Controllers\Master\MasterDataController;
use Illuminate\Support\Facades\Route;

/*
| Module: ข้อมูลหลัก (Master Data)
|
| อ่านอย่างเดียวทั้งหมดในตอนนี้ — ชุดข้อมูลที่มีจริงมาจากมิเรอร์ของ Insight
| ชุดที่ SBMS ต้องเป็นเจ้าของเอง (ศูนย์ต้นทุน · หมวดค่าใช้จ่าย · ผู้ขาย · สินค้า · หน่วยนับ)
| จะทำตอน Phase 1 ของโมดูลงบประมาณ
*/

Route::middleware(['bms.auth', 'bms.log', 'bms.admin'])->prefix('master')->name('master.')->group(function () {
    Route::get('/', [MasterDataController::class, 'index'])->name('index');
    // รายชื่อพนักงาน — โหลดทีละหน้าเข้าหน้าต่างซ้อน (JSON)
    Route::get('/employees', [MasterDataController::class, 'employees'])->name('employees');
    Route::get('/departments', [MasterDataController::class, 'departments'])->name('departments');
    Route::get('/positions', [MasterDataController::class, 'positions'])->name('positions');
    Route::get('/branches', [MasterDataController::class, 'branches'])->name('branches');
});
