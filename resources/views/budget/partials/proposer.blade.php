{{--
  ผู้เสนอ — กดปุ่ม "รายละเอียด" ในคอลัมน์ผู้เสนอแล้วเด้งหน้าต่างนี้ขึ้นมา
  (เจ้าของสั่ง 2026-09-04: ในตารางอย่าโชว์ชื่อยาวๆ ให้เป็นปุ่มแล้วกางดูในหน้าต่างแทน)

  🔴 "วันที่เสนอ" คือวันที่ผู้ขอเดินมาขอตั้งงบ ไม่ใช่วันที่ฝ่ายบัญชีกรอกเอกสาร
     ฝ่ายบัญชีเป็นคนเลือกวันนี้ให้ตอนกรอก จึงเก็บแยกจาก created_at

  🔴 รูปต้องกดขยาย/ซูมได้ตามกฎของโปรเจค (DESIGN_SYSTEM.md หัวข้อ 6A)
     ใช้ชิ้นส่วน avatar เดิม จะได้ผูกกับตัวขยายรูปกลางอัตโนมัติ

  วิธีใช้
    หน้าที่แถวเป็น Invest  ส่ง rows ตรงๆ
    หน้าที่แถวเป็น Budget  ส่ง rows->pluck('invest')->filter()
    แล้วในตารางใส่ปุ่มที่เปิดหน้าต่าง pp- ตามด้วย id ของ Invest

  หน้าไหนใช้ชิ้นส่วนนี้ ต้องมี access.partials.picker-assets ในหน้าด้วย
  (โค้ดหน้าต่างซ้อนอยู่ที่นั่น)
--}}
@php
  /*
    ข้อมูลคนดึงรวดเดียวทั้งหน้า — ไม่ยิงคิวรีทีละแถว
    🔴 ใช้ ->all() เสมอ: หน้าที่แบ่งหน้าส่ง Paginator มา ถ้า collect() ตรงๆ
       จะได้ข้อมูลหน้ากระดาษ (current_page ฯลฯ) แทนที่จะได้แถวเอกสาร
  */
  $ppCodes = collect($rows->all())->map(fn ($d) => $d->proposerCode())->filter()->unique()->values()->all();
  $ppPeople = $ppCodes === []
    ? []
    : collect(app(\App\Services\Access\AccessService::class)->describeCodes($ppCodes))
        ->keyBy('employee_code')
        ->all();
@endphp

@foreach ($rows as $doc)
  @php
    $code = $doc->proposerCode();
    // ชื่อใช้สำเนาที่เก็บไว้ในเอกสาร ส่วนตำแหน่ง/แผนก/รูป ดึงของปัจจุบันมาประกอบ
    $person = ($ppPeople[$code] ?? null) ?: [
      'employee_code' => $code,
      'name_th' => $doc->proposerName(),
      'name_en' => $doc->proposerNameEn(),
      'position' => '',
      'department' => '',
      'photo' => null,
      'initial' => mb_substr($doc->proposerName() ?: '?', 0, 1),
    ];
  @endphp

  <div class="modal-wrap" data-modal="pp-{{ $doc->id }}" hidden>
    <div class="modal" style="width:min(460px,100%)">
      <div class="modal-head">
        <span class="modal-title">
          <span data-i18n="budget.proposer">ผู้ขอ</span>
          @if ($doc->doc_no)<span class="soft"> · {{ $doc->doc_no }}</span>@endif
        </span>
        @include('access.partials.modal-x')
      </div>

      <div class="modal-body">
        <div class="person-row">
          @include('access.partials.avatar', ['person' => $person, 'size' => ''])
          <span class="person-text">
            <span class="person-name" data-loc-th="{{ $doc->proposerName() }}" data-loc-en="{{ $doc->proposerNameEn() }}">{{ $doc->proposerName() }}</span>
            <span class="person-meta">
              {{ $code }}{{ $person['position'] ? ' · '.$person['position'] : '' }}{{ $person['department'] ? ' · '.$person['department'] : '' }}
            </span>
          </span>
        </div>

        <dl class="pp-rows">
          <div class="pp-row">
            <dt data-i18n="budget.preparedAt">วันที่จัดทำ</dt>
            {{-- 🔴 วันที่จัดทำต้องมีเวลาด้วยทุกหน้า (เจ้าของสั่ง 2026-09-10) --}}
            <dd>{{ $doc->submitted_at?->format('d/m/Y H:i') ?: '—' }}</dd>
          </div>
          <div class="pp-row">
            <dt data-i18n="budget.itemTitle">ชื่อเอกสาร</dt>
            <dd>{{ $doc->title }}</dd>
          </div>
        </dl>
      </div>

      <div class="modal-foot">
        <span class="spacer"></span>
        <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.close">ปิด</button>
      </div>
    </div>
  </div>
@endforeach

<style>
  /* รายละเอียดผู้เสนอ — ป้ายซ้าย ค่าขวา แบบเดียวกับกระดาษเอกสาร */
  .pp-rows { display: grid; gap: 0; }
  .pp-row {
    display: grid; grid-template-columns: 128px 1fr; gap: 12px;
    padding: 9px 2px; border-bottom: 1px solid var(--line-soft);
  }
  .pp-row:last-child { border-bottom: 0; }
  .pp-row dt { color: var(--ink-soft); font-size: var(--fs-sm); font-weight: 600; }
  .pp-row dd { margin: 0; color: var(--ink); }
</style>
