<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/akses.php';

$currentLevel = isset($_SESSION['level']) ? (string) $_SESSION['level'] : '';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
    || !aptd_can_access($currentLevel, 'export_rekap_pasien_prmrj_pdf')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses export PDF Rekap Pasien PRMRJ.');
}

if (!extension_loaded('gd')) {
    http_response_code(500);
    exit('Export PDF image-based membutuhkan ekstensi GD.');
}

require_once $projectRoot . '/config/koneksi.php';
require_once __DIR__ . '/rekap_pasien_prmrj_helper.php';

$period = aptd_prmrj_period_from_request();
if (!$period['valid']) {
    http_response_code(422);
    exit($period['message']);
}

try {
    $parentMedicalRecords = aptd_prmrj_fetch_parent_medical_records(
        $mysqli,
        $period['tanggal_awal'],
        $period['tanggal_akhir']
    );
    $details = aptd_prmrj_fetch_latest_details_for_medical_records($mysqli, $parentMedicalRecords, 5);
    $groups = aptd_prmrj_group_details($details);
} catch (Throwable $exception) {
    error_log('Export PDF Rekap Pasien PRMRJ: ' . $exception->getMessage());
    http_response_code(500);
    exit('PDF Rekap Pasien PRMRJ belum dapat dibuat.');
}

function aptd_prmrj_pdf_font($bold = false)
{
    $candidates = $bold
        ? ['C:/Windows/Fonts/arialbd.ttf', 'C:/Windows/Fonts/calibrib.ttf']
        : ['C:/Windows/Fonts/arial.ttf', 'C:/Windows/Fonts/calibri.ttf'];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function aptd_prmrj_pdf_color($image, $hex)
{
    $hex = ltrim($hex, '#');
    return imagecolorallocate(
        $image,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    );
}

function aptd_prmrj_pdf_text_width($text, $size, $bold, $scale)
{
    $font = aptd_prmrj_pdf_font($bold);
    if (!$font) {
        return strlen((string) $text) * $size * 0.58;
    }
    $box = imagettfbbox($size * $scale, 0, $font, (string) $text);
    return abs($box[2] - $box[0]) / $scale;
}

function aptd_prmrj_pdf_draw_text($image, $text, $x, $baselineY, $size, $color, $bold, $scale)
{
    $font = aptd_prmrj_pdf_font($bold);
    if ($font) {
        imagettftext(
            $image,
            $size * $scale,
            0,
            (int) round($x * $scale),
            (int) round($baselineY * $scale),
            $color,
            $font,
            (string) $text
        );
        return;
    }
    imagestring(
        $image,
        $bold ? 3 : 2,
        (int) round($x * $scale),
        (int) round(($baselineY - $size) * $scale),
        (string) $text,
        $color
    );
}

function aptd_prmrj_pdf_draw_text_right($image, $text, $rightX, $baselineY, $size, $color, $bold, $scale)
{
    $width = aptd_prmrj_pdf_text_width($text, $size, $bold, $scale);
    aptd_prmrj_pdf_draw_text($image, $text, $rightX - $width, $baselineY, $size, $color, $bold, $scale);
}

function aptd_prmrj_pdf_wrap($text, $maxWidth, $size = 7.2, $bold = false, $scale = 2)
{
    $text = str_replace(["\r\n", "\r"], "\n", trim((string) $text));
    if ($text === '') {
        return ['-'];
    }

    $lines = [];
    foreach (explode("\n", $text) as $paragraph) {
        $paragraph = trim(preg_replace('/\s+/u', ' ', $paragraph));
        if ($paragraph === '') {
            $lines[] = '';
            continue;
        }
        $words = preg_split('/\s+/u', $paragraph);
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && aptd_prmrj_pdf_text_width($candidate, $size, $bold, $scale) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    return empty($lines) ? ['-'] : $lines;
}

function aptd_prmrj_pdf_rect($image, $x, $y, $width, $height, $lineColor, $fillColor, $scale)
{
    $x1 = (int) round($x * $scale);
    $y1 = (int) round($y * $scale);
    $x2 = (int) round(($x + $width) * $scale);
    $y2 = (int) round(($y + $height) * $scale);
    imagefilledrectangle($image, $x1, $y1, $x2, $y2, $fillColor);
    imagerectangle($image, $x1, $y1, $x2, $y2, $lineColor);
}

function aptd_prmrj_pdf_draw_lines($image, array $lines, $x, $y, $size, $lineHeight, $color, $bold, $scale)
{
    foreach ($lines as $index => $line) {
        aptd_prmrj_pdf_draw_text(
            $image,
            $line,
            $x,
            $y + $size + ($index * $lineHeight),
            $size,
            $color,
            $bold,
            $scale
        );
    }
}

function aptd_prmrj_pdf_visit_cells(array $visit, array $columns, $scale)
{
    $diagnoses = empty($visit['diagnosa']) ? '-' : implode("\n", $visit['diagnosa']);
    $medicines = empty($visit['obat']) ? '-' : implode("\n", $visit['obat']);
    $dateTime = aptd_prmrj_empty_text($visit['tgl_kunjungan']) . ' ' . aptd_prmrj_empty_text($visit['jam_kunjungan'])
        . "\nNo. Rawat: " . aptd_prmrj_empty_text($visit['no_rawat']);
    $diagnosis = 'Subjektif: ' . aptd_prmrj_empty_text($visit['keluhan'])
        . "\nAsesmen: " . aptd_prmrj_empty_text($visit['penilaian'])
        . "\nICD-10: " . $diagnoses;
    $therapy = 'RTL: ' . aptd_prmrj_empty_text($visit['rtl'])
        . "\nInstruksi: " . aptd_prmrj_empty_text($visit['instruksi'])
        . "\nObat: " . $medicines;

    return [
        aptd_prmrj_pdf_wrap($dateTime, $columns[0] - 10, 7.2, false, $scale),
        aptd_prmrj_pdf_wrap($diagnosis, $columns[1] - 10, 7.2, false, $scale),
        aptd_prmrj_pdf_wrap($therapy, $columns[2] - 10, 7.2, false, $scale),
        aptd_prmrj_pdf_wrap(aptd_prmrj_empty_text($visit['dpjp']), $columns[3] - 10, 7.2, true, $scale),
    ];
}

function aptd_prmrj_pdf_add_group_headers(array &$page, &$cursor, array $group, $continued)
{
    $page[] = [
        'type' => 'group',
        'y' => $cursor,
        'height' => 24,
        'text' => $group['no_rkm_medis'] . ' - ' . $group['nm_pasien'] . ($continued ? ' (lanjutan)' : ''),
    ];
    $cursor += 24;
    $page[] = ['type' => 'table_header', 'y' => $cursor, 'height' => 28];
    $cursor += 28;
}

function aptd_prmrj_pdf_plan(array $groups, array $columns, $scale)
{
    $pages = [[]];
    $pageIndex = 0;
    $cursor = 58;
    $bottom = 558;
    $lineHeight = 10.5;
    $stripe = 0;

    if (empty($groups)) {
        $pages[0][] = ['type' => 'empty', 'y' => 95, 'height' => 50];
        return $pages;
    }

    foreach ($groups as $group) {
        if ($cursor + 24 + 28 + 30 > $bottom && !empty($pages[$pageIndex])) {
            $pages[] = [];
            $pageIndex++;
            $cursor = 58;
        }
        aptd_prmrj_pdf_add_group_headers($pages[$pageIndex], $cursor, $group, false);

        foreach ($group['visits'] as $visit) {
            $cells = aptd_prmrj_pdf_visit_cells($visit, $columns, $scale);
            $maxLines = max(array_map('count', $cells));
            $offset = 0;

            while ($offset < $maxLines) {
                if ($cursor + 30 > $bottom) {
                    $pages[] = [];
                    $pageIndex++;
                    $cursor = 58;
                    aptd_prmrj_pdf_add_group_headers($pages[$pageIndex], $cursor, $group, true);
                }

                $capacity = max(1, (int) floor(($bottom - $cursor - 8) / $lineHeight));
                $take = min($maxLines - $offset, $capacity);
                $segmentCells = [];
                foreach ($cells as $cellIndex => $cellLines) {
                    $segment = array_slice($cellLines, $offset, $take);
                    if ($offset > 0 && $cellIndex === 0) {
                        $segment = ['Lanjutan'];
                    } elseif ($offset > 0 && $cellIndex === 3) {
                        $segment = $cells[3];
                    }
                    $segmentCells[] = $segment;
                }
                $segmentLineCount = max(1, max(array_map('count', $segmentCells)));
                $rowHeight = max(30, ($segmentLineCount * $lineHeight) + 8);
                $pages[$pageIndex][] = [
                    'type' => 'visit',
                    'y' => $cursor,
                    'height' => $rowHeight,
                    'cells' => $segmentCells,
                    'stripe' => $stripe % 2,
                ];
                $cursor += $rowHeight;
                $offset += $take;

                if ($offset < $maxLines) {
                    $pages[] = [];
                    $pageIndex++;
                    $cursor = 58;
                    aptd_prmrj_pdf_add_group_headers($pages[$pageIndex], $cursor, $group, true);
                }
            }
            $stripe++;
        }
        $cursor += 7;
    }

    return $pages;
}

function aptd_prmrj_pdf_append_object(&$pdf, array &$offsets, $number, $object)
{
    $offsets[$number] = strlen($pdf);
    $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
}

function aptd_prmrj_pdf_finish($pdf, array $offsets, $objectCount)
{
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . ($objectCount + 1) . "\n0000000000 65535 f \n";
    for ($index = 1; $index <= $objectCount; $index++) {
        $pdf .= str_pad((string) $offsets[$index], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . ($objectCount + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    return $pdf;
}

function aptd_prmrj_pdf_build(array $groups, array $period, $generatedAt)
{
    $scale = 2;
    $pageWidth = 842;
    $pageHeight = 595;
    $margin = 24;
    $columns = [115, 260, 270, 149];
    $layoutPages = aptd_prmrj_pdf_plan($groups, $columns, $scale);
    $totalPages = count($layoutPages);
    $kids = [];
    for ($pageIndex = 0; $pageIndex < $totalPages; $pageIndex++) {
        $kids[] = (3 + ($pageIndex * 3)) . ' 0 R';
    }
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    aptd_prmrj_pdf_append_object($pdf, $offsets, 1, '<< /Type /Catalog /Pages 2 0 R >>');
    aptd_prmrj_pdf_append_object(
        $pdf,
        $offsets,
        2,
        '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $totalPages . ' >>'
    );

    foreach ($layoutPages as $pageIndex => $instructions) {
        $image = imagecreatetruecolor($pageWidth * $scale, $pageHeight * $scale);
        $colors = [
            'white' => aptd_prmrj_pdf_color($image, '#ffffff'),
            'dark' => aptd_prmrj_pdf_color($image, '#263e57'),
            'blue' => aptd_prmrj_pdf_color($image, '#315f89'),
            'text' => aptd_prmrj_pdf_color($image, '#1e3449'),
            'muted' => aptd_prmrj_pdf_color($image, '#61778d'),
            'line' => aptd_prmrj_pdf_color($image, '#b8c6d3'),
            'stripe' => aptd_prmrj_pdf_color($image, '#f5f8fb'),
            'head' => aptd_prmrj_pdf_color($image, '#dceaf6'),
        ];
        imagefilledrectangle($image, 0, 0, $pageWidth * $scale, $pageHeight * $scale, $colors['white']);

        aptd_prmrj_pdf_draw_text($image, 'Rekap Pasien PRMRJ', $margin, 21, 12, $colors['text'], true, $scale);
        aptd_prmrj_pdf_draw_text(
            $image,
            'Periode: ' . $period['tanggal_awal'] . ' s.d ' . $period['tanggal_akhir'] . ' | Rincian: maksimal 5 riwayat terakhir',
            $margin,
            39,
            8.5,
            $colors['muted'],
            false,
            $scale
        );
        aptd_prmrj_pdf_draw_text_right($image, 'Source by: APTD IT RSPI', $pageWidth - $margin, 22, 9, $colors['text'], true, $scale);
        imageline($image, $margin * $scale, 50 * $scale, ($pageWidth - $margin) * $scale, 50 * $scale, $colors['line']);

        foreach ($instructions as $instruction) {
            if ($instruction['type'] === 'empty') {
                aptd_prmrj_pdf_rect($image, $margin, $instruction['y'], array_sum($columns), $instruction['height'], $colors['line'], $colors['stripe'], $scale);
                aptd_prmrj_pdf_draw_text($image, 'Tidak ada pasien PRMRJ pada periode yang dipilih.', $margin + 12, $instruction['y'] + 29, 9, $colors['muted'], false, $scale);
                continue;
            }

            if ($instruction['type'] === 'group') {
                aptd_prmrj_pdf_rect($image, $margin, $instruction['y'], array_sum($columns), $instruction['height'], $colors['dark'], $colors['dark'], $scale);
                aptd_prmrj_pdf_draw_text($image, $instruction['text'], $margin + 8, $instruction['y'] + 16, 8.5, $colors['white'], true, $scale);
                continue;
            }

            if ($instruction['type'] === 'table_header') {
                $headers = ['Waktu Kunjungan', 'Diagnosa & Penilaian', 'Terapi & Tindakan', 'DPJP'];
                $x = $margin;
                foreach ($columns as $columnIndex => $columnWidth) {
                    aptd_prmrj_pdf_rect($image, $x, $instruction['y'], $columnWidth, $instruction['height'], $colors['line'], $colors['head'], $scale);
                    $headerLines = aptd_prmrj_pdf_wrap($headers[$columnIndex], $columnWidth - 8, 7.6, true, $scale);
                    aptd_prmrj_pdf_draw_lines($image, $headerLines, $x + 5, $instruction['y'] + 5, 7.6, 10, $colors['text'], true, $scale);
                    $x += $columnWidth;
                }
                continue;
            }

            if ($instruction['type'] === 'visit') {
                $x = $margin;
                $fill = $instruction['stripe'] ? $colors['stripe'] : $colors['white'];
                foreach ($columns as $columnIndex => $columnWidth) {
                    aptd_prmrj_pdf_rect($image, $x, $instruction['y'], $columnWidth, $instruction['height'], $colors['line'], $fill, $scale);
                    aptd_prmrj_pdf_draw_lines(
                        $image,
                        $instruction['cells'][$columnIndex],
                        $x + 5,
                        $instruction['y'] + 4,
                        7.2,
                        10.5,
                        $colors['text'],
                        $columnIndex === 3,
                        $scale
                    );
                    $x += $columnWidth;
                }
            }
        }

        imageline($image, $margin * $scale, 566 * $scale, ($pageWidth - $margin) * $scale, 566 * $scale, $colors['line']);
        aptd_prmrj_pdf_draw_text($image, 'Generated at: ' . $generatedAt, $margin, 583, 8, $colors['muted'], false, $scale);
        aptd_prmrj_pdf_draw_text_right(
            $image,
            'Page ' . ($pageIndex + 1) . ' of ' . $totalPages,
            $pageWidth - $margin,
            583,
            8,
            $colors['muted'],
            false,
            $scale
        );

        ob_start();
        imagejpeg($image, null, 88);
        $jpeg = ob_get_clean();
        $pageObject = 3 + ($pageIndex * 3);
        $imageObject = $pageObject + 1;
        $contentObject = $pageObject + 2;
        $content = "q\n{$pageWidth} 0 0 {$pageHeight} 0 0 cm\n/Im1 Do\nQ\n";
        aptd_prmrj_pdf_append_object(
            $pdf,
            $offsets,
            $pageObject,
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /XObject << /Im1 {$imageObject} 0 R >> >> /Contents {$contentObject} 0 R >>"
        );
        aptd_prmrj_pdf_append_object(
            $pdf,
            $offsets,
            $imageObject,
            "<< /Type /XObject /Subtype /Image /Width " . imagesx($image) . ' /Height ' . imagesy($image) . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpeg) . ">>\nstream\n" . $jpeg . "\nendstream"
        );
        aptd_prmrj_pdf_append_object(
            $pdf,
            $offsets,
            $contentObject,
            "<< /Length " . strlen($content) . ">>\nstream\n{$content}\nendstream"
        );
        imagedestroy($image);
        unset($jpeg);
    }

    return aptd_prmrj_pdf_finish($pdf, $offsets, 2 + ($totalPages * 3));
}

$generatedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
$pdf = aptd_prmrj_pdf_build($groups, $period, $generatedAt);
$filename = 'rekap_pasien_prmrj_' . $period['tanggal_awal'] . '_' . $period['tanggal_akhir'] . '.pdf';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo $pdf;
exit;
