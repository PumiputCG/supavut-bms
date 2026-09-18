{{--
  หน้าพิมพ์ / ดาวน์โหลด PDF ของงบประมาณที่อนุมัติแล้ว (เจ้าของสั่ง 2026-09-10)

  🔴 โครงหน้า (กฎการพิมพ์ · ระยะขอบ · แถบปุ่ม) อยู่ที่ชิ้นส่วนกลาง budget.partials.print-shell
     ยกออกไปเมื่อ 2026-09-18 ตอนเพิ่มปุ่มดาวน์โหลดให้ใบ INV — กฎการพิมพ์ต้องมีชุดเดียวทั้งระบบ

  🔴 ตัวกระดาษใช้ชิ้นส่วนเดียวกับหน้าจอปกติ (budget.list.paper)
     แก้เอกสารที่เดียว ทั้ง 2 หน้าเปลี่ยนตามกัน

  ตัวแปรที่ต้องส่งมา: $budget · $signerPhotos · $canPdf · $forPdf
--}}
@php
  $forPdf = $forPdf ?? false;
  $canPdf = $canPdf ?? false;

  $A = \App\Models\Budget\Approval::class;
  $invest = $budget->invest;
  $signers = $invest ? $invest->approvals->where('action', $A::APPROVE) : collect();
@endphp

@include('budget.partials.print-shell', [
  'docNo' => $budget->doc_no,
  'titleText' => 'งบประมาณที่อนุมัติแล้ว',
  'paperView' => 'budget.list.paper',
  'pdfUrl' => $canPdf ? route('budget.list.pdf', $budget) : null,
])
