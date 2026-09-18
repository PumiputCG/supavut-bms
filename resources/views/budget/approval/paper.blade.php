{{--
  ตัวกระดาษของ "ใบขออนุมัติตั้งงบประมาณ" (ใบ INV)

  🔴 ยกออกมาเป็นชิ้นส่วนเมื่อ 2026-09-18 ตอนเพิ่มปุ่มพิมพ์/ดาวน์โหลด PDF ให้ใบ INV
     หน้าจอปกติ (budget.approval.show) · หน้าพิมพ์ · ไฟล์ PDF ใช้ตัวนี้ทั้ง 3 ทาง
     แก้เอกสารที่เดียว เปลี่ยนพร้อมกันหมด ไม่มีทางที่ไฟล์ที่พิมพ์ออกไปจะไม่ตรงกับที่เห็นบนจอ

  ตัวแปรที่ต้องส่งมา
    $invest        เอกสาร
    $signerPhotos  รูปผู้ลงนาม
    $myStep        คิวของคนที่เปิดดู (null = ไม่มีปุ่มเซ็น · หน้าพิมพ์กับไฟล์ PDF ส่ง null เสมอ)
    $mySignature   ลายเซ็นของคนที่เปิดดู (null ได้)
--}}
@php
  $labels = \App\Models\Budget\Invest::STATUS_LABELS;
  $A = \App\Models\Budget\Approval::class;

  // แยก 2 บทบาทให้ขาดกัน — ผู้รับทราบไม่มีช่องเซ็น (เจ้าของสั่ง 2026-09-03)
  $recipients = $invest->approvals->where('action', $A::ACK);
  $signers = $invest->approvals->where('action', $A::APPROVE);

  $myStep = $myStep ?? null;
  $mySignature = $mySignature ?? null;
@endphp

<section class="paper">
  @include('budget.partials.paper-head', [
    'docNo' => $invest->doc_no,
    'pendingNo' => ! $invest->hasNumber(),
    'status' => ['code' => $invest->approval_status, 'labels' => $labels],
    'trackUrl' => $invest->trackUrl(),
  ])

  {{--
    🔴 เนื้อในของเอกสารอยู่ที่ชิ้นส่วนกลาง (เจ้าของสั่ง 2026-09-10)
       หน้างบที่อนุมัติแล้วใช้ตัวเดียวกัน — หน้าตาจึงเหมือนกันเสมอ ไม่มีทางหลุดจากกัน
  --}}
  @include('budget.partials.doc-body', [
    'invest' => $invest,
    'signers' => $signers,
    'myStep' => $myStep,
    'mySignature' => $mySignature,
  ])

  {{--
    🔴 ความเห็นรายคนย้ายไปอยู่ในสำเนาเรียนแล้ว (เจ้าของสั่ง 2026-09-10)
       อยู่ติดกับชื่อเจ้าของคำพูด อ่านง่ายกว่ากองรวมกันท้ายกระดาษ
       เหลือไว้เฉพาะทางถอยของเอกสารเก่าที่เก็บเหตุผลไว้ที่หัวเอกสารอย่างเดียว
  --}}
  @if ($invest->approval_status === \App\Models\Budget\Invest::REJECTED
    && $invest->reject_reason
    && $signers->every(fn ($s) => trim((string) $s->comment) === ''))
    <p class="doc-reason" style="margin-top:20px">
      <span class="doc-reason-key" data-i18n="budget.reasonShort">เหตุผล</span>:
      <span class="doc-reason-val is-no">{{ $invest->reject_reason }}</span>
    </p>
  @endif
</section>
