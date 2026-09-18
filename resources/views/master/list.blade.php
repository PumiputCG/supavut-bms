@extends('layouts.app')

@section('title', $titleTh.' / Master data')

@push('page-style')
  <style>
    :root { --page-max: 940px; }

    .tbl-wrap { overflow-x: auto; }
    .tbl-wrap .tbl { min-width: 520px; }
    .col-code { width: 1%; white-space: nowrap; }
    .col-n { width: 1%; white-space: nowrap; text-align: right; }
    .code { color: var(--ink-soft); font-variant-numeric: tabular-nums; }
    .num { color: var(--ink); font-variant-numeric: tabular-nums; }
    .soft { color: var(--muted); font-size: var(--fs-xs); }
    .back { color: var(--accent); font-size: var(--fs-sm); text-decoration: underline; text-underline-offset: 2px; }
  </style>
@endpush

@section('content')
  <section class="card">
    <div class="card-head">
      <h3 data-i18n="{{ $titleKey }}">{{ $titleTh }}</h3>
      <a class="back" href="{{ route('master.index') }}" style="margin-left:auto" data-i18n="master.back">กลับไปข้อมูลหลัก</a>
    </div>

    <div class="card-body">
      <div class="tbl-wrap">
        <table class="tbl" data-filterable>
          <thead>
            <tr>
              <th class="col-seq" data-no-filter data-i18n="tbl.seq">ลำดับ</th>
              <th class="col-code" data-i18n="master.code">รหัส</th>
              <th data-i18n="master.name">ชื่อ</th>
              <th class="col-n" data-i18n="master.headcount">จำนวนคน</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $row)
              <tr>
                <td class="col-seq">{{ $loop->iteration }}</td>
                <td class="col-code"><span class="code">{{ $row['code'] }}</span></td>
                <td>
                  <span data-loc-th="{{ $row['name_th'] }}"
                        data-loc-en="{{ $row['name_en'] ?: $row['name_th'] }}">{{ $row['name_th'] }}</span>
                </td>
                <td class="col-n"><span class="num">{{ number_format($row['headcount']) }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      {{-- นับจากพนักงานที่ยังทำงานอยู่ ต้องบอกไว้ ไม่งั้นตัวเลขไม่ตรงกับที่อื่นแล้วงงกันเปล่าๆ --}}
      <p class="soft" style="margin-top:10px">
        <span data-i18n="master.note">นับจากทะเบียนพนักงานที่ยังทำงานอยู่ · แก้ข้อมูลได้ที่ระบบ Insight เท่านั้น</span>
        — {{ count($rows) }} <span data-i18n="master.items">รายการ</span>
      </p>
    </div>
  </section>
@endsection
