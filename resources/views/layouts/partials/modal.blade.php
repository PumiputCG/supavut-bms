{{--
  หน้าต่างซ้อน (modal) — หน้าตา + การเปิด/ปิด ใช้ร่วมทั้งระบบ

  🐛 บั๊กจริง 2026-09-16 (เจ้าของแจ้ง "กดที่สัญลักษณ์เปรียบเทียบ ไม่เห็นมีอะไรเกิดขึ้นเลย")

     ของเดิมโค้ดชุดนี้ฝังอยู่ใน `access/partials/picker-assets.blade.php`
     ซึ่งเป็นชิ้นส่วนของ **โมดูลสิทธิ์** แต่ดันเป็นเจ้าของ modal ของทั้งระบบ
     หน้าไหนอยากมี modal ต้องไป include ชิ้นส่วนของโมดูลอื่น — ผิดชั้นและมองไม่ออก
     แดชบอร์ดจึงลืม include แล้ว **ปุ่มเปรียบเทียบกดแล้วไม่มีอะไรเกิดขึ้นเลย**
     (ไม่มี error ไม่มีอะไรเตือน เพราะตัวรับการกดไม่ได้ถูกโหลดมาตั้งแต่แรก)

     ยกออกมาไว้ตรงกลางแล้ว · `picker-assets` include ตัวนี้ต่อ หน้าเดิมทั้ง 7 หน้าจึงไม่ต้องแก้

  วิธีใช้:
    @include('layouts.partials.modal')

    <button data-modal-open="ชื่อ">เปิด</button>

    <div class="modal-wrap" data-modal="ชื่อ" hidden role="dialog" aria-modal="true">
      <div class="modal">
        <div class="modal-head">…<button data-modal-close>✕</button></div>
        <div class="modal-body">…</div>
        (🔴 ยินยอมอยู่ซ้าย · ยกเลิกอยู่ขวา — กติกาของโปรเจค)
        <div class="modal-foot">…</div>
      </div>
    </div>

  🔴 ห้ามเขียนคอมเมนต์ Blade ซ้อนในคอมเมนต์นี้ (บั๊กจริง 2026-09-17)
     เคยมีบรรทัดตัวอย่างที่ครอบด้วยเครื่องหมายคอมเมนต์ซ้อนอยู่ คอมเมนต์เลยจบก่อนเวลา
     แล้ว `<div class="modal-foot">…</div></div></div>` หลุดออกไปวาดบน **ทุกหน้า**
     (เห็นเป็น "…" ใต้เนื้อหา และปิดแท็กของหน้าก่อนเวลา)

  🔴 คลาสของกรอบนอกต้องเป็น `.modal-wrap` เท่านั้น
     ตัวปิด (กดพื้นหลัง · Esc · ปุ่มปิด) ค้นหาด้วย `.modal-wrap` ทั้งหมด
     ตั้งชื่อคลาสอื่นแล้วหน้าต่างจะเปิดได้แต่ปิดไม่ได้
--}}
@once
  <style>
  /* ── หน้าต่างซ้อน (modal) ───────────────────────────────── */
  .modal-wrap {
    position: fixed; inset: 0; z-index: var(--z-drawer);
    display: grid; place-items: center; padding: 24px;
    background: rgb(8 18 40 / 46%);
  }
  .modal-wrap[hidden] { display: none; }

  /*
    🔴 min-width/min-height: 0 ต้องมีเสมอ (เจ้าของแจ้ง 2026-09-11)
       .modal เป็นลูกของ grid ซึ่งมี "ขนาดต่ำสุดอัตโนมัติ" = ขนาดของเนื้อใน
       เนื้อในที่กว้างเป็นพิกเซลตายตัว (เช่น SVG ของกราฟ) จึงดันหน้าต่างให้กว้างเกิน
       ค่าที่ตั้งไว้ได้ — **ซูมเข้าแล้วหน้าต่างบานออกเรื่อยๆ** เพราะ 100% ของจอหดลง
       แต่ความกว้างพิกเซลของกราฟเท่าเดิม · ตั้งเป็น 0 แล้ว width/max-height คุมได้จริง
       ส่วนที่ล้นให้เลื่อนดูในกรอบของตัวเอง (.modal-body และ .chart-draw.is-big)
  */
  .modal {
    display: flex; flex-direction: column;
    width: min(760px, 100%);
    max-height: min(84vh, 720px);
    max-height: min(84dvh, 720px);
    min-width: 0; min-height: 0;
    border-radius: var(--radius-lg); background: var(--surface);
    box-shadow: var(--shadow-lg);
    animation: modal-in .16s var(--ease);
  }
  @keyframes modal-in {
    from { opacity: 0; transform: translateY(8px) scale(.99); }
    to   { opacity: 1; transform: none; }
  }
  @media (prefers-reduced-motion: reduce) { .modal { animation: none; } }

  /*
    🔴 มือถือ: ขอบ 24px รอบหน้าต่างกินความกว้างไปเกือบ 1 ใน 6 ของจอ
       และความสูงต้องคิดจาก dvh ไม่งั้นท้ายหน้าต่างไหลลงไปใต้ขอบจอที่มองไม่เห็น
  */
  @media (max-width: 560px) {
    .modal-wrap { padding: 10px; }
    .modal { width: 100%; max-height: calc(100dvh - 20px); }
  }

  .modal-head {
    display: flex; align-items: center; gap: 10px; flex: 0 0 auto;
    padding: 13px 16px; border-bottom: 1px solid var(--line-soft);
  }
  .modal-title { flex: 1; min-width: 0; color: var(--navy-800); font-weight: 700; }
  .modal-x {
    display: grid; place-items: center; width: 28px; height: 28px; flex: 0 0 auto;
    border: 0; border-radius: var(--radius-sm); background: transparent;
    color: var(--muted); cursor: pointer;
  }
  .modal-x:hover { background: var(--surface-2); color: var(--ink); }

  .modal-body { flex: 1 1 auto; overflow: auto; padding: 16px; display: grid; gap: 14px; }
  .modal-foot {
    display: flex; align-items: center; gap: 10px; flex: 0 0 auto; flex-wrap: wrap;
    padding: 12px 16px; border-top: 1px solid var(--line-soft);
  }
  .modal-foot .spacer { flex: 1; }
  </style>

  <script>
    'use strict';

    // ═══ หน้าต่างซ้อน (modal) ═══════════════════════════════════
    (function () {
      var opener = null;
  
      function open(id) {
        var wrap = document.querySelector('[data-modal="' + id + '"]');
        if (!wrap) { return; }
  
        wrap.hidden = false;
        document.body.style.overflow = 'hidden';     // กันหน้าเลื่อนอยู่ข้างหลัง
  
        var focusMe = wrap.querySelector('[data-picker-input]') || wrap.querySelector('[data-modal-close]');
        if (focusMe) { focusMe.focus(); }
      }
  
      function close(wrap) {
        wrap.hidden = true;
        if (!document.querySelector('.modal-wrap:not([hidden])')) { document.body.style.overflow = ''; }
        if (opener && document.contains(opener)) { opener.focus(); }
        opener = null;
      }
  
      document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-modal-open]');
        if (trigger) {
          // ปุ่มที่มีทั้งปิดและเปิด = ย้ายจากหน้าต่างหนึ่งไปอีกหน้าต่าง (เช่น รายละเอียด -> แก้ไข)
          var from = trigger.hasAttribute('data-modal-close') ? trigger.closest('.modal-wrap') : null;
          if (from) { from.hidden = true; }
  
          opener = trigger;
          open(trigger.getAttribute('data-modal-open'));
  
          return;
        }
  
        var closer = e.target.closest('[data-modal-close]');
        if (closer) {
          close(closer.closest('.modal-wrap'));
  
          return;
        }
  
        /*
          กดพื้นหลังนอกกล่องถึงจะปิด
          🔴 กดเลือกคนในผลค้นหาต้องไม่ปิดหน้าต่าง (เจ้าของสั่ง 2026-09-03)
             เช็คว่าคลิกโดนตัว .modal-wrap ตรงๆ เท่านั้น ไม่ใช่ของที่อยู่ข้างใน
        */
        if (e.target.classList && e.target.classList.contains('modal-wrap')) {
          close(e.target);
        }
      });
  
      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
  
        /*
          🔴 กำลังดูรูปขยายอยู่ ให้ Esc ปิดแค่รูป อย่าปิดหน้าต่างไปด้วย (บั๊กจริง 2026-09-03)
             ไม่งั้นกดดูรูปพนักงานแล้วกด Esc รายชื่อที่เลือกไว้หายหมด
        */
        if (document.querySelector('[data-zoom-overlay]:not([hidden])')) { return; }
  
        var top = document.querySelector('.modal-wrap:not([hidden])');
        if (top) { close(top); }
      });
    })();
  </script>
@endonce
