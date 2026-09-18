<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
  🐛 บั๊กจริง 2026-09-10 (เจ้าของแจ้ง): กดแจ้งเตือนแล้ว "หาเอกสารไม่เจอ"

  สาเหตุมี 2 อย่าง
    1. คอลัมน์ url เก็บ "ที่อยู่เต็มพร้อมชื่อโฮสต์" ที่สร้างไว้ตอนส่งแจ้งเตือน
       เช่น http://127.0.0.1:8000/budget/doc/3
       เปิดเว็บจากที่อยู่อื่น (เช่น Apache ที่ /SBMS/public) ลิงก์จึงพาไปคนละเครื่อง
       เป็นบทเรียนเดียวกับที่เคยเจอกับ Storage::url() ที่ยึด APP_URL

    2. แจ้งเตือนของเอกสารที่ถูกลบไปแล้วยังค้างอยู่ กดแล้วไปเจอ 404 แน่นอน

  วิธีแก้: เก็บ "ชื่อ route + พารามิเตอร์" แทนที่อยู่เต็ม แล้วให้หน้าจอสร้างลิงก์เอง
           ตอนแสดงผล ลิงก์จึงเดินตามที่อยู่จริงที่ผู้ใช้เปิดอยู่เสมอ
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // ชื่อ route ปลายทาง เช่น budget.doc.show — โมดูลอื่นใช้ route ของตัวเองได้
            $table->string('route_name', 100)->nullable()->after('url');
            $table->string('route_param', 60)->nullable()->after('route_name');
        });

        /* ── แปลงของเดิม: แกะเลขเอกสารออกจากที่อยู่เต็ม ── */
        $moved = 0;

        DB::table('notifications')->whereNotNull('url')->orderBy('id')
            ->each(function ($row) use (&$moved) {
                if (! preg_match('~/budget/doc/(\d+)~', (string) $row->url, $m)) {
                    return;
                }

                DB::table('notifications')->where('id', $row->id)->update([
                    'route_name' => 'budget.doc.show',
                    'route_param' => $m[1],
                    'url' => null,
                ]);

                $moved++;
            });

        /* ── แจ้งเตือนที่ชี้ไปยังเอกสารที่ถูกลบไปแล้ว — สำรองก่อนแล้วค่อยลบ ── */

        // เทสต์รันเฉพาะ migration ของ module ที่ใช้ ตารางของโมดูลงบอาจยังไม่มี
        if (! Schema::hasTable('budget_invests')) {
            return;
        }

        $live = DB::table('budget_invests')->pluck('id')->map(fn ($id) => (string) $id)->all();

        $dead = DB::table('notifications')
            ->where('route_name', 'budget.doc.show')
            ->whereNotNull('route_param')
            ->whereNotIn('route_param', $live)
            ->get();

        if ($dead->isNotEmpty()) {
            // 🔴 สำรองเป็น JSON ก่อนเสมอ ตามกฎโปรเจค (ห้ามลบทิ้งเฉยๆ)
            $dir = storage_path('app/backup');

            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            file_put_contents(
                $dir.'/notifications-orphan-'.date('Ymd-His').'.json',
                json_encode($dead, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            );

            DB::table('notifications')->whereIn('id', $dead->pluck('id'))->delete();
        }

        info(sprintf('notifications: แปลงลิงก์ %d ใบ · ลบใบที่เอกสารหายแล้ว %d ใบ', $moved, $dead->count()));
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['route_name', 'route_param']);
        });
    }
};
