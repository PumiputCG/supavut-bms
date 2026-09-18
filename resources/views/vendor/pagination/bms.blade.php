{{--
  ตัวแบ่งหน้าของระบบ (เจ้าของแจ้งว่าของเดิมพัง 2026-09-04)

  🔴 ของเดิมใช้ view มาตรฐานของ Laravel ซึ่งเขียนด้วยคลาส Tailwind
     โปรเจคนี้ไม่ได้ใช้ Tailwind ปุ่มจึงกลายเป็นข้อความเปล่าๆ ไม่มีกรอบ
     และเป็นภาษาอังกฤษล้วน ("Showing 1 to 50 of 1149 results") ผิดกฎ 2 ภาษาของโปรเจค

  ตั้งเป็นค่าเริ่มต้นที่ AppServiceProvider แล้ว — ทุกหน้าที่แบ่งหน้าได้ตัวนี้เหมือนกันหมด
  สไตล์อยู่ที่ layouts/theme.blade.php (.pager) เพื่อให้แก้ที่เดียวเปลี่ยนทั้งระบบ
--}}
@if ($paginator->hasPages())
  <nav class="pager" role="navigation"
       aria-label="แบ่งหน้า" data-i18n-aria="pager.nav">

    {{-- บอกว่ากำลังดูรายการที่เท่าไหร่จากทั้งหมดเท่าไหร่ --}}
    <span class="pager-info">
      <b>{{ number_format($paginator->firstItem() ?? 0) }}</b>–<b>{{ number_format($paginator->lastItem() ?? 0) }}</b>
      <span data-i18n="pager.of">จาก</span>
      <b>{{ number_format($paginator->total()) }}</b>
      <span data-i18n="pager.items">รายการ</span>
    </span>

    <span class="pager-links">
      @if ($paginator->onFirstPage())
        <span class="pg is-off" aria-hidden="true">&lsaquo;</span>
      @else
        <a class="pg" href="{{ $paginator->previousPageUrl() }}" rel="prev"
           aria-label="หน้าก่อนหน้า" data-i18n-aria="pager.prev">&lsaquo;</a>
      @endif

      @foreach ($elements as $element)
        {{-- ช่วงที่ถูกย่อ เช่น 1 2 … 8 9 10 --}}
        @if (is_string($element))
          <span class="pg is-gap" aria-hidden="true">{{ $element }}</span>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <span class="pg is-on" aria-current="page">{{ $page }}</span>
            @else
              <a class="pg" href="{{ $url }}">{{ $page }}</a>
            @endif
          @endforeach
        @endif
      @endforeach

      @if ($paginator->hasMorePages())
        <a class="pg" href="{{ $paginator->nextPageUrl() }}" rel="next"
           aria-label="หน้าถัดไป" data-i18n-aria="pager.next">&rsaquo;</a>
      @else
        <span class="pg is-off" aria-hidden="true">&rsaquo;</span>
      @endif
    </span>
  </nav>
@endif
