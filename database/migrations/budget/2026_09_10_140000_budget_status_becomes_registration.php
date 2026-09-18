<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
  🔴 สถานะงบเปลี่ยนความหมายทั้งชุด (เจ้าของสั่ง 2026-09-10 · DECISIONS ข้อ 37.2)

    ของเดิม  ACTIVE / HOLD / STOPPED / CLOSED  = "เงินก้อนนี้ยังใช้ได้ไหม"  → เป็นเรื่องของ ERP
    ของใหม่  PENDING_REGISTER / REGISTERED     = "บัญชีคีย์เข้า ERP หรือยัง" → เป็นเรื่องของ SBMS

  แถวเดิมทุกแถวเกิดขึ้นตอนที่ยังไม่มีแนวคิด "ลงทะเบียน" แปลว่ายังไม่มีใครคีย์เข้า ERP แน่นอน
  จึงย้ายไปเป็น "รอลงทะเบียน" ทั้งหมด ไม่ว่าสถานะเดิมจะเป็นอะไร

  🔴 บทเรียน 2026-09-10: เปลี่ยนกติกาสถานะแล้วต้องตามไปแก้ข้อมูลเก่าด้วยเสมอ
     ไม่งั้นหน้าจอจะขึ้นรหัสดิบที่ไม่มีคำแปล (ครั้งก่อนใบที่ถูกตีกลับค้างเป็น "ร่าง")
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budgets')) {
            return;
        }

        $old = ['ACTIVE', 'HOLD', 'STOPPED', 'CLOSED'];

        $rows = DB::table('budgets')->whereIn('budget_status', $old)
            ->select('id', 'doc_no', 'budget_status')->get();

        if ($rows->isEmpty()) {
            return;
        }

        // 🔴 สำรองสถานะเดิมก่อนแก้เสมอ ตามกฎโปรเจค
        $dir = storage_path('app/backup');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $dir.'/budgets-status-to-registration-'.date('Ymd-His').'.json',
            json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        DB::table('budgets')->whereIn('budget_status', $old)
            ->update(['budget_status' => 'PENDING_REGISTER']);

        info(sprintf('budgets: ย้ายสถานะเดิมไปเป็น "รอลงทะเบียน" %d ก้อน', $rows->count()));
    }

    public function down(): void
    {
        // ย้อนกลับไม่ได้แบบตรงตัว — สถานะเดิมอยู่ในไฟล์สำรองที่ storage/app/backup
    }
};
