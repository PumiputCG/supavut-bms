{{--
  การ์ดกราฟ "งบประมาณตามแผนก" (เจ้าของสั่ง 2026-09-04)

  🔴 วาดกราฟด้วย SVG เอง ไม่ใช้ไลบรารีจากอินเทอร์เน็ต
     เซิร์ฟจริงอยู่ในวงแลน (192.168.7.12) ถ้าไปดึง CDN แล้วเน็ตไม่ถึง กราฟจะหายทั้งหน้า

  🔴 กรอบกราฟในการ์ดสูงคงที่ — เปลี่ยนชนิดกราฟแล้วการ์ดต้องไม่ยืด/ไม่หด

  ชนิดกราฟที่มี: แท่งแนวตั้ง (ค่าเริ่มต้น) · แท่งแนวนอน
  (โดนัท · วงกลม · แถบสัดส่วน เจ้าของสั่งเอาออกแล้ว)

  ตัวกรองแผนกไม่ได้อยู่ในการ์ดนี้ — อยู่ที่แถบตัวกรองของหน้า (core/partials/dash-filter)

  🔴 ชิ้นส่วนนี้วาดได้ "หลายชุดในหน้าเดียว" — ชุดละบริษัท (เจ้าของสั่ง 2026-09-11)
     ทุกอย่างจึงต้องหาของในกล่องของตัวเอง (root) ไม่ใช่หาทั้งหน้า
     และชื่อหน้าต่างต้องต่อท้ายด้วยรหัสบริษัท ไม่งั้น 2 ชุดเปิดหน้าต่างเดียวกัน

  ต้องส่งมา
    $scope   รหัสบริษัท เช่น 'si5' — ใช้ตั้งชื่อกล่องและหน้าต่างให้ไม่ชนกัน
    $firm    ['th' => 'สุภาวุฒิ อินดัสทรี', 'en' => 'Supavut Industry']
    $byDept  [['name' => 'IT', 'total' => 450000.0, 'rows' => 3, 'years' => [...]], ...] เรียงมากไปน้อย
    $docs    ['IT' => [ ...รายการงบของแผนก... ], ...] ของบริษัทนี้เท่านั้น
--}}
<div class="dash-col" data-chart-scope="{{ $scope }}">
<section class="card dash-card">
  {{--
    🔴 หัวการ์ดพื้นน้ำเงิน ตัวอักษรขาว (เจ้าของสั่ง 2026-09-11) — ชุดสีเดียวกับหัวตารางของระบบ
       ชื่อบริษัทอยู่บรรทัดบนชิดซ้าย ตัวใหญ่ · ชื่อการ์ดอยู่บรรทัดล่าง
       เพราะสิ่งแรกที่ต้องอ่านออกคือ "การ์ดนี้ของบริษัทไหน" ไม่ใช่ชื่อการ์ดที่ซ้ำกันทั้ง 2 ฝั่ง
  --}}
  <div class="card-head is-firm">
    <div class="firm-head">
      <b class="firm-name" data-loc-th="{{ $firm['th'] }}" data-loc-en="{{ $firm['en'] }}">{{ $firm['th'] }}</b>
      <span class="firm-sub" data-i18n="dash.budgetApproved">งบประมาณที่อนุมัติแล้ว</span>
    </div>

    {{--
      ชนิดกราฟเป็นปุ่มสี่เหลี่ยมเล็กๆ อยู่ซ้ายปุ่ม "ดูทั้งหมด" (เจ้าของสั่ง 2026-09-04)
      🔴 ปุ่มวงกลมกดไม่ได้จนกว่าจะเลือกแผนกที่แถบตัวกรองของหน้า
         เพราะกราฟวงกลมของเราไว้เทียบ "แผนกที่เลือก vs ทุกแผนก" ไม่ได้ไว้ดูรวม
    --}}
    <div class="chart-kind" role="group" aria-label="ชนิดกราฟ" data-i18n-aria="chart.type">
      <button type="button" class="kbtn is-on" data-kind="bar-v"
              title="แท่งแนวตั้ง" data-i18n-title="chart.barV">
        <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
          <rect x="1.5" y="7" width="3" height="7.5" rx="1"></rect>
          <rect x="6.5" y="3.5" width="3" height="11" rx="1"></rect>
          <rect x="11.5" y="9.5" width="3" height="5" rx="1"></rect>
        </svg>
      </button>

      <button type="button" class="kbtn" data-kind="bar-h"
              title="แท่งแนวนอน" data-i18n-title="chart.barH">
        <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
          <rect x="1.5" y="2.5" width="11" height="3" rx="1"></rect>
          <rect x="1.5" y="6.5" width="7" height="3" rx="1"></rect>
          <rect x="1.5" y="10.5" width="13" height="3" rx="1"></rect>
        </svg>
      </button>

      <button type="button" class="kbtn" data-kind="pie" disabled
              title="วงกลม — เลือกแผนกก่อน" data-i18n-title="chart.pieNeedDept">
        <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
          <path d="M8 8 V1.2 A6.8 6.8 0 0 1 14.8 8 Z"></path>
          <path d="M8 1.2 A6.8 6.8 0 1 0 14.8 8 H8 Z" opacity=".38"></path>
        </svg>
      </button>
    </div>
  </div>

  <div class="card-body">
    {{-- 🔴 อ่านเป็นประโยคเดียว "วงเงินที่อนุมัติรวม <ตัวเลข> (บาท)" (เจ้าของสั่ง 2026-09-11) --}}
    <div class="dash-total">
      <span data-i18n="dash.budgetTotalLabel">วงเงินที่อนุมัติรวม</span>
      <b data-chart-total>{{ number_format(array_sum(array_column($byDept, 'total')), 2) }}</b>
      <span data-i18n="common.baht">(บาท)</span>

      {{--
        จำนวนรายการงบ — อยู่ทางขวาของยอดรวม (เจ้าของสั่ง 2026-09-11)
        🔴 เดินตามตัวกรองแผนก/ปี เหมือนยอดเงิน ไม่ใช่ตัวเลขค้างของทั้งระบบ
      --}}
      <span class="dash-count">
        <span data-i18n="dash.countLabel">จำนวน</span>
        <b data-chart-count>{{ number_format(array_sum(array_column($byDept, 'rows'))) }}</b>
        <span data-i18n="dash.budgetRows">รายการ</span>
      </span>
    </div>

    @if (count($byDept))
      {{-- กดที่กราฟเพื่อเปิดหน้าต่างขนาดใหญ่ · กรอบสูงคงที่ กราฟย่อมาพอดีเอง --}}
      <button type="button" class="chart-box" data-chart-open data-modal-open="chart-big-{{ $scope }}"
              title="กดเพื่อดูขนาดใหญ่" data-i18n-title="chart.openBig">
        <span class="chart-draw" data-chart-card></span>
      </button>

      <div class="chart-legend" data-chart-legend></div>
    @else
      <div class="dash-empty">
        <img src="{{ asset('img/nav/mod-budget.png') }}" alt="" width="48" height="48">
        <p data-i18n="budget.list.none">ยังไม่มีงบที่อนุมัติแล้ว</p>
      </div>
    @endif

    {{-- กรองแผนกแล้วไม่เหลือข้อมูล — บอกให้รู้ ไม่ใช่ปล่อยกรอบว่าง --}}
    <p class="chart-none" data-chart-none hidden data-i18n="chart.noneInDept">แผนกนี้ยังไม่มีงบที่อนุมัติ</p>
  </div>
</section>

{{--
  ═══════════ การ์ดที่ 2 — ตารางสรุปรายแผนก ═══════════
  เดิมอยู่ใต้กราฟในหน้าต่างขยาย · เจ้าของสั่งย้ายออกมาเป็นการ์ดของตัวเอง (2026-09-04)
  🔴 สูงเท่าการ์ดกราฟ แล้วเลื่อนดูข้างในเอา · เดินตามตัวกรองแผนกของหน้าเหมือนกัน
--}}
<section class="card dash-card">
  <div class="card-head is-firm">
    <div class="firm-head">
      <b class="firm-name" data-loc-th="{{ $firm['th'] }}" data-loc-en="{{ $firm['en'] }}">{{ $firm['th'] }}</b>
      <span class="firm-sub" data-i18n="chart.byDept">งบประมาณตามแผนก</span>
    </div>

    <span class="soft dept-count" data-dept-count></span>

    {{-- เรียงตามวงเงิน — ค่าเริ่มต้นคือมากไปน้อย (เจ้าของสั่ง 2026-09-04) --}}
    <div class="chart-kind" role="group" aria-label="เรียงลำดับ" data-i18n-aria="sort.by">
      <button type="button" class="kbtn is-on" data-sort="desc"
              title="วงเงินมากไปน้อย" data-i18n-title="sort.desc">
        <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
          <rect x="2" y="2.5" width="8" height="2" rx="1"></rect>
          <rect x="2" y="7" width="5.5" height="2" rx="1"></rect>
          <rect x="2" y="11.5" width="3" height="2" rx="1"></rect>
          <path d="M12.6 2.6 v8.2 l2 -2 v1.4 l-2.6 2.6 l-2.6 -2.6 v-1.4 l2 2 V2.6 Z"></path>
        </svg>
      </button>

      <button type="button" class="kbtn" data-sort="asc"
              title="วงเงินน้อยไปมาก" data-i18n-title="sort.asc">
        <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
          <rect x="2" y="2.5" width="3" height="2" rx="1"></rect>
          <rect x="2" y="7" width="5.5" height="2" rx="1"></rect>
          <rect x="2" y="11.5" width="8" height="2" rx="1"></rect>
          <path d="M12.6 13.4 V5.2 l2 2 V5.8 l-2.6 -2.6 l-2.6 2.6 v1.4 l2 -2 v8.2 Z"></path>
        </svg>
      </button>
    </div>
  </div>

  {{--
    🔴 การ์ดนี้ไม่มีบรรทัดยอดรวมแล้ว (เจ้าของสั่ง 2026-09-11)
       ยอดเดียวกันนี้อยู่บนการ์ดกราฟที่อยู่เหนือขึ้นไปแล้ว เขียนซ้ำอีกใบเปลืองที่เปล่าๆ
       ยอดรวมของชุดที่กำลังดูยังมีให้เห็นที่ท้ายหน้าต่างเวลากดเข้าไปดูรายละเอียด
  --}}
  <div class="card-body">
    {{--
      🔴 กดตรงไหนของตารางก็เปิดหน้าต่างรายละเอียด (เจ้าของสั่ง 2026-09-07)
         ตารางในการ์ดเป็นตัวอ่านอย่างเดียว ไม่กางแถวย่อยเองแล้ว
    --}}
    <div class="dept-scroll is-open" data-modal-open="dept-big-{{ $scope }}" role="button" tabindex="0"
         title="ดูรายละเอียดรายแผนก" data-i18n-title="chart.openDept">
      <table class="tbl chart-tbl">
        <thead>
          <tr>
            <th class="col-seq" data-i18n="tbl.seq">ลำดับ</th>
            {{-- หัวคอลัมน์อยู่กึ่งกลางเหมือนหัวอื่น ส่วนข้อมูลชิดซ้าย (เจ้าของสั่ง 2026-09-07) --}}
            <th data-i18n="budget.dept">แผนก</th>
            <th class="col-no" data-i18n="chart.docCount">จำนวนรายการ</th>
            <th class="col-money" data-i18n="budget.approvedAmount">วงเงินที่อนุมัติ</th>
            <th class="col-no" data-i18n="chart.share">สัดส่วน</th>
          </tr>
        </thead>
        <tbody data-chart-rows>
          @unless (count($byDept))
            {{-- ไม่มีข้อมูลเลย — สคริปต์กราฟหยุดทำงานตั้งแต่ต้น ตารางจึงต้องมีข้อความบอกจากฝั่งเซิร์ฟเวอร์ --}}
            <tr><td colspan="4" class="empty" data-i18n="budget.list.none">ยังไม่มีงบที่อนุมัติแล้ว</td></tr>
          @endunless
        </tbody>
      </table>
    </div>
  </div>
</section>

{{-- ═══════════ หน้าต่างตารางรายแผนก — กดแถวแล้วสลับเป็นเอกสารของแผนกนั้น ═══════════ --}}
<div class="modal-wrap" data-modal="dept-big-{{ $scope }}" hidden role="dialog" aria-modal="true">
  <div class="modal is-chart">
    <div class="modal-head">
      <button type="button" class="modal-back" data-dept-back hidden
              aria-label="กลับไปรายชื่อแผนก" data-i18n-aria="chart.backDept"
              title="กลับไปรายชื่อแผนก" data-i18n-title="chart.backDept">
        <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true">
          <path d="M10 2.5 L4.5 8 L10 13.5" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
      </button>

      <span class="modal-title">
        <span data-i18n="chart.byDept">งบประมาณตามแผนก</span>
        <span class="soft" data-dept-modal-sub></span>
      </span>
      @include('access.partials.modal-x')
    </div>

    <div class="modal-body">
      {{-- หน้าที่ 1 — ทุกแผนก --}}
      <div class="chart-page" data-dept-page="all">
        {{--
          🔴 ตัวกรองของตารางนี้ "กรองที่ข้อมูล" ไม่ใช่ซ่อนแถวใน DOM (เจ้าของแจ้ง 2026-09-11)
             ตารางมีเลขลำดับและยอดรวมท้ายหน้าต่าง — ซ่อนแถวเฉยๆ เลขลำดับจะข้าม (1, 5, 9)
             และยอดท้ายกระดาษไม่ตรงกับที่ตาเห็น · ดู layouts/partials/data-filter
          🔴 คอลัมน์ "ลำดับ" ไม่ให้กรอง — กรองด้วยเลขแถวไม่มีความหมาย
        --}}
        <table class="tbl chart-tbl" data-dept-table>
          <thead>
            <tr>
              <th class="col-seq" data-no-filter data-i18n="tbl.seq">ลำดับ</th>
              <th data-i18n="budget.dept">แผนก</th>
              <th class="col-no" data-i18n="chart.docCount">จำนวนรายการ</th>
              <th class="col-money" data-i18n="budget.approvedAmount">วงเงินที่อนุมัติ</th>
              <th class="col-no" data-i18n="chart.share">สัดส่วน</th>
            </tr>
          </thead>
          <tbody data-dept-rows></tbody>
        </table>
      </div>

      {{-- หน้าที่ 2 — เอกสารของแผนกที่กด --}}
      <div class="chart-page" data-dept-page="one" hidden>
        {{-- ตัวกรองกรองที่ข้อมูลเหมือนกัน — ตารางนี้ merge ช่อง "แผนก" ด้วย rowspan
             ถ้าซ่อนแถวใน DOM แล้วแถวแรกหายไป คอลัมน์ที่เหลือจะเลื่อนผิดช่องทั้งตาราง --}}
        <table class="tbl chart-tbl" data-dept-doc-table>
          <thead>
            {{--
              🔴 คอลัมน์ชุดนี้เจ้าของกำหนดเอง 2026-09-11
                 ลำดับ · Model · Budget No. · ชื่อรายการ · Budget · วันเริ่ม · วันที่สร้าง
              ยังคงคอลัมน์ "แผนก" ที่ merge เป็นก้อนเดียวไว้ เพราะทั้งหน้าเป็นของแผนกเดียว
            --}}
            {{-- 🔴 "แผนก" ไม่ให้กรอง — ทั้งหน้าเป็นของแผนกเดียวอยู่แล้ว กรองไปก็ไม่ได้อะไร --}}
            <tr>
              <th class="col-seq" data-no-filter data-i18n="tbl.seq">ลำดับ</th>
              <th class="col-dept" data-no-filter data-i18n="budget.dept">แผนก</th>
              <th class="col-no" data-i18n="erp.year">ปี</th>
              <th class="col-no" data-i18n="erp.model">Model</th>
              <th class="col-no" data-i18n="erp.budgetNo">Budget No.</th>
              <th data-i18n="erp.comment">ชื่อรายการ</th>
              <th class="col-money" data-i18n="erp.budget">Budget</th>
              <th class="col-no" data-i18n="erp.startDate">วันเริ่ม</th>
              <th class="col-no" data-i18n="erp.createdAt">วันที่สร้าง</th>
            </tr>
          </thead>
          <tbody data-dept-doc-rows></tbody>
        </table>
      </div>
    </div>

    {{-- ยอดรวมอยู่ท้ายกระดาษชิดซ้าย เห็นได้ตลอดแม้เลื่อนตารางลงไปแล้ว --}}
    <div class="modal-foot">
      <span class="spacer"></span>
      <span class="dash-total foot-total">
        <span data-i18n="dash.budgetTotalLabel">วงเงินที่อนุมัติรวม</span>
        <b data-dept-foot-sum>0.00</b>
        <span data-i18n="common.baht">(บาท)</span>
      </span>
      <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.close">ปิด</button>
    </div>
  </div>
</div>

{{-- ═══════════ หน้าต่างกราฟขนาดใหญ่ — ดูละเอียดทุกแผนก ═══════════ --}}
<div class="modal-wrap" data-modal="chart-big-{{ $scope }}" hidden role="dialog" aria-modal="true">
  <div class="modal is-chart">
    <div class="modal-head">
      {{-- ปุ่มย้อนกลับ — โผล่เฉพาะตอนดูรายแผนก (เจ้าของสั่ง 2026-09-07) --}}
      <button type="button" class="modal-back" data-chart-back hidden
              aria-label="กลับไปกราฟรวม" data-i18n-aria="chart.back" title="กลับไปกราฟรวม" data-i18n-title="chart.back">
        <svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true">
          <path d="M10 2.5 L4.5 8 L10 13.5" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
      </button>

      <span class="modal-title">
        <span data-i18n="chart.byDept">งบประมาณตามแผนก</span>
        <span class="soft" data-chart-big-sub></span>
      </span>
      @include('access.partials.modal-x')
    </div>

    {{--
      🔴 สองหน้าจอในหน้าต่างเดียว ไม่ใช่หน้าต่างซ้อนหน้าต่าง (แบบเดียวกับแผงกระดิ่ง)
         หน้า 1 = กราฟรวมทุกแผนก · หน้า 2 = แผนกเดียว (กราฟ + เอกสารของแผนกนั้น)
    --}}
    <div class="modal-body">
      <div class="chart-page" data-chart-page="all">
        <div class="chart-draw is-big" data-chart-big></div>
        <p class="chart-hint" data-i18n="chart.clickDept">กดที่กราฟของแผนกใดก็ได้ เพื่อดูงบที่อนุมัติของแผนกนั้น</p>

      </div>

      <div class="chart-page" data-chart-page="one" hidden>
        <div class="dept-pane">
          {{-- กราฟของแผนกที่กดเข้ามา — ชนิดเดียวกับที่กำลังดูอยู่ --}}
          <div class="chart-draw dept-draw" data-dept-draw></div>

          <div class="dept-docs">
            <div class="dept-docs-scroll">
              <table class="tbl chart-tbl" data-one-doc-table>
                <thead>
                  {{-- คอลัมน์ชุดเดียวกับตารางในหน้าต่างรายแผนก (เจ้าของกำหนด 2026-09-11) --}}
                  <tr>
                    <th class="col-seq" data-no-filter data-i18n="tbl.seq">ลำดับ</th>
                    <th class="col-no" data-i18n="erp.year">ปี</th>
                    <th class="col-no" data-i18n="erp.model">Model</th>
                    <th class="col-no" data-i18n="erp.budgetNo">Budget No.</th>
                    <th class="txt-left" data-i18n="erp.comment">ชื่อรายการ</th>
                    <th class="col-money" data-i18n="erp.budget">Budget</th>
                    <th class="col-no" data-i18n="erp.startDate">วันเริ่ม</th>
                    <th class="col-no" data-i18n="erp.createdAt">วันที่สร้าง</th>
                  </tr>
                </thead>
                <tbody data-dept-docs></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{--
      🔴 ยอดรวมอยู่ท้ายกระดาษชิดซ้าย (เจ้าของสั่ง 2026-09-07)
         เลื่อนดูข้อมูลยาวๆ แล้วยังเห็นยอดรวมอยู่ ไม่ต้องเลื่อนกลับขึ้นไปดู
    --}}
    <div class="modal-foot">
      <span class="spacer"></span>
      <span class="dash-total foot-total">
        <span data-i18n="dash.budgetTotalLabel">วงเงินที่อนุมัติรวม</span>
        <b data-chart-foot-sum>0.00</b>
        <span data-i18n="common.baht">(บาท)</span>
      </span>
      <button type="button" class="btn btn-quiet" data-modal-close data-i18n="common.close">ปิด</button>
    </div>
  </div>
</div>
</div>{{-- /.dash-col --}}

{{-- 🔴 สไตล์กับตัววาดออกครั้งเดียว ถึงจะวาดการ์ดหลายชุดก็ตาม --}}
@once
@push('page-style')
<style>
  /* ── ตารางรายแผนก: กางเอกสารเป็นแถวย่อย (เจ้าของสั่ง 2026-09-07) ── */
  .dept-cell { display: flex; align-items: center; gap: 6px; min-width: 0; }
  .dept-cell .lg { min-width: 0; }
  .dept-cell .lg > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  /*
    กรอบตารางในการ์ด = ปุ่มเปิดหน้าต่าง (เจ้าของสั่ง 2026-09-07)
    กดตรงไหนก็ได้ ไม่ต้องเล็งแถวหรือปุ่มเล็กๆ
  */
  .dept-scroll.is-open { cursor: pointer; }
  .dept-scroll.is-open:hover tbody tr > td { background: var(--surface-2); }
  .dept-scroll.is-open:focus-visible { outline: 2px solid var(--navy-500); outline-offset: -2px; }

  /* แถวแผนกในหน้าต่าง — กดเพื่อดูเอกสารของแผนกนั้น */
  .tbl tr.dept-tr[data-dept-row] { cursor: pointer; }
  .tbl tr.dept-tr[data-dept-row]:hover > td { background: var(--surface-2); }
  .tbl tr.dept-tr[data-dept-row]:focus-visible { outline: 2px solid var(--navy-500); outline-offset: -2px; }

  /* ชื่อรายการยาวเกินช่องให้ตัดด้วย ... ไม่ดันตารางให้กว้าง */
  .doc-t { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  /*
    ยอดรวมท้ายกระดาษ — ชิดซ้าย บรรทัดเดียวกับปุ่มปิด (เจ้าของสั่ง 2026-09-07)
    ใช้ .dash-total ที่มีอยู่แล้ว จะได้หน้าตาเดียวกับยอดในการ์ด แค่ย่อลงนิดให้พอดีท้ายกระดาษ
  */
  .foot-total { margin-right: 14px; }
  .foot-total b { font-size: 17px; }

  /* คอลัมน์แผนกที่ merge แถว — ชื่อยาวตัดด้วย ... ไม่ดันตาราง */
  .chart-tbl .col-dept { width: 190px; vertical-align: middle; }
  .chart-tbl .col-dept .lg { min-width: 0; }
  .chart-tbl .col-dept .lg > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  /* แถวในหน้าต่างไล่ขึ้นทีละแถว */
  [data-dept-doc-rows] tr { animation: rowIn .26s var(--ease) both; }

  /* เส้นคั่นตอนขึ้นปีใหม่ — อ่านเป็นกลุ่มปีได้โดยไม่ต้องตีกรอบ */
  /* 🔴 ช่องแผนกที่ merge หลายแถว ให้ข้อความอยู่บรรทัดแรก (เจ้าของสั่ง 2026-09-11)
     ค่าเริ่มต้นของตารางคือกึ่งกลางแนวตั้ง ผู้ใช้ต้องเลื่อนลงไปกลางตารางถึงจะเห็นชื่อแผนก */
  [data-dept-doc-rows] td.col-dept { vertical-align: top; }

  [data-dept-doc-rows] tr.yr-split > td,
  [data-dept-docs] tr.yr-split > td { border-top: 2px solid var(--navy-200); }

  /* ── หน้าต่างกราฟ: สองหน้าจอ (เจ้าของสั่ง 2026-09-07) ── */
  .modal-back {
    display: grid; place-items: center; width: 26px; height: 26px; margin-right: 8px; padding: 0;
    border: 1px solid var(--line); border-radius: var(--radius-sm);
    background: var(--surface); color: var(--ink-soft); cursor: pointer;
    transition: background .14s var(--ease), color .14s var(--ease);
  }

  .modal-back:hover { background: var(--surface-2); color: var(--ink); }
  .modal-back[hidden] { display: none !important; }

  /* หน้าที่สไลด์เข้ามา — ทิศทางบอกว่ากำลังเข้าไปข้างในหรือถอยกลับ */
  .chart-page[hidden] { display: none !important; }
  .chart-page.is-fwd { animation: chartFwd .24s var(--ease) both; }
  .chart-page.is-back { animation: chartBack .24s var(--ease) both; }

  @keyframes chartFwd {
    from { opacity: 0; transform: translateX(22px); }
    to   { opacity: 1; transform: none; }
  }

  @keyframes chartBack {
    from { opacity: 0; transform: translateX(-22px); }
    to   { opacity: 1; transform: none; }
  }

  .chart-hint {
    margin: 10px 0 0; text-align: center;
    color: var(--muted); font-size: var(--fs-xs);
  }

  /* หน้าที่ 2 — กราฟซ้าย เอกสารขวา */
  .dept-pane {
    display: grid; gap: 18px; align-items: start;
    grid-template-columns: minmax(0, 420px) minmax(0, 1fr);
  }

  @media (max-width: 900px) { .dept-pane { grid-template-columns: minmax(0, 1fr); } }

  /* กราฟของแผนกเดียว — โผล่มาแบบค่อยๆ ขยาย ให้เห็นว่าเป็นชิ้นที่เพิ่งกด */
  .dept-draw { animation: chartPop .34s var(--ease) both; }

  @keyframes chartPop {
    from { opacity: 0; transform: scale(.92); }
    to   { opacity: 1; transform: none; }
  }

  .dept-docs { min-width: 0; }
  .dept-docs-scroll { max-height: 420px; overflow: auto; }
  .dept-docs-scroll thead th { position: sticky; top: 0; z-index: 1; }

  /* แถวเอกสารไล่ขึ้นทีละแถว — อ่านทันว่ามีกี่รายการ */
  .dept-docs tbody tr { animation: rowIn .26s var(--ease) both; }

  @keyframes rowIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: none; }
  }

  /* 🔴 กราฟในหน้าต่างใหญ่กดได้ทุกชิ้น ต้องบอกด้วยเคอร์เซอร์ว่ากดได้ */
  .is-big .c-seg, .dept-draw .c-seg { cursor: pointer; }

  /* ── ปุ่มเลือกชนิดกราฟ ── */
  .chart-kind { display: inline-flex; gap: 4px; margin-left: auto; flex: 0 0 auto; }

  .kbtn {
    display: grid; place-items: center; width: 26px; height: 26px; padding: 0;
    border: 1px solid var(--line); border-radius: var(--radius-sm);
    background: var(--surface); color: var(--ink-soft); cursor: pointer;
    transition: background .14s var(--ease), color .14s var(--ease), border-color .14s var(--ease);
  }

  .kbtn svg { fill: currentColor; }
  .kbtn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
  .kbtn.is-on { border-color: var(--navy-800); background: var(--navy-800); color: #fff; }

  /* 🔴 ปิดไว้ = ต้องดูออกว่ากดไม่ได้ และบอกเหตุผลผ่าน tooltip */
  .kbtn:disabled { color: #c3cbd9; background: var(--surface-2); cursor: not-allowed; }


  /*
    🔴 กรอบกราฟในการ์ดสูงคงที่ (เจ้าของสั่ง 2026-09-04)
       เปลี่ยนชนิดกราฟแล้วการ์ดต้องไม่ยืด/ไม่หด ไม่งั้นการ์ดข้างๆ ขยับตาม
  */
  .chart-box {
    display: block; width: 100%; height: 186px; padding: 0; border: 0;
    background: transparent; cursor: zoom-in; border-radius: var(--radius);
  }

  .chart-box:hover { background: var(--surface-2); }
  .chart-draw { display: block; width: 100%; height: 100%; }
  .chart-draw svg { display: block; width: 100%; height: 100%; }

  /*
    หน้าต่างใหญ่ปล่อยให้สูงตามกราฟ — แท่งแนวนอนหลายสิบแผนกต้องยืดได้
    🔴 แนวตั้งที่มีหลายสิบแผนก **เลื่อนดูแนวนอนได้** (เจ้าของสั่ง 2026-09-04)
       ถ้าบีบทุกแท่งลงในความกว้างหน้าต่าง แท่งจะบางจนดูไม่รู้เรื่อง
  */
  .chart-draw.is-big { height: auto; overflow-x: auto; overflow-y: hidden; }
  .chart-draw.is-big svg { height: auto; }

  /* หน้าต่างกราฟกว้าง/สูงกว่าหน้าต่างทั่วไป — ให้เห็นกราฟชัดขึ้น (เจ้าของสั่ง 2026-09-04) */
  .modal.is-chart { width: min(1320px, 100%); max-height: min(92vh, 940px); max-height: min(92dvh, 940px); }

  /*
    ตารางสรุปในหน้าต่างกราฟ
    🔴 ไม่ปล่อยให้ยืดเต็มความกว้างหน้าต่าง ไม่งั้นคอลัมน์ "แผนก" กว้างเป็นบล็อกสีน้ำเงินว่างๆ
       ทั้งแถบ (เจ้าของแจ้ง 2026-09-04) · จำกัดความกว้างแล้ววางกึ่งกลาง
  */
  .tbl-wrap .chart-tbl { min-width: 0; max-width: 780px; margin: 0 auto; }

  .chart-none { color: var(--muted); font-size: var(--fs-sm); text-align: center; padding: 18px 0; }

  /*
    การ์ดตารางสรุปรายแผนก — สูงเท่ากรอบกราฟ (186) แล้วเลื่อนดูข้างในเอา
    🔴 ความสูงต้องเท่ากับการ์ดกราฟ ไม่งั้นการ์ด 2 ใบข้างกันสูงไม่เท่ากัน ดูไม่เรียบร้อย
  */
  /*
    🔴 `flex: 1 1 auto` + `height` คู่กันเสมอ (บทเรียน 2026-09-04)
       ใช้ `flex: 1` เฉยๆ (basis 0) ตอนคิดความสูงตามเนื้อหา เบราว์เซอร์เอาความสูงของตารางทั้ง 57 แถวมาคิด
       การ์ดเลยสูง 1,924px แทนที่จะเป็นกรอบเลื่อนสูง 186px
       ตั้ง height ไว้ = ความสูงที่ใช้คิดผัง · flex-grow = ยืดเติมช่องว่างเมื่อการ์ดข้างๆ สูงกว่า
  */
  .dept-scroll {
    flex: 1 1 auto; height: 186px; overflow: auto;
    border: 1px solid var(--line-soft); border-radius: var(--radius);
  }
  .dept-scroll .tbl { min-width: 0; max-width: none; margin: 0; font-size: var(--fs-xs); }
  .dept-scroll .tbl th { position: sticky; top: 0; z-index: 1; }
  .dept-scroll .tbl th, .dept-scroll .tbl td { padding: 6px 6px; }

  /*
    🔴 เพิ่มคอลัมน์ "จำนวนรายการ" แล้วตารางกว้างเกินการ์ด จนคอลัมน์สัดส่วนโดนตัด (2026-09-07)
       บีบให้พอดีด้วยการปล่อยชื่อแผนกเป็นคอลัมน์เดียวที่ยืด/หดได้ แล้วตัดด้วย ... เมื่อไม่พอ
       คอลัมน์ที่เหลือกว้างเท่าเนื้อหาพอดี (white-space: nowrap) จะได้ไม่มีอันไหนหาย
  */
  .dept-scroll .chart-tbl { width: 100%; table-layout: fixed; }
  .dept-scroll .chart-tbl .col-seq { width: 42px; }
  .dept-scroll .chart-tbl td.col-no { width: 62px; white-space: nowrap; }
  .dept-scroll .chart-tbl td.col-money { white-space: nowrap; }
  .dept-scroll .chart-tbl th:nth-child(3) { width: 68px; }
  .dept-scroll .chart-tbl th:nth-child(4) { width: 92px; }
  .dept-scroll .chart-tbl th:nth-child(5) { width: 58px; }
  /* 🔴 หัวคอลัมน์ขึ้นบรรทัดใหม่ได้ ไม่งั้นชื่อยาวดันตารางจนคอลัมน์ท้ายโดนตัด (กฎเดิม 2026-09-04) */
  .dept-scroll .chart-tbl th { white-space: normal; line-height: 1.25; }
  .dept-count { margin-left: auto; margin-right: 8px; }

  /* ── คำอธิบายสี — โชว์แค่ 3 อันดับแรก (เจ้าของสั่ง 2026-09-04) ── */
  .chart-legend {
    display: flex; flex-wrap: wrap; gap: 6px 14px;
    color: var(--ink-soft); font-size: var(--fs-xs);
  }

  .lg { display: inline-flex; align-items: center; gap: 6px; min-width: 0; }
  .lg i { width: 9px; height: 9px; flex: 0 0 auto; border-radius: 2px; }
  .lg b { color: var(--navy-900); font-variant-numeric: tabular-nums; font-weight: 600; }
  .lg span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 170px; }

  /* ── ตัวหนังสือในกราฟ ── */
  .chart-draw .c-label { fill: var(--ink-soft); font-size: 11px; }
  .chart-draw .c-value { fill: var(--navy-900); font-size: 11px; font-weight: 700; }
  .chart-draw .c-grid { stroke: var(--line-soft); stroke-width: 1; }
  .chart-draw .c-seg { transition: opacity .14s var(--ease); }
  .chart-draw .c-seg:hover { opacity: .82; }

  /*
    ── ป้ายบอกค่าเวลาเอาเมาส์ชี้ที่แท่ง (เจ้าของสั่ง 2026-09-04) ──
    🔴 เป็น div ของเราเอง ไม่ใช่ <title> ของ SVG
       เพราะ tooltip ของเบราว์เซอร์ตกแต่งไม่ได้และรอ 1 วินาทีกว่าจะขึ้น
  */
  .chart-tip {
    position: fixed; z-index: var(--z-dropdown); pointer-events: none;
    display: grid; gap: 2px; min-width: 120px; max-width: 280px;
    padding: 8px 10px; border-radius: var(--radius);
    background: var(--navy-900); color: #fff;
    font-size: var(--fs-xs); line-height: 1.45;
    box-shadow: var(--shadow-lg);
  }

  .chart-tip[hidden] { display: none; }
  .chart-tip b { font-weight: 700; }

  /* จำนวนเงินใช้สีเดียวกับตัวเลขเงินทั้งระบบ แต่สว่างขึ้นให้อ่านออกบนพื้นเข้ม */
  .chart-tip .tip-val { color: #7fe3b0; font-variant-numeric: tabular-nums; font-weight: 700; }

  /* เคอร์เซอร์กะพริบตอนพิมพ์ — หายไปเมื่อพิมพ์จบ */
  .chart-tip.is-typing::after {
    content: ''; position: absolute; right: 8px; bottom: 8px;
    width: 6px; height: 12px; background: #7fe3b0;
    animation: tip-caret .7s steps(1) infinite;
  }

  @keyframes tip-caret { 50% { opacity: 0; } }

  @media (prefers-reduced-motion: reduce) {
    .chart-tip.is-typing::after { animation: none; }
  }

  /*
    ── หัวการ์ดพื้นน้ำเงิน ตัวอักษรขาว (เจ้าของสั่ง 2026-09-11) ──
    🔴 ใช้ชุดสีเดียวกับหัวตารางของระบบ (--navy-800 + #fff) ไม่คิดสีใหม่
  */
  .card-head.is-firm {
    align-items: center;
    background: var(--navy-800); border-bottom-color: var(--navy-900);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  }

  .firm-head { display: grid; gap: 1px; min-width: 0; margin-right: auto; }
  .firm-name { color: #fff; font-size: var(--fs-lg); font-weight: 700; line-height: 1.25; }
  .firm-sub { color: var(--on-dark-soft); font-size: var(--fs-xs); font-weight: 600; }

  /* จำนวนแผนกบนพื้นเข้ม — ต้องสว่างพอ ไม่งั้นจมหายไปกับพื้น */
  .card-head.is-firm .dept-count { color: var(--on-dark-soft); }

  /* ปุ่มสัญลักษณ์บนพื้นเข้ม — กลับด้านจากปุ่มบนพื้นขาว */
  .card-head.is-firm .kbtn {
    border-color: var(--line-on-dark); background: rgb(255 255 255 / 10%); color: #fff;
  }

  .card-head.is-firm .kbtn:hover:not(:disabled) {
    border-color: #fff; background: rgb(255 255 255 / 24%); color: #fff;
  }

  .card-head.is-firm .kbtn.is-on { border-color: #fff; background: #fff; color: var(--navy-800); }

  .card-head.is-firm .kbtn:disabled {
    border-color: var(--line-on-dark); background: rgb(255 255 255 / 6%); color: rgb(255 255 255 / 40%);
  }

  /* การ์ดของบริษัทเดียวกันวางซ้อนกันเป็นคอลัมน์ — กราฟบน ตารางล่าง (เจ้าของสั่ง 2026-09-11) */
  .dash-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
  .dash-col > .card { flex: 0 0 auto; }
</style>
@endpush

@push('page-script')
<script>
  'use strict';

  /**
   * วาดการ์ดกราฟ + การ์ดตารางของ "หนึ่งบริษัท"
   *
   * 🔴 หาของในกล่องของตัวเองเสมอ (root.querySelector) ห้ามหาทั้งหน้า
   *    ไม่งั้นชุดที่ 2 จะไปจับ element ของชุดแรกแล้ววาดทับกัน
   */
  window.BMS = window.BMS || {};

  window.BMS.budgetChart = function (root, opts) {
    if (! root) { return; }

    var data = opts.data || [];

    var LEGEND_MAX = 3;        // คำอธิบายสีในการ์ดโชว์ 3 อันดับแรก (เจ้าของสั่ง 2026-09-04)

    /*
      ขนาดกระดาษวาดของการ์ด — คงที่เสมอ ไม่ขึ้นกับจำนวนแผนกหรือชนิดกราฟ
      หน้าต่างใหญ่คิดความสูงเองตามจำนวนแผนกและความยาวชื่อ
    */
    var CARD = { w: 570, h: 186 };
    var BIG_W = 1240;

    var kindBtns = Array.prototype.slice.call(root.querySelectorAll('[data-kind]'));
    var kind = 'bar-v';
    var cardBox = root.querySelector('[data-chart-card]');
    var bigBox = root.querySelector('[data-chart-big]');
    var legend = root.querySelector('[data-chart-legend]');
    var totalOut = root.querySelector('[data-chart-total]');
    // จำนวนรายการงบ — เดินตามตัวกรองเดียวกับยอดเงิน
    var countOut = root.querySelector('[data-chart-count]');
    // จำนวนรายการต่อแผนก — คิดจาก data ที่ส่งมาแล้ว ไม่ต้องส่งซ้ำเป็นก้อนที่ 2
    var rowsOf = {};

    data.forEach(function (d) { rowsOf[d.name] = d.rows; });
    var rowsOut = root.querySelector('[data-chart-rows]');
    // หน้าต่างตารางรายแผนก 2 หน้าจอ (เจ้าของสั่ง 2026-09-07)
    var deptRows = root.querySelector('[data-dept-rows]');
    var deptDocRows = root.querySelector('[data-dept-doc-rows]');
    var deptPageAll = root.querySelector('[data-dept-page="all"]');
    var deptPageOne = root.querySelector('[data-dept-page="one"]');
    var deptBack = root.querySelector('[data-dept-back]');
    var deptSub = root.querySelector('[data-dept-modal-sub]');
    var deptOpen = null;
    var cntOut = root.querySelector('[data-dept-count]');
    var sortBtns = Array.prototype.slice.call(root.querySelectorAll('[data-sort]'));

    // เรียงตามวงเงิน — ค่าเริ่มต้น "มากไปน้อย" ตามที่เจ้าของกำหนด
    var order = 'desc';
    var subOut = root.querySelector('[data-chart-big-sub]');
    var noneOut = root.querySelector('[data-chart-none]');
    // หน้าต่างกราฟ 2 หน้าจอ (เจ้าของสั่ง 2026-09-07)
    var pageAll = root.querySelector('[data-chart-page="all"]');
    var pageOne = root.querySelector('[data-chart-page="one"]');
    var backBtn = root.querySelector('[data-chart-back]');
    var oneDraw = root.querySelector('[data-dept-draw]');
    var oneDocs = root.querySelector('[data-dept-docs]');
    // ยอดรวมท้ายกระดาษของทั้ง 2 หน้าต่าง (เจ้าของสั่ง 2026-09-07)
    var chartFootSum = root.querySelector('[data-chart-foot-sum]');
    var deptFootSum = root.querySelector('[data-dept-foot-sum]');
    /*
      ตารางในหน้าต่างที่มีตัวกรองหัวคอลัมน์ — กรองที่ข้อมูล ไม่ใช่ซ่อนแถว (เจ้าของแจ้ง 2026-09-11)
      ดู layouts/partials/data-filter · ตัวกรองสร้างครั้งเดียวท้ายสคริปต์
    */
    var deptTable = root.querySelector('[data-dept-table]');
    var deptDocTable = root.querySelector('[data-dept-doc-table]');
    var oneDocTable = root.querySelector('[data-one-doc-table]');
    var deptFilter = null;
    var deptDocFilter = null;
    var oneDocFilter = null;
    // แผนกที่เห็นอยู่จริงในตารางของหน้าต่าง — กดแถวแล้วอ้าง index จากชุดนี้เท่านั้น
    var deptShown = [];
    var docsOf = opts.docs || {};
    // แผนกที่กำลังดูอยู่ในหน้าที่ 2 — null = อยู่หน้ากราฟรวม
    var openDept = null;
    var openBtn = root.querySelector('[data-chart-open]');

    if (! kindBtns.length || ! cardBox) { return; }

    /*
      จานสี — 10 สี วนซ้ำเมื่อแผนกเกิน
      🔴 คุมโทนให้เข้ากับธีม (น้ำเงินองค์กรนำ) ไม่ใช้สีสดจัดแบบสุ่ม
    */
    var COLORS = [
      '#2f5aa8', '#16794a', '#c98a1b', '#7a56ad', '#2f8f9e',
      '#b3312a', '#5b7285', '#7a9c3c', '#c05c8e', '#3f6f6f',
    ];

    /*
      🔴 สีต้อง "ประจำแผนก" ไม่ใช่ประจำลำดับที่วาด (เจ้าของสั่ง 2026-09-04)
         เดิมให้สีตามลำดับในรายการที่กำลังวาด พอกรองเหลือแผนกเดียวมันเลยกลายเป็นสีแรกเสมอ
         ตอนนี้จำสีไว้ตั้งแต่ตอนโหลดหน้า ใช้สีเดียวกันทุกชนิดกราฟและทุกตัวกรอง
    */
    var COLOR_OF = {};

    data.forEach(function (d, i) { COLOR_OF[d.name] = COLORS[i % COLORS.length]; });

    function color(d) {
      if (typeof d === 'string') { return COLOR_OF[d] || COLORS[0]; }

      return (d && COLOR_OF[d.name]) || COLORS[0];
    }
    function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; }
    function baht(n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function en() { return window.BMS.lang() === 'en'; }

    function short(n) {
      if (n >= 1e6) { return (n / 1e6).toFixed(n >= 1e7 ? 0 : 1) + 'M'; }
      if (n >= 1e3) { return (n / 1e3).toFixed(n >= 1e5 ? 0 : 1) + 'K'; }

      return String(Math.round(n));
    }

    /** ข้อมูลที่ผ่านตัวกรองแผนกของหน้า (แถบตัวกรองนอกการ์ด) */
    /*
      ยอดรวมทุกแผนก — ตัวหารของ "สัดส่วน" ทุกที่ในหน้านี้

      🔴 ห้ามใช้ยอดของชุดที่กรองแล้วเป็นตัวหาร (เจ้าของแจ้ง 2026-09-07)
         กรองแผนกเดียวจะได้ 100% เสมอ ซึ่งไม่ได้บอกอะไรเลย
    */
    function grandTotal() {
      return data.reduce(function (n, d) { return n + d.total; }, 0);
    }

    function picked() {
      var only = window.BMS.dashDept ? window.BMS.dashDept() : '';

      return only ? data.filter(function (d) { return d.name === only; }) : data.slice();
    }

    // ── ตัวช่วยวาด ──────────────────────────────────────────────
    /**
     * @param  big  หน้าต่างใหญ่กำหนดความกว้างเป็นพิกเซลจริง เพื่อให้กรอบเลื่อนแนวนอนได้
     *              (การ์ดต้องพอดีกรอบเสมอ จึงปล่อยให้ย่อตามสัดส่วน)
     */
    function svg(box, body, big) {
      var size = big ? ' style="width:' + box.w + 'px;height:' + box.h + 'px"' : '';

      return '<svg viewBox="0 0 ' + box.w + ' ' + box.h + '" preserveAspectRatio="xMidYMid meet"'
        + size + ' role="img" aria-hidden="true">' + body + '</svg>';
    }

    /** ชิ้นกราฟ 1 อัน — ใส่ data-i ไว้ให้ป้ายบอกค่ารู้ว่าเป็นแผนกไหน */
    function seg(i, tag, attrs) {
      return '<' + tag + ' class="c-seg" data-i="' + i + '" ' + attrs + '></' + tag + '>';
    }

    /**
     * แท่งแนวตั้ง — ชนิดหลักของการ์ด
     *
     * 🔴 ชื่อแผนกเอียง 45° และเขียนเต็ม ไม่ตัดคำ (เจ้าของสั่ง 2026-09-04)
     *    ที่ว่างด้านล่างคิดจากชื่อที่ยาวที่สุดจริงๆ ไม่ใช่ตั้งตัวเลขตายตัว
     *    ช่องแคบจนชื่อเอียงยังทับกัน → ไม่เขียนชื่อ (ดูได้จากป้ายตอนเอาเมาส์ชี้)
     */
    function barV(list, big) {
      var w = big ? BIG_W : CARD.w;
      var fs = big ? 10.5 : 9.5;
      var pieceW = 0;
      var longest0 = 0;

      list.forEach(function (d) { longest0 = Math.max(longest0, d.name.length); });

      /*
        🔴 ป้ายเอียง 45° ของแท่งแรกจะยื่นออกไปทางซ้ายเท่ากับความสูงที่มันกินลงล่าง
           ถ้าเว้นขอบซ้ายแค่ 22 หน่วย ชื่อแผนกแรกๆ จะถูกกรอบตัดหัวทิ้ง (บั๊กจริง 2026-09-04)
      */
      var lean = Math.min(big ? 190 : 74, longest0 * fs * 0.56 * 0.71 + 14);
      var padL = 22, padR = 22;
      var slot = 0;

      /*
        🔴 หน้าต่างใหญ่: กันที่ให้แต่ละแผนกอย่างน้อย 34 หน่วย แล้วเลื่อนดูแนวนอนเอา
           ถ้าอัดทุกแผนกลงในความกว้างเดียว 57 แท่งจะบางจนดูไม่รู้เรื่อง (เจ้าของแจ้ง 2026-09-04)
      */
      if (big) { w = Math.max(BIG_W, list.length * 34 + lean + 22); }

      // 45° ต้องการระยะห่างแนวนอนอย่างน้อยเท่าความสูงบรรทัด หารด้วย sin45
      var showName = ((w - 44) / list.length) >= fs * 1.55;

      /*
        🔴 ช่องกว้างพอจะเขียนชื่อแนวนอนได้ ก็ไม่ต้องเอียง (เจ้าของสั่ง 2026-09-04)
           ป้ายเอียงกินที่ด้านล่างเยอะ เลือกแผนกเดียวแล้วแท่งเลยเตี้ยกว่าที่ควรจะเป็น
      */
      var flat = showName && ((w - 44) / list.length) >= longest0 * fs * 0.58;

      if (showName && ! flat) { padL = lean; }

      slot = (w - padL - padR) / list.length;

      // ความยาวข้อความโดยประมาณ × cos45 = ความสูงที่ป้ายเอียงกินลงไป
      var footH = ! showName ? 10 : (flat ? 22 : lean);
      var plotH = big ? 330 : (CARD.h - footH - 4);
      var box = { w: w, h: plotH + footH };
      var base = plotH;
      var head = 18;
      var max = list[0].total || 1;
      var barW = Math.max(1.5, Math.min(big ? 74 : 46, slot * 0.66));
      var showValue = slot >= (big ? 40 : 44);
      var out = '';

      for (var g = 0; g <= 4; g++) {
        var gy = base - (base - head) * (g / 4);
        out += '<line class="c-grid" x1="' + padL + '" y1="' + gy + '" x2="' + (w - padR) + '" y2="' + gy + '"></line>';
      }

      list.forEach(function (d, i) {
        var cx = padL + slot * i + slot / 2;
        var len = Math.max(1.5, ((base - head) * d.total) / max);
        var y = base - len;

        out += seg(i, 'rect', 'x="' + (cx - barW / 2) + '" y="' + y + '" width="' + barW
          + '" height="' + len + '" rx="' + (barW < 6 ? 1 : 3) + '" fill="' + color(d) + '"');

        if (showValue) {
          out += '<text class="c-value" x="' + cx + '" y="' + (y - 5) + '" text-anchor="middle">' + short(d.total) + '</text>';
        }

        if (flat) {
          // ช่องกว้างพอ เขียนชื่อแนวนอนใต้แท่งได้เลย แท่งจะได้สูงเต็มที่
          out += '<text class="c-label" x="' + cx + '" y="' + (base + 15) + '" text-anchor="middle"'
            + ' style="font-size:' + fs + 'px">' + esc(d.name) + '</text>';
        } else if (showName) {
          // เอียง 45° ปลายข้อความชี้เข้าหาแท่งของตัวเอง
          var ax = cx, ay = base + 12;

          out += '<text class="c-label" x="' + ax + '" y="' + ay + '" text-anchor="end"'
            + ' style="font-size:' + fs + 'px" transform="rotate(-45 ' + ax + ' ' + ay + ')">'
            + esc(d.name) + '</text>';
        }
      });

      return svg(box, out, big);
    }

    /**
     * แท่งแนวนอน
     *
     * 🐛 บั๊กเดิม 2026-09-04: คิดช่องไฟเป็นค่าคงที่ 7 หน่วยต่อแถว
     *    57 แผนกในกรอบสูง 186 → ช่องไฟกินไป 406 หน่วย ความสูงแท่งติดลบ กราฟเลยหายทั้งใบ
     *    ตอนนี้ช่องไฟคิดจากพื้นที่ที่มีจริง และแท่งมีความสูงขั้นต่ำเสมอ
     */
    function barH(list, big) {
      var w = big ? BIG_W : CARD.w;
      var n = list.length;

      // หน้าต่างใหญ่: ให้แต่ละแถวสูงพอจะอ่านชื่อได้ แล้วปล่อยให้กระดาษวาดยืดลงไป
      // 🔴 มีไม่กี่แผนกก็อย่าให้แท่งอ้วนเกิน (เจ้าของสั่ง 2026-09-04) — เลือกแผนกเดียวเคยได้แท่งสูง 134 หน่วย
      var rowH = big ? 26 : Math.max(2, Math.min(30, CARD.h / n * 0.72));
      var gap = big ? 8 : Math.max(0.6, CARD.h / n * 0.28);
      var h = big ? (rowH + gap) * n + gap : CARD.h;
      var box = { w: w, h: h };

      var showText = rowH >= 11;
      var labelW = showText ? (big ? 230 : 118) : 0;
      var valueW = showText ? (big ? 110 : 56) : 0;
      var barW = w - labelW - valueW - (showText ? 10 : 0);
      var max = list[0].total || 1;
      var top = (h - ((rowH + gap) * n - gap)) / 2;
      var out = '';

      list.forEach(function (d, i) {
        var y = top + i * (rowH + gap);
        var len = Math.max(1.5, (d.total / max) * barW);
        var rx = rowH < 6 ? 1 : 3;

        if (showText) {
          out += '<text class="c-label" x="0" y="' + (y + rowH / 2 + 3.5) + '" style="font-size:'
            + (big ? 11 : 9.5) + 'px">' + esc(d.name) + '</text>';
        }

        out += '<rect x="' + labelW + '" y="' + y + '" width="' + barW + '" height="' + rowH
          + '" rx="' + rx + '" fill="var(--surface-2)"></rect>';

        out += seg(i, 'rect', 'x="' + labelW + '" y="' + y + '" width="' + len + '" height="' + rowH
          + '" rx="' + rx + '" fill="' + color(d) + '"');

        if (showText) {
          out += '<text class="c-value" x="' + w + '" y="' + (y + rowH / 2 + 3.5) + '" text-anchor="end" style="font-size:'
            + (big ? 11 : 9.5) + 'px">' + (big ? baht(d.total) : short(d.total)) + '</text>';
        }
      });

      return svg(box, out, big);
    }

    /**
     * กราฟวงกลม — เทียบ "แผนกที่เลือก" กับ "ทุกแผนกรวมกัน" (เจ้าของสั่ง 2026-09-04)
     *
     * 🔴 ใช้ได้เฉพาะตอนเลือกแผนกที่แถบตัวกรองของหน้า
     *    ชิ้นของแผนกใช้สีประจำแผนกเดิม · ที่เหลือเป็นสีกลางเพื่อไม่ให้แย่งสายตา
     */
    function pie(one, grand, big) {
      var size = big ? 380 : 168;
      var box = { w: big ? BIG_W : CARD.w, h: size };
      // เว้นขอบให้เส้นชี้กับตัวเลขที่อยู่นอกวง ไม่ให้โดนกรอบตัด
      var cx = box.w / 2, cy = size / 2, r = size / 2 - 26;
      var part = grand > 0 ? one.total / grand : 0;
      var span = part * Math.PI * 2;
      var from = -Math.PI / 2;
      var to = from + span;
      var out = '';

      // ชิ้นใหญ่ (แผนกอื่นทั้งหมด) วาดเป็นวงเต็มก่อน แล้วทับด้วยชิ้นของแผนกที่เลือก
      out += '<circle cx="' + cx + '" cy="' + cy + '" r="' + r + '" fill="var(--surface-2)" stroke="var(--line)"></circle>';

      var whole = part >= 0.9999;

      if (whole) {
        /*
          🔴 แผนกนี้กินทั้งหมด — ต้องวาดเป็นวงกลมเต็มใบ
             ถ้าวาดด้วยเส้นโค้ง (path) มุมกวาด 360° จุดเริ่มกับจุดจบเป็นจุดเดียวกัน
             จะได้รูปที่ไม่มีพื้นที่ มองเห็นแต่วงพื้นหลังสีขาว (บั๊กจริง 2026-09-07)
        */
        out += '<circle class="c-seg" data-i="0" cx="' + cx + '" cy="' + cy + '" r="' + r
          + '" fill="' + color(one) + '"></circle>';
      } else if (span > 0.0001) {
        var x1 = cx + r * Math.cos(from), y1 = cy + r * Math.sin(from);
        var x2 = cx + r * Math.cos(to), y2 = cy + r * Math.sin(to);

        out += '<path class="c-seg" data-i="0" fill="' + color(one) + '" d="M ' + cx + ' ' + cy
          + ' L ' + x1 + ' ' + y1
          + ' A ' + r + ' ' + r + ' 0 ' + (span > Math.PI ? 1 : 0) + ' 1 ' + x2 + ' ' + y2 + ' Z"></path>';
      }

      // ป้าย % 2 ฝั่ง — ของแผนกที่เลือก และของแผนกอื่นทั้งหมด
      var mid = from + span / 2;
      var mine = (part * 100);
      var rest = 100 - mine;
      var fs = big ? 13 : 11;

      if (whole) {
        // เต็มวง — เขียน 100% ไว้กลางวง ไม่ต้องมีป้ายของแผนกอื่น
        out += '<text x="' + cx + '" y="' + (cy + 6) + '" text-anchor="middle" fill="#fff"'
          + ' style="font-size:' + (big ? 26 : 18) + 'px;font-weight:700">100%</text>';
      } else if (mine >= 8) {
        // ชิ้นใหญ่พอ เขียนตัวเลขในชิ้นได้เลย
        out += '<text x="' + (cx + r * 0.62 * Math.cos(mid)) + '" y="' + (cy + r * 0.62 * Math.sin(mid) + 4)
          + '" text-anchor="middle" fill="#fff" style="font-size:' + fs + 'px;font-weight:700">'
          + mine.toFixed(1) + '%</text>';
      } else {
        /*
          🔴 ชิ้นเล็กเกินกว่าจะเขียนตัวเลขข้างใน (เจ้าของสั่ง 2026-09-04)
             ลากเส้นจากกลางชิ้นออกไปนอกวง แล้วเขียนตัวเลขข้างๆ แบบกราฟวงกลมทั่วไป
        */
        var x1 = cx + r * 0.72 * Math.cos(mid), y1 = cy + r * 0.72 * Math.sin(mid);
        var x2 = cx + (r + 16) * Math.cos(mid), y2 = cy + (r + 16) * Math.sin(mid);
        var right = Math.cos(mid) >= 0;
        var x3 = x2 + (right ? 22 : -22);

        out += '<path d="M ' + x1 + ' ' + y1 + ' L ' + x2 + ' ' + y2 + ' L ' + x3 + ' ' + y2 + '"'
          + ' fill="none" stroke="' + color(one) + '" stroke-width="1.2"></path>';

        out += '<text x="' + (x3 + (right ? 4 : -4)) + '" y="' + (y2 + 4) + '" text-anchor="' + (right ? 'start' : 'end') + '"'
          + ' fill="' + color(one) + '" style="font-size:' + fs + 'px;font-weight:700">'
          + mine.toFixed(2) + '%</text>';
      }

      // สัดส่วนของแผนกอื่นทั้งหมด — ไม่เหลือแล้วก็ไม่ต้องเขียน 0.0% ให้รก
      if (! whole) {
        out += '<text x="' + (cx - r * 0.45) + '" y="' + (cy + r * 0.45) + '" text-anchor="middle" class="c-value"'
          + ' style="font-size:' + fs + 'px">' + rest.toFixed(1) + '%</text>';
      }

      return svg(box, out, big);
    }

    function draw(list, big) {
      if (kind === 'pie') {
        return pie(list[0], grandTotal(), big);
      }

      return kind === 'bar-h' ? barH(list, big) : barV(list, big);
    }

    /* ═══════════ ป้ายบอกค่าตอนเอาเมาส์ชี้ — มีอนิเมชันพิมพ์ทีละตัว ═══════════ */
    var tip = document.createElement('div');
    tip.className = 'chart-tip';
    tip.hidden = true;
    tip.innerHTML = '<b class="tip-name"></b><span class="tip-val"></span>';
    document.body.appendChild(tip);

    var tipName = tip.querySelector('.tip-name');
    var tipVal = tip.querySelector('.tip-val');
    var typer = null;
    var slow = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function type(name, value) {
      clearInterval(typer);

      if (slow) {
        tipName.textContent = name;
        tipVal.textContent = value;
        tip.classList.remove('is-typing');

        return;
      }

      var full = name + '\n' + value;
      var at = 0;

      tipName.textContent = '';
      tipVal.textContent = '';
      tip.classList.add('is-typing');

      typer = setInterval(function () {
        at++;

        if (at >= full.length) {
          clearInterval(typer);
          tip.classList.remove('is-typing');
        }

        var cut = full.slice(0, at);
        var br = cut.indexOf('\n');

        tipName.textContent = br === -1 ? cut : cut.slice(0, br);
        tipVal.textContent = br === -1 ? '' : cut.slice(br + 1);
      }, 14);
    }

    function place(e) {
      // กันป้ายล้นขอบจอ — ชิดขวาแล้วให้เด้งไปอยู่ซ้ายเมาส์แทน
      var w = tip.offsetWidth || 160;
      var h = tip.offsetHeight || 44;
      var x = e.clientX + 14;
      var y = e.clientY + 16;

      if (x + w > window.innerWidth - 8) { x = e.clientX - w - 14; }
      if (y + h > window.innerHeight - 8) { y = e.clientY - h - 12; }

      tip.style.left = Math.max(8, x) + 'px';
      tip.style.top = Math.max(8, y) + 'px';
    }

    function hideTip() {
      clearInterval(typer);
      tip.hidden = true;
      tip.classList.remove('is-typing');
    }

    /** ผูกป้ายบอกค่าเข้ากับกรอบกราฟ 1 กรอบ — อ่านแผนกจาก data-i ของชิ้นที่ชี้อยู่ */
    function bindTip(host, listOf) {
      host.addEventListener('mousemove', function (e) {
        var hit = e.target.closest ? e.target.closest('[data-i]') : null;

        if (! hit) { return hideTip(); }

        var d = listOf()[+hit.getAttribute('data-i')];
        if (! d) { return hideTip(); }

        if (tip.hidden || tip.dataset.at !== hit.getAttribute('data-i') + host.className) {
          tip.dataset.at = hit.getAttribute('data-i') + host.className;
          tip.hidden = false;
          type(d.name, baht(d.total) + (en() ? ' THB' : ' บาท'));
        }

        place(e);
      });

      host.addEventListener('mouseleave', hideTip);
    }

    var cardList = [];
    var bigList = [];

    bindTip(cardBox, function () { return cardList; });
    bindTip(bigBox, function () { return bigList; });

    // ── วาดใหม่ทั้งการ์ด ────────────────────────────────────────
    function render() {
      var list = picked();
      var sum = list.reduce(function (n, d) { return n + d.total; }, 0);
      var empty = list.length === 0;

      totalOut.textContent = baht(sum);

      if (countOut) {
        // นับเฉพาะแผนกที่ผ่านตัวกรอง — ตัวเลขจึงตรงกับยอดเงินที่อยู่ข้างกันเสมอ
        countOut.textContent = data.reduce(function (n, d) {
          return n + (rowsOf[d.name] || 0);
        }, 0).toLocaleString('en-US');
      }
      noneOut.hidden = ! empty;
      legend.hidden = empty;
      if (openBtn) { openBtn.hidden = empty; }

      if (empty) { return; }

      // 🔴 การ์ดวาดครบทุกแผนก — กราฟย่อตัวเองให้พอดีกรอบ
      cardList = list;
      cardBox.innerHTML = draw(list, false);

      /*
        กราฟวงกลม = เทียบแผนกเดียวกับทั้งหมด คำอธิบายสีจึงต้องบอก 2 ฝั่ง
        ไม่ใช่ 3 อันดับแรกเหมือนกราฟแท่ง
      */
      if (kind === 'pie') {
        var grand = grandTotal();
        var mine = grand > 0 ? (list[0].total / grand) * 100 : 0;

        // ส่วนที่เหลือ = แผนกอื่นทั้งหมดรวมกัน ให้ตรงกับพื้นที่เทาในวงกลม
        var restAmt = grand - list[0].total;
        var restPct = 100 - mine;

        legend.innerHTML = '<span class="lg"><i style="background:' + color(list[0]) + '"></i>'
          + '<span title="' + esc(list[0].name) + '">' + esc(list[0].name) + '</span>'
          + '<b>' + short(list[0].total) + '</b><span class="soft">' + mine.toFixed(1) + '%</span></span>'
          // มีแผนกเดียวในระบบ ก็ไม่มีส่วนที่เหลือให้บอก
          + (mine >= 99.99 ? '' : '<span class="lg"><i style="background:var(--line)"></i>'
            + '<span>' + (en() ? 'Other departments' : 'แผนกอื่นทั้งหมด') + '</span>'
            + '<b>' + short(restAmt) + '</b><span class="soft">' + restPct.toFixed(1) + '%</span></span>');

        return;
      }

      // คำอธิบายสี: 3 อันดับแรก ที่เหลือบอกเป็นจำนวนแผนก
      var top = list.slice(0, LEGEND_MAX);
      var more = list.length - top.length;

      // 🔴 เทียบกับยอดทั้งหมดเสมอ ไม่ใช่ยอดที่กรองไว้ (เจ้าของแจ้ง 2026-09-07)
      var grandAll = grandTotal();

      legend.innerHTML = top.map(function (d, i) {
        var pct = grandAll > 0 ? (d.total / grandAll) * 100 : 0;

        return '<span class="lg"><i style="background:' + color(d) + '"></i>'
          + '<span title="' + esc(d.name) + '">' + esc(d.name) + '</span>'
          + '<b>' + short(d.total) + '</b>'
          + '<span class="soft">' + pct.toFixed(1) + '%</span></span>';
      }).join('') + (more
        ? '<span class="lg soft">+' + more + (en() ? ' more departments' : ' แผนก') + '</span>'
        : '');
    }

    /*
      ตารางสรุปรายแผนก — การ์ดที่ 2 (ย้ายออกจากหน้าต่างขยายเมื่อ 2026-09-04)
      🔴 ต้องวาดใหม่ทุกครั้งที่กรอง ไม่ใช่วาดตอนเปิดหน้าต่างอย่างเดียวเหมือนตอนที่ยังอยู่ใน modal
    */
    function renderTable() {
      // ตารางในการ์ดอ่านอย่างเดียว · ตารางในหน้าต่างกดแถวเพื่อดูเอกสารของแผนกได้
      fillTable(rowsOut, false);
      fillTable(deptRows, true);

      // อยู่หน้าเอกสารอยู่ก็ต้องวาดใหม่ตามตัวกรอง/ภาษาที่เพิ่งเปลี่ยน
      if (deptOpen) { renderDeptDocs(); }
    }

    /**
     * รายชื่อแผนกที่ตารางใช้ — ผ่านตัวกรองแผนกของหน้าแล้วเรียงตามปุ่มเรียงลำดับ
     *
     * 🔴 เรียงเฉพาะ "ตาราง" เท่านั้น ไม่ไปยุ่งกับกราฟ
     *    กราฟคิดความยาวแท่งจากก้อนที่มากที่สุด (list[0]) ถ้าเรียงกลับด้านกราฟจะเพี้ยนทั้งใบ
     */
    function deptSorted() {
      var list = picked().slice();

      list.sort(function (a, b) { return order === 'asc' ? a.total - b.total : b.total - a.total; });

      return list;
    }

    /** ข้อความคอลัมน์ "จำนวนรายการ" — ตัวกรองกับตารางต้องใช้ตัวเดียวกัน ไม่งั้นค่าที่ติ๊กไม่ตรงกับที่เห็น */
    function deptCount(d) {
      var n = (docsOf[d.name] || []).length;

      return n ? n + (en() ? ' items' : ' รายการ') : '—';
    }

    /** ข้อความคอลัมน์ "สัดส่วน" — 🔴 เทียบยอดทั้งหมดเสมอ ไม่ใช่ยอดที่กรองแล้ว (ข้อ 33.8) */
    function deptShare(d) {
      var g = grandTotal();

      return (g > 0 ? (d.total / g) * 100 : 0).toFixed(1) + '%';
    }

    function fillTable(box, clickable) {
      if (! box) { return; }

      var list = deptSorted();

      /*
        จำนวนแผนกที่หัวการ์ด = เดินตามตัวกรองแผนกของหน้าเท่านั้น
        🔴 ห้ามให้ตัวกรองหัวคอลัมน์ของตารางในหน้าต่างมาเปลี่ยนตัวเลขบนการ์ด
           เพราะการ์ดกับหน้าต่างคนละเรื่องกัน ผู้ใช้ปิดหน้าต่างแล้วจะงงว่าตัวเลขเปลี่ยนไปเอง
      */
      if (box === rowsOut && cntOut) {
        cntOut.textContent = (en() ? 'Count ' : 'จำนวน ')
          + list.length + (en() ? ' departments' : ' แผนก');
      }

      // ตารางในหน้าต่างมีตัวกรองหัวคอลัมน์ของตัวเอง — กรองที่ข้อมูลก่อนวาด แล้วเลขลำดับจะเรียง 1..N ใหม่เอง
      if (box === deptRows) {
        if (deptFilter) { list = deptFilter.view(); }

        deptShown = list;

        // ยอดท้ายหน้าต่างเดินตามสิ่งที่เห็นจริง (เฉพาะตอนอยู่หน้ารายชื่อแผนก)
        if (deptFootSum && ! deptOpen) {
          deptFootSum.textContent = baht(list.reduce(function (n, d) { return n + d.total; }, 0));
        }
      }

      if (! list.length) {
        // แยกให้ออกว่า "ไม่มีข้อมูล" กับ "กรองจนไม่เหลือ" — ไม่งั้นผู้ใช้นึกว่าข้อมูลหาย
        var none = box === deptRows && deptFilter && deptFilter.on()
          ? (en() ? 'Nothing matches the filter' : 'ไม่มีรายการที่ตรงกับตัวกรอง')
          : (en() ? 'No approved budget in this department' : 'แผนกนี้ยังไม่มีงบที่อนุมัติ');

        box.innerHTML = '<tr><td colspan="5" class="empty">' + none + '</td></tr>';

        return;
      }

      /*
        🔴 แถวกดได้เฉพาะในหน้าต่าง (เจ้าของสั่ง 2026-09-07)
           ในการ์ดกดตรงไหนก็เปิดหน้าต่าง แถวจึงไม่ต้องกดเองซ้อนอีกชั้น
        🔴 ชื่อแผนกใส่ใน attribute ตรงๆ ไม่ได้ (มี · และวรรค) — อ้างด้วยลำดับแล้วไปหยิบจาก deptShown
      */
      box.innerHTML = list.map(function (d, i) {
        var docs = docsOf[d.name] || [];

        // แผนกที่ไม่มีเอกสารกดไปก็ไม่มีอะไรให้ดู จึงไม่ต้องรับโฟกัสจากคีย์บอร์ด
        var hit = clickable && docs.length
          ? ' data-dept-row="' + i + '" tabindex="0" role="button"'
            + ' title="เอกสารของแผนกนี้" data-i18n-title="chart.deptDocs"'
          : '';

        return '<tr class="dept-tr"' + hit + '>'
          + '<td class="col-seq">' + (i + 1) + '</td>'
          + '<td class="txt-left"><div class="dept-cell">'
            + '<span class="lg"><i style="background:' + color(d) + '"></i><span>' + esc(d.name) + '</span></span>'
          + '</div></td>'
          + '<td class="col-no">' + deptCount(d) + '</td>'
          + '<td class="col-money txt-right"><span class="money money-strong">' + baht(d.total) + '</span></td>'
          + '<td class="col-no">' + deptShare(d) + '</td>'
        + '</tr>';
      }).join('');
    }

    /*
      หน้าที่ 2 ของหน้าต่าง — เอกสารของแผนกที่กด
      🔴 สัดส่วนเทียบยอดทั้งหมดเสมอ (ข้อ 33.8) ไม่ใช่เทียบยอดของแผนกตัวเอง
         ไม่งั้นแผนกที่มีใบเดียวจะขึ้น 100% ซึ่งไม่ได้บอกอะไร
    */
    function renderDeptDocs() {
      if (! deptDocRows || ! deptOpen) { return; }

      // 🔴 กรองที่ข้อมูลก่อนวาด — เลขลำดับ ยอดท้ายกระดาษ และ rowspan จึงตรงกับที่เห็นเสมอ
      var rows = deptDocFilter ? deptDocFilter.view() : (docsOf[deptOpen] || []);
      var mine = data.filter(function (d) { return d.name === deptOpen; })[0];

      // ยอดของแผนกนี้ — ไปโผล่ท้ายกระดาษ เห็นได้ตลอดแม้เลื่อนตารางลงไปแล้ว
      if (deptFootSum) {
        deptFootSum.textContent = baht(rows.reduce(function (n, r) { return n + r.amount; }, 0));
      }

      if (! rows.length) {
        deptDocRows.innerHTML = '<tr><td colspan="9" class="empty">'
          + (deptDocFilter && deptDocFilter.on()
            ? (en() ? 'Nothing matches the filter' : 'ไม่มีรายการที่ตรงกับตัวกรอง')
            : (en() ? 'No budget rows in the ERP' : 'ไม่มีรายการงบใน ERP'))
          + '</td></tr>';

        return;
      }

      deptDocRows.innerHTML = rows.map(function (r, i) {
        /*
          🔴 ทั้งหน้าเป็นของแผนกเดียว ชื่อแผนกจึงเขียนครั้งเดียวแล้วครอบทุกแถวด้วย rowspan
             (เจ้าของสั่ง 2026-09-07) เขียนซ้ำทุกแถวจะรกและอ่านยากขึ้นเปล่าๆ
        */
        var dept = i === 0
          ? '<td class="col-dept txt-left" rowspan="' + rows.length + '">'
            + '<span class="lg"><i style="background:' + (mine ? color(mine) : 'var(--line)') + '"></i>'
            + '<span>' + esc(deptOpen) + '</span></span></td>'
          : '';

        /*
          🔴 ขีดเส้นคั่นเมื่อขึ้นปีใหม่ — เจ้าของสั่งให้ "แยกตามปีแต่ละแผนก"
             แถวเรียงปีปัจจุบันมาก่อนอยู่แล้ว เส้นคั่นจึงอ่านเป็นกลุ่มปีได้ทันที
        */
        var newYear = i > 0 && rows[i - 1].year !== r.year;

        return '<tr class="' + (newYear ? 'yr-split' : '') + '"'
            + ' style="animation-delay:' + Math.min(i * 40, 400) + 'ms">'
          + '<td class="col-seq">' + (i + 1) + '</td>'
          + dept
          + '<td class="col-no">' + (r.year || '—') + '</td>'
          + '<td class="col-no"><span class="doc-no">' + esc(r.model) + '</span></td>'
          + '<td class="col-no"><span class="doc-no">' + esc(r.budgetNo) + '</span></td>'
          + '<td class="txt-left"><span class="doc-t" title="' + esc(r.title) + '">' + esc(r.title) + '</span></td>'
          + '<td class="col-money txt-right"><span class="money money-strong">' + baht(r.amount) + '</span></td>'
          + '<td class="col-no soft">' + esc(r.startDate || '—') + '</td>'
          + '<td class="col-no soft">' + esc(r.createdAt || '—') + '</td>'
        + '</tr>';
      }).join('');
    }

    /** สลับหน้าในหน้าต่างตาราง · dept = null คือกลับไปรายชื่อแผนก */
    function showDeptPage(dept, back) {
      deptOpen = dept;

      var into = dept ? deptPageOne : deptPageAll;
      var out = dept ? deptPageAll : deptPageOne;

      out.hidden = true;
      into.hidden = false;

      // เริ่มอนิเมชันใหม่ทุกครั้ง ไม่งั้นสลับหน้ารอบที่ 2 จะไม่ขยับ
      into.classList.remove('is-fwd', 'is-back');
      void into.offsetWidth;
      into.classList.add(back ? 'is-back' : 'is-fwd');

      if (deptBack) { deptBack.hidden = ! dept; }
      if (deptSub) { deptSub.textContent = dept ? ' · ' + dept : ''; }

      /*
        🔴 เปลี่ยนแผนก = ล้างตัวกรองหัวคอลัมน์ของตารางเอกสารเสมอ
           ค่าที่ติ๊กค้างไว้เป็นของแผนกเก่า ถ้าไม่ล้าง แผนกใหม่จะโชว์ตารางว่างโดยไม่บอกเหตุผล
      */
      if (deptDocFilter) { deptDocFilter.reset(); }

      if (dept) {
        renderDeptDocs();
      } else {
        // กลับมาหน้ารวม — วาดตารางแผนกใหม่ ยอดท้ายกระดาษจะตามไปเองในตัววาด
        fillTable(deptRows, true);
      }
    }

    if (deptRows) {
      function openRow(row) {
        /*
          🔴 หยิบจาก deptShown (ชุดที่ตารางวาดจริง) เท่านั้น
             เพราะตารางอาจถูกกรองหัวคอลัมน์ไว้ — คิดใหม่เองจะได้คนละแถวกับที่ผู้ใช้กด
        */
        var d = deptShown[+row.getAttribute('data-dept-row')];

        if (d) { showDeptPage(d.name, false); }
      }

      deptRows.addEventListener('click', function (e) {
        var row = e.target.closest ? e.target.closest('[data-dept-row]') : null;

        if (row) { openRow(row); }
      });

      // แถวทำหน้าที่เป็นปุ่ม จึงต้องกดด้วยคีย์บอร์ดได้ด้วย
      deptRows.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') { return; }

        var row = e.target.closest ? e.target.closest('[data-dept-row]') : null;

        if (! row) { return; }

        e.preventDefault();
        openRow(row);
      });
    }

    if (deptBack) {
      deptBack.addEventListener('click', function () { showDeptPage(null, true); });
    }

    /*
      🔴 Esc ตอนอยู่หน้าเอกสาร ให้ถอยกลับก่อน ค่อยปิดหน้าต่างในครั้งที่ 2
         ดักช่วง capture เพราะตัวปิดหน้าต่างกลางของระบบดักที่ document เหมือนกัน
    */
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || ! deptOpen) { return; }

      e.stopPropagation();
      showDeptPage(null, true);
    }, true);

    // เปิดหน้าต่างทีไร เริ่มที่รายชื่อแผนกเสมอ
    var deptOpener = root.querySelector('.dept-scroll[data-modal-open]');

    if (deptOpener) {
      deptOpener.addEventListener('click', function () { showDeptPage(null, false); });

      // กรอบตารางไม่ใช่ปุ่มจริง ต้องรับ Enter/Space เอง
      deptOpener.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') { return; }

        e.preventDefault();
        deptOpener.click();
      });
    }


    // ── หน้าต่างใหญ่ — เห็นครบทุกแผนก (มีแต่กราฟ ไม่มีตารางแล้ว) ──
    function renderBig() {
      var list = picked();
      if (! list.length) { return; }

      bigList = list;
      bigBox.innerHTML = draw(list, true);

      /*
        🐛 บั๊กจริง 2026-09-04: พอกรอบกราฟกลายเป็นตัวเลื่อน (overflow) ความสูงขั้นต่ำอัตโนมัติของมัน
           จะกลายเป็น 0 แถวใน modal เลยบีบกราฟให้เตี้ยลง ป้ายชื่อแผนกที่อยู่ใต้แท่งโดนตัดหายหมด
           แก้ด้วยการล็อกความสูงขั้นต่ำเท่ากับความสูงจริงของกราฟ
      */
      var drawn = bigBox.querySelector('svg');

      if (drawn) { bigBox.style.minHeight = drawn.style.height; }

      subOut.textContent = ' · ' + (en() ? 'Count ' : 'จำนวน ')
        + list.length + (en() ? ' departments' : ' แผนก');
    }

    /*
      หน้าที่ 2 — แผนกเดียว: กราฟทางซ้าย เอกสารทางขวา
      🔴 กราฟใช้ชนิดเดียวกับที่กำลังดูอยู่ ผู้ใช้จะได้รู้ว่าเป็นชิ้นที่เพิ่งกดเข้ามา
    */
    function renderOne() {
      if (! openDept || ! oneDraw) { return; }

      var one = data.filter(function (d) { return d.name === openDept; });

      if (! one.length) { return; }

      // กราฟวงกลมต้องเทียบกับทั้งหมด ส่วนแท่งวาดแค่แผนกเดียวก็พอ
      oneDraw.innerHTML = kind === 'pie'
        ? pie(one[0], grandTotal(), false)
        : draw(one, false);

      // 🔴 กรองที่ข้อมูลก่อนวาด — เลขลำดับกับยอดท้ายกระดาษจึงตรงกับที่เห็นเสมอ
      var rows = oneDocFilter ? oneDocFilter.view() : (docsOf[openDept] || []);
      var sum = rows.reduce(function (n, r) { return n + r.amount; }, 0);

      // ยอดของแผนกที่กำลังดู — ไปโผล่ท้ายกระดาษ ไม่ใช่ในตัวหน้าแล้ว
      if (chartFootSum) { chartFootSum.textContent = baht(sum); }

      oneDocs.innerHTML = rows.length
        ? rows.map(function (r, i) {
            // ขีดเส้นคั่นเมื่อขึ้นปีใหม่ — แถวเรียงปีปัจจุบันมาก่อนอยู่แล้ว
            var newYear = i > 0 && rows[i - 1].year !== r.year;

            // ไล่ขึ้นทีละแถว — หน่วงตามลำดับ แต่ไม่ให้แถวท้ายๆ รอนานเกินไป
            return '<tr class="' + (newYear ? 'yr-split' : '') + '"'
                + ' style="animation-delay:' + Math.min(i * 40, 400) + 'ms">'
              + '<td class="col-seq">' + (i + 1) + '</td>'
              + '<td class="col-no">' + (r.year || '—') + '</td>'
              + '<td class="col-no"><span class="doc-no">' + esc(r.model) + '</span></td>'
              + '<td class="col-no"><span class="doc-no">' + esc(r.budgetNo) + '</span></td>'
              + '<td class="txt-left">' + esc(r.title) + '</td>'
              + '<td class="col-money txt-right"><span class="money money-strong">' + baht(r.amount) + '</span></td>'
              + '<td class="col-no soft">' + esc(r.startDate || '—') + '</td>'
              + '<td class="col-no soft">' + esc(r.createdAt || '—') + '</td>'
            + '</tr>';
          }).join('')
        : '<tr><td colspan="8" class="empty">'
          + (oneDocFilter && oneDocFilter.on()
            ? (en() ? 'Nothing matches the filter' : 'ไม่มีรายการที่ตรงกับตัวกรอง')
            : (en() ? 'No budget rows in the ERP' : 'ไม่มีรายการงบใน ERP'))
          + '</td></tr>';
    }

    /** สลับหน้าในหน้าต่างเดียว · dept = null คือกลับไปกราฟรวม */
    function showPage(dept, back) {
      openDept = dept;
      hideTip();

      var into = dept ? pageOne : pageAll;
      var out = dept ? pageAll : pageOne;

      out.hidden = true;
      into.hidden = false;

      // เริ่มอนิเมชันใหม่ทุกครั้ง ไม่งั้นกดสลับหน้ารอบที่ 2 จะไม่ขยับ
      into.classList.remove('is-fwd', 'is-back');
      void into.offsetWidth;
      into.classList.add(back ? 'is-back' : 'is-fwd');

      if (backBtn) { backBtn.hidden = ! dept; }

      // เปลี่ยนแผนก = ล้างตัวกรองหัวคอลัมน์ ไม่งั้นค่าที่ติ๊กของแผนกเก่าทำให้ตารางว่างเปล่า
      if (oneDocFilter) { oneDocFilter.reset(); }

      if (dept) {
        renderOne();
        subOut.textContent = ' · ' + dept;
      } else {
        renderBig();

        // กลับมาหน้ารวม ยอดท้ายกระดาษต้องกลับเป็นยอดของทุกแผนกที่กรองอยู่
        if (chartFootSum) {
          chartFootSum.textContent = baht(picked().reduce(function (n, d) { return n + d.total; }, 0));
        }
      }
    }

    // กดชิ้นกราฟในหน้าต่างใหญ่ = เข้าไปดูแผนกนั้น
    if (bigBox) {
      bigBox.addEventListener('click', function (e) {
        var hit = e.target.closest ? e.target.closest('.c-seg') : null;

        if (! hit) { return; }

        var d = bigList[+hit.getAttribute('data-i')];

        if (d) { showPage(d.name, false); }
      });
    }

    if (backBtn) {
      backBtn.addEventListener('click', function () { showPage(null, true); });
    }

    /*
      🔴 Esc ตอนอยู่หน้าที่ 2 ให้ถอยกลับก่อน ค่อยปิดหน้าต่างในครั้งที่ 2 (แบบเดียวกับกระดิ่ง)
         ต้องดักช่วง capture เพราะตัวปิดหน้าต่างกลางของระบบดักที่ document เหมือนกัน
    */
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || ! openDept) { return; }

      e.stopPropagation();
      showPage(null, true);
    }, true);

    function redraw() {
      hideTip();
      render();
      renderTable();

      // อยู่หน้าไหนก็วาดหน้านั้น ไม่ต้องเด้งผู้ใช้กลับหน้าแรกเวลาสลับภาษา/ชนิดกราฟ
      if (openDept) { renderOne(); } else { renderBig(); }
    }

    /**
     * เปิด/ปิดปุ่มวงกลมตามตัวกรองแผนก
     * 🔴 เลิกกรองแล้วยังค้างอยู่ที่วงกลมไม่ได้ ต้องเด้งกลับเป็นแท่งแนวตั้ง
     */
    function syncKinds() {
      var one = window.BMS.dashDept ? window.BMS.dashDept() !== '' : false;
      var pieBtn = kindBtns.filter(function (b) { return b.getAttribute('data-kind') === 'pie'; })[0];

      if (pieBtn) { pieBtn.disabled = ! one; }

      if (! one && kind === 'pie') { kind = 'bar-v'; }

      kindBtns.forEach(function (b) {
        b.classList.toggle('is-on', b.getAttribute('data-kind') === kind);
      });
    }

    kindBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        if (b.disabled) { return; }

        kind = b.getAttribute('data-kind');
        syncKinds();
        redraw();
      });
    });

    sortBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        order = b.getAttribute('data-sort');

        sortBtns.forEach(function (o) { o.classList.toggle('is-on', o === b); });
        renderTable();
      });
    });

    // ตัวกรองแผนกอยู่นอกการ์ด — การ์ดนี้แค่ฟังแล้ววาดใหม่
    document.addEventListener('dash:filter', function () { syncKinds(); redraw(); });
    document.addEventListener('bms:languagechange', redraw);

    if (openBtn) {
      /*
        วาดกราฟใหญ่ก่อน แล้วปล่อยให้ตัวเปิดหน้าต่างกลางของระบบเปิดเอง
        🔴 ตัวเปิดกลางดักที่ document (ช่วง bubble) ส่วนตัวนี้ผูกที่ปุ่มตรงๆ จึงทำงานก่อนเสมอ
      */
      openBtn.addEventListener('click', function () { hideTip(); showPage(null, false); });
    }

    /*
      ── ตัวกรองหัวคอลัมน์ของตารางในหน้าต่าง ──
      🔴 สร้างครั้งเดียว หัวตารางไม่ได้วาดใหม่ ปุ่มจึงไม่ซ้อนและที่กรองไว้ไม่หลุดตอนวาดแถวใหม่
         (ของเดิมติดตั้งใหม่ทุกครั้งที่วาด ผู้ใช้กรองแล้วโดนล้างทิ้งทันที)
      🔴 ลำดับของ cols ต้องตรงกับ <th> เป๊ะ · null = คอลัมน์นั้นกรองไม่ได้
      🔴 ข้อความที่ใช้เทียบต้องเป็นตัวเดียวกับที่วาดในเซลล์ ไม่งั้นติ๊กแล้วไม่ตรงกับที่เห็น
    */
    if (deptTable) {
      deptFilter = window.BMS.dataFilter({
        table: deptTable,
        rows: deptSorted,
        onChange: function () { fillTable(deptRows, true); },
        cols: [
          null,
          { get: function (d) { return d.name; } },
          { get: deptCount },
          { get: function (d) { return baht(d.total); } },
          { get: deptShare },
        ],
      });
    }

    if (deptDocTable) {
      deptDocFilter = window.BMS.dataFilter({
        table: deptDocTable,
        rows: function () { return docsOf[deptOpen] || []; },
        onChange: renderDeptDocs,
        cols: [
          null,
          null,
          { get: function (r) { return r.year || '—'; } },
          { get: function (r) { return r.model || '—'; } },
          { get: function (r) { return r.budgetNo || '—'; } },
          { get: function (r) { return r.title || '—'; } },
          { get: function (r) { return baht(r.amount); } },
          { get: function (r) { return r.startDate || '—'; } },
          { get: function (r) { return r.createdAt || '—'; } },
        ],
      });
    }

    if (oneDocTable) {
      oneDocFilter = window.BMS.dataFilter({
        table: oneDocTable,
        rows: function () { return docsOf[openDept] || []; },
        onChange: renderOne,
        cols: [
          null,
          { get: function (r) { return r.year || '—'; } },
          { get: function (r) { return r.model || '—'; } },
          { get: function (r) { return r.budgetNo || '—'; } },
          { get: function (r) { return r.title || '—'; } },
          { get: function (r) { return baht(r.amount); } },
          { get: function (r) { return r.startDate || '—'; } },
          { get: function (r) { return r.createdAt || '—'; } },
        ],
      });
    }

    syncKinds();
    render();
    renderTable();
  };
</script>
@endpush
@endonce

{{-- เรียกวาดการ์ดของบริษัทนี้ · ข้อมูลของใครของมัน ไม่ปนกัน --}}
@push('page-script')
<script>
  'use strict';

  window.BMS.budgetChart(document.querySelector('[data-chart-scope="{{ $scope }}"]'), {
    data: @json($byDept),
    // 🔴 หยิบส่วนของบริษัทตัวเองจากก้อนรวมที่หน้าส่งมาแล้ว ไม่ฝังซ้ำ (ก้อนละเมกะไบต์)
    docs: (window.BMS.budgetDocs || {})['{{ $scope }}'] || {},
  });
</script>
@endpush
