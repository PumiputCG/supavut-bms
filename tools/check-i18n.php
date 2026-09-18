<?php

/*
  ตรวจคีย์แปลของทั้งระบบ — รันก่อนบอกว่า "เสร็จ" ทุกครั้ง

      php tools/check-i18n.php

  ตรวจ 3 อย่าง
    1. คีย์ในบล็อก th กับ en มีครบเท่ากันไหม (กฎโปรเจค: ห้ามเพิ่มคีย์ภาษาเดียว)
    2. คีย์ที่หน้าจอเรียกใช้ มีคำแปลครบไหม (ไม่งั้นผู้ใช้เห็นชื่อคีย์ดิบ)
    3. คีย์ที่มีคำแปลแต่ไม่มีใครใช้ (บอกเฉยๆ ไม่ถือว่าผิด)

  🔴 บทเรียน 2026-09-10: ห้ามสแกนแค่ data-i18n="..." ตรงๆ
     คีย์บางตัวถูกเรียกผ่านตัวแปร เช่น $mark['key'] ใน person-chip.blade.php
     รอบก่อนสคริปต์เก็บกวาดจึงลบ budget.tl.waiting ทิ้ง แล้ว "รอลงนาม" ไม่ยอมแปล
*/

$views = __DIR__.'/../resources/views';
$dict = $views.'/layouts/i18n.blade.php';

$src = file_get_contents($dict);
preg_match_all("/^\s*'([a-zA-Z0-9._-]+)':/m", $src, $m, PREG_OFFSET_CAPTURE);

$enAt = strpos($src, 'en: {');
$th = $en = [];

foreach ($m[1] as $hit) {
    $hit[1] < $enAt ? $th[$hit[0]] = 1 : $en[$hit[0]] = 1;
}

$missing = array_merge(array_keys(array_diff_key($th, $en)), array_keys(array_diff_key($en, $th)));

printf("คีย์ ไทย %d · อังกฤษ %d — %s\n", count($th), count($en),
    $missing ? '❌ ขาด: '.implode(', ', $missing) : '✅ ครบทั้ง 2 ภาษา');

/* ── รวบรวมคีย์ที่หน้าจอใช้จริง ── */
$used = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views));

foreach ($files as $file) {
    $path = $file->getPathname();

    if (! str_ends_with($path, '.blade.php') || str_contains($path, 'i18n.blade.php')) {
        continue;
    }

    $body = file_get_contents($path);

    // แบบที่ 1 — เขียนตรงๆ ใน attribute (data-i18n · data-i18n-placeholder · data-i18n-title …)
    preg_match_all('/data-i18n(?:-[a-z]+)?="([a-zA-Z0-9._-]+)"/', $body, $direct);

    // แบบที่ 2 — 🔴 ส่งผ่านตัวแปร เช่น ['key' => 'budget.tl.waiting']
    preg_match_all("/'key'\s*=>\s*'([a-zA-Z0-9._-]+)'/", $body, $viaVar);

    foreach (array_merge($direct[1], $viaVar[1]) as $key) {
        // คีย์แปลมีจุดเสมอ — กันชื่อ id ของแท็บ/การ์ดที่บังเอิญใช้คีย์ชื่อ 'key' เหมือนกัน
        if (str_contains($key, '.')) {
            $used[$key] = 1;
        }
    }
}

$orphan = array_keys(array_diff_key($used, $th));

echo $orphan
    ? '❌ ใช้แต่ไม่มีคำแปล: '.implode(', ', $orphan)."\n"
    : "✅ ทุกคีย์ที่หน้าจอใช้ (รวมที่เรียกผ่านตัวแปร) มีคำแปลครบ\n";

exit($missing || $orphan ? 1 : 0);
