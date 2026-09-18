@extends('layouts.app')

@section('title', 'สิทธิ์การเข้าถึงโมดูล / Module access')

@push('page-style')
  <style>
    :root { --page-max: 1120px; }

    .mod { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .mod .nav-icon { width: 20px; height: 20px; flex: 0 0 auto; }
    .mod-name { color: var(--navy-900); font-weight: 700; }

    /*
      ── ชั้นที่ 1: หัวข้อหลัก (เจ้าของสั่ง 2026-09-10) ──
      พื้นเข้มกว่าแถวอื่นเล็กน้อย เห็นได้ว่าเป็นหัวก้อน ไม่ต้องตีกรอบ
    */
    .tbl tr.grp-tr > td { background: var(--navy-50); border-top: 1px solid var(--line); }
    .grp-name { color: var(--navy-900); font-weight: 700; font-size: var(--fs-md); }
    .tbl tr.grp-tr .nav-icon { width: 22px; height: 22px; }

    /* ── ชั้นที่ 2: โมดูล — ย่อหน้าเข้ามาให้เห็นว่าอยู่ใต้หัวข้อหลัก ── */
    .mod.is-child { padding-left: 26px; }
    .tbl tr.mod-tr[hidden] { display: none; }

    /*
      ปุ่ม "V" กางหัวข้อย่อยของโมดูล
      เจ้าของสั่ง 2026-09-03: ให้กางเป็นแถวย่อยในตารางเลย ไม่ต้องเปิดหน้าต่างซ้อนอีกชั้น
    */
    .fn-toggle {
      display: grid; place-items: center; width: 22px; height: 22px; flex: 0 0 auto;
      border: 0; border-radius: var(--radius-sm); background: transparent;
      color: var(--muted); font-size: 10px; cursor: pointer;
      transition: background .14s var(--ease), color .14s var(--ease), transform .2s var(--ease);
    }
    .fn-toggle:hover { background: var(--surface-2); color: var(--navy-800); }
    .fn-toggle[aria-expanded="true"] { transform: rotate(90deg); color: var(--navy-800); }
    .fn-toggle[hidden] { display: none; }

    /*
      แถวที่ไม่มีอะไรให้กาง แต่ยังต้องกินที่เท่าปุ่ม
      🔴 ใช้ visibility ไม่ใช่ hidden — ไม่งั้นไอคอนของโมดูลนั้นเลื่อนไปทางซ้ายคนเดียว
    */
    .fn-toggle.is-blank { visibility: hidden; pointer-events: none; }

    /* แถวหัวข้อย่อย — ย่อหน้าเข้ามาให้เห็นว่าเป็นลูกของโมดูลด้านบน */
    .tbl tr.fn-tr > td { background: var(--surface-2); }
    .tbl tr.fn-tr[hidden] { display: none; }

    /*
      ย่อหน้าไล่เป็นขั้นละ 26px: หัวข้อหลัก 32 → โมดูล 58 → หัวข้อย่อย 84
      (เจ้าของสั่ง 2026-09-10 ให้ไอคอน+ชื่อหัวข้อย่อยย่อหน้าจากชื่อโมดูลที่มันสังกัด)
    */
    .fn-cell { display: flex; align-items: center; gap: 10px; min-width: 0; padding-left: 84px; }

    /* รูปคนในเซลล์ — จัดกึ่งกลางให้ตรงกับคอลัมน์อื่น */
    .fn-people { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 6px 8px; }
    .fn-cell .nav-icon { width: 18px; height: 18px; flex: 0 0 auto; }
    .fn-name { color: var(--navy-800); font-weight: 600; font-size: var(--fs-sm); }

    /*
      แถว "สิทธิ์ย่อย" — ย่อหน้าลึกกว่าหัวข้อย่อยอีกชั้น (เจ้าของสั่ง 2026-09-04)
      🔴 แยกเป็นคนละแถว ห้ามเอาไปต่อท้ายชื่อหัวข้อย่อยด้วย /
         ผู้ดูแลระบบจะแยกไม่ออกว่ากำลังตั้งสิทธิ์ของอันไหน
    */
    .fn-cell.is-extra { position: relative; padding-left: 110px; }
    .fn-cell.is-extra .fn-name { color: var(--ink-soft); font-weight: 500; }

    /*
      เส้นโยงรูปตัว L — ลากจากแถวหัวข้อแม่ด้านบนลงมาหาสิทธิ์ย่อย (เจ้าของสั่งให้ยาวขึ้น)
      🔴 ต้องลากขึ้นไปพ้นขอบแถว (top ติดลบ) ไม่งั้นดูเหมือนขีดลอยๆ ไม่ได้โยงจากอะไร
    */
    .fn-cell.is-extra::before {
      content: '';
      position: absolute; left: 94px; top: -26px; height: 40px; width: 16px;
      border-left: 2px solid var(--line);
      border-bottom: 2px solid var(--line);
      border-bottom-left-radius: 8px;
    }
  </style>
@endpush

@section('content')
  @php $showFaces = 8; @endphp

  <section class="card">
    <div class="card-head">
      <h3 data-i18n="access.mod.title">สิทธิ์การเข้าถึงโมดูล</h3>
    </div>

    <div class="card-body">
      <div class="tbl-wrap">
        {{-- ไม่ใส่ data-filterable — ตารางนี้มีแถวหัวข้อย่อยที่กาง/พับเอง ตัวกรองจะไปแหย่สถานะซ่อน --}}
        <table class="tbl">
          <thead>
            <tr>
              <th class="col-seq" data-i18n="tbl.seq">ลำดับ</th>
              <th data-i18n="access.mod.name">โมดูล</th>
              <th data-i18n="access.mod.people">ผู้ใช้งานที่มีสิทธิ</th>
              <th class="col-detail" data-i18n="access.detail">รายละเอียด</th>
              <th class="col-act"><span data-i18n="access.tbl.manage">จัดการ</span></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($groups as $group)
              {{--
                ── ชั้นที่ 1: หัวข้อหลัก ──
                🔴 เจ้าของสั่ง 2026-09-10 ให้หัวข้อหลักเป็นแถวใหญ่ กดแล้วค่อยกางโมดูลข้างใน
                   ของเดิมเป็นรายการโมดูลแบนๆ ยาวเป็นหางว่าว ไล่หาโมดูลที่ต้องการยาก
              --}}
              <tr class="grp-tr">
                <td class="col-seq">{{ $loop->iteration }}</td>
                <td>
                  <div class="mod">
                    {{-- 🔴 เจ้าของสั่ง 2026-09-10: เริ่มต้นพับไว้ทุกหัวข้อ กดเองถึงกาง --}}
                    <button type="button" class="fn-toggle" aria-expanded="false"
                            data-grp-toggle="{{ $group['id'] }}"
                            title="โมดูลในหัวข้อนี้" data-i18n-title="access.grp.col"
                            aria-label="โมดูลในหัวข้อนี้">&#10148;</button>

                    @include('layouts.partials.nav-icon', ['name' => $group['icon']])
                    <span class="grp-name" data-i18n="{{ $group['key'] }}">{{ $group['th'] }}</span>
                  </div>
                </td>
                <td></td>
                <td class="col-detail"></td>
                <td class="col-act"></td>
              </tr>

            @foreach ($group['modules'] as $m)
              @php
                /*
                  🔴 โมดูลที่ไม่มีหัวข้อย่อยจริง (แดชบอร์ด) — ตั้งสิทธิ์ที่แถวตัวเองเลย
                     เจ้าของสั่ง 2026-09-10: กด "ภาพรวมระบบ" แล้วต้องเจอแดชบอร์ดทันที
                */
                $self = $m['direct'] ? $m['functions'][0] : null;
              @endphp

              {{-- ── ชั้นที่ 2: โมดูล ── --}}
              <tr class="mod-tr" data-grp-of="{{ $group['id'] }}"
                  @if ($self) data-fn-key="{{ $self['key'] }}" @endif hidden>
                <td class="col-seq"></td>
                <td>
                  <div class="mod is-child">
                    {{-- ปุ่ม V — กางหัวข้อย่อยของโมดูลนี้ (โมดูลที่ไม่มีหัวข้อย่อยเหลือไว้แค่ที่ว่าง) --}}
                    <button type="button" @class(['fn-toggle', 'is-blank' => $self]) aria-expanded="false"
                            data-fn-toggle="{{ $m['id'] }}" @if (! $self && ! count($m['functions'])) hidden @endif
                            title="หัวข้อย่อย" data-i18n-title="access.fn.col"
                            aria-label="หัวข้อย่อย">&#10148;</button>

                    @include('layouts.partials.nav-icon', ['name' => $m['icon']])
                    <span class="mod-name" data-i18n="{{ $m['key'] }}">{{ $m['th'] }}</span>
                  </div>
                </td>

                {{--
                  🔴 แถวหัวข้อหลักเป็น "สรุป" อย่างเดียว ไม่มีปุ่มแก้ไขสิทธิ์แล้ว (เจ้าของสั่ง 2026-09-03)
                     เห็นโมดูลไหม = คำนวณจากหัวข้อย่อย ตั้งซ้ำอีกชั้นแล้วขัดกันเอง
                --}}
                <td>
                  {{-- 3 สถานะ: เปิดให้ทุกคน · ไม่ได้กำหนดใคร · รายชื่อคน (เจ้าของสั่ง 2026-09-07) --}}
                  @if ($self)
                    {{-- ห่อด้วย .fn-people เพราะสคริปต์บันทึกสิทธิ์วาดรูปคนใหม่ที่กล่องนี้ --}}
                    <div class="fn-people">
                      @if ($self['everyone'])
                        <span class="state state-all" data-i18n="access.fn.everyone">ทุกคน</span>
                      @elseif ($self['nobody'])
                        <span class="state state-off" data-i18n="access.fn.nobodyYet">ไม่ได้กำหนด</span>
                      @else
                        <div class="faces">
                          @foreach (array_slice($self['people'], 0, $showFaces) as $person)
                            @include('access.partials.avatar', ['person' => $person, 'size' => 'sm'])
                          @endforeach
                        </div>
                      @endif
                    </div>
                  @elseif ($m['everyone'])
                    <span class="state state-all" data-i18n="access.mod.set">กำหนดแล้ว</span>
                  @elseif ($m['nobody'])
                    <span class="state state-off" data-i18n="access.mod.nobody">ไม่ได้กำหนด</span>
                  @else
                    <div class="faces">
                      @foreach (array_slice($m['people'], 0, $showFaces) as $person)
                        @include('access.partials.avatar', ['person' => $person])
                      @endforeach
                    </div>
                  @endif
                </td>

                {{--
                  🔴 แถวหัวข้อหลักไม่บอกจำนวนคนแล้ว (เจ้าของสั่ง 2026-09-04)
                     สิทธิ์คิดจากหัวข้อย่อยอยู่แล้ว ตัวเลขตรงนี้ทำให้เข้าใจผิดว่าตั้งได้ที่แถวนี้
                --}}
                <td class="col-detail">
                  @if ($self)
                    @if ($self['nobody'])
                      <span class="state state-muted">—</span>
                    @else
                      <span class="state state-muted">
                        {{ number_format($self['everyone'] ? $totalAccounts : count($self['people'])) }}
                        <span data-i18n="access.pos.people">คน</span>
                      </span>
                    @endif
                  @endif
                </td>

                {{-- แถวโมดูลที่ตั้งสิทธิ์เองไม่ได้ ปล่อยว่างไว้ (เจ้าของสั่ง 2026-09-10) --}}
                <td class="col-act">
                  @if ($self && ! $self['no_edit'])
                    <button type="button" class="btn btn-quiet"
                            data-modal-open="fn-{{ $m['id'] }}-0" data-i18n="access.edit">แก้ไข</button>
                  @endif
                </td>
              </tr>

              {{-- แถวหัวข้อย่อย — ซ่อนไว้จนกว่าจะกดปุ่ม V ที่แถวโมดูลด้านบน --}}
              @if (! $self)
              @foreach ($m['functions'] as $index => $fn)
                <tr class="fn-tr" data-fn-of="{{ $m['id'] }}" data-grp-of="{{ $group['id'] }}" data-fn-key="{{ $fn['key'] }}" hidden>
                  {{-- แถวหัวข้อย่อยไม่มีลำดับของตัวเอง — เว้นไว้ให้คอลัมน์ตรงกัน --}}
                  <td class="col-seq"></td>
                  <td>
                    <div class="fn-cell">
                      @include('layouts.partials.nav-icon', ['name' => $fn['icon'] ?? 'file'])
                      <span class="fn-name" data-i18n="{{ $fn['key'] }}">{{ $fn['th'] }}</span>
                    </div>
                  </td>

                  <td>
                    <div class="fn-people">
                      @if ($fn['note'] && ($fn['everyone'] || $fn['nobody']))
                        {{-- หัวข้อที่มีคำอธิบายเฉพาะของตัวเอง เช่น "ผู้เสนอเลือกเองตอนส่งเอกสาร" --}}
                        <span class="state state-muted" data-i18n="{{ $fn['note']['key'] }}">{{ $fn['note']['th'] }}</span>
                      @elseif ($fn['everyone'])
                        <span class="state state-all" data-i18n="access.fn.everyone">ทุกคน</span>
                      @elseif ($fn['nobody'])
                        {{-- 🔴 ยังไม่ได้กำหนด = ไม่มีใครใช้ได้ (กติกาใหม่ 2026-09-07) --}}
                        <span class="state state-off" data-i18n="access.fn.nobodyYet">ไม่ได้กำหนด</span>
                      @else
                        <div class="faces">
                          @foreach (array_slice($fn['people'], 0, $showFaces) as $person)
                            @include('access.partials.avatar', ['person' => $person, 'size' => 'sm'])
                          @endforeach
                        </div>
                      @endif
                    </div>
                  </td>

                  {{--
                    จำนวนคนของหัวข้อย่อย
                    🔴 ติ๊ก "ทุกคน" = บอกจำนวนบัญชีทั้งหมด · ระบุรายชื่อ = นับรายชื่อ
                       ยังไม่ได้กำหนด = ขีด (ไม่ใช่ 0 คน เพราะ 0 อ่านเหมือนตั้งใจตั้งเป็นศูนย์)
                  --}}
                  <td class="col-detail">
                    @if ($fn['nobody'])
                      <span class="state state-muted">—</span>
                    @else
                      <span class="state state-muted">
                        {{ number_format($fn['everyone'] ? $totalAccounts : count($fn['people'])) }}
                        <span data-i18n="access.pos.people">คน</span>
                      </span>
                    @endif
                  </td>

                  <td class="col-act">
                    @unless ($fn['no_edit'])
                      <button type="button" class="btn btn-quiet"
                              data-modal-open="fn-{{ $m['id'] }}-{{ $index }}" data-i18n="access.edit">แก้ไข</button>
                    @endunless
                  </td>
                </tr>

                {{--
                  แถวของ "สิทธิ์ย่อย" — 1 สิทธิ์ 1 แถว อยู่ใต้หัวข้อที่มันสังกัด
                  🔴 ตั้งแต่ 2026-09-07 กติกาเหมือนหัวข้อทั่วไปทั้งหมดแล้ว
                     ไม่ระบุใคร = ไม่มีใครทำได้ · อยากเปิดให้ทุกคนต้องติ๊ก "ทุกคน"
                --}}
                @foreach ($fn['extras'] as $extra)
                  <tr class="fn-tr" data-fn-of="{{ $m['id'] }}" data-grp-of="{{ $group['id'] }}" data-fn-key="{{ $extra['key'] }}" hidden>
                    <td class="col-seq"></td>

                    <td>
                      <div class="fn-cell is-extra">
                        @include('layouts.partials.nav-icon', ['name' => 'file'])
                        <span class="fn-name" data-i18n="{{ $extra['key'] }}">{{ $extra['th'] }}</span>
                      </div>
                    </td>

                    <td>
                      <div class="fn-people">
                        @if ($extra['everyone'])
                          <span class="state state-all" data-i18n="access.fn.everyone">ทุกคน</span>
                        @elseif ($extra['people'])
                          <div class="faces">
                            @foreach (array_slice($extra['people'], 0, $showFaces) as $person)
                              @include('access.partials.avatar', ['person' => $person, 'size' => 'sm'])
                            @endforeach
                          </div>
                        @else
                          <span class="state state-off" data-i18n="access.fn.nobody">ยังไม่มีใคร</span>
                        @endif
                      </div>
                    </td>

                    <td class="col-detail">
                      <span class="state state-muted">
                        {{ number_format($extra['everyone'] ? $totalAccounts : count($extra['people'])) }}
                        <span data-i18n="access.pos.people">คน</span>
                      </span>
                    </td>

                    <td class="col-act">
                      {{-- 🔴 หน้าต่างของตัวเอง แยกจากหัวข้อแม่ (เจ้าของสั่ง 2026-09-04) --}}
                      <button type="button" class="btn btn-quiet"
                              data-modal-open="fx-{{ $m['id'] }}-{{ $index }}-{{ $loop->index }}"
                              data-i18n="access.edit">แก้ไข</button>
                    </td>
                  </tr>
                @endforeach
              @endforeach
              @endif
            @endforeach
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </section>

  {{-- ═══════════ หน้าต่างกำหนดสิทธิ์รายหัวข้อย่อย ═══════════ --}}
  @foreach ($modules as $m)
    @foreach ($m['functions'] as $index => $fn)
      @continue($fn['no_edit'])
      <div class="modal-wrap" data-modal="fn-{{ $m['id'] }}-{{ $index }}" hidden role="dialog" aria-modal="true">
        <div class="modal">
          <div class="modal-head">
            <span class="modal-title">
              <span data-i18n="access.fn.pick">เลือกคนที่เข้าหัวข้อย่อยนี้ได้</span> ·
              <span data-i18n="{{ $fn['key'] }}">{{ $fn['th'] }}</span>
            </span>
            @include('access.partials.modal-x')
          </div>

          {{--
            🔴 บันทึกแบบไม่ปิดหน้าต่าง (เจ้าของสั่ง 2026-09-04)
               เดิมเป็น POST ธรรมดา -> โหลดหน้าใหม่ -> หน้าต่างปิด ต้องเปิดใหม่ทุกครั้งที่เพิ่มคน
          --}}
          <form method="POST" action="{{ route('access.modules.function') }}" style="display:contents"
                data-fn-form data-fn-key="{{ $fn['key'] }}" data-fn-empty="all">
            @csrf
            <input type="hidden" name="module_id" value="{{ $m['id'] }}">
            <input type="hidden" name="function_key" value="{{ $fn['key'] }}">

            {{-- 🔴 หน้าต่างนี้ตั้งเฉพาะ "ใครเข้าหัวข้อย่อยนี้ได้" · สิทธิ์ย่อยมีหน้าต่างของตัวเอง --}}
            <div class="modal-body">
              @include('access.partials.everyone-tick', ['checked' => $fn['everyone']])

              @include('access.partials.people-picker', [
                'pickerId' => 'fn-'.$m['id'].'-'.$index,
                'field' => 'employee_codes[]',
                'selected' => $fn['people'],
                'emptyKey' => 'access.fn.emptyNone',
                'emptyText' => 'ยังไม่ได้เลือกใคร = ยังไม่มีใครใช้ได้',
              ])
            </div>

            {{-- บันทึกแล้วหน้าต่างค้างอยู่ ให้เลือกคนต่อได้ — ปิดเองเมื่อกด "ปิด" --}}
            <div class="modal-foot">
              <button type="submit" class="btn" data-i18n="common.save">บันทึก</button>
              <span class="spacer"></span>
              <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.close">ปิด</button>
            </div>
          </form>
        </div>
      </div>

      {{--
        หน้าต่างของ "สิทธิ์ย่อย" — 1 สิทธิ์ 1 หน้าต่าง (เจ้าของสั่ง 2026-09-04)
        🔴 ยิงไปที่ endpoint เดิม แต่ส่ง function_key เป็นคีย์ของสิทธิ์ย่อย
           เพราะเก็บอยู่ตารางเดียวกัน ต่างกันแค่คีย์
        🔴 ไม่เลือกใคร = ไม่มีใครทำได้ (กติกาเดียวกับทั้งระบบตั้งแต่ 2026-09-07)
      --}}
      @foreach ($fn['extras'] as $ei => $extra)
        <div class="modal-wrap" data-modal="fx-{{ $m['id'] }}-{{ $index }}-{{ $ei }}" hidden role="dialog" aria-modal="true">
          <div class="modal">
            <div class="modal-head">
              <span class="modal-title">
                <span data-i18n="access.fn.pickExtra">เลือกคนที่ได้สิทธิ์นี้</span> ·
                <span data-i18n="{{ $extra['key'] }}">{{ $extra['th'] }}</span>
              </span>
              @include('access.partials.modal-x')
            </div>

            <form method="POST" action="{{ route('access.modules.function') }}" style="display:contents"
                  data-fn-form data-fn-key="{{ $extra['key'] }}" data-fn-empty="none">
              @csrf
              <input type="hidden" name="module_id" value="{{ $m['id'] }}">
              <input type="hidden" name="function_key" value="{{ $extra['key'] }}">

              <div class="modal-body">
                @include('access.partials.everyone-tick', ['checked' => $extra['everyone']])

                @include('access.partials.people-picker', [
                  'pickerId' => 'fx-'.$m['id'].'-'.$index.'-'.$ei,
                  'field' => 'employee_codes[]',
                  'selected' => $extra['people'],
                  'emptyKey' => 'access.fn.emptyNone',
                  'emptyText' => 'ยังไม่ได้เลือกใคร = ยังไม่มีใครทำได้',
                ])
              </div>

              <div class="modal-foot">
                <button type="submit" class="btn" data-i18n="common.save">บันทึก</button>
                <span class="spacer"></span>
                <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.close">ปิด</button>
              </div>
            </form>
          </div>
        </div>
      @endforeach
    @endforeach
  @endforeach

  @include('access.partials.picker-assets')

  <script>
    'use strict';

    /*
      บันทึกสิทธิ์โดยไม่ปิดหน้าต่าง (เจ้าของสั่ง 2026-09-04)
      🔴 เดิมเป็น POST ธรรมดา หน้าโหลดใหม่ทุกครั้ง -> เพิ่มคนทีละคนต้องเปิดหน้าต่างใหม่ตลอด
         ตอนนี้ยิง AJAX แล้ววาดรูปในตารางใหม่เอง หน้าต่างค้างอยู่จนกว่าจะกดปิด
    */
    (function () {
      var token = document.querySelector('meta[name="csrf-token"]');
      token = token ? token.getAttribute('content') : null;

      // สร้างรูปพนักงาน 1 คน — ต้องกดขยายได้เหมือนที่ blade วาด
      function avatar(p) {
        var name = (p.name_th || '') + ' (' + p.employee_code + ')';

        if (!p.photo) {
          return '<span class="avatar avatar-sm" aria-hidden="true">' + (p.initial || '?') + '</span>';
        }

        var el = document.createElement('button');
        el.type = 'button';
        el.className = 'avatar avatar-sm';
        el.setAttribute('data-zoomable', '');
        el.setAttribute('data-zoom-src', p.photo);
        el.setAttribute('data-zoom-alt', name);
        el.title = name;
        el.innerHTML = '<img src="' + p.photo + '" alt="" loading="lazy">';

        return el.outerHTML;
      }

      function facesHtml(people) {
        return '<div class="faces">' + people.slice(0, 8).map(avatar).join('') + '</div>';
      }

      document.querySelectorAll('[data-fn-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
          e.preventDefault();

          var en = window.BMS.lang() === 'en';
          var btn = form.querySelector('button[type="submit"]');

          btn.disabled = true;

          fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            credentials: 'same-origin',
            body: new FormData(form),
          })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
              if (!res.ok || !res.d.ok) {
                throw new Error(res.d.message ? (en ? res.d.message.en : res.d.message.th) : 'HTTP error');
              }

              /*
                อัปเดตรูปในแถวของตาราง โดยไม่ต้องโหลดหน้าใหม่
                🔴 3 สถานะ (กติกาใหม่ 2026-09-07)
                   ติ๊ก "ทุกคน"      = ทุกคนที่เข้าระบบได้
                   มีรายชื่อ          = รูปคน
                   ไม่ได้ติ๊กและว่าง = ยังไม่ได้กำหนด ไม่มีใครใช้ได้
              */
              /* 🔴 แถวของหัวข้อนี้อาจเป็นแถวโมดูลเอง (แดชบอร์ด) ไม่ใช่แถวหัวข้อย่อยเสมอไป */
              var row = document.querySelector('tr[data-fn-key="' + form.getAttribute('data-fn-key') + '"]');

              if (row) {
                var cell = row.querySelector('.fn-people');
                var main = cell.querySelector('.faces, .state');
                var everyone = !! res.d.everyone;

                if (everyone) {
                  main.outerHTML = '<span class="state state-all" data-i18n="access.fn.everyone">'
                    + (en ? 'Everyone' : 'ทุกคน') + '</span>';
                } else if (res.d.people.length) {
                  main.outerHTML = facesHtml(res.d.people);
                } else {
                  main.outerHTML = '<span class="state state-off" data-i18n="access.fn.nobodyYet">'
                    + (en ? 'Not set' : 'ไม่ได้กำหนด') + '</span>';
                }

                // ตัวเลขในคอลัมน์ "รายละเอียด" ต้องเดินตามด้วย
                var count = row.querySelector('.col-detail .state');

                if (count) {
                  if (! everyone && ! res.d.people.length) {
                    count.textContent = '—';
                  } else {
                    var n = everyone ? {{ $totalAccounts }} : res.d.people.length;
                    count.innerHTML = n.toLocaleString() + ' <span data-i18n="access.pos.people">'
                      + (en ? 'people' : 'คน') + '</span>';
                  }
                }
              }

              window.BMS.notify({
                kind: 'ok',
                title: en ? res.d.message.en : res.d.message.th,
                autoClose: 1600,
              });
            })
            .catch(function (err) {
              window.BMS.notify({
                kind: 'no',
                title: en ? 'Save failed' : 'บันทึกไม่สำเร็จ',
                text: err.message,
                autoClose: false,
              });
            })
            .finally(function () { btn.disabled = false; });
        });
      });
    })();

    /*
      ปุ่ม V กางหัวข้อย่อย
      ไม่ใส่อนิเมชันความสูงกับ <tr> เพราะ display:table-row ทำ transition ความสูงไม่ได้
      ใช้ค่อยๆ จางเข้าแทน ซึ่งเห็นชัดพอและไม่กระตุก
    */
    /*
      กาง/พับ "หัวข้อหลัก" (เจ้าของสั่ง 2026-09-10)
      🔴 พับกลุ่มต้องพับหัวข้อย่อยที่กางค้างอยู่ข้างในไปด้วย
         ไม่งั้นพับกลุ่มแล้วแถวหัวข้อย่อยยังลอยอยู่ กลายเป็นแถวกำพร้า
    */
    document.querySelectorAll('[data-grp-toggle]').forEach(function (btn) {
      var id = btn.getAttribute('data-grp-toggle');
      var rows = document.querySelectorAll('[data-grp-of="' + id + '"]');

      btn.addEventListener('click', function () {
        var open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', open ? 'false' : 'true');

        if (open) {
          rows.forEach(function (row) { row.hidden = true; row.style.animation = ''; });

          // พับปุ่มของโมดูลข้างในให้กลับไปเป็น "ยังไม่กาง" ด้วย
          document.querySelectorAll('[data-grp-of="' + id + '"] [data-fn-toggle]')
            .forEach(function (sub) { sub.setAttribute('aria-expanded', 'false'); });

          return;
        }

        // กางกลุ่ม = โชว์เฉพาะแถวโมดูล ส่วนหัวข้อย่อยรอให้กดโมดูลอีกที
        var shown = 0;

        rows.forEach(function (row) {
          if (! row.classList.contains('mod-tr')) {
            return;
          }

          row.hidden = false;
          row.style.animation = 'fn-in .18s var(--ease) ' + (shown * 0.03) + 's both';
          shown++;
        });
      });
    });

    document.querySelectorAll('[data-fn-toggle]').forEach(function (btn) {
      var id = btn.getAttribute('data-fn-toggle');
      var rows = document.querySelectorAll('[data-fn-of="' + id + '"]');

      btn.addEventListener('click', function () {
        var open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', open ? 'false' : 'true');

        rows.forEach(function (row, i) {
          if (open) {
            row.hidden = true;
            row.style.animation = '';

            return;
          }

          row.hidden = false;
          // ไล่ทีละแถว จะได้เห็นว่ากางออกมาเป็นลำดับ ไม่โผล่พรวดทีเดียว
          row.style.animation = 'fn-in .18s var(--ease) ' + (i * 0.03) + 's both';
        });
      });
    });
  </script>

  <style>
    @keyframes fn-in {
      from { opacity: 0; transform: translateY(-3px); }
      to   { opacity: 1; transform: none; }
    }
    @media (prefers-reduced-motion: reduce) { .fn-tr { animation: none !important; } }
  </style>
@endsection
