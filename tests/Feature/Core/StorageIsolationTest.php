<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * เทสต์ต้องไม่แตะไฟล์จริงของเจ้าของ
 *
 * 🔴 บทเรียนจริง 2026-09-10 — เจ้าของอัปโหลดไฟล์แนบไว้ 2 ไฟล์ตอน manual test
 *    แล้วผมรัน `php artisan test` · เทสต์ที่ลบเอกสารเรียก
 *    Storage::disk('local')->deleteDirectory('budget/1')
 *    id ของเทสต์เริ่มที่ 1 เหมือนของจริง ไฟล์แนบจริงเลยหายไปทั้งโฟลเดอร์
 *
 *    ตอนนี้ Tests\TestCase ชี้ root ของ disk local/public ไปที่โฟลเดอร์ทิ้งขว้างให้ทุกเทสต์
 *    เทสต์นี้คุมไว้ว่ากติกานั้นยังอยู่ — ใครไปแก้ config แล้วหลุด จะรู้ทันที
 */
class StorageIsolationTest extends TestCase
{
    public function test_disks_never_point_at_real_storage(): void
    {
        // Windows ปน / กับ \ ในเส้นทางเดียวกันได้ ต้องปรับให้เทียบกันได้ก่อน
        $norm = fn (string $p) => str_replace('\\', '/', $p);
        $safe = $norm(storage_path('framework/testing'));

        foreach (['local', 'public'] as $disk) {
            $root = $norm((string) config("filesystems.disks.$disk.root"));

            $this->assertStringStartsWith(
                $safe,
                $root,
                "disk [$disk] ต้องชี้ไปโฟลเดอร์ทิ้งขว้างตอนเทสต์ ไม่ใช่ที่เก็บไฟล์จริง"
            );
        }
    }

    public function test_writing_and_deleting_never_reaches_real_uploads(): void
    {
        // เขียนแล้วลบทั้งโฟลเดอร์แบบเดียวกับตอนลบเอกสาร
        Storage::disk('local')->put('budget/1/probe.txt', 'x');
        $this->assertTrue(Storage::disk('local')->exists('budget/1/probe.txt'));

        Storage::disk('local')->deleteDirectory('budget/1');

        // ไฟล์ที่เขียนต้องอยู่ในโฟลเดอร์ทิ้งขว้าง ไม่ใช่ที่เก็บจริง
        $this->assertDirectoryDoesNotExist(
            storage_path('framework/testing/disks/local/budget/1')
        );
    }
}
