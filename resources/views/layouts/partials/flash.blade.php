{{--
  ข้อความแจ้งผลหลังบันทึก — ใช้ร่วมทุกหน้า
  include ไว้ที่ layouts/app.blade.php แล้ว ทุกหน้าที่ @extends('layouts.app') ได้ไปด้วย

  ฝั่ง controller ส่งมาเป็นคู่ 2 ภาษาเสมอ:
    ->with('flash_success', ['th' => '...', 'en' => '...'])

  🔴 กติกาที่เจ้าของสั่ง 2026-09-03: ทุก Action ต้องมีหน้าต่างแจ้งผลกลางจอพร้อมอนิเมชัน
     จึงเด้งหน้าต่าง (layouts/partials/dialog) ให้อัตโนมัติ แล้วแสดงบรรทัดข้อความไว้ในหน้าด้วย
     เผื่อผู้ใช้ปิดหน้าต่างไปแล้วยังย้อนอ่านได้
--}}
@php
  $flashes = [];
  foreach (['flash_success' => 'ok', 'flash_error' => 'no'] as $key => $kind) {
    if (session($key)) {
      $msg = session($key);
      $flashes[] = [
        'kind' => $kind,
        'th' => is_array($msg) ? ($msg['th'] ?? '') : $msg,
        'en' => is_array($msg) ? ($msg['en'] ?? $msg['th'] ?? '') : $msg,
      ];
    }
  }
@endphp

@foreach ($flashes as $flash)
  <p class="flash {{ $flash['kind'] === 'ok' ? 'flash-ok' : 'flash-error' }}" role="status"
     data-loc-th="{{ $flash['th'] }}" data-loc-en="{{ $flash['en'] }}"
     style="margin-bottom:14px">{{ $flash['th'] }}</p>
@endforeach

@if ($flashes)
  <script>
    'use strict';

    // รอให้ทั้งหน้าโหลดจบก่อน เพราะ layouts/partials/dialog อยู่ท้ายไฟล์ ยังไม่ถูกอ่านตอนนี้
    document.addEventListener('DOMContentLoaded', function () {
      var flashes = @json($flashes);
      var first = flashes[0];
      var en = window.BMS.lang() === 'en';

      window.BMS.notify({
        kind: first.kind,
        title: en ? first.en : first.th,
        // ข้อผิดพลาดให้ค้างไว้จนกว่าจะกดปิด จะได้อ่านทัน
        autoClose: first.kind === 'no' ? false : 1800,
      });
    });
  </script>
@endif
