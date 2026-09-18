<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
  เก็บ "แผนก" ของคนในสายอนุมัติไว้ในเอกสารด้วย (เจ้าของสั่ง 2026-09-09)

  ตารางเส้นทางเอกสารแบบใหม่ต้องโชว์ แผนก + ตำแหน่ง ของทุกคน

  🔴 เก็บเป็นสำเนา ไม่ join สดจากบัญชี — หลักเดียวกับ employee_name / position ที่มีอยู่แล้ว
     เพราะคนลาออกแล้วบัญชีหาย เอกสารเก่าต้องยังพิมพ์ออกมาได้เหมือนเดิม
     และคนย้ายแผนกทีหลัง เอกสารเก่าต้องยังบอกแผนก ณ ตอนที่เซ็น ไม่ใช่แผนกปัจจุบัน
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_approvals', function (Blueprint $table) {
            $table->string('department', 191)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('budget_approvals', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }
};
