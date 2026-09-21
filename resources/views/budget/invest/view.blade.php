{{--
  โหมด "ดูเอกสาร" ของหน้าของบประมาณ (เจ้าของสั่ง 2026-09-04)

  บันทึกร่างแล้วต้องเห็นเป็น **ตัวเอกสาร** ก่อนเสมอ ไม่ใช่ช่องกรอก
  ยังเป็นร่าง = มีปุ่ม "แก้ไข" สลับไปโหมดกรอก
  ส่งไปแล้ว   = แก้ไม่ได้ เหลือปุ่มดาวน์โหลด PDF กับปุ่มลบ

  🔴🔴 เนื้อในใช้ชิ้นส่วนกลาง `budget.partials.doc-body` ตัวเดียวกับ /budget/doc/{id}
     และ /budget/list/{id} (เจ้าของแจ้ง 2026-09-21 ว่า "บรรทัดไม่เท่ากับหน้าอื่น")
     ของเดิมหน้านี้ **เขียนตัวเอกสารซ้ำเป็นสำเนาของตัวเอง** แล้วหลุดจากกันไปจริง —
     ช่องลายเซ็นของคนที่ไม่อนุมัติไม่เป็นสีแดง และบรรทัดหมวดงบแสดงไม่เหมือนกัน
     ซ้ำรอยบทเรียนเดิมเรื่องชิ้นส่วนที่ก็อปไปวาง (`.avatar` · `.doc-reason*` · modal · `.tbl-wrap`)
--}}
@php
  $labels = \App\Models\Budget\Invest::STATUS_LABELS;

  // ช่องลงชื่อมีแต่ผู้อนุมัติ — ผู้รับทราบไม่มีช่องเซ็น (กติกาเดิม 2026-09-03)
  $signers = $invest->approvals->where('action', \App\Models\Budget\Approval::APPROVE);

  /*
    ร่างที่หมวดถูกปิดไปหลังบันทึก — ต้องบอกให้เห็นในตัวเอกสารเลย
    ตัดสินที่นี่ แล้วส่งผลให้ชิ้นส่วนกลางวาด (ชิ้นส่วนกลางไม่ตัดสินเอง)
  */
  $groupOff = $invest->group && ! $invest->group->active && $invest->isDraft();
@endphp

{{--
  ═══════════ แถบปุ่มเอกสาร ═══════════
  🔴 เจ้าของสั่ง 2026-09-21: หน้านี้ต้องดาวน์โหลด PDF ได้ด้วย "เพื่อเขาจะได้ดาวน์โหลดและติดตาม"
     วางไว้เหนือกระดาษชิดขวา แบบเดียวกับหน้า /budget/doc/{id} เป๊ะ
  🔴 ร่างที่ยังไม่กดส่งไม่มีปุ่มนี้ — ยังไม่มีเลขที่ ไม่มี QR และเนื้อหายังแก้ได้ทุกบรรทัด
  🔴 ต้องมี download คู่กับ data-download ไม่งั้นจอ "กำลังโหลด" ค้าง (บทเรียน 2026-09-16)
--}}
@if ($invest->hasNumber() && ($canPdf ?? false))
  <div class="bar is-paper">
    <span class="spacer"></span>
    <a class="btn btn-primary" download data-download href="{{ route('budget.doc.pdf', $invest) }}"
       data-i18n="budget.downloadPdf">ดาวน์โหลด PDF</a>
  </div>
@endif

<section class="paper" data-doc-view>
  @include('budget.partials.paper-head', [
    'docNo' => $invest->doc_no,
    // 🔴 ร่างยังไม่มีเลขที่ — ออกตอนกดส่ง (เจ้าของสั่ง 2026-09-17)
    'pendingNo' => ! $invest->hasNumber(),
    'status' => ['code' => $invest->approval_status, 'labels' => $labels],
    'trackUrl' => $invest->trackUrl(),
  ])

  {{-- ไม่ส่ง myStep/mySignature — หน้านี้กดเซ็นไม่ได้ จึงไม่มีช่องลายเซ็นของ "เรา" --}}
  @include('budget.partials.doc-body', [
    'invest' => $invest,
    'signers' => $signers,
    'signerPhotos' => $signerPhotos,
    'groupOff' => $groupOff,
  ])
</section>

{{--
  ปุ่มของโหมดดู
  🔴 ยินยอมอยู่ซ้าย · ทำลายอยู่ขวา (กติกาของโปรเจค)
  🔴 ส่งไปแล้วห้ามมีปุ่มแก้ไข — เอกสารที่คนอื่นเห็นแล้วต้องไม่เปลี่ยนเนื้อหาได้อีก
--}}
<div class="bar" style="max-width:800px;margin:16px auto 0">
  @if ($invest->isDraft())
    <button type="button" class="btn" data-do-submit data-i18n="budget.submit">ส่งเอกสาร</button>
    <button type="button" class="btn btn-outline" data-go-edit data-i18n="budget.edit">แก้ไข</button>
  @endif
  <span class="spacer"></span>
  @if ($canDelete)
    <button type="button" class="btn btn-danger" data-do-delete data-i18n="budget.delete">ลบเอกสาร</button>
  @endif
</div>
