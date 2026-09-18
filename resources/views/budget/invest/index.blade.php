@extends('layouts.app')

@section('title', 'ของบประมาณ / Budget request')

@push('page-style')
  <style>
    :root { --page-max: 1180px; }

    /*
      ชื่อรายการยาวเกินให้ตัดด้วย ... — ตารางจะได้ไม่ยืดจนอ่านคอลัมน์อื่นไม่ทัน
      🔴 ต้องบังคับที่ <td> ด้วย ไม่ใช่แค่ div ข้างใน — ไม่งั้น td ยืดตามข้อความอยู่ดี
    */
    .tbl td.col-name { max-width: 280px; }
    .cell-title { max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /*
      ── หัวการ์ด: ของบประมาณ › หมวดที่เลือก (เจ้าของสั่ง 2026-09-17) ──
      เลือกหมวดมาจากหน้าคั่นแล้ว หัวการ์ดต้องบอกว่ากำลังอยู่หมวดไหน
    */
    .inv-head { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; }
    .inv-head h3 { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .inv-sep { color: var(--line-dark); font-weight: 400; }
    .inv-group { min-width: 0; color: var(--navy-900); }
    .inv-acts { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-left: auto; }
    .inv-acts .btn { min-height: 36px; }
    .inv-off {
      padding: 4px 10px; border-radius: var(--radius-sm);
      background: var(--warn-soft); color: var(--warn); font-size: var(--fs-xs); font-weight: 600;
    }

    /* โค้ดกลุ่มหน้าร่างที่ยังไม่มีเลข — ชุดสีเดียวกับการ์ด จับคู่ด้วยสายตาได้ */
    .row-code {
      display: inline-block; margin-right: 6px; padding: 1px 7px;
      border: 1px solid var(--accent); border-radius: var(--radius-sm);
      background: var(--accent-soft); color: var(--navy-800);
      font-size: var(--fs-xs); font-weight: 700; letter-spacing: .05em;
    }
  </style>
@endpush

@section('content')
  {{--
    ═══════════ หน้าตารางของบประมาณ ═══════════
    🔴 เข้ามาจากหน้าคั่นเลือกกลุ่มเสมอ (?group=) — ไม่มีพารามิเตอร์ controller จะส่งหน้าคั่นแทน
  --}}
  @php
    $groupOn = $currentGroup !== null;
    $canCreate = $groupOn && $currentGroup->active;
  @endphp
  <section class="card">
    <div class="card-head inv-head">
      <h3>
        <span data-i18n="fn.budget.invest">ของบประมาณ</span>
        <span class="inv-sep" aria-hidden="true">›</span>
        @if ($groupOn)
          {{-- 🔴 ไม่มีป้ายโค้ดท้ายชื่อแล้ว (เจ้าของสั่ง 2026-09-17) — โค้ดอยู่ในเลขที่เอกสารในตารางแล้ว --}}
          <span class="inv-group" data-loc-th="{{ $currentGroup->name_th }}" data-loc-en="{{ $currentGroup->name_en }}">{{ $currentGroup->name_th }}</span>
        @else
          <span class="inv-group" data-i18n="budget.pick.allGroups">ทุกหมวด</span>
        @endif
      </h3>

      <div class="inv-acts">
        @if ($groupOn && ! $currentGroup->active)
          <span class="inv-off" data-i18n="budget.pick.disabledHere">หมวดนี้ปิดใช้งานแล้ว — เสนอรายการใหม่ไม่ได้</span>
        @endif

        {{-- กลับไปหน้าคั่น — ทุกหมวดต้องเลือกหมวดก่อนถึงจะเสนอรายการใหม่ได้ --}}
        <a class="btn btn-quiet" href="{{ route('budget.invest.index') }}">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
          <span data-i18n="budget.pick.change">เปลี่ยนหมวด</span>
        </a>

        @if ($canCreate)
          <a class="btn" href="{{ route('budget.invest.create', ['group' => $currentGroup->id]) }}">
            <span data-i18n="budget.invest.newPlus">+ เสนอรายการใหม่</span>
          </a>
        @endif
      </div>
    </div>

    <div class="card-body" style="display:grid;gap:14px">
      {{--
        🔴 หน้านี้ไม่มีแถบแท็บหมวด (เจ้าของสั่งให้กระชับ 2026-09-17)
           เลือกหมวดมาจากหน้าคั่นแล้ว · เปลี่ยนหมวดด้วยปุ่ม "เปลี่ยนหมวด" บนหัวการ์ด
           อีก 3 หน้า (สถานะการดำเนินการ · ลงทะเบียน · ประวัติ) ยังมีแท็บตามเดิม
      --}}
      {{-- สรุปหัวตาราง — หน้านี้เป็นกล่องของผู้ขอ จึงมีช่อง "ร่าง" ด้วย --}}
      @include('budget.partials.summary', ['summary' => $summary, 'showDraft' => true])

      {{--
        🔴 เอาช่องค้นหาออกแล้ว (เจ้าของสั่ง 2026-09-10)
           ค้นหาเลขที่/ชื่อ/แผนก ใช้ตัวกรองรายคอลัมน์ในตารางได้อยู่แล้ว ไม่ต้องมี 2 ทาง
        เลือกสถานะแล้วส่งฟอร์มเลย ไม่ต้องมีปุ่มค้นหาให้กดซ้ำ
        🔴 พาหมวดที่เลือกติดไปด้วย "เสมอ" รวมทุกหมวด (all)
           หน้านี้ไม่มี ?group= = หน้าคั่นเลือกหมวด — ถ้าไม่ส่งไป เปลี่ยนสถานะแล้วเด้งกลับหน้าคั่น
      --}}
      <form method="GET" class="bar">
        {{--
          🔴 ตัวกรองหมวด (เจ้าของสั่ง 2026-09-17) — เลือกแล้วโหลดหมวดนั้นทันที
             เป็นช่องเดียวกับที่พา ?group= ติดไปกับตัวกรองสถานะ จะได้ไม่มี 2 ที่ให้หลุดจากกัน
             หน้านี้ไม่มี ?group= = หน้าคั่นเลือกหมวด จึงต้องส่งค่าไปเสมอ แม้แต่ "ทุกหมวด" (all)
        --}}
        <select class="sel" name="group" style="width:200px" onchange="this.form.submit()">
          @foreach ($groupTabs['tabs'] as $tab)
            <option value="{{ $tab['key'] }}" @selected($tab['key'] === $groupTabs['picked'])
                    @if ($tab['i18n']) data-i18n="{{ $tab['i18n'] }}" @else data-loc-th="{{ $tab['th'] }}" data-loc-en="{{ $tab['en'] }}" @endif>{{ $tab['th'] }}</option>
          @endforeach
        </select>
        <select class="sel" name="status" style="width:180px" onchange="this.form.submit()">
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
              <th class="txt-left" data-filter-key="title" data-i18n="budget.itemTitle">ชื่อเอกสาร</th>
              <th data-filter-key="dept" data-i18n="budget.dept">แผนก</th>
              <th class="col-money" data-filter-key="amount" data-i18n="budget.proposedAmount">วงเงินที่เสนอ</th>
              <th class="col-no" data-filter-key="status" data-i18n="budget.status">สถานะ</th>
              <th class="col-detail" data-no-filter data-i18n="budget.timeline">สถานะทั้งหมด</th>
              <th class="col-no" data-no-filter data-i18n="budget.tl.actedAt">วันที่ดำเนินการ</th>
              <th class="col-act" data-no-filter data-i18n="access.tbl.manage">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rows as $row)
              <tr>
                {{-- เลขแดงบอกว่าใบนี้มีเรื่องค้างที่เรายังไม่ได้อ่าน (เจ้าของสั่ง 2026-09-07) --}}
                <td class="col-seq">
                  <span class="seq-n">
                    {{ $rows->firstItem() + $loop->index }}
                    @include('layouts.partials.row-badge', ['docNo' => $row->doc_no])
                  </span>
                </td>
                {{--
                  🔴 ร่างยังไม่มีเลขที่ — เลขออกตอนกดส่ง (เจ้าของสั่ง 2026-09-17)
                     ใบที่ออกเลขแล้วมีโค้ดกลุ่มอยู่ในเลขที่เอง จึงแปะป้ายโค้ดเฉพาะร่าง
                     (ไม่แยกเป็นคอลัมน์ — ตารางนี้กว้างเต็มพื้นที่อยู่แล้ว เพิ่มอีกคอลัมน์ต้องเลื่อนขวา)
                --}}
                <td class="col-no">
                  @if (! $row->hasNumber() && $row->group)
                    <span class="row-code" title="{{ $row->group->name_th }}"
                          data-loc-title-th="{{ $row->group->name_th }}" data-loc-title-en="{{ $row->group->name_en }}">{{ $row->group->code }}</span>
                  @endif
                  @include('budget.partials.doc-no', ['no' => $row->doc_no])
                </td>
                <td>{{ $row->fiscal_year }}</td>
                <td class="col-name txt-left"><div class="cell-title" title="{{ $row->title }}">{{ $row->title }}</div></td>
                <td>{{ $row->dept_name ?: $row->dept_code }}</td>

                <td class="col-money txt-right"><span class="money money-strong">{{ number_format($row->amount, 2) }}</span></td>
                <td>
                  @include('budget.partials.status', ['code' => $row->approval_status, 'labels' => $statuses])
                </td>
                <td class="col-detail">
                  <button type="button" class="link-btn" data-modal-open="tl-{{ $row->id }}"
                          data-i18n="budget.timeline.open">ดูเส้นทาง</button>
                </td>
                {{--
                  🔴 เวลาที่ "คนดูหน้านี้" ทำอะไรกับใบนี้ (เจ้าของสั่ง 2026-09-10)
                     หน้านี้เป็นกล่องของผู้ขอ สิ่งที่เขาทำคือ "กดส่งเรื่อง"
                     ยังเป็นร่าง = ยังไม่ได้ทำอะไร ขึ้นขีดกลาง
                --}}
                <td class="col-no">{{ $row->submitted_at?->format('d/m/Y H:i') ?: '—' }}</td>
                <td class="col-act">
                  @if ($row->canDelete())
                    {{-- ร่าง = แก้ไขได้ · รออนุมัติ = เข้าไปดู/ลบได้ แต่แก้ไม่ได้ --}}
                    <a class="btn btn-quiet" href="{{ route('budget.invest.edit', $row) }}"
                       data-i18n="{{ $row->isDraft() ? 'budget.edit' : 'budget.manage' }}">{{ $row->isDraft() ? 'แก้ไข' : 'จัดการ' }}</a>
                  @else
                    <a class="btn btn-quiet" href="{{ route('budget.doc.show', $row) }}"
                       data-i18n="budget.view">ดูเอกสาร</a>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="10" class="empty" data-i18n="budget.invest.none">ยังไม่มีข้อเสนอขอตั้งงบ</td></tr>
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
