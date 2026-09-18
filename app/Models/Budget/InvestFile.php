<?php

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** เอกสารแนบของข้อเสนอ — เก็บไฟล์จริงใน private storage และเปิดผ่าน route ที่ตรวจสิทธิ์ */
class InvestFile extends Model
{
    protected $table = 'budget_invest_files';

    protected $fillable = ['invest_id', 'original_name', 'path', 'mime', 'size', 'uploaded_by'];

    /**
     * URL เปิดไฟล์ต้องผ่าน Controller เสมอ ห้ามคืน public storage URL โดยตรง
     * เพราะไฟล์งบประมาณต้องตรวจว่าเป็นเจ้าของหรืออยู่ในสายเอกสารก่อนทุกครั้ง
     */
    public function url(): string
    {
        return route('budget.file.download', $this);
    }

    public function invest(): BelongsTo
    {
        return $this->belongsTo(Invest::class, 'invest_id');
    }

    /** ขนาดไฟล์แบบอ่านง่าย */
    public function sizeText(): string
    {
        $bytes = (int) $this->size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return number_format(max($bytes / 1024, 0.1), 1).' KB';
    }
}
