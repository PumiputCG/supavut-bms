{{--
  ตัวสลับภาษา — ต้องมีทุกหน้า (กฎใน CLAUDE.md)
  ใช้ธงชาติเป็นตัวบอกภาษา ตามที่เจ้าของสั่งเมื่อ 2026-09-02
  ภาษาที่เลือกถูกจำไว้ใน localStorage จึงไม่รีเซ็ตเวลาเปลี่ยนหน้า

  ใส่ class เพิ่มได้ผ่าน $class เช่น @include('layouts.partials.lang-switch', ['class' => 'on-dark'])
--}}
<div style="position:relative">
  <button type="button" class="lang {{ $class ?? '' }}" data-lang-toggle
          aria-haspopup="true" aria-label="ภาษา" data-i18n-aria="common.lang">
    {{-- ธงของภาษาที่เลือกอยู่ — JS สลับ src ให้ตอนเปลี่ยนภาษา --}}
    <img class="flag" data-lang-flag src="{{ asset('img/flag-th.webp') }}" alt="" width="22" height="15">
    <span data-lang-label>ไทย</span>
    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="m6 9 6 6 6-6"></path>
    </svg>
  </button>

  <div class="lang-menu" data-lang-menu hidden>
    <button type="button" data-lang-set="th" aria-current="true">
      <img class="flag" src="{{ asset('img/flag-th.webp') }}" alt="" width="22" height="15">
      <span data-i18n="common.langTh">ไทย</span>
      <span class="code">TH</span>
    </button>
    <button type="button" data-lang-set="en" aria-current="false">
      <img class="flag" src="{{ asset('img/flag-en.png') }}" alt="" width="22" height="15">
      <span data-i18n="common.langEn">อังกฤษ</span>
      <span class="code">EN</span>
    </button>
  </div>
</div>
