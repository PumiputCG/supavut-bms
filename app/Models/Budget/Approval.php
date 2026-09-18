<?php

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;

/**
 * หนึ่งขั้นของสายอนุมัติ (Central Approval Engine — DECISIONS ข้อ 5)
 *
 * ออกแบบให้ใช้กับเอกสารชนิดอื่นได้ด้วย จึงมี doc_type ไม่ผูกกับ invest อย่างเดียว
 *
 * 🔴 ชื่อ/ตำแหน่ง/ลายเซ็น เก็บเป็น "สำเนา ณ ตอนนั้น" ไม่ join เอาทีหลัง
 *    คนลาออกแล้วบัญชีหาย เอกสารที่อนุมัติไปแล้วต้องยังพิมพ์ออกมาได้เหมือนเดิม
 */
class Approval extends Model
{
    protected $table = 'budget_approvals';

    protected $fillable = [
        // 🔴 round ต้องอยู่ในนี้ด้วย ไม่งั้น Eloquent ทิ้งค่าเงียบๆ แล้วรอบใหม่ทับรอบเก่า
        'doc_type', 'doc_id', 'round', 'step', 'action', 'is_final',
        'employee_code', 'employee_name', 'employee_name_en', 'position', 'department',
        'status', 'acted_at', 'comment', 'signature',
    ];

    protected $casts = ['acted_at' => 'datetime', 'is_final' => 'boolean'];

    public const DOC_INVEST = 'invest';

    /*
      บทบาทของแต่ละแถว
      🔴 เจ้าของสั่งแยกให้ชัด 2026-09-03 — "รับทราบ" ไม่ใช่การเซ็น
         ACK      = ผู้รับทราบ ได้เห็นเอกสาร **เซ็นไม่ได้ และไม่ได้ขวางเอกสาร**
         APPROVE  = ผู้อนุมัติ เซ็นจริง ลายเซ็นประทับลงเอกสาร เซ็นครบแล้วระบบสร้าง Budget
    */
    public const ACK = 'acknowledge';

    public const APPROVE = 'approve';

    // สถานะของขั้น
    public const WAITING = 'WAITING';

    public const DONE = 'DONE';

    public const REJECTED = 'REJECTED';

    /** ผู้รับทราบ — ได้รับเอกสารแล้ว ไม่ต้องทำอะไรต่อ */
    public const NOTIFIED = 'NOTIFIED';

    public const ACTION_LABELS = [
        self::ACK => ['th' => 'รับทราบ', 'en' => 'For information'],
        self::APPROVE => ['th' => 'ลงนามอนุมัติ', 'en' => 'Sign to approve'],
    ];

    public const STATUS_LABELS = [
        self::WAITING => ['th' => 'รอดำเนินการ', 'en' => 'Waiting'],
        self::DONE => ['th' => 'เรียบร้อย', 'en' => 'Done'],
        self::REJECTED => ['th' => 'ไม่อนุมัติ', 'en' => 'Rejected'],
        self::NOTIFIED => ['th' => 'ส่งให้รับทราบแล้ว', 'en' => 'Sent for information'],
    ];

    /** แถวนี้ต้องมีลายเซ็นไหม — มีแต่ผู้อนุมัติเท่านั้นที่เซ็น */
    public function isSigner(): bool
    {
        return $this->action === self::APPROVE;
    }

    public function actionLabel(string $lang = 'th'): string
    {
        return self::ACTION_LABELS[$this->action][$lang] ?? $this->action;
    }

    public function statusLabel(string $lang = 'th'): string
    {
        return self::STATUS_LABELS[$this->status][$lang] ?? $this->status;
    }

    /**
     * ผลของขั้นนี้ "ตามที่ควรแสดงบนหน้าจอ"
     *
     * 🔴 เจ้าของแจ้ง 2026-09-10: เอกสารถูกตีกลับที่ลำดับ 2 แต่ลำดับ 3, 4
     *    ยังขึ้นว่า "รออนุมัติ" ทั้งที่เรื่องจบไปแล้ว ไม่มีทางได้เซ็นอีก
     *    → เปลี่ยนเป็น "ไม่ได้ดำเนินการ" พื้นเทา
     *
     * 🔴 ตัวแปลงผลอยู่ที่นี่ที่เดียว ทั้งตารางเส้นทางและสำเนาเรียนในเอกสารเรียกตัวนี้
     *    ห้ามไปเขียน match สถานะซ้ำในหน้าใดหน้าหนึ่ง ไม่งั้นคำที่ผู้ใช้เห็นจะไม่ตรงกัน
     *
     * @param  string|null  $docStatus  สถานะของเอกสารทั้งใบ (Invest::*)
     * @return array{cls:string, th:string, en:string}
     */
    public function outcome(?string $docStatus = null): array
    {
        /*
          🔴 ผู้รับทราบไม่ต้องเซ็น จึงไม่มีคำว่า "รออนุมัติ" (เจ้าของสั่ง 2026-09-16)
             เขาเห็นเอกสารตั้งแต่ตอนส่งแล้ว ผลของเขาไม่ขึ้นกับสถานะของเอกสารทั้งใบ
             ต้องเช็คก่อนทุกเงื่อนไข ไม่งั้นจะตกไปเข้ากรณี "รออนุมัติ" ซึ่งอ่านผิดว่าค้างที่เขา
        */
        if ($this->action === self::ACK) {
            /*
              🔴 มีสถานะเดียว "รับทราบ" ไม่มี "รอการรับทราบ" (เจ้าของถาม AI ตอบ · เคาะ 2026-09-16)
                 เพราะผู้รับทราบไม่มีงานต้องทำ เอกสารไม่ได้รออะไรจากเขา
                 ถ้ามีสถานะ "รอ…" จะอ่านเหมือนเอกสารค้างที่เขา ทั้งที่เดินต่อไปแล้ว
                 และต้องไปตามเก็บว่า "เปิดอ่านหรือยัง" ซึ่งเป็นภาระที่ไม่ได้ประโยชน์
            */
            return ['cls' => 'st-ack', 'th' => 'รับทราบ', 'en' => 'Acknowledged'];
        }

        if ($this->status === self::DONE) {
            return ['cls' => 'st-approved', 'th' => 'อนุมัติแล้ว', 'en' => 'Approved'];
        }

        if ($this->status === self::REJECTED) {
            return ['cls' => 'st-rejected', 'th' => 'ไม่อนุมัติ', 'en' => 'Rejected'];
        }

        // เอกสารจบไปแล้วโดยที่คนนี้ยังไม่ได้เซ็น = เรื่องไปไม่ถึงเขา
        if ($docStatus === Invest::REJECTED) {
            return ['cls' => 'st-none', 'th' => 'ไม่ได้ดำเนินการ', 'en' => 'No action'];
        }

        return ['cls' => 'st-pending', 'th' => 'รออนุมัติ', 'en' => 'Pending approval'];
    }
}
