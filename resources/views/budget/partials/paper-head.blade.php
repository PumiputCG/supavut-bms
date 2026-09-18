{{--
  หัวกระดาษ — โลโก้บริษัท + ชื่อบริษัท + เลขที่เอกสาร + สถานะ
  ใช้ร่วมทุกหน้าที่เป็นตัวเอกสาร (สร้าง Invest · ดูเอกสาร · เซ็น)

  พารามิเตอร์
    $title   ชื่อเรื่องกลางกระดาษ (คู่ th/en)
    $docNo   เลขที่เอกสาร (ไม่มีก็ได้ — ตอนยังเป็นร่างที่ยังไม่บันทึก)
    $pendingNo  (ไม่บังคับ) true = ร่างที่บันทึกแล้วแต่ยังไม่ส่ง ให้ขึ้นป้าย "ยังไม่ออกเลข"
                🔴 เลขที่ออกตอนกดส่ง (เจ้าของสั่ง 2026-09-17) ร่างจึงไม่มีเลขให้โชว์
    $status  ['code' => ..., 'labels' => ...] สำหรับป้ายสถานะ (ไม่มีก็ได้)
    $trackUrl ลิงก์หน้าติดตามเอกสาร — มีค่าแล้วจะวาด QR ให้ที่มุมขวาบน (ไม่มีก็ได้)
--}}
<div class="paper-head">
  <img class="paper-logo" src="{{ asset('img/company-logo.png') }}" alt="">

  <div class="paper-org">
    <b data-i18n="doc.company">บริษัท สุภาวุฒิ อินดัสทรี จำกัด</b>
    <span class="paper-org-en" data-i18n="doc.companyEn">Supavut Industry Co., Ltd.</span>

    {{--
      🔴 เลขที่เอกสาร + ป้ายสถานะ อยู่ "ใต้ชื่อบริษัท" (เจ้าของสั่งย้าย 2026-09-18)
         เดิมอยู่มุมขวาบนเหนือ QR ทำให้หัวกระดาษสูงและ QR ถูกดันลงมา
         ย้ายมาฝั่งซ้ายแล้ว QR ได้ขึ้นไปอยู่บนสุดของมุมขวา และเส้นกั้นขยับขึ้นตาม
    --}}
    @if (! empty($docNo) || ! empty($pendingNo) || ! empty($status))
      <span class="paper-ids">
        @if (! empty($docNo) || ! empty($pendingNo))
          @include('budget.partials.doc-no', ['no' => $docNo ?? null])
        @endif
        @if (! empty($status))
          @include('budget.partials.status', ['code' => $status['code'], 'labels' => $status['labels']])
        @endif
      </span>
    @endif
  </div>

  <div class="paper-meta">

    {{--
      QR ติดตามเอกสาร — เจ้าของกำหนดให้อยู่ "มุมขวาบนของเอกสาร" (2026-09-18)
      🔴 วาดเป็น SVG จึงติดลงไฟล์ PDF เองโดยไม่ต้องโหลดรูปจากที่ไหน (ดู App\Support\QrCode)
      🔴 ร่างที่ยังไม่กดส่งไม่มีกุญแจ = ไม่มี QR (เนื้อหายังแก้ได้ · ยังไม่มีเลขที่เอกสาร)
    --}}
    @if (! empty($trackUrl))
      <div class="paper-qr">
        {!! \App\Support\QrCode::svg($trackUrl, 22, 'ติดตามเอกสาร / Track document') !!}
        <span data-i18n="budget.qrHint">ติดตามเอกสาร</span>
      </div>
    @endif
  </div>
</div>

<p class="paper-title" data-i18n="{{ $titleKey ?? 'doc.invest.title' }}">{{ $title ?? 'ใบขออนุมัติตั้งงบประมาณ' }}</p>
