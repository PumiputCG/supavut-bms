{{--
  โหมด "ดูเอกสาร" ของหน้าของบประมาณ (เจ้าของสั่ง 2026-09-04)

  บันทึกร่างแล้วต้องเห็นเป็น **ตัวเอกสาร** ก่อนเสมอ ไม่ใช่ช่องกรอก
  ยังเป็นร่าง = มีปุ่ม "แก้ไข" สลับไปโหมดกรอก
  ส่งไปแล้ว   = แก้ไม่ได้ เหลือแค่ปุ่มลบ

  🔴 หน้าตาต้องเหมือนหน้า "ดูเอกสาร" (/budget/doc/{id}) — ใช้ชิ้นส่วนกระดาษชุดเดียวกัน
     ต่างกันแค่ตรงนี้ยังไม่มีลายเซ็นผู้อนุมัติ เพราะเอกสารยังไม่ถูกส่ง
--}}
@php
  $labels = \App\Models\Budget\Invest::STATUS_LABELS;
@endphp

<section class="paper" data-doc-view>
  @include('budget.partials.paper-head', [
    'docNo' => $invest->doc_no,
    // 🔴 ร่างยังไม่มีเลขที่ — ออกตอนกดส่ง (เจ้าของสั่ง 2026-09-17)
    'pendingNo' => ! $invest->hasNumber(),
    'status' => ['code' => $invest->approval_status, 'labels' => $labels],
    'trackUrl' => $invest->trackUrl(),
  ])

  <dl class="paper-rows">
    {{--
      กลุ่มเอกสาร — ร่างยังเปลี่ยนได้ จึงต้องบอกให้เห็นว่าจะส่งเข้ากลุ่มไหน (เจ้าของสั่ง 2026-09-17)
      ส่งแล้วโค้ดกลุ่มอยู่ในเลขที่อยู่แล้ว แต่คงแถวนี้ไว้ ชื่อกลุ่มอ่านง่ายกว่าโค้ด
    --}}
    <div class="paper-row">
      <dt data-i18n="budget.docGroup">หมวดงบประมาณ</dt>
      <dd>
        @if ($invest->group)
          <span data-loc-th="{{ $invest->group->name_th }}" data-loc-en="{{ $invest->group->name_en ?: $invest->group->name_th }}">{{ $invest->group->name_th }}</span>
          <span class="soft">({{ $invest->group->code }})</span>
          @if (! $invest->group->active && $invest->isDraft())
            <span class="pill pill-warn" data-i18n="budget.group.goneShort">หมวดนี้ถูกปิดใช้งาน — ต้องเลือกหมวดใหม่</span>
          @endif
        @else
          <span class="soft">—</span>
        @endif
      </dd>
    </div>
    <div class="paper-row">
      <dt data-i18n="budget.year">ปีงบประมาณ</dt>
      <dd>{{ $invest->fiscal_year }}</dd>
    </div>
    {{-- 🔴 เวลาที่กดส่งเรื่อง ไม่ใช่เวลาบันทึกร่าง — ให้ตรงกับ "ดูเส้นทาง" (เจ้าของสั่ง 2026-09-10) --}}
    <div class="paper-row">
      <dt data-i18n="budget.preparedAt">วันที่จัดทำ</dt>
      <dd>{{ $invest->submitted_at?->format('d/m/Y H:i') ?: '—' }}</dd>
    </div>
    <div class="paper-row">
      <dt data-i18n="budget.proposer">ผู้ขอ</dt>
      <dd>
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
      <dt data-i18n="budget.proposedAmount">วงเงินที่เสนอ (บาท)</dt>
      <dd>
        <span class="paper-amount">
          {{ number_format($invest->amount, 2) }}
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
    'docStatus' => $invest->approval_status,
  ])

  {{-- ── ช่องลงชื่อ ── --}}
  <p class="paper-sec" data-i18n="budget.signatures">ลงชื่อ</p>
  @php
    /*
      🔴 อ่านจากสายอนุมัติจริง ไม่ใช่รายชื่อที่ส่งมากับฟอร์ม
         จะได้เห็นลายเซ็นกับวันที่ของคนที่เซ็นไปแล้วด้วย
    */
    $signers = $invest->approvals->where('action', \App\Models\Budget\Approval::APPROVE)->sortBy('step')->values();

    // 🔴 ผู้ขอไม่ต้องลงนาม (เจ้าของสั่ง 2026-09-10) — ช่องลงชื่อมีแต่ผู้อนุมัติ
    $signCount = max($signers->count(), 1);
  @endphp
  <div class="sign-grid @if ($signCount > 5) is-tight @endif" style="--sign-cols: {{ min($signCount, 5) }}">
    {{-- ผู้อนุมัติ — ช่องว่างไว้ให้เซ็นตอนเอกสารไปถึงเขา --}}
    @forelse ($signers as $step)
      <div class="sign-box">
        <span class="sign-area">
          @if ($step->signature)
            <img src="{{ $step->signature }}" alt="">
          @else
            <span class="sign-wait">&nbsp;</span>
          @endif
        </span>
        <span class="sign-name" data-loc-th="{{ $step->employee_name ?: $step->employee_code }}" data-loc-en="{{ $step->employee_name_en ?: ($step->employee_name ?: $step->employee_code) }}">{{ $step->employee_name ?: $step->employee_code }}</span>
        <span class="sign-role">
          <span data-i18n="budget.approver">ผู้อนุมัติ</span>{{ $step->position ? ' · '.$step->position : '' }}
        </span>
        {{-- 🔴 วันเวลาที่ลงนาม ต้องมีเวลาด้วยทุกเอกสาร · ไม่อนุมัติเป็นสีแดง --}}
        <span @class(['sign-date', 'is-no' => $step->status === \App\Models\Budget\Approval::REJECTED])>
          @if ($step->status === \App\Models\Budget\Approval::REJECTED)
            <span data-i18n="budget.rejectedShort">ไม่อนุมัติ</span>
          @endif
          {{ $step->acted_at?->format('d/m/Y H:i') ?: '' }}
        </span>
      </div>
    @empty
      <div class="sign-box">
        <span class="sign-area"><span class="sign-wait">&nbsp;</span></span>
        <span class="sign-name soft" data-i18n="budget.noApprover">ยังไม่ได้กำหนดผู้อนุมัติ</span>
        <span class="sign-role" data-i18n="budget.approver">ผู้อนุมัติ</span>
      </div>
    @endforelse
  </div>
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
