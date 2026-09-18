{{--
  หน้าติดตามเอกสาร — ปลายทางของ QR Code บนกระดาษ (เจ้าของสั่ง 2026-09-18 · DECISIONS 50.15)

  🔴 ออกแบบให้ "มือถือก่อน" เพราะคนใช้หน้านี้คือคนที่ยกโทรศัพท์ขึ้นสแกนกระดาษ
     ไม่มีเมนูซ้าย · ไม่มีแถบเครื่องมือ · ไม่มีตารางกว้าง · เส้นทางเรียงลงมาเป็นแนวตั้ง

  🔴 2 ชั้น (เจ้าของเคาะ)
       ไม่ล็อกอิน  เห็นเส้นทาง ใครเซ็นแล้ว รอใคร เมื่อไหร่
       ล็อกอินแล้ว เห็นยอดเงินและรูปลายเซ็นเพิ่ม (เท่าที่มีสิทธิ์เปิดเอกสารใบนั้น)

  ตัวแปรที่ส่งมาจาก TrackController: $invest · $budget · $full · $me · $steps · $now
--}}
@php
  // ตัวช่วยเขียนข้อความ 2 ภาษา (รูปแบบเดียวกับหน้าอื่นในระบบ)
  $L = fn ($th, $en) => new \Illuminate\Support\HtmlString('<span data-loc-th="'.e($th).'" data-loc-en="'.e($en).'">'.e($th).'</span>');

  $labels = \App\Models\Budget\Invest::STATUS_LABELS;
  $acks = array_values(array_filter($steps, fn ($s) => ($s['group'] ?? '') === 'ack'));
  $flow = array_values(array_filter($steps, fn ($s) => ($s['group'] ?? '') !== 'ack'));
@endphp

{{-- 🔴 ชื่อแท็บใช้เลขเดียวกับที่หน้าโชว์ (BGT เมื่อเซ็นครบ) ไม่งั้นแท็บกับหน้าอ้างอิงคนละเลข --}}
<title>{{ $budget?->doc_no ?: $invest->doc_no }} · ติดตามเอกสาร</title>

@include('layouts.theme')

<style>
  /*
    ── หน้าติดตาม (มือถือก่อน) ──────────────────────────────────
    🔴 ทุกค่าสี/ขนาด/ระยะ อ่านจาก token ของธีมกลางเท่านั้น (กฎโปรเจค)
    🔴 ความกว้างสูงสุด 560px แล้วจัดกลางจอ — เปิดบนคอมก็ยังอ่านเป็นคอลัมน์เดียวเหมือนบนมือถือ
       ไม่ทำผัง 2 คอลัมน์สำหรับจอใหญ่ เพราะหน้านี้มีงานเดียวคือ "ไล่อ่านลำดับจากบนลงล่าง"
  */
  body { background: var(--bg); }

  .tk-wrap { max-width: 560px; margin: 0 auto; padding: 12px 12px 36px; display: grid; gap: 12px; }

  /*
    ── แถบบน: พื้นน้ำเงินเหมือนแถบบนของเว็บ (เจ้าของสั่ง 2026-09-18) ──
    🔴 ใช้ชุดสีเดียวกับ .topbar ของ layouts/app (ไล่เฉด navy-900 -> navy-700) ไม่ได้คิดสีใหม่
    🔴 เต็มความกว้างจอ แต่ของข้างในกว้างเท่าการ์ด (560px) จึงตรงแนวกับเนื้อหาด้านล่าง
  */
  .tk-bar {
    background: linear-gradient(90deg, var(--navy-900) 0%, var(--navy-700) 100%);
    color: var(--on-dark);
  }
  .tk-bar-in {
    max-width: 560px; margin: 0 auto; padding: 9px 12px;
    display: flex; align-items: center; gap: 10px;
  }
  .tk-brand { display: grid; line-height: 1.15; }
  .tk-brand b { font-size: var(--fs-md); letter-spacing: .02em; }
  .tk-brand span { color: rgba(255, 255, 255, .72); font-size: var(--fs-xs); }
  .tk-bar-in .spacer { flex: 1; }

  .tk-card {
    background: var(--surface); border: 1px solid var(--line);
    border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
    padding: 14px;
  }

  /*
    ── หัวเรื่อง: เลขที่เอกสาร "บรรทัดเดียว" (เจ้าของสั่ง 2026-09-18) ──
    🔴 โชว์เลขเดียว — ยังเซ็นไม่ครบใช้เลขที่ INV · ครบแล้วใช้เลขที่ BGT
       เดิมโชว์ 2 บรรทัด (INV ตัวใหญ่ + BGT ข้างล่าง) ซึ่งอ่านแล้วไม่รู้ว่าต้องดูเลขไหน
  */
  .tk-no { margin: 0; display: grid; gap: 2px; }
  .tk-no .tk-no-key { color: var(--muted); font-size: var(--fs-xs); }
  .tk-no b { color: var(--navy-900); font-size: var(--fs-lg); letter-spacing: .01em; }

  /*
    ── บรรทัด "ตอนนี้อยู่ที่ใคร" ──
    🔴 ใจความของหน้านี้ จึงตัวใหญ่สุดและอยู่เหนือรายละเอียดทั้งหมด
       คนสแกนต้องได้คำตอบนี้ก่อน ไม่ต้องไล่อ่านตารางเอง
  */
  .tk-now {
    margin-top: 12px; padding: 11px 12px;
    border-radius: var(--radius-sm); background: var(--surface-2);
    display: grid; gap: 3px;
  }
  .tk-now .tk-now-key { color: var(--muted); font-size: var(--fs-xs); }
  .tk-now .tk-now-val { font-size: var(--fs-md); font-weight: 700; line-height: 1.45; }

  /* ── ข้อมูลเอกสาร: คู่หัวข้อ-ค่า เรียงลงมา ── */
  .tk-rows { display: grid; gap: 9px; margin: 0; }
  .tk-row { display: grid; grid-template-columns: 92px minmax(0, 1fr); gap: 10px; align-items: baseline; }
  .tk-row > dt { color: var(--muted); font-size: var(--fs-sm); }
  .tk-row > dd { margin: 0; color: var(--ink); font-size: var(--fs-sm); font-weight: 600; word-break: break-word; }
  .tk-row .money { color: var(--ok); font-weight: 700; font-variant-numeric: tabular-nums; }

  /* ── เส้นทางเอกสาร (แนวตั้ง) ── */
  .tk-sec {
    margin: 0 0 10px; color: var(--muted);
    font-size: var(--fs-xs); font-weight: 700; letter-spacing: .03em;
  }

  .tk-steps { display: grid; gap: 0; }

  .tk-step { display: grid; grid-template-columns: 26px minmax(0, 1fr); gap: 10px; }

  /* เส้นต่อระหว่างขั้น — วาดที่คอลัมน์ซ้ายของแต่ละขั้น ไม่ใช้ element ลอยๆ */
  .tk-dot { position: relative; display: grid; justify-items: center; }
  .tk-dot i {
    width: 22px; height: 22px; border-radius: 50%;
    display: grid; place-items: center;
    background: var(--surface-3); color: var(--ink-soft);
    font-size: 11px; font-weight: 700; font-style: normal;
  }
  .tk-dot::after {
    content: ''; position: absolute; top: 24px; bottom: -4px; width: 2px;
    background: var(--line);
  }
  .tk-step:last-child .tk-dot::after { display: none; }

  /* สีของวงกลมบอกผลของขั้นนั้นทันที ไม่ต้องอ่านข้อความ */
  .tk-step.is-approved .tk-dot i { background: var(--ok); color: #fff; }
  .tk-step.is-rejected .tk-dot i { background: var(--danger); color: #fff; }
  .tk-step.is-pending .tk-dot i { background: var(--warn); color: #3a2a00; }

  .tk-body { padding-bottom: 16px; min-width: 0; }
  .tk-role { color: var(--muted); font-size: var(--fs-xs); }
  .tk-name { color: var(--navy-900); font-size: var(--fs-sm); font-weight: 700; word-break: break-word; }
  .tk-meta { color: var(--ink-soft); font-size: var(--fs-xs); line-height: 1.5; word-break: break-word; }
  .tk-when { color: var(--muted); font-size: var(--fs-xs); font-variant-numeric: tabular-nums; }
  .tk-state { font-size: var(--fs-xs); font-weight: 700; }

  /* หมายเหตุ/ข้อทักท้วงของผู้ลงนาม — ต้องเห็น เพราะเป็นเหตุผลที่เอกสารเดินต่อหรือไม่เดิน */
  .tk-note {
    margin-top: 5px; padding: 7px 9px;
    border-left: 3px solid var(--line); border-radius: var(--radius-sm);
    background: var(--surface-2); color: var(--ink-soft);
    font-size: var(--fs-xs); line-height: 1.5; word-break: break-word;
  }

  /* รูปลายเซ็น — เฉพาะคนที่ล็อกอินและมีสิทธิ์เปิดเอกสารใบนี้ */
  .tk-sign { margin-top: 6px; }
  .tk-sign img { max-width: 150px; max-height: 44px; object-fit: contain; display: block; }

  /*
    ── การ์ดท้ายหน้า: มีแค่ปุ่มเดียว (เจ้าของสั่ง 2026-09-18) ──
    🔴 เอาแถบ "ยอดเงินและลายเซ็น…" กับบรรทัด "หน้านี้อ่านข้อมูล…" ออกทั้งคู่
       เจ้าของบอกว่าการ์ดนี้ควรมีแค่ทางไปต่อ ไม่ใช่ที่อธิบายระบบ
  */
  .tk-foot { display: grid; }
  .tk-foot .btn { width: 100%; justify-content: center; }

  /* จอใหญ่ขึ้นเล็กน้อย ให้หายใจได้มากกว่านี้หน่อย */
  @media (min-width: 480px) {
    .tk-wrap { padding: 16px 16px 40px; gap: 14px; }
    .tk-card { padding: 16px; }
    .tk-row { grid-template-columns: 104px minmax(0, 1fr); }
  }
</style>

{{-- แถบบนพื้นน้ำเงินเต็มความกว้าง — อยู่นอกกรอบเนื้อหา (เจ้าของสั่ง 2026-09-18) --}}
<header class="tk-bar">
  <div class="tk-bar-in">
    <span class="tk-brand">
      <b>SBMS</b>
      <span>{{ $L('ติดตามเอกสาร', 'Document tracking') }}</span>
    </span>
    <span class="spacer"></span>
    {{-- ตัวสลับภาษาบนพื้นเข้มต้องกลับด้านสี ไม่งั้นจมหายไปกับพื้น (กติกาเดิมของระบบ) --}}
    @include('layouts.partials.lang-switch', ['class' => 'on-dark'])
  </div>
</header>

<div class="tk-wrap">

  {{-- ── เลขที่เอกสาร · ตอนนี้อยู่ที่ใคร ── --}}
  <div class="tk-card">
    {{--
      🔴 โชว์เลขเดียว (เจ้าของสั่ง 2026-09-18)
         ยังเซ็นไม่ครบ = ยังไม่เกิดใบงบ จึงใช้เลขที่ INV · เซ็นครบแล้วใช้เลขที่ BGT
         ไม่โชว์ทั้ง 2 เลขพร้อมกัน เพราะคนสแกนจะไม่รู้ว่าต้องอ้างอิงเลขไหน
    --}}
    <p class="tk-no">
      <span class="tk-no-key">{{ $L('เลขที่งบประมาณ', 'Budget document no.') }}</span>
      <b>{{ $budget?->doc_no ?: $invest->doc_no }}</b>
    </p>

    <div class="tk-now">
      <span class="tk-now-key">{{ $L('สถานะปัจจุบัน', 'Current status') }}</span>
      <span class="tk-now-val {{ $now['cls'] }}"
            data-loc-th="{{ $now['th'] }}" data-loc-en="{{ $now['en'] }}">{{ $now['th'] }}</span>
    </div>
  </div>

  {{-- ── รายละเอียดเอกสาร ── --}}
  <div class="tk-card">
    <p class="tk-sec">{{ $L('รายละเอียดเอกสาร', 'Document details') }}</p>

    <dl class="tk-rows">
      <div class="tk-row">
        <dt>{{ $L('ชื่อรายการ', 'Title') }}</dt>
        <dd>{{ $invest->title }}</dd>
      </div>
      <div class="tk-row">
        <dt>{{ $L('แผนกที่ขอ', 'Department') }}</dt>
        <dd>{{ $invest->dept_name }}</dd>
      </div>
      <div class="tk-row">
        <dt>{{ $L('ปีงบประมาณ', 'Fiscal year') }}</dt>
        <dd>{{ $invest->fiscal_year }}</dd>
      </div>
      <div class="tk-row">
        <dt>{{ $L('ผู้ขอ', 'Requester') }}</dt>
        <dd data-loc-th="{{ $invest->proposer_name }}"
            data-loc-en="{{ $invest->proposer_name_en ?: $invest->proposer_name }}">{{ $invest->proposer_name }}</dd>
      </div>
      {{--
        🔴 "วันที่จัดทำ" ยึดเวลาที่กดส่งเรื่อง (submitted_at) ให้ตรงกับตัวเอกสาร
           กติกาเดิมของระบบ (2026-09-10): วันเวลาในเอกสารคือ "เวลาที่ลงมือทำ" ไม่ใช่เวลาบันทึกร่าง
      --}}
      <div class="tk-row">
        <dt>{{ $L('วันที่จัดทำ', 'Prepared on') }}</dt>
        <dd>{{ $invest->submitted_at?->format('d/m/Y H:i') ?: '—' }}</dd>
      </div>

      {{-- 🔴 ยอดเงินเฉพาะคนที่ล็อกอินและมีสิทธิ์เปิดเอกสารใบนี้ (เจ้าของเคาะ 2026-09-18) --}}
      @if ($full)
        <div class="tk-row">
          <dt>{{ $budget ? $L('วงเงินที่อนุมัติ', 'Approved amount') : $L('วงเงินที่เสนอ', 'Requested amount') }}</dt>
          <dd><span class="money">{{ number_format((float) ($budget?->approved_amount ?? $invest->amount), 2) }}</span>
            {{ $L('บาท', 'THB') }}</dd>
        </div>
      @endif
    </dl>
  </div>

  {{-- ── เส้นทางเอกสาร ── --}}
  <div class="tk-card">
    <p class="tk-sec">{{ $L('เส้นทางเอกสาร', 'Document route') }}</p>

    <div class="tk-steps">
      @foreach ($flow as $i => $step)
        @php
          $mark = match ($step['state']['cls']) {
            'st-approved' => 'is-approved',
            'st-rejected' => 'is-rejected',
            'st-pending' => 'is-pending',
            default => '',
          };
        @endphp
        <div class="tk-step {{ $mark }}">
          <span class="tk-dot"><i>{{ $i + 1 }}</i></span>

          <div class="tk-body">
            <p class="tk-role">{{ $L($step['role']['th'], $step['role']['en']) }}</p>
            <p class="tk-name" data-loc-th="{{ $step['name']['th'] }}"
               data-loc-en="{{ $step['name']['en'] }}">{{ $step['name']['th'] }}</p>

            @if ($step['position'] !== '' || $step['dept'] !== '')
              <p class="tk-meta">{{ trim($step['position'].($step['position'] !== '' && $step['dept'] !== '' ? ' · ' : '').$step['dept']) }}</p>
            @endif

            <p>
              <span class="tk-state {{ $step['state']['cls'] }}"
                    data-loc-th="{{ $step['state']['th'] }}"
                    data-loc-en="{{ $step['state']['en'] }}">{{ $step['state']['th'] }}</span>
              @if ($step['at'])
                <span class="tk-when">· {{ $step['at'] }}</span>
              @endif
            </p>

            @if (trim((string) $step['note']) !== '')
              <p class="tk-note">{{ $step['note'] }}</p>
            @endif

            @if (! empty($step['signature']))
              <span class="tk-sign"><img src="{{ $step['signature'] }}" alt=""></span>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- ── ผู้รับทราบ (สำเนาเรียน) — ไม่ได้อยู่ในคิว จึงแยกก้อน ── --}}
  @if ($acks !== [])
    <div class="tk-card">
      <p class="tk-sec">{{ $L('สำเนาเรียน (เพื่อทราบ)', 'For information') }}</p>

      <div class="tk-steps">
        @foreach ($acks as $step)
          <div class="tk-step">
            <span class="tk-dot"><i>·</i></span>
            <div class="tk-body">
              <p class="tk-name" data-loc-th="{{ $step['name']['th'] }}"
                 data-loc-en="{{ $step['name']['en'] }}">{{ $step['name']['th'] }}</p>
              @if ($step['position'] !== '' || $step['dept'] !== '')
                <p class="tk-meta">{{ trim($step['position'].($step['position'] !== '' && $step['dept'] !== '' ? ' · ' : '').$step['dept']) }}</p>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  {{-- ── ปลายทาง: เปิดเอกสารเต็ม ── --}}
  {{-- 🔴 การ์ดนี้มีแค่ปุ่มเดียว (เจ้าของสั่ง 2026-09-18) --}}
  <div class="tk-card tk-foot">
    @if ($full)
      <a class="btn btn-primary" href="{{ route('budget.doc.show', $invest) }}">{{ $L('เปิดเอกสารเต็ม', 'Open full document') }}</a>
    @else
      {{-- ยังไม่ล็อกอิน: พาไปหน้ากรอกล็อกอินของเว็บตามที่เจ้าของสั่ง --}}
      <a class="btn btn-primary" href="{{ route('login') }}">{{ $L('เข้าสู่หน้าเว็บ', 'Go to the website') }}</a>
    @endif
  </div>
</div>

@include('budget.partials.assets')
@include('layouts.i18n')
