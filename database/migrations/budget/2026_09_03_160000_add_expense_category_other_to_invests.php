<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * หมวดงบประมาณเลือก "อื่นๆ" แล้วต้องกรอกว่าอื่นๆ คืออะไร (เจ้าของสั่ง 2026-09-03)
 *
 * เก็บเป็นคอลัมน์แยก ไม่ยัดรวมใน description
 * เพราะต่อไปจะเอาไปทำรายงานสรุปตามหมวดได้ว่า "อื่นๆ" ที่ขอกันจริงๆ มีอะไรบ้าง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->string('expense_category_other', 191)->nullable()->after('expense_category_id');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->string('expense_category_other', 191)->nullable()->after('expense_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->dropColumn('expense_category_other');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn('expense_category_other');
        });
    }
};
