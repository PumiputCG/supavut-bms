<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * 🔴 บังคับให้ทุกเทสต์เขียน/ลบไฟล์ในโฟลเดอร์ทิ้งขว้างเสมอ (บทเรียนจริง 2026-09-10)
     *
     * เทสต์ที่ลบเอกสารเรียก Storage::disk('local')->deleteDirectory('budget/{id}')
     * และ id ของเทสต์เริ่มที่ 1 เหมือนของจริง — เทสต์ที่ลืม Storage::fake จึงไป
     * ลบไฟล์แนบจริงของเจ้าของใน storage/app/private/budget/1 ทิ้งตอนรัน php artisan test
     *
     * กันที่ต้นทางแบบนี้ปลอดภัยกว่าไล่เติม Storage::fake ทีละเทสต์ เพราะเทสต์ที่เขียนใหม่
     * ทีหลังไม่มีทางลืม · เทสต์ที่เรียก Storage::fake เองอยู่แล้วก็ยังทำงานเหมือนเดิม
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.disks.local.root' => storage_path('framework/testing/disks/local'),
            'filesystems.disks.public.root' => storage_path('framework/testing/disks/public'),
        ]);

        // ล้าง instance ที่อาจถูกสร้างไว้ก่อนเปลี่ยน config ไม่งั้นยังชี้ root เดิม
        Storage::forgetDisk(['local', 'public']);
    }
}
