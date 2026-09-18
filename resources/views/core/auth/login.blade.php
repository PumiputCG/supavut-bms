<!DOCTYPE html>
<html lang="th" data-lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ config('app.name') }} — เข้าสู่ระบบ / Sign in</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}">
  @include('layouts.theme')
  <style>
    /* ── โครงหน้า: เต็มจอ แบ่งซ้าย(แบรนด์) / ขวา(ฟอร์ม) ── */
    body { min-height: 100vh; min-height: 100dvh; background: var(--surface); }

    .login-card {
      display: grid; grid-template-columns: 46% 54%;
      width: 100%; min-height: 100vh; min-height: 100dvh;
      overflow: hidden; background: var(--surface);
    }

    /* ── ฝั่งซ้าย: แผงแบรนด์สีน้ำเงิน ────────────────────── */
    .brand {
      position: relative; display: grid; place-items: center;
      /* เว้นขวาไว้ให้ขอบคลื่น ไม่งั้นตัวหนังสือจะไปโผล่ทับส่วนโค้ง */
      padding: 40px 96px 40px 36px;
      overflow: hidden;
      background: linear-gradient(150deg, var(--royal-400) 0%, var(--royal) 55%, var(--navy-700) 100%);
      color: var(--on-dark);
    }
    /* ขอบขวาโค้งแบบคลื่น — วาดด้วย SVG ซ้อนทับ ไม่ใช่ border-radius เพราะต้องได้เส้นโค้งอิสระ */
    .brand-wave { position: absolute; inset: 0 -1px 0 auto; width: 84px; height: 100%; color: var(--surface); }
    .brand-deco { position: absolute; border-radius: 50%; background: rgb(255 255 255 / 7%); pointer-events: none; }
    /* เหลือแค่วงกลม — เจ้าของสั่งเอาลายจุดออกเมื่อ 2026-09-02 */
    .brand-deco.d1 { width: 340px; height: 340px; top: -120px; left: -90px; }
    .brand-deco.d2 { width: 400px; height: 400px; bottom: -150px; left: 6%; }
    .brand-deco.d3 { width: 190px; height: 190px; bottom: 18%; right: 16%; }

    .brand-inner { position: relative; z-index: 1; display: grid; gap: 20px; justify-items: center; text-align: center; }
    /* ขนาดยืดตามความกว้างจอ — จอแคบลงแล้วโลโก้กับตัวหนังสือต้องไม่ล้นไปทับขอบคลื่น */
    .brand-lockup { display: flex; align-items: center; gap: clamp(14px, 1.6vw, 22px); }
    .brand-mark { flex: 0 0 auto; width: clamp(86px, 9.2vw, 132px); height: auto; }
    .brand-divider { width: 1px; height: clamp(70px, 7vw, 96px); background: rgb(255 255 255 / 45%); }
    .brand-name { display: grid; gap: 4px; text-align: left; }
    .brand-name b { font-size: clamp(40px, 4.2vw, 60px); font-weight: 800; letter-spacing: -1.2px; line-height: 1; }
    .brand-name span { font-size: clamp(10px, 0.92vw, 13px); font-weight: 600; letter-spacing: 1.7px; white-space: nowrap; }
    /* ชื่อบริษัทคั่นระหว่างตัวย่อกับชื่อเต็ม — ชิดซ้ายแนวเดียวกับอีก 2 บรรทัด */
    .brand-name .brand-org { font-size: clamp(15px, 1.5vw, 21px); font-weight: 700; letter-spacing: 0.4px; opacity: 0.93; }
    .brand-rule { width: 360px; max-width: 100%; height: 1px; background: rgb(255 255 255 / 32%); }
    .brand-tagline { font-size: 21px; font-weight: 500; }

    /* ── ฝั่งขวา: ฟอร์ม ─────────────────────────────────── */
    .pane {
      position: relative; display: grid; grid-template-rows: auto 1fr auto;
      padding: 22px 56px 22px;
    }
    .pane-top { display: flex; justify-content: flex-end; }
    .pane-form { display: grid; align-content: center; gap: 18px; max-width: 400px; width: 100%; margin: 0 auto; }

    .pane-head { display: grid; gap: 4px; margin-bottom: 6px; }
    .pane-head h1 { color: var(--navy-800); font-size: 34px; font-weight: 800; letter-spacing: -.3px; }
    .pane-head p { color: var(--muted); font-size: var(--fs-md); }

    .check { display: inline-flex; align-items: center; gap: 9px; color: var(--ink-soft); font-size: var(--fs-base); cursor: pointer; }
    .check input { width: 17px; height: 17px; accent-color: var(--navy-800); cursor: pointer; }

    .err { color: var(--danger); font-size: var(--fs-sm); }

    .pane-foot {
      display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap;
      color: var(--muted); font-size: var(--fs-sm);
    }
    .pane-foot .sep { color: var(--line); }

    /* ── จอแคบ: ซ่อนแผงแบรนด์ เหลือฟอร์มอย่างเดียว ─────── */
    @media (max-width: 900px) {
      .login-card { grid-template-columns: 1fr; min-height: 100vh; min-height: 100dvh; align-content: start; }

      /*
        🔴 ของเดิมซ่อนแผงแบรนด์ทิ้งทั้งแผง (display: none)
           หน้าล็อกอินบนมือถือจึงเหลือฟอร์มขาวเปล่าๆ ไม่มีโลโก้ ไม่มีชื่อระบบ ไม่มีสีของแบรนด์
           เจ้าของแจ้ง 2026-09-18 ว่า "ไม่คล้ายกับบนคอมเลย" (เข้ามาจาก QR หน้าติดตาม)

           เปลี่ยนเป็น **หัวแบรนด์แถบเตี้ยบนฟอร์ม** — ใช้ชิ้นส่วนเดิมทุกชิ้น แค่ย่อขนาดและจัดใหม่
           ไม่ได้สร้างชุดตกแต่งใหม่สำหรับมือถือ หน้าตาจึงยังเป็นตัวเดียวกับบนคอม
      */
      .brand { display: grid; padding: 24px 20px 20px; }
      /* ขอบคลื่นเป็นของ "ขอบขวา" ตอนแบ่ง 2 คอลัมน์ — วางซ้อนแนวตั้งแล้วไม่มีความหมาย */
      .brand-wave { display: none; }
      .brand-inner { gap: 12px; }
      .brand-lockup { gap: 14px; }
      .brand-mark { width: 64px; }
      .brand-divider { height: 54px; }
      .brand-name b { font-size: 34px; }
      .brand-name .brand-org { font-size: 15px; }
      .brand-name span { font-size: 10px; letter-spacing: 1.4px; }
      .brand-rule { width: 220px; }
      .brand-tagline { font-size: 15px; }
      /* วงกลมตกแต่งย่อลงให้ได้บรรยากาศเดิมโดยไม่กินที่ */
      .brand-deco.d1 { width: 190px; height: 190px; top: -96px; left: -64px; }
      .brand-deco.d2 { width: 210px; height: 210px; bottom: -130px; left: 10%; }
      .brand-deco.d3 { display: none; }

      .pane { padding: 20px 24px; }
      .pane-form { padding: 18px 0 26px; }
    }
  </style>
</head>
<body>
  <main class="login-card">
    {{-- ───────── แผงแบรนด์ ───────── --}}
    <section class="brand" aria-hidden="true">
      <span class="brand-deco d1"></span>
      <span class="brand-deco d2"></span>
      <span class="brand-deco d3"></span>

      <div class="brand-inner">
        <div class="brand-lockup">
          {{-- โลโก้จริงที่เจ้าของให้มา (public/img/bms-logo.png) --}}
          <img class="brand-mark" src="{{ asset('img/bms-logo.png') }}" alt="SBMS" width="92" height="92">

          <span class="brand-divider"></span>

          <span class="brand-name">
            <b>SBMS</b>
            <span class="brand-org">Supavut</span>
            <span>BUSINESS MANAGEMENT SYSTEM</span>
          </span>
        </div>

        <span class="brand-rule"></span>
        <p class="brand-tagline" data-i18n="app.tagline">ระบบบริหารจัดการธุรกิจ</p>
      </div>

      {{-- ขอบโค้งฝั่งขวาให้กลืนกับพื้นขาวของฟอร์ม --}}
      <svg class="brand-wave" viewBox="0 0 84 620" preserveAspectRatio="none" fill="currentColor">
        <path d="M84 0H30c0 104-30 142-30 214s34 104 34 176-34 96-34 158 30 72 30 72h54V0Z"></path>
      </svg>
    </section>

    {{-- ───────── ฟอร์มเข้าสู่ระบบ ───────── --}}
    <section class="pane">
      <div class="pane-top">
        @include('layouts.partials.lang-switch')
      </div>

      <form class="pane-form" method="POST" action="{{ route('login.attempt') }}" novalidate>
        @csrf

        <div class="pane-head">
          <h1 data-i18n="login.title">เข้าสู่ระบบ</h1>
          <p data-i18n="login.subtitle">เข้าสู่ระบบเพื่อใช้งาน</p>
        </div>

        @if (session('flash_error'))
          <p class="flash flash-error"
             data-loc-th="{{ session('flash_error')['th'] ?? '' }}"
             data-loc-en="{{ session('flash_error')['en'] ?? '' }}">{{ session('flash_error')['th'] ?? '' }}</p>
        @endif
        @if (session('flash_success'))
          <p class="flash flash-ok"
             data-loc-th="{{ session('flash_success')['th'] ?? '' }}"
             data-loc-en="{{ session('flash_success')['en'] ?? '' }}">{{ session('flash_success')['th'] ?? '' }}</p>
        @endif

        <div class="field">
          <div class="input-wrap">
            <svg class="lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"></path>
            </svg>
            <input class="input has-lead @error('employee_code') is-invalid @enderror"
                   type="text" name="employee_code" value="{{ old('employee_code', request()->cookie('bms_last_code')) }}"
                   autocomplete="username" autocapitalize="off" spellcheck="false" required autofocus
                   placeholder="รหัสพนักงาน" data-i18n-placeholder="login.employeeCode"
                   aria-label="รหัสพนักงาน" data-i18n-aria="login.employeeCode">
          </div>
          @error('employee_code')
            <span class="err"
                  data-loc-th="{{ session('field_error.th') ?? $message }}"
                  data-loc-en="{{ session('field_error.en') ?? $message }}">{{ $message }}</span>
          @enderror
        </div>

        <div class="field">
          <div class="input-wrap">
            <svg class="lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="4" y="10" width="16" height="10" rx="2.5"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
            </svg>
            <input class="input has-lead has-trail @error('password') is-invalid @enderror"
                   type="password" name="password" id="password" autocomplete="current-password" required
                   placeholder="รหัสผ่าน" data-i18n-placeholder="login.password"
                   aria-label="รหัสผ่าน" data-i18n-aria="login.password">
            <button type="button" class="trail" data-toggle-password
                    aria-label="แสดงรหัสผ่าน" data-i18n-aria="login.showPassword">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M1.6 12S5.7 5.5 12 5.5 22.4 12 22.4 12 18.3 18.5 12 18.5 1.6 12 1.6 12Z"></path>
                <circle cx="12" cy="12" r="3.2"></circle>
              </svg>
            </button>
          </div>
          @error('password')
            <span class="err"
                  data-loc-th="{{ session('field_error.th') ?? $message }}"
                  data-loc-en="{{ session('field_error.en') ?? $message }}">{{ $message }}</span>
          @enderror
        </div>

        <label class="check">
          <input type="checkbox" name="remember" value="1" @checked(request()->cookie('bms_last_code'))>
          <span data-i18n="login.remember">จำรหัสพนักงานไว้</span>
        </label>

        <button type="submit" class="btn btn-block" style="min-height:52px" data-submit>
          <span data-i18n="login.submit">เข้าสู่ระบบ</span>
        </button>
      </form>

      <div class="pane-foot">
        <span>&copy; {{ date('Y') }}
          <span data-loc-th="{{ config('bms.company_th') }}" data-loc-en="{{ config('bms.company_en') }}">{{ config('bms.company_th') }}</span>
        </span>
        <span class="sep">|</span>
        <span>{{ config('app.name') }} v{{ config('bms.version') }}</span>
      </div>
    </section>
  </main>

  @include('layouts.i18n')

  {{-- จอ "กำลังโหลด" — หน้านี้ไม่ได้ extends layouts.app จึงต้อง include เอง --}}
  @include('layouts.partials.loading')

  <script>
    'use strict';
    (function () {
      // ปุ่มตา: สลับแสดง/ซ่อนรหัสผ่าน พร้อมเปลี่ยนคำอธิบายให้ screen reader
      var toggle = document.querySelector('[data-toggle-password]');
      var input = document.getElementById('password');
      if (toggle && input) {
        toggle.addEventListener('click', function () {
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          toggle.setAttribute('data-i18n-aria', show ? 'login.hidePassword' : 'login.showPassword');
          toggle.setAttribute('aria-label', window.BMS.t(show ? 'login.hidePassword' : 'login.showPassword'));
          input.focus();
        });
      }

      // กันกดซ้ำระหว่างรอเซิร์ฟเวอร์ตอบ
      var form = document.querySelector('.pane-form');
      var submit = document.querySelector('[data-submit]');
      if (form && submit) {
        form.addEventListener('submit', function () {
          submit.disabled = true;
          submit.querySelector('span').textContent = window.BMS.t('login.submitting', 'กำลังเข้าสู่ระบบ...');
        });
      }
    })();
  </script>
</body>
</html>
