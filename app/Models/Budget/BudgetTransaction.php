<?php

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;

/**
 * บัญชีเดินสะพัดของงบ 1 บรรทัด
 *
 * 🔴 เขียนอย่างเดียว ห้ามแก้ย้อนหลัง — จะปรับยอดให้บันทึกรายการใหม่แบบติดลบแทน
 *    ยอดคงเหลือคิดจากผลรวมของตารางนี้เสมอ ไม่มีคอลัมน์ยอดสะสมที่ไหน
 */
class BudgetTransaction extends Model
{
    protected $table = 'budget_transactions';

    public $timestamps = false;

    protected $fillable = [
        'budget_id', 'type', 'amount', 'ref_type', 'ref_id',
        'note', 'created_by', 'created_by_name', 'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public const INITIAL = 'initial';   // ตั้งงบครั้งแรก

    public const ADJUST = 'adjust';     // ปรับเพิ่ม/ลด

    public const RESERVE = 'reserve';   // กันเงินไว้ (เฟสถัดไป ตอนเปิด PR)

    public const RELEASE = 'release';   // คืนเงินที่กันไว้

    public const ACTUAL = 'actual';     // ใช้จริง

    public const TYPE_LABELS = [
        self::INITIAL => ['th' => 'ตั้งงบ', 'en' => 'Initial budget'],
        self::ADJUST => ['th' => 'ปรับงบ', 'en' => 'Adjustment'],
        self::RESERVE => ['th' => 'กันเงิน', 'en' => 'Reserved'],
        self::RELEASE => ['th' => 'คืนเงินที่กันไว้', 'en' => 'Released'],
        self::ACTUAL => ['th' => 'ใช้จริง', 'en' => 'Actual spend'],
    ];

    public function typeLabel(string $lang = 'th'): string
    {
        return self::TYPE_LABELS[$this->type][$lang] ?? $this->type;
    }
}
