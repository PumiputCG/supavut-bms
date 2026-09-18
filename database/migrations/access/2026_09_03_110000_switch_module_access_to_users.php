<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เปลี่ยนสิทธิ์รายโมดูลจาก "ตามตำแหน่ง" เป็น "ระบุคนรายคน"
 *
 * เจ้าของสั่งเมื่อ 2026-09-03: หน้าแก้ไขสิทธิ์โมดูลให้ค้นหาพนักงานรายคน เลือกได้หลายคน
 * ของเดิมผูกกับ job_code ซึ่งกว้างเกินไป — ในแผนกเดียวกันตำแหน่งเดียวกันก็ไม่จำเป็นต้องเห็นเหมือนกัน
 *
 * ตารางเดิมเพิ่งสร้างวันเดียวกันและยังไม่มีข้อมูลจริง จึงตัดทิ้งได้เลย ไม่ต้องย้ายข้อมูล
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('access_module_positions');

        Schema::create('access_module_users', function (Blueprint $table) {
            $table->id();
            $table->string('module_id', 50)->index();   // ตรงกับ id ใน App\Support\NavMenu
            $table->string('employee_code', 30);
            $table->string('updated_by', 30)->nullable();
            $table->timestamps();

            // 🔴 กติกาเดิมยังอยู่: โมดูลที่ไม่มีแถวเลย = ทุกคนที่เข้าระบบได้เห็นได้
            $table->unique(['module_id', 'employee_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_module_users');

        Schema::create('access_module_positions', function (Blueprint $table) {
            $table->id();
            $table->string('module_id', 50)->index();
            $table->string('job_code', 30);
            $table->string('updated_by', 30)->nullable();
            $table->timestamps();

            $table->unique(['module_id', 'job_code']);
        });
    }
};
