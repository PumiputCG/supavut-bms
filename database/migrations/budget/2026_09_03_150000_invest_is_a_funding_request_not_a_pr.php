<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * แก้ให้ Invest เป็น "ข้อเสนอขอวงเงินระดับแผนก" ไม่ใช่ใบขอซื้อ
 *
 * 🔴 เจ้าของแก้ requirement เมื่อ 2026-09-03
 *    ของเดิมทำเป็นตารางรายการสินค้า (ชื่อ · หน่วย · จำนวน · ราคา/หน่วย) ซึ่งนั่นคือหน้าตาของ PR
 *    Invest จริงๆ คือการขอ "วงเงิน" ก้อนเดียวให้แผนก แล้วค่อยไปแตกเป็น PR ทีหลัง
 *
 * ที่เอาออก
 *   - ตาราง budget_invest_items ทั้งตาราง
 *   - cost_center_id ทั้งใน invest และ budget (ยังไม่ใช้ในเฟสนี้ — เป็น Future Module)
 *
 * ที่เพิ่ม
 *   - budget_invests.description  รายละเอียด / วัตถุประสงค์ (คนละอันกับ "เหตุผลความจำเป็น")
 *   - budgets.approved_amount     วงเงินที่อนุมัติ
 *     🔴 เก็บเป็นคอลัมน์ตรงๆ เพราะเฟสนี้ยังไม่ confirm ว่าจะตัดงบตอน PR/PO/Payment
 *        ตาราง budget_transactions ยังอยู่เพื่อรองรับอนาคต แต่ยังไม่เอามาคิดยอดบนหน้าจอ
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('budget_invest_items');

        Schema::table('budget_invests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
            $table->text('description')->nullable()->after('title');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('budgets_key_index');
            $table->dropConstrainedForeignId('cost_center_id');
            $table->decimal('approved_amount', 15, 2)->default(0)->after('title');
            $table->index(['fiscal_year', 'dept_code', 'expense_category_id'], 'budgets_key_index');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('budgets_key_index');
            $table->dropColumn('approved_amount');
            $table->foreignId('cost_center_id')->nullable()->constrained('budget_cost_centers');
            $table->index(['fiscal_year', 'dept_code', 'cost_center_id', 'expense_category_id'], 'budgets_key_index');
        });

        Schema::table('budget_invests', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->foreignId('cost_center_id')->nullable()->constrained('budget_cost_centers');
        });

        Schema::create('budget_invest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invest_id')->constrained('budget_invests')->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->string('name', 191);
            $table->string('unit', 30)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }
};
