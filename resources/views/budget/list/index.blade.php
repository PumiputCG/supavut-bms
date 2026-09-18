@extends('layouts.app')

@section('title', 'ลงทะเบียนงบประมาณ / Budget registration')

@push('page-style')
  <style>:root { --page-max: 1180px; }

    /* ชื่องบยาวเกินให้ตัดด้วย ... — บังคับที่ <td> ด้วย ไม่งั้น td ยืดตามข้อความ */
    .tbl td.col-name { max-width: 300px; }
    .cell-title { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }</style>
@endpush

@section('content')
  <section class="card">
    <div class="card-head">
      <h3 data-i18n="fn.budget.approved">ลงทะเบียนงบประมาณ</h3>
    </div>

    <div class="card-body" style="display:grid;gap:14px">

      {{--
        🔴 เฟสนี้แสดงแค่ "วงเงินที่อนุมัติ"
           ยังไม่แสดง รอตัดจ่าย / ใช้จริง / คงเหลือ / % ใช้งบ เพราะยังไม่ confirm ว่าจะตัดงบตอน PR / PO / Payment
      --}}
      {{-- แท็บกลุ่มเอกสาร (เจ้าของสั่ง 2026-09-17) — สรุปและตารางข้างล่างเดินตามแท็บที่เลือก --}}
      @include('budget.partials.group-tabs', ['groupTabs' => $groupTabs])
      {{--
        แถบสรุป — หน้านี้เป็นคิวงานของบัญชี จึงนับเฉพาะฝั่งการลงทะเบียน
        (ทุกใบในหน้านี้อนุมัติครบแล้ว ตัวเลขฝั่งอนุมัติจึงไม่บอกอะไร)
      --}}
      @include('budget.partials.summary', [
        'summary' => $summary,
        'showApproval' => false,
        'showRegister' => true,
        'amount' => [
          'key' => $isFiltered ? 'budget.filteredTotal' : 'budget.allTotal',
          'th' => $isFiltered ? 'รวมวงเงินตามที่กรอง' : 'รวมวงเงินทั้งหมด',
          'value' => $pageTotal,
        ],
      ])

      {{--
        🔴 เอาตัวกรองปีงบ/ค้นหาออกหมดแล้ว (เจ้าของสั่ง 2026-09-10)
           ตัวกรองรายคอลัมน์ในหัวตารางทำได้อยู่แล้ว ไม่ต้องมี 2 ทาง
        เหลือตัวกรองเดียว = สถานะการลงทะเบียน เพราะเป็นสิ่งที่บัญชีใช้คัดงานจริง
      --}}
      <form method="GET" class="bar">
        {{-- 🔴 พาแท็บกลุ่มติดไปด้วย ไม่งั้นเปลี่ยนตัวกรองแล้วเด้งกลับแท็บ "ทั้งหมด" --}}
        @if ($groupTabs['picked'] !== \App\Services\Budget\DocGroupTabs::ALL)
          <input type="hidden" name="group" value="{{ $groupTabs['picked'] }}">
        @endif
        <select class="sel" name="status" style="width:190px" onchange="this.form.submit()">
          <option value="" data-i18n="budget.allStatus">ทุกสถานะ</option>
          @foreach ($statuses as $code => $label)
            <option value="{{ $code }}" @selected(($filters['status'] ?? '') === $code)
                    data-loc-th="{{ $label['th'] }}" data-loc-en="{{ $label['en'] }}">{{ $label['th'] }}</option>
          @endforeach
        </select>
      </form>

      <div class="tbl-wrap">
        {{-- 🔴 กรองที่เซิร์ฟเวอร์ — ตารางนี้แบ่งหน้า ถ้ากรองด้วย JS จะได้แค่หน้าที่เปิดอยู่ --}}
        <table class="tbl" data-filter-server>
          <thead>
            <tr>
              <th class="col-seq" data-no-filter data-i18n="tbl.seq">ลำดับ</th>
              <th class="col-no" data-filter-key="doc" data-i18n="budget.docNo">เลขที่</th>
              <th class="col-no" data-filter-key="year" data-i18n="budget.year">ปีงบ</th>
              <th class="txt-left" data-filter-key="title" data-i18n="budget.itemTitle">ชื่องบ</th>
              <th data-filter-key="dept" data-i18n="budget.dept">แผนก</th>
              <th class="col-money" data-filter-key="amount" data-i18n="budget.approvedAmount">วงเงินที่อนุมัติ</th>
              <th class="col-no" data-i18n="budget.approvedAt">วันที่อนุมัติ</th>
              <th class="col-no" data-no-filter data-i18n="budget.status">สถานะ</th>
              {{-- คอลัมน์ใหม่ — บัญชีคีย์เข้า ERP หรือยัง (เจ้าของสั่ง 2026-09-10) --}}
              <th class="col-no" data-filter-key="status" data-i18n="budget.register">การลงทะเบียน</th>
              {{-- เส้นทางเอกสารทั้งเส้น ตั้งแต่ผู้ขอจนถึงการลงทะเบียน (เจ้าของสั่ง 2026-09-10) --}}
              <th class="col-detail" data-no-filter data-i18n="budget.timeline">สถานะทั้งหมด</th>
              @if ($canManage)
                <th class="col-no" data-no-filter data-i18n="budget.markRegistered">ลงทะเบียนแล้ว</th>
              @endif
              <th class="col-act" data-no-filter data-i18n="access.tbl.manage">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rows as $row)
              <tr>
                {{--
                  🔴 เลขแดงกลับมาที่หน้านี้แล้ว (เจ้าของสั่ง 2026-09-10)
                     เดิมเอาออกเพราะถือว่าเป็น "หน้ารายงาน" แต่ตอนนี้เป็นคิวงานของบัญชี
                     ต้องเห็นตั้งแต่ในตารางว่าใบไหนเพิ่งเข้ามาและยังไม่ได้ลงทะเบียน
                     ส่ง 2 เลขที่ เพราะแจ้งเตือนของโมดูลนี้ผูกได้ทั้งเลขที่งบและเลขที่ Invest
                --}}
                <td class="col-seq">
                  {{ $rows->firstItem() + $loop->index }}
                  @include('layouts.partials.row-badge', [
                    'docNo' => [$row->doc_no, $row->invest?->doc_no],
                  ])
                </td>
                {{--
                  🔴 เลขที่ Invest ต้นทางไม่กินคอลัมน์แล้ว (เจ้าของสั่ง 2026-09-10)
                     ย้ายมาเป็นสัญลักษณ์เล็กๆ ข้างเลขที่งบ กดแล้วเปิดหน้าต่างบอก
                --}}
                <td class="col-doc">
                  <span class="doc-no">{{ $row->doc_no }}</span>
                  @if ($row->invest)
                    <button type="button" class="ref-dot" data-modal-open="inv-{{ $row->id }}"
                            title="อ้างอิง Invest" data-i18n-title="budget.investRef"
                            aria-label="อ้างอิง Invest">
                      {{-- ไอคอนลิงก์ วาดเป็น SVG ในหน้า ไม่ดึงจากที่อื่น --}}
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10 13.5a3.5 3.5 0 0 0 5 0l3-3a3.5 3.5 0 0 0-5-5l-1.2 1.2"/>
                        <path d="M14 10.5a3.5 3.5 0 0 0-5 0l-3 3a3.5 3.5 0 0 0 5 5l1.2-1.2"/>
                      </svg>
                    </button>
                  @endif
                </td>
                <td>{{ $row->fiscal_year }}</td>
                <td class="col-name txt-left"><div class="cell-title" title="{{ $row->title }}">{{ $row->title }}</div></td>
                <td>{{ $row->dept_name ?: $row->dept_code }}</td>
                <td class="col-money txt-right"><span class="money money-strong">{{ number_format($row->approved_amount, 2) }}</span></td>
                <td class="col-no soft">{{ $row->approved_at?->format('d/m/Y') ?: '—' }}</td>
                {{-- สถานะการอนุมัติของเอกสาร — หน้านี้มีแต่ใบที่อนุมัติครบแล้ว --}}
                <td>
                  @include('budget.partials.status', [
                    'code' => $row->approval_status,
                    'labels' => \App\Models\Budget\Invest::STATUS_LABELS,
                  ])
                </td>

                {{-- การลงทะเบียนกับ ERP — เหลือง = รอบัญชีคีย์ · เขียว = คีย์แล้ว --}}
                <td>@include('budget.partials.status', ['code' => $row->budget_status, 'labels' => $statuses])</td>

                {{--
                  🔴 หน้าต่างเส้นทางผูกกับ "เอกสาร Invest" ไม่ใช่ก้อนงบ
                     เพราะสายอนุมัติทั้งเส้นอยู่ที่ใบ Invest · งบเป็นแค่ปลายทาง
                  งบที่ไม่มีใบต้นทาง (ข้อมูลเก่า) จึงไม่มีเส้นทางให้ดู
                --}}
                <td class="col-detail">
                  @if ($row->invest)
                    <button type="button" class="link-btn" data-modal-open="tl-{{ $row->invest->id }}"
                            data-i18n="budget.timeline.open">ดูเส้นทาง</button>
                  @else
                    <span class="soft">—</span>
                  @endif
                </td>

                {{--
                  🔴 ติ๊ก = ลงทะเบียนแล้ว · ไม่ติ๊ก = รอลงทะเบียน (เจ้าของสั่ง 2026-09-10)
                     ติ๊กแล้วบันทึกทันที แต่ต้องผ่านหน้าต่างยืนยันก่อนตามกฎของระบบ
                --}}
                @if ($canManage)
                  <td class="col-status">
                    <form method="POST" action="{{ route('budget.list.status', $row) }}" data-register-form>
                      @csrf
                      <input type="hidden" name="budget_status" value="{{ $row->budget_status }}">
                      <label class="reg-tick">
                        <input type="checkbox" data-register-tick
                               @checked($row->isRegistered())
                               aria-label="ลงทะเบียนแล้ว" data-i18n-aria="budget.markRegistered">
                      </label>
                    </form>
                  </td>
                @endif

                <td class="col-act">
                  <a class="btn btn-quiet" href="{{ route('budget.list.show', $row) }}" data-i18n="budget.view">ดูรายละเอียด</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="{{ $canManage ? 12 : 11 }}" class="empty" data-i18n="budget.list.none">ยังไม่มีงบที่อนุมัติแล้ว</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if ($rows->hasPages())
        {{ $rows->links() }}
      @endif
    </div>
  </section>

  {{-- ═══════════ หน้าต่างอ้างอิง Invest ═══════════ --}}
  @foreach ($rows as $row)
    @if ($row->invest)
      <div class="modal-wrap" data-modal="inv-{{ $row->id }}" hidden role="dialog" aria-modal="true">
        <div class="modal" style="width:min(420px,100%)">
          <div class="modal-head">
            <span class="modal-title" data-i18n="budget.investRef">อ้างอิง Invest</span>
            @include('access.partials.modal-x')
          </div>

          <div class="modal-body">
            <dl class="ref-rows">
              {{-- แถวเลขที่งบ — พื้นน้ำเงินตัวอักษรขาว บอกว่ากำลังดูงบก้อนไหน (เจ้าของสั่ง 2026-09-10) --}}
              <div class="ref-row is-head">
                <dt data-i18n="budget.docNo">เลขที่</dt>
                <dd>{{ $row->doc_no }}</dd>
              </div>
              <div class="ref-row">
                <dt data-i18n="budget.investRef">อ้างอิง Invest</dt>
                <dd>
                  {{-- กดเลขที่แล้วไปที่ตัวเอกสาร Invest ได้เลย --}}
                  <a class="doc-no" href="{{ route('budget.doc.show', $row->invest) }}">{{ $row->invest->doc_no }}</a>
                </dd>
              </div>
            </dl>
          </div>

          <div class="modal-foot">
            <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.close">ปิด</button>
          </div>
        </div>
      </div>
    @endif
  @endforeach

  <style>
    /* สัญลักษณ์อ้างอิงข้างเลขที่งบ — เล็ก ไม่แย่งสายตาไปจากเลขที่ */
    .ref-dot {
      display: inline-grid; place-items: center;
      width: 20px; height: 20px; margin-left: 4px; padding: 0;
      vertical-align: middle;
      border: 0; border-radius: 50%;
      background: var(--surface-3); color: var(--muted); cursor: pointer;
    }

    .ref-dot:hover { background: var(--navy-800); color: #fff; }

    .ref-dot svg {
      width: 13px; height: 13px;
      fill: none; stroke: currentColor; stroke-width: 2;
      stroke-linecap: round; stroke-linejoin: round;
    }
  </style>

  {{--
    ═══════════ หน้าต่างเส้นทางเอกสาร ═══════════
    🔴 ชิ้นส่วนนี้รับ "เอกสาร Invest" ไม่ใช่ก้อนงบ จึงต้องแปลงก่อน

    🔴 ยัดก้อนงบที่โหลดมาแล้วกลับเข้าไปในความสัมพันธ์ด้วย (setRelation)
       ไม่งั้นแถวลงทะเบียนจะไปดึงงบใหม่ทีละใบ = คิวรีเพิ่มเท่าจำนวนแถวในหน้า
  --}}
  @php
    $routeDocs = collect($rows->items())
      ->map(fn ($budget) => $budget->invest?->setRelation('budget', $budget))
      ->filter()
      ->values();
  @endphp

  @include('budget.partials.timeline', ['rows' => $routeDocs])

  @include('budget.partials.assets')
  @include('access.partials.picker-assets')

  <style>
    .tbl td.col-status { width: 1%; white-space: nowrap; }

    /*
      🔴 เลขที่งบกับสัญลักษณ์อ้างอิงต้องอยู่บรรทัดเดียวกัน (เจ้าของสั่ง 2026-09-10)
         ไม่งั้นช่องแคบแล้วสัญลักษณ์ตกลงไปบรรทัดล่าง แถวสูงขึ้นโดยไม่จำเป็น
    */
    .tbl td.col-doc { white-space: nowrap; }

    /*
      หน้าต่างอ้างอิง Invest — 2 บรรทัด ป้ายซ้าย ค่าขวา ระยะเท่ากันทั้งคู่
      🔴 ไม่ใช้ .paper-rows ของกระดาษ เพราะป้ายกว้าง 190px ทำให้หน้าต่างเล็กๆ ดูโหว่
    */
    .ref-rows { display: grid; gap: 0; margin: 0; }

    .ref-row {
      display: grid; grid-template-columns: 110px 1fr; align-items: center; gap: 12px;
      padding: 10px 12px;
      border-bottom: 1px solid var(--line-soft);
    }

    .ref-row:last-child { border-bottom: 0; }
    .ref-row > dt { color: var(--ink-soft); font-size: var(--fs-sm); font-weight: 600; }
    .ref-row > dd { margin: 0; color: var(--ink); }

    /* แถวหัว — พื้นน้ำเงิน ตัวอักษรขาวทั้งแถว */
    .ref-row.is-head { background: var(--navy-800); border-bottom: 0; }
    .ref-row.is-head > dt,
    .ref-row.is-head > dd { color: #fff; font-weight: 700; }

    /* ช่องติ๊ก "ลงทะเบียนแล้ว" — ใหญ่พอให้กดง่าย และอยู่กลางช่อง */
    .reg-tick { display: grid; place-items: center; cursor: pointer; }
    .reg-tick input {
      width: 18px; height: 18px; margin: 0;
      accent-color: var(--ok); cursor: pointer;
    }
  </style>

  <script>
    'use strict';

    /*
      ติ๊ก = ลงทะเบียนแล้ว · ไม่ติ๊ก = รอลงทะเบียน (เจ้าของสั่ง 2026-09-10)
      🔴 ทุก Action ต้องมีหน้าต่างยืนยัน (กฎโปรเจค) — ยกเลิกแล้วต้องคืนช่องติ๊กกลับด้วย
    */
    document.querySelectorAll('[data-register-form]').forEach(function (form) {
      var tick = form.querySelector('[data-register-tick]');
      var field = form.querySelector('input[name="budget_status"]');

      tick.addEventListener('change', function () {
        var en = window.BMS.lang() === 'en';
        var on = tick.checked;

        window.BMS.confirm({
          kind: on ? 'ok' : 'warn',
          title: on
            ? (en ? 'Mark as registered?' : 'ยืนยันว่าลงทะเบียนแล้ว?')
            : (en ? 'Back to awaiting registration?' : 'เปลี่ยนกลับเป็นรอลงทะเบียน?'),
          text: on
            ? (en ? 'This budget has been keyed into the ERP.' : 'งบก้อนนี้ถูกคีย์เข้า ERP แล้ว')
            : (en ? 'This budget has not been keyed into the ERP yet.' : 'งบก้อนนี้ยังไม่ได้คีย์เข้า ERP'),
          ok: en ? 'Confirm' : 'ยืนยัน',
          onOk: function () {
            field.value = on ? 'REGISTERED' : 'PENDING_REGISTER';
            form.requestSubmit();
          },
          onCancel: function () { tick.checked = !on; },
        });
      });
    });
  </script>
  @include("layouts.partials.table-filter-data")
@endsection
