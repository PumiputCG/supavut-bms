@extends('layouts.app')
@section('title', 'งบประมาณและการใช้เงิน / Budget overview')

{{--
  แดชบอร์ดงบประมาณจาก ERP
  Codex สร้างข้อมูล/ตัวจับคู่ 2026-09-14 · Claude จัดหน้าใหม่ตามที่เจ้าของสั่ง 2026-09-14

  - ตัวกรอง บริษัท · ปีงบ · แผนก — เลือกแล้วโหลดทันที ไม่มีปุ่ม "แสดงข้อมูล" (ตัวกรอง Model เจ้าของสั่งเอาออก)
  - กราฟ "แท่งเดี่ยวซ้อนสี" บนรางวงเงินงบ: ใช้ไป (แดง) → รอตัดจ่าย (เหลือง) → คงเหลือ (เขียว)
    ชนิดกราฟ แนวตั้ง · แนวนอน · วงกลม — วาด SVG เอง ห้ามดึงไลบรารีจาก CDN (เซิร์ฟอยู่ในวงแลน)
  - เงินสกุลต่างประเทศ (เยน) อยู่ในแผนกของมัน แสดงเป็น "+ … JPY" และไม่รวมในยอดบาท
  🔴 งบเงินเดือน/ค่าตอบแทนพนักงานถูกตัดตั้งแต่ ErpDashboard ตามกฎเด็ดขาดของโปรเจค — หน้านี้ห้ามดึงข้อมูลเพิ่มเอง
--}}

@php
  $L = fn($th, $en) => new \Illuminate\Support\HtmlString('<span data-loc-th="'.e($th).'" data-loc-en="'.e($en).'">'.e($th).'</span>');
  $money = fn($value) => number_format($value / 100, 2);
  /*
    ตัวกรองหัวคอลัมน์ (?f[คีย์]=…) ติดไปกับลิงก์ "เปลี่ยนหน้า / เรียงลำดับ" เท่านั้น (เจ้าของสั่ง 2026-09-14)
    ลิงก์ที่เปลี่ยนมุมมอง/แผนก/งบ ต้องล้างทิ้ง — คีย์ของแต่ละตารางต่างกัน ค้างไปจะกรองผิดตาราง
  */
  $column_filter = array_filter((array)request()->query('f', []), fn($v) => is_string($v) && $v !== '');
  $href = function ($changes = []) use ($filters, $column_filter) {
    $params = array_filter(array_replace($filters, $changes), fn($v) => $v !== '' && $v !== null);
    if ($column_filter && !array_diff(array_keys($changes), ['page', 'sort'])) {
      $params['f'] = $column_filter;
    }
    return route('dashboard', $params);
  };
  $budget_view = ['view'=>'budgets', 'budget'=>'', 'record'=>'', 'page'=>1];
  $first = $rows[0] ?? null;
  $heading_dept = $first['dept_name'] ?? ($groups[0]['first']['dept_name'] ?? null);
  $heading_dept_th = $heading_dept ?? ($deptOptions[$filters['dept']]['th'] ?? '');
  $heading_dept_en = $heading_dept ?? ($deptOptions[$filters['dept']]['en'] ?? '');
  $sort_options = ['date_desc'=>['วันที่ล่าสุด','Newest first'], 'date_asc'=>['วันที่เก่าสุด','Oldest first'], 'amount_desc'=>['ยอดสูง → ต่ำ','Amount high to low'], 'amount_asc'=>['ยอดต่ำ → สูง','Amount low to high']];
  $highlight_negative = ($filters['highlight'] ?? '') === 'negative';
  $budget_pages = max(1, (int)ceil(count($groups) / 25));
  $budget_page = min($filters['page'], $budget_pages);
  $focus_budget = (string)request()->query('focus', '');
  foreach ($groups as $index => $group) {
    if (hash('sha256', $group['key'].'|'.$group['currency']) === $focus_budget) {
      $budget_page = (int)floor($index / 25) + 1;
      break;
    }
  }
  $visible_groups = array_slice($groups, ($budget_page - 1) * 25, 25);
  $fx_text = fn($list) => implode(' ', array_map(fn($code, $f) => '+ '.number_format($f['budget'] / 100, 2).' '.\App\Services\Erp\ErpDashboard::currency_label((string)$code), array_keys($list), $list));
  $pct = fn($part, $whole) => $whole > 0 ? number_format($part / $whole * 100, 1).'%' : '—';
  $firm = $filters['company'] !== '' ? collect($companyOptions)->firstWhere('id', $filters['company']) : ['th'=>'ทุกบริษัท', 'en'=>'All companies'];
  $isExpense = ($filters['tab'] ?? '') === 'expense';
  $ex = $expense ?? null;
  /*
    ป้ายงวดงบ — ใช้รหัสถังจริงของ ERP เช่น BG26Q1 (เจ้าของสั่ง 2026-09-17)
    เดิมเขียนแค่ Q1 แล้วคนอ่านเป็น "ม.ค.–มี.ค." ทั้งที่ความหมายจริงคือ "ถังงบ Q1"
    ซึ่งรายการที่จ่ายเดือนอื่นก็มาตัดได้ · เลือก "ทุกปี" / "งบที่ยังไม่จัดปี" → BGxxQ1 (ใช้ xx แบบเลขที่ตัวอย่าง)
    🔴 ค่าที่ส่งใน URL ยังเป็น 1–4 เหมือนเดิม เปลี่ยนแค่ป้าย — ลิงก์เก่าใช้ได้ต่อ
    🔴 รหัสเหมือนกันทั้ง 2 ภาษา จึงไม่ต้องแยกคำแปล
  */
  $quarterLabel = fn (int $q, string $year = '') => 'BG'.(ctype_digit($year) ? substr($year, 2, 2) : 'xx').'Q'.$q;

  /*
    ป้ายช่วงวันที่จ่ายของแท็บ "ค่าใช้จ่ายตามวันที่" — ปี ค.ศ. ตามที่แดชบอร์ดใช้ทั้งหน้า
    🔴 ตั้งแต่ 2026-09-17 แท็บนี้นับเฉพาะรายการที่จ่ายในปีที่เลือก จึงไม่มีช่วงนอกปีให้ติดป้ายอีก
  */
  $thMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
  $enMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  $paidText = function (string $level, string $key) use ($thMonths, $enMonths) {
    $y = substr($key, 0, 4);
    return match ($level) {
      'quarter' => ['Q'.substr($key, 5).'/'.$y, 'Q'.substr($key, 5).'/'.$y],
      'month' => [$thMonths[(int) substr($key, 5, 2) - 1].' '.$y, $enMonths[(int) substr($key, 5, 2) - 1].' '.$y],
      default => [(int) substr($key, 8, 2).' '.$thMonths[(int) substr($key, 5, 2) - 1].' '.$y,
                  (int) substr($key, 8, 2).' '.$enMonths[(int) substr($key, 5, 2) - 1].' '.$y],
    };
  };
  // ช่วงที่กำลังดูอยู่ทั้งก้อน — ไม่ได้เลือกช่วง = ทั้งปีงบ (ระดับ year)
  $windowText = fn (?array $w) => $w === null || $w['level'] === 'year'
    ? ['ทั้งปีงบ '.$filters['year'], 'Whole budget year '.$filters['year']]
    : $paidText($w['level'], $w['key']);
@endphp

@push('page-style')
<style>
  :root { --page-max: var(--dashboard-page-max); }

  /* 🔴 ต้องมี minmax(0, 1fr) — ไม่งั้นช่อง grid ยืดตามตารางกว้างๆ จนทั้งหน้าล้นแนวนอนที่จอแคบ (ตรวจจริง 400px) */
  .bd { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; padding-bottom: 28px; min-width: 0; }
  .bd-head { display: flex; align-items: center; justify-content: space-between; gap: 10px 16px; flex-wrap: wrap; }
  .bd-head h1 { font-size: var(--fs-xl); font-weight: 700; color: var(--navy-900); line-height: 1.3; }
  /* แถบแท็บอยู่แทนหัวเรื่อง — กินเต็มแถว ชื่อแผนกที่เลือกอยู่ชิดขวาบนเส้นเดียวกัน */
  .bd-head .dash-tabs { flex: 1 1 100%; }
  .bd-head-dept { margin-left: auto; align-self: center; padding: 0 4px 6px; color: var(--ink-soft); font-size: var(--fs-sm); font-weight: 600; }
  .bd-meta { color: var(--muted); font-size: var(--fs-xs); line-height: 1.6; }
  /* เวลาที่อ่าน ERP — ชิดท้ายแถวตัวกรอง อ่านผ่านๆ ได้ ไม่แย่งสายตาจากตัวเลขเงิน */
  .bd-fetched { white-space: nowrap; font-variant-numeric: tabular-nums; }

  /* ── การ์ดสรุป: ตัวกรองซ้าย + ยอด 4 ช่องขวา (เจ้าของสั่ง 2026-09-14) ── */
  /* ตัวกรองไม่มีการ์ดครอบ · มีการ์ดเฉพาะยอดสรุป (เจ้าของสั่ง 2026-09-14) */
  .bd-summary { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; }
  .bd-filter { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; flex: 0 1 auto; }

  /*
    🔴 ช่องตัวกรองกว้างเท่ากันทุกช่อง (เจ้าของแจ้ง 2026-09-17 ว่า "จัดระเบียบกันไม่สวย")

       ของเดิมเป็น `width: auto` ความกว้างจึงยืด/หดตาม **ข้อความของตัวเลือกที่เลือกอยู่**
       เลือก "WD:Welding" กับ "ทุกแผนก" ได้คนละความกว้าง แถวเลยเบี้ยว
       และขยับทุกครั้งที่เปลี่ยนค่า — ยิ่งตอนซูม 90% ที่ทุกช่องอัดกันในบรรทัดเดียว
  */
  .bd-filter select {
    flex: 0 0 148px; width: 148px; height: 30px; min-width: 0; padding: 0 6px;
    border: 1px solid var(--line); border-radius: var(--radius);
    background: var(--surface); color: var(--ink); font-size: var(--fs-xs);
  }

  /*
    🔴 ความกว้างของแต่ละช่องตั้งตาม "ข้อความที่ยาวที่สุดที่ช่องนั้นมีจริง"
       ไม่ใช่ตั้งเท่ากันหมดแล้วเหลือที่ว่างเปล่าๆ — เพราะที่ว่างที่ประหยัดได้
       คือที่ที่การ์ดยอดเอาไปใช้ ทำให้ทั้ง 2 ก้อนอยู่บรรทัดเดียวกันได้
         ปี      สั้นสุด (2026 · ทุกปี)      แต่ต้องพอกับ "งบที่ยังไม่จัดปี"
         แผนก   ยาวสุด (WD:Welding · SPV1-12)
  */
  .bd-filter select[name="year"] { flex-basis: 124px; width: 124px; }
  .bd-filter select[name="dept"] { flex-basis: 186px; width: 186px; }
  .bd-filter select:hover { border-color: var(--line-dark); }
  .bd-link { color: var(--accent); text-decoration: none; font-size: var(--fs-xs); white-space: nowrap; }
  .bd-link:hover { text-decoration: underline; }
  .bd-body { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; min-width: 0; transition: opacity .2s var(--ease); }
  .bd[aria-busy="true"] .bd-body, .bd[aria-busy="true"] .bd-stats { opacity: .4; pointer-events: none; }
  .bd-subject { font-size: var(--fs-lg); color: var(--navy-900); }

  /*
    🔴 `flex: 1 1 560px` ไม่ใช่ `0 1 720px` (เจ้าของแจ้ง 2026-09-17)

       ของเดิมโตไม่ได้ (grow = 0) พอตัวกรองกว้างขึ้นการ์ดจึงถูกบีบจนตัวเลขล้นช่อง
       และตอนตกลงบรรทัดใหม่ก็ยังกว้างแค่ 720px ลอยอยู่กลางๆ ไม่เต็มแถว
       ให้โตได้ = อยู่บรรทัดเดียวกันก็กินที่ที่เหลือพอดี · ตกบรรทัดใหม่ก็เต็มความกว้าง
  */
  /*
    🔴 `min-width` คือตัวตัดสินว่าจะอยู่บรรทัดเดียวกับตัวกรองหรือตกลงมาเอง
       วัดจริงแล้วยอดเงิน 14 หลัก (`176,388,618.92`) กว้าง ~141px + ระยะขอบ 24px
       ช่องจึงต้องไม่ต่ำกว่า ~176px — ต่ำกว่านี้ตัวเลขจะล้นออกนอกการ์ด
       บีบไม่ได้แล้ว = ตกลงมาเป็นบรรทัดของตัวเองแล้วกินเต็มความกว้าง ซึ่งอ่านง่ายกว่าถูกบีบ
  */
  .bd-stats {
    display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
    /* 🔴 ไม่ใช้ `margin-left: auto` — มันกินที่ว่างแทนที่จะให้การ์ดโตไปกินเอง
       ผลคือตอนตกลงมาเป็นบรรทัดของตัวเอง การ์ดไม่เต็มแถว ลอยชิดขวาแทน
       `flex-grow: 1` ดันไปชิดขวาให้อยู่แล้วเมื่ออยู่บรรทัดเดียวกับตัวกรอง */
    flex: 1 1 560px; min-width: 704px; align-items: start;
    transition: opacity .2s var(--ease);
  }
  .bd-stat { padding: 8px 12px; min-width: 0; }
  .bd-stat + .bd-stat { border-left: 1px solid var(--line-soft); }
  /* 🔴 ต้อง wrap ได้ ไม่งั้นชื่อกับ % ชนกันตอนช่องแคบ แล้วตัวเลขล้นออกนอกการ์ด */
  .bd-stat-label { display: flex; align-items: center; flex-wrap: wrap; gap: 0 6px; color: var(--ink-soft); font-size: var(--fs-xs); font-weight: 600; }
  .bd-stat-label small { margin-left: auto; padding-left: 8px; color: var(--muted); font-size: var(--fs-xs); font-weight: 400; font-variant-numeric: tabular-nums; }
  .bd-stat-value { margin-top: 1px; font-size: var(--fs-md); font-weight: 700; line-height: 1.3; color: var(--navy-900); font-variant-numeric: tabular-nums; white-space: nowrap; }
  .bd-dot { width: 10px; height: 10px; border-radius: var(--radius-sm); flex: 0 0 auto; }
  .bd-dot.budget { background: var(--chart-track); border: 1px solid var(--line-dark); }
  .bd-dot.used { background: var(--chart-used); }
  .bd-dot.reserve { background: var(--chart-reserve); }
  .bd-dot.left { background: var(--chart-left); }
  .bd-used { color: var(--danger); } .bd-reserve { color: var(--warn); } .bd-left { color: var(--ok); }
  .bd-fx { display: block; color: var(--info); font-size: var(--fs-xs); font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }

  /*
    การ์ดเดียวของแท็บค่าใช้จ่าย (เจ้าของสั่ง 2026-09-17 "เอาแค่ค่าใช้จ่ายก็พอ")
    🔴 ไม่มีช่องงบ/รอตัดจ่าย/คงเหลือ เพราะ ERP ไม่มีงบรายเดือนรายวันให้เทียบ
    🔴 `.bd-stats.is-single` (0,2,0) ชนะ `.bd-stats` ในบล็อกจอแคบ — ไม่ต้องไล่เขียนทับทีละขนาดจอ
  */
  /*
    🔴 การ์ดเดียวต้องเล็กพอให้ "อยู่บรรทัดเดียวกับตัวกรองที่มี 6 ช่อง" (เจ้าของสั่ง 2026-09-17)
       ช่องเดียวไม่ต้องกว้างเท่าการ์ด 4 ช่อง จึงไม่ให้โต (`flex-grow: 0`) และตั้งขั้นต่ำแค่พอกับยอด 14 หลัก
  */
  /* 🔴 flex-basis ตายตัว ไม่ใช่ auto — ไม่งั้นการ์ดกว้างตามข้อความบรรทัดเทียบ (MoM …) แล้วตกไปอยู่บรรทัดของตัวเอง
     ตั้ง 208px = พอกับยอด 14 หลัก + ระยะขอบ · ข้อความที่ยาวกว่านั้นตัดบรรทัดในการ์ดแทน */
  .bd-stats.is-single { grid-template-columns: minmax(0, 1fr); flex: 0 1 208px; min-width: 168px; }
  .bd-stats.is-single .bd-stat { padding: 5px 10px; }
  .bd-stats.is-single .bd-stat-value { font-size: var(--fs-sm); }
  .bd-stat-cmp { display: flex; flex-wrap: wrap; gap: 0 6px; margin-top: 2px; color: var(--muted); font-size: var(--fs-xs); }
  .bd-stat-cmp b { font-variant-numeric: tabular-nums; }
  /* ค่าใช้จ่ายเพิ่ม = แดง · ลด = เขียว (สีเดียวกับ "ใช้ไป" ในแท็บงวดงบ) */
  .bd-stat-cmp .up { color: var(--danger); }
  .bd-stat-cmp .down { color: var(--ok); }

  /*
    ── จอแคบ: ยอด 4 ช่องพับเป็น 2×2 แทนที่จะบีบจนตัวเลขล้น ──
    🔴 เส้นคั่นต้องคิดใหม่ทั้งชุด ไม่ใช่แค่ปล่อย `.bd-stat + .bd-stat`
       ไม่งั้นเส้นซ้ายของช่องที่ 3 จะไปโผล่กลางแถวล่างโดยไม่มีอะไรอยู่ข้างซ้าย
  */
  @media (max-width: 820px) {
    /* 🔴 ต้องปลด min-width ด้วย ไม่งั้น 704px ดันหน้าจนเลื่อนแนวนอนบนมือถือ */
    .bd-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); min-width: 0; }
    .bd-stat + .bd-stat { border-left: 0; }
    .bd-stat:nth-child(even) { border-left: 1px solid var(--line-soft); }
    .bd-stat:nth-child(n+3) { border-top: 1px solid var(--line-soft); }
  }

  /* จอแคบมาก: ช่องตัวกรองเลิกล็อกความกว้าง ปล่อยให้ยืดเต็มแถวแทนการตกทีละช่อง */
  @media (max-width: 560px) {
    .bd-filter select,
    .bd-filter select[name="dept"] { flex: 1 1 140px; width: auto; }
  }

  /* ── การ์ดหัวน้ำเงิน (รูปแบบเดิมของแดชบอร์ด 2026-09-11) ── */
  .bd-card-head { background: var(--navy-800); border-bottom-color: var(--navy-900); border-radius: var(--radius) var(--radius) 0 0; flex-wrap: wrap; }
  .bd-card-lead { display: flex; align-items: center; gap: 8px 16px; margin-right: auto; min-width: 0; flex-wrap: wrap; }
  .bd-card-title { display: grid; gap: 1px; min-width: 0; }
  .bd-card-title b { color: var(--on-dark); font-size: var(--fs-lg); line-height: 1.25; }
  .bd-card-title span { color: var(--on-dark-soft); font-size: var(--fs-xs); font-weight: 600; }
  .bd-legend { display: flex; flex-wrap: wrap; gap: 4px 14px; color: var(--on-dark-soft); font-size: var(--fs-xs); }
  .bd-legend span { display: inline-flex; align-items: center; gap: 6px; }
  .bd-kinds { display: inline-flex; align-items: center; gap: 4px; }
  .bd-kinds-sep { width: 1px; height: 18px; margin: 0 4px; background: var(--line-on-dark); }
  .bd-kbtn {
    display: grid; place-items: center; width: 28px; height: 28px; padding: 0;
    border: 1px solid var(--line-on-dark); border-radius: var(--radius-sm);
    background: transparent; color: var(--on-dark); cursor: pointer;
    transition: background .14s var(--ease), color .14s var(--ease), border-color .14s var(--ease);
  }
  .bd-kbtn svg { fill: currentColor; }
  .bd-kbtn:hover { border-color: var(--on-dark); }
  .bd-kbtn.is-on { background: var(--on-dark); border-color: var(--on-dark); color: var(--navy-800); }

  /* 🔴 กรอบกราฟสูงคงที่ — สลับชนิดกราฟแล้วการ์ดต้องไม่ยืด/หด (กติกาเดิม DESIGN_SYSTEM "กราฟ") */
  /* สูงขึ้นจาก 330 → 460 ให้แท่งยอดน้อยยาวพอชี้เมาส์โดน (เจ้าของสั่ง 2026-09-14) · มุมมองตารางใช้ความสูงเดียวกัน */
  .bd-chart, .bd-table-panel { position: relative; height: 460px; overflow: auto; }
  .bd-chart { scrollbar-gutter: stable; overscroll-behavior: contain; cursor: grab; }
  .bd-chart.is-panning, .bd-chart.is-panning * { cursor: grabbing; user-select: none; }
  .bd-zoom-stage { position: relative; overflow: hidden; }
  .bd-zoom-content { position: absolute; top: 0; left: 0; transform-origin: 0 0; }
  .bd [data-chart-zoom="reset"] { width: auto; min-width: 52px; padding-inline: 6px; }
  .bd-table-panel { border: 1px solid var(--line-soft); border-radius: var(--radius); }
  .bd-table-panel .tbl th { z-index: 1; }

  /*
    ── เต็มจอ (เจ้าของสั่ง 2026-09-14) ──
    ใช้ Fullscreen API กับทั้งการ์ด · เบราว์เซอร์ที่ไม่ยอมเต็มจอจริง ใช้ .is-full คลุมหน้าต่างแทน
    กราฟ/ตารางยืดเต็มพื้นที่ที่เหลือ แล้ววาดใหม่ตามขนาดจริง
  */
  .bd-card:fullscreen, .bd-card.is-full {
    display: flex; flex-direction: column; width: 100%; height: 100%;
    margin: 0; border: 0; border-radius: 0; background: var(--surface); overflow: hidden;
  }
  .bd-card.is-full { position: fixed; inset: 0; z-index: var(--z-modal); }
  .bd-card:fullscreen::backdrop { background: var(--surface); }
  .bd-card:fullscreen .bd-card-head, .bd-card.is-full .bd-card-head { border-radius: 0; }
  .bd-card:fullscreen .card-body, .bd-card.is-full .card-body { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
  .bd-card:fullscreen .bd-chart, .bd-card.is-full .bd-chart,
  .bd-card:fullscreen .bd-table-panel, .bd-card.is-full .bd-table-panel { flex: 1 1 auto; height: auto; min-height: 0; }
  .bd-chart svg { display: block; }
  .bd-alert { color: var(--chart-used); font-weight: 700; }
  .bd-tbl tr.bd-negative-row > td { background: var(--danger-soft); }
  .bd-tbl tr:target { scroll-margin-top: 100px; }
  .bd-tbl tr:target > td { background: var(--danger-soft); }
  .c-alert rect { fill: var(--chart-used); stroke: var(--surface); stroke-width: 2; }
  .c-alert text { fill: white; font-size: 11px; font-weight: 700; }
  .c-alert:focus-visible { outline: 2px solid var(--navy-900); }
  .bd-chart .c-label { fill: var(--ink-soft); font-size: 11px; }
  .bd-chart .c-value { fill: var(--navy-900); font-size: 11px; font-weight: 700; }
  .bd-chart .c-axis { fill: var(--muted); font-size: 10px; }
  .bd-chart .c-grid { stroke: var(--line-soft); stroke-width: 1; }
  .bd-chart .c-track { fill: var(--chart-track); }
  .bd-chart .c-used { fill: var(--chart-used); }
  .bd-chart .c-reserve { fill: var(--chart-reserve); }
  .bd-chart .c-left { fill: var(--chart-left); }
  .bd-chart .c-slice { stroke: var(--surface); stroke-width: 2; }
  /* พื้นที่กดทั้งช่องของแผนก — ระบายจางๆ ตอนชี้ ให้รู้ว่ากำลังชี้แผนกไหน แม้แท่งจะเตี้ย */
  .bd-chart .c-hit { fill: var(--navy-800); fill-opacity: 0; cursor: pointer; transition: fill-opacity .12s var(--ease); }
  .bd-chart .c-hit:hover, .bd-chart .c-hit:focus-visible { fill-opacity: .06; }
  .bd-chart .c-hit:focus { outline: none; }
  .bd-chart .c-hit:focus-visible { stroke: var(--accent); stroke-width: 2; }
  .bd-chart .c-bar { transition: opacity .14s var(--ease); }
  .bd-chart .is-dim .c-bar { opacity: .55; }
  .bd-chart.is-anim .c-grow-y { transform-box: fill-box; transform-origin: bottom; animation: bdGrowY .5s var(--ease) both; }
  .bd-chart.is-anim .c-grow-x { transform-box: fill-box; transform-origin: left; animation: bdGrowX .5s var(--ease) both; }
  @keyframes bdGrowY { from { transform: scaleY(0); } to { transform: none; } }
  @keyframes bdGrowX { from { transform: scaleX(0); } to { transform: none; } }
  @media (prefers-reduced-motion: reduce) { .bd-chart.is-anim .c-grow-y, .bd-chart.is-anim .c-grow-x { animation: none; } }

  /* วงกลม — รูปซ้าย ตัวเลขขวา */
  .bd-pie { display: flex; align-items: center; justify-content: center; gap: 28px; height: 100%; flex-wrap: wrap; padding: 8px; }
  .bd-pie-list { display: grid; gap: 9px; min-width: 240px; }
  .bd-pie-list h4 { font-size: var(--fs-md); color: var(--navy-900); }
  .bd-pie-row { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 4px 10px; font-size: var(--fs-sm); }
  .bd-pie-row b { font-variant-numeric: tabular-nums; text-align: right; }
  .bd-pie-row small { grid-column: 2 / 4; color: var(--muted); font-size: var(--fs-xs); }
  .bd-hint { margin-top: 8px; text-align: center; }

  /* ป้ายบอกค่าตอนชี้แท่ง */
  .bd-tip {
    position: fixed; z-index: var(--z-dropdown); pointer-events: none;
    display: grid; gap: 2px; min-width: 210px; padding: 9px 11px;
    border-radius: var(--radius); background: var(--navy-900); color: var(--on-dark);
    font-size: var(--fs-xs); line-height: 1.5; box-shadow: var(--shadow-lg);
  }
  .bd-tip b { font-size: var(--fs-sm); }
  .bd-tip i { font-style: normal; color: var(--on-dark-soft); }
  .bd-tip-row { display: flex; justify-content: space-between; gap: 16px; font-variant-numeric: tabular-nums; }
  .bd-tip .t-used { color: var(--danger-on-dark); } .bd-tip .t-reserve { color: var(--warn-on-dark); }
  .bd-tip .t-left { color: var(--ok-on-dark); } .bd-tip .t-fx { color: var(--on-dark-soft); }

  /* ── ตาราง ── */
  .bd-tbl td.txt-right, .bd-tbl td.num { white-space: nowrap; font-variant-numeric: tabular-nums; }
  .bd .bd-tbl th, .bd .bd-tbl td { text-align: center; vertical-align: middle; }
  .bd-tbl a { color: var(--navy-800); font-weight: 600; text-decoration: none; }
  .bd-tbl a:hover { text-decoration: underline; }
  /* 🔴 ตัวเลข "ใช้ไป" ที่เป็นลิงก์ต้องแดงด้วย — `.bd-tbl a` (0,1,1) แรงกว่า `.bd-used` (0,1,0) ลิงก์เลยกลายเป็นน้ำเงิน (เจ้าของแจ้ง 2026-09-14) */
  .bd-tbl a.bd-used, .bd-tbl .bd-used a { color: var(--danger); }
  .bd-tbl .bd-title { min-width: 220px; max-width: 420px; overflow-wrap: anywhere; }
  .bd-code { display: block; color: var(--muted); font-size: var(--fs-xs); font-weight: 400; overflow-wrap: anywhere; }
  .bd-entry-modal { width: min(640px, calc(100vw - 32px)); max-height: 85vh; max-height: 85dvh; margin: auto; padding: 24px; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); color: var(--ink-soft); text-align: left; overflow: auto; }
  .bd-entry-modal::backdrop { background: rgb(0 0 0 / .4); }
  .bd-entry-modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
  .bd-entry-modal h2 { font-size: var(--fs-lg); overflow-wrap: anywhere; }
  .bd-entry-modal-head button { flex-shrink: 0; }
  .bd .bd-text-link { border: 0; border-radius: 0; background: transparent; box-shadow: none; padding: 4px 0; color: var(--accent); font: inherit; cursor: pointer; text-decoration: underline; text-decoration-style: solid; text-decoration-thickness: 1px; text-underline-offset: 4px; }
  .bd .bd-text-link:hover, .bd .bd-text-link:focus-visible { color: var(--accent); background: transparent; animation: bdLinkPulse .65s ease-in-out 1; }
  @keyframes bdLinkPulse { 0%, 100% { opacity: 1; } 50% { opacity: .65; } }
  @media (prefers-reduced-motion: reduce) { .bd .bd-text-link:hover, .bd .bd-text-link:focus-visible { animation: none; } }
  .bd-entry-fields { display: grid; grid-template-columns: minmax(100px, 140px) minmax(0, 1fr); gap: 10px 14px; margin: 12px 0 4px; font-size: var(--fs-sm); color: var(--ink-soft); }
  .bd-entry-fields dt { margin: 0; font-weight: 600; }
  .bd-entry-fields dd { margin: 0; font-weight: 400; overflow-wrap: anywhere; }
  @media (max-width: 640px) { .bd-entry-fields { grid-template-columns: 100px minmax(0, 1fr); gap: 10px; } }
  .bd-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 10px 16px; border-top: 1px solid var(--line-soft); font-size: var(--fs-sm); }
  .bd-pages { display: flex; align-items: center; gap: 4px; margin-left: auto; }
  .bd-page-now { padding: 0 8px; color: var(--ink-soft); font-size: var(--fs-xs); font-variant-numeric: tabular-nums; white-space: nowrap; }

  /* ── เส้นทาง + แท็บของหน้าย่อย ── */
  .bd-tabs { display: flex; gap: 22px; border-bottom: 1px solid var(--line); overflow-x: auto; }
  .bd-tabs a { padding: 8px 1px 10px; white-space: nowrap; color: var(--ink-soft); text-decoration: none; border-bottom: 2px solid transparent; font-size: var(--fs-base); }
  .bd-tabs a[aria-current="page"] { color: var(--navy-900); font-weight: 700; border-bottom-color: var(--navy-800); }
  .bd-sort { margin-left: auto; display: flex; align-items: center; gap: 6px; white-space: nowrap; font-size: var(--fs-xs); }
  .bd-sort select { max-width: 180px; padding: 6px; color: var(--ink); background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); }
  @media (max-width: 640px) { .bd-tabs { flex-wrap: wrap; gap: 10px; } .bd-sort { width: 100%; justify-content: flex-end; } }
  .bd-empty { padding: 44px 16px; text-align: center; }
  .bd-empty h2 { font-size: var(--fs-lg); color: var(--navy-900); margin-bottom: 6px; }
  .bd-empty p { color: var(--muted); margin-bottom: 14px; }
  .bd-source summary { cursor: pointer; padding: 11px 16px; font-size: var(--fs-sm); }

  /* จอกลาง: ยอด 4 ช่องลงบรรทัดใต้ตัวกรอง */
  @media (max-width: 1280px) {
    .bd-stats { flex-basis: 100%; margin-left: 0; }
  }
  @media (max-width: 760px) {
    .bd-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .bd-stat:nth-child(3) { border-left: 0; }
    .bd-stat:nth-child(n+3) { border-top: 1px solid var(--line-soft); }
  }
  /*
    🔴 กฎจอแคบของโครงเมนู/แถบบน/แถบเครื่องมือ ถูกยกไปไว้ที่ layouts/app แล้ว (2026-09-18)

       ของเดิมหน้านี้เขียนกฎของตัวเองด้วย `body:has(.bd)` ซึ่ง specificity สูงกว่ากฎกลาง
       ผลคือ **แดชบอร์ดจัดหน้ามือถือคนละแบบกับหน้าอื่นทั้งระบบ** (ปุ่มเมนูไปอยู่แถวเครื่องมือ
       ส่วนหน้าอื่นอยู่ในแถบบน) ซึ่งเป็นสาเหตุที่เจ้าของบอกว่า "ไม่ชอบการจัดเรียงเลย" (2026-09-18)

       ตามกฎโปรเจค: ของที่ใช้ร่วมทั้งระบบต้องอยู่ที่ชิ้นส่วนกลาง ห้ามให้แต่ละหน้าทำเอง
       ส่วน `minmax(0, 1fr)` ที่หน้านี้ค้นพบว่าจำเป็น (ตรวจที่ 400px) ถูกยกไปใช้เป็นกฎกลางด้วย
  */
  @media (max-width: 560px) {
    .bd-filter { flex-basis: 100%; }
    .bd-filter select { flex: 1 1 140px; max-width: none; }
    .bd-stat { padding: 7px 10px; }
    .bd-stat-value { font-size: var(--fs-sm); }
  }
</style>
@endpush

@section('content')
<div class="bd" data-budget-dashboard>
  <header class="bd-head">
    {{-- 🔴 หัวเรื่องเดิม "งบประมาณและการใช้เงิน" กลายเป็นแถบแท็บ 2 มุมมอง (เจ้าของสั่ง 2026-09-17) --}}
    @if($canSeeBudget)
      @include('core.partials.dash-tabs')
      @if($filters['dept'] !== '' && $heading_dept_th !== '')<span class="bd-head-dept">{{ $L('แผนก '.$heading_dept_th, $heading_dept_en.' department') }}</span>@endif
    @else
      <h1>{{ $L('งบประมาณและการใช้เงิน', 'Budget overview') }}</h1>
    @endif
  </header>
  @if(session('dashboard_department_reset'))
    <p class="bd-meta">{{ $L('เปลี่ยนมาใช้แผนกตาม ERP แล้ว กรุณาเลือกแผนกอีกครั้ง', 'Departments now follow ERP. Please select a department again.') }}</p>
  @endif

  @if(!$canSeeBudget)
    <div class="card bd-empty"><h2>{{ $L('ไม่มีสิทธิ์ดูข้อมูลงบประมาณ', 'Budget access is unavailable') }}</h2><p>{{ $L('ติดต่อผู้ดูแลเพื่อกำหนดสิทธิ์การใช้งาน', 'Contact your administrator to request access.') }}</p></div>
  @elseif(!$erpReady)
    <div class="card bd-empty"><h2>{{ $L('ยังอ่านข้อมูล ERP ไม่สำเร็จ', 'ERP data could not be retrieved') }}</h2><p>{{ $L('กรุณาตรวจการเชื่อมต่อแล้วลองใหม่', 'Check the connection and retry.') }}</p><a class="btn" href="{{ $href() }}">{{ $L('ลองอีกครั้ง', 'Retry') }}</a></div>
  @elseif($compare)
    {{--
      🔴 โหมดเปรียบเทียบกินทั้งหน้า (เจ้าของสั่ง 2026-09-16)
         ไม่ใช่การ์ดต่อท้ายเนื้อหาปกติเหมือนของเดิม เพราะผู้ใช้ต้องเลื่อนหาว่าผลอยู่ไหน
         ออกจากโหมดนี้ด้วยปุ่ม "<" มุมซ้ายบน
    --}}
    @include($isExpense ? 'core.partials.expense-compare-view' : 'core.partials.compare-view')
  @else
    {{--
      ═══ แถวสรุป: ตัวกรอง (ซ้าย ไม่มีการ์ด) + ยอด 4 ช่อง (ขวา ในการ์ด) (เจ้าของสั่ง 2026-09-14) ═══
      ตัวกรองเลือกแล้วโหลดทันที ไม่มีปุ่ม · ไม่ระบุปี = ปีปัจจุบัน · "ทุกปี" = year=all
    --}}
    <section class="bd-summary">
    <form class="bd-filter" method="get" action="{{ route('dashboard') }}" data-dashboard-filter data-auto-submit>
      <select name="company" aria-label="บริษัท / Company">
        <option value="" data-loc-th="ทุกบริษัท" data-loc-en="All companies">ทุกบริษัท</option>
        @foreach($companyOptions as $co)<option value="{{ $co['id'] }}" @selected($filters['company']===$co['id']) data-loc-th="{{ $co['th'] }}" data-loc-en="{{ $co['en'] }}">{{ $co['th'] }}</option>@endforeach
      </select>
      {{--
        🔴 แท็บค่าใช้จ่ายไม่มี "ทุกปี" / "งบที่ยังไม่จัดปี" (เจ้าของเลือก 2026-09-17)
           ถังงบหมื่นกว่าใบเกินเวลารอ 30 วิของ ERP — ดูเหตุผลที่ DashboardController::index()
      --}}
      <select name="year" aria-label="ปีงบ / Budget year">
        @unless($isExpense)<option value="all" @selected($filters['year']==='all') data-loc-th="ทุกปี" data-loc-en="All years">ทุกปี</option>@endunless
        @foreach($years as $year)<option value="{{ $year }}" @selected($filters['year']===(string)$year)>{{ $year }}</option>@endforeach
        @unless($isExpense)<option value="unassigned" @selected($filters['year']==='unassigned') data-loc-th="งบที่ยังไม่จัดปี" data-loc-en="Unclassified year">งบที่ยังไม่จัดปี</option>@endunless
      </select>
      <select name="dept" aria-label="แผนก / Department">
        <option value="" data-loc-th="ทุกแผนก" data-loc-en="All departments">ทุกแผนก</option>
        @foreach($deptOptions as $key=>$name)<option value="{{ $key }}" @selected($filters['dept']===$key) data-loc-th="{{ $name['th'] }}" data-loc-en="{{ $name['en'] }}">{{ $name['th'] }}</option>@endforeach
      </select>
      {{--
        ไตรมาส (เจ้าของสั่ง 2026-09-16) — อยู่ขวาของ "ทุกแผนก"
        🔴 คีย์มาจาก DIMENSION3_ (Propose) เท่านั้น เช่น BG26Q1 · ห้ามเดาจากวันที่
           STARTDATE คือวันที่ตั้งงบ (ตั้งล่วงหน้าข้ามปีได้) เดาแล้วผิด 17.9%
        🔴 "ไม่ระบุไตรมาส" ต้องมีเสมอเมื่อมีข้อมูลชนิดนั้น — งบโปรเจค/New Model
           ตั้งทั้งโครงการข้ามปี ไม่มีไตรมาสจริงๆ ถ้าไม่มีช่องนี้ยอดจะหายเงียบๆ
      --}}
      @if($isExpense)
        {{--
          ช่วงวันที่จ่ายจริง (เจ้าของสั่ง 2026-09-17) — ไตรมาส → เดือน → วัน แคบลงทีละชั้น
          🔴 ไตรมาสในแท็บนี้คิดจาก "วันที่จ่าย" (Q2 = เม.ย.–มิ.ย. เสมอ) ไม่ใช่ถัง BG26Q2
          🔴 ช่องวันขึ้นเฉพาะตอนเลือกเดือนแล้ว — ทั้งปีมีหลายร้อยวัน ใส่ในช่องเดียวไม่ไหว
        --}}
        <input type="hidden" name="tab" value="expense">
        @php $exPeriods = $ex['periods'] ?? ['quarters' => [], 'months' => [], 'days' => []]; @endphp
        <select name="paid_q" aria-label="ไตรมาสที่จ่าย / Payment quarter">
          <option value="" data-loc-th="ทุกไตรมาส" data-loc-en="All quarters">ทุกไตรมาส</option>
          @foreach($exPeriods['quarters'] as $key)
            @php [$th, $en] = $paidText('quarter', $key); @endphp
            <option value="{{ $key }}" @selected($filters['paid_q'] === $key) data-loc-th="{{ $th }}" data-loc-en="{{ $en }}">{{ $th }}</option>
          @endforeach
        </select>
        <select name="paid_m" aria-label="เดือนที่จ่าย / Payment month">
          <option value="" data-loc-th="ทุกเดือน" data-loc-en="All months">ทุกเดือน</option>
          @foreach($exPeriods['months'] as $key)
            @php [$th, $en] = $paidText('month', $key); @endphp
            <option value="{{ $key }}" @selected($filters['paid_m'] === $key) data-loc-th="{{ $th }}" data-loc-en="{{ $en }}">{{ $th }}</option>
          @endforeach
        </select>
        {{-- 🔴 ช่องวันมีเสมอ (เจ้าของสั่ง 2026-09-17) — ไม่ต้องเลือกเดือนก่อน
             รายการวันแคบตามชั้นที่เลือกไว้ และเลือกวันแล้วเซิร์ฟเวอร์เติมเดือน/ไตรมาสให้เอง --}}
        <select name="paid_d" aria-label="วันที่จ่าย / Payment date">
          <option value="" data-loc-th="ทุกวัน" data-loc-en="All days">ทุกวัน</option>
          @foreach($exPeriods['days'] as $key)
            @php [$th, $en] = $paidText('day', $key); @endphp
            <option value="{{ $key }}" @selected($filters['paid_d'] === $key) data-loc-th="{{ $th }}" data-loc-en="{{ $en }}">{{ $th }}</option>
          @endforeach
        </select>
      @else
        {{-- งวดงบ = ถังงบของ ERP (BG26Q1) — ป้ายเปลี่ยนจาก "ไตรมาส Q1" ตามที่เจ้าของสั่ง 2026-09-17 --}}
        <select name="quarter" aria-label="งวดงบ / Budget period">
          <option value="" data-loc-th="ทุกงวดงบ" data-loc-en="All budget periods">ทุกงวดงบ</option>
          @foreach($quarters as $q)
            <option value="{{ $q }}" @selected($filters['quarter']===(string)$q)>{{ $quarterLabel($q, $filters['year']) }}</option>
          @endforeach
          @if($hasUnassignedQuarter)
            <option value="unassigned" @selected($filters['quarter']==='unassigned')
                    data-loc-th="ไม่ระบุงวดงบ" data-loc-en="No budget period">ไม่ระบุงวดงบ</option>
          @endif
        </select>
      @endif
      {{--
        🔴 ปรับตัวกรองแล้วต้อง "อยู่หน้าเดิม" (เจ้าของสั่ง 2026-09-16)
           ของเดิมตั้งค่าตายตัวเป็น budgets และไม่พา kind ไปด้วย
           อยู่หน้า "ประวัติสั่งซื้อ" แล้วเปลี่ยนไตรมาส เลยเด้งไปหน้า "รายการงบ" ทุกที

           ส่ง view/kind ตามหน้าที่เปิดอยู่จริง · ปล่อยให้ budget/record/page/sort หลุดไป
           เพราะเปลี่ยนขอบเขตแล้วงบก้อนเดิมกับเลขหน้าเดิมอาจไม่มีอยู่แล้ว
      --}}
      @if($filters['view'] !== 'overview' && $filters['dept'] !== '')
        <input type="hidden" name="view" value="{{ $filters['view'] }}">
        @if($filters['view'] === 'activity')<input type="hidden" name="kind" value="{{ $filters['kind'] }}">@endif
        {{-- อยู่ในงบก้อนหนึ่งแล้วเปลี่ยนไตรมาส ให้ยังอยู่ในงบก้อนเดิม (ก้อนงบครอบทุกไตรมาสของ Model นั้น)
             ถ้าขอบเขตใหม่ไม่มีก้อนนี้จริงๆ ฝั่งเซิร์ฟเวอร์จะถอยออกมาระดับแผนกให้เอง --}}
        @if($filters['budget'] !== '')<input type="hidden" name="budget" value="{{ $filters['budget'] }}">@endif
      @endif
      @if($filters['company'] !== '' || $filters['year'] !== (string)now()->year || $filters['quarter'] !== '' || $filters['dept'] !== '' || $filters['paid_q'] !== '')<a class="bd-link" href="{{ route('dashboard', $isExpense ? ['tab' => 'expense'] : []) }}">{{ $L('ล้างตัวกรอง', 'Clear filters') }}</a>@endif
      <span class="bd-meta" data-loading hidden>{{ $L('กำลังโหลด…', 'Loading…') }}</span>
      {{--
        เวลาที่อ่าน ERP (เจ้าของขอกลับมา 2026-09-18)
        🔴 ระบบอ่าน ERP สดทุกครั้งที่เปิดหน้า ไม่มีแคช — บรรทัดนี้คือหลักฐานว่าตัวเลขสดจริง
           เวลาที่เห็นคือเวลาไทยเสมอ (APP_TIMEZONE=Asia/Bangkok)
      --}}
      @if($erpReady && $fetchedAt)
        <span class="bd-meta bd-fetched" title="อ่านข้อมูลจาก ERP สดทุกครั้งที่เปิดหน้า ไม่มีข้อมูลค้าง / Read live from ERP on every page load — nothing cached"
              data-i18n-title="dash.liveHint">{{ $L('อ่านข้อมูลเมื่อ', 'Read at') }} {{ substr($fetchedAt, 11) }}</span>
      @endif
    </form>

      @if($isExpense)
      {{--
        การ์ดเดียว "ค่าใช้จ่าย" (เจ้าของสั่ง 2026-09-17) — จำนวนรายการ + ยอด + เทียบช่วงก่อนหน้า
        🔴 เทียบช่วงก่อนหน้าขึ้นเฉพาะตอนเลือกไตรมาส/เดือน/วัน (QoQ · MoM · DoD)
           ฐานเป็น 0 เขียนตรงๆ ว่าช่วงก่อนหน้าไม่มีรายการ ห้ามเดาเป็น 0% หรือ 100%
      --}}
      @php
        $exTotal = $ex['total'] ?? ['n' => 0, 'amount' => 0];
        $exPrev = $ex['previous'] ?? null;
        $exMode = ['day' => 'DoD', 'month' => 'MoM', 'quarter' => 'QoQ'];
      @endphp
      <div class="card bd-stats is-single" data-dash-stats>
        <div class="bd-stat">
          <div class="bd-stat-label"><i class="bd-dot used"></i>{{ $L('ค่าใช้จ่าย', 'Expenses') }}<small>{{ number_format($exTotal['n']) }} {{ $L('รายการ', 'entries') }}</small></div>
          <div class="bd-stat-value bd-used">{{ $money($exTotal['amount']) }}</div>
          @if($exPrev)
            @php
              [$prevTh, $prevEn] = $paidText($exPrev['window']['level'], $exPrev['window']['key']);
              $exPct = $exPrev['pct'];
              $exDir = $exPct === null ? '' : (round($exPct, 2) > 0 ? 'up' : (round($exPct, 2) < 0 ? 'down' : ''));
            @endphp
            <span class="bd-stat-cmp">
              <span>{{ $exMode[$exPrev['window']['level']] }}</span>
              @if($exPct === null)
                <span>{{ $L($prevTh.' ไม่มีรายการ', 'no entries in '.$prevEn) }}</span>
              @else
                <b class="{{ $exDir }}">{{ $exDir === 'up' ? '▲ +' : ($exDir === 'down' ? '▼ ' : '') }}{{ number_format($exPct, 2) }}%</b>
                <span>{{ $L('จาก '.$prevTh.' ('.$money($exPrev['total']['amount']).')', 'vs '.$prevEn.' ('.$money($exPrev['total']['amount']).')') }}</span>
              @endif
            </span>
          @endif
        </div>
      </div>
      @else
      {{-- ยอด 4 ช่อง (การ์ด) — บรรทัดบน: ชื่อ + % (หรือจำนวนรายการ) · บรรทัดล่าง: ยอดเงิน --}}
      <div class="card bd-stats" data-dash-stats>
        <div class="bd-stat">
          <div class="bd-stat-label"><i class="bd-dot budget"></i>{{ $L('งบประมาณ', 'Budget') }}<small>{{ number_format($total['count']) }} {{ $L('รายการ', 'rows') }}</small></div>
          <div class="bd-stat-value">{{ $money($total['budget']) }}</div>
          @if($foreign)<span class="bd-fx" title="ไม่รวมในยอดบาท / Not included in THB">{{ $fx_text($foreign) }}</span>@endif
        </div>
        <div class="bd-stat">
          <div class="bd-stat-label"><i class="bd-dot used"></i>{{ $L('ใช้ไป', 'Actual') }}<small>{{ $pct($total['actual'], $total['budget']) }}</small></div>
          <div class="bd-stat-value bd-used">{{ $money($total['actual']) }}</div>
        </div>
        <div class="bd-stat">
          <div class="bd-stat-label"><i class="bd-dot reserve"></i>{{ $L('รอตัดจ่าย', 'Committed') }}<small>{{ $pct($total['reserve'], $total['budget']) }}</small></div>
          <div class="bd-stat-value bd-reserve">{{ $money($total['reserve']) }}</div>
        </div>
        <div class="bd-stat">
          <div class="bd-stat-label"><i class="bd-dot left"></i>{{ $L('คงเหลือ', 'Available') }}<small>@if($total['available'] < 0){{ $L('เกินงบ', 'Over budget') }}@else{{ $pct($total['available'], $total['budget']) }}@endif</small></div>
          <div class="bd-stat-value {{ $total['available'] < 0 ? 'bd-used' : 'bd-left' }}">{{ $money($total['available']) }}</div>
        </div>
      </div>
      @endif
    </section>

    <div class="bd-body">
      {{--
        แถบ "ยังมีงบที่ไม่ระบุรอบปี" เจ้าของสั่งเอาออก 2026-09-14 — ซ้ำกับตัวเลือก "งบที่ยังไม่จัดปี" ในตัวกรองปี
        (ตรวจแล้วตัวเลขตรงกันทุกสตางค์ จึงไม่มีข้อมูลหาย)
      --}}

      {{--
        แถบ "คงเหลือของ ERP ต่างจากสูตร" เจ้าของสั่งเอาออก 2026-09-14
        หน้าจอแสดงยอดคงเหลือตาม ERP อยู่แล้ว ส่วนต่างเป็นเรื่องให้บัญชีตรวจในระบบ ERP ไม่ใช่ข้อมูลที่ผู้บริหารต้องเห็น
        (ErpDashboard::totals() ยังคิด difference ไว้ให้ตรวจได้)
      --}}

      {{-- แถบเส้นทาง "ภาพรวมแผนก / แผนก" เจ้าของสั่งเอาออก 2026-09-14 — กลับภาพรวมใช้ตัวกรองแผนก "ทุกแผนก" --}}

      @if(!$rows && !$foreign)
        <div class="card bd-empty"><h2>{{ $L('ไม่พบงบในขอบเขตที่เลือก', 'No budgets match these filters') }}</h2><p>{{ $L('ลองเลือกปี ไตรมาส หรือแผนกอื่น', 'Try another year, quarter or department.') }}</p></div>

      @elseif($isExpense)
        {{-- ═══ แท็บค่าใช้จ่ายตามวันที่ (เจ้าของสั่ง 2026-09-17) — กราฟรายแผนก / รายการของแผนก ═══ --}}
        @include('core.partials.expense')

      @elseif($filters['view'] === 'overview')
        {{-- ═══ กราฟ + ตารางรายแผนก (การ์ดเดียว — เรื่องเดียวกัน) ═══ --}}
        <section class="card bd-card" data-dash-card>
          <div class="card-head bd-card-head">
            <div class="bd-card-lead">
              <div class="bd-card-title">
                <b data-loc-th="{{ $firm['th'] }}" data-loc-en="{{ $firm['en'] }}">{{ $firm['th'] }}</b>
                <span>{{ $L('งบประมาณแต่ละแผนก', 'Budget by department') }}</span>
              </div>
              {{-- เรียงตามวงเงินงบ — ค่าเริ่มต้นสูงไปต่ำ ทุกครั้งที่โหลดหน้า (เจ้าของสั่ง 2026-09-14) · เรียงทั้งกราฟและตาราง --}}
              <div class="bd-kinds" role="group" aria-label="เรียงลำดับ" data-i18n-aria="sort.by">
                <button type="button" class="bd-kbtn is-on" data-sort="desc" aria-pressed="true" title="วงเงินมากไปน้อย" aria-label="วงเงินมากไปน้อย" data-i18n-title="sort.desc" data-i18n-aria="sort.desc">
                  <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><rect x="2" y="2.5" width="9" height="2.2" rx="1"></rect><rect x="2" y="6.9" width="6" height="2.2" rx="1"></rect><rect x="2" y="11.3" width="3" height="2.2" rx="1"></rect><path d="M13 2.5v8.2h1.8L12.5 14l-2.3-3.3H12V2.5h1Z"></path></svg>
                </button>
                <button type="button" class="bd-kbtn" data-sort="asc" aria-pressed="false" title="วงเงินน้อยไปมาก" aria-label="วงเงินน้อยไปมาก" data-i18n-title="sort.asc" data-i18n-aria="sort.asc">
                  <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><rect x="2" y="2.5" width="3" height="2.2" rx="1"></rect><rect x="2" y="6.9" width="6" height="2.2" rx="1"></rect><rect x="2" y="11.3" width="9" height="2.2" rx="1"></rect><path d="M13 14V5.8h1.8L12.5 2.5 10.2 5.8H12V14h1Z"></path></svg>
                </button>
                {{-- ปุ่มเปรียบเทียบ + หน้าต่างเลือกขอบเขต + ผลเทียบ (เจ้าของสั่ง 2026-09-16) --}}
                @include('core.partials.compare')
              </div>
            </div>
            <div class="bd-legend">
              <span><i class="bd-dot used"></i>{{ $L('ใช้ไป', 'Actual') }}</span>
              <span><i class="bd-dot reserve"></i>{{ $L('รอตัดจ่าย', 'Committed') }}</span>
              <span><i class="bd-dot left"></i>{{ $L('คงเหลือ', 'Available') }}</span>
            </div>
            <div class="bd-kinds" role="group" aria-label="ชนิดกราฟ" data-i18n-aria="chart.type">
              {{-- ปุ่มตาราง อยู่ซ้ายสุด แยกจากกลุ่มกราฟด้วยเส้นคั่น (เจ้าของสั่ง 2026-09-14) --}}
              <button type="button" class="bd-kbtn" data-kind="table" title="ตาราง" aria-label="ตาราง" data-i18n-title="chart.table" data-i18n-aria="chart.table">
                <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M2 2.5h12a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1Zm0 3.2v2.4h3.5V5.7H2Zm4.7 0v2.4H14V5.7H6.7ZM2 9.3v3.2h3.5V9.3H2Zm4.7 0v3.2H14V9.3H6.7Z"></path></svg>
              </button>
              <span class="bd-kinds-sep" aria-hidden="true"></span>
              <button type="button" class="bd-kbtn" data-kind="bar-v" title="แท่งแนวตั้ง" aria-label="แท่งแนวตั้ง" data-i18n-title="chart.barV" data-i18n-aria="chart.barV">
                <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><rect x="1.5" y="7" width="3" height="7.5" rx="1"></rect><rect x="6.5" y="3.5" width="3" height="11" rx="1"></rect><rect x="11.5" y="9.5" width="3" height="5" rx="1"></rect></svg>
              </button>
              <button type="button" class="bd-kbtn" data-kind="bar-h" title="แท่งแนวนอน" aria-label="แท่งแนวนอน" data-i18n-title="chart.barH" data-i18n-aria="chart.barH">
                <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><rect x="1.5" y="2.5" width="11" height="3" rx="1"></rect><rect x="1.5" y="6.5" width="7" height="3" rx="1"></rect><rect x="1.5" y="10.5" width="13" height="3" rx="1"></rect></svg>
              </button>
              <button type="button" class="bd-kbtn" data-kind="pie" title="วงกลม" aria-label="วงกลม" data-i18n-title="chart.pie" data-i18n-aria="chart.pie">
                <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M8 8 V1.2 A6.8 6.8 0 0 1 14.8 8 Z"></path><path d="M8 1.2 A6.8 6.8 0 1 0 14.8 8 H8 Z" opacity=".45"></path></svg>
              </button>
              {{--
                เต็มจอ (เจ้าของสั่ง 2026-09-14) — ขยายทั้งการ์ด ปุ่มสลับตาราง/กราฟอยู่ในการ์ด
                จึงสลับมุมมองได้โดยไม่หลุดจากโหมดเต็มจอ · ออกได้ด้วยปุ่มนี้หรือ Esc
              --}}
              <span class="bd-kinds-sep" aria-hidden="true"></span>
              <button type="button" class="bd-kbtn" data-chart-zoom="out" aria-label="ซูมออก / Zoom out">−</button>
              <button type="button" class="bd-kbtn" data-chart-zoom="reset" aria-label="คืนขนาดกราฟ / Reset chart zoom">100%</button>
              <button type="button" class="bd-kbtn" data-chart-zoom="in" aria-label="ซูมเข้า / Zoom in">+</button>
              <button type="button" class="bd-kbtn" data-dash-full title="เต็มจอ" aria-label="เต็มจอ" aria-pressed="false" data-i18n-title="chart.fullscreen" data-i18n-aria="chart.fullscreen">
                <svg data-icon-open viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M2 6V2h4v1.6H3.6V6H2Zm8-4h4v4h-1.6V3.6H10V2ZM3.6 10v2.4H6V14H2v-4h1.6Zm8.8 0H14v4h-4v-1.6h2.4V10Z"></path></svg>
                <svg data-icon-close hidden viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M4.4 2H6v4H2V4.4h2.4V2Zm5.6 0h1.6v2.4H14V6h-4V2ZM2 10h4v4H4.4v-2.4H2V10Zm8 0h4v1.6h-2.4V14H10v-4Z"></path></svg>
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="bd-chart" data-dash-chart role="img" aria-label="กราฟงบประมาณรายแผนก / Budget chart by department"></div>
            {{-- ตารางรายแผนก — ย้ายจากใต้การ์ดมาเป็นมุมมอง "ตาราง" ในกรอบเดียวกับกราฟ (เจ้าของสั่ง 2026-09-14) --}}
            <div class="bd-table-panel" data-dash-table hidden>
            {{-- ตารางไม่แบ่งหน้า (ทุกแผนกอยู่ในหน้าแล้ว) จึงกรองด้วย JavaScript ได้ครบ --}}
            <table class="tbl bd-tbl" data-filterable>
              <thead><tr>
                <th class="col-seq" data-no-filter>{{ $L('ลำดับ', 'No.') }}</th>
                <th class="txt-left">{{ $L('แผนก', 'Department') }}</th>
                <th>{{ $L('รายการ', 'Rows') }}</th>
                <th class="txt-right">{{ $L('งบประมาณ', 'Budget') }}</th>
                <th class="txt-right">{{ $L('ใช้ไป', 'Actual') }}</th>
                <th class="txt-right">{{ $L('รอตัดจ่าย', 'Committed') }}</th>
                <th class="txt-right">{{ $L('คงเหลือ', 'Available') }}</th>
                <th>{{ $L('% ใช้ไป', '% used') }}</th>
                <th data-no-filter>{{ $L('งบคงเหลือติดลบ', 'Negative budget balances') }}</th>
              </tr></thead>
              <tbody>
                @foreach($departments as $i => $d)
                  <tr data-dept-row data-budget="{{ $d['total']['budget'] }}">
                    <td class="col-seq">{{ $i + 1 }}</td>
                    <td class="txt-left"><a href="{{ $href(array_replace($budget_view, ['dept'=>$d['key']])) }}" data-loc-th="{{ $chart['items'][$i]['name_th'] }}" data-loc-en="{{ $chart['items'][$i]['name_en'] }}">{{ $chart['items'][$i]['name_th'] }}</a></td>
                    <td>{{ number_format($d['total']['count']) }}</td>
                    <td class="num">{{ $money($d['total']['budget']) }}@if($d['foreign'])<span class="bd-fx">{{ $fx_text($d['foreign']) }}</span>@endif</td>
                    <td class="num bd-used">{{ $money($d['total']['actual']) }}</td>
                    <td class="num bd-reserve">{{ $money($d['total']['reserve']) }}</td>
                    <td class="num {{ $d['total']['available'] < 0 ? 'bd-used' : 'bd-left' }}">{{ $money($d['total']['available']) }}</td>
                    <td>{{ $pct($d['total']['actual'], $d['total']['budget']) }}</td>
                    <td>
                      @if($chart['items'][$i]['alerts'])
                        <a class="bd-alert" href="{{ $chart['items'][$i]['alerts_href'] }}">! {{ count($chart['items'][$i]['alerts']) }} {{ $L('รายการ', 'budgets') }}</a>
                      @else — @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
            </div>
            <p class="bd-meta bd-hint" data-dash-hint>{{ $L('หมุนล้อเมาส์เพื่อซูม · ลากเพื่อเลื่อน · ชี้เพื่อดูยอด · คลิกเพื่อดูงบแผนก', 'Scroll to zoom · drag to pan · hover for figures · click for department budgets') }}</p>
          </div>
        </section>
        <script type="application/json" id="dash-chart-data">@json($chart)</script>

      @else
        @if($filters['dept'] !== '' && $filters['budget'] === '')
          <nav class="bd-tabs" aria-label="มุมมองแผนก / Department views">
            <a href="{{ $href($budget_view) }}" @if($filters['view']==='budgets') aria-current="page" @endif>{{ $L('รายการงบ', 'Budgets') }}</a>
            <a href="{{ $href(['view'=>'activity','kind'=>'actual','page'=>1]) }}" @if($filters['view']==='activity' && $filters['kind']==='actual') aria-current="page" @endif>{{ $L('ประวัติใช้ไป (ทั้งหมด)', 'Consumption history (all)') }}</a>
            <a href="{{ $href(['view'=>'activity','kind'=>'purchases','page'=>1]) }}" @if($filters['view']==='activity' && $filters['kind']==='purchases') aria-current="page" @endif>{{ $L('ประวัติสั่งซื้อ (ทั้งหมด)', 'Purchase history (all)') }}</a>
            @if($filters['view'] === 'activity')
              <label class="bd-sort">{{ $L('เรียงตาม', 'Sort by') }}<select data-activity-sort>
                @foreach($sort_options as $key => $label)<option value="{{ $href(['sort'=>$key,'page'=>1]) }}" @selected($filters['sort'] === $key) data-loc-th="{{ $label[0] }}" data-loc-en="{{ $label[1] }}">{{ $label[0] }}</option>@endforeach
              </select></label>
            @endif
          </nav>
        @endif

        @if($filters['view'] === 'budgets')
          {{-- ═══ รายการงบ (กลุ่มตาม Model + Budget No.) ═══ --}}
          <label class="bd-sort">{{ $L('สถานะงบ', 'Budget balance') }}
            <select data-activity-sort aria-label="สถานะงบ / Budget balance">
              <option value="{{ $href(['highlight'=>'','page'=>1,'focus'=>null]) }}" @selected(!$highlight_negative) data-loc-th="งบทั้งหมด" data-loc-en="All budgets">งบทั้งหมด</option>
              <option value="{{ $href(['highlight'=>'negative','page'=>1,'focus'=>null]) }}#negative-budgets" @selected($highlight_negative) data-loc-th="งบคงเหลือติดลบ" data-loc-en="Negative balance budgets">งบคงเหลือติดลบ</option>
            </select>
          </label>
          <section class="card">
            <div class="card-head"><h3>{{ $L('รายการงบประมาณ', 'Budget allocations') }}</h3><span class="bd-meta">{{ number_format(count($groups)) }}@if($filterPicked) {{ $L('จาก', 'of') }} {{ number_format($groupsTotal) }}@endif {{ $L('กลุ่มงบ', 'budget groups') }}@if($filterPicked) · <a class="bd-link" href="{{ $href(['page' => 1, 'view' => 'budgets']) }}">{{ $L('ล้างตัวกรองคอลัมน์', 'Clear column filters') }}</a>@endif</span></div>
            <div class="table-scroll">
              {{-- ตารางแบ่งหน้า → กรองที่เซิร์ฟเวอร์ครบทุกหน้า (data-filter-server) · ข้อความที่กรองต้องตรงกับช่องตาราง (DashboardController::group_filter_map) --}}
              <table class="tbl bd-tbl" data-filter-server>
                <thead><tr>
                  <th class="col-seq">{{ $L('ลำดับ', 'No.') }}</th>
                  <th class="txt-left" data-filter-key="name">{{ $L('ชื่องบ', 'Budget') }}</th>
                  <th data-filter-key="start">{{ $L('วันที่เริ่มงบ', 'Budget start date') }}</th>
                  <th class="txt-right" data-filter-key="budget">{{ $L('งบประมาณ', 'Budget') }}</th>
                  <th class="txt-right" data-filter-key="actual">{{ $L('ใช้ไป', 'Actual') }}</th>
                  <th class="txt-right" data-filter-key="reserve">{{ $L('รอตัดจ่าย', 'Committed') }}</th>
                  <th class="txt-right" data-filter-key="available">{{ $L('คงเหลือ', 'Available') }}</th>
                </tr></thead>
                <tbody>
                  @foreach($visible_groups as $n => $g)
                    @php
                      $thb = $g['currency'] === 'THB';
                      $unit = $thb ? '' : ' '.\App\Services\Erp\ErpDashboard::currency_label((string)$g['currency']);
                      $open = $href(['view'=>'activity','dept'=>$g['first']['dept_group'],'budget'=>$g['key'],'record'=>'','kind'=>'actual','page'=>1]);
                      $name = implode(' / ', $g['titles']) ?: $g['first']['budget_no'];
                    @endphp
                    <tr id="budget-{{ hash('sha256', $g['key'].'|'.$g['currency']) }}" class="{{ $g['total']['available'] < 0 ? 'bd-negative-row' : '' }}" tabindex="-1">
                      <td class="col-seq">{{ ($budget_page - 1) * 25 + $n + 1 }}</td>
                      <td class="txt-left bd-title">
                        @if($thb)<a href="{{ $open }}">{{ $name }}</a>@else{{ $name }}@endif
                        <span class="bd-code">{{ $g['first']['budget_no'] }}@if(in_array($filters['year'], ['', 'all', 'unassigned'], true)) · {{ $g['first']['model'] }}@endif</span>
                        @if($filters['dept'] === '')<span class="bd-code">{{ $g['first']['dept_name'] }} · {{ $g['first']['company'] }}</span>@endif
                      </td>
                      <td style="white-space:nowrap">{{ min(array_column($g['rows'], 'start_date')) }}@if(min(array_column($g['rows'], 'start_date')) !== max(array_column($g['rows'], 'start_date')))<span class="bd-code">{{ $L('ถึง', 'to') }} {{ max(array_column($g['rows'], 'start_date')) }}</span>@endif</td>
                      <td class="num">{{ $money($g['total']['budget']) }}{{ $unit }}</td>
                      <td class="num bd-used">@if($thb)<a class="bd-used" href="{{ $open }}">{{ $money($g['total']['actual']) }}</a>@else{{ $money($g['total']['actual']) }}{{ $unit }}@endif</td>
                      <td class="num bd-reserve">{{ $money($g['total']['reserve']) }}{{ $unit }}</td>
                      <td class="num {{ $g['total']['available'] < 0 ? 'bd-used' : 'bd-left' }}">{{ $money($g['total']['available']) }}{{ $unit }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
              @if(!$visible_groups)<p class="bd-empty">{{ $L('ไม่พบงบตามตัวกรองที่เลือก', 'No budgets match the selected filters') }}</p>@endif
              @include('layouts.partials.table-filter-data', ['filterOptions' => $filterOptions, 'filterPicked' => $filterPicked])
            </div>
            @if($budget_pages > 1)
              <div class="bd-foot">@include('core.partials.dash-pager', ['page' => $budget_page, 'pages' => $budget_pages, 'href' => $href])</div>
            @endif
          </section>

        @else
          {{-- ═══ ใช้เงินไปกับอะไร (รายการตัดงบ / PO) ═══ --}}
          {{-- ข้อความ "ขอบเขตรายการ: …" เจ้าของสั่งเอาออก 2026-09-14 --}}
          @if($filters['budget'] && $first)
            <div>
              <h2 class="bd-subject">{{ implode(' / ', array_values(array_unique(array_column($rows, 'title')))) }}</h2>
              <div class="bd-meta">
                @if($filters['record'])
                  · {{ $L('กำลังดูงวดที่เลือก', 'Selected allocation only') }}
                  <a class="bd-link" href="{{ $href(['record'=>'','page'=>1]) }}">{{ $L('ดูทุกงวด', 'All allocations') }}</a>
                @endif
              </div>
            </div>
            <nav class="bd-tabs" aria-label="รายละเอียดงบ / Budget details">
              <a href="{{ $href(['kind'=>'actual','page'=>1]) }}" @if($filters['kind']==='actual') aria-current="page" @endif>{{ $L('รายการใช้ไป', 'Consumption') }}</a>
              <a href="{{ $href(['kind'=>'purchases','page'=>1]) }}" @if($filters['kind']==='purchases') aria-current="page" @endif>{{ $L('รายการสั่งซื้อ', 'Purchase orders') }}</a>
              <label class="bd-sort">{{ $L('เรียงตาม', 'Sort by') }}<select data-activity-sort>
                @foreach($sort_options as $key => $label)<option value="{{ $href(['sort'=>$key,'page'=>1]) }}" @selected($filters['sort'] === $key) data-loc-th="{{ $label[0] }}" data-loc-en="{{ $label[1] }}">{{ $label[0] }}</option>@endforeach
              </select></label>
            </nav>
          @endif

          @if($activityError)
            <div class="flash flash-error">{{ $L('อ่านรายการไม่สำเร็จ ยอดด้านบนยังไม่ใช่ผลรวมรายละเอียด กรุณาลองใหม่', 'Entries could not be loaded. The balances above are not a reconciled detail total. Please retry.') }} <a class="bd-link" href="{{ $href() }}">{{ $L('ลองอีกครั้ง', 'Retry') }}</a></div>
          @elseif($activity)
            {{-- กรองคอลัมน์อยู่ ยอดรายการย่อมไม่เท่ายอด ERP — ไม่ต้องแจ้งส่วนต่าง --}}
            @if($filters['kind'] === 'actual' && !$filterPicked && $total['actual'] !== $activity['total'])
              <p class="bd-meta" role="status">{{ $L('ยอดสรุป ERP ต่างจากรายการที่พบ', 'ERP balance differs from matched entries') }}: {{ $money($total['actual'] - $activity['total']) }} {{ $L('บาท · รอตรวจสอบกับบัญชี', 'THB · Accounting review required') }}</p>
            @endif
            @if($filters['kind'] === 'purchases' && str_starts_with($filters['sort'], 'amount_'))<p class="bd-meta">{{ $L('เรียงยอดภายในสกุลเงินเดียวกัน', 'Amounts sorted within each currency') }}</p>@endif
            {{--
              กล่องกระทบยอด "ใช้ไปตาม ERP / รวมรายการที่จับคู่ได้ / ส่วนต่าง" และหมายเหตุ "มูลค่าสั่งซื้อเป็นยอดตาม PO…"
              เจ้าของสั่งเอาออก 2026-09-14 · ErpDashboard::activity() ยังคืน total ไว้ให้ตรวจได้
            --}}
            @if($activity['ambiguous'])<div class="flash flash-error">{{ $activity['ambiguous'] }} {{ $L('รายการมีงบอ้างอิงหลายแถว จึงยังไม่รวมจนกว่าจะจับคู่ได้แน่นอน', 'entries have ambiguous allocation references and are excluded until they can be assigned reliably.') }}</div>@endif

            <section class="card">
              @php $clear_columns = $filterPicked ? $href(['page' => 1, 'view' => 'activity']) : null; @endphp
              <div class="card-head"><h3>{{ $filters['kind'] === 'actual' ? $L('รายการตัดใช้งบ', 'Budget consumption entries') : $L('รายการสั่งซื้อที่อ้างอิงงบ', 'Purchases referencing this budget') }}</h3><span class="bd-meta">{{ number_format($activity['count']) }} {{ $L('รายการ', 'entries') }}@if($clear_columns) · <a class="bd-link" href="{{ $clear_columns }}">{{ $L('ล้างตัวกรองคอลัมน์', 'Clear column filters') }}</a>@endif</span></div>
              @if(!$activity['count'])
                {{-- กรองจนไม่เหลือรายการ ตารางหาย ปุ่มกรองหายด้วย — ต้องมีทางล้างตัวกรอง --}}
                <div class="bd-empty"><h2>{{ $L('ไม่พบรายการในเงื่อนไขที่เลือก', 'No entries for the selected filters') }}</h2>@if($clear_columns)<a class="bd-link" href="{{ $clear_columns }}">{{ $L('ล้างตัวกรองคอลัมน์', 'Clear column filters') }}</a>@endif</div>
              @else
                <div class="table-scroll">
                  {{-- แบ่งหน้าด้วย SQL → กรองที่เซิร์ฟเวอร์ (ErpDashboard::filtered_sql) ครบทุกหน้า และยอด/จำนวนหน้าเดินตามตัวกรอง --}}
                  <table class="tbl bd-tbl" data-filter-server>
                    <thead><tr>
                      <th class="col-seq">{{ $L('ลำดับ', 'No.') }}</th>
                      <th data-filter-key="date">{{ $L('วันที่', 'Date') }}</th>
                      <th class="txt-left" data-filter-key="title">{{ $L('รายการ', 'Description') }}</th>
                      @if($filters['kind'] === 'purchases')<th data-filter-key="po">{{ $L('เลข PO', 'PO number') }}</th>@endif
                      @if($filters['kind'] === 'purchases')<th data-filter-key="supplier">{{ $L('ชื่อผู้ขาย', 'Supplier') }}</th>@endif
                      <th class="txt-right" data-filter-key="amount">{{ $filters['kind'] === 'actual' ? $L('ยอดตัดงบ (บาท)', 'Consumed (THB)') : $L('มูลค่าสั่งซื้อ', 'Ordered value') }}</th>
                      @if($filters['kind'] === 'purchases')<th data-filter-key="status">{{ $L('สถานะ PO', 'PO status') }}</th>@endif
                      <th data-no-filter>{{ $L('ดูรายละเอียด', 'View details') }}</th>
                    </tr></thead>
                    <tbody>
                      @foreach($activity['rows'] as $entry_index => $entry)
                        <tr>
                          <td class="col-seq">{{ ($activity['page'] - 1) * 40 + $entry_index + 1 }}</td>
                          <td style="white-space:nowrap">{{ $entry['date'] }}</td>
                          <td class="txt-left bd-title">{{ $entry['title'] ?: $L('ไม่ระบุรายละเอียด', 'No description provided') }}
                            <template id="entry-details-{{ $entry_index }}">
                              <dl class="bd-entry-fields">
                                <dt>{{ $L('วันที่', 'Date') }}</dt><dd>{{ $entry['date'] }}</dd>
                                <dt>{{ $filters['kind'] === 'actual' ? $L('ยอดตัดงบ', 'Consumed amount') : $L('มูลค่าสั่งซื้อ', 'Ordered value') }}</dt><dd>{{ $money($entry['amount']) }} {{ \App\Services\Erp\ErpDashboard::currency_label($entry['currency']) }}</dd>
                                @if($filters['kind'] === 'purchases')<dt>{{ $L('ชื่อผู้ขาย', 'Supplier') }}</dt><dd>{{ ($entry['supplier_name'] ?? '') ?: '—' }}</dd>@endif
                                <dt>{{ $L('จำนวน', 'Quantity') }}</dt>
                                <dd>{{ number_format($entry['qty'], 2) }}</dd>
                                @foreach([
                                  'unit'=>['หน่วยนับ','Unit'],
                                  'item'=>['รหัสสินค้า','Item code'],
                                  'line'=>['บรรทัด','Line'],
                                  'pr'=>['PR','PR'],
                                  'po'=>['PO','PO'],
                                  'invoice'=>['ใบแจ้งหนี้','Invoice'],
                                  'voucher'=>['ใบสำคัญ','Voucher'],
                                  'journal'=>['สมุดรายวัน','Journal'],
                                ] as $field => $label)
                                  @if($entry[$field] !== '')
                                    <dt>{{ $L($label[0], $label[1]) }}</dt>
                                    <dd>{{ $field === 'line' && str_contains($entry[$field], '.') ? rtrim(rtrim($entry[$field], '0'), '.') : $entry[$field] }}</dd>
                                  @endif
                                @endforeach
                              @if($filters['kind'] === 'purchases')
                                <dt>{{ $L('สถานะ PO', 'PO status') }}</dt>
                                <dd>@switch($entry['status'])@case(1){{ $L('ค้างส่งของ', 'Open order') }}@break @case(2){{ $L('รับของแล้ว', 'Received') }}@break @case(3){{ $L('ลงใบแจ้งหนี้แล้ว', 'Invoiced') }}@break @case(4){{ $L('ยกเลิก', 'Canceled') }}@break @default {{ $L('สถานะ', 'Status') }} {{ $entry['status'] }}@endswitch</dd>
                                <dt>{{ $L('ผู้ขอซื้อ', 'Requester') }}</dt>
                                <dd>{{ $entry['requester_name'] ?: ($entry['requester'] ?: $L('ไม่ระบุ', 'Not specified')) }}</dd>
                                @if($entry['requester'])
                                  <dt>{{ $L('รหัสผู้ขอซื้อ', 'Requester code') }}</dt><dd>{{ $entry['requester'] }}</dd>
                                @endif
                                <dt>{{ $L('ผู้สร้าง PO', 'PO creator') }}</dt>
                                <dd>{{ $entry['creator_name'] ?: ($entry['creator'] ?: '—') }}</dd>
                              @endif
                            @if(!empty($entry['budget_group']))
                              <dt>{{ $L('Model', 'Model') }}</dt><dd>{{ $entry['budget_model'] }}</dd>
                              <dt>{{ $L('งบอ้างอิง', 'Budget reference') }}</dt>
                              <dd><a class="bd-text-link" data-budget-reference href="{{ $href(['budget'=>$entry['budget_group'],'view'=>'activity','kind'=>$filters['kind'],'record'=>'','page'=>1]) }}">{{ $entry['budget_no'] }}</a></dd>
                              <dt>{{ $L('งวดงบ', 'Budget period') }}</dt><dd>{{ $entry['budget_period'] ?: '—' }}</dd>
                            @endif
                              </dl>
                            </template>
                          </td>
                          @if($filters['kind'] === 'purchases')<td>{{ $entry['po'] ?: '—' }}</td>@endif
                          @if($filters['kind'] === 'purchases')<td>{{ ($entry['supplier_name'] ?? '') ?: $L('ไม่ระบุ', 'Not specified') }}</td>@endif
                          {{-- ยอดตัดงบ และมูลค่าสั่งซื้อ (รายการสั่งซื้อ · ประวัติสั่งซื้อทั้งหมด) เป็นสีแดงทั้งคู่ (เจ้าของสั่ง 2026-09-14) · ป้ายสกุลเงินยังเป็นสีจาง --}}
                          <td class="num bd-used">{{ $money($entry['amount']) }}@if($filters['kind'] === 'purchases')<span class="bd-code">{{ \App\Services\Erp\ErpDashboard::currency_label($entry['currency']) }}</span>@endif</td>
                          @if($filters['kind'] === 'purchases')
                            <td style="white-space:nowrap">@switch($entry['status'])@case(1){{ $L('ค้างส่งของ', 'Open order') }}@break @case(2){{ $L('รับของแล้ว', 'Received') }}@break @case(3){{ $L('ลงใบแจ้งหนี้แล้ว', 'Invoiced') }}@break @case(4){{ $L('ยกเลิก', 'Canceled') }}@break @default {{ $L('สถานะ', 'Status') }} {{ $entry['status'] }}@endswitch</td>
                          @endif
                          <td><button type="button" class="bd-text-link" data-entry-open="entry-details-{{ $entry_index }}" data-entry-title="{{ $entry['title'] }}" aria-haspopup="dialog" aria-controls="entry-detail-modal">{{ $L('ดูรายละเอียด', 'View details') }}</button></td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                  @include('layouts.partials.table-filter-data', ['filterOptions' => $filterOptions, 'filterPicked' => $filterPicked])
                </div>
              @endif
              @if($activity['pages'] > 1)
                <div class="bd-foot">@include('core.partials.dash-pager', ['page' => $activity['page'], 'pages' => $activity['pages'], 'href' => $href])</div>
              @endif
            </section>
          @else
            <div class="flash">{{ $L('เลือกแผนกก่อนเปิดรายละเอียดรายการ', 'Select a department to open entry details.') }}</div>
          @endif
        @endif
      @endif

    </div>
  @endif
  <dialog id="entry-detail-modal" class="bd-entry-modal" aria-labelledby="entry-detail-title">
    <div class="bd-entry-modal-head">
      <div><p class="bd-meta">{{ $L('รายละเอียดรายการ', 'Entry details') }}</p><h2 id="entry-detail-title"></h2></div>
      <button type="button" class="btn btn-ghost btn-sm" data-entry-close autofocus>{{ $L('ปิด', 'Close') }}</button>
    </div>
    <div data-entry-body></div>
  </dialog>
</div>
@endsection

@push('page-script')
<script>
  'use strict';

  (function () {
    var root = document.querySelector('[data-budget-dashboard]');
    if (!root) { return; }
    var entryModal = root.querySelector('#entry-detail-modal');
    root.querySelectorAll('[data-entry-open]').forEach(function (button) {
      button.addEventListener('click', function () {
        var template = document.getElementById(button.getAttribute('data-entry-open'));
        if (!template) { return; }
        var language = window.BMS && window.BMS.lang ? window.BMS.lang() : 'th';
        entryModal.querySelector('#entry-detail-title').textContent = button.getAttribute('data-entry-title') || (language === 'en' ? 'No description provided' : 'ไม่ระบุรายละเอียด');
        var body = entryModal.querySelector('[data-entry-body]');
        body.replaceChildren(template.content.cloneNode(true));
        body.querySelectorAll('[data-loc-th]').forEach(function (label) {
          label.textContent = label.getAttribute(language === 'en' ? 'data-loc-en' : 'data-loc-th');
        });
        entryModal.showModal();
        entryModal.scrollTop = 0;
      });
    });
    entryModal.querySelector('[data-entry-close]').addEventListener('click', function () { entryModal.close(); });
    entryModal.addEventListener('click', function (e) {
      if (e.target !== entryModal) { return; }
      var bounds = entryModal.getBoundingClientRect();
      if (e.clientX < bounds.left || e.clientX > bounds.right || e.clientY < bounds.top || e.clientY > bounds.bottom) { entryModal.close(); }
    });
    root.querySelectorAll('[data-activity-sort]').forEach(function (select) {
      select.addEventListener('change', function () { window.location.href = select.value; });
    });

    /* ═══ ตัวกรอง — เลือกแล้วโหลดทันที ไม่มีปุ่ม (เจ้าของสั่ง 2026-09-14) ═══
       ประกอบ URL เอง เพื่อไม่ส่งช่องว่าง (?company=&year=) ให้ลิงก์รก */
    var form = root.querySelector('[data-dashboard-filter]');
    if (form) {
      form.addEventListener('change', function (e) {
        /*
          🔴 รหัสแผนกผูกกับบริษัท (si5|SPV-02) เปลี่ยนบริษัทแล้วใช้ต่อไม่ได้ ต้องล้าง
             แต่ "เปลี่ยนปี" ไม่ต้องล้างแผนกแล้ว (เจ้าของสั่ง 2026-09-16)
             อยากดูแผนกเดิมข้ามปี ซึ่งเป็นเรื่องปกติ · ถ้าปีใหม่ไม่มีแผนกนั้นจริงๆ
             ฝั่งเซิร์ฟเวอร์มีด่านล้างให้อยู่แล้ว ไม่ต้องล้างล่วงหน้าแบบเหมารวม
        */
        if (e.target.name === 'company') { form.elements.dept.value = ''; }
        /*
          🔴 แท็บค่าใช้จ่าย: ช่วงวันที่จ่ายแคบลงทีละชั้น (เจ้าของสั่ง 2026-09-17)
             เปลี่ยนชั้นนอกต้องล้างชั้นในที่อาจไม่มีแล้ว · เปลี่ยนบริษัท/ปีงบ = ช่วงที่มีรายการเปลี่ยนทั้งชุด
             ฝั่งเซิร์ฟเวอร์มีด่านล้างซ้ำอีกชั้นอยู่แล้ว ที่นี่ล้างก่อนเพื่อไม่ต้องเด้งหน้า 2 รอบ
        */
        var inner = { company: ['paid_q', 'paid_m', 'paid_d'], year: ['paid_q', 'paid_m', 'paid_d'],
          paid_q: ['paid_m', 'paid_d'], paid_m: ['paid_d'] }[e.target.name] || [];
        inner.forEach(function (name) { if (form.elements[name]) { form.elements[name].value = ''; } });
        var params = new URLSearchParams();
        Array.prototype.forEach.call(form.elements, function (el) {
          if (!el.name || el.value === '') { return; }
          // หน้ารายการ/ประวัติต้องมีแผนก — ไม่มีแผนกก็กลับไปหน้าภาพรวม
          if ((el.name === 'view' || el.name === 'kind') && form.elements.dept.value === '') { return; }
          params.set(el.name, el.value);
        });
        root.setAttribute('aria-busy', 'true');
        form.querySelector('[data-loading]').hidden = false;
        // ตัวกรองพาไปหน้าใหม่ด้วย location.href ซึ่งไม่ยิง submit จึงต้องเรียกจอโหลดเอง
        if (window.BMS && window.BMS.loading) { window.BMS.loading.show(); }
        window.location.href = form.action + (params.toString() ? '?' + params.toString() : '');
      });
      // กดย้อนกลับแล้วเบราว์เซอร์คืนหน้าจากแคช — ต้องเอาสถานะกำลังโหลดออก
      window.addEventListener('pageshow', function () {
        root.removeAttribute('aria-busy');
        form.querySelector('[data-loading]').hidden = true;
      });
    }

    /* ═══ กราฟ ═══ */
    if (window.location.hash.indexOf('#budget-') === 0 || window.location.hash === '#negative-budgets') {
      var focusedBudget = window.location.hash === '#negative-budgets' ? root.querySelector('.bd-negative-row') : document.getElementById(window.location.hash.slice(1));
      if (focusedBudget) {
        window.requestAnimationFrame(function () {
          focusedBudget.scrollIntoView({ block: 'center' });
          focusedBudget.focus({ preventScroll: true });
        });
      }
    }
    var box = root.querySelector('[data-dash-chart]');
    var source = document.getElementById('dash-chart-data');
    if (!box || !source) { return; }

    var chart = JSON.parse(source.textContent);
    var items = chart.items || [];
    /*
      🔴 โหมด spend = แท็บ "ค่าใช้จ่ายตามวันที่" (เจ้าของสั่ง 2026-09-17)
         ตัววาดตัวเดียวกับแท็บงวดงบ แต่วาดแท่งเดียว (ค่าใช้จ่าย) ไม่มีรางวงเงินงบ
         ยอดอยู่ในช่อง v.budget เพราะการเรียงลำดับกับสเกลอ่านจากช่องนั้น
         ยอดติดลบ (คืนของ / ปรับลดบัญชี) วาดยาวตามขนาดจริงด้วยสีเขียว — เงินไหลกลับ
    */
    var spend = chart.mode === 'spend';
    var kindBtns = Array.prototype.slice.call(root.querySelectorAll('[data-kind]'));
    /*
      🔴 โหลดหน้าใหม่ทุกครั้ง (refresh / เปลี่ยนตัวกรอง) เริ่มที่กราฟแท่งแนวตั้งเสมอ (เจ้าของสั่ง 2026-09-14)
         จึงไม่จำชนิดกราฟที่เลือกไว้ — สลับได้เฉพาะระหว่างอยู่ในหน้า
    */
    var kind = 'bar-v';
    var NS = 'http://www.w3.org/2000/svg';

    // ล้างค่าที่รุ่นก่อนหน้าเคยจำไว้ในเครื่องผู้ใช้ ไม่ให้ค้างเป็นขยะ
    try { window.localStorage.removeItem('bms.dashboard.chartKind'); } catch (e) { /* โหมดส่วนตัว — ไม่มีอะไรต้องล้าง */ }

    var WORDS = {
      th: { budget: 'งบประมาณ', actual: 'ใช้ไป', reserve: 'รอตัดจ่าย', available: 'คงเหลือ', over: 'เกินงบ',
            ofBudget: 'ของงบ', rows: 'รายการ', fx: 'ไม่รวมในยอดบาท', all: 'ทุกแผนก', open: 'กดเพื่อดูรายการงบ',
            spend: 'ค่าใช้จ่าย', refund: 'คืน / ปรับลด', entries: 'รายการ' },
      en: { budget: 'Budget', actual: 'Actual', reserve: 'Committed', available: 'Available', over: 'Over budget',
            ofBudget: 'of budget', rows: 'rows', fx: 'not in THB totals', all: 'All departments', open: 'Click to open budgets',
            spend: 'Expenses', refund: 'Refunds / adjustments', entries: 'entries' }
    };
    function lang() { return window.BMS && window.BMS.lang ? window.BMS.lang() : 'th'; }
    function w(key) { return (WORDS[lang()] || WORDS.th)[key]; }
    function nameOf(d) { return lang() === 'en' ? (d.name_en || d.name_th) : d.name_th; }
    function firmOf(d) { return lang() === 'en' ? (d.firm_en || d.firm_th) : d.firm_th; }
    function esc(s) { var el = document.createElement('div'); el.textContent = s == null ? '' : String(s); return el.innerHTML; }
    function pos(n) { return n > 0 ? n : 0; }

    function short(n) {
      var a = Math.abs(n), sign = n < 0 ? '-' : '';
      if (a >= 1e9) { return sign + (a / 1e9).toFixed(1) + 'B'; }
      if (a >= 1e6) { return sign + (a / 1e6).toFixed(a >= 1e8 ? 0 : 1) + 'M'; }
      if (a >= 1e3) { return sign + (a / 1e3).toFixed(a >= 1e5 ? 0 : 1) + 'K'; }
      return sign + Math.round(a);
    }

    // ความยาวข้อความโดยประมาณ (px) ที่ตัวอักษร 11px — ใช้กันชื่อแผนกทับกัน
    var measure = document.createElement('canvas').getContext('2d');
    function textW(s) {
      if (!measure) { return Array.from(String(s)).length * 11; }
      measure.font = '11px ' + window.getComputedStyle(box).fontFamily;
      return measure.measureText(String(s)).width;
    }

    /*
      สเกลของกราฟ = ค่าที่ยาวที่สุดระหว่าง "วงเงินงบ" กับ "ใช้ไป + รอตัดจ่าย + คงเหลือ"
      🔴 ใช้เกินงบได้ (คงเหลือติดลบ) → แท่งสีจะยาวเลยรางงบออกไป ให้เห็นว่าเกินจริง ไม่ตัดทิ้ง
    */
    function stackOf(d) { return pos(d.v.actual) + pos(d.v.reserve) + pos(d.v.available); }
    // ความยาวที่แท่งต้องใช้ — โหมด spend ใช้ขนาดของยอด (ติดลบก็ยาวตามจริง)
    function sizeOf(d) { return spend ? Math.abs(d.v.budget) : Math.max(pos(d.v.budget), stackOf(d)); }

    /*
      🔴 แท่งยอดน้อยต้องยาวอย่างน้อย MIN_BAR px (เจ้าของแจ้ง 2026-09-14 ว่าแท่งสั้นจนชี้เมาส์ไม่โดน)
         ยืดทั้งแท่งด้วยอัตราเดียวกัน สัดส่วนสีในแท่งจึงยังถูก · ตัวเลขจริงดูที่ป้ายชี้/ตาราง
    */
    var MIN_BAR = 12;
    function stretch(d, unit) {
      var len = sizeOf(d) * unit;
      return len > 0 && len < MIN_BAR ? MIN_BAR / len : 1;
    }
    function scaleMax() {
      return items.reduce(function (m, d) { return Math.max(m, sizeOf(d)); }, 0) || 1;
    }

    function el(tag, attrs) {
      var node = document.createElementNS(NS, tag);
      Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); });
      return node;
    }

    function txt(x, y, s, cls, extra) {
      var t = el('text', Object.assign({ x: x, y: y, 'class': cls }, extra || {}));
      t.textContent = s;
      return t;
    }

    function alertBadge(svg, d, x, y) {
      if (!d.alerts || !d.alerts.length) { return; }
      var originalIndex = chart.items.indexOf(d);
      var a = el('a', { 'class': 'c-alert', href: d.alerts_href,
        'data-alert-index': originalIndex, tabindex: 0,
        'aria-label': nameOf(d) + ' · ' + (lang() === 'en' ? 'Negative budget balances: ' : 'งบคงเหลือติดลบ ') + d.alerts.length });
      a.appendChild(el('rect', { x: x, y: y, width: 34, height: 20, rx: 5 }));
      a.appendChild(txt(x + 17, y + 14, '! ' + d.alerts.length, '', { 'text-anchor': 'middle' }));
      var title = el('title', {});
      title.textContent = a.getAttribute('aria-label');
      a.appendChild(title);
      svg.appendChild(a);
    }

    /* ── แท่งแนวตั้ง ── */
    function barV(width) {
      var n = items.length;
      var longest = items.reduce(function (m, d) { return Math.max(m, textW(nameOf(d))); }, 0);
      var padL = 48, padR = 12, head = 48;
      var plotW = Math.max(width - padL - padR, n * 42);
      var slot = plotW / n;
      var flat = slot >= longest + 8;
      // Preserve the original angled labels; include their complete bounds in the scrollable SVG.
      if (!flat) { padL = Math.max(padL, longest * Math.cos(Math.PI * 40 / 180) - slot / 2 + 20); }
      var W = padL + plotW + padR;
      var foot = flat ? 28 : longest * Math.sin(Math.PI * 40 / 180) + 28;
      var H = Math.max(box.clientHeight - 4, foot + 320);
      var base = H - foot;
      var max = scaleMax();
      var svg = el('svg', { width: W, height: H, viewBox: '0 0 ' + W + ' ' + H });
      var barW = Math.max(6, Math.min(46, slot * 0.6));

      for (var g = 0; g <= 4; g++) {
        var gy = base - (base - head) * g / 4;
        svg.appendChild(el('line', { x1: padL, y1: gy, x2: W - padR, y2: gy, 'class': 'c-grid' }));
        svg.appendChild(txt(padL - 6, gy + 3, short(max * g / 4), 'c-axis', { 'text-anchor': 'end' }));
      }

      items.forEach(function (d, i) {
        var cx = padL + slot * i + slot / 2;
        var x = cx - barW / 2;
        var unit = (base - head) / max;
        unit *= stretch(d, unit);
        var group = el('g', { 'class': 'c-bar' });

        if (spend) {
          var tall = sizeOf(d) * unit;
          group.appendChild(el('rect', { x: x, y: base - tall, width: barW, height: tall, rx: 2,
            'class': (d.v.budget < 0 ? 'c-refund' : 'c-used') + ' c-grow-y' }));
        } else {
          var track = pos(d.v.budget) * unit;
          group.appendChild(el('rect', { x: x, y: base - track, width: barW, height: track, 'class': 'c-track c-grow-y', rx: 2 }));
          var y = base;
          [['actual', 'c-used'], ['reserve', 'c-reserve'], ['available', 'c-left']].forEach(function (p) {
            var len = pos(d.v[p[0]]) * unit;
            if (len <= 0) { return; }
            y -= len;
            group.appendChild(el('rect', { x: x, y: y, width: barW, height: len, 'class': p[1] + ' c-grow-y' }));
          });
        }
        svg.appendChild(group);

        var topY = base - sizeOf(d) * unit;
        if (slot >= 34) { svg.appendChild(txt(cx, topY - 5, short(d.v.budget), 'c-value', { 'text-anchor': 'middle' })); }

        if (flat) {
          svg.appendChild(txt(cx, base + 16, nameOf(d), 'c-label', { 'text-anchor': 'middle' }));
        } else {
          svg.appendChild(txt(cx + 3, base + 12, nameOf(d), 'c-label', { 'text-anchor': 'end', transform: 'rotate(-40 ' + (cx + 3) + ' ' + (base + 12) + ')' }));
        }

        svg.appendChild(hit(i, padL + slot * i, head - 14, slot, base - head + 14 + foot));
        alertBadge(svg, d, cx - 17, topY - 40);
      });

      return svg;
    }

    /* ── แท่งแนวนอน ── */
    function barH(width) {
      var n = items.length;
      var rowH = 22, gap = 30, top = 30;
      var labelW = Math.max(110, items.reduce(function (m, d) { return Math.max(m, textW(nameOf(d))); }, 0) + 54);
      var valueW = 112;
      var W = Math.max(width, labelW + valueW + 200);
      var H = Math.max(box.clientHeight - 4, top * 2 + n * (rowH + gap));
      var plotW = W - labelW - valueW - 8;
      var max = scaleMax();
      var svg = el('svg', { width: W, height: H, viewBox: '0 0 ' + W + ' ' + H });

      items.forEach(function (d, i) {
        var y = top + i * (rowH + gap);
        var unit = plotW / max;
        unit *= stretch(d, unit);
        var group = el('g', { 'class': 'c-bar' });

        svg.appendChild(txt(4, y + rowH / 2 + 4, nameOf(d), 'c-label'));
        if (spend) {
          group.appendChild(el('rect', { x: labelW, y: y, width: sizeOf(d) * unit, height: rowH, rx: 2,
            'class': (d.v.budget < 0 ? 'c-refund' : 'c-used') + ' c-grow-x' }));
        } else {
          group.appendChild(el('rect', { x: labelW, y: y, width: pos(d.v.budget) * unit, height: rowH, 'class': 'c-track c-grow-x', rx: 2 }));
          var x = labelW;
          [['actual', 'c-used'], ['reserve', 'c-reserve'], ['available', 'c-left']].forEach(function (p) {
            var len = pos(d.v[p[0]]) * unit;
            if (len <= 0) { return; }
            group.appendChild(el('rect', { x: x, y: y, width: len, height: rowH, 'class': p[1] + ' c-grow-x' }));
            x += len;
          });
        }
        svg.appendChild(group);
        svg.appendChild(txt(W, y + rowH / 2 + 4, d.text.budget, 'c-value', { 'text-anchor': 'end' }));
        svg.appendChild(hit(i, 0, y - gap / 2, W, rowH + gap));
        alertBadge(svg, d, labelW, y - 24);
      });

      return svg;
    }

    /* ── วงกลม: ใช้ไป / รอตัดจ่าย / คงเหลือ ของขอบเขตที่เลือก (ทั้งหมด หรือแผนกเดียว) ── */
    function pie() {
      var d = items.length === 1 ? items[0] : chart.total;
      var name = items.length === 1 ? nameOf(items[0]) : w('all');
      var parts = [['actual', 'c-used', 'used'], ['reserve', 'c-reserve', 'reserve'], ['available', 'c-left', 'left']];
      var sum = stackOf(d);
      var size = Math.max(120, Math.min(box.clientHeight > 600 ? 480 : 260, box.clientHeight - 24));
      var r = size / 2 - 4, c = size / 2;
      var svg = el('svg', { width: size, height: size, viewBox: '0 0 ' + size + ' ' + size });
      var from = -Math.PI / 2;

      if (sum <= 0) {
        svg.appendChild(el('circle', { cx: c, cy: c, r: r, 'class': 'c-track' }));
      }
      parts.forEach(function (p) {
        var part = pos(d.v[p[0]]) / (sum || 1);
        if (part <= 0) { return; }
        if (part >= 0.9999) {
          svg.appendChild(el('circle', { cx: c, cy: c, r: r, 'class': p[1] }));
        } else {
          var to = from + part * Math.PI * 2;
          var path = 'M ' + c + ' ' + c + ' L ' + (c + r * Math.cos(from)) + ' ' + (c + r * Math.sin(from))
            + ' A ' + r + ' ' + r + ' 0 ' + (to - from > Math.PI ? 1 : 0) + ' 1 ' + (c + r * Math.cos(to)) + ' ' + (c + r * Math.sin(to)) + ' Z';
          svg.appendChild(el('path', { d: path, 'class': p[1] + ' c-slice' }));
          if (part >= 0.06) {
            var mid = (from + to) / 2;
            svg.appendChild(txt(c + r * 0.62 * Math.cos(mid), c + r * 0.62 * Math.sin(mid) + 4, (part * 100).toFixed(1) + '%', 'c-value', { 'text-anchor': 'middle' }));
          }
          from = to;
        }
      });

      var wrap = document.createElement('div');
      wrap.className = 'bd-pie';
      wrap.appendChild(svg);
      var list = '<div class="bd-pie-list"><h4>' + esc(name) + '</h4>'
        + '<div class="bd-pie-row"><i class="bd-dot budget"></i><span>' + w('budget') + '</span><b>' + d.text.budget + '</b>'
        + (d.fx.length ? '<small class="bd-fx">' + d.fx.map(function (f) { return '+ ' + f.budget + ' ' + esc(f.code); }).join(' ') + ' · ' + w('fx') + '</small>' : '') + '</div>';
      parts.forEach(function (p) {
        var over = p[0] === 'available' && d.v.available < 0;
        list += '<div class="bd-pie-row"><i class="bd-dot ' + p[2] + '"></i><span>' + w(p[0]) + '</span>'
          + '<b class="bd-' + (over ? 'used' : p[2]) + '">' + d.text[p[0]] + '</b>'
          + '<small>' + (over ? w('over') : (d.pct ? d.pct[p[0]] + '% ' + w('ofBudget') : '—')) + '</small></div>';
      });
      wrap.insertAdjacentHTML('beforeend', list + '</div>');
      return wrap;
    }

    /* พื้นที่กดของแต่ละแผนก — กว้างทั้งช่อง ไม่ต้องเล็งแท่งเล็กๆ · กด Enter ได้ด้วย */
    function hit(i, x, y, width, height) {
      var d = items[i];
      return el('rect', { x: x, y: y, width: width, height: height, 'class': 'c-hit', 'data-i': i, tabindex: 0, role: 'link',
        'aria-label': nameOf(d) + (spend
          ? ' · ' + w('spend') + ' ' + d.text.budget
          : ' · ' + w('budget') + ' ' + d.text.budget + ' · ' + w('actual') + ' ' + d.text.actual) });
    }

    /* ── ป้ายบอกค่า ── */
    var card = root.querySelector('[data-dash-card]');
    var tip = document.createElement('div');
    tip.className = 'bd-tip';
    tip.hidden = true;
    // 🔴 ป้ายต้องอยู่ในการ์ด — ตอนเต็มจอ เบราว์เซอร์แสดงเฉพาะของที่อยู่ใน element ที่เต็มจอ
    (card || document.body).appendChild(tip);

    function showTip(i, x, y) {
      var d = items[i];
      var row = function (label, value, cls) { return '<div class="bd-tip-row ' + cls + '"><span>' + label + '</span><span>' + value + '</span></div>'; };
      var head = '<b>' + esc(nameOf(d)) + '</b><i>' + esc(firmOf(d)) + ' · ' + d.count.toLocaleString('en-US') + ' ' + w(spend ? 'entries' : 'rows') + '</i>';
      if (spend) {
        tip.innerHTML = head + row(d.v.budget < 0 ? w('refund') : w('spend'), d.text.budget, d.v.budget < 0 ? 't-left' : 't-used');
      } else {
        tip.innerHTML = head
          + row(w('budget'), d.text.budget, '')
          + d.fx.map(function (f) { return row('', '+ ' + f.budget + ' ' + esc(f.code), 't-fx'); }).join('')
          + row(w('actual') + (d.pct ? ' ' + d.pct.actual + '%' : ''), d.text.actual, 't-used')
          + row(w('reserve') + (d.pct ? ' ' + d.pct.reserve + '%' : ''), d.text.reserve, 't-reserve')
          + row(d.v.available < 0 ? w('over') : w('available'), d.text.available, d.v.available < 0 ? 't-used' : 't-left');
      }
      tip.hidden = false;
      var left = Math.min(x + 14, window.innerWidth - tip.offsetWidth - 8);
      var top = Math.min(y + 14, window.innerHeight - tip.offsetHeight - 8);
      tip.style.left = Math.max(8, left) + 'px';
      tip.style.top = Math.max(8, top) + 'px';
    }

    function hideTip() { tip.hidden = true; }

    var zoom = 1, zoomStage, zoomContent, zoomW, zoomH, drag = null, suppressClick = false;

    /*
      กดชิ้นกราฟแล้วพาไปหน้าใหม่ — ต้องเรียกจอ "กำลังโหลด" เอง (เจ้าของสั่ง 2026-09-16)
      🔴 กราฟไม่ใช่ลิงก์ <a> แต่ย้ายหน้าด้วย location.href ตัวดักกดลิงก์กลางจึงไม่เห็น
    */
    function goTo(url) {
      if (!url) { return; }
      if (window.BMS && window.BMS.loading) { window.BMS.loading.show(); }
      window.location.href = url;
    }
    var zoomBtns = Array.prototype.slice.call(root.querySelectorAll('[data-chart-zoom]'));
    function applyZoom(value, x, y) {
      if (!zoomStage) { return; }
      x = x == null ? box.clientWidth / 2 : x;
      y = y == null ? box.clientHeight / 2 : y;
      var sourceX = (box.scrollLeft + x) / zoom;
      var sourceY = (box.scrollTop + y) / zoom;
      zoom = Math.max(0.5, Math.min(4, value));
      zoomStage.style.width = (zoomW * zoom) + 'px';
      zoomStage.style.height = (zoomH * zoom) + 'px';
      zoomContent.style.transform = 'scale(' + zoom + ')';
      box.scrollLeft = sourceX * zoom - x;
      box.scrollTop = sourceY * zoom - y;
      zoomBtns.forEach(function (b) {
        var action = b.getAttribute('data-chart-zoom');
        if (action === 'reset') { b.textContent = Math.round(zoom * 100) + '%'; }
        b.disabled = (action === 'in' && zoom >= 4) || (action === 'out' && zoom <= 0.5);
      });
      hideTip();
    }
    zoomBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        var action = b.getAttribute('data-chart-zoom');
        applyZoom(action === 'reset' ? 1 : zoom * (action === 'in' ? 1.25 : 0.8));
        if (action === 'reset') { box.scrollLeft = 0; box.scrollTop = 0; }
      });
    });
    box.addEventListener('wheel', function (e) {
      if (!zoomStage || e.ctrlKey || !e.deltaY) { return; }
      e.preventDefault();
      var rect = box.getBoundingClientRect();
      applyZoom(zoom * (e.deltaY < 0 ? 1.12 : 1 / 1.12), e.clientX - rect.left, e.clientY - rect.top);
    }, { passive: false });
    box.addEventListener('pointerdown', function (e) {
      if (e.pointerType !== 'mouse' || e.button !== 0) { return; }
      var rect = box.getBoundingClientRect();
      if (e.clientX - rect.left >= box.clientWidth || e.clientY - rect.top >= box.clientHeight) { return; }
      suppressClick = false;
      drag = { id: e.pointerId, x: e.clientX, y: e.clientY, left: box.scrollLeft, top: box.scrollTop, moved: false };
    });
    box.addEventListener('pointermove', function (e) {
      if (!drag || drag.id !== e.pointerId) { return; }
      var dx = e.clientX - drag.x, dy = e.clientY - drag.y;
      if (!drag.moved && Math.hypot(dx, dy) < 5) { return; }
      drag.moved = true;
      suppressClick = true;
      box.setPointerCapture(e.pointerId);
      box.classList.add('is-panning');
      box.scrollLeft = drag.left - dx;
      box.scrollTop = drag.top - dy;
      e.preventDefault();
      hideTip();
    });
    function endPan(e) {
      if (!drag || drag.id !== e.pointerId) { return; }
      drag = null;
      box.classList.remove('is-panning');
      if (box.hasPointerCapture(e.pointerId)) { box.releasePointerCapture(e.pointerId); }
    }
    box.addEventListener('pointerup', endPan);
    box.addEventListener('pointercancel', endPan);
    box.addEventListener('lostpointercapture', endPan);

    box.addEventListener('mousemove', function (e) {
      if (drag && drag.moved) { return; }
      var target = e.target.closest ? e.target.closest('.c-hit') : null;
      if (!target) { hideTip(); return; }
      showTip(+target.getAttribute('data-i'), e.clientX, e.clientY);
    });
    box.addEventListener('mouseleave', hideTip);
    box.addEventListener('focusin', function (e) {
      if (!e.target.classList || !e.target.classList.contains('c-hit')) { return; }
      var rect = e.target.getBoundingClientRect();
      showTip(+e.target.getAttribute('data-i'), rect.left + rect.width / 2, rect.top + 20);
    });
    box.addEventListener('focusout', hideTip);
    box.addEventListener('click', function (e) {
      if (suppressClick) { suppressClick = false; e.preventDefault(); return; }
      var badge = e.target.closest ? e.target.closest('[data-alert-index]') : null;
      if (badge) {
        return;
      }
      var target = e.target.closest ? e.target.closest('.c-hit') : null;
      if (target) { goTo(items[+target.getAttribute('data-i')].href); }
    });
    box.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('c-hit')) {
        goTo(items[+e.target.getAttribute('data-i')].href);
      }
    });

    var tablePanel = root.querySelector('[data-dash-table]');
    var hint = root.querySelector('[data-dash-hint]');

    function render(animate) {
      hideTip();
      var asTable = kind === 'table' && tablePanel;
      // มุมมองตาราง = ตารางรายแผนกที่เซิร์ฟเวอร์วาดไว้แล้ว แค่สลับให้แสดงแทนกราฟ
      if (tablePanel) { tablePanel.hidden = !asTable; }
      if (hint) { hint.hidden = !!asTable; }
      box.hidden = !!asTable;
      zoomBtns.forEach(function (b) { b.hidden = !!asTable; });
      zoomStage = null;
      zoom = 1;
      box.textContent = '';
      if (!asTable) {
        box.classList.toggle('is-anim', !!animate);
        var width = box.clientWidth;
        var chart = kind === 'pie' ? pie() : (kind === 'bar-h' ? barH(width) : barV(width));
        box.appendChild(chart);
        zoomW = Math.max(chart.getBoundingClientRect().width, chart.scrollWidth);
        zoomH = Math.max(chart.getBoundingClientRect().height, chart.scrollHeight);
        zoomStage = document.createElement('div');
        zoomStage.className = 'bd-zoom-stage';
        zoomContent = document.createElement('div');
        zoomContent.className = 'bd-zoom-content';
        zoomContent.style.width = zoomW + 'px';
        zoomContent.style.height = zoomH + 'px';
        zoomContent.appendChild(chart);
        zoomStage.appendChild(zoomContent);
        box.appendChild(zoomStage);
        applyZoom(1, 0, 0);
        box.scrollLeft = 0;
        box.scrollTop = 0;
      }
      kindBtns.forEach(function (b) {
        var on = b.getAttribute('data-kind') === kind;
        b.classList.toggle('is-on', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }

    kindBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        kind = b.getAttribute('data-kind');
        render(true);
      });
    });

    /*
      ═══ เรียงตามวงเงินงบ สูง→ต่ำ / ต่ำ→สูง (เจ้าของสั่ง 2026-09-14) ═══
      ค่าเริ่มต้นสูง→ต่ำทุกครั้งที่โหลดหน้า (ไม่จำ) · เรียงทั้งกราฟ (items) และแถวตาราง พร้อมเลขลำดับใหม่
      🔴 เรียงจากค่าจริง ไม่ใช่กลับด้านลำดับเดิม — ลำดับจากเซิร์ฟเวอร์อาจเปลี่ยนได้ทีหลัง
    */
    var sortBtns = Array.prototype.slice.call(root.querySelectorAll('[data-sort]'));
    var baseItems = items.slice();
    var tableBody = tablePanel ? tablePanel.querySelector('tbody') : null;
    var tableRows = tableBody ? Array.prototype.slice.call(tableBody.querySelectorAll('[data-dept-row]')) : [];

    function applySort(order) {
      var dir = order === 'asc' ? 1 : -1;
      items = baseItems.slice().sort(function (a, b) { return dir * (a.v.budget - b.v.budget); });
      tableRows.slice().sort(function (a, b) {
        return dir * (Number(a.getAttribute('data-budget')) - Number(b.getAttribute('data-budget')));
      }).forEach(function (tr, i) {
        tr.querySelector('.col-seq').textContent = i + 1;
        tableBody.appendChild(tr);
      });
      sortBtns.forEach(function (b) {
        var on = b.getAttribute('data-sort') === order;
        b.classList.toggle('is-on', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }

    sortBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        applySort(b.getAttribute('data-sort'));
        render(true);
      });
    });
    applySort('desc');

    /* ═══ เต็มจอ — สลับชนิดกราฟ/ตารางระหว่างเต็มจอได้ ไม่หลุดออก ═══ */
    var fullBtn = root.querySelector('[data-dash-full]');

    function isFull() {
      return !!card && (document.fullscreenElement === card || card.classList.contains('is-full'));
    }

    function syncFull() {
      if (!fullBtn) { return; }
      var on = isFull();
      var key = on ? 'chart.exitFullscreen' : 'chart.fullscreen';
      var label = window.BMS && window.BMS.t ? window.BMS.t(key) : (on ? 'ออกจากเต็มจอ' : 'เต็มจอ');
      fullBtn.classList.toggle('is-on', on);
      fullBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
      fullBtn.setAttribute('data-i18n-title', key);
      fullBtn.setAttribute('data-i18n-aria', key);
      fullBtn.title = label;
      fullBtn.setAttribute('aria-label', label);
      fullBtn.querySelector('[data-icon-open]').hidden = on;
      fullBtn.querySelector('[data-icon-close]').hidden = !on;
      render(false);
    }

    if (fullBtn && card) {
      fullBtn.addEventListener('click', function () {
        if (isFull()) {
          if (document.fullscreenElement) { document.exitFullscreen(); } else { card.classList.remove('is-full'); syncFull(); }
          return;
        }
        var fallback = function () { card.classList.add('is-full'); syncFull(); };
        if (card.requestFullscreen) { card.requestFullscreen().catch(fallback); } else { fallback(); }
      });
      document.addEventListener('fullscreenchange', syncFull);
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && card.classList.contains('is-full')) { card.classList.remove('is-full'); syncFull(); }
      });
    }

    var frame = 0;
    window.addEventListener('resize', function () {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(function () { render(false); });
    });
    document.addEventListener('bms:languagechange', function () { render(false); });

    render(true);
  })();
</script>
@endpush
