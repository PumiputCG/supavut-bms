<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
  เลิกใช้ "มิเรอร์งบจาก ERP" (เจ้าของสั่งเก็บกวาด 2026-09-18)

  🔴 แดชบอร์ดอ่าน ERP สดทุกครั้งตั้งแต่ 2026-09-11 — ไม่เคยอ่านตารางคู่นี้เลย
     ข้อมูลในนั้นหยุดนิ่งตั้งแต่ 14 ก.ย. 16:18 (Windows task ถูกปิดไปแล้ว)
     เก็บไว้เฉยๆ มีแต่ทำให้คนเข้าใจผิดว่าเป็นตัวเลขจริงของวันนี้
  สำรองเป็น JSON ไว้ที่ storage/app/backup/erp-mirror-drop-20260918-094703 ก่อนลบแล้ว
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('erp_budget_mirror');
        Schema::dropIfExists('erp_sync_status');
    }

    public function down(): void
    {
        // คืนโครงตารางให้เท่าของเดิม (ข้อมูลอยู่ในไฟล์สำรอง JSON)
        Schema::create('erp_budget_mirror', function (Blueprint $table) {
            $table->id();
            $table->string('company', 8);
            $table->string('erp_recid', 32);
            $table->string('model', 40)->nullable();
            $table->string('budget_no', 60)->nullable();
            $table->string('title', 255)->nullable();
            $table->string('dept', 40)->nullable();
            $table->string('cost', 40)->nullable();
            $table->string('purpose', 40)->nullable();
            $table->string('currency', 8)->nullable();
            $table->date('start_date')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('stopped')->default(false);
            $table->bigInteger('budget')->default(0);
            $table->bigInteger('actual')->default(0);
            $table->bigInteger('reserve')->default(0);
            $table->bigInteger('available')->default(0);
            $table->timestamps();
            $table->unique(['company', 'erp_recid']);
        });
        Schema::create('erp_sync_status', function (Blueprint $table) {
            $table->string('dataset', 40)->primary();
            $table->string('revision', 64)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->dateTime('checked_at')->nullable();
            $table->dateTime('changed_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->string('error_type', 120)->nullable();
        });
    }
};
