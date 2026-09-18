@extends('layouts.app')

@section('title', 'ประวัติการใช้งานระบบ / Activity log')

@push('page-style')
  <style>
    :root { --page-max: 1180px; }

    /* ── แท็บสลับมุมมอง — ไม่มีกรอบ ใช้เส้นใต้บอกว่าอยู่แท็บไหน ── */
    .tabs { display: flex; gap: 18px; border-bottom: 1px solid var(--line-soft); }
    .tab {
      padding: 0 0 9px; margin-bottom: -1px;
      border-bottom: 2px solid transparent;
      color: var(--muted); font-size: var(--fs-sm);
    }
    .tab:hover { color: var(--ink); }
    .tab.is-on { color: var(--navy-900); font-weight: 700; border-bottom-color: var(--accent); }

    /* ── ตัวเลขสรุปด้านบน — ไม่มีกรอบ ใช้เส้นคั่นแทน ── */
    .sums { display: flex; flex-wrap: wrap; gap: 0; }
    .sum { padding: 0 20px; border-left: 1px solid var(--line-soft); }
    .sum:first-child { padding-left: 0; border-left: 0; }
    .sum b { display: block; color: var(--navy-900); font-size: 19px; font-variant-numeric: tabular-nums; }
    .sum span { color: var(--muted); font-size: var(--fs-xs); }
    .sum-warn b { color: var(--danger); }

    .bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .bar .spacer { flex: 1; }
    .bar .soft { color: var(--muted); font-size: var(--fs-xs); }

    /* ── ตาราง ── */
    /* กรอบเลื่อนอยู่ที่ธีมกลางแล้ว */
    .tbl-wrap .tbl { min-width: 860px; }   /* พอดีกับพื้นที่จริง ไม่ให้ช่องกรองคอลัมน์สุดท้ายโดนตัด */
    .tbl td { vertical-align: top; }

    /*
      แถวตัวกรองใต้หัวตาราง (เจ้าของสั่ง 2026-09-03)
      🔴 ทุกช่องกว้างเต็มคอลัมน์และสูงเท่ากันหมด — ช่องค้นหาต้องขนาดเดียวกับช่องเหตุการณ์
         จึงคุมด้วย .frow control ตัวเดียว ไม่ตั้งความกว้างรายช่อง
    */
    .frow td { padding: 6px 8px 10px; border-bottom: 1px solid var(--line); background: var(--surface); }
    .frow .fin {
      width: 100%; height: 32px; padding: 0 8px;
      border: 1px solid var(--line); border-radius: var(--radius-sm);
      background: var(--surface); color: var(--ink);
      font: inherit; font-size: var(--fs-sm);
    }
    .frow .fin:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
    .frow .fin::placeholder { color: #9aa6bb; }
    .frow .dates { display: grid; gap: 4px; }

    .when { white-space: nowrap; font-variant-numeric: tabular-nums; color: var(--ink); }
    .when small { display: block; color: var(--muted); font-size: var(--fs-xs); }
    .actor { color: var(--navy-900); font-weight: 700; }
    .actor small { display: block; color: var(--muted); font-weight: 400; font-size: var(--fs-xs); }
    /*
      สีของเหตุการณ์ (เจ้าของสั่ง 2026-09-04)
      🔴 ใช้สีตัวอักษรอย่างเดียว ไม่มีกรอบ ตามกติกาป้ายสถานะของระบบ
    */
    .ev { white-space: nowrap; }
    .ev-fail { color: var(--danger); }
    .ev-in { color: var(--ok); }
    .ev-act { color: var(--accent); }
    .ev-ok { color: var(--ok); font-weight: 700; }     /* อนุมัติ · ผ่าน */
    .ev-no { color: var(--danger); font-weight: 700; } /* ไม่อนุมัติ · ลบ */
    .detail { color: var(--ink-soft); }
    .col-when { width: 118px; }
    .col-who { width: 170px; }
    .col-ev { width: 148px; }
    .col-from { width: 132px; color: var(--muted); font-size: var(--fs-xs); }

    .empty { padding: 26px; text-align: center; color: var(--muted); font-size: var(--fs-sm); }
    .pager nav { display: flex; gap: 4px; flex-wrap: wrap; align-items: center; font-size: var(--fs-sm); }
    /* 🔴 สไตล์ตัวแบ่งหน้าเก่าถูกลบแล้ว — มันทับ .pg ของธีมจนเลขหน้าปัจจุบันมองไม่เห็น */
  </style>
@endpush

@section('content')
  @php
    // จัดกลุ่มสีของเหตุการณ์ — เข้าระบบเขียว · ล้มเหลวแดง · การกระทำน้ำเงิน · เปิดหน้าเทา
    $tone = function (string $event) {
      // 🔴 ผลของเรื่อง อ่านออกจากสีทันที (เจ้าของสั่ง 2026-09-04)
      if (in_array($event, ['invest_approved'], true)) { return 'ev-ok'; }
      if (in_array($event, ['invest_rejected', 'admin_removed', 'invest_deleted'], true)) { return 'ev-no'; }

      if ($event === 'login_failed') { return 'ev-fail'; }
      if (in_array($event, ['login', 'logout'], true)) { return 'ev-in'; }

      return in_array($event, \App\Models\Audit\ActivityLog::actionEvents(), true) ? 'ev-act' : '';
    };

    $hasFilter = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
  @endphp

  <section class="card">
    <div class="card-head">
      <h3 data-i18n="{{ $scope === 'signin' ? 'audit.signinTitle' : 'audit.title' }}">{{ $scope === 'signin' ? 'ประวัติการเข้าสู่ระบบ' : 'ประวัติการใช้งานระบบ' }}</h3>
    </div>

    <div class="card-body" style="display:grid;gap:14px">

      <nav class="tabs">
        <a class="tab {{ $scope === 'all' ? 'is-on' : '' }}"
           href="{{ route('activity.index') }}" data-i18n="audit.tabAll">ทั้งหมด</a>
        <a class="tab {{ $scope === 'signin' ? 'is-on' : '' }}"
           href="{{ route('activity.signins') }}" data-i18n="audit.tabSignin">การเข้าสู่ระบบ</a>
      </nav>

      <div class="bar">
        <div class="sums">
          <span class="sum"><b>{{ number_format($summary['total']) }}</b><span data-i18n="audit.sumTotal">ทั้งหมด</span></span>
          <span class="sum"><b>{{ number_format($summary['today']) }}</b><span data-i18n="audit.sumToday">วันนี้</span></span>
          <span class="sum"><b>{{ number_format($summary['people']) }}</b><span data-i18n="audit.sumPeople">ผู้ใช้</span></span>
          <span class="sum sum-warn"><b>{{ number_format($summary['failed']) }}</b><span data-i18n="audit.sumFailed">เข้าไม่สำเร็จ</span></span>
        </div>

        <span class="spacer"></span>

        @if ($hasFilter)
          <span class="soft">{{ number_format($rows->total()) }} <span data-i18n="audit.found">รายการที่กรองได้</span></span>
        @endif
        <button type="submit" form="log-filter" class="btn" data-i18n="audit.apply">ค้นหา</button>
        <a class="btn btn-quiet" href="{{ url()->current() }}" data-i18n="audit.clear">ล้างตัวกรอง</a>
      </div>

      {{--
        ฟอร์มวางไว้นอกตาราง แล้วช่องกรองในตารางอ้างถึงด้วย attribute `form`
        เพราะวาง <form> คร่อม <tr> ตรงๆ เป็น HTML ที่ไม่ถูกต้อง เบราว์เซอร์จะดึงออกจากตาราง
        กด Enter ในช่องไหนก็ส่งฟอร์มได้ · ส่งเป็น GET จะได้แชร์ลิงก์ผลการกรองได้
      --}}
      <form method="GET" id="log-filter"></form>

      <div class="tbl-wrap">
        {{--
          🔴 ห้ามใส่ data-filterable ที่ตารางนี้
             หน้านี้มีแถวตัวกรองของตัวเองอยู่แล้ว (กรองที่เซิร์ฟเวอร์ ครอบทุกหน้า)
             ตัวกรองแบบ Excel กรองได้เฉพาะแถวในหน้าปัจจุบัน ใส่ซ้อนกันแล้วผลไม่ตรงกัน
        --}}
        <table class="tbl">
          <thead>
            <tr>
              <th class="col-seq" data-i18n="tbl.seq">ลำดับ</th>
              <th class="col-when" data-i18n="audit.when">เวลา</th>
              <th class="col-who" data-i18n="audit.who">ผู้ใช้</th>
              <th class="col-ev" data-i18n="audit.event">เหตุการณ์</th>
              <th data-i18n="audit.detail">รายละเอียด</th>
              <th class="col-from" data-i18n="audit.from">มาจาก</th>
            </tr>

            <tr class="frow">
              {{-- ช่องว่างให้ตรงกับคอลัมน์ลำดับ --}}
              <td class="col-seq"></td>
              <td>
                <div class="dates">
                  <input class="fin" type="date" form="log-filter" name="from" value="{{ $filters['from'] ?? '' }}"
                         title="ตั้งแต่วันที่" data-i18n-title="audit.filterFrom">
                  <input class="fin" type="date" form="log-filter" name="to" value="{{ $filters['to'] ?? '' }}"
                         title="ถึงวันที่" data-i18n-title="audit.filterTo">
                </div>
              </td>

              <td>
                <input class="fin" type="search" form="log-filter" name="who" value="{{ $filters['who'] ?? '' }}"
                       placeholder="รหัส หรือ ชื่อ" data-i18n-placeholder="audit.filterWhoHint">
              </td>

              <td>
                <select class="fin" form="log-filter" name="event">
                  <option value="" data-i18n="audit.allEvents">ทั้งหมด</option>
                  @foreach ($events as $code => $label)
                    <option value="{{ $code }}" @selected(($filters['event'] ?? '') === $code)
                            data-loc-th="{{ $label['th'] }}" data-loc-en="{{ $label['en'] }}">{{ $label['th'] }}</option>
                  @endforeach
                </select>
              </td>

              <td>
                <input class="fin" type="search" form="log-filter" name="detail" value="{{ $filters['detail'] ?? '' }}"
                       placeholder="ค้นในรายละเอียด" data-i18n-placeholder="audit.filterDetailHint">
              </td>

              <td>
                <input class="fin" type="search" form="log-filter" name="ip" value="{{ $filters['ip'] ?? '' }}"
                       placeholder="IP หรือ เบราว์เซอร์" data-i18n-placeholder="audit.filterFromHint">
              </td>
            </tr>
          </thead>

          <tbody>
            @forelse ($rows as $row)
              <tr>
                <td class="col-seq">{{ $rows->firstItem() + $loop->index }}</td>
                <td class="col-when">
                  {{-- แยกวันกับเวลาคนละบรรทัด อ่านง่ายกว่า และไม่ใช้ diffForHumans เพราะมันออกมาเป็นอังกฤษเสมอ --}}
                  <span class="when">
                    {{ $row->created_at?->format('d/m/Y') }}
                    <small>{{ $row->created_at?->format('H:i:s') }}</small>
                  </span>
                </td>

                <td>
                  @if ($row->employee_code)
                    <span class="actor">
                      {{ $row->actor_name ?: $row->employee_code }}
                      <small>{{ $row->employee_code }}</small>
                    </span>
                  @else
                    <span class="detail" data-i18n="audit.anonymous">ยังไม่ได้ล็อกอิน</span>
                  @endif
                </td>

                <td class="col-ev">
                  <span class="ev {{ $tone($row->event) }}"
                        data-loc-th="{{ $row->label('th') }}" data-loc-en="{{ $row->label('en') }}">{{ $row->label('th') }}</span>
                </td>

                <td>
                  <span class="detail"
                        data-loc-th="{{ $row->detail_th }}" data-loc-en="{{ $row->detail_en ?: $row->detail_th }}">{{ $row->detail_th }}</span>
                </td>

                <td class="col-from">
                  {{ $row->ip }}
                  @if ($row->agent)
                    <small style="display:block">{{ $row->agent }}</small>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="empty" data-i18n="audit.none">ยังไม่มีประวัติในช่วงที่เลือก</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if ($rows->hasPages())
        {{ $rows->links() }}
      @endif
    </div>
  </section>
@endsection
