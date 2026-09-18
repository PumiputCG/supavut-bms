{{--
  ตัวเอกสาร "งบประมาณที่อนุมัติแล้ว" (รูปแบบกระดาษ)

  🔴 ใช้ร่วมกัน 2 ที่ — หน้าจอปกติ (list/show) และหน้าพิมพ์/ดาวน์โหลด PDF (list/print)
     แก้ที่นี่ที่เดียว ทั้ง 2 หน้าเปลี่ยนตามพร้อมกัน (เจ้าของสั่ง 2026-09-10)

  🔴 เจ้าของสั่ง 2026-09-10: หน้าตาต้องเหมือน /budget/doc/{id} ทุกอย่าง
     ต่างกันแค่เลขที่มุมขวาบนเป็น BGT-… (ไม่มีป้ายสถานะ)
     เนื้อในจึงใช้ชิ้นส่วนกลางตัวเดียวกัน (budget.partials.doc-body)

  ต้องมีตัวแปรพร้อมก่อน include: $budget · $invest · $signers · $signerPhotos
--}}
<section class="paper">
  {{--
    🔴 มุมขวาบนมีแค่เลขที่งบ ไม่ต้องมีป้ายสถานะ (เจ้าของสั่ง 2026-09-10)
       "รอลงทะเบียน / ลงทะเบียนแล้ว" เป็นสถานะการทำงานภายใน ไม่ใช่ส่วนหนึ่งของตัวเอกสาร
       ดูได้ที่ช่องติ๊กเหนือกระดาษและคอลัมน์ในตารางอยู่แล้ว
  --}}
  {{-- 🔴 QR ของใบงบใช้กุญแจของใบ INV ต้นทาง — เรื่องเดียวกัน สแกนเข้าหน้าเดียวกัน (DECISIONS 50.15.2) --}}
  @include('budget.partials.paper-head', ['docNo' => $budget->doc_no, 'trackUrl' => $budget->trackUrl()])

  @if ($invest)
    @include('budget.partials.doc-body', [
      'invest' => $invest,
      'signers' => $signers,
      // 🔴 หน้างบใช้ "วงเงินที่อนุมัติ" ของตัวงบเอง ไม่ใช่ยอดที่เสนอมา
      'amount' => [
        'key' => 'budget.approvedAmount',
        'th' => 'วงเงินที่อนุมัติ',
        'value' => $budget->approved_amount,
      ],
      'tail' => 'budget.list.paper-tail',
    ])
  @else
    {{-- งบที่ไม่มีเอกสารต้นทาง (ข้อมูลเก่า) — แสดงเท่าที่ตัวงบมี --}}
    <dl class="paper-rows">
      <div class="paper-row">
        <dt data-i18n="budget.year">ปีงบประมาณ</dt>
        <dd>{{ $budget->fiscal_year }}</dd>
      </div>
      <div class="paper-row">
        <dt data-i18n="budget.dept">แผนก</dt>
        <dd>{{ $budget->dept_name ?: $budget->dept_code }}</dd>
      </div>
      <div class="paper-row">
        <dt data-i18n="budget.itemTitle">ชื่องบ</dt>
        <dd class="strong">{{ $budget->title }}</dd>
      </div>
      <div class="paper-row">
        <dt data-i18n="budget.approvedAmount">วงเงินที่อนุมัติ</dt>
        <dd>
          <span class="paper-amount">
            {{ number_format($budget->approved_amount, 2) }}
            <small data-i18n="budget.baht">บาท</small>
          </span>
        </dd>
      </div>
      @include('budget.list.paper-tail')
    </dl>
  @endif
</section>
