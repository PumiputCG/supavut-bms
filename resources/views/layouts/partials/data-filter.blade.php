{{--
  ═══════════ ตัวกรองหัวคอลัมน์แบบ "กรองที่ข้อมูล" ═══════════

  🔴 ต่างจาก [`table-filter`](table-filter.blade.php) ตรงที่ตัวนั้นกรอง **แถวที่อยู่ใน DOM**
     ซึ่งใช้ได้เฉพาะตารางที่วาดครบทุกแถวและไม่มีอะไรคิดต่อจากแถว
     ตารางพวกนี้ใช้ไม่ได้ (เจ้าของแจ้ง 2026-09-11):
       · ตารางที่แบ่งหน้า — กรองได้แค่หน้าที่เปิดอยู่ อีกหมื่นแถวหลุดไปเงียบๆ
       · ตารางที่มีคอลัมน์ "ลำดับ" — ซ่อนแถวแล้วเลขกลายเป็น 1, 5, 9 ข้ามไปมา
       · ตารางที่มียอดรวมท้ายกระดาษ — ยอดไม่เดินตามสิ่งที่เห็น
       · ตารางที่ merge เซลล์ด้วย rowspan — ซ่อนแถวแรกทิ้ง คอลัมน์ที่เหลือเลื่อนผิดช่องทั้งตาราง

  ตัวนี้จึง **กรองที่ชุดข้อมูลก่อน แล้วให้หน้าวาดใหม่เอง** ทุกอย่างข้างบนจึงถูกต้องตามไปด้วย
  หน้าตา (ปุ่ม ▼ · แผงเลือกค่า) ยืมคลาสชุดเดียวกับตัวกรองกลาง จะได้เหมือนกันทั้งระบบ

  ── วิธีใช้ ──
    var f = window.BMS.dataFilter({
      table: ตาราง,                      // <table> ที่มี <thead> แถวเดียว
      cols: [null, { get: r => r.year }],  // ลำดับตรงกับ <th> · null = คอลัมน์นี้กรองไม่ได้
      rows: function () { return ชุดข้อมูลตั้งต้น; },   // เรียกใหม่ได้ทุกครั้ง
      onChange: function () { วาดตารางใหม่; },
    });

    f.view()    ชุดข้อมูลที่ผ่านตัวกรองแล้ว
    f.on()      กำลังกรองอยู่ไหม
    f.reset()   ล้างตัวกรองทุกคอลัมน์ (ใช้ตอนเปลี่ยนชุดข้อมูล)

  🔴 คอลัมน์ที่ไม่ควรให้กรอง (ลำดับ · ปุ่ม) ใส่ `data-no-filter` ที่ <th>
--}}
<script>
  'use strict';

  (function () {
    /*
      🔴 วาดรายการค่าไม่เกินเท่านี้ — คอลัมน์อย่าง "ชื่อรายการ" มีค่าไม่ซ้ำเป็นหมื่น
         วาดครบทุกค่าแผงจะค้างตอนเปิด · ที่เหลือใช้ช่องค้นหาซึ่งค้นจากค่าทั้งหมด
    */
    var CHOICE_MAX = 300;
    var pop = null;
    var popBtn = null;

    function closePop() {
      if (pop) { pop.remove(); pop = null; popBtn = null; }
    }

    /**
     * วางแผงให้ติดอยู่ใต้ปุ่มของหัวคอลัมน์เสมอ
     *
     * 🔴 .fpop เป็น position: fixed — วางครั้งเดียวแล้วเลื่อนหน้าจอ แผงจะค้างกลางจอตามสายตา
     *    ดูเหมือนแผงไหลตามเมาส์ลงมา (เจ้าของแจ้ง 2026-09-11)
     *    ต้องคำนวณตำแหน่งใหม่ทุกครั้งที่เลื่อน · หัวคอลัมน์เป็น sticky แผงจึงติดอยู่กับที่จริงๆ
     * 🔴 ปุ่มเลื่อนพ้นจอเมื่อไหร่ให้ปิดแผง ไม่งั้นแผงลอยชี้ไปที่ว่าง
     */
    function place() {
      if (! pop || ! popBtn) { return; }

      var at = popBtn.getBoundingClientRect();

      if (at.bottom < 0 || at.top > window.innerHeight) { return closePop(); }

      pop.style.top = (at.bottom + 4) + 'px';
      pop.style.left = Math.max(8, Math.min(at.left - 8, window.innerWidth - pop.offsetWidth - 8)) + 'px';
    }

    // ดักช่วง capture ด้วย เพราะบางตารางเลื่อนในกรอบของตัวเอง ไม่ใช่เลื่อนทั้งหน้า
    window.addEventListener('scroll', place, true);
    window.addEventListener('resize', place);

    document.addEventListener('click', function (e) {
      if (pop && ! pop.contains(e.target)) { closePop(); }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { closePop(); }
    });

    window.BMS = window.BMS || {};

    window.BMS.dataFilter = function (opts) {
      var table = opts.table;
      var cols = opts.cols || [];
      var source = opts.rows;
      var onChange = opts.onChange || function () {};

      if (! table || ! table.tHead || ! table.tHead.rows.length) { return null; }

      // ป้ายประจำตัวของตัวกรองตัวนี้ — ใช้แยกว่าแผงที่เปิดค้างอยู่เป็นของตารางไหน
      var id = 'df' + (window.BMS.__dfSeq = (window.BMS.__dfSeq || 0) + 1);
      var picked = cols.map(function () { return null; });

      function en() { return window.BMS.lang() === 'en'; }

      function base() { return source() || []; }

      /** แถวนี้ผ่านตัวกรองไหม — ข้าม `skip` ไว้ใช้ตอนสร้างรายการค่าแบบ Excel */
      function passes(row, skip) {
        for (var i = 0; i < cols.length; i++) {
          if (i === skip || ! cols[i] || ! picked[i]) { continue; }

          if (picked[i].indexOf(String(cols[i].get(row))) === -1) { return false; }
        }

        return true;
      }

      function view() {
        return base().filter(function (r) { return passes(r, -1); });
      }

      function on() {
        return picked.some(function (p) { return !! p; });
      }

      function mark() {
        Array.prototype.forEach.call(table.tHead.rows[0].cells, function (th, i) {
          var btn = th.querySelector('.fbtn');

          if (btn) { btn.classList.toggle('is-on', !! picked[i]); }
        });
      }

      function reset() {
        picked = cols.map(function () { return null; });
        closePop();
        mark();
      }

      // ── ปุ่ม ▼ ที่หัวคอลัมน์ — ติดตั้งครั้งเดียว หัวตารางไม่ได้วาดใหม่ ──
      Array.prototype.forEach.call(table.tHead.rows[0].cells, function (th, index) {
        if (th.hasAttribute('data-no-filter') || ! cols[index]) { return; }

        /*
          ห่อข้อความไว้ใน .th-in แล้ววางปุ่มต่อท้าย
          🔴 ต้องย้าย data-i18n จาก <th> ลงไปที่ <span> ข้อความด้วย
             ตัวแปลภาษาสั่ง `el.textContent = คำแปล` กับทุก [data-i18n]
             ถ้าทิ้งไว้ที่ <th> พอสลับภาษาทีไร ปุ่มกรองหายทั้งตาราง (บั๊กจริง 2026-09-11)
        */
        var wrap = document.createElement('span');
        wrap.className = 'th-in';

        var label = document.createElement('span');

        while (th.firstChild) { label.appendChild(th.firstChild); }

        if (th.hasAttribute('data-i18n')) {
          label.setAttribute('data-i18n', th.getAttribute('data-i18n'));
          th.removeAttribute('data-i18n');
        }

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'fbtn';
        btn.innerHTML = '&#9660;';   // สามเหลี่ยมแบบเดียวกับ Excel
        btn.setAttribute('aria-label', 'กรองคอลัมน์นี้');
        btn.setAttribute('aria-haspopup', 'true');

        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          openPop(btn, index);
        });

        wrap.appendChild(label);
        wrap.appendChild(btn);
        th.appendChild(wrap);
        th.classList.add('has-filter');
      });

      mark();

      // ── แผงเลือกค่า ────────────────────────────────────────────
      function openPop(btn, index) {
        var reopen = pop && pop.dataset.col === String(index) && pop.dataset.owner === id;

        closePop();

        if (reopen) { return; }

        // ค่าที่เลือกได้คิดจากแถวที่ผ่านตัวกรองคอลัมน์อื่นแล้ว (เหมือน Excel)
        var seen = {};
        var values = [];

        base().forEach(function (r) {
          if (! passes(r, index)) { return; }

          var v = String(cols[index].get(r));

          if (seen[v]) { return; }

          seen[v] = true;
          values.push(v);
        });

        values.sort(function (a, b) { return a.localeCompare(b, 'th'); });

        // สถานะติ๊กของทุกค่า — เก็บแยกจากที่วาด เพราะวาดแค่บางส่วนเมื่อค่าเยอะ
        var state = {};

        values.forEach(function (v) {
          state[v] = ! picked[index] || picked[index].indexOf(v) !== -1;
        });

        pop = document.createElement('div');
        pop.className = 'fpop';
        pop.dataset.col = String(index);
        pop.dataset.owner = id;
        pop.setAttribute('role', 'dialog');
        popBtn = btn;

        var search = document.createElement('div');
        search.className = 'fpop-search';

        var box = document.createElement('input');
        box.type = 'search';
        box.placeholder = en() ? 'Search' : 'ค้นหา';
        search.appendChild(box);

        var list = document.createElement('div');
        list.className = 'fpop-list';

        var foot = document.createElement('div');
        foot.className = 'fpop-foot';

        var allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.textContent = en() ? 'Select all' : 'เลือกทั้งหมด';

        var noneBtn = document.createElement('button');
        noneBtn.type = 'button';
        noneBtn.textContent = en() ? 'Clear all' : 'ล้างที่เลือกทั้งหมด';

        foot.appendChild(allBtn);
        foot.appendChild(noneBtn);
        pop.appendChild(search);
        pop.appendChild(list);
        pop.appendChild(foot);
        document.body.appendChild(pop);

        /** ค่าที่ตรงกับคำค้น — เป็นตัวตั้งของทั้งการวาดและปุ่มเลือกทั้งหมด/ล้าง */
        function hits() {
          var term = box.value.trim().toLowerCase();

          return term === ''
            ? values
            : values.filter(function (v) { return v.toLowerCase().indexOf(term) !== -1; });
        }

        function render() {
          var found = hits();

          list.innerHTML = '';

          found.slice(0, CHOICE_MAX).forEach(function (v) {
            var item = document.createElement('label');
            item.className = 'fpop-item';

            var tick = document.createElement('input');
            tick.type = 'checkbox';
            tick.checked = state[v];

            tick.addEventListener('change', function () {
              state[v] = tick.checked;
              apply();
            });

            var span = document.createElement('span');
            span.textContent = v;
            span.title = v;

            item.appendChild(tick);
            item.appendChild(span);
            list.appendChild(item);
          });

          if (found.length > CHOICE_MAX) {
            var more = document.createElement('p');
            more.className = 'filter-note';
            more.style.padding = '6px 10px';
            more.textContent = en()
              ? 'Showing ' + CHOICE_MAX + ' of ' + found.length.toLocaleString('en-US') + ' values — type to narrow it down'
              : 'แสดง ' + CHOICE_MAX + ' จาก ' + found.length.toLocaleString('en-US') + ' ค่า — พิมพ์ค้นหาเพื่อหาค่าที่เหลือ';
            list.appendChild(more);
          }
        }

        /** เก็บค่าที่ติ๊กไว้แล้วสั่งวาดใหม่ — ติ๊กครบทุกค่า = ไม่ได้กรองคอลัมน์นี้ */
        function apply() {
          var chosen = values.filter(function (v) { return state[v]; });

          picked[index] = chosen.length === values.length ? null : chosen;
          mark();
          onChange();
        }

        box.addEventListener('input', render);

        allBtn.addEventListener('click', function () {
          hits().forEach(function (v) { state[v] = true; });
          render();
          apply();
        });

        noneBtn.addEventListener('click', function () {
          hits().forEach(function (v) { state[v] = false; });
          render();
          apply();
        });

        render();
        place();

        /*
          🔴 โฟกัสช่องค้นหาแบบไม่เลื่อนหน้าจอ
             ค่าปกติของเบราว์เซอร์คือเลื่อนให้ช่องที่โฟกัสมาอยู่กลางจอ
             ตารางยาว 50 แถวจะกระโดดทันทีที่กดปุ่มกรอง แล้วแผงก็หลุดจากหัวคอลัมน์
        */
        box.focus({ preventScroll: true });
      }

      return { view: view, on: on, reset: reset, close: closePop };
    };
  })();
</script>
