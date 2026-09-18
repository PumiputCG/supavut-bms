@extends('layouts.app')

@section('title', 'ของบประมาณ / Budget request')

{{--
  หน้าคั่น "เลือกหมวดงบประมาณ" (เจ้าของสั่ง 2026-09-17)

  เข้าเมนู งบประมาณ > ของบประมาณ แล้วเจอหน้านี้ก่อนเสมอ — มีแต่การ์ดหมวด
  กดการ์ด -> หน้าตารางของหมวดนั้น (?group=) · ปุ่ม "เสนอรายการใหม่" อยู่ในหน้าตาราง

  🔴 เจ้าของสั่งให้กระชับ — การ์ดมีแค่ ลำดับ · ชื่อหมวด · รูปแบบเลขที่เอกสาร 2 ชนิด
  🔴 ปีและเลขวิ่งเขียนเป็น xx/xxx (เจ้าของสั่ง 2026-09-17) เพราะการ์ดบอก "รูปแบบ" ไม่ใช่ "เลขที่จะได้"
     เลขจริงออกตอนกดส่งเท่านั้น — เขียน 000001 จะอ่านเหมือนเป็นเลขของใบถัดไป (DocNumber::pattern)
--}}

@push('page-style')
  <style>
    :root { --page-max: 960px; }

    .pick-lead { color: var(--ink-soft); font-size: var(--fs-sm); }

    .pick-grid {
      display: grid; gap: 12px;
      grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
    }

    /* การ์ดละหมวด — กดได้ทั้งใบ */
    .pick-card {
      display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 12px; align-items: center;
      padding: 14px 16px;
      border: 1px solid var(--line); border-radius: var(--radius);
      background: var(--surface); color: inherit; text-decoration: none;
      transition: border-color .15s var(--ease), box-shadow .15s var(--ease);
    }
    .pick-card:hover { border-color: var(--accent); box-shadow: var(--shadow-sm); }
    .pick-card:focus-visible { outline-offset: 3px; }

    .pick-no {
      display: grid; place-items: center; width: 32px; height: 32px;
      border-radius: var(--radius-sm); background: var(--navy-800); color: var(--surface);
      font-weight: 700; font-variant-numeric: tabular-nums;
    }

    .pick-body { display: grid; gap: 4px; min-width: 0; }
    .pick-title { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .pick-name {
      min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
      color: var(--navy-900); font-size: var(--fs-md); font-weight: 700;
    }
    /*
      รูปแบบเลขที่ — คนละบรรทัด (เจ้าของสั่ง 2026-09-17) INV บรรทัดบน · BGT บรรทัดล่าง
      🔴 ตัวเลขใช้ความกว้างเท่ากันทุกตัว (tabular-nums) ทั้ง 2 บรรทัดจึงตรงหลักกันพอดี
         และเว้นระยะตัวอักษรให้อ่าน INV/BGT · โค้ดหมวด · ปีงบ ออกจากกันได้ในแวบเดียว
    */
    .pick-samples {
      display: grid; gap: 3px;
      color: var(--ink-soft); font-size: var(--fs-sm); font-weight: 600;
      letter-spacing: .04em; font-variant-numeric: tabular-nums;
    }
    /* เลขวิ่งยังไม่ออก จึงจางกว่าส่วนที่แน่นอนแล้ว — ตัวเล็กเพื่อไม่ให้อ่านปนกับโค้ดหมวดตัวใหญ่ */
    .pick-run { color: var(--muted); font-weight: 500; letter-spacing: .12em; }

    .pick-go { color: var(--line-dark); }
    .pick-card:hover .pick-go { color: var(--accent); }

    .pick-empty {
      padding: 22px 16px; border: 1px dashed var(--line); border-radius: var(--radius);
      color: var(--muted); font-size: var(--fs-sm); text-align: center; line-height: 1.8;
    }

    /* ทางเข้าเอกสารทั้งหมด — ลิงก์เงียบมุมขวาบน ไม่แย่งความสนใจจากการ์ด */
    .pick-all {
      margin-left: auto; display: inline-flex; align-items: center; gap: 6px;
      color: var(--ink-soft); font-size: var(--fs-sm); font-weight: 600; text-decoration: none;
    }
    .pick-all:hover { color: var(--navy-800); }
  </style>
@endpush

@section('content')
  <section class="card">
    <div class="card-head">
      <h3 data-i18n="fn.budget.invest">ของบประมาณ</h3>

      {{--
        🔴 ทางเข้าเอกสารทั้งหมด อยู่มุมขวาบนบรรทัดเดียวกับหัวข้อ (เจ้าของสั่ง 2026-09-17)
           ต้องมีเสมอเมื่อมีเอกสาร ไม่งั้นเอกสารของหมวดที่ถูกปิดไปแล้วจะเปิดดูจากหน้านี้ไม่ได้อีก
      --}}
      @if ($totalDocs > 0)
        <a class="pick-all" href="{{ route('budget.invest.index', ['group' => \App\Services\Budget\DocGroupTabs::ALL]) }}">
          <span data-i18n="budget.pick.all">ดูเอกสารทั้งหมด</span>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"></path></svg>
        </a>
      @endif
    </div>

    <div class="card-body" style="display:grid;gap:14px">
      <p class="pick-lead" data-i18n="budget.pick.title">เลือกหมวดงบประมาณ</p>

      @if ($pickGroups->isEmpty())
        <p class="pick-empty">
          <span data-i18n="budget.pick.none">ยังไม่มีหมวดงบประมาณที่เปิดใช้งาน</span><br>
          @if ($canManageGroups)
            <a href="{{ route('budget.docgroup.index') }}" data-i18n="budget.pick.goSetup">ไปที่ตั้งค่าหมายเลขเอกสาร</a>
          @else
            <span data-i18n="budget.pick.askAdmin">กรุณาติดต่อผู้ดูแลระบบ</span>
          @endif
        </p>
      @else
        <div class="pick-grid">
          @foreach ($pickGroups as $group)
            <a class="pick-card" href="{{ route('budget.invest.index', ['group' => $group->id]) }}">
              <span class="pick-no">{{ $loop->iteration }}</span>

              <span class="pick-body">
                {{-- 🔴 ไม่มีป้ายโค้ดในการ์ดแล้ว (เจ้าของสั่ง 2026-09-17) — โค้ดอยู่ในเลขที่ตัวอย่างข้างล่างแล้ว --}}
                <span class="pick-title">
                  <span class="pick-name" title="{{ $group->name_th }}"
                        data-loc-title-th="{{ $group->name_th }}" data-loc-title-en="{{ $group->name_en }}"
                        data-loc-th="{{ $group->name_th }}" data-loc-en="{{ $group->name_en }}">{{ $group->name_th }}</span>
                </span>
                <span class="pick-samples">
                  {{-- ตัวแทน (ปี xx · เลขวิ่ง xxx) จางกว่าส่วนที่แน่นอนแล้ว — e() ก่อนเสมอ แล้วค่อยแต่งเฉพาะตัว x --}}
                  @foreach ([$group->sample_inv, $group->sample_bgt] as $no)
                    <span>{!! preg_replace('~x+~', '<span class="pick-run">$0</span>', e($no)) !!}</span>
                  @endforeach
                </span>
              </span>

              <svg class="pick-go" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"></path></svg>
            </a>
          @endforeach
        </div>
      @endif

    </div>
  </section>
@endsection
