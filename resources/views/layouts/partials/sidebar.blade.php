{{--
  เมนูซ้ายของ SBMS — โครงเมนูอยู่ที่ไฟล์นี้ที่เดียว

  โครงสร้าง 3 ชั้นตามที่เจ้าของกำหนด (2026-09-02)
    หัวข้อหลัก (icon: group / mod-dashboard / settings-main)
      └─ โมดูล (icon: mod-*)              ชื่อกว้างๆ ครอบคลุมงานทั้งก้อน เช่น "งบประมาณ"
           └─ ฟังก์ชันในโมดูล (icon: file) รายละเอียดอยู่ในแผงที่เปิดออกมาทางขวา

  🔴 โมดูลที่มีคีย์ `functions` จะ **ไม่ลิงก์ไปหน้าไหน** แต่จะเปิดแผงเมนูย่อยทางขวาแทน
     (ดู layouts/partials/submenu.blade.php) — แผงนั้นดันแถบเครื่องมือกับ breadcrumb ไปด้วย

  เพิ่มเมนูใหม่: เติมลง $groups แล้วเพิ่มคีย์แปลให้ครบ 2 ภาษาใน layouts/i18n.blade.php
--}}
@php
  // โครงเมนูอยู่ที่ App\Support\NavMenu ที่เดียว — เมนูซ้ายกับแผงย่อยทางขวาใช้ชุดเดียวกัน
  $current = request()->route()?->getName();

  /* กติกาการแสดงเมนูตามสิทธิ์ (เจ้าของสั่ง 2026-09-03)
       🟢 มีสิทธิ์          -> แสดงปกติ กดได้
       ⚪ ไม่มีสิทธิ์        -> เทาลง กดไม่ได้ + ขึ้น tooltip บอกว่าไม่มีสิทธิ์
       🔒 เมนูของผู้ดูแลระบบ -> ซ่อนไปเลย ไม่ต้องให้เห็นว่ามีอยู่
     จงใจไม่ซ่อนโมดูลระบบงาน เพื่อให้ผู้ใช้รู้ว่าระบบมีอะไรบ้างแล้วไปขอสิทธิ์ถูก */
  $access = app(\App\Services\Access\AccessService::class);
  $visible = $access->visibleModuleIds($me ?? null);
  $isAdmin = $access->isAdmin($me ?? null);

  $groups = [];
  // 🔴 เลขแดงบอกว่ามีเรื่องค้างอยู่กี่เรื่องในโมดูลไหน (เจ้าของสั่ง 2026-09-07)
  //    ดึงครั้งเดียวต่อคำขอ — Notifier แคชไว้ให้แล้ว เมนูย่อยจึงเรียกซ้ำได้ไม่เปลือง
  $unread = app(\App\Services\Core\Notifier::class)->unreadMap($me ?? null);

  foreach (\App\Support\NavMenu::groups() as $g) {
    // 🔒 กลุ่มที่ตั้งธง admin ไว้ (จัดการระบบ) = ของผู้ดูแลระบบเท่านั้น ไม่มีสิทธิ์ก็ไม่ต้องเห็นว่ามี
    if (! empty($g['admin']) && ! $isAdmin) {
      continue;
    }

    /* ทำเครื่องหมายโมดูลที่ไม่มีสิทธิ์ — รวมรายงานด้วย
       ข้ามกลุ่มของผู้ดูแลระบบ เพราะทั้งกลุ่มถูกกันด้วย $isAdmin ไปแล้ว
       ถ้ามาทำเครื่องหมายซ้ำ โมดูลในกลุ่มนั้นจะขึ้นแม่กุญแจให้แอดมินเองเพราะไม่ได้อยู่ในรายการสิทธิ์ */
    if (empty($g['admin'])) {
      $g['items'] = array_map(function ($i) use ($visible) {
        $i['denied'] = ! empty($i['id']) && ! in_array($i['id'], $visible, true);

        return $i;
      }, $g['items']);
    }

    $groups[] = $g;
  }
@endphp

<div class="nav-root">
  @foreach ($groups as $group)
    <details class="nav-group" @if ($group['open'] ?? false) open @endif>
      <summary>
        <svg class="nav-caret" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m9 6 6 6-6 6"></path>
        </svg>
        @include('layouts.partials.nav-icon', ['name' => $group['icon']])
        <span class="nav-label" data-i18n="{{ $group['key'] }}">{{ $group['th'] }}</span>
      </summary>

      <div class="nav-items">
        @forelse ($group['items'] as $item)
          @if (! empty($item['functions']))
            {{-- โมดูลที่มีฟังก์ชันย่อย: กดแล้วเปิดแผงทางขวา ไม่ได้ไปหน้าไหน
                 ⚪ ไม่มีสิทธิ์ = ปิดปุ่มไว้ ยังเห็นชื่อได้แต่กดไม่ติด และมี tooltip บอกเหตุผล --}}
            <button type="button"
                    class="nav-item {{ ! empty($item['denied']) ? 'is-denied' : '' }}"
                    @if (empty($item['denied']))
                      data-open-submenu="{{ $item['id'] }}"
                    @else
                      disabled aria-disabled="true"
                      title="คุณไม่มีสิทธิ์เข้าใช้งานโมดูลนี้" data-i18n-title="nav.noAccess"
                    @endif>
              @include('layouts.partials.nav-icon', ['name' => $item['icon']])
              <span class="nav-label" data-i18n="{{ $item['key'] }}">{{ $item['th'] }}</span>
              {{-- เลขแดงรวมของทั้งโมดูล — บอกว่ามีเรื่องค้างข้างในกี่เรื่อง --}}
              @include('layouts.partials.nav-badge', ['n' => $unread[$item['id']] ?? 0, 'key' => $item['id']])
              @if (! empty($item['denied']))
                {{-- แม่กุญแจ บอกด้วยรูปว่าล็อกอยู่ ไม่ได้พึ่งสีอย่างเดียว (คนตาบอดสีต้องแยกออก) --}}
                <svg class="nav-lock" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect x="4" y="10" width="16" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                </svg>
              @else
                <svg class="nav-more" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="m9 6 6 6-6 6"></path>
                </svg>
              @endif
            </button>
          @else
            @php
              $href = $item['route'] ? route($item['route']) : null;
              $active = $item['route'] && $item['route'] === $current;
            @endphp
            <a class="nav-item {{ $active ? 'is-active' : '' }} {{ $href ? '' : 'is-soon' }}"
               href="{{ $href ?? '#' }}"
               @if (! $href) aria-disabled="true" title="อยู่ระหว่างพัฒนา" data-i18n-title="common.comingSoon" @endif>
              @include('layouts.partials.nav-icon', ['name' => $item['icon']])
              <span class="nav-label" data-i18n="{{ $item['key'] }}">{{ $item['th'] }}</span>
              @include('layouts.partials.nav-badge', ['n' => $unread[$item['id'] ?? ''] ?? 0, 'key' => $item['id'] ?? ''])
            </a>
          @endif
        @empty
          <p class="nav-none" data-i18n="nav.noModule">ยังไม่มีโมดูล</p>
        @endforelse
      </div>
    </details>
  @endforeach
</div>
