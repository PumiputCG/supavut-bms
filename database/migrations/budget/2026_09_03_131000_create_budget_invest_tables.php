<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ข้อเสนอขอตั้งงบ (Invest Proposal) + ขั้นตอนอนุมัติ
 *
 * ขั้นตอนตามที่เจ้าของกำหนด 2026-09-03
 *   1. ฝ่ายบัญชีเสนอ Invest       -> approval_status = DRAFT
 *   2. ส่งให้ผู้เกี่ยวข้องรับทราบ  -> PENDING_APPROVAL (ไล่ทีละขั้นตามลำดับ)
 *   3. CEO ลงนามเป็นขั้นสุดท้าย    -> APPROVED แล้วระบบสร้าง Budget ให้อัตโนมัติ
 *
 * 🔴 `budget_approvals` คือ Central Approval Engine ที่ตกลงกันไว้ (DECISIONS ข้อ 5)
 *    ออกแบบให้ใช้กับเอกสารชนิดอื่นได้ด้วย (PR/PO/จ่ายเงิน) จึงมี doc_type ไม่ผูกกับ invest อย่างเดียว
 *    และ **คัดลอกชื่อ/ตำแหน่งผู้เซ็นมาเก็บไว้ตอนส่งเรื่อง** ไม่ join เอาทีหลัง
 *    เพราะถ้าคนลาออกแล้วบัญชีหาย เอกสารที่อนุมัติไปแล้วต้องยังพิมพ์ออกมาได้เหมือนเดิม
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── หัวเอกสารข้อเสนอ ────────────────────────────────────────
        Schema::create('budget_invests', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 30)->unique();       // INV-2569-000001
            $table->unsignedSmallInteger('fiscal_year');  // ปี พ.ศ.

            $table->string('dept_code', 20)->index();     // แผนกจากมิเรอร์ Insight
            $table->string('dept_name', 191)->nullable(); // ชื่อ ณ ตอนสร้าง กันแผนกเปลี่ยนชื่อทีหลัง
            $table->foreignId('cost_center_id')->nullable()->constrained('budget_cost_centers');
            $table->foreignId('expense_category_id')->nullable()->constrained('budget_expense_categories');

            $table->string('title', 191);                 // ชื่อรายการที่ขอตั้งงบ
            $table->text('reason')->nullable();           // เหตุผลความจำเป็น
            $table->decimal('amount', 15, 2)->default(0); // รวมจากรายการย่อย ไม่ให้กรอกมือ

            // 🔴 แยก 2 สถานะตามกฎ (DECISIONS 4.3) — ห้ามยุบรวมเป็นฟิลด์เดียว
            $table->string('approval_status', 20)->default('DRAFT')->index();

            $table->string('created_by', 30)->index();    // รหัสพนักงานผู้เสนอ
            $table->string('created_by_name', 191)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();  // วันที่จบเรื่อง (อนุมัติหรือไม่อนุมัติ)
            $table->string('reject_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['fiscal_year', 'dept_code']);
        });

        // ── รายการย่อยในข้อเสนอ ─────────────────────────────────────
        Schema::create('budget_invest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invest_id')->constrained('budget_invests')->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->string('name', 191);
            $table->string('unit', 30)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);   // quantity * unit_price คิดตอนบันทึก
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        // ── เอกสารแนบ ───────────────────────────────────────────────
        Schema::create('budget_invest_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invest_id')->constrained('budget_invests')->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('path', 255);                  // เก็บใน storage/app/public/budget
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('uploaded_by', 30)->nullable();
            $table->timestamps();
        });

        // ── ขั้นตอนอนุมัติ (ใช้ร่วมได้กับเอกสารชนิดอื่นในอนาคต) ──────
        Schema::create('budget_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 30)->default('invest')->index();
            $table->unsignedBigInteger('doc_id')->index();

            $table->unsignedInteger('step');              // ลำดับ 1,2,3... ไล่ทีละขั้น
            $table->string('action', 20);                 // acknowledge = รับทราบ · approve = ลงนามอนุมัติ

            // ผู้เซ็น — เก็บสำเนาชื่อ/ตำแหน่งไว้ตอนส่งเรื่อง (ดูเหตุผลในหัวไฟล์)
            $table->string('employee_code', 30)->index();
            $table->string('employee_name', 191)->nullable();
            $table->string('position', 191)->nullable();

            $table->string('status', 20)->default('WAITING')->index();  // WAITING · DONE · REJECTED
            $table->timestamp('acted_at')->nullable();
            $table->string('comment', 255)->nullable();
            $table->longText('signature')->nullable();    // สำเนาลายเซ็น ณ ตอนเซ็น (ของเดิมอยู่ที่ Insight)

            $table->timestamps();

            $table->unique(['doc_type', 'doc_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_approvals');
        Schema::dropIfExists('budget_invest_files');
        Schema::dropIfExists('budget_invest_items');
        Schema::dropIfExists('budget_invests');
    }
};
