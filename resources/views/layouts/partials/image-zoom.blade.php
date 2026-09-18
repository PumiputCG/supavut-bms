{{--
  ตัวขยายรูป (image lightbox) — ใช้ร่วมทั้งระบบ
  include ครั้งเดียวที่ layouts/app.blade.php แล้วทุกหน้าใช้ได้เลย

  วิธีใช้: ใส่ data-zoomable ที่ <img> หรือปุ่มที่ครอบรูป
    <img src="..." data-zoomable>
    <button data-zoomable data-zoom-src="รูปใหญ่.jpg" data-zoom-alt="ชื่อคน"><img src="รูปเล็ก.jpg"></button>

  🔴 กฎของโปรเจค: รูปโปรไฟล์ทุกที่ต้องกดขยายได้ และหมุนล้อเมาส์เพื่อซูมได้
     (ดู DESIGN_SYSTEM.md หัวข้อ "รูปภาพ")
--}}
<style>
  .zoom-overlay {
    position: fixed; inset: 0; z-index: var(--z-modal);
    display: grid; place-items: center;
    background: rgb(8 18 40 / 82%);
    /* ผู้ใช้จะหมุนล้อเพื่อซูม ไม่ใช่เลื่อนหน้า จึงกันไม่ให้เบราว์เซอร์แย่ง gesture ไป */
    overscroll-behavior: contain; touch-action: none;
  }
  .zoom-overlay[hidden] { display: none; }

  .zoom-stage {
    position: relative; display: grid; place-items: center;
    width: 100%; height: 100%; overflow: hidden;
  }
  .zoom-img {
    max-width: min(88vw, 900px); max-height: 86vh; max-height: 86dvh;
    border-radius: var(--radius);
    background: var(--surface); box-shadow: var(--shadow-lg);
    transform-origin: center center;
    cursor: grab; user-select: none; -webkit-user-drag: none;
    will-change: transform;
  }
  .zoom-img.is-panning { cursor: grabbing; }

  .zoom-bar {
    position: absolute; top: 16px; right: 16px;
    display: flex; align-items: center; gap: 6px;
  }
  .zoom-btn {
    display: grid; place-items: center; width: 34px; height: 34px;
    border: 1px solid rgb(255 255 255 / 22%); border-radius: var(--radius-sm);
    background: rgb(255 255 255 / 10%); color: #fff; cursor: pointer;
  }
  .zoom-btn:hover { background: rgb(255 255 255 / 20%); }

  .zoom-info {
    position: absolute; bottom: 18px; left: 50%; transform: translateX(-50%);
    display: flex; align-items: center; gap: 10px;
    padding: 6px 12px; border-radius: var(--radius-sm);
    background: rgb(0 0 0 / 40%); color: #fff; font-size: var(--fs-sm);
  }
  .zoom-info b { font-weight: 600; }
  .zoom-info span { color: rgb(255 255 255 / 70%); font-size: var(--fs-xs); }

  [data-zoomable] { cursor: zoom-in; }
</style>

<div class="zoom-overlay" data-zoom-overlay hidden role="dialog" aria-modal="true">
  <div class="zoom-stage" data-zoom-stage>
    <img class="zoom-img" data-zoom-img src="" alt="">
  </div>

  <div class="zoom-bar">
    <button type="button" class="zoom-btn" data-zoom-out aria-label="ย่อ" data-i18n-aria="zoom.out">
      <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"></path></svg>
    </button>
    <button type="button" class="zoom-btn" data-zoom-in aria-label="ขยาย" data-i18n-aria="zoom.in">
      <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
    </button>
    <button type="button" class="zoom-btn" data-zoom-reset aria-label="ขนาดเดิม" data-i18n-aria="zoom.reset">
      <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M20 12a8 8 0 1 1-2.6-5.9"></path><path d="M20 4v5h-5"></path>
      </svg>
    </button>
    <button type="button" class="zoom-btn" data-zoom-close aria-label="ปิด" data-i18n-aria="common.close">
      <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"></path></svg>
    </button>
  </div>

  <p class="zoom-info">
    <b data-zoom-caption></b>
    <span data-i18n="zoom.hint">หมุนล้อเมาส์เพื่อซูม · ลากเพื่อเลื่อน · Esc เพื่อปิด</span>
  </p>
</div>

<script>
  'use strict';
  (function () {
    var overlay = document.querySelector('[data-zoom-overlay]');
    if (!overlay) return;

    var img = overlay.querySelector('[data-zoom-img]');
    var caption = overlay.querySelector('[data-zoom-caption]');
    var MIN = 1, MAX = 6;
    var scale = 1, x = 0, y = 0;
    var opener = null;

    function apply() {
      img.style.transform = 'translate(' + x + 'px,' + y + 'px) scale(' + scale + ')';
    }

    function reset() { scale = 1; x = 0; y = 0; apply(); }

    function open(src, alt) {
      img.src = src;
      img.alt = alt || '';
      caption.textContent = alt || '';
      reset();
      overlay.hidden = false;
      document.body.style.overflow = 'hidden';   // กันหน้าเลื่อนอยู่ข้างหลัง
      overlay.querySelector('[data-zoom-close]').focus();
    }

    function close() {
      overlay.hidden = true;
      img.src = '';
      document.body.style.overflow = '';
      if (opener && document.contains(opener)) opener.focus();
      opener = null;
    }

    // ── เปิดจากรูปหรือปุ่มที่ติด data-zoomable (ผูกที่ document เพื่อให้รูปที่ JS สร้างทีหลังใช้ได้ด้วย) ──
    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-zoomable]');
      if (!trigger) return;

      var inner = trigger.matches('img') ? trigger : trigger.querySelector('img');
      var src = trigger.getAttribute('data-zoom-src') || (inner && inner.getAttribute('src'));
      if (!src) return;

      e.preventDefault();
      e.stopPropagation();
      opener = trigger;
      open(src, trigger.getAttribute('data-zoom-alt') || (inner && inner.alt) || '');
    });

    // ── หมุนล้อเมาส์เพื่อซูม โดยซูมเข้าหาตำแหน่งเคอร์เซอร์ ──
    overlay.addEventListener('wheel', function (e) {
      if (overlay.hidden) return;
      e.preventDefault();

      var rect = img.getBoundingClientRect();
      var cx = e.clientX - (rect.left + rect.width / 2);
      var cy = e.clientY - (rect.top + rect.height / 2);

      var next = Math.min(MAX, Math.max(MIN, scale * (e.deltaY < 0 ? 1.15 : 1 / 1.15)));
      var ratio = next / scale;

      // เลื่อนภาพชดเชย เพื่อให้จุดใต้เคอร์เซอร์อยู่กับที่ตอนซูม
      x -= cx * (ratio - 1);
      y -= cy * (ratio - 1);
      scale = next;

      if (scale === MIN) { x = 0; y = 0; }
      apply();
    }, { passive: false });

    // ── ลากเพื่อเลื่อนตอนซูมอยู่ ──
    var dragging = false, startX = 0, startY = 0;
    img.addEventListener('pointerdown', function (e) {
      if (scale <= MIN) return;
      dragging = true;
      startX = e.clientX - x;
      startY = e.clientY - y;
      img.classList.add('is-panning');
      img.setPointerCapture(e.pointerId);
    });
    img.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      x = e.clientX - startX;
      y = e.clientY - startY;
      apply();
    });
    ['pointerup', 'pointercancel'].forEach(function (ev) {
      img.addEventListener(ev, function () { dragging = false; img.classList.remove('is-panning'); });
    });

    // ── ดับเบิลคลิกสลับขยาย/ขนาดเดิม ──
    img.addEventListener('dblclick', function () {
      if (scale > MIN) { reset(); } else { scale = 2.5; apply(); }
    });

    overlay.querySelector('[data-zoom-in]').addEventListener('click', function () {
      scale = Math.min(MAX, scale * 1.3); apply();
    });
    overlay.querySelector('[data-zoom-out]').addEventListener('click', function () {
      scale = Math.max(MIN, scale / 1.3);
      if (scale === MIN) { x = 0; y = 0; }
      apply();
    });
    overlay.querySelector('[data-zoom-reset]').addEventListener('click', reset);
    overlay.querySelector('[data-zoom-close]').addEventListener('click', close);

    // คลิกพื้นหลัง (ไม่ใช่ตัวรูป) เพื่อปิด
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay || e.target.hasAttribute('data-zoom-stage')) close();
    });

    document.addEventListener('keydown', function (e) {
      if (overlay.hidden) return;
      if (e.key === 'Escape') close();
      if (e.key === '+' || e.key === '=') { scale = Math.min(MAX, scale * 1.3); apply(); }
      if (e.key === '-') { scale = Math.max(MIN, scale / 1.3); if (scale === MIN) { x = 0; y = 0; } apply(); }
      if (e.key === '0') reset();
    });
  })();
</script>
