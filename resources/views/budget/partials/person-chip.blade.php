{{--
  คนหนึ่งคนในสายอนุมัติ — รูป + ชื่อ + สถานะ + หมายเหตุ
  ใช้ในรายการ "สำเนาเรียน (ผู้อนุมัติตามลำดับ)" ของทุกหน้าที่เป็นตัวเอกสาร

  จัดเป็น 2 บรรทัด อ่านไล่ลงมาได้ (เจ้าของสั่งจัดใหม่ 2026-09-10)
    1  ชื่อ-สกุล (ตำแหน่ง, แผนก)
    2  [สถานะ] [หมายเหตุ: …]   — 2 คอลัมน์ ตรงกันทุกแถว · ไม่มีกรอบสี · ไม่มีวันที่

  $step       แถว App\Models\Budget\Approval
  $docStatus  สถานะของเอกสารทั้งใบ — ใช้ตัดสินว่าคนที่ยังไม่เซ็นคือ "รออนุมัติ" หรือ "ไม่ได้ดำเนินการ"
--}}
@php
  // ชื่อ/รูปเก็บเป็นสำเนาไว้ในเอกสารแล้ว — หารูปจากบัญชีจริงมาเสริมถ้ายังมีอยู่
  $person = [
    'employee_code' => (string) $step->employee_code,
    'name_th' => $step->employee_name ?: $step->employee_code,
    // 🔴 ไม่มีชื่ออังกฤษให้ถอยไปใช้ไทย ห้ามปล่อยว่างจนชื่อหายตอนสลับภาษา
    'name_en' => $step->employee_name_en ?: ($step->employee_name ?: $step->employee_code),
    'photo' => $signerPhotos[$step->employee_code] ?? null,
    'initial' => mb_substr($step->employee_name ?: $step->employee_code, 0, 1),
  ];

  // ตำแหน่ง · แผนก — อ่านจากสำเนาในเอกสาร ไม่ join สดจากบัญชี
  // คนย้ายแผนกทีหลัง เอกสารเก่าต้องบอกของ ณ ตอนที่อยู่ในสาย
  $meta = array_filter([$step->position, $step->department]);

  // หมายเหตุของคนนี้ — เดิมไปกองรวมกันท้ายกระดาษ จับคู่กับเจ้าของคำพูดยาก
  $note = trim((string) $step->comment);
@endphp

@include('access.partials.avatar', ['person' => $person, 'size' => 'sm'])

<span class="chip-body">
  {{-- บรรทัดที่ 1 — ชื่อ-สกุล (ตำแหน่ง, แผนก) --}}
  <span class="chip-name">
    <span data-loc-th="{{ $person['name_th'] }}" data-loc-en="{{ $person['name_en'] }}">{{ $person['name_th'] }}</span>
    @if ($meta)
      <span class="chip-meta">({{ implode(', ', $meta) }})</span>
    @endif
  </span>

  {{--
    บรรทัดที่ 2 — สถานะ + หมายเหตุ อยู่บรรทัดเดียวกัน (เจ้าของสั่ง 2026-09-10)

    🔴 เอาวันที่ออกแล้ว — วันเวลาที่ดำเนินการดูได้ที่ช่องลงชื่อและหน้าต่าง "ดูเส้นทาง"
       ใส่ตรงนี้อีกทำให้บรรทัดยาวโดยไม่ได้ข้อมูลใหม่
    🔴 หมายเหตุไม่ใส่กรอบสี — สีบอกผลอยู่ที่คำว่าสถานะแล้ว
  --}}
  <span class="chip-state">
    {{-- 🔴 คำและสีมาจากตัวแปลผลตัวเดียวกับตารางเส้นทาง --}}
    @include('budget.partials.step-status', ['step' => $step, 'docStatus' => $docStatus ?? null])

    @if ($note !== '')
      <span class="chip-note">
        <span class="chip-note-key" data-i18n="budget.tl.note">หมายเหตุ</span>:
        <span class="chip-note-val">{{ $note }}</span>
      </span>
    @else
      <span></span>
    @endif
  </span>
</span>
