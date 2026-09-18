<?php

use App\Support\TrackLink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
  กุญแจของ QR Code ติดตามเอกสาร (เจ้าของสั่ง 2026-09-18 · DECISIONS 50.15)

  🔴 อยู่ที่ตาราง budget_invests "ตารางเดียว" — ใบงบ (budgets) ไม่มีคอลัมน์นี้
     เพราะ 1 ใบ INV ได้ใบ BGT อย่างมาก 1 ใบ (unique index ที่ budgets.invest_id)
     และสายอนุมัติทั้งเส้นเก็บด้วย doc_type = INVEST เท่านั้น → เป็น "เรื่องเดียว" เส้นเดียว
     ใบ BGT จึงพิมพ์ QR ดวงเดียวกันกับใบ INV ต้นทางของตัวเอง

  🔴 กุญแจไม่ใช่เลขที่เอกสาร เพราะเลขที่เดาได้ (ดู App\Support\TrackLink)
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->string('track_token', 32)->nullable()->unique()->after('doc_no');
        });

        /*
          เอกสารที่ส่งไปแล้วก่อนหน้านี้ต้องมีกุญแจย้อนหลังให้ด้วย
          ไม่งั้นเอกสารเก่าที่ยังเดินอยู่ในสายอนุมัติจะไม่มี QR ให้สแกนเลย
          🔴 ร่างที่ยังไม่ส่งไม่ต้องมี — กุญแจออกตอนกดส่งพร้อมเลขที่เอกสาร (กติกาเดียวกับ doc_no)
        */
        DB::table('budget_invests')
            ->whereNotNull('doc_no')
            ->orderBy('id')
            ->select('id')
            ->get()
            ->each(function ($row) {
                DB::table('budget_invests')->where('id', $row->id)->update(['track_token' => TrackLink::newToken()]);
            });
    }

    public function down(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->dropUnique(['track_token']);
            $table->dropColumn('track_token');
        });
    }
};
