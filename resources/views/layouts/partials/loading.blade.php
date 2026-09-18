{{--
  หน้าจอ "กำลังโหลด" คลุมทั้งหน้า (เจ้าของสั่ง 2026-09-16)

  วงแหวนหมุน + ตัวหนังสือ 3 ภาษา + เบลอพื้นหลังเล็กน้อย

  🔴 ขึ้นข้อความ "ภาษาเดียว" ตามที่ผู้ใช้เลือก — เลือกด้วย CSS จาก data-lang ที่ <html>
     ไม่ใช้ data-i18n เพราะจอนี้ขึ้นตอนกำลังเปลี่ยนหน้า ซึ่งตัวแปลภาษาอาจยังไม่ทำงาน
     แล้วผู้ใช้จะเห็นชื่อคีย์ดิบแวบหนึ่ง · CSS ทำงานทันทีที่วาดหน้า ไม่ต้องรอ JS

  🔴 ไทย/อังกฤษ เท่านั้น ตามกฎโปรเจค (SBMS กำหนดไว้ 2 ภาษา)
     รอบแรกเคยใส่พม่าไว้ด้วย แต่เจ้าของยืนยัน 2026-09-16 ว่าระบบนี้ไม่มีพม่า — ถอดออกแล้ว

  วิธีใช้จากที่อื่น:  window.BMS.loading.show()  /  window.BMS.loading.hide()
--}}
@once
  <style>
    .loading-veil {
      position: fixed;
      inset: 0;
      z-index: 9000;
      display: grid;
      place-items: center;
      gap: clamp(18px, 3.5vmin, 34px);
      align-content: center;
      /* กันตัวหนังสือชนขอบจอตอนมือถือ */
      padding: 24px 16px;
      background: rgb(255 255 255 / 62%);
      /* เบลอ "เล็กน้อย" พอให้รู้ว่าพื้นหลังยังอยู่ ไม่ใช่บังจนมืด */
      backdrop-filter: blur(3px);
      -webkit-backdrop-filter: blur(3px);
      opacity: 0;
      visibility: hidden;
      transition: opacity .16s ease, visibility .16s ease;
    }

    .loading-veil[data-on] { opacity: 1; visibility: visible; }

    /*
      วงแหวนหมุน — เส้นจางทั้งวง แล้วเน้นสีเฉพาะด้านบนให้เห็นว่าหมุน
      🔴 เจ้าของสั่งให้ใหญ่ชัดๆ (2026-09-16) · ใช้ clamp เพื่อให้มือถือไม่ล้นจอ
    */
    .loading-ring {
      width: clamp(64px, 13vmin, 96px);
      height: clamp(64px, 13vmin, 96px);
      border: clamp(5px, 1vmin, 8px) solid var(--line);
      border-top-color: var(--navy-800);
      border-radius: 50%;
      animation: loading-spin .7s linear infinite;
    }

    @keyframes loading-spin { to { transform: rotate(360deg); } }

    .loading-words {
      text-align: center;
      color: var(--navy-800);
      font-size: clamp(var(--fs-lg), 3vmin, 26px);
      font-weight: 700;
      line-height: 1.3;
    }

    /*
      🔴 ขึ้นข้อความ "ภาษาเดียว" ตามที่ผู้ใช้เลือก (เจ้าของสั่ง 2026-09-16)
         ใช้ CSS อ่าน data-lang ที่ <html> ไม่ใช้ data-i18n เพราะจอนี้ขึ้นตอนกำลังเปลี่ยนหน้า
         ซึ่งตัวแปลภาษาอาจยังไม่ทำงาน — ถ้าใช้ data-i18n ผู้ใช้จะเห็นชื่อคีย์ดิบแวบหนึ่ง
         CSS ทำงานทันทีที่วาดหน้า ไม่ต้องรอ JS
    */
    .loading-words > i { display: none; font-style: normal; }
    .loading-words > .l-th { display: block; }
    :root[data-lang='en'] .loading-words > .l-th { display: none; }
    :root[data-lang='en'] .loading-words > .l-en { display: block; }

    /* 🔴 เคารพการตั้งค่าของเครื่องที่ขอให้ลดอนิเมชัน — หยุดหมุน แต่ยังบอกว่ากำลังโหลด */
    @media (prefers-reduced-motion: reduce) {
      .loading-ring { animation: none; }
      .loading-veil { transition: none; }
    }
  </style>

  <div class="loading-veil" data-loading-veil role="status" aria-live="polite" aria-hidden="true">
    <div class="loading-ring"></div>
    <div class="loading-words">
      <i class="l-th">กำลังโหลด</i>
      <i class="l-en">Loading</i>
    </div>
  </div>

  <script>
    'use strict';
    (function () {
      var veil = document.querySelector('[data-loading-veil]');
      if (!veil) { return; }

      var timer = null;
      var giveUp = null;

      /*
        🔴 ตาข่ายกันจอค้าง (เจ้าของแจ้ง 2026-09-16 "ดาวน์โหลด PDF เหมือนมันจะค้างไปเลย")

           จอนี้ปิดตัวเองด้วยเหตุการณ์ pageshow ซึ่งจะเกิดก็ต่อเมื่อ "เปลี่ยนหน้าจริง"
           แต่มีลิงก์ที่กดแล้ว **ไม่เปลี่ยนหน้า** เช่นลิงก์ที่เซิร์ฟเวอร์ตอบเป็นไฟล์
           (Content-Disposition: attachment) เบราว์เซอร์แค่เซฟไฟล์แล้วอยู่หน้าเดิม
           pageshow จึงไม่มีวันมา จอเลยค้างอยู่อย่างนั้นตลอดไป

           ทางแก้ที่ถูกคือใส่ download ที่ลิงก์นั้น (ตัวจับลิงก์ข้าม a[download] ให้อยู่แล้ว)
           แต่กันไว้อีกชั้นด้วย เพราะลิงก์แบบนี้เพิ่มใหม่เมื่อไหร่ก็ได้
           และคนที่เจอจะแยกไม่ออกเลยว่า "เว็บค้าง" หรือ "แค่จอโหลดไม่ยอมหาย"
      */
      var MAX_WAIT = 30000;

      function show() {
        /*
          🔴 หน่วง 120ms ก่อนค่อยโชว์ — หน้าที่เปิดเร็วจะได้ไม่เห็นจอกะพริบแวบเดียว
             ซึ่งกวนตากว่าไม่มีอะไรเลย
        */
        if (timer) { return; }
        timer = window.setTimeout(function () {
          veil.setAttribute('data-on', '');
          veil.setAttribute('aria-hidden', 'false');
        }, 120);

        if (giveUp) { window.clearTimeout(giveUp); }
        giveUp = window.setTimeout(hide, MAX_WAIT);
      }

      function hide() {
        if (timer) { window.clearTimeout(timer); timer = null; }
        if (giveUp) { window.clearTimeout(giveUp); giveUp = null; }
        veil.removeAttribute('data-on');
        veil.setAttribute('aria-hidden', 'true');
      }

      window.BMS = window.BMS || {};
      window.BMS.loading = { show: show, hide: hide };

      /* ── กดลิงก์ที่พาออกจากหน้านี้ ── */
      document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('a[href]') : null;
        if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        if (link.target && link.target !== '_self') { return; }
        if (link.hasAttribute('download')) { return; }

        var href = link.getAttribute('href') || '';
        // ลิงก์ที่ไม่ได้พาไปไหน — สมอในหน้า · ปุ่มเปิดหน้าต่าง · เบอร์โทร/อีเมล
        if (href === '' || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) { return; }
        if (link.protocol && link.protocol !== 'http:' && link.protocol !== 'https:') { return; }
        if (link.origin && link.origin !== window.location.origin) { return; }
        // ลิงก์ที่เปลี่ยนแค่ส่วน #... ของหน้าเดิม เบราว์เซอร์ไม่ได้โหลดหน้าใหม่
        if (link.pathname === window.location.pathname && link.search === window.location.search && link.hash) { return; }

        show();
      });

      /* ── ส่งฟอร์ม ── */
      document.addEventListener('submit', function (e) {
        if (!e.defaultPrevented) { show(); }
      });

      /*
        🔴 กดปุ่มย้อนกลับแล้วเบราว์เซอร์คืนหน้าจากแคช ต้องเอาจอโหลดออก
           ไม่งั้นผู้ใช้จะเจอหน้าค้างอยู่ที่จอ "กำลังโหลด" ตลอดไป
      */
      window.addEventListener('pageshow', hide);
    }());
  </script>
@endonce
