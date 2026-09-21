<?php

namespace App\Console\Commands\Core;

use App\Jobs\Core\SendNotificationEmail;
use App\Models\Core\Notification;
use App\Services\Core\InsightMirror;
use Illuminate\Console\Command;
use Throwable;

/**
 * เก็บตกอีเมลที่ยังไม่ได้ส่ง เพราะตอนนั้นผู้รับยังไม่มีอีเมล
 *
 * 🔴 เจ้าของสั่งไว้ 2026-09-21:
 *    "ถ้าบัญชีนั้นไม่มีเมลก็ไม่เป็นไร … แต่ถ้าเขากลับมาเพิ่มเมลก็ให้เด้งเหมือนเดิม"
 *
 * วิธีคิด: ถามอีเมลล่าสุดจาก **Insight** (ไม่ใช่มิเรอร์ที่ค้างอยู่) ด้วยคิวรีเดียว
 * แล้วสั่งส่งเฉพาะคนที่ "ตอนนี้มีอีเมลแล้ว" — คนที่ยังไม่มีก็ไม่ถูกสั่งงานซ้ำทุกนาทีให้เปลืองคิว
 *
 * ขอบเขตที่จงใจจำกัดไว้:
 *   - เฉพาะใบที่ **ยังไม่ได้อ่าน** — อ่าน/ลงมือไปแล้วก็ไม่ต้องตามไปเตือนทางอีเมลอีก
 *   - เฉพาะใบที่ยังไม่เก่าเกิน `--days` วัน — กันคนที่เพิ่งเพิ่มอีเมลแล้วโดนเมลย้อนหลังถล่ม
 *   - ส่งได้ไม่เกิน `--limit` ใบต่อรอบ — รอบถัดไป (อีก 1 นาที) ค่อยทำต่อ
 */
class MailPendingNotifications extends Command
{
    protected $signature = 'bms:mail-pending {--days=7 : ย้อนหลังได้ไม่เกินกี่วัน} {--limit=100 : ส่งได้ไม่เกินกี่ใบต่อรอบ}';

    protected $description = 'ส่งอีเมลของแจ้งเตือนที่ค้างอยู่ ให้คนที่เพิ่งมาเพิ่มอีเมลที่ Insight';

    public function handle(InsightMirror $mirror): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));

        $pending = Notification::query()
            ->where('wants_mail', true)
            ->whereNull('emailed_at')
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('id')
            // ดึงมามากกว่าโควตา เพราะส่วนใหญ่จะเป็นคนที่ยังไม่มีอีเมลและถูกข้ามไป
            ->limit($limit * 5)
            ->get(['id', 'employee_code']);

        if ($pending->isEmpty()) {
            return self::SUCCESS;
        }

        try {
            $emails = $mirror->emailsFor($pending->pluck('employee_code')->all());
        } catch (Throwable $e) {
            /*
              🔴 ต่อ Insight ไม่ติด = ยังไม่ถึงเวลา ไม่ใช่ความล้มเหลว
                 คืนค่าสำเร็จ เพื่อไม่ให้ Scheduled Task ขึ้นสถานะพังทุกนาทีตอน Insight ปิดปรับปรุง
            */
            $this->warn('อ่านอีเมลจาก Insight ไม่ได้ ข้ามรอบนี้ไปก่อน: '.$e->getMessage());

            return self::SUCCESS;
        }

        $queued = 0;

        foreach ($pending as $note) {
            if ($queued >= $limit) {
                break;
            }

            if (! isset($emails[(string) $note->employee_code])) {
                continue;
            }

            SendNotificationEmail::dispatch($note->id);
            $queued++;
        }

        // เงียบเมื่อไม่มีอะไรทำ — คำสั่งนี้วิ่งทุกนาที ถ้าพูดทุกรอบ log จะบวมเปล่าๆ
        if ($queued > 0) {
            $this->info('เข้าคิวอีเมลที่ค้างอยู่ '.$queued.' ใบ');
        }

        return self::SUCCESS;
    }
}
