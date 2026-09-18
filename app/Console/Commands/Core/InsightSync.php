<?php

namespace App\Console\Commands\Core;

use App\Services\Core\InsightMirror;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * คัดลอกพนักงาน + บัญชีผู้ใช้จาก Insight มาเก็บเป็นมิเรอร์ของ SBMS
 *   php artisan insight:sync            ซิงค์ปกติ
 *   php artisan insight:sync --fresh    ล้างมิเรอร์ทิ้งก่อนดึงใหม่
 *
 * ⚠️ เปลี่ยน INSIGHT_DB_HOST เมื่อไหร่ ต้องใช้ --fresh เสมอ
 *    เพราะมิเรอร์จับคู่ด้วย insight_id ของฐานเดิม พอสลับฐานแล้ว id ชุดใหม่ไม่ตรงของเก่า
 *    จะชน unique (company + employee_code) แล้วซิงค์ล้มกลางคัน
 */
class InsightSync extends Command
{
    protected $signature = 'insight:sync
        {--fresh : ล้างมิเรอร์ทิ้งก่อนดึงใหม่ทั้งหมด}';

    protected $description = 'ซิงค์พนักงานและบัญชีผู้ใช้จาก Supavut Insight (อ่านอย่างเดียว)';

    public function handle(InsightMirror $mirror): int
    {
        if (! $mirror->sourceReachable()) {
            $this->error('ต่อฐานข้อมูลของ Insight ไม่ได้ — เช็ค INSIGHT_DB_HOST ใน .env');

            return self::FAILURE;
        }

        $started = microtime(true);

        try {
            $result = $mirror->sync((bool) $this->option('fresh'));
        } catch (Throwable $e) {
            $this->log('manual', false, $e->getMessage(), null);
            $this->error('ซิงค์ล้มเหลว: '.$e->getMessage());

            return self::FAILURE;
        }

        $seconds = number_format(microtime(true) - $started, 1);

        $this->info('ซิงค์เสร็จใน '.$seconds.' วินาที');
        $this->line('  พนักงาน (employees) : '.$result['employees']['synced'].' แถว · ลบที่หายจากต้นทาง '.$result['employees']['deleted']);
        $this->line('  บัญชี (app_users)   : '.$result['app_users']['synced'].' แถว · ลบที่หายจากต้นทาง '.$result['app_users']['deleted']);

        $this->log('manual', true, 'ซิงค์สำเร็จใน '.$seconds.' วินาที', $result);

        return self::SUCCESS;
    }

    /** @param  array<string,mixed>|null  $detail */
    private function log(string $source, bool $ok, string $message, ?array $detail): void
    {
        DB::table('sync_logs')->insert([
            'source' => $source,
            'ok' => $ok,
            'message' => mb_substr($message, 0, 1000),
            'detail' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            'ran_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
