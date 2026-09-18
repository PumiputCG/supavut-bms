<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ข้อเสนอ Invest หนึ่งฉบับสร้าง Budget ได้ไม่เกินหนึ่งก้อน
 *
 * application lock ใน InvestFlow กันการกดพร้อมกันตามปกติ ส่วน unique นี้เป็นด่านสุดท้าย
 * สำหรับกรณีมีโค้ดเส้นทางอื่นเพิ่มในอนาคตหรือเกิด race condition นอก flow หลัก
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->unique('invest_id', 'budgets_invest_id_unique');
        });
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('budgets'));
        $hasNonUniqueIndex = $indexes->contains(function (array $index) {
            return ! ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['invest_id'];
        });

        // MySQL อาจลบ index เดิมที่ซ้ำซ้อนตอนเพิ่ม unique แต่ FK ยังต้องมี index รองรับ
        if (! $hasNonUniqueIndex) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->index('invest_id', 'budgets_invest_id_restore_index');
            });
        }

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique('budgets_invest_id_unique');
        });

        if (! $hasNonUniqueIndex) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->index('invest_id', 'budgets_invest_id_foreign');
                $table->dropIndex('budgets_invest_id_restore_index');
            });
        }
    }
};
