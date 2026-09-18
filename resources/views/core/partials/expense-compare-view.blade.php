{{--
  หน้าผลเปรียบเทียบของแท็บ "ค่าใช้จ่ายตามวันที่" (เจ้าของสั่ง 2026-09-17)

  โครงเดียวกับ core/partials/compare-view (เจ้าของสั่ง "ทำคล้ายกัน แต่เทียบตามวันจ่าย")
    การ์ดละฝั่ง = ขอบเขตที่เลือก + ตาราง (รายการ · จำนวน · ผลต่าง) · % ต่อท้ายตัวเลขในช่องเดียวกัน
  แต่ละฝั่งเลือก บริษัท · ปีงบ · แผนก · ช่วงวันที่จ่าย (ไตรมาส / เดือน / วัน)

  ชนิดการเทียบ (คิดที่ DashboardController::expenseCompareMode จากขอบเขตจริง)
    วัน + วัน = Day on Day (DoD) · เดือน + เดือน = MoM · ไตรมาส + ไตรมาส = QoQ · ไม่เลือกช่วง = YoY

  🔴 แถวที่เทียบมีแค่ ค่าใช้จ่าย · จำนวนรายการ · เฉลี่ยต่อรายการ
     ไม่มีงบประมาณ/คงเหลือ — ERP ไม่มีงบรายเดือนรายวัน (เจ้าของตกลงแล้ว)
  🔴 ค่าใช้จ่าย **และค่าเฉลี่ยต่อรายการ** มากกว่า = แดง · น้อยกว่า = เขียว (ภาษาสีเดียวกับ "ใช้ไป" ของแท็บงวดงบ)
  🔴 **จำนวนรายการเงียบทั้งแถว** — ไม่เขียน % และผลต่างเป็นตัวอักษรสีดำ (เจ้าของสั่ง 2026-09-17)
     จำนวนใบมากขึ้นไม่ได้แปลว่าแย่ลง · แต่ตัวเลขยังต้องตรงตำแหน่งกับแถวอื่น จึงวางช่องว่างแทน %
--}}
@php
  $ecMoney = fn (int $v) => number_format($v / 100, 2);
  $ecRange = function (array $scope) use ($paidText) {
    $w = \App\Services\Erp\ErpDashboard::paid_window($scope);
    if ($w === null) {
      return ['ทั้งปีงบ', 'whole budget year'];
    }
    [$th, $en] = $paidText($w['level'], $w['key']);

    return match ($w['level']) {
      'day' => ['วันที่ '.$th, $en],
      'month' => ['เดือน '.$th, $en],
      default => ['ไตรมาส '.$th, $en],
    };
  };

  $ecScopeRows = function (array $scope) use ($companyOptions, $compareOptions, $L, $ecRange) {
    $firm = collect($companyOptions)->firstWhere('id', $scope['company'] ?? '');
    $year = (string) ($scope['year'] ?? '');
    $dept = $scope['dept'] ?? '';
    [$rTh, $rEn] = $ecRange($scope);

    return [
      [$L('บริษัท', 'Company'), $firm ? $L($firm['th'], $firm['en']) : $L('ทุกบริษัท', 'All companies')],
      [$L('ปีงบ', 'Budget year'), ctype_digit($year) ? $year : $L('ยังไม่ได้เลือก', 'Not selected')],
      [$L('แผนก', 'Department'), $dept === '' ? $L('ทุกแผนก', 'All departments') : ($compareOptions['depts'][$dept] ?? $dept)],
      [$L('ช่วงที่จ่าย', 'Paid in'), $L($rTh, $rEn)],
    ];
  };

  // ชื่อสั้นสำหรับประโยคสรุป — ใส่แผนกเฉพาะตอน 2 ฝั่งต่างกัน (กติกาเดียวกับหน้าเปรียบเทียบงบ)
  $ecShort = function (array $scope, array $other) use ($compareOptions, $ecRange) {
    [$rTh, $rEn] = $ecRange($scope);
    $th = [$rTh.' ปีงบ '.($scope['year'] ?? '')];
    $en = [$rEn.' of budget year '.($scope['year'] ?? '')];
    if (($scope['dept'] ?? '') !== ($other['dept'] ?? '')) {
      $dept = $scope['dept'] ?? '';
      $th[] = $dept === '' ? 'ทุกแผนก' : 'แผนก '.($compareOptions['depts'][$dept] ?? $dept);
      $en[] = $dept === '' ? 'all departments' : ($compareOptions['depts'][$dept] ?? $dept);
    }

    return [implode(' ', $th), implode(' ', $en)];
  };

  $ecFields = [
    'amount' => ['th' => 'ค่าใช้จ่าย', 'en' => 'Expenses', 'unit' => 'money', 'good' => 'down'],
    'n' => ['th' => 'จำนวนรายการ', 'en' => 'Entries', 'unit' => 'count', 'quiet' => true],
    'avg' => ['th' => 'เฉลี่ยต่อรายการ', 'en' => 'Average per entry', 'unit' => 'money', 'good' => 'down'],
  ];
  $ecShow = fn (string $unit, int $v) => $unit === 'money' ? $ecMoney($v) : number_format($v);

  $ecPanels = [
    ['side' => 'a', 'scope' => $compare['left'], 'th' => 'ชุดข้อมูลปัจจุบัน', 'en' => 'Current dataset'],
    ['side' => 'b', 'scope' => $compare['right'], 'th' => 'ชุดข้อมูลเปรียบเทียบ', 'en' => 'Comparison dataset'],
  ];

  // ศัพท์บัญชีที่ใช้ทับศัพท์ทั้ง 2 ภาษา จึงไม่ต้องแปล (กติกาเดียวกับหน้าเปรียบเทียบงบ)
  $ecKind = match ($compare['mode'] ?? null) {
    'dod' => 'Day on Day (DoD)',
    'mom' => 'Month on Month (MoM)',
    'qoq' => 'Quarter on Quarter (QoQ)',
    'yoy' => 'Year-on-Year (YoY)',
    default => '',
  };

  [$meTh, $meEn] = $ecShort($compare['left'], $compare['right']);
  [$itTh, $itEn] = $ecShort($compare['right'], $compare['left']);
  $ecPct = $compare['fields']['amount']['side']['a']['pct'];
  $ecDir = $ecPct === null ? 'flat' : (round($ecPct, 2) > 0 ? 'up' : (round($ecPct, 2) < 0 ? 'down' : 'flat'));
  [$ecWordTh, $ecWordEn] = match ($ecDir) {
    'up' => ['มากกว่า', 'higher than'],
    'down' => ['น้อยกว่า', 'lower than'],
    default => ['เท่ากับ', 'the same as'],
  };
  $ecTone = match ($ecDir) { 'up' => 'bad', 'down' => 'good', default => '' };
  // 🔴 ยอดของอีกฝั่งต้องมีสีด้วย และเป็นสีกลับด้านกันเสมอ (เจ้าของแจ้ง 2026-09-17 ว่าลืมสี)
  $ecToneOther = match ($ecTone) { 'good' => 'bad', 'bad' => 'good', default => '' };
@endphp

@include('core.partials.compare-style')

<header class="card cv-head">
  <a class="cv-back" href="{{ $href() }}" title="ย้อนกลับ" aria-label="ย้อนกลับ"
     data-i18n-title="common.back" data-i18n-aria="common.back">
    <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true">
      <path d="M10 2.5 4.5 8l5.5 5.5" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round"></path>
    </svg>
  </a>

  <div class="cv-title">
    <h1>{{ $L('รายงานเปรียบเทียบค่าใช้จ่าย', 'Expense comparison report') }}@if ($ecKind !== '')<span class="cv-kind">: {{ $ecKind }}</span>@endif</h1>

    {{-- สรุปบรรทัดเดียว มองจากฝั่ง "ชุดข้อมูลปัจจุบัน" เป็นตัวตั้ง (กติกาเดียวกับหน้าเปรียบเทียบงบ) --}}
    <p class="cv-usage">
      <b class="cv-usage-head">{{ $L('สรุปค่าใช้จ่าย:', 'Expense summary:') }}</b>
      {{ $L($meTh.' เท่ากับ', $meEn.' is') }}
      <b class="cv-usage-rate {{ $ecTone }}">{{ $ecMoney($compare['a']['amount']) }}</b>
      <b class="cv-usage-word">{{ $L($ecWordTh, $ecWordEn) }}</b>
      {{ $L($itTh.' ที่เท่ากับ', $itEn.' at') }}
      <b class="cv-usage-rate {{ $ecToneOther }}">{{ $ecMoney($compare['b']['amount']) }}</b>
      @if ($ecPct !== null && $ecDir !== 'flat')
        {{ $L('คิดเป็น', 'a difference of') }}
        <span class="cv-dir {{ $ecTone }}">{{ $ecDir === 'up' ? '▲ +' : '▼ ' }}{{ number_format(abs($ecPct), 2) }}%</span>
      @endif
    </p>
  </div>

  @include('core.partials.compare')
</header>

{{--
  ช่วงที่ยังไม่จบ (เจ้าของสั่งทำ 2026-09-18 · เสนอไว้ที่ DECISIONS 50.9.7)
  🔴 ไม่ได้แก้ตัวเลขให้ เพราะการ "เทียบช่วงเท่ากัน" เป็นการเปลี่ยนขอบเขตที่ผู้ใช้เลือกมาเอง
     หน้าที่ของแถบนี้คือบอกให้รู้ว่าส่วนต่างที่เห็นมีเวลาที่ยังไม่ถึงปนอยู่
--}}
@if (! empty($compare['partial']))
  <p class="cv-empty cv-partial">{{ $L($compare['partial']['th'], $compare['partial']['en']) }}</p>
@endif
@if ($compare['a']['missing_year'] || $compare['b']['missing_year'])
  <p class="cv-empty">{{ $L('ต้องเลือกปีงบทั้ง 2 ฝั่ง — แท็บค่าใช้จ่ายอ่านทีละปีงบ', 'Pick a budget year on both sides — the expense tab reads one budget year at a time') }}</p>
@elseif ($compare['a']['n'] === 0 || $compare['b']['n'] === 0)
  <p class="cv-empty">{{ $L('มีชุดข้อมูลที่ไม่มีรายการเลย — ตัวเลขที่ลดลงจึงไม่ได้แปลว่าจ่ายน้อยลง แต่แปลว่าช่วงนั้นไม่มีการจ่ายจากถังงบที่เลือก',
                          'One dataset has no entries — the drop means nothing was paid from the selected budgets in that period, not that spending fell') }}</p>
@endif

<div class="cv-pair">
  @foreach ($ecPanels as $panel)
    <section class="card cv-panel is-{{ $panel['side'] }}">
      <div class="card-head"><b>{{ $L($panel['th'], $panel['en']) }}</b></div>

      <div class="card-body">
        <p class="cv-sub">{{ $L('ขอบเขตข้อมูล', 'Data scope') }}</p>
        <dl class="cv-rows">
          @foreach ($ecScopeRows($panel['scope']) as [$label, $value])
            <div class="cv-row"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
          @endforeach
        </dl>

        <p class="cv-sub">{{ $L('ยอดรวม', 'Totals') }}</p>
        <div class="tbl-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th class="txt-left">{{ $L('รายการ', 'Item') }}</th>
                <th class="txt-right">{{ $L('จำนวน', 'Value') }}</th>
                <th class="txt-right">{{ $L('ผลต่าง', 'Variance') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($ecFields as $key => $field)
                @php
                  $v = $compare[$panel['side']][$key];
                  $d = $compare['fields'][$key]['side'][$panel['side']];
                  $pct = $d['pct'];
                  $dir = $pct === null ? 'flat' : (round($pct, 2) > 0 ? 'up' : (round($pct, 2) < 0 ? 'down' : 'flat'));
                  $quiet = ! empty($field['quiet']);
                  $tone = $quiet ? 'plain' : ($dir === 'flat' ? 'flat' : ($dir === $field['good'] ? 'good' : 'bad'));
                @endphp
                <tr>
                  <td class="txt-left">{{ $L($field['th'], $field['en']) }}@if ($field['unit'] === 'money') <span class="soft">{{ $L('(บาท)', '(THB)') }}</span>@endif</td>
                  <td class="txt-right">
                    <span class="cv-cell">
                      <span class="cv-amt">{{ $ecShow($field['unit'], $v) }}</span>
                      @if ($pct !== null && $dir !== 'flat' && ! $quiet)
                        <span class="cv-pct {{ $tone }}">{{ $dir === 'up' ? '▲ +' : '▼ ' }}{{ number_format($pct, 2) }}%</span>
                      @else
                        <span class="cv-pct-slot" aria-hidden="true"></span>
                      @endif
                    </span>
                  </td>
                  <td class="txt-right">
                    <span class="cv-pct {{ $tone }}">{{ $d['diff'] >= 0 ? '+' : '−' }}{{ $ecShow($field['unit'], abs($d['diff'])) }}</span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <p class="cv-count">{{ $L('นับตามวันที่จ่ายจริง ของถังงบในปีงบที่เลือก', 'Counted by actual payment date, from budgets of the selected budget year') }}</p>
      </div>
    </section>
  @endforeach
</div>
