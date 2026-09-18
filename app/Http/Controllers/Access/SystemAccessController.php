<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\Access\AccessAdmin;
use App\Models\Access\LoginPosition;
use App\Models\Core\AppUser;
use App\Services\Access\AccessService;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewContract;

/**
 * สิทธิ์การเข้าถึงระบบ SBMS — ใครเป็นผู้ดูแลระบบ และตำแหน่งไหนเข้าระบบได้
 *
 * เข้าได้เฉพาะผู้ดูแลระบบ (กันไว้ที่ routes/web/access.php ด้วย middleware bms.admin)
 */
class SystemAccessController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly ActivityLogger $log,
    ) {}

    public function index(): ViewContract
    {
        return View::make('access.system', [
            'admins' => $this->access->admins(),
            'positions' => $this->access->positions(),
        ]);
    }

    /** ค้นหาพนักงานเพื่อเพิ่มเป็นผู้ดูแลระบบ — เรียกจากช่องค้นหาในหน้า (JSON) */
    public function searchUsers(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        return response()->json(['results' => $this->access->searchUsers($term)->all()]);
    }

    /**
     * เพิ่มผู้ดูแลระบบ — รับได้ทีละหลายคน (เลือกจากตัวค้นหาพนักงาน)
     *
     * คนที่เป็นแอดมินอยู่แล้วจะข้ามไปเงียบๆ ไม่ถือเป็นข้อผิดพลาด
     * เพราะผู้ใช้เลือกมาหลายคนพร้อมกัน ไม่ควรล้มทั้งชุดเพราะซ้ำคนเดียว
     */
    public function addAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_codes' => ['required', 'array', 'min:1'],
            'employee_codes.*' => ['string', 'max:30'],
        ]);

        $by = $this->currentCode($request);
        $added = [];
        $skipped = 0;

        foreach (array_unique($data['employee_codes']) as $code) {
            $user = AppUser::where('employee_code', $code)->first();

            if (! $user || $this->access->isAdmin($user)) {
                $skipped++;

                continue;
            }

            AccessAdmin::create([
                'employee_code' => $user->employee_code,
                'company' => $user->company,
                'granted_by' => $by,
                'granted_at' => now(),
            ]);

            $this->log->record('admin_added', [
                'th' => 'ให้สิทธิ์ผู้ดูแลระบบแก่ '.$user->displayName('th').' ('.$user->employee_code.')',
                'en' => 'Granted administrator rights to '.$user->displayName('en').' ('.$user->employee_code.')',
            ], $user->employee_code);

            $added[] = $user;
        }

        if ($added === []) {
            return back()->with('flash_error', [
                'th' => 'ไม่ได้เพิ่มใครเลย — คนที่เลือกเป็นผู้ดูแลระบบอยู่แล้ว หรือไม่มีบัญชีในระบบ',
                'en' => 'Nobody was added — the people selected are already administrators or have no account',
            ]);
        }

        $tail = $skipped > 0 ? ' (ข้าม '.$skipped.' คนที่ซ้ำ)' : '';
        $tailEn = $skipped > 0 ? ' ('.$skipped.' skipped)' : '';

        return back()->with('flash_success', [
            'th' => 'เพิ่มผู้ดูแลระบบแล้ว '.count($added).' คน'.$tail,
            'en' => 'Added '.count($added).' administrator(s)'.$tailEn,
        ]);
    }

    /** ถอดสิทธิ์ผู้ดูแลระบบ — ถอดได้เฉพาะแถวที่ให้สิทธิ์ที่ SBMS เท่านั้น */
    public function removeAdmin(Request $request, AccessAdmin $admin): RedirectResponse
    {
        // 🔴 กันถอดสิทธิ์ตัวเองจนไม่เหลือใครเข้าไปแก้คืนได้
        if ($admin->employee_code === $this->currentCode($request)) {
            return back()->with('flash_error', [
                'th' => 'ถอดสิทธิ์ผู้ดูแลระบบของตัวเองไม่ได้',
                'en' => 'You cannot remove your own administrator rights',
            ]);
        }

        $code = $admin->employee_code;
        $admin->delete();

        $this->log->record('admin_removed', [
            'th' => 'ถอดสิทธิ์ผู้ดูแลระบบของ '.$code,
            'en' => 'Removed administrator rights from '.$code,
        ], $code);

        return back()->with('flash_success', [
            'th' => 'ถอดสิทธิ์ผู้ดูแลระบบแล้ว',
            'en' => 'Administrator rights removed',
        ]);
    }

    /** บันทึกตำแหน่งที่เข้าสู่ระบบได้ (ติ๊กทั้งตารางแล้วกดบันทึกครั้งเดียว) */
    public function savePositions(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'allowed' => ['nullable', 'array'],
            'allowed.*' => ['string', 'max:30'],
        ]);

        $allowed = array_flip($data['allowed'] ?? []);
        $by = $this->currentCode($request);

        // เขียนทุกตำแหน่งที่มีอยู่จริง เพื่อให้ค่าในตารางตรงกับสิ่งที่เห็นบนหน้าจอเสมอ
        foreach ($this->access->positions() as $position) {
            LoginPosition::updateOrCreate(
                ['job_code' => $position['job_code']],
                [
                    'job_th' => $position['job_th'],
                    'job_en' => $position['job_en'],
                    'can_login' => isset($allowed[$position['job_code']]),
                    'updated_by' => $by,
                ]
            );
        }

        $this->log->record('positions_saved', [
            'th' => 'บันทึกตำแหน่งที่เข้าระบบได้ — อนุญาต '.count($allowed).' ตำแหน่ง',
            'en' => 'Saved sign-in positions — '.count($allowed).' allowed',
        ]);

        return back()->with('flash_success', [
            'th' => 'บันทึกตำแหน่งที่เข้าสู่ระบบได้แล้ว',
            'en' => 'Login positions saved',
        ]);
    }

    private function currentCode(Request $request): ?string
    {
        $me = app()->bound('current_user') ? app('current_user') : null;

        return $me?->employee_code;
    }
}
