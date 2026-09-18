{{--
  เส้นทางของหน้า (breadcrumb) — บอกว่าตอนนี้อยู่ตรงไหนของระบบ
  อยู่ในแถบเครื่องมือ ค่อนไปทางซ้าย

  🔴 หน้าใหม่ทุกหน้าต้องมาเพิ่มเส้นทางของตัวเองที่ $trails ข้างล่าง
     ไม่งั้นจะไม่ขึ้นอะไรเลย แล้วผู้ใช้จะไม่รู้ว่าอยู่ที่ไหน

  ⚠️ เส้นทางของแต่ละหน้าเขียนให้ครบเอง **ไม่มีการเติม "หน้าแรก" ให้อัตโนมัติ**
     เพราะ "หน้าแรก" คือหน้า /guide ซึ่งเป็นคนละหน้ากับที่กำลังดูอยู่
     (เจ้าของสั่งเอาออกเมื่อ 2026-09-02)

  รูปแบบ: 'ชื่อ route' => [ [คีย์แปล, ข้อความไทย, ชื่อ route ปลายทาง (null = ไม่ใช่ลิงก์)], ... ]
--}}
@php
  $trails = [
    'guide' => [
      ['crumb.home', 'หน้าแรก', 'guide'],
    ],
    'dashboard' => [
      ['nav.overview', 'ภาพรวมระบบ', null],
      ['nav.dashboard', 'แดชบอร์ด', 'dashboard'],
    ],
    'profile' => [
      ['top.profile', 'ข้อมูลส่วนตัว', 'profile'],
    ],
    'admin.external' => [
      ['nav.manage', 'จัดการระบบ', null],
      ['nav.settings', 'ตั้งค่าระบบ', null],
      ['nav.external', 'บัญชีผู้ใช้ภายนอก (Supplier)', 'admin.external'],
    ],
    // ── งบประมาณ ──
    'budget.invest.index' => [
      ['nav.budget', 'งบประมาณ', null],
      ['fn.budget.invest', 'ของบประมาณ', 'budget.invest.index'],
    ],
    'budget.invest.create' => [
      ['fn.budget.invest', 'ของบประมาณ', 'budget.invest.index'],
      ['budget.invest.new', 'เสนอรายการใหม่', 'budget.invest.create'],
    ],
    'budget.invest.edit' => [
      ['fn.budget.invest', 'ของบประมาณ', 'budget.invest.index'],
      ['budget.invest.editTitle', 'แก้ไขข้อเสนอ', null],
    ],
    'budget.approval.index' => [
      ['nav.budget', 'งบประมาณ', null],
      ['fn.budget.approval', 'สถานะการดำเนินการ', 'budget.approval.index'],
    ],
    'budget.approval.show' => [
      ['fn.budget.approval', 'สถานะการดำเนินการ', 'budget.approval.index'],
      ['budget.docNo', 'เลขที่', null],
    ],
    'budget.list.index' => [
      ['nav.budget', 'งบประมาณ', null],
      ['fn.budget.approved', 'ลงทะเบียนงบประมาณ', 'budget.list.index'],
    ],
    'budget.list.show' => [
      ['fn.budget.approved', 'ลงทะเบียนงบประมาณ', 'budget.list.index'],
      ['budget.docNo', 'เลขที่', null],
    ],
    // ตั้งค่าหมายเลขเอกสาร — อยู่ใต้จัดการระบบ แม้ไฟล์จะเป็นของโมดูลงบ (เจ้าของสั่ง 2026-09-17)
    'budget.docgroup.index' => [
      ['nav.manage', 'จัดการระบบ', null],
      ['nav.docNumber', 'ตั้งค่าหมายเลขเอกสาร', 'budget.docgroup.index'],
    ],
    'master.index' => [
      ['nav.manage', 'จัดการระบบ', null],
      ['nav.master', 'ข้อมูลหลัก (Master Data)', 'master.index'],
    ],
    'master.departments' => [
      ['nav.master', 'ข้อมูลหลัก (Master Data)', 'master.index'],
      ['master.department', 'แผนก', 'master.departments'],
    ],
    'master.positions' => [
      ['nav.master', 'ข้อมูลหลัก (Master Data)', 'master.index'],
      ['master.position', 'ตำแหน่งงาน', 'master.positions'],
    ],
    'master.branches' => [
      ['nav.master', 'ข้อมูลหลัก (Master Data)', 'master.index'],
      ['master.branch', 'สาขา', 'master.branches'],
    ],
    'activity.index' => [
      ['nav.manage', 'จัดการระบบ', null],
      ['audit.title', 'ประวัติการใช้งานระบบ', 'activity.index'],
    ],
    'activity.signins' => [
      ['nav.manage', 'จัดการระบบ', null],
      ['nav.activityLog', 'ประวัติการใช้งานระบบ', 'activity.index'],
      ['audit.signinTitle', 'ประวัติการเข้าสู่ระบบ', 'activity.signins'],
    ],
    'access.system' => [
      ['nav.manage', 'จัดการระบบ', null],
      ['nav.access', 'สิทธิ์การเข้าถึง', null],
      ['fn.access.system', 'สิทธิ์การเข้าถึงระบบ SBMS', 'access.system'],
    ],
    'access.modules' => [
      ['nav.manage', 'จัดการระบบ', null],
      ['nav.access', 'สิทธิ์การเข้าถึง', null],
      ['fn.access.module', 'สิทธิ์การเข้าถึงโมดูล', 'access.modules'],
    ],
  ];

  $trail = $trails[request()->route()?->getName()] ?? [];
@endphp

@if ($trail !== [])
  <nav class="crumb" aria-label="เส้นทางหน้า" data-i18n-aria="crumb.aria">
    @foreach ($trail as [$key, $th, $route])
      @if (! $loop->first)
        <span class="crumb-sep" aria-hidden="true">›</span>
      @endif

      @if ($route && $loop->last)
        {{-- ข้อสุดท้ายคือหน้าที่กำลังดูอยู่ ไม่ต้องทำเป็นลิงก์ --}}
        <span class="crumb-current" aria-current="page" data-i18n="{{ $key }}">{{ $th }}</span>
      @elseif ($route)
        <a href="{{ route($route) }}" data-i18n="{{ $key }}">{{ $th }}</a>
      @else
        <span class="crumb-plain" data-i18n="{{ $key }}">{{ $th }}</span>
      @endif
    @endforeach
  </nav>
@endif
