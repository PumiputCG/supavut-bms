<?php

declare(strict_types=1);

use App\Services\Core\InsightMirror;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv, true);
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$candidates = $input['signatures'] ?? [];

if (! is_array($candidates) || $candidates === []) {
    throw new RuntimeException('No Memo signatures were supplied.');
}

$validated = [];
foreach ($candidates as $employee_code => $candidate) {
    $employee_code = trim((string) $employee_code);
    $data_url = trim((string) ($candidate['data_url'] ?? ''));
    $source = trim((string) ($candidate['source'] ?? ''));

    if (! preg_match('/^\d+$/', $employee_code)) {
        throw new RuntimeException("Invalid employee code: {$employee_code}");
    }

    if (! preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=\r\n]+)$#', $data_url, $matches)) {
        throw new RuntimeException("Invalid signature data URL for employee {$employee_code}");
    }

    $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);
    if ($binary === false || $binary === '' || strlen($binary) > 2 * 1024 * 1024) {
        throw new RuntimeException("Invalid signature image payload for employee {$employee_code}");
    }

    $image_info = getimagesizefromstring($binary);
    if ($image_info === false || ! in_array($image_info['mime'], ['image/png', 'image/jpeg'], true)) {
        throw new RuntimeException("Unsupported signature image for employee {$employee_code}");
    }

    $validated[$employee_code] = [
        'data_url' => $data_url,
        'source' => $source,
        'source_reference' => (string) ($candidate['source_reference'] ?? ''),
        'bytes' => strlen($binary),
        'width' => (int) $image_info[0],
        'height' => (int) $image_info[1],
        'sha256' => hash('sha256', $binary),
    ];
}

$codes = array_keys($validated);
$rows = DB::connection('insight')->table('app_users')
    ->whereIn('employee_code', $codes)
    ->get(['id', 'employee_code', 'signature'])
    ->groupBy(fn ($row) => trim((string) $row->employee_code));

$duplicates = $rows->filter(fn ($group) => $group->count() > 1)->keys()->values()->all();
if ($duplicates !== []) {
    throw new RuntimeException('Duplicate Insight app_users employee codes: '.implode(', ', $duplicates));
}

$matched_codes = array_values(array_filter($codes, fn ($code) => $rows->has($code)));
$unmatched_codes = array_values(array_diff($codes, $matched_codes));
$source_counts = [];
foreach ($matched_codes as $code) {
    $source = $validated[$code]['source'];
    $source_counts[$source] = ($source_counts[$source] ?? 0) + 1;
}

$result = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'candidate_people' => count($validated),
    'matched_insight_app_users' => count($matched_codes),
    'unmatched_employee_codes' => $unmatched_codes,
    'duplicate_employee_codes' => $duplicates,
    'source_counts_for_matched' => $source_counts,
    'overwriting_existing_signatures' => count(array_filter(
        $matched_codes,
        fn ($code) => trim((string) $rows->get($code)->first()->signature) !== ''
    )),
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

$backup_rows = [];
foreach ($matched_codes as $code) {
    $row = $rows->get($code)->first();
    $backup_rows[] = [
        'id' => (int) $row->id,
        'employee_code' => $code,
        'signature' => $row->signature,
    ];
}

$backup_path = $backup_directory."/insight_signatures_before_memo_{$timestamp}.enc";
$encrypted_backup = Crypt::encryptString(json_encode([
    'created_at' => now()->toIso8601String(),
    'reason' => 'Before importing and overwriting Insight signatures from Memo Online',
    'rows' => $backup_rows,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

if (file_put_contents($backup_path, $encrypted_backup, LOCK_EX) === false) {
    throw new RuntimeException("Cannot write encrypted backup: {$backup_path}");
}

$has_updated_at = DB::connection('insight')->getSchemaBuilder()->hasColumn('app_users', 'updated_at');
DB::connection('insight')->transaction(function () use ($matched_codes, $rows, $validated, $has_updated_at): void {
    foreach ($matched_codes as $code) {
        $row = $rows->get($code)->first();
        $values = ['signature' => $validated[$code]['data_url']];
        if ($has_updated_at) {
            $values['updated_at'] = now();
        }

        $updated = DB::connection('insight')->table('app_users')
            ->where('id', $row->id)
            ->where('employee_code', $code)
            ->update($values);

        if ($updated !== 1) {
            throw new RuntimeException("Expected one updated Insight row for employee {$code}; got {$updated}");
        }
    }
});

$verification_failures = [];
foreach ($matched_codes as $code) {
    $stored = (string) DB::connection('insight')->table('app_users')
        ->where('employee_code', $code)
        ->value('signature');
    if (! hash_equals(hash('sha256', $validated[$code]['data_url']), hash('sha256', $stored))) {
        $verification_failures[] = $code;
    }
}

if ($verification_failures !== []) {
    throw new RuntimeException('Post-write verification failed for: '.implode(', ', $verification_failures));
}

$mirror = app(InsightMirror::class);
$mirrored = 0;
$mirror_failures = [];
foreach ($matched_codes as $code) {
    try {
        if ($mirror->pullUserByCode($code) !== null) {
            $mirrored++;
        } else {
            $mirror_failures[] = $code;
        }
    } catch (Throwable) {
        $mirror_failures[] = $code;
    }
}

$manifest_path = $backup_directory."/memo_signature_import_{$timestamp}.json";
$manifest = [
    'imported_at' => now()->toIso8601String(),
    'backup_path' => $backup_path,
    'updated_people' => array_map(fn ($code) => [
        'employee_code' => $code,
        'source' => $validated[$code]['source'],
        'source_reference' => $validated[$code]['source_reference'],
        'bytes' => $validated[$code]['bytes'],
        'width' => $validated[$code]['width'],
        'height' => $validated[$code]['height'],
        'sha256' => $validated[$code]['sha256'],
    ], $matched_codes),
    'unmatched_employee_codes' => $unmatched_codes,
    'mirror_failures' => $mirror_failures,
];
file_put_contents(
    $manifest_path,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    LOCK_EX
);

$result['updated_insight_people'] = count($matched_codes);
$result['verified_insight_people'] = count($matched_codes);
$result['mirrored_to_bms_people'] = $mirrored;
$result['mirror_failures'] = $mirror_failures;
$result['encrypted_backup_path'] = $backup_path;
$result['manifest_path'] = $manifest_path;

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
