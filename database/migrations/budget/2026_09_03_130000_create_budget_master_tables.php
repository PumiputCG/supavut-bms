<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ข้อมูลหลักที่โมดูลงบประมาณต้องใช้ + บทบาทของคนในโมดูลนี้
 *
 * ทำไมต้องมีศูนย์ต้นทุนแยกจากแผนก (ตกลงกันไว้ 2026-09-02)
 *   แผนกมาจาก Insight แก้ที่นี่ไม่ได้ · ศูนย์ต้นทุนเป็นของ SBMS เอง ฝ่ายบัญชีดูแล
 *   Phase 1 แมป 1:1 กับแผนก แต่แยกตารางไว้ตั้งแต่แรก จะได้ไม่ต้องรื้อตอนบัญชีขอแตกศูนย์ต้นทุนย่อย
 *
 * รันเฉพาะโฟลเดอร์นี้:  php artisan migrate --path=database/migrations/budget
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── หมวดค่าใช้จ่าย ────────────────────────────────────────────
        Schema::create('budget_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_th', 191);
            $table->string('name_en', 191);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ค่าตั้งต้นที่เจ้าของตกลงไว้แล้ว — เป็นข้อมูลตั้งค่าของระบบ ไม่ใช่ข้อมูลทดสอบ
        DB::table('budget_expense_categories')->insert([
            ['code' => 'SUPPLY', 'name_th' => 'วัสดุสิ้นเปลือง', 'name_en' => 'Consumable supplies', 'sort' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ASSET', 'name_th' => 'ครุภัณฑ์', 'name_en' => 'Durable articles', 'sort' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'MAINT', 'name_th' => 'ซ่อมบำรุง', 'name_en' => 'Maintenance', 'sort' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'SERVICE', 'name_th' => 'จ้างบริการ', 'name_en' => 'Outsourced services', 'sort' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'GENERAL', 'name_th' => 'ทั่วไป', 'name_en' => 'General', 'sort' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'OTHER', 'name_th' => 'อื่นๆ', 'name_en' => 'Other', 'sort' => 6, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── ศูนย์ต้นทุน ──────────────────────────────────────────────
        Schema::create('budget_cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_th', 191);
            $table->string('name_en', 191)->nullable();
            $table->string('dept_code', 20)->nullable()->index();   // อ้างแผนกที่มิเรอร์มาจาก Insight
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── บทบาทในโมดูลงบประมาณ ────────────────────────────────────
        // 🔴 ห้ามตัดสินจาก job_code ตรงๆ (กฎใน DECISIONS 4.4) — ให้ผู้ดูแลระบบระบุตัวคนเอง
        Schema::create('budget_roles', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 30);
            $table->string('role', 20);          // accounting = เสนอ Invest · ceo = ลงนามอนุมัติขั้นสุดท้าย
            $table->string('granted_by', 30)->nullable();
            $table->timestamps();

            $table->unique(['employee_code', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_roles');
        Schema::dropIfExists('budget_cost_centers');
        Schema::dropIfExists('budget_expense_categories');
    }
};
