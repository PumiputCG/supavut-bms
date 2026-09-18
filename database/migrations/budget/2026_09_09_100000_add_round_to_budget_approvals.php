<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
  รอบการอนุมัติ — เก็บประวัติสายเซ็นที่ถูกตีกลับไว้ ไม่ลบทิ้ง

  เดิม submit() ลบสายอนุมัติเดิมทั้งชุดทุกครั้งที่ส่งใหม่ ประวัติรอบที่ถูกตีกลับจึงหายหมด
  เจ้าของสั่ง 2026-09-09 ว่าไม่อนุมัติแล้วต้องกลับเป็นร่างแก้แล้วส่งใหม่ได้
  และต้องย้อนดูได้ว่ารอบก่อนใครตีกลับเพราะอะไร (DECISIONS ข้อ 35)
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_approvals', function (Blueprint $table) {
            $table->unsignedSmallInteger('round')->default(1)->after('doc_id');

            // ใช้หารอบล่าสุดของเอกสารหนึ่งใบ — คิวรีที่ยิงบ่อยที่สุดของตารางนี้
            $table->index(['doc_type', 'doc_id', 'round'], 'budget_approvals_doc_round_idx');
        });
    }

    public function down(): void
    {
        Schema::table('budget_approvals', function (Blueprint $table) {
            $table->dropIndex('budget_approvals_doc_round_idx');
            $table->dropColumn('round');
        });
    }
};
