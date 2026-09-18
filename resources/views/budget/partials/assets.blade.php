{{--
  หน้าตาที่ใช้ร่วมทุกหน้าในโมดูลงบประมาณ — ใส่ครั้งเดียวต่อหน้า
  ยึดแนวเดียวกับโมดูลอื่น: ไม่มีกรอบให้ป้ายสถานะ ใช้สีข้อความบอกแทน
--}}
<style>
  /* กรอบเลื่อนอยู่ที่ธีมกลางแล้ว */
  .tbl-wrap .tbl { min-width: 820px; }
  .tbl td { vertical-align: middle; }

  .doc-no { color: var(--navy-900); font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .money { font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }

  /* จำนวนเงินที่เป็นตัวเลขหลัก — ตัวหนา สีเขียว (เจ้าของสั่ง 2026-09-03) */
  .money-strong { color: var(--ok); font-weight: 700; }
  .col-money { width: 120px; text-align: right; }
  .col-act { width: 1%; white-space: nowrap; text-align: right; }
  .col-no { width: 1%; white-space: nowrap; }
  /* คอลัมน์ชื่อคน — ชื่อไทยยาว ถ้าปล่อยให้ตัดบรรทัดจะกลายเป็น 3 บรรทัด อ่านยาก */
  .col-person { width: 1%; white-space: nowrap; }
  .soft { color: var(--muted); font-size: var(--fs-xs); }

  /*
    หมวด "อื่นๆ" — ข้อความที่ผู้ใช้กรอกเอง (เจ้าของสั่ง 2026-09-04)
    🔴 ตัวอักษรขนาดเท่ากันและสีเดียวกับชื่อหมวด ห้ามทำให้จางลง
       มันคือเนื้อหาจริงของเอกสาร ไม่ใช่คำอธิบายประกอบ
  */
  .cat-other { margin-left: 4px; color: inherit; font-size: inherit; }

  /*
    บรรทัด "เหตุผล" — ใช้ทั้งในเส้นทางเอกสารและในตัวเอกสาร (เจ้าของสั่ง 2026-09-04)
    🔴 ป้ายเป็นสีดำเสมอ · ตัวข้อมูลอยู่ในกรอบสีตามผล ขนาดพอดีข้อความ
  */
  .doc-reason { margin: 10px 0 0; color: var(--ink); font-size: var(--fs-sm); }

  /* หลายคนพูดได้หลายบรรทัด — บอกด้วยว่าใครเป็นคนพูด (เจ้าของสั่ง 2026-09-07) */
  .doc-reason-key { color: var(--ink); font-weight: 600; }

  .doc-reason-val {
    display: inline-block; margin-left: 4px; padding: 2px 10px;
    border-radius: 999px; font-size: var(--fs-sm);
  }

  .doc-reason-val.is-ok { background: var(--ok-soft); color: var(--ok); }
  .doc-reason-val.is-no { background: var(--danger-soft); color: var(--danger); }

  /*
    ── ป้ายสถานะ ──
    🔴 เจ้าของสั่ง 2026-09-04: ในตารางให้ระบายพื้นหลังเต็มช่อง จะได้กวาดตาเห็นทันที
       (นอกตาราง เช่นบนกระดาษเอกสาร ยังใช้สีตัวอักษรล้วนเหมือนเดิม)
  */
  .tbl td > .st {
    display: block; margin: -9px -12px; padding: 9px 12px;
    font-weight: 700;
  }

  .tbl td > .st-draft { background: var(--surface-2); color: var(--ink-soft); }
  .tbl td > .st-pending { background: #fdf2e0; color: #8a5a0b; }
  .tbl td > .st-approved { background: #e3f5ec; color: #10603a; }
  .tbl td > .st-rejected { background: #fdecea; color: #a02a24; }
  .tbl td > .st-active { background: #e3f5ec; color: #10603a; }
  .tbl td > .st-hold { background: #fdf2e0; color: #8a5a0b; }
  .tbl td > .st-stopped { background: #fdecea; color: #a02a24; }
  .tbl td > .st-closed { background: var(--surface-2); color: var(--ink-soft); }
  /* เอกสารจบไปก่อน คนนี้จึงไม่ได้ลงนาม — เทา บอกว่า "ไม่มีอะไรเกิดขึ้นตรงนี้" */
  .tbl td > .st-none { background: var(--surface-4); color: var(--muted); }
  /*
    ผู้รับทราบ — พื้นขาว ตัวอักษรดำ (เจ้าของสั่ง 2026-09-16)
    🔴 จงใจไม่ระบายสี เพราะไม่ใช่ผลของการตัดสิน แค่บอกว่าเขาได้รับสำเนา
       ถ้าใส่สีจะแย่งสายตาไปจากผู้ลงนามซึ่งเป็นคนที่เอกสารรออยู่จริง
  */
  .tbl td > .st-ack { background: var(--surface); color: var(--ink); }

  /*
    🔴 ทางหลัก — ระบายที่ "ตัวช่อง" เลย จึงเต็มช่องจริงแม้แถวจะสูงเพราะคอลัมน์อื่น
       (การยืดตัวป้ายด้วย margin ติดลบข้างบน คลุมได้แค่ความสูงของป้ายเอง)
  */
  @supports selector(td:has(> span)) {
    .tbl td:has(> .st) { padding: 0; }
    .tbl td:has(> .st) > .st { margin: 0; background: transparent; }

    .tbl td:has(> .st-draft) { background: #eceff3; }
    .tbl td:has(> .st-pending) { background: #fdf2e0; }
    .tbl td:has(> .st-approved) { background: #e3f5ec; }
    .tbl td:has(> .st-rejected) { background: #fdecea; }
    .tbl td:has(> .st-active) { background: #e3f5ec; }
    .tbl td:has(> .st-hold) { background: #fdf2e0; }
    .tbl td:has(> .st-stopped) { background: #fdecea; }
    .tbl td:has(> .st-closed) { background: #eceff3; }
    .tbl td:has(> .st-none) { background: var(--surface-4); }
    .tbl td:has(> .st-ack) { background: var(--surface); }
  }

  /* ── ป้ายสถานะ — ไม่มีกรอบ ใช้สีล้วน ── */
  .st { font-size: var(--fs-sm); white-space: nowrap; }
  .st-draft { color: var(--muted); }
  .st-pending { color: var(--warn); }
  .st-approved { color: var(--ok); }
  .st-rejected { color: var(--danger); }
  .st-hold { color: var(--warn); }
  .st-stopped { color: var(--danger); }
  .st-closed { color: var(--muted); }
  .st-none { color: var(--muted); }
  .st-ack { color: var(--ink); }

  /* ── ตัวเลข 4 ค่าของงบ ── */
  .figs { display: flex; flex-wrap: wrap; gap: 0; }
  .fig { padding: 0 22px; border-left: 1px solid var(--line-soft); }
  .fig:first-child { padding-left: 0; border-left: 0; }
  .fig b { display: block; color: var(--navy-900); font-size: 19px; font-variant-numeric: tabular-nums; }
  .fig span { color: var(--muted); font-size: var(--fs-xs); }
  .fig-reserved b { color: var(--warn); }
  .fig-actual b { color: var(--accent); }
  .fig-left b { color: var(--ok); }
  .fig-left.is-out b { color: var(--danger); }

  /* แถบสัดส่วนการใช้งบ — บอกด้วยความยาว ไม่ใช่สีอย่างเดียว */
  .meter { height: 5px; border-radius: 3px; background: var(--surface-2); overflow: hidden; }
  .meter i { display: block; height: 100%; background: var(--accent); }
  .meter i.warn { background: var(--warn); }
  .meter i.over { background: var(--danger); }

  /*
    ── แถบสรุปหัวตาราง ──
    🔴 ต้องอยู่ที่ชิ้นส่วนกลาง ไม่ใช่ในหน้าใดหน้าหนึ่ง (บทเรียนเดิมของโปรเจค)
       เดิมนิยามไว้ในหน้าประวัติงบประมาณหน้าเดียว หน้าอื่นที่เอาไปใช้จะเพี้ยน
    ตัวเลขใหญ่ ป้ายเล็ก ไม่ใส่กรอบตามกฎของระบบ
  */
  .sum { display: flex; flex-wrap: wrap; gap: 22px 34px; }
  .sum-cell { display: grid; gap: 2px; }
  .sum-cell b { color: var(--navy-900); font-size: var(--fs-lg); font-variant-numeric: tabular-nums; }
  .sum-cell span { color: var(--muted); font-size: var(--fs-xs); }
  .sum-cell.is-ok b { color: var(--ok); }
  .sum-cell.is-run b { color: var(--warn); }
  .sum-cell.is-no b { color: var(--danger); }

  /*
    เส้นคั่นกลุ่ม — บอกว่าตัวเลขฝั่งขวาเป็นคนละเรื่องกับฝั่งซ้าย
    🔴 เส้นเดียวพอ ไม่ตีกรอบ (เจ้าของสั่ง 2026-09-10)
  */
  .sum-cell.has-split { padding-left: 34px; border-left: 1px solid var(--line); }

  .bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .bar .spacer { flex: 1; }

  /* แถบปุ่มเหนือกระดาษ — ขอบขวาต้องตรงกับขอบกระดาษพอดี */
  /*
    แถบปุ่มเหนือกระดาษ — กว้างเท่ากระดาษพอดี
    🔴 เดิมเป็น margin-bottom -8px ซึ่งดึงกระดาษขึ้นมา "ทับ" แถบปุ่ม (เจ้าของแจ้ง 2026-09-18)
       เปลี่ยนเป็นระยะห่างจริง กระดาษจึงเริ่มใต้ปุ่ม ไม่ซ้อนกัน
  */
  .bar.is-paper { max-width: var(--paper-max); margin: 0 auto 12px; }

  /* ── ฟอร์ม ── */
  .grid2 { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }
  .grid2 .span-2 { grid-column: 1 / -1; }
  .sec { color: var(--navy-900); font-weight: 700; font-size: var(--fs-sm); }
  .sel {
    width: 100%; height: 40px; padding: 0 10px;
    border: 1px solid var(--line); border-radius: var(--radius);
    background: var(--surface); color: var(--ink); font: inherit; font-size: var(--fs-sm);
  }
  .sel:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }

  /* ── รายการย่อยในข้อเสนอ ── */
  .items .tbl { min-width: 720px; }
  .items input { width: 100%; height: 32px; padding: 0 8px; border: 1px solid var(--line); border-radius: var(--radius-sm); font: inherit; font-size: var(--fs-sm); }
  .items input:focus { outline: none; border-color: var(--accent); }
  .items input.num { text-align: right; font-variant-numeric: tabular-nums; }
  .items .x { border: 0; background: transparent; color: var(--muted); cursor: pointer; font-size: 16px; line-height: 1; }
  .items .x:hover { color: var(--danger); }

  /* ── สายอนุมัติ ── */
  .flow { display: grid; gap: 0; }
  .flow-row { display: flex; align-items: center; gap: 12px; padding: 10px 2px; border-bottom: 1px solid var(--line-soft); }
  .flow-row:last-child { border-bottom: 0; }
  .flow-n {
    width: 24px; height: 24px; flex: 0 0 auto; display: grid; place-items: center;
    border-radius: 50%; background: var(--surface-2); color: var(--ink-soft);
    font-size: var(--fs-xs); font-weight: 700;
  }
  .flow-row.is-done .flow-n { background: var(--ok-soft); color: var(--ok); }
  .flow-row.is-now .flow-n { background: var(--accent-soft); color: var(--accent); }
  .flow-row.is-rejected .flow-n { background: var(--danger-soft); color: var(--danger); }
  .flow-who { flex: 1; min-width: 0; display: grid; gap: 1px; }
  .flow-who b { color: var(--navy-900); font-size: var(--fs-sm); }
  .flow-sign img { max-height: 34px; mix-blend-mode: multiply; }

  .empty { padding: 26px; text-align: center; color: var(--muted); font-size: var(--fs-sm); }
  /*
    🔴 สไตล์ตัวแบ่งหน้าเก่าถูกลบออกจากที่นี่แล้ว (2026-09-04)
       มันเขียนทับตัวแบ่งหน้าของระบบ (.pg ใน theme.blade.php) เพราะน้ำหนักเท่ากันแต่ include ทีหลัง
       ผลคือ **เลขหน้าปัจจุบันเป็นตัวหนังสือน้ำเงินบนพื้นน้ำเงิน มองไม่เห็นเลย**
       ตัวแบ่งหน้ามีที่เดียวคือ resources/views/vendor/pagination/bms.blade.php — อย่าเขียนสไตล์ซ้ำที่หน้าไหนอีก
  */
</style>
