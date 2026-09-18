{{--
  ไอคอนของเมนูซ้าย — ใช้ไฟล์ภาพที่เจ้าของให้มา (public/img/nav/)

  🔴 ใช้ให้ตรงความหมายเสมอ (เจ้าของกำหนด 2026-09-02)

  โครงเมนู
    group        หัวข้อหลักทั่วไป (โฟลเดอร์เปล่า)
    module       โมดูลย่อย (โฟลเดอร์มีเอกสาร)
    file         ฟังก์ชัน/หน้าจอในโมดูลย่อย

  แดชบอร์ด
    mod-dashboard  หัวข้อหลัก "แดชบอร์ด" (กราฟแท่ง)
    dashboard      หน้า "ภาพรวมระบบ" (โฟลเดอร์แดชบอร์ด)

  ตั้งค่าระบบ
    settings-main  หัวข้อหลัก
    settings-sub   ทุกหน้าย่อยใต้ตั้งค่าระบบ

  โมดูลระบบงาน — ไอคอนตรงตามขั้นตอนจัดซื้อ
    mod-budget · mod-pr · mod-pr-review · mod-rfq · mod-spec · mod-quote
    mod-po-invoice · mod-receiving · mod-payment · mod-tax · mod-report · mod-master
--}}
@php
  $navIcons = [
    'group' => 'folder-group.png',
    'module' => 'folder-module.png',
    'file' => 'file.png',

    'mod-dashboard' => 'mod-dashboard.png',
    'dashboard' => 'dashboard.png',

    'settings-main' => 'settings-main.png',
    'settings-sub' => 'settings-sub.png',
    'settings' => 'settings.png',

    'mod-budget' => 'mod-budget.png',
    'mod-pr' => 'mod-pr.png',
    'mod-pr-review' => 'mod-pr-review.png',
    'mod-quote' => 'mod-quote.png',
    'mod-receiving' => 'mod-receiving.png',
    'mod-po-invoice' => 'mod-po-invoice.png',
    'mod-payment' => 'mod-payment.png',
    'mod-tax' => 'mod-tax.png',
    'mod-rfq' => 'mod-rfq.png',
    'mod-spec' => 'mod-spec.png',
    'mod-report' => 'mod-report.png',
    'mod-master' => 'mod-master.png',

    /*
      ไอคอนของ "หัวข้อย่อย" ในโมดูล (เจ้าของสั่ง 2026-09-04)
      🔴 fn-history ใช้กับหัวข้อ "ประวัติ..." ของทุกโมดูล (ประวัติงบประมาณ · ประวัติ PR · ...)
         หัวข้อย่อยที่ไม่ได้กำหนดไอคอน ยังได้ไอคอนเอกสารเปล่า (file) ตามเดิม
    */
    'fn-history' => 'fn-history.png',
    'fn-budget-approved' => 'fn-budget-approved.png',
    // สถานะการดำเนินการ — เดิมชื่อ "รับทราบ / อนุมัติ Invest" (เจ้าของให้ไอคอนมา 2026-09-10)
    'fn-budget-inbox' => 'fn-budget-inbox.png',
    // เฟืองเส้น — หัวข้อย่อยของ "สิทธิ์การเข้าถึง" และหน้าตั้งค่าอื่นๆ ต่อไป
    'fn-settings' => 'fn-settings.png',
  ];
  $file = $navIcons[$name] ?? $navIcons['file'];
@endphp
<img class="nav-icon" src="{{ asset('img/nav/'.$file) }}" alt="" width="18" height="18" loading="lazy">
