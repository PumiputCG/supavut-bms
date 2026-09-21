<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "ใบนี้ต้องส่งอีเมลด้วยไหม" — เก็บติดไปกับแจ้งเตือน
 *
 * 🔴 ทำไมต้องเก็บลงฐาน ทั้งที่ Notifier รู้อยู่แล้วตอนส่ง
 *    เพราะผู้รับอาจ **ยังไม่มีอีเมลในตอนนั้น** แล้วมาเพิ่มทีหลังที่ Insight (เจ้าของสั่ง 2026-09-21)
 *    ถ้าไม่จำไว้ว่าใบไหนอยากได้เมล ก็ไม่มีทางรู้ทีหลังว่าควรย้อนกลับมาส่งใบไหน
 *    (จงใจไม่เดาจากชื่อ event เพราะขอบเขตการส่งเมลเป็นเรื่องที่เจ้าของกำหนด ไม่ใช่กฎที่ฝังในชื่อ)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->boolean('wants_mail')->default(false)->after('emailed_at');
            // ตัวกวาดวิ่งทุกนาที — ให้คิวรีเกาะ index แทนการไล่อ่านทั้งตาราง
            $table->index(['wants_mail', 'emailed_at'], 'notifications_mail_pending_idx');
        });

        /*
          เติมย้อนหลังให้ใบที่ "ยังค้างอยู่จริง" ของโมดูลงบ
          🔴 ตรงนี้อ้างชื่อ event ได้ เพราะเป็นการซ่อมข้อมูลเก่าครั้งเดียว ไม่ใช่กฎที่ใช้ต่อไป
             ใบที่อ่านแล้วหรือส่งเมลไปแล้วไม่ต้องยุ่ง จะได้ไม่มีเมลย้อนหลังโผล่ไปกวนใคร
        */
        DB::table('notifications')
            ->whereNull('emailed_at')
            ->whereNull('read_at')
            ->whereIn('event', ['invest_cc', 'invest_to_sign'])
            ->update(['wants_mail' => true]);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_mail_pending_idx');
            $table->dropColumn('wants_mail');
        });
    }
};
