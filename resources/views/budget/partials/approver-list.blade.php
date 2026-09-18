{{--
  สำเนาเรียน — แยก 2 ส่วน (เจ้าของสั่ง 2026-09-16)

    1. สำเนาเรียน (ผู้อนุมัติตามลำดับ)  — ต้องลงนาม · มีเลขลำดับ เพราะลำดับคือคิวการเซ็นจริง
    2. สำเนาเรียน (ผู้รับทราบ)          — ไม่ต้องลงนาม · ไม่มีเลขลำดับ เพราะไม่มีคิว

  🔴 ของเดิมรวมอยู่ก้อนเดียว ผู้ใช้แยกไม่ออกว่าใครต้องเซ็นใครแค่รับทราบ

  🔴 เรียงตาม step เสมอ ห้ามเรียงตามชื่อหรือรหัส — ลำดับคือสายอนุมัติจริง
  🔴 CEO อยู่ในสายตั้งแต่ตอนบันทึกร่างแล้ว (แถว is_final) หน้านี้จึงไม่ต้องเติมเอง
  🔴 ช่องลงชื่อในกระดาษยังมีเฉพาะผู้อนุมัติ ($signers กรอง action = approve อยู่แล้ว)

  $rows          คอลเลกชันของ App\Models\Budget\Approval
  $signerPhotos  แผนที่รูปพนักงาน (รหัส => url) — person-chip ใช้วาดรูป
--}}
@php
  $A = \App\Models\Budget\Approval::class;
  $all = collect($rows)->sortBy('step')->values();

  /*
    🔴 ทั้ง 2 ก้อนนับเลขของตัวเองเริ่มที่ 1 ใหม่ (เจ้าของสั่ง 2026-09-16)
       ไม่ใช่นับต่อกัน — คนละเรื่องกัน คนละหัวข้อ
  */
  $groups = [
    [
      'key' => 'budget.approvers', 'th' => 'สำเนาเรียน (ผู้อนุมัติตามลำดับ)',
      'list' => $all->where('action', $A::APPROVE)->values(),
    ],
    [
      'key' => 'budget.recipients', 'th' => 'สำเนาเรียน (ผู้รับทราบ)',
      'list' => $all->where('action', $A::ACK)->values(),
    ],
  ];
@endphp

@foreach ($groups as $group)
  {{-- กลุ่มที่ไม่มีคนเลย ไม่ต้องขึ้นหัวข้อโล่งๆ ให้สงสัยว่าข้อมูลหาย --}}
  @continue ($group['list']->isEmpty())

  <p class="paper-sec" data-i18n="{{ $group['key'] }}">{{ $group['th'] }}</p>

  <ol class="apv-doc">
    @foreach ($group['list'] as $step)
      <li class="apv-doc-row">
        <span class="apv-doc-no">{{ $loop->iteration }}</span>
        @include('budget.partials.person-chip', ['step' => $step, 'docStatus' => $docStatus ?? null])
      </li>
    @endforeach
  </ol>
@endforeach
