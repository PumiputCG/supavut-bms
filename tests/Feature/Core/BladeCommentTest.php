<?php

namespace Tests\Feature\Core;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * 🔴 ห้ามมีคอมเมนต์ Blade ซ้อนกัน (บั๊กจริง 2 ครั้ง)
 *
 * คอมเมนต์ Blade จบที่ "--}}" ตัวแรกที่เจอ ไม่ได้นับคู่
 * ถ้าข้างในมีคอมเมนต์ซ้อนอยู่ ข้อความที่เหลือจะหลุดออกไปวาดบนหน้า
 *
 *   2026-09-04  partial include ตัวเองจนหน่วยความจำเต็ม
 *   2026-09-17  modal.blade.php วาด `<div class="modal-foot">…</div></div></div>` บนทุกหน้า
 *               เห็นเป็น "…" ใต้เนื้อหา และปิดแท็กของหน้าก่อนเวลา — ไม่มี error ใดๆ ฟ้อง
 *
 * ตรวจทั้งโฟลเดอร์ view เพราะอาการไม่ฟ้องที่ไหนเลย ต้องกันที่ต้นทาง
 */
class BladeCommentTest extends TestCase
{
    public function test_no_view_has_a_nested_blade_comment(): void
    {
        $found = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            preg_match_all('/\{\{--|--\}\}/', $source, $tokens, PREG_OFFSET_CAPTURE);

            $open = false;
            foreach ($tokens[0] as [$token, $offset]) {
                if ($token === '{{--') {
                    if ($open) {
                        $found[] = str_replace(resource_path('views'), '', $file->getPathname())
                            .':'.(substr_count(substr($source, 0, $offset), "\n") + 1);
                    }
                    $open = true;
                } else {
                    $open = false;
                }
            }
        }

        $this->assertSame([], $found, 'คอมเมนต์ Blade ซ้อนกันที่: '.implode(', ', $found));
    }
}
