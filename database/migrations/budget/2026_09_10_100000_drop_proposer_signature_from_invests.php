<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
  ผู้ขอไม่ต้องลงนามแล้ว (เจ้าของสั่ง 2026-09-10)

  ช่องลงชื่อในเอกสารมีแต่ผู้อนุมัติ — คนกรอกไม่ได้เป็นคนอนุมัติอะไร
  จึงไม่ต้องเก็บลายเซ็นของเขา และไม่ต้องมีลายเซ็นใน Insight ถึงจะส่งเอกสารได้

  ⚠️ down() คืนได้แค่โครงสร้าง ลายเซ็นเดิมคืนไม่ได้
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->dropColumn(['proposer_signed_at', 'proposer_signature']);
        });
    }

    public function down(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->timestamp('proposer_signed_at')->nullable();
            $table->longText('proposer_signature')->nullable();
        });
    }
};
