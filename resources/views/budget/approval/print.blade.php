{{--
  หน้าพิมพ์ / ดาวน์โหลด PDF ของ "ใบขออนุมัติตั้งงบประมาณ" (ใบ INV)

  🔴 ทำไมต้องมี (เจ้าของถาม 2026-09-18 ว่า "ถ้าต้องเปิดเอกสารใน SBMS ตลอด จะเพิ่ม QR ไปทำไม")
     ใบ INV คือใบที่เดินเซ็นจริง แต่ก่อนหน้านี้ **ดาวน์โหลดหรือพิมพ์ไม่ได้เลย**
     ไม่มีกระดาษ = ไม่มีที่ให้ QR ไปอยู่ = QR ไม่มีใครได้ใช้

  🔴 ไฟล์ที่ออกไปต้องไม่มีปุ่มอนุมัติ/ปฏิเสธ — ตัวกระดาษอยู่ที่ budget.approval.paper
     ซึ่งรับ $myStep = null จากที่นี่ ปุ่มจึงไม่ถูกวาดตั้งแต่ต้น (ไม่ใช่ซ่อนด้วย CSS)

  ตัวแปรที่ต้องส่งมา: $invest · $signerPhotos · $canPdf · $forPdf
--}}
@php
  $forPdf = $forPdf ?? false;
  $canPdf = $canPdf ?? false;

  // 🔴 หน้าพิมพ์ไม่มีการลงนาม — ส่ง null ให้ชัดเจน ไม่ปล่อยให้ตัวกระดาษเดาเอง
  $myStep = null;
  $mySignature = null;
@endphp

@include('budget.partials.print-shell', [
  'docNo' => $invest->hasNumber() ? $invest->doc_no : 'ร่าง',
  'titleText' => 'ใบขออนุมัติตั้งงบประมาณ',
  'paperView' => 'budget.approval.paper',
  'pdfUrl' => $canPdf ? route('budget.doc.pdf', $invest) : null,
])
