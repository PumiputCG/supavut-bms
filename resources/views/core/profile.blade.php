@extends('layouts.app')

@section('title', 'ข้อมูลส่วนตัว / My profile')

@push('page-style')
  <style>
    :root { --page-max: 760px; }

    /* ───────────────────────────────────────────────────────────
       นามบัตรพนักงาน — เอียงตามเมาส์แบบ 3 มิติ
       เวทีต้องมี perspective ตัวการ์ดถึงจะดูเป็นสามมิติจริง ไม่ใช่แค่บิดแบน
       ค่ามุม/ตำแหน่งเมาส์ส่งมาจาก JS ผ่านตัวแปร CSS (--rx --ry --mx --my --lift)
       ─────────────────────────────────────────────────────────── */
    .card-stage {
      --rx: 0deg; --ry: 0deg; --mx: 50%; --my: 50%; --lift: 0;
      perspective: 1100px;
      /* เว้นบนเยอะกว่าล่าง — บัตรจะได้ไม่ลอยติดแถบเครื่องมือ และมีที่ให้เงาทอดลงข้างล่าง */
      padding: 72px 0 54px;
      display: grid; justify-items: center;
    }

    .namecard {
      position: relative;
      width: min(560px, 100%); aspect-ratio: 16 / 9;
      transform-style: preserve-3d;
      transform:
        rotateX(var(--rx)) rotateY(var(--ry))
        translateZ(calc(var(--lift) * 16px))
        scale(calc(1 + var(--lift) * 0.015));
      transition: transform .5s var(--ease);
      will-change: transform;
    }
    .namecard.is-live { transition: transform .08s linear; }   /* ตอนเมาส์อยู่บนบัตรต้องตามทันที ไม่หน่วง */

    /* ── เงาใต้บัตร — แยกเป็นอีกชั้น จะได้ขยับสวนทางกับการเอียง ── */
    .card-shadow {
      position: absolute; left: 50%; bottom: -26px;
      width: 76%; height: 26px;
      transform:
        translateX(-50%)
        translateX(calc(var(--ry) * -1.1))
        translateY(calc(var(--rx) * 0.5))
        scale(calc(1 - var(--lift) * 0.06));
      border-radius: 50%;
      background: radial-gradient(ellipse at center,
        rgb(var(--shadow-rgb) / calc(26% + var(--lift) * 10%)) 0%,
        rgb(var(--shadow-rgb) / 0%) 70%);
      filter: blur(9px);
      transition: transform .5s var(--ease);
      pointer-events: none;
    }
    .namecard.is-live .card-shadow { transition: transform .08s linear; }

    /* ── ตัวบัตร ── */
    .card-face {
      position: absolute; inset: 0; overflow: hidden;
      display: grid; grid-template-rows: auto 1fr;
      border: 1px solid var(--line); border-radius: var(--radius-lg);
      background: var(--surface);
      box-shadow:
        0 1px 2px rgb(var(--shadow-rgb) / 8%),
        0 calc(10px + var(--lift) * 14px) calc(24px + var(--lift) * 26px) calc(-12px) rgb(var(--shadow-rgb) / calc(18% + var(--lift) * 12%));
      transition: box-shadow .4s var(--ease);
    }

    /* แสงสะท้อนวิ่งตามเคอร์เซอร์ ทำให้รู้สึกเป็นวัตถุมันวาวจริง */
    .card-glare {
      position: absolute; inset: 0; z-index: 3; pointer-events: none;
      border-radius: var(--radius-lg);
      background: radial-gradient(320px circle at var(--mx) var(--my),
        rgb(255 255 255 / 42%) 0%, rgb(255 255 255 / 10%) 34%, transparent 62%);
      opacity: var(--lift);
      transition: opacity .35s var(--ease);
      mix-blend-mode: overlay;
    }

    /* ── แถบหัวบัตร ── */
    .card-top {
      display: flex; align-items: center; gap: 10px;
      padding: 11px 20px;
      background: linear-gradient(100deg, var(--navy-900) 0%, var(--royal) 100%);
      color: var(--on-dark);
    }
    .card-top img { width: 26px; height: 26px; flex: 0 0 auto; }
    .card-top b { font-size: var(--fs-md); font-weight: 800; letter-spacing: -.2px; }
    .card-top span { color: var(--on-dark-soft); font-size: var(--fs-xs); }

    /* ── เนื้อบัตร ── */
    .card-body {
      display: grid; grid-template-columns: auto 1fr; gap: 16px;
      align-content: start; padding: 16px 20px 18px;
    }

    /* ชั้นที่ลอยสูงกว่าบัตร ทำให้เกิดมิติตอนเอียง (parallax) */
    .card-photo {
      width: 84px; height: 84px; flex: 0 0 auto;
      display: grid; place-items: center; overflow: hidden; padding: 0;
      border: 1px solid var(--line); border-radius: var(--radius);
      background: var(--surface-2); color: var(--navy-800);
      font-size: 28px; font-weight: 700;
      transform: translateZ(28px);
      box-shadow: 0 6px 14px -8px rgb(var(--shadow-rgb) / 45%);
    }
    .card-photo img { width: 100%; height: 100%; object-fit: cover; }
    button.card-photo { cursor: zoom-in; transition: border-color .16s var(--ease); }
    button.card-photo:hover, button.card-photo:focus-visible { border-color: var(--accent); }

    /* ── ตัวตน — มีหัวข้อกำกับเหมือนตารางด้านล่าง จะได้อ่านเป็นชุดเดียวกัน ── */
    .card-who { display: grid; align-content: center; gap: 3px; min-width: 0; margin: 0; transform: translateZ(18px); }
    .card-who > div { display: grid; grid-template-columns: 62px 1fr; gap: 8px; align-items: baseline; }
    .card-who dt { color: var(--muted); font-size: var(--fs-xs); }
    .card-who dd { margin: 0; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .card-who .name { color: var(--navy-800); font-size: var(--fs-md); font-weight: 800; line-height: 1.3; }
    .card-who .role { color: var(--ink-soft); font-size: var(--fs-sm); }
    .card-who .code { color: var(--ink); font-size: var(--fs-sm); letter-spacing: .3px; }
    .card-who .empty { color: var(--muted); }

    /* ── รายละเอียดด้านล่างบัตร ── */
    .card-details {
      grid-column: 1 / -1;
      display: grid; grid-template-columns: 1fr 1fr; gap: 3px 18px;
      padding-top: 12px; margin-top: 2px;
      border-top: 1px solid var(--line-soft);
      transform: translateZ(10px);
    }
    .card-details > div { display: grid; grid-template-columns: 62px 1fr; gap: 8px; align-items: baseline; }
    .card-details dt { color: var(--muted); font-size: var(--fs-xs); }
    .card-details dd { margin: 0; color: var(--ink); font-size: var(--fs-sm); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .card-details dd.empty { color: var(--muted); }

    /* ── ลายเซ็น — โชว์เป็นลายเส้นเปล่าๆ ไม่มีกรอบ ตามที่เจ้าของสั่ง ── */
    /* หัวข้อ "ลายเซ็น" อยู่ระดับเดียวกับ "อีเมล" ส่วนตัวลายเซ็นห้อยลงมาข้างล่าง
       ไม่งั้นภาพจะไปชนบรรทัด "วันเริ่มงาน" ที่อยู่เหนือขึ้นไป */
    /* ต้องเจาะ .card-details > div ด้วย ไม่งั้นแพ้ specificity ของกฎ baseline ข้างบน */
    .card-details > div.sig-cell { align-items: start; }
    .sig {
      display: block; padding: 0; border: 0; background: transparent;
      cursor: zoom-in; line-height: 0;
      margin-top: 7px;              /* เว้นจากบรรทัด "วันเริ่มงาน" ที่อยู่เหนือขึ้นไป ไม่ให้ภาพไปชนกัน */
    }
    .sig img {
      max-height: 42px; width: auto; max-width: 100%;
      object-fit: contain;
      /* ลายเซ็นบางใบพื้นขาวทึบ — คูณสีให้พื้นขาวจมไปกับบัตร เหลือแต่เส้น */
      mix-blend-mode: multiply;
      transition: transform .16s var(--ease);
    }
    .sig:hover img, .sig:focus-visible img { transform: scale(1.06); }

    /* ── ท้ายหน้า: จะแก้ข้อมูลต้องทำยังไง ── */
    .edit-note { display: grid; gap: 6px; padding: 12px 16px; }
    .edit-note .line {
      display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
      color: var(--ink-soft); font-size: var(--fs-sm);
    }
    .edit-note .who { color: var(--muted); min-width: 74px; }
    .edit-note a { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }

    /* ───────────────────────────────────────────────────────────
       มือถือ — เป็น "การ์ด" จริงที่สูงตามเนื้อหา ไม่ใช่บัตร 16:9
       🔴 ของเดิมล็อกบัตรสูง 340px แต่เนื้อในสูง 452px และ .card-face
          เป็น absolute + overflow:hidden → แถว "อีเมล" กับ "ลายเซ็น"
          ถูกตัดหายไปเลยบนมือถือ (วัดจริงที่ 360 · 390 · 414px)
       จอสัมผัสไม่มีตำแหน่งเมาส์ จึงไม่มีทั้งการเอียงและแสงสะท้อนอยู่แล้ว
       เอากรอบ 3 มิติออกได้หมด แล้วคืนที่ว่างให้เนื้อหาแทน
       ─────────────────────────────────────────────────────────── */
    @media (max-width: 620px) {
      .card-stage { perspective: none; padding: 12px 0 18px; }

      .namecard {
        width: 100%; aspect-ratio: auto;
        transform: none; transform-style: flat;
      }
      .card-shadow, .card-glare { display: none; }
      .card-face { position: relative; }                        /* เลิกลอยทับ ให้สูงตามเนื้อใน */
      .card-photo, .card-who, .card-details { transform: none; }

      .card-top { padding: 10px 14px; }

      /* รูปอยู่กลางด้านบน ชื่อจึงได้ความกว้างเต็มบรรทัด ไม่ต้องตัดด้วย ... */
      .card-body { grid-template-columns: 1fr; gap: 12px; padding: 14px 14px 16px; }
      .card-photo { width: 96px; height: 96px; justify-self: center; }

      .card-who { gap: 7px; }
      .card-who > div,
      .card-details > div { grid-template-columns: 88px minmax(0, 1fr); align-items: start; }

      /* บนมือถือมีที่เหลือทางตั้ง ไม่ใช่ทางนอน — ยาวเกินให้ขึ้นบรรทัดใหม่ ดีกว่าตัดทิ้ง */
      .card-who dd,
      .card-details dd { white-space: normal; overflow: visible; text-overflow: clip; }
      .card-who .name { font-size: var(--fs-lg); line-height: 1.35; }

      /* รายละเอียดเรียงลงมาแถวละบรรทัด มีเส้นคั่นบางๆ ให้กวาดตาทีละแถว */
      .card-details { grid-template-columns: 1fr; gap: 0; padding-top: 10px; }
      .card-details > div { padding: 7px 0; border-bottom: 1px solid var(--line-soft); }
      .card-details > div:last-child { border-bottom: 0; padding-bottom: 0; }
      .card-details > div.sig-cell { align-items: center; }
      .sig { margin-top: 0; }

      /* ท้ายหน้า — หัวข้อขึ้นบรรทัดของตัวเอง ไม่ต้องจองความกว้างค้างไว้ */
      .edit-note { gap: 12px; padding: 14px; }
      .edit-note .line { gap: 2px 8px; }
      .edit-note .who { min-width: 100%; }
    }

    /* ผู้ใช้ที่ตั้งค่าลดการเคลื่อนไหว — ตัดการเอียงและแสงสะท้อนทิ้ง เหลือบัตรนิ่งๆ */
    @media (prefers-reduced-motion: reduce) {
      .namecard { transform: none !important; }
      .card-shadow, .card-glare { display: none; }
    }
  </style>
@endpush

@section('content')
  @php
    $photo = $me->avatarUrl() ?? $employee?->photoUrl();
    $nameTh = $me->displayName('th');
    $nameEn = $me->displayName('en');

    $jobCode = $employee?->job_code ?: $me->job_code;
    $deptCode = $employee?->dept_code ?: $me->dept_code;
    $branchCode = $employee?->branch_code ?: $me->branch_code;

    // รวมชื่อกับรหัสไว้บรรทัดเดียว เช่น "Programmer Staff (P1)"
    $withCode = fn (?string $name, ?string $code) => match (true) {
      filled($name) && filled($code) => $name.' ('.$code.')',
      filled($name) => $name,
      filled($code) => $code,
      default => null,
    };

    $positionTh = $withCode($employee?->job_th ?: $me->position, $jobCode);
    $positionEn = $withCode($employee?->job_en ?: $me->position, $jobCode);

    $fields = [
      ['profile.company', 'บริษัท',      $me->company, null],
      ['profile.dept',    'แผนก',        $withCode($employee?->dept_th ?: $me->department, $deptCode), $withCode($employee?->dept_en ?: $me->department, $deptCode)],
      ['profile.branch',  'สาขา',        $withCode($employee?->branch_th, $branchCode), $withCode($employee?->branch_en, $branchCode)],
      ['profile.hireDate','วันเริ่มงาน',  $employee?->hire_date?->format('d/m/Y'), null],
      ['profile.email',   'อีเมล',       $me->email, null],
    ];

    // ลายเซ็นมิเรอร์มาจาก Insight เป็น data URL อยู่แล้ว ใช้ได้เลยไม่ต้องโหลดไฟล์เพิ่ม
    // ⚠️ มีแค่ไม่กี่คนที่เซ็นไว้ (6 จาก 1,547 บัญชี) ต้องเผื่อกรณีไม่มีเสมอ
    $signature = trim((string) $me->signature);
  @endphp

  <div class="card-stage" data-card-stage>
    <div class="namecard" data-card>
      <span class="card-shadow" aria-hidden="true"></span>

      <div class="card-face">
        <div class="card-top">
          <img src="{{ asset('img/bms-logo.png') }}" alt="" width="26" height="26">
          <b>{{ config('app.name') }}</b>
          <span data-i18n="app.full">Supavut Business Management System</span>
        </div>

        <div class="card-body">
          @if ($photo)
            <button type="button" class="card-photo" data-zoomable
                    data-zoom-src="{{ $photo }}" data-zoom-alt="{{ $nameTh }}"
                    aria-label="ดูรูปโปรไฟล์ขนาดใหญ่" data-i18n-aria="top.viewPhoto">
              <img src="{{ $photo }}" alt="" loading="lazy" data-avatar data-initial="{{ $me->initial() }}">
            </button>
          @else
            <span class="card-photo" aria-hidden="true">{{ $me->initial() }}</span>
          @endif

          <dl class="card-who">
            <div>
              <dt data-i18n="profile.fullName">ชื่อ-สกุล</dt>
              <dd class="name" data-loc-th="{{ $nameTh }}" data-loc-en="{{ $nameEn }}">{{ $nameTh }}</dd>
            </div>
            <div>
              <dt data-i18n="profile.position">ตำแหน่ง</dt>
              @if (filled($positionTh))
                <dd class="role" data-loc-th="{{ $positionTh }}" data-loc-en="{{ $positionEn }}">{{ $positionTh }}</dd>
              @else
                <dd class="role empty" data-i18n="profile.noData">ยังไม่มีข้อมูล</dd>
              @endif
            </div>
            <div>
              <dt data-i18n="profile.code">รหัสพนักงาน</dt>
              <dd class="code">{{ $me->employee_code }}</dd>
            </div>
          </dl>

          <dl class="card-details">
            @foreach ($fields as [$key, $label, $value, $valueEn])
              <div>
                <dt data-i18n="{{ $key }}">{{ $label }}</dt>
                @if (filled($value))
                  <dd @if ($valueEn) data-loc-th="{{ $value }}" data-loc-en="{{ $valueEn }}" @endif>{{ $value }}</dd>
                @else
                  <dd class="empty" data-i18n="profile.noData">ยังไม่มีข้อมูล</dd>
                @endif
              </div>
            @endforeach

            {{-- ลายเซ็น — อยู่ขวาของอีเมล ไม่มีกรอบ กดแล้วขยายดูได้เหมือนรูปโปรไฟล์ --}}
            <div class="sig-cell">
              <dt data-i18n="profile.signature">ลายเซ็น</dt>
              @if ($signature !== '')
                <dd>
                  <button type="button" class="sig" data-zoomable
                          data-zoom-src="{{ $signature }}" data-zoom-alt="{{ $nameTh }}"
                          aria-label="ดูลายเซ็นขนาดใหญ่" data-i18n-aria="profile.viewSignature">
                    <img src="{{ $signature }}" alt="" loading="lazy">
                  </button>
                </dd>
              @else
                <dd class="empty" data-i18n="profile.noSignature">ยังไม่ได้เซ็น</dd>
              @endif
            </div>
          </dl>
        </div>

        <span class="card-glare" aria-hidden="true"></span>
      </div>
    </div>
  </div>

  {{-- ───────── จะแก้ข้อมูลต้องทำยังไง ─────────
    ข้อมูลเป็นมิเรอร์ทางเดียว แก้ที่ SBMS ไปก็ถูกทับกลับตอนซิงค์รอบถัดไป
  --}}
  <section class="card edit-note">
    <p class="line">
      <span class="who" data-i18n="profile.forStaff">พนักงาน</span>
      <span data-i18n="profile.editViaInsight">แก้ไขข้อมูลได้ที่ระบบ Supavut Insight</span>
      <a href="{{ config('bms.insight_url') }}" target="_blank" rel="noopener" data-i18n="profile.openInsight">เปิด Insight</a>
    </p>
    <p class="line">
      <span class="who" data-i18n="profile.forSupplier">Supplier</span>
      <span data-i18n="profile.editViaHr">ติดต่อฝ่ายบุคคลเพื่อแก้ไขข้อมูล</span>
      <a href="mailto:{{ config('bms.hr_email') }}">{{ config('bms.hr_email') }}</a>
    </p>
  </section>
@endsection

@push('page-script')
  <script>
    'use strict';
    (function () {
      var stage = document.querySelector('[data-card-stage]');
      var card = document.querySelector('[data-card]');
      if (!stage || !card) return;

      // เคารพการตั้งค่าลดการเคลื่อนไหวของเครื่องผู้ใช้ — ไม่ผูก event เลย
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

      var MAX = 11;          // องศาสูงสุดที่ยอมให้เอียง เกินกว่านี้จะดูบิดเบี้ยว
      var raf = null;
      var target = { rx: 0, ry: 0, mx: 50, my: 50, lift: 0 };

      function apply() {
        raf = null;
        stage.style.setProperty('--rx', target.rx.toFixed(2) + 'deg');
        stage.style.setProperty('--ry', target.ry.toFixed(2) + 'deg');
        stage.style.setProperty('--mx', target.mx.toFixed(1) + '%');
        stage.style.setProperty('--my', target.my.toFixed(1) + '%');
        stage.style.setProperty('--lift', target.lift.toFixed(3));
      }

      // รวบการอัปเดตไว้เฟรมเดียว ไม่ให้เขียน style ทุกครั้งที่เมาส์ขยับ
      function schedule() { if (raf === null) { raf = window.requestAnimationFrame(apply); } }

      card.addEventListener('pointermove', function (e) {
        if (e.pointerType === 'touch') return;   // จอสัมผัสไม่ต้องเอียง กดแล้วค้างจะดูแปลก

        var r = card.getBoundingClientRect();
        var px = (e.clientX - r.left) / r.width;    // 0..1
        var py = (e.clientY - r.top) / r.height;

        // เมาส์ไปทางขวา -> บัตรหันขวา · เมาส์ลงล่าง -> บัตรก้มลง
        target.ry = (px - 0.5) * 2 * MAX;
        target.rx = (0.5 - py) * 2 * MAX;
        target.mx = px * 100;
        target.my = py * 100;
        target.lift = 1;

        card.classList.add('is-live');
        schedule();
      });

      card.addEventListener('pointerleave', function () {
        card.classList.remove('is-live');   // กลับตำแหน่งเดิมแบบนุ่มๆ
        target.rx = 0; target.ry = 0; target.mx = 50; target.my = 50; target.lift = 0;
        schedule();
      });

      // กดค้างแล้วบัตรยุบลงเล็กน้อย ให้รู้สึกว่าจับต้องได้
      card.addEventListener('pointerdown', function () { target.lift = 0.35; schedule(); });
      card.addEventListener('pointerup', function () { target.lift = 1; schedule(); });
    })();
  </script>
@endpush
