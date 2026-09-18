<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget\Budget;
use App\Models\Budget\Invest;
use App\Services\Access\AccessService;
use App\Services\Budget\DocGroupTabs;
use App\Support\TableFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewContract;

/**
 * ประวัติงบประมาณ — เอกสารขอตั้งงบทุกฉบับ ไม่ว่าจบยังไง
 *
 * ต่างจากหน้าอื่นในโมดูลตรงที่ **ไม่กรองตามบทบาทของผู้ดู**
 *   เสนอ Invest        เห็นเฉพาะที่ตัวเองสร้าง/แก้ได้
 *   รับทราบ/อนุมัติ     เห็นเฉพาะที่มีชื่อตัวเองอยู่
 *   ลงทะเบียน เห็นเฉพาะงบที่อนุมัติผ่านแล้ว
 *   🔴 ประวัติงบประมาณ  เห็น **ทุกฉบับ ทุกสถานะ** — เป็นหน้าไว้ตรวจย้อนหลัง
 *
 * 🔴 สิทธิ์เข้าหน้านี้คุมที่ /access/modules หัวข้อ "ประวัติงบประมาณ" (middleware bms.fn)
 *    ไม่ต้องเขียนเงื่อนไขสิทธิ์ซ้ำในนี้
 */
class HistoryController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly DocGroupTabs $groupTabs,
    ) {}

    public function index(Request $request): ViewContract
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2500', 'max:2600'],
        ]);

        $query = Invest::query()
            ->with(['approvals', 'budget', 'group'])
            ->when(! empty($filters['year']), fn ($q) => $q->where('fiscal_year', $filters['year']))
            ->orderByDesc('id');

        /*
          แท็บกลุ่มเอกสาร (เจ้าของสั่ง 2026-09-17)
          🔴 นับก่อนกรองกลุ่ม ทุกแท็บจะได้โชว์ยอดของตัวเอง · แล้วค่อยกรองตามแท็บที่เลือก
        */
        $groupTabs = $this->groupTabs->tabs($this->groupTabs->countQuery($query), $this->groupTabs->picked($request));
        $this->groupTabs->applyQuery($query, $groupTabs['picked']);

        // ตัวกรองรายคอลัมน์ทำที่เซิร์ฟเวอร์ — กรองได้ครบทุกหน้า (เจ้าของสั่ง 2026-09-04)
        $map = self::filterMap();
        $options = TableFilter::options(clone $query, $map);
        $picked = TableFilter::picked($request, $map);
        TableFilter::apply($query, $request, $map);

        /*
          🔴 ต้องโคลนคิวรีไว้ "ก่อน" แบ่งหน้า
             paginate() ใส่ limit/offset ลงในคิวรีตัวเดิม พอเอาไปทำ subquery ต่อ
             MariaDB จะฟ้อง "LIMIT & IN/ALL/ANY/SOME subquery" (บั๊กจริง 2026-09-07)
        */
        $forSummary = clone $query;

        $rows = $query->paginate(30)->withQueryString();

        return View::make('budget.history.index', [
            'rows' => $rows,
            'filters' => $filters,
            'filterOptions' => $options,
            'filterPicked' => $picked,
            'groupTabs' => $groupTabs,
            'years' => $this->years(),
            // 🔴 แถบสรุปต้องคิดจากชุดที่ผ่านตัวกรองแล้ว ไม่ใช่ทั้งตาราง (เจ้าของสั่ง 2026-09-07)
            'summary' => $this->summary($forSummary),
            // รูปคนในสายเอกสาร — ดึงรวดเดียวให้เส้นทางเอกสารใช้ กัน N+1
            'signerPhotos' => $this->photosFor($rows->getCollection()),
        ]);
    }

    // ── ภายใน ───────────────────────────────────────────────────────

    /**
     * คอลัมน์ที่กรองได้ของหน้านี้
     *
     * @return array<string,array<string,mixed>>
     */
    private static function filterMap(): array
    {
        return [
            'doc' => ['column' => 'doc_no'],
            'year' => ['column' => 'fiscal_year'],
            'title' => ['column' => 'title'],
            'dept' => ['column' => 'dept_name'],
            'amount' => ['column' => 'amount', 'format' => 'money'],
            'status' => ['column' => 'approval_status', 'labels' => Invest::STATUS_LABELS],
            /*
              🔴 สถานะการลงทะเบียนอยู่ในตาราง budgets ไม่ใช่ตารางหลักของหน้านี้
                 จึงต้องบอก relation ให้ตัวกรองรู้ (ดู App\Support\TableFilter)
                 ใบที่ยังไม่อนุมัติจะไม่มีงบ — กรอง "—" = ยังไม่มีงบ
            */
            'register' => [
                'column' => 'budget_status',
                'relation' => 'budget',
                'labels' => Budget::STATUS_LABELS,
            ],
        ];
    }

    /** ปีงบที่มีเอกสารอยู่จริง — ไม่ต้องโชว์ปีที่ยังไม่มีอะไร */
    private function years(): array
    {
        return Invest::query()
            ->select('fiscal_year')
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year')
            ->all();
    }

    /**
     * สรุปหัวตาราง — นับตามสถานะ + รวมวงเงินที่อนุมัติจริง
     *
     * 🔴 คิดจาก **ชุดที่ผ่านตัวกรองแล้ว** เสมอ (เจ้าของสั่ง 2026-09-07)
     *    กรองแผนก IT แล้วตัวเลขข้างบนยังเป็นของทั้งบริษัท ผู้ใช้จะอ่านผิดทันที
     *
     * 🔴 ต้อง reorder() ก่อนใช้ groupBy — คิวรีหลักมี orderByDesc('id') ติดมา
     *    MySQL ไม่ยอมให้ ORDER BY คอลัมน์ที่ไม่ได้อยู่ใน SELECT ตอนจัดกลุ่ม
     *
     * @param  Builder  $query  คิวรีที่ใส่ตัวกรองครบแล้ว
     * @return array<string,mixed>
     */
    private function summary($query): array
    {
        $byStatus = (clone $query)->reorder()
            ->selectRaw('approval_status, COUNT(*) AS n')
            ->groupBy('approval_status')
            ->pluck('n', 'approval_status')
            ->all();

        /*
          วงเงินที่อนุมัติจริงอยู่ในตาราง budgets — เอาเฉพาะก้อนที่ผูกกับเอกสารในชุดที่กรอง
          (เอกสารที่ยังไม่อนุมัติจะไม่มีก้อนงบ ยอดจึงเป็น 0 เองโดยไม่ต้องกรองสถานะซ้ำ)
        */
        $amount = (float) Budget::query()
            ->whereIn('invest_id', (clone $query)->reorder()->select('budget_invests.id'))
            ->sum('approved_amount');

        /*
          นับสถานะการลงทะเบียนของงบที่ผูกกับเอกสารในชุดที่กรอง (เจ้าของสั่ง 2026-09-10)
          เอกสารที่ยังไม่อนุมัติจะไม่มีงบ จึงไม่ถูกนับในทั้ง 2 ช่องนี้เอง
        */
        $byRegister = Budget::query()
            ->whereIn('invest_id', (clone $query)->reorder()->select('budget_invests.id'))
            ->selectRaw('budget_status, COUNT(*) AS n')
            ->groupBy('budget_status')
            ->pluck('n', 'budget_status')
            ->all();

        return [
            'total' => array_sum($byStatus),
            'draft' => $byStatus[Invest::DRAFT] ?? 0,
            'pending' => $byStatus[Invest::PENDING] ?? 0,
            'approved' => $byStatus[Invest::APPROVED] ?? 0,
            'rejected' => $byStatus[Invest::REJECTED] ?? 0,
            'waitRegister' => $byRegister[Budget::PENDING_REGISTER] ?? 0,
            'registered' => $byRegister[Budget::REGISTERED] ?? 0,
            'amount' => $amount,
        ];
    }

    /**
     * รูปพนักงานของทุกคนที่อยู่ในเอกสารพวกนี้
     *
     * @return array<string,?string>
     */
    private function photosFor($docs): array
    {
        $codes = $docs->pluck('approvals')->flatten()->pluck('employee_code')
            ->merge($docs->pluck('created_by'))
            ->merge($docs->pluck('proposer_code'))
            ->filter()->unique()->values()->all();

        if ($codes === []) {
            return [];
        }

        return collect($this->access->describeCodes($codes))->pluck('photo', 'employee_code')->all();
    }
}
