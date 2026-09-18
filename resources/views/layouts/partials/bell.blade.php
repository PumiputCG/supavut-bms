{{--
  กระดิ่งแจ้งเตือน — ใช้ร่วมทุกโมดูล

  🔴 หน้าตาแบบเดียวกับ Insight → Time&Leave Approval
     ตัวเลขแดง ตัวอักษรขาว เกาะมุมขวาบนของกระดิ่ง

  โครง 2 หน้าจอ (เจ้าของสั่ง 2026-09-04)
    หน้าที่ 1  รายชื่อโมดูลเรียงเป็นรายการแอป — ไอคอน + ชื่อ + ตัวเลขแดง
    หน้าที่ 2  กดโมดูลแล้ว "สลับ" มาหน้าแจ้งเตือนของโมดูลนั้น
               มีแท็บด้านบนบอกว่าเป็นแจ้งเตือนเรื่องอะไร (หัวข้อย่อย)
               มีปุ่ม "<" มุมซ้ายบนย้อนกลับไปหน้ารายการโมดูล

  🔴 ไม่ใช้ <details> ซ้อนกันแล้ว — เจ้าของอยากได้แบบสลับหน้า ไม่ใช่กางซ้อนลงไปเรื่อยๆ
--}}
@php
  $bellGroups = app(\App\Services\Core\Notifier::class)->grouped($me ?? null);
  $bellCount = collect($bellGroups)->sum('count');
@endphp

<div class="bell" data-bell>
  <button type="button" class="icon-btn" data-bell-toggle
          aria-label="การแจ้งเตือน" data-i18n-aria="top.notifications"
          aria-haspopup="dialog" aria-expanded="false">
    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"></path><path d="M13.7 21a2 2 0 0 1-3.4 0"></path>
    </svg>

    {{-- ตัวเลขแดงมุมขวาบน — เกิน 99 ขึ้น 99+ ไม่งั้นล้นกรอบ --}}
    <span class="bell-dot" data-bell-count @if (! $bellCount) hidden @endif>{{ $bellCount > 99 ? '99+' : $bellCount }}</span>
  </button>

  <div class="bell-pop" data-bell-pop hidden role="dialog" aria-labelledby="bell-title">
    <div class="bell-head">
      {{-- ปุ่มย้อนกลับ — โผล่เฉพาะตอนอยู่ในหน้าของโมดูล --}}
      <button type="button" class="bell-back" data-bell-back hidden
              aria-label="ย้อนกลับ" data-i18n-aria="bell.back">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M15 18 9 12l6-6"></path>
        </svg>
      </button>

      <b id="bell-title" data-bell-title data-i18n="top.notifications">การแจ้งเตือน</b>

      @if ($bellCount)
        <button type="button" class="link-btn" data-bell-readall data-i18n="bell.readAll">อ่านทั้งหมด</button>
      @endif
    </div>

    {{-- ═══ หน้าที่ 1 · รายการโมดูล ═══ --}}
    <div class="bell-body" data-bell-view="apps">
      @forelse ($bellGroups as $group)
        <button type="button" class="bell-app" data-bell-open="{{ $group['id'] }}">
          <span class="bell-app-ico">
            @include('layouts.partials.nav-icon', ['name' => $group['icon']])
          </span>
          <span class="bell-app-name" data-i18n="{{ $group['key'] }}">{{ $group['th'] }}</span>
          <span class="bell-n">{{ $group['count'] }}</span>
          <svg class="bell-app-go" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m9 18 6-6-6-6"></path>
          </svg>
        </button>
      @empty
        <p class="bell-empty" data-i18n="bell.empty">ยังไม่มีแจ้งเตือน</p>
      @endforelse
    </div>

    {{-- ═══ หน้าที่ 2 · แจ้งเตือนของแต่ละโมดูล (ทีละโมดูล) ═══ --}}
    @foreach ($bellGroups as $group)
      <div class="bell-body" data-bell-view="{{ $group['id'] }}"
           data-bell-name="{{ $group['th'] }}" data-bell-key="{{ $group['key'] }}" hidden>

        {{-- แท็บบอกว่าเป็นแจ้งเตือนเรื่องอะไร — มีหัวข้อเดียวก็ยังขึ้น จะได้รู้ว่ากำลังดูอะไรอยู่ --}}
        <div class="bell-tabs" role="tablist">
          @foreach ($group['functions'] as $i => $fn)
            <button type="button" class="bell-tab" role="tab"
                    data-bell-tab="{{ $group['id'] }}-{{ $i }}"
                    aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
              <span data-i18n="{{ $fn['key'] }}">{{ $fn['th'] }}</span>
              <span class="bell-n">{{ $fn['count'] }}</span>
            </button>
          @endforeach
        </div>

        @foreach ($group['functions'] as $i => $fn)
          <div class="bell-panel" data-bell-panel="{{ $group['id'] }}-{{ $i }}" @if ($i > 0) hidden @endif>
            @foreach ($fn['items'] as $item)
              {{--
                🔴 ทุกบรรทัดต้องมีหัวข้อกำกับ (เจ้าของสั่ง 2026-09-04)
                   ป้ายของบรรทัดที่ 2 มาจาก subject_key ของแจ้งเตือนใบนั้น
                   โมดูลงบประมาณใช้ "แผนก" · โมดูลอื่นส่งป้ายของตัวเองมาได้
              --}}
              {{-- 🔴 ลิงก์สร้างตอนแสดงผล ไม่ใช่ที่อยู่เต็มที่เก็บไว้ตอนส่ง (บั๊กจริง 2026-09-10) --}}
              <a class="bell-item" href="{{ $item->link() ?: ($fn['url'] ?? '#') }}"
                 data-bell-read="{{ $item->id }}">
                @if ($item->doc_no)
                  <span class="bell-line">
                    <span class="bell-key" data-i18n="bell.docNo">เลขที่</span>
                    <span class="bell-val bell-val-doc">{{ $item->doc_no }}</span>
                  </span>
                @endif

                @if ($item->title_th)
                  <span class="bell-line">
                    <span class="bell-key" data-i18n="{{ $item->subject_key ?: 'bell.subject' }}">หัวข้อ</span>
                    <span class="bell-val"
                          data-loc-th="{{ $item->title_th }}" data-loc-en="{{ $item->title_en }}">{{ $item->title_th }}</span>
                  </span>
                @endif

                <span class="bell-line">
                  <span class="bell-key" data-i18n="bell.message">แจ้งเตือน</span>
                  {{-- ผลลัพธ์อ่านได้จากสีทันที — เขียว = อนุมัติ · แดง = ไม่อนุมัติ --}}
                  <span class="bell-val bell-tone-{{ $item->tone ?: 'none' }}"
                        data-loc-th="{{ $item->body_th }}" data-loc-en="{{ $item->body_en }}">{{ $item->body_th }}</span>
                </span>

                {{-- บรรทัดเสริมที่ 2 เช่น ความเห็นของผู้อนุมัติ (ต้องมาก่อน "วันเวลา" เสมอ) --}}
                @if ($item->note_th)
                  {{-- บรรทัดเสริม เช่น "เหตุผล" — ป้ายสีดำ ตัวข้อมูลสีตามผล --}}
                  <span class="bell-line">
                    <span class="bell-key" data-i18n="{{ $item->note_key ?: 'bell.note' }}">หมายเหตุ</span>
                    <span class="bell-val bell-tone-{{ $item->tone ?: 'none' }}"
                          data-loc-th="{{ $item->note_th }}" data-loc-en="{{ $item->note_en }}">{{ $item->note_th }}</span>
                  </span>
                @endif

                @if ($item->note2_th)
                  <span class="bell-line">
                    <span class="bell-key" data-i18n="{{ $item->note2_key ?: 'bell.note' }}">หมายเหตุ</span>
                    <span class="bell-val {{ $item->tone === 'no' ? 'bell-tone-no' : ($item->tone === 'ok' ? 'bell-tone-ok' : '') }}"
                          data-loc-th="{{ $item->note2_th }}" data-loc-en="{{ $item->note2_en }}">{{ $item->note2_th }}</span>
                  </span>
                @endif

                <span class="bell-line">
                  <span class="bell-key" data-i18n="bell.when">วันเวลา</span>
                  <span class="bell-val bell-val-time">{{ $item->created_at?->format('d/m/Y H:i') }}</span>
                </span>
              </a>
            @endforeach

            @if ($fn['count'] > count($fn['items']) && $fn['url'])
              <a class="bell-more" href="{{ $fn['url'] }}">
                <span data-i18n="bell.seeMore">ดูทั้งหมด</span> ({{ $fn['count'] }})
              </a>
            @endif
          </div>
        @endforeach
      </div>
    @endforeach
  </div>
</div>

<style>
  /* ── กระดิ่ง + ตัวเลขแดง ─────────────────────────────────── */
  .bell { position: relative; display: inline-flex; }

  .bell-dot {
    position: absolute; top: 2px; right: 2px;
    min-width: 16px; height: 16px; padding: 0 4px;
    display: grid; place-items: center;
    border-radius: 999px; background: #e02424; color: #fff;
    font-size: 10px; font-weight: 700; line-height: 1;
    box-shadow: 0 0 0 2px var(--navy-900);
  }

  .bell-dot[hidden] { display: none; }

  /* ── แผงแจ้งเตือน ────────────────────────────────────────── */
  .bell-pop {
    position: absolute; top: calc(100% + 8px); right: 0; z-index: var(--z-dropdown);
    width: min(380px, calc(100vw - 24px));
    max-height: min(70vh, 520px);
    max-height: min(70dvh, 520px);
    display: flex; flex-direction: column;
    border: 1px solid var(--line); border-radius: var(--radius);
    background: var(--surface); box-shadow: var(--shadow-lg);
    color: var(--ink); font-size: var(--fs-sm);
    overflow: hidden;                       /* กันแท็บล้นออกนอกมุมโค้ง */
  }

  .bell-pop[hidden] { display: none; }

  /*
    🔴 มือถือ: กางเต็มความกว้างจอ ไม่ใช่ห้อยจากปุ่ม (บั๊กจริง 2026-09-18)
       ของเดิมยึด right: 0 ของปุ่มกระดิ่ง ซึ่งอยู่กลางแถบบน แล้วกว้างถึง 380px
       ขอบซ้ายของแผงจึงเลยขอบจอไปทางซ้าย ~30px แล้วถูกตัดหายไปเลย
  */
  @media (max-width: 560px) {
    .bell-pop {
      position: fixed; top: calc(var(--topbar-h) + 4px);
      left: 8px; right: 8px; width: auto;
      max-height: calc(100dvh - var(--topbar-h) - 16px);
    }
  }

  .bell-head {
    display: flex; align-items: center; gap: 8px; flex: 0 0 auto;
    padding: 10px 14px; border-bottom: 1px solid var(--line-soft);
  }

  .bell-head b { flex: 1; min-width: 0; color: var(--navy-900); }

  /* ปุ่มย้อนกลับ — อยู่ซ้ายสุดของหัวแผง เหมือนปุ่มถอยในแอปมือถือ */
  .bell-back {
    display: grid; place-items: center; width: 24px; height: 24px; flex: 0 0 auto;
    margin-left: -4px;
    border: 0; border-radius: var(--radius-sm); background: transparent;
    color: var(--ink-soft); cursor: pointer;
  }

  .bell-back:hover { background: var(--surface-2); color: var(--navy-800); }
  .bell-back[hidden] { display: none; }

  .bell-body { flex: 1; overflow-y: auto; padding: 4px 0; }
  .bell-body[hidden] { display: none; }

  /* ── หน้าที่ 1: รายการโมดูล ─────────────────────────────── */
  .bell-app {
    display: flex; align-items: center; gap: 10px; width: 100%;
    padding: 10px 14px; border: 0; background: transparent;
    font: inherit; text-align: left; cursor: pointer;
  }

  .bell-app:hover { background: var(--surface-2); }

  /* กรอบไอคอนแบบไอคอนแอป — ให้กวาดตาเห็นว่าเป็นคนละโมดูลกัน */
  .bell-app-ico {
    display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 auto;
    border-radius: var(--radius); background: var(--surface-2);
  }

  .bell-app-ico .nav-icon { width: 20px; height: 20px; }
  .bell-app-name { flex: 1; min-width: 0; color: var(--navy-900); font-weight: 700; }
  .bell-app-go { color: var(--muted); flex: 0 0 auto; }

  /* ตัวเลขของแต่ละชั้น — แดงเหมือนบนกระดิ่ง จะได้รู้ว่านับอะไรอยู่ */
  .bell-n {
    min-width: 18px; padding: 1px 6px; flex: 0 0 auto;
    border-radius: 999px; background: #e02424; color: #fff;
    font-size: 10px; font-weight: 700; line-height: 1.55; text-align: center;
  }

  /* ── หน้าที่ 2: แท็บหัวข้อย่อย ──────────────────────────── */
  .bell-tabs {
    display: flex; gap: 2px; flex: 0 0 auto;
    padding: 0 8px; margin-bottom: 4px;
    border-bottom: 1px solid var(--line-soft);
    overflow-x: auto;                        /* หัวข้อเยอะให้เลื่อนในแถบตัวเอง */
    scrollbar-width: none;
  }

  .bell-tabs::-webkit-scrollbar { display: none; }

  .bell-tab {
    display: inline-flex; align-items: center; gap: 6px; flex: 0 0 auto;
    padding: 9px 10px; border: 0; border-bottom: 2px solid transparent;
    background: transparent; color: var(--ink-soft);
    font: inherit; font-size: var(--fs-xs); font-weight: 600;
    white-space: nowrap; cursor: pointer;
  }

  .bell-tab:hover { color: var(--navy-800); }

  .bell-tab[aria-selected="true"] {
    color: var(--navy-900);
    border-bottom-color: var(--accent);
  }

  .bell-panel[hidden] { display: none; }

  /* ── รายการเรื่อง ───────────────────────────────────────── */
  .bell-item {
    display: grid; gap: 3px;
    padding: 9px 14px; border-left: 2px solid transparent;
    color: inherit; text-decoration: none;
  }

  .bell-item + .bell-item { border-top: 1px solid var(--line-soft); }
  .bell-item:hover { background: var(--surface-2); border-left-color: var(--accent); }

  /* 1 บรรทัด = ป้าย + ค่า · ป้ายกว้างเท่ากันทุกบรรทัด ค่าจึงเรียงตรงกัน */
  .bell-line { display: grid; grid-template-columns: 58px 1fr; gap: 8px; align-items: baseline; }
  /* 🔴 ป้ายเป็นตัวอักษรสีดำเสมอ สีบอกผลอยู่ที่ "ค่า" เท่านั้น (เจ้าของสั่ง 2026-09-04) */
  .bell-key { color: var(--ink); font-size: var(--fs-xs); }
  .bell-val { color: var(--ink); font-size: var(--fs-xs); overflow-wrap: anywhere; }
  .bell-val-doc { color: var(--navy-900); font-weight: 700; font-variant-numeric: tabular-nums; }
  .bell-val-time { color: var(--muted); font-variant-numeric: tabular-nums; }

  /*
    สีของผลลัพธ์ (เจ้าของสั่ง 2026-09-04)
    🔴 สีอยู่ที่ "ค่า" เท่านั้น ป้ายเป็นสีเทาคงที่ทุกบรรทัด จะได้กวาดตาอ่านป้ายได้ง่าย
  */
  .bell-tone-ok { color: var(--ok); font-weight: 600; }
  .bell-tone-no { color: var(--danger); font-weight: 600; }

  .bell-more { display: block; padding: 7px 14px; color: var(--accent); font-size: var(--fs-xs); }
  .bell-empty { padding: 26px 14px; color: var(--muted); font-size: var(--fs-sm); text-align: center; }
</style>

<script>
  'use strict';

  (function () {
    var wrap = document.querySelector('[data-bell]');
    if (!wrap) { return; }

    var btn = wrap.querySelector('[data-bell-toggle]');
    var pop = wrap.querySelector('[data-bell-pop]');
    var dot = wrap.querySelector('[data-bell-count]');
    var back = pop.querySelector('[data-bell-back]');
    var title = pop.querySelector('[data-bell-title]');
    var apps = pop.querySelector('[data-bell-view="apps"]');
    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : null;

    /*
      สลับหน้าในแผง — ไม่ใช่กางซ้อนลงไป (เจ้าของสั่ง 2026-09-04)
      ส่ง null = กลับหน้ารายการโมดูล
    */
    function show(view) {
      pop.querySelectorAll('[data-bell-view]').forEach(function (v) {
        v.hidden = view ? v.getAttribute('data-bell-view') !== view : v.getAttribute('data-bell-view') !== 'apps';
      });

      back.hidden = ! view;

      if (view) {
        var box = pop.querySelector('[data-bell-view="' + view + '"]');
        // ชื่อโมดูลขึ้นเป็นหัวแผง จะได้รู้ว่าอยู่ในโมดูลไหน · ตัวสลับภาษาต้องเห็นด้วย
        title.textContent = box.getAttribute('data-bell-name');
        title.setAttribute('data-i18n', box.getAttribute('data-bell-key'));
        box.scrollTop = 0;
      } else {
        title.textContent = window.BMS.lang() === 'en' ? 'Notifications' : 'การแจ้งเตือน';
        title.setAttribute('data-i18n', 'top.notifications');
      }

      if (window.BMS && window.BMS.refresh) { window.BMS.refresh(); }
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      // เปิดได้ทีละแผง — ปิดแผงช่วยเหลือ/เมนูผู้ใช้/ภาษา ที่ค้างอยู่ก่อน
      if (window.BMS && window.BMS.closePopovers) { window.BMS.closePopovers(pop); }
      pop.hidden = !pop.hidden;
      btn.setAttribute('aria-expanded', pop.hidden ? 'false' : 'true');

      // เปิดใหม่ทุกครั้งเริ่มที่หน้ารายการโมดูลเสมอ
      if (!pop.hidden) { show(null); }
    });

    back.addEventListener('click', function () { show(null); });

    pop.querySelectorAll('[data-bell-open]').forEach(function (b) {
      b.addEventListener('click', function () { show(b.getAttribute('data-bell-open')); });
    });

    // แท็บในหน้าของโมดูล
    pop.querySelectorAll('[data-bell-tab]').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var id = tab.getAttribute('data-bell-tab');
        var group = tab.closest('[data-bell-view]');

        group.querySelectorAll('[data-bell-tab]').forEach(function (t) {
          t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
        });

        group.querySelectorAll('[data-bell-panel]').forEach(function (p) {
          p.hidden = p.getAttribute('data-bell-panel') !== id;
        });
      });
    });

    // กดที่อื่นให้ปิด — แต่กดในแผงต้องไม่ปิด (ยังสลับหน้า/แท็บต่อได้)
    document.addEventListener('click', function (e) {
      if (!wrap.contains(e.target)) { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || pop.hidden) { return; }

      // Esc ครั้งแรกถอยกลับหน้ารายการโมดูลก่อน ครั้งที่สองค่อยปิดแผง
      if (!back.hidden) { show(null); return; }

      pop.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
    });

    function post(url, body) {
      return fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(body || {}),
      });
    }

    /*
      ทาสีเลขแดงใหม่ทั้งหน้าจากตัวเลขที่เซิร์ฟเวอร์เพิ่งตอบกลับมา

      🔴 เลขแดงที่เมนูซ้าย เมนูย่อย และในตาราง วาดมาจากเซิร์ฟเวอร์ตอนโหลดหน้า
         กดอ่านทีหลังแล้วไม่มีใครไปลบให้ มันเลยค้าง (เจ้าของแจ้ง 2026-09-07)
         ตัวนี้เดินไล่ทุกป้ายที่ติดคีย์ไว้ แล้วเขียนตัวเลขใหม่ให้ทั้งหมดในทีเดียว
    */
    function repaint(data) {
      if (! data || ! data.ok) { return; }

      var map = data.map || {};
      var docs = data.docs || {};

      // กระดิ่ง
      dot.textContent = data.unread > 99 ? '99+' : data.unread;
      dot.hidden = ! data.unread;

      // เมนูซ้าย + เมนูย่อย — คีย์ที่ยุบรวมกันคั่นด้วย , ต้องบวกรวมเหมือนฝั่งเซิร์ฟเวอร์
      document.querySelectorAll('[data-badge-key]').forEach(function (el) {
        var n = 0;

        el.getAttribute('data-badge-key').split(',').forEach(function (k) {
          if (k) { n += map[k] || 0; }
        });

        el.textContent = n > 99 ? '99+' : n;
        el.hidden = n < 1;
      });

      // เลขแดงประจำแถวในตาราง
      document.querySelectorAll('[data-badge-doc]').forEach(function (el) {
        var n = 0;

        el.getAttribute('data-badge-doc').split(',').forEach(function (d) {
          if (d) { n += docs[d] || 0; }
        });

        el.textContent = n > 9 ? '9+' : n;
        el.hidden = n < 1;
      });
    }

    function readThen(body, after) {
      post(@json(route('notifications.read')), body)
        .then(function (r) { return r.json(); })
        .then(function (d) {
          repaint(d);

          if (after) { after(d); }
        })
        .catch(function () { /* อ่านไม่สำเร็จก็ไม่ต้องทำอะไร ตัวเลขเดิมยังถูกอยู่ */ });
    }

    // กดเรื่อง -> ทำเครื่องหมายว่าอ่านแล้วก่อนค่อยไป
    pop.querySelectorAll('[data-bell-read]').forEach(function (a) {
      a.addEventListener('click', function () {
        readThen({ id: a.getAttribute('data-bell-read') });
      });
    });

    var readAll = pop.querySelector('[data-bell-readall]');

    if (readAll) {
      readAll.addEventListener('click', function () {
        readThen({ all: true }, function () {
          show(null);

          pop.querySelectorAll('[data-bell-view]:not([data-bell-view="apps"])').forEach(function (v) { v.remove(); });
          apps.innerHTML = '<p class="bell-empty">' +
            (window.BMS.lang() === 'en' ? 'No notifications' : 'ยังไม่มีแจ้งเตือน') + '</p>';
          readAll.hidden = true;
        });
      });
    }
  })();
</script>
