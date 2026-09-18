@extends('layouts.app')

@section('title', 'สถานะการดำเนินการ / Processing status')

@push('page-style')
  <style>
    :root { --page-max: 1180px; }

    /*
      ชื่อรายการยาวเกินให้ตัดด้วย ... — ตารางจะได้ไม่ยืดจนอ่านคอลัมน์อื่นไม่ทัน
      🔴 ต้องบังคับที่ <td> ด้วย ไม่ใช่แค่ div ข้างใน — ไม่งั้น td ยืดตามข้อความอยู่ดี
    */
    .tbl td.col-name { max-width: 280px; }
    .cell-title { max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* บทบาทของเราในเอกสารฉบับนั้น */

    /* ── ช่องติ๊กเลือกเอกสาร ─────────────────────────────────── */
    .col-tick { width: 1%; }
    .col-tick input { width: 15px; height: 15px; accent-color: var(--navy-800); cursor: pointer; }

    /*
      แถบดำเนินการหลายฉบับ — โผล่เมื่อเลือกอย่างน้อย 1 ฉบับ
      เจ้าของสั่ง 2026-09-03: ติ๊กเลือกแล้วขึ้น 2 ปุ่ม อนุมัติ(เขียว) / ไม่อนุมัติ(แดง)
    */
    .bulk-bar { display: flex; align-items: center; gap: 10px; }
    .bulk-bar[hidden] { display: none; }
    .bulk-count { color: var(--navy-900); font-weight: 700; }


    /* ── ตารางเอกสารในหน้าต่างตรวจก่อนลงนาม ─────────────────── */
    .rv { width: 100%; border-collapse: collapse; font-size: var(--fs-sm); }

    .rv th, .rv td {
      padding: 9px 10px; text-align: center; vertical-align: top;
      border-bottom: 1px solid var(--line-soft); border-right: 1px solid var(--line-soft);
    }

    .rv th:last-child, .rv td:last-child { border-right: 0; }
    .rv th { background: var(--navy-800); color: #fff; font-size: var(--fs-xs); white-space: nowrap; }
    .rv .txt-left { text-align: left; }
    .rv .txt-right { text-align: right; }

    /* หัวคอลัมน์จัดกึ่งกลางเสมอ เหมือนตารางอื่นทั้งระบบ (เจ้าของสั่ง 2026-09-10) */
    .rv thead th,
    .rv thead th.txt-left,
    .rv thead th.txt-right { text-align: center; }

    /* ไฟล์แนบในหน้าต่าง — เรียงลงมาเป็นแถว */
    .rv-files { display: grid; gap: 4px; justify-items: start; }

    .rv-file {
      display: inline-flex; align-items: center; gap: 6px;
      color: var(--navy-900); font-size: var(--fs-xs); text-decoration: none;
    }

    .rv-file:hover { text-decoration: underline; }
    .rv-file img { width: 16px; height: 16px; object-fit: contain; }

    .rv-note { display: grid; gap: 5px; justify-items: start; min-width: 210px; }
    .rv-note .input { height: 30px; font-size: var(--fs-xs); }

    .tick-line {
      display: inline-flex; align-items: center; gap: 6px;
      color: var(--ink-soft); font-size: var(--fs-xs); cursor: pointer;
    }

    .tick-line input { width: 14px; height: 14px; accent-color: var(--navy-800); cursor: pointer; }
  </style>
@endpush

@section('content')
  @php
    // 🔴 ต่อท้ายด้วยป้าย "รับทราบ" ของผู้รับทราบ ไม่งั้นช่องสถานะจะโชว์รหัสดิบ ACKNOWLEDGED
    $labels = \App\Models\Budget\Invest::STATUS_LABELS + ['ACKNOWLEDGED' => ['th' => 'รับทราบ', 'en' => 'Acknowledged']];
    $mine = $rows->where('my_state', 'mine');
  @endphp

  {{--
    🔴 เจ้าของสั่งยุบ 3 ตารางเหลือตารางเดียว + ตัวกรองรายคอลัมน์แบบ Excel (2026-09-03)
       เดิมแยกเป็น รอฉันลงนาม / ส่งมาให้ทราบ / ลงนามไปแล้ว — ต้องเลื่อนหาเอกสารทีละกอง
  --}}
  <section class="card">
    <div class="card-head">
      <h3 data-i18n="fn.budget.inbox">สถานะการดำเนินการ</h3>
      <span class="soft" style="margin-left:auto">
        <b data-count>{{ $rows->count() }}</b> <span data-i18n="budget.docs">เรื่อง</span>
      </span>
    </div>

    <div class="card-body" style="display:grid;gap:14px">
      {{-- แท็บกลุ่มเอกสาร (เจ้าของสั่ง 2026-09-17) — สรุปและตารางข้างล่างเดินตามแท็บที่เลือก --}}
      @include('budget.partials.group-tabs', ['groupTabs' => $groupTabs])

      {{-- แถบดำเนินการหลายฉบับ — ซ่อนไว้จนกว่าจะติ๊กเลือก --}}
      @if ($mine->count())
        <div class="bar bulk-bar" id="bulk-bar" hidden>
          <span class="bulk-count"><b id="bulk-n">0</b> <span data-i18n="budget.bulk.picked">ฉบับที่เลือก</span></span>
          <span class="spacer"></span>
          <button type="button" class="btn btn-ok" data-bulk="approve" data-i18n="budget.approve">อนุมัติ</button>
          <button type="button" class="btn btn-danger" data-bulk="reject" data-i18n="budget.rejectFull">ไม่อนุมัติ</button>
        </div>
      @endif

      {{--
        สรุปหัวตาราง + ตัวกรองสถานะ (เจ้าของสั่ง 2026-09-10)
        🔴 นับและกรองด้วย "สถานะของเราเอง" ให้ตรงกับคอลัมน์สถานะในตาราง
           ถ้าใช้สถานะของเอกสาร ตัวเลขข้างบนจะไม่ตรงกับที่เห็นในแถว
        หน้านี้ไม่มีช่อง "ร่าง" เพราะร่างยังไม่ถูกส่ง ไม่มีทางโผล่ในกล่องนี้
      --}}
      @include('budget.partials.summary', ['summary' => $summary])

      <form method="GET" class="bar">
        {{-- 🔴 พาแท็บกลุ่มติดไปด้วย ไม่งั้นเปลี่ยนตัวกรองแล้วเด้งกลับแท็บ "ทั้งหมด" --}}
        @if ($groupTabs['picked'] !== \App\Services\Budget\DocGroupTabs::ALL)
          <input type="hidden" name="group" value="{{ $groupTabs['picked'] }}">
        @endif
        <select class="sel" name="status" style="width:180px" onchange="this.form.submit()">
          <option value="" data-i18n="budget.allStatus">ทุกสถานะ</option>
          @foreach ($statuses as $code => $label)
            <option value="{{ $code }}" @selected(($filters['status'] ?? '') === $code)
                    data-loc-th="{{ $label['th'] }}" data-loc-en="{{ $label['en'] }}">{{ $label['th'] }}</option>
          @endforeach
        </select>

        {{-- แยกเอกสารที่ "ต้องเซ็น" ออกจากที่ "แค่ให้ทราบ" (เจ้าของสั่ง 2026-09-16) --}}
        <select class="sel" name="role" style="width:180px" onchange="this.form.submit()">
          <option value="" data-i18n="budget.allRoles">ทุกบทบาท</option>
          @foreach ($roles as $code => $label)
            <option value="{{ $code }}" @selected(($filters['role'] ?? '') === $code)
                    data-loc-th="{{ $label['th'] }}" data-loc-en="{{ $label['en'] }}">{{ $label['th'] }}</option>
          @endforeach
        </select>
      </form>

      <div class="tbl-wrap">
        <table class="tbl" data-filterable>
          <thead>
            <tr>
              @if ($mine->count())
                <th class="col-tick" data-no-filter>
                  <input type="checkbox" id="tick-all" title="เลือกทั้งหมด"
                         data-i18n-title="budget.bulk.all" aria-label="เลือกทั้งหมด">
                </th>
              @endif
              <th class="col-seq" data-no-filter data-i18n="tbl.seq">ลำดับ</th>
              <th class="col-no" data-i18n="budget.docNo">เลขที่</th>
              <th class="col-no" data-i18n="budget.year">ปีงบ</th>
              <th class="txt-left" data-i18n="budget.itemTitle">ชื่อเอกสาร</th>
              <th data-i18n="budget.dept">แผนก</th>
              <th class="col-money" data-i18n="budget.amount">วงเงิน</th>
              <th class="col-no" data-i18n="budget.status">สถานะ</th>
              {{-- บทบาทของเราในใบนี้ — อยู่ขวาของสถานะ (เจ้าของสั่ง 2026-09-16) --}}
              <th class="col-no" data-i18n="budget.tl.role">บทบาท</th>
              <th class="col-detail" data-no-filter data-i18n="budget.timeline">สถานะทั้งหมด</th>
              <th class="col-no" data-no-filter data-i18n="budget.tl.actedAt">วันที่ดำเนินการ</th>
              <th class="col-act" data-no-filter data-i18n="access.tbl.manage">จัดการ</th>
            </tr>
          </thead>
          <tbody id="rows">
            @forelse ($rows as $row)
              <tr data-state="{{ $row->my_state }}" data-doc="{{ $row->approval_status }}">
                @if ($mine->count())
                  <td class="col-tick">
                    {{-- ติ๊กได้เฉพาะฉบับที่รอเราลงนาม --}}
                    @if ($row->my_state === 'mine')
                      <input type="checkbox" class="tick" value="{{ $row->id }}"
                             aria-label="เลือก {{ $row->doc_no }}">
                    @endif
                  </td>
                @endif
                {{-- เลขแดงบอกว่าใบนี้มีเรื่องค้างที่เรายังไม่ได้อ่าน (เจ้าของสั่ง 2026-09-07) --}}
                <td class="col-seq">
                  <span class="seq-n">
                    {{ $loop->iteration }}
                    @include('layouts.partials.row-badge', ['docNo' => $row->doc_no])
                  </span>
                </td>
                <td><span class="doc-no">{{ $row->doc_no }}</span></td>
                <td>{{ $row->fiscal_year }}</td>
                <td class="col-name txt-left"><div class="cell-title" title="{{ $row->title }}">{{ $row->title }}</div></td>
                <td>{{ $row->dept_name ?: $row->dept_code }}</td>
                <td class="col-money txt-right"><span class="money money-strong">{{ number_format($row->amount, 2) }}</span></td>

                {{-- 🔴 สถานะของ "เรา" ในใบนี้ ไม่ใช่ของทั้งเอกสาร (เจ้าของสั่ง 2026-09-10) --}}
                <td>@include('budget.partials.status', ['code' => $row->my_status, 'labels' => $labels])</td>
                <td class="col-no">
                  @if ($row->my_role)
                    <span data-loc-th="{{ $roles[$row->my_role]['th'] }}"
                          data-loc-en="{{ $roles[$row->my_role]['en'] }}">{{ $roles[$row->my_role]['th'] }}</span>
                  @else
                    {{-- ผู้ดูแลระบบที่เข้ามาดู ไม่มีบทบาทในเอกสารใบนี้ --}}
                    <span class="soft">—</span>
                  @endif
                </td>
                <td class="col-detail">
                  <button type="button" class="link-btn" data-modal-open="tl-{{ $row->id }}"
                          data-i18n="budget.timeline.open">ดูเส้นทาง</button>
                </td>
                {{--
                  🔴 เวลาที่ "คนดูหน้านี้" ลงนามใบนี้ (เจ้าของสั่ง 2026-09-10)
                     ยังไม่ได้ลงนาม = ขึ้นขีดกลาง ไม่ใช่เอาเวลาของเอกสารมาแสดงแทน
                --}}
                <td class="col-no">{{ $row->my_acted_at?->format('d/m/Y H:i') ?: '—' }}</td>
                <td class="col-act">
                  {{--
                    🔴 ใบที่ "ยังไม่ถึงคิว" เปิดอ่านได้ แต่ไม่มีปุ่มอนุมัติในเอกสาร (เจ้าของสั่ง 2026-09-16)
                       บอกเหตุผลไว้ที่ tooltip ไม่งั้นผู้ใช้เปิดเข้าไปแล้วงงว่าทำไมไม่มีปุ่ม
                  --}}
                  <a class="btn {{ $row->my_state === 'mine' ? '' : 'btn-quiet' }}"
                     href="{{ route('budget.doc.show', $row) }}"
                     @if ($row->my_state === 'waiting')
                       title="ยังไม่ถึงคิวของคุณ — เปิดอ่านได้ แต่ยังลงนามไม่ได้"
                       data-i18n-title="budget.appr.waitTurn"
                     @endif
                     data-i18n="{{ $row->my_state === 'mine' ? 'budget.appr.open' : 'budget.view' }}">{{ $row->my_state === 'mine' ? 'เปิดเอกสาร' : 'ดูเอกสาร' }}</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="12" class="empty" data-i18n="budget.appr.none">ยังไม่มีเอกสารที่เกี่ยวข้องกับคุณ</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>

  {{-- ═══════════ หน้าต่างตรวจก่อนลงนามหลายฉบับ ═══════════ --}}
  @if ($mine->count())
    <div class="modal-wrap" data-modal="bulk-modal" hidden role="dialog" aria-modal="true">
      <div class="modal" style="width:min(1040px,100%)">
        <div class="modal-head">
          <span class="modal-title">
            <span id="bulk-title">อนุมัติ</span>
            <span class="soft"><b id="bulk-n2">0</b> <span data-i18n="budget.bulk.picked">ฉบับที่เลือก</span></span>
          </span>
          @include('access.partials.modal-x')
        </div>

        <form method="POST" action="{{ route('budget.approval.bulk') }}" id="bulk-form" style="display:contents">
          @csrf
          <input type="hidden" name="action" id="bulk-action" value="approve">

          <div class="modal-body">
            <p class="soft" id="bulk-hint" style="margin-bottom:10px"></p>

            <div class="tbl-wrap">
              <table class="rv">
                <thead>
                  <tr>
                    <th class="col-seq" data-i18n="tbl.seq">ลำดับ</th>
                    <th data-i18n="budget.docNo">เลขที่</th>
                    <th class="txt-left" data-i18n="budget.itemTitle">ชื่อเอกสาร</th>
                    <th data-i18n="budget.dept">แผนก</th>
                    <th data-i18n="budget.amount">วงเงิน</th>
                    <th data-i18n="budget.preparedAt">วันที่จัดทำ</th>
                    <th data-i18n="budget.proposer">ผู้ขอ</th>
                    <th data-i18n="budget.files">เอกสารแนบ</th>
                    <th id="bulk-note-head">ความเห็น (ถ้ามี)</th>
                  </tr>
                </thead>
                <tbody>
                  {{-- ทุกฉบับที่รอเราลงนาม — ซ่อนแถวที่ไม่ได้ติ๊กตอนเปิดหน้าต่าง --}}
                  @foreach ($mine as $doc)
                    <tr class="rv-row" data-id="{{ $doc->id }}" hidden>
                      <td class="col-seq">{{ $loop->iteration }}</td>
                      <td><span class="doc-no">{{ $doc->doc_no }}</span></td>
                      <td class="txt-left">{{ $doc->title }}</td>
                      <td>{{ $doc->dept_name ?: $doc->dept_code }}</td>

                      <td class="txt-right"><span class="money money-strong">{{ number_format($doc->amount, 2) }}</span></td>
                      <td>{{ $doc->submitted_at?->format('d/m/Y H:i') ?: '—' }}</td>
                      <td><span data-loc-th="{{ $doc->proposerName() }}" data-loc-en="{{ $doc->proposerNameEn() }}">{{ $doc->proposerName() }}</span></td>
                      <td>
                        <span class="rv-files">
                          @forelse ($doc->files as $file)
                            <a class="rv-file" href="{{ $file->url() }}" target="_blank" rel="noopener"
                               title="{{ $file->original_name }} · {{ $file->sizeText() }}">
                              <img src="{{ \App\Support\FileIcon::url($file->original_name) }}" alt="">
                              {{ \Illuminate\Support\Str::limit($file->original_name, 22) }}
                            </a>
                          @empty
                            <span class="soft">—</span>
                          @endforelse
                        </span>
                      </td>
                      <td>
                        <span class="rv-note">
                          {{-- อนุมัติ: ติ๊กก่อนถึงจะขึ้นช่อง · ปฏิเสธ: ช่องขึ้นเลยและบังคับกรอก --}}
                          <label class="tick-line note-tick">
                            <input type="checkbox" class="note-on">
                            <span data-i18n="budget.bulk.addNote">ใส่หมายเหตุ</span>
                          </label>
                          <input class="input note-box" type="text" maxlength="255"
                                 name="notes[{{ $doc->id }}]" hidden disabled>
                        </span>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>

          {{-- 🔴 ยินยอมซ้าย · ยกเลิกขวา --}}
          <div class="modal-foot">
            <button type="button" class="btn" id="bulk-go">อนุมัติ</button>
            <span class="spacer"></span>
            <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.cancel">ยกเลิก</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  @include('budget.partials.timeline', ['rows' => $rows])
  @include('budget.partials.assets')
  @include('access.partials.picker-assets')

  <script>
    'use strict';

    (function () {
      var bar = document.getElementById('bulk-bar');
      if (!bar) { return; }

      var isEn = function () { return window.BMS.lang() === 'en'; };
      var ticks = Array.prototype.slice.call(document.querySelectorAll('.tick'));
      var all = document.getElementById('tick-all');
      var count = document.getElementById('bulk-n');
      var count2 = document.getElementById('bulk-n2');

      var modal = document.querySelector('[data-modal="bulk-modal"]');
      var form = document.getElementById('bulk-form');
      var actionField = document.getElementById('bulk-action');
      var title = document.getElementById('bulk-title');
      var hint = document.getElementById('bulk-hint');
      var go = document.getElementById('bulk-go');
      var noteHead = document.getElementById('bulk-note-head');

      function chosen() {
        return ticks.filter(function (t) { return t.checked && !t.closest('tr').hidden; });
      }

      function sync() {
        var n = chosen().length;
        var usable = ticks.filter(function (t) { return !t.closest('tr').hidden; });

        bar.hidden = n === 0;
        count.textContent = n;

        // ติ๊กบนหัวตารางสะท้อนสถานะจริง
        all.checked = usable.length > 0 && n === usable.length;
        all.indeterminate = n > 0 && n < usable.length;
      }

      all.addEventListener('change', function () {
        ticks.forEach(function (t) {
          if (!t.closest('tr').hidden) { t.checked = all.checked; }
        });

        sync();
      });

      ticks.forEach(function (t) { t.addEventListener('change', sync); });

      // ── เปิดหน้าต่างตรวจก่อนลงนาม ──
      document.querySelectorAll('[data-bulk]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var reject = btn.getAttribute('data-bulk') === 'reject';
          var ids = chosen().map(function (t) { return t.value; });

          if (ids.length === 0) { return; }

          actionField.value = reject ? 'reject' : 'approve';
          title.textContent = reject ? (isEn() ? 'Reject' : 'ไม่อนุมัติ') : (isEn() ? 'Approve' : 'อนุมัติ');
          go.textContent = title.textContent;
          go.className = 'btn ' + (reject ? 'btn-danger' : 'btn-ok');

          noteHead.textContent = reject
            ? (isEn() ? 'Reason (required)' : 'เหตุผลที่ไม่อนุมัติ (บังคับ)')
            : (isEn() ? 'Comment (optional)' : 'ความเห็น (ถ้ามี)');

          hint.textContent = reject
            ? (isEn() ? 'Every document needs a reason. They go back to the proposer.'
              : 'ทุกฉบับต้องกรอกเหตุผล · เอกสารจะจบที่สถานะไม่อนุมัติ')
            : (isEn() ? 'Your signature will be stamped on every document below.'
              : 'ลายเซ็นของคุณจะถูกประทับลงทุกฉบับด้านล่าง');

          count2.textContent = ids.length;

          // โชว์เฉพาะฉบับที่เลือก และตั้งช่องหมายเหตุตามชนิดงาน
          document.querySelectorAll('.rv-row').forEach(function (row) {
            var on = ids.indexOf(row.getAttribute('data-id')) !== -1;
            row.hidden = !on;

            var tick = row.querySelector('.note-tick');
            var box = row.querySelector('.note-box');

            // ปฏิเสธ = ช่องขึ้นเลยและบังคับ · อนุมัติ = ติ๊กก่อนถึงจะขึ้น
            tick.hidden = reject;
            box.hidden = !reject && !row.querySelector('.note-on').checked;
            box.placeholder = reject ? (isEn() ? 'Reason' : 'เหตุผล') : '';

            // ฉบับที่ไม่ได้เลือก หรือช่องที่ยังไม่เปิด ต้องไม่ส่งค่าไปด้วย
            box.disabled = !on || box.hidden;
          });

          modal.hidden = false;
        });
      });

      // ติ๊กแล้วค่อยขึ้นช่องหมายเหตุ (เฉพาะตอนอนุมัติ)
      document.querySelectorAll('.note-on').forEach(function (tick) {
        tick.addEventListener('change', function () {
          var box = tick.closest('.rv-note').querySelector('.note-box');

          box.hidden = !tick.checked;
          box.disabled = !tick.checked;

          if (tick.checked) { box.focus(); } else { box.value = ''; }
        });
      });

      go.addEventListener('click', function () {
        var reject = actionField.value === 'reject';
        var open = Array.prototype.slice.call(document.querySelectorAll('.rv-row')).filter(function (r) {
          return !r.hidden;
        });

        // ปฏิเสธต้องกรอกเหตุผลครบทุกฉบับ
        if (reject) {
          var miss = open.filter(function (row) {
            return row.querySelector('.note-box').value.trim() === '';
          });

          if (miss.length) {
            var box = miss[0].querySelector('.note-box');
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
            box.focus({ preventScroll: true });

            window.BMS.notify({
              kind: 'warn',
              title: isEn() ? 'Reason required on every document' : 'ต้องกรอกเหตุผลให้ครบทุกฉบับ',
              autoClose: 2400,
            });

            return;
          }
        }

        window.BMS.confirm({
          kind: reject ? 'no' : 'ok',
          title: reject
            ? (isEn() ? 'Reject ' + open.length + ' documents?' : 'ไม่อนุมัติ ' + open.length + ' ฉบับ?')
            : (isEn() ? 'Approve ' + open.length + ' documents?' : 'อนุมัติ ' + open.length + ' ฉบับ?'),
          text: reject
            ? (isEn() ? 'They end here — the requesters have to raise new ones.' : 'เอกสารจะจบที่สถานะไม่อนุมัติ ผู้ขอต้องสร้างใบใหม่')
            : (isEn() ? 'Your signature will be stamped.' : 'ลายเซ็นของคุณจะถูกประทับลงเอกสาร'),
          ok: title.textContent,
          onOk: function () {
            // ส่ง id ที่เลือกไปกับฟอร์ม
            form.querySelectorAll('input[name="docs[]"]').forEach(function (el) { el.remove(); });

            open.forEach(function (row) {
              var el = document.createElement('input');
              el.type = 'hidden';
              el.name = 'docs[]';
              el.value = row.getAttribute('data-id');
              form.appendChild(el);
            });

            form.requestSubmit();
          },
        });
      });

      sync();
    })();
  </script>
@endsection
