<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เก็บรหัสตำแหน่ง / แผนก / สาขา ไว้ที่ตารางบัญชีด้วย
 *
 * ทำไมต้องเก็บซ้ำทั้งที่ `employees` มีอยู่แล้ว:
 * `insight.app_users` มีแค่ชื่อตำแหน่ง/แผนกเป็นข้อความ (`position`, `department`) ไม่มีรหัส
 * แต่งานของ SBMS ต้องใช้ "รหัส" เป็นตัวตัดสิน เช่น เส้นทางอนุมัติ PR ตามรหัสแผนก
 * ถ้าอิงชื่อที่เป็นข้อความจะพังทันทีที่ HR เปลี่ยนคำ (เช่น "IT" -> "Information Technology")
 *
 * ค่าถูกเติมตอนซิงค์โดยจับคู่กับตาราง employees (ดู App\Services\Core\InsightMirror)
 * ไม่ได้ให้ผู้ใช้กรอกเอง — แหล่งจริงยังเป็น Bplus ผ่าน Insight เหมือนเดิม
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            $table->string('job_code', 30)->nullable()->after('department')->index();
            $table->string('dept_code', 20)->nullable()->after('job_code')->index();
            $table->string('branch_code', 20)->nullable()->after('dept_code');
        });
    }

    public function down(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            $table->dropIndex(['job_code']);
            $table->dropIndex(['dept_code']);
            $table->dropColumn(['job_code', 'dept_code', 'branch_code']);
        });
    }
};
