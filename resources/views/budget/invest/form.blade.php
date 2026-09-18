@extends('layouts.app')

@section('title', 'ใบขออนุมัติตั้งงบประมาณ / Budget approval request')

@push('page-style')
  <style>
    :root { --page-max: 900px; }

    /* ช่องกรอกในกระดาษ — เรียงเป็นคู่ ป้าย / ช่องกรอก เหมือนแบบฟอร์มจริง */
    .paper-field {
      display: grid; grid-template-columns: 190px 1fr; gap: 14px; align-items: start;
      padding: 10px 0; border-bottom: 1px dashed var(--line-soft);
    }

    .paper-field > span { padding-top: 9px; color: var(--ink-soft); font-size: var(--fs-sm); font-weight: 600; }


    /*
      ตัวเลือกผู้เสนอ — เลือกได้คนเดียว จึงตัดส่วนที่ไม่จำเป็นออก
      🔴 ซ่อนป้าย "ค้นหาพนักงาน" ข้างในทิ้ง เพราะมีป้าย "ผู้เสนอ" อยู่หน้าแถวแล้ว
         ถ้าไม่ซ่อน ช่องกรอกจะต่ำกว่าช่องวันที่ครึ่งบรรทัด มองแล้วไม่ตรงกัน
    */
    .picker-one .picker { gap: 8px; }
    .picker-one .picker-label { display: none; }
    .picker-one .picker-search .field > span { display: none; }
    .picker-one .picker-list { min-height: 0; }

    /*
      ช่องผู้เสนอเมื่อเลือกคนแล้ว — วาดให้เหมือนช่องกรอกที่มีค่าอยู่ (เจ้าของสั่ง 2026-09-04)
      🔴 ความสูงต้องเท่ากับ .input ของช่องวันที่ ไม่งั้น 2 ช่องในบรรทัดเดียวกันจะเหลื่อม
    */
    .picker-one .picker-list.is-filled {
      display: flex; align-items: center; min-height: 40px;
      padding: 4px 10px;
      border: 1px solid var(--line); border-radius: var(--radius);
      background: var(--surface);
    }
    .picker-one .picker-list .chip { width: 100%; }
    .picker-one .picker-list .chip-x { margin-left: auto; }

    /*
      ── สำเนาเรียน = ผู้อนุมัติตามลำดับ (เจ้าของสั่ง 2026-09-09) ─────────
      หน้าตาเลียนแบบระบบ Memo เดิม: จับลาก · เลขลำดับ · เลือกคน · ปุ่มเอาออก
    */
    /*
      🔴 เว้นระยะเหนือหัวข้อคั่นในหน้ากรอกให้มากกว่าค่ากลาง (เจ้าของสั่ง 2026-09-09)
         ค่ากลางคือ 22px ที่ paper.blade.php — ที่นี่ขอ 34px เฉพาะหน้ากรอก
         จำกัดขอบเขตด้วย [data-doc-form] จะได้ไม่ไปดันหน้าอื่นที่ใช้กระดาษเดียวกัน
    */
    [data-doc-form] .paper-sec { margin-top: 48px; }

    .apv { display: grid; gap: 8px; margin-bottom: 6px; }
    .apv-rows { display: grid; gap: 8px; }

    /*
      🔴 เจ้าของสั่ง 2026-09-09: กรอบเยอะเกินไป ให้มินิมอลลง
         ตัดกรอบรอบแถวออก เหลือเส้นคั่นบางๆ ระหว่างแถว รูปแบบอื่นคงเดิม
    */
    /* คอลัมน์: ที่จับลาก · เลขลำดับ · รูป · ชื่อ · บทบาท · ปุ่มเอาออก */
    .apv-row {
      display: grid; grid-template-columns: 20px 24px 30px minmax(0, 1fr) 132px 30px;
      align-items: center; gap: 10px;
      padding: 7px 2px;
      border-bottom: 1px dashed var(--line-soft);
    }

    .apv-row:last-child { border-bottom: 0; }

    /* ชื่อ + รหัส + ตำแหน่ง/แผนก ของคนที่เลือกแล้ว */
    .apv-who { display: grid; gap: 1px; min-width: 0; }
    .apv-name { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .apv-name > span:first-child {
      color: var(--navy-900); font-size: var(--fs-sm); font-weight: 700;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    .apv-code {
      flex: none; color: var(--muted); font-size: var(--fs-xs);
      font-variant-numeric: tabular-nums;
    }

    .apv-meta {
      color: var(--muted); font-size: var(--fs-xs);
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    /* ช่องค้นหาในแถวที่ยังไม่ได้เลือกคน */
    .apv-find { position: relative; min-width: 0; }
    .apv-find .input { width: 100%; }

    .apv-hits {
      position: absolute; z-index: 30; inset-inline: 0; top: calc(100% + 4px);
      max-height: 260px; overflow: auto;
      border: 1px solid var(--line); border-radius: var(--radius);
      background: var(--surface); box-shadow: var(--shadow-md, 0 8px 24px rgb(6 22 51 / 12%));
    }

    .apv-hits[hidden] { display: none; }

    .apv-hit {
      display: grid; grid-template-columns: 30px 1fr; align-items: center; gap: 10px;
      width: 100%; padding: 7px 10px; border: 0; background: transparent;
      text-align: start; cursor: pointer;
    }

    .apv-hit:hover, .apv-hit:focus-visible { background: var(--navy-50); }
    .apv-note { padding: 8px 10px; color: var(--muted); font-size: var(--fs-xs); }

    /* ลากอยู่ — ทำให้จางลงเพื่อบอกว่ากำลังย้ายแถวนี้ */
    .apv-row.is-dragging { opacity: .45; }

    /* แถวที่ลากผ่าน — ขีดเส้นบนบอกว่าจะไปแทรกตรงนี้ */
    .apv-row.is-over { box-shadow: inset 0 2px 0 0 var(--navy-800); }

    .apv-drag { color: var(--muted); cursor: grab; text-align: center; user-select: none; }
    .apv-drag:active { cursor: grabbing; }

    .apv-no {
      display: grid; place-items: center; width: 26px; height: 26px; border-radius: 50%;
      background: var(--navy-50); color: var(--navy-800);
      font-size: var(--fs-xs); font-weight: 700;
    }

    .apv-row .sel { width: 100%; min-width: 0; }

    .apv-x {
      width: 30px; height: 30px; border: 0; border-radius: var(--radius);
      background: transparent; color: var(--danger); cursor: pointer; font-size: 15px;
    }

    .apv-x:hover { background: var(--danger-50, rgb(179 49 42 / 8%)); }
    .apv-x[disabled] { opacity: .3; cursor: not-allowed; }

    .apv-add { justify-self: start; }

    .apv-empty { color: var(--muted); font-size: var(--fs-sm); }

    /* CEO — ปิดท้ายเสมอ แก้ไม่ได้ จึงไม่มีที่จับลาก ไม่มีปุ่มเอาออก และเปลี่ยนบทบาทไม่ได้ */
    .apv-row.is-locked { grid-template-columns: 20px 24px 30px minmax(0, 1fr) 132px 30px; }
    .apv-no-last { background: var(--ok-50, #e6f4ec); color: var(--ok); }

    /* บทบาทของแถวที่ล็อกไว้ — เป็นข้อความ ไม่ใช่ช่องเลือก */
    .apv-role-fixed {
      color: var(--muted); font-size: var(--fs-xs); font-weight: 600; text-align: center;
    }

    /*
      ── ช่องลงชื่อ ────────────────────────────────────────────────
      🔴 ใช้ #sign-grid (id) เพราะสไตล์กลางใน paper.blade.php เขียนด้วย .sign-grid
         และถูกวางไว้ในหน้าทีหลัง — สู้ด้วยลำดับไม่ได้ ต้องชนะด้วย specificity
      คนเยอะขึ้นช่องเล็กลงเอง จะได้ไม่ตกบรรทัดจนกระดาษยาว
    */
    /*
      🔴 สไตล์ช่องลงชื่อย้ายไปอยู่ที่ budget/partials/paper.blade.php แล้ว (2026-09-09)
         เพื่อให้หน้าดูเอกสารเรียงเหมือนกัน — ที่นี่เหลือแค่ให้ JS ตั้ง --sign-cols
    */

    /*
      ── หมวดงบประมาณ (เจ้าของสั่ง 2026-09-17) ──
      🔴 ไม่มีบรรทัดบอกรูปแบบเลขที่ใต้ dropdown แล้ว (เจ้าของสั่งเอาออก) — เลขออกตอนกดส่งอยู่ดี
    */
    .grp-pick { display: grid; gap: 6px; }

    .grp-warn {
      padding: 7px 10px; border-radius: var(--radius-sm);
      background: var(--warn-soft); color: var(--warn); font-size: var(--fs-xs); font-weight: 600;
    }

    /* ช่องที่ยังไม่ได้กรอก — ตีกรอบแดงให้เห็นว่าต้องกรอกตรงไหน */
    .input.is-missing, .sel.is-missing {
      border-color: var(--danger);
      box-shadow: 0 0 0 2px rgb(179 49 42 / 12%);
    }

    @media (max-width: 720px) {
      .paper-field { grid-template-columns: 1fr; gap: 4px; }
      .paper-field > span { padding-top: 0; }
    }
  </style>
@endpush

@section('content')
  @php
    $isNew = ! $invest->exists;
    $action = $isNew ? route('budget.invest.store') : route('budget.invest.update', $invest);
    // 🔴 ต้องประกาศตรงนี้ ใช้ตัดสินตั้งแต่ต้นหน้าว่าจะวาดฟอร์มกรอกไหม
    //    เอกสารใหม่ยังไม่มี approval_status จึงเช็ค $isNew ด้วย
    $editable = $isNew || $invest->isDraft();
  @endphp

  {{--
    🔴 เอกสารที่บันทึกแล้วต้องเห็นเป็น "ตัวเอกสาร" ก่อนเสมอ (เจ้าของสั่ง 2026-09-04)
       ช่องกรอกจะโผล่ต่อเมื่อกดปุ่ม "แก้ไข" และต้องยังเป็นร่างเท่านั้น
  --}}
  @unless ($isNew)
    @include('budget.invest.view')
  @endunless

  {{-- ฟอร์มกรอกมีเฉพาะตอนที่ยังแก้ไขได้ (ร่าง หรือเอกสารใหม่) --}}
  @if ($editable)
  <form method="POST" action="{{ $action }}" enctype="multipart/form-data"
        data-doc-form @unless ($isNew) hidden @endunless>
    @csrf
    @unless ($isNew) @method('PUT') @endunless

    {{-- ═══════════ ตัวเอกสาร (รูปแบบกระดาษ) ═══════════ --}}
    <section class="paper">
      @include('budget.partials.paper-head', [
        'docNo' => $isNew ? null : $invest->doc_no,
        // 🔴 หน้ากรอกเป็นร่างเสมอ — เลขที่ออกตอนกดส่ง (เจ้าของสั่ง 2026-09-17)
        'pendingNo' => true,
        'status' => $isNew ? null : ['code' => $invest->approval_status, 'labels' => \App\Models\Budget\Invest::STATUS_LABELS],
        // ร่างยังไม่มีกุญแจ จึงยังไม่มี QR — ที่นี่ส่งไว้เผื่อเปิดหน้ากรอกของเอกสารที่ส่งไปแล้ว
        'trackUrl' => $isNew ? null : $invest->trackUrl(),
      ])

      {{--
        🔴 Invest = ขอ "วงเงิน" ระดับแผนก ไม่ใช่ใบขอซื้อสินค้า (เจ้าของยืนยัน 2026-09-03)
           จึงไม่มีตารางรายการสินค้า / หน่วย / จำนวน / ราคาต่อหน่วย — ของพวกนั้นเป็นเรื่องของ PR
      --}}
      <div>
        {{--
          ═══ กลุ่มเอกสาร (เจ้าของสั่ง 2026-09-17) ═══
          ค่าเริ่มต้น = กลุ่มที่คลิกมาจากหน้าเลือกกลุ่ม · ร่างเปลี่ยนได้อิสระ · ส่งแล้วล็อก
          🔴 เลขที่ออกตอนกดส่งจากโค้ดของกลุ่มที่เลือกไว้ตอนนั้น

          🔴 กลุ่มเดิมของร่างถูกแอดมินปิดไปแล้ว -> ขึ้น "— เลือกกลุ่ม —" ว่างไว้ ไม่ปล่อยให้เบราว์เซอร์
             เลือกตัวแรกให้เงียบๆ (ผู้ใช้จะส่งเข้ากลุ่มผิดโดยไม่รู้ตัว) · ช่องนี้ required จึงบันทึกไม่ได้จนกว่าจะเลือก
        --}}
        @php
          $pickedGroup = (int) old('group_id', $invest->group_id);
          $groupGone = $pickedGroup > 0 && ! $groups->contains('id', $pickedGroup);
        @endphp
        <div class="paper-field">
          <span data-i18n="budget.docGroup">หมวดงบประมาณ</span>
          <div class="grp-pick">
            <select class="sel" name="group_id" required data-group-select>
              @if ($groupGone || $pickedGroup === 0)
                <option value="" selected data-i18n="budget.group.choose">— เลือกหมวดงบประมาณ —</option>
              @endif
              @foreach ($groups as $g)
                <option value="{{ $g->id }}" data-code="{{ $g->code }}" @selected($pickedGroup === $g->id)
                        data-loc-th="{{ $g->name_th }} ({{ $g->code }})"
                        data-loc-en="{{ ($g->name_en ?: $g->name_th) }} ({{ $g->code }})">{{ $g->name_th }} ({{ $g->code }})</option>
              @endforeach
            </select>

            @if ($groupGone)
              <p class="grp-warn" data-i18n="budget.group.gone">หมวดเดิมของร่างนี้ถูกปิดใช้งานแล้ว กรุณาเลือกหมวดใหม่ก่อนบันทึก</p>
            @endif


          </div>
        </div>

        <label class="paper-field">
          <span data-i18n="budget.year">ปีงบประมาณ</span>
          <select class="sel" name="fiscal_year" required>
            @foreach ($years as $y)
              <option value="{{ $y }}" @selected(old('fiscal_year', $invest->fiscal_year) == $y)>{{ $y }}</option>
            @endforeach
          </select>
        </label>

        {{--
          🔴 "ผู้ขอ" ล็อกเป็นคนที่ล็อกอินเสมอ เปลี่ยนไม่ได้ (เจ้าของสั่ง 2026-09-10)
             ยกเลิกกติกาเดิมที่ให้ฝ่ายบัญชีกรอกแทนคนอื่นได้ (DECISIONS ข้อ 26)
             ล็อกทั้ง 2 ชั้น — หน้าจอไม่มีช่องค้นหา/ปุ่มกากบาท และเซิร์ฟเวอร์บังคับรหัสเองอีกที
        --}}
        <div class="paper-field picker-one">
          <span data-i18n="budget.requester">ผู้ขอ</span>
          <div>
            @include('access.partials.people-picker', [
              'pickerId' => 'proposer',
              'field' => 'proposer_code',
              'selected' => $chosenProposer,
              'emptyKey' => '',
              'emptyText' => '',
              'limitTo' => [],
              'max' => 1,
              'locked' => true,
            ])
          </div>
        </div>

        <label class="paper-field">
          <span data-i18n="budget.dept">แผนก</span>
          <select class="sel" name="dept_code" required>
            <option value="" data-i18n="budget.choose">— เลือก —</option>
            @foreach ($departments as $d)
              <option value="{{ $d['code'] }}" @selected(old('dept_code', $invest->dept_code) === $d['code'])>{{ $d['code'] }} · {{ $d['name'] }}</option>
            @endforeach
          </select>
        </label>


        <label class="paper-field">
          <span data-i18n="budget.docTitle">ชื่อเอกสาร</span>
          <input class="input" type="text" name="title" required maxlength="191"
                 value="{{ old('title', $invest->title) }}">
        </label>

        {{--
          วงเงิน — ช่องนี้ตั้งใจให้หน้าตาต่างจากช่องอื่น เพราะเป็นตัวเลขสำคัญที่สุดในเอกสาร
          🔴 ช่องที่เห็นเป็น text เพราะ type="number" ใส่ลูกน้ำไม่ได้
             ค่าที่ส่งจริงอยู่ในช่องซ่อน #amount ซึ่งเป็นตัวเลขล้วนเสมอ
        --}}
        <label class="paper-field">
          <span data-i18n="budget.proposedAmount">วงเงินที่เสนอ (บาท)</span>
          <span class="money-field">
            @php $amountValue = old('amount', $invest->exists ? $invest->amount : ''); @endphp
            <input class="input money-box" type="text" inputmode="decimal" required
                   data-money-input data-money-target="amount"
                   autocomplete="off" placeholder="0.00"
                   value="{{ $amountValue === '' ? '' : number_format((float) $amountValue, 2) }}">
            <span class="money-unit" data-i18n="budget.baht">บาท</span>
          </span>
        </label>
        <input type="hidden" name="amount" id="amount" value="{{ $amountValue }}">

        <label class="paper-field">
          <span data-i18n="budget.description">รายละเอียด / วัตถุประสงค์</span>
          <textarea class="input" name="description" rows="3" style="min-height:88px;padding-top:12px">{{ old('description', $invest->description) }}</textarea>
        </label>
      </div>

      {{--
        ═══════════ สำเนาเรียน = ผู้อนุมัติตามลำดับ ═══════════
        🔴 เจ้าของสั่ง 2026-09-09: ผู้ขอเลือกผู้อนุมัติเองและจัดลำดับเอง 1,2,3,…
           แล้วระบบต่อท้ายด้วย CEO ให้เสมอ ถอด/สลับไม่ได้ (ยกกติกามาจากระบบ Memo เดิม)
           หน้าตาเลียนแบบ Memo — จับลากสลับลำดับได้ · เลขลำดับอยู่หน้าแถว · กันเลือกซ้ำ
      --}}
      <p class="paper-sec" data-i18n="budget.approvers">สำเนาเรียน (ผู้อนุมัติตามลำดับ)</p>

      <div class="apv">
        {{--
          CEO ถูกวาดเป็นแถวสุดท้ายของรายการนี้โดย JS (ไม่ได้เขียนไว้ในหน้า)
          เพราะเลขลำดับของเขาต้องเดินต่อจากคนที่เพิ่งกดเพิ่ม — เปลี่ยนทุกครั้งที่เพิ่ม/ลบคน
        --}}
        <div class="apv-rows" id="apv-rows"></div>

        {{--
          🔴 มีปุ่มเพิ่มปุ่มเดียวทั้งหน้า (เจ้าของสั่ง 2026-09-16 รอบ 2)
             เพิ่มแถวที่นี่เสมอ แล้วค่อยสลับ dropdown เป็น "แจ้งให้ทราบ" ถ้าอยากย้ายลงกลุ่มล่าง
             ชื่อปุ่มจึงเป็น "เพิ่มลำดับ" ไม่ใช่ "เพิ่มผู้อนุมัติ" เพราะใช้เพิ่มได้ทั้ง 2 บทบาท
        --}}
        <button type="button" class="btn btn-quiet apv-add" id="apv-add">
          <span data-i18n="budget.approver.add">+ เพิ่มลำดับ</span>
        </button>
      </div>

      {{--
        ── สำเนาเรียน (ผู้รับทราบ) — เจ้าของสั่ง 2026-09-16 ──
        🔴 เป็น "รายการเดียวกัน" กับข้างบน แค่แยกที่วาดตามบทบาทที่เลือก
           สลับ dropdown เป็น "แจ้งให้ทราบ" แล้วแถวนั้นย้ายลงมาที่นี่ทันที
           เก็บใน picked/roles ชุดเดียว จะได้ไม่มีรายชื่อ 2 ชุดให้หลุดจากกัน
      --}}
      <p class="paper-sec" data-i18n="budget.recipients">สำเนาเรียน (ผู้รับทราบ)</p>

      <div class="apv">
        <div class="apv-rows" id="ack-rows"></div>

        {{--
          ไม่มีใครในกลุ่มนี้ = บอกให้รู้ว่าเว้นว่างได้ ไม่ใช่ลืมกรอก
          🔴 ไม่มีปุ่มเพิ่มที่นี่ (เจ้าของสั่ง) — เพิ่มจากปุ่มเดียวข้างบนแล้วสลับบทบาทลงมา
        --}}
        <p class="apv-empty" id="ack-empty" hidden>
          <span data-i18n="budget.recipients.none">ยังไม่มีผู้รับทราบ — เลือก "แจ้งให้ทราบ" ที่รายชื่อด้านบนเพื่อย้ายลงมา</span>
        </p>
      </div>

      {{-- ═══════════ เอกสารแนบ ═══════════ --}}
      <p class="paper-sec" data-i18n="budget.files">เอกสารแนบ</p>

      <div style="display:grid;gap:10px">
        @if ($invest->exists && $invest->files->count())
          <ul class="file-list">
            @foreach ($invest->files as $file)
              <li class="file-row">
                <img class="file-ico" src="{{ \App\Support\FileIcon::url($file->original_name) }}" alt="">
                <a class="file-name" href="{{ $file->url() }}" target="_blank" rel="noopener">{{ $file->original_name }}</a>
                <span class="file-size">{{ $file->sizeText() }}</span>
                <button type="button" class="file-x" data-drop-file="{{ $file->id }}"
                        title="นำไฟล์ออก" data-i18n-title="budget.file.remove"
                        aria-label="นำไฟล์ออก">&#10005;</button>
              </li>
            @endforeach
          </ul>
        @endif

        {{--
          🔴 <input type="file"> ตั้งค่ารายการไฟล์เองไม่ได้ (เบราว์เซอร์ล็อกไว้)
             จึงเก็บไฟล์ที่เลือกไว้เอง แล้วยัดกลับผ่าน DataTransfer ทุกครั้งที่กดกากบาท
        --}}
        <input class="input" type="file" name="files[]" id="file-input" multiple style="height:auto;padding:9px 12px">
        <ul class="file-list" id="file-picked" hidden></ul>
      </div>


      {{-- ═══════════ ช่องลงชื่อ — เหมือนหน้าเอกสารจริง ═══════════ --}}
      <p class="paper-sec" data-i18n="budget.signatures">ลงชื่อ</p>

      {{--
        🔴 ช่องลงชื่อเดินตามคนที่เลือกใน "สำเนาเรียน" (เจ้าของสั่ง 2026-09-09)
           เริ่มที่ผู้ส่งเอกสารเสมอ → ผู้อนุมัติตามลำดับ → CEO ปิดท้าย
           คนเยอะขึ้นช่องจะเล็กลงเอง เพื่อให้ยังอยู่ในกระดาษหน้าเดียว
        🔴 ไม่มีปุ่มประทับลายเซ็นแล้ว — ระบบเซ็นให้ตอนกด "ส่งเอกสาร"
      --}}
      <div class="sign-grid" id="sign-grid"></div>
    </section>

    {{--
      ═══════════ ปุ่มดำเนินการ ═══════════
      🔴 ลบได้เฉพาะตอนเป็นร่าง หรือส่งไปแล้วแต่ยังไม่มีใครตัดสิน (เจ้าของสั่ง 2026-09-03)
         อนุมัติแล้ว / ไม่อนุมัติแล้ว ลบไม่ได้ — เอกสารที่ตัดสินไปแล้วต้องเก็บไว้ตรวจย้อนได้
    --}}
    {{--
      ปุ่มของโหมดกรอก — ส่งเอกสาร/ลบ ย้ายไปอยู่โหมดดูแล้ว
      🔴 ยินยอมอยู่ซ้าย · ยกเลิกอยู่ขวา (กติกาของโปรเจค)
    --}}
    <div class="bar" style="max-width:800px;margin:16px auto 0">
      @if ($editable)
        <button type="submit" class="btn" data-i18n="budget.saveDraft">บันทึกร่าง</button>
      @endif
      <span class="spacer"></span>
      @unless ($isNew)
        <button type="button" class="btn btn-quiet" data-go-view data-i18n="common.cancel">ยกเลิก</button>
      @endunless
    </div>
  </form>
  @endif

  @unless ($isNew)
    {{-- ฟอร์มส่งเอกสาร/ลบเอกสาร — วางนอกฟอร์มหลัก เพราะซ้อน <form> ใน <form> ไม่ได้ --}}
    <form method="POST" action="{{ route('budget.invest.submit', $invest) }}" id="send-form" hidden>@csrf</form>
    <form method="POST" action="{{ route('budget.invest.destroy', $invest) }}" id="del-form" hidden>@csrf @method('DELETE')</form>

    @foreach ($invest->files as $file)
      <form method="POST" action="{{ route('budget.invest.file.remove', $file) }}" id="drop-file-{{ $file->id }}" hidden>
        @csrf @method('DELETE')
      </form>
    @endforeach
  @endunless

  @include('budget.partials.assets')
  @include('budget.partials.paper')
  @include('access.partials.picker-assets')

  <script>
    'use strict';

    var fileIcons = @json($fileIcons);
    /*
      🔴 สคริปต์ของหน้านี้อยู่ "ก่อน" ตัวนิยาม window.BMS ในหน้าเดียวกัน
         เรียกตรงๆ ตอนโหลดจะได้ TypeError แล้วสคริปต์ทั้งก้อนตายเงียบ
         (บั๊กจริง 2026-09-09 — ตัวเลือกผู้อนุมัติไม่ขึ้นเลยเพราะเหตุนี้)
    */
    var isEn = function () {
      return !!(window.BMS && typeof window.BMS.lang === 'function' && window.BMS.lang() === 'en');
    };

    /*
      ── สำเนาเรียน = ผู้อนุมัติตามลำดับ (เจ้าของสั่ง 2026-09-09) ────────
      ยกหน้าตามาจากระบบ Memo เดิม: แถวละ 1 คน มีเลขลำดับ ลากสลับได้ กันเลือกซ้ำ
      🔴 CEO ไม่อยู่ในรายการนี้ — เซิร์ฟเวอร์ต่อท้ายให้เองตอนกดส่ง
         (ถ้าใส่ไว้ในฟอร์มด้วย ผู้ขอจะลบทิ้งได้ ซึ่งผิดกติกา)
    */
    /*
      🔴 เลือกได้จากพนักงานทุกคน (เจ้าของสั่ง 2026-09-09)
         จึงค้นที่เซิร์ฟเวอร์ ไม่ฝังรายชื่อลงหน้า — บริษัทมี 1,494 บัญชี
         (กฎโปรเจค: ห้ามยัดรหัสพนักงานทั้งบริษัทลง attribute · เคยได้ attribute ยาว 9 KB มาแล้ว)
    */
    var apvSearchUrl = @json(route('people.search'));
    var apvChosen = @json(collect($chosenApprovers)->pluck('employee_code')->values());
    var apvRoles = @json($chosenRoles ?? []);
    var apvFinal = @json($finalApprover);
    var apvMax = @json((int) config('bms.max_approvers', 8));
    var meName = @json($invest->created_by_name ?: $me->displayName('th'));

    /*
      ทะเบียนคนที่ "รู้จักแล้ว" — ใช้วาดชื่อ/รูปของคนที่ถูกเลือกไว้
      เริ่มจากคนที่บันทึกไว้ตอนร่าง แล้วเติมทีละคนตอนผู้ใช้เลือกจากผลค้นหา
    */
    var apvKnown = {};

    @foreach ($chosenApprovers as $person)
      apvKnown[@json($person['employee_code'])] = @json($person);
    @endforeach

    var apvNameOf = function (code) {
      return apvKnown[code] || null;
    };

    (function () {
      var rows = document.getElementById('apv-rows');
      var addBtn = document.getElementById('apv-add');
      // กล่องผู้รับทราบ — รายการเดียวกัน แค่แยกที่วาดตามบทบาท (เจ้าของสั่ง 2026-09-16)
      var ackRows = document.getElementById('ack-rows');
      var ackEmpty = document.getElementById('ack-empty');
      var grid = document.getElementById('sign-grid');
      if (!rows || !grid) { return; }

      // รายชื่อที่เลือกไว้ — ลำดับใน array คือลำดับการเซ็น
      var picked = Array.isArray(apvChosen) ? apvChosen.slice() : [];

      /*
        บทบาทรายคน คู่ index กับ picked (เจ้าของสั่ง 2026-09-16)
          'approve' = ต้องลงนาม   ·   'notice' = แจ้งให้ทราบเฉยๆ ไม่ต้องเซ็น
        🔴 ต้องขยับพร้อม picked ทุกครั้งที่เพิ่ม/ลบ/ลากสลับ ไม่งั้นบทบาทไปตกใส่คนผิด
      */
      var roles = (Array.isArray(apvRoles) ? apvRoles.slice() : []);
      var syncRoles = function () {
        while (roles.length < picked.length) { roles.push('approve'); }
        roles.length = picked.length;
      };
      syncRoles();

      /*
        🔴 เปิดหน้ามาให้มีช่องแรกรออยู่เลย แล้วต่อด้วยผู้บริหาร (เจ้าของสั่ง 2026-09-09)
           ไม่ต้องกด "เพิ่มผู้อนุมัติ" ก่อนถึงจะเห็นช่อง — คนส่วนใหญ่ต้องเลือกอย่างน้อย 1 คนอยู่แล้ว
           และคงไว้อย่างน้อย 1 แถวเสมอ (ปุ่มเอาออกของแถวสุดท้ายจะถูกปิด) แบบเดียวกับ Memo
      */
      if (picked.length === 0) { picked.push(''); }
      var dragFrom = null;

      /* ── ช่องลงชื่อเดินตามคนที่เลือก — ผู้ส่ง → ผู้อนุมัติ → CEO ── */
      var drawSigns = function () {
        // 🔴 ผู้ขอไม่ลงนามแล้ว (เจ้าของสั่ง 2026-09-10) — ช่องลงชื่อมีแต่ผู้อนุมัติ
        var people = [];

        // 🔴 ข้ามแถวที่ยังไม่ได้เลือกคน ไม่งั้นจะมีกล่องลายเซ็นเปล่าโผล่มาโดยไม่มีชื่อ
        //    และข้าม "ผู้รับทราบ" ด้วย — เขาไม่ต้องเซ็น จึงไม่ควรมีช่องลงชื่อในกระดาษ
        picked.forEach(function (code, i) {
          if (!code || roles[i] === 'notice') { return; }

          var p = apvNameOf(code);
          people.push({
            name: p ? (isEn() ? (p.name_en || p.name_th) : p.name_th) : code,
            role: isEn() ? 'Approver' : 'ผู้อนุมัติ',
          });
        });

        if (apvFinal) {
          people.push({
            name: isEn() ? (apvFinal.name_en || apvFinal.name_th) : apvFinal.name_th,
            role: apvFinal.position || 'CEO',
          });
        }

        /*
          🔴 แถวละไม่เกิน 5 ช่อง (เจ้าของสั่ง 2026-09-09)
             คนน้อยกว่า 5 = แบ่งเต็มความกว้าง · เกิน 5 = ขึ้นแถวใหม่
             แถวสุดท้ายที่ไม่เต็มจะถูกจัดกึ่งกลางให้เองด้วย justify-content ของ flex
        */
        grid.style.setProperty('--sign-cols', String(Math.min(people.length || 1, 5)));
        grid.classList.toggle('is-tight', people.length > 5);

        grid.textContent = '';

        people.forEach(function (person) {
          var box = document.createElement('div');
          box.className = 'sign-box';

          var area = document.createElement('span');
          area.className = 'sign-area';
          area.appendChild(document.createElement('span')).className = 'sign-wait';
          box.appendChild(area);

          var name = document.createElement('span');
          name.className = 'sign-name';
          name.textContent = person.name;
          box.appendChild(name);

          var role = document.createElement('span');
          role.className = 'sign-role';
          role.textContent = person.role;
          box.appendChild(role);

          grid.appendChild(box);
        });
      };

      /*
        ── ชิ้นส่วนของแถว ─────────────────────────────────────────
        🔴 รูปต้องกดขยายได้ตามกฎของโปรเจค (DESIGN_SYSTEM 6A)
           ตัวขยายผูก event ไว้ที่ document จึงใช้ได้กับรูปที่ JS สร้างทีหลัง
           ขอแค่ใส่ data-zoomable / data-zoom-src ให้ถูก
      */
      var avatarEl = function (person) {
        var label = (person.name_th || '') + ' (' + person.employee_code + ')';

        if (!person.photo) {
          var box = document.createElement('span');
          box.className = 'avatar avatar-sm';
          box.setAttribute('aria-hidden', 'true');
          box.textContent = person.initial || '';

          return box;
        }

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'avatar avatar-sm';
        btn.setAttribute('data-zoomable', '');
        btn.setAttribute('data-zoom-src', person.photo);
        btn.setAttribute('data-zoom-alt', label);
        btn.title = label;

        var img = document.createElement('img');
        img.src = person.photo;
        img.alt = '';
        img.loading = 'lazy';
        img.addEventListener('error', function () { btn.textContent = person.initial || ''; });
        btn.appendChild(img);

        return btn;
      };

      var whoEl = function (person) {
        var wrap = document.createElement('span');
        wrap.className = 'apv-who';

        var line = document.createElement('span');
        line.className = 'apv-name';

        var name = document.createElement('span');
        name.textContent = isEn() ? (person.name_en || person.name_th) : person.name_th;
        line.appendChild(name);

        var codeTag = document.createElement('span');
        codeTag.className = 'apv-code';
        codeTag.textContent = person.employee_code;
        line.appendChild(codeTag);

        wrap.appendChild(line);

        var meta = document.createElement('span');
        meta.className = 'apv-meta';
        meta.textContent = [person.position, person.department].filter(Boolean).join(' · ');
        wrap.appendChild(meta);

        return wrap;
      };

      /* ช่องค้นหาของแถวที่ยังว่าง — ค้นจากทะเบียนผู้มีอำนาจอนุมัติเท่านั้น */
      var findEl = function (index) {
        var wrap = document.createElement('span');
        wrap.className = 'apv-find';

        var input = document.createElement('input');
        input.type = 'search';
        input.className = 'input';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.placeholder = isEn()
          ? 'Employee code or name (min. 2 characters)'
          : 'รหัสพนักงาน หรือ ชื่อ-สกุล (อย่างน้อย 2 ตัวอักษร)';
        wrap.appendChild(input);

        var hits = document.createElement('div');
        hits.className = 'apv-hits';
        hits.hidden = true;
        wrap.appendChild(hits);

        var note = function (text) {
          hits.textContent = '';
          var p = document.createElement('p');
          p.className = 'apv-note';
          p.textContent = text;
          hits.appendChild(p);
          hits.hidden = false;
        };

        var show = function (found) {
          hits.textContent = '';

          // คนที่ถูกเลือกในแถวอื่นแล้วต้องไม่โผล่ซ้ำ — คนเดียวเซ็น 2 รอบไม่ได้
          var list = found.filter(function (o) { return picked.indexOf(o.employee_code) === -1; });

          if (list.length === 0) {
            note(isEn() ? 'No one found' : 'ไม่พบพนักงานที่ค้นหา');

            return;
          }

          list.forEach(function (o) {
            var hit = document.createElement('button');
            hit.type = 'button';
            hit.className = 'apv-hit';
            hit.appendChild(avatarEl(o));
            hit.appendChild(whoEl(o));

            hit.addEventListener('click', function () {
              apvKnown[o.employee_code] = o;   // จำไว้ใช้วาดชื่อ/รูปในแถวและช่องลงชื่อ
              picked[index] = o.employee_code;
              draw();
            });

            hits.appendChild(hit);
          });

          hits.hidden = false;
        };

        /*
          🔴 หน่วงก่อนยิง และทิ้งผลของคำค้นเก่า
             พิมพ์เร็วๆ จะยิงหลายรอบ ผลที่กลับมาช้ากว่าอาจมาทับผลของคำล่าสุด
        */
        var timer = null;
        var seq = 0;

        var render = function () {
          var term = input.value.trim();
          window.clearTimeout(timer);

          if (term.length < 2) { hits.hidden = true; return; }

          timer = window.setTimeout(function () {
            var mine = ++seq;
            note(isEn() ? 'Searching…' : 'กำลังค้นหา…');

            window.fetch(apvSearchUrl + '?q=' + encodeURIComponent(term), {
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
              credentials: 'same-origin',
            })
              .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
              .then(function (data) {
                if (mine !== seq) { return; }   // มีคำค้นใหม่แล้ว ทิ้งผลนี้
                show(data.results || []);
              })
              .catch(function () {
                if (mine !== seq) { return; }
                note(isEn() ? 'Search failed' : 'ค้นหาไม่สำเร็จ ลองใหม่อีกครั้ง');
              });
          }, 220);
        };

        input.addEventListener('input', render);
        input.addEventListener('focus', render);

        // คลิกที่อื่นแล้วปิดรายการ — ไม่งั้นค้างบังของข้างล่าง
        input.addEventListener('blur', function () {
          window.setTimeout(function () { hits.hidden = true; }, 160);
        });

        // อย่าให้การลากของแถวไปกวนการเลือกข้อความในช่องค้นหา
        input.addEventListener('dragstart', function (e) { e.stopPropagation(); });

        return wrap;
      };
      /* ── วาดแถวเลือกคน — แยกลง 2 กล่องตามบทบาท (เจ้าของสั่ง 2026-09-16) ── */
      var draw = function () {
        rows.textContent = '';
        if (ackRows) { ackRows.textContent = ''; }

        /*
          🔴 แต่ละกลุ่มนับเลขของตัวเองเริ่มที่ 1 ใหม่ (เจ้าของสั่ง 2026-09-16 รอบ 2)
             ไม่ใช่นับต่อกัน — คนละหัวข้อ คนละเรื่อง
        */
        var apvNo = 0;
        var ackNo = 0;

        picked.forEach(function (code, i) {
          var isAck = roles[i] === 'notice';

          var row = document.createElement('div');
          row.className = 'apv-row';
          // ลากสลับลำดับมีความหมายเฉพาะฝั่งผู้อนุมัติ
          row.draggable = !isAck;

          var handle = document.createElement('span');
          handle.className = 'apv-drag';
          handle.textContent = isAck ? '' : '⠿';
          handle.title = isEn() ? 'Drag to reorder' : 'ลากเพื่อสลับลำดับ';
          row.appendChild(handle);

          var no = document.createElement('span');
          no.className = 'apv-no';
          if (isAck) {
            ackNo += 1;
            no.textContent = String(ackNo);
          } else {
            apvNo += 1;
            no.textContent = String(apvNo);
          }
          row.appendChild(no);

          if (code) {
            // เลือกคนแล้ว — โชว์รูป รหัส ชื่อ-สกุล ตำแหน่ง แผนก (เจ้าของสั่ง 2026-09-09)
            var person = apvNameOf(code) || { employee_code: code, name_th: code, initial: '' };
            row.appendChild(avatarEl(person));
            row.appendChild(whoEl(person));
          } else {
            // ยังไม่ได้เลือก — ช่องค้นหาด้วยรหัสพนักงานหรือชื่อ-สกุล เหมือนตัวเลือกพนักงานที่อื่น
            row.appendChild(document.createElement('span'));
            row.appendChild(findEl(i));
          }

          /*
            บทบาทรายคน (เจ้าของสั่ง 2026-09-16)
              อนุมัติ    = ต้องลงนาม เข้าคิวรอเซ็น
              แจ้งให้ทราบ = เห็นเอกสารเฉยๆ ไม่ต้องเซ็น ไม่มีช่องลงชื่อในกระดาษ
            🔴 ยังไม่ได้เลือกคนก็ยังเลือกบทบาทไว้ล่วงหน้าได้ ไม่ต้องบังคับลำดับการกรอก
          */
          var role = document.createElement('select');
          role.className = 'sel apv-role';
          role.setAttribute('aria-label', isEn() ? 'Role' : 'บทบาท');
          [['approve', 'อนุมัติ', 'Approve'], ['notice', 'แจ้งให้ทราบ', 'Notice']].forEach(function (opt) {
            var o = document.createElement('option');
            o.value = opt[0];
            o.textContent = isEn() ? opt[2] : opt[1];
            if (roles[i] === opt[0]) { o.selected = true; }
            role.appendChild(o);
          });
          role.addEventListener('change', function () {
            roles[i] = role.value;
            /*
              🔴 ต้องวาดใหม่ทั้งชุด ไม่ใช่แค่ drawSigns() (เจ้าของแจ้ง 2026-09-16)
                 เพราะ "ที่อยู่ของแถว" ขึ้นกับบทบาท — เลือกแจ้งให้ทราบต้องตกลงกลุ่มล่างทันที
                 เลือกอนุมัติต้องเด้งกลับขึ้นมาทันที · และเลขลำดับของทั้ง 2 กลุ่มต้องนับใหม่
            */
            draw();
          });
          row.appendChild(role);

          var x = document.createElement('button');
          x.type = 'button';
          x.className = 'apv-x';
          x.innerHTML = '&#10005;';
          x.title = isEn() ? 'Remove' : 'เอาออก';
          // เหลือแถวเดียวห้ามเอาออก ไม่งั้นจะไม่มีช่องให้เลือกคนเลย (กติกาเดียวกับ Memo)
          x.disabled = picked.length <= 1;

          x.addEventListener('click', function () {
            picked.splice(i, 1);
            roles.splice(i, 1);
            draw();
          });
          row.appendChild(x);

          /* ── ลากสลับลำดับ ── */
          row.addEventListener('dragstart', function () {
            dragFrom = i;
            row.classList.add('is-dragging');
          });

          row.addEventListener('dragend', function () {
            dragFrom = null;
            row.classList.remove('is-dragging');
          });

          row.addEventListener('dragover', function (e) {
            e.preventDefault();
            row.classList.add('is-over');
          });

          row.addEventListener('dragleave', function () { row.classList.remove('is-over'); });

          row.addEventListener('drop', function (e) {
            e.preventDefault();
            row.classList.remove('is-over');
            if (dragFrom === null || dragFrom === i) { return; }

            // 🔴 ย้ายบทบาทตามคนไปด้วย ไม่งั้นลากสลับแล้วบทบาทค้างอยู่ที่ตำแหน่งเดิม
            var moved = picked.splice(dragFrom, 1)[0];
            var movedRole = roles.splice(dragFrom, 1)[0];
            picked.splice(i, 0, moved);
            roles.splice(i, 0, movedRole);
            draw();
          });

          // 🔴 ที่อยู่ของแถวขึ้นกับบทบาท — สลับ dropdown แล้ววาดใหม่ แถวจะย้ายกล่องเอง
          (isAck && ackRows ? ackRows : rows).appendChild(row);
        });

        // ไม่มีผู้รับทราบเลย = บอกให้รู้ว่าเว้นว่างได้ ไม่ใช่ลืมกรอก
        if (ackEmpty) { ackEmpty.hidden = roles.indexOf('notice') !== -1; }

        /*
          CEO ปิดท้ายเสมอ — เลขลำดับเดินต่อจากคนสุดท้ายที่กดเพิ่ม
          🔴 ไม่มีที่จับลากและไม่มีปุ่มเอาออก เพราะถอด/สลับไม่ได้
             และไม่ส่งค่ากลับ เพราะเซิร์ฟเวอร์ต่อท้ายให้เองอยู่แล้ว
        */
        if (apvFinal) {
          var last = document.createElement('div');
          last.className = 'apv-row is-locked';

          last.appendChild(document.createElement('span'));   // ช่องที่จับลาก — เว้นว่างไว้ให้ตรงคอลัมน์

          var lastNo = document.createElement('span');
          lastNo.className = 'apv-no apv-no-last';
          /*
            🔴 เลขของ CEO ต่อจาก "ผู้อนุมัติ" เท่านั้น ไม่ใช่นับรวมผู้รับทราบ (เจ้าของแจ้ง 2026-09-16)
               มีผู้อนุมัติ 4 คน CEO ต้องเป็น 5 · ผู้รับทราบมีกี่คนก็ไม่เกี่ยว เพราะเขาไม่ได้อยู่ในคิวเซ็น
          */
          lastNo.textContent = String(apvNo + 1);
          last.appendChild(lastNo);

          last.appendChild(avatarEl(apvFinal));
          last.appendChild(whoEl(apvFinal));

          // 🔴 CEO เป็นผู้ลงนามปิดท้ายเสมอ เปลี่ยนเป็น "แจ้งให้ทราบ" ไม่ได้ จึงเป็นข้อความไม่ใช่ช่องเลือก
          var lastRole = document.createElement('span');
          lastRole.className = 'apv-role-fixed';
          lastRole.textContent = isEn() ? 'Approve' : 'อนุมัติ';
          last.appendChild(lastRole);

          last.appendChild(document.createElement('span'));   // ช่องปุ่มเอาออก — เว้นว่างไว้

          rows.appendChild(last);
        }

        if (addBtn) { addBtn.disabled = picked.length >= apvMax; }
        drawSigns();
      };

      if (addBtn) {
        addBtn.addEventListener('click', function () {
          if (picked.length >= apvMax) { return; }
          picked.push('');
          roles.push('approve');
          draw();
        });
      }


      // เปลี่ยนภาษาแล้วต้องวาดใหม่ ไม่งั้นชื่อกับป้ายค้างเป็นภาษาเดิม
      window.addEventListener('bms:lang', draw);

      /*
        ช่องที่ส่งไปกับฟอร์ม — สร้างสดตอน submit
        🔴 ต้องเป็น input ซ่อน ไม่ใช่ตัว <select> เอง เพราะลำดับใน DOM คือลำดับที่ส่ง
           และ select ที่ยังไม่ได้เลือกคนจะส่งค่าว่างไปด้วย
      */
      /*
        คืนรายชื่อพร้อมบทบาท เรียงตามลำดับที่ผู้ใช้จัดไว้
        🔴 ตัดแถวที่ยังไม่ได้เลือกคนทิ้ง แต่ต้องตัด "บทบาทของแถวนั้น" ไปพร้อมกัน
           ไม่งั้น index เลื่อน แล้วบทบาทไปตกใส่คนถัดไป
      */
      window.bmsApprovers = function () {
        var out = [];
        picked.forEach(function (code, i) {
          if (code !== '') { out.push({ code: code, role: roles[i] === 'notice' ? 'notice' : 'approve' }); }
        });

        return out;
      };

      draw();

      /*
        วาดซ้ำเมื่อหน้าโหลดครบ — ตอนวาดรอบแรก window.BMS ยังไม่มา ภาษาจึงยังเป็นค่าตั้งต้น
        รอบนี้ค่อยได้ภาษาที่ผู้ใช้เลือกไว้จริง
      */
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', draw);
      }
    })();


    // ── รายการไฟล์ที่เพิ่งเลือก — กดกากบาทเอาออกได้ทีละไฟล์ ───────
    (function () {
      var input = document.getElementById('file-input');
      var list = document.getElementById('file-picked');
      if (!input || !list) { return; }

      var picked = [];

      /*
        ที่อยู่ไฟล์ในเครื่อง (blob:) คู่กับ picked ทีละตัว — ใช้เปิดดูก่อนบันทึก
        🔴 ต้องคืนหน่วยความจำด้วย revokeObjectURL ตอนเอาไฟล์ออก ไม่งั้นค้างจนกว่าจะปิดแท็บ
      */
      var urls = [];

      var iconFor = function (name) {
        return fileIcons[(name.split('.').pop() || '').toLowerCase()] || fileIcons._default;
      };

      var sizeText = function (bytes) {
        if (bytes < 1024) { return bytes + ' B'; }
        if (bytes < 1048576) { return (bytes / 1024).toFixed(1) + ' KB'; }

        return (bytes / 1048576).toFixed(1) + ' MB';
      };

      // ยัดรายการกลับเข้า input เพื่อให้ตอน submit ส่งเฉพาะไฟล์ที่ยังเหลืออยู่
      var push = function () {
        var dt = new DataTransfer();
        picked.forEach(function (file) { dt.items.add(file); });
        input.files = dt.files;
      };

      var draw = function () {
        list.textContent = '';
        list.hidden = picked.length === 0;

        picked.forEach(function (file, i) {
          var row = document.createElement('li');
          row.className = 'file-row';

          var ico = document.createElement('img');
          ico.className = 'file-ico';
          ico.src = iconFor(file.name);
          ico.alt = '';

          // เปิดดูไฟล์ที่เพิ่งแนบได้เลย ไม่ต้องรอบันทึกร่างก่อน (เจ้าของแจ้ง 2026-09-04)
          var name = document.createElement('a');
          name.className = 'file-name';
          name.textContent = file.name;
          name.href = urls[i];
          name.target = '_blank';
          name.rel = 'noopener';
          name.title = isEn()
            ? 'Open this file (not uploaded yet)'
            : 'เปิดดูไฟล์นี้ (ยังไม่ได้อัปโหลด — กดบันทึกร่างเพื่อเก็บไว้)';

          var size = document.createElement('span');
          size.className = 'file-size';
          size.textContent = sizeText(file.size);

          var x = document.createElement('button');
          x.type = 'button';
          x.className = 'file-x';
          x.innerHTML = '&#10005;';
          x.title = isEn() ? 'Remove file' : 'นำไฟล์ออก';
          x.setAttribute('aria-label', x.title);
          x.addEventListener('click', function () {
            URL.revokeObjectURL(urls[i]);
            picked.splice(i, 1);
            urls.splice(i, 1);
            push();
            draw();
          });

          row.append(ico, name, size, x);
          list.appendChild(row);
        });
      };

      input.addEventListener('change', function () {
        // เลือกซ้ำให้ต่อท้ายของเดิม ไม่ทับ — แต่ตัดชื่อ+ขนาดที่ซ้ำกันออก
        Array.prototype.forEach.call(input.files, function (file) {
          var dup = picked.some(function (f) { return f.name === file.name && f.size === file.size; });

          if (!dup) {
            picked.push(file);
            urls.push(URL.createObjectURL(file));
          }
        });

        push();
        draw();
      });
    })();

    // ── นำไฟล์ที่บันทึกไว้แล้วออก ─────────────────────────────────
    document.querySelectorAll('[data-drop-file]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        window.BMS.confirm({
          kind: 'warn',
          title: isEn() ? 'Remove attachment?' : 'นำไฟล์แนบออก?',
          text: isEn() ? 'This file will be deleted.' : 'ไฟล์นี้จะถูกลบออกจากเอกสาร',
          ok: isEn() ? 'Remove' : 'นำออก',
          onOk: function () {
            document.getElementById('drop-file-' + btn.getAttribute('data-drop-file')).requestSubmit();
          },
        });
      });
    });


    /*
      ── ตรวจก่อนบันทึก/ส่ง ────────────────────────────────────────
      🔴 เจ้าของสั่ง 2026-09-03: กรอกไม่ครบต้องแจ้ง แล้วเลื่อนไปที่ช่องนั้นให้ด้วย
         และต้องประทับลายเซ็นก่อนเสมอ — เอกสารที่ไม่มีลายเซ็นผู้เสนอถือว่ายังไม่สมบูรณ์
    */
    /*
      🔴 ตรวจไม่ผ่านตอนอยู่ "โหมดดู" ต้องเปิดโหมดกรอกให้เห็นช่องที่ผิดก่อน
         ไม่งั้นเด้งบอกว่ากรอกไม่ครบ แต่ผู้ใช้มองไม่เห็นว่าช่องไหน (ฟอร์มถูกซ่อนอยู่)
    */
    var openEditor = function () {
      var view = document.querySelector('[data-doc-view]');
      var form = document.querySelector('[data-doc-form]');
      if (!form || !form.hidden) { return; }

      if (view) { view.hidden = true; if (view.nextElementSibling) { view.nextElementSibling.hidden = true; } }
      form.hidden = false;
    };

    var checkForm = function (form) {
      form.querySelectorAll('.is-missing').forEach(function (el) { el.classList.remove('is-missing'); });

      var fields = form.querySelectorAll('[required]');

      for (var i = 0; i < fields.length; i++) {
        if (fields[i].value.trim() !== '') { continue; }

        var el = fields[i];
        openEditor();
        var label = el.closest('.paper-field');
        var name = label ? (label.querySelector('span').textContent || '').trim() : '';

        el.classList.add('is-missing');
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(function () { el.focus({ preventScroll: true }); }, 320);

        window.BMS.notify({
          kind: 'warn',
          title: isEn() ? 'Please complete this field' : 'กรอกข้อมูลให้ครบก่อน',
          text: name,
          autoClose: 2600,
        });

        return false;
      }

      // ต้องเลือกผู้เสนอก่อน — ช่องนี้เป็นชิปในตัวเลือกพนักงาน ไม่ใช่ input[required] ปกติ
      if (!form.querySelector('[data-picker="proposer"] [data-chip]')) {
        openEditor();
        var pick = document.querySelector('[data-picker="proposer"]');
        pick.scrollIntoView({ behavior: 'smooth', block: 'center' });

        window.BMS.notify({
          kind: 'warn',
          title: isEn() ? 'Please complete this field' : 'กรอกข้อมูลให้ครบก่อน',
          text: isEn() ? 'Proposer' : 'ผู้เสนอ',
          autoClose: 2600,
        });

        return false;
      }


      return true;
    };

    var mainForm = document.querySelector('form[enctype]');

    /*
      ลำดับผู้อนุมัติส่งเป็น input ซ่อน สร้างสดตอนกดส่ง
      🔴 ลำดับใน DOM คือลำดับที่เซิร์ฟเวอร์จะได้รับ จึงต้องเติมตามลำดับใน array เป๊ะๆ
         และต้องล้างของรอบก่อนทิ้งทุกครั้ง ไม่งั้นกดบันทึกซ้ำแล้วรายชื่อทบกันไปเรื่อยๆ
    */
    var putApprovers = function (form) {
      form.querySelectorAll('input[data-apv]').forEach(function (el) { el.remove(); });

      (window.bmsApprovers ? window.bmsApprovers() : []).forEach(function (person) {
        // 🔴 ส่งรหัสกับบทบาทเป็นคู่ index กัน เซิร์ฟเวอร์จับคู่ด้วยตำแหน่งในรายการ
        [['approvers[]', person.code], ['roles[]', person.role]].forEach(function (pair) {
          var el = document.createElement('input');
          el.type = 'hidden';
          el.name = pair[0];
          el.value = pair[1];
          el.setAttribute('data-apv', '1');
          form.appendChild(el);
        });
      });
    };

    if (mainForm) {
      mainForm.addEventListener('submit', function (e) {
        if (!checkForm(e.currentTarget)) { e.preventDefault(); return; }

        putApprovers(e.currentTarget);
      });
    }


    /*
      ── สลับโหมดดู / โหมดกรอก ─────────────────────────────────────
      🔴 ยกเลิกการแก้ไขต้องโหลดหน้าใหม่ ไม่ใช่แค่ซ่อนฟอร์ม
         ไม่งั้นค่าที่พิมพ์ค้างอยู่จะยังอยู่ พอกดแก้ไขอีกทีจะเห็นของที่ไม่ได้บันทึก
    */
    (function () {
      var view = document.querySelector('[data-doc-view]');
      var viewBar = view ? view.nextElementSibling : null;
      var form = document.querySelector('[data-doc-form]');
      var goEdit = document.querySelector('[data-go-edit]');
      var goView = document.querySelector('[data-go-view]');
      if (!view || !form) { return; }

      if (goEdit) {
        goEdit.addEventListener('click', function () {
          view.hidden = true;
          if (viewBar) { viewBar.hidden = true; }
          form.hidden = false;
          form.scrollIntoView({ behavior: 'smooth', block: 'start' });

          // แจ้งแบบไม่ขวางทาง — บอกว่าเข้าโหมดแก้ไขแล้ว และต้องกดบันทึกร่างถึงจะเก็บ
          window.BMS.notify({
            kind: 'ok',
            title: isEn() ? 'Edit mode' : 'เข้าโหมดแก้ไขแล้ว',
            text: isEn() ? 'Press Save draft to keep the changes.' : 'แก้เสร็จแล้วกด "บันทึกร่าง" เพื่อเก็บ',
            autoClose: 2400,
          });
        });
      }

      if (goView) {
        goView.addEventListener('click', function () {
          window.BMS.confirm({
            kind: 'warn',
            title: isEn() ? 'Discard changes?' : 'ทิ้งการแก้ไข?',
            text: isEn() ? 'Anything not saved will be lost.' : 'สิ่งที่ยังไม่ได้บันทึกจะหายไป',
            ok: isEn() ? 'Discard' : 'ทิ้ง',
            onOk: function () { window.location.reload(); },
          });
        });
      }
    })();

    // ── ลบเอกสาร ─────────────────────────────────────────────────
    (function () {
      var btn = document.querySelector('[data-do-delete]');
      var form = document.getElementById('del-form');
      if (!btn || !form) { return; }

      btn.addEventListener('click', function () {
        window.BMS.confirm({
          kind: 'no',
          title: isEn() ? 'Delete this document?' : 'ลบเอกสารนี้?',
          text: isEn() ? 'This cannot be undone.' : 'ลบแล้วกู้คืนไม่ได้',
          ok: isEn() ? 'Delete' : 'ลบ',
          onOk: function () { form.requestSubmit(); },
        });
      });
    })();

    // ── ส่งเอกสาร ─────────────────────────────────────────────────
    (function () {
      var btn = document.querySelector('[data-do-submit]');
      var sendForm = document.getElementById('send-form');
      if (!btn || !sendForm) { return; }

      btn.addEventListener('click', function () {
        if (mainForm && !checkForm(mainForm)) { return; }

        // ต้องมีผู้อนุมัติอย่างน้อย 1 คน (ยังไม่นับ CEO ที่ระบบต่อท้ายให้)
        var chosen = window.bmsApprovers ? window.bmsApprovers() : [];

        if (chosen.length === 0) {
          var box = document.getElementById('apv-rows');
          if (box) { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); }

          window.BMS.notify({
            kind: 'warn',
            title: isEn() ? 'Choose an approver first' : 'เลือกผู้อนุมัติก่อน',
            text: isEn() ? 'Add at least one approver in the CC list.' : 'เพิ่มผู้อนุมัติในสำเนาเรียนอย่างน้อย 1 คน',
            autoClose: 2800,
          });

          return;
        }

        window.BMS.confirm({
          kind: 'warn',
          title: isEn() ? 'Send this document?' : 'ส่งเอกสารนี้?',
          // 🔴 บอกด้วยว่าเลขที่จะออกตอนนี้ — ผู้ใช้เห็นร่างขึ้น "ยังไม่ออกเลข" มาตลอด (เจ้าของสั่ง 2026-09-17)
          text: isEn()
            ? 'It can no longer be edited, and a document number will be issued now.'
            : 'ส่งแล้วแก้ไขไม่ได้อีก และระบบจะออกเลขที่เอกสารให้ทันที',
          ok: isEn() ? 'Send' : 'ส่งเอกสาร',
          onOk: function () {
            // ย้ายลำดับผู้อนุมัติมาใส่ฟอร์มส่ง เพราะซ้อน <form> ใน <form> ไม่ได้
            sendForm.querySelectorAll('input[type="hidden"]:not([name="_token"])').forEach(function (el) { el.remove(); });
            putApprovers(sendForm);

            sendForm.requestSubmit();
          },
        });
      });
    })();

    // เลือกหมวดแล้วเอากรอบแดง "ยังไม่ได้กรอก" ออกทันที (ไม่มีบรรทัดตัวอย่างเลขที่ให้อัปเดตแล้ว)
    (function () {
      var group = document.querySelector('[data-group-select]');
      if (!group) { return; }

      group.addEventListener('change', function () { group.classList.remove('is-missing'); });
    })();
  </script>
@endsection
