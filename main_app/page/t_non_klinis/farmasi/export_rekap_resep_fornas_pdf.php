<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
require_once $projectRoot . '/config/akses.php';

$currentLevel = isset($_SESSION['level']) ? (string) $_SESSION['level'] : '';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
    || !aptd_can_access($currentLevel, 'export_rekap_resep_fornas_pdf')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses export PDF.');
}

if (!extension_loaded('gd')) {
    http_response_code(500);
    exit('Export PDF image-based membutuhkan ekstensi GD.');
}

require_once $projectRoot . '/config/koneksi.php';
require_once __DIR__ . '/rekap_resep_fornas_non_fornas_helper.php';

$period = aptd_fornas_period_from_request();
if (!$period['valid']) {
    http_response_code(422);
    exit($period['message']);
}

try {
    $report = aptd_fornas_fetch_report($mysqli, $period['tanggal_awal'], $period['tanggal_akhir']);
} catch (Throwable $exception) {
    error_log('AR-163 Export PDF Rekap Resep: ' . $exception->getMessage());
    http_response_code(500);
    exit('PDF belum dapat dibuat.');
}

function aptd_fornas_pdf_font_path($bold = false)
{
    $fonts = $bold
        ? ['C:/Windows/Fonts/arialbd.ttf', 'C:/Windows/Fonts/calibrib.ttf']
        : ['C:/Windows/Fonts/arial.ttf', 'C:/Windows/Fonts/calibri.ttf'];

    foreach ($fonts as $font) {
        if (is_file($font)) {
            return $font;
        }
    }

    return null;
}

function aptd_fornas_pdf_color($image, $hex)
{
    $hex = ltrim($hex, '#');
    return imagecolorallocate(
        $image,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    );
}

function aptd_fornas_pdf_text_width($text, $size, $bold = false, $scale = 2)
{
    $font = aptd_fornas_pdf_font_path($bold);
    if (!$font) {
        return strlen((string) $text) * $size * 0.62;
    }

    $box = imagettfbbox($size * $scale, 0, $font, (string) $text);
    return ($box[2] - $box[0]) / $scale;
}

function aptd_fornas_pdf_draw_text($image, $text, $x, $baselineY, $size, $color, $bold = false, $maxWidth = 0, $scale = 2)
{
    $text = preg_replace('/\s+/', ' ', trim((string) $text));
    $font = aptd_fornas_pdf_font_path($bold);

    if ($font) {
        $fontSize = $size * $scale;
        if ($maxWidth > 0) {
            $max = $maxWidth * $scale;
            while ($fontSize > 7) {
                $box = imagettfbbox($fontSize, 0, $font, $text);
                if (($box[2] - $box[0]) <= $max) {
                    break;
                }
                $fontSize -= 0.5;
            }
        }

        imagettftext(
            $image,
            $fontSize,
            0,
            (int) round($x * $scale),
            (int) round($baselineY * $scale),
            $color,
            $font,
            $text
        );
        return;
    }

    imagestring(
        $image,
        $bold ? 3 : 2,
        (int) round($x * $scale),
        (int) round(($baselineY - $size) * $scale),
        $text,
        $color
    );
}

function aptd_fornas_pdf_draw_text_right($image, $text, $rightX, $baselineY, $size, $color, $bold = false, $scale = 2)
{
    $width = aptd_fornas_pdf_text_width($text, $size, $bold, $scale);
    aptd_fornas_pdf_draw_text($image, $text, $rightX - $width, $baselineY, $size, $color, $bold, 0, $scale);
}

function aptd_fornas_pdf_draw_rect($image, $x, $y, $width, $height, $lineColor, $fillColor, $scale = 2)
{
    $x1 = (int) round($x * $scale);
    $y1 = (int) round($y * $scale);
    $x2 = (int) round(($x + $width) * $scale);
    $y2 = (int) round(($y + $height) * $scale);

    imagefilledrectangle($image, $x1, $y1, $x2, $y2, $fillColor);
    imagerectangle($image, $x1, $y1, $x2, $y2, $lineColor);
}

function aptd_fornas_pdf_draw_cell_text($image, $text, $x, $y, $width, $height, $size, $color, $bold, $align, $scale)
{
    $baselineY = $y + (($height + $size) / 2) - 1;
    $textWidth = aptd_fornas_pdf_text_width($text, $size, $bold, $scale);

    if ($align === 'center') {
        $textX = $x + max(4, ($width - $textWidth) / 2);
    } elseif ($align === 'right') {
        $textX = $x + max(4, $width - $textWidth - 5);
    } else {
        $textX = $x + 5;
    }

    aptd_fornas_pdf_draw_text($image, $text, $textX, $baselineY, $size, $color, $bold, $width - 10, $scale);
}

function aptd_fornas_pdf_from_jpeg_pages(array $pages, $scale = 2)
{
    $objects = ["<< /Type /Catalog /Pages 2 0 R >>", ''];
    $kids = [];
    $objectNo = 3;

    foreach ($pages as $page) {
        $pageObject = $objectNo++;
        $imageObject = $objectNo++;
        $contentObject = $objectNo++;
        $pageWidth = round($page['width'] / $scale, 2);
        $pageHeight = round($page['height'] / $scale, 2);
        $content = "q\n{$pageWidth} 0 0 {$pageHeight} 0 0 cm\n/Im1 Do\nQ\n";

        $kids[] = $pageObject . ' 0 R';
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /XObject << /Im1 {$imageObject} 0 R >> >> /Contents {$contentObject} 0 R >>";
        $objects[] = "<< /Type /XObject /Subtype /Image /Width {$page['width']} /Height {$page['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($page['jpeg']) . ">>\nstream\n" . $page['jpeg'] . "\nendstream";
        $objects[] = "<< /Length " . strlen($content) . ">>\nstream\n{$content}\nendstream";
    }

    $objects[1] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $number = $index + 1;
        $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($index = 1; $index <= count($objects); $index++) {
        $pdf .= str_pad((string) $offsets[$index], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    return $pdf;
}

function aptd_fornas_pdf_report_rows(array $report, array $dimensions)
{
    $rows = [];
    $stripe = 0;

    foreach ($dimensions['rawat'] as $rawat) {
        foreach ($dimensions['racikan'] as $racikan) {
            foreach ($dimensions['bayar'] as $bayar) {
                $counts = $report['matrix'][$rawat][$racikan][$bayar];
                $rows[] = [
                    'type' => 'detail',
                    'rawat' => $rawat,
                    'racikan' => $racikan,
                    'values' => array_merge([$bayar], array_values($counts), [array_sum($counts)]),
                    'stripe' => $stripe++ % 2,
                ];
            }
        }

        $rawatTotals = $report['rawat_totals'][$rawat];
        $rows[] = [
            'type' => 'subtotal',
            'label' => 'Subtotal ' . $rawat,
            'values' => array_merge(array_values($rawatTotals), [array_sum($rawatTotals)]),
        ];
    }

    $denominator = (int) $report['total_terklasifikasi'];
    $percentages = [];
    foreach ($dimensions['formularium'] as $formularium) {
        $percentages[] = $denominator > 0
            ? number_format(($report['formularium_totals'][$formularium] / $denominator) * 100, 2, '.', ',') . '%'
            : '0.00%';
    }

    $rows[] = [
        'type' => 'grand',
        'label' => 'Grand Total',
        'values' => array_merge(array_values($report['formularium_totals']), [$denominator]),
    ];
    $rows[] = [
        'type' => 'percentage',
        'label' => 'Persentase',
        'values' => array_merge($percentages, [$denominator > 0 ? '100.00%' : '0.00%']),
    ];

    return $rows;
}

function aptd_fornas_pdf_draw_table_header($image, array $columns, $margin, $tableY, $colors, $scale)
{
    $topHeight = 24;
    $subHeight = 24;
    $fullHeight = $topHeight + $subHeight;
    $x = $margin;
    $topLabels = ['Jenis Rawat', 'Kategori Resep', 'Jenis Bayar'];

    foreach ($topLabels as $index => $label) {
        aptd_fornas_pdf_draw_rect($image, $x, $tableY, $columns[$index], $fullHeight, $colors['line'], $colors['dark'], $scale);
        aptd_fornas_pdf_draw_cell_text($image, $label, $x, $tableY, $columns[$index], $fullHeight, 8, $colors['white'], true, 'center', $scale);
        $x += $columns[$index];
    }

    $formulariumWidth = $columns[3] + $columns[4] + $columns[5];
    aptd_fornas_pdf_draw_rect($image, $x, $tableY, $formulariumWidth, $topHeight, $colors['line'], $colors['dark'], $scale);
    aptd_fornas_pdf_draw_cell_text($image, 'Kategori Formularium', $x, $tableY, $formulariumWidth, $topHeight, 8, $colors['white'], true, 'center', $scale);

    foreach (['Fornas', 'Non-Fornas', 'Non For RSPI'] as $offset => $label) {
        $width = $columns[$offset + 3];
        aptd_fornas_pdf_draw_rect($image, $x, $tableY + $topHeight, $width, $subHeight, $colors['line'], $colors['subhead'], $scale);
        aptd_fornas_pdf_draw_cell_text($image, $label, $x, $tableY + $topHeight, $width, $subHeight, 8, $colors['white'], true, 'center', $scale);
        $x += $width;
    }

    aptd_fornas_pdf_draw_rect($image, $x, $tableY, $columns[6], $fullHeight, $colors['line'], $colors['dark'], $scale);
    aptd_fornas_pdf_draw_cell_text($image, 'Total Terklasifikasi', $x, $tableY, $columns[6], $fullHeight, 8, $colors['white'], true, 'center', $scale);

    return $fullHeight;
}

function aptd_fornas_pdf_draw_rows($image, array $rows, array $columns, $margin, $startY, $rowHeight, $colors, $scale)
{
    $xPositions = [$margin];
    foreach ($columns as $columnWidth) {
        $xPositions[] = end($xPositions) + $columnWidth;
    }

    foreach ($rows as $rowIndex => $row) {
        $y = $startY + ($rowIndex * $rowHeight);

        if ($row['type'] === 'detail') {
            $fill = $row['stripe'] ? $colors['stripe'] : $colors['white'];

            if ($rowIndex === 0 || $rows[$rowIndex - 1]['type'] !== 'detail' || $rows[$rowIndex - 1]['rawat'] !== $row['rawat']) {
                $run = 1;
                while (isset($rows[$rowIndex + $run])
                    && $rows[$rowIndex + $run]['type'] === 'detail'
                    && $rows[$rowIndex + $run]['rawat'] === $row['rawat']) {
                    $run++;
                }
                aptd_fornas_pdf_draw_rect($image, $xPositions[0], $y, $columns[0], $rowHeight * $run, $colors['line'], $fill, $scale);
                aptd_fornas_pdf_draw_cell_text($image, $row['rawat'], $xPositions[0], $y, $columns[0], $rowHeight * $run, 8, $colors['text'], true, 'left', $scale);
            }

            if ($rowIndex === 0 || $rows[$rowIndex - 1]['type'] !== 'detail'
                || $rows[$rowIndex - 1]['rawat'] !== $row['rawat']
                || $rows[$rowIndex - 1]['racikan'] !== $row['racikan']) {
                $run = 1;
                while (isset($rows[$rowIndex + $run])
                    && $rows[$rowIndex + $run]['type'] === 'detail'
                    && $rows[$rowIndex + $run]['rawat'] === $row['rawat']
                    && $rows[$rowIndex + $run]['racikan'] === $row['racikan']) {
                    $run++;
                }
                aptd_fornas_pdf_draw_rect($image, $xPositions[1], $y, $columns[1], $rowHeight * $run, $colors['line'], $fill, $scale);
                aptd_fornas_pdf_draw_cell_text($image, $row['racikan'], $xPositions[1], $y, $columns[1], $rowHeight * $run, 8, $colors['text'], false, 'left', $scale);
            }

            foreach ($row['values'] as $valueIndex => $value) {
                $columnIndex = $valueIndex + 2;
                aptd_fornas_pdf_draw_rect($image, $xPositions[$columnIndex], $y, $columns[$columnIndex], $rowHeight, $colors['line'], $fill, $scale);
                aptd_fornas_pdf_draw_cell_text(
                    $image,
                    is_numeric($value) ? number_format($value, 0, ',', '.') : $value,
                    $xPositions[$columnIndex],
                    $y,
                    $columns[$columnIndex],
                    $rowHeight,
                    8,
                    $colors['text'],
                    $columnIndex === 6,
                    $columnIndex === 2 ? 'left' : 'center',
                    $scale
                );
            }
            continue;
        }

        $fill = $colors['subtotal'];
        $textColor = $colors['text'];
        if ($row['type'] === 'grand') {
            $fill = $colors['orange'];
            $textColor = $colors['white'];
        } elseif ($row['type'] === 'percentage') {
            $fill = $colors['percentage'];
        }

        $labelWidth = $columns[0] + $columns[1] + $columns[2];
        aptd_fornas_pdf_draw_rect($image, $xPositions[0], $y, $labelWidth, $rowHeight, $colors['line'], $fill, $scale);
        aptd_fornas_pdf_draw_cell_text($image, $row['label'], $xPositions[0], $y, $labelWidth, $rowHeight, 8, $textColor, true, 'left', $scale);

        foreach ($row['values'] as $valueIndex => $value) {
            $columnIndex = $valueIndex + 3;
            aptd_fornas_pdf_draw_rect($image, $xPositions[$columnIndex], $y, $columns[$columnIndex], $rowHeight, $colors['line'], $fill, $scale);
            aptd_fornas_pdf_draw_cell_text(
                $image,
                is_numeric($value) ? number_format($value, 0, ',', '.') : $value,
                $xPositions[$columnIndex],
                $y,
                $columns[$columnIndex],
                $rowHeight,
                8,
                $textColor,
                true,
                'center',
                $scale
            );
        }
    }
}

function aptd_fornas_pdf_build(array $report, array $dimensions, array $period, $downloadTime)
{
    $scale = 2;
    $pageWidth = 842;
    $pageHeight = 595;
    $margin = 24;
    $columns = [115, 115, 105, 100, 100, 100, 159];
    $tableY = 66;
    $tableHeaderHeight = 48;
    $rowHeight = 24;
    $firstRowY = $tableY + $tableHeaderHeight;
    $footerTop = 566;
    $rowsPerPage = max(1, (int) floor(($footerTop - $firstRowY - 8) / $rowHeight));
    $rows = aptd_fornas_pdf_report_rows($report, $dimensions);
    $chunks = array_chunk($rows, $rowsPerPage);
    $totalPages = max(1, count($chunks));
    $pages = [];

    foreach ($chunks as $pageIndex => $pageRows) {
        $image = imagecreatetruecolor($pageWidth * $scale, $pageHeight * $scale);
        $colors = [
            'white' => aptd_fornas_pdf_color($image, '#ffffff'),
            'dark' => aptd_fornas_pdf_color($image, '#263746'),
            'subhead' => aptd_fornas_pdf_color($image, '#315b78'),
            'line' => aptd_fornas_pdf_color($image, '#aebbc8'),
            'text' => aptd_fornas_pdf_color($image, '#172d44'),
            'muted' => aptd_fornas_pdf_color($image, '#536a82'),
            'stripe' => aptd_fornas_pdf_color($image, '#f7f9fb'),
            'subtotal' => aptd_fornas_pdf_color($image, '#eaf1f7'),
            'orange' => aptd_fornas_pdf_color($image, '#ff8a43'),
            'percentage' => aptd_fornas_pdf_color($image, '#fff3e9'),
        ];

        imagefilledrectangle($image, 0, 0, $pageWidth * $scale, $pageHeight * $scale, $colors['white']);

        aptd_fornas_pdf_draw_text(
            $image,
            'Rekap Resep Fornas, Non-Fornas dan Non For RSPI',
            $margin,
            24,
            12,
            $colors['text'],
            true,
            560,
            $scale
        );
        aptd_fornas_pdf_draw_text(
            $image,
            'Periode: ' . $period['tanggal_awal'] . ' s.d. ' . $period['tanggal_akhir'],
            $margin,
            43,
            9,
            $colors['muted'],
            false,
            560,
            $scale
        );
        aptd_fornas_pdf_draw_text_right($image, 'Source: APTD IT RSPI', $pageWidth - $margin, 23, 9, $colors['text'], true, $scale);
        aptd_fornas_pdf_draw_text_right(
            $image,
            'Halaman ' . ($pageIndex + 1) . ' / ' . $totalPages,
            $pageWidth - $margin,
            41,
            8,
            $colors['muted'],
            false,
            $scale
        );

        aptd_fornas_pdf_draw_table_header($image, $columns, $margin, $tableY, $colors, $scale);
        aptd_fornas_pdf_draw_rows($image, $pageRows, $columns, $margin, $firstRowY, $rowHeight, $colors, $scale);

        imageline(
            $image,
            $margin * $scale,
            $footerTop * $scale,
            ($pageWidth - $margin) * $scale,
            $footerTop * $scale,
            $colors['line']
        );
        aptd_fornas_pdf_draw_text(
            $image,
            'Waktu Unduh: ' . $downloadTime . ' WITA',
            $margin,
            583,
            8,
            $colors['muted'],
            false,
            420,
            $scale
        );

        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = ob_get_clean();
        $pages[] = ['jpeg' => $jpeg, 'width' => imagesx($image), 'height' => imagesy($image)];
        imagedestroy($image);
    }

    return aptd_fornas_pdf_from_jpeg_pages($pages, $scale);
}

$dimensions = aptd_fornas_dimensions();
$wita = new DateTimeZone('Asia/Makassar');
$downloadedAt = new DateTimeImmutable('now', $wita);
$pdf = aptd_fornas_pdf_build($report, $dimensions, $period, $downloadedAt->format('Y-m-d H:i:s'));
$filename = 'rekap_resep_formularium_' . $period['tanggal_awal'] . '_' . $period['tanggal_akhir'] . '_' . $downloadedAt->format('Ymd_His') . '.pdf';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
echo $pdf;
exit;
