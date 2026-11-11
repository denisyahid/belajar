<?php
declare(strict_types=1);
// Pembuat berkas Excel (.xlsx) tanpa pustaka tambahan dan tanpa ekstensi zip.
// Hanya dipakai oleh admin.php; akses langsung ditolak.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; }

/** Nama kolom Excel dari indeks 0: 0 => A, 26 => AA. */
function xlsx_column(int $index): string {
    $name = '';
    for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
        $name = chr(65 + (($i - 1) % 26)) . $name;
    }
    return $name;
}

function xlsx_escape(string $text): string {
    $clean = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text);
    if ($clean === null) $clean = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $text);
    return htmlspecialchars((string)$clean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Sel teks; selalu disimpan sebagai teks sehingga aman dari formula. */
function xlsx_text($value, int $style = 0): array {
    return ['t' => 's', 'v' => (string)$value, 's' => $style];
}

/** Sel angka; nilai kosong menjadi sel kosong. */
function xlsx_number($value, int $style = 0): array {
    return ['t' => 'n', 'v' => $value, 's' => $style];
}

function xlsx_sheet_name(string $name, int $index): string {
    $clean = preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', $name);
    $clean = trim((string)preg_replace('/\s+/', ' ', (string)$clean));
    if ($clean === '') $clean = 'Sheet' . ($index + 1);
    return substr($clean, 0, 31);
}

function xlsx_sheet_xml(array $sheet): string {
    $rows = isset($sheet['rows']) && is_array($sheet['rows']) ? $sheet['rows'] : [];
    $widths = isset($sheet['widths']) && is_array($sheet['widths']) ? $sheet['widths'] : [];
    $freeze = (int)($sheet['freeze'] ?? 0);
    $lastColumn = count($widths);
    foreach ($rows as $row) $lastColumn = max($lastColumn, is_array($row) ? count($row) : 0);

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    if ($freeze > 0) {
        $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $freeze . '" topLeftCell="A' . ($freeze + 1) . '" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A' . ($freeze + 1) . '" sqref="A' . ($freeze + 1) . '"/></sheetView></sheetViews>';
    } else {
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
    }
    $xml .= '<sheetFormatPr defaultRowHeight="16"/>';
    if ($widths) {
        $xml .= '<cols>';
        foreach (array_values($widths) as $i => $width) {
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . round((float)$width, 2) . '" customWidth="1"/>';
        }
        $xml .= '</cols>';
    }
    $xml .= '<sheetData>';
    foreach (array_values($rows) as $rowIndex => $row) {
        $rowNumber = $rowIndex + 1;
        if (!is_array($row) || !$row) {
            $xml .= '<row r="' . $rowNumber . '"/>';
            continue;
        }
        $xml .= '<row r="' . $rowNumber . '">';
        foreach (array_values($row) as $columnIndex => $cell) {
            if ($cell === null) continue;
            if (!is_array($cell)) $cell = ['t' => is_int($cell) || is_float($cell) ? 'n' : 's', 'v' => $cell, 's' => 0];
            $reference = xlsx_column($columnIndex) . $rowNumber;
            $style = (int)($cell['s'] ?? 0);
            $attributes = ' r="' . $reference . '"' . ($style > 0 ? ' s="' . $style . '"' : '');
            if (($cell['t'] ?? 's') === 'n') {
                $raw = $cell['v'] ?? null;
                if ($raw === null || $raw === '' || !is_numeric($raw)) continue;
                $number = (float)$raw;
                $text = rtrim(rtrim(number_format($number, 10, '.', ''), '0'), '.');
                if ($text === '' || $text === '-') $text = '0';
                $xml .= '<c' . $attributes . '><v>' . $text . '</v></c>';
                continue;
            }
            $text = (string)($cell['v'] ?? '');
            if ($text === '') continue;
            $xml .= '<c' . $attributes . ' t="inlineStr"><is><t xml:space="preserve">' . xlsx_escape($text) . '</t></is></c>';
        }
        $xml .= '</row>';
    }
    $xml .= '</sheetData>';
    if (!empty($sheet['autofilter']) && $rows && $lastColumn > 0) {
        $xml .= '<autoFilter ref="A1:' . xlsx_column($lastColumn - 1) . '1"/>';
    }
    $xml .= '</worksheet>';
    return $xml;
}

function xlsx_styles_xml(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
        . '<fonts count="5">'
        . '<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="14"/><color rgb="FF123D68"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><sz val="10"/><color rgb="FF6B8295"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FF123D68"/><name val="Calibri"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF145E99"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFEAF3FB"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left/><right/><top/><bottom style="thin"><color rgb="FFDCE8F0"/></bottom><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="7">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '<dxfs count="0"/>'
        . '<tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
        . '</styleSheet>';
}

/** CRC-32 sebagai 4 bita little-endian, aman untuk PHP 32-bit maupun 64-bit. */
function xlsx_crc_bytes(string $data): string {
    $hex = hash('crc32b', $data);
    return strrev((string)hex2bin($hex));
}

/** ZIP "store" minimal agar tidak bergantung pada ekstensi zip. */
function xlsx_zip(array $files): string {
    $local = '';
    $central = '';
    $offset = 0;
    $now = getdate();
    $time = (($now['hours'] & 0x1F) << 11) | (($now['minutes'] & 0x3F) << 5) | ((intdiv($now['seconds'], 2)) & 0x1F);
    $date = ((($now['year'] - 1980) & 0x7F) << 9) | (($now['mon'] & 0x0F) << 5) | ($now['mday'] & 0x1F);
    foreach ($files as $name => $data) {
        $data = (string)$data;
        $crc = xlsx_crc_bytes($data);
        $size = strlen($data);
        $nameLength = strlen($name);
        $localHeader = pack('Vvvvvv', 0x04034b50, 20, 0x0800, 0, $time, $date) . $crc . pack('VV', $size, $size) . pack('vv', $nameLength, 0);
        $local .= $localHeader . $name . $data;
        $central .= pack('Vvvvvvv', 0x02014b50, 20, 20, 0x0800, 0, $time, $date) . $crc . pack('VV', $size, $size)
            . pack('vvvvv', $nameLength, 0, 0, 0, 0) . pack('VV', 0, $offset) . $name;
        $offset += strlen($localHeader) + $nameLength + $size;
    }
    $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($local), 0);
    return $local . $central . $end;
}

/**
 * Bangun berkas .xlsx dari daftar lembar.
 * Setiap lembar: ['name' => string, 'widths' => [lebar kolom], 'rows' => [[sel, ...]],
 *                'freeze' => jumlah baris header, 'autofilter' => bool]
 */
function xlsx_build(array $sheets): string {
    if (!$sheets) return '';
    $sheetXml = [];
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
    $workbookSheets = '';
    $workbookRels = '';
    $appSheets = '';
    $usedNames = [];
    foreach (array_values($sheets) as $index => $sheet) {
        $name = xlsx_sheet_name((string)($sheet['name'] ?? ''), $index);
        $suffix = 2;
        while (in_array($name, $usedNames, true)) {
            $name = substr($name, 0, 28) . ' ' . $suffix;
            $suffix++;
        }
        $usedNames[] = $name;
        $id = $index + 1;
        $sheetXml[$id] = xlsx_sheet_xml($sheet);
        $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $id . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $workbookSheets .= '<sheet name="' . xlsx_escape($name) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
        $workbookRels .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
        $appSheets .= '<vt:lpstr>' . xlsx_escape($name) . '</vt:lpstr>';
    }
    $contentTypes .= '</Types>';
    $styleRelId = count($sheets) + 1;
    $workbookRels .= '<Relationship Id="rId' . $styleRelId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $workbookSheets . '</sheets>'
        . '<calcPr calcId="171027" fullCalcOnLoad="1"/>'
        . '</workbook>';
    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $workbookRels . '</Relationships>';
    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
    $created = gmdate('Y-m-d\TH:i:s\Z');
    $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>Rekap Seleksi Magang RSUD Malangbong</dc:title>'
        . '<dc:creator>Panel Panitia RSUD Malangbong</dc:creator>'
        . '<cp:lastModifiedBy>Panel Panitia RSUD Malangbong</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>'
        . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>'
        . '</cp:coreProperties>';
    $app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>RSUD Malangbong</Application>'
        . '<TitlesOfParts><vt:vector size="' . count($sheets) . '" baseType="lpstr">' . $appSheets . '</vt:vector></TitlesOfParts>'
        . '</Properties>';

    $files = [
        '[Content_Types].xml' => $contentTypes,
        '_rels/.rels' => $rootRels,
        'docProps/core.xml' => $core,
        'docProps/app.xml' => $app,
        'xl/workbook.xml' => $workbook,
        'xl/_rels/workbook.xml.rels' => $workbookRelsXml,
        'xl/styles.xml' => xlsx_styles_xml(),
    ];
    foreach ($sheetXml as $id => $xml) $files['xl/worksheets/sheet' . $id . '.xml'] = $xml;
    return xlsx_zip($files);
}

/** Kirim berkas ke browser sebagai unduhan. */
function xlsx_download(string $filename, array $sheets): void {
    $data = xlsx_build($sheets);
    if ($data === '') { http_response_code(500); exit('Berkas Excel tidak dapat dibuat.'); }
    $fallback = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fallback . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo $data;
    exit;
}
