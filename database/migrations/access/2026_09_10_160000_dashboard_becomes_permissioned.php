<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
  🔴 "แดชบอร์ด" กำหนดสิทธิ์ได้แล้ว (เจ้าของสั่ง 2026-09-10)

     เดิมเป็นลิงก์ตรงที่ไม่เคยโผล่ในหน้า /access/modules เลย = ใครล็อกอินได้ก็เห็น

  🔴 ปัญหาที่ต้องกันไว้: กติกาของระบบคือ "ไม่ระบุใคร = ไม่มีใครใช้ได้"
     ถ้าปล่อยให้ว่าง ทุกคนจะเห็นแดชบอร์ดไม่ได้ทันทีที่ deploy
     จึงตั้งเป็น "เปิดให้ทุกคนที่เข้าระบบได้" (แถว *) เพื่อรักษาพฤติกรรมเดิมไว้ก่อน
     ผู้ดูแลระบบค่อยเข้าไปจำกัดเองทีหลังที่ /access/modules
*/
return new class extends Migration
{
    private const MODULE = 'dashboard';

    private const KEY = 'fn.overview.dashboard';

    public function up(): void
    {
        if (! Schema::hasTable('access_function_users')) {
            return;
        }

        $exists = DB::table('access_function_users')
            ->where('module_id', self::MODULE)
            ->where('function_key', self::KEY)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('access_function_users')->insert([
            'module_id' => self::MODULE,
            'function_key' => self::KEY,
            'employee_code' => '*',   // AccessService::EVERYONE
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        info('access: เปิดสิทธิ์แดชบอร์ดให้ทุกคนไว้ก่อน (รักษาพฤติกรรมเดิม)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('access_function_users')) {
            return;
        }

        DB::table('access_function_users')
            ->where('module_id', self::MODULE)
            ->where('function_key', self::KEY)
            ->delete();
    }
};
