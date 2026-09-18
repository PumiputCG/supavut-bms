<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ประวัติการใช้งานระบบ — ใครทำอะไร เมื่อไหร่ จากเครื่องไหน
 *
 * ใช้ตรวจสอบย้อนหลัง จึงต้อง **เก็บชื่อไว้ตอนนั้นด้วย** (actor_name)
 * ถ้าไป join เอาชื่อจาก app_users ตอนเปิดดู พอคนลาออกแล้วบัญชีหาย ประวัติจะกลายเป็นบรรทัดว่าง
 *
 * 🔴 ห้ามเก็บรหัสผ่าน เลขบัตรประชาชน หรือค่าจ้าง ลงตารางนี้เด็ดขาด (กฎใน CLAUDE.md)
 *    ล็อกอินไม่สำเร็จให้เก็บแค่ "รหัสพนักงานที่กรอกมา" ห้ามเก็บรหัสผ่านที่พิมพ์ผิด
 *
 * รันเฉพาะโฟลเดอร์นี้:  php artisan migrate --path=database/migrations/audit
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // ใครทำ — เก็บทั้งรหัสและชื่อ ณ ตอนนั้น
            $table->string('employee_code', 30)->nullable()->index();
            $table->string('actor_name', 191)->nullable();

            // ทำอะไร
            $table->string('event', 40)->index();          // login · logout · login_failed · page_view · admin_added ...
            $table->string('subject', 191)->nullable();    // สิ่งที่ถูกกระทำ เช่น รหัสพนักงาน หรือ id ของโมดูล

            // อธิบายให้คนอ่านรู้เรื่อง — เก็บ 2 ภาษาตามกฎของโปรเจค
            $table->string('detail_th', 255)->nullable();
            $table->string('detail_en', 255)->nullable();

            // มาจากไหน
            $table->string('ip', 45)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('agent', 255)->nullable();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
