@extends('layouts.app')

@section('title', 'ตั้งค่าหมายเลขเอกสาร / Document numbering')

{{--
  ตั้งค่าหมายเลขเอกสาร (เจ้าของสั่ง 2026-09-17 · DECISIONS 50.10)

  แอดมินตั้ง "หมวดงบประมาณ" = ชื่อหมวด 2 ภาษา + โค้ด 2–4 ตัว
    New Model · NM  ->  INV-NM-2569-000001  ·  BGT-NM-2569-000001

  🔴 เจ้าของสั่งให้กระชับ (2026-09-17) — ตารางเหลือเท่าที่จำเป็น ไม่มีช่อง "ลำดับการแสดง"
  🔴 หน้าต่างเพิ่ม/แก้ไขใช้ชุดเดียว — JS เติมค่าตามแถวที่กด
--}}

@push('page-style')
  <style>
    :root { --page-max: 960px; }

    .dg-name { color: var(--navy-900); font-weight: 700; }

    /* 🔴 โค้ดไม่มีกรอบแล้ว (เจ้าของสั่ง 2026-09-17) — โค้ดซ้ำอยู่ในเลขที่ช่องถัดไป กรอบจึงเป็นเส้นส่วนเกิน
       ตัวหนาน้อยลงด้วย (เจ้าของสั่ง) — ชื่อหมวดเป็นตัวหนาอยู่แล้ว 2 คอลัมน์ติดกันหนาทั้งคู่จะตีกัน */
    .dg-code { color: var(--navy-900); font-weight: 500; letter-spacing: .08em; }

    .dg-samples { display: grid; gap: 3px; justify-items: start; }
    .dg-samples .doc-no { font-size: var(--fs-sm); font-weight: 500; }
    /* เลขวิ่ง/ปี ยังไม่ออก จึงจางกว่าส่วนที่แน่นอนแล้ว */
    .dg-run { color: var(--muted); font-style: normal; letter-spacing: .1em; }

    /*
      🔴 สถานะระบายพื้นเต็มช่อง (เจ้าของสั่ง 2026-09-17) — ใช้ชิ้นส่วนกลาง .st ของโมดูล
         (budget/partials/assets) ไม่คิดชุดสีใหม่: ใช้งาน = เขียว · ปิดใช้งาน = เทา
    */
    .dg-table .st { white-space: nowrap; }

    /*
      หมายเหตุท้ายแถว — บอกเหตุผลที่ลบไม่ได้ (เจ้าของสั่ง 2026-09-17)
      🔴 ตัวอักษรแดงล้วน ไม่มีพื้นหลัง — ช่อง "ลบ" ข้างๆ เป็นพื้นเทาอยู่แล้ว
         ใส่พื้นอีกช่องจะกลายเป็นแถบสี 2 ก้อนติดกันจนอ่านไม่ออกว่าอันไหนสำคัญ
    */
    .dg-why { color: var(--danger); font-size: var(--fs-xs); white-space: nowrap; }

    /* ── ช่อง "ลบ" ── ถังขยะเปล่าๆ ไม่มีกรอบ (เจ้าของสั่ง) · หมวดที่ลบไม่ได้เป็นพื้นเทาล้วน */
    .dg-del {
      display: inline-grid; place-items: center; width: 32px; height: 32px;
      border: 0; border-radius: var(--radius);
      background: transparent; color: var(--danger); cursor: pointer;
      transition: background .15s var(--ease);
    }
    .dg-del:hover { background: var(--danger-soft); }

    .dg-acts { display: inline-flex; gap: 6px; }
    .dg-acts .btn { min-height: 32px; padding: 0 12px; font-size: var(--fs-sm); }

    /* หมวดที่ปิดใช้งาน — จางลง แต่ยังอ่านออก และปุ่มยังกดได้ */
    .tbl tr.is-off td:not(.col-act) { opacity: .6; }

    .dg-empty { display: grid; justify-items: center; gap: 6px; padding: 30px 16px; text-align: center; }
    .dg-empty b { color: var(--navy-900); font-size: var(--fs-md); }
    .dg-empty span { color: var(--muted); font-size: var(--fs-sm); }

    /* ── หน้าต่างเพิ่ม/แก้ไข ── */
    .dg-modal { width: min(520px, 100%); }
    .dg-form { display: grid; gap: 14px; }
    .dg-form .field { display: grid; gap: 6px; }
    .dg-form .field > span { color: var(--ink-soft); font-size: var(--fs-sm); font-weight: 600; }
    .dg-form .field > span b { color: var(--danger); }
    .dg-form small { color: var(--muted); font-size: var(--fs-xs); }

    /* ช่องโค้ด — ตัวใหญ่ เว้นระยะอักษร ให้ตรงกับที่จะไปโผล่ในเลขที่ */
    .dg-form .dg-code-input { max-width: 180px; text-transform: uppercase; font-weight: 700; letter-spacing: .12em; }
    .dg-form .dg-code-input[readonly] { background: var(--surface-2); color: var(--ink-soft); cursor: not-allowed; }

    /* 🔴 แดง ไม่ใช่เหลือง (เจ้าของสั่ง 2026-09-17) — ไม่ใช่คำเตือนให้ระวัง แต่คือ "แก้ไม่ได้แล้ว" */
    .dg-lock-note {
      display: flex; align-items: center; gap: 7px;
      padding: 7px 10px; border-radius: var(--radius-sm);
      background: var(--danger-soft); color: var(--danger); font-size: var(--fs-xs); font-weight: 600;
    }

    /* เลขที่ที่จะได้ — อัปเดตทันทีที่พิมพ์โค้ด */
    .dg-preview {
      display: grid; gap: 4px; padding: 10px 14px;
      border: 1px dashed var(--accent); border-radius: var(--radius);
      background: var(--accent-soft);
    }
    .dg-preview > span { color: var(--navy-800); font-size: var(--fs-xs); font-weight: 700; }
    .dg-preview b {
      color: var(--navy-900); font-size: var(--fs-md); font-weight: 700; letter-spacing: .02em;
      font-variant-numeric: tabular-nums; overflow-wrap: anywhere;
    }
    .dg-preview b i { color: var(--accent); font-style: normal; }

    .input.is-invalid { border-color: var(--danger); }
  </style>
@endpush

@section('content')
  <section class="card">
    <div class="card-head">
      <h3 data-i18n="nav.docNumber">ตั้งค่าหมายเลขเอกสาร</h3>
      <button type="button" class="btn" style="margin-left:auto" data-dg-add data-modal-open="dg-form">
        <span data-i18n="dg.add">+ เพิ่มหมวด</span>
      </button>
    </div>

    <div class="card-body">
      @if ($groups->isEmpty())
        <div class="dg-empty">
          <b data-i18n="dg.none">ยังไม่มีหมวดงบประมาณ</b>
          <span data-i18n="dg.noneHint">กด "+ เพิ่มหมวด" เพื่อเริ่มใช้งาน</span>
        </div>
      @else
        <div class="tbl-wrap">
          <table class="tbl dg-table">
            <thead>
              <tr>
                <th class="col-seq" data-i18n="tbl.seq">ลำดับ</th>
                <th class="txt-left" data-i18n="dg.col.name">หมวดงบประมาณ</th>
                <th class="col-no" data-i18n="dg.col.code">โค้ด</th>
                <th class="txt-left" data-i18n="dg.col.sample">เลขที่เอกสาร</th>
                <th class="col-no" data-i18n="dg.col.status">สถานะ</th>
                <th class="col-act" data-i18n="access.tbl.manage">จัดการ</th>
                <th class="col-no" data-i18n="dg.col.delete">ลบ</th>
                <th class="col-no" data-i18n="dg.col.why">หมายเหตุ</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($groups as $group)
                <tr @class(['is-off' => ! $group->active])>
                  <td class="col-seq">{{ $loop->iteration }}</td>
                  {{-- 🔴 ชื่อ 2 ภาษาสลับด้วยตัวเปลี่ยนภาษา ไม่แสดงพร้อมกัน 2 บรรทัด (DESIGN_SYSTEM ข้อ 2) --}}
                  <td class="txt-left">
                    <b class="dg-name" data-loc-th="{{ $group->name_th }}" data-loc-en="{{ $group->name_en }}">{{ $group->name_th }}</b>
                  </td>
                  <td><span class="dg-code">{{ $group->code }}</span></td>
                  <td class="txt-left">
                    {{-- ตัวแทน (ปี xx · เลขวิ่ง xxx) จางกว่าส่วนที่แน่นอนแล้ว — e() ก่อน แล้วค่อยแต่งเฉพาะตัว x --}}
                    <span class="dg-samples">
                      @foreach ([$group->sample_inv, $group->sample_bgt] as $no)
                        <span class="doc-no">{!! preg_replace('~x+~', '<i class="dg-run">$0</i>', e($no)) !!}</span>
                      @endforeach
                    </span>
                  </td>
                  {{-- 🔴 .st ต้องเป็นลูกตรงของ td ไม่งั้นกฎ :has() ที่ระบายเต็มช่องไม่ติด --}}
                  <td>
                    @if ($group->active)
                      <span class="st st-active" data-i18n="dg.active">ใช้งาน</span>
                    @else
                      <span class="st st-closed" data-i18n="dg.inactive">ปิดใช้งาน</span>
                    @endif
                  </td>
                  <td class="col-act">
                    <span class="dg-acts">
                      <button type="button" class="btn btn-quiet" data-modal-open="dg-form"
                              data-dg-edit="{{ $group->id }}"
                              data-action="{{ route('budget.docgroup.update', $group) }}"
                              data-name-th="{{ $group->name_th }}"
                              data-name-en="{{ $group->name_en }}"
                              data-code="{{ $group->code }}"
                              data-locked="{{ $group->code_locked ? '1' : '0' }}">
                        <span data-i18n="dg.edit">แก้ไข</span>
                      </button>

                      <button type="button" class="btn btn-quiet"
                              data-dg-toggle="{{ $group->id }}" data-on="{{ $group->active ? '1' : '0' }}"
                              data-name-th="{{ $group->name_th }}" data-name-en="{{ $group->name_en }}">
                        <span data-i18n="{{ $group->active ? 'dg.disable' : 'dg.enable' }}">{{ $group->active ? 'ปิดใช้งาน' : 'เปิดใช้งาน' }}</span>
                      </button>

                    </span>

                    <form method="POST" action="{{ route('budget.docgroup.toggle', $group) }}" id="dg-toggle-{{ $group->id }}" hidden>@csrf</form>
                  </td>

                  {{--
                    ── ช่อง "ลบ" (เจ้าของสั่ง 2026-09-17) ──
                    🔴 มีเอกสาร (รวมร่าง) = ลบไม่ได้ — ช่องเป็น "พื้นเทาล้วน" ไม่ใช่ปุ่มจางที่กดไม่ได้
                       เจ้าของสั่งเอาแม่กุญแจออก เพราะพื้นเทาบอกอยู่แล้วว่าแถวนี้แตะไม่ได้
                       เหตุผลยังอ่านได้จากคำใบ้ตอนชี้เมาส์ และจากกล่องแดงในหน้าต่างแก้ไข
                  --}}
                  @if ($group->can_delete)
                    <td>
                      <button type="button" class="dg-del" data-dg-delete="{{ $group->id }}"
                              data-name-th="{{ $group->name_th }}" data-name-en="{{ $group->name_en }}"
                              title="ลบ" data-i18n-title="dg.delete">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                          <path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path>
                          <path d="M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"></path>
                          <path d="M9 7V4h6v3"></path>
                        </svg>
                      </button>
                      <form method="POST" action="{{ route('budget.docgroup.destroy', $group) }}" id="dg-delete-{{ $group->id }}" hidden>@csrf @method('DELETE')</form>
                    </td>
                    {{-- ลบได้ = ไม่มีอะไรต้องอธิบาย --}}
                    <td class="soft">—</td>
                  @else
                    <td>
                      {{-- &nbsp; ไว้ให้ช่องมีความสูงเท่าแถวอื่น — ไม่มีอะไรข้างในเลยพื้นจะเป็นแถบบางๆ --}}
                      <span class="st st-none" title="ไม่สามารถแก้ไขโค้ดได้ เนื่องจากเปิดใช้เลข INV , BGT แล้ว"
                            data-i18n-title="dg.locked">&nbsp;</span>
                    </td>
                    <td><span class="dg-why" data-i18n="dg.hasDocs">มีเอกสารในหมวดแล้ว</span></td>
                  @endif
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </section>

  {{-- ═══════════ หน้าต่างเพิ่ม / แก้ไขหมวด ═══════════ --}}
  <div class="modal-wrap" data-modal="dg-form" hidden role="dialog" aria-modal="true" aria-labelledby="dg-form-title">
    <div class="modal dg-modal">
      <div class="modal-head">
        <span class="modal-title" id="dg-form-title">
          <span data-dg-title-add data-i18n="dg.modal.add">เพิ่มหมวดงบประมาณ</span>
          <span data-dg-title-edit data-i18n="dg.modal.edit" hidden>แก้ไขหมวดงบประมาณ</span>
        </span>
        @include('access.partials.modal-x')
      </div>

      <form class="modal-body dg-form" method="POST" action="{{ route('budget.docgroup.store') }}" id="dg-form" novalidate>
        @csrf
        <input type="hidden" name="_method" value="POST" data-dg-method>

        <label class="field">
          <span><span data-i18n="dg.f.nameTh">ชื่อหมวด (ภาษาไทย)</span> <b>*</b></span>
          <input class="input" type="text" name="name_th" maxlength="80" required autocomplete="off"
                 placeholder="เช่น โมเดลใหม่" data-i18n-placeholder="dg.ph.nameTh"
                 value="{{ old('name_th') }}">
        </label>

        <label class="field">
          <span><span data-i18n="dg.f.nameEn">ชื่อหมวด (ภาษาอังกฤษ)</span> <b>*</b></span>
          <input class="input" type="text" name="name_en" maxlength="80" required autocomplete="off"
                 placeholder="เช่น New Model" data-i18n-placeholder="dg.ph.nameEn"
                 value="{{ old('name_en') }}">
        </label>

        <label class="field">
          <span><span data-i18n="dg.f.code">โค้ด</span> <b>*</b></span>
          <input class="input dg-code-input" type="text" name="code" maxlength="4" required autocomplete="off"
                 placeholder="NM" spellcheck="false" value="{{ old('code') }}">
          <small data-i18n="dg.f.codeHint">A–Z หรือ 0–9 · 2–4 ตัว</small>
        </label>

        <p class="dg-lock-note" data-dg-lock-note hidden>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex:0 0 auto">
            <rect x="4" y="10" width="16" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
          </svg>
          <span data-i18n="dg.locked">ไม่สามารถแก้ไขโค้ดได้ เนื่องจากเปิดใช้เลข INV , BGT แล้ว</span>
        </p>

        {{-- เลขที่ที่จะได้ — อัปเดตทันทีที่พิมพ์โค้ด --}}
        <div class="dg-preview" aria-live="polite">
          <span data-i18n="dg.f.preview">เลขที่เอกสาร</span>
          {{-- 🔴 ปี/เลขวิ่งเป็น xx/xxx — บอกรูปแบบ ไม่ใช่เลขที่จะได้ (เจ้าของสั่ง 2026-09-17) --}}
          <b>INV-<i data-dg-sample>??</i>-{{ $era }}xx-000xxx</b>
          <b>BGT-<i data-dg-sample>??</i>-{{ $era }}xx-000xxx</b>
        </div>
      </form>

      {{-- 🔴 ยินยอมอยู่ซ้าย · ยกเลิกอยู่ขวา (กติกาของโปรเจค) --}}
      <div class="modal-foot">
        <button type="submit" class="btn" form="dg-form" data-i18n="common.save">บันทึก</button>
        <span class="spacer"></span>
        <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.cancel">ยกเลิก</button>
      </div>
    </div>
  </div>

  @include('budget.partials.assets')
@endsection

@push('page-script')
  <script>
    'use strict';
    (function () {
      var isEn = function () { return window.BMS.lang() === 'en'; };
      var form = document.getElementById('dg-form');
      if (!form) { return; }

      var storeUrl = form.getAttribute('action');
      var method = form.querySelector('[data-dg-method]');
      var nameTh = form.elements.name_th;
      var nameEn = form.elements.name_en;
      var code = form.elements.code;
      var lockNote = form.querySelector('[data-dg-lock-note]');
      var titleAdd = document.querySelector('[data-dg-title-add]');
      var titleEdit = document.querySelector('[data-dg-title-edit]');
      var CODE_RE = /^[A-Z0-9]{2,4}$/;

      // ── เลขที่ตัวอย่าง + บังคับรูปแบบโค้ดระหว่างพิมพ์ ──
      function paintSample() {
        var v = code.value;
        form.querySelectorAll('[data-dg-sample]').forEach(function (el) { el.textContent = v || '??'; });
      }

      code.addEventListener('input', function () {
        /*
          🔴 ตัดทุกอย่างที่ไม่ใช่ A–Z / 0–9 ทิ้งทันที แล้วแปลงเป็นตัวใหญ่
             เก็บตำแหน่งเคอร์เซอร์ไว้ ไม่งั้นพิมพ์แทรกกลางแล้วเคอร์เซอร์เด้งไปท้าย
        */
        var pos = code.selectionStart;
        var before = code.value.length;
        code.value = code.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 4);
        var shift = before - code.value.length;
        try { code.setSelectionRange(Math.max(0, pos - shift), Math.max(0, pos - shift)); } catch (e) { /* บาง type ไม่รองรับ */ }
        code.classList.remove('is-invalid');
        paintSample();
      });

      [nameTh, nameEn].forEach(function (el) {
        el.addEventListener('input', function () { el.classList.remove('is-invalid'); });
      });

      function clearMarks() {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      }

      // ── โหมดเพิ่ม ──
      function setAdd(keepValues) {
        form.setAttribute('action', storeUrl);
        method.value = 'POST';
        titleAdd.hidden = false;
        titleEdit.hidden = true;
        code.readOnly = false;
        lockNote.hidden = true;

        if (!keepValues) {
          nameTh.value = '';
          nameEn.value = '';
          code.value = '';
        }
        paintSample();
      }

      // ── โหมดแก้ไข ──
      function setEdit(btn, keepValues) {
        form.setAttribute('action', btn.getAttribute('data-action'));
        method.value = 'PUT';
        titleAdd.hidden = true;
        titleEdit.hidden = false;

        // 🔴 มีเอกสารออกเลขแล้ว = ล็อกโค้ด (เซิร์ฟเวอร์กันซ้ำอีกชั้น)
        var locked = btn.getAttribute('data-locked') === '1';
        code.readOnly = locked;
        lockNote.hidden = !locked;

        if (!keepValues) {
          nameTh.value = btn.getAttribute('data-name-th');
          nameEn.value = btn.getAttribute('data-name-en');
          code.value = btn.getAttribute('data-code');
        }
        // โค้ดที่ล็อกต้องเป็นค่าเดิมเสมอ แม้จะกลับมาจากหน้าที่กรอกผิด
        if (locked) { code.value = btn.getAttribute('data-code'); }
        paintSample();
      }

      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-dg-add]')) {
          clearMarks();
          setAdd(false);
          window.setTimeout(function () { nameTh.focus(); }, 30);
          return;
        }

        var edit = e.target.closest('[data-dg-edit]');
        if (edit) {
          clearMarks();
          setEdit(edit, false);
          window.setTimeout(function () { nameTh.focus(); }, 30);
        }
      });

      /*
        ── ตรวจก่อนส่ง แล้วถามยืนยัน ──
        🔴 ทุก Action ต้องเด้งหน้าต่างถามกลางจอ (กติกาของโปรเจค) — บันทึกหมวดก็เช่นกัน
           เพราะโค้ดที่บันทึกแล้วมีเอกสารออกเลขจะล็อกทันที แก้ทีหลังไม่ได้
        🔴 กันวนซ้ำด้วยธง confirmed: กด "บันทึก" ในหน้าต่างแล้วเรียก submit อีกรอบ
           ถ้าไม่มีธงจะถามไม่รู้จบ
      */
      var confirmed = false;

      form.addEventListener('submit', function (e) {
        var problem = null;

        if (nameTh.value.trim() === '') {
          problem = { el: nameTh, th: 'กรุณากรอกชื่อหมวดภาษาไทย', en: 'Please enter the Thai category name' };
        } else if (nameEn.value.trim() === '') {
          problem = { el: nameEn, th: 'กรุณากรอกชื่อหมวดภาษาอังกฤษ', en: 'Please enter the English category name' };
        } else if (!CODE_RE.test(code.value)) {
          problem = { el: code, th: 'โค้ดต้องเป็น A–Z หรือ 0–9 จำนวน 2–4 ตัว', en: 'Code must be 2–4 characters of A–Z or 0–9' };
        }

        if (problem) {
          e.preventDefault();
          problem.el.classList.add('is-invalid');
          window.BMS.notify({ kind: 'warn', title: isEn() ? problem.en : problem.th, autoClose: false });
          problem.el.focus();
          return;
        }

        if (confirmed) { confirmed = false; return; }

        e.preventDefault();

        var editing = method.value === 'PUT';
        var name = isEn() ? nameEn.value.trim() : nameTh.value.trim();

        window.BMS.confirm({
          kind: 'warn',
          title: editing
            ? (isEn() ? 'Save changes to ' + name + '?' : 'บันทึกการแก้ไขหมวด ' + name + '?')
            : (isEn() ? 'Add ' + name + '?' : 'เพิ่มหมวด ' + name + '?'),
          text: code.readOnly
            ? ''
            : (isEn()
              ? 'Code ' + code.value + ' locks once a document is numbered.'
              : 'โค้ด ' + code.value + ' จะล็อกทันทีที่มีเอกสารออกเลข'),
          ok: isEn() ? 'Save' : 'บันทึก',
          onOk: function () { confirmed = true; form.requestSubmit(); },
        });
      });

      // ── ปิด/เปิดใช้งาน ──
      document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-dg-toggle]');
        if (!btn) { return; }

        var on = btn.getAttribute('data-on') === '1';
        var name = isEn() ? btn.getAttribute('data-name-en') : btn.getAttribute('data-name-th');

        window.BMS.confirm({
          kind: 'warn',
          title: on
            ? (isEn() ? 'Disable ' + name + '?' : 'ปิดใช้งานหมวด ' + name + '?')
            : (isEn() ? 'Enable ' + name + '?' : 'เปิดใช้งานหมวด ' + name + '?'),
          text: on
            ? (isEn() ? 'It can no longer be chosen. Existing documents stay.' : 'เลือกหมวดนี้ไม่ได้อีก · เอกสารเดิมยังอยู่ครบ')
            : '',
          ok: on ? (isEn() ? 'Disable' : 'ปิดใช้งาน') : (isEn() ? 'Enable' : 'เปิดใช้งาน'),
          onOk: function () { document.getElementById('dg-toggle-' + btn.getAttribute('data-dg-toggle')).requestSubmit(); },
        });
      });

      // ── ลบ ──
      document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-dg-delete]');
        if (!btn) { return; }

        var name = isEn() ? btn.getAttribute('data-name-en') : btn.getAttribute('data-name-th');

        window.BMS.confirm({
          kind: 'no',
          title: isEn() ? 'Delete ' + name + '?' : 'ลบหมวด ' + name + '?',
          text: isEn() ? 'This cannot be undone.' : 'ลบแล้วกู้คืนไม่ได้',
          ok: isEn() ? 'Delete' : 'ลบ',
          onOk: function () { document.getElementById('dg-delete-' + btn.getAttribute('data-dg-delete')).requestSubmit(); },
        });
      });

      /*
        ── กลับมาจากการบันทึกที่ไม่ผ่าน — เปิดหน้าต่างเดิมพร้อมค่าที่กรอกค้างไว้ ──
        🔴 ไม่งั้นพิมพ์ผิดตัวเดียวต้องกดเปิดหน้าต่างแล้วกรอกใหม่ทั้งหมด
      */
      var reopen = @json(session('dg_reopen'));
      var badField = @json(session('dg_field'));

      if (reopen) {
        document.addEventListener('DOMContentLoaded', function () {
          var trigger = reopen === 'new'
            ? document.querySelector('[data-dg-add]')
            : document.querySelector('[data-dg-edit="' + reopen + '"]');
          if (!trigger) { return; }

          // กดปุ่มจริงให้ตัวเปิดหน้าต่างกลางทำงาน — ตัวรับการกดของหน้านี้จะล้างค่า จึงต้องใส่คืนหลังกด
          var old = { th: nameTh.value, en: nameEn.value, code: code.value };
          trigger.click();
          nameTh.value = old.th;
          nameEn.value = old.en;
          code.value = old.code;
          if (reopen === 'new') { setAdd(true); } else { setEdit(trigger, true); }

          var bad = badField && form.elements[badField];
          if (bad) { bad.classList.add('is-invalid'); }
        });
      }

      paintSample();
    })();
  </script>
@endpush
