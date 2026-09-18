{{--
  ปุ่ม + หน้าต่างเลือกขอบเขตที่จะเปรียบเทียบ (เจ้าของสั่งรื้อใหม่ 2026-09-16)

  🔴 ของเดิม: ฝั่งซ้ายล็อกเป็นตัวกรองของหน้า แก้ไม่ได้ · เลือกได้แค่ฝั่งขวา
     ของใหม่: **เลือกได้ทั้ง 2 ฝั่ง** ฝั่งซ้ายตั้งต้นเป็นสิ่งที่กำลังเปิดอยู่ แต่เปลี่ยนได้

  🔴 ฝั่งซ้ายส่งไปทาง ?cmpa[...] · ฝั่งขวา ?cmp[...]
     ลิงก์เก่าที่มีแต่ ?cmp[...] ยังใช้ได้ — ฝั่งซ้ายจะถอยไปใช้ตัวกรองของหน้าเหมือนเดิม

  🔴 ทั้ง 2 ฝั่งมีช่อง on=1 ติดไปด้วยเสมอ
     เพราะ "เลือกทุกช่องเป็นทั้งหมด" กับ "ไม่ได้ส่งมาเลย" ทำให้ค่าว่างเหมือนกัน
     แต่ความหมายคนละเรื่อง — ไม่มีธงนี้ ผู้ใช้จะเลือกดูภาพรวมทั้งหมดไม่ได้เลย

  ผลการเปรียบเทียบไม่ได้อยู่ในไฟล์นี้ — อยู่ที่ core/partials/compare-view
  เพราะมันกินทั้งหน้า ส่วนไฟล์นี้ถูก include อยู่ในแถวปุ่มเล็กๆ บนหัวการ์ด
--}}
@php
  /*
    🔴 แท็บค่าใช้จ่าย (เจ้าของสั่ง 2026-09-17) เลือก "ช่วงวันที่จ่าย" แทนงวดงบ
       ไตรมาสที่จ่าย · เดือน · วัน → เทียบได้ทั้ง QoQ · MoM · DoD
       และไม่มีตัวเลือก "ทุกปี" เหตุผลเดียวกับตัวกรองของแท็บ (เกินเวลารอของ ERP)
  */
  $cmpExpense = ($filters['tab'] ?? '') === 'expense';
  $cmpDims = $cmpExpense
    ? ['company', 'year', 'dept', 'paid_q', 'paid_m', 'paid_d']
    : ['company', 'year', 'quarter', 'dept'];
  // ป้ายงวดงบของปีที่เลือกในฝั่งนั้น เช่น BG26Q1 · ทุกปี = BGxxQ1 (สคริปต์ท้ายไฟล์เปลี่ยนให้ตอนสลับปี)
  $cmpPeriod = fn (int $q, string $year) => 'BG'.(ctype_digit($year) ? substr($year, 2, 2) : 'xx').'Q'.$q;

  // ชื่อเดือนของช่อง "เดือนที่จ่าย" (ไทย/อังกฤษ) — ประกาศที่นี่ ไม่พึ่งตัวแปรของหน้าแม่
  $cmpMonths = [['ม.ค.', 'Jan'], ['ก.พ.', 'Feb'], ['มี.ค.', 'Mar'], ['เม.ย.', 'Apr'], ['พ.ค.', 'May'], ['มิ.ย.', 'Jun'],
    ['ก.ค.', 'Jul'], ['ส.ค.', 'Aug'], ['ก.ย.', 'Sep'], ['ต.ค.', 'Oct'], ['พ.ย.', 'Nov'], ['ธ.ค.', 'Dec']];

  /*
    ก้อนข้อมูลที่ส่งให้สคริปต์ไปเติมช่วงวันที่จ่าย
    🔴 ประกอบไว้ในนี้แล้วส่งตัวแปรเดียวให้ @json — เขียน @json([...]) หลายบรรทัด Blade อ่านวงเล็บไม่ครบ
       (เจอมาแล้วเมื่อ 2026-09-17 ทั้งระบบขึ้น 500)
  */
  $cmpCalendar = [
    'url' => route('dashboard.expense-periods'),
    'months' => array_map(fn ($m) => ['th' => $m[0], 'en' => $m[1]], $cmpMonths),
  ];

  // ค่าตั้งต้นของแต่ละฝั่ง — ซ้าย = สิ่งที่กำลังเปิดอยู่ · ขวา = ที่ผู้ใช้เคยเลือกไว้
  $cmpSides = [
    ['key' => 'cmpa', 'th' => 'ชุดข้อมูลปัจจุบัน', 'en' => 'Current dataset',
     'now' => $compare['left'] ?? array_intersect_key($filters, array_flip($cmpDims))],
    /*
      🔴 แท็บค่าใช้จ่าย: ฝั่งขวาตั้งต้นที่ "ปีเดียวกับที่ดูอยู่"
         เพราะช่องปีไม่มีตัวเลือก "ทุกปี" เบราว์เซอร์จะเลือกตัวแรกในรายการให้เอง (ปีล่าสุด)
         = เปิดหน้าต่างมาก็เป็นการเทียบข้ามปีโดยที่ผู้ใช้ไม่ได้สั่ง
    */
    ['key' => 'cmp', 'th' => 'ชุดข้อมูลเปรียบเทียบ', 'en' => 'Comparison dataset',
     'now' => $compare['right'] ?? ($cmpExpense ? ['year' => $filters['year'] ?? ''] : [])],
  ];
@endphp

@once
  <script type="application/json" data-cmp-calendar>@json($cmpCalendar)</script>
  <style>
    .cmp-open { margin-left: auto; }

    /* ── หน้าต่างเลือกขอบเขต — 2 ฝั่งเท่ากัน ── */
    .cmp-modal { width: min(760px, 100%); }
    .cmp-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }

    .cmp-side { display: grid; gap: 10px; align-content: start; padding: 4px 16px 4px 0; }
    /* เส้นคั่นกลาง บอกว่าเป็นคนละฝั่งกัน โดยไม่ต้องตีกรอบให้รก */
    .cmp-side + .cmp-side { padding: 4px 0 4px 16px; border-left: 1px solid var(--line-soft); }

    .cmp-side > h4 { margin: 0; color: var(--navy-900); font-size: var(--fs-sm); font-weight: 700; }

    .cmp-side label { display: grid; gap: 4px; }
    .cmp-side label > span { color: var(--muted); font-size: var(--fs-xs); font-weight: 600; }

    /*
      🔴 ทุกช่องในหน้าต่างนี้ต้องสูงเท่ากัน ไม่ว่าจะเป็น dropdown หรือช่องเดือน/วัน
         .sel ของระบบอยู่ในชิ้นส่วนของโมดูลงบ (budget/partials/assets) ซึ่งหน้าแดชบอร์ดไม่ได้ include
         ส่วน .input เป็นช่องกรอกขนาดหน้าล็อกอิน (สูง 52px) เอามาปนกับ dropdown แล้วแถวเบี้ยว
    */
    .cmp-side .sel, .cmp-side input[type="month"], .cmp-side input[type="date"] {
      width: 100%; height: 40px; padding: 0 10px;
      border: 1px solid var(--line); border-radius: var(--radius);
      background: var(--surface); color: var(--ink); font: inherit; font-size: var(--fs-sm);
    }
    .cmp-side .sel:focus, .cmp-side input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
    .cmp-hint { margin: 0; color: var(--muted); font-size: var(--fs-xs); line-height: 1.6; }
    /* กำลังถามเซิร์ฟเวอร์ว่าฝั่งนี้มีวันไหนให้เลือกบ้าง */
    .cmp-side.is-loading select[data-cmp-q],
    .cmp-side.is-loading select[data-cmp-m],
    .cmp-side.is-loading select[data-cmp-d] { opacity: .55; cursor: progress; }

    @media (max-width: 620px) {
      .cmp-grid { grid-template-columns: minmax(0, 1fr); }
      .cmp-side { padding: 4px 0; }
      .cmp-side + .cmp-side { padding: 14px 0 4px; border-left: 0; border-top: 1px solid var(--line-soft); }
    }
  </style>
@endonce

{{-- ปุ่มเปิดหน้าต่าง — สัญลักษณ์แท่งกราฟ 2 แท่ง --}}
<button type="button" class="bd-kbtn cmp-open" data-modal-open="cmp-pick"
        title="เปรียบเทียบ" aria-label="เปรียบเทียบ"
        data-i18n-title="dash.compare" data-i18n-aria="dash.compare">
  <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
    <rect x="2" y="6" width="4" height="8" rx="1"></rect>
    <rect x="10" y="2" width="4" height="12" rx="1"></rect>
  </svg>
</button>

{{-- 🔴 คลาสต้องเป็น .modal-wrap เท่านั้น ตัวปิด (พื้นหลัง · Esc · ปุ่มปิด) ค้นหาด้วยคลาสนี้ --}}
<div class="modal-wrap" data-modal="cmp-pick" hidden role="dialog" aria-modal="true" aria-label="เปรียบเทียบ">
  <div class="modal cmp-modal">
    <div class="modal-head">
      @if ($cmpExpense)
        <h3>{{ $L('เปรียบเทียบค่าใช้จ่าย', 'Compare expenses') }}</h3>
      @else
        <h3 data-i18n="dash.compare">เปรียบเทียบ</h3>
      @endif
      <button type="button" class="modal-x" data-modal-close aria-label="ปิด" data-i18n-aria="common.close">&#10005;</button>
    </div>

    <form class="modal-body" method="get" action="{{ route('dashboard') }}">
      {{--
        พาตัวกรองของหน้าไปด้วย เพื่อให้ปุ่ม "ย้อนกลับ" ในหน้าผลเทียบกลับมาที่หน้าเดิมได้ถูกต้อง
        — คนละเรื่องกับขอบเขตที่เอาไปเทียบ
      --}}
      @php
        /*
          🔴 ไม่ส่ง view=overview กับ kind ที่ไม่ได้ใช้ — เป็นค่าเริ่มต้นอยู่แล้ว
             ส่งไปนอกจากทำให้ลิงก์รก ยังทำให้หน้าภาพรวมมีช่อง name="view" โผล่มาโดยไม่จำเป็น
        */
        $cmpKeep = $cmpExpense
          ? ['company', 'year', 'dept', 'tab', 'paid_q', 'paid_m', 'paid_d']
          : ['company', 'year', 'quarter', 'dept'];
        if (($filters['view'] ?? 'overview') !== 'overview') {
          $cmpKeep[] = 'view';
          if ($filters['view'] === 'activity') { $cmpKeep[] = 'kind'; }
        }
      @endphp
      @foreach ($cmpKeep as $keep)
        @if (($filters[$keep] ?? '') !== '')<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif
      @endforeach

      <div class="cmp-grid">
        @foreach ($cmpSides as $side)
          @php $n = fn (string $f) => $side['key'].'['.$f.']'; $now = $side['now']; @endphp

          <div class="cmp-side">
            <h4>{{ $L($side['th'], $side['en']) }}</h4>

            {{-- ธงบอกว่าฝั่งนี้ผู้ใช้กรอกมาเอง — ดูคำอธิบายหัวไฟล์ --}}
            <input type="hidden" name="{{ $n('on') }}" value="1">

            <label>
              <span>{{ $L('บริษัท', 'Company') }}</span>
              <select class="sel" name="{{ $n('company') }}">
                <option value="" data-loc-th="ทุกบริษัท" data-loc-en="All companies">ทุกบริษัท</option>
                @foreach ($companyOptions as $co)
                  <option value="{{ $co['id'] }}" @selected(($now['company'] ?? '') === $co['id'])
                          data-loc-th="{{ $co['th'] }}" data-loc-en="{{ $co['en'] }}">{{ $co['th'] }}</option>
                @endforeach
              </select>
            </label>

            <label>
              <span>{{ $L('ปีงบ', 'Budget year') }}</span>
              <select class="sel" name="{{ $n('year') }}" data-cmp-year>
                @unless ($cmpExpense)
                  <option value="all" @selected(($now['year'] ?? '') === 'all') data-loc-th="ทุกปี" data-loc-en="All years">ทุกปี</option>
                @endunless
                @foreach ($compareOptions['years'] as $y)
                  <option value="{{ $y }}" @selected(($now['year'] ?? '') === (string) $y)>{{ $y }}</option>
                @endforeach
              </select>
            </label>

            @unless ($cmpExpense)
              <label>
                <span>{{ $L('งวดงบ', 'Budget period') }}</span>
                <select class="sel" name="{{ $n('quarter') }}" data-cmp-quarter>
                  <option value="" data-loc-th="ทุกงวดงบ" data-loc-en="All budget periods">ทุกงวดงบ</option>
                  @foreach ($compareOptions['quarters'] as $q)
                    <option value="{{ $q }}" data-q="{{ $q }}" @selected(($now['quarter'] ?? '') === (string) $q)>{{ $cmpPeriod((int) $q, (string) ($now['year'] ?? '')) }}</option>
                  @endforeach
                </select>
              </label>
            @endunless

            <label>
              <span>{{ $L('แผนก', 'Department') }}</span>
              <select class="sel" name="{{ $n('dept') }}">
                <option value="" data-loc-th="ทุกแผนก" data-loc-en="All departments">ทุกแผนก</option>
                @foreach ($compareOptions['depts'] as $key => $name)
                  <option value="{{ $key }}" @selected(($now['dept'] ?? '') === $key)>{{ $name }}</option>
                @endforeach
              </select>
            </label>

            @if ($cmpExpense)
              {{--
                ช่วงวันที่จ่าย — ทุกช่องเดินตาม "ปีงบของฝั่งนี้" (เจ้าของสั่ง 2026-09-17)
                🔴 ป้ายเป็น Q1–Q4 และ ม.ค.–ธ.ค. เฉยๆ ไม่มีปีต่อท้าย เพราะปีอยู่ในช่องข้างบนแล้ว
                   ค่าจริงยังเป็น YYYY-n / YYYY-MM / YYYY-MM-DD ตามที่เซิร์ฟเวอร์ตรวจ
                   สคริปต์ท้ายไฟล์เปลี่ยนปีในค่าให้เองเมื่อสลับปี
                🔴 ช่องวันเปิดใช้เมื่อเลือกเดือนแล้วเท่านั้น — จำนวนวันขึ้นกับเดือนและปีอธิกสุรทิน
              --}}
              @php $cmpYear = (string) ($now['year'] ?? $filters['year'] ?? ''); @endphp
              <label>
                <span>{{ $L('ไตรมาสที่จ่าย', 'Payment quarter') }}</span>
                <select class="sel" name="{{ $n('paid_q') }}" data-cmp-q>
                  <option value="" data-loc-th="ทุกไตรมาส" data-loc-en="All quarters">ทุกไตรมาส</option>
                  @if (($now['paid_q'] ?? '') !== '')
                    <option value="{{ $now['paid_q'] }}" selected>Q{{ substr($now['paid_q'], 5) }}</option>
                  @endif
                </select>
              </label>
              <label>
                <span>{{ $L('เดือนที่จ่าย', 'Payment month') }}</span>
                <select class="sel" name="{{ $n('paid_m') }}" data-cmp-m>
                  <option value="" data-loc-th="ทุกเดือน" data-loc-en="All months">ทุกเดือน</option>
                  @if (($now['paid_m'] ?? '') !== '')
                    <option value="{{ $now['paid_m'] }}" selected>{{ $cmpMonths[(int) substr($now['paid_m'], 5, 2) - 1][0] }}</option>
                  @endif
                </select>
              </label>
              <label>
                <span>{{ $L('วันที่จ่าย', 'Payment date') }}</span>
                <select class="sel" name="{{ $n('paid_d') }}" data-cmp-d @disabled(($now['paid_m'] ?? '') === '')>
                  <option value="" data-loc-th="ทุกวัน" data-loc-en="All days">ทุกวัน</option>
                  @if (($now['paid_d'] ?? '') !== '')
                    <option value="{{ $now['paid_d'] }}" selected>{{ (int) substr($now['paid_d'], 8, 2) }}</option>
                  @endif
                </select>
              </label>
              <p class="cmp-hint">{{ $L('กรอกหลายช่อง ระบบใช้ช่องที่ละเอียดที่สุด (วัน > เดือน > ไตรมาส) · ไม่กรอกเลย = ทั้งปีงบ',
                                        'If several are filled, the finest wins (day > month > quarter) · none = whole budget year') }}</p>
            @endif
          </div>
        @endforeach
      </div>
    </form>

    {{-- 🔴 ยินยอมอยู่ซ้าย · ยกเลิกอยู่ขวา (กติกาของโปรเจค) --}}
    <div class="modal-foot">
      <button type="button" class="btn btn-primary" data-cmp-go>{{ $L('เปรียบเทียบ', 'Compare') }}</button>
      <button type="button" class="btn btn-quiet" data-modal-close>{{ $L('ยกเลิก', 'Cancel') }}</button>
    </div>
  </div>
</div>

@once
  <script>
    'use strict';
    (function () {
      // ปุ่มในท้ายหน้าต่างอยู่นอก <form> จึงสั่งส่งเอง (ซ้อน form ใน modal ไม่ได้)
      document.addEventListener('click', function (e) {
        var go = e.target.closest ? e.target.closest('[data-cmp-go]') : null;
        if (!go) { return; }

        var form = go.closest('.modal').querySelector('form');
        if (form) { form.requestSubmit ? form.requestSubmit() : form.submit(); }
      });

      // ป้ายงวดงบเปลี่ยนตามปีของฝั่งนั้น — BG26Q1 · ทุกปี = BGxxQ1 (เจ้าของสั่ง 2026-09-17)
      document.addEventListener('change', function (e) {
        if (!e.target.matches || !e.target.matches('[data-cmp-year]')) { return; }
        var side = e.target.closest('.cmp-side');
        var quarter = side ? side.querySelector('[data-cmp-quarter]') : null;
        if (!quarter) { return; }
        var yy = /^\d{4}$/.test(e.target.value) ? e.target.value.slice(2) : 'xx';
        quarter.querySelectorAll('option[data-q]').forEach(function (opt) {
          opt.textContent = 'BG' + yy + 'Q' + opt.getAttribute('data-q');
        });
      });

      /*
        แท็บค่าใช้จ่าย: ช่องช่วงวันที่จ่ายเติมจาก "ช่วงที่มีรายการจริง" ของฝั่งนั้น (เจ้าของแจ้ง 2026-09-18)

        🔴 ของเดิมสร้างเป็นปฏิทินเปล่า (Q1–Q4 · ม.ค.–ธ.ค. · วันที่ 1–31) เลือกวันที่ไม่มีรายการ
           แล้วกดเปรียบเทียบจะเจอ "ไม่พบข้อมูล" — ตอนนี้ถามเซิร์ฟเวอร์ว่าปีนั้นมีวันไหนบ้าง
        🔴 ยิงเมื่อเปิดหน้าต่าง/เปลี่ยน บริษัท-ปี-แผนก เท่านั้น แล้วจำไว้ทั้งหน้า (คีย์ = บริษัท|ปี|แผนก)
           เปลี่ยนไตรมาส/เดือนใช้ข้อมูลก้อนเดิม ไม่ถามซ้ำ
      */
      var calendarBox = document.querySelector('[data-cmp-calendar]');
      var calendarCfg = { url: '', months: [] };

      try { calendarCfg = JSON.parse(calendarBox ? calendarBox.textContent : '{}') || calendarCfg; } catch (err) { calendarCfg = { url: '', months: [] }; }

      var calendarCache = {};   // promise ระหว่างรอ
      var calendarData = {};    // ผลที่ได้แล้ว — ใช้เติมได้ทันทีโดยไม่ต้องรอรอบถัดไป
      var monthName = function (mm) {
        var m = calendarCfg.months[Number(mm) - 1];
        return m ? m : { th: mm, en: mm };
      };
      var option = function (value, th, en, selected) {
        var opt = document.createElement('option');
        opt.value = value;
        opt.textContent = window.BMS.lang() === 'en' ? en : th;
        opt.setAttribute('data-loc-th', th);
        opt.setAttribute('data-loc-en', en);
        opt.selected = selected;

        return opt;
      };
      var keepOnly = function (select) {
        while (select.options.length > 1) { select.remove(1); }
      };

      /** เติม 3 ช่องจากปฏิทินจริง — เดือนแคบตามไตรมาส · วันแคบตามเดือน (กติกาเดียวกับตัวกรองบนหน้า) */
      var fillSide = function (side, calendar) {
        var quarter = side.querySelector('[data-cmp-q]');
        var month = side.querySelector('[data-cmp-m]');
        var day = side.querySelector('[data-cmp-d]');
        if (!quarter || !month || !day) { return; }

        var wantQ = quarter.value, wantM = month.value, wantD = day.value;
        var quarters = calendar.quarters || [], months = calendar.months || [], days = calendar.days || {};

        keepOnly(quarter);
        quarters.forEach(function (q) {
          var n = q.slice(5);
          quarter.appendChild(option(q, 'Q' + n, 'Q' + n, q === wantQ));
        });
        if (quarters.indexOf(wantQ) === -1) { quarter.value = ''; }

        keepOnly(month);
        months.filter(function (m) {
          if (!quarter.value) { return true; }

          return Math.ceil(Number(m.slice(5)) / 3) === Number(quarter.value.slice(5));
        }).forEach(function (m) {
          var name = monthName(m.slice(5));
          month.appendChild(option(m, name.th, name.en, m === wantM));
        });
        if (month.value !== wantM) { month.value = ''; }

        keepOnly(day);
        var list = month.value ? (days[month.value] || []) : [];
        list.forEach(function (d) {
          var n = String(Number(d.slice(8)));
          day.appendChild(option(d, n, n, d === wantD));
        });
        day.disabled = list.length === 0;
        if (day.value !== wantD) { day.value = ''; }
      };

      var loadSide = function (side) {
        var year = side.querySelector('[data-cmp-year]');
        var company = side.querySelector('select[name$="[company]"]');
        var dept = side.querySelector('select[name$="[dept]"]');
        var day = side.querySelector('[data-cmp-d]');
        if (!year || !day || !calendarCfg.url) { return; }

        var scope = [company ? company.value : '', year.value, dept ? dept.value : ''].join('|');
        if (calendarData[scope]) {
          return fillSide(side, calendarData[scope]);
        }
        if (calendarCache[scope]) {
          return calendarCache[scope].then(function (cal) { fillSide(side, cal); });
        }
        side.classList.add('is-loading');
        calendarCache[scope] = fetch(calendarCfg.url + '?' + new URLSearchParams({
          tab: 'expense', company: company ? company.value : '', year: year.value, dept: dept ? dept.value : '',
        }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
          .then(function (res) { return res.ok ? res.json() : { quarters: [], months: [], days: {} }; })
          .catch(function () { return { quarters: [], months: [], days: {} }; });

        return calendarCache[scope].then(function (cal) {
          side.classList.remove('is-loading');
          calendarData[scope] = cal;
          fillSide(side, cal);
        });
      };

      document.addEventListener('change', function (e) {
        if (!e.target.matches) { return; }
        var side = e.target.closest('.cmp-side');
        if (!side) { return; }
        // เปลี่ยนขอบเขต = ต้องถามช่วงวันที่ใหม่ · เปลี่ยนไตรมาส/เดือน = จัดของเดิมพอ
        if (e.target.matches('[data-cmp-year], select[name$="[company]"], select[name$="[dept]"]')) {
          loadSide(side);
        } else if (e.target.matches('[data-cmp-q], [data-cmp-m]')) {
          var year = side.querySelector('[data-cmp-year]');
          var company = side.querySelector('select[name$="[company]"]');
          var dept = side.querySelector('select[name$="[dept]"]');
          var scope = [company ? company.value : '', year ? year.value : '', dept ? dept.value : ''].join('|');
          if (calendarData[scope]) { fillSide(side, calendarData[scope]); }
          else if (calendarCache[scope]) { calendarCache[scope].then(function (cal) { fillSide(side, cal); }); }
        }
      });

      // เปิดหน้าต่างเมื่อไหร่ค่อยถาม — ไม่ต้องรอตอนเปิดหน้า
      document.addEventListener('click', function (e) {
        var open = e.target.closest ? e.target.closest('[data-modal-open="cmp-pick"]') : null;
        if (open) { document.querySelectorAll('.cmp-side').forEach(loadSide); }
      });
    }());
  </script>
@endonce
