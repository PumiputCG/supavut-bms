{{--
  รูปพนักงาน — กดแล้วขยายดูได้ และหมุนล้อเมาส์ซูมได้
  🔴 กฎของโปรเจค: รูปโปรไฟล์ทุกที่ต้องกดขยายได้ (DESIGN_SYSTEM.md หัวข้อ 6A)
     ตัวขยายอยู่ที่ layouts/partials/image-zoom.blade.php — include ไว้ที่ layout แล้ว
     ผูก event ที่ document จึงใช้ได้กับรูปที่ JS สร้างขึ้นทีหลังด้วย

  $person = แถวที่ AccessService::describeUser() คืนมา
  $size   = 'sm' ให้เล็กลง (ใช้ในป้ายคนที่เลือก) · ไม่ใส่ = ขนาดปกติ
--}}
@php
  $zoomName = $person['name_th'].' ('.$person['employee_code'].')';
  $sizeClass = ($size ?? '') === 'sm' ? ' avatar-sm' : '';
@endphp
@if (! empty($person['photo']))
  <button type="button" class="avatar{{ $sizeClass }}"
          data-zoomable
          data-zoom-src="{{ $person['photo'] }}"
          data-zoom-alt="{{ $zoomName }}"
          title="{{ $zoomName }}"
          aria-label="ดูรูปของ {{ $zoomName }}">
    <img src="{{ $person['photo'] }}" alt="" loading="lazy"
         onerror="this.closest('.avatar').textContent = @js($person['initial'])">
  </button>
@else
  {{-- ไม่มีรูป — โชว์ตัวอักษรแรกของชื่อแทน และไม่ต้องให้กด เพราะไม่มีอะไรให้ดู --}}
  <span class="avatar{{ $sizeClass }}" aria-hidden="true">{{ $person['initial'] }}</span>
@endif
