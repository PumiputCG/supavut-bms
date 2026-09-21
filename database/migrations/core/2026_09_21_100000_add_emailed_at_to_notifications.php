<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เวลาที่ "ส่งอีเมลของแจ้งเตือนใบนี้ออกไปแล้ว"
 *
 * 🔴 มีไว้เพื่อกติกา "1 แจ้งเตือน = ส่งเมลได้ครั้งเดียวตลอดกาล"
 *    ตัวกันซ้ำเดิมของ Notifier ดูแค่ใบที่ "ยังไม่อ่าน" — พออ่านแล้ว เหตุการณ์เดิมส่งใหม่ได้อีก
 *    ซึ่งรับได้สำหรับเลขแดงบนกระดิ่ง (แค่ขึ้นใหม่) แต่รับไม่ได้ถ้ามันกลายเป็นเมลเข้ากล่องซ้ำ
 *
 * null = ยังไม่ได้ส่ง — รวมกรณีผู้รับยังไม่ได้กรอกอีเมลไว้ที่ Insight ซึ่งไม่ใช่ความผิดพลาด
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('emailed_at');
        });
    }
};
