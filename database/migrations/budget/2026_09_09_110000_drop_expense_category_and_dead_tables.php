<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
  เก็บกวาดของที่เลิกใช้ (เจ้าของสั่ง 2026-09-09 — "เก็บแต่ที่ใช้ ไม่งั้นรกและดูยาก")

  1) หมวดค่าใช้จ่ายของ SBMS เอง (SUPPLY/ASSET/MAINT/…) ถูกถอดออกจากทุกหน้า
     เพราะ map เข้า ERP ไม่ได้ — ต่อไปจะแทนด้วยเลขผังบัญชี (ACCOUNTNUM) ของ AX
     ดู DECISIONS ข้อ 34.2 และ 35
  2) "เหตุผลความจำเป็น" (reason) เอาออกจากฟอร์ม เหลือแค่ "รายละเอียด"
     🔴 ไม่เกี่ยวกับ reject_reason ซึ่งยังใช้อยู่
  3) ตารางที่ประกาศเลิกใช้ไปแล้วแต่ยังค้างอยู่ในฐาน

  ⚠️ down() คืนได้แค่โครงสร้าง ข้อมูลเดิมคืนไม่ได้
*/
return new class extends Migration
{
    public function up(): void
    {
        /*
          🔴 ต้องปลด foreign key ก่อนลบคอลัมน์ และต้องอ้างด้วย "ชื่อคอลัมน์" ไม่ใช่ชื่อ constraint
             MySQL  — ประกอบชื่อ constraint ตามแบบแผนให้เอง
             SQLite — ลบตามชื่อไม่ได้ (โยน RuntimeException) แต่รับแบบชื่อคอลัมน์
                      แล้วจัดการตอนสร้างตารางใหม่ให้ · ถ้าไม่ปลดจะพังตอน drop column
        */
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropColumn(['expense_category_id', 'expense_category_other', 'reason']);
        });

        /*
          budgets มี index รวม `budgets_key_index` ที่คร่อม expense_category_id อยู่
          ต้องรื้อ index ก่อน แล้วสร้างใหม่ให้เหลือเฉพาะคอลัมน์ที่ยังอยู่
        */
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('budgets_key_index');
            $table->dropForeign(['expense_category_id']);
            $table->dropColumn(['expense_category_id', 'expense_category_other']);
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->index(['fiscal_year', 'dept_code'], 'budgets_key_index');
        });

        // ตารางที่ไม่มีโค้ดไหนเรียกใช้แล้ว
        Schema::dropIfExists('budget_expense_categories');
        Schema::dropIfExists('budget_cost_centers');
        Schema::dropIfExists('budget_roles');   // ยุบไปใช้สิทธิ์ที่ /access/modules แทนแล้ว
        Schema::dropIfExists('access_module_users');  // สิทธิ์เหลือ 2 ชั้น ไม่มีชั้นโมดูลแล้ว
    }

    public function down(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->unsignedBigInteger('expense_category_id')->nullable()->after('dept_name');
            $table->string('expense_category_other', 191)->nullable()->after('expense_category_id');
            $table->text('reason')->nullable()->after('description');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->unsignedBigInteger('expense_category_id')->nullable()->after('dept_name');
            $table->string('expense_category_other', 191)->nullable()->after('expense_category_id');
        });
    }
};
