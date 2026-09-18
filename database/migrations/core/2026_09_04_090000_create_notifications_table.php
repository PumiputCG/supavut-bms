<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * แจ้งเตือนของระบบ — ใช้ร่วมทุกโมดูล (เจ้าของสั่ง 2026-09-04)
 *
 * 🔴 ทำไมต้องมีตาราง ไม่คำนวณสดจากเอกสาร
 *    เรื่องอย่าง "รอฉันลงนาม" คำนวณสดได้ แต่ "เอกสารของคุณถูกอนุมัติแล้ว" ไม่ได้
 *    เพราะต้องรู้ว่าผู้ใช้ **เห็นแล้วหรือยัง** ไม่งั้นจะค้างเป็นแจ้งเตือนตลอดไป
 *
 * 🔴 เก็บ module_id + function_key ไว้ด้วย เพื่อให้กระดิ่งจัดกลุ่มเป็น
 *    โมดูล -> หัวข้อย่อย ได้โดยไม่ต้องเดาจาก url
 *
 * ข้อความเก็บเป็นคู่ th/en ตามกฎ 2 ภาษาของโปรเจค
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // ใครได้รับ — เก็บรหัสพนักงาน ไม่ผูก FK เพราะบัญชีเป็นมิเรอร์จาก Insight
            $table->string('employee_code', 30)->index();

            // จัดกลุ่มในกระดิ่ง
            $table->string('module_id', 50)->index();
            $table->string('function_key', 60)->index();

            $table->string('event', 40);                 // invest_submitted · invest_approved ...
            $table->string('doc_no', 40)->nullable();    // เลขที่เอกสาร ไว้โชว์ในรายการ
            $table->string('url', 191)->nullable();      // กดแล้วไปไหน

            $table->string('title_th', 191);
            $table->string('title_en', 191);
            $table->string('body_th', 191)->nullable();
            $table->string('body_en', 191)->nullable();

            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            // ดึง "ที่ยังไม่อ่านของคนนี้" เป็นคิวรีที่ยิงบ่อยที่สุด
            $table->index(['employee_code', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
