<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\Notification;
use App\Services\Core\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * กระดิ่งแจ้งเตือน — ทำเครื่องหมายว่าอ่านแล้ว
 *
 * 🔴 ลบแจ้งเตือนไม่ได้ ทำได้แค่ทำเครื่องหมายว่าอ่าน
 *    เก็บไว้เป็นร่องรอยว่าเคยแจ้งอะไรไปแล้ว (แนวเดียวกับประวัติการใช้งานระบบ)
 */
class NotificationController extends Controller
{
    public function __construct(private readonly Notifier $notify) {}

    /**
     * จำนวนที่ยังไม่อ่านล่าสุด — ส่งกลับให้หน้าเว็บทาสีเลขแดงใหม่ทั้งหน้า
     *
     * 🔴 ต้องส่งครบทั้ง 3 ชั้น (กระดิ่ง · เมนู/เมนูย่อย · รายเอกสาร)
     *    ไม่งั้นกดอ่านแล้วเลขแดงบางจุดค้าง (เจ้าของแจ้ง 2026-09-07)
     */
    private function counts($me): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'unread' => $this->notify->unreadCount($me),
            'map' => $this->notify->unreadMap($me),
            'docs' => $this->notify->unreadDocs($me),
        ]);
    }

    /** เรียก Notifier แล้วล้างแคชในคำขอเดียวกัน ไม่งั้นได้ตัวเลขก่อนอ่าน */
    private function markRead($me, ?string $moduleId, ?string $functionKey): void
    {
        $this->notify->markRead($me, $moduleId, $functionKey);
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'all' => ['nullable', 'boolean'],
            'module_id' => ['nullable', 'string', 'max:50'],
            'function_key' => ['nullable', 'string', 'max:60'],
        ]);

        $me = app('current_user');

        // อ่านทีละเรื่อง — ต้องเป็นของตัวเองเท่านั้น
        if (! empty($data['id'])) {
            $n = Notification::where('employee_code', $me->employee_code)
                ->whereKey($data['id'])
                ->first();

            if ($n) {
                $n->update(['read_at' => now()]);
            }

            return $this->counts($me);
        }

        $this->markRead(
            $me,
            $data['module_id'] ?? null,
            $data['function_key'] ?? null,
        );

        return $this->counts($me);
    }
}
