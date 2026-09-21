{{--
  หน้าต่างแจ้งผล / ถามยืนยัน กลางจอ — ใช้ร่วมทั้งระบบ (เจ้าของสั่ง 2026-09-03)

  🔴 กติกาที่ห้ามผิด
     1. ทุก Action (บันทึก · ส่ง · ลบ · ยกเลิก) ต้องขึ้นหน้าต่างกลางจอ ไม่ใช้ alert()/confirm() ของเบราว์เซอร์
     2. เครื่องหมายต้องเป็น Animation — ขีดถูก ✓ · ตกใจ ! · กากบาท ✕ วาดทีละเส้น
     3. ปุ่ม "ยินยอมอยู่ซ้าย · ยกเลิกอยู่ขวา" เสมอ
     4. ข้อความสั้น กระชับ ไม่อธิบายยาว

  วิธีเรียก (มีอยู่แล้วในทุกหน้าที่ @extends('layouts.app'))
    window.BMS.confirm({ kind:'warn', title:'ลบรายการ?', text:'...', ok:'ลบ', onOk: fn })
    window.BMS.notify({ kind:'ok', title:'บันทึกแล้ว' })            // ปิดเองใน 1.8 วินาที
--}}
<div class="dlg-wrap" id="bms-dialog" hidden role="dialog" aria-modal="true" aria-labelledby="bms-dialog-title">
  <div class="dlg" role="document">
    <div class="dlg-mark" data-dlg-mark>
      {{-- วาดด้วย SVG stroke-dashoffset จะได้เห็นเส้นค่อยๆ ลากออกมา ไม่ใช่เด้งขึ้นทั้งอัน --}}
      <svg viewBox="0 0 52 52" aria-hidden="true">
        <circle class="dlg-ring" cx="26" cy="26" r="23"></circle>
        <path class="dlg-ok"   d="M15 27 L23 34 L38 18"></path>
        <path class="dlg-bang" d="M26 15 L26 30"></path>
        <circle class="dlg-dot" cx="26" cy="37" r="1.9"></circle>
        <path class="dlg-no1"  d="M18 18 L34 34"></path>
        <path class="dlg-no2"  d="M34 18 L18 34"></path>
      </svg>
    </div>

    <p class="dlg-title" id="bms-dialog-title" data-dlg-title></p>
    <p class="dlg-text" data-dlg-text hidden></p>

    {{-- 🔴 ยินยอมซ้าย · ยกเลิกขวา — ห้ามสลับ --}}
    <div class="dlg-foot" data-dlg-foot>
      <button type="button" class="btn" data-dlg-ok>ตกลง</button>
      <button type="button" class="btn btn-quiet" data-dlg-cancel data-i18n="common.cancel">ยกเลิก</button>
    </div>
  </div>
</div>

<style>
  .dlg-wrap {
    position: fixed; inset: 0; z-index: var(--z-modal);
    display: grid; place-items: center; padding: 24px;
    background: rgb(8 18 40 / 46%);
    animation: dlg-fade .14s var(--ease);
  }
  .dlg-wrap[hidden] { display: none; }
  @keyframes dlg-fade { from { opacity: 0; } to { opacity: 1; } }

  .dlg {
    width: min(360px, 100%); padding: 26px 24px 20px;
    border-radius: var(--radius-lg); background: var(--surface);
    box-shadow: var(--shadow-lg); text-align: center;
    animation: dlg-in .2s var(--ease);
  }
  @keyframes dlg-in {
    from { opacity: 0; transform: translateY(10px) scale(.97); }
    to   { opacity: 1; transform: none; }
  }

  /* ── เครื่องหมาย ─────────────────────────────────────────── */
  .dlg-mark { width: 62px; height: 62px; margin: 0 auto 14px; }
  .dlg-mark svg { width: 100%; height: 100%; overflow: visible; }

  .dlg-mark circle, .dlg-mark path {
    fill: none; stroke: currentColor; stroke-width: 3;
    stroke-linecap: round; stroke-linejoin: round;
  }
  .dlg-dot { fill: currentColor; stroke: none; }

  /* ซ่อนทุกเส้นไว้ก่อน แล้วเปิดเฉพาะชนิดที่เรียกใช้ */
  .dlg-ok, .dlg-bang, .dlg-dot, .dlg-no1, .dlg-no2 { display: none; }

  .dlg-mark.is-ok   { color: var(--accent); }
  .dlg-mark.is-warn { color: #b8790f; }
  .dlg-mark.is-no   { color: var(--danger); }

  .dlg-mark.is-ok   .dlg-ok  { display: block; }
  .dlg-mark.is-warn .dlg-bang,
  .dlg-mark.is-warn .dlg-dot { display: block; }
  .dlg-mark.is-no   .dlg-no1,
  .dlg-mark.is-no   .dlg-no2 { display: block; }

  /* วงกลมลากรอบ แล้วเส้นข้างในค่อยตามมา */
  .dlg-ring { stroke-dasharray: 145; stroke-dashoffset: 145; animation: dlg-draw .42s var(--ease) forwards; }
  .dlg-ok   { stroke-dasharray:  40; stroke-dashoffset:  40; animation: dlg-draw .28s var(--ease) .28s forwards; }
  .dlg-bang { stroke-dasharray:  16; stroke-dashoffset:  16; animation: dlg-draw .2s  var(--ease) .28s forwards; }
  .dlg-no1  { stroke-dasharray:  24; stroke-dashoffset:  24; animation: dlg-draw .2s  var(--ease) .26s forwards; }
  .dlg-no2  { stroke-dasharray:  24; stroke-dashoffset:  24; animation: dlg-draw .2s  var(--ease) .38s forwards; }
  .dlg-dot  { opacity: 0; animation: dlg-pop .18s var(--ease) .5s forwards; }

  @keyframes dlg-draw { to { stroke-dashoffset: 0; } }
  @keyframes dlg-pop  { to { opacity: 1; } }

  /* ── ข้อความ + ปุ่ม ──────────────────────────────────────── */
  .dlg-title { color: var(--navy-900); font-size: var(--fs-md); font-weight: 700; }
  .dlg-text { margin-top: 6px; color: var(--ink-soft); font-size: var(--fs-sm); }
  .dlg-text[hidden] { display: none; }

  .dlg-foot { display: flex; gap: 8px; margin-top: 20px; }
  .dlg-foot > .btn { flex: 1; justify-content: center; }
  .dlg-foot > [data-dlg-cancel][hidden] { display: none; }

  /* ปุ่มยืนยันของงานที่ลบ/ตีกลับ ใช้สีเตือน จะได้ไม่กดผิด */
  .dlg-foot > .btn.is-danger { border-color: var(--danger); background: var(--danger); }
  .dlg-foot > .btn.is-danger:hover:not(:disabled) { background: #9c2a24; border-color: #9c2a24; }

  /*
    🔴 มือถือ: ขอบ 24px รอบหน้าต่างกินความกว้างไปมาก และปุ่มยาวๆ 2 ปุ่มวางข้างกันจะถูกบีบจนตัดคำ
       เรียงปุ่มลงมาเต็มความกว้างแทน · **ยินยอมอยู่บน · ยกเลิกอยู่ล่าง**
       (กติกา "ยินยอมซ้าย ยกเลิกขวา" ของโปรเจค เมื่อเรียงแนวตั้งคือ บน/ล่าง เจตนาเดิมไม่เปลี่ยน)
  */
  @media (max-width: 560px) {
    .dlg-wrap { padding: 12px; }
    .dlg { padding: 22px 18px 18px; }
    .dlg-foot { flex-direction: column; }
  }

  @media (prefers-reduced-motion: reduce) {
    .dlg-wrap, .dlg { animation: none; }
    .dlg-mark circle, .dlg-mark path { animation: none; stroke-dashoffset: 0; opacity: 1; }
  }
</style>

<script>
  'use strict';

  (function () {
    var wrap = document.getElementById('bms-dialog');
    var mark = wrap.querySelector('[data-dlg-mark]');
    var titleEl = wrap.querySelector('[data-dlg-title]');
    var textEl = wrap.querySelector('[data-dlg-text]');
    var okBtn = wrap.querySelector('[data-dlg-ok]');
    var cancelBtn = wrap.querySelector('[data-dlg-cancel]');

    var onOk = null;
    var onCancel = null;
    var lastFocus = null;
    var autoTimer = null;

    var close = function (cancelled) {
      window.clearTimeout(autoTimer);
      wrap.hidden = true;

      // กดยกเลิก/ปิด — ให้ผู้เรียกคืนสถานะเดิมได้ (เช่น dropdown ในตาราง)
      if (cancelled && onCancel) { onCancel(); }

      onOk = null;
      onCancel = null;

      if (lastFocus && document.contains(lastFocus)) { lastFocus.focus(); }
    };

    var open = function (opt) {
      window.clearTimeout(autoTimer);
      lastFocus = document.activeElement;

      // สร้าง svg ใหม่ทุกครั้ง ไม่งั้นเปิดซ้ำแล้วอนิเมชันไม่เล่น (เบราว์เซอร์ถือว่าเป็นตัวเดิม)
      var svg = mark.querySelector('svg');
      mark.replaceChild(svg.cloneNode(true), svg);
      mark.className = 'dlg-mark is-' + (opt.kind === 'ok' ? 'ok' : (opt.kind === 'no' ? 'no' : 'warn'));

      titleEl.textContent = opt.title || '';
      textEl.textContent = opt.text || '';
      textEl.hidden = !opt.text;

      okBtn.textContent = opt.ok || window.BMS.t('common.ok', 'ตกลง');
      okBtn.classList.toggle('is-danger', opt.kind === 'no' && !!opt.onOk);
      cancelBtn.textContent = opt.cancel || window.BMS.t('common.cancel', 'ยกเลิก');
      cancelBtn.hidden = !opt.onOk;   // ไม่มีอะไรให้ยืนยัน ก็ไม่ต้องมีปุ่มยกเลิก

      onOk = opt.onOk || null;
      onCancel = opt.onCancel || null;
      wrap.hidden = false;
      okBtn.focus();

      // แจ้งผลเฉยๆ ปิดเองได้ ไม่ต้องให้ผู้ใช้กดทุกครั้ง
      if (!opt.onOk && opt.autoClose !== false) {
        autoTimer = window.setTimeout(close, opt.autoClose || 1800);
      }
    };

    okBtn.addEventListener('click', function () {
      var run = onOk;
      close();
      if (run) { run(); }
    });

    cancelBtn.addEventListener('click', function () { close(true); });
    wrap.addEventListener('click', function (e) { if (e.target === wrap) { close(true); } });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !wrap.hidden) { close(true); }
    });

    window.BMS = window.BMS || {};
    window.BMS.confirm = open;
    window.BMS.notify = function (opt) { open(Object.assign({ kind: 'ok' }, opt, { onOk: null })); };
    window.BMS.closeDialog = close;

    /*
      ปุ่มที่ต้องถามก่อนทำ — ใส่ data-confirm ที่ตัวปุ่มได้เลย ไม่ต้องเขียน JS ซ้ำทุกหน้า
        <button data-confirm-th="ลบรายการ?" data-confirm-en="Delete?" data-confirm-kind="no">
    */
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-confirm-th]');
      if (!btn || btn.dataset.confirmed === '1') { return; }

      e.preventDefault();
      var en = window.BMS.lang() === 'en';

      open({
        kind: btn.getAttribute('data-confirm-kind') || 'warn',
        title: (en ? btn.getAttribute('data-confirm-en') : btn.getAttribute('data-confirm-th')) || '',
        text: (en ? btn.getAttribute('data-confirm-text-en') : btn.getAttribute('data-confirm-text-th')) || '',
        ok: (en ? btn.getAttribute('data-confirm-ok-en') : btn.getAttribute('data-confirm-ok-th')) || '',
        onOk: function () {
          // ปล่อยให้คลิกเดิมทำงานต่อ (submit / ตามลิงก์) โดยไม่วนกลับมาถามอีก
          btn.dataset.confirmed = '1';
          btn.click();
          delete btn.dataset.confirmed;
        },
      });
    });
  })();
</script>
