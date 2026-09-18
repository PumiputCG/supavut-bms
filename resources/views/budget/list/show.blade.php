@extends('layouts.app')

@section('title', 'งบประมาณที่อนุมัติแล้ว / Approved budget')

@push('page-style')
  <style>:root { --page-max: 900px; }</style>
@endpush

@section('content')
  @php
    $A = \App\Models\Budget\Approval::class;
    $invest = $budget->invest;
    $signers = $invest ? $invest->approvals->where('action', $A::APPROVE) : collect();
  @endphp

  {{--
    ═══════════ เปลี่ยนสถานะ — อยู่เหนือเอกสาร (เจ้าของสั่ง 2026-09-03) ═══════════
    🔴 เปลี่ยนเฉพาะสถานะการใช้งบ ไม่แตะสถานะการอนุมัติ (กฎใน DECISIONS 4.3)
       เอาช่องเหตุผลออกแล้ว — เจ้าของบอกว่าไม่ต้องมี
  --}}
  {{--
    แถบเครื่องมือเหนือกระดาษ — เปลี่ยนสถานะ (ซ้าย) · ดาวน์โหลด PDF (ขวา)
    🔴 เจ้าของสั่ง 2026-09-10 ให้อยู่บรรทัดเดียวกัน ไม่ต้องแยก 2 แถว
  --}}
  <div class="bar is-paper" style="margin-bottom:14px">
    {{--
      🔴 ช่องติ๊กชุดเดียวกับในตาราง (เจ้าของสั่ง 2026-09-10)
         ติ๊ก = ลงทะเบียนแล้ว · ไม่ติ๊ก = รอลงทะเบียน
    --}}
    @if ($canManage)
      <form method="POST" action="{{ route('budget.list.status', $budget) }}" data-register-form>
        @csrf
        <input type="hidden" name="budget_status" value="{{ $budget->budget_status }}">
        <label class="reg-tick-lg">
          <input type="checkbox" data-register-tick @checked($budget->isRegistered())>
          <span data-i18n="budget.markRegistered">ลงทะเบียนแล้ว</span>
        </label>
      </form>
    @endif

    <span class="spacer"></span>

    {{--
      กดแล้วได้ไฟล์ PDF เลย ไม่ต้องผ่านหน้าต่างพิมพ์ (เจ้าของสั่ง 2026-09-10)
      🔴 ต้องมี download ไม่งั้นจอ "กำลังโหลด" ขึ้นแล้วค้าง (ดูคำอธิบายใน list/print.blade.php)
    --}}
    <a class="btn btn-primary" download data-download href="{{ route('budget.list.pdf', $budget) }}"
       data-i18n="budget.downloadPdf">ดาวน์โหลด PDF</a>
  </div>

  @include('budget.list.paper')

  @include('budget.partials.assets')
  @include('budget.partials.paper')

  <style>
    /* ช่องติ๊กในหน้าเอกสาร — มีคำกำกับข้างๆ ต่างจากในตารางที่มีหัวคอลัมน์บอกอยู่แล้ว */
    .reg-tick-lg {
      display: inline-flex; align-items: center; gap: 8px; cursor: pointer;
      color: var(--ink-soft); font-size: var(--fs-sm); font-weight: 600;
    }

    .reg-tick-lg input {
      width: 17px; height: 17px; margin: 0; accent-color: var(--ok); cursor: pointer;
    }
  </style>

  <script>
    'use strict';

    /*
      ติ๊ก = ลงทะเบียนแล้ว · ไม่ติ๊ก = รอลงทะเบียน (เจ้าของสั่ง 2026-09-10)
      🔴 ทุก Action ต้องมีหน้าต่างยืนยัน · ยกเลิกแล้วต้องคืนช่องติ๊กกลับ
    */
    document.querySelectorAll('[data-register-form]').forEach(function (form) {
      var tick = form.querySelector('[data-register-tick]');
      var field = form.querySelector('input[name="budget_status"]');

      tick.addEventListener('change', function () {
        var en = window.BMS.lang() === 'en';
        var on = tick.checked;

        window.BMS.confirm({
          kind: on ? 'ok' : 'warn',
          title: on
            ? (en ? 'Mark as registered?' : 'ยืนยันว่าลงทะเบียนแล้ว?')
            : (en ? 'Back to awaiting registration?' : 'เปลี่ยนกลับเป็นรอลงทะเบียน?'),
          text: on
            ? (en ? 'This budget has been keyed into the ERP.' : 'งบก้อนนี้ถูกคีย์เข้า ERP แล้ว')
            : (en ? 'This budget has not been keyed into the ERP yet.' : 'งบก้อนนี้ยังไม่ได้คีย์เข้า ERP'),
          ok: en ? 'Confirm' : 'ยืนยัน',
          onOk: function () {
            field.value = on ? 'REGISTERED' : 'PENDING_REGISTER';
            form.requestSubmit();
          },
          onCancel: function () { tick.checked = !on; },
        });
      });
    });
  </script>
@endsection
