{{--
  หน้าผลการเปรียบเทียบ — กินทั้งหน้าแทนเนื้อหาปกติ (เจ้าของสั่ง 2026-09-16)

  🔴 ทำไมกินทั้งหน้า ไม่ใช่การ์ดต่อท้าย
     ของเดิมผลเทียบเป็นการ์ดที่ถูก include อยู่ "ในแถวปุ่มเล็กๆ บนหัวการ์ด"
     ซึ่งผิดโครงสร้าง และผู้ใช้ต้องเลื่อนหาว่าผลอยู่ไหน
     ตอนนี้เข้าโหมดเปรียบเทียบ = เห็นแต่ผลเทียบ และมีปุ่ม "<" ย้อนกลับมุมซ้ายบน

  ผังที่เจ้าของกำหนด (สั่งแก้ 3 รอบจนลงตัว)
     1. ไม่มีกราฟ — "แสดงแค่ตารางผลต่างดีกว่า"
     2. **เหลือการ์ดเดียวต่อฝั่ง อ่านจบในตัวเอง** ไม่มีการ์ดสรุปผลต่างแยกข้างล่าง
        การ์ด = ขอบเขตที่เลือก + ตาราง (รายการ · จำนวนเงิน · ผลต่าง)
     3. 🔴 **% เขียนต่อท้ายจำนวนเงินในช่องเดียวกัน** ไม่ใช่คอลัมน์แยก
        เช่น `12,688,508.17 ▲ +26.8%` · ฝั่งที่น้อยกว่าเป็นสีแดง `▼ −21.2%`

  🔴 ผลต่างคิด "2 ทิศทาง" — การ์ดแต่ละฝั่งบอกว่า **ตัวเองต่างจากอีกฝั่งเท่าไร**
     ฝั่งซ้ายใช้ฝั่งขวาเป็นฐาน · ฝั่งขวาใช้ฝั่งซ้ายเป็นฐาน
     (คิดที่ `DashboardController::compare()` ไม่ได้คิดในหน้า)

  🔴 ขอบเขตเขียนเป็นคู่ "หัวข้อ: ค่า" ลำดับ บริษัท → ปี → แผนก → รอบ (เจ้าของกำหนดเอง)
     และ **ช่องที่ไม่ได้เลือกต้องเขียนว่า "ทั้งหมด" ให้เห็น ไม่ใช่เว้นว่าง**
     ไม่งั้นอ่านไม่ออกว่าฝั่งนั้นกว้างแค่ไหน ซึ่งเป็นที่มาของการเทียบผิดมาตลอด
--}}
@php
  $cmpScopeRows = function (array $scope) use ($companyOptions, $compareOptions, $L) {
    $firm = collect($companyOptions)->firstWhere('id', $scope['company'] ?? '');
    $year = $scope['year'] ?? '';
    $quarter = $scope['quarter'] ?? '';
    $dept = $scope['dept'] ?? '';

    return [
      [$L('บริษัท', 'Company'),
       $firm ? $L($firm['th'], $firm['en']) : $L('ทุกบริษัท', 'All companies')],

      [$L('ปี', 'Year'), match (true) {
        $year === '' || $year === 'all' => $L('ทุกปี', 'All years'),
        $year === 'unassigned' => $L('ยังไม่จัดปี', 'Unclassified year'),
        default => $year,
      }],

      [$L('แผนก', 'Department'),
       $dept === '' ? $L('ทุกแผนก', 'All departments') : ($compareOptions['depts'][$dept] ?? $dept)],

      // 🔴 รอบ = งวดงบ ใช้รหัสถังจริง เช่น BG26Q1 (เจ้าของสั่ง 2026-09-17) · ทุกปี = BGxxQ1
      [$L('รอบ', 'Period'), match (true) {
        $quarter === '' || $quarter === 'all' => $L('ทุกงวดงบ', 'All budget periods'),
        $quarter === 'unassigned' => $L('ไม่ระบุงวดงบ', 'No budget period'),
        default => 'BG'.(ctype_digit($year) ? substr($year, 2, 2) : 'xx').'Q'.$quarter,
      }],
    ];
  };

  /*
    🔴🔴 สีของแต่ละแถว "ไม่เหมือนกัน" — ขึ้นกับความหมายของตัวเลขนั้น (เจ้าของแจ้ง 2026-09-17)

       ของเดิมใช้กฎเหมาเดียวทั้งหน้า "เพิ่ม = เขียว · ลด = แดง" ซึ่ง **อ่านผิดความหมาย 2 แถว**
         ใช้ไปลดลง   ขึ้นแดง  ทั้งที่จ่ายเงินน้อยลง = ดี
         รอตัดจ่ายเพิ่ม ขึ้นเขียว ทั้งที่เงินถูกล็อกไว้กับ PO มากขึ้น = ต้องระวัง

       กฎที่ถูกคือคิดจาก **"เงินเหลือมากขึ้นหรือน้อยลง"**
         งบประมาณ ↑  มีงบมากขึ้น        -> เขียว   (good = up)
         ใช้ไป ↑      จ่ายออกไปมากขึ้น   -> แดง    (good = down)
         รอตัดจ่าย ↑  ผูกเงินไว้มากขึ้น   -> แดง    (good = down)
         คงเหลือ ↑    เหลือเงินมากขึ้น    -> เขียว   (good = up)

       🔴 แดชบอร์ดเข้ารหัสสีนี้ไว้ถูกอยู่แล้ว (`.bd-used` แดง · `.bd-reserve` เหลือง · `.bd-left` เขียว)
          หน้านี้เคยไม่ทำตาม — อย่ากลับไปใช้กฎเหมาเดียวอีก
       🔴 ลูกศรยังบอก "ทิศทางจริง" เสมอ (▲ เพิ่ม · ▼ ลด) เปลี่ยนแค่สี

       🔴🔴 'quiet' = แถวนี้ **ไม่เขียน % และไม่ระบายสี** (เจ้าของสั่ง 2026-09-17)
          ใช้กับ "รอตัดจ่าย" เพราะฐานของมันเล็กและแกว่งมาก % จึงพุ่งเป็นพันเปอร์เซ็นต์
          (เคยขึ้น ▲ +4,452.32% จากเงินแค่ 105,217.20 บาท) ตัวเลขแบบนั้นไม่ได้บอกอะไร
          และช่องนี้ยังมี **ERP Error ยอดติดลบ** ที่บัญชีรับว่าจะเคลียร์ (ข้อ 50.2)
          จึงเหลือ "ยอด" กับ "ผลต่างเป็นบาท" สีดำ ให้คนอ่านตัดสินเอง
  */
  $cmpFields = [
    'budget' => ['th' => 'งบประมาณ', 'en' => 'Budget', 'good' => 'up'],
    'actual' => ['th' => 'ใช้ไป', 'en' => 'Actual', 'good' => 'down'],
    'reserve' => ['th' => 'รอตัดจ่าย', 'en' => 'Committed', 'good' => 'down', 'quiet' => true],
    'available' => ['th' => 'คงเหลือ', 'en' => 'Available', 'good' => 'up'],
  ];

  $cmpPanels = [
    ['side' => 'a', 'scope' => $compare['left'], 'th' => 'ชุดข้อมูลปัจจุบัน', 'en' => 'Current dataset'],
    ['side' => 'b', 'scope' => $compare['right'], 'th' => 'ชุดข้อมูลเปรียบเทียบ', 'en' => 'Comparison dataset'],
  ];

  /*
    ชื่อขอบเขตแบบสั้นสำหรับเขียนเป็นประโยค เช่น "ปี 2025 รอบ Q1"

    🔴 ใส่บริษัท/แผนกเฉพาะตอนที่ 2 ฝั่ง "ต่างกัน" เท่านั้น
       ถ้าเหมือนกันแล้วใส่ซ้ำทั้ง 2 ฝั่ง ประโยคจะยาวโดยไม่ได้บอกอะไรเพิ่ม
       แต่ถ้าต่างกันแล้วไม่ใส่ ประโยคจะกำกวมทันที (เช่นเทียบ HR กับ IT ในปีเดียวกัน)
  */
  $cmpShort = function (array $scope, array $other) use ($companyOptions, $compareOptions) {
    $th = [];
    $en = [];

    if (($scope['company'] ?? '') !== ($other['company'] ?? '')) {
      $firm = collect($companyOptions)->firstWhere('id', $scope['company'] ?? '');
      $th[] = $firm ? $firm['th'] : 'ทุกบริษัท';
      $en[] = $firm ? $firm['en'] : 'all companies';
    }

    $year = $scope['year'] ?? '';
    [$yTh, $yEn] = match (true) {
      $year === '' || $year === 'all' => ['ทุกปี', 'all years'],
      $year === 'unassigned' => ['ปีที่ยังไม่จัด', 'unclassified year'],
      default => ['ปี '.$year, 'year '.$year],
    };
    $th[] = $yTh;
    $en[] = $yEn;

    if (($scope['dept'] ?? '') !== ($other['dept'] ?? '')) {
      $dept = $scope['dept'] ?? '';
      $th[] = $dept === '' ? 'ทุกแผนก' : 'แผนก '.($compareOptions['depts'][$dept] ?? $dept);
      $en[] = $dept === '' ? 'all departments' : 'dept '.($compareOptions['depts'][$dept] ?? $dept);
    }

    $q = $scope['quarter'] ?? '';
    if ($q !== '' && $q !== 'all') {
      $bg = 'BG'.(ctype_digit((string) $year) ? substr((string) $year, 2, 2) : 'xx').'Q'.$q;
      $th[] = $q === 'unassigned' ? 'รอบที่ไม่ระบุงวดงบ' : 'รอบ '.$bg;
      $en[] = $q === 'unassigned' ? 'no budget period' : $bg;
    }

    return [implode(' ', $th), implode(' ', $en)];
  };
@endphp

@include('core.partials.compare-style')

<header class="card cv-head">
  {{-- 🔴 ย้อนกลับไปหน้าที่เปิดอยู่ก่อนกดเทียบ — $href() ไม่มี cmp/cmpa ติดไปด้วยอยู่แล้ว --}}
  <a class="cv-back" href="{{ $href() }}" title="ย้อนกลับ" aria-label="ย้อนกลับ"
     data-i18n-title="common.back" data-i18n-aria="common.back">
    <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true">
      <path d="M10 2.5 4.5 8l5.5 5.5" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round"></path>
    </svg>
  </a>

  @php
    /*
      🔴 ชื่อรายงานบอกชนิดการเทียบเต็มๆ ในบรรทัดเดียวกัน (เจ้าของสั่ง 2026-09-16)
         เลือกแค่ปี -> Year-on-Year (YoY) · เลือกไตรมาสด้วย -> Quarter on Quarter (QoQ)
         ชนิดมาจาก DashboardController::compareMode() ซึ่งดูจากขอบเขตจริงของ 2 ชุด
         ไม่ได้รับธงจาก URL — ป้ายที่บอกผิดแย่กว่าไม่มีป้าย

      🔴 ไม่ต้องแปล — เป็นศัพท์บัญชีที่ใช้ทับศัพท์เหมือนกันทั้ง 2 ภาษา
    */
    $cvKind = match ($compare['mode'] ?? null) {
      'yoy' => 'Year-on-Year (YoY)',
      'qoq' => 'Quarter on Quarter (QoQ)',
      default => '',
    };
  @endphp

  <div class="cv-title">
    {{-- 🔴 ชนิดการเทียบไม่ต้องตัวหนา (เจ้าของสั่ง) — ตัวหนาไว้ที่ชื่อรายงานอย่างเดียว --}}
    <h1>{{ $L('รายงานเปรียบเทียบงบประมาณ', 'Budget comparison report') }}@if ($cvKind !== '')<span class="cv-kind">: {{ $cvKind }}</span>@endif</h1>

    {{--
      ── สรุปการใช้งบ — บรรทัดเดียวใต้ชื่อรายงาน ที่เดียวทั้งหน้า (เจ้าของสั่ง 2026-09-17) ──

      🔴 มองจากฝั่ง "ชุดข้อมูลปัจจุบัน" เป็นตัวตั้งเสมอ
         ของเดิมใส่ไว้ในการ์ดทั้ง 2 ฝั่ง ซึ่งพูดเรื่องเดียวกันสลับข้างกัน = อ่านซ้ำเปล่าๆ

      🔴 อัตราการใช้งบไม่ขึ้นกับขนาดของงบ จึงเป็นตัวเดียวในหน้านี้ที่เทียบ 2 ฝั่งได้ตรงๆ
         (ยอดบาทเทียบกันแล้ว % ออกมาไม่เท่ากันทั้ง 2 ทาง สรุปไม่ได้ว่าใครดีกว่า)
    --}}
    @php
      $ua = $compare['usage']['a'];
      $ub = $compare['usage']['b'];

      [$meTh, $meEn] = $cmpShort($compare['left'], $compare['right']);
      [$itTh, $itEn] = $cmpShort($compare['right'], $compare['left']);

      $meTxt = $ua['rate'] === null ? '—' : number_format($ua['rate'], 2).'%';
      $itTxt = $ub['rate'] === null ? '—' : number_format($ub['rate'], 2).'%';

      // ตัดสินคำจากผลต่างที่ปัดแล้ว ให้ตรงกับตัวเลขที่ผู้ใช้เห็นบนจอ
      $uDir = $ua['gap'] === null ? 'flat'
        : (round($ua['gap'], 2) > 0 ? 'up' : (round($ua['gap'], 2) < 0 ? 'down' : 'flat'));

      [$wordTh, $wordEn] = match ($uDir) {
        'up' => ['มากกว่า', 'higher than'],
        'down' => ['น้อยกว่า', 'lower than'],
        default => ['เท่ากับ', 'the same as'],
      };

      /*
        🔴 สีกลับด้านกับแถวยอดเงิน — ใช้น้อยกว่า = เขียว · ใช้มากกว่า = แดง
           (ระบบนี้คือ budget control · ดูเหตุผลเต็มที่บล็อก CSS ของ .cv-dir)
      */
      $uDirClass = match ($uDir) { 'down' => 'good', 'up' => 'bad', default => '' };

      // เกิน 100% = ทะลุกรอบงบ แดงเสมอไม่ว่าจะน้อยกว่าอีกฝั่งหรือไม่
      $meOver = $ua['rate'] !== null && $ua['rate'] > 100;
      $itOver = $ub['rate'] !== null && $ub['rate'] > 100;

      /*
        สีของอัตราแต่ละฝั่ง — ฝั่งที่ใช้งบน้อยกว่า = เขียว · มากกว่า = แดง
        🔴 เกิน 100% แดงเสมอ เพราะ "แย่น้อยกว่าอีกฝั่ง" ไม่ได้แปลว่าไม่แย่
      */
      $cls = function (string $base, string $side, bool $over) use ($uDir) {
        $tone = $over ? 'bad' : match ($uDir) {
          'down' => $side === 'me' ? 'good' : 'bad',
          'up' => $side === 'me' ? 'bad' : 'good',
          default => '',
        };

        return $base.($tone !== '' ? ' '.$tone : '').($over ? ' is-over' : '');
      };
    @endphp
    <p class="cv-usage">
      <b class="cv-usage-head">{{ $L('สรุปการใช้งบ:', 'Budget utilisation:') }}</b>
      {{ $L('อัตราการใช้งบ '.$meTh.' เท่ากับ', 'utilisation for '.$meEn.' is') }}
      <b class="{{ $cls('cv-usage-rate', 'me', $meOver) }}">{{ $meTxt }}</b>
      <b class="cv-usage-word">{{ $L($wordTh, $wordEn) }}</b>
      {{ $L($itTh.' ที่เท่ากับ', $itEn.' at') }}
      <b class="{{ $cls('cv-usage-rate', 'it', $itOver) }}">{{ $itTxt }}</b>
      @if ($ua['gap'] !== null && $uDir !== 'flat')
        {{--
          🔴 บรรทัดนี้ "ระบายสี" แล้ว — กลับด้านกับแถวยอดเงิน (เจ้าของสั่งเปลี่ยน 2026-09-17)
             ใช้งบน้อยกว่า = เขียว · ใช้งบมากกว่า = แดง · เกิน 100% = แดงเสมอ
             เหตุผลเต็มอยู่ที่คำอธิบายของ .cv-dir ด้านบน
        --}}
        {{ $L('คิดเป็น', 'a difference of') }}
        <span class="cv-dir {{ $uDirClass }}">{{ $uDir === 'up' ? '▲ +' : '▼ ' }}{{ number_format(abs($ua['gap']), 2) }}%</span>
      @endif
    </p>
  </div>

  {{-- เปลี่ยนขอบเขตได้จากในโหมดนี้เลย ไม่ต้องย้อนกลับไปก่อน (ปุ่มมี margin-left:auto ในตัว) --}}
  @include('core.partials.compare')
</header>

{{--
  🔴 ต้องบอกตรงๆ เมื่อมีฝั่งที่ไม่มีข้อมูล
     ไม่งั้นทุกช่องขึ้น "−100%" ซึ่งอ่านว่า "ยอดหายไปหมด"
     ทั้งที่ความจริงคือ **ไม่เคยตั้งงบก้อนนั้นในช่วงเวลานั้น** — คนละเรื่องกันเลย
--}}
{{--
  ช่วงที่ยังไม่จบ (เจ้าของสั่งทำ 2026-09-18 · เสนอไว้ที่ DECISIONS 50.9.7)
  🔴 ไม่ได้แก้ตัวเลขให้ เพราะการ "เทียบช่วงเท่ากัน" เป็นการเปลี่ยนขอบเขตที่ผู้ใช้เลือกมาเอง
     หน้าที่ของแถบนี้คือบอกให้รู้ว่าส่วนต่างที่เห็นมีเวลาที่ยังไม่ถึงปนอยู่
--}}
@if (! empty($compare['partial']))
  <p class="cv-empty cv-partial">{{ $L($compare['partial']['th'], $compare['partial']['en']) }}</p>
@endif
@if ($compare['a']['count'] === 0 || $compare['b']['count'] === 0)
  <p class="cv-empty">
    {{ $L('มีชุดข้อมูลที่ไม่มีรายการเลย — ตัวเลขที่ลดลงจึงไม่ได้แปลว่ายอดหายไป แต่แปลว่าช่วงเวลานั้นไม่มีงบก้อนนี้',
          'One dataset has no records — the drop does not mean the amount fell, it means nothing was budgeted in that period') }}
  </p>
@endif

<div class="cv-pair">
  @foreach ($cmpPanels as $panel)
    <section class="card cv-panel is-{{ $panel['side'] }}">
      <div class="card-head">
        <b>{{ $L($panel['th'], $panel['en']) }}</b>
      </div>

      <div class="card-body">
        <p class="cv-sub">{{ $L('ขอบเขตข้อมูล', 'Data scope') }}</p>
        <dl class="cv-rows">
          @foreach ($cmpScopeRows($panel['scope']) as [$label, $value])
            <div class="cv-row">
              <dt>{{ $label }}</dt>
              <dd>{{ $value }}</dd>
            </div>
          @endforeach
        </dl>

        {{--
          🔴 ตารางสรุปอยู่ในการ์ดของแต่ละฝั่ง ไม่มีการ์ด "สรุปผลต่าง" แยกข้างล่างแล้ว
             (เจ้าของสั่ง 2026-09-16) — แต่ละการ์ดจึงอ่านจบในตัวเอง
             เปอร์เซ็นต์เขียนต่อท้ายจำนวนเงินในช่องเดียวกัน · ผลต่างเป็นคอลัมน์ทางขวา
        --}}
        <p class="cv-sub">{{ $L('ยอดรวม', 'Totals') }}</p>
        <div class="tbl-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th class="txt-left">{{ $L('รายการ', 'Item') }}</th>
                <th class="txt-right">{{ $L('จำนวนเงิน (บาท)', 'Amount (THB)') }}</th>
                <th class="txt-right">{{ $L('ผลต่าง (บาท)', 'Variance (THB)') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($cmpFields as $key => $label)
                @php
                  $v = $compare[$panel['side']][$key];
                  $d = $compare['fields'][$key]['side'][$panel['side']];
                  $pct = $d['pct'];
                  // 🔴 ฐาน 0 คิด % ไม่ได้ · ต่างกันน้อยกว่า 0.05% ถือว่าเท่าเดิม ไม่ระบายสี
                  $dir = $pct === null ? 'flat' : (round($pct, 2) > 0 ? 'up' : (round($pct, 2) < 0 ? 'down' : 'flat'));
                  // 🔴 สีคิดจาก "ทิศทางที่ดีของแถวนั้น" ไม่ใช่กฎเหมาเดียว (ดูคำอธิบายที่ $cmpFields)
                  $tone = $dir === 'flat' ? 'flat' : ($dir === $label['good'] ? 'good' : 'bad');
                @endphp
                <tr>
                  <td class="txt-left">{{ $L($label['th'], $label['en']) }}</td>
                  <td class="txt-right">
                    <span class="cv-cell">
                      <span class="cv-amt">{{ number_format($v / 100, 2) }}</span>
                      @if ($pct !== null && $tone !== 'flat' && empty($label['quiet']))
                        <span class="cv-pct {{ $tone }}">{{ $dir === 'up' ? '▲ +' : '▼ ' }}{{ number_format($pct, 2) }}%</span>
                      @else
                        {{-- 🔴 จองที่ของช่อง % ไว้ ไม่งั้นยอดของแถวนี้จะไม่ตรงกับแถวอื่น --}}
                        <span class="cv-pct-slot" aria-hidden="true"></span>
                      @endif
                    </span>
                  </td>
                  <td class="txt-right">
                    <span class="cv-pct {{ empty($label['quiet']) ? $tone : 'plain' }}">{{ $d['diff'] >= 0 ? '+' : '−' }}{{ number_format(abs($d['diff']) / 100, 2) }}</span>
                  </td>
                </tr>
              @endforeach

              {{--
                🔴 แถว "สรุป" = อัตราการใช้งบของฝั่งนี้ (ใช้ไป ÷ ตั้งงบ) — เจ้าของสั่ง 2026-09-17
                   อยู่ในตารางเดียวกับยอดเงินตามที่สั่ง แต่ตีเส้นคั่นหนาให้เห็นว่าเป็นค่าอีกชนิด
                   เพราะช่องกลางของแถวนี้เป็น % ไม่ใช่บาท จึงไม่ได้อยู่ในหน่วยเดียวกับแถวข้างบน
              --}}
              @php
                $u = $compare['usage'][$panel['side']];
                $uRowDir = $u['gap'] === null ? 'flat'
                  : (round($u['gap'], 2) > 0 ? 'up' : (round($u['gap'], 2) < 0 ? 'down' : 'flat'));

                /*
                  🔴 สีใช้กฎเดียวกับบรรทัดสรุปใต้ชื่อรายงาน (เจ้าของสั่ง 2026-09-17)
                     ใช้งบน้อยกว่าอีกฝั่ง = เขียว · มากกว่า = แดง · เกิน 100% = แดงเสมอ
                */
                $uRowOver = $u['rate'] !== null && $u['rate'] > 100;
                $uRowTone = $uRowOver ? 'bad' : match ($uRowDir) {
                  'down' => 'good',
                  'up' => 'bad',
                  default => '',
                };
              @endphp
              <tr class="cv-sum-row">
                {{-- 🔴 บอกให้ชัดว่าสรุป "อะไร" เพราะค่าในแถวนี้เป็น % ไม่ใช่บาทเหมือนแถวข้างบน --}}
                <td class="txt-left">{{ $L('สรุปอัตราการใช้งบ', 'Utilisation summary') }}</td>
                <td class="txt-right">
                  @if ($u['rate'] === null)
                    <span class="soft">—</span>
                  @else
                    <span class="cv-sum-rate {{ $uRowTone }}">{{ number_format($u['rate'], 2) }}%</span>
                  @endif
                </td>
                <td class="txt-right">
                  @if ($u['gap'] !== null && $uRowDir !== 'flat')
                    <span class="cv-dir {{ $uRowTone }}">{{ $uRowDir === 'up' ? '▲ +' : '▼ ' }}{{ number_format(abs($u['gap']), 2) }}%</span>
                  @else
                    <span class="soft">—</span>
                  @endif
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <p class="cv-count">
          {{ $L('จำนวนรายการงบประมาณ', 'Budget records') }}
          {{ number_format($compare[$panel['side']]['count']) }}
        </p>
      </div>
    </section>
  @endforeach
</div>

