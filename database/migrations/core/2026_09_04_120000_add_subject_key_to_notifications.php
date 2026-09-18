<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * แจ้งเตือนต้องมี "หัวข้อ" กำกับทุกบรรทัด (เจ้าของสั่ง 2026-09-04)
 *
 *   เลขที่:   INV-2569-000001     <- doc_no
 *   แผนก:    RVT002 · IT          <- subject_key (ป้าย) + title_th/en (ค่า)
 *   แจ้งเตือน: รอคุณลงนามอนุมัติ    <- body_th/en
 *   วันเวลา:  04/09/2026 04:32     <- created_at
 *
 * 🔴 ป้ายของบรรทัดที่ 2 เก็บเป็น "คีย์แปล" ไม่ใช่ข้อความตรงๆ
 *    เพราะโมดูลอื่นจะใช้ป้ายคนละคำ (แผนก · ผู้ขาย · ใบสั่งซื้อ ฯลฯ)
 *    และต้องสลับภาษาได้เหมือนที่อื่นในระบบ
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('subject_key', 60)->nullable()->after('doc_no');
        });

        // แจ้งเตือนเดิมทั้งหมดมาจากโมดูลงบประมาณ ซึ่งใช้ "แผนก" เป็นป้าย
        DB::table('notifications')->whereNull('subject_key')->update(['subject_key' => 'budget.dept']);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('subject_key');
        });
    }
};
