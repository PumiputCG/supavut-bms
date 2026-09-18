@extends('layouts.app')

@section('title', 'ประวัติงบประมาณ / Budget history')

@push('page-style')
  <style>
    :root { --page-max: 1240px; }

    /* ชื่อรายการยาวเกินให้ตัดด้วย ... — บังคับที่ <td> ด้วย ไม่งั้น td ยืดตามข้อความ */
    .tbl td.col-name { max-width: 300px; }
    .cell-title { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }


  </style>
@endpush

@section('content')
  @php $labels = \App\Models\Budget\Invest::STATUS_LABELS; @endphp

  <section class="card">
    <div class="card-head">
      <h3 data-i18n="fn.budget.history">ประวัติงบประมาณ</h3>
      <span class="soft" style="margin-left:auto" data-i18n="budget.hist.note">ทุกฉบับ ทุกสถานะ · ไว้ตรวจย้อนหลัง</span>
    </div>

    <div class="card-body" style="display:grid;gap:16px">
      {{-- แท็บกลุ่มเอกสาร (เจ้าของสั่ง 2026-09-17) — สรุปและตารางข้างล่างเดินตามแท็บที่เลือก --}}
      @include('budget.partials.group-tabs', ['groupTabs' => $groupTabs])
      @include('budget.partials.summary', [
        'summary' => $summary,
        'showDraft' => true,
        // ฝั่งบัญชี — คั่นเส้นแยกจากฝั่งการอนุมัติ (เจ้าของสั่ง 2026-09-10)
        'showRegister' => true,
        'amount' => [
          'key' => 'budget.hist.amount',
          'th' => 'วงเงินที่อนุมัติรวม (บาท)',
          'value' => $summary['amount'],
        ],
      ])

      <form method="GET" class="bar">
        {{-- 🔴 พาแท็บกลุ่มติดไปด้วย ไม่งั้นเปลี่ยนตัวกรองแล้วเด้งกลับแท็บ "ทั้งหมด" --}}
        @if ($groupTabs['picked'] !== \App\Services\Budget\DocGroupTabs::ALL)
          <input type="hidden" name="group" value="{{ $groupTabs['picked'] }}">
        @endif
        <select class="sel" name="year" style="width:170px" onchange="this.form.submit()">
          <option value="" data-i18n="budget.hist.allYears">ทุกปีงบประมาณ</option>
          @foreach ($years as $y)
            <option value="{{ $y }}" @selected(($filters['year'] ?? null) == $y)>{{ $y }}</option>
          @endforeach
        </select>
      </form>

      <div class="tbl-wrap">
        {{-- 🔴 กรองที่เซิร์ฟเวอร์ — ตารางนี้แบ่งหน้า ถ้ากรองด้วย JS จะได้แค่หน้าที่เปิดอยู่ --}}
        <table class="tbl" data-filter-server>
          <thead>
            <tr>
              {{--
                🔴 เรียงคอลัมน์ใหม่ (เจ้าของสั่ง 2026-09-10)
                   ปีงบ → เลขที่ Invest → รายละเอียดเอกสาร → สถานะ → ฝั่งงบ (เลขที่ Budget + การลงทะเบียน)
                   จัดกลุ่มของฝั่ง Invest กับฝั่ง Budget ให้อยู่คนละข้าง อ่านไล่จากซ้ายไปขวาได้
              --}}
              <th class="col-seq" data-no-filter data-i18n="tbl.seq">ลำดับ</th>
              <th class="col-no" data-filter-key="year" data-i18n="budget.year">ปีงบ</th>
              <th class="col-no" data-filter-key="doc" data-i18n="budget.investNo">เลขที่ Invest</th>
              {{-- เลขที่ Budget อยู่ติดกับเลขที่ Invest — คู่เลขที่ของเรื่องเดียวกัน (เจ้าของสั่ง 2026-09-10) --}}
              <th class="col-no" data-no-filter data-i18n="budget.budgetNo">เลขที่ Budget</th>
              <th class="txt-left" data-filter-key="title" data-i18n="budget.itemTitle">ชื่อเอกสาร</th>
              <th data-filter-key="dept" data-i18n="budget.dept">แผนก</th>
              <th class="col-money" data-filter-key="amount" data-i18n="budget.proposedAmount">วงเงินที่เสนอ</th>
              <th class="col-no" data-filter-key="status" data-i18n="budget.status">สถานะ</th>
              {{-- บัญชีคีย์เข้า ERP หรือยัง — ใบที่ยังไม่อนุมัติจะยังไม่มีงบ จึงขึ้นขีดกลาง --}}
              <th class="col-no" data-filter-key="register" data-i18n="budget.register">การลงทะเบียน</th>
              <th class="col-detail" data-no-filter data-i18n="budget.timeline">สถานะทั้งหมด</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rows as $row)
              <tr>
                <td class="col-seq">{{ $rows->firstItem() + $loop->index }}</td>
                <td>{{ $row->fiscal_year }}</td>
                <td>@include('budget.partials.doc-no', ['no' => $row->doc_no])</td>
                <td class="col-no">
                  @if ($row->budget)
                    <a class="doc-no" href="{{ route('budget.list.show', $row->budget) }}">{{ $row->budget->doc_no }}</a>
                  @else
                    <span class="soft">—</span>
                  @endif
                </td>
                <td class="col-name txt-left"><div class="cell-title" title="{{ $row->title }}">{{ $row->title }}</div></td>
                <td>{{ $row->dept_name ?: $row->dept_code }}</td>

                <td class="col-money txt-right"><span class="money money-strong">{{ number_format($row->amount, 2) }}</span></td>
                <td>@include('budget.partials.status', ['code' => $row->approval_status, 'labels' => $labels])</td>
                <td class="col-no">
                  @if ($row->budget)
                    @include('budget.partials.status', [
                      'code' => $row->budget->budget_status,
                      'labels' => \App\Models\Budget\Budget::STATUS_LABELS,
                    ])
                  @else
                    <span class="soft">—</span>
                  @endif
                </td>
                <td class="col-detail">
                  <button type="button" class="link-btn" data-modal-open="tl-{{ $row->id }}"
                          data-i18n="budget.timeline.open">ดูเส้นทาง</button>
                </td>
              </tr>
            @empty
              <tr><td colspan="11" class="empty" data-i18n="budget.hist.none">ยังไม่มีเอกสารในระบบ</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if ($rows->hasPages())
        {{ $rows->links() }}
      @endif
    </div>
  </section>

  @include('budget.partials.timeline', ['rows' => $rows])
  @include('budget.partials.assets')
  @include('access.partials.picker-assets')
  @include("layouts.partials.table-filter-data")
@endsection
