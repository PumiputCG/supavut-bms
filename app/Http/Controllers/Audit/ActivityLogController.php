<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\Audit\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewContract;

/**
 * ประวัติการใช้งานระบบ — อ่านอย่างเดียว ไม่มีหน้าแก้ไขและไม่มีปุ่มลบ
 *
 * 🔴 ประวัติที่แก้ได้ใช้ตรวจสอบไม่ได้ — ถ้าจะล้างของเก่าให้ทำผ่าน scheduled job ที่ลบตามอายุ
 *    ไม่ใช่ให้คนกดลบทีละแถวจากหน้าเว็บ
 */
class ActivityLogController extends Controller
{
    private const PER_PAGE = 50;

    /** ทุกเหตุการณ์ */
    public function index(Request $request): ViewContract
    {
        return $this->render($request, 'all');
    }

    /** เฉพาะการเข้า-ออกระบบ */
    public function signIns(Request $request): ViewContract
    {
        return $this->render($request, 'signin');
    }

    private function render(Request $request, string $scope): ViewContract
    {
        // ตัวกรองแยกตามคอลัมน์ (เจ้าของสั่ง 2026-09-03) — ช่องกรองอยู่ใต้หัวตารางของคอลัมน์นั้นเลย
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:40'],
            'who' => ['nullable', 'string', 'max:60'],
            'detail' => ['nullable', 'string', 'max:60'],
            'ip' => ['nullable', 'string', 'max:45'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = ActivityLog::query()->orderByDesc('created_at');

        // หน้า "การเข้าสู่ระบบ" ตัดเรื่องอื่นออกให้หมด จะได้ไล่ดูได้เร็ว
        if ($scope === 'signin') {
            $query->whereIn('event', ['login', 'logout', 'login_failed']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        // คอลัมน์ "ผู้ใช้" — ค้นทั้งรหัสพนักงานและชื่อ
        if (! empty($filters['who'])) {
            $term = $this->like($filters['who']);
            $query->where(function ($q) use ($term) {
                $q->where('employee_code', 'like', $term)
                    ->orWhere('actor_name', 'like', $term);
            });
        }

        // คอลัมน์ "รายละเอียด" — ค้นทั้ง 2 ภาษา ผู้ใช้จะพิมพ์ไทยหรืออังกฤษก็เจอ
        if (! empty($filters['detail'])) {
            $term = $this->like($filters['detail']);
            $query->where(function ($q) use ($term) {
                $q->where('detail_th', 'like', $term)
                    ->orWhere('detail_en', 'like', $term);
            });
        }

        // คอลัมน์ "มาจาก" — ค้นทั้ง IP และชื่อเบราว์เซอร์
        if (! empty($filters['ip'])) {
            $term = $this->like($filters['ip']);
            $query->where(function ($q) use ($term) {
                $q->where('ip', 'like', $term)
                    ->orWhere('agent', 'like', $term);
            });
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return View::make('audit.index', [
            'scope' => $scope,
            'rows' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'events' => $scope === 'signin'
                ? array_intersect_key(ActivityLog::EVENTS, array_flip(['login', 'logout', 'login_failed']))
                : ActivityLog::EVENTS,
            'summary' => $this->summary($scope),
        ]);
    }

    /** เตรียมคำค้นแบบ LIKE — หนี % และ _ เพื่อไม่ให้ผู้ใช้พิมพ์แล้วกลายเป็น wildcard เอง */
    private function like(string $term): string
    {
        return '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';
    }

    /**
     * ตัวเลขสรุปด้านบน — นับจากทั้งตาราง ไม่ใช่เฉพาะหน้าที่เปิดอยู่
     *
     * @return array<string,int>
     */
    private function summary(string $scope): array
    {
        $base = fn () => ActivityLog::query()
            ->when($scope === 'signin', fn ($q) => $q->whereIn('event', ['login', 'logout', 'login_failed']));

        return [
            'total' => $base()->count(),
            'today' => $base()->whereDate('created_at', now()->toDateString())->count(),
            'people' => $base()->whereNotNull('employee_code')->distinct()->count('employee_code'),
            'failed' => ActivityLog::where('event', 'login_failed')->count(),
        ];
    }
}
