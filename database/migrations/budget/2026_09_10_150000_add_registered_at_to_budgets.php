<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
  🔴 เก็บ "ใครติ๊กลงทะเบียน เมื่อไหร่" (เจ้าของสั่งให้ตารางเส้นทางโชว์วันเวลา 2026-09-10)

     ใช้ updated_at แทนไม่ได้ เพราะมันขยับทุกครั้งที่แถวถูกแก้ไม่ว่าเรื่องอะไร
     และการยืนยันว่า "คีย์เข้า ERP แล้ว" เป็นการกระทำที่กระทบเงินจริง ต้องรู้ว่าใครทำ
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dateTime('registered_at')->nullable()->after('status_note');
            $table->string('registered_by', 30)->nullable()->after('registered_at');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn(['registered_at', 'registered_by']);
        });
    }
};
