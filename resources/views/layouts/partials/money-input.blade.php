{{--
  ช่องกรอกจำนวนเงิน — ใส่ลูกน้ำคั่นหลักพันให้ระหว่างพิมพ์ (เจ้าของสั่ง 2026-09-04)

  include ไว้ที่ layouts/app.blade.php แล้ว ทุกหน้าได้ไปด้วย ไม่ต้อง include ซ้ำ

  วิธีใช้ — ครอบช่องกรอกด้วยกล่อง .money-field แล้วใส่ data-money-input ที่ช่อง
    <span class="money-field">
      <input class="input money-box" type="text" inputmode="decimal"
             data-money-input data-money-target="amount" value="...">
      <span class="money-unit">บาท</span>
    </span>
    <input type="hidden" name="amount" id="amount">

  🔴 ช่องที่ผู้ใช้เห็นเป็น type="text" เสมอ — type="number" ใส่ลูกน้ำไม่ได้ เบราว์เซอร์จะถือว่าค่าผิด
  🔴 ค่าที่ส่งไปเซิร์ฟเวอร์อยู่ในช่องซ่อน (data-money-target ชี้ไปที่ id ของมัน)
     เป็นตัวเลขล้วนเสมอ ไม่มีลูกน้ำ — ฝั่งเซิร์ฟเวอร์จึง validate numeric ได้ตามปกติ
  🔴 ถึงจะมีตัวนี้แล้ว ฝั่งเซิร์ฟเวอร์ก็ยังต้องตัดลูกน้ำทิ้งก่อน validate อยู่ดี
     เผื่อ JS ไม่ทำงาน หรือมีคนยิงค่าตรงเข้ามา (ดู InvestController::validated)
--}}
<style>
  /*
    ช่องกรอกเงิน — ตั้งใจให้หน้าตาต่างจากช่องอื่น เพราะเป็นตัวเลขที่สำคัญที่สุดในเอกสาร
    ตัวหนาสีเขียวชุดเดียวกับจำนวนเงินในตาราง (.money-strong) จะได้เป็นภาษาเดียวกันทั้งระบบ
  */
  .money-field { position: relative; display: block; }

  .input.money-box {
    padding-right: 52px;                       /* เว้นที่ให้คำว่า "บาท" ที่ลอยอยู่ในช่อง */
    color: var(--ok);
    font-size: 17px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;        /* ตัวเลขกว้างเท่ากัน ไม่ขยับตอนพิมพ์ */
    letter-spacing: .2px;
    text-align: right;
    background: var(--ok-soft);
    border-color: #b9e2cd;
  }

  .input.money-box::placeholder { color: #8fbfa8; font-weight: 400; letter-spacing: 0; }
  .input.money-box:focus { border-color: var(--ok); box-shadow: 0 0 0 3px rgb(22 121 74 / 12%); }

  /* ช่องที่กรอกไม่ครบจะถูกตีกรอบแดง — ต้องชนะสีเขียวของช่องเงิน */
  .input.money-box.is-missing { border-color: var(--danger); box-shadow: 0 0 0 2px rgb(179 49 42 / 12%); }

  .money-unit {
    position: absolute; top: 50%; right: 12px; transform: translateY(-50%);
    color: var(--ok); font-size: var(--fs-sm); font-weight: 600;
    pointer-events: none;                      /* กดโดนคำว่า "บาท" ต้องได้โฟกัสที่ช่องกรอก */
  }
</style>

<script>
  'use strict';

  (function () {
    /*
      แปลงค่าที่พิมพ์เป็นรูปแบบมีลูกน้ำ แล้วเก็บตัวเลขล้วนไว้ในช่องซ่อน

      🔴 ต้องคืนตำแหน่งเคอร์เซอร์เอง — การเขียนทับ value ทำให้เคอร์เซอร์เด้งไปท้ายช่อง
         พิมพ์แทรกกลางตัวเลขแล้วจะกระโดด ใช้งานจริงไม่ได้
    */
    function digitsBefore(text, pos) {
      var n = 0;

      for (var i = 0; i < pos && i < text.length; i++) {
        if (text[i] >= '0' && text[i] <= '9') { n++; }
      }

      return n;
    }

    function posAfterDigits(text, count) {
      if (count <= 0) { return 0; }

      var seen = 0;

      for (var i = 0; i < text.length; i++) {
        if (text[i] >= '0' && text[i] <= '9') {
          seen++;
          if (seen === count) { return i + 1; }
        }
      }

      return text.length;
    }

    function group(raw) {
      // เก็บได้แค่ตัวเลขกับจุดทศนิยมจุดเดียว · ทศนิยมไม่เกิน 2 ตำแหน่ง
      var clean = raw.replace(/[^\d.]/g, '');
      var dot = clean.indexOf('.');

      if (dot !== -1) {
        clean = clean.slice(0, dot + 1) + clean.slice(dot + 1).replace(/\./g, '');
      }

      var parts = clean.split('.');
      var whole = parts[0].replace(/^0+(?=\d)/, '');          // ตัดศูนย์นำหน้า แต่เหลือ "0" ไว้ได้
      var dec = parts.length > 1 ? parts[1].slice(0, 2) : null;

      var shown = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

      if (dec !== null) { shown += '.' + dec; }

      return {
        shown: shown,
        raw: (whole === '' ? '' : whole) + (dec !== null && dec !== '' ? '.' + dec : ''),
      };
    }

    function bind(input) {
      if (input.dataset.moneyBound === '1') { return; }

      input.dataset.moneyBound = '1';

      var hidden = document.getElementById(input.getAttribute('data-money-target') || '');

      function sync(keepCaret) {
        var before = input.value;
        var caret = keepCaret ? (input.selectionStart || 0) : null;
        var typed = keepCaret ? digitsBefore(before, caret) : 0;

        var out = group(before);
        input.value = out.shown;

        if (hidden) { hidden.value = out.raw; }

        if (keepCaret) {
          var at = posAfterDigits(out.shown, typed);
          try { input.setSelectionRange(at, at); } catch (e) { /* ช่องที่ซ่อนอยู่ตั้งเคอร์เซอร์ไม่ได้ */ }
        }
      }

      input.addEventListener('input', function () { sync(true); });

      // ออกจากช่องแล้วเติมทศนิยมให้ครบ 2 ตำแหน่ง เหมือนที่แสดงในตาราง
      input.addEventListener('blur', function () {
        if (input.value.trim() === '') { return; }

        var n = parseFloat((hidden && hidden.value) || input.value.replace(/,/g, ''));
        if (isNaN(n)) { return; }

        input.value = n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (hidden) { hidden.value = String(n); }
      });

      sync(false);
    }

    document.querySelectorAll('[data-money-input]').forEach(bind);

    // เผื่อหน้าไหนสร้างช่องเงินขึ้นมาทีหลังด้วย JS
    window.BMS = window.BMS || {};
    window.BMS.bindMoney = function (root) {
      (root || document).querySelectorAll('[data-money-input]').forEach(bind);
    };
  })();
</script>
