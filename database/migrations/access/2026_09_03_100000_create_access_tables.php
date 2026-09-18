<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ตารางสิทธิ์การเข้าถึงของ SBMS
 *
 * 🔴 ทำไมต้องมีตารางของตัวเอง ไม่ใช้ `app_users.role` ที่มิเรอร์มา
 *    `app_users` ถูก `insight:sync` เขียนทับทุกรอบ ถ้าตั้ง admin ไว้ที่นั่นจะหายทุกครั้งที่ซิงค์
 *    สิทธิ์เป็นเรื่องของ SBMS เอง จึงต้องเก็บแยกในตารางที่ซิงค์ไม่แตะ
 *
 * รันเฉพาะโฟลเดอร์นี้:  php artisan migrate --path=database/migrations/access
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1) ผู้ดูแลระบบของ SBMS ────────────────────────────────────────────
        // เก็บเป็นรหัสพนักงาน ไม่ใช่ app_users.id เพราะ id ฝั่งมิเรอร์อาจเปลี่ยนเมื่อซิงค์ใหม่แบบ --fresh
        Schema::create('access_admins', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 30);
            $table->string('company')->nullable();
            $table->string('note', 255)->nullable();
            $table->string('granted_by', 30)->nullable();   // รหัสพนักงานของคนที่กดให้สิทธิ์
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();

            $table->unique('employee_code');
        });

        // ── 2) ตำแหน่งที่เข้าสู่ระบบ SBMS ได้ ─────────────────────────────────
        // ค่าเริ่มต้นคือ "เข้าได้ทุกตำแหน่ง" เพื่อไม่ให้พฤติกรรมเดิมเปลี่ยนตอน migrate
        // เจ้าของค่อยไปติ๊กออกทีหลังว่าตำแหน่งไหนไม่ให้เข้า
        Schema::create('access_login_positions', function (Blueprint $table) {
            $table->id();
            $table->string('job_code', 30)->unique();
            $table->string('job_th', 191)->nullable();
            $table->string('job_en', 191)->nullable();
            $table->boolean('can_login')->default(true);
            $table->string('updated_by', 30)->nullable();
            $table->timestamps();
        });

        // ── 3) ตำแหน่งที่เห็นโมดูลไหนได้บ้าง ─────────────────────────────────
        // 🔴 กติกา: โมดูลที่ "ไม่มีแถวเลย" = ทุกคนที่เข้าระบบได้เห็นได้
        //    พอเพิ่มแถวแรกเมื่อไหร่ จะกลายเป็นเห็นได้เฉพาะตำแหน่งที่ระบุไว้ทันที
        //    ทำแบบนี้เพื่อให้ migrate แล้วระบบยังใช้งานได้เหมือนเดิม ไม่ใช่จอว่างเปล่า
        Schema::create('access_module_positions', function (Blueprint $table) {
            $table->id();
            $table->string('module_id', 50)->index();       // ตรงกับ id ใน App\Support\NavMenu
            $table->string('job_code', 30);
            $table->string('updated_by', 30)->nullable();
            $table->timestamps();

            $table->unique(['module_id', 'job_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_module_positions');
        Schema::dropIfExists('access_login_positions');
        Schema::dropIfExists('access_admins');
    }
};
