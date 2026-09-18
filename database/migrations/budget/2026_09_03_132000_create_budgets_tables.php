<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * งบประมาณที่อนุมัติแล้ว + บัญชีเดินสะพัดของงบ
 *
 * เกิดขึ้นเมื่อ CEO ลงนามอนุมัติ Invest — ระบบสร้าง Budget ให้อัตโนมัติ 1 ก้อนต่อ 1 Invest
 *
 * 🔴 ยอดคงเหลือ **ห้ามเก็บเป็นคอลัมน์สะสม** (กฎใน DECISIONS 4.1)
 *    ให้บวกจาก budget_transactions ทุกครั้ง จะได้ตอบได้เสมอว่ายอดมาจากรายการไหน
 *    และแก้รายการย้อนหลังได้โดยไม่ต้องไล่คำนวณใหม่ทั้งระบบ
 *
 *    ตัวเลข 4 ค่าที่ตกลงกันไว้ คิดจาก ledger ดังนี้
 *      งบตั้งไว้ (Budget)   = ผลรวม type = initial, adjust
 *      กันไว้ (Reserved)    = ผลรวม type = reserve, release   (release เป็นค่าติดลบ)
 *      ใช้จริง (Actual)     = ผลรวม type = actual
 *      คงเหลือ (Available)  = งบตั้งไว้ - กันไว้ - ใช้จริง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 30)->unique();       // BGT-2569-000001
            $table->foreignId('invest_id')->nullable()->constrained('budget_invests');

            $table->unsignedSmallInteger('fiscal_year')->index();
            $table->string('dept_code', 20)->index();
            $table->string('dept_name', 191)->nullable();
            $table->foreignId('cost_center_id')->nullable()->constrained('budget_cost_centers');
            $table->foreignId('expense_category_id')->nullable()->constrained('budget_expense_categories');
            $table->string('title', 191);

            // 🔴 2 สถานะแยกกัน — สั่งพักงบ (HOLD) ต้องไม่ไปแตะ approval_status
            $table->string('approval_status', 20)->default('APPROVED');
            $table->string('budget_status', 20)->default('ACTIVE')->index();

            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by', 30)->nullable();
            $table->string('status_note', 255)->nullable();   // เหตุผลตอนพัก/หยุด/ปิดงบ
            $table->timestamps();

            // กุญแจของงบ 1 ก้อนตามที่ตกลงไว้: ปีงบ + แผนก + ศูนย์ต้นทุน + หมวด + ชื่องบ
            // ⚠️ ต้องตั้งชื่อ index เองสั้นๆ — ชื่ออัตโนมัติของ Laravel ยาวเกิน 64 ตัวอักษรที่ MySQL รับได้
            $table->index(['fiscal_year', 'dept_code', 'cost_center_id', 'expense_category_id'], 'budgets_key_index');
        });

        Schema::create('budget_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();

            // initial = ตั้งงบครั้งแรก · adjust = ปรับเพิ่ม/ลด
            // reserve = กันเงินไว้ตอนเปิด PR · release = คืนเงินที่กันไว้ · actual = ใช้จริงตอนจ่าย
            $table->string('type', 20)->index();
            $table->decimal('amount', 15, 2);             // ติดลบได้ สำหรับ release / ปรับลด

            $table->string('ref_type', 30)->nullable();   // เอกสารอ้างอิง เช่น invest, pr, po (เฟสถัดไป)
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('note', 255)->nullable();

            $table->string('created_by', 30)->nullable();
            $table->string('created_by_name', 191)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_transactions');
        Schema::dropIfExists('budgets');
    }
};
