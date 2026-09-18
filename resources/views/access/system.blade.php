@extends('layouts.app')

@section('title', 'สิทธิ์การเข้าถึงระบบ SBMS / SBMS system access')

@push('page-style')
  <style>
    :root { --page-max: 1120px; }
  </style>
@endpush

@section('content')
  @php
    // โชว์ในคอลัมน์ได้เท่านี้ ที่เหลือกด "รายละเอียด" ดูในหน้าต่างซ้อน
    $showFaces = 8;
    $showNames = 3;   // เจ้าของสั่งลดลง 2026-09-03 — ในคอลัมน์ไม่ต้องโชว์เยอะ ที่เหลือดูใน "รายละเอียด"
    $allowed = $positions->where('can_login', true)->values();
    $blocked = $positions->where('can_login', false)->values();
  @endphp

  <section class="card">
    <div class="card-head">
      <h3 data-i18n="access.sys.title">สิทธิ์การเข้าถึงระบบ SBMS</h3>
    </div>

    <div class="card-body">
      <div class="tbl-wrap">
        <table class="tbl" data-filterable>
          <thead>
            <tr>
              <th class="col-seq" data-no-filter data-i18n="tbl.seq">ลำดับ</th>
              <th data-i18n="access.sys.right">สิทธิ์</th>
              <th data-i18n="access.sys.holders">ผู้ที่ได้รับสิทธิ์</th>
              <th class="col-detail"><span data-i18n="access.detail">รายละเอียด</span></th>
              <th class="col-act"><span data-i18n="access.tbl.manage">จัดการ</span></th>
            </tr>
          </thead>
          <tbody>

            {{-- ── ผู้ดูแลระบบ ── --}}
            <tr>
              <td class="col-seq">1</td>
              <td><span class="row-title" data-i18n="access.admin.title">ผู้ดูแลระบบ (Admin)</span></td>

              <td>
                <div class="faces">
                  @forelse ($admins->take($showFaces) as $a)
                    @include('access.partials.avatar', ['person' => $a])
                  @empty
                    <span class="state state-muted" data-i18n="access.admin.none">ยังไม่มีผู้ดูแลระบบ</span>
                  @endforelse

                </div>
              </td>

              <td class="col-detail">
                @if ($admins->count())
                  <button type="button" class="link-btn"
                          data-modal-open="detail-admins">{{ $admins->count() }} <span data-i18n="access.pos.people">คน</span></button>
                @else
                  <span class="state state-muted">—</span>
                @endif
              </td>

              <td class="col-act">
                <button type="button" class="btn btn-quiet"
                        data-modal-open="edit-admins" data-i18n="access.edit">แก้ไขสิทธิ์</button>
              </td>
            </tr>

            {{-- ── ตำแหน่งที่เข้าสู่ระบบได้ ── --}}
            <tr>
              <td class="col-seq">2</td>
              <td><span class="row-title" data-i18n="access.pos.title">ตำแหน่งที่เข้าสู่ระบบได้</span></td>

              <td>
                <span class="inline-list">
                  @foreach ($allowed->take($showNames) as $p)
                    <span data-loc-th="{{ $p['job_th'] }}"
                          data-loc-en="{{ $p['job_en'] ?: $p['job_th'] }}">{{ $p['job_th'] }}</span>@if (! $loop->last) · @endif
                  @endforeach
                </span>
                @if ($blocked->count())
                  <span class="state state-off" style="margin-left:8px">{{ $blocked->count() }} <span data-i18n="access.pos.blocked">ตำแหน่งที่ปิดไว้</span></span>
                @endif
              </td>

              <td class="col-detail">
                <button type="button" class="link-btn"
                        data-modal-open="detail-positions">{{ $allowed->count() }} / {{ $positions->count() }}</button>
              </td>

              <td class="col-act">
                <button type="button" class="btn btn-quiet"
                        data-modal-open="edit-positions" data-i18n="access.edit">แก้ไขสิทธิ์</button>
              </td>
            </tr>

          </tbody>
        </table>
      </div>
    </div>
  </section>

  {{-- ═══════════ หน้าต่าง: แก้ไขผู้ดูแลระบบ ═══════════ --}}
  <div class="modal-wrap" data-modal="edit-admins" hidden role="dialog" aria-modal="true">
    <div class="modal">
      <div class="modal-head">
        <span class="modal-title" data-i18n="access.admin.editTitle">แก้ไขสิทธิ์ผู้ดูแลระบบ</span>
        @include('access.partials.modal-x')
      </div>

      <form method="POST" action="{{ route('access.system.admin.add') }}" style="display:contents">
        @csrf

        <div class="modal-body">
          @include('access.partials.people-picker', [
            'pickerId' => 'admins',
            'field' => 'employee_codes[]',
            'selected' => [],
            'emptyKey' => 'access.pick.emptyAdmin',
            'emptyText' => 'ค้นหาแล้วกดเลือกคนที่จะให้เป็นผู้ดูแลระบบ',
          ])

          {{-- รายชื่อผู้ดูแลระบบปัจจุบัน + ปุ่มถอดสิทธิ์ --}}
          <div>
            <p class="picker-label" style="margin-bottom:6px">
              <span data-i18n="access.admin.current">ผู้ดูแลระบบปัจจุบัน</span> <b>{{ $admins->count() }}</b>
            </p>
            <div class="person-list">
              @forelse ($admins as $a)
                <div class="person-row">
                  @include('access.partials.avatar', ['person' => $a, 'size' => 'sm'])
                  <span class="person-text">
                    <span class="person-name"
                          data-loc-th="{{ $a['name_th'] }}" data-loc-en="{{ $a['name_en'] }}">{{ $a['name_th'] }}</span>
                    <span class="person-meta">{{ $a['employee_code'] }}{{ $a['position'] ? ' · '.$a['position'] : '' }}</span>
                  </span>
                  @if ($a['from_insight'])
                    <span class="state state-muted" data-i18n="access.admin.lockedHint">แก้ที่ Insight</span>
                  @else
                    <button type="button" class="link-btn"
                            data-remove-admin="{{ $a['row_id'] }}" data-i18n="access.admin.remove">ถอดสิทธิ์</button>
                  @endif
                </div>
              @empty
                <p class="picker-msg" data-i18n="access.admin.none">ยังไม่มีผู้ดูแลระบบ</p>
              @endforelse
            </div>
          </div>
        </div>

        <div class="modal-foot">
          <button type="submit" class="btn" data-i18n="access.admin.add">เพิ่มเป็นผู้ดูแลระบบ</button>
          <span class="spacer"></span>
          <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.close">ปิด</button>
        </div>
      </form>
    </div>
  </div>

  {{-- ฟอร์มถอดสิทธิ์ — แยกออกมานอกฟอร์มเพิ่ม เพราะซ้อน <form> ใน <form> ไม่ได้ --}}
  @foreach ($admins->where('from_insight', false) as $a)
    <form method="POST" action="{{ route('access.system.admin.remove', $a['row_id']) }}"
          id="remove-admin-{{ $a['row_id'] }}" hidden
          data-confirm="ยืนยันถอดสิทธิ์ผู้ดูแลระบบของคนนี้?"
          data-confirm-en="Remove administrator rights from this person?">
      @csrf
      @method('DELETE')
    </form>
  @endforeach

  {{-- ═══════════ หน้าต่าง: ติ๊กตำแหน่งที่เข้าระบบได้ ═══════════ --}}
  <div class="modal-wrap" data-modal="edit-positions" hidden role="dialog" aria-modal="true">
    <div class="modal">
      <div class="modal-head">
        <span class="modal-title" data-i18n="access.pos.pick">ติ๊กตำแหน่งที่อนุญาตให้เข้าใช้งาน SBMS</span>
        @include('access.partials.modal-x')
      </div>

      <form method="POST" action="{{ route('access.system.positions') }}" style="display:contents">
        @csrf

        <div class="modal-body" data-check-group>
          <p class="picker-label">
            <b data-check-count>{{ $allowed->count() }}</b> / {{ $positions->count() }}
            <span data-i18n="access.pos.unit">ตำแหน่ง</span>
          </p>

          <div class="pos-grid">
            @foreach ($positions as $p)
              <label class="pos-item">
                <input type="checkbox" name="allowed[]" value="{{ $p['job_code'] }}" @checked($p['can_login'])>
                <span class="pos-name"
                      data-loc-th="{{ $p['job_th'] }}"
                      data-loc-en="{{ $p['job_en'] ?: $p['job_th'] }}">{{ $p['job_th'] }}</span>
                <span class="pos-code">{{ $p['job_code'] }} · {{ $p['accounts'] }}</span>
              </label>
            @endforeach
          </div>
        </div>

        <div class="modal-foot">
          <button type="submit" class="btn" data-i18n="common.save">บันทึก</button>
          <button type="button" class="btn btn-quiet" data-check-all data-i18n="access.pos.all">เลือกทั้งหมด</button>
          <button type="button" class="btn btn-quiet" data-check-none data-i18n="access.pos.none">ไม่เลือกเลย</button>
          <span class="spacer"></span>
          <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.cancel">ยกเลิก</button>
        </div>
      </form>
    </div>
  </div>

  {{-- ═══════════ หน้าต่าง: รายละเอียดผู้ดูแลระบบ ═══════════ --}}
  <div class="modal-wrap" data-modal="detail-admins" hidden role="dialog" aria-modal="true">
    <div class="modal">
      <div class="modal-head">
        <span class="modal-title">
          <span data-i18n="access.admin.title">ผู้ดูแลระบบ (Admin)</span>
          <span class="state state-muted">{{ $admins->count() }} <span data-i18n="access.pos.people">คน</span></span>
        </span>
        @include('access.partials.modal-x')
      </div>

      <div class="modal-body">
        <div class="person-list">
          @foreach ($admins as $a)
            <div class="person-row">
              @include('access.partials.avatar', ['person' => $a, 'size' => 'sm'])
              <span class="person-text">
                <span class="person-name"
                      data-loc-th="{{ $a['name_th'] }}" data-loc-en="{{ $a['name_en'] }}">{{ $a['name_th'] }}</span>
                <span class="person-meta">{{ $a['employee_code'] }}{{ $a['position'] ? ' · '.$a['position'] : '' }}{{ $a['department'] ? ' · '.$a['department'] : '' }}</span>
              </span>
              <span class="state state-muted"
                    data-i18n="{{ $a['from_insight'] ? 'access.src.insight' : 'access.src.bms' }}">{{ $a['from_insight'] ? 'จาก Insight' : 'ตั้งใน SBMS' }}</span>
            </div>
          @endforeach
        </div>
      </div>

      <div class="modal-foot">
        <span class="spacer"></span>
        <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.close">ปิด</button>
      </div>
    </div>
  </div>

  {{-- ═══════════ หน้าต่าง: รายละเอียดตำแหน่ง ═══════════ --}}
  <div class="modal-wrap" data-modal="detail-positions" hidden role="dialog" aria-modal="true">
    <div class="modal">
      <div class="modal-head">
        <span class="modal-title">
          <span data-i18n="access.pos.title">ตำแหน่งที่เข้าสู่ระบบได้</span>
          <span class="state state-muted">{{ $allowed->count() }} / {{ $positions->count() }}</span>
        </span>
        @include('access.partials.modal-x')
      </div>

      <div class="modal-body">
        <div class="pos-grid">
          @foreach ($positions as $p)
            <span class="pos-item" style="cursor:default">
              <span class="pos-name {{ $p['can_login'] ? '' : 'state-off' }}"
                    data-loc-th="{{ $p['job_th'] }}"
                    data-loc-en="{{ $p['job_en'] ?: $p['job_th'] }}">{{ $p['job_th'] }}</span>
              <span class="pos-code">{{ $p['job_code'] }} · {{ $p['accounts'] }}</span>
            </span>
          @endforeach
        </div>
      </div>

      <div class="modal-foot">
        <span class="spacer"></span>
        <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.close">ปิด</button>
      </div>
    </div>
  </div>

  @include('access.partials.picker-assets')

  <script>
    'use strict';
    // ปุ่มถอดสิทธิ์อยู่ในฟอร์ม "เพิ่มแอดมิน" จึงส่งฟอร์มถอดสิทธิ์ที่วางไว้ข้างนอกแทน
    document.querySelectorAll('[data-remove-admin]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var form = document.getElementById('remove-admin-' + btn.getAttribute('data-remove-admin'));
        if (form) { form.requestSubmit(); }
      });
    });
  </script>
@endsection
