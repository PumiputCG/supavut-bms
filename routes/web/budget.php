<?php

use App\Http\Controllers\Budget\ApprovalController;
use App\Http\Controllers\Budget\BudgetController;
use App\Http\Controllers\Budget\DocGroupController;
use App\Http\Controllers\Budget\HistoryController;
use App\Http\Controllers\Budget\InvestController;
use App\Http\Controllers\Budget\TrackController;
use Illuminate\Support\Facades\Route;

/*
| Module: งบประมาณ (Budget)
|
| 4 หน้าตามที่เจ้าของกำหนด 2026-09-03
|   1. เสนอ Invest          -> ฝ่ายบัญชี สร้างและส่งเอกสาร
|   2. รับทราบ              -> คนที่ผู้เสนอเลือก **เห็นเอกสารเฉยๆ เซ็นไม่ได้**
|   3. อนุมัติ Invest        -> คนที่ตั้งไว้ในสิทธิ์ เซ็นจริง แล้วระบบสร้าง Budget
|   4. ลงทะเบียน
|
| 🔴 สิทธิ์ "เข้าหน้าไหนได้" คุมด้วย middleware bms.fn ตัวเดียว
|    ซึ่งอ่านจากหัวข้อย่อยที่ตั้งไว้ที่ /access/modules (เจ้าของสั่งยุบมาที่เดียว 2026-09-03)
|    controller เหลือหน้าที่คุมแค่ "เห็นข้อมูลก้อนไหน" เช่น เห็นเฉพาะงบแผนกตัวเอง
*/

Route::middleware(['bms.auth', 'bms.log', 'bms.fn'])->prefix('budget')->name('budget.')->group(function () {

    // ── 1) เสนอ Invest ──────────────────────────────────────────
    Route::get('/invest', [InvestController::class, 'index'])->name('invest.index');
    Route::get('/invest/create', [InvestController::class, 'create'])->name('invest.create');
    Route::post('/invest', [InvestController::class, 'store'])->name('invest.store');
    Route::get('/invest/{invest}/edit', [InvestController::class, 'edit'])->name('invest.edit');
    Route::put('/invest/{invest}', [InvestController::class, 'update'])->name('invest.update');
    Route::post('/invest/{invest}/submit', [InvestController::class, 'submit'])->name('invest.submit');
    // ลบได้เฉพาะร่าง หรือส่งแล้วแต่ยังไม่มีใครตัดสิน — กติกาอยู่ที่ Invest::canDelete()
    Route::delete('/invest/{invest}', [InvestController::class, 'destroy'])->name('invest.destroy');
    Route::delete('/invest-file/{file}', [InvestController::class, 'removeFile'])->name('invest.file.remove');
    Route::get('/invest-file/{file}/download', [ApprovalController::class, 'download'])->name('file.download');

    /*
      ── 2) รับทราบ / อนุมัติ Invest — หน้าเดียวกัน (เจ้าของสั่งรวมเมนู 2026-09-03)
         คนที่มีสิทธิ์ "รับทราบ" เห็นเอกสารเฉยๆ · คนที่มีสิทธิ์ "อนุมัติ" มีปุ่มเซ็น
         middleware bms.fn ปล่อยผ่านถ้ามีสิทธิ์ข้อใดข้อหนึ่ง
    */
    Route::get('/approval', [ApprovalController::class, 'index'])->name('approval.index');

    /*
      หน้าเอกสาร — ใช้ร่วมกันทั้งผู้รับทราบและผู้อนุมัติ
      🔴 จงใจไม่ผูกกับหัวข้อย่อยไหน (middleware bms.fn จะปล่อยผ่าน)
         เพราะสิทธิ์ตรงนี้ตัดสินจาก "มีชื่อในเอกสารใบนี้ไหม" ไม่ใช่จากเมนู
    */
    // อนุมัติ/ปฏิเสธหลายฉบับพร้อมกันจากหน้าตาราง (เจ้าของสั่ง 2026-09-03)
    Route::post('/approval/bulk', [ApprovalController::class, 'bulk'])->name('approval.bulk');

    Route::get('/doc/{invest}', [ApprovalController::class, 'show'])->name('doc.show');
    Route::post('/doc/{invest}/approve', [ApprovalController::class, 'approve'])->name('doc.approve');
    Route::post('/doc/{invest}/reject', [ApprovalController::class, 'reject'])->name('doc.reject');

    /*
      หน้าพิมพ์ / ดาวน์โหลด PDF ของใบ INV (เจ้าของสั่ง 2026-09-18)
      🔴 มีเพราะใบ INV คือใบที่เดินเซ็นจริง แต่เดิมพิมพ์/ดาวน์โหลดไม่ได้เลย
         ไม่มีกระดาษ = QR บนหัวเอกสารไม่มีใครได้ใช้
      🔴 สิทธิ์ตัดสินจาก "มีชื่อในเอกสารใบนี้ไหม" เหมือนหน้าเอกสาร (BudgetAccess ที่เดียว)
    */
    Route::get('/doc/{invest}/print', [ApprovalController::class, 'print'])->name('doc.print');
    Route::get('/doc/{invest}/pdf', [ApprovalController::class, 'pdf'])->name('doc.pdf');

    // ── 4) ประวัติงบประมาณ — ทุกฉบับ ทุกสถานะ ไว้ตรวจย้อนหลัง ──
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');

    // ── 5) ลงทะเบียน ────────────────────────────────
    Route::get('/list', [BudgetController::class, 'index'])->name('list.index');
    Route::get('/list/{budget}', [BudgetController::class, 'show'])->name('list.show');
    Route::post('/list/{budget}/status', [BudgetController::class, 'changeStatus'])->name('list.status');
    // หน้าพิมพ์ / บันทึก PDF — เปิดแท็บใหม่ เห็นแต่ตัวกระดาษ (เจ้าของสั่ง 2026-09-10)
    Route::get('/list/{budget}/print', [BudgetController::class, 'print'])->name('list.print');
    // ดาวน์โหลดเป็นไฟล์ PDF จริง — กดแล้วได้ไฟล์เลย (เจ้าของสั่ง 2026-09-10)
    Route::get('/list/{budget}/pdf', [BudgetController::class, 'pdf'])->name('list.pdf');
});

/*
| หน้าติดตามเอกสาร — ปลายทางของ QR Code บนกระดาษ (เจ้าของสั่ง 2026-09-18)
|
| 🔴 อยู่นอกด่าน bms.auth โดยตั้งใจ — สแกนแล้วต้องเห็นสถานะทันทีจากมือถือ
|    ไม่ล็อกอินเห็นแค่เส้นทาง (ไม่มียอดเงิน ไม่มีลายเซ็น) · ล็อกอินแล้วเห็นเพิ่ม
| 🔴 ไม่ใส่ bms.log เพราะคนสแกนอาจไม่มีบัญชี — ตัวบันทึกประวัติการใช้งานผูกกับผู้ใช้
| 🔴 กุญแจเป็นรหัสสุ่ม 32 ตัว ไม่ใช่เลขที่เอกสาร (ดู App\Support\TrackLink)
*/
Route::get('/track/{token}', [TrackController::class, 'show'])->name('budget.track.show');

/*
| ตั้งค่าหมายเลขเอกสาร (เจ้าของสั่ง 2026-09-17)
|
| 🔒 เมนูอยู่ใต้ "จัดการระบบ" จึงกันด้วย bms.admin แบบเดียวกับหน้าข้อมูลหลัก
|    ไม่ใช้ bms.fn เพราะไม่มีหัวข้อย่อยให้ตั้งสิทธิ์รายคน — กลุ่มจัดการระบบเป็นของผู้ดูแลระบบทั้งกลุ่ม
| 🔴 ไฟล์อยู่ในโมดูล Budget เพราะเป็นเลขที่เอกสารของงบประมาณ (1 โมดูล = 1 โฟลเดอร์ทุกชั้น)
*/
Route::middleware(['bms.auth', 'bms.log', 'bms.admin'])->prefix('budget/doc-groups')->name('budget.docgroup.')->group(function () {
    Route::get('/', [DocGroupController::class, 'index'])->name('index');
    Route::post('/', [DocGroupController::class, 'store'])->name('store');
    Route::put('/{group}', [DocGroupController::class, 'update'])->name('update');
    Route::post('/{group}/toggle', [DocGroupController::class, 'toggle'])->name('toggle');
    Route::delete('/{group}', [DocGroupController::class, 'destroy'])->name('destroy');
});
