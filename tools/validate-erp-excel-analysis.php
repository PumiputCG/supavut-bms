<?php

declare(strict_types=1);

/**
 * Independently validates the aggregates in the four ERP Excel exports.
 * Read-only: no connection to ERP and no workbook is changed.
 */
$base_path = $argv[1] ?? __DIR__.'/../ให้วิเคราะห์/วิเคราะห์ ERP';
$output_path = $argv[2] ?? null;

function xlsx_column_index(string $cell_reference): int
{
    preg_match('/^([A-Z]+)/i', $cell_reference, $match);
    $index = 0;
    foreach (str_split(strtoupper($match[1] ?? '')) as $letter) {
        $index = ($index * 26) + ord($letter) - 64;
    }

    return $index;
}

function xlsx_rows(string $xlsx_path): Generator
{
    $zip = new ZipArchive;
    if ($zip->open($xlsx_path) !== true) {
        throw new RuntimeException("Cannot open {$xlsx_path}");
    }

    $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
    $relationships = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
    if ($workbook === false || $relationships === false) {
        throw new RuntimeException("Invalid workbook XML: {$xlsx_path}");
    }

    $relationship_map = [];
    foreach ($relationships->Relationship as $relationship) {
        $target = str_replace('\\', '/', (string) $relationship['Target']);
        $relationship_map[(string) $relationship['Id']] = str_starts_with($target, 'xl/')
          ? $target
          : 'xl/'.ltrim($target, '/');
    }

    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $sheet = $workbook->sheets->sheet[0];
    $relationship_attributes = $sheet->attributes('r', true);
    $worksheet_path = $relationship_map[(string) $relationship_attributes['id']];
    $stream = $zip->getStream($worksheet_path);
    if ($stream === false) {
        throw new RuntimeException("Cannot read {$worksheet_path}");
    }

    $temporary_path = tempnam(sys_get_temp_dir(), 'bms-erp-xlsx-');
    $temporary = fopen((string) $temporary_path, 'wb');
    stream_copy_to_stream($stream, $temporary);
    fclose($temporary);
    fclose($stream);

    $reader = new XMLReader;
    $reader->open((string) $temporary_path);
    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
            continue;
        }

        $row_xml = $reader->readOuterXml();
        $row = simplexml_load_string($row_xml);
        if ($row === false) {
            continue;
        }

        $values = [];
        foreach ($row->c as $cell) {
            $column = xlsx_column_index((string) $cell['r']);
            $type = (string) $cell['t'];
            if ($type === 'inlineStr') {
                $value = (string) ($cell->is->t ?? '');
                if (isset($cell->is->r)) {
                    $value = '';
                    foreach ($cell->is->r as $run) {
                        $value .= (string) ($run->t ?? '');
                    }
                }
            } else {
                $value = (string) ($cell->v ?? '');
            }
            $values[$column] = $value;
        }

        yield (int) ($row['r'] ?? 0) => $values;
    }

    $reader->close();
    unlink((string) $temporary_path);
    $zip->close();
}

function string_value(array $row, int $column): string
{
    return trim((string) ($row[$column] ?? ''));
}

function number_value(array $row, int $column): float
{
    $value = string_value($row, $column);

    return $value === '' ? 0.0 : (float) $value;
}

function increment(array &$counts, string|int $key, int $amount = 1): void
{
    $counts[(string) $key] = ($counts[(string) $key] ?? 0) + $amount;
}

function top_counts(array $counts, int $limit = 12): array
{
    arsort($counts);

    return array_slice($counts, 0, $limit, true);
}

$po_line_path = $base_path.'/01_PO-รายการสั่งซื้อ.xlsx';
$po_header_path = $base_path.'/02_PO-หัวเอกสาร.xlsx';
$budget_path = $base_path.'/03_งบประมาณ-LEDGERBUDGET.xlsx';

$po = [
    'rows' => 0,
    'unique_po' => [],
    'budget_codes' => [],
    'model_budget_pairs' => [],
    'departments' => [],
    'blank_budget_rows' => 0,
    'blank_budget_amount' => 0.0,
    'blank_ledger_account_rows' => 0,
    'nonblank_ledger_accounts' => [],
    'zero_amount_rows' => 0,
    'budget_status_counts' => [],
    'year' => [],
    'year_with_budget' => [],
];
foreach (xlsx_rows($po_line_path) as $row_number => $row) {
    if ($row_number === 1) {
        continue;
    }
    $po['rows']++;
    $purch_id = string_value($row, 1);
    $year = string_value($row, 4);
    $amount = number_value($row, 11);
    $ledger_account = string_value($row, 15);
    $department = string_value($row, 16);
    $model = string_value($row, 20);
    $budget_no = string_value($row, 21);
    $budget_status = string_value($row, 22);

    $po['unique_po'][$purch_id] = true;
    if ($department !== '') {
        $po['departments'][$department] = true;
    }
    if ($ledger_account === '') {
        $po['blank_ledger_account_rows']++;
    } else {
        increment($po['nonblank_ledger_accounts'], $ledger_account);
    }
    if (abs($amount) < 0.000001) {
        $po['zero_amount_rows']++;
    }
    increment($po['budget_status_counts'], $budget_status === '' ? '(blank)' : $budget_status);
    if (! isset($po['year'][$year])) {
        $po['year'][$year] = ['rows' => 0, 'amount' => 0.0];
    }
    $po['year'][$year]['rows']++;
    $po['year'][$year]['amount'] += $amount;

    if ($budget_no === '') {
        $po['blank_budget_rows']++;
        $po['blank_budget_amount'] += $amount;

        continue;
    }

    $po['budget_codes'][$budget_no] = true;
    $po['model_budget_pairs'][$model."\x1F".$budget_no] = true;
    if (! isset($po['year_with_budget'][$year])) {
        $po['year_with_budget'][$year] = ['rows' => 0, 'amount' => 0.0];
    }
    $po['year_with_budget'][$year]['rows']++;
    $po['year_with_budget'][$year]['amount'] += $amount;
}

$headers = [
    'rows' => 0,
    'unique_po' => [],
    'blank_budget_rows' => 0,
    'budget_codes' => [],
    'departments' => [],
    'duplicate_po_rows' => 0,
];
foreach (xlsx_rows($po_header_path) as $row_number => $row) {
    if ($row_number === 1) {
        continue;
    }
    $headers['rows']++;
    $purch_id = string_value($row, 1);
    $budget_no = string_value($row, 17);
    $department = string_value($row, 12);
    if (isset($headers['unique_po'][$purch_id])) {
        $headers['duplicate_po_rows']++;
    }
    $headers['unique_po'][$purch_id] = true;
    if ($budget_no === '') {
        $headers['blank_budget_rows']++;
    } else {
        $headers['budget_codes'][$budget_no] = true;
    }
    if ($department !== '') {
        $headers['departments'][$department] = true;
    }
}

$budget = [
    'rows' => 0,
    'budget_code_counts' => [],
    'model_budget_counts' => [],
    'year_model_budget_counts' => [],
    'departments' => [],
    'year' => [],
    'available_formula_matches' => [
        'budget_minus_actual' => 0,
        'budget_minus_actual_minus_reserve' => 0,
        'budget_minus_actual_minus_reserve_minus_sp' => 0,
        'budget_minus_actual_minus_reserve_minus_sp_plus_transfer' => 0,
    ],
    'available_formula_unmatched' => 0,
    'available_formula_unmatched_examples' => [],
];
foreach (xlsx_rows($budget_path) as $row_number => $row) {
    if ($row_number === 1) {
        continue;
    }
    $budget['rows']++;
    $model = string_value($row, 1);
    $budget_no = string_value($row, 2);
    $department = string_value($row, 5);
    $year = string_value($row, 10);
    $budget_amount = number_value($row, 12);
    $actual = number_value($row, 13);
    $available = number_value($row, 14);
    $reserve = number_value($row, 17);
    $sp_amount = number_value($row, 18);
    $transfer = number_value($row, 19);

    increment($budget['budget_code_counts'], $budget_no === '' ? '(blank)' : $budget_no);
    increment($budget['model_budget_counts'], $model.' | '.$budget_no);
    increment($budget['year_model_budget_counts'], $year.' | '.$model.' | '.$budget_no);
    if ($department !== '') {
        $budget['departments'][$department] = true;
    }
    if (! isset($budget['year'][$year])) {
        $budget['year'][$year] = ['rows' => 0, 'budget' => 0.0, 'actual' => 0.0, 'available' => 0.0];
    }
    $budget['year'][$year]['rows']++;
    $budget['year'][$year]['budget'] += $budget_amount;
    $budget['year'][$year]['actual'] += $actual;
    $budget['year'][$year]['available'] += $available;

    $formulas = [
        'budget_minus_actual' => $budget_amount - $actual,
        'budget_minus_actual_minus_reserve' => $budget_amount - $actual - $reserve,
        'budget_minus_actual_minus_reserve_minus_sp' => $budget_amount - $actual - $reserve - $sp_amount,
        'budget_minus_actual_minus_reserve_minus_sp_plus_transfer' => $budget_amount - $actual - $reserve - $sp_amount + $transfer,
    ];
    $matched = false;
    foreach ($formulas as $formula => $expected) {
        if (abs($available - $expected) <= 0.02) {
            $budget['available_formula_matches'][$formula]++;
            $matched = true;
        }
    }
    if (! $matched) {
        $budget['available_formula_unmatched']++;
        if (count($budget['available_formula_unmatched_examples']) < 20) {
            $budget['available_formula_unmatched_examples'][] = [
                'model' => $model,
                'budget_no' => $budget_no,
                'year' => $year,
                'budget' => $budget_amount,
                'actual' => $actual,
                'available' => $available,
                'reserve' => $reserve,
                'sp_amount' => $sp_amount,
                'transfer' => $transfer,
                'expected' => $formulas['budget_minus_actual_minus_reserve_minus_sp_plus_transfer'],
                'difference' => $available - $formulas['budget_minus_actual_minus_reserve_minus_sp_plus_transfer'],
            ];
        }
    }
}

$budget_codes = array_filter(array_keys($budget['budget_code_counts']), fn ($code) => $code !== '(blank)');
$budget_code_set = array_fill_keys($budget_codes, true);
$po_budget_codes = array_keys($po['budget_codes']);
$unmatched_po_budget_codes = array_values(array_diff($po_budget_codes, $budget_codes));
$budget_only_codes = array_values(array_diff($budget_codes, $po_budget_codes));

$duplicate_budget_codes = array_filter($budget['budget_code_counts'], fn ($count, $code) => $code !== '(blank)' && $count > 1, ARRAY_FILTER_USE_BOTH);
$duplicate_model_budget = array_filter($budget['model_budget_counts'], fn ($count) => $count > 1);
$duplicate_year_model_budget = array_filter($budget['year_model_budget_counts'], fn ($count) => $count > 1);

$po_departments = array_keys($po['departments']);
$budget_departments = array_keys($budget['departments']);
$po_ids = array_keys($po['unique_po']);
$header_po_ids = array_keys($headers['unique_po']);
sort($po_departments);
sort($budget_departments);

$result = [
    'generated_at' => date(DATE_ATOM),
    'po_lines' => [
        'rows' => $po['rows'],
        'unique_po_count' => count($po['unique_po']),
        'blank_budget_rows' => $po['blank_budget_rows'],
        'blank_budget_amount' => round($po['blank_budget_amount'], 2),
        'nonblank_budget_rows' => $po['rows'] - $po['blank_budget_rows'],
        'distinct_budget_codes' => count($po['budget_codes']),
        'distinct_model_budget_pairs' => count($po['model_budget_pairs']),
        'blank_ledger_account_rows' => $po['blank_ledger_account_rows'],
        'nonblank_ledger_accounts' => $po['nonblank_ledger_accounts'],
        'zero_amount_rows' => $po['zero_amount_rows'],
        'budget_status_counts' => $po['budget_status_counts'],
        'year_all_rows' => $po['year'],
        'year_with_budget' => $po['year_with_budget'],
    ],
    'po_headers' => [
        'rows' => $headers['rows'],
        'unique_po_count' => count($headers['unique_po']),
        'duplicate_po_rows' => $headers['duplicate_po_rows'],
        'blank_budget_rows' => $headers['blank_budget_rows'],
        'nonblank_budget_rows' => $headers['rows'] - $headers['blank_budget_rows'],
        'distinct_budget_codes' => count($headers['budget_codes']),
    ],
    'ledger_budget' => [
        'rows' => $budget['rows'],
        'distinct_budget_codes' => count($budget_codes),
        'distinct_model_budget_pairs' => count($budget['model_budget_counts']),
        'distinct_year_model_budget_keys' => count($budget['year_model_budget_counts']),
        'blank_budget_code_rows' => $budget['budget_code_counts']['(blank)'] ?? 0,
        'duplicate_budget_code_count' => count($duplicate_budget_codes),
        'duplicate_model_budget_pair_count' => count($duplicate_model_budget),
        'duplicate_year_model_budget_key_count' => count($duplicate_year_model_budget),
        'top_duplicate_budget_codes' => top_counts($duplicate_budget_codes),
        'top_duplicate_model_budget_pairs' => top_counts($duplicate_model_budget),
        'top_duplicate_year_model_budget_keys' => top_counts($duplicate_year_model_budget),
        'available_formula_matches' => $budget['available_formula_matches'],
        'available_formula_unmatched' => $budget['available_formula_unmatched'],
        'available_formula_unmatched_examples' => $budget['available_formula_unmatched_examples'],
        'year' => $budget['year'],
    ],
    'cross_checks' => [
        'po_ids_in_both_header_and_lines' => count(array_intersect($po_ids, $header_po_ids)),
        'po_ids_only_in_header_count' => count(array_diff($header_po_ids, $po_ids)),
        'po_ids_only_in_header_examples' => array_slice(array_values(array_diff($header_po_ids, $po_ids)), 0, 20),
        'po_ids_only_in_lines_count' => count(array_diff($po_ids, $header_po_ids)),
        'po_ids_only_in_lines_examples' => array_slice(array_values(array_diff($po_ids, $header_po_ids)), 0, 20),
        'po_budget_codes_matched' => count($po_budget_codes) - count($unmatched_po_budget_codes),
        'po_budget_codes_total' => count($po_budget_codes),
        'unmatched_po_budget_codes' => $unmatched_po_budget_codes,
        'budget_codes_without_po_count' => count($budget_only_codes),
        'po_department_count' => count($po_departments),
        'budget_department_count' => count($budget_departments),
        'departments_in_both' => array_values(array_intersect($po_departments, $budget_departments)),
        'departments_only_in_po' => array_values(array_diff($po_departments, $budget_departments)),
        'departments_only_in_budget' => array_values(array_diff($budget_departments, $po_departments)),
    ],
];

$json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";
if ($output_path !== null) {
    file_put_contents($output_path, $json);
} else {
    echo $json;
}
