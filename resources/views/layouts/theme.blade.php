{{--
  ธีมกลางของ SBMS — แหล่งความจริงเดียวของหน้าตาทั้งระบบ
  ทุกหน้าต้อง @include('layouts.theme') แล้วใช้ตัวแปรจากไฟล์นี้เท่านั้น

  🔴 ห้ามเขียนค่าสี / ขนาด / เงา / รัศมี เป็นค่าดิบในหน้าใดๆ
     ถ้าต้องการค่าใหม่ ให้เพิ่ม token ที่ไฟล์นี้ ไม่ใช่เขียนดิบในหน้า
     (เหตุผลและกติกาเต็มอยู่ใน DESIGN_SYSTEM.md ที่ root)
--}}
<style>
  :root {
    /* ── สีหลัก: น้ำเงินองค์กร ────────────────────────────── */
    --navy-900: #0d2350;   /* แถบบนซ้าย · ตัวอักษรหัวข้อเข้ม */
    --navy-800: #12306b;   /* ปุ่มหลัก · หัวข้อ */
    --navy-700: #1c4189;   /* แถบบนขวา (ไล่สี) */
    --royal:    #2f57a6;   /* แผงซ้ายหน้าล็อกอิน (เข้ม) */
    --royal-400:#4a6bb5;   /* แผงซ้ายหน้าล็อกอิน (อ่อน) */
    --accent:   #2563c7;   /* ลิงก์ · จุดเน้น · เส้นใต้แท็บ */
    --accent-soft: #e8f0fc;

    /* ── พื้นผิว ──────────────────────────────────────────── */
    --page:     #eef1f7;   /* พื้นหลังนอกการ์ด */
    --surface:  #ffffff;   /* การ์ด · แผงเนื้อหา */
    --surface-2:#f7f9fc;   /* แถบเครื่องมือ · หัวตาราง */
    --surface-3:#eef2f8;   /* แถบสถานะ · แถวสลับ */
    --surface-4:#dfe3e8;   /* ช่องที่ตั้งใจเว้นว่าง (ไม่มีข้อมูล) — เข้มกว่าแถวสลับให้เห็นชัด */

    /* ── ตัวอักษร (ผ่าน WCAG AA บนพื้น --surface ทุกตัว) ──── */
    --ink:      #16233d;   /* ข้อความหลัก */
    --ink-soft: #45556f;   /* ข้อความรอง */
    --muted:    #6b7a94;   /* ป้ายกำกับ · คำอธิบาย */
    --on-dark:  #ffffff;
    --on-dark-soft: #c2d0ea;

    /* ── เส้น ─────────────────────────────────────────────── */
    --line:      #dde4ef;
    --line-soft: #eaeff6;
    --line-dark: #97a6bf;   /* เส้นที่ต้องมองเห็นชัด เช่น กิ่งผังเมนู · ตัวคั่น breadcrumb */
    --line-on-dark: rgb(255 255 255 / 18%);   /* เส้นคั่นบนแถบสีเข้ม */

    /* ── สีบอกสถานะ ───────────────────────────────────────── */
    --ok: #16794a;      --ok-soft: #e3f5ec;
    /*
      🔴 สีเขียวสำหรับ "พื้นเข้ม" (เจ้าของถามเอง 2026-09-11 ว่าจะกลมกลืนกับพื้นไหม)
         --ok เป็นเขียวเข้ม วางบนพื้นน้ำเงิน --navy-800 แล้วอัตราส่วนความต่างแค่ ~2.4:1 อ่านไม่ออก
         ตัวนี้วัดได้ ~7.2:1 ผ่านเกณฑ์ AA · ใช้กับจำนวนเงินที่อยู่บนแถบสีเข้มเท่านั้น
    */
    --ok-on-dark: #5fe0a0;
    --warn: #97600f;    --warn-soft: #fdf2e0;
    --danger: #b3312a;  --danger-soft: #fdecea;
    --info: #1d5fa8;    --info-soft: #e6f0fb;

    /*
      ── กราฟงบประมาณ: ใช้ไป / รอตัดจ่าย / คงเหลือ (เจ้าของสั่ง 2026-09-14) ──
      🔴 รอตัดจ่าย = สีเหลือง (เจ้าของระบุเอง) · ใช้ไป = แดง · คงเหลือ = เขียว · ราง = วงเงินงบ
         --warn เป็นน้ำตาลไว้ทำตัวอักษร ใช้ระบายแท่งไม่ได้ จึงแยก token สำหรับพื้นแท่ง
         ตัวอักษรบนพื้นขาวยังใช้ --danger / --warn / --ok ตามเดิม
    */
    --chart-used: #c9443c;
    --chart-reserve: #f2b632;
    --chart-left: #2f9a63;
    --chart-track: var(--surface-3);
    --danger-on-dark: #ffa69e;   /* ตัวเลข "ใช้ไป" บนป้ายพื้นน้ำเงิน — --danger วางบนพื้นเข้มแล้วอ่านไม่ออก */
    --warn-on-dark: #ffd36b;

    /* ── ไอคอนโฟลเดอร์ในเมนู (โทนทองแบบ ERP) ─────────────── */
    --folder: #efb245;
    --folder-dark: #d99a24;

    /* ── รูปทรง · เงา · จังหวะ ────────────────────────────── */
    /* เจ้าของไม่ชอบมุมโค้งมาก (สั่งเมื่อ 2026-09-02) — คุมความโค้งทั้งระบบจาก 3 ค่านี้ */
    --radius-sm: 3px;    /* ปุ่มไอคอน · ป้าย · รายการเมนู · ธงภาษา */
    --radius:    4px;    /* ปุ่ม · ช่องกรอก · การ์ด */
    --radius-lg: 8px;    /* การ์ดใหญ่กลางจอ (หน้าล็อกอิน) */
    --shadow-rgb: 13 35 80;   /* สีฐานของเงา — ใช้ตอนต้องสร้างเงาเองแบบไดนามิก */
    --shadow-sm: 0 1px 2px rgb(13 35 80 / 6%);
    --shadow:    0 2px 10px rgb(13 35 80 / 8%);
    --shadow-lg: 0 24px 60px -28px rgb(13 35 80 / 38%);
    --ease: cubic-bezier(.22, 1, .36, 1);

    /* ── ตัวอักษร ─────────────────────────────────────────── */
    --font: "Segoe UI", "Noto Sans Thai", "Leelawadee UI", system-ui, -apple-system, sans-serif;
    --fs-xs: 11.5px;
    --fs-sm: 12.5px;
    --fs-base: 14px;
    --fs-md: 15px;
    --fs-lg: 18px;
    --fs-xl: 24px;

    /* ── ขนาดโครงหน้า ─────────────────────────────────────── */
    --topbar-h: 58px;
    --toolbar-h: 44px;
    --statusbar-h: 30px;
    --paper-max: 800px;        /* ความกว้างแผ่นกระดาษ — แถบปุ่มเหนือกระดาษอ่านค่าเดียวกันนี้ */
    --sidebar-w: 232px;
    --sidebar-w-collapsed: 52px;
    --submenu-w: 226px;   /* แผงเมนูย่อยของโมดูล — กว้าง 0 ตอนปิด */

    /* ความกว้างสูงสุดของเนื้อหาในหน้า — เนื้อหาถูกจัดกึ่งกลางพื้นที่ที่เหลือเสมอ
       หน้าไหนอยากแคบ/กว้างกว่านี้ ให้ override --page-max ที่ :root ในบล็อก style ของหน้านั้น
       (อย่าเขียนชื่อคำสั่ง Blade ในคอมเมนต์ CSS — Blade อ่านเป็นคำสั่งจริงแล้วเปิด output buffer ค้าง) */
    --page-max: 1180px;
    /* Shared spacing grid and wide analytical pages; dashboard keeps the SBMS shell. */
    --space-unit: 4px;
    --border-width: 1px;
    --dashboard-page-max: 1440px;

    /* ── ลำดับชั้นการซ้อน — ห้ามใส่ตัวเลขมั่วนอกสเกลนี้ ───── */
    --z-sticky: 20;
    --z-scrim: 30;
    --z-drawer: 40;
    --z-dropdown: 50;
    --z-modal: 60;
  }

  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; height: 100%; }

  body {
    font-family: var(--font);
    font-size: var(--fs-base);
    line-height: 1.55;
    color: var(--ink);
    background: var(--page);
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
  }

  a { color: inherit; text-decoration: none; }
  button, input, select, textarea { font: inherit; color: inherit; }
  h1, h2, h3, h4 { margin: 0; text-wrap: balance; }
  p { margin: 0; }

  :focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
    border-radius: var(--radius-sm);
  }

  img { max-width: 100%; }

  /* ── ปุ่ม ─────────────────────────────────────────────── */
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    min-height: 40px; padding: 0 18px;
    border: 1px solid var(--navy-800); border-radius: var(--radius);
    background: var(--navy-800); color: var(--on-dark);
    font-size: var(--fs-base); font-weight: 600; cursor: pointer;
    transition: background .18s var(--ease), border-color .18s var(--ease);
  }
  .btn:hover:not(:disabled) { background: var(--navy-900); border-color: var(--navy-900); }
  .btn:disabled { opacity: .55; cursor: default; }
  .btn-block { width: 100%; }

  .btn-outline {
    background: var(--surface); color: var(--navy-800); border-color: var(--navy-800);
  }
  .btn-outline:hover:not(:disabled) { background: var(--accent-soft); }

  .btn-quiet {
    background: var(--surface); color: var(--ink-soft); border-color: var(--line);
  }
  .btn-quiet:hover:not(:disabled) { background: var(--surface-2); border-color: #c7d2e4; }

  /* ปุ่มของงานที่ลบทิ้ง — แดง ตัวอักษรขาว (เจ้าของสั่ง 2026-09-03) */
  .btn-danger { border-color: var(--danger); background: var(--danger); color: #fff; }
  .btn-danger:hover:not(:disabled) { background: #9c2a24; border-color: #9c2a24; }

  /*
    ปุ่มยืนยันเชิงบวก เช่น "อนุมัติ" (เจ้าของสั่งให้เป็นสีเขียว 2026-09-04)
    🔴 ใช้คู่กับ .btn-danger เสมอ — เขียว = ผ่าน · แดง = ตีกลับ อ่านออกก่อนอ่านตัวหนังสือ
    🔴 เคยนิยามไว้ในหน้า /budget/approval หน้าเดียว หน้าอื่นใส่คลาสแล้วไม่เขียว
       สไตล์ที่ใช้ข้ามหน้าต้องอยู่ที่ธีมกลางเสมอ
  */
  .btn-ok { border-color: var(--ok); background: var(--ok); color: #fff; }
  .btn-ok:hover:not(:disabled) { background: #12603a; border-color: #12603a; }

  /*
    🔴 ต้องมาก่อนทุกกฎที่ตั้ง display เอง
       .field { display: grid } ชนะกฎ [hidden] ของเบราว์เซอร์ (class แรงกว่า UA stylesheet)
       เคยทำให้ช่อง "อื่นๆ" ที่สั่งซ่อนไว้ยังโผล่มาให้เห็น (2026-09-03)
  */
  [hidden] { display: none !important; }

  /* ── ช่องกรอก ─────────────────────────────────────────── */
  .field { display: grid; gap: 6px; }
  .field > label { color: var(--ink-soft); font-size: var(--fs-sm); font-weight: 600; }

  .input-wrap { position: relative; display: flex; align-items: center; }
  .input-wrap > svg.lead {
    position: absolute; left: 14px; width: 18px; height: 18px;
    color: var(--muted); pointer-events: none;
  }
  .input-wrap > .trail {
    position: absolute; right: 8px;
    display: grid; place-items: center; width: 34px; height: 34px;
    border: 0; border-radius: var(--radius-sm); background: transparent;
    color: var(--muted); cursor: pointer;
  }
  .input-wrap > .trail:hover { background: var(--surface-2); color: var(--ink-soft); }

  .input {
    width: 100%; min-height: 52px;
    padding: 0 14px; border: 1px solid var(--line); border-radius: var(--radius);
    background: var(--surface); color: var(--ink);
    transition: border-color .16s var(--ease), box-shadow .16s var(--ease);
  }
  .input::placeholder { color: #9aa6bb; }
  .input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
  .input.has-lead { padding-left: 44px; }
  .input.has-trail { padding-right: 46px; }
  .input.is-invalid { border-color: var(--danger); }

  /* ── รูปพนักงาน — ไม่มีกรอบ (เจ้าของสั่ง 2026-09-03) ─────── */
  .avatar {
    width: 32px; height: 32px; flex: 0 0 auto; padding: 0;
    display: grid; place-items: center; overflow: hidden;
    border: 0; border-radius: 50%;
    background: var(--surface-2); color: var(--navy-800);
    font-size: var(--fs-xs); font-weight: 700; cursor: zoom-in;
    transition: transform .14s var(--ease);
  }
  .avatar:hover, .avatar:focus-visible { transform: translateY(-1px); z-index: 1; }
  .avatar img { width: 100%; height: 100%; object-fit: cover; }
  .avatar-sm { width: 26px; height: 26px; }

  /* กองรูปเรียงกันในตาราง — เว้นห่างพอให้เห็นหน้า ไม่ซ้อนทับจนดูไม่ออก */
  .faces { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }

  /*
    ── ตัวแบ่งหน้า ────────────────────────────────────────
    🔴 อยู่ที่ธีมกลาง ห้ามเขียนซ้ำในหน้าใดหน้าหนึ่ง (ทุกหน้าที่แบ่งหน้าใช้ตัวเดียวกัน)
  */
  .pager {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    margin-top: 14px; color: var(--muted); font-size: var(--fs-sm);
  }

  .pager-info { flex: 1; min-width: 0; }
  .pager-info b { color: var(--navy-900); font-variant-numeric: tabular-nums; }
  .pager-links { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }

  .pg {
    display: grid; place-items: center; min-width: 32px; height: 32px; padding: 0 8px;
    border: 1px solid var(--line); border-radius: var(--radius-sm);
    background: var(--surface); color: var(--ink);
    font-size: var(--fs-sm); font-variant-numeric: tabular-nums; text-decoration: none;
  }

  a.pg:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
  .pg.is-on { border-color: var(--navy-800); background: var(--navy-800); color: #fff; font-weight: 700; }
  .pg.is-off { color: #c3cbd9; }
  .pg.is-gap { border: 0; background: transparent; min-width: 18px; padding: 0; }

  /*
    เลขแดงในเมนู — บอกว่ามีเรื่องค้างกี่เรื่อง (เจ้าของสั่ง 2026-09-07)
    🔴 รูปแบบเดียวกับเลขแดงบนกระดิ่ง จะได้อ่านเป็นภาษาเดียวกันทั้งระบบ
  */
  .nav-badge {
    margin-left: auto; flex: 0 0 auto;
    min-width: 17px; height: 17px; padding: 0 5px;
    display: grid; place-items: center;
    border-radius: 999px; background: #e02424; color: #fff;
    font-size: 10px; font-weight: 700; line-height: 1;
  }

  /*
    เลขแดงประจำแถวในตาราง — ลอยที่ "มุมขวาบน" ของช่องลำดับ (เจ้าของสั่ง 2026-09-07)
    🔴 ต้องเป็น absolute เท่านั้น — ของเดิมวางต่อท้ายในสายเนื้อหา เลขลำดับเลยถูกเบียดจนขยับ
       ช่องลำดับกว้างแค่พอใส่ตัวเลข การเพิ่มอะไรเข้าไปในสายเนื้อหาจึงดันตัวเลขทันที
  */
  /* ยึดกับตัวเลข ไม่ใช่กับช่อง — จะได้ชิดเลขลำดับเสมอ ไม่ว่าช่องจะกว้างเท่าไหร่ */
  .tbl td.col-seq .seq-n { position: relative; display: inline-block; }

  .row-badge {
    /* เกาะขวาบนของตัวเลข — เยื้องออกไปพอไม่ให้ทับตัวเลข แต่ยังอ่านเป็นชุดเดียวกัน */
    position: absolute; top: -5px; right: -15px;
    display: grid; place-items: center;
    min-width: 13px; height: 13px; padding: 0 3px;
    border-radius: 999px; background: #e02424; color: #fff;
    font-size: 8.5px; font-weight: 700; line-height: 1;
  }

  /* ── ป้ายสถานะ ────────────────────────────────────────── */
  .pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 999px;
    font-size: var(--fs-xs); font-weight: 700; line-height: 1.5;
  }
  .pill-ok { background: var(--ok-soft); color: var(--ok); }
  .pill-warn { background: var(--warn-soft); color: var(--warn); }
  .pill-danger { background: var(--danger-soft); color: var(--danger); }
  .pill-info { background: var(--info-soft); color: var(--info); }

  /* ── กล่องแจ้งเตือน ───────────────────────────────────── */
  .flash {
    display: flex; align-items: flex-start; gap: 9px;
    padding: 11px 14px; border: 1px solid var(--line); border-radius: var(--radius);
    background: var(--surface-2); color: var(--ink-soft); font-size: var(--fs-sm);
  }
  .flash-error { border-color: #f0c4c0; background: var(--danger-soft); color: var(--danger); }
  .flash-ok { border-color: #b9e2cd; background: var(--ok-soft); color: var(--ok); }

  /* ── การ์ด ────────────────────────────────────────────── */
  .card {
    border: 1px solid var(--line); border-radius: var(--radius);
    background: var(--surface); box-shadow: var(--shadow-sm);
  }
  .card-head {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 12px 16px; border-bottom: 1px solid var(--line-soft);
  }
  .card-head h3 { font-size: var(--fs-md); font-weight: 700; color: var(--navy-800); }
  .card-body { padding: 16px; }

  /* ── ตาราง — กว้างเกินให้เลื่อนในกรอบตัวเอง ไม่ใช่ทั้งหน้า ── */
  .table-scroll { overflow-x: auto; }
  /*
    ตารางทั้งระบบ (เจ้าของสั่ง 2026-09-03)
      · ขีดเส้นแบ่งคอลัมน์
      · ข้อมูลและหัวคอลัมน์จัดกึ่งกลาง
    🔴 คอลัมน์ที่ต้องชิดซ้าย/ขวาเป็นพิเศษ ให้ใส่คลาส .txt-left / .txt-right ที่ <th>/<td>
       (เช่น ชื่อรายการยาวๆ อ่านง่ายกว่าเมื่อชิดซ้าย · จำนวนเงินอ่านง่ายกว่าเมื่อชิดขวา)
  */
  .tbl { width: 100%; border-collapse: collapse; font-size: var(--fs-sm); }

  /*
    กรอบของตาราง — ตารางที่กว้างเกินต้องเลื่อนในกรอบตัวเอง หน้าเว็บห้ามเลื่อนแนวนอน
    🔴 ต้องอยู่ที่ธีมกลาง (บั๊กจริง 2026-09-18 เจ้าของแจ้งว่าตารางเปรียบเทียบ "ข้อมูลไม่ตรงหัวคอลัมน์" ในมือถือ)
       ของเดิมกฎนี้เขียนซ้ำอยู่ในชิ้นส่วนของ **โมดูล** (สิทธิ์ · ประวัติการใช้งาน · งบประมาณ)
       หน้าที่ไม่ได้ include ชิ้นส่วนพวกนั้นจึงได้ overflow-x: visible ตามค่าเริ่มต้น
       = ส่วนที่ล้นถูก **ตัดทิ้งและเลื่อนไปดูไม่ได้เลย** ไม่ใช่แค่ต้องเลื่อน
       วัดจริงที่ 390px: กรอบ 317px · ตาราง 417px · scrollLeft ค้างที่ 0 → คอลัมน์ "ผลต่าง" หายทั้งคอลัมน์
    (ความกว้างขั้นต่ำของแต่ละตารางยังตั้งเองได้ที่โมดูล เช่น `.tbl-wrap .tbl { min-width: 820px }`)
  */
  .tbl-wrap { overflow-x: auto; }

  .tbl th, .tbl td {
    padding: 9px 12px; text-align: center; vertical-align: middle;
    border-bottom: 1px solid var(--line-soft);
    border-right: 1px solid var(--line-soft);
  }

  .tbl th:last-child, .tbl td:last-child { border-right: 0; }

  /*
    คอลัมน์ "ลำดับ" — มีทุกตารางในระบบ (เจ้าของสั่ง 2026-09-04)
    แคบที่สุดเท่าที่ใส่เลขได้ · ตัวเลขกว้างเท่ากันจะได้เรียงตรงกัน · สีจางกว่าข้อมูลจริง
  */
  .tbl .col-seq { width: 1%; white-space: nowrap; text-align: center; font-variant-numeric: tabular-nums; }

  /*
    🔴 สีจางใช้กับ "ตัวเลขในตาราง" เท่านั้น ห้ามเหมารวมหัวคอลัมน์
       เขียน `.tbl .col-seq { color: muted }` เฉยๆ จะโดนหัวตารางด้วย
       กลายเป็นตัวหนังสือเทาจางบนพื้นน้ำเงิน อ่านยากกว่าหัวคอลัมน์อื่น (เจ้าของแจ้ง 2026-09-04)
  */
  .tbl td.col-seq { color: var(--muted); }

  .tbl .txt-left { text-align: left; }
  .tbl .txt-right { text-align: right; }

  /*
    🔴 หัวคอลัมน์จัดกึ่งกลาง "ทุกคอลัมน์" (เจ้าของสั่ง 2026-09-10)
       .txt-left / .txt-right มีไว้จัดข้อมูลในเซลล์ ไม่ใช่หัวตาราง

    🔴 ต้องเขียนถึง .txt-left / .txt-right ตรงๆ ด้วย
       เพราะ `.tbl .txt-left` มีน้ำหนัก (0,2,0) มากกว่า `.tbl thead th` (0,1,2)
       เขียนแค่ `.tbl thead th { text-align: center }` จะไม่ชนะ หัวคอลัมน์ยังชิดซ้ายเหมือนเดิม
  */
  .tbl thead th,
  .tbl thead th.txt-left,
  .tbl thead th.txt-right { text-align: center; }

  /* ของที่วางในเซลล์กึ่งกลางต้องจัดกลางตามไปด้วย */
  .tbl td .faces, .tbl td .cc-list { justify-content: center; }
  .tbl td .mod, .tbl td .fn-cell { justify-content: flex-start; }
  /*
    หัวตาราง — พื้นน้ำเงิน ตัวอักษรขาว (เจ้าของสั่ง 2026-09-03)
    🔴 ใช้ทุกตารางในระบบ ห้ามเขียนสีหัวตารางเองในหน้าใดหน้าหนึ่ง
  */
  .tbl th {
    position: sticky; top: 0;
    background: var(--navy-800); color: #fff;
    font-size: var(--fs-xs); font-weight: 700; white-space: nowrap;
    border-bottom-color: var(--navy-900);
    border-right-color: rgb(255 255 255 / 22%);
  }

  /* ลิงก์/ปุ่มในหัวตาราง (เช่นปุ่มเรียงลำดับ) ต้องอ่านออกบนพื้นเข้ม */
  .tbl th a, .tbl th button { color: #fff; }
  .tbl tr:last-child td { border-bottom: 0; }

  /*
    แถวสรุปท้ายตาราง (tfoot)
    🐛 บั๊กจริง 2026-09-04: กฎ `tr:last-child` ข้างบนนับ "แถวสุดท้ายของแต่ละกลุ่ม"
       แถวสุดท้ายของ tbody เลยไม่มีเส้นล่าง ตารางที่มีแถวรวมจึงดูเชื่อมติดกันเป็นแถวเดียว
       แก้ด้วยการตีเส้นบนให้ tfoot เอง (ได้ผลทุกเบราว์เซอร์ ไม่ต้องพึ่ง :has)
  */
  .tbl tfoot td {
    border-top: 2px solid var(--line);
    background: var(--surface-2); color: var(--navy-900);
  }

  /* ── ตัวสลับภาษา (ต้องมีทุกหน้า) — ใช้ธงชาติเป็นตัวบอกภาษา ──── */
  .lang {
    position: relative; display: inline-flex; align-items: center; gap: 7px;
    padding: 5px 9px; border: 1px solid transparent; border-radius: var(--radius-sm);
    background: transparent; color: var(--ink-soft);
    font-size: var(--fs-sm); font-weight: 600; cursor: pointer;
  }
  .lang:hover { background: var(--surface-2); border-color: var(--line); }
  /* วางบนแถบสีเข้ม (แถบบนของ Dashboard) — ต้องกลับสีตัวอักษร ไม่งั้นอ่านไม่ออก */
  .lang.on-dark { color: var(--on-dark-soft); }
  .lang.on-dark:hover { background: rgb(255 255 255 / 12%); border-color: transparent; color: var(--on-dark); }

  /* ธงเป็นสัดส่วน 3:2 ทุกใบ — ครอบด้วย object-fit กันธงที่ไฟล์ต้นฉบับสัดส่วนไม่เท่ากันบิดเบี้ยว */
  .flag {
    width: 22px; height: 15px; flex: 0 0 auto;
    object-fit: cover; border: 1px solid var(--line); border-radius: 2px;
    background: var(--surface);
  }
  .lang.on-dark .flag { border-color: var(--line-on-dark); }

  .lang-menu {
    position: absolute; top: calc(100% + 5px); right: 0; z-index: var(--z-dropdown);
    min-width: 150px; padding: 4px;
    border: 1px solid var(--line); border-radius: var(--radius-sm);
    background: var(--surface); box-shadow: var(--shadow);
  }
  .lang-menu button {
    display: flex; align-items: center; gap: 9px;
    width: 100%; padding: 7px 9px;
    border: 0; border-radius: var(--radius-sm); background: transparent;
    color: var(--ink-soft); font-size: var(--fs-sm); text-align: left; cursor: pointer;
  }
  .lang-menu button:hover { background: var(--surface-2); }
  .lang-menu button[aria-current="true"] { color: var(--accent); font-weight: 700; }
  .lang-menu button .code { margin-left: auto; color: var(--muted); font-size: var(--fs-xs); }

  .sr-only {
    position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0;
    overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
  }

  @media (prefers-reduced-motion: reduce) {
    * { animation-duration: .01ms !important; transition-duration: .01ms !important; }
  }
</style>
