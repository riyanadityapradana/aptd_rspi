<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
require_once $projectRoot . '/config/koneksi.php';
require_once $projectRoot . '/config/akses.php';
require_once $projectRoot . '/main_app/page/export_excel_helper.php';
require_once __DIR__ . '/evaluasi_task4_bpjs_helper.php';

$isLoggedIn = isset($_SESSION['login_aptd_rspi']) && $_SESSION['login_aptd_rspi'] === true;
$level = isset($_SESSION['level']) ? (string) $_SESSION['level'] : '';

if (!$isLoggedIn) {
    http_response_code(401);
    exit('Sesi login telah berakhir.');
}
if (!aptd_can_access($level, 'export_evaluasi_task4_bpjs')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses untuk export laporan ini.');
}

list($filters, $errors) = aptd_task4_filter_from_request($_GET);
if (!empty($errors)) {
    http_response_code(422);
    exit(implode(' ', $errors));
}

try {
    $filters['search'] = '';
    $filters['page'] = 1;
    $report = aptd_task4_build_report($mysqli, $filters, false);
    $rows = [];

    foreach ($report['data'] as $index => $row) {
        $rows[] = [
            $index + 1,
            $row['tanggal_label'],
            $row['nama_poli'],
            $row['jam_buka_poli'],
            $row['nama_dokter'],
            $row['no_registrasi_awal'],
            $row['task_4_paling_awal'],
            $row['selisih_waktu'],
            $row['kesesuaian'],
        ];
    }

    list($spreadsheet, $sheet) = aptd_excel_create(
        'Evaluasi Task ID 4 BPJS',
        'Periode ' . $filters['tanggal_awal'] . ' sampai sebelum ' . $filters['tanggal_akhir'],
        'Detail Evaluasi'
    );

    if (!$spreadsheet || !$sheet) {
        throw new RuntimeException('PhpSpreadsheet tidak tersedia.');
    }

    $sheet->unmergeCells('A1:F1');
    $sheet->unmergeCells('A2:F2');
    $sheet->mergeCells('A1:I1');
    $sheet->mergeCells('A2:I2');
    $sheet->setCellValue(
        'A3',
        'Poli: ' . ($filters['kd_poli'] !== '' ? $filters['kd_poli'] : 'Semua')
        . ' | Dokter: ' . ($filters['kd_dokter'] !== '' ? $filters['kd_dokter'] : 'Semua')
        . ' | Status: ' . ($filters['kesesuaian'] === 'semua' ? 'Semua' : ucwords(str_replace('_', ' ', $filters['kesesuaian'])))
    );
    $sheet->mergeCells('A3:I3');
    $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);

    aptd_excel_render_table($sheet, [
        'Nomor',
        'Tanggal',
        'Nama Poli',
        'Jam Buka Poli',
        'Nama Dokter',
        'Nomor Registrasi Awal',
        'Waktu Task ID 4 Paling Awal',
        'Selisih Waktu',
        'Status Kesesuaian',
    ], $rows, 5);

    $lastDataRow = max(6, count($rows) + 5);
    $sheet->getStyle('A5:I' . $lastDataRow)->getAlignment()->setVertical(
        \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
    );
    $sheet->getStyle('B5:I' . $lastDataRow)->getAlignment()->setWrapText(true);
    $sheet->freezePane('A6');
    $sheet->setAutoFilter('A5:I' . $lastDataRow);

    $filename = 'evaluasi-task-4-bpjs-' . $filters['tanggal_awal'] . '-sampai-' . $filters['tanggal_akhir'] . '.xlsx';
    aptd_excel_output($spreadsheet, $filename);
} catch (Throwable $exception) {
    error_log('Export Evaluasi Task ID 4: ' . $exception->getMessage());
    http_response_code(500);
    exit('Export belum dapat dibuat. Silakan coba kembali atau hubungi Unit IT RSPI.');
}
