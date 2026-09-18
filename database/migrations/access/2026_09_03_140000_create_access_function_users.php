<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * สิทธิ์ระดับ "หัวข้อย่อย" ในโมดูล (เจ้าของสั่ง 2026-09-03)
 *
 * ของเดิม `access_module_users` คุมได้แค่ "เห็นโมดูลไหน"
 * ตารางนี้ลงลึกอีกชั้น — ในโมดูลเดียวกัน ใครเข้าหัวข้อย่อยไหนได้บ้าง
 * เช่น ในงบประมาณ: ฝ่ายบัญชีเข้า "เสนอ Invest" ได้ · CEO เข้าแค่ "รอรับทราบ / อนุมัติ"
 *
 * 🔴 กติกาเดียวกับระดับโมดูล: หัวข้อย่อยที่ไม่มีแถวเลย = ทุกคนที่เห็นโมดูลนั้นเข้าได้
 *    ถ้าทำกลับกัน พอ migrate เสร็จทุกเมนูย่อยจะถูกล็อกหมดทันที
 *
 * รันเฉพาะโฟลเดอร์นี้:  php artisan migrate --path=database/migrations/access
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_function_users', function (Blueprint $table) {
            $table->id();
            $table->string('module_id', 50)->index();       // ตรงกับ id ใน App\Support\NavMenu
            $table->string('function_key', 60)->index();    // คีย์ของหัวข้อย่อย เช่น fn.budget.invest
            $table->string('employee_code', 30);
            $table->string('updated_by', 30)->nullable();
            $table->timestamps();

            // ⚠️ ตั้งชื่อ index เองสั้นๆ — ชื่ออัตโนมัติของ Laravel ยาวเกิน 64 ตัวอักษรที่ MySQL รับได้
            $table->unique(['module_id', 'function_key', 'employee_code'], 'access_fn_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_function_users');
    }
};
