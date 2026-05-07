<?php
function aptd_excel_is_supported()
{
    return PHP_INT_SIZE >= 8 && (version_compare(PHP_VERSION, '8.1.0', '>=') || version_compare(PHP_VERSION, '7.4.26', '>='));
}

function aptd_excel_bootstrap()
{
    static $loaded = null;

    if ($loaded !== null) {
        return $loaded;
    }

    if (!aptd_excel_is_supported()) {
        $loaded = false;
        return false;
    }

    $autoload = dirname(dirname(__DIR__)) . '/assets/vendor/autoload.php';
    if (!file_exists($autoload)) {
        $loaded = false;
        return false;
    }

    require_once $autoload;
    $loaded = class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet');
    return $loaded;
}

function aptd_excel_cell($columnIndex, $rowNumber)
{
    if (!aptd_excel_bootstrap()) {
        return '';
    }

    return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex((int) $columnIndex) . (int) $rowNumber;
}

function aptd_excel_create($title, $subtitle, $sheetTitle = 'Data')
{
    if (!aptd_excel_bootstrap()) {
        return array(null, null);
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr((string) $sheetTitle, 0, 31));
    $sheet->setCellValue('A1', $title);
    $sheet->setCellValue('A2', $subtitle);
    $sheet->mergeCells('A1:F1');
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
    $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
    $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
    return array($spreadsheet, $sheet);
}

function aptd_excel_render_table($sheet, array $headers, array $rows, $startRow = 4)
{
    if (!aptd_excel_bootstrap() || !$sheet) {
        return;
    }

    $rowNumber = (int) $startRow;
    $lastColumn = count($headers);

    foreach ($headers as $index => $header) {
        $cell = aptd_excel_cell($index + 1, $rowNumber);
        $sheet->setCellValue($cell, $header);
    }

    $headerRange = 'A' . $rowNumber . ':' . aptd_excel_cell($lastColumn, $rowNumber);
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $sheet->getStyle($headerRange)->getFill()->getStartColor()->setARGB('FF2E86DE');
    $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    $currentRow = $rowNumber + 1;
    if (empty($rows)) {
        $sheet->setCellValue('A' . $currentRow, 'Tidak ada data');
        $sheet->mergeCells('A' . $currentRow . ':' . aptd_excel_cell(max(1, $lastColumn), $currentRow));
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $currentRow++;
    } else {
        foreach ($rows as $row) {
            foreach (array_values($row) as $index => $value) {
                $cell = aptd_excel_cell($index + 1, $currentRow);
                $sheet->setCellValue($cell, $value);
            }
            $dataRange = 'A' . $currentRow . ':' . aptd_excel_cell($lastColumn, $currentRow);
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $currentRow++;
        }
    }

    for ($column = 1; $column <= max(1, $lastColumn); $column++) {
        $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
    }
}

function aptd_excel_add_sheet($spreadsheet, $sheetTitle, $title, array $headers, array $rows, $note = '')
{
    if (!aptd_excel_bootstrap() || !$spreadsheet) {
        return null;
    }

    $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, substr((string) $sheetTitle, 0, 31));
    $spreadsheet->addSheet($sheet);
    $sheet->setCellValue('A1', $title);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    if ($note !== '') {
        $sheet->setCellValue('A2', $note);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
    }
    aptd_excel_render_table($sheet, $headers, $rows, 4);
    return $sheet;
}

function aptd_excel_add_bar_chart_sheet($spreadsheet, $sheetTitle, $chartTitle, $categoryLabel, array $labels, array $seriesMap, $horizontal = false)
{
    if (!aptd_excel_bootstrap() || !$spreadsheet) {
        return null;
    }

    $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, substr((string) $sheetTitle, 0, 31));
    $spreadsheet->addSheet($sheet);
    $sheet->setCellValue('A1', $chartTitle);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $sheet->setCellValue('A3', $categoryLabel);
    $row = 4;
    foreach ($labels as $label) {
        $sheet->setCellValue('A' . $row, $label);
        $row++;
    }

    $seriesLabels = array();
    $seriesValues = array();
    $column = 2;
    foreach ($seriesMap as $name => $values) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
        $sheet->setCellValue($columnLetter . '3', $name);
        foreach (array_values($values) as $idx => $value) {
            $sheet->setCellValue($columnLetter . ($idx + 4), $value);
        }
        $seriesLabels[] = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(\PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheet->getTitle()}'!\${$columnLetter}\$3", null, 1);
        $seriesValues[] = new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(\PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheet->getTitle()}'!\${$columnLetter}\$4:\${$columnLetter}\$" . (count($labels) + 3), null, count($labels));
        $column++;
    }

    $xAxisTickValues = array(new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(\PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheet->getTitle()}'!\$A\$4:\$A\$" . (count($labels) + 3), null, count($labels)));
    $dataSeries = new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(\PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_BARCHART, null, range(0, max(0, count($seriesValues) - 1)), $seriesLabels, $xAxisTickValues, $seriesValues, $horizontal ? \PhpOffice\PhpSpreadsheet\Chart\DataSeries::DIRECTION_BAR : \PhpOffice\PhpSpreadsheet\Chart\DataSeries::DIRECTION_COL);
    $dataSeries->setPlotDirection($horizontal ? \PhpOffice\PhpSpreadsheet\Chart\DataSeries::DIRECTION_BAR : \PhpOffice\PhpSpreadsheet\Chart\DataSeries::DIRECTION_COL);

    $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea(new \PhpOffice\PhpSpreadsheet\Chart\Layout(), array($dataSeries));
    $legend = new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_BOTTOM, null, false);
    $chart = new \PhpOffice\PhpSpreadsheet\Chart\Chart('chart_' . preg_replace('/[^A-Za-z0-9]/', '_', $sheetTitle), new \PhpOffice\PhpSpreadsheet\Chart\Title($chartTitle), $legend, $plotArea, true, 0, new \PhpOffice\PhpSpreadsheet\Chart\Title($categoryLabel), new \PhpOffice\PhpSpreadsheet\Chart\Title('Jumlah'));
    $chart->setTopLeftPosition('E3');
    $chart->setBottomRightPosition('N22');
    $sheet->addChart($chart);

    for ($i = 1; $i < $column; $i++) {
        $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }

    return $sheet;
}

function aptd_excel_add_pie_chart_sheet($spreadsheet, $sheetTitle, $chartTitle, $categoryLabel, $valueLabel, array $labels, array $values)
{
    if (!aptd_excel_bootstrap() || !$spreadsheet) {
        return null;
    }

    $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, substr((string) $sheetTitle, 0, 31));
    $spreadsheet->addSheet($sheet);
    $sheet->setCellValue('A1', $chartTitle);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->setCellValue('A3', $categoryLabel);
    $sheet->setCellValue('B3', $valueLabel);

    $row = 4;
    foreach ($labels as $index => $label) {
        $sheet->setCellValue('A' . $row, $label);
        $sheet->setCellValue('B' . $row, isset($values[$index]) ? $values[$index] : 0);
        $row++;
    }

    $seriesLabels = array(new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(\PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheet->getTitle()}'!\$B\$3", null, 1));
    $xAxisTickValues = array(new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(\PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheet->getTitle()}'!\$A\$4:\$A\$" . (count($labels) + 3), null, count($labels)));
    $dataSeriesValues = array(new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(\PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheet->getTitle()}'!\$B\$4:\$B\$" . (count($labels) + 3), null, count($labels)));

    $series = new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(\PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_PIECHART, null, array(0), $seriesLabels, $xAxisTickValues, $dataSeriesValues);
    $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea(new \PhpOffice\PhpSpreadsheet\Chart\Layout(), array($series));
    $legend = new \PhpOffice\PhpSpreadsheet\Chart\Legend(\PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_BOTTOM, null, false);
    $chart = new \PhpOffice\PhpSpreadsheet\Chart\Chart('chart_' . preg_replace('/[^A-Za-z0-9]/', '_', $sheetTitle), new \PhpOffice\PhpSpreadsheet\Chart\Title($chartTitle), $legend, $plotArea, true, 0, null, null);
    $chart->setTopLeftPosition('D3');
    $chart->setBottomRightPosition('L20');
    $sheet->addChart($chart);

    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);

    return $sheet;
}

function aptd_excel_output($spreadsheet, $filename)
{
    if (!aptd_excel_bootstrap() || !$spreadsheet) {
        return false;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setIncludeCharts(true);
    $writer->save('php://output');
    exit;
}

function aptd_excel_output_csv($filename, array $headers, array $rows)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        exit;
    }

    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
