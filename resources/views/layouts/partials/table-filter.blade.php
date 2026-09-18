{{--
  ตัวกรองรายคอลัมน์แบบ Excel — ใช้ได้กับทุกตารางในระบบ (เจ้าของสั่ง 2026-09-03)

  include ไว้ที่ layouts/app.blade.php แล้ว **ทุกหน้าได้ไปด้วย ไม่ต้อง include ซ้ำ**

  วิธีใช้: ใส่ data-filterable ที่ตาราง
    <table class="tbl" data-filterable> ... </table>

  ระบบจะเติม "ปุ่มสามเหลี่ยม" ไว้ท้ายชื่อคอลัมน์ให้เอง
  กดแล้วกางแผงเลือกค่าแบบ Excel — ติ๊กเลือกได้หลายค่า · มีช่องค้นหา · เลือกทั้งหมด/ล้าง

  ข้ามคอลัมน์ที่ไม่ควรกรอง (ปุ่ม/รูป) ด้วย data-no-filter ที่ <th>

  ── 2 โหมด ──────────────────────────────────────────────────────────
  1) `data-filterable`      กรองด้วย JavaScript จากแถวที่วาดอยู่
                            ใช้กับตารางที่ **ไม่แบ่งหน้า** (เห็นทุกแถวอยู่แล้ว)
  2) `data-filter-server`   กรองที่เซิร์ฟเวอร์ — ส่งค่าไปกับ URL แล้วโหลดหน้าใหม่
                            🔴 **ตารางที่แบ่งหน้าต้องใช้โหมดนี้เท่านั้น** (เจ้าของสั่ง 2026-09-04)
                            ไม่งั้นกรองได้แค่หน้าที่เปิดอยู่ อีก 20 หน้าที่เหลือไม่ถูกกรอง

  โหมดเซิร์ฟเวอร์ต้องมี
    <th data-filter-key="dept">          บอกว่าคอลัมน์นี้ผูกกับคีย์อะไร
    <script type="application/json" data-filter-options>{...}</script>   ค่าที่เลือกได้ทั้งหมด
    เรียกจาก controller ด้วย App\Support\TableFilter

  🔴 ตารางที่มีแถวกาง/พับเอง ห้ามใส่ — ตัวกรองจะไปแหย่สถานะซ่อนของแถวย่อย
--}}
<style>
  /* ── ปุ่มสามเหลี่ยมท้ายชื่อคอลัมน์ ─────────────────────────── */
  .tbl th.has-filter { position: sticky; top: 0; }

  .th-in { display: inline-flex; align-items: center; gap: 6px; }

  .fbtn {
    display: grid; place-items: center; width: 16px; height: 16px; flex: 0 0 auto;
    padding: 0; border: 0; border-radius: 3px;
    background: rgb(255 255 255 / 18%); color: #fff;
    font-size: 8px; line-height: 1; cursor: pointer;
    transition: background .14s var(--ease);
  }

  .fbtn:hover { background: rgb(255 255 255 / 34%); }
  /* กำลังดึงค่าที่เลือกได้จากเซิร์ฟเวอร์ (โหมดโหลดทีหลัง) — บอกด้วยการกะพริบ ไม่ขยับตำแหน่งปุ่ม */
  .fbtn.is-busy { animation: fbtnBusy .7s ease-in-out infinite; cursor: progress; }
  @keyframes fbtnBusy { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
  @media (prefers-reduced-motion: reduce) { .fbtn.is-busy { animation: none; opacity: .5; } }

  /* คอลัมน์ที่กรองค้างอยู่ — ทำปุ่มให้เด่นจะได้รู้ว่าตารางถูกกรองอยู่ */
  .fbtn.is-on { background: var(--ok); color: #fff; }

  /* ── แผงเลือกค่า ───────────────────────────────────────────── */
  .fpop {
    position: fixed; z-index: var(--z-dropdown);
    width: 232px; max-height: 330px; display: flex; flex-direction: column;
    border: 1px solid var(--line); border-radius: var(--radius);
    background: var(--surface); box-shadow: var(--shadow-lg);
    color: var(--ink); font-size: var(--fs-sm); font-weight: 400;
  }

  .fpop[hidden] { display: none; }

  .fpop-search { padding: 8px; border-bottom: 1px solid var(--line-soft); }

  .fpop-search input {
    width: 100%; height: 28px; padding: 0 8px;
    border: 1px solid var(--line); border-radius: var(--radius-sm);
    background: var(--surface); color: var(--ink); font: inherit; font-size: var(--fs-xs);
  }

  .fpop-search input:focus { outline: none; border-color: var(--accent); }

  .fpop-list { flex: 1; overflow-y: auto; padding: 4px 0; }

  .fpop-item {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 10px; cursor: pointer; white-space: nowrap;
  }

  .fpop-item:hover { background: var(--surface-2); }
  .fpop-item input { width: 14px; height: 14px; flex: 0 0 auto; accent-color: var(--navy-800); }
  .fpop-item span { overflow: hidden; text-overflow: ellipsis; }
  .fpop-item[hidden] { display: none; }

  .fpop-foot {
    display: flex; gap: 6px; padding: 8px; border-top: 1px solid var(--line-soft);
  }

  .fpop-foot button {
    flex: 1; height: 26px; border: 1px solid var(--line); border-radius: var(--radius-sm);
    background: var(--surface); color: var(--ink-soft);
    font: inherit; font-size: var(--fs-xs); cursor: pointer;
  }

  .fpop-foot button:hover { background: var(--surface-2); }

  /* โหมดกรองที่เซิร์ฟเวอร์ต้องกด "ตกลง" ถึงจะโหลดหน้าใหม่ — ทำให้เด่นกว่าปุ่มอื่น */
  .fpop-foot button.is-go { border-color: var(--navy-800); background: var(--navy-800); color: #fff; }
  .fpop-foot button.is-go:hover { background: var(--navy-900); }

  /* 3 ปุ่มใส่แถวเดียวไม่พอ — เลือกทั้งหมด/ล้างที่เลือกทั้งหมด อยู่แถวบน · ตกลงเต็มแถวล่าง */
  .fpop-foot.is-two-row { flex-wrap: wrap; }
  .fpop-foot button.is-wide { flex: 1 0 100%; }

  /* บรรทัดสรุปผลการกรอง — วางใต้ตาราง */
  .filter-note { padding: 7px 2px 0; color: var(--muted); font-size: var(--fs-xs); }
  .filter-note[hidden] { display: none; }
</style>

<script>
  'use strict';

  (function () {
    document.addEventListener('DOMContentLoaded', function () {
      // 🔴 ต้องรับทั้ง 2 โหมด — ตารางที่กรองที่เซิร์ฟเวอร์ไม่ได้ใส่ data-filterable
      document.querySelectorAll('table.tbl[data-filterable], table.tbl[data-filter-server]').forEach(setup);
    });

    /**
     * ห่อข้อความของหัวคอลัมน์ไว้ใน .th-in แล้วคืนกล่องนั้นให้เอาปุ่มกรองไปใส่ต่อ
     *
     * 🔴 ต้องย้าย data-i18n จาก <th> ลงไปที่ <span> ข้อความด้วย (บั๊กจริง 2026-09-11)
     *    ตัวแปลภาษาสั่ง `el.textContent = คำแปล` กับทุก [data-i18n]
     *    ถ้าปล่อย data-i18n ไว้ที่ <th> พอสลับภาษาทีไร ลูกทั้งหมดใน <th> ถูกล้างทิ้ง
     *    **ปุ่มกรองของทุกตารางในระบบหายหมด** (วัดจริง: ก่อนสลับ 5 ปุ่ม · หลังสลับ 0 ปุ่ม)
     *    ย้ายลงไปที่ span แล้ว ตัวแปลจะเขียนทับแค่ข้อความ ปุ่มอยู่คนละชั้นจึงรอด
     */
    function wrapHead(th) {
      var wrap = document.createElement('span');
      wrap.className = 'th-in';

      var label = document.createElement('span');

      while (th.firstChild) { label.appendChild(th.firstChild); }

      ['data-i18n', 'data-loc-th', 'data-loc-en'].forEach(function (name) {
        if (th.hasAttribute(name)) {
          label.setAttribute(name, th.getAttribute(name));
          th.removeAttribute(name);
        }
      });

      wrap.appendChild(label);
      th.appendChild(wrap);

      return wrap;
    }

    function setup(table) {
      var head = table.tHead;
      var body = table.tBodies[0];
      if (!head || !body || head.rows.length === 0) { return; }

      // ตารางที่กรองที่เซิร์ฟเวอร์ ใช้คนละเส้นทางกันทั้งหมด
      if (table.hasAttribute('data-filter-server')) { return serverMode(table, head); }

      var headRow = head.rows[head.rows.length - 1];
      var cols = Array.prototype.slice.call(headRow.cells);

      // แถวว่าง ("ยังไม่มีข้อมูล") ไม่ใช่ข้อมูลจริง อย่าเอามากรอง
      var rows = Array.prototype.filter.call(body.rows, function (r) {
        return !r.querySelector('td.empty');
      });

      if (rows.length === 0) { return; }

      // ค่าที่เลือกไว้ของแต่ละคอลัมน์ — ว่าง = ไม่กรองคอลัมน์นั้น
      var picked = cols.map(function () { return null; });
      var note = document.createElement('p');
      note.className = 'filter-note';
      note.hidden = true;
      (table.closest('.tbl-wrap') || table).insertAdjacentElement('afterend', note);

      cols.forEach(function (th, index) {
        if (th.hasAttribute('data-no-filter')) { return; }

        // ห่อข้อความเดิมไว้ เพื่อวางปุ่มต่อท้ายโดยไม่ทำลาย data-i18n ของหัวคอลัมน์
        var wrap = wrapHead(th);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'fbtn';
        btn.innerHTML = '&#9660;';   // ▼ แบบเดียวกับ Excel
        btn.setAttribute('aria-label', 'กรองคอลัมน์นี้');
        btn.setAttribute('aria-haspopup', 'true');

        wrap.appendChild(btn);   // wrapHead ใส่ wrap เข้า th ให้แล้ว ตรงนี้แค่เติมปุ่ม
        th.classList.add('has-filter');

        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          openPop(btn, index);
        });
      });

      /** ค่าที่ไม่ซ้ำของคอลัมน์นี้ (เอาเฉพาะแถวที่ผ่านตัวกรองคอลัมน์อื่น เหมือน Excel) */
      function valuesOf(index) {
        var seen = {};
        var out = [];

        rows.forEach(function (row) {
          if (!matches(row, index)) { return; }

          var text = cellText(row, index);
          if (seen[text]) { return; }

          seen[text] = true;
          out.push(text);
        });

        return out.sort(function (a, b) { return a.localeCompare(b, 'th'); });
      }

      function cellText(row, index) {
        var cell = row.cells[index];
        var text = cell ? (cell.textContent || '').replace(/\s+/g, ' ').trim() : '';

        return text === '' ? '—' : text;
      }

      /** แถวนี้ผ่านตัวกรองไหม — ข้ามคอลัมน์ skip ได้ (ใช้ตอนสร้างรายการค่า) */
      function matches(row, skip) {
        for (var i = 0; i < picked.length; i++) {
          if (i === skip || picked[i] === null) { continue; }
          if (picked[i].indexOf(cellText(row, i)) === -1) { return false; }
        }

        return true;
      }

      function apply() {
        var shown = 0;

        rows.forEach(function (row) {
          var ok = matches(row, -1);
          row.hidden = !ok;
          if (ok) { shown++; }
        });

        var on = picked.some(function (p) { return p !== null; });

        note.hidden = !on;
        note.textContent = on
          ? (window.BMS.lang() === 'en'
            ? 'Showing ' + shown + ' of ' + rows.length + ' rows'
            : 'แสดง ' + shown + ' จาก ' + rows.length + ' แถว')
          : '';

        cols.forEach(function (th, i) {
          var b = th.querySelector('.fbtn');
          if (b) { b.classList.toggle('is-on', picked[i] !== null); }
        });

        /*
          บอกให้หน้าที่ใช้ตารางนี้รู้ว่ากรองเสร็จแล้ว เหลือแถวไหนบ้าง
          🔴 หน้าที่มียอดรวมต้องคิดใหม่ตามแถวที่เหลือ ไม่งั้นตัวเลขไม่ตรงกับที่เห็น
        */
        table.dispatchEvent(new CustomEvent('table:filtered', {
          bubbles: true,
          detail: { shown: shown, total: rows.length, rows: rows.filter(function (r) { return ! r.hidden; }) },
        }));
      }

      // ── แผงเลือกค่า — มีตัวเดียวในหน้า สร้างใหม่ทุกครั้งที่กด ──
      var pop = null;
      var popBtn = null;

      function closePop() {
        if (pop) { pop.remove(); pop = null; popBtn = null; }
      }

      /*
        🔴 .fpop เป็น position: fixed — วางครั้งเดียวแล้วเลื่อนหน้าจอ แผงจะค้างกลางจอ
           ดูเหมือนแผงไหลตามเมาส์ลงมา แทนที่จะอยู่ติดหัวคอลัมน์ (เจ้าของแจ้ง 2026-09-11)
           จึงคำนวณตำแหน่งใหม่ทุกครั้งที่เลื่อน · ปุ่มพ้นจอเมื่อไหร่ก็ปิดแผงไป
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
        if (pop && !pop.contains(e.target)) { closePop(); }
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closePop(); }
      });

      function openPop(btn, index) {
        var reopen = pop && pop.dataset.col === String(index);
        closePop();
        if (reopen) { return; }

        var values = valuesOf(index);
        var chosen = picked[index];

        pop = document.createElement('div');
        pop.className = 'fpop';
        pop.dataset.col = String(index);
        pop.setAttribute('role', 'dialog');

        var en = window.BMS.lang() === 'en';

        var search = document.createElement('div');
        search.className = 'fpop-search';
        var box = document.createElement('input');
        box.type = 'search';
        box.placeholder = en ? 'Search' : 'ค้นหา';
        search.appendChild(box);

        var list = document.createElement('div');
        list.className = 'fpop-list';

        values.forEach(function (val) {
          var item = document.createElement('label');
          item.className = 'fpop-item';

          var tick = document.createElement('input');
          tick.type = 'checkbox';
          tick.value = val;
          tick.checked = chosen === null || chosen.indexOf(val) !== -1;

          var text = document.createElement('span');
          text.textContent = val;
          text.title = val;

          item.append(tick, text);
          list.appendChild(item);
        });

        box.addEventListener('input', function () {
          var term = box.value.trim().toLowerCase();

          Array.prototype.forEach.call(list.children, function (item) {
            item.hidden = term !== '' && item.textContent.toLowerCase().indexOf(term) === -1;
          });
        });

        var foot = document.createElement('div');
        foot.className = 'fpop-foot';

        var all = document.createElement('button');
        all.type = 'button';
        all.textContent = en ? 'Select all' : 'เลือกทั้งหมด';

        var none = document.createElement('button');
        none.type = 'button';
        none.textContent = en ? 'Clear all' : 'ล้างที่เลือกทั้งหมด';

        foot.append(all, none);
        pop.append(search, list, foot);
        document.body.appendChild(pop);

        // วางแผงใต้ปุ่ม แล้วดึงกลับถ้าล้นขอบขวา — ตามปุ่มไปทุกครั้งที่เลื่อนหน้าจอ
        popBtn = btn;
        place();

        function readTicks() {
          var ticks = Array.prototype.slice.call(list.querySelectorAll('input'));
          var on = ticks.filter(function (t) { return t.checked; }).map(function (t) { return t.value; });

          // ติ๊กครบทุกค่า = ไม่ได้กรองคอลัมน์นี้
          picked[index] = on.length === ticks.length ? null : on;
          apply();
        }

        list.addEventListener('change', readTicks);

        all.addEventListener('click', function () {
          list.querySelectorAll('input').forEach(function (t) { t.checked = true; });
          readTicks();
        });

        none.addEventListener('click', function () {
          list.querySelectorAll('input').forEach(function (t) { t.checked = false; });
          readTicks();
        });

        // โฟกัสแบบไม่เลื่อนหน้าจอ ไม่งั้นหน้ากระโดดแล้วแผงหลุดจากหัวคอลัมน์
        box.focus({ preventScroll: true });
      }
    }

    /* ═══════════════════════════════════════════════════════════════
       โหมดกรองที่เซิร์ฟเวอร์ — ใช้กับตารางที่แบ่งหน้า

       ต่างจากโหมด JavaScript ตรงที่
         · รายการค่าที่เลือกได้มาจาก **ทั้งชุดข้อมูล** (เซิร์ฟเวอร์ส่งมาให้)
         · กดตกลงแล้ว **โหลดหน้าใหม่** พร้อม `?f[คีย์]=ค่า|ค่า` และย้อนกลับไปหน้าแรกเสมอ
           (กรองแล้วยังค้างอยู่หน้า 7 ทั้งที่ผลลัพธ์เหลือ 2 หน้า จะเจอจอว่าง)
       ═══════════════════════════════════════════════════════════════ */
    function serverMode(table, head) {
      var holder = document.querySelector('[data-filter-options]');
      if (!holder) { return; }

      var data;

      try { data = JSON.parse(holder.textContent || '{}'); } catch (err) { return; }

      var options = data.options || {};
      var picked = data.picked || {};
      /*
        🔴 ค่าที่เลือกได้ของบางหน้าใช้เวลาอ่านจาก ERP นาน จึงส่งมาแค่ชื่อคอลัมน์ก่อน (data.keys + data.url)
           ปุ่มขึ้นทันที · กดครั้งแรกถึงจะดึงค่า แล้วจำไว้ใช้ทั้งหน้า
      */
      var lazyKeys = data.keys || [];
      var lazyUrl = data.url || null;
      var loading = null;
      var loadOptions = function () {
        if (!lazyUrl) { return Promise.resolve(options); }
        loading = loading || fetch(lazyUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
          .then(function (res) { return res.ok ? res.json() : { options: {} }; })
          .then(function (body) {
            Object.keys(body.options || {}).forEach(function (key) { options[key] = body.options[key]; });

            return options;
          })
          .catch(function () { return options; });

        return loading;
      };
      var url = new URL(window.location.href);
      var headRow = head.rows[head.rows.length - 1];
      var pop = null;

      Array.prototype.forEach.call(headRow.cells, function (th) {
        var key = th.getAttribute('data-filter-key');
        if (!key || (!options[key] && lazyKeys.indexOf(key) === -1)) { return; }

        var wrap = wrapHead(th);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'fbtn' + (picked[key] ? ' is-on' : '');
        btn.innerHTML = '&#9660;';
        btn.setAttribute('aria-label', 'กรองคอลัมน์นี้');
        btn.setAttribute('aria-haspopup', 'true');

        wrap.appendChild(btn);
        th.appendChild(wrap);
        th.classList.add('has-filter');

        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          if (options[key]) { return openServerPop(btn, key); }

          // ครั้งแรกของหน้า: ดึงค่าที่เลือกได้ก่อน · ระหว่างรอทำปุ่มให้รู้ว่ากำลังโหลด
          btn.classList.add('is-busy');
          loadOptions().then(function () {
            btn.classList.remove('is-busy');
            if (options[key]) { openServerPop(btn, key); }
          });
        });
      });

      var popBtn = null;

      function closePop() { if (pop) { pop.remove(); pop = null; popBtn = null; } }

      // 🔴 แผงเป็น position: fixed ต้องตามปุ่มไปทุกครั้งที่เลื่อน ไม่งั้นค้างกลางจอ (ดูโหมด JS)
      function place() {
        if (! pop || ! popBtn) { return; }

        var at = popBtn.getBoundingClientRect();

        if (at.bottom < 0 || at.top > window.innerHeight) { return closePop(); }

        pop.style.top = (at.bottom + 4) + 'px';
        pop.style.left = Math.max(8, Math.min(at.left - 8, window.innerWidth - pop.offsetWidth - 8)) + 'px';
      }

      window.addEventListener('scroll', place, true);
      window.addEventListener('resize', place);

      document.addEventListener('click', function (e) {
        if (pop && !pop.contains(e.target)) { closePop(); }
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closePop(); }
      });

      /** ยิง URL ใหม่พร้อมค่าที่เลือก — null = เอาตัวกรองคอลัมน์นี้ออก */
      function go(key, values) {
        var next = new URL(url.href);

        if (values === null) {
          next.searchParams.delete('f[' + key + ']');
        } else {
          next.searchParams.set('f[' + key + ']', values.join('|'));
        }

        next.searchParams.delete('page');   // กรองแล้วต้องกลับไปหน้าแรกเสมอ
        window.location.assign(next.href);
      }

      function openServerPop(btn, key) {
        var reopen = pop && pop.dataset.col === key;
        closePop();
        if (reopen) { return; }

        var en = window.BMS.lang() === 'en';
        var list4 = options[key] || [];
        var chosen = picked[key] || null;

        pop = document.createElement('div');
        pop.className = 'fpop';
        pop.dataset.col = key;
        pop.setAttribute('role', 'dialog');

        var search = document.createElement('div');
        search.className = 'fpop-search';
        var box = document.createElement('input');
        box.type = 'search';
        box.placeholder = en ? 'Search' : 'ค้นหา';
        search.appendChild(box);

        var list = document.createElement('div');
        list.className = 'fpop-list';

        list4.forEach(function (o) {
          var item = document.createElement('label');
          item.className = 'fpop-item';

          var tick = document.createElement('input');
          tick.type = 'checkbox';
          tick.value = o.v;
          tick.checked = chosen === null || chosen.indexOf(o.v) !== -1;

          var text = document.createElement('span');
          text.textContent = en ? o.e : o.t;
          text.title = text.textContent;

          item.append(tick, text);
          list.appendChild(item);
        });

        box.addEventListener('input', function () {
          var term = box.value.trim().toLowerCase();

          Array.prototype.forEach.call(list.children, function (item) {
            item.hidden = term !== '' && item.textContent.toLowerCase().indexOf(term) === -1;
          });
        });

        var foot = document.createElement('div');
        foot.className = 'fpop-foot is-two-row';

        var all = document.createElement('button');
        all.type = 'button';
        all.textContent = en ? 'Select all' : 'เลือกทั้งหมด';

        var none = document.createElement('button');
        none.type = 'button';
        none.textContent = en ? 'Clear all' : 'ล้างที่เลือกทั้งหมด';

        var ok = document.createElement('button');
        ok.type = 'button';
        ok.className = 'is-go is-wide';
        ok.textContent = en ? 'Apply' : 'ตกลง';

        // ติ๊ก/ล้างทั้งหมดเป็นการ "เลือกในแผง" เฉยๆ ยังไม่โหลดหน้าใหม่จนกว่าจะกดตกลง
        all.addEventListener('click', function () {
            list.querySelectorAll('input').forEach(function (i) { i.checked = true; });
        });

        none.addEventListener('click', function () {
            list.querySelectorAll('input').forEach(function (i) { i.checked = false; });
        });

        foot.append(all, none, ok);
        pop.append(search, list, foot);
        document.body.appendChild(pop);

        popBtn = btn;
        place();

        ok.addEventListener('click', function () {
          var ticks = Array.prototype.slice.call(list.querySelectorAll('input'));
          var on = ticks.filter(function (t) { return t.checked; }).map(function (t) { return t.value; });

          // ติ๊กครบทุกค่า = ไม่ได้กรองคอลัมน์นี้
          go(key, on.length === ticks.length ? null : on);
        });

        // โฟกัสแบบไม่เลื่อนหน้าจอ ไม่งั้นหน้ากระโดดแล้วแผงหลุดจากหัวคอลัมน์
        box.focus({ preventScroll: true });
      }
    }
  })();
</script>
