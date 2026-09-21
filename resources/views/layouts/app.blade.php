<!DOCTYPE html>
<html lang="th" data-lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  {{-- ใช้ตอนยิงฟอร์มแบบ AJAX — Laravel ต้องการ token ทุกคำขอที่เปลี่ยนข้อมูล --}}
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}">
  @include('layouts.theme')
  <style>
    /* ───────────────────────────────────────────────────────────
       โครงหน้าหลัก — แถบบน / แถบเครื่องมือ / เมนูซ้าย / เนื้อหา / แถบสถานะ
       ยึดสัดส่วนตาม token ใน theme.blade.php ห้ามใส่ตัวเลขดิบ
       ─────────────────────────────────────────────────────────── */
    body { overflow: hidden; }

    /*
      คอลัมน์กลาง = แผงเมนูย่อยของโมดูล ปกติกว้าง 0
      พอเปิดแผง คอลัมน์นี้กางออก แถบเครื่องมือกับเนื้อหาจึงถูกดันไปทางขวาเองทั้งแถว
    */
    .shell {
      display: grid;
      /*
        🔴 ต้องเป็น dvh ไม่ใช่ vh (บั๊กจริง 2026-09-18 เจ้าของแจ้ง "เลื่อนดูข้อมูลล่างสุดไม่ได้ เหมือนติดขอบจอ")
           บนมือถือ 100vh = ความสูง "ตอนแถบที่อยู่ของเบราว์เซอร์หุบ" ซึ่งสูงกว่าที่เห็นจริงเสมอ
           โครงหน้าจึงยาวเกินจอ ท้ายเนื้อหากับแถบสถานะไหลลงไปใต้ขอบจอ
           และ body สั่ง overflow: hidden ไว้ จึงเลื่อนตามลงไปดูไม่ได้เลย
           เขียน vh ไว้ก่อนเป็นตัวสำรองของเบราว์เซอร์เก่าที่ยังไม่รู้จัก dvh
      */
      height: 100vh;
      height: 100dvh;
      grid-template-rows: var(--topbar-h) var(--toolbar-h) 1fr var(--statusbar-h);
      grid-template-columns: var(--sidebar-w) 0 1fr;
      grid-template-areas:
        "top     top     top"
        "menubar submenu toolbar"
        "sidebar submenu content"
        "status  status  status";
      transition: grid-template-columns .18s var(--ease);
    }
    .shell.is-collapsed { grid-template-columns: var(--sidebar-w-collapsed) 0 1fr; }
    .shell.is-submenu-open { grid-template-columns: var(--sidebar-w) var(--submenu-w) 1fr; }
    .shell.is-collapsed.is-submenu-open { grid-template-columns: var(--sidebar-w-collapsed) var(--submenu-w) 1fr; }
    /* ตอนกู้สถานะแผงคืนตอนโหลดหน้า ไม่ต้องเล่นอนิเมชัน ไม่งั้นจะวูบทุกครั้งที่เปลี่ยนหน้า */
    .shell.no-anim, .shell.no-anim .submenu { transition: none !important; }

    /* ── แถบบน ────────────────────────────────────────────── */
    .topbar {
      grid-area: top;
      display: flex; align-items: center; justify-content: space-between; gap: 16px;
      padding: 0 16px;
      background: linear-gradient(90deg, var(--navy-900) 0%, var(--navy-700) 100%);
      color: var(--on-dark);
    }
    .topbar-brand { display: flex; align-items: baseline; gap: 10px; min-width: 0; }
    .topbar-brand b { font-size: 25px; font-weight: 800; letter-spacing: -.4px; }
    .topbar-brand span { color: var(--on-dark-soft); font-size: var(--fs-sm); }
    .topbar-right { display: flex; align-items: center; gap: 6px; }

    .icon-btn {
      position: relative; display: grid; place-items: center;
      width: 36px; height: 36px;
      border: 0; border-radius: var(--radius-sm); background: transparent;
      color: var(--on-dark); cursor: pointer;
    }
    .icon-btn:hover { background: rgb(255 255 255 / 12%); }
    .badge {
      position: absolute; top: 3px; right: 3px;
      min-width: 17px; height: 17px; padding: 0 4px;
      display: grid; place-items: center;
      border-radius: 999px; background: #e63946; color: #fff;
      font-size: 10.5px; font-weight: 700;
    }
    .topbar-sep { width: 1px; height: 26px; background: var(--line-on-dark); margin: 0 4px; }

    .who {
      display: flex; align-items: center; gap: 9px;
      padding: 5px 9px; border: 0; border-radius: var(--radius-sm);
      background: transparent; color: var(--on-dark); cursor: pointer;
    }
    .who:hover { background: rgb(255 255 255 / 12%); }
    .who-avatar {
      width: 32px; height: 32px; flex: 0 0 auto;
      display: grid; place-items: center; overflow: hidden;
      border: 1px solid var(--line-on-dark); border-radius: 50%;
      background: rgb(255 255 255 / 14%); color: var(--on-dark);
      font-size: var(--fs-sm); font-weight: 700;
    }
    .who-avatar img { width: 100%; height: 100%; object-fit: cover; }
    /* รูปโปรไฟล์เป็นปุ่มกดขยายได้ ต้องมีสัญญาณว่ากดได้ (cursor + ขอบสว่างตอน hover) */
    .who-avatar.is-btn { padding: 0; border-style: solid; cursor: zoom-in; transition: border-color .16s var(--ease), transform .16s var(--ease); }
    .who-avatar.is-btn:hover, .who-avatar.is-btn:focus-visible { border-color: rgb(255 255 255 / 65%); transform: scale(1.06); }
    .who-text { display: grid; text-align: left; line-height: 1.25; min-width: 0; }
    .who-text b { font-size: var(--fs-sm); font-weight: 600; }
    .who-text span { color: var(--on-dark-soft); font-size: var(--fs-xs); }

    .who-menu {
      position: absolute; top: calc(100% + 6px); right: 0; z-index: var(--z-dropdown);
      min-width: 190px; padding: 5px;
      border: 1px solid var(--line); border-radius: var(--radius-sm);
      background: var(--surface); box-shadow: var(--shadow);
    }
    .who-menu a, .who-menu button {
      display: flex; align-items: center; gap: 9px; width: 100%;
      padding: 8px 10px; border: 0; border-radius: var(--radius-sm);
      background: transparent; color: var(--ink-soft);
      font-size: var(--fs-sm); text-align: left; cursor: pointer;
    }
    .who-menu a:hover, .who-menu button:hover { background: var(--surface-2); }
    .who-menu .danger { color: var(--danger); }

    /* ── หัวเมนู (อยู่เหนือ sidebar) ──────────────────────── */
    .menubar {
      grid-area: menubar;
      display: flex; align-items: center; justify-content: space-between; gap: 8px;
      padding: 0 10px 0 12px;
      border-right: 1px solid var(--line); border-bottom: 1px solid var(--line);
      background: var(--surface-2);
    }
    .menubar-title { display: flex; align-items: center; gap: 8px; color: var(--navy-800); font-size: var(--fs-base); font-weight: 700; }
    /* ไอคอน ☰ ในปุ่มเป็นของจอมือถือเท่านั้น — จอกว้างใช้ลูกศรย่อเมนู */
    .menubar button .ic-burger { display: none; }
    .menubar-title svg { color: var(--accent); }
    .menubar button {
      display: grid; place-items: center; width: 28px; height: 28px; flex: 0 0 auto;
      border: 0; border-radius: var(--radius-sm); background: transparent;
      color: var(--muted); cursor: pointer;
    }
    .menubar button:hover { background: var(--line-soft); color: var(--ink); }
    .menubar button svg { transition: transform .18s var(--ease); }

    /*
      ตอนย่อเมนู แถบหัวเมนูเหลือกว้างแค่ 52px
      ถ้าปล่อยให้ทั้งชื่อและปุ่มอยู่แบบ space-between จะเบียดกันจนล้น
      จึงซ่อนชื่อทิ้งแล้วจัดปุ่มไว้กลางแถบ และกลับหัวลูกศรให้ชี้ออก (« -> »)
    */
    .shell.is-collapsed .menubar { justify-content: center; padding: 0; }
    .shell.is-collapsed .menubar-title { display: none; }
    .shell.is-collapsed .menubar button svg { transform: scaleX(-1); }

    /* ── แถบเครื่องมือ ────────────────────────────────────── */
    .toolbar {
      grid-area: toolbar;
      display: flex; align-items: center; gap: 2px;
      padding: 0 10px;
      border-bottom: 1px solid var(--line); background: var(--surface-2);
      /* 🔴 ห้ามใส่ overflow ที่นี่ — จะไปตัดกล่องช่วยเหลือที่ลอยออกมานอกแถบทิ้ง
         ของที่ยาวเกินให้จัดการในตัวมันเอง (เช่น .crumb ตัดด้วย ellipsis) */
    }
    .tool {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 6px 11px; border: 0; border-radius: var(--radius-sm);
      background: transparent; color: var(--ink-soft);
      font-size: var(--fs-sm); font-weight: 600; white-space: nowrap; cursor: pointer;
    }
    .tool:hover { background: var(--accent-soft); color: var(--navy-800); }
    /* ไอคอนเป็นภาพ ไม่ใช่ SVG จึงกำหนดขนาดคงที่ กันภาพยืดตอนโหลดช้า */
    .tool-icon { width: 20px; height: 20px; flex: 0 0 auto; object-fit: contain; }
    .tool-sep { width: 1px; height: 22px; background: var(--line); margin: 0 6px; }
    .tool-wrap { position: relative; display: inline-flex; }

    /* ── ปุ่มยุบแถบเครื่องมือ (ยุบไปทางซ้าย) ──────────────── */
    .tool-collapse {
      flex: 0 0 auto; margin: 0 4px;
      display: grid; place-items: center; width: 26px; height: 26px;
      border: 0; border-radius: var(--radius-sm); background: transparent;
      color: var(--muted); cursor: pointer;
    }
    .tool-collapse:hover { background: var(--line-soft); color: var(--ink); }
    .tool-collapse svg { transition: transform .18s var(--ease); }

    /*
      ยุบแล้วปุ่มทั้ง 3 หายไปทางซ้าย เหลือแค่ปุ่มกดกลับ
      ความสูงแถวเท่าเดิม เพราะแถวนี้ใช้ร่วมกับหัวเมนูซ้ายและมี breadcrumb อยู่ด้วย
    */
    .shell.is-toolbar-hidden .tool,
    .shell.is-toolbar-hidden .tool-wrap { display: none; }
    .shell.is-toolbar-hidden .tool-collapse svg { transform: scaleX(-1); }

    /* ── เส้นทางของหน้า (breadcrumb) ──────────────────────── */
    /*
      วางค่อนไปทางซ้าย ไม่ถึงกึ่งกลาง — เว้นจากปุ่มพอไม่ให้ดูติดกัน
      margin-right: auto ดันที่ว่างไปกองทางขวา
      อยู่ในคอลัมน์เดียวกับแถบเครื่องมือ พอย่อ/ปิดเมนูซ้าย จึงขยับตามเองไม่ต้องใช้ JS
    */
    .crumb {
      display: flex; align-items: center; gap: 7px; flex-wrap: nowrap;
      margin-left: clamp(16px, 6vw, 90px); margin-right: auto;
      padding-right: 12px; overflow: hidden;
      color: var(--muted); font-size: var(--fs-sm); white-space: nowrap;
    }
    .crumb a { color: var(--ink-soft); transition: color .14s var(--ease); }
    .crumb a:hover, .crumb a:focus-visible { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }
    .crumb .crumb-sep { color: var(--ink-soft); font-weight: 600; }
    .crumb .crumb-current { color: var(--navy-800); font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
    .crumb .crumb-plain { color: var(--muted); }

    @media (max-width: 720px) { .crumb { display: none; } }

    /* ── กล่องช่วยเหลือ — หน้าต่างเล็กแบบช่องแชท ─────────── */
    .help-pop {
      position: absolute; top: calc(100% + 8px); left: 0; z-index: var(--z-dropdown);
      width: 286px; overflow: hidden;
      border: 1px solid var(--line); border-radius: var(--radius);
      background: var(--surface); box-shadow: var(--shadow-lg);
    }
    /* หัวกล่องสีเข้มเหมือนหัวหน้าต่างแชท ให้รู้ทันทีว่านี่คือกล่องคุยกับใครสักคน */
    .help-head {
      display: flex; align-items: center; justify-content: space-between; gap: 10px;
      padding: 9px 10px 9px 13px;
      background: var(--navy-800); color: var(--on-dark);
      font-size: var(--fs-sm); font-weight: 600;
    }
    .help-close {
      display: grid; place-items: center; width: 22px; height: 22px; flex: 0 0 auto;
      border: 0; border-radius: var(--radius-sm); background: transparent;
      color: var(--on-dark-soft); cursor: pointer;
    }
    .help-close:hover { background: rgb(255 255 255 / 16%); color: var(--on-dark); }

    .help-list { display: grid; margin: 0; padding: 0; list-style: none; }
    .help-list li {
      display: grid; gap: 2px;
      padding: 10px 14px; border-bottom: 1px solid var(--line-soft);
    }
    .help-list li:last-child { border-bottom: 0; }
    .help-list b { color: var(--ink); font-size: var(--fs-sm); font-weight: 600; }
    .help-list a {
      color: var(--accent); font-size: var(--fs-sm);
      overflow: hidden; text-overflow: ellipsis;
    }
    .help-list a:hover { text-decoration: underline; text-underline-offset: 2px; }

    /* ── เมนูซ้าย ─────────────────────────────────────────── */
    .sidebar {
      grid-area: sidebar; overflow: hidden auto;
      padding: 6px 0 10px;
      border-right: 1px solid var(--line); background: var(--surface);
    }
    .shell.is-collapsed .sidebar { overflow: hidden; }

    .nav-root { padding: 4px 0; }
    .nav-empty { padding: 16px 12px; color: var(--muted); font-size: var(--fs-sm); text-align: center; }
    .shell.is-collapsed .nav-empty { display: none; }

    /* ── หัวข้อหลัก ── เส้นคั่นระหว่างกลุ่ม ให้เห็นชัดว่าแต่ละกลุ่มจบตรงไหน */
    .nav-group { border-bottom: 1px solid var(--line-soft); }
    .nav-group:last-child { border-bottom: 0; }
    .nav-group > summary {
      display: flex; align-items: center; gap: 8px;
      padding: 9px 10px;
      color: var(--navy-800); font-size: var(--fs-sm); font-weight: 700;
      cursor: pointer; list-style: none;
    }
    .nav-group > summary::-webkit-details-marker { display: none; }
    .nav-group > summary:hover { background: var(--surface-2); }
    .nav-group[open] > summary { background: var(--surface-2); }
    .nav-caret { flex: 0 0 auto; color: var(--muted); transition: transform .24s var(--ease); }
    .nav-group[open] > summary .nav-caret { transform: rotate(90deg); }

    /*
      ── หัวข้อย่อย ── วาดเป็นผังต้นไม้: เส้นตั้ง + กิ่งแนวนอนของแต่ละแถว
      แต่ละแถววาดเส้นตั้งของตัวเอง (::before) แถวสุดท้ายวาดแค่ครึ่งบน
      เส้นจึงจบพอดีที่กิ่งสุดท้าย ไม่ห้อยเลยลงไปเฉยๆ
    */
    .nav-items { display: grid; gap: 0; padding: 6px 8px 8px 26px; }
    .nav-item {
      position: relative;
      display: flex; align-items: center; gap: 8px;
      padding: 6px 8px; border-radius: var(--radius-sm);
      color: var(--ink-soft); font-size: var(--fs-sm); cursor: pointer;
    }
    .nav-item::before {   /* เส้นตั้ง */
      content: ''; position: absolute; left: -8px; top: 0; bottom: 0;
      width: 1px; background: var(--line-dark);
    }
    .nav-item:last-child::before { bottom: 50%; }
    .nav-item::after {    /* กิ่งแนวนอนที่แตกไปหาแถว */
      content: ''; position: absolute; left: -8px; top: 50%;
      width: 8px; height: 1px; background: var(--line-dark);
    }
    /* โมดูลที่เปิดแผงย่อยได้เป็น <button> ต้องล้างสไตล์ปุ่มให้เหมือนลิงก์รายการอื่น */
    button.nav-item {
      width: 100%; border: 0; background: transparent;
      font: inherit; font-size: var(--fs-sm); text-align: left;
    }
    .nav-item:hover { background: var(--surface-2); color: var(--ink); }
    .nav-item.is-active { background: var(--accent-soft); color: var(--navy-800); font-weight: 700; }
    .nav-item.is-soon { color: var(--muted); }

    /* ⚪ ไม่มีสิทธิ์เข้าโมดูล — ยังเห็นชื่อได้ แต่จางลงและกดไม่ติด (เจ้าของสั่ง 2026-09-03)
       ไม่ซ่อนทิ้ง เพราะอยากให้ผู้ใช้รู้ว่าระบบมีโมดูลอะไรบ้างแล้วไปขอสิทธิ์ถูกที่ */
    .nav-item.is-denied {
      color: var(--muted); cursor: not-allowed; opacity: .62;
    }
    .nav-item.is-denied:hover { background: transparent; color: var(--muted); }
    .nav-item.is-denied .nav-icon { filter: grayscale(1); opacity: .55; }
    .nav-lock { margin-left: auto; flex: 0 0 auto; color: var(--muted); }
    /* ลูกศรบอกว่ากดแล้วมีเมนูกางออกไปทางขวา */
    .nav-more { margin-left: auto; flex: 0 0 auto; color: var(--muted); }
    .nav-item.is-active .nav-more { color: var(--accent); }
    /* ข้อความ "ยังไม่มีโมดูล" ต้องเยื้องเท่ารายการเมนู ไม่งั้นดูเหมือนหลุดออกมานอกกลุ่ม */
    .nav-none { padding: 4px 8px; color: var(--muted); font-size: var(--fs-sm); font-style: italic; }
    /* ขนาดไอคอนเมนู — เจ้าของขอให้ใหญ่ขึ้นอีกนิด (2026-09-02) */
    .nav-icon { width: 22px; height: 22px; flex: 0 0 auto; object-fit: contain; }
    .nav-group > summary .nav-icon { width: 24px; height: 24px; }
    .nav-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .nav-state { margin-left: auto; }

    /* ย่อเมนู: เหลือแค่ไอคอน ซ่อนตัวหนังสือทั้งหมด */
    .shell.is-collapsed .nav-label,
    .shell.is-collapsed .nav-state,
    .shell.is-collapsed .nav-caret,
    .shell.is-collapsed .nav-items { display: none; }
    .shell.is-collapsed .nav-group > summary { justify-content: center; padding: 8px 0; }

    /* ── แผงเมนูย่อยของโมดูล ──────────────────────────────── */
    .submenu {
      grid-area: submenu; overflow: hidden auto;
      border-right: 1px solid var(--line); background: var(--surface);
      /* เลื่อนเข้า-ออกให้เห็นการเคลื่อนไหว ไม่ใช่โผล่มาทันที (เจ้าของสั่ง 2026-09-03)
         ใช้ visibility แทน display:none เพราะ display เปลี่ยนแบบทันที ทรานซิชันจะไม่ทำงาน
         ตัวคอลัมน์ของ grid กว้าง 0 อยู่แล้วตอนปิด เนื้อหาจึงไม่ล้นออกมา */
      transition: opacity .18s var(--ease), transform .22s var(--ease), visibility .22s;
    }
    .shell:not(.is-submenu-open) .submenu {
      opacity: 0; visibility: hidden; transform: translateX(-12px); border-right-color: transparent;
    }
    .shell.is-submenu-open .submenu { opacity: 1; visibility: visible; transform: none; }

    .submenu-head {
      display: flex; align-items: center; gap: 9px;
      height: var(--toolbar-h); padding: 0 8px 0 12px;
      background: var(--navy-800); color: var(--on-dark);
    }
    .submenu-head .nav-icon { width: 22px; height: 22px; }
    .submenu-title {
      flex: 1; min-width: 0;
      font-size: var(--fs-sm); font-weight: 700;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .submenu-close {
      display: grid; place-items: center; width: 26px; height: 26px; flex: 0 0 auto;
      border: 0; border-radius: var(--radius-sm); background: transparent;
      color: var(--on-dark-soft); cursor: pointer;
    }
    .submenu-close:hover { background: rgb(255 255 255 / 16%); color: var(--on-dark); }

    /*
      หัวข้อหลักในเมนูซ้าย — กาง/พับแบบเลื่อน

      🔴 เคยคุมความสูงเป็น px ด้วย JS แล้วมีปัญหา (เจ้าของแจ้ง 2026-09-03)
         เพราะต้องอ่าน scrollHeight ตอนที่ <details> ยังปิดอยู่ ซึ่งได้ค่า 0 บ้าง ไม่ตรงบ้าง
         เปลี่ยนมาใช้ grid-template-rows 0fr -> 1fr ซึ่งเบราว์เซอร์คำนวณความสูงให้เอง
         JS เหลือหน้าที่แค่หน่วงการปิด <details> ไว้จนอนิเมชันจบ
    */
    .nav-group > .nav-items {
      display: grid; grid-template-rows: 1fr;
      transition: grid-template-rows .24s var(--ease), opacity .18s var(--ease);
    }
    .nav-group > .nav-items > * { min-height: 0; }

    /* ระหว่างกำลังพับ — บีบแถวลงจนเป็นศูนย์ */
    .nav-group.is-closing > .nav-items { grid-template-rows: 0fr; opacity: 0; }

    /* ตอนเพิ่งกางออก — เริ่มจากศูนย์แล้วค่อยขยาย */
    .nav-group.is-opening > .nav-items { grid-template-rows: 0fr; opacity: 0; }

    /* ตอนกำลังพับ ลูกศรหมุนกลับพร้อมกับเนื้อหาที่หุบลง (กฎหมุนปกติอยู่ที่ .nav-caret ด้านบน) */
    .nav-group.is-closing > summary .nav-caret { transform: rotate(0deg); }

    .submenu-items { display: grid; gap: 1px; padding: 8px; }
    .submenu-item {
      display: flex; align-items: center; gap: 9px;
      padding: 8px 10px; border-radius: var(--radius-sm);
      color: var(--ink-soft); font-size: var(--fs-sm);
    }
    .submenu-item:hover { background: var(--surface-2); color: var(--ink); }
    .submenu-item.is-active { background: var(--accent-soft); color: var(--navy-800); font-weight: 700; }
    .submenu-item.is-soon { color: var(--muted); }
    /* ⚪ ไม่มีสิทธิ์ในหัวข้อย่อย — เทาลงและกดไม่ได้ เหมือนกติกาของโมดูล */
    .submenu-item.is-denied { color: var(--muted); cursor: not-allowed; opacity: .62; }
    .submenu-item.is-denied:hover { background: transparent; }
    .submenu-item.is-denied .nav-icon { filter: grayscale(1); opacity: .55; }

    @media (prefers-reduced-motion: reduce) {
      .shell, .submenu, .nav-group > .nav-items { transition: none; }
      .shell:not(.is-submenu-open) .submenu { transform: none; }
    }

    /* ── เนื้อหา ──────────────────────────────────────────── */
    .content { grid-area: content; overflow: auto; padding: 20px 26px 26px; }
    @media (max-width: 860px) { .content { padding: 16px; } }

    /*
      กรอบเนื้อหาของทุกหน้า — จัดกึ่งกลางพื้นที่ที่เหลือจากเมนูเสมอ
      พอย่อ/ปิดเมนูซ้าย พื้นที่กว้างขึ้น เนื้อหาจะเลื่อนมาอยู่กลางเองอัตโนมัติ
      (ไม่ต้องเขียน JS อะไร แค่ margin auto ในพื้นที่ grid ที่หดขยายอยู่แล้ว)
    */
    .page { width: 100%; max-width: var(--page-max); margin-inline: auto; }

    /* ── แถบสถานะล่าง ─────────────────────────────────────── */
    .statusbar {
      grid-area: status;
      display: flex; align-items: center; justify-content: space-between; gap: 16px;
      padding: 0 14px; overflow-x: auto;
      border-top: 1px solid var(--line); background: var(--surface-3);
      color: var(--muted); font-size: var(--fs-xs); white-space: nowrap;
    }
    .statusbar-left { display: flex; align-items: center; gap: 7px; }
    .statusbar-right { display: flex; align-items: center; gap: 12px; }
    .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ok); }
    .statusbar .sep { color: var(--line); }

    /* ── จอแคบ: เมนูกลายเป็นลิ้นชักซ้อนทับ ────────────────── */
    @media (max-width: 860px) {
      /*
        🔴 เขียนให้ครบทั้ง 3 คอลัมน์ (เจ้าของสั่งจัดหน้ามือถือ 2026-09-18)
           ของเดิมเขียนแค่ 2 ค่า คอลัมน์ที่ 3 จึงตกไปเป็น auto และแผงเมนูย่อย
           (คอลัมน์กลาง) กลายเป็น 1fr ซึ่งพร้อมจะแย่งที่ของเนื้อหาเมื่อเปิดแผง
      */
      .shell,
      .shell.is-collapsed,
      .shell.is-submenu-open,
      .shell.is-collapsed.is-submenu-open { grid-template-columns: 0 0 minmax(0, 1fr); }

      /* แผงเมนูย่อยเป็นลิ้นชักซ้อนทับเหมือนเมนูซ้าย — ไม่ดันเนื้อหาให้แคบลง */
      .submenu {
        position: fixed; top: calc(var(--topbar-h) + var(--toolbar-h));
        bottom: var(--statusbar-h); left: 0; z-index: var(--z-drawer);
        width: min(320px, 86vw); box-shadow: var(--shadow-lg);
      }
      .menubar { position: fixed; z-index: var(--z-drawer); }
      .sidebar {
        position: fixed; top: calc(var(--topbar-h) + var(--toolbar-h));
        bottom: var(--statusbar-h); left: 0; z-index: var(--z-drawer);
        width: var(--sidebar-w); box-shadow: var(--shadow-lg);
        transform: translateX(-100%); transition: transform .2s var(--ease);
      }
      .shell.is-drawer-open .sidebar { transform: none; }
      .menubar { left: 0; width: var(--sidebar-w); }
      .toolbar { grid-column: 1 / -1; }
    }

    /*
      ══════════════════════════════════════════════════════════════
       จอมือถือ (≤560px) — จัดแถบบน/แถบเมนู/แถบสถานะใหม่ทั้งชุด
       เจ้าของแจ้ง 2026-09-18: "เข้าผ่านโทรศัพท์ ไม่ชอบการจัดเรียงเลย"

       วัดจริงที่ 390px ก่อนแก้: ชื่อระบบถูกบีบเหลือกว้าง 24px แล้ว wrap สูง 93px
       ล้นออกนอกแถบบนที่สูง 58px ไปทับแถบเมนูและแถบเครื่องมือ · กล่องผู้ใช้เหลือ 74px
       ตกบรรทัดทับรูปโปรไฟล์ · แถบเมนูสีขาวทับแถบบนน้ำเงิน 232×29px

       🔴 หลักที่ใช้: บนมือถือเก็บแต่ "ของที่กดได้" ตัดของที่เป็นแค่คำอธิบายออก
          ไม่ย่อตัวอักษรให้เล็กลงเรื่อยๆ เพราะจะกลายเป็นอ่านไม่ออกทั้งหมด
      ══════════════════════════════════════════════════════════════
    */
    @media (max-width: 560px) {
      /*
        แถบเมนูยุบเข้าไปอยู่ "ในแถบบน" เหลือปุ่ม ☰ ปุ่มเดียว
        🔴 ของเดิมเป็นแถบสีขาวกว้าง 232px ลอยทับแถบบนน้ำเงิน (วัดได้ 232×29px)
      */
      .menubar {
        top: 0; left: 0; height: var(--topbar-h); width: auto;
        padding: 0 6px 0 10px; justify-content: flex-start;
        background: transparent; border: 0;
      }
      .menubar-title { display: none; }
      .menubar button { width: 38px; height: 38px; color: var(--on-dark); }
      .menubar button:hover { background: rgb(255 255 255 / 12%); }
      .menubar button .ic-collapse { display: none; }
      .menubar button .ic-burger { display: block; }
      /* ย่อ/กางเมนูบนมือถือคือลิ้นชัก ไม่ใช่การย่อ จึงห้ามพลิกไอคอนตามสถานะ */
      .shell.is-collapsed .menubar button svg { transform: none; }

      /* เว้นที่ให้ปุ่ม ☰ ที่มาลอยอยู่ซ้ายสุดของแถบบน */
      .topbar { padding: 0 8px 0 56px; gap: 8px; }

      /*
        ชื่อเต็มของระบบตัดออก — เป็นคำอธิบาย ไม่ใช่ของที่กดได้
        และมันคือตัวที่ถูกบีบจนล้นแถบไปทับของอื่น
      */
      .topbar-brand span { display: none; }
      .topbar-brand b { font-size: 20px; }

      /* กล่องผู้ใช้เหลือรูป — ชื่อ/ตำแหน่งดูได้ในเมนูที่กดจากรูป */
      .who-text { display: none; }
      .who { padding: 4px 6px; }
      .topbar-sep { display: none; }

      /* ตัวสลับภาษาเหลือธง (ยังบอกภาษาปัจจุบันได้ด้วยตัวธงเอง) */
      .lang [data-lang-label] { display: none; }

      /*
        แถบสถานะล่างเหลือ "พร้อมใช้งาน + เวลา"
        🔴 ของเดิมกว้าง 476px ในจอ 390px จึงถูกตัดข้อความกลางประโยค
           ชื่อผู้ใช้/บริษัทดูได้จากเมนูผู้ใช้อยู่แล้ว ไม่ต้องย้ำที่แถบล่าง
      */
      .statusbar { padding: 0 10px; }
      .statusbar-right > span:not([data-clock]),
      .statusbar-right .sep { display: none; }

      /* เนื้อหาชิดขอบน้อยลงหน่อย ให้ได้ความกว้างคืนมาใช้
         เผื่อขอบล่างของเครื่องที่มีแถบลากกลับหน้าหลัก ไม่ให้ทับบรรทัดสุดท้าย */
      .content { padding: 12px; padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px)); }

      /*
        แผงที่กางจากแถบบน/แถบเครื่องมือ — ช่วยเหลือ · เมนูผู้ใช้ · ภาษา
        🔴 บนมือถือต้องกางเต็มความกว้างจอ ไม่ใช่ห้อยจากปุ่ม (บั๊กจริง 2026-09-18)
           ของเดิมยึดขอบปุ่ม (right: 0 / left: 0) แล้วกว้างคงที่ 190–286px
           ปุ่มอยู่กลางแถบ แผงจึงยื่นเลยขอบจอแล้วถูกตัดจนอ่านไม่ครบ
        แผงกระดิ่งอยู่ในไฟล์ของตัวเอง (partials/bell) เพราะ CSS ของมันมาทีหลังในเอกสาร
        เขียนไว้ที่นี่จะแพ้ลำดับ cascade
      */
      .who-menu, .lang-menu {
        position: fixed; top: calc(var(--topbar-h) + 4px);
        left: 8px; right: 8px; width: auto; min-width: 0;
        max-height: calc(100dvh - var(--topbar-h) - 16px); overflow: auto;
      }
      .help-pop {
        position: fixed; top: calc(var(--topbar-h) + var(--toolbar-h) + 4px);
        left: 8px; right: 8px; width: auto;
        max-height: calc(100dvh - var(--topbar-h) - var(--toolbar-h) - 16px); overflow: auto;
      }
    }

    /*
      ── จอมือถือ: แถบเครื่องมือเหลือ "ไอคอน" ────────────────────
      🐛 วัดจริงที่ความกว้าง 390px (2026-09-18): หน้าล้นแนวนอน 9px
         ต้นเหตุคือปุ่มยุบแถบเครื่องมือ (.tool-collapse) ที่ยื่นเกินขอบจอ
         เพราะปุ่มทั้ง 4 มีป้ายข้อความและ white-space: nowrap รวมกันแล้วกว้าง ~399px
      🔴 แก้ด้วยการซ่อน "ป้าย" ไม่ใช่ซ่อนปุ่ม — ทุกปุ่มยังกดได้ครบเหมือนเดิม
         และห้ามใส่ overflow ที่ .toolbar (จะไปตัดกล่องช่วยเหลือที่ลอยออกมานอกแถบ)
      🔴 ป้ายที่ซ่อนย้ายไปอยู่ใน title ทุกปุ่ม (2 ภาษาผ่าน data-i18n-title)
         ไม่งั้นคนที่ใช้โปรแกรมอ่านหน้าจอจะเจอปุ่มที่ไม่มีชื่อ
    */
    @media (max-width: 420px) {
      .tool span { display: none; }
      .tool { padding: 6px 8px; }
      .toolbar { padding: 0 6px; }
    }
  </style>
  @stack('page-style')
</head>
<body>
  @php
    // หน้าแรกของสายเมนูที่กำลังอยู่ — ใช้ทั้งปุ่ม "หน้าแรก" ปุ่ม "กลับ" และการกางแผงเมนูย่อย
    $section = \App\Support\NavMenu::sectionFor(request()->route()?->getName());
    $sectionHome = $section && \Illuminate\Support\Facades\Route::has($section['route'])
      ? route($section['route'])
      : route('guide');
    $sectionHomeLabel = $section ? $section['th'] : 'หน้าแรก';
  @endphp

  {{-- data-section-module = โมดูลของสายที่หน้านี้สังกัด ใช้บอก JS ว่าให้กางแผงเมนูย่อยอันไหน --}}
  <div class="shell" id="shell" data-section-module="{{ $section['module']['id'] ?? '' }}">

    {{-- ───────── แถบบน ───────── --}}
    <header class="topbar">
      <div class="topbar-brand">
        <b>{{ config('app.name') }}</b>
        <span data-i18n="app.full">Supavut Business Management System</span>
      </div>

      <div class="topbar-right">
        {{-- กระดิ่งแจ้งเตือน — ตัวเลขแดงมุมขวาบน · กดแล้วกางเป็น โมดูล > หัวข้อย่อย > เรื่อง --}}
        @include('layouts.partials.bell')

        <span class="topbar-sep"></span>

        {{--
          แยกรูปโปรไฟล์ออกจากปุ่มเปิดเมนูโดยตั้งใจ
          กดที่รูป = ขยายรูป · กดที่ชื่อ = เปิดเมนู — ถ้ารวมเป็นปุ่มเดียวจะทำทั้ง 2 อย่างไม่ได้
        --}}
        @if ($me->avatarUrl())
          <button type="button" class="who-avatar is-btn" data-zoomable
                  data-zoom-src="{{ $me->avatarUrl() }}" data-zoom-alt="{{ $me->displayName('th') }}"
                  aria-label="ดูรูปโปรไฟล์ขนาดใหญ่" data-i18n-aria="top.viewPhoto">
            <img src="{{ $me->avatarUrl() }}" alt="" loading="lazy" data-avatar data-initial="{{ $me->initial() }}">
          </button>
        @else
          <span class="who-avatar" aria-hidden="true">{{ $me->initial() }}</span>
        @endif

        <div style="position:relative">
          <button type="button" class="who" data-who-toggle aria-haspopup="true">
            <span class="who-text">
              <b data-loc-th="{{ $me->displayName('th') }}" data-loc-en="{{ $me->displayName('en') }}">{{ $me->displayName('th') }}</b>
              <span data-loc-th="{{ $me->position ?: ($me->isAdmin() ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน') }}"
                    data-loc-en="{{ $me->position ?: ($me->isAdmin() ? 'Administrator' : 'User') }}">{{ $me->position ?: ($me->isAdmin() ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน') }}</span>
            </span>
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="m6 9 6 6 6-6"></path>
            </svg>
          </button>

          <div class="who-menu" data-who-menu hidden>
            <a href="{{ route('profile') }}">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"></path>
              </svg>
              <span data-i18n="top.profile">ข้อมูลส่วนตัว</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="danger">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="m16 17 5-5-5-5"></path><path d="M21 12H9"></path>
                </svg>
                <span data-i18n="top.signOut">ออกจากระบบ</span>
              </button>
            </form>
          </div>
        </div>

        <span class="topbar-sep"></span>

        {{-- ธงภาษาอยู่ขวาสุด ตำแหน่งเดียวกับหน้าล็อกอิน (เจ้าของสั่ง 2026-09-02) --}}
        @include('layouts.partials.lang-switch', ['class' => 'on-dark'])
      </div>
    </header>

    {{-- ───────── หัวเมนู ───────── --}}
    <div class="menubar">
      <span class="menubar-title">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
          <path d="M4 7h16M4 12h16M4 17h16"></path>
        </svg>
        <span data-i18n="menu.title">เมนู</span>
      </span>
      {{--
        🔴 ปุ่มนี้ทำ 2 หน้าที่ตามขนาดจอ (เจ้าของสั่งจัดหน้ามือถือ 2026-09-18)
             จอกว้าง = ย่อเมนูเหลือไอคอน  -> ใช้ลูกศร «
             จอมือถือ = เปิด/ปิดลิ้นชักเมนู -> ใช้ ☰ ซึ่งเป็นสัญลักษณ์ที่คนอ่านออกทันทีบนมือถือ
        🔴 วาดไอคอนไว้ทั้ง 2 อัน แล้วให้ CSS เลือกโชว์ — เปลี่ยนรูปไอคอนด้วย CSS ไม่ได้
      --}}
      <button type="button" data-menu-toggle aria-label="ย่อเมนู" data-i18n-aria="menu.collapse">
        <svg class="ic-burger" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <path d="M4 7h16M4 12h16M4 17h16"></path>
        </svg>
        <svg class="ic-collapse" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m13 17-5-5 5-5M19 17l-5-5 5-5"></path>
        </svg>
      </button>
    </div>

    {{-- ───────── แถบเครื่องมือ ───────── --}}
    <nav class="toolbar" aria-label="แถบเครื่องมือ">
      {{--
        ปุ่มในแถบนี้เหลือ 3 ตัวตามที่เจ้าของสั่ง (2026-09-02) — ตัด ค้นหา / ส่งออก ออกแล้ว
        ไอคอนเป็นไฟล์ภาพที่เจ้าของให้มา เก็บที่ public/img/toolbar/
        เพิ่ม/ลดปุ่มให้แก้ที่นี่ที่เดียว แล้ววางไฟล์ไอคอนชื่อเดียวกัน + เพิ่มคีย์แปล 2 ภาษา
      --}}
      {{--
        ปุ่ม "กลับ" — ย้อนไปหน้าก่อนหน้าตามประวัติเบราว์เซอร์ (เจ้าของสั่ง 2026-09-03)
        ถ้าไม่มีประวัติ (เปิดลิงก์ตรงมาเลย) จะถอยไปหน้าแรกของสายแทน จะได้ไม่กดแล้วไม่มีอะไรเกิดขึ้น
      --}}
      <button type="button" class="tool" data-tool="back"
              data-home="{{ $sectionHome }}"
              title="ย้อนกลับหน้าก่อนหน้า" data-i18n-title="tool.backHint">
        <img class="tool-icon" src="{{ asset('img/toolbar/back.png') }}" alt="" width="20" height="20">
        <span data-i18n="tool.back">กลับ</span>
      </button>

      {{--
        ปุ่ม "หน้าแรก" — กลับไปหน้าแรกของสายเมนูที่กำลังอยู่ ไม่ใช่หน้าแรกของทั้งระบบ
        เช่น งบประมาณ > ของบประมาณ > เสนอรายการใหม่  ->  กดแล้วกลับไป "ของบประมาณ"
        ตัวตัดสินว่าอยู่สายไหนอยู่ที่ NavMenu::sectionFor()
      --}}
      <a class="tool" href="{{ $sectionHome }}"
         title="{{ $sectionHomeLabel }}">
        <img class="tool-icon" src="{{ asset('img/toolbar/home.png') }}" alt="" width="20" height="20">
        <span data-i18n="tool.home">หน้าแรก</span>
      </a>

      <button type="button" class="tool" data-tool="refresh"
              title="รีเฟรช" data-i18n-title="tool.refresh">
        <img class="tool-icon" src="{{ asset('img/toolbar/refresh.png') }}" alt="" width="20" height="20">
        <span data-i18n="tool.refresh">รีเฟรช</span>
      </button>

      <div class="tool-wrap">
        <button type="button" class="tool" data-help-toggle aria-haspopup="true"
                title="ช่วยเหลือ" data-i18n-title="tool.help">
          <img class="tool-icon" src="{{ asset('img/toolbar/help.png') }}" alt="" width="20" height="20">
          <span data-i18n="tool.help">ช่วยเหลือ</span>
        </button>

        {{--
          กล่องช่วยเหลือ — หน้าตาแบบหน้าต่างแชทเล็กๆ ลอยอยู่ใต้ปุ่ม
          บอกแค่ "ติดต่อใคร" กับ "อีเมลอะไร" พอ · อีเมลเก็บที่ config/bms.php ไม่ฝังในหน้า
        --}}
        <div class="help-pop" data-help-menu hidden role="dialog" aria-labelledby="help-title">
          <div class="help-head">
            <span id="help-title" data-i18n="help.title">ติดต่อขอความช่วยเหลือ</span>
            <button type="button" class="help-close" data-help-close aria-label="ปิด" data-i18n-aria="common.close">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                <path d="M18 6 6 18M6 6l12 12"></path>
              </svg>
            </button>
          </div>

          <ul class="help-list">
            <li>
              <b data-i18n="help.hr">แผนกบุคคล</b>
              <a href="mailto:{{ config('bms.hr_email') }}">{{ config('bms.hr_email') }}</a>
            </li>
            <li>
              <b data-i18n="help.it">แผนกไอที</b>
              <a href="mailto:{{ config('bms.it_email') }}">{{ config('bms.it_email') }}</a>
            </li>
          </ul>
        </div>
      </div>

      {{-- ยุบปุ่มไปทางซ้าย ปุ่มนี้ยังอยู่เสมอเพื่อกดกลับได้ --}}
      <button type="button" class="tool-collapse" data-toolbar-toggle
              aria-label="ซ่อนแถบเครื่องมือ" data-i18n-aria="tool.hide">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m13 17-5-5 5-5"></path><path d="m19 17-5-5 5-5"></path>
        </svg>
      </button>

      {{-- พื้นที่ที่เหลือทางขวา = เส้นทางของหน้าที่กำลังดูอยู่ --}}
      @include('layouts.partials.breadcrumb')
    </nav>

    {{-- ───────── เมนูซ้าย ───────── --}}
    <aside class="sidebar" aria-label="เมนูหลัก">
      @include('layouts.partials.sidebar')
    </aside>

    {{-- ───────── แผงเมนูย่อยของโมดูล ───────── --}}
    @include('layouts.partials.submenu')

    {{--
      ตัวกรองหัวคอลัมน์แบบกรองที่ข้อมูล — สำหรับตารางที่แบ่งหน้า มีเลขลำดับ ยอดรวม หรือ merge เซลล์
      (ตัวกรองกลาง table-filter กรองจากแถวใน DOM ซึ่งตารางพวกนั้นใช้ไม่ได้)

      🔴 ต้องอยู่ "ก่อนเนื้อหา" เพราะบางหน้าเขียน <script> ไว้ในเนื้อหาแล้วเรียกใช้ทันที
         ไม่ได้รอ DOMContentLoaded — วางไว้ท้ายหน้าจะยังไม่มี window.BMS.dataFilter ให้เรียก
         (บั๊กจริง 2026-09-11: ตารางในหน้าต่างของแดชบอร์ดไม่มีปุ่มกรองเลย)
    --}}
    @include('layouts.partials.data-filter')

    {{-- ───────── เนื้อหา ───────── --}}
    <main class="content">
      <div class="page">
        @include('layouts.partials.flash')
        @yield('content')
      </div>
    </main>

    {{-- ───────── แถบสถานะ ───────── --}}
    <footer class="statusbar">
      <span class="statusbar-left">
        <span class="dot"></span>
        <span data-i18n="status.ready">พร้อมใช้งาน</span>
      </span>
      <span class="statusbar-right">
        <span>
          <span data-i18n="status.user">ผู้ใช้</span> :
          <span data-loc-th="{{ $me->displayName('th') }}" data-loc-en="{{ $me->displayName('en') }}">{{ $me->displayName('th') }}</span>
          ({{ $me->employee_code }})
        </span>
        <span class="sep">|</span>
        <span>
          <span data-i18n="status.company">บริษัท</span> :
          <span data-loc-th="{{ config('bms.company_th') }}" data-loc-en="{{ config('bms.company_en') }}">{{ config('bms.company_th') }}</span>
        </span>
        <span class="sep">|</span>
        <span data-clock>{{ now()->format('d/m/Y H:i:s') }}</span>
      </span>
    </footer>
  </div>

  @include('layouts.partials.image-zoom')
  @include('layouts.i18n')

  {{-- หน้าต่างแจ้งผล/ถามยืนยันกลางจอ — ต้องอยู่หลัง i18n เพราะเรียก window.BMS.t() --}}
  @include('layouts.partials.dialog')

  {{--
    🔴 หน้าต่างซ้อน (modal) ของทั้งระบบ (2026-09-16)
       ย้ายมา include ที่ layout กลาง หน้าไหนมี data-modal-open ก็ใช้ได้ทันที
       เดิมอยู่ใน access/partials/picker-assets ซึ่งเป็นชิ้นส่วนของโมดูลสิทธิ์
       หน้าไหนลืม include จะกดปุ่มแล้ว **ไม่มีอะไรเกิดขึ้นเลย ไม่มี error ให้เห็น**
       (แดชบอร์ดเจอมาแล้ว — ปุ่มเปรียบเทียบกดไม่ติดทั้งที่โค้ดถูกหมด)
  --}}
  @include('layouts.partials.modal')

  {{-- ตัวกรองรายคอลัมน์แบบ Excel — ทำงานกับทุกตารางที่ใส่ data-filterable --}}
  @include('layouts.partials.table-filter')

  {{-- ช่องกรอกจำนวนเงิน — ใส่ลูกน้ำคั่นหลักพันให้ระหว่างพิมพ์ ทุกช่องที่ใส่ data-money-input --}}
  @include('layouts.partials.money-input')

  {{-- จอ "กำลังโหลด" คลุมทั้งหน้า — ขึ้นเองเมื่อกดลิงก์หรือส่งฟอร์ม --}}
  @include('layouts.partials.loading')

  {{-- ลิงก์ที่ตอบเป็นไฟล์ — ดาวน์โหลดเสร็จแล้วเด้งหน้าต่างขีดถูก ไม่ใช่ปล่อยจอโหลดค้าง --}}
  @include('layouts.partials.download')

  {{-- หมดเวลาเชื่อมต่อ — เด้งบอกผู้ใช้ แทนที่จะเงียบแล้วเด้งไปหน้าล็อกอินเฉยๆ --}}
  @include('layouts.partials.session-timeout')
  <script>
    'use strict';
    (function () {
      var shell = document.getElementById('shell');
      var narrow = function () { return window.matchMedia('(max-width: 860px)').matches; };

      /*
        แผงที่กางจากแถบบน — กระดิ่ง · ช่วยเหลือ · เมนูผู้ใช้ · ภาษา
        🔴 เปิดได้ทีละแผง (บั๊กจริง 2026-09-18 เจ้าของแจ้ง "กดกระดิ่งแล้วกดเปลี่ยนภาษา แผงเก่าไม่ปิด")
           แต่ละแผงมีตัวปิดของตัวเองที่ดักการกดทั้งหน้า แต่ทุกปุ่มสั่ง stopPropagation()
           กดปุ่มอีกอันจึงไม่มีใครได้ยิน แผงเก่าเลยค้างซ้อนกันอยู่
        รวมตัวปิดไว้ที่เดียว แล้วให้ทุกปุ่มเรียกก่อนเปิดของตัวเอง
        (ตัวสลับภาษาอยู่คนละไฟล์ จึงต้องเช็คก่อนเรียก — หน้าล็อกอินไม่มีตัวนี้)
      */
      window.BMS = window.BMS || {};
      window.BMS.closePopovers = function (keep) {
        document.querySelectorAll('[data-bell-pop], [data-help-menu], [data-who-menu], [data-lang-menu]')
          .forEach(function (el) {
            if (keep && (el === keep || el.contains(keep))) { return; }
            el.hidden = true;
          });

        // ปุ่มกระดิ่งบอกสถานะตัวเองไว้ใน aria — ต้องคืนค่าให้ตรงกับที่เห็นจริง
        var bellBtn = document.querySelector('[data-bell-toggle]');
        var bellPop = document.querySelector('[data-bell-pop]');
        if (bellBtn && bellPop) { bellBtn.setAttribute('aria-expanded', bellPop.hidden ? 'false' : 'true'); }
      };

      // ปุ่มย่อเมนู — จอกว้างคือย่อเหลือไอคอน จอแคบคือเปิด/ปิดลิ้นชัก
      var toggle = document.querySelector('[data-menu-toggle]');
      if (toggle) {
        toggle.addEventListener('click', function () {
          shell.classList.toggle(narrow() ? 'is-drawer-open' : 'is-collapsed');
          var collapsed = shell.classList.contains('is-collapsed');
          toggle.setAttribute('data-i18n-aria', collapsed ? 'menu.expand' : 'menu.collapse');
          toggle.setAttribute('aria-label', window.BMS.t(collapsed ? 'menu.expand' : 'menu.collapse'));
        });
      }

      /*
        แตะที่เนื้อหาแล้วปิดลิ้นชักเมนู (เจ้าของสั่ง 2026-09-18)
        🔴 เฉพาะจอแคบเท่านั้น — จอกว้างเมนูซ้ายกับแผงเมนูย่อยเป็นคอลัมน์ถาวรของหน้า
           ไม่ใช่ลิ้นชักที่ลอยทับ ปิดทิ้งเองจะกลายเป็นเมนูหายทั้งที่ผู้ใช้ไม่ได้สั่ง
        closeSubmenu ประกาศไว้ข้างล่างแต่เป็น function declaration จึงเรียกจากตรงนี้ได้
      */
      var contentBox = document.querySelector('.content');
      if (contentBox) {
        contentBox.addEventListener('click', function () {
          if (! narrow()) { return; }
          shell.classList.remove('is-drawer-open');
          if (shell.classList.contains('is-submenu-open')) { closeSubmenu(); }
        });
      }

      /*
        แผงเมนูย่อยของโมดูล — กดโมดูลในเมนูซ้ายแล้วกางออกมาทางขวา
        แผงทุกอันถูกวาดไว้ล่วงหน้าแล้วซ่อนไว้ ตรงนี้แค่สลับว่าจะโชว์อันไหน
        (วาดล่วงหน้าเพื่อให้ระบบ 2 ภาษาแปลได้ตอนโหลดหน้าตามปกติ)
      */
      var panels = document.querySelectorAll('[data-submenu-panel]');

      function openSubmenu(id) {
        var found = false;
        panels.forEach(function (p) {
          var match = p.getAttribute('data-submenu-panel') === id;
          p.hidden = ! match;
          if (match) found = true;
        });
        shell.classList.toggle('is-submenu-open', found);

        document.querySelectorAll('[data-open-submenu]').forEach(function (b) {
          b.classList.toggle('is-active', found && b.getAttribute('data-open-submenu') === id);
        });

      }

      function closeSubmenu() {
        panels.forEach(function (p) { p.hidden = true; });
        shell.classList.remove('is-submenu-open');
        document.querySelectorAll('[data-open-submenu]').forEach(function (b) { b.classList.remove('is-active'); });
      }

      /*
        กาง/พับหัวข้อหลักในเมนูซ้าย
        <details> เปลี่ยนสถานะทันที จึงต้องหน่วงการปิดไว้ให้อนิเมชันเล่นจบก่อน
        ส่วนความสูงปล่อยให้ CSS (grid-template-rows) จัดการ ไม่คำนวณ px เอง
      */
      var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      document.querySelectorAll('.nav-group').forEach(function (group) {
        var summary = group.querySelector('summary');
        var body = group.querySelector('.nav-items');
        if (!summary || !body || reduceMotion) { return; }

        summary.addEventListener('click', function (e) {
          e.preventDefault();

          // กดรัวๆ ตอนกำลังเล่นอนิเมชันอยู่ ให้ข้ามไปเลย กันสถานะค้างครึ่งทาง
          if (group.classList.contains('is-closing') || group.classList.contains('is-opening')) { return; }

          if (group.open) {
            group.classList.add('is-closing');
            window.setTimeout(function () {
              group.open = false;
              group.classList.remove('is-closing');
            }, 240);

            return;
          }

          group.open = true;
          group.classList.add('is-opening');
          // รอให้เบราว์เซอร์วาดสถานะเริ่มต้น (0fr) ก่อน แล้วค่อยปล่อยให้ขยาย
          window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () { group.classList.remove('is-opening'); });
          });
        });
      });

      document.querySelectorAll('[data-open-submenu]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-open-submenu');
          // กดโมดูลเดิมซ้ำ = ปิดแผง
          var already = shell.classList.contains('is-submenu-open') && btn.classList.contains('is-active');
          if (already) { closeSubmenu(); } else { openSubmenu(id); }
        });
      });

      document.querySelectorAll('[data-close-submenu]').forEach(function (btn) {
        btn.addEventListener('click', closeSubmenu);
      });

      /*
        แผงเมนูย่อยค้างอยู่ได้ก็ต่อเมื่อ "หน้าที่เปิดอยู่เป็นหน้าในแผงนั้น"

        กติกา (เจ้าของสั่ง 2026-09-03)
          อยู่ในแผง        -> แผงค้างไว้ เช่น สิทธิ์การเข้าถึง -> สิทธิ์การเข้าถึงระบบ SBMS
          ย้ายไปหน้าอื่น   -> ปิดแผงทิ้ง เช่น กดแดชบอร์ดที่ไม่มีแผงของตัวเอง

        🔴 เคยจำ id ที่เปิดล่าสุดไว้ใน localStorage แล้วกู้คืนตอนโหลดหน้า
           ทำให้แผงตามไปโผล่ในหน้าที่ไม่เกี่ยวข้องด้วย จึงเลิกใช้แล้ว
           สถานะที่ถูกต้องอ่านจากหน้าปัจจุบันได้อยู่แล้ว ไม่ต้องเก็บเอง
      */
      (function restoreSubmenu() {
        /*
          เปิดแผงของ "สาย" ที่หน้านี้สังกัดอยู่ — ฝั่ง PHP คำนวณมาให้แล้ว (NavMenu::sectionFor)
          🔴 ใช้ค่านี้แทนการดู .submenu-item.is-active อย่างเดียว
             เพราะหน้าลูกอย่าง "เสนอรายการใหม่" ไม่มีเมนูของตัวเอง แผงเลยปิดทิ้งทั้งที่ยังอยู่ในสายเดิม
             (เจ้าของสั่งแก้ 2026-09-03: เมนูขวาต้องไม่ปิดถ้าผู้ใช้ไม่กดปิดเอง)
        */
        var id = shell.getAttribute('data-section-module');
        if (!id || !document.querySelector('[data-submenu-panel="' + id + '"]')) { return; }

        // ตอนโหลดหน้าไม่ต้องเล่นอนิเมชัน ไม่งั้นแผงจะวูบทุกครั้งที่เปลี่ยนหน้า
        shell.classList.add('no-anim');
        openSubmenu(id);
        window.requestAnimationFrame(function () { shell.classList.remove('no-anim'); });
      })();

      /*
        ปุ่ม "กลับ" — ย้อนตามประวัติเบราว์เซอร์
        เปิดลิงก์ตรงเข้ามา (ไม่มีประวัติในแท็บนี้) ให้ถอยไปหน้าแรกของสายแทน
        เช็คด้วย history.length เพราะ document.referrer เชื่อไม่ได้เมื่อข้ามโดเมน
      */
      var backBtn = document.querySelector('[data-tool="back"]');
      if (backBtn) {
        backBtn.addEventListener('click', function () {
          if (window.history.length > 1) { window.history.back(); return; }

          window.location.href = backBtn.getAttribute('data-home');
        });
      }

      // ปุ่มรีเฟรช — โหลดหน้าปัจจุบันใหม่ เหมือนกดรีเฟรชของเบราว์เซอร์
      var refreshBtn = document.querySelector('[data-tool="refresh"]');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', function () { window.location.reload(); });
      }

      // ปุ่มยุบ/กางแถบเครื่องมือ
      var toolbarBtn = document.querySelector('[data-toolbar-toggle]');
      if (toolbarBtn) {
        toolbarBtn.addEventListener('click', function () {
          shell.classList.toggle('is-toolbar-hidden');
          var hidden = shell.classList.contains('is-toolbar-hidden');
          toolbarBtn.setAttribute('data-i18n-aria', hidden ? 'tool.show' : 'tool.hide');
          toolbarBtn.setAttribute('aria-label', window.BMS.t(hidden ? 'tool.show' : 'tool.hide'));
        });
      }

      // กล่องช่วยเหลือ (อีเมลแผนกบุคคล / ไอที)
      var helpBtn = document.querySelector('[data-help-toggle]');
      var helpMenu = document.querySelector('[data-help-menu]');
      if (helpBtn && helpMenu) {
        helpBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          window.BMS.closePopovers(helpMenu);   // เปิดได้ทีละแผง
          helpMenu.hidden = !helpMenu.hidden;
        });
        helpMenu.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () { helpMenu.hidden = true; });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') helpMenu.hidden = true; });

        var helpClose = helpMenu.querySelector('[data-help-close]');
        if (helpClose) {
          helpClose.addEventListener('click', function () {
            helpMenu.hidden = true;
            helpBtn.focus();   // คืนโฟกัสให้ปุ่มที่เปิดกล่อง
          });
        }
      }

      // เมนูผู้ใช้มุมขวาบน
      var whoBtn = document.querySelector('[data-who-toggle]');
      var whoMenu = document.querySelector('[data-who-menu]');
      if (whoBtn && whoMenu) {
        whoBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          window.BMS.closePopovers(whoMenu);    // เปิดได้ทีละแผง
          whoMenu.hidden = !whoMenu.hidden;
        });
        document.addEventListener('click', function () { whoMenu.hidden = true; });
      }

      // รูปโปรไฟล์เปิดจากสตอเรจของ Insight ผ่าน HTTP — ถ้าโหลดไม่ได้ให้ถอยไปใช้ตัวอักษรแรก
      // และต้องปิดการกดขยายด้วย ไม่งั้นกดแล้วจะเปิดกล่องรูปเปล่า
      document.querySelectorAll('[data-avatar]').forEach(function (img) {
        img.addEventListener('error', function () {
          var holder = img.parentElement;
          if (!holder) return;
          holder.textContent = img.getAttribute('data-initial') || '';
          holder.removeAttribute('data-zoomable');
          holder.removeAttribute('data-zoom-src');
          holder.style.cursor = 'default';
        });
      });

      // นาฬิกาในแถบสถานะ — เดินจริงเหมือนโปรแกรม ERP บนเดสก์ท็อป
      var clock = document.querySelector('[data-clock]');
      if (clock) {
        setInterval(function () {
          var d = new Date();
          var p = function (n) { return String(n).padStart(2, '0'); };
          clock.textContent = p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear()
            + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
        }, 1000);
      }
    })();
  </script>
  @stack('page-script')
</body>
</html>
