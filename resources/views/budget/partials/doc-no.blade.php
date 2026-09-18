{{--
  เลขที่เอกสาร — หรือป้าย "ยังไม่ออกเลข" สำหรับร่าง

  🔴 เลขที่ออกตอน "กดส่ง" ไม่ใช่ตอนบันทึกร่าง (เจ้าของสั่ง 2026-09-17)
     ร่างจึงยังไม่มีเลข — ห้ามปล่อยช่องว่างเปล่า อ่านไม่ออกว่าหายหรือยังไม่ได้ออก
     ใช้ชิ้นเดียวทุกหน้า คำจะได้ตรงกัน

  ต้องส่งมา
    $no      เลขที่ (null/ว่างได้)
  ไม่บังคับ
    $href    ถ้ามี เลขที่จะกดได้
--}}
@once
  <style>
    /* ร่างที่ยังไม่มีเลข — ตัวเอียงสีจาง ให้ต่างจากเลขจริงชัดๆ แต่ไม่ดูเป็น error */
    .doc-no.is-pending {
      color: var(--muted); font-style: italic; font-weight: 600;
      font-variant-numeric: normal; white-space: nowrap;
    }
  </style>
@endonce
@if ((string) ($no ?? '') !== '')
  @if (! empty($href))
    <a class="doc-no" href="{{ $href }}">{{ $no }}</a>
  @else
    <span class="doc-no">{{ $no }}</span>
  @endif
@else
  <span class="doc-no is-pending" title="เลขที่จะออกให้เมื่อกดส่งเอกสาร"
        data-i18n-title="budget.noNumberHint"><span data-i18n="budget.noNumber">ยังไม่ออกเลข</span></span>
@endif
