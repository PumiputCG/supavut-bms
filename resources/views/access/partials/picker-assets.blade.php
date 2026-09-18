{{--
  หน้าตา + การทำงานของตารางสิทธิ์ · ตัวเลือกพนักงาน
  ใช้ร่วมทั้ง 2 หน้าในโมดูลนี้ — ใส่ครั้งเดียวต่อหน้า

  วิธีใช้:  @include('access.partials.picker-assets')
--}}

{{-- 🔴 หน้าต่างซ้อน (modal) ยกไปไว้ตรงกลางแล้ว (2026-09-16) — ทั้งระบบใช้ตัวเดียวกัน --}}
@include('layouts.partials.modal')
<style>
  /* ── ตารางสิทธิ์ ─────────────────────────────────────────── */
  /* กรอบเลื่อนอยู่ที่ธีมกลางแล้ว — ที่นี่ตั้งแค่ความกว้างขั้นต่ำของตารางนี้ */
  .tbl-wrap .tbl { min-width: 620px; }         /* พอดีกับพื้นที่ตอนกางแผงเมนูย่อย ไม่ให้คอลัมน์ "จัดการ" โดนตัด */
  .tbl td { vertical-align: middle; }
  .col-act { width: 1%; white-space: nowrap; text-align: right; }
  /* คอลัมน์ "รายละเอียด" — แยกออกมาเอง จัดกึ่งกลางให้ตรงกับแถวรูป (เจ้าของสั่ง 2026-09-03) */
  .col-detail { width: 1%; white-space: nowrap; text-align: center; }

  .row-title { color: var(--navy-800); font-weight: 700; }

  /*
    🔴 สไตล์ .avatar / .faces ย้ายไปอยู่ที่ layouts/theme.blade.php แล้ว (2026-09-04)
       เพราะรูปพนักงานถูกใช้ข้ามโมดูล หน้าไหนไม่ได้ include ชิ้นส่วนนี้ รูปจะขึ้นเต็มจอ
       (บั๊กจริงที่หน้าเอกสารงบ) — อย่าย้ายกลับมาไว้ที่นี่อีก
  */

  /* ── ข้อความสถานะ — ไม่มีกรอบ ใช้สีบอกอย่างเดียว ─────────── */
  .state { color: var(--ink-soft); font-size: var(--fs-sm); }
  .state-all { color: var(--accent); }
  .state-off { color: var(--danger); }
  .state-muted { color: var(--muted); }

  /* รายการที่คั่นด้วยจุด — ใช้แทนป้ายกรอบ */
  .inline-list { color: var(--ink-soft); font-size: var(--fs-sm); }

  /* ปุ่มแบบข้อความ ไม่มีกรอบ */
  .link-btn {
    padding: 0; border: 0; background: transparent;
    color: var(--accent); font: inherit; font-size: var(--fs-sm);
    cursor: pointer; text-decoration: underline; text-underline-offset: 2px;
  }
  .link-btn:hover { color: var(--navy-800); }

  /* ── ตัวเลือกพนักงาน ─────────────────────────────────────── */
  .picker { display: grid; gap: 12px; }
  .picker-search { display: grid; gap: 8px; }
  /* 🔴 อยู่ในสายเนื้อหาปกติ ไม่ลอยทับ — เพราะ .modal-body มี overflow:auto
     ถ้าลอยแบบ absolute รายการจะโดนกรอบหน้าต่างตัดทิ้ง เลื่อนดูคนท้ายๆ ไม่ได้ */
  .picker-results {
    max-height: 264px; overflow-y: auto;
    border: 1px solid var(--line); border-radius: var(--radius);
    background: var(--surface);
  }
  .picker-results[hidden] { display: none; }

  /* 🔴 แถวผลค้นหาเป็น div ไม่ใช่ button — ข้างในมี 2 ปุ่ม (ดูรูป / เลือกคน) ซ้อน button ไม่ได้ */
  .res-row { display: flex; align-items: center; gap: 10px; padding: 6px 9px; }
  .res-row + .res-row { border-top: 1px solid var(--line-soft); }
  .res-pick {
    display: flex; align-items: center; flex: 1; min-width: 0;
    padding: 4px 6px; border: 0; border-radius: var(--radius-sm);
    background: transparent; font: inherit; text-align: left; cursor: pointer;
  }
  .res-pick:hover:not(:disabled) { background: var(--accent-soft); }
  .res-pick:disabled { opacity: .5; cursor: default; }
  .res-text { display: grid; gap: 1px; min-width: 0; }
  .res-name { color: var(--navy-800); font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .res-meta { color: var(--muted); font-size: var(--fs-xs); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .picker-msg { padding: 12px; color: var(--muted); font-size: var(--fs-sm); text-align: center; }

  .picker-label { color: var(--muted); font-size: var(--fs-xs); }
  .picker-label b { color: var(--navy-800); font-size: var(--fs-sm); }
  .picker-list { display: flex; flex-wrap: wrap; gap: 6px 14px; }
  .picker-empty { color: var(--muted); font-size: var(--fs-xs); }
  .picker-empty[hidden] { display: none; }

  /* คนที่เลือกไว้ — ไม่มีกรอบ */
  .chip { display: inline-flex; align-items: center; gap: 7px; }
  .chip-name { color: var(--ink); font-size: var(--fs-sm); }
  .chip-code { color: var(--muted); font-size: var(--fs-xs); }
  .chip-x {
    display: grid; place-items: center; width: 18px; height: 18px; flex: 0 0 auto;
    border: 0; border-radius: 50%; background: transparent; color: var(--muted); cursor: pointer;
  }
  .chip-x:hover { color: var(--danger); }

  /* ── ติ๊กตำแหน่ง — ไม่มีกรอบ ใช้เส้นคั่นแทน ─────────────── */
  .pos-grid { display: grid; gap: 0 18px; grid-template-columns: repeat(auto-fill, minmax(238px, 1fr)); }
  .pos-item {
    display: flex; align-items: center; gap: 9px;
    padding: 7px 2px; border-bottom: 1px solid var(--line-soft);
    cursor: pointer; font-size: var(--fs-sm);
  }
  .pos-item input { width: 16px; height: 16px; flex: 0 0 auto; accent-color: var(--navy-800); cursor: pointer; }
  .pos-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .pos-code { margin-left: auto; flex: 0 0 auto; color: var(--muted); font-size: var(--fs-xs); }

  /* ── รายชื่อในหน้าต่างรายละเอียด ─────────────────────────
     🔴 ห้ามตั้งชื่อคลาสว่า .who-* ที่นี่ — แถบบนใช้ .who-text อยู่แล้ว
        และมีกฎ `.who-text span { color: var(--on-dark-soft) }` ซึ่งเป็นสีสำหรับพื้นเข้ม
        ถ้าชื่อชนกัน ตัวหนังสือบนพื้นขาวจะกลายเป็นน้ำเงินจางจนอ่านไม่ออก
        (specificity 0,1,1 ชนะ 0,1,0 — บทเรียนเดิมใน CLAUDE.md) */
  .person-list { display: grid; gap: 0; }
  .person-row {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 2px; border-bottom: 1px solid var(--line-soft);
  }
  .person-row:last-child { border-bottom: 0; }
  .person-text { display: grid; gap: 1px; min-width: 0; flex: 1; }
  .person-name { color: var(--navy-900); font-weight: 700; font-size: var(--fs-sm); }
  .person-meta { color: var(--ink-soft); font-size: var(--fs-xs); }
</style>

<script>
  'use strict';


  // ═══ ติ๊กทั้งหมด / ไม่ติ๊กเลย ════════════════════════════════
  (function () {
    document.querySelectorAll('[data-check-group]').forEach(function (group) {
      var boxes = group.querySelectorAll('input[type="checkbox"]');
      var counter = group.querySelector('[data-check-count]');

      function recount() {
        if (counter) { counter.textContent = group.querySelectorAll('input[type="checkbox"]:checked').length; }
      }

      function setAll(value) {
        boxes.forEach(function (b) { b.checked = value; });
        recount();
      }

      var all = group.querySelector('[data-check-all]');
      var none = group.querySelector('[data-check-none]');
      if (all) { all.addEventListener('click', function () { setAll(true); }); }
      if (none) { none.addEventListener('click', function () { setAll(false); }); }
      group.addEventListener('change', recount);
    });
  })();

  // ═══ ตัวเลือกพนักงาน ═══════════════════════════════════════
  (function () {
    // 🔴 ใช้เส้นทางกลางที่ทุกคนที่ล็อกอินเรียกได้ — ของเดิมอยู่หลัง bms.admin คนทั่วไปค้นหาไม่เจอ
    var SEARCH_URL = @json(route('people.search'));

    function esc(text) {
      var d = document.createElement('div');
      d.textContent = text == null ? '' : String(text);

      return d.innerHTML;
    }

    document.querySelectorAll('[data-picker]').forEach(function (picker) {
      var input = picker.querySelector('[data-picker-input]');
      var results = picker.querySelector('[data-picker-results]');
      var list = picker.querySelector('[data-picker-list]');
      var count = picker.querySelector('[data-picker-count]');
      var empty = picker.querySelector('[data-picker-empty]');
      var field = picker.getAttribute('data-picker-field');
      var timer = null;

      // รายชื่อที่เลือกได้ — ว่าง = เลือกได้ทุกคน (กติกา "ไม่ระบุ = เปิด")
      var limitAttr = picker.getAttribute('data-picker-limit');
      var limitTo = limitAttr ? limitAttr.split(',') : null;

      // เลือกได้กี่คน — 1 = เลือกคนใหม่แล้วแทนที่คนเดิม (ช่อง "ผู้เสนอ") · ไม่กำหนด = ไม่จำกัด
      var max = parseInt(picker.getAttribute('data-picker-max'), 10) || 0;

      function allowed(person) {
        return !limitTo || limitTo.indexOf(String(person.employee_code)) !== -1;
      }

      function chosenCodes() {
        return Array.prototype.map.call(list.querySelectorAll('[data-chip]'), function (c) {
          return c.getAttribute('data-chip');
        });
      }

      // โหมดเลือกคนเดียว: เลือกแล้วให้ชื่อคนมาแทนช่องค้นหาไปเลย เหมือนช่องที่กรอกแล้ว
      var searchBox = picker.querySelector('.picker-search');

      function refreshCount() {
        var n = list.querySelectorAll('[data-chip]').length;
        if (count) { count.textContent = n; }
        if (empty) { empty.hidden = n > 0; }

        list.classList.toggle('is-filled', n > 0);

        if (max === 1 && searchBox) {
          searchBox.hidden = n > 0;
          // กดกากบาทเอาคนออก = กลับมาเป็นช่องค้นหาว่างๆ พร้อมให้พิมพ์ต่อได้ทันที
          if (n === 0) { input.value = ''; }
        }
      }

      function closeResults() { results.hidden = true; results.innerHTML = ''; }

      // รูปในผลค้นหาต้องกดขยายได้ — จึงเป็นปุ่มของตัวเอง แยกจากปุ่มเลือกคน
      function avatarHtml(p, extraClass) {
        var cls = 'avatar' + (extraClass ? ' ' + extraClass : '');
        if (!p.photo) { return '<span class="' + cls + '">' + esc(p.initial || '?') + '</span>'; }

        var name = esc(p.name_th + ' (' + p.employee_code + ')');

        return '<button type="button" class="' + cls + '" data-zoomable' +
          ' data-zoom-src="' + esc(p.photo) + '" data-zoom-alt="' + name + '" title="' + name + '">' +
          '<img src="' + esc(p.photo) + '" alt="" loading="lazy"></button>';
      }

      function chipHtml(p) {
        return '<span class="chip" data-chip="' + esc(p.employee_code) + '">' +
          '<input type="hidden" name="' + esc(field) + '" value="' + esc(p.employee_code) + '">' +
          avatarHtml(p, 'avatar-sm') +
          '<span class="chip-name" data-loc-th="' + esc(p.name_th) + '" data-loc-en="' + esc(p.name_en) + '">' + esc(p.name_th) + '</span>' +
          '<span class="chip-code">' + esc(p.employee_code) + '</span>' +
          '<button type="button" class="chip-x" data-chip-remove aria-label="' +
            (window.BMS ? esc(window.BMS.t('access.pick.remove')) : 'เอาออก') + '">' +
            '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"></path></svg>' +
          '</button>' +
        '</span>';
      }

      function render(people) {
        if (!people.length) {
          results.innerHTML = '<p class="picker-msg">' +
            (window.BMS ? window.BMS.t('access.pick.notFound') : 'ไม่พบพนักงานที่ค้นหา') + '</p>';
          results.hidden = false;

          return;
        }

        var taken = chosenCodes();

        results.innerHTML = people.map(function (p) {
          var already = taken.indexOf(p.employee_code) !== -1;

          return '<div class="res-row">' +
            avatarHtml(p) +
            '<button type="button" class="res-pick" data-pick=\'' + esc(JSON.stringify(p)) + '\'' +
              (already ? ' disabled' : '') + '>' +
              '<span class="res-text">' +
                '<span class="res-name" data-loc-th="' + esc(p.name_th) + '" data-loc-en="' + esc(p.name_en) + '">' + esc(p.name_th) + '</span>' +
                '<span class="res-meta">' + esc(p.employee_code) + (p.position ? ' · ' + esc(p.position) : '') +
                  (p.department ? ' · ' + esc(p.department) : '') + '</span>' +
              '</span>' +
            '</button>' +
            (already ? '<span class="state state-muted">' +
              (window.BMS ? window.BMS.t('access.pick.already') : 'เลือกแล้ว') + '</span>' : '') +
          '</div>';
        }).join('');

        // ชื่อคนมาจากฐานข้อมูล ต้องให้ตัวสลับภาษาเห็นด้วย ไม่งั้นสลับเป็นอังกฤษแล้วชื่อไม่เปลี่ยน
        if (window.BMS && window.BMS.refresh) { window.BMS.refresh(); }
        results.hidden = false;
      }

      input.addEventListener('input', function () {
        var term = input.value.trim();
        window.clearTimeout(timer);

        if (term.length < 2) { closeResults(); return; }

        // บอกว่ากำลังค้นอยู่ ไม่งั้นดูเหมือนกดแล้วไม่มีอะไรเกิดขึ้น
        results.innerHTML = '<p class="picker-msg">' +
          (window.BMS.lang() === 'en' ? 'Searching…' : 'กำลังค้นหา…') + '</p>';
        results.hidden = false;

        // หน่วงไว้ก่อนยิง — พิมพ์เร็วๆ จะได้ไม่ยิงทุกตัวอักษร
        timer = window.setTimeout(function () {
          fetch(SEARCH_URL + '?q=' + encodeURIComponent(term), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
          })
            .then(function (r) {
              if (!r.ok) { throw new Error('HTTP ' + r.status); }

              return r.json();
            })
            .then(function (d) { render((d.results || []).filter(allowed)); })
            .catch(function (err) {
              // 🔴 อย่าปิดเงียบ — เคยพลาดเพราะ endpoint คืน 403 แล้วผู้ใช้เห็นแค่ "ไม่ขึ้นอะไรเลย"
              results.innerHTML = '<p class="picker-msg">' +
                (window.BMS.lang() === 'en'
                  ? 'Search failed — ' + err.message
                  : 'ค้นหาไม่สำเร็จ — ' + err.message) + '</p>';
              results.hidden = false;
            });
        }, 260);
      });

      input.addEventListener('keydown', function (e) {
        // Esc ครั้งแรกปิดแค่รายการผลค้นหา ยังไม่ปิดหน้าต่าง
        if (e.key === 'Escape' && !results.hidden) {
          e.stopPropagation();
          closeResults();
        }
      });

      results.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-pick]');
        if (!btn || btn.disabled) { return; }

        var person = JSON.parse(btn.getAttribute('data-pick'));
        if (chosenCodes().indexOf(person.employee_code) !== -1 || !allowed(person)) { return; }

        // เลือกได้คนเดียว = คนใหม่แทนที่คนเก่าทันที ไม่ต้องให้ผู้ใช้ไปกดกากบาทเอง
        if (max === 1) {
          list.querySelectorAll('[data-chip]').forEach(function (c) { c.remove(); });
        }

        list.insertAdjacentHTML('beforeend', chipHtml(person));
        if (window.BMS && window.BMS.refresh) { window.BMS.refresh(); }
        refreshCount();
        picker.dispatchEvent(new CustomEvent('picker:change', { bubbles: true, detail: person }));

        // 🔴 เลือกคนแล้วหน้าต่างต้องค้างอยู่ ให้เลือกคนต่อไปได้เลย (เจ้าของสั่ง 2026-09-03)
        btn.disabled = true;
        input.value = '';
        closeResults();
        input.focus();
      });

      list.addEventListener('click', function (e) {
        var x = e.target.closest('[data-chip-remove]');
        if (!x) { return; }

        x.closest('[data-chip]').remove();
        refreshCount();
        picker.dispatchEvent(new CustomEvent('picker:change', { bubbles: true, detail: null }));
      });

      // กดที่อื่นในหน้าต่างให้ปิดแค่รายการผลค้นหา ไม่ปิดหน้าต่าง
      document.addEventListener('click', function (e) {
        if (!picker.contains(e.target)) { closeResults(); }
      });

      refreshCount();
    });
  })();

  /*
    ยืนยันก่อนถอดสิทธิ์
    🔴 ใช้หน้าต่างกลางจอของระบบ (layouts/partials/dialog) ไม่ใช้ confirm() ของเบราว์เซอร์
       เพราะเจ้าของสั่งไว้ว่าทุก Action ต้องมีหน้าต่างพร้อมอนิเมชัน และปุ่มยินยอมต้องอยู่ซ้าย
  */
  (function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (form.dataset.confirmed === '1') { return; }

        e.preventDefault();
        var en = window.BMS.lang() === 'en';

        window.BMS.confirm({
          kind: 'no',
          title: en ? form.getAttribute('data-confirm-en') : form.getAttribute('data-confirm'),
          ok: en ? 'Remove' : 'ถอดสิทธิ์',
          onOk: function () {
            form.dataset.confirmed = '1';
            form.requestSubmit();
          },
        });
      });
    });
  })();
</script>
