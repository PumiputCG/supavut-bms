<?php

declare(strict_types=1);

/**
 * Read-only inspector for the ERP Excel exports in ให้วิเคราะห์/วิเคราะห์ ERP.
 *
 * This intentionally uses only ZipArchive + XMLReader so the large PO workbook
 * can be inspected without loading hundreds of thousands of rows into memory.
 */
if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/inspect-erp-excel.php <xlsx> [sample-row-count] [output-json]\n");
    exit(1);
}

$xlsx_path = $argv[1];
$sample_row_count = isset($argv[2]) ? max(1, (int) $argv[2]) : 8;
$output_path = $argv[3] ?? null;

$zip = new ZipArchive;
if ($zip->open($xlsx_path) !== true) {
    fwrite(STDERR, "Cannot open workbook: {$xlsx_path}\n");
    exit(1);
}

function xml_from_zip(ZipArchive $zip, string $path): SimpleXMLElement
{
    $contents = $zip->getFromName($path);
    if ($contents === false) {
        throw new RuntimeException("Missing workbook part: {$path}");
    }

    $xml = simplexml_load_string($contents);
    if ($xml === false) {
        throw new RuntimeException("Invalid XML in workbook part: {$path}");
    }

    return $xml;
}

function normalize_part_path(string $target): string
{
    $target = str_replace('\\', '/', $target);
    if (str_starts_with($target, '/')) {
        return ltrim($target, '/');
    }

    return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
}

function column_index(string $cell_reference): int
{
    if (! preg_match('/^([A-Z]+)/i', $cell_reference, $match)) {
        return 0;
    }

    $index = 0;
    foreach (str_split(strtoupper($match[1])) as $letter) {
        $index = ($index * 26) + (ord($letter) - 64);
    }

    return $index;
}

function read_shared_strings(ZipArchive $zip): array
{
    if ($zip->locateName('xl/sharedStrings.xml') === false) {
        return [];
    }

    $stream = $zip->getStream('xl/sharedStrings.xml');
    if ($stream === false) {
        return [];
    }

    $reader = new XMLReader;
    $reader->open('php://memory');
    $contents = stream_get_contents($stream);
    fclose($stream);
    $reader->XML($contents ?: '');

    $strings = [];
    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
            continue;
        }

        $node = $reader->expand();
        $strings[] = $node?->textContent ?? '';
    }
    $reader->close();

    return $strings;
}

function read_sheet(
    ZipArchive $zip,
    string $part_path,
    array $shared_strings,
    int $sample_row_count
): array {
    $stream = $zip->getStream($part_path);
    if ($stream === false) {
        throw new RuntimeException("Cannot open worksheet part: {$part_path}");
    }

    $temporary_path = tempnam(sys_get_temp_dir(), 'bms-xlsx-');
    if ($temporary_path === false) {
        throw new RuntimeException('Cannot create temporary worksheet file.');
    }
    $temporary = fopen($temporary_path, 'wb');
    stream_copy_to_stream($stream, $temporary);
    fclose($temporary);
    fclose($stream);

    $reader = new XMLReader;
    $reader->open($temporary_path);

    $dimension = null;
    $auto_filter = null;
    $freeze_pane = null;
    $row_count = 0;
    $nonempty_row_count = 0;
    $max_column = 0;
    $formula_count = 0;
    $formula_error_count = 0;
    $samples = [];

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT) {
            continue;
        }

        if ($reader->localName === 'dimension') {
            $dimension = $reader->getAttribute('ref');

            continue;
        }
        if ($reader->localName === 'autoFilter') {
            $auto_filter = $reader->getAttribute('ref');

            continue;
        }
        if ($reader->localName === 'pane' && $reader->getAttribute('state') === 'frozen') {
            $freeze_pane = $reader->getAttribute('topLeftCell');

            continue;
        }
        if ($reader->localName !== 'row') {
            continue;
        }

        $row_count++;
        $row_xml = $reader->readOuterXml();
        if ($row_xml === '') {
            continue;
        }
        $row = simplexml_load_string($row_xml);
        if ($row === false) {
            continue;
        }

        $values = [];
        $has_value = false;
        foreach ($row->c as $cell) {
            $reference = (string) $cell['r'];
            $column = column_index($reference);
            $max_column = max($max_column, $column);
            $type = (string) $cell['t'];
            $value = '';

            if ($type === 'inlineStr') {
                $value = (string) ($cell->is->t ?? '');
                if (isset($cell->is->r)) {
                    $value = '';
                    foreach ($cell->is->r as $run) {
                        $value .= (string) ($run->t ?? '');
                    }
                }
            } elseif ($type === 's') {
                $shared_index = (int) ($cell->v ?? -1);
                $value = $shared_strings[$shared_index] ?? '';
            } elseif ($type === 'b') {
                $value = ((string) ($cell->v ?? '0')) === '1';
            } else {
                $value = (string) ($cell->v ?? '');
            }

            if (isset($cell->f)) {
                $formula_count++;
            }
            if ($type === 'e') {
                $formula_error_count++;
            }
            if ($value !== '') {
                $has_value = true;
            }
            $values[$column] = $value;
        }

        if ($has_value) {
            $nonempty_row_count++;
            if (count($samples) < $sample_row_count) {
                ksort($values);
                $samples[] = [
                    'row' => (int) ($row['r'] ?? $row_count),
                    'values' => $values,
                ];
            }
        }
    }

    $reader->close();
    unlink($temporary_path);

    return [
        'dimension' => $dimension,
        'row_count' => $row_count,
        'nonempty_row_count' => $nonempty_row_count,
        'max_column' => $max_column,
        'formula_count' => $formula_count,
        'formula_error_count' => $formula_error_count,
        'freeze_pane' => $freeze_pane,
        'auto_filter' => $auto_filter,
        'samples' => $samples,
    ];
}

$workbook = xml_from_zip($zip, 'xl/workbook.xml');
$relationships = xml_from_zip($zip, 'xl/_rels/workbook.xml.rels');
$relationship_map = [];
foreach ($relationships->Relationship as $relationship) {
    $relationship_map[(string) $relationship['Id']] = normalize_part_path((string) $relationship['Target']);
}

$workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
$shared_strings = read_shared_strings($zip);
$sheets = [];
foreach ($workbook->sheets->sheet as $sheet) {
    $attributes = $sheet->attributes('r', true);
    $relationship_id = (string) $attributes['id'];
    $part_path = $relationship_map[$relationship_id] ?? '';
    $sheets[] = [
        'name' => (string) $sheet['name'],
        'state' => (string) ($sheet['state'] ?: 'visible'),
        'part' => $part_path,
        ...read_sheet($zip, $part_path, $shared_strings, $sample_row_count),
    ];
}

$result = [
    'file' => str_replace('\\', '/', $xlsx_path),
    'size_bytes' => filesize($xlsx_path),
    'shared_string_count' => count($shared_strings),
    'sheets' => $sheets,
];

$zip->close();
$json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";
if ($output_path !== null) {
    file_put_contents($output_path, $json);
} else {
    echo $json;
}
