@extends('layouts.app')

@section('title', 'บัญชีผู้ใช้ภายนอก / External accounts')

@push('page-style')
  <style>
    .form-wrap { max-width: 900px; display: grid; gap: 14px; }
    .form-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .form-grid .span-2 { grid-column: 1 / -1; }
    .section-title {
      display: flex; align-items: center; gap: 8px;
      color: var(--navy-800); font-size: var(--fs-md); font-weight: 700;
    }
    .section-title::after { content: ''; flex: 1; height: 1px; background: var(--line-soft); }
    .hint-text { color: var(--muted); font-size: var(--fs-xs); }
    .checks { display: grid; gap: 8px; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }
    .check-card {
      display: flex; align-items: center; gap: 9px;
      padding: 10px 12px; border: 1px solid var(--line); border-radius: var(--radius-sm);
      background: var(--surface-2); font-size: var(--fs-sm); cursor: pointer;
    }
    .check-card:hover { border-color: var(--accent); }
    .check-card input { width: 16px; height: 16px; accent-color: var(--navy-800); cursor: pointer; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; }
  </style>
@endpush

@section('content')
  <div class="form-wrap">

    <div class="card">
      <div class="card-head">
        <h3 data-i18n="ext.title">บัญชีผู้ใช้ภายนอก (Supplier)</h3>
        <span class="pill pill-warn" data-i18n="common.comingSoon">อยู่ระหว่างพัฒนา</span>
      </div>

      <div class="card-body" style="display:grid;gap:18px">
        <p class="flash" data-i18n="ext.note">ผู้ดูแลระบบเป็นผู้สร้างบัญชีให้ Supplier แล้วส่งชื่อผู้ใช้และรหัสผ่านไปทางอีเมล — Supplier ลงทะเบียนเองไม่ได้</p>

        {{-- ───────── ข้อมูลบริษัท ───────── --}}
        <h4 class="section-title"><span data-i18n="ext.sectionCompany">ข้อมูลบริษัท</span></h4>
        <div class="form-grid">
          <label class="field">
            <span data-i18n="ext.companyNameTh">ชื่อบริษัท (ไทย)</span>
            <input class="input" type="text" name="company_name_th" autocomplete="off">
          </label>
          <label class="field">
            <span data-i18n="ext.companyNameEn">ชื่อบริษัท (อังกฤษ)</span>
            <input class="input" type="text" name="company_name_en" autocomplete="off">
          </label>
          <label class="field">
            <span data-i18n="ext.taxId">เลขประจำตัวผู้เสียภาษี</span>
            <input class="input" type="text" name="tax_id" inputmode="numeric" maxlength="13" autocomplete="off">
          </label>
          <label class="field">
            <span data-i18n="ext.branch">สาขา</span>
            <input class="input" type="text" name="branch" autocomplete="off">
          </label>
          <label class="field span-2">
            <span data-i18n="ext.address">ที่อยู่</span>
            <textarea class="input" name="address" rows="2" style="min-height:76px;padding-top:12px"></textarea>
          </label>
          <label class="field">
            <span data-i18n="ext.phone">โทรศัพท์</span>
            <input class="input" type="tel" name="phone" autocomplete="off">
          </label>
        </div>

        {{-- ───────── ผู้ติดต่อ ───────── --}}
        <h4 class="section-title"><span data-i18n="ext.sectionContact">ผู้ติดต่อ</span></h4>
        <div class="form-grid">
          <label class="field">
            <span data-i18n="ext.contactName">ชื่อผู้ติดต่อ</span>
            <input class="input" type="text" name="contact_name" autocomplete="off">
          </label>
          <label class="field">
            <span data-i18n="ext.contactPosition">ตำแหน่ง</span>
            <input class="input" type="text" name="contact_position" autocomplete="off">
          </label>
          <label class="field span-2">
            <span data-i18n="ext.email">อีเมล (ใช้ส่งบัญชีผู้ใช้)</span>
            <input class="input" type="email" name="email" autocomplete="off">
          </label>
        </div>

        {{-- ───────── สิทธิ์การใช้งาน ───────── --}}
        <h4 class="section-title"><span data-i18n="ext.sectionAccess">สิทธิ์การใช้งาน</span></h4>
        <div class="form-grid">
          <label class="field span-2">
            <span data-i18n="ext.username">ชื่อผู้ใช้</span>
            <input class="input" type="text" name="username" autocomplete="off">
            <span class="hint-text" data-i18n="ext.usernameHint">ระบบจะสร้างให้อัตโนมัติจากเลขผู้เสียภาษี หรือกำหนดเองได้</span>
          </label>
        </div>

        <div class="field">
          <span style="color:var(--ink-soft);font-size:var(--fs-sm);font-weight:600" data-i18n="ext.modules">Module ที่ให้เข้าถึง</span>
          <div class="checks">
            @php
              $mods = [
                ['quote',    'ext.modQuote',    'เสนอราคา / เปรียบเทียบราคา'],
                ['po',       'ext.modPo',       'ใบสั่งซื้อ (PO)'],
                ['delivery', 'ext.modDelivery', 'แจ้งส่งของ'],
                ['invoice',  'ext.modInvoice',  'วางบิล / Invoice'],
              ];
            @endphp
            @foreach ($mods as [$value, $key, $th])
              <label class="check-card">
                <input type="checkbox" name="modules[]" value="{{ $value }}">
                <span data-i18n="{{ $key }}">{{ $th }}</span>
              </label>
            @endforeach
          </div>
        </div>

        <label class="check-card" style="max-width:520px">
          <input type="checkbox" name="send_mail" value="1" checked>
          <span data-i18n="ext.sendMail">ส่งชื่อผู้ใช้และรหัสผ่านไปที่อีเมลนี้</span>
        </label>

        <div class="actions">
          <button type="button" class="btn" disabled>
            <span data-i18n="ext.create">สร้างบัญชีและส่งอีเมล</span>
          </button>
          <button type="button" class="btn btn-quiet">
            <span data-i18n="common.cancel">ยกเลิก</span>
          </button>
        </div>

        <p class="hint-text" data-i18n="ext.draftOnly">หน้านี้ยังเป็นแบบฟอร์มตัวอย่าง ยังไม่บันทึกลงฐานข้อมูล</p>
      </div>
    </div>
  </div>
@endsection
