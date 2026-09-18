{{--
  ปุ่มติ๊ก "ทุกคน" ของหน้าต่างแก้ไขสิทธิ์ (เจ้าของสั่ง 2026-09-07)

  🔴 กติกาของระบบคือ **ไม่เลือกใคร = ไม่มีใครใช้ได้**
     ถ้าอยากเปิดให้ทุกคนต้องติ๊กตรงนี้ให้ชัดเจน จะได้อ่านออกว่า
     "ตั้งใจเปิด" ไม่ใช่ "ยังไม่ได้ตั้ง"

  ติ๊กแล้วรายชื่อรายคนไม่มีความหมาย — จึงหรี่ตัวเลือกคนลงและบอกเหตุผลไว้

  ต้องส่งมา: $checked (ตอนนี้ตั้งเป็นทุกคนอยู่ไหม)
--}}
<label class="everyone-tick">
  <input type="checkbox" name="everyone" value="1" data-everyone @checked($checked ?? false)>
  <span>
    <b data-i18n="access.everyone.tick">เปิดให้ทุกคนที่เข้าระบบได้</b>
    <span class="soft" data-i18n="access.everyone.hint">ติ๊กแล้วไม่ต้องเลือกรายชื่อ — ทุกคนที่ล็อกอินได้จะใช้หัวข้อนี้ได้</span>
  </span>
</label>

<style>
  .everyone-tick {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 12px; border: 1px solid var(--line); border-radius: var(--radius);
    background: var(--surface-2); cursor: pointer;
  }

  .everyone-tick input { width: 16px; height: 16px; margin-top: 2px; flex: 0 0 auto; accent-color: var(--navy-800); }
  .everyone-tick > span { display: grid; gap: 2px; }
  .everyone-tick b { color: var(--navy-900); font-size: var(--fs-sm); }

  /* ติ๊กแล้ว — หรี่ตัวเลือกคนลงให้รู้ว่าไม่ต้องใช้แล้ว */
  .picker.is-off { opacity: .45; pointer-events: none; }
</style>

<script>
  'use strict';

  (function () {
    /*
      ผูกครั้งเดียวที่ document — หน้านี้มีหน้าต่างแก้ไขสิทธิ์หลายสิบอัน
      ถ้าผูกทีละอันจะได้ตัวจัดการซ้ำกันเป็นร้อยตัว
    */
    if (window.__everyoneTickReady) { return; }

    window.__everyoneTickReady = true;

    function sync(tick) {
      var form = tick.closest('form');
      if (! form) { return; }

      var picker = form.querySelector('.picker');
      if (picker) { picker.classList.toggle('is-off', tick.checked); }
    }

    document.addEventListener('change', function (e) {
      if (e.target.matches('[data-everyone]')) { sync(e.target); }
    });

    document.querySelectorAll('[data-everyone]').forEach(sync);
  })();
</script>
