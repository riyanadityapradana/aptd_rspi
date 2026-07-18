<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/akses.php';
require_once __DIR__ . '/kunjungan_kecamatan_mingguan_ranap_helper.php';

$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true || !aptd_can_access($levelLogin, 'kunjungan_kecamatan_mingguan_ranap')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses export PDF.');
}

if (!extension_loaded('gd')) {
    http_response_code(500);
    exit('Export PDF image-based membutuhkan ekstensi GD.');
}

if (ob_get_length()) {
    ob_end_clean();
}

$conn = $mysqli;
list($selectedMonth, $filterYear, $filterMonth, $startDate, $endDate) = aptd_kec_mingguan_period_from_request();
$monthLabels = aptd_kec_mingguan_month_labels();
$paymentLabels = aptd_kec_mingguan_payment_labels();
$weeks = aptd_kec_mingguan_weeks($filterYear, $filterMonth);
$report = aptd_kec_mingguan_fetch_ranap($conn, $startDate, $endDate, $weeks);
$rows = array_values(array_filter($report['rows'], function ($row) {
    return $row['counts']['total'] > 0;
}));

function aptd_pdf_font_path($bold = false)
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

function aptd_pdf_color($image, $hex, $alpha = 0)
{
    $hex = ltrim($hex, '#');
    return imagecolorallocatealpha(
        $image,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
        $alpha
    );
}

function aptd_pdf_text_width($text, $size, $bold = false, $scale = 2)
{
    $font = aptd_pdf_font_path($bold);
    if (!$font) {
        return strlen((string) $text) * $size * 0.62;
    }

    $box = imagettfbbox($size * $scale, 0, $font, (string) $text);
    return ($box[2] - $box[0]) / $scale;
}

function aptd_pdf_draw_text($image, $text, $x, $y, $size, $color, $bold = false, $maxWidth = 0, $scale = 2)
{
    $text = preg_replace('/\s+/', ' ', trim((string) $text));
    $font = aptd_pdf_font_path($bold);

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
        imagettftext($image, $fontSize, 0, (int) round($x * $scale), (int) round($y * $scale), $color, $font, $text);
        return;
    }

    imagestring($image, $bold ? 3 : 2, (int) round($x * $scale), (int) round(($y - $size) * $scale), $text, $color);
}

function aptd_pdf_draw_text_right($image, $text, $rightX, $y, $size, $color, $bold = false, $scale = 2)
{
    $width = aptd_pdf_text_width($text, $size, $bold, $scale);
    aptd_pdf_draw_text($image, $text, $rightX - $width, $y, $size, $color, $bold, 0, $scale);
}

function aptd_pdf_draw_rect($image, $x, $y, $w, $h, $lineColor, $fillColor = null, $scale = 2)
{
    $x1 = (int) round($x * $scale);
    $y1 = (int) round($y * $scale);
    $x2 = (int) round(($x + $w) * $scale);
    $y2 = (int) round(($y + $h) * $scale);

    if ($fillColor !== null) {
        imagefilledrectangle($image, $x1, $y1, $x2, $y2, $fillColor);
    }
    imagerectangle($image, $x1, $y1, $x2, $y2, $lineColor);
}

function aptd_pdf_from_jpeg_pages($pages, $scale = 2)
{
    $objects = [];
    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[] = '';

    $kids = [];
    $objectNo = 3;
    foreach ($pages as $page) {
        $pageObj = $objectNo++;
        $imageObj = $objectNo++;
        $contentObj = $objectNo++;
        $pageW = round($page['width'] / $scale, 2);
        $pageH = round($page['height'] / $scale, 2);
        $content = "q\n$pageW 0 0 $pageH 0 0 cm\n/Im1 Do\nQ\n";

        $kids[] = $pageObj . ' 0 R';
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageW $pageH] /Resources << /XObject << /Im1 $imageObj 0 R >> >> /Contents $contentObj 0 R >>";
        $objects[] = "<< /Type /XObject /Subtype /Image /Width " . $page['width'] . " /Height " . $page['height'] . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($page['jpeg']) . " >>\nstream\n" . $page['jpeg'] . "\nendstream";
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n$content\nendstream";
    }

    $objects[1] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($kids) . " >>";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $idx => $object) {
        $offsets[] = strlen($pdf);
        $num = $idx + 1;
        $pdf .= "$num 0 obj\n$object\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";

    return $pdf;
}

function aptd_pdf_draw_table_header($image, $weeks, $paymentLabels, $cols, $metricW, $margin, $y, $headerH, $colors, $scale)
{
    $x = $margin;
    foreach ($cols as $idx => $cw) {
        aptd_pdf_draw_rect($image, $x, $y, $cw, $headerH, $colors['line'], $idx < 4 ? $colors['dark'] : $colors['subHead'], $scale);
        $x += $cw;
    }

    $x = $margin;
    foreach (['NO', 'KAB/KOTA', 'KECAMATAN', 'TOTAL'] as $idx => $label) {
        aptd_pdf_draw_text($image, $label, $x + 5, $y + 31, 8, $colors['white'], true, $cols[$idx] - 10, $scale);
        $x += $cols[$idx];
    }

    foreach ($weeks as $week) {
        $spanW = $metricW * count($paymentLabels);
        aptd_pdf_draw_text($image, $week['label'], $x + 5, $y + 19, 8, $colors['white'], true, $spanW - 10, $scale);
        $subX = $x;
        foreach ($paymentLabels as $payment) {
            aptd_pdf_draw_text($image, strtoupper($payment), $subX + 5, $y + 42, 7, $colors['white'], true, $metricW - 10, $scale);
            $subX += $metricW;
        }
        $x += $spanW;
    }
}

function aptd_pdf_build_image_report($title, $subtitle, $weeks, $paymentLabels, $rows, $totals, $sourceText)
{
    $scale = 2;
    $margin = 24;
    $fixedCols = [44, 170, 190, 64];
    $metricW = 72;
    $cols = array_merge($fixedCols, array_fill(0, count($weeks) * count($paymentLabels), $metricW));
    $tableW = array_sum($cols);
    $headerH = 52;
    $rowH = 26;
    $imageW = $tableW + ($margin * 2);
    $imageH = 842;
    $titleY = 30;
    $subtitleY = 50;
    $tableHeaderY = 74;
    $firstRowY = $tableHeaderY + $headerH;
    $bottomMargin = 28;
    $rowsPerPage = max(1, (int) floor(($imageH - $firstRowY - $bottomMargin - $rowH) / $rowH));
    $chunks = count($rows) > 0 ? array_chunk($rows, $rowsPerPage) : [[]];
    $pages = [];
    $startNo = 1;

    foreach ($chunks as $pageIndex => $pageRows) {
        $isLastPage = $pageIndex === count($chunks) - 1;
        $image = imagecreatetruecolor($imageW * $scale, $imageH * $scale);
        imagealphablending($image, true);

        $colors = [
            'white' => aptd_pdf_color($image, '#ffffff'),
            'dark' => aptd_pdf_color($image, '#2f3944'),
            'subHead' => aptd_pdf_color($image, '#3e7ba8'),
            'line' => aptd_pdf_color($image, '#1f1f1f'),
            'text' => aptd_pdf_color($image, '#111827'),
            'muted' => aptd_pdf_color($image, '#374151'),
            'cream' => aptd_pdf_color($image, '#fff3cd'),
            'orange' => aptd_pdf_color($image, '#ff8c42'),
            'source' => aptd_pdf_color($image, '#5f6b7a'),
        ];

        imagefilledrectangle($image, 0, 0, $imageW * $scale, $imageH * $scale, $colors['white']);

        aptd_pdf_draw_text($image, $title, $margin, $titleY, 15, $colors['text'], true, $tableW - 220, $scale);
        aptd_pdf_draw_text($image, $subtitle, $margin, $subtitleY, 10, $colors['muted'], false, $tableW, $scale);
        aptd_pdf_draw_text_right($image, $sourceText, $imageW - $margin, 28, 10, $colors['source'], true, $scale);
        if (count($chunks) > 1) {
            aptd_pdf_draw_text_right($image, 'Halaman ' . ($pageIndex + 1) . ' dari ' . count($chunks), $imageW - $margin, 44, 8, $colors['muted'], false, $scale);
        }

        aptd_pdf_draw_table_header($image, $weeks, $paymentLabels, $cols, $metricW, $margin, $tableHeaderY, $headerH, $colors, $scale);

        $y = $firstRowY;
        $no = $startNo;
        foreach ($pageRows as $row) {
            $x = $margin;
            $values = [$no, $row['kabupaten'], $row['kecamatan'], number_format($row['counts']['total'], 0, ',', '.')];
            foreach ($values as $idx => $value) {
                aptd_pdf_draw_rect($image, $x, $y, $cols[$idx], $rowH, $colors['line'], $idx === 3 ? $colors['cream'] : $colors['white'], $scale);
                aptd_pdf_draw_text($image, $value, $x + 5, $y + 17, 8, $colors['text'], $idx === 3, $cols[$idx] - 10, $scale);
                $x += $cols[$idx];
            }

            $colIdx = 4;
            foreach ($weeks as $weekIdx => $week) {
                foreach ($paymentLabels as $payment) {
                    aptd_pdf_draw_rect($image, $x, $y, $cols[$colIdx], $rowH, $colors['line'], $colors['white'], $scale);
                    aptd_pdf_draw_text($image, number_format($row['counts']['weeks'][$weekIdx][$payment], 0, ',', '.'), $x + 5, $y + 17, 8, $colors['text'], false, $cols[$colIdx] - 10, $scale);
                    $x += $cols[$colIdx++];
                }
            }
            $no++;
            $y += $rowH;
        }

        if ($isLastPage) {
            $x = $margin;
            aptd_pdf_draw_rect($image, $x, $y, $tableW, $rowH, $colors['orange'], $colors['orange'], $scale);
            aptd_pdf_draw_text($image, 'Grand Total', $x + 5, $y + 17, 8, $colors['white'], true, $fixedCols[0] + $fixedCols[1] + $fixedCols[2] - 10, $scale);
            $x += $fixedCols[0] + $fixedCols[1] + $fixedCols[2];
            aptd_pdf_draw_text($image, number_format($totals['total'], 0, ',', '.'), $x + 5, $y + 17, 8, $colors['white'], true, $fixedCols[3] - 10, $scale);
            $x += $fixedCols[3];
            foreach ($weeks as $weekIdx => $week) {
                foreach ($paymentLabels as $payment) {
                    aptd_pdf_draw_text($image, number_format($totals['weeks'][$weekIdx][$payment], 0, ',', '.'), $x + 5, $y + 17, 8, $colors['white'], true, $metricW - 10, $scale);
                    $x += $metricW;
                }
            }
        }

        ob_start();
        imagejpeg($image, null, 94);
        $jpeg = ob_get_clean();
        $pages[] = [
            'jpeg' => $jpeg,
            'width' => imagesx($image),
            'height' => imagesy($image),
        ];
        imagedestroy($image);
        $startNo += count($pageRows);
    }

    return aptd_pdf_from_jpeg_pages($pages, $scale);
}

$sourceText = 'Source: APTD IT RSPI';
$title = 'Data Kunjungan Pasien Rawat Inap per Kecamatan per Minggu';
$subtitle = 'Periode: ' . $monthLabels[$filterMonth] . ' ' . $filterYear . ' (' . $startDate . ' s/d ' . $endDate . ')';
$pdf = aptd_pdf_build_image_report($title, $subtitle, $weeks, $paymentLabels, $rows, $report['totals'], $sourceText);

$filename = 'kunjungan_kecamatan_mingguan_ranap_' . $selectedMonth . '_' . date('Ymd_His') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
?>
