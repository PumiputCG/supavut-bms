<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use App\Services\Erp\ErpBudget;
use App\Services\Erp\ErpDashboard;
use App\Support\NavMenu;
use App\Support\TableFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly ErpBudget $erp,
        private readonly ErpDashboard $dashboard,
    ) {}

    /**
     * เห็นตัวเลขงบบนแดชบอร์ดได้ไหม
     *
     * 🔴 ใช้สิทธิ์ของแดชบอร์ดเอง ไม่ยืมของโมดูลงบ (เจ้าของสั่ง 2026-09-15)
     *    เดิมเช็ค fn.budget.approved ("ลงทะเบียนงบประมาณ" ซึ่งเป็นคิวงานของบัญชี) คนที่ได้สิทธิ์
     *    "ภาพรวมระบบ → แดชบอร์ด" จึงเปิดหน้าได้แต่เห็นข้อความ "ไม่มีสิทธิ์ดูข้อมูลงบประมาณ"
     *    แทนตัวเลข — ต้องไปตั้งสิทธิ์ 2 ที่ถึงจะใช้ได้จริง ซึ่งขัดกับกฎ
     *    "สิทธิ์ทั้งระบบกำหนดที่ /access/modules ที่เดียว"
     *
     * 🔴 คีย์อ่านจาก NavMenu ที่เดียว ห้ามเขียนสตริง fn.overview.dashboard ซ้ำตรงนี้
     *    ไม่งั้นวันที่เปลี่ยนคีย์ในเมนู ด่านนี้จะค้างอยู่กับคีย์เก่าแบบเงียบๆ
     */
    private function allowed(): bool
    {
        $hit = NavMenu::functionKeyFor('dashboard');
        if (! $hit) {
            return false;
        }
        $me = app()->bound('current_user') ? app('current_user') : null;
        foreach ($hit['function_keys'] as $key) {
            if ($this->access->canUseFunction($me, $hit['module_id'], $key)) {
                return true;
            }
        }

        return false;
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'company' => ['nullable', 'string', 'max:8'],
            'year' => ['nullable', 'regex:/^(?:20[0-9]{2}|unassigned|all)$/'],
            // ไตรมาส — ค่าว่าง = ทุกไตรมาส · unassigned = งบโปรเจคที่ไม่มีรหัสไตรมาส
            'quarter' => ['nullable', 'regex:/^(?:[1-4]|unassigned|all)$/'],
            'dept' => ['nullable', 'string', 'max:50'],
            'budget' => ['nullable', 'regex:/^[a-f0-9]{64}$/'],
            'record' => ['nullable', 'regex:/^[1-9][0-9]{0,18}$/'],
            'focus' => ['nullable', 'regex:/^[a-f0-9]{64}$/'],
            'highlight' => ['nullable', 'in:negative'],
            'view' => ['nullable', 'in:overview,budgets,activity,entries'],
            // แท็บ + ช่วงวันที่จ่ายของแท็บ "ค่าใช้จ่ายตามวันที่" (เจ้าของสั่ง 2026-09-17)
            'tab' => ['nullable', 'in:period,expense'],
            'paid_q' => ['nullable', 'regex:/^20[0-9]{2}-[1-4]$/'],
            'paid_m' => ['nullable', 'regex:/^20[0-9]{2}-(?:0[1-9]|1[0-2])$/'],
            'paid_d' => ['nullable', 'date_format:Y-m-d'],
            'kind' => ['nullable', 'in:actual,purchases'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'sort' => ['nullable', 'in:date_desc,date_asc,amount_desc,amount_asc'],
        ]);
        if (! empty($data['company']) && ! in_array($data['company'], $this->erp->companies(), true)) {
            abort(404);
        }
        $out = [];
        // ตัวกรอง Model เจ้าของสั่งเอาออก 2026-09-14 — ค่า model ที่ค้างมากับลิงก์เก่าจะถูกเมินไปเฉยๆ
        foreach (['company', 'year', 'quarter', 'dept', 'budget', 'record', 'highlight'] as $key) {
            $out[$key] = (string) ($data[$key] ?? '');
        }
        /*
          🔴 ค่าว่าง = "ทุกไตรมาส" อยู่แล้ว ต่างจากตัวกรองปีที่ต้องส่ง all มาชัดๆ
             เพราะปีมีค่าเริ่มต้นเป็น "ปีปัจจุบัน" ส่วนไตรมาสเริ่มต้นคือ "ทุกไตรมาส"
             ตัวสร้างลิงก์ตัดค่าว่างทิ้ง จึงได้ผลตรงกับที่ตั้งใจพอดี
        */
        if ($out['quarter'] === 'all') {
            $out['quarter'] = '';
        }
        /*
          🔴 ไม่ระบุปี = ปีปัจจุบัน (เจ้าของสั่ง 2026-09-14) · "ทุกปี" ต้องส่ง year=all มาชัดๆ
             ถ้าใช้ค่าว่างแทน "ทุกปี" ลิงก์ที่ตัดค่าว่างทิ้งจะเด้งกลับปีปัจจุบันเงียบๆ
        */
        if ($out['year'] === '') {
            $out['year'] = (string) now()->year;
        }
        $out['view'] = $data['view'] ?? 'overview';
        $out['kind'] = $data['kind'] ?? 'actual';
        $out['page'] = (int) ($data['page'] ?? 1);
        $out['sort'] = $data['sort'] ?? 'date_desc';

        /*
          🔴 แท็บงวดงบเก็บเป็นค่าว่าง ลิงก์เดิมทุกอันจึงหน้าตาเหมือนเดิม (ตัวสร้างลิงก์ตัดค่าว่างทิ้ง)
          🔴 ช่องของแต่ละแท็บมีความหมายคนละแบบ ต้องไม่ข้ามแท็บกัน
             ไตรมาสของแท็บงวดงบ = ถัง BG26Q1 · ไตรมาสของแท็บค่าใช้จ่าย = วันที่จ่ายจริง (paid_q)
        */
        $out['tab'] = ($data['tab'] ?? '') === 'expense' ? 'expense' : '';
        foreach (['paid_q', 'paid_m', 'paid_d'] as $key) {
            $out[$key] = $out['tab'] === 'expense' ? (string) ($data[$key] ?? '') : '';
        }
        if ($out['tab'] === 'expense') {
            $out['quarter'] = $out['budget'] = $out['record'] = $out['highlight'] = '';
            $out['view'] = $out['view'] === 'entries' ? 'entries' : 'overview';
            $out['kind'] = 'actual';
        } elseif ($out['view'] === 'entries') {
            $out['view'] = 'overview';
        }

        return $out;
    }

    public function index(Request $request): View|RedirectResponse
    {
        $filters = $this->filters($request);
        $data = [
            'canSeeBudget' => $this->allowed(), 'erpReady' => false, 'filters' => $filters,
            'rows' => [], 'departments' => [], 'groups' => [], 'years' => [], 'chart' => [],
            'quarters' => [], 'hasUnassignedQuarter' => false,
            'compare' => null, 'compareOptions' => ['years' => [], 'quarters' => [], 'depts' => []],
            'deptOptions' => [], 'total' => ErpDashboard::totals([]),
            'activity' => null, 'activityError' => false, 'fetchedAt' => null, 'foreign' => [],
            'filterOptions' => [], 'filterPicked' => [], 'groupsTotal' => 0, 'expense' => null,
            'filterKeys' => [], 'filterOptionsUrl' => null,
            'companyOptions' => array_map(fn ($id) => ['id' => $id, ...$this->erp->companyName($id)], $this->erp->companies()),
        ];
        if (! $data['canSeeBudget']) {
            return view('core.dashboard', $data);
        }
        /*
          🔴 แท็บค่าใช้จ่ายต้องเลือกปีงบเสมอ (เจ้าของเลือก 2026-09-17)
             "ทุกปี" ต้องจับคู่ถังงบหมื่นกว่าใบกับรายการหลายแสนแถว — เกินเวลารอ 30 วิของ ERP (วัดจริงแล้ว)
             "งบที่ยังไม่จัดปี" เป็นงบโปรเจคข้ามปีหลายพันถัง จึงตัดออกด้วยเหตุผลเดียวกัน
        */
        if ($filters['tab'] === 'expense' && ! ctype_digit($filters['year'])) {
            $filters['year'] = (string) now()->year;
            $filters['paid_q'] = $filters['paid_m'] = $filters['paid_d'] = '';

            return redirect()->route('dashboard', array_filter($filters, fn ($v) => $v !== ''));
        }
        try {
            /*
              🔴 อ่าน ERP 2 ชั้น (เจ้าของแจ้งว่าหน้าโหลดนาน 2026-09-17)
                 ① scopes()   = ค่าที่มีอยู่จริงของตัวกรอง อ่านแบบรวมกลุ่ม ไม่มียอดเงิน
                 ② snapshot() = ข้อมูลเต็ม "เฉพาะปีที่หน้านี้ใช้จริง" (ปีที่เลือก + ปีของ 2 ฝั่งเปรียบเทียบ)
                 เลือก "ทุกปี"/"งบที่ยังไม่จัดปี" ถึงจะอ่านทั้งหมดเหมือนเดิม
            */
            $scopes = $this->dashboard->scopes();
            // Old mapped URLs cannot silently keep combining ERP departments.
            if (str_contains($filters['dept'], '|g:')) {
                $all = $this->dashboard->snapshot();
                $target = $filters['budget'] !== '' ? ErpDashboard::filter($all, ['budget' => $filters['budget'], 'company' => $filters['company']]) : [];
                $filters['dept'] = $target[0]['dept_key'] ?? '';
                if (! $target) {
                    $filters['view'] = 'overview';
                    $filters['budget'] = $filters['record'] = '';
                }
                $filters['page'] = 1;

                return redirect()->route('dashboard', array_filter($filters, fn ($v) => $v !== ''))
                    ->with('dashboard_department_reset', ! $target);
            }
            $options = ErpDashboard::filter($scopes, ['company' => $filters['company']]);
            $data['years'] = array_values(array_unique(array_filter(array_column($options, 'year'))));
            if (preg_match('/^20[0-9]{2}$/', $filters['year']) && ! in_array((int) $filters['year'], $data['years'], true)) {
                $data['years'][] = (int) $filters['year'];
            }
            rsort($data['years']);
            /*
              🔴 ตัวกรองแคบลงทีละชั้นตามลำดับที่เห็นบนจอ: บริษัท -> ปี -> แผนก -> ไตรมาส
                 (เจ้าของสั่ง 2026-09-16) แต่ละช่องเสนอเฉพาะค่าที่ "มีข้อมูลจริง" ของช่องทางซ้าย
                 เช่น แผนก IT ยังไม่มีงบ Q4 -> ช่องไตรมาสต้องไม่มี Q4 ให้เลือกเลย
                 ไม่ใช่ให้เลือกได้แล้วเจอจอว่าง
            */
            $period_options = ErpDashboard::filter($options, ['year' => $filters['year']]);
            foreach ($period_options as $row) {
                $data['deptOptions'][$row['dept_group']] = [
                    'th' => $row['dept_name'],
                    'en' => $row['dept_name'],
                ];
            }
            if ($filters['dept'] !== '' && ! isset($data['deptOptions'][$filters['dept']])) {
                $filters['dept'] = $filters['quarter'] = $filters['budget'] = $filters['record'] = '';
                $filters['view'] = 'overview';
                $filters['page'] = 1;

                return redirect()->route('dashboard', array_filter($filters, fn ($v) => $v !== ''));
            }
            uasort($data['deptOptions'], fn ($a, $b) => strcmp($a['th'], $b['th']));
            // ไตรมาสเป็นชั้นในสุด จึงคิดจากแผนกที่เลือกแล้วด้วย
            $quarter_scope = ErpDashboard::filter($period_options, ['dept' => $filters['dept']]);
            $data['quarters'] = array_values(array_unique(array_filter(
                array_column($quarter_scope, 'quarter'),
                fn ($q) => $q !== null,
            )));
            sort($data['quarters']);
            // งบโปรเจคไม่มีรหัสไตรมาส — ต้องมีที่ให้มันอยู่เสมอ ไม่งั้นยอดหายเงียบๆ
            $data['hasUnassignedQuarter'] = (bool) array_filter($quarter_scope, fn ($r) => ($r['quarter'] ?? null) === null);
            /*
              🔴 เลือกไตรมาสค้างไว้แล้วสลับปี/แผนกไปที่ไม่มีไตรมาสนั้น ต้องล้างเฉพาะไตรมาส
                 ไม่งั้นช่องจะโชว์ค่าที่ไม่มีในรายการ แล้วหน้าจอว่างโดยผู้ใช้ไม่รู้สาเหตุ
                 🔴 ล้างแค่ไตรมาส ห้ามล้างแผนกทิ้งไปด้วย — ผู้ใช้เพิ่งเลือกแผนกนั้นมาเอง
            */
            $quarter_missing = match ($filters['quarter']) {
                '' => false,
                'unassigned' => ! $data['hasUnassignedQuarter'],
                default => ! in_array((int) $filters['quarter'], $data['quarters'], true),
            };
            if ($quarter_missing) {
                $filters['quarter'] = '';

                return redirect()->route('dashboard', array_filter($filters, fn ($v) => $v !== ''));
            }
            /*
              ข้อมูลเต็มอ่านตรงนี้ — ตัวกรองถูกตรวจ/แก้ให้ถูกต้องหมดแล้ว จึงรู้แน่ว่าต้องใช้ปีไหนบ้าง
              🔴 ปีของหน้า + ปีของ 2 ฝั่งในหน้าต่างเปรียบเทียบ (ไม่งั้นฝั่งที่เทียบจะไม่มีข้อมูล)
            */
            $all = $this->dashboard->snapshot($this->yearsNeeded($filters, $request));
            /*
              🔴 อยู่ในงบก้อนหนึ่งแล้วเปลี่ยนตัวกรองจนก้อนนั้นหลุดขอบเขต ต้องถอยออกมาระดับแผนก
                 ไม่ใช่ปล่อยให้ค้างอยู่หน้าเดิมแล้วโชว์จอว่างซึ่งผู้ใช้กดต่อไม่ถูก
                 🔴 ถอยแค่ชั้นเดียว — แผนกกับหน้าที่เปิดอยู่ยังคงไว้ ผู้ใช้เลือกมาเอง
            */
            if ($filters['budget'] !== '' && ! ErpDashboard::filter($all, $filters)) {
                $filters['budget'] = $filters['record'] = '';
                $filters['page'] = 1;

                return redirect()->route('dashboard', array_filter($filters, fn ($v) => $v !== ''));
            }
            $scoped = ErpDashboard::filter($all, $filters);
            $foreign_rows = array_values(array_filter($scoped, fn ($row) => $row['currency'] !== 'THB'));
            $data['rows'] = array_values(array_filter($scoped, fn ($row) => $row['currency'] === 'THB'));
            $data['foreign'] = ErpDashboard::foreign($scoped);
            $data['total'] = ErpDashboard::totals($data['rows']);
            $data['departments'] = ErpDashboard::departments($scoped);
            /*
              ═══ แท็บ "ค่าใช้จ่ายตามวันที่" (เจ้าของสั่ง 2026-09-17) ═══
              ขอบเขตถังงบชุดเดียวกับแท็บงวดงบ (บริษัท · ปีงบ · แผนก) คิดไว้แล้วข้างบน
              ต่างกันแค่แกนเวลา — รายละเอียดอยู่ที่ expense()
            */
            if ($filters['tab'] === 'expense') {
                $redirect = $this->expense($data, $filters, $request, $all, $options);
                if ($redirect) {
                    return $redirect;
                }
                $data['filters'] = $filters;
                $data['erpReady'] = true;
                $data['fetchedAt'] = now()->format('d/m/Y H:i:s');

                return view('core.dashboard', $data);
            }
            // กลุ่มงบสกุลเงินต่างประเทศต่อท้ายตาราง แยกจากกลุ่มเงินบาทเสมอ ห้ามรวมยอดข้ามสกุล
            $data['groups'] = array_merge(
                array_map(fn ($g) => $g + ['currency' => 'THB'], ErpDashboard::groups($data['rows'], 'group')),
                array_map(fn ($g) => $g + ['currency' => $g['first']['currency']], ErpDashboard::groups($foreign_rows, 'group')),
            );
            $data['chart'] = $this->chart($data['departments'], $data['total'], $data['foreign'], $filters, $scoped);
            // ชุดที่เอามาเทียบ — เลือกในหน้าต่าง "เปรียบเทียบ" ส่งมาทาง ?cmp[...]
            $data['compare'] = $this->compare($all, $filters, $request);
            $data['compareOptions'] = [
                'years' => $data['years'],
                'quarters' => array_values(array_unique(array_filter(array_column($options, 'quarter'), fn ($q) => $q !== null))),
                'depts' => collect($options)->pluck('dept_name', 'dept_group')->sort()->all(),
            ];
            sort($data['compareOptions']['quarters']);
            $data['erpReady'] = true;
            $data['fetchedAt'] = now()->format('d/m/Y H:i:s');
            /*
              ตัวกรองหัวคอลัมน์ (เจ้าของสั่ง 2026-09-14) — ตารางที่แบ่งหน้าต้องกรอง "ทุกหน้า" ที่เซิร์ฟเวอร์
              ค่าอยู่ใน ?f[คีย์]=ค่า|ค่า · คีย์ที่รับได้มาจากรายการตายตัวด้านล่างเท่านั้น
            */
            if ($filters['view'] === 'budgets') {
                $map = $this->group_filter_map();
                if ($filters['highlight'] === 'negative') {
                    $data['groups'] = array_values(array_filter($data['groups'], fn ($g) => $g['total']['available'] < 0));
                }
                $data['groupsTotal'] = count($data['groups']);
                $data['filterOptions'] = TableFilter::rowOptions($data['groups'], $map);
                $data['filterPicked'] = TableFilter::picked($request, $map);
                $data['groups'] = TableFilter::filterRows($data['groups'], $data['filterPicked'], $map);
            }
            if ($filters['view'] === 'activity' && $filters['dept'] !== '') {
                $data['filterPicked'] = TableFilter::picked($request, array_fill_keys(ErpDashboard::activity_filter_keys($filters['kind']), []));
                try {
                    $data['activity'] = match (true) {
                        $data['filterPicked'] !== [] => $this->dashboard->activity($data['rows'], $filters['kind'], $filters['page'], $filters['sort'], $data['filterPicked']),
                        $filters['sort'] === 'date_desc' => $this->dashboard->activity($data['rows'], $filters['kind'], $filters['page']),
                        default => $this->dashboard->activity($data['rows'], $filters['kind'], $filters['page'], $filters['sort']),
                    };
                } catch (Throwable $e) {
                    $data['activityError'] = true;
                    Log::warning('ERP dashboard activity unavailable', ['error_type' => get_class($e)]);
                }
                // รายการค่าให้เลือกในหัวคอลัมน์ — รออ่านตอนกดปุ่มกรอง อ่านไม่ได้ก็แค่ไม่มีปุ่ม ห้ามทำให้ตารางพัง
                $data['filterKeys'] = ErpDashboard::activity_filter_keys($filters['kind']);
                $data['filterOptionsUrl'] = route('dashboard.filter-options', array_filter([
                    'company' => $filters['company'], 'year' => $filters['year'], 'quarter' => $filters['quarter'],
                    'dept' => $filters['dept'], 'budget' => $filters['budget'], 'view' => 'activity', 'kind' => $filters['kind'],
                ], fn ($v) => $v !== ''));
            }
        } catch (Throwable $e) {
            Log::warning('ERP dashboard snapshot unavailable', ['error_type' => get_class($e)]);
        }

        return view('core.dashboard', $data);
    }

    /**
     * ตัวกรองหัวคอลัมน์ของตารางรายการงบ — ข้อความต้องตรงกับที่แสดงในช่องตารางทุกตัวอักษร
     * (dashboard.blade.php ตาราง "รายการงบประมาณ")
     */
    private function group_filter_map(): array
    {
        $unit = fn (array $g) => $g['currency'] === 'THB' ? '' : ' '.ErpDashboard::currency_label((string) $g['currency']);
        $money = fn (string $field) => [
            'value' => fn (array $g) => number_format($g['total'][$field] / 100, 2).$unit($g),
            'sort' => fn (array $g) => $g['total'][$field],
        ];

        return [
            'name' => ['value' => fn (array $g) => implode(' / ', $g['titles']) ?: $g['first']['budget_no']],
            'start' => ['value' => fn (array $g) => (string) min(array_column($g['rows'], 'start_date'))],
            'budget' => $money('budget'),
            'actual' => $money('actual'),
            'reserve' => $money('reserve'),
            'available' => $money('available'),
        ];
    }

    /**
     * ข้อมูลกราฟรายแผนก — คิดตัวเลขและจัดรูปแบบที่เซิร์ฟเวอร์ให้ครบ
     * 🔴 JS ใช้ตัวเลขทศนิยมแค่วาดความยาวแท่ง ตัวเลขที่คนอ่านมาจาก number_format ของสตางค์เท่านั้น
     *    กันยอดเพี้ยนจากการบวกทศนิยมในเบราว์เซอร์
     */
    /**
     * ขอบเขตที่ผู้ใช้เลือกไว้ — ต้องติดไปกับทุกลิงก์ที่กราฟสร้างเอง
     *
     * 🔴 บั๊กจริง 2026-09-16 (เจ้าของแจ้ง): ลิงก์ของกราฟเคยไล่พิมพ์ชื่อพารามิเตอร์เองทีละตัว
     *    (company · year · dept · view) พอเพิ่มตัวกรอง "ไตรมาส" ลิงก์เลย **ทิ้ง quarter ไปเงียบๆ**
     *    กดจากกราฟเข้าไปดูรายการงบแล้วตัวกรองไตรมาสหาย ข้อมูลข้างในกลายเป็นทุกไตรมาส
     *
     *    ต่างจากลิงก์ใน blade ที่ใช้ $href() ซึ่งยก $filters มาทั้งชุดอยู่แล้ว จึงไม่มีปัญหา
     *    🔴 เพิ่มตัวกรองใหม่เมื่อไหร่ ให้เติมชื่อที่ค่าคงที่นี้ที่เดียว
     */
    private const LINK_SCOPE = ['company', 'year', 'quarter'];

    /** ช่องที่ประกอบเป็น "ขอบเขต" ของการเปรียบเทียบ 1 ฝั่ง */
    private const COMPARE_DIMS = ['company', 'year', 'quarter', 'dept'];

    /** ลิงก์ที่พาขอบเขตปัจจุบันไปด้วยเสมอ แล้วทับด้วยปลายทางที่ระบุ */
    private function scopedLink(array $filters, array $extra): string
    {
        $params = array_replace(array_intersect_key($filters, array_flip(self::LINK_SCOPE)), $extra);

        return route('dashboard', array_filter($params, fn ($v) => $v !== '' && $v !== null));
    }

    /**
     * ขอบเขตของ "ชุดที่เอามาเทียบ" จาก ?cmp[...]
     *
     * 🔴 ตรวจด้วยกฎชุดเดียวกับตัวกรองหลัก ห้ามเชื่อค่าที่ส่งมาตรงๆ
     *
     * @return array<string,string>
     */
    private function compareScope(Request $request, string $key = 'cmp', bool $expense = false): array
    {
        $raw = $request->input($key);
        if (! is_array($raw)) {
            return [];
        }
        // แท็บค่าใช้จ่ายเลือกช่วงวันที่จ่ายแทนถังไตรมาส · แท็บงวดงบไม่รับช่องเหล่านี้
        $raw = $expense
            ? array_diff_key($raw, ['quarter' => true])
            : array_diff_key($raw, ['paid_q' => true, 'paid_m' => true, 'paid_d' => true]);
        $data = validator($raw, [
            'paid_q' => ['nullable', 'regex:/^20[0-9]{2}-[1-4]$/'],
            'paid_m' => ['nullable', 'regex:/^20[0-9]{2}-(?:0[1-9]|1[0-2])$/'],
            'paid_d' => ['nullable', 'date_format:Y-m-d'],
            'company' => ['nullable', 'string', 'max:8'],
            'year' => ['nullable', 'regex:/^(?:20[0-9]{2}|unassigned|all)$/'],
            'quarter' => ['nullable', 'regex:/^(?:[1-4]|unassigned|all)$/'],
            'dept' => ['nullable', 'string', 'max:50'],
            // ธงบอกว่า "ผู้ใช้กรอกฝั่งนี้มาเอง" — ต่างจากไม่ได้ส่งมาเลย
            'on' => ['nullable', 'in:1'],
        ])->validate();

        $on = ($data['on'] ?? '') === '1';
        unset($data['on']);

        $scope = array_filter(array_map(strval(...), $data), fn ($v) => $v !== '');

        /*
          🔴 ต้องแยก "เลือกทั้งหมดทุกช่อง" ออกจาก "ไม่ได้ส่งมาเลย" ให้ได้

             ทั้ง 2 กรณีทำให้ $scope ว่างเหมือนกัน แต่ความหมายคนละเรื่อง
               ไม่ได้ส่งมา  = ยังไม่ได้สั่งเทียบ / ให้ใช้ตัวกรองของหน้าแทน
               ส่งมาแต่ว่าง = ผู้ใช้ตั้งใจเลือก "ทุกบริษัท ทุกปี ทุกไตรมาส ทุกแผนก"
             ธง on จึงจำเป็น ไม่งั้นผู้ใช้เลือกดูภาพรวมทั้งหมดไม่ได้เลย
        */
        if ($scope === []) {
            return $on ? ['on' => '1'] : [];
        }

        return $on ? $scope + ['on' => '1'] : $scope;
    }

    /**
     * ฝั่งไหนเป็น "ช่วงที่ยังไม่จบ" — ใช้ขึ้นแถบเตือนในหน้าเปรียบเทียบ
     *
     * 🔴 ปัญหาที่ต้องเตือน (DECISIONS 50.9.7): ปี 2026 มียอดจ่ายจริงแค่ต้นปีถึงวันนี้
     *    เอาไปเทียบกับ 2025 ที่ครบ 12 เดือน ตัวเลขจะดู "ลดลง" ทั้งที่ยังไม่ถึงเวลาใช้เงิน
     *    (วัดจริง 2026-09-17: เทียบช่วงเท่ากันได้ −33.2% แต่เทียบกับปีเต็มได้ −52.0% เพี้ยน 18.8 จุด)
     * 🔴 เตือนเฉพาะตอน "ฝั่งเดียวยังไม่จบ" — ยังไม่จบทั้ง 2 ฝั่งถือว่าเทียบกันได้ตามปกติ
     *
     * @return array{side:string, th:string, en:string}|null
     */
    private function partialSide(array $left, array $right, bool $expense): ?array
    {
        $end = function (array $scope) use ($expense): ?string {
            $year = (string) ($scope['year'] ?? '');
            if (! preg_match('/^20\d{2}$/D', $year)) {
                return null;
            }
            if ($expense) {
                $window = ErpDashboard::paid_window($scope) ?? ErpDashboard::year_window($year);

                // ช่อง to ของหน้าต่างเป็น "วันถัดจากวันสุดท้าย" จึงถอยกลับ 1 วัน
                return $window === null ? null : date('Y-m-d', strtotime($window['to'].' -1 day'));
            }
            // แท็บงวดงบ: งวด BG26Q1 = ม.ค.–มี.ค. ของปีนั้น · ไม่ระบุงวด = ทั้งปี
            $quarter = (string) ($scope['quarter'] ?? '');

            return ctype_digit($quarter)
              ? date('Y-m-t', strtotime($year.'-'.str_pad((string) ((int) $quarter * 3), 2, '0', STR_PAD_LEFT).'-01'))
              : $year.'-12-31';
        };
        $today = now()->format('Y-m-d');
        $open = ['a' => ($end($left) ?? $today) >= $today, 'b' => ($end($right) ?? $today) >= $today];
        // ยังไม่จบทั้งคู่ หรือจบทั้งคู่ = ไม่ต้องเตือน
        if ($open['a'] === $open['b']) {
            return null;
        }
        $side = $open['a'] ? 'a' : 'b';
        $scope = $side === 'a' ? $left : $right;
        $thai = now()->locale('th')->translatedFormat('j M');

        return ['side' => $side,
            'th' => 'ปีงบ '.$scope['year'].' ยังไม่จบ — มีข้อมูลถึง '.$thai.' เท่านั้น ส่วนอีกฝั่งครบช่วงแล้ว'
                .' ตัวเลขที่ต่ำกว่าจึงมาจาก "เวลาที่ยังไม่ถึง" ด้วย ไม่ใช่การใช้เงินน้อยลงทั้งหมด',
            'en' => 'Budget year '.$scope['year'].' is still in progress — data runs to '.now()->format('j M').' only,'
                .' while the other side is complete. Part of the gap is unelapsed time, not lower spending.'];
    }

    /**
     * เปรียบเทียบ 2 ขอบเขต (เจ้าของสั่ง 2026-09-16)
     *
     * ชุด ก. = ตัวกรองที่เลือกอยู่บนหน้า · ชุด ข. = ขอบเขตที่เลือกในหน้าต่างเปรียบเทียบ (`?cmp[...]`)
     * เทียบครบทั้ง 4 ช่อง แล้วบอกว่าเพิ่มขึ้น/ลดลงกี่เปอร์เซ็นต์
     *
     * 🔴 เพิ่มขึ้น = เขียว · ลดลง = แดง (เจ้าของกำหนด) — คิดจากชุด ก. เป็นฐานเสมอ
     * 🔴 ฐานเป็น 0 คิดเปอร์เซ็นต์ไม่ได้ ต้องคืน null แล้วให้หน้าจอขึ้นขีดกลาง
     *    ห้ามแทนด้วย 0% หรือ 100% เพราะทั้งคู่โกหกคนอ่าน
     *
     * @return array<string,mixed>|null
     */
    private function compare(array $all, array $filters, Request $request): ?array
    {
        $right = $this->compareScope($request, 'cmp');

        if ($right === []) {
            return null;
        }

        /*
          🔴 ฝั่งซ้ายเลือกเองได้แล้ว (เจ้าของสั่ง 2026-09-16)
             ไม่ได้ส่ง cmpa มา = ลิงก์เก่าที่มีแต่ ?cmp[...] → ใช้ตัวกรองของหน้าเป็นฝั่งซ้ายตามเดิม
        */
        $left = $this->compareScope($request, 'cmpa');

        if ($left === []) {
            $left = array_filter(
                array_intersect_key($filters, array_flip(self::COMPARE_DIMS)),
                fn ($v) => $v !== '',
            );
        }

        // ธง on เป็นตัวบอกเจตนา ไม่ใช่ตัวกรองข้อมูล — ต้องถอดก่อนส่งให้ตัวกรอง
        $sum = function (array $scope) use ($all) {
            $rows = ErpDashboard::filter($all, array_diff_key($scope, ['on' => null]));

            return ErpDashboard::totals(array_values(array_filter($rows, fn ($r) => $r['currency'] === 'THB')));
        };

        $a = $sum($left);
        $b = $sum($right);

        /*
          🔴 คิดผลต่าง "ทั้ง 2 ทิศทาง" (เจ้าของสั่ง 2026-09-16)
             เพราะการ์ดของแต่ละฝั่งต้องบอกได้เองว่า **ตัวเองต่างจากอีกฝั่งเท่าไร**
             ฝั่งซ้ายใช้ฝั่งขวาเป็นฐาน · ฝั่งขวาใช้ฝั่งซ้ายเป็นฐาน

          🔴 ฐานเป็น 0 คิดเปอร์เซ็นต์ไม่ได้ ต้องคืน null แล้วให้หน้าจอขึ้นขีดกลาง
             ห้ามแทนด้วย 0% หรือ 100% เพราะทั้งคู่โกหกคนอ่าน
        */
        $against = fn (int $mine, int $base) => [
            'diff' => $mine - $base,
            'pct' => $base === 0 ? null : ($mine - $base) / abs($base) * 100,
        ];

        $fields = [];
        foreach (['budget', 'actual', 'reserve', 'available'] as $key) {
            $fields[$key] = [
                'a' => $a[$key],
                'b' => $b[$key],
                // คู่เดิม — ผลต่างมองจากชุด ข. เทียบกับชุด ก. (บวกคือชุด ข. มากกว่า)
                ...$against($b[$key], $a[$key]),
                'side' => [
                    'a' => $against($a[$key], $b[$key]),
                    'b' => $against($b[$key], $a[$key]),
                ],
            ];
        }

        /*
          🔴 อัตราการใช้งบ = ใช้ไป ÷ ตั้งงบ (เจ้าของสั่ง 2026-09-17)

             เกิดจากปัญหาที่เจ้าของเจอเอง: ยอดบาทของ 2 ฝั่งคนละขนาด
             เทียบกันแล้ว % ออกมาไม่เท่ากัน (ฝั่งหนึ่ง −27.2% อีกฝั่ง +37.4%) สรุปไม่ได้ว่าใครดีกว่า
             อัตราการใช้งบ **ไม่ขึ้นกับขนาดของงบ** จึงเอามาเทียบกันตรงๆ ได้
             เช่น Q1 ตั้ง 18.5 ล้านใช้ 77.2% · Q2 ตั้ง 12.0 ล้านใช้ 80.8% — เทียบได้ทันทีว่า Q2 ใช้แน่นกว่า

          🔴 ผลต่างของ 2 อัตราเป็นหน่วย "จุด" (percentage point) ไม่ใช่ "%"
             77.2 − 80.8 = −3.6 จุด · ถ้าคิดเป็น % จะได้ −4.5% ซึ่งเป็นคนละค่าและคนละความหมาย
             หน้าจอจึงต้องเขียนคำว่า "จุด" ให้ชัด ห้ามใส่เครื่องหมาย %

          🔴 ตั้งงบ ≤ 0 คิดอัตราไม่ได้ ต้องคืน null แล้วให้หน้าจอขึ้นขีดกลาง
        */
        $rate = fn (array $t) => $t['budget'] <= 0 ? null : $t['actual'] / $t['budget'] * 100;

        $rateA = $rate($a);
        $rateB = $rate($b);
        $gap = ($rateA === null || $rateB === null) ? null : $rateA - $rateB;

        return ['a' => $a, 'b' => $b, 'fields' => $fields,
            'usage' => [
                'a' => ['rate' => $rateA, 'gap' => $gap === null ? null : $gap],
                'b' => ['rate' => $rateB, 'gap' => $gap === null ? null : -$gap],
            ],
            'left' => $left, 'right' => $right,
            'partial' => $this->partialSide($left, $right, false),
            // ชื่อเดิม เผื่อที่อื่นยังอ้างอยู่
            'scope' => $right,
            'mode' => $this->compareMode($left, $right)];
    }

    /*
      ═══════════ แท็บ "ค่าใช้จ่ายตามวันที่" (เจ้าของสั่ง 2026-09-17 · DECISIONS 50.11) ═══════════

      🔴 เงินชุดเดียวกับแท็บ "งบประมาณตามงวดงบ" ทุกบาท — ขอบเขตถังงบ (บริษัท · ปีงบ · แผนก) ตรงกัน
         ต่างกันแค่แกนเวลา: แท็บนี้แบ่งตาม "วันที่จ่ายจริง" (ไตรมาส · เดือน · วัน)
         ยอดทั้งปีจึงเท่ากับ "ใช้ไป" ของแท็บงวดงบ ยกเว้นถังที่ ERP สะสมยอดเพี้ยน (แสดงให้เห็น ไม่ซ่อน)
      🔴 ไม่มีช่อง งบประมาณ / รอตัดจ่าย / คงเหลือ — ERP ไม่มีงบรายเดือน/รายวันให้เทียบ (เจ้าของตกลงแล้ว)
    */

    /** ช่องที่ประกอบเป็นขอบเขตของแท็บค่าใช้จ่าย — ติดไปกับทุกลิงก์ที่สร้างในแท็บนี้ */
    private const EXPENSE_SCOPE = ['company', 'year', 'tab', 'paid_q', 'paid_m', 'paid_d'];

    /** ช่องของการเปรียบเทียบ 1 ฝั่งในแท็บค่าใช้จ่าย */
    private const EXPENSE_COMPARE_DIMS = ['company', 'year', 'dept', 'paid_q', 'paid_m', 'paid_d'];

    /**
     * ปีงบที่หน้านี้ต้องใช้จริง — ใช้บอก ERP ว่าจะขอข้อมูลเต็มแค่ปีไหน
     * คืน [] เมื่อต้องใช้ทุกปี (เลือก "ทุกปี" / "งบที่ยังไม่จัดปี" / ฝั่งเปรียบเทียบไม่ระบุปี)
     *
     * @return list<string>
     */
    private function yearsNeeded(array $filters, Request $request): array
    {
        $wanted = [$filters['year']];
        foreach (['cmpa', 'cmp'] as $side) {
            $scope = $request->query($side);
            if (is_array($scope) && ($scope['on'] ?? '') !== '') {
                $wanted[] = (string) ($scope['year'] ?? '');
            }
        }
        foreach ($wanted as $year) {
            if (! preg_match('/^20\d{2}$/D', (string) $year)) {
                return [];
            }
        }

        return array_values(array_unique(array_map('strval', $wanted)));
    }

    private function expenseLink(array $filters, array $extra): string
    {
        $params = array_replace(array_intersect_key($filters, array_flip(self::EXPENSE_SCOPE)), $extra);

        return route('dashboard', array_filter($params, fn ($v) => $v !== '' && $v !== null));
    }

    private function expense(array &$data, array &$filters, Request $request, array $all, array $options): ?RedirectResponse
    {
        $data['compareOptions'] = [
            'years' => $data['years'],
            // ไตรมาสที่จ่ายเกิดข้ามปีงบได้ (ถังปีนี้จ่ายปีหน้า) จึงเสนอถึงปีถัดจากปีล่าสุด
            'quarters' => $this->expenseQuarterChoices($data['years']),
            'depts' => collect($options)->pluck('dept_name', 'dept_group')->sort()->all(),
        ];

        // โหมดเปรียบเทียบกินทั้งหน้า — ไม่ต้องอ่านข้อมูลของหน้าหลักให้เปลือง ERP
        $data['compare'] = $this->expenseCompare($all, $filters, $request);
        if ($data['compare']) {
            return null;
        }

        /*
          🔴 เลือกวันแล้วเดือน/ไตรมาสต้องตรงกับวันนั้น (ลิงก์จากที่อื่นอาจส่งมาแค่วัน)
             ปรับให้ตรงแล้วพาไปที่อยู่ใหม่ ช่องตัวกรองบนจอจะได้ไม่ขัดกันเอง
        */
        $want = $filters;
        if ($filters['paid_d'] !== '') {
            $want['paid_m'] = substr($filters['paid_d'], 0, 7);
            $want['paid_q'] = ErpDashboard::quarter_key($filters['paid_d']);
        } elseif ($filters['paid_m'] !== '') {
            $want['paid_q'] = ErpDashboard::quarter_key($filters['paid_m'].'-01');
        }
        if ($want !== $filters) {
            return redirect()->route('dashboard', array_filter($want, fn ($v) => $v !== ''));
        }

        /*
          🔴 ตัดให้เหลือเฉพาะรายการที่จ่าย "ในปีงบที่เลือก" ตั้งแต่ต้น (เจ้าของสั่ง 2026-09-17)
             จ่าย ก.พ. 2026 แต่ไปตัดถัง BG25Q4 → ไม่ขึ้นในปี 2025 ของแท็บนี้
             ตัวเลือกไตรมาส/เดือน/วัน จึงมีแค่ของปีนั้นด้วย ไม่มี "หลังสิ้นปีงบ" ให้เลือกอีก
        */
        $spend = $this->dashboard->spending($data['rows']);
        $year_window = ErpDashboard::year_window($filters['year']);
        $days = ErpDashboard::spend_within($spend['days'], $year_window);
        $periods = ErpDashboard::spend_periods($days, $filters['paid_q'], $filters['paid_m']);

        /*
          🔴 ช่วงที่เลือกไม่มีรายการแล้ว (เปลี่ยนปี/แผนก) → ล้างเฉพาะช่องนั้นกับช่องที่ลึกกว่า
             ห้ามล้างบริษัท/ปี/แผนก — ผู้ใช้เพิ่งเลือกมาเอง (กติกาเดียวกับตัวกรองไตรมาสของแท็บงวดงบ)
        */
        $stale = match (true) {
            $filters['paid_q'] !== '' && ! in_array($filters['paid_q'], $periods['quarters'], true) => ['paid_q', 'paid_m', 'paid_d'],
            $filters['paid_m'] !== '' && ! in_array($filters['paid_m'], $periods['months'], true) => ['paid_m', 'paid_d'],
            $filters['paid_d'] !== '' && ! in_array($filters['paid_d'], $periods['days'], true) => ['paid_d'],
            default => [],
        };
        if ($stale) {
            foreach ($stale as $key) {
                $filters[$key] = '';
            }
            $filters['page'] = 1;

            return redirect()->route('dashboard', array_filter($filters, fn ($v) => $v !== ''));
        }

        // ไม่ได้เลือกช่วง = ทั้งปีงบ (ซึ่งตอนนี้แปลว่า "จ่ายในปีนั้น" ตามกติกาใหม่)
        $window = ErpDashboard::paid_window($filters) ?? $year_window;
        $total = ErpDashboard::spend_total($days, $window);

        /*
          ตัวเลขเทียบช่วงก่อนหน้าบนการ์ด — DoD / MoM / QoQ ตามระดับที่เลือก
          🔴 ฐานเป็น 0 คิดเปอร์เซ็นต์ไม่ได้ → null แล้วหน้าจอขึ้นขีดกลาง (กติกาเดียวกับหน้าเปรียบเทียบ)
             เช่นเดือน ม.ค. ของถังปีนี้ เทียบ ธ.ค. ซึ่งถังปีนี้ยังไม่มีรายการ
          🔴 ระดับ "ทั้งปี" ไม่เทียบบนการ์ด — ปีก่อนหน้าเป็นถังคนละปี ต้องอ่าน ERP อีกรอบ
             ใครอยากได้ YoY ใช้หน้าเปรียบเทียบ ซึ่งอ่านข้อมูลของแต่ละฝั่งแยกกันอยู่แล้ว
        */
        $previous = null;
        if ($window !== null && $window['level'] !== 'year') {
            $prev_window = ErpDashboard::previous_window($window);
            $prev_total = ErpDashboard::spend_total($days, $prev_window);
            $previous = ['window' => $prev_window, 'total' => $prev_total,
                'pct' => $prev_total['amount'] === 0 ? null : ($total['amount'] - $prev_total['amount']) / abs($prev_total['amount']) * 100];
        }

        $by_dept = ErpDashboard::spend_by_dept($days, $window);
        $data['chart'] = $this->expenseChart($data['departments'], $by_dept, $total, $filters);
        $data['expense'] = [
            'window' => $window,
            'total' => $total,
            'previous' => $previous,
            'periods' => $periods,
            'depts' => $by_dept,
            'ambiguous' => $spend['ambiguous'],
        ];

        if ($filters['view'] === 'entries' && $filters['dept'] !== '') {
            $keys = array_fill_keys(ErpDashboard::activity_filter_keys('actual'), []);
            $data['filterPicked'] = TableFilter::picked($request, $keys);
            try {
                $data['activity'] = $this->dashboard->activity($data['rows'], 'actual', $filters['page'], $filters['sort'], $data['filterPicked'], $window);
            } catch (Throwable $e) {
                $data['activityError'] = true;
                Log::warning('ERP expense entries unavailable', ['error_type' => get_class($e)]);
            }
            // ค่าที่เลือกได้ในหัวคอลัมน์รออ่านตอนผู้ใช้กดปุ่มกรอง (วัดจริง 1.19 วิต่อการเปิดหน้า 1 ครั้ง)
            $data['filterKeys'] = ErpDashboard::activity_filter_keys('actual');
            $data['filterOptionsUrl'] = route('dashboard.filter-options', array_filter([
                'company' => $filters['company'], 'year' => $filters['year'], 'dept' => $filters['dept'],
                'tab' => 'expense', 'view' => 'entries', 'paid_q' => $filters['paid_q'],
                'paid_m' => $filters['paid_m'], 'paid_d' => $filters['paid_d'],
            ], fn ($v) => $v !== ''));
        }

        return null;
    }

    /**
     * ตัวเลือกไตรมาสที่จ่ายในหน้าต่างเปรียบเทียบ — 'YYYY-n' ใหม่ไปเก่า
     * 🔴 เฉพาะไตรมาสของปีที่มีงบจริง — เลิกเสนอปีถัดไป เพราะแท็บนี้ไม่นับรายการที่จ่ายข้ามปีแล้ว
     */
    private function expenseQuarterChoices(array $years): array
    {
        $all = [];
        foreach ($years as $year) {
            foreach ([4, 3, 2, 1] as $q) {
                $all[(int) $year.'-'.$q] = true;
            }
        }
        $keys = array_keys($all);
        rsort($keys);

        return $keys;
    }

    /**
     * ข้อมูลกราฟของแท็บค่าใช้จ่าย — รูปแบบเดียวกับ chart() เพื่อใช้ตัววาดตัวเดียวกัน
     * 🔴 mode = spend บอกตัววาดให้วาดแท่งเดียว (ค่าใช้จ่าย) ไม่มีรางวงเงินงบ
     *    ยอดเก็บไว้ในช่อง budget เพราะตัววาดใช้ช่องนั้นเรียงลำดับและกำหนดสเกล
     */
    private function expenseChart(array $departments, array $by_dept, array $total, array $filters): array
    {
        $names = $info = [];
        foreach ($departments as $d) {
            $names[$d['first']['dept_name']][$d['first']['company']] = true;
            $info[$d['key']] = $d['first'];
        }
        $item = function (array $t): array {
            $text = number_format($t['amount'] / 100, 2);

            return [
                'count' => $t['n'],
                'v' => ['budget' => $t['amount'] / 100, 'actual' => $t['amount'] / 100, 'reserve' => 0, 'available' => 0],
                'text' => ['budget' => $text, 'actual' => $text, 'reserve' => '0.00', 'available' => '0.00'],
                'pct' => null, 'fx' => [], 'alerts' => [], 'alerts_href' => '',
            ];
        };
        $items = [];
        foreach ($by_dept as $key => $t) {
            $first = $info[$key] ?? null;
            if (! $first || ($t['n'] === 0 && $t['amount'] === 0)) {
                continue;
            }
            $firm = $this->erp->companyName($first['company']);
            $twin = $filters['company'] === '' && count($names[$first['dept_name']]) > 1;
            $items[] = $item($t) + [
                'name_th' => $first['dept_name'].($twin ? ' · '.$firm['th'] : ''),
                'name_en' => $first['dept_name'].($twin ? ' · '.$firm['en'] : ''),
                'firm_th' => $firm['th'], 'firm_en' => $firm['en'],
                'dept_key' => $key,
                'href' => $this->expenseLink($filters, ['dept' => $key, 'view' => 'entries']),
            ];
        }

        return ['mode' => 'spend', 'items' => $items, 'total' => $item($total)];
    }

    /**
     * เปรียบเทียบค่าใช้จ่าย 2 ขอบเขต — DoD / MoM / QoQ / YoY (เจ้าของสั่ง 2026-09-17)
     *
     * ใช้กติกาเดียวกับหน้าเปรียบเทียบของแท็บงวดงบ: ฝั่งซ้าย ?cmpa[...] · ฝั่งขวา ?cmp[...]
     * แต่ละฝั่งเลือก บริษัท · ปีงบ · แผนก · ช่วงวันที่จ่าย (ไตรมาส / เดือน / วัน)
     *
     * 🔴 ต้องเลือกปีงบทั้ง 2 ฝั่ง เหตุผลเดียวกับตัวกรองของแท็บ (ทุกปี = เกินเวลารอของ ERP)
     * 🔴 ค่าใช้จ่ายเพิ่ม = แดง เหมือน "ใช้ไป" ของแท็บงวดงบ · จำนวนรายการ/เฉลี่ยไม่ระบายสี
     */
    private function expenseCompare(array $all, array $filters, Request $request): ?array
    {
        $right = $this->compareScope($request, 'cmp', true);
        if ($right === []) {
            return null;
        }
        $left = $this->compareScope($request, 'cmpa', true);
        if ($left === []) {
            $left = array_filter(array_intersect_key($filters, array_flip(self::EXPENSE_COMPARE_DIMS)), fn ($v) => $v !== '');
        }

        $sum = function (array $scope) use ($all): array {
            if (! ctype_digit((string) ($scope['year'] ?? ''))) {
                return ['amount' => 0, 'n' => 0, 'avg' => 0, 'missing_year' => true];
            }
            $rows = ErpDashboard::filter($all, array_intersect_key($scope, array_flip(['company', 'year', 'dept'])));
            $rows = array_values(array_filter($rows, fn ($r) => $r['currency'] === 'THB'));
            // 🔴 กติกาเดียวกับหน้าหลัก — นับเฉพาะที่จ่ายในปีของฝั่งนั้น แล้วค่อยแคบตามช่วงที่เลือก
            $year_window = ErpDashboard::year_window((string) $scope['year']);
            $days = ErpDashboard::spend_within($this->dashboard->spending($rows)['days'], $year_window);
            $t = ErpDashboard::spend_total($days, ErpDashboard::paid_window($scope) ?? $year_window);

            return ['amount' => $t['amount'], 'n' => $t['n'],
                'avg' => $t['n'] === 0 ? 0 : (int) round($t['amount'] / $t['n']), 'missing_year' => false];
        };
        $a = $sum($left);
        $b = $sum($right);

        $against = fn (int $mine, int $base) => [
            'diff' => $mine - $base,
            'pct' => $base === 0 ? null : ($mine - $base) / abs($base) * 100,
        ];
        $fields = [];
        foreach (['amount', 'n', 'avg'] as $key) {
            $fields[$key] = ['side' => ['a' => $against($a[$key], $b[$key]), 'b' => $against($b[$key], $a[$key])]];
        }

        return ['expense' => true, 'a' => $a, 'b' => $b, 'fields' => $fields,
            'partial' => $this->partialSide($left, $right, true),
            'left' => $left, 'right' => $right, 'mode' => $this->expenseCompareMode($left, $right)];
    }

    /**
     * ชนิดการเทียบของแท็บค่าใช้จ่าย — ดูจาก "ระดับละเอียดสุดที่เลือก" ของทั้ง 2 ฝั่ง
     * วัน+วัน = DoD · เดือน+เดือน = MoM · ไตรมาส+ไตรมาส = QoQ · ไม่เลือกช่วงทั้งคู่ = YoY
     * 🔴 คิดจากขอบเขตจริง ไม่รับธงจาก URL (ป้ายที่บอกผิดแย่กว่าไม่มีป้าย) · ระดับไม่เท่ากัน = ไม่มีป้าย
     */
    private function expenseCompareMode(array $left, array $right): ?string
    {
        $la = ErpDashboard::paid_window($left)['level'] ?? null;
        $lb = ErpDashboard::paid_window($right)['level'] ?? null;
        if ($la !== $lb) {
            return null;
        }

        return match ($la) {
            'day' => 'dod',
            'month' => 'mom',
            'quarter' => 'qoq',
            default => ctype_digit((string) ($left['year'] ?? '')) && ctype_digit((string) ($right['year'] ?? '')) ? 'yoy' : null,
        };
    }

    /**
     * การเทียบครั้งนี้คือ YoY หรือ QoQ (เจ้าของสั่ง 2026-09-16)
     *
     * 🔴 อ่านจาก "ขอบเขตจริงของ 2 ชุด" ไม่ใช่รับธงมาจาก URL
     *    ถ้ารับธงมา ใครแก้ URL ก็ทำให้ป้ายโกหกได้ — ป้ายที่บอกผิดแย่กว่าไม่มีป้าย
     *
     * เงื่อนไข: บริษัทกับแผนกต้องเป็นตัวเดียวกัน ไม่งั้นไม่ใช่การเทียบ "ช่วงเวลา"
     *          แต่เป็นการเทียบคนละก้อนซึ่งไม่มีชื่อเรียกเฉพาะ
     */
    private function compareMode(array $filters, array $scope): ?string
    {
        // 'all' กับค่าว่าง แปลว่า "ไม่กรอง" เหมือนกัน ต้องมองเป็นค่าเดียวกัน
        $norm = fn (string $v) => $v === 'all' ? '' : $v;
        $of = fn (array $src, string $key) => $norm((string) ($src[$key] ?? ''));

        /*
          🔴 กติกาที่เจ้าของกำหนด (2026-09-16) — ดูจาก "ผู้ใช้เลือกช่องไหน" เท่านั้น
               เลือกปีอย่างเดียว        -> YoY
               เลือกไตรมาสด้วย          -> QoQ

             🔴 ไม่สนใจบริษัทกับแผนก (เจ้าของสั่งชัด)
                "ถ้ามีการยุ่งกับปีงบอย่างเดียว ให้ขึ้น YoY ไม่ว่าจะเลือกแผนกใดหรือบริษัทใดก็ตาม"
                เช่น ปี 2026 ทั้ง 2 ฝั่ง แต่แผนก HR เทียบ IT -> ยังเป็น YoY

             🔴 ไม่สนใจว่าช่วงเวลาติดกันไหม — 2026 เทียบ 2024 ก็ยังเป็น YoY
                ของเดิมบังคับว่าต้องเป็นปีก่อนหน้า/ไตรมาสก่อนหน้าติดกันเป๊ะ
                ทำให้หลายเคสไม่ขึ้นป้ายอะไรเลย ซึ่งไม่ตรงกับที่เจ้าของต้องการ

             ไม่มีป้ายก็ต่อเมื่อ "ไม่ได้ระบุปีเป็นตัวเลข" เช่นเลือกทุกปี หรืองบที่ยังไม่จัดปี
        */
        if (ctype_digit($of($filters, 'quarter')) && ctype_digit($of($scope, 'quarter'))) {
            return 'qoq';
        }

        return ctype_digit($of($filters, 'year')) && ctype_digit($of($scope, 'year')) ? 'yoy' : null;
    }

    private function chart(array $departments, array $total, array $foreign, array $filters, array $scoped): array
    {
        // ชื่อแผนกซ้ำข้ามบริษัทได้ (Mold มีทั้ง si5 และ pd) — ตอนดูทุกบริษัทต้องต่อชื่อบริษัทให้แยกออก
        $names = [];
        foreach ($departments as $d) {
            $names[$d['first']['dept_name']][$d['first']['company']] = true;
        }
        $items = [];
        foreach ($departments as $d) {
            $firm = $this->erp->companyName($d['first']['company']);
            $twin = $filters['company'] === '' && count($names[$d['first']['dept_name']]) > 1;
            $alerts = [];
            $dept_rows = array_values(array_filter($scoped, fn ($r) => $r['dept_group'] === $d['key']));
            foreach (array_unique(array_column($dept_rows, 'currency')) as $currency) {
                $currency_rows = array_values(array_filter($dept_rows, fn ($r) => $r['currency'] === $currency));
                foreach (ErpDashboard::groups($currency_rows, 'group') as $g) {
                    if ($g['total']['available'] >= 0) {
                        continue;
                    }
                    $focus = hash('sha256', $g['key'].'|'.$currency);
                    $alerts[] = ['title' => implode(' / ', $g['titles']), 'budget_no' => $g['first']['budget_no'],
                        'available' => $g['total']['available'], 'currency' => ErpDashboard::currency_label($currency),
                        'href' => $this->scopedLink($filters, ['company' => $g['first']['company'],
                            'dept' => $d['key'], 'view' => 'budgets', 'focus' => $focus]).'#budget-'.$focus];
                }
            }
            $items[] = $this->chart_item($d['total'], $d['foreign']) + [
                'alerts' => $alerts,
                'alerts_href' => $this->scopedLink($filters, ['company' => $d['first']['company'],
                    'dept' => $d['key'], 'view' => 'budgets', 'highlight' => 'negative']).'#negative-budgets',
                'name_th' => $d['first']['dept_name'].($twin ? ' · '.$firm['th'] : ''),
                'name_en' => $d['first']['dept_name'].($twin ? ' · '.$firm['en'] : ''),
                'firm_th' => $firm['th'], 'firm_en' => $firm['en'],
                'href' => $this->scopedLink($filters, ['dept' => $d['key'], 'view' => 'budgets']),
            ];
        }

        return ['items' => $items, 'total' => $this->chart_item($total, $foreign)];
    }

    private function chart_item(array $t, array $foreign): array
    {
        $baht = fn (int $satang) => number_format($satang / 100, 2);

        return [
            'count' => $t['count'],
            'v' => ['budget' => $t['budget'] / 100, 'actual' => $t['actual'] / 100, 'reserve' => $t['reserve'] / 100, 'available' => $t['available'] / 100],
            'text' => ['budget' => $baht($t['budget']), 'actual' => $baht($t['actual']), 'reserve' => $baht($t['reserve']), 'available' => $baht($t['available'])],
            'pct' => $t['budget'] > 0 ? [
                'actual' => number_format($t['actual'] / $t['budget'] * 100, 1),
                'reserve' => number_format($t['reserve'] / $t['budget'] * 100, 1),
                'available' => number_format($t['available'] / $t['budget'] * 100, 1),
            ] : null,
            'fx' => array_values(array_map(fn ($code, $f) => ['code' => ErpDashboard::currency_label((string) $code), 'budget' => $baht($f['budget'])],
                array_keys($foreign), $foreign)),
        ];
    }

    /**
     * ช่วงวันที่จ่ายที่ "มีรายการจริง" ของขอบเขตหนึ่ง (บริษัท · ปีงบ · แผนก)
     *
     * 🔴 หน้าต่างเปรียบเทียบเคยสร้างช่องวันแบบปฏิทินเปล่า (1–31) เลือกวันที่ไม่มีรายการแล้วเจอ
     *    "ไม่พบข้อมูล" (เจ้าของแจ้ง 2026-09-18) · ตอนนี้ช่องทั้ง 3 เติมจากของจริงเท่านั้น
     * 🔴 ยิงเมื่อเปิดหน้าต่าง/เปลี่ยนบริษัท-ปี-แผนก เท่านั้น ไม่ได้อ่านตอนเปิดหน้าปกติ
     */
    public function expensePeriods(Request $request): JsonResponse
    {
        abort_unless($this->allowed(), 403, 'ไม่มีสิทธิ์ดูงบ / Budget access denied');
        $filters = $this->filters($request);
        $empty = ['quarters' => [], 'months' => [], 'days' => (object) []];
        if (! preg_match('/^20\d{2}$/D', $filters['year'])) {
            return response()->json($empty);
        }
        try {
            $all = $this->dashboard->snapshot([$filters['year']]);
            $rows = ErpDashboard::filter($all, array_intersect_key($filters, array_flip(['company', 'year', 'dept'])));
            $rows = array_values(array_filter($rows, fn ($row) => $row['currency'] === 'THB'));
            if (! $rows) {
                return response()->json($empty);
            }
            $days = ErpDashboard::spend_within($this->dashboard->spending($rows)['days'], ErpDashboard::year_window($filters['year']));
            $calendar = ErpDashboard::spend_calendar($days);

            return response()->json($calendar['days'] === [] ? $empty : $calendar);
        } catch (Throwable $e) {
            Log::warning('ERP expense periods unavailable', ['error_type' => get_class($e)]);

            return response()->json($empty, 503);
        }
    }

    /**
     * ค่าที่เลือกได้ของหัวคอลัมน์ในตารางรายการ — ยิงเมื่อผู้ใช้กดปุ่มกรอง
     *
     * 🔴 แยกออกมาเพราะคิวรีนี้กิน ~1.2 วินาที แต่คนส่วนใหญ่เปิดหน้าดูเฉยๆ ไม่ได้กรอง (เจ้าของแจ้ง 2026-09-17)
     *    ด่านสิทธิ์ ขอบเขต และช่วงวันที่ ใช้ชุดเดียวกับหน้าแดชบอร์ดทุกประการ
     */
    public function filterOptions(Request $request): JsonResponse
    {
        abort_unless($this->allowed(), 403, 'ไม่มีสิทธิ์ดูงบ / Budget access denied');
        $filters = $this->filters($request);
        abort_if($filters['dept'] === '', 422, 'กรุณาระบุแผนก / Department is required');
        try {
            $all = $this->dashboard->snapshot($this->yearsNeeded($filters, $request));
            $rows = array_values(array_filter(ErpDashboard::filter($all, $filters), fn ($row) => $row['currency'] === 'THB'));
            $window = $filters['tab'] === 'expense'
              ? (ErpDashboard::paid_window($filters) ?? ErpDashboard::year_window($filters['year']))
              : null;

            return response()->json(['options' => $this->dashboard->activity_options($rows, $filters['kind'], $window)]);
        } catch (Throwable $e) {
            Log::warning('ERP column filter options unavailable', ['error_type' => get_class($e)]);

            return response()->json(['options' => []], 503);
        }
    }

    /** Exact record scope and the same access gate as the dashboard. */
    public function spend(Request $request): JsonResponse
    {
        abort_unless($this->allowed(), 403, 'ไม่มีสิทธิ์ดูงบ / Budget access denied');
        $filters = $this->filters($request);
        abort_if($filters['dept'] === '', 422, 'กรุณาระบุแผนก / Department is required');
        try {
            // อ่านเฉพาะปีที่ขอมา (กติกาเดียวกับหน้าแดชบอร์ด) — ลิงก์เก่าที่ยังใช้รหัสแผนกรวมกลุ่มถึงจะอ่านทั้งหมด
            $all = $this->dashboard->snapshot(str_contains($filters['dept'], '|g:') ? [] : $this->yearsNeeded($filters, $request));
            if (str_contains($filters['dept'], '|g:')) {
                $target = $filters['budget'] !== '' ? ErpDashboard::filter($all, ['budget' => $filters['budget'], 'company' => $filters['company']]) : [];
                if (! $target) {
                    return response()->json(['message' => 'กรุณาเลือกแผนกตาม ERP อีกครั้ง / Please select an ERP department again.'], 422);
                }
                $filters['dept'] = $target[0]['dept_key'];
            }
            $rows = ErpDashboard::filter($all, $filters);
            $thb_rows = array_values(array_filter($rows, fn ($row) => $row['currency'] === 'THB'));
            $excluded = count($rows) - count($thb_rows);
            if (! $thb_rows && $excluded) {
                return response()->json(['message' => 'สกุลเงินงบยังไม่ยืนยัน จึงไม่รวมเป็นบาท / Budget currency is unverified; excluded from THB totals.'], 422);
            }
            $activity = $filters['sort'] === 'date_desc'
              ? $this->dashboard->activity($thb_rows, $filters['kind'], $filters['page'])
              : $this->dashboard->activity($thb_rows, $filters['kind'], $filters['page'], $filters['sort']);

            return response()->json($activity + ['currency_exceptions' => $excluded]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'อ่านรายการ ERP ไม่สำเร็จ กรุณาลองใหม่ / Unable to read ERP entries. Please retry.'], 503);
        }
    }
}
