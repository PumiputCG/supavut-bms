<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 🔴 เลขที่เอกสารออกตอน "กดส่ง" ไม่ใช่ตอน "บันทึกร่าง" (เจ้าของสั่ง 2026-09-17)
 *
 * เหตุผลของเจ้าของ: ร่างที่ได้เลขแล้วแต่ไม่ยอมกดส่งสักที **จองเลขค้างไว้ถาวร**
 * คนที่กรอกทีหลังแล้วส่งก่อนจะได้เลขที่มากกว่า ทำให้เลขในระบบไม่เรียงตามลำดับการส่งจริง
 * ออกตอนกดส่งแล้วเลขจะเรียงตามลำดับที่ส่งเข้ามาเสมอ และไม่มีเลขค้างจอง
 *
 * ผลที่ตามมา: ร่างไม่มีเลขที่ -> คอลัมน์ต้องรับค่าว่างได้
 * (unique เดิมยังอยู่ · MySQL ยอมให้มีค่า NULL ซ้ำกันได้หลายแถว จึงไม่ชนกัน)
 *
 * เพิ่ม group_id ให้ทั้ง 2 ตารางด้วย — งบ denormalize ค่าจากข้อเสนออยู่แล้ว (dept_code · title)
 * จึงเก็บกลุ่มไว้ที่ตัวเองด้วย ไม่ต้อง join ตอนกรองแท็บ
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->string('doc_no', 30)->nullable()->change();
            $table->foreignId('group_id')->nullable()->after('doc_no');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('doc_no');
        });
    }

    public function down(): void
    {
        Schema::table('budget_invests', function (Blueprint $table) {
            $table->dropColumn('group_id');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn('group_id');
        });
    }
};
