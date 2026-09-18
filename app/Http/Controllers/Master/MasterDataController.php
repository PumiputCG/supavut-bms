<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Services\Master\MasterData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewContract;

/**
 * ข้อมูลหลักของระบบ (Master Data)
 *
 * หน้าแรกคือสรุปว่ามีชุดข้อมูลอะไร แต่ละชุดมีกี่รายการ และใครเป็นเจ้าของข้อมูล
 * ชุดที่มิเรอร์มาจาก Insight เปิดดูรายการจริงได้ · ชุดที่ SBMS ต้องสร้างเองยังไม่มีตาราง
 */
class MasterDataController extends Controller
{
    public function __construct(private readonly MasterData $master) {}

    public function index(): ViewContract
    {
        return View::make('master.index', ['sets' => $this->master->sets()]);
    }

    /**
     * รายชื่อพนักงานสำหรับหน้าต่างซ้อน — โหลดทีละหน้า
     *
     * ไม่วาดทั้ง 1,500+ คนมากับหน้าเลย เพราะรูปโปรไฟล์จะยิงพร้อมกันทีเดียวจนหน้าค้าง
     */
    public function employees(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return response()->json($this->master->employees(
            (string) ($data['q'] ?? ''),
            (int) ($data['page'] ?? 1),
        ));
    }

    public function departments(): ViewContract
    {
        return $this->list('master.department', 'แผนก', $this->master->departments());
    }

    public function positions(): ViewContract
    {
        return $this->list('master.position', 'ตำแหน่งงาน', $this->master->positions());
    }

    public function branches(): ViewContract
    {
        return $this->list('master.branch', 'สาขา', $this->master->branches());
    }

    private function list(string $titleKey, string $titleTh, $rows): ViewContract
    {
        return View::make('master.list', [
            'titleKey' => $titleKey,
            'titleTh' => $titleTh,
            'rows' => $rows,
        ]);
    }
}
