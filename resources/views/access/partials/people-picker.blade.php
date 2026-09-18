{{--
  ตัวเลือกพนักงาน — ค้นหาด้วยรหัสพนักงานหรือชื่อ-สกุล แล้วกดเลือกได้หลายคน
  ใช้ร่วมกัน 2 ที่: เลือกผู้ดูแลระบบ · เลือกคนที่เห็นโมดูล

  พารามิเตอร์
    $pickerId   รหัสอ้างอิงของตัวเลือกในหน้านี้ (ต้องไม่ซ้ำกันในหน้าเดียว)
    $field      ชื่อ input ที่จะส่งไปกับฟอร์ม เช่น 'employee_codes[]'
    $selected   รายชื่อที่เลือกไว้แล้ว (array จาก AccessService::describeCodes)
    $emptyKey   คีย์แปลของข้อความตอนยังไม่ได้เลือกใคร
    $emptyText  ข้อความไทยของบรรทัดนั้น
    $limitTo    (ไม่บังคับ) รหัสพนักงานที่เลือกได้เท่านั้น — ส่ง [] หรือไม่ส่ง = เลือกได้ทุกคน
                ใช้ตอนที่หัวข้อย่อยระบุตัวคนไว้แล้ว จะได้ไม่เลือกคนที่ไม่มีสิทธิ์มาแล้วโดน 403 ทีหลัง
    $max        (ไม่บังคับ) เลือกได้กี่คน — ส่ง 1 = เลือกคนใหม่แล้วแทนที่คนเดิม (ใช้กับช่อง "ผู้เสนอ")
                ไม่ส่ง = เลือกได้ไม่จำกัด
    $locked     (ไม่บังคับ) true = ดูอย่างเดียว เปลี่ยนคนไม่ได้
                🔴 ต้องซ่อน "ช่องค้นหา" ด้วย ไม่ใช่เอาแค่ปุ่มกากบาทออก
                   ไม่งั้นยังค้นหาคนใหม่มาแทนที่ได้ = ไม่ได้ล็อกจริง

  🔴 รูปในผลค้นหาต้องกดขยาย/ซูมได้ตามกฎของโปรเจค (DESIGN_SYSTEM.md หัวข้อ 6A)
     ปุ่มรูปกับปุ่มเลือกจึงต้องแยกเป็น 2 ปุ่ม ไม่งั้นกดดูรูปแล้วจะกลายเป็นเลือกคนไปด้วย
--}}
<div class="picker" data-picker="{{ $pickerId }}" data-picker-field="{{ $field }}"
     @if (! empty($limitTo)) data-picker-limit="{{ implode(',', $limitTo) }}" @endif
     @if (! empty($max)) data-picker-max="{{ $max }}" @endif>

  @unless ($locked ?? false)
    <div class="picker-search">
      <label class="field">
        <span data-i18n="access.pick.search">ค้นหาพนักงาน (รหัสพนักงาน หรือ ชื่อ-สกุล)</span>
        <input class="input" type="search" autocomplete="off" spellcheck="false"
               data-picker-input
               placeholder="{{ $hintText ?? 'พิมพ์อย่างน้อย 2 ตัวอักษร' }}"
               data-i18n-placeholder="{{ $hintKey ?? 'access.pick.hint' }}">
      </label>
      <div class="picker-results" data-picker-results hidden></div>
    </div>
  @endunless

  <div>
    {{-- ล็อกไว้แล้ว = มีคนเดียวแน่นอน ไม่ต้องมีบรรทัดนับจำนวน --}}
    @unless ($locked ?? false)
      <p class="picker-label" style="margin-bottom:6px">
        <span data-i18n="access.pick.chosen">ผู้ที่เลือกไว้</span>
        <b data-picker-count>{{ count($selected) }}</b>
      </p>
    @endunless

    <div class="picker-list" data-picker-list>
      @foreach ($selected as $person)
        <span class="chip" data-chip="{{ $person['employee_code'] }}">
          <input type="hidden" name="{{ $field }}" value="{{ $person['employee_code'] }}">
          @include('access.partials.avatar', ['person' => $person, 'size' => 'sm'])
          <span class="chip-name"
                data-loc-th="{{ $person['name_th'] }}" data-loc-en="{{ $person['name_en'] }}">{{ $person['name_th'] }}</span>
          <span class="chip-code">{{ $person['employee_code'] }}</span>
          {{-- ล็อกไว้ = ไม่มีปุ่มเอาออก (เจ้าของสั่ง 2026-09-10) --}}
          @unless ($locked ?? false)
            <button type="button" class="chip-x" data-chip-remove
                    aria-label="เอาออก" data-i18n-aria="access.pick.remove">
              <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                <path d="M18 6 6 18M6 6l12 12"></path>
              </svg>
            </button>
          @endunless
        </span>
      @endforeach
    </div>

    {{-- ไม่ส่ง emptyText มา = ไม่ต้องมีบรรทัดอธิบาย (เจ้าของสั่งเอาข้อความรกๆ ออก 2026-09-03) --}}
    @if ($emptyText !== '')
      <p class="picker-empty" data-picker-empty @if (count($selected)) hidden @endif
         data-i18n="{{ $emptyKey }}">{{ $emptyText }}</p>
    @endif
  </div>
</div>
