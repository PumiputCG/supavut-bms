<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * กลุ่มเอกสาร (ตั้งค่าหมายเลขเอกสาร) — เจ้าของสั่ง 2026-09-17
 *
 * แอดมินตั้งกลุ่มเอง เช่น New Model โค้ด NM
 *   -> เลขที่กลายเป็น INV-NM-2569-000001 และ BGT-NM-2569-000001
 *
 * 🔴 โค้ดช่องเดียวใช้กับเอกสารทั้ง 2 ชนิด (เจ้าของยืนยัน)
 * 🔴 เลขวิ่งแยกต่อกลุ่มได้ฟรี เพราะ DocNumber::next() นับด้วย LIKE 'INV-NM-2569-%' อยู่แล้ว
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_doc_groups', function (Blueprint $table) {
            $table->id();

            // 🔴 โค้ดห้ามซ้ำ เพราะเป็นตัวแยกช่วงเลขวิ่งของแต่ละกลุ่ม
            $table->string('code', 4)->unique();

            // ชื่อต้องมีครบ 2 ภาษา (กฎโปรเจค — ชื่อจาก DB ใช้ data-loc-th/en)
            $table->string('name_th', 80);
            $table->string('name_en', 80);

            $table->unsignedSmallInteger('sort')->default(0);

            // ปิดใช้งานแทนการลบ เมื่อกลุ่มนั้นมีเอกสารออกไปแล้ว
            $table->boolean('active')->default(true);

            $table->string('created_by', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_doc_groups');
    }
};
