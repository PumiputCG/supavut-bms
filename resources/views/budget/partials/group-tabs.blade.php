{{--
  แท็บกรอง "หมวดงบประมาณ" (เจ้าของสั่ง 2026-09-17)
    สถานะการดำเนินการ · ลงทะเบียน · ประวัติงบประมาณ
    (หน้าของบประมาณใช้หน้าคั่นเลือกหมวดแทน ไม่มีแถบนี้)

  ต้องส่งมา
    $groupTabs  ผลจาก App\Services\Budget\DocGroupTabs::tabs() — ['picked', 'tabs']
  ไม่บังคับ
    $allParam   true = แท็บ "ทั้งหมด" ส่ง ?group=all ไปด้วย (หน้าของบประมาณ)
                🔴 หน้านั้นไม่มี ?group= = หน้าคั่นเลือกกลุ่ม ถ้าลบพารามิเตอร์ทิ้งจะเด้งกลับหน้าคั่น

  🔴 แท็บเป็นลิงก์ (GET ?group=) ไม่ใช่ปุ่มที่กรองด้วย JS
     เพราะตารางพวกนี้แบ่งหน้า กรองด้วย JS จะได้แค่หน้าที่เปิดอยู่ (บทเรียน 2026-09-04)
  🔴 สลับแท็บแล้ว "คงตัวกรองอื่นไว้" แต่กลับไปหน้าแรกเสมอ
     หน้า 5 ของกลุ่มหนึ่งอาจไม่มีอยู่จริงในอีกกลุ่ม
--}}
@once
  <style>
    /* ── แท็บกลุ่มเอกสาร — ไม่มีกรอบ ใช้เส้นใต้บอกว่าอยู่แท็บไหน (แนวเดียวกับหน้าประวัติการใช้งาน) ── */
    .dg-tabs {
      display: flex; flex-wrap: wrap; align-items: flex-end; gap: 4px 22px;
      border-bottom: 1px solid var(--line);
    }

    .dg-tab {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 2px 2px 10px; margin-bottom: -1px;
      border-bottom: 2px solid transparent;
      color: var(--muted); font-size: var(--fs-sm); font-weight: 600;
      text-decoration: none; white-space: nowrap;
    }
    .dg-tab:hover { color: var(--ink); }
    .dg-tab.is-on { color: var(--navy-900); font-weight: 700; border-bottom-color: var(--accent); }

    /* โค้ดกลุ่ม — ตัวเดียวกับที่อยู่ในเลขที่เอกสาร จะได้จับคู่ด้วยสายตาได้ทันที */
    .dg-code {
      padding: 1px 6px; border-radius: var(--radius-sm);
      background: var(--surface-2); color: var(--ink-soft);
      font-size: var(--fs-xs); font-weight: 700; letter-spacing: .04em;
      font-variant-numeric: tabular-nums;
    }
    .dg-tab.is-on .dg-code { background: var(--accent-soft); color: var(--navy-800); }

    /* จำนวนเอกสาร — ป้ายเล็กท้ายชื่อ (เจ้าของไม่ชอบมุมโค้งมาก จึงใช้ token มุมเล็ก ไม่ใช่วงรี) */
    .dg-n {
      min-width: 20px; padding: 1px 6px; border-radius: var(--radius-sm);
      background: var(--surface-3); color: var(--muted);
      font-size: var(--fs-xs); font-weight: 700; text-align: center;
      font-variant-numeric: tabular-nums;
    }
    .dg-tab.is-on .dg-n { background: var(--navy-800); color: var(--surface); }

    /* กลุ่มที่ปิดใช้งานแล้ว แต่ยังมีเอกสารเก่าให้ดู */
    .dg-tab.is-off > span:first-child { text-decoration: line-through; text-decoration-thickness: 1px; }
  </style>
@endonce

<nav class="dg-tabs" aria-label="หมวดงบประมาณ" data-i18n-aria="budget.group.tabs">
  @foreach ($groupTabs['tabs'] as $tab)
    @php
      $on = $tab['key'] === $groupTabs['picked'];
      $href = $tab['key'] === \App\Services\Budget\DocGroupTabs::ALL && empty($allParam)
        ? request()->fullUrlWithoutQuery(['group', 'page'])
        : request()->fullUrlWithQuery(['group' => $tab['key'], 'page' => null]);
    @endphp
    <a href="{{ $href }}" @class(['dg-tab', 'is-on' => $on, 'is-off' => ! $tab['active']])
       @if ($on) aria-current="page" @endif
       @unless ($tab['active']) title="ปิดใช้งานแล้ว" data-i18n-title="budget.group.disabled" @endunless>
      @if ($tab['i18n'])
        <span data-i18n="{{ $tab['i18n'] }}">{{ $tab['th'] }}</span>
      @else
        <span data-loc-th="{{ $tab['th'] }}" data-loc-en="{{ $tab['en'] }}">{{ $tab['th'] }}</span>
      @endif

      @if ($tab['code'])
        <span class="dg-code">{{ $tab['code'] }}</span>
      @endif

      <span class="dg-n">{{ number_format($tab['n']) }}</span>
    </a>
  @endforeach
</nav>
