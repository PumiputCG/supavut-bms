<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
  ยุบสิทธิ์ของโมดูลงบประมาณ (เจ้าของสั่ง 2026-09-09)

  1) "รับทราบ" (fn.budget.acknowledge) + "อนุมัติ Invest" (fn.budget.approve)
     → รวมเป็น fn.budget.inbox หัวข้อเดียว
     เหตุผล: ผู้ขอเลือกผู้อนุมัติเองตอนส่งเอกสารแล้ว ไม่ต้องตั้งรายชื่อผู้ลงนามล่วงหน้าอีก

  2) ถอดสิทธิ์ย่อย "ปรับสถานะงบประมาณ" (fn.budget.approved.status) ออกทั้งระบบ
     ใครเห็นหน้า "Budget ที่อนุมัติแล้ว" ได้ ก็ปรับสถานะได้เลย

  🔴 ย้ายแถวสิทธิ์ของคนเดิมให้ด้วย ไม่ใช่แค่เปลี่ยนโค้ด
     ไม่งั้นคนที่เคยมีสิทธิ์จะเข้าหน้าไม่ได้ทันทีหลัง deploy
*/
return new class extends Migration
{
    private const OLD_KEYS = ['fn.budget.acknowledge', 'fn.budget.approve'];

    private const NEW_KEY = 'fn.budget.inbox';

    private const DEAD_KEY = 'fn.budget.approved.status';

    public function up(): void
    {
        if (! Schema::hasTable('access_function_users')) {
            return;
        }

        // คนที่มีสิทธิ์เดิมอย่างน้อย 1 ใน 2 หัวข้อ → ได้สิทธิ์หัวข้อใหม่
        $codes = DB::table('access_function_users')
            ->where('module_id', 'budget')
            ->whereIn('function_key', self::OLD_KEYS)
            ->pluck('employee_code')
            ->unique();

        foreach ($codes as $code) {
            // มีอยู่แล้วก็ข้าม — ตารางนี้มี unique (module, function, employee)
            $exists = DB::table('access_function_users')
                ->where('module_id', 'budget')
                ->where('function_key', self::NEW_KEY)
                ->where('employee_code', $code)
                ->exists();

            if (! $exists) {
                DB::table('access_function_users')->insert([
                    'module_id' => 'budget',
                    'function_key' => self::NEW_KEY,
                    'employee_code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('access_function_users')
            ->where('module_id', 'budget')
            ->whereIn('function_key', [...self::OLD_KEYS, self::DEAD_KEY])
            ->delete();
    }

    public function down(): void
    {
        // คืนไม่ได้ — รวมแล้วแยกกลับไม่รู้ว่าใครเคยอยู่หัวข้อไหน
    }
};
