{{--
  หน้าบอกข้อผิดพลาดของระบบ — ใช้ร่วมกันทุกรหัส (403 · 404)

  🔴 มีเพราะบั๊กจริง 2026-09-18 (เจ้าของแจ้งจากมือถือ)
     "คนที่ไม่มีสิทธิ ให้มีปุ่มกลับ เพราะมันค้างไม่มีปุ่มให้กด และล็อคเอ้าไม่ได้"
     ของเดิมใช้หน้า error ดิบของ Laravel ซึ่ง **ไม่มีเมนู ไม่มีปุ่ม ไม่มีทางออกเลย**
     บนคอมยังกดปุ่มย้อนกลับของเบราว์เซอร์ได้ แต่บนมือถือที่เปิดจากลิงก์ตรง = ตัน

  🔴 เป็นหน้าเดี่ยว ไม่ @extends('layouts.app')
     เพราะโครงหน้าต้องใช้ $me ที่ middleware แชร์ไว้ ถ้าเกิด error ตอนยังไม่ผ่าน middleware
     (เช่นคนที่ยังไม่ล็อกอิน) หน้า error จะพังเองกลายเป็น 500 ซ้อน error เดิม

  ตัวแปรที่ต้องส่งมา: $code · $titleTh/$titleEn · $textTh/$textEn
--}}
@php
  // ข้อความ 2 ภาษาแบบหน้าเดี่ยว — ตัวแปลกลางสลับให้เองตอนผู้ใช้เปลี่ยนภาษา
  $L = fn ($th, $en) => new \Illuminate\Support\HtmlString('<span data-loc-th="'.e($th).'" data-loc-en="'.e($en).'">'.e($th).'</span>');

  // 🔴 ถามจาก container ไม่ใช่ session ตรงๆ — ผ่าน middleware มาแล้วเท่านั้นถึงจะมีค่า
  //    ใช้ bound() ก่อนเสมอ ไม่งั้นหน้านี้จะพังเองตอนคนที่ยังไม่ล็อกอินมาถึง
  $me = app()->bound('current_user') ? app('current_user') : null;
@endphp
<!DOCTYPE html>
<html lang="th" data-lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ config('app.name') }} — {{ $titleTh }} / {{ $titleEn }}</title>

  @include('layouts.theme')

  <style>
    body { background: var(--bg); }

    /* กึ่งกลางจอทั้งแนวตั้งและแนวนอน — dvh เพราะบนมือถือ vh สูงเกินที่ตามองเห็น */
    .er-wrap {
      min-height: 100vh; min-height: 100dvh;
      /* 🔴 ต้องมี minmax(0, 1fr) (บทเรียนเดิมของโปรเจค)
         ไม่งั้นคอลัมน์เป็น auto = กว้างตามเนื้อหา แล้ว width: min(420px, 100%) ของการ์ด
         จะอ้าง 100% กับคอลัมน์ที่ยืดตามตัวเอง — วัดจริงที่ 390px แล้วการ์ดล้นขอบขวา */
      display: grid; grid-template-columns: minmax(0, 1fr); place-items: center;
      padding: 24px 16px;
    }

    .er-card {
      width: min(420px, 100%);
      display: grid; gap: 12px; justify-items: center; text-align: center;
      padding: 26px 20px;
      border: 1px solid var(--line); border-radius: var(--radius-lg);
      background: var(--surface); box-shadow: var(--shadow-sm);
    }

    .er-mark {
      display: grid; place-items: center;
      width: 52px; height: 52px; border-radius: 50%;
      background: var(--surface-3); color: var(--muted);
    }

    .er-code { color: var(--muted); font-size: var(--fs-xs); letter-spacing: 1px; }
    .er-card b { color: var(--navy-900); font-size: var(--fs-md); }
    .er-card p { margin: 0; color: var(--ink-soft); font-size: var(--fs-sm); line-height: 1.6; }

    /* ปุ่มเรียงลงมาบนมือถือ เต็มความกว้างการ์ด จะได้กดง่ายด้วยนิ้ว */
    .er-acts { display: grid; gap: 8px; width: 100%; margin-top: 4px; }
    .er-acts form { display: grid; }
    .er-acts .btn { justify-content: center; }

    .er-lang { margin-top: 2px; }
  </style>
</head>
<body>
  <div class="er-wrap">
    <div class="er-card">
      <span class="er-mark">
        @if ($code === 403 || $code === 419)
          {{-- แม่กุญแจ = ปิดอยู่ ไม่ใช่ระบบพัง จึงไม่ใช้สีแดง --}}
          <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="4" y="10" width="16" height="10" rx="2"></rect>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
          </svg>
        @else
          <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="m20 20-3.5-3.5"></path>
          </svg>
        @endif
      </span>

      <span class="er-code">{{ $code }}</span>
      <b>{{ $L($titleTh, $titleEn) }}</b>
      <p>{{ $L($textTh, $textEn) }}</p>

      <div class="er-acts">
        @if ($me)
          {{-- 🔴 กลับไป /profile ไม่ใช่ /dashboard — แดชบอร์ดมีด่านสิทธิ์ของตัวเอง
                 คนที่เพิ่งโดน 403 อาจโดนซ้ำอีกที แล้ววนอยู่ในหน้านี้ไม่จบ --}}
          <a class="btn btn-block" href="{{ route('profile') }}">{{ $L('กลับไปหน้าข้อมูลส่วนตัว', 'Back to my profile') }}</a>

          {{-- เจ้าของแจ้งว่า "ล็อคเอ้าไม่ได้" — หน้าตันต้องออกจากระบบได้เสมอ --}}
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-quiet btn-block">{{ $L('ออกจากระบบ', 'Sign out') }}</button>
          </form>
        @else
          <a class="btn btn-block" href="{{ route('login') }}">{{ $L('เข้าสู่ระบบ', 'Sign in') }}</a>
        @endif
      </div>

      <div class="er-lang">
        @include('layouts.partials.lang-switch')
      </div>
    </div>
  </div>

  @include('layouts.i18n')
</body>
</html>
