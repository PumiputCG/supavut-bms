<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ตารางมิเรอร์ข้อมูลพนักงานและบัญชีผู้ใช้ของ SBMS
 *
 * เจ้าของข้อมูลจริงคือ Supavut Insight (DB `insight`) ซึ่งดึงมาจาก Bplus อีกที
 * SBMS คัดลอกมาเก็บเอง เพื่อให้ล็อกอิน/แสดงผลเร็ว ไม่ต้อง join ข้าม DB ทุก request
 * และยังใช้งานต่อได้ถ้า Insight ล่มชั่วคราว
 *
 * 🔴 สำเนาทางเดียว — ห้ามแก้ข้อมูลพนักงานจากฝั่ง SBMS
 *    แก้ที่ Insight ที่เดียว แล้วรอ `insight:sync` คัดลอกมาทับ
 *
 * 🔴 ห้ามเพิ่มคอลัมน์เงินเดือน/ค่าจ้าง/ภาษี ลงตารางเหล่านี้เด็ดขาด (กฎใน CLAUDE.md)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1) employees : ทะเบียนพนักงาน (ไม่ใช่ตารางล็อกอิน) ────────────────
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('insight_id')->unique();   // id ต้นทางใน insight.employees
            $table->unsignedInteger('no')->nullable()->index();

            $table->string('company', 30)->index();
            $table->string('employee_code', 30);
            $table->string('license_id', 30)->nullable();         // เลขบัตรประชาชน = รหัสผ่านเริ่มต้น

            $table->string('title', 30)->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('name_th', 100)->nullable();
            $table->string('surname_th', 100)->nullable();
            $table->string('name_en', 150)->nullable();

            $table->string('job_code', 30)->nullable();
            $table->string('job_th', 191)->nullable();
            $table->string('job_en', 191)->nullable();

            $table->string('dept_code', 20)->nullable()->index();
            $table->string('dept_th', 191)->nullable();
            $table->string('dept_en', 191)->nullable();

            $table->string('branch_code', 20)->nullable();
            $table->string('branch_th', 191)->nullable();
            $table->string('branch_en', 191)->nullable();

            $table->date('hire_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->string('emp_status', 10)->nullable()->index(); // 1 = ทำงาน · 2 = ลาออก

            // รูปพนักงาน — Insight ย้ายมาเก็บที่ employees แล้ว (2026-09-02) รูปจึงไม่หายตอนลาออก
            $table->string('photo_path')->nullable();

            $table->timestamp('mirrored_at')->nullable();
            $table->timestamps();

            $table->unique(['company', 'employee_code']);
        });

        // ── 2) app_users : ตารางที่ใช้ล็อกอินจริง ─────────────────────────────
        Schema::create('app_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('insight_id')->unique();   // id ต้นทางใน insight.app_users

            $table->string('id_thai_hash')->nullable()->unique(); // เลขบัตรประชาชน (ตัวตนหลัก)
            $table->string('company')->index();
            $table->string('employee_code', 30)->index();
            $table->json('companies')->nullable();                // map บริษัท -> รหัส (คนข้ามบริษัท)
            $table->string('password')->nullable();               // plaintext ตาม decision D-007 ของ Insight
            $table->string('role', 50)->default('user');          // admin / user

            $table->string('full_name_th', 255)->nullable();
            $table->string('full_name_en', 255)->nullable();
            $table->string('position', 255)->nullable();
            $table->string('department', 255)->nullable();
            $table->string('email', 255)->nullable();

            $table->string('profile_picture')->nullable();        // path ในสตอเรจของ Insight
            $table->longText('signature')->nullable();            // data URL PNG

            $table->timestamp('registered_at')->nullable();
            $table->timestamp('mirrored_at')->nullable();
            $table->timestamps();
        });

        // ── 3) sync_logs : ประวัติการซิงค์ ────────────────────────────────────
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);            // manual · schedule · login · webhook
            $table->boolean('ok')->default(true);
            $table->text('message')->nullable();
            $table->json('detail')->nullable();
            $table->timestamp('ran_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('app_users');
        Schema::dropIfExists('employees');
    }
};
