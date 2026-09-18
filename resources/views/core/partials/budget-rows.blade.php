{{--
  ═══════════ การ์ด "รายการงบล่าสุด" — แท็บย่อยที่ 2 ของงบประมาณ ═══════════

  🔴 เจ้าของสั่ง 2026-09-11 — ย้ายมาอยู่แท็บย่อยของตัวเอง · **ถอดหน้าต่างซ้อนออกแล้ว**
     เป็นตารางเต็มการ์ดเลย มีแบ่งหน้าและมีตัวกรองที่หัวคอลัมน์

  🔴 เรียงตาม "เวลาที่บัญชีเพิ่งคีย์" ไม่ใช่วันเริ่มงบ — จุดประสงค์คือเห็นของใหม่ก่อน

  🔴 ตัวกรองใช้ [`data-filter`](../../layouts/partials/data-filter.blade.php)
     ซึ่ง **กรองที่ข้อมูลทั้งชุดก่อน แล้วค่อยแบ่งหน้า** (เจ้าของสั่ง 2026-09-11)
     ตัวกรองกลาง (table-filter) ใช้ไม่ได้ เพราะมันกรองจากแถวที่อยู่ใน DOM
     ตารางนี้วาดทีละ 50 แถว จะกรองได้แค่หน้าที่เปิดอยู่ อีกหมื่นกว่าแถวหลุดไปเงียบๆ

  ข้อมูลใช้ชุดเดียวกับที่การ์ดกราฟฝังไว้แล้ว (window.BMS.budgetDocs) ไม่ส่งซ้ำ — ก้อนนี้หนักเป็นเมกะไบต์

  ต้องส่งมา
    $budgetRows  จำนวนรายการทั้งหมด (ไว้โชว์ก่อนสคริปต์วาดเสร็จ)
--}}
<section class="card">
  {{--
    หัวข้ออยู่กึ่งกลาง · จำนวนรายการชิดขวา (เจ้าของสั่ง 2026-09-11)
    ใช้ grid 3 ช่อง ช่องซ้ายเว้นว่าง หัวข้อจึงอยู่กลางการ์ดจริงๆ
  --}}
  <div class="card-head rows-head">
    <span></span>
    <h3 data-i18n="dash.latestRows">รายการงบล่าสุด</h3>

    <span class="soft rows-count">
      <span data-i18n="dash.countLabel">จำนวน</span>
      <b data-rows-count>{{ number_format($budgetRows ?? 0) }}</b>
      <span data-i18n="dash.budgetRows">รายการ</span>
    </span>
  </div>

  <div class="card-body">
    {{--
      ยอดรวมอยู่ริมซ้ายเหนือตาราง (เจ้าของสั่ง 2026-09-11)
      🔴 เดินตามตัวกรองหัวคอลัมน์เสมอ กรองปีไหนก็เห็นยอดของปีนั้น
    --}}
    <div class="dash-total">
      <span data-i18n="dash.budgetTotalLabel">วงเงินที่อนุมัติรวม</span>
      <b data-rows-sum>{{ number_format($budgetTotal ?? 0, 2) }}</b>
      <span data-i18n="common.baht">(บาท)</span>
    </div>

    <div class="tbl-wrap rows-wrap">
      <table class="tbl chart-tbl" data-rows-table>
        <thead>
          <tr>
            <th class="col-seq" data-no-filter data-i18n="tbl.seq">ลำดับ</th>
            <th class="col-no" data-i18n="erp.year">ปี</th>
            <th class="col-no" data-i18n="erp.model">Model</th>
            <th class="col-no" data-i18n="erp.budgetNo">Budget No.</th>
            <th class="txt-left" data-i18n="erp.comment">ชื่อรายการ</th>
            <th data-i18n="budget.dept">แผนก</th>
            <th class="col-money" data-i18n="erp.budget">Budget</th>
            <th class="col-no" data-i18n="erp.startDate">วันเริ่ม</th>
            <th class="col-no" data-i18n="erp.createdAt">วันที่สร้าง</th>
          </tr>
        </thead>
        <tbody data-rows-body></tbody>
      </table>
    </div>

    <p class="filter-note" data-rows-note hidden></p>

    {{-- แถบแบ่งหน้า — ยอดรวมย้ายไปอยู่เหนือตารางแล้ว จะได้เห็นตั้งแต่ยังไม่เลื่อนลงมา --}}
    <div class="rows-foot" data-rows-foot>
      <div class="rows-pager" data-rows-pager></div>
    </div>
  </div>
</section>

@push('page-style')
  <style>
    /* หัวการ์ด 3 ช่อง — หัวข้ออยู่กลางจริง จำนวนรายการชิดขวา */
    .rows-head { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; }
    .rows-head h3 { text-align: center; font-size: var(--fs-lg); font-weight: 700; }
    .rows-head .rows-count { justify-self: end; }
    .rows-count b { color: var(--navy-800); font-weight: 700; }

    /*
      🔴 ตารางนี้ต้องกว้างเต็มการ์ด
         การ์ดกราฟตั้ง `.tbl-wrap .chart-tbl { max-width: 780px }` ไว้สำหรับตารางในหน้าต่างของมัน
         ถ้าไม่เขียนทับ ตาราง 9 คอลัมน์ของเราจะถูกบีบเหลือ 780px ลอยอยู่กลางการ์ด
    */
    .tbl-wrap.rows-wrap .chart-tbl { width: 100%; max-width: none; margin: 0; }

    /* หัวคอลัมน์ค้างไว้ตอนเลื่อนอ่าน — 50 แถวยาวเกินหนึ่งหน้าจอ */
    .rows-wrap thead th { position: sticky; top: 0; z-index: 1; }

    .rows-foot { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding-top: 12px; }
    .rows-foot[hidden] { display: none !important; }

    /* ── แถบแบ่งหน้า ── */
    .rows-pager { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

    .rows-pager button {
      min-width: 32px; height: 30px; padding: 0 8px;
      border: 1px solid var(--line); border-radius: var(--radius-sm);
      background: var(--surface); color: var(--navy-800);
      font-size: var(--fs-xs); cursor: pointer;
      transition: background .14s var(--ease), border-color .14s var(--ease);
    }

    .rows-pager button:hover:not(:disabled) { background: var(--surface-2); border-color: var(--navy-200); }
    .rows-pager button:disabled { opacity: .45; cursor: default; }
    .rows-pager button.is-now { background: var(--navy-900); border-color: var(--navy-900); color: #fff; font-weight: 700; }
    .rows-pager .rows-at { margin-left: 8px; color: var(--muted); font-size: var(--fs-xs); }

    /* เส้นคั่นตอนขึ้นปีใหม่ — อ่านเป็นกลุ่มปีได้โดยไม่ต้องตีกรอบ */
    [data-rows-body] tr.yr-split > td { border-top: 2px solid var(--navy-200); }
  </style>
@endpush

@push('page-script')
  <script>
    'use strict';

    /*
      ตาราง "รายการงบล่าสุด"
      🔴 ลำดับต้องเป็น กรองทั้งชุด → แบ่งหน้า เท่านั้น
         ถ้าสลับเป็นแบ่งหน้าก่อนแล้วค่อยกรอง จะได้ผลแค่ 50 แถวที่เปิดอยู่
    */
    (function () {
      var table = document.querySelector('[data-rows-table]');

      if (! table) { return; }

      var body = table.querySelector('[data-rows-body]');
      var foot = document.querySelector('[data-rows-foot]');
      var pager = document.querySelector('[data-rows-pager]');
      var sumOut = document.querySelector('[data-rows-sum]');
      var cntOut = document.querySelector('[data-rows-count]');
      var note = document.querySelector('[data-rows-note]');

      var PER = 50;
      var page = 1;
      var all = [];           // ทุกแถวที่เซิร์ฟเวอร์ส่งมา
      var base = [];          // หลังหักตัวกรองแผนกของหน้า (แถบตัวกรองด้านบน)

      function en() { return window.BMS.lang() === 'en'; }

      function txt(v) {
        var s = v == null ? '' : String(v).trim();

        return s === '' ? '—' : s;
      }

      function baht(n) {
        return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }

      function num(n) { return Number(n || 0).toLocaleString('en-US'); }

      function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);

        return d.innerHTML;
      }

      /**
       * แบนข้อมูล "บริษัท → แผนก → รายการ" ให้เป็นรายการเดียว แล้วเรียงของใหม่ขึ้นก่อน
       *
       * 🔴 ตารางนี้รวมทุกบริษัท (เจ้าของยืนยัน 2026-09-11) ต่างจากการ์ดกราฟที่แยกบริษัท
       *    จุดประสงค์คือ "บัญชีเพิ่งคีย์อะไรเข้ามาบ้าง" ซึ่งไม่เกี่ยวว่าเป็นสมุดของบริษัทไหน
       */
      function build() {
        var docs = (window.BMS && window.BMS.budgetDocs) || {};
        var out = [];

        Object.keys(docs).forEach(function (co) {
          Object.keys(docs[co]).forEach(function (dept) {
            docs[co][dept].forEach(function (r) {
              out.push({
                dept: dept, year: r.year, model: r.model, budgetNo: r.budgetNo,
                title: r.title, amount: r.amount, startDate: r.startDate, createdAt: r.createdAt,
              });
            });
          });
        });

        out.sort(function (a, b) {
          return String(b.createdAt || '').localeCompare(String(a.createdAt || ''));
        });

        return out;
      }

      /*
        ตัวกรองแผนกอยู่นอกการ์ดและคุมทุกการ์ดพร้อมกัน (กติกาเดิม 2026-09-04)
        ตารางนี้จึงตัดข้อมูลตามแผนกที่เลือกไว้ก่อน แล้วตัวกรองหัวคอลัมน์ค่อยกรองซ้อนลงไปอีกชั้น
      */
      function scope() {
        var dept = (window.BMS && window.BMS.dashDept) ? window.BMS.dashDept() : '';

        base = dept === '' ? all : all.filter(function (r) { return r.dept === dept; });
      }

      all = build();
      scope();

      // ลำดับของ cols ต้องตรงกับ <th> · ช่อง 0 คือ "ลำดับ" จึงเป็น null
      var filter = window.BMS.dataFilter({
        table: table,
        rows: function () { return base; },
        onChange: function () { page = 1; draw(); },
        cols: [
          null,
          { get: function (r) { return txt(r.year); } },
          { get: function (r) { return txt(r.model); } },
          { get: function (r) { return txt(r.budgetNo); } },
          { get: function (r) { return txt(r.title); } },
          { get: function (r) { return txt(r.dept); } },
          { get: function (r) { return baht(r.amount); } },
          { get: function (r) { return txt(r.startDate); } },
          { get: function (r) { return txt(r.createdAt); } },
        ],
      });

      // ── วาดตาราง ───────────────────────────────────────────────
      function draw() {
        var rows = filter.view();
        var pages = Math.max(Math.ceil(rows.length / PER), 1);

        page = Math.min(Math.max(page, 1), pages);

        var from = (page - 1) * PER;
        var slice = rows.slice(from, from + PER);

        body.innerHTML = slice.length
          ? slice.map(function (r, i) {
              var newYear = i > 0 && slice[i - 1].year !== r.year;

              return '<tr class="' + (newYear ? 'yr-split' : '') + '">'
                + '<td class="col-seq">' + (from + i + 1) + '</td>'
                + '<td class="col-no">' + esc(txt(r.year)) + '</td>'
                + '<td class="col-no"><span class="doc-no">' + esc(r.model) + '</span></td>'
                + '<td class="col-no"><span class="doc-no">' + esc(r.budgetNo) + '</span></td>'
                + '<td class="txt-left"><span class="doc-t" title="' + esc(r.title) + '">' + esc(r.title) + '</span></td>'
                + '<td>' + esc(r.dept) + '</td>'
                + '<td class="col-money txt-right"><span class="money money-strong">' + baht(r.amount) + '</span></td>'
                + '<td class="col-no soft">' + esc(txt(r.startDate)) + '</td>'
                + '<td class="col-no soft">' + esc(txt(r.createdAt)) + '</td>'
              + '</tr>';
            }).join('')
          : '<tr><td colspan="9" class="empty">'
            + (all.length
              ? (en() ? 'Nothing matches the filter' : 'ไม่มีรายการที่ตรงกับตัวกรอง')
              : (en() ? 'No budget rows in the ERP' : 'ไม่มีรายการงบใน ERP'))
            + '</td></tr>';

        /*
          🔴 ยอดรวมกับจำนวนรายการคิดจาก "ทั้งชุดที่กรองอยู่" ไม่ใช่เฉพาะหน้านี้
             ไม่งั้นตัวเลขจะกระโดดทุกครั้งที่กดเปลี่ยนหน้า
        */
        sumOut.textContent = baht(rows.reduce(function (n, r) { return n + Number(r.amount || 0); }, 0));
        cntOut.textContent = num(rows.length);

        note.hidden = ! filter.on();
        note.textContent = filter.on()
          ? (en()
            ? 'Showing ' + num(rows.length) + ' of ' + num(base.length) + ' rows'
            : 'แสดง ' + num(rows.length) + ' จาก ' + num(base.length) + ' แถว')
          : '';

        foot.hidden = base.length === 0;
        drawPager(pages, from, slice.length, rows.length);
      }

      function drawPager(pages, from, shown, total) {
        pager.innerHTML = '';

        var add = function (label, to, opts) {
          var b = document.createElement('button');
          b.type = 'button';
          b.textContent = label;

          if (opts && opts.now) { b.classList.add('is-now'); }
          if (opts && opts.off) { b.disabled = true; }

          b.addEventListener('click', function () { page = to; draw(); });
          pager.appendChild(b);
        };

        add('‹', page - 1, { off: page <= 1 });

        /*
          เลขหน้าแบบหน้าต่างเลื่อน — ข้อมูลมีเป็นหมื่นแถว
          พิมพ์ทุกเลขหน้าจะยาวเป็นหางว่าวจนกดไม่ถูก
        */
        var first = Math.max(1, page - 2);
        var last = Math.min(pages, first + 4);

        first = Math.max(1, last - 4);

        if (first > 1) { add('1', 1, {}); }
        if (first > 2) { pager.insertAdjacentHTML('beforeend', '<span class="rows-at">…</span>'); }

        for (var p = first; p <= last; p++) { add(String(p), p, { now: p === page }); }

        if (last < pages - 1) { pager.insertAdjacentHTML('beforeend', '<span class="rows-at">…</span>'); }
        if (last < pages) { add(String(pages), pages, {}); }

        add('›', page + 1, { off: page >= pages });

        var at = document.createElement('span');
        at.className = 'rows-at';
        at.textContent = total
          ? (en()
            ? (from + 1) + '–' + (from + shown) + ' of ' + num(total)
            : (from + 1) + '–' + (from + shown) + ' จาก ' + num(total))
          : '';
        pager.appendChild(at);
      }

      draw();

      /*
        เปลี่ยนแผนกที่แถบตัวกรองของหน้า = เริ่มดูชุดใหม่
        🔴 ต้องล้างตัวกรองหัวคอลัมน์ด้วย ไม่งั้นค่าที่ติ๊กค้างไว้เป็นของแผนกเก่า
           แล้วตารางจะว่างเปล่าโดยที่ผู้ใช้ไม่รู้ว่าเพราะอะไร
      */
      document.addEventListener('dash:filter', function () {
        filter.reset();
        scope();
        page = 1;
        draw();
      });

      // สลับภาษาแล้ววาดใหม่ ไม่งั้นคำว่า "รายการ / rows" ค้างภาษาเดิม
      document.addEventListener('bms:languagechange', function () { filter.close(); draw(); });
    })();
  </script>
@endpush
