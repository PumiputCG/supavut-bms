{{--
  ป้ายสถานะ — ไม่มีกรอบ ใช้สีข้อความบอก (กฎใน DESIGN_SYSTEM)
  $code = รหัสสถานะ · $labels = ตารางป้าย 2 ภาษาจาก model
--}}
@php
  $tone = [
    'DRAFT' => 'st-draft', 'PENDING_APPROVAL' => 'st-pending',
    'APPROVED' => 'st-approved', 'REJECTED' => 'st-rejected',
    // สถานะการลงทะเบียนกับ ERP — เหลือง = ยังไม่ได้คีย์ · เขียว = คีย์แล้ว
    'PENDING_REGISTER' => 'st-pending', 'REGISTERED' => 'st-approved',
    /*
      ผู้รับทราบ — พื้นขาว ตัวอักษรดำ (เจ้าของสั่ง 2026-09-16)
      🔴 จงใจไม่ระบายสี เพราะไม่ใช่ผลของการตัดสิน ใส่สีจะแย่งสายตาไปจากผู้ลงนาม
    */
    'ACKNOWLEDGED' => 'st-ack',
  ][$code] ?? '';
  $label = $labels[$code] ?? ['th' => $code, 'en' => $code];
@endphp
<span class="st {{ $tone }}"
      data-loc-th="{{ $label['th'] }}" data-loc-en="{{ $label['en'] }}">{{ $label['th'] }}</span>
