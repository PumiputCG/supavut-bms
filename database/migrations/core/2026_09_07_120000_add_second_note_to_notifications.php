<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * แจ้งเตือนรองรับบรรทัดเสริม 2 บรรทัด
 *
 * 🔴 ตอนอนุมัติต้องบอกทั้ง "เลขที่งบ" และ "เหตุผล/ความเห็นของผู้อนุมัติ" พร้อมกัน
 *    ของเดิมมีช่องเดียว ความเห็นของผู้อนุมัติเลยหายไปทั้งใบ (เจ้าของแจ้ง 2026-09-07)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('note2_key', 60)->nullable()->after('note_en');
            $table->string('note2_th', 255)->nullable()->after('note2_key');
            $table->string('note2_en', 255)->nullable()->after('note2_th');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['note2_key', 'note2_th', 'note2_en']);
        });
    }
};
