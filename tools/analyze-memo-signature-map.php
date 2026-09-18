<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$file_codes = array_values(array_unique(array_filter(array_map('trim', $input['file_codes'] ?? []))));
$flow_codes = array_values(array_unique(array_filter(array_map('trim', $input['flow_codes'] ?? []))));
$all_codes = array_values(array_unique(array_merge($file_codes, $flow_codes)));

$users = DB::connection('insight')->table('app_users')
    ->whereIn('employee_code', $all_codes)
    ->get(['employee_code', 'signature'])
    ->groupBy(fn ($row) => trim((string) $row->employee_code));

$employees = DB::connection('insight')->table('employees')
    ->whereIn('employee_code', $all_codes)
    ->pluck('employee_code')
    ->map(fn ($code) => trim((string) $code))
    ->unique()
    ->values()
    ->all();

$employee_lookup = array_fill_keys($employees, true);
$has_user = fn (string $code): bool => $users->has($code);
$has_employee = fn (string $code): bool => isset($employee_lookup[$code]);
$has_insight_signature = function (string $code) use ($users): bool {
    return $users->get($code, collect())->contains(
        fn ($row) => trim((string) ($row->signature ?? '')) !== ''
    );
};

$file_matched_users = array_values(array_filter($file_codes, $has_user));
$flow_matched_users = array_values(array_filter($flow_codes, $has_user));
$union_matched_users = array_values(array_filter($all_codes, $has_user));
$union_matched_employees = array_values(array_filter($all_codes, $has_employee));
$file_mapped_with_signature = array_values(array_filter($file_matched_users, $has_insight_signature));
$file_mapped_without_signature = array_values(array_diff($file_matched_users, $file_mapped_with_signature));
$mapped_with_signature = array_values(array_filter($union_matched_users, $has_insight_signature));
$mapped_without_signature = array_values(array_diff($union_matched_users, $mapped_with_signature));

$insight_signature_summary = DB::connection('insight')->table('app_users')
    ->selectRaw('COUNT(*) AS rows_with_signature, COUNT(DISTINCT employee_code) AS employees_with_signature')
    ->whereNotNull('signature')
    ->whereRaw("TRIM(signature) <> ''")
    ->first();

$duplicate_user_codes = DB::connection('insight')->table('app_users')
    ->selectRaw('employee_code, COUNT(*) AS row_count')
    ->whereIn('employee_code', $all_codes)
    ->groupBy('employee_code')
    ->havingRaw('COUNT(*) > 1')
    ->get()
    ->mapWithKeys(fn ($row) => [trim((string) $row->employee_code) => (int) $row->row_count])
    ->all();

$signature_column = DB::connection('insight')->selectOne(
    "SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_users' AND COLUMN_NAME = 'signature'"
);

echo json_encode([
    'memo_file_people' => count($file_codes),
    'memo_flow_signature_people' => count($flow_codes),
    'memo_union_people' => count($all_codes),
    'file_codes_matched_to_insight_app_users' => count($file_matched_users),
    'file_codes_already_have_insight_signature' => count($file_mapped_with_signature),
    'file_codes_missing_insight_signature' => count($file_mapped_without_signature),
    'file_codes_unmatched_to_insight_app_users' => array_values(array_diff($file_codes, $file_matched_users)),
    'flow_codes_matched_to_insight_app_users' => count($flow_matched_users),
    'union_codes_matched_to_insight_app_users' => count($union_matched_users),
    'union_codes_matched_to_insight_employees' => count($union_matched_employees),
    'mapped_people_already_have_insight_signature' => count($mapped_with_signature),
    'mapped_people_missing_insight_signature' => count($mapped_without_signature),
    'unmatched_to_insight_app_users' => array_values(array_diff($all_codes, $union_matched_users)),
    'unmatched_to_insight_employees' => array_values(array_diff($all_codes, $union_matched_employees)),
    'mapped_missing_insight_signature_codes' => $mapped_without_signature,
    'duplicate_candidate_app_user_codes' => $duplicate_user_codes,
    'signature_column' => $signature_column,
    'insight_total_signature_rows' => (int) ($insight_signature_summary->rows_with_signature ?? 0),
    'insight_total_signature_people' => (int) ($insight_signature_summary->employees_with_signature ?? 0),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
