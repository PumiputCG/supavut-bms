<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv, true);
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$codes = array_values(array_unique(array_filter(array_map(
    fn ($code) => trim((string) $code),
    $input['employee_codes'] ?? []
))));

if ($codes === []) {
    throw new RuntimeException('No employee codes were supplied.');
}

$source_rows = DB::connection('insight')->table('app_users')
    ->whereIn('employee_code', $codes)
    ->get(['id', 'employee_code', 'signature'])
    ->keyBy(fn ($row) => (int) $row->id);

$source_duplicates = $source_rows->groupBy(fn ($row) => trim((string) $row->employee_code))
    ->filter(fn ($group) => $group->count() > 1)
    ->keys()
    ->values()
    ->all();
if ($source_duplicates !== []) {
    throw new RuntimeException('Duplicate Insight employee codes: '.implode(', ', $source_duplicates));
}

$local_rows = DB::connection('mysql')->table('app_users')
    ->whereIn('insight_id', $source_rows->keys()->all())
    ->get(['id', 'insight_id', 'employee_code', 'signature'])
    ->keyBy(fn ($row) => (int) $row->insight_id);

$matched_source_ids = $source_rows->keys()->filter(fn ($id) => $local_rows->has((int) $id))->values()->all();
$missing_local_codes = $source_rows->filter(fn ($row) => ! $local_rows->has((int) $row->id))
    ->pluck('employee_code')
    ->map(fn ($code) => trim((string) $code))
    ->values()
    ->all();

$result = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'requested_people' => count($codes),
    'source_people' => $source_rows->count(),
    'matched_local_people' => count($matched_source_ids),
    'missing_local_employee_codes' => $missing_local_codes,
    'insight_total_signature_people' => DB::connection('insight')->table('app_users')
        ->whereNotNull('signature')->whereRaw("TRIM(signature) <> ''")->distinct()->count('employee_code'),
    'bms_total_signature_people' => DB::connection('mysql')->table('app_users')
        ->whereNotNull('signature')->whereRaw("TRIM(signature) <> ''")->distinct()->count('employee_code'),
];

if (! $apply) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

$timestamp = now()->format('Ymd_His');
$backup_directory = storage_path('app/private/backups');
if (! is_dir($backup_directory) && ! mkdir($backup_directory, 0700, true) && ! is_dir($backup_directory)) {
    throw new RuntimeException("Cannot create private backup directory: {$backup_directory}");
}

$backup_path = $backup_directory."/bms_signatures_before_memo_sync_{$timestamp}.enc";
$backup = $local_rows->only($matched_source_ids)->map(fn ($row) => [
    'id' => (int) $row->id,
    'insight_id' => (int) $row->insight_id,
    'employee_code' => (string) $row->employee_code,
    'signature' => $row->signature,
])->values()->all();

$encrypted = Crypt::encryptString(json_encode([
    'created_at' => now()->toIso8601String(),
    'reason' => 'Before targeted signature sync after Memo import into Insight',
    'rows' => $backup,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
if (file_put_contents($backup_path, $encrypted, LOCK_EX) === false) {
    throw new RuntimeException("Cannot write encrypted BMS backup: {$backup_path}");
}

DB::connection('mysql')->transaction(function () use ($matched_source_ids, $source_rows, $local_rows): void {
    foreach ($matched_source_ids as $source_id) {
        $source = $source_rows->get((int) $source_id);
        $local = $local_rows->get((int) $source_id);
        $updated = DB::connection('mysql')->table('app_users')
            ->where('id', $local->id)
            ->where('insight_id', $source->id)
            ->update([
                'signature' => $source->signature,
                'mirrored_at' => now(),
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw new RuntimeException("Expected one updated BMS row for Insight ID {$source_id}; got {$updated}");
        }
    }
});

$verification_failures = [];
foreach ($matched_source_ids as $source_id) {
    $source_signature = (string) $source_rows->get((int) $source_id)->signature;
    $local_signature = (string) DB::connection('mysql')->table('app_users')
        ->where('insight_id', $source_id)
        ->value('signature');
    if (! hash_equals(hash('sha256', $source_signature), hash('sha256', $local_signature))) {
        $verification_failures[] = (string) $source_rows->get((int) $source_id)->employee_code;
    }
}

if ($verification_failures !== []) {
    throw new RuntimeException('BMS signature verification failed for: '.implode(', ', $verification_failures));
}

$result['updated_local_people'] = count($matched_source_ids);
$result['verified_local_people'] = count($matched_source_ids);
$result['encrypted_backup_path'] = $backup_path;

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
