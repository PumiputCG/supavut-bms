<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * แจ้งเตือนบอกผลด้วยสี + มีบรรทัดเหตุผลของตัวเอง (เจ้าของสั่ง 2026-09-04)
 *
 *   เลขที่     INV-2569-000001
 *   แผนก      RVT002 · IT
 *   แจ้งเตือน   ไม่อนุมัติ            <- body_th/en · สีมาจาก tone
 *   เหตุผล     ของบเยอะไป          <- note_key (ป้าย) + note_th/en (ค่า) · สีตาม tone
 *   วันเวลา    04/09/2026 04:48
 *
 * 🔴 tone มี 3 ค่า: ok (เขียว) · no (แดง) · null (สีปกติ)
 *    ป้ายเป็นสีดำเสมอ สีบอกผลอยู่ที่ "ค่า" ไม่ใช่ที่ป้าย
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('tone', 10)->nullable()->after('body_en');
            $table->string('note_key', 60)->nullable()->after('tone');
            $table->string('note_th', 255)->nullable()->after('note_key');
            $table->string('note_en', 255)->nullable()->after('note_th');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['tone', 'note_key', 'note_th', 'note_en']);
        });
    }
};
