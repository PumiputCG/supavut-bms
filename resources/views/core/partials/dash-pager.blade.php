{{--
  ตัวแบ่งหน้าของแดชบอร์ด — ปุ่มสัญลักษณ์ล้วน (เจ้าของสั่ง 2026-09-14)

  ต้องส่งมา
    $page   หน้าปัจจุบัน
    $pages  จำนวนหน้าทั้งหมด
    $href   callable รับ ['page' => n] แล้วคืน URL (ตัวเดียวกับที่หน้าแดชบอร์ดใช้ คงตัวกรองเดิมไว้ครบ)

  - ใช้คลาส .pg ของธีมกลาง ไม่เขียนสไตล์ปุ่มใหม่
  - ปุ่มที่กดไม่ได้ขึ้นจาง (.is-off) แต่ยังอยู่ที่เดิม ตำแหน่งปุ่มจะได้ไม่กระโดดเวลาเปลี่ยนหน้า
  - มีหน้าเดียวไม่ต้องแสดง (ผู้เรียกเช็ค $pages > 1 เอง)
--}}
@php
  $icons = [
    'first' => 'M12 3.5 7.5 8l4.5 4.5M4.5 3.5v9',
    'prev' => 'M10 3.5 5.5 8 10 12.5',
    'next' => 'M6 3.5 10.5 8 6 12.5',
    'last' => 'M4 3.5 8.5 8 4 12.5M11.5 3.5v9',
  ];
  $buttons = [
    ['first', 1, $page > 1, 'pager.first', 'หน้าแรก'],
    ['prev', $page - 1, $page > 1, 'pager.prev', 'หน้าก่อนหน้า'],
    ['next', $page + 1, $page < $pages, 'pager.next', 'หน้าถัดไป'],
    ['last', $pages, $page < $pages, 'pager.last', 'หน้าสุดท้าย'],
  ];
@endphp
<nav class="bd-pages" aria-label="แบ่งหน้า" data-i18n-aria="pager.nav">
  @foreach($buttons as [$icon, $target, $enabled, $key, $label])
    @if($icon === 'next')<span class="bd-page-now">{{ number_format($page) }} / {{ number_format($pages) }}</span>@endif
    @if($enabled)
      <a class="pg" href="{{ $href(['page' => $target]) }}" title="{{ $label }}" aria-label="{{ $label }}" data-i18n-title="{{ $key }}" data-i18n-aria="{{ $key }}">
        <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="{{ $icons[$icon] }}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </a>
    @else
      <span class="pg is-off" aria-hidden="true">
        <svg viewBox="0 0 16 16" width="14" height="14"><path d="{{ $icons[$icon] }}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </span>
    @endif
  @endforeach
</nav>
