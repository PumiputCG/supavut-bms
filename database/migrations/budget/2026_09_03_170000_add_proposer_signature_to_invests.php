<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ผู้เสนอกดประทับลายเซ็นลงในเอกสารได้เอง (เจ้าของสั่ง 2026-09-03)
 *
 * เก็บทั้งเวลาที่เซ็นและสำเนาลายเซ็น ณ ตอนนั้น
 * 🔴 เก็บสำเนาไว้เลย ไม่ join สดทีหลัง — คนลาออกแล้วเอกสารเก่าต้องยังพิมพ์ได้เหมือนเดิม
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->dateTime('proposer_signed_at')->nullable()->after('submitted_at');
            $table->text('proposer_signature')->nullable()->after('proposer_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->dropColumn(['proposer_signed_at', 'proposer_signature']);
        });
    }
};
