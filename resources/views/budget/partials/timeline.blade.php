{{--
  เส้นทางเอกสาร — กดคอลัมน์ "สถานะทั้งหมด" แล้วเด้งขึ้นมา

  🔴 เจ้าของสั่งรื้อใหม่ 2026-09-09: เปลี่ยนจากแผนผัง 4 ด่านแนวนอน
     เป็น "ตาราง" แบบเดียวกับหน้ารายละเอียดของระบบ Memo เดิม

  แถวแรกเสมอ = ผู้ขอ (คนที่สร้างและกดส่งเอกสาร)
  แถวถัดไป   = ผู้ลงนามตามลำดับ 1, 2, 3, … และ CEO ปิดท้าย (แถว is_final)

  🔴 ข้อมูลของผู้ลงนามอ่านจาก "สำเนาที่เก็บไว้ในเอกสาร" (ชื่อ · ตำแหน่ง · แผนก · ลายเซ็น)
     ไม่ join สดจากบัญชี — คนลาออกหรือย้ายแผนกแล้ว เอกสารเก่าต้องบอกของ ณ ตอนนั้น
     ส่วน "ผู้ขอ" ไม่มีแถวในสายอนุมัติ จึงต้องหาตำแหน่ง/แผนกจากบัญชีให้ (ยิงรวดเดียวข้างล่าง)

  วิธีใช้:  @include('budget.partials.timeline', ['rows' => $rows])
            แล้วในตารางใส่ปุ่ม  data-modal-open="tl-{{ $row->id }}"
--}}
@php
  $A = \App\Models\Budget\Approval::class;
  $I = \App\Models\Budget\Invest::class;

  /*
    🔴 หาข้อมูลผู้ขอของทุกใบในหน้าเดียว "ยิงครั้งเดียว"
       ห้ามยิงทีละแถวในลูป ไม่งั้นหน้าที่มี 20 ใบจะได้ 20 คิวรี

    🐛 บั๊กจริง 2026-09-09: collect($rows) บน Paginator ได้ metadata ของหน้ากระดาษ
       (current_page, data, …) ไม่ใช่แถวเอกสาร — รายชื่อผู้ขอเลยว่าง ตำแหน่งไม่ขึ้น
       เป็นบทเรียนเดิมที่โปรเจคเคยเจอมาแล้ว (DECISIONS ข้อ 26)
  */
  $docList = collect($rows instanceof \Illuminate\Pagination\AbstractPaginator ? $rows->items() : $rows);

  $creatorCodes = $docList->pluck('created_by')->filter()->unique()->values()->all();

  $access = app(\App\Services\Access\AccessService::class);

  $creators = $creatorCodes === []
    ? collect()
    : collect($access->describeCodes($creatorCodes))->keyBy('employee_code');

  /*
    ── ขั้นสุดท้าย: ลงทะเบียนกับ ERP (เจ้าของสั่ง 2026-09-10) ──

    🔴 รายชื่อมาจากสิทธิ์ "ลงทะเบียน" ที่ /access/modules ไม่ใช่รายชื่อที่เก็บในเอกสาร
       เพราะตอนเอกสารยังไม่ถูกลงทะเบียน ยังไม่มีใครลงมือ ต้องบอกว่า "รอใครอยู่"
    🔴 ยิงครั้งเดียวต่อหน้า ห้ามยิงในลูปของเอกสาร
  */
  $B = \App\Models\Budget\Budget::class;
  $registerKey = \App\Services\Budget\BudgetAccess::MODULE.'|'.\App\Services\Budget\BudgetAccess::FN_BUDGETS;

  $registrarCodes = collect($access->functionUserMap()[$registerKey] ?? [])
    ->reject(fn ($c) => $c === \App\Services\Access\AccessService::EVERYONE)
    ->values()->all();

  // คนที่ติ๊กจริงของแต่ละใบ — ต้องเอามารวมกับรายชื่อกลุ่มก่อนถามชื่อทีเดียว
  $actualCodes = $docList->pluck('budget.registered_by')->filter()->unique()->values()->all();

  $registrars = ($registrarCodes === [] && $actualCodes === [])
    ? collect()
    : collect($access->describeCodes(array_values(array_unique([...$registrarCodes, ...$actualCodes]))))
        ->keyBy('employee_code');
@endphp

@foreach ($docList as $doc)
  @php
    $signers = $doc->approvals->where('action', $A::APPROVE)->sortBy('step')->values();
    $me = $creators->get((string) $doc->created_by);

    /*
      แถวของ "ผู้ขอ" ประกอบเองจากหัวเอกสาร — ไม่มีแถวในตารางสายอนุมัติ
      🔴 ไม่มีหมายเหตุ (เจ้าของสั่ง 2026-09-09) — หมายเหตุเป็นของผู้ลงนามเท่านั้น
    */
    $lines = [[
      'role' => ['key' => 'budget.requester', 'th' => 'ผู้ขอ'],
      'name' => $doc->created_by_name ?: $doc->created_by,
      'nameEn' => $doc->creatorNameEn(),
      // แผนก/ตำแหน่งของ "ตัวคน" — ไม่ใช่แผนกที่ขอตั้งงบ
      'dept' => $me['department'] ?? ($doc->dept_name ?: $doc->dept_code),
      'position' => $me['position'] ?? null,
      /*
        🔴 ผู้ขอไม่ได้ลงนาม สถานะของเขาจึงบอกแค่ "ส่งแล้ว / ยังไม่ได้ส่ง" (เจ้าของสั่ง 2026-09-10)
           ส่วนผู้ลงนามใช้ชุดคำเดียวกับคอลัมน์สถานะในตาราง (รออนุมัติ · อนุมัติแล้ว · ไม่อนุมัติ)
      */
      'step' => null,
      'st' => $doc->approval_status === $I::DRAFT
        ? ['cls' => 'st-draft', 'th' => 'ยังไม่ได้ส่ง', 'en' => 'Not sent']
        : ['cls' => 'st-approved', 'th' => 'ส่งแล้ว', 'en' => 'Sent'],
      // 🔴 ผู้ขอไม่ลงนามและไม่มีหมายเหตุ (เจ้าของสั่ง 2026-09-10)
      'sign' => null,
      'when' => $doc->submitted_at?->format('d/m/Y H:i'),
      'note' => null,
      'noteTone' => null,
      'blank' => true,
    ]];

    foreach ($signers as $step) {
      $lines[] = [
        // CEO บอกให้ชัดว่าเป็นคนปิดท้าย จะได้รู้ว่าทำไมอยู่ท้ายสุดเสมอ
        'role' => $step->is_final
          ? ['key' => 'budget.tl.finalSigner', 'th' => 'ผู้ลงนามปิดท้าย']
          : ['key' => 'budget.tl.signer', 'th' => 'ผู้ลงนาม'],
        'name' => $step->employee_name ?: $step->employee_code,
        'nameEn' => $step->employee_name_en ?: ($step->employee_name ?: $step->employee_code),
        'dept' => $step->department,
        'position' => $step->position,
        // 🔴 ผลของขั้นคิดที่ Approval::outcome() ที่เดียว (ดู budget.partials.step-status)
        'step' => $step,
        'st' => null,
        'sign' => $step->signature,
        'when' => $step->acted_at?->format('d/m/Y H:i'),
        // หมายเหตุของผู้ลงนาม — ใช้ช่องเดียวกันทั้งตอนอนุมัติและตอนไม่อนุมัติ
        'note' => $step->comment,
        /*
          🔴 ระบายพื้นช่องหมายเหตุตามผลของคนนั้น (เจ้าของสั่ง 2026-09-10)
             ระบายเฉพาะแถวที่ตัดสินไปแล้วและมีข้อความ — แถวที่ยังรอไม่ต้องมีสี
        */
        'noteTone' => trim((string) $step->comment) === '' ? null : match ($step->status) {
          $A::DONE => 'ok',
          $A::REJECTED => 'no',
          default => null,
        },
        'blank' => false,
      ];
    }

    /*
      ── ขั้นสุดท้าย: ลงทะเบียนกับ ERP ──
      🔴 สถานะเดินตามการติ๊กในหน้า "ลงทะเบียน" ทันที (เจ้าของสั่ง 2026-09-10)
    */
    $budget = $doc->budget;

    // ติ๊กแล้ว = โชว์คนที่ติ๊กจริง · ยังไม่ติ๊ก = โชว์คนที่ถือสิทธิ์ (กำลังรอเขาอยู่)
    $who = $budget?->registered_by
      ? array_filter([$registrars->get((string) $budget->registered_by)])
      : array_values(array_filter(array_map(fn ($c) => $registrars->get($c), $registrarCodes)));

    $regState = match (true) {
      $budget?->budget_status === $B::REGISTERED
        => ['cls' => 'st-approved', 'th' => 'ลงทะเบียนแล้ว', 'en' => 'Registered'],
      // เอกสารถูกตีกลับ = ไม่มีวันไปถึงบัญชี
      $doc->approval_status === $I::REJECTED
        => ['cls' => 'st-none', 'th' => 'ไม่ได้ดำเนินการ', 'en' => 'No action'],
      default
        => ['cls' => 'st-pending', 'th' => 'รอลงทะเบียน', 'en' => 'Awaiting registration'],
    };

    $lines[] = [
      'role' => ['key' => 'budget.tl.registrar', 'th' => 'ผู้ลงทะเบียน'],
      'name' => $who ? implode(' · ', array_column($who, 'name_th')) : '—',
      'nameEn' => $who ? implode(' · ', array_column($who, 'name_en')) : '—',
      'dept' => $who ? implode(' · ', array_filter(array_column($who, 'department'))) : null,
      'position' => $who ? implode(' · ', array_filter(array_column($who, 'position'))) : null,
      'step' => null,
      'st' => $regState,
      // 🔴 ไม่มีลายเซ็นและหมายเหตุ — เป็นการติ๊กยืนยัน ไม่ใช่การลงนาม
      'sign' => null,
      'when' => $budget?->registered_at?->format('d/m/Y H:i'),
      'note' => null,
      'noteTone' => null,
      'blank' => true,
    ];

    /*
      ── แยกเป็นตารางตามบทบาท (เจ้าของสั่ง 2026-09-16) ──
      🔴 ของเดิมยัดทุกบทบาทไว้ในตารางเดียว ผู้ใช้แยกไม่ออกว่าใครทำหน้าที่อะไร
         ตอนนี้แยกเป็นตารางละบทบาท แล้วตัดคอลัมน์ "บทบาท" ทิ้ง เพราะหัวตารางบอกอยู่แล้ว

      🔴 ผู้รับทราบเพิ่งมีในสายเอกสารตั้งแต่ 2026-09-16 (dropdown Approval/Notice)
         ของเดิมกรองเฉพาะ action = approve ผู้รับทราบจึงไม่เคยโผล่ในหน้าต่างนี้เลย
    */
    $acks = $doc->approvals->where('action', $A::ACK)->sortBy('step')->values();

    $ackLines = $acks->map(fn ($step) => [
      // บทบาทคงที่ทั้งตาราง แต่ยังใส่รายแถวเพื่อให้ใช้โครงเดียวกับตารางหลักได้
      'role' => ['key' => 'budget.tl.ackRole', 'th' => 'แจ้งให้ทราบ'],
      'name' => $step->employee_name ?: $step->employee_code,
      'nameEn' => $step->employee_name_en ?: ($step->employee_name ?: $step->employee_code),
      'dept' => $step->department,
      'position' => $step->position,
      'step' => $step,
      'st' => null,
      // 🔴 ผู้รับทราบไม่ลงนามและไม่มีหมายเหตุ — ช่องพวกนี้ระบายเทาเหมือนแถวผู้ขอ
      'sign' => null,
      'when' => $step->acted_at?->format('d/m/Y H:i'),
      'note' => null,
      'noteTone' => null,
      'blank' => true,
    ])->all();

    /*
      🔴 ตารางหลักกลับไปเป็นแบบเดิมทั้งก้อน (เจ้าของสั่งแก้ 2026-09-16 รอบ 2)
         ผู้ขอ → ผู้ลงนาม → ผู้ลงนามปิดท้าย → ผู้ลงทะเบียน อยู่ตารางเดียวเรียง 1,2,3,… เหมือนเดิม
         มีคอลัมน์ "บทบาท" ตามเดิม · **แยกออกมาเฉพาะผู้รับทราบ** ไปไว้ตารางล่าง
    */
    $groups = [
      ['key' => 'budget.timeline.route', 'th' => 'เส้นทางเอกสาร', 'lines' => $lines, 'role' => true],
      ['key' => 'budget.tl.ackGroup', 'th' => 'ผู้รับทราบ', 'lines' => $ackLines, 'role' => true],
    ];
  @endphp

  <div class="modal-wrap" data-modal="tl-{{ $doc->id }}" hidden role="dialog" aria-modal="true">
    <div class="modal is-route">
      <div class="modal-head">
        <span class="modal-title">
          <span data-i18n="budget.timeline">สถานะทั้งหมด</span> ·
          @include('budget.partials.doc-no', ['no' => $doc->doc_no])
        </span>
        @include('access.partials.modal-x')
      </div>

      <div class="modal-body">
        {{--
          แยกตารางตามบทบาท (เจ้าของสั่ง 2026-09-16)
          🔴 กลุ่มที่ไม่มีคนเลย ไม่ต้องวาดตารางเปล่าทิ้งไว้ — เอกสารที่ไม่ได้เลือกผู้รับทราบ
             จะได้ไม่มีหัวข้อโล่งๆ ให้สงสัยว่าข้อมูลหาย
        --}}
        @foreach ($groups as $group)
          @continue (! $group['lines'])

          <p class="route-group" data-i18n="{{ $group['key'] }}">{{ $group['th'] }}</p>

          {{--
            🔴 ตารางในหน้าต่างนี้ "ไม่มีกรอบเลื่อนของตัวเอง" (เจ้าของสั่ง 2026-09-16)
               ใช้ .modal-body เป็นตัวเลื่อนตัวเดียวทั้งหน้าต่าง เพราะ 2 ตารางต้องเลื่อน
               แนวนอนไปพร้อมกัน — ถ้าแยก .tbl-wrap คนละอัน เลื่อนตารางบนแล้วตารางล่าง
               ค้างที่เดิม คอลัมน์จะไม่ตรงกันทันที
               (หน้าเว็บยังไม่เลื่อนแนวนอน เพราะเลื่อนอยู่ในกรอบหน้าต่าง ไม่ใช่ตัวหน้า)

            🔴 colgroup + table-layout: fixed ล็อกความกว้างทุกคอลัมน์
               ทั้ง 2 ตารางจึงกว้างเท่ากันเป๊ะ ไม่ใช่ต่างคนต่างยืดตามข้อความของตัวเอง
          --}}
          <table class="tbl route-tbl">
            <colgroup>
              <col class="c-seq">
              @if ($group['role'])<col class="c-role">@endif
              <col class="c-name">
              <col class="c-dept">
              <col class="c-pos">
              <col class="c-state">
              <col class="c-sign">
              <col class="c-when">
              <col class="c-note">
            </colgroup>
            <thead>
              <tr>
                <th class="col-seq" data-i18n="tbl.seq">ลำดับ</th>
                {{-- คอลัมน์บทบาทมีเฉพาะตารางหลักที่รวมหลายบทบาท · ตารางผู้รับทราบไม่ต้อง หัวข้อบอกแล้ว --}}
                @if ($group['role'])<th data-i18n="budget.tl.role">บทบาท</th>@endif
                <th class="txt-left" data-i18n="budget.tl.person">ชื่อ-สกุล</th>
                <th data-i18n="budget.dept">แผนก</th>
                <th data-i18n="budget.tl.position">ตำแหน่ง</th>
                <th class="col-no" data-i18n="budget.status">สถานะ</th>
                <th data-i18n="budget.tl.signature">ลายเซ็น</th>
                <th class="col-no" data-i18n="budget.tl.actedAt">วันที่ดำเนินการ</th>
                <th class="txt-left" data-i18n="budget.tl.note">หมายเหตุ</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($group['lines'] as $line)
                <tr>
                  <td class="col-seq">{{ $loop->iteration }}</td>
                  @if ($group['role'])<td><span data-i18n="{{ $line['role']['key'] }}">{{ $line['role']['th'] }}</span></td>@endif
                  <td class="txt-left"><span data-loc-th="{{ $line['name'] }}" data-loc-en="{{ $line['nameEn'] }}">{{ $line['name'] }}</span></td>
                  <td>{{ $line['dept'] ?: '—' }}</td>
                  <td>{{ $line['position'] ?: '—' }}</td>
                  {{--
                    🔴 ผลของขั้นใช้ชิ้นส่วนกลางตัวเดียวกับสำเนาเรียนในเอกสาร
                       คำและสีจึงตรงกันเสมอ และระบายพื้นเต็มช่องให้เองอยู่แล้ว
                  --}}
                  <td>
                    @if ($line['step'])
                      @include('budget.partials.step-status', [
                        'step' => $line['step'],
                        'docStatus' => $doc->approval_status,
                      ])
                    @else
                      {{-- ผู้ขอ · ผู้ลงทะเบียน — ใช้คลาส .st ชุดเดียวกัน สีและพื้นเต็มช่องจึงเหมือนกัน --}}
                      <span class="st {{ $line['st']['cls'] }}"
                            data-loc-th="{{ $line['st']['th'] }}"
                            data-loc-en="{{ $line['st']['en'] }}">{{ $line['st']['th'] }}</span>
                    @endif
                  </td>
                  {{--
                    🔴 ช่องที่ "ไม่มีข้อมูลให้แสดง" ระบายเทาอ่อนเต็มช่อง (เจ้าของสั่ง 2026-09-10)
                       ต่างจากช่องที่ "ยังไม่มีข้อมูล" ซึ่งขึ้นขีดกลาง — คนละความหมายกัน
                       ผู้ขอไม่ลงนามและไม่มีหมายเหตุ จึงเป็นเทาทั้ง 2 ช่อง
                  --}}
                  <td @class(['is-na' => $line['blank']])>
                    @if ($line['blank'])
                    @elseif ($line['sign'])
                      {{-- ลายเซ็นที่ประทับลงเอกสารแล้ว — กดขยายดูได้เหมือนรูปอื่นในระบบ --}}
                      <button type="button" class="route-sign" data-zoomable
                              data-zoom-src="{{ $line['sign'] }}"
                              data-zoom-alt="{{ $line['name'] }}"
                              aria-label="{{ $line['name'] }}">
                        <img src="{{ $line['sign'] }}" alt="">
                      </button>
                    @else
                      <span class="soft">—</span>
                    @endif
                  </td>
                  <td class="col-no">{{ $line['when'] ?: '—' }}</td>
                  {{--
                    🔴 ห้ามเขียน class= คู่กับ @class บน tag เดียวกัน
                       HTML เอา attribute ตัวแรกตัวเดียว ที่เหลือถูกทิ้งเงียบๆ
                       (บั๊กจริง: ช่องหมายเหตุของผู้ขอไม่เคยเป็นสีเทาเลย)
                    ข้อ 6 — หมายเหตุของผู้ลงนามระบายตามผล: เขียว=อนุมัติ · แดง=ไม่อนุมัติ
                  --}}
                  <td @class(['txt-left', 'is-na' => $line['blank'], 'note-ok' => $line['noteTone'] === 'ok', 'note-no' => $line['noteTone'] === 'no'])>
                    @if ($line['blank'])
                    @elseif ($line['note'])
                      <span class="route-note">{{ $line['note'] }}</span>
                    @else
                      <span class="soft">—</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endforeach
      </div>

      <div class="modal-foot">
        <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.close">ปิด</button>
      </div>
    </div>
  </div>
@endforeach

<style>
  /*
    หน้าต่างเส้นทางเอกสาร — กว้างพอให้ทุกคอลัมน์อยู่ครบโดยไม่ต้องเลื่อน

    🔴 ล็อกความสูงตายตัว (เจ้าของสั่ง 2026-09-16)
       ของเดิมใช้ max-height หน้าต่างจึง "ยืดตามจำนวนแถว" พอเอกสารมีผู้ลงนามหลายคน
       แถวท้าย (ผู้ลงทะเบียน) กับตาราง "ผู้รับทราบ" เลยตกไปอยู่ใต้ขอบจอแบบเงียบๆ
       ผู้ใช้เข้าใจว่าข้อมูลหาย ทั้งที่แค่ต้องเลื่อนลง
       ล็อกความสูงแล้วกรอบเลื่อนมีอยู่เสมอ ตำแหน่งเท่ากันทุกใบ และแถบเลื่อนโผล่ให้เห็น
  */
  .modal.is-route {
    width: min(1180px, 100%);
    height: min(84vh, 760px);
    height: min(84dvh, 760px);
  }

  /*
    🔴 เนื้อหาเกาะขอบบน ไม่ยืดเต็มความสูง
       .modal-body เป็น grid ถ้าปล่อยตามค่าเริ่มต้น (stretch) เอกสารที่มีไม่กี่แถว
       ตารางจะถูกดึงยืดจนแถวสูงผิดปกติ
  */
  .modal.is-route .modal-body {
    align-content: start;
    /*
      🔴 แถบเลื่อนต้อง "เห็นได้ตลอด" ไม่ใช่โผล่ตอนเลื่อนแล้วหายไป
         เพราะอาการที่เจ้าของเจอคือไม่รู้ว่ายังมีข้อมูลข้างล่างอีก
      scrollbar-gutter: stable = จองที่ไว้ให้แถบเลื่อนเสมอ ตารางจึงไม่ขยับซ้าย-ขวา
      ตอนเนื้อหาสั้น/ยาวสลับกัน
    */
    scrollbar-gutter: stable;
    scrollbar-width: thin;
    scrollbar-color: var(--navy-800) var(--surface-2);
  }

  /* Chrome/Edge ไม่รองรับ scrollbar-color ต้องวาดเอง */
  .modal.is-route .modal-body::-webkit-scrollbar { width: 12px; height: 12px; }

  .modal.is-route .modal-body::-webkit-scrollbar-track {
    background: var(--surface-2);
    border-radius: 8px;
  }

  .modal.is-route .modal-body::-webkit-scrollbar-thumb {
    background: var(--navy-800);
    border: 3px solid var(--surface-2);
    border-radius: 8px;
  }

  .modal.is-route .modal-body::-webkit-scrollbar-thumb:hover { background: var(--navy-900); }

  /*
    🔴 2 ตารางต้องกว้างเท่ากันทุกคอลัมน์ (เจ้าของสั่ง 2026-09-16)
       table-layout: fixed ทำให้ความกว้างมาจาก colgroup อย่างเดียว
       ไม่ใช่ "ยืดตามข้อความที่ยาวที่สุดในตารางนั้น" ซึ่งทำให้ 2 ตารางเหลื่อมกัน
       min-width กันไม่ให้คอลัมน์ถูกบีบจนอ่านไม่ออกตอนจอแคบ (เลื่อนแนวนอนใน modal-body แทน)
  */
  .route-tbl { table-layout: fixed; min-width: 1096px; }

  .route-tbl col.c-seq   { width: 52px; }
  .route-tbl col.c-role  { width: 118px; }
  .route-tbl col.c-name  { width: 178px; }
  .route-tbl col.c-dept  { width: 124px; }
  .route-tbl col.c-pos   { width: 124px; }
  .route-tbl col.c-state { width: 112px; }
  .route-tbl col.c-sign  { width: 100px; }
  .route-tbl col.c-when  { width: 118px; }
  .route-tbl col.c-note  { width: 170px; }

  /*
    🔴 ทุกแถวสูงเท่ากัน — แถวที่มีลายเซ็น (สูง 34px) เคยสูงกว่าแถวที่ไม่มีอย่างเห็นได้ชัด
       พอ 2 ตารางวางต่อกันเลยดูเหมือนคนละขนาด
       ใช้ height กับ td = "ความสูงขั้นต่ำ" หมายเหตุยาวๆ ยังดันแถวให้สูงขึ้นได้ตามปกติ
  */
  .route-tbl tbody td { height: 52px; }

  /* ชื่อ/แผนก/ตำแหน่งยาวเกินช่อง ตัดด้วย ... ไม่ให้ดันความกว้างที่ล็อกไว้ */
  .route-tbl tbody td > span[data-loc-th] { display: block; }

  /*
    หัวข้อกลุ่มบทบาท (เจ้าของสั่งแยกตาราง 2026-09-16)
    เว้นระยะเหนือหัวข้อให้เห็นว่าเป็นคนละก้อน แต่ก้อนแรกไม่ต้องเว้น
  */
  .route-group {
    margin: 22px 0 8px;
    color: var(--navy-900);
    font-size: var(--fs-md);
    font-weight: 700;
  }

  .route-group:first-child { margin-top: 0; }

  /* เส้นบางๆ ใต้หัวข้อ ช่วยให้สายตาแยกก้อนได้โดยไม่ต้องตีกรอบให้รก */
  .route-group::after {
    content: '';
    display: block;
    width: 34px;
    height: 2px;
    margin-top: 5px;
    background: var(--accent);
    border-radius: 2px;
  }

  /*
    🔴 หัวตารางเป็นสีน้ำเงิน (เจ้าของสั่ง 2026-09-09)
       ใช้ token ของธีมกลาง ไม่เขียนค่าสีดิบ (กฎโปรเจค)
  */
  .route-tbl thead th {
    background: var(--navy-800);
    color: #fff;
    border-bottom-color: var(--navy-800);
  }

  /*
    ช่องที่ไม่มีข้อมูลให้แสดง — เทาอ่อนเต็มช่อง
    🔴 บอกคนละเรื่องกับขีดกลาง: เทา = "ไม่มีให้แสดง" · ขีดกลาง = "ยังไม่มี"
  */
  .route-tbl td.is-na { background: var(--surface-4); }

  /*
    🔴 หมายเหตุระบายตามผล (เจ้าของสั่ง 2026-09-10) — เต็มช่อง อ่านผลได้จากสีทันที
       ใช้ token คู่เดียวกับป้ายสถานะทั้งระบบ จะได้ไม่มีเขียว/แดงคนละเฉด
  */
  .route-tbl td.note-ok { background: var(--ok-soft); }
  .route-tbl td.note-no { background: var(--danger-soft); }

  .route-tbl td.note-ok .route-note { color: var(--ok); }
  .route-tbl td.note-no .route-note { color: var(--danger); }

  /* ลายเซ็นในตาราง — เล็กพอให้แถวไม่สูง แต่ยังกดขยายดูได้ */
  .route-sign {
    display: inline-grid; place-items: center;
    width: 88px; height: 34px; padding: 0;
    border: 0; background: transparent; cursor: zoom-in;
  }

  .route-sign img { max-width: 100%; max-height: 34px; object-fit: contain; }

  /* หมายเหตุยาวได้ แต่ห้ามดันตารางจนล้น */
  .route-note {
    display: block; max-width: 100%;
    color: var(--ink-soft); font-size: var(--fs-xs); line-height: 1.5;
    overflow-wrap: anywhere;
  }
</style>
