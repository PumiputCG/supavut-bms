{{--
  เลขแดงประจำแถวในตารางเอกสาร (เจ้าของสั่ง 2026-09-07)

  🔴 บอกตั้งแต่ในตารางว่าใบไหนมีเรื่องค้างที่เรายังไม่ได้อ่าน
     ไม่ต้องเปิดกระดิ่งก่อนถึงจะรู้ · หายเองเมื่อเปิดเอกสารใบนั้น หรือกด "อ่านทั้งหมด"

  🔴 วาดไว้เสมอแม้จำนวนเป็น 0 (แค่ซ่อนไว้) เพื่อให้ JS หาเจอตอนกดอ่านทั้งหมด

  ต้องส่งมา
    $docNo  เลขที่เอกสาร — ส่งเป็น string หรือ array ก็ได้
            (หน้ารายการงบส่ง 2 ตัว: เลขที่งบ + เลขที่ Invest ต้นทาง เพราะแจ้งเตือนผูกกับ Invest)
--}}
@php
  $rowDocs = array_values(array_filter((array) $docNo));
  $rowUnread = 0;

  foreach ($rowDocs as $one) {
    $rowUnread += app(\App\Services\Core\Notifier::class)
      ->unreadDocs(app()->bound('current_user') ? app('current_user') : null)[$one] ?? 0;
  }
@endphp

<span class="row-badge" data-badge-doc="{{ implode(',', $rowDocs) }}"
      title="มีแจ้งเตือนที่ยังไม่ได้อ่าน" data-i18n-title="bell.unreadRow"
      @if ($rowUnread < 1) hidden @endif>{{ $rowUnread > 9 ? '9+' : $rowUnread }}</span>
