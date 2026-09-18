@extends('layouts.app')

@section('title', 'หน้าหลัก / Home')

@push('page-style')
  <style>
    :root { --page-max: 900px; }

    /* หน้าหลัก — ยังไม่ใส่เนื้อหา รอเจ้าของกำหนดว่าจะให้แสดงอะไร */
    .home-empty {
      display: grid; gap: 6px; place-items: center;
      padding: 64px 20px; text-align: center;
    }
    .home-empty img { width: 56px; height: 56px; opacity: .55; }
    .home-empty p { color: var(--muted); font-size: var(--fs-sm); }
  </style>
@endpush

@section('content')
  <section class="card">
    <div class="card-head">
      <h3 data-i18n="home.title">หน้าหลัก</h3>
    </div>

    <div class="home-empty">
      <img src="{{ asset('img/nav/dashboard.png') }}" alt="" width="56" height="56">
      <p data-i18n="home.empty">ยังไม่มีเนื้อหาในหน้านี้</p>
    </div>
  </section>
@endsection
