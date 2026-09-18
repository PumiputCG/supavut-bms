@extends('layouts.app')

@section('title', 'ใบขออนุมัติตั้งงบประมาณ / Budget approval request')

@push('page-style')
  <style>:root { --page-max: 900px; }</style>
@endpush

@section('content')
  @php
    $labels = \App\Models\Budget\Invest::STATUS_LABELS;
    $A = \App\Models\Budget\Approval::class;

    // แยก 2 บทบาทให้ขาดกัน — ผู้รับทราบไม่มีช่องเซ็น (เจ้าของสั่ง 2026-09-03)
    $recipients = $invest->approvals->where('action', $A::ACK);
    $signers = $invest->approvals->where('action', $A::APPROVE);
  @endphp

  {{--
    ═══════════ แถบปุ่มเอกสาร ═══════════
    🔴 เพิ่มเมื่อ 2026-09-18 ตามที่เจ้าของสั่ง — ก่อนหน้านี้ใบ INV พิมพ์/ดาวน์โหลดไม่ได้เลย
       ซึ่งทำให้ QR บนหัวกระดาษไม่มีใครได้ใช้ (ไม่มีกระดาษให้สแกน)
    🔴 ร่างที่ยังไม่กดส่งไม่มีปุ่มนี้ — ยังไม่มีเลขที่ ไม่มี QR และเนื้อหายังแก้ได้ทุกบรรทัด
  --}}
  @if ($invest->hasNumber())
    <div class="bar is-paper">
      <span class="spacer"></span>

      {{--
        🔴 เจ้าของสั่งเอาปุ่ม "พิมพ์" ออก (2026-09-18) เหลือดาวน์โหลด PDF อย่างเดียว
           เหมือนหน้าใบงบที่อนุมัติแล้ว — ได้ไฟล์มาแล้วสั่งพิมพ์จากไฟล์ได้เลย
           หน้า budget.doc.print ยังอยู่ เพราะเป็นทางถอยเวลาสร้างไฟล์ไม่สำเร็จ
        🔴 ต้องมี download คู่กับ data-download ไม่งั้นจอ "กำลังโหลด" ค้าง (บทเรียน 2026-09-16)
      --}}
      @if ($canPdf)
        <a class="btn btn-primary" download data-download href="{{ route('budget.doc.pdf', $invest) }}"
           data-i18n="budget.downloadPdf">ดาวน์โหลด PDF</a>
      @endif
    </div>
  @endif

  {{-- ═══════════ ตัวเอกสาร (รูปแบบกระดาษ) — ชิ้นส่วนร่วมกับหน้าพิมพ์และไฟล์ PDF ═══════════ --}}
  @include('budget.approval.paper')

  {{-- ═══════════ ปุ่มดำเนินการ — เห็นเฉพาะผู้อนุมัติที่ยังไม่เซ็น ═══════════ --}}
  @if ($myStep)
    {{--
      🔴 เจ้าของสั่ง 2026-09-03: เหลือแค่ 2 ปุ่ม "อนุมัติ" กับ "ปฏิเสธ"
         ไม่มีหัวข้อ "ลงนามอนุมัติ" และไม่มีช่องความเห็นโผล่มาลอยๆ
         อนุมัติ  -> ติ๊กถ้าอยากใส่ความเห็น (ไม่บังคับ)
         ปฏิเสธ  -> ต้องกรอกเหตุผล (บังคับ)
    --}}
    <div class="bar" style="max-width:800px;margin:16px auto 0">
      <button type="button" class="btn btn-ok" data-act="ok" data-i18n="budget.approve">อนุมัติ</button>
      <button type="button" class="btn btn-danger" data-act="no" data-i18n="budget.reject">ปฏิเสธ</button>
    </div>

    <form method="POST" action="{{ route('budget.doc.approve', $invest) }}" id="ok-form" hidden>
      @csrf
      <input type="hidden" name="comment" id="ok-comment">
    </form>

    <form method="POST" action="{{ route('budget.doc.reject', $invest) }}" id="no-form" hidden>
      @csrf
      <input type="hidden" name="reason" id="no-reason">
    </form>

    {{-- ── หน้าต่างอนุมัติ — ความเห็นไม่บังคับ ── --}}
    <div class="modal-wrap" data-modal="ok-modal" hidden role="dialog" aria-modal="true">
      <div class="modal" style="width:min(440px,100%)">
        <div class="modal-head">
          <span class="modal-title" data-i18n="budget.approve">อนุมัติ</span>
          @include('access.partials.modal-x')
        </div>

        <div class="modal-body" style="display:grid;gap:12px">
          <p class="soft" data-i18n="budget.approveNote">ลายเซ็นจะถูกประทับลงในเอกสาร</p>

          <label class="cc-toggle">
            <input type="checkbox" id="ok-note-on">
            <span data-i18n="budget.comment">ความเห็น (ถ้ามี)</span>
          </label>

          <input class="input" type="text" id="ok-note" maxlength="255" hidden>
        </div>

        {{-- 🔴 ยินยอมซ้าย · ยกเลิกขวา --}}
        <div class="modal-foot">
          <button type="button" class="btn btn-ok" data-do-ok data-i18n="budget.approve">อนุมัติ</button>
          <span class="spacer"></span>
          <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.cancel">ยกเลิก</button>
        </div>
      </div>
    </div>

    {{-- ── หน้าต่างปฏิเสธ — เหตุผลบังคับ ── --}}
    <div class="modal-wrap" data-modal="no-modal" hidden role="dialog" aria-modal="true">
      <div class="modal" style="width:min(440px,100%)">
        <div class="modal-head">
          <span class="modal-title" data-i18n="budget.reject">ปฏิเสธ</span>
          @include('access.partials.modal-x')
        </div>

        <div class="modal-body" style="display:grid;gap:12px">
          <p class="soft" data-i18n="budget.rejectNote">เอกสารจะถูกตีกลับไปที่ผู้เสนอ</p>

          <label class="field">
            <span data-i18n="budget.rejectReason">เหตุผลที่ไม่อนุมัติ</span>
            <input class="input" type="text" id="no-note" maxlength="255" required>
          </label>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn btn-danger" data-do-no data-i18n="budget.reject">ปฏิเสธ</button>
          <span class="spacer"></span>
          <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.cancel">ยกเลิก</button>
        </div>
      </div>
    </div>

    <script>
      'use strict';

      var isEn = function () { return window.BMS.lang() === 'en'; };

      /*
        🔴 เจ้าของสั่ง 2026-09-10: เลิกให้กดประทับลายเซ็นเอง
           กด "อนุมัติ" = ระบบประทับลายเซ็นให้เลย (เซิร์ฟเวอร์เป็นคนเขียนลงเอกสาร)
           แต่ยังต้องถามยืนยันก่อนเสมอ เพราะประทับแล้วย้อนไม่ได้

        ไม่มีลายเซ็นในบัญชี = อนุมัติไม่ได้ — บอกตั้งแต่ตอนกด ไม่ปล่อยไปตายที่เซิร์ฟเวอร์
      */
      var hasSignature = @json((bool) $mySignature);

      document.querySelector('[data-act="ok"]').addEventListener('click', function () {
        if (!hasSignature) {
          window.BMS.notify({
            kind: 'warn',
            title: isEn() ? 'Add your signature in Insight first' : 'ลงนามลายเซ็นที่ Insight ก่อน',
            autoClose: 2600,
          });

          return;
        }

        document.querySelector('[data-modal="ok-modal"]').hidden = false;
      });

      document.querySelector('[data-act="no"]').addEventListener('click', function () {
        document.querySelector('[data-modal="no-modal"]').hidden = false;
      });

      // ติ๊กแล้วค่อยขึ้นช่องความเห็น
      (function () {
        var tick = document.getElementById('ok-note-on');
        var note = document.getElementById('ok-note');

        tick.addEventListener('change', function () {
          note.hidden = !tick.checked;
          if (tick.checked) { note.focus(); } else { note.value = ''; }
        });
      })();

      document.querySelector('[data-do-ok]').addEventListener('click', function () {
        window.BMS.confirm({
          kind: 'ok',
          title: isEn() ? 'Approve this document?' : 'อนุมัติเอกสารนี้?',
          text: isEn()
            ? 'Your signature will be stamped on the document. This cannot be undone.'
            : 'ระบบจะประทับลายเซ็นของคุณลงในเอกสาร และแก้ไขไม่ได้อีก',
          ok: isEn() ? 'Approve' : 'อนุมัติ',
          onOk: function () {
            document.getElementById('ok-comment').value = document.getElementById('ok-note').value.trim();
            document.getElementById('ok-form').requestSubmit();
          },
        });
      });

      document.querySelector('[data-do-no]').addEventListener('click', function () {
        var reason = document.getElementById('no-note').value.trim();

        if (!reason) {
          window.BMS.notify({
            kind: 'warn',
            title: isEn() ? 'Reason required' : 'ต้องระบุเหตุผล',
            autoClose: 2200,
          });

          document.getElementById('no-note').focus();

          return;
        }

        window.BMS.confirm({
          kind: 'no',
          title: isEn() ? 'Reject this document?' : 'ปฏิเสธเอกสารนี้?',
          text: isEn()
            ? 'The document ends here — the requester has to raise a new one.'
            : 'เอกสารจะจบที่สถานะไม่อนุมัติ ผู้ขอต้องสร้างเอกสารใบใหม่',
          ok: isEn() ? 'Reject' : 'ปฏิเสธ',
          onOk: function () {
            document.getElementById('no-reason').value = reason;
            document.getElementById('no-form').requestSubmit();
          },
        });
      });
    </script>
  @endif
  @include('budget.partials.assets')
  @include('budget.partials.paper')
  @include('access.partials.picker-assets')

  <style>
    /* ปุ่มติ๊กเปิดช่องความเห็น */
    .cc-toggle {
      display: inline-flex; align-items: center; gap: 8px;
      color: var(--ink-soft); font-size: var(--fs-sm); font-weight: 600; cursor: pointer;
    }

    .cc-toggle input { width: 15px; height: 15px; accent-color: var(--navy-800); cursor: pointer; }
  </style>
@endsection
