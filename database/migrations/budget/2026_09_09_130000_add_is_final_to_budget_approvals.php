<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
  ทำเครื่องหมายว่าแถวไหนคือ "ผู้อนุมัติลำดับสุดท้าย" (CEO)

  🔴 เจ้าของสั่ง 2026-09-09: ล็อก CEO ไว้ในสายอนุมัติตั้งแต่ตอนบันทึกร่างเลย
     เพื่อให้ทุกหน้า (สำเนาเรียน · ช่องลงชื่อ · รูปพนักงาน · เส้นทางเอกสาร)
     อ่านจากแหล่งเดียวกันคือฐานข้อมูล ไม่ต้องให้แต่ละหน้าจำเติมเอง

  ทำไมต้องมีคอลัมน์นี้ ไม่ใช่แค่ดูว่าเป็นแถวสุดท้าย:
     เปลี่ยนตัว CEO ใน config เมื่อไหร่ ร่างเก่าจะค้างชื่อคนเดิม
     ตอนกดส่งต้อง "ถอดแถวปิดท้ายเก่าออกแล้วต่อคนปัจจุบัน" ให้ได้แบบไม่กำกวม
     ถ้าเดาจากลำดับอย่างเดียว จะแยกไม่ออกว่าแถวสุดท้ายเป็น CEO หรือเป็นคนที่ผู้ขอเลือกเอง
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_approvals', function (Blueprint $table) {
            $table->boolean('is_final')->default(false)->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('budget_approvals', function (Blueprint $table) {
            $table->dropColumn('is_final');
        });
    }
};
