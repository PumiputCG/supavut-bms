{{--
  เนื้อในของ "ตัวเอกสาร" — ใช้ร่วมกันทุกหน้าที่แสดงเอกสารขออนุมัติตั้งงบ

  🔴 เจ้าของสั่ง 2026-09-10: หน้า /budget/list/{id} ต้องหน้าตาเหมือน /budget/doc/{id}
     ต่างกันแค่เลขที่มุมขวาบน (BGT-… แทน INV-…)
     จึงยกเนื้อในมาไว้ที่เดียว ทั้ง 2 หน้า include ตัวเดียวกัน
     — แก้เอกสารทีเดียวเปลี่ยนพร้อมกัน ไม่มีทางหลุดจากกันอีก

  ตัวแปรที่ต้องส่งมา
    $invest        เอกสาร Invest ต้นทาง
    $signers       ผู้ลงนามในสาย (action = approve)
    $signerPhotos  แผนที่รูปพนักงาน

  ตัวเลือก
    $amount    ['key','th','value']  ทับบรรทัดวงเงิน (หน้างบใช้ "วงเงินที่อนุมัติ")
    $tail      ชื่อ view ของบรรทัดเพิ่มเติมท้ายตาราง (หน้างบใช้บอกหมายเหตุสถานะ)
    $myStep    ขั้นของคนที่กำลังดูอยู่ — มีเฉพาะหน้าที่กดเซ็นได้
    $mySignature ลายเซ็นของคนที่กำลังดู
--}}
@php
  $A = \App\Models\Budget\Approval::class;

  // หน้าที่ไม่ได้ส่งมา = ไม่มีใครกำลังรอเซ็นในหน้านั้น
  $myStep = $myStep ?? null;
  $mySignature = $mySignature ?? null;

  /*
    ป้ายเตือน "หมวดนี้ถูกปิดใช้งาน" — หน้าที่เรียกใช้เป็นคนตัดสินว่าจะขึ้นไหม
    🔴 ชิ้นส่วนนี้แค่วาดตามที่สั่ง ไม่ตัดสินเอง (กฎของโปรเจค: ตรรกะไม่อยู่ใน Blade ของชิ้นส่วนกลาง)
  */
  $groupOff = $groupOff ?? false;

  // บรรทัดวงเงิน — หน้างบทับด้วย "วงเงินที่อนุมัติ" ของตัวงบเอง
  $amount = $amount ?? [
    'key' => 'budget.proposedAmount',
    'th' => 'วงเงินที่เสนอ (บาท)',
    'value' => $invest->amount,
  ];
@endphp

<dl class="paper-rows">
  {{--
    หมวดงบประมาณ — ต้องอยู่ในตัวเอกสารด้วย (เจ้าของแจ้ง 2026-09-17)
    โค้ดอยู่ในเลขที่มุมขวาบนก็จริง แต่ชื่อหมวดอ่านง่ายกว่าโค้ด
  --}}
  @if ($invest->group)
    <div class="paper-row">
      <dt data-i18n="budget.docGroup">หมวดงบประมาณ</dt>
      <dd>
        <span data-loc-th="{{ $invest->group->name_th }}" data-loc-en="{{ $invest->group->name_en ?: $invest->group->name_th }}">{{ $invest->group->name_th }}</span>
        <span class="soft">({{ $invest->group->code }})</span>
        @if ($groupOff)
          <span class="pill pill-warn" data-i18n="budget.group.goneShort">หมวดนี้ถูกปิดใช้งาน — ต้องเลือกหมวดใหม่</span>
        @endif
      </dd>
    </div>
  @endif
  <div class="paper-row">
    <dt data-i18n="budget.year">ปีงบประมาณ</dt>
    <dd>{{ $invest->fiscal_year }}</dd>
  </div>
  {{--
    🔴 "วันที่จัดทำ" = เวลาที่กดส่งเรื่อง ไม่ใช่เวลาที่กดบันทึกร่างครั้งแรก (เจ้าของสั่ง 2026-09-10)
       ต้องตรงกับแถวผู้ขอในตาราง "ดูเส้นทาง" คอลัมน์ "วันที่ดำเนินการ"
       ร่างที่ยังไม่ได้ส่ง = ยังไม่มีวันที่ ขึ้นขีดกลางไว้
  --}}
  <div class="paper-row">
    <dt data-i18n="budget.preparedAt">วันที่จัดทำ</dt>
    <dd>{{ $invest->submitted_at?->format('d/m/Y H:i') ?: '—' }}</dd>
  </div>
  <div class="paper-row">
    <dt data-i18n="budget.proposer">ผู้ขอ</dt>
    <dd>
      {{-- รูปอยู่บรรทัดเดียวกับชื่อ และกดขยายได้ตามกฎของโปรเจค --}}
      @include('budget.partials.paper-person', [
        'code' => $invest->proposerCode(),
        'name' => $invest->proposerName(),
        'nameEn' => $invest->proposerNameEn(),
      ])
    </dd>
  </div>
  <div class="paper-row">
    <dt data-i18n="budget.dept">แผนก</dt>
    <dd>{{ $invest->dept_name ?: $invest->dept_code }}</dd>
  </div>

  <div class="paper-row">
    <dt data-i18n="budget.investName">ชื่อเอกสาร</dt>
    <dd class="strong">{{ $invest->title }}</dd>
  </div>
  <div class="paper-row">
    <dt data-i18n="{{ $amount['key'] }}">{{ $amount['th'] }}</dt>
    <dd>
      <span class="paper-amount">
        {{ number_format($amount['value'], 2) }}
        <small data-i18n="budget.baht">บาท</small>
      </span>
    </dd>
  </div>
  @if ($invest->description)
    <div class="paper-row">
      <dt data-i18n="budget.description">รายละเอียด / วัตถุประสงค์</dt>
      <dd class="pre">{{ $invest->description }}</dd>
    </div>
  @endif

  {{-- บรรทัดเฉพาะของหน้าที่เรียกใช้ เช่น หมายเหตุสถานะของตัวงบ --}}
  @if (! empty($tail))
    @include($tail)
  @endif
</dl>

{{-- ── เอกสารแนบ ── --}}
@if ($invest->files->count())
  <p class="paper-sec" data-i18n="budget.files">เอกสารแนบ</p>
  <ul class="file-list">
    @foreach ($invest->files as $file)
      <li class="file-row">
        <img class="file-ico" src="{{ \App\Support\FileIcon::url($file->original_name) }}" alt="">
        <a class="file-name" href="{{ $file->url() }}" target="_blank" rel="noopener">{{ $file->original_name }}</a>
        <span class="file-size">{{ $file->sizeText() }}</span>
      </li>
    @endforeach
  </ul>
@endif

{{-- ── สำเนาเรียน (ผู้อนุมัติตามลำดับ) ── --}}
@include('budget.partials.approver-list', [
  'rows' => $invest->approvals,
  // 🔴 ผลของแต่ละขั้นขึ้นกับสถานะของเอกสารทั้งใบ — เอกสารตีกลับแล้ว คนที่ยังไม่เซ็น = ไม่ได้ดำเนินการ
  'docStatus' => $invest->approval_status,
])

{{-- ── ช่องลงชื่อ — ลายเซ็นประทับลงตรงนี้ตอน "ตัดสิน" ทั้งอนุมัติและไม่อนุมัติ ── --}}
<p class="paper-sec" data-i18n="budget.signatures">ลงชื่อ</p>
@php
  // 🔴 ผู้ขอไม่ต้องลงนาม (เจ้าของสั่ง 2026-09-10) — ช่องลงชื่อมีแต่ผู้อนุมัติ
  $signCount = max($signers->count(), 1);
@endphp
<div class="sign-grid @if ($signCount > 5) is-tight @endif" style="--sign-cols: {{ min($signCount, 5) }}">
  @forelse ($signers as $step)
    @php $isMine = $myStep && $myStep->id === $step->id; @endphp
    <div class="sign-box">
      <span @class(['sign-area', 'is-no' => $step->status === $A::REJECTED])
            @if ($isMine) id="my-sign" @endif>
        @if ($step->signature)
          <img src="{{ $step->signature }}" alt="">
        @elseif ($isMine && ! $mySignature)
          {{--
            🔴 ไม่มีลายเซ็นในบัญชี = อนุมัติไม่ได้ (เซิร์ฟเวอร์ปัดตกอีกชั้น)
               บอกตั้งแต่ในช่องเลยว่าต้องไปทำอะไรก่อน
          --}}
          <span class="soft" data-i18n="budget.signAtInsight">ลงนามลายเซ็นที่ Insight</span>
        @else
          {{-- ว่างไว้ — กดอนุมัติ/ไม่อนุมัติ แล้วระบบประทับให้เอง (เจ้าของสั่ง 2026-09-10) --}}
          <span class="sign-wait">&nbsp;</span>
        @endif
      </span>
      <span class="sign-name" data-loc-th="{{ $step->employee_name ?: $step->employee_code }}" data-loc-en="{{ $step->employee_name_en ?: ($step->employee_name ?: $step->employee_code) }}">{{ $step->employee_name ?: $step->employee_code }}</span>
      <span class="sign-role">
        <span data-i18n="budget.approver">ผู้อนุมัติ</span>{{ $step->position ? ' · '.$step->position : '' }}
      </span>
      {{--
        🔴 วันเวลาที่ลงนาม ต้องมีเวลาด้วยทุกเอกสาร (เจ้าของสั่ง 2026-09-10)
           คนที่ไม่อนุมัติ ขึ้นคำว่า "ไม่อนุมัติ" ต่อด้วยวันเวลา และเป็นสีแดงทั้งบรรทัด
      --}}
      <span @class(['sign-date', 'is-no' => $step->status === $A::REJECTED])>
        @if ($step->status === $A::REJECTED)
          <span data-i18n="budget.rejectedShort">ไม่อนุมัติ</span>
        @endif
        {{ $step->acted_at?->format('d/m/Y H:i') ?: '' }}
      </span>
    </div>
  @empty
    {{--
      ร่างที่ยังไม่ได้เลือกผู้อนุมัติ — ต้องบอกให้เห็น ไม่ใช่ปล่อยกรอบเปล่า
      (ปล่อยว่างแล้วอ่านไม่ออกว่า "ยังไม่เลือก" หรือ "เลือกแล้วแต่วาดไม่ขึ้น")
    --}}
    <div class="sign-box">
      <span class="sign-area"><span class="sign-wait">&nbsp;</span></span>
      <span class="sign-name soft" data-i18n="budget.noApprover">ยังไม่ได้กำหนดผู้อนุมัติ</span>
      <span class="sign-role" data-i18n="budget.approver">ผู้อนุมัติ</span>
    </div>
  @endforelse
</div>
