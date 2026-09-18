{{--
  สแกนแล้วไม่พบเอกสาร (เจ้าของสั่ง 2026-09-18)

  🔴 ตอบเหมือนกันทุกกรณี — กุญแจผิดรูป · ไม่มีในระบบ · เอกสารถูกลบ
     ข้อความที่ต่างกันจะกลายเป็นตัวบอกคนเดาว่ากุญแจไหน "เกือบถูก"
  🔴 ต้องเป็นหน้าที่อ่านรู้เรื่อง ไม่ใช่หน้า error ดิบของเซิร์ฟเวอร์
     เพราะคนที่มาถึงหน้านี้คือคนที่ยกโทรศัพท์ขึ้นสแกนกระดาษ ไม่ใช่คนที่พิมพ์ URL ผิด
--}}
@php
  $L = fn ($th, $en) => new \Illuminate\Support\HtmlString('<span data-loc-th="'.e($th).'" data-loc-en="'.e($en).'">'.e($th).'</span>');
@endphp

<title>ไม่พบเอกสาร · SBMS</title>

@include('layouts.theme')

<style>
  body { background: var(--bg); }

  .tk-wrap { max-width: 480px; margin: 0 auto; padding: 48px 16px; display: grid; gap: 14px; }

  .tk-card {
    background: var(--surface); border: 1px solid var(--line);
    border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
    padding: 22px 18px; text-align: center; display: grid; gap: 10px; justify-items: center;
  }

  .tk-card b { color: var(--navy-900); font-size: var(--fs-md); }
  .tk-card p { margin: 0; color: var(--ink-soft); font-size: var(--fs-sm); line-height: 1.6; }

  /* วงกลมเครื่องหมายคำถาม — บอกว่า "หาไม่เจอ" ไม่ใช่ "ระบบพัง" จึงไม่ใช้สีแดง */
  .tk-mark {
    width: 44px; height: 44px; border-radius: 50%;
    display: grid; place-items: center;
    background: var(--surface-3); color: var(--muted);
    font-size: var(--fs-lg); font-weight: 700;
  }
</style>

<div class="tk-wrap">
  <div class="tk-card">
    <span class="tk-mark">?</span>
    <b>{{ $L('ไม่พบเอกสารนี้', 'Document not found') }}</b>
    <p>{{ $L('QR Code นี้อาจเป็นของเอกสารที่ถูกลบไปแล้ว หรือสแกนไม่ครบทั้งดวง — ลองสแกนอีกครั้งให้เห็น QR ทั้งสี่มุม',
            'This QR code may belong to a deleted document, or the scan was incomplete — try again with all four corners in view') }}</p>
    <a class="btn" href="{{ route('login') }}">{{ $L('ไปหน้าเข้าสู่ระบบ', 'Go to sign in') }}</a>
  </div>
</div>

@include('layouts.i18n')
