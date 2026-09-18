<?php

namespace App\Http\Middleware;

use App\Services\Audit\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * บันทึกว่าใครเปิดหน้าไหนบ้าง — ใช้ต่อจาก Authenticate เสมอ
 *
 * บันทึกเฉพาะ **GET ที่เป็นหน้าจริง** เท่านั้น
 * ไม่บันทึก: POST/PUT/DELETE (ตัวคำสั่งเองบันทึกเองอยู่แล้ว พร้อมรายละเอียดที่ดีกว่า)
 *            คำขอ JSON เช่นช่องค้นหา (ยิงทุกครั้งที่พิมพ์ จะท่วมตาราง)
 *            route ที่ไม่มีชื่อ และไฟล์ static
 */
class RecordPageView
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            $this->logger->record('page_view', [
                'th' => 'เปิดหน้า '.$this->pageName($request),
                'en' => 'Opened '.$this->pageName($request),
            ], $request->route()?->getName());
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->expectsJson()) {
            return false;
        }

        // เปิดหน้าไม่สำเร็จ (redirect / 403 / 500) ไม่ใช่การ "เข้าหน้า" จริง
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        return $request->route()?->getName() !== null;
    }

    private function pageName(Request $request): string
    {
        return '/'.ltrim($request->path(), '/');
    }
}
