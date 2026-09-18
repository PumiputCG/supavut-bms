<?php

// Read-only verification. Protected budgets are excluded in SQL before monetary data is read.
require_once __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use App\Services\Erp\ErpDashboard as D;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$db = DB::connection('erp');
$db->getPdo()->setAttribute(PDO::SQLSRV_ATTR_QUERY_TIMEOUT, 60);
$parts = [];
$params = [];
foreach (D::payroll_patterns() as $pattern) {
    $parts[] = "(' ' + COALESCE(b.COMMENT_, '') + ' ') NOT LIKE ?";
    $parts[] = "(' ' + COALESCE(a.ACCOUNTNAME, '') + ' ') NOT LIKE ?";
    array_push($params, $pattern, $pattern);
}
foreach (D::payroll_budget_patterns() as $pattern) {
    $parts[] = "LTRIM(RTRIM(COALESCE(b.BPC_BUDGETNO, ''))) NOT LIKE ?";
    $params[] = $pattern;
}
$policy = implode(' AND ', $parts);
$safe = "SELECT b.* FROM dbo.LEDGERBUDGET b LEFT JOIN dbo.LEDGERTABLE a ON a.DATAAREAID=b.DATAAREAID AND a.ACCOUNTNUM=b.ACCOUNTNUM WHERE b.DATAAREAID IN ('si5','pd') AND $policy";
$rows = $db->select("WITH safe AS ($safe) SELECT DATAAREAID,RECID,MODELNUM,BPC_BUDGETNO,DIMENSION,DIMENSION2_,DIMENSION3_,CURRENCY,AMOUNT,AMOUNTMST,BPC_BUDGET,BPC_TRANSFER,BPC_ACTUALAMOUNT,BPC_RESERVEAMOUNT,BPC_AVAILABLEAMOUNT,STOP,ACTIVE FROM safe", $params);
$out = ['at' => date(DATE_ATOM), 'rows' => count($rows), 'amount_difference' => ['count' => 0, 'sum' => 0, 'by_year' => [], '2026_rows' => []], 'formula' => ['thb_rows' => 0, 'mst_matches' => 0, 'bpc_matches' => 0, '2026_mst_difference' => 0], 'status' => ['stop' => 0, 'inactive' => 0, 'both' => 0, '2026_stop' => 0, '2026_inactive' => 0], 'foreign' => []];
$departments = [];
$tuple_headers = [];
$money = fn ($v) => D::satang((string) $v);
$key = fn ($r) => implode('|', array_map(fn ($f) => trim((string) $r->$f), ['DATAAREAID', 'MODELNUM', 'BPC_BUDGETNO', 'DIMENSION', 'DIMENSION2_', 'DIMENSION3_']));
foreach ($rows as $r) {
    $year = D::budget_year(trim($r->MODELNUM), trim($r->DIMENSION3_)) ?? 'unassigned';
    $departments[trim($r->DATAAREAID).'|'.trim($r->DIMENSION)] = true;
    $out['status']['stop'] += (int) (bool) $r->STOP;
    $out['status']['inactive'] += (int) ! $r->ACTIVE;
    $out['status']['both'] += (int) ($r->STOP && ! $r->ACTIVE);
    if ((string) $year === '2026') {
        $out['status']['2026_stop'] += (int) (bool) $r->STOP;
        $out['status']['2026_inactive'] += (int) ! $r->ACTIVE;
    }
    if (trim($r->CURRENCY) !== 'THB') {
        $out['foreign'][] = ['company' => $r->DATAAREAID, 'budget_no' => $r->BPC_BUDGETNO, 'currency' => $r->CURRENCY, 'amount' => $r->AMOUNT, 'mst' => $r->AMOUNTMST];

        continue;
    }
    $mst = $money($r->AMOUNTMST);
    $bpc = $money($r->BPC_BUDGET) + $money($r->BPC_TRANSFER);
    $actual = $money($r->BPC_ACTUALAMOUNT);
    $reserve = $money($r->BPC_RESERVEAMOUNT);
    $available = $money($r->BPC_AVAILABLEAMOUNT);
    $diff = $mst - $bpc;
    if ($diff) {
        $out['amount_difference']['count']++;
        $out['amount_difference']['sum'] += $diff;
        $out['amount_difference']['by_year'][$year] = ($out['amount_difference']['by_year'][$year] ?? 0) + $diff;
        if ((string) $year === '2026') {
            $out['amount_difference']['2026_rows'][] = ['company' => $r->DATAAREAID, 'budget_no' => $r->BPC_BUDGETNO, 'period' => $r->DIMENSION3_, 'mst' => $mst, 'bpc' => $bpc, 'difference' => $diff];
        }
    }
    $out['formula']['thb_rows']++;
    $out['formula']['mst_matches'] += (int) ($mst - $actual - $reserve === $available);
    $out['formula']['bpc_matches'] += (int) ($bpc - $actual - $reserve === $available);
    if ((string) $year === '2026') {
        $out['formula']['2026_mst_difference'] += $available - ($mst - $actual - $reserve);
    }
    $k = $key($r);
    if (! isset($tuple_headers[$k])) {
        $tuple_headers[$k] = ['year' => $year, 'company' => trim($r->DATAAREAID), 'dept' => trim($r->DIMENSION), 'actual' => 0, 'reserve' => 0];
    }
    $tuple_headers[$k]['actual'] += $actual;
    $tuple_headers[$k]['reserve'] += $reserve;
}
$out['departments'] = count($departments);
// Metadata only for historical companies, with the same exclusion rules.
$out['historical'] = $db->select("SELECT b.DATAAREAID,COUNT(*) AS n,MIN(YEAR(b.STARTDATE)) AS first_start_year,MAX(YEAR(b.STARTDATE)) AS last_start_year FROM dbo.LEDGERBUDGET b LEFT JOIN dbo.LEDGERTABLE a ON a.DATAAREAID=b.DATAAREAID AND a.ACCOUNTNUM=b.ACCOUNTNUM WHERE b.DATAAREAID NOT IN ('si5','pd') AND $policy GROUP BY b.DATAAREAID", $params);
file_put_contents(__DIR__.'/../storage/app/private/dashboard-audit-recheck.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Budget fields and historical metadata checked.\n";
$fields = ['DATAAREAID', 'MODELNUM', 'BPC_BUDGETNO', 'DIMENSION', 'DIMENSION2_', 'DIMENSION3_'];
$cols = implode(',', $fields);
$join = implode(' AND ', array_map(fn ($f) => "b.$f=k.$f", $fields));
$tj = implode(' AND ', array_map(fn ($f) => "t.$f=k.$f", $fields));
$bcols = implode(',', array_map(fn ($f) => "b.$f", $fields));
$kcols = implode(',', array_map(fn ($f) => "k.$f", $fields));
$transactions = $db->select("WITH safe AS ($safe), keys_ AS (
  SELECT $cols,COUNT(*) AS selected_count FROM safe WHERE CURRENCY='THB' GROUP BY $cols
), valid_keys AS (
  SELECT $kcols FROM keys_ k JOIN dbo.LEDGERBUDGET b ON $join GROUP BY $kcols,k.selected_count HAVING COUNT(*)=k.selected_count
), matched AS (
  SELECT $bcols,t.STATUS,t.AMOUNTMST FROM dbo.BPC_LEDGERBUDGETTRANS t JOIN safe b ON b.DATAAREAID=t.DATAAREAID AND b.RECID=t.REFLEDGERBUDGETRECID WHERE b.CURRENCY='THB' AND t.CANCEL=0 AND t.STATUS IN (1,2)
  UNION ALL
  SELECT $kcols,t.STATUS,t.AMOUNTMST FROM dbo.BPC_LEDGERBUDGETTRANS t JOIN valid_keys k ON $tj WHERE t.REFLEDGERBUDGETRECID=0 AND t.CANCEL=0 AND t.STATUS IN (1,2)
) SELECT $cols,STATUS,SUM(AMOUNTMST) AS amount,COUNT(*) AS n FROM matched GROUP BY $cols,STATUS", $params);
$details = [];
foreach ($transactions as $r) {
    $details[$key($r)][(int) $r->STATUS] = $money($r->amount);
}
$out['reconciliation'] = ['tuple_groups' => count($tuple_headers), 'actual_mismatch' => 0, 'reserve_mismatch' => 0, 'all_difference' => 0, '2026_difference' => 0, '2026_header_actual' => 0, '2026_depts' => []];
foreach ($tuple_headers as $k => $h) {
    $diff = $h['actual'] - ($details[$k][2] ?? 0);
    $out['reconciliation']['actual_mismatch'] += (int) ($diff !== 0);
    $out['reconciliation']['reserve_mismatch'] += (int) ($h['reserve'] !== ($details[$k][1] ?? 0));
    $out['reconciliation']['all_difference'] += $diff;
    if ((string) $h['year'] === '2026') {
        $out['reconciliation']['2026_difference'] += $diff;
        $out['reconciliation']['2026_header_actual'] += $h['actual'];
        $dept = $h['company'].'|'.$h['dept'];
        $out['reconciliation']['2026_depts'][$dept] = ($out['reconciliation']['2026_depts'][$dept] ?? 0) + $diff;
    }
}
$out['completed_at'] = date(DATE_ATOM);
$out['historical_unfiltered_metadata'] = $db->select("SELECT DATAAREAID,COUNT(*) AS n FROM dbo.LEDGERBUDGET WHERE DATAAREAID NOT IN ('si5','pd') GROUP BY DATAAREAID");
$service = $app->make(D::class);
$snapshot = $service->snapshot();
$out['service_check'] = [];
foreach (['2026'] as $period) {
    $header = 0;
    $detail = 0;
    $year_rows = array_values(array_filter($snapshot, fn ($r) => $r['currency'] === 'THB' && (string) $r['year'] === $period));
    foreach (array_unique(array_column($year_rows, 'dept_group')) as $dept) {
        $scope = array_values(array_filter($year_rows, fn ($r) => $r['dept_group'] === $dept));
        $header += array_sum(array_column($scope, 'actual'));
        $activity = $service->activity($scope, 'actual');
        $detail += $activity['total'];
    }
    $out['service_check'][$period] = ['header' => $header, 'details' => $detail, 'difference' => $header - $detail];
}
$out['service_completed_at'] = date(DATE_ATOM);
file_put_contents(__DIR__.'/../storage/app/private/dashboard-audit-recheck.json', json_encode($out,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($out,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
