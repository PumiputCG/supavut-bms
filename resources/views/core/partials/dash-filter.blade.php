{{--
  ═══════════ แถบตัวกรองของหน้าภาพรวม ═══════════

  🔴 ตัวกรองนี้ "อยู่นอกการ์ด" และคุมทุกการ์ดพร้อมกัน (เจ้าของสั่ง 2026-09-04)
     เลือกแผนก IT แล้วทุกการ์ดในหน้าต้องแสดงเฉพาะ IT — แนวเดียวกับ Power BI
     อนาคตจะมีการ์ด "ใบขอซื้อ (PR)" ที่ต้องเอาไปหักกับ Budget · "งบคงเหลือ" · ค่าใช้จ่ายอื่น
     ทุกตัวต้องเดินตามตัวกรองเดียวกันนี้ ห้ามมีตัวกรองแผนกซ้อนอยู่ในการ์ดใครการ์ดมัน

  วิธีให้การ์ดใหม่เข้าร่วม — ฟังเหตุการณ์เดียวนี้พอ
    document.addEventListener('dash:filter', function (e) { e.detail.dept });   // '' = ทุกแผนก
    window.BMS.dashDept()                                                       // อ่านค่าปัจจุบัน
--}}
<div class="dash-bar">
  <span class="dash-bar-label" data-i18n="dash.filter">ตัวกรอง</span>

  {{--
    🔴 ตัวกรองปีทำงานที่เซิร์ฟเวอร์ (โหลดหน้าใหม่) ไม่ใช่กรองใน JS
       เพราะข้อมูลงบทุกปีรวมกันหลักหมื่นแถว ส่งมาทั้งหมดแล้วให้เบราว์เซอร์กรองจะอืด
    🔴 ค่าเริ่มต้นคือ "ทุกปี" ตามที่เจ้าของสั่ง
  --}}
  <form method="GET" style="display:contents">
    <select class="sel sel-sm" name="year" onchange="this.form.submit()"
            aria-label="ปีงบ" data-i18n-aria="budget.year">
      <option value="" data-i18n="dash.allYears">ทุกปี</option>
      @foreach ($years ?? [] as $y)
        <option value="{{ $y }}" @selected(($year ?? null) === $y)>{{ $y }}</option>
      @endforeach
    </select>
  </form>

  <select class="sel sel-sm" data-dash-dept aria-label="แผนก" data-i18n-aria="budget.dept">
    {{-- ค่าเริ่มต้นคือทุกแผนก --}}
    <option value="" data-i18n="chart.allDept">แผนกทั้งหมด</option>
    @foreach ($deptOptions as $name)
      <option value="{{ $name }}">{{ $name }}</option>
    @endforeach
  </select>

  <button type="button" class="btn btn-quiet dash-clear" data-dash-clear hidden
          data-i18n="dash.clearFilter">ล้างตัวกรอง</button>
</div>

<style>
  /* 🔴 อยู่บรรทัดเดียวกันเสมอ — ห้ามให้ปุ่ม "ล้างตัวกรอง" ตกบรรทัด (เจ้าของสั่ง 2026-09-04) */
  .dash-bar {
    display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;
    margin-bottom: 14px;
  }

  .dash-bar-label { color: var(--muted); font-size: var(--fs-xs); flex: 0 0 auto; }

  /*
    🔴 ต้องเขียนทับ `.sel { width: 100% }` ของฟอร์ม ไม่งั้นช่องยืดกินทั้งบรรทัด
       กว้างพอสำหรับชื่อแผนกที่ยาวสุด แต่ไม่เกิน 260px
  */
  .dash-bar .sel-sm {
    width: auto; min-width: 190px; max-width: 260px; flex: 0 1 auto;
    height: 30px; font-size: var(--fs-xs);
  }

  .dash-clear { height: 28px; padding: 0 9px; font-size: var(--fs-xs); flex: 0 0 auto; }
</style>

<script>
  'use strict';

  (function () {
    var sel = document.querySelector('[data-dash-dept]');
    var clear = document.querySelector('[data-dash-clear]');
    if (! sel) { return; }

    function tell() {
      clear.hidden = sel.value === '';

      // การ์ดทุกใบฟังเหตุการณ์นี้ — เพิ่มการ์ดใหม่ไม่ต้องมาแก้ไฟล์นี้
      document.dispatchEvent(new CustomEvent('dash:filter', { detail: { dept: sel.value } }));
    }

    window.BMS = window.BMS || {};
    window.BMS.dashDept = function () { return sel.value; };

    sel.addEventListener('change', tell);
    clear.addEventListener('click', function () { sel.value = ''; tell(); });
  })();
</script>
