@extends('layouts.app')

@section('title', 'ข้อมูลหลัก / Master data')

@push('page-style')
  <style>
    :root { --page-max: 940px; }

    .tbl-wrap { overflow-x: auto; }
    .tbl-wrap .tbl { min-width: 560px; }
    .tbl td { vertical-align: middle; }

    .set-name { color: var(--navy-900); font-weight: 700; }
    .col-n { width: 1%; white-space: nowrap; text-align: right; }
    .col-src { width: 1%; white-space: nowrap; }
    .col-go { width: 1%; white-space: nowrap; text-align: right; }

    .num { color: var(--ink); font-variant-numeric: tabular-nums; }
    .soft { color: var(--muted); }
    .link-btn {
      padding: 0; border: 0; background: transparent; color: var(--accent);
      font: inherit; font-size: var(--fs-sm); cursor: pointer;
      text-decoration: underline; text-underline-offset: 2px;
    }
    .link-btn:hover { color: var(--navy-800); }

    /* ── หน้าต่างซ้อน ── */
    .modal-wrap {
      position: fixed; inset: 0; z-index: var(--z-drawer);
      display: grid; place-items: center; padding: 24px;
      background: rgb(8 18 40 / 46%);
    }
    .modal-wrap[hidden] { display: none; }
    .modal {
      display: flex; flex-direction: column;
      width: min(980px, 100%); max-height: min(86vh, 780px); max-height: min(86dvh, 780px);
      border-radius: var(--radius-lg); background: var(--surface); box-shadow: var(--shadow-lg);
    }
    .modal-head {
      display: flex; align-items: center; gap: 10px; flex: 0 0 auto;
      padding: 13px 16px; border-bottom: 1px solid var(--line-soft);
    }
    .modal-title { flex: 1; min-width: 0; color: var(--navy-900); font-weight: 700; }
    .modal-x {
      display: grid; place-items: center; width: 28px; height: 28px; flex: 0 0 auto;
      border: 0; border-radius: var(--radius-sm); background: transparent; color: var(--muted); cursor: pointer;
    }
    .modal-x:hover { background: var(--surface-2); color: var(--ink); }
    .modal-body { flex: 1 1 auto; overflow: auto; padding: 0 16px; }
    .modal-tools { position: sticky; top: 0; z-index: 1; padding: 12px 0; background: var(--surface); }
    .modal-foot {
      display: flex; align-items: center; gap: 10px; flex: 0 0 auto;
      padding: 12px 16px; border-top: 1px solid var(--line-soft);
    }
    .modal-foot .spacer { flex: 1; }

    /* ── รูปพนักงาน — ไม่มีกรอบ กดขยายได้ ── */
    .avatar {
      width: 34px; height: 34px; flex: 0 0 auto; padding: 0;
      display: grid; place-items: center; overflow: hidden;
      border: 0; border-radius: 50%;
      background: var(--surface-2); color: var(--navy-800);
      font-size: var(--fs-xs); font-weight: 700; cursor: zoom-in;
      transition: transform .14s var(--ease);
    }
    .avatar:hover, .avatar:focus-visible { transform: translateY(-1px); }
    .avatar img { width: 100%; height: 100%; object-fit: cover; }

    .emp-name { color: var(--navy-900); font-weight: 700; }
    .emp-tbl td { padding: 7px 10px; }
    .emp-tbl .col-photo { width: 1%; }
    .emp-tbl .code { color: var(--ink-soft); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .msg { padding: 22px; text-align: center; color: var(--muted); font-size: var(--fs-sm); }
  </style>
@endpush

@section('content')
  <section class="card">
    <div class="card-head">
      <h3 data-i18n="master.title">ข้อมูลหลัก (Master Data)</h3>
    </div>

    <div class="card-body" style="display:grid;gap:12px">
      <p class="soft" data-i18n="master.scopeNote">
        เฟสปัจจุบันโฟกัสที่โมดูลงบประมาณ — ชุดข้อมูลที่ทำเครื่องหมาย "โมดูลในอนาคต" ยังไม่ได้สรุปว่าจะเอาอย่างไร
      </p>
      <div class="tbl-wrap">
        <table class="tbl" data-filterable>
          <thead>
            <tr>
              <th class="col-seq" data-no-filter data-i18n="tbl.seq">ลำดับ</th>
              <th data-i18n="master.set">ชุดข้อมูล</th>
              <th class="col-src" data-i18n="master.owner">เจ้าของข้อมูล</th>
              <th class="col-n" data-i18n="master.count">จำนวน</th>
              <th class="col-go"></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($sets as $set)
              <tr>
                <td class="col-seq">{{ $loop->iteration }}</td>
                <td><span class="set-name" data-i18n="{{ $set['key'] }}">{{ $set['th'] }}</span></td>

                <td class="col-src">
                  @if ($set['source'] === 'mirror')
                    <span class="soft" data-i18n="master.fromInsight">มิเรอร์จาก Insight</span>
                  @else
                    <span class="soft" data-i18n="master.ownedByBms">SBMS ดูแลเอง</span>
                  @endif
                </td>

                <td class="col-n">
                  @if (! empty($set['future']))
                    {{-- 🔴 ยังไม่ confirm ว่าจะเอาอย่างไร — ห้ามทำให้ดูเหมือนตกลงแล้ว --}}
                    <span class="soft" data-i18n="master.future">โมดูลในอนาคต</span>
                  @elseif ($set['count'] === null)
                    <span class="soft" data-i18n="master.notReady">ยังไม่ได้ทำ</span>
                  @else
                    <span class="num">{{ number_format($set['count']) }}</span>
                  @endif
                </td>

                <td class="col-go">
                  @if ($set['id'] === 'employee')
                    {{-- ทะเบียนพนักงานมีเป็นพันคน เปิดเป็นหน้าต่างซ้อนที่โหลดทีละหน้าแทน (เจ้าของสั่ง 2026-09-03) --}}
                    <button type="button" class="link-btn" data-open-employees data-i18n="master.open">เปิดดู</button>
                  @elseif ($set['route'])
                    <a class="link-btn" href="{{ route($set['route']) }}" data-i18n="master.open">เปิดดู</a>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </section>

  {{-- ═══════════ หน้าต่าง: ทะเบียนพนักงาน (เฉพาะคนที่ยังทำงานอยู่) ═══════════ --}}
  <div class="modal-wrap" data-emp-modal hidden role="dialog" aria-modal="true">
    <div class="modal">
      <div class="modal-head">
        <span class="modal-title">
          <span data-i18n="master.employee">ทะเบียนพนักงาน</span>
          <span class="soft" style="font-weight:400" data-i18n="master.activeOnly">เฉพาะที่ยังทำงานอยู่</span>
        </span>
        <button type="button" class="modal-x" data-emp-close aria-label="ปิด" data-i18n-aria="common.close">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" aria-hidden="true">
            <path d="M18 6 6 18M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="modal-tools">
          <label class="field">
            <span data-i18n="master.empSearch">ค้นหา (รหัส · ชื่อ · แผนก · ตำแหน่ง)</span>
            <input class="input" type="search" autocomplete="off" data-emp-search
                   placeholder="พิมพ์เพื่อค้นหา" data-i18n-placeholder="master.empSearchHint">
          </label>
        </div>

        <table class="tbl emp-tbl">
          <thead>
            <tr>
              <th class="col-seq" data-i18n="tbl.seq">ลำดับ</th>
              <th class="col-photo"></th>
              <th data-i18n="master.code">รหัส</th>
              <th data-i18n="master.name">ชื่อ</th>
              <th data-i18n="master.position">ตำแหน่ง</th>
              <th data-i18n="master.department">แผนก</th>
              <th data-i18n="master.branch">สาขา</th>
              <th data-i18n="master.hireDate">วันเริ่มงาน</th>
            </tr>
          </thead>
          <tbody data-emp-rows></tbody>
        </table>

        <p class="msg" data-emp-msg hidden></p>
      </div>

      <div class="modal-foot">
        <button type="button" class="btn btn-quiet" data-emp-more hidden data-i18n="master.loadMore">โหลดเพิ่ม</button>
        <span class="spacer"></span>
        <span class="soft"><b data-emp-shown>0</b> / <b data-emp-total>0</b> <span data-i18n="master.people">คน</span></span>
        <button type="button" class="btn btn-quiet" data-emp-close data-i18n="common.close">ปิด</button>
      </div>
    </div>
  </div>

  <script>
    'use strict';

    (function () {
      var URL_EMP = @json(route('master.employees'));
      var modal = document.querySelector('[data-emp-modal]');
      if (!modal) { return; }

      var body = modal.querySelector('[data-emp-rows]');
      var msg = modal.querySelector('[data-emp-msg]');
      var more = modal.querySelector('[data-emp-more]');
      var search = modal.querySelector('[data-emp-search]');
      var shown = modal.querySelector('[data-emp-shown]');
      var total = modal.querySelector('[data-emp-total]');
      var page = 1, term = '', loading = false, count = 0, opener = null;

      function esc(text) {
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);

        return d.innerHTML;
      }

      // รูปต้องกดขยาย/ซูมได้ตามกฎของโปรเจค — ตัวขยายผูก event ที่ document อยู่แล้ว
      function avatar(p) {
        if (!p.photo) { return '<span class="avatar">' + esc(p.initial || '?') + '</span>'; }

        var name = esc(p.name_th + ' (' + p.code + ')');

        return '<button type="button" class="avatar" data-zoomable' +
          ' data-zoom-src="' + esc(p.photo) + '" data-zoom-alt="' + name + '" title="' + name + '">' +
          '<img src="' + esc(p.photo) + '" alt="" loading="lazy"></button>';
      }

      // ลำดับนับต่อเนื่องข้ามการกด "โหลดเพิ่ม" — ส่งเลขแถวเข้ามาเอง
      function rowHtml(p, i) {
        return '<tr>' +
          '<td class="col-seq">' + i + '</td>' +
          '<td class="col-photo">' + avatar(p) + '</td>' +
          '<td><span class="code">' + esc(p.code) + '</span></td>' +
          '<td><span class="emp-name" data-loc-th="' + esc(p.name_th) + '" data-loc-en="' + esc(p.name_en) + '">' + esc(p.name_th) + '</span></td>' +
          '<td><span data-loc-th="' + esc(p.job_th) + '" data-loc-en="' + esc(p.job_en || p.job_th) + '">' + esc(p.job_th) + '</span></td>' +
          '<td><span data-loc-th="' + esc(p.dept_th) + '" data-loc-en="' + esc(p.dept_en || p.dept_th) + '">' + esc(p.dept_th) + '</span></td>' +
          '<td><span data-loc-th="' + esc(p.branch_th) + '" data-loc-en="' + esc(p.branch_en || p.branch_th) + '">' + esc(p.branch_th) + '</span></td>' +
          '<td>' + esc(p.hire_date || '—') + '</td>' +
        '</tr>';
      }

      function load(reset) {
        if (loading) { return; }
        loading = true;

        if (reset) { page = 1; count = 0; body.innerHTML = ''; }

        msg.hidden = false;
        msg.textContent = window.BMS ? window.BMS.t('master.loading') : 'กำลังโหลด…';

        fetch(URL_EMP + '?q=' + encodeURIComponent(term) + '&page=' + page, {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin'
        })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            var rows = d.rows || [];
            body.insertAdjacentHTML('beforeend', rows.map(function (p, i) {
              return rowHtml(p, count + i + 1);
            }).join(''));
            count += rows.length;

            shown.textContent = count;
            total.textContent = (d.total || 0).toLocaleString();
            more.hidden = ! d.has_more;
            msg.hidden = count > 0;

            if (count === 0) {
              msg.hidden = false;
              msg.textContent = window.BMS ? window.BMS.t('master.empNone') : 'ไม่พบพนักงานที่ค้นหา';
            }

            // ชื่อ/แผนกมาจากฐานข้อมูล ต้องให้ตัวสลับภาษาเห็นด้วย
            if (window.BMS && window.BMS.refresh) { window.BMS.refresh(); }
            loading = false;
          })
          .catch(function () {
            msg.hidden = false;
            msg.textContent = window.BMS ? window.BMS.t('master.loadFailed') : 'โหลดข้อมูลไม่สำเร็จ';
            loading = false;
          });
      }

      function open() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        if (! body.children.length) { load(true); }
        search.focus();
      }

      function close() {
        modal.hidden = true;
        document.body.style.overflow = '';
        if (opener) { opener.focus(); }
      }

      document.querySelectorAll('[data-open-employees]').forEach(function (btn) {
        btn.addEventListener('click', function () { opener = btn; open(); });
      });

      modal.querySelectorAll('[data-emp-close]').forEach(function (btn) {
        btn.addEventListener('click', close);
      });

      // กดพื้นหลังนอกกล่องถึงจะปิด — กดรูปเพื่อซูมต้องไม่ปิดหน้าต่าง
      modal.addEventListener('click', function (e) {
        if (e.target === modal) { close(); }
      });

      document.addEventListener('keydown', function (e) {
        // กำลังดูรูปขยายอยู่ ให้ Esc ปิดแค่รูป (กติกาเดียวกับตัวเลือกพนักงาน)
        if (document.querySelector('[data-zoom-overlay]:not([hidden])')) { return; }

        if (e.key === 'Escape' && ! modal.hidden) { close(); }
      });

      more.addEventListener('click', function () { page += 1; load(false); });

      var timer = null;
      search.addEventListener('input', function () {
        window.clearTimeout(timer);
        // หน่วงไว้ก่อนยิง — พิมพ์เร็วๆ จะได้ไม่ยิงทุกตัวอักษร
        timer = window.setTimeout(function () {
          term = search.value.trim();
          load(true);
        }, 280);
      });
    })();
  </script>
@endsection
