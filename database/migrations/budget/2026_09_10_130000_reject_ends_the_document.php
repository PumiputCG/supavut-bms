<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
  🔴 เจ้าของสั่งกลับกติกา 2026-09-10: ไม่อนุมัติ = เอกสารจบที่สถานะ "ไม่อนุมัติ"
     (เดิมกลับเป็น "ร่าง" ให้แก้แล้วส่งใหม่ — ดู DECISIONS ข้อ 36.15)

  แก้โค้ดอย่างเดียวไม่พอ เอกสารที่ "ถูกตีกลับไปแล้วก่อนหน้านี้" ยังค้างเป็นร่างอยู่ในฐาน
  หน้าตารางกับหัวเอกสารจึงยังขึ้นว่า "ร่าง" อยู่ (เจ้าของแจ้ง)

  ตัวเลือกที่ชัดเจน: ใบไหนเป็นร่าง แต่ในสายอนุมัติมีคน "ไม่อนุมัติ" อยู่ = ใบนั้นถูกตีกลับมาแล้ว
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_invests') || ! Schema::hasTable('budget_approvals')) {
            return;
        }

        $ids = DB::table('budget_invests as i')
            ->join('budget_approvals as a', function ($join) {
                $join->on('a.doc_id', '=', 'i.id')
                    ->where('a.doc_type', '=', 'invest')
                    ->where('a.status', '=', 'REJECTED');
            })
            ->where('i.approval_status', 'DRAFT')
            ->distinct()
            ->pluck('i.id')
            ->all();

        if ($ids === []) {
            return;
        }

        // 🔴 สำรองสถานะเดิมก่อนแก้เสมอ ตามกฎโปรเจค
        $dir = storage_path('app/backup');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $dir.'/invests-draft-to-rejected-'.date('Ymd-His').'.json',
            json_encode(
                DB::table('budget_invests')->whereIn('id', $ids)
                    ->select('id', 'doc_no', 'approval_status', 'decided_at')->get(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ),
        );

        DB::table('budget_invests')->whereIn('id', $ids)->update(['approval_status' => 'REJECTED']);

        /*
          เอกสารเก่าบางใบอาจไม่มี decided_at (ตอนนั้นยังไม่ได้เก็บ)
          เติมจากเวลาที่ผู้ลงนามกดไม่อนุมัติ จะได้ไม่มีช่องว่างในประวัติ
        */
        foreach ($ids as $id) {
            $actedAt = DB::table('budget_approvals')
                ->where('doc_type', 'invest')->where('doc_id', $id)
                ->where('status', 'REJECTED')
                ->max('acted_at');

            if ($actedAt !== null) {
                DB::table('budget_invests')->where('id', $id)->whereNull('decided_at')
                    ->update(['decided_at' => $actedAt]);
            }
        }

        info(sprintf('budget_invests: ปรับใบที่ถูกตีกลับจาก DRAFT เป็น REJECTED %d ใบ', count($ids)));
    }

    public function down(): void
    {
        // ย้อนกลับไม่ได้แบบตรงตัว — สถานะเดิมอยู่ในไฟล์สำรองที่ storage/app/backup
    }
};
