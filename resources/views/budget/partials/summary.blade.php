{{--
  แถบสรุปหัวตาราง — เห็นภาพรวมก่อนไล่ดูรายฉบับ

  🔴 ใช้ร่วมทุกหน้าที่มีตารางเอกสาร (เจ้าของสั่งให้มีเหมือนกัน 2026-09-10)
     คำและลำดับจึงตรงกันทุกหน้า ไม่ต้องไปเขียนซ้ำในแต่ละหน้า

  🔴 ตัวเลขต้องคิดจาก "ชุดที่ผ่านตัวกรองแล้ว" เสมอ
     กรองอยู่แล้วตัวเลขข้างบนยังเป็นของทั้งหมด ผู้ใช้จะอ่านผิดทันที (บทเรียน 2026-09-07)

  $summary       ['total','draft','pending','approved','rejected','waitRegister','registered']
                 ไม่มีคีย์ไหนก็ถือเป็น 0
  $showApproval  (ไม่บังคับ · ค่าเริ่มต้น true) false = ไม่ต้องมีช่องฝั่งการอนุมัติ
                 หน้า "ลงทะเบียน" มีแต่ใบที่อนุมัติครบแล้ว ตัวเลขพวกนั้นจึงไม่บอกอะไร
  $showDraft     (ไม่บังคับ) true = มีช่อง "ร่าง" ด้วย
                 หน้ารับทราบ/อนุมัติไม่ต้องมี เพราะร่างยังไม่ถูกส่ง ไม่มีทางโผล่ในกล่องนั้น
  $showRegister  (ไม่บังคับ) true = มีช่องฝั่งบัญชี (รอลงทะเบียน · ลงทะเบียนแล้ว)
  $amount        (ไม่บังคับ) ['th','key','value'] ช่องยอดเงินท้ายแถบ
--}}
<div class="sum">
  <span class="sum-cell">
    <b>{{ number_format($summary['total'] ?? 0) }}</b>
    <span data-i18n="budget.hist.total">เอกสารทั้งหมด</span>
  </span>

  @if ($showApproval ?? true)
    @if ($showDraft ?? false)
      <span class="sum-cell">
        <b>{{ number_format($summary['draft'] ?? 0) }}</b>
        <span data-i18n="budget.hist.draft">ร่าง</span>
      </span>
    @endif

    <span class="sum-cell is-run">
      <b>{{ number_format($summary['pending'] ?? 0) }}</b>
      <span data-i18n="budget.hist.pending">รออนุมัติ</span>
    </span>

    <span class="sum-cell is-ok">
      <b>{{ number_format($summary['approved'] ?? 0) }}</b>
      <span data-i18n="budget.hist.approved">อนุมัติแล้ว</span>
    </span>

    <span class="sum-cell is-no">
      <b>{{ number_format($summary['rejected'] ?? 0) }}</b>
      <span data-i18n="budget.hist.rejected">ไม่อนุมัติ</span>
    </span>
  @endif

  {{--
    ── ฝั่งบัญชี ──
    🔴 คั่นด้วยเส้นให้เห็นว่าเป็นคนละเรื่องกับฝั่งการอนุมัติ แต่ไม่ตีกรอบ
       (เจ้าของสั่ง 2026-09-10 — "อาจจะแบ่งเส้นไว้ แต่ไม่ต้องตีกรอบ")
  --}}
  @if ($showRegister ?? false)
    <span @class(['sum-cell', 'is-run', 'has-split' => ($showApproval ?? true)])>
      <b>{{ number_format($summary['waitRegister'] ?? 0) }}</b>
      <span data-i18n="budget.status.waitRegister">รอลงทะเบียน</span>
    </span>

    <span class="sum-cell is-ok">
      <b>{{ number_format($summary['registered'] ?? 0) }}</b>
      <span data-i18n="budget.status.registered">ลงทะเบียนแล้ว</span>
    </span>
  @endif

  @if (! empty($amount))
    <span class="sum-cell is-ok has-split">
      <b>{{ number_format($amount['value'], 2) }}</b>
      <span data-i18n="{{ $amount['key'] }}">{{ $amount['th'] }}</span>
    </span>
  @endif
</div>
