<?php

namespace App\Models\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * หนึ่งบรรทัดของประวัติการใช้งานระบบ
 *
 * 🔴 เขียนอย่างเดียว ห้ามแก้ย้อนหลัง — ประวัติที่แก้ได้ใช้ตรวจสอบไม่ได้
 *    จึงไม่มี updated_at และไม่ควรมีหน้าให้แก้ไข
 */
class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    public $timestamps = false;   // มีแค่ created_at และเซ็ตเองตอนบันทึก

    protected $fillable = [
        'employee_code', 'actor_name', 'event', 'subject',
        'detail_th', 'detail_en', 'ip', 'method', 'url', 'agent', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    /** ป้ายกำกับของเหตุการณ์ 2 ภาษา — ใช้ที่หน้าจอและตัวกรอง */
    public const EVENTS = [
        'login' => ['th' => 'เข้าสู่ระบบ', 'en' => 'Signed in'],
        'login_failed' => ['th' => 'เข้าสู่ระบบไม่สำเร็จ', 'en' => 'Sign-in failed'],
        'logout' => ['th' => 'ออกจากระบบ', 'en' => 'Signed out'],
        'page_view' => ['th' => 'เปิดหน้า', 'en' => 'Page opened'],
        'admin_added' => ['th' => 'เพิ่มผู้ดูแลระบบ', 'en' => 'Administrator added'],
        'admin_removed' => ['th' => 'ถอดผู้ดูแลระบบ', 'en' => 'Administrator removed'],
        'positions_saved' => ['th' => 'แก้ตำแหน่งที่เข้าระบบได้', 'en' => 'Sign-in positions changed'],
        'module_access_saved' => ['th' => 'แก้สิทธิ์เข้าถึงโมดูล', 'en' => 'Module access changed'],
        'function_access_saved' => ['th' => 'แก้สิทธิ์หัวข้อย่อย', 'en' => 'Function access changed'],
        'budget_roles_saved' => ['th' => 'แก้บทบาทในงบประมาณ', 'en' => 'Budget roles changed'],
        'invest_created' => ['th' => 'สร้างข้อเสนอขอตั้งงบ', 'en' => 'Budget proposal created'],
        'invest_updated' => ['th' => 'แก้ไขข้อเสนอขอตั้งงบ', 'en' => 'Budget proposal updated'],
        'invest_file_removed' => ['th' => 'ลบไฟล์แนบข้อเสนอ', 'en' => 'Proposal attachment removed'],
        'invest_deleted' => ['th' => 'ลบข้อเสนอขอตั้งงบ', 'en' => 'Budget proposal deleted'],
        'invest_submitted' => ['th' => 'ส่งข้อเสนอเข้าอนุมัติ', 'en' => 'Proposal submitted'],
        'invest_acknowledged' => ['th' => 'รับทราบข้อเสนอ', 'en' => 'Proposal acknowledged'],
        'invest_approved' => ['th' => 'อนุมัติข้อเสนอ', 'en' => 'Proposal approved'],
        'invest_rejected' => ['th' => 'ไม่อนุมัติข้อเสนอ', 'en' => 'Proposal rejected'],
        'budget_status_changed' => ['th' => 'เปลี่ยนสถานะงบ', 'en' => 'Budget status changed'],
    ];

    public function label(string $lang = 'th'): string
    {
        return self::EVENTS[$this->event][$lang] ?? $this->event;
    }

    /** เหตุการณ์ที่ถือว่าเป็น "การกระทำ" ไม่ใช่แค่เปิดหน้าดู */
    public static function actionEvents(): array
    {
        return [
            'admin_added', 'admin_removed', 'positions_saved', 'module_access_saved', 'function_access_saved', 'budget_roles_saved',
            'invest_created', 'invest_updated', 'invest_file_removed', 'invest_deleted', 'invest_submitted',
            'invest_acknowledged', 'invest_approved', 'invest_rejected',
            'budget_status_changed',
        ];
    }
}
