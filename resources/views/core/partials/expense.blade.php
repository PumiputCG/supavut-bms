{{--
  ═══════════ แท็บ "ค่าใช้จ่ายตามวันที่" (เจ้าของสั่ง 2026-09-17 · DECISIONS 50.11) ═══════════

  เงินชุดเดียวกับแท็บ "งบประมาณตามงวดงบ" ทุกบาท — ต่างกันแค่แกนเวลา
    แท็บงวดงบ        แบ่งตามถังงบ (BG26Q1)
    แท็บนี้           แบ่งตามวันที่จ่ายจริง (ไตรมาส · เดือน · วัน)
  ยอดทั้งปีงบจึงเท่ากับ "ใช้ไป" ของแท็บงวดงบ · ส่วนต่างที่เหลือคือถังที่ ERP สะสมยอดไม่ตรงกับรายการของตัวเอง
  🔴 เจ้าของสั่งเอาแถบกระทบยอด (ส่วนต่างกับแท็บงวดงบ + ตารางชุดงบที่ไม่ตรง) ออกทั้งหมด 2026-09-17
     — เป็นเรื่องของ ERP/บัญชี ไม่ใช่สิ่งที่คนดูแดชบอร์ดต้องเห็น **ห้ามเอากลับมาเองโดยไม่ได้สั่ง**

  2 หน้าจอ (view)
    overview  กราฟค่าใช้จ่ายรายแผนก — ตัววาดตัวเดียวกับแท็บงวดงบ (โหมด spend)
    entries   รายการค่าใช้จ่ายของแผนกที่กด เรียงจากวันล่าสุด พร้อมคอลัมน์ "งวดงบที่ตัด"

  🔴 ไม่มีช่อง งบประมาณ / รอตัดจ่าย / คงเหลือ — ERP ไม่มีงบรายเดือนรายวันให้เทียบ (เจ้าของตกลงแล้ว)
  🔴 ไม่มีกราฟวงกลม — แท่งเดียวแบ่งสัดส่วนไม่ได้ ใช้ตารางดูสัดส่วนแทน
  ตัวแปร $L $money $href $firm $paidText $windowText มาจาก core/dashboard
--}}
@php
  $exWindow = $ex['window'] ?? null;
  [$exWinTh, $exWinEn] = $windowText($exWindow);
  $exSum = $ex['total']['amount'] ?? 0;
@endphp

<style>
  /* แท่งยอดติดลบ (คืนของ / ปรับลดบัญชี) — เงินไหลกลับ จึงใช้สีเดียวกับ "คงเหลือ" */
  .bd-chart .c-refund { fill: var(--chart-left); }

  /* ── หน้ารายการของแผนก ── */
  .ex-entries-head { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 14px; }
  .ex-back { display: inline-flex; align-items: center; gap: 4px; color: var(--accent); font-size: var(--fs-sm); font-weight: 600; text-decoration: none; }
  .ex-back:hover { text-decoration: underline; }
  .ex-period { white-space: nowrap; font-variant-numeric: tabular-nums; }
  .ex-ref { white-space: nowrap; font-variant-numeric: tabular-nums; }
  .ex-ref i { display: block; color: var(--muted); font-size: var(--fs-xs); font-style: normal; }
</style>

@if(($ex['ambiguous'] ?? 0) > 0)
  <div class="flash flash-error">{{ number_format($ex['ambiguous']) }} {{ $L('รายการมีงบอ้างอิงหลายแถว จึงยังไม่รวมจนกว่าจะจับคู่ได้แน่นอน', 'entries have ambiguous allocation references and are excluded until they can be assigned reliably.') }}</div>
@endif

@if($filters['view'] === 'entries')
  {{-- ═══ รายการค่าใช้จ่ายของแผนก — เรียงวันล่าสุดก่อน (เจ้าของสั่ง 2026-09-17) ═══ --}}
  <nav class="bd-tabs ex-entries-head" aria-label="รายการค่าใช้จ่าย / Expense entries">
    <a class="ex-back" href="{{ $href(['view' => 'overview', 'dept' => '', 'page' => 1, 'sort' => 'date_desc']) }}">
      <svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true"><path d="M10 2.5 4.5 8l5.5 5.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      {{ $L('ค่าใช้จ่ายทุกแผนก', 'All departments') }}
    </a>
    @if($filters['dept'] !== '')
      <label class="bd-sort">{{ $L('เรียงตาม', 'Sort by') }}<select data-activity-sort>
        @foreach($sort_options as $key => $label)<option value="{{ $href(['sort'=>$key,'page'=>1]) }}" @selected($filters['sort'] === $key) data-loc-th="{{ $label[0] }}" data-loc-en="{{ $label[1] }}">{{ $label[0] }}</option>@endforeach
      </select></label>
    @endif
  </nav>

  @if($filters['dept'] === '')
    <div class="flash">{{ $L('เลือกแผนกก่อนเปิดรายการค่าใช้จ่าย', 'Select a department to open expense entries.') }}</div>
  @elseif($activityError)
    <div class="flash flash-error">{{ $L('อ่านรายการไม่สำเร็จ กรุณาลองใหม่', 'Entries could not be loaded. Please retry.') }} <a class="bd-link" href="{{ $href() }}">{{ $L('ลองอีกครั้ง', 'Retry') }}</a></div>
  @elseif($activity)
    <section class="card">
      @php $clear_columns = $filterPicked ? $href(['page' => 1, 'view' => 'entries']) : null; @endphp
      <div class="card-head">
        <h3>{{ $L('รายการค่าใช้จ่าย · '.$heading_dept_th.' · '.$exWinTh, 'Expense entries · '.$heading_dept_en.' · '.$exWinEn) }}</h3>
        <span class="bd-meta">{{ number_format($activity['count']) }} {{ $L('รายการ', 'entries') }} · {{ $money($activity['total'] ?? 0) }} {{ $L('บาท', 'THB') }}@if($clear_columns) · <a class="bd-link" href="{{ $clear_columns }}">{{ $L('ล้างตัวกรองคอลัมน์', 'Clear column filters') }}</a>@endif</span>
      </div>
      @if(!$activity['count'])
        <div class="bd-empty"><h2>{{ $L('ไม่พบรายการในเงื่อนไขที่เลือก', 'No entries for the selected filters') }}</h2>@if($clear_columns)<a class="bd-link" href="{{ $clear_columns }}">{{ $L('ล้างตัวกรองคอลัมน์', 'Clear column filters') }}</a>@endif</div>
      @else
        <div class="table-scroll">
          {{-- แบ่งหน้าด้วย SQL → กรองที่เซิร์ฟเวอร์ครบทุกหน้า และจำกัดช่วงวันที่จ่ายใน SQL ตั้งแต่ต้น --}}
          <table class="tbl bd-tbl" data-filter-server>
            <thead><tr>
              <th class="col-seq">{{ $L('ลำดับ', 'No.') }}</th>
              <th data-filter-key="date">{{ $L('วันที่จ่าย', 'Paid on') }}</th>
              <th class="txt-left" data-filter-key="title">{{ $L('รายการ', 'Description') }}</th>
              <th data-no-filter>{{ $L('เลขที่ PO / เอกสาร', 'PO / document no.') }}</th>
              <th data-no-filter>{{ $L('งวดงบที่ตัด', 'Budget period charged') }}</th>
              <th class="txt-right" data-filter-key="amount">{{ $L('ค่าใช้จ่าย (บาท)', 'Expense (THB)') }}</th>
              <th data-no-filter>{{ $L('ดูรายละเอียด', 'View details') }}</th>
            </tr></thead>
            <tbody>
              @foreach($activity['rows'] as $entry_index => $entry)
                @php
                  $budgetRef = route('dashboard', array_filter([
                    // รหัสแผนก = บริษัท|แผนก จึงอ่านบริษัทจากแผนกที่เปิดอยู่ได้เสมอ
                    'company' => strtok($filters['dept'], '|'), 'year' => $filters['year'], 'dept' => $filters['dept'],
                    'budget' => $entry['budget_group'], 'view' => 'activity', 'kind' => 'actual',
                  ]));
                @endphp
                <tr>
                  <td class="col-seq">{{ ($activity['page'] - 1) * 40 + $entry_index + 1 }}</td>
                  <td style="white-space:nowrap">{{ $entry['date'] }}</td>
                  <td class="txt-left bd-title">{{ $entry['title'] ?: $L('ไม่ระบุรายละเอียด', 'No description provided') }}
                    <template id="entry-details-{{ $entry_index }}">
                      <dl class="bd-entry-fields">
                        <dt>{{ $L('วันที่จ่าย', 'Paid on') }}</dt><dd>{{ $entry['date'] }}</dd>
                        <dt>{{ $L('ค่าใช้จ่าย', 'Expense') }}</dt><dd>{{ $money($entry['amount']) }} THB</dd>
                        <dt>{{ $L('จำนวน', 'Quantity') }}</dt><dd>{{ number_format($entry['qty'], 2) }}</dd>
                        @foreach([
                          'item'=>['รหัสสินค้า','Item code'],
                          'pr'=>['PR','PR'],
                          'po'=>['PO','PO'],
                          'invoice'=>['ใบแจ้งหนี้','Invoice'],
                          'voucher'=>['ใบสำคัญ','Voucher'],
                          'journal'=>['สมุดรายวัน','Journal'],
                        ] as $field => $label)
                          @if($entry[$field] !== '')
                            <dt>{{ $L($label[0], $label[1]) }}</dt><dd>{{ $entry[$field] }}</dd>
                          @endif
                        @endforeach
                        <dt>{{ $L('Model', 'Model') }}</dt><dd>{{ $entry['budget_model'] ?: '—' }}</dd>
                        {{-- งบอ้างอิงพาไปดูในแท็บงวดงบ — ที่นั่นเห็นงบ/คงเหลือของถังนั้นครบ --}}
                        <dt>{{ $L('งบอ้างอิง', 'Budget reference') }}</dt>
                        <dd><a class="bd-text-link" data-budget-reference href="{{ $budgetRef }}">{{ $entry['budget_no'] }}</a></dd>
                        <dt>{{ $L('งวดงบ', 'Budget period') }}</dt>
                        <dd>{{ $entry['budget_period'] ?: '—' }}</dd>
                      </dl>
                    </template>
                  </td>
                  {{-- 🔴 ไม่มี PO ไม่ได้แปลว่าข้อมูลหาย — ค่าใช้จ่ายที่ตั้งเบิกด้วยใบสำคัญ/สมุดรายวันไม่มีใบสั่งซื้อ (เจ้าของถาม 2026-09-17)
                       จึงบอกเลขเอกสารที่มีจริงแทนขีดเปล่าๆ --}}
                  <td class="ex-ref">
                    @if($entry['po'] !== '')
                      {{ $entry['po'] }}
                    @elseif($entry['voucher'] !== '')
                      <i data-loc-th="ใบสำคัญ" data-loc-en="Voucher">ใบสำคัญ</i> {{ $entry['voucher'] }}
                    @elseif($entry['journal'] !== '')
                      <i data-loc-th="สมุดรายวัน" data-loc-en="Journal">สมุดรายวัน</i> {{ $entry['journal'] }}
                    @else
                      —
                    @endif
                  </td>
                  <td class="ex-period">{{ $entry['budget_period'] ?: '—' }}</td>
                  <td class="num {{ $entry['amount'] < 0 ? 'bd-left' : 'bd-used' }}">{{ $money($entry['amount']) }}</td>
                  <td><button type="button" class="bd-text-link" data-entry-open="entry-details-{{ $entry_index }}" data-entry-title="{{ $entry['title'] }}" aria-haspopup="dialog" aria-controls="entry-detail-modal">{{ $L('ดูรายละเอียด', 'View details') }}</button></td>
                </tr>
              @endforeach
            </tbody>
          </table>
          @include('layouts.partials.table-filter-data', ['filterOptions' => $filterOptions, 'filterPicked' => $filterPicked])
        </div>
      @endif
      @if($activity['pages'] > 1)
        <div class="bd-foot">@include('core.partials.dash-pager', ['page' => $activity['page'], 'pages' => $activity['pages'], 'href' => $href])</div>
      @endif
    </section>
  @endif

@else
  {{-- ═══ กราฟค่าใช้จ่ายรายแผนก (ตัววาดตัวเดียวกับแท็บงวดงบ) ═══ --}}
  <section class="card bd-card" data-dash-card>
    <div class="card-head bd-card-head">
      <div class="bd-card-lead">
        <div class="bd-card-title">
          <b data-loc-th="{{ $firm['th'] }}" data-loc-en="{{ $firm['en'] }}">{{ $firm['th'] }}</b>
          <span>{{ $L('ค่าใช้จ่ายแต่ละแผนก · '.$exWinTh, 'Expenses by department · '.$exWinEn) }}</span>
        </div>
        {{-- เรียงตามค่าใช้จ่าย — ค่าเริ่มต้นมากไปน้อยทุกครั้งที่โหลดหน้า --}}
        <div class="bd-kinds" role="group" aria-label="เรียงลำดับ" data-i18n-aria="sort.by">
          <button type="button" class="bd-kbtn is-on" data-sort="desc" aria-pressed="true"
                  title="ค่าใช้จ่ายมากไปน้อย" aria-label="ค่าใช้จ่ายมากไปน้อย"
                  data-loc-title-th="ค่าใช้จ่ายมากไปน้อย" data-loc-title-en="Highest expense first">
            <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><rect x="2" y="2.5" width="9" height="2.2" rx="1"></rect><rect x="2" y="6.9" width="6" height="2.2" rx="1"></rect><rect x="2" y="11.3" width="3" height="2.2" rx="1"></rect><path d="M13 2.5v8.2h1.8L12.5 14l-2.3-3.3H12V2.5h1Z"></path></svg>
          </button>
          <button type="button" class="bd-kbtn" data-sort="asc" aria-pressed="false"
                  title="ค่าใช้จ่ายน้อยไปมาก" aria-label="ค่าใช้จ่ายน้อยไปมาก"
                  data-loc-title-th="ค่าใช้จ่ายน้อยไปมาก" data-loc-title-en="Lowest expense first">
            <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><rect x="2" y="2.5" width="3" height="2.2" rx="1"></rect><rect x="2" y="6.9" width="6" height="2.2" rx="1"></rect><rect x="2" y="11.3" width="9" height="2.2" rx="1"></rect><path d="M13 14V5.8h1.8L12.5 2.5 10.2 5.8H12V14h1Z"></path></svg>
          </button>
          {{-- เปรียบเทียบค่าใช้จ่าย DoD / MoM / QoQ / YoY --}}
          @include('core.partials.compare')
        </div>
      </div>
      <div class="bd-legend">
        <span><i class="bd-dot used"></i>{{ $L('ค่าใช้จ่าย', 'Expenses') }}</span>
        <span><i class="bd-dot left"></i>{{ $L('คืน / ปรับลด', 'Refunds / adjustments') }}</span>
      </div>
      <div class="bd-kinds" role="group" aria-label="ชนิดกราฟ" data-i18n-aria="chart.type">
        <button type="button" class="bd-kbtn" data-kind="table" title="ตาราง" aria-label="ตาราง" data-i18n-title="chart.table" data-i18n-aria="chart.table">
          <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M2 2.5h12a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1Zm0 3.2v2.4h3.5V5.7H2Zm4.7 0v2.4H14V5.7H6.7ZM2 9.3v3.2h3.5V9.3H2Zm4.7 0v3.2H14V9.3H6.7Z"></path></svg>
        </button>
        <span class="bd-kinds-sep" aria-hidden="true"></span>
        <button type="button" class="bd-kbtn" data-kind="bar-v" title="แท่งแนวตั้ง" aria-label="แท่งแนวตั้ง" data-i18n-title="chart.barV" data-i18n-aria="chart.barV">
          <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><rect x="1.5" y="7" width="3" height="7.5" rx="1"></rect><rect x="6.5" y="3.5" width="3" height="11" rx="1"></rect><rect x="11.5" y="9.5" width="3" height="5" rx="1"></rect></svg>
        </button>
        <button type="button" class="bd-kbtn" data-kind="bar-h" title="แท่งแนวนอน" aria-label="แท่งแนวนอน" data-i18n-title="chart.barH" data-i18n-aria="chart.barH">
          <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><rect x="1.5" y="2.5" width="11" height="3" rx="1"></rect><rect x="1.5" y="6.5" width="7" height="3" rx="1"></rect><rect x="1.5" y="10.5" width="13" height="3" rx="1"></rect></svg>
        </button>
        <span class="bd-kinds-sep" aria-hidden="true"></span>
        <button type="button" class="bd-kbtn" data-chart-zoom="out" aria-label="ซูมออก / Zoom out">−</button>
        <button type="button" class="bd-kbtn" data-chart-zoom="reset" aria-label="คืนขนาดกราฟ / Reset chart zoom">100%</button>
        <button type="button" class="bd-kbtn" data-chart-zoom="in" aria-label="ซูมเข้า / Zoom in">+</button>
        <button type="button" class="bd-kbtn" data-dash-full title="เต็มจอ" aria-label="เต็มจอ" aria-pressed="false" data-i18n-title="chart.fullscreen" data-i18n-aria="chart.fullscreen">
          <svg data-icon-open viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M2 6V2h4v1.6H3.6V6H2Zm8-4h4v4h-1.6V3.6H10V2ZM3.6 10v2.4H6V14H2v-4h1.6Zm8.8 0H14v4h-4v-1.6h2.4V10Z"></path></svg>
          <svg data-icon-close hidden viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M4.4 2H6v4H2V4.4h2.4V2Zm5.6 0h1.6v2.4H14V6h-4V2ZM2 10h4v4H4.4v-2.4H2V10Zm8 0h4v1.6h-2.4V14H10v-4Z"></path></svg>
        </button>
      </div>
    </div>
    <div class="card-body">
      @if(!$chart['items'])
        <div class="bd-empty"><h2>{{ $L('ไม่มีค่าใช้จ่ายในช่วงที่เลือก', 'No expenses in the selected period') }}</h2><p>{{ $L('ลองเลือกเดือน ไตรมาส หรือแผนกอื่น', 'Try another month, quarter or department.') }}</p></div>
      @else
        <div class="bd-chart" data-dash-chart role="img" aria-label="กราฟค่าใช้จ่ายรายแผนก / Expense chart by department"></div>
        <div class="bd-table-panel" data-dash-table hidden>
          <table class="tbl bd-tbl" data-filterable>
            <thead><tr>
              <th class="col-seq" data-no-filter>{{ $L('ลำดับ', 'No.') }}</th>
              <th class="txt-left">{{ $L('แผนก', 'Department') }}</th>
              <th>{{ $L('รายการ', 'Entries') }}</th>
              <th class="txt-right">{{ $L('ค่าใช้จ่าย (บาท)', 'Expense (THB)') }}</th>
              <th>{{ $L('สัดส่วน', 'Share') }}</th>
            </tr></thead>
            <tbody>
              @foreach($chart['items'] as $i => $item)
                @php $amt = $ex['depts'][$item['dept_key']]['amount'] ?? 0; @endphp
                <tr data-dept-row data-budget="{{ $amt }}">
                  <td class="col-seq">{{ $i + 1 }}</td>
                  <td class="txt-left"><a href="{{ $item['href'] }}" data-loc-th="{{ $item['name_th'] }}" data-loc-en="{{ $item['name_en'] }}">{{ $item['name_th'] }}</a></td>
                  <td>{{ number_format($item['count']) }}</td>
                  <td class="num {{ $amt < 0 ? 'bd-left' : 'bd-used' }}">{{ $money($amt) }}</td>
                  <td>{{ $exSum > 0 ? number_format($amt / $exSum * 100, 1).'%' : '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <p class="bd-meta bd-hint" data-dash-hint>{{ $L('หมุนล้อเมาส์เพื่อซูม · ลากเพื่อเลื่อน · ชี้เพื่อดูยอด · คลิกเพื่อดูรายการค่าใช้จ่ายของแผนก', 'Scroll to zoom · drag to pan · hover for figures · click for department expense entries') }}</p>
      @endif
    </div>
  </section>
  @if($chart['items'])
    <script type="application/json" id="dash-chart-data">@json($chart)</script>
  @endif
@endif
