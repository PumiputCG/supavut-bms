{{--
  ═══════════ แท็บของหน้าภาพรวม (เจ้าของสั่ง 2026-09-17) ═══════════

  แทนหัวเรื่อง "งบประมาณและการใช้เงิน" เดิม — 2 มุมมองของเงินชุดเดียวกัน
    งบประมาณตามงวดงบ    = ของเดิมทั้งหมด (แบ่งตามถัง BG26Q1)
    ค่าใช้จ่ายตามวันที่   = แบ่งตามวันที่จ่ายจริง (ไตรมาส · เดือน · วัน)

  🔴 แท็บเป็น "ลิงก์" ไม่ใช่ปุ่มสลับด้วย JS
     2 แท็บอ่าน ERP คนละชุด ถ้าวาดทั้งคู่ทุกครั้งหน้าจะช้าเป็นเท่าตัว
     (ไฟล์นี้เคยเป็นแท็บสลับด้วย JS ตั้งแต่ 2026-09-07 แต่ไม่มีหน้าไหนใช้ — รื้อมาใช้ใหม่)

  🔴 พาไปเฉพาะ บริษัท · ปีงบ · แผนก — ช่องอื่นมีความหมายคนละแบบในแต่ละแท็บ
     (ไตรมาสของแท็บงวดงบ = ถัง · ของแท็บค่าใช้จ่าย = วันที่จ่าย)
     ปีงบ "ทุกปี" ใช้ในแท็บค่าใช้จ่ายไม่ได้ ฝั่งเซิร์ฟเวอร์เปลี่ยนเป็นปีปัจจุบันให้เอง

  🔴 ชื่อแท็บเจ้าของเลือกเอง (2026-09-17) — ไอคอนจากไฟล์ที่เจ้าของให้มา ย่อเหลือ 72px
--}}
@php
  $tabKeep = array_filter([
    'company' => $filters['company'] ?? '',
    'year' => $filters['year'] ?? '',
    'dept' => $filters['dept'] ?? '',
  ], fn ($v) => $v !== '');
  $tabNow = ($filters['tab'] ?? '') === 'expense' ? 'expense' : 'period';
  $tabs = [
    ['key' => 'period', 'th' => 'งบประมาณตามงวดงบ', 'en' => 'Budget by period',
     'icon' => 'tab-period.png', 'href' => route('dashboard', $tabKeep)],
    ['key' => 'expense', 'th' => 'ค่าใช้จ่ายตามวันที่', 'en' => 'Expenses by date',
     'icon' => 'tab-expense.png', 'href' => route('dashboard', $tabKeep + ['tab' => 'expense'])],
  ];
@endphp

<nav class="dash-tabs" aria-label="มุมมองแดชบอร์ด / Dashboard views">
  @foreach ($tabs as $tab)
    <a class="dtab{{ $tab['key'] === $tabNow ? ' is-on' : '' }}" href="{{ $tab['href'] }}"
       data-dash-tab="{{ $tab['key'] }}" @if ($tab['key'] === $tabNow) aria-current="page" @endif>
      <img src="{{ asset('img/nav/'.$tab['icon']) }}" alt="" width="24" height="24">
      <span data-loc-th="{{ $tab['th'] }}" data-loc-en="{{ $tab['en'] }}">{{ $tab['th'] }}</span>
    </a>
  @endforeach
</nav>

<style>
  /* ── แท็บของหน้าภาพรวม — อยู่แทนหัวเรื่องของหน้า จึงตัวใหญ่กว่าแท็บย่อยในการ์ด ── */
  .dash-tabs {
    display: flex; flex-wrap: wrap; align-items: flex-end; gap: 4px;
    min-width: 0; border-bottom: 1px solid var(--line);
  }

  .dtab {
    display: inline-flex; align-items: center; gap: 9px;
    height: 44px; padding: 0 16px; margin-bottom: -1px;
    border: 1px solid transparent; border-bottom: 0;
    border-radius: var(--radius) var(--radius) 0 0;
    color: var(--ink-soft); font-size: var(--fs-md); font-weight: 600;
    text-decoration: none; white-space: nowrap;
    transition: background .14s var(--ease), color .14s var(--ease);
  }

  .dtab img { width: 24px; height: 24px; object-fit: contain; flex: 0 0 auto; }
  .dtab:hover:not(.is-on) { background: var(--surface-2); color: var(--navy-800); }

  /* แท็บที่เปิดอยู่ต่อเนื่องกับพื้นเนื้อหา — ขอบล่างกลืนกับเส้นคั่น · เส้นน้ำเงินบนบอกว่าอยู่แท็บนี้ */
  .dtab.is-on {
    border-color: var(--line); border-top: 3px solid var(--navy-800);
    background: var(--surface); color: var(--navy-900); font-weight: 700;
  }

  @media (max-width: 560px) {
    .dtab { flex: 1 1 auto; justify-content: center; height: 40px; padding: 0 10px; font-size: var(--fs-sm); }
  }
</style>
