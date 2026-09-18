<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "วันที่เสนอ" + "ผู้เสนอ" กรอกเองได้ (เจ้าของสั่ง 2026-09-04)
 *
 * 🔴 แยกจาก created_by ตั้งใจ — ไม่ใช่ข้อมูลชุดเดียวกัน
 *      created_by   คนที่นั่งกรอกในระบบ (ร่องรอยการใช้งาน ห้ามแก้)
 *      proposer_*   คนที่เป็นเจ้าของเรื่องจริง — ฝ่ายบัญชีกรอกแทนหัวหน้าแผนกได้
 *    ถ้าเอา created_by มาใช้เป็นผู้เสนอเลย จะแก้ประวัติว่าใครกรอกทิ้งไป ตรวจย้อนไม่ได้
 *
 * เอกสารเก่าเติมค่าให้เท่ากับผู้สร้าง เพื่อให้หน้าจอเดิมไม่มีช่องว่าง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->date('proposed_at')->nullable()->after('fiscal_year');
            $table->string('proposer_code', 30)->nullable()->after('proposed_at');
            $table->string('proposer_name', 191)->nullable()->after('proposer_code');
        });

        // เอกสารเดิมยังไม่มีค่า — ถือว่าคนกรอกคือผู้เสนอ และวันที่สร้างคือวันที่เสนอ
        DB::table('budget_invests')->whereNull('proposer_code')->update([
            'proposer_code' => DB::raw('created_by'),
            'proposer_name' => DB::raw('created_by_name'),
            'proposed_at' => DB::raw('DATE(created_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->dropColumn(['proposed_at', 'proposer_code', 'proposer_name']);
        });
    }
};
