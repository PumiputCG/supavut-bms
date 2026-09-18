{{--
  คนหนึ่งคนในตัวเอกสาร (กระดาษ) — รูปโปรไฟล์อยู่บรรทัดเดียวกับชื่อ-สกุล
  (เจ้าของสั่ง 2026-09-04)

  🔴 หน้ากระดาษต้องใช้ชิ้นส่วนนี้เท่านั้น — คลาส .route-* มีสไตล์อยู่ในชิ้นส่วน
     เส้นทางเอกสาร หน้ากระดาษไม่ได้โหลดไป รูปกับชื่อจะหล่นคนละบรรทัด (บั๊กจริง 2026-09-04)

  🔴 รูปต้องกดขยาย/ซูมได้ตามกฎของโปรเจค (DESIGN_SYSTEM.md หัวข้อ 6A)

  พารามิเตอร์
    $code  รหัสพนักงาน
    $name  ชื่อที่จะแสดง (สำเนาที่เก็บไว้ในเอกสาร ไม่ join สด)
    $nameEn ชื่อภาษาอังกฤษ (ไม่ส่งมา = ใช้ชื่อไทยแทน) — สลับตามภาษาที่ผู้ใช้เลือก
    $note  ข้อความต่อท้ายชื่อ เช่น ตำแหน่ง (ไม่ใส่ก็ได้)
--}}
@php
  // รูปมาจากแผนที่ที่ controller เตรียมไว้ ไม่ยิงคิวรีทีละคน
  $paperPerson = [
    'employee_code' => (string) $code,
    'name_th' => $name,
    'name_en' => $nameEn ?? $name,
    'photo' => ($signerPhotos ?? [])[$code] ?? null,
    'initial' => mb_substr((string) ($name ?: '?'), 0, 1),
  ];
@endphp

<span class="paper-person">
  @include('access.partials.avatar', ['person' => $paperPerson, 'size' => 'sm'])
  {{-- 🔴 ชื่อคนต้องสลับตามภาษา (เจ้าของสั่ง 2026-09-10) --}}
  <span class="paper-person-name"
        data-loc-th="{{ $name }}" data-loc-en="{{ $nameEn ?? $name }}">{{ $name }}</span>
  @if (! empty($note))
    <span class="paper-person-note">{{ $note }}</span>
  @endif
</span>
