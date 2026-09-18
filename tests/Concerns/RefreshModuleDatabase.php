<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * RefreshDatabase ที่มองเห็น migration ของทุก module
 *
 * โปรเจคนี้แยก migration เป็นโฟลเดอร์ต่อระบบย่อย (core, access, ...) ตามกฎใน CLAUDE.md
 * แต่ Laravel มองเห็นแค่ database/migrations ชั้นบนสุด เทสต์เลยจะไม่มีตารางของ module ให้ใช้
 *
 * 🔴 จงใจแก้เฉพาะฝั่งเทสต์ ไม่ไปลงทะเบียน path เหล่านี้กับตัวแอป
 *    เพราะกฎห้ามรัน `php artisan migrate` รวดเดียวทั้งโปรเจคบนเซิร์ฟเวอร์
 *    (บทเรียนจาก Insight: migration ของ module อื่นที่ค้างอยู่จะไปชนตารางที่มีอยู่แล้วจนพัง)
 *
 * ⚠️ ต้องประกาศ migrateFreshUsing() ใน trait ตัวนี้ ไม่ใช่ใน Tests\TestCase
 *    เพราะเมธอดของ trait ชนะเมธอดที่สืบทอดมาจากคลาสแม่เสมอ — เขียนไว้ที่แม่จะไม่ถูกเรียก
 */
trait RefreshModuleDatabase
{
    use RefreshDatabase;

    protected function migrateFreshUsing()
    {
        return [
            '--drop-views' => $this->shouldDropViews(),
            '--drop-types' => $this->shouldDropTypes(),
            '--path' => $this->moduleMigrationPaths(),
        ];
    }

    /**
     * database/migrations + ทุกโฟลเดอร์ย่อยในนั้น
     *
     * ใช้ glob เพื่อให้ module ใหม่ที่เพิ่มทีหลังถูกรันในเทสต์เองโดยไม่ต้องมาแก้ไฟล์นี้
     *
     * @return array<int,string>
     */
    private function moduleMigrationPaths(): array
    {
        $base = 'database/migrations';
        $paths = [$base];

        foreach (glob(base_path($base).'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $paths[] = $base.'/'.basename($dir);
        }

        return $paths;
    }
}
