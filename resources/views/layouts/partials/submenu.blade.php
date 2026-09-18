{{--
  แผงเมนูย่อยของโมดูล — โผล่ทางขวาของเมนูซ้ายเมื่อกดโมดูล (เช่น "งบประมาณ")
  ดันแถบเครื่องมือกับ breadcrumb ไปทางขวาด้วย

  ข้อมูลมาจาก App\Support\NavMenu::modulesWithFunctions()
  วาดทุกโมดูลไว้ล่วงหน้าแล้วซ่อนไว้ แล้วให้ JS สลับว่าจะโชว์อันไหน
  ทำแบบนี้เพื่อให้ระบบ 2 ภาษาแปลได้ตามปกติ (data-i18n ทำงานตอนโหลดหน้า)
--}}
@php
  /* กรองให้ตรงกับเมนูซ้ายเป๊ะๆ ไม่งั้นกดโมดูลแล้วแผงไม่ขึ้น (บั๊กจริง 2026-09-03)
       - โมดูลระบบงาน  ต้องอยู่ในรายการที่มีสิทธิ์เห็น
       - โมดูลในกลุ่มของผู้ดูแลระบบ  ขึ้นเมื่อเป็นแอดมินเท่านั้น
     🔴 ห้ามไล่ hardcode id ทีละตัว — เพิ่มโมดูลใหม่ในกลุ่มแอดมินแล้วจะลืมมาแก้ตรงนี้ */
  $access = app(\App\Services\Access\AccessService::class);
  $visible = $access->visibleModuleIds($me ?? null);
  $isAdmin = $access->isAdmin($me ?? null);
  $adminModuleIds = \App\Support\NavMenu::adminModuleIds();

  $modules = array_values(array_filter(
    \App\Support\NavMenu::modulesWithFunctions(),
    fn ($m) => in_array($m['id'], $adminModuleIds, true)
      ? $isAdmin
      : in_array($m['id'], $visible, true)
  ));
@endphp

<aside class="submenu" data-submenu aria-label="เมนูของโมดูล" data-i18n-aria="submenu.aria">
  @foreach ($modules as $m)
    <div class="submenu-panel" data-submenu-panel="{{ $m['id'] }}" hidden>
      <div class="submenu-head">
        @include('layouts.partials.nav-icon', ['name' => $m['icon']])
        <span class="submenu-title" data-i18n="{{ $m['key'] }}">{{ $m['th'] }}</span>
        <button type="button" class="submenu-close" data-close-submenu
                aria-label="ปิดเมนูโมดูล" data-i18n-aria="submenu.close">
          <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m13 17-5-5 5-5"></path><path d="m19 17-5-5 5-5"></path>
          </svg>
        </button>
      </div>

      <div class="submenu-items">
        @php
          // เลขแดงรายหัวข้อย่อย — คีย์ที่ยุบรวมกันในเมนูต้องบวกรวมกันด้วย
          $unread = app(\App\Services\Core\Notifier::class)->unreadMap($me ?? null);
        @endphp

        @foreach (\App\Support\NavMenu::menuFunctions($m) as $fn)
          @php
            // ฟังก์ชันที่ยังไม่มีหน้าจริง (route = null) ให้ขึ้นป้าย "อยู่ระหว่างพัฒนา" ไว้ก่อน
            $fnHref = ! empty($fn['route']) && \Illuminate\Support\Facades\Route::has($fn['route']) ? route($fn['route']) : null;
            $fnActive = $fnHref && ($fn['route'] ?? null) === request()->route()?->getName();

            // ⚪ ไม่มีสิทธิ์ในหัวข้อย่อยนี้ = เทาลงและกดไม่ได้ (กติกาเดียวกับระดับโมดูล)
            //    กำหนดสิทธิ์ได้ที่หน้า "สิทธิ์การเข้าถึงโมดูล" คอลัมน์ "หัวข้อย่อย"
            $fnDenied = ! $access->canUseAnyFunction($me ?? null, $m['id'], $fn['keys'] ?? [$fn['key']]);
          @endphp
          <a class="submenu-item {{ $fnActive ? 'is-active' : '' }} {{ $fnHref && ! $fnDenied ? '' : 'is-soon' }} {{ $fnDenied ? 'is-denied' : '' }}"
             href="{{ $fnDenied ? '#' : ($fnHref ?? '#') }}"
             @if ($fnDenied)
               aria-disabled="true" title="คุณไม่มีสิทธิ์เข้าใช้งานหัวข้อนี้" data-i18n-title="access.fn.noAccess"
             @elseif (! $fnHref)
               aria-disabled="true" title="อยู่ระหว่างพัฒนา" data-i18n-title="common.comingSoon"
             @endif>
            {{-- หัวข้อย่อยกำหนดไอคอนของตัวเองได้ (NavMenu คีย์ icon) ไม่ได้กำหนด = ไอคอนเอกสารเปล่า --}}
            @include('layouts.partials.nav-icon', ['name' => $fn['icon'] ?? 'file'])
            @php
              // คีย์ที่ยุบรวมกันในเมนู (เช่น รับทราบ + อนุมัติ) ต้องบวกจำนวนของทุกคีย์
              $fnUnread = 0;
              foreach ($fn['keys'] ?? [$fn['key']] as $k) { $fnUnread += $unread[$m['id'].'|'.$k] ?? 0; }
            @endphp
            <span data-i18n="{{ $fn['key'] }}">{{ $fn['th'] }}</span>
            @include('layouts.partials.nav-badge', [
              'n' => $fnUnread,
              // คีย์ที่ยุบรวมกันในเมนู ส่งไปทุกตัว JS จะได้บวกรวมเหมือนฝั่งเซิร์ฟเวอร์
              'key' => implode(',', array_map(fn ($k) => $m['id'].'|'.$k, $fn['keys'] ?? [$fn['key']])),
            ])
          </a>
        @endforeach
      </div>
    </div>
  @endforeach
</aside>
