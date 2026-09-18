<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
  เก็บชื่อ-สกุลภาษาอังกฤษไว้ในเอกสารด้วย (เจ้าของสั่ง 2026-09-10)

  กฎของโปรเจค: ทุกอย่างที่ผู้ใช้เห็นต้องมีทั้งไทยและอังกฤษ
  แต่เดิมเก็บสำเนาชื่อไว้แค่ภาษาไทย พอสลับเป็น ENG ชื่อคนจึงยังเป็นไทยอยู่

  🔴 ต้องเก็บเป็น "สำเนา" คู่กับชื่อไทย ไม่ใช่ join สดจากบัญชี
     หลักเดียวกับ employee_name / position / department ที่ทำไว้แล้ว
     คนลาออกแล้วบัญชีหาย เอกสารเก่าต้องยังพิมพ์ออกมาได้ทั้ง 2 ภาษา
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_approvals', function (Blueprint $table) {
            $table->string('employee_name_en', 191)->nullable()->after('employee_name');
        });

        Schema::table('budget_invests', function (Blueprint $table) {
            $table->string('created_by_name_en', 191)->nullable()->after('created_by_name');
            $table->string('proposer_name_en', 191)->nullable()->after('proposer_name');
        });
    }

    public function down(): void
    {
        Schema::table('budget_approvals', function (Blueprint $table) {
            $table->dropColumn('employee_name_en');
        });

        Schema::table('budget_invests', function (Blueprint $table) {
            $table->dropColumn(['created_by_name_en', 'proposer_name_en']);
        });
    }
};
