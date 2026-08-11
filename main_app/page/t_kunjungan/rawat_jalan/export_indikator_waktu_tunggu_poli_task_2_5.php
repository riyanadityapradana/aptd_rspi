<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
require_once $projectRoot . '/config/koneksi.php';
require_once $projectRoot . '/config/akses.php';
require_once $projectRoot . '/main_app/page/export_excel_helper.php';
require_once __DIR__ . '/indikator_waktu_tunggu_poli_task_2_5_helper.php';

$isLoggedIn = isset($_SESSION['login_aptd_rspi']) && $_SESSION['login_aptd_rspi'] === true;
$level = isset($_SESSION['level']) ? (string) $_SESSION['level'] : '';

if (!$isLoggedIn) {
    http_response_code(401);
    exit('Sesi login telah berakhir.');
}
if (!aptd_can_access($level, 'export_indikator_waktu_tunggu_poli_task_2_5')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses untuk export laporan ini.');
}

list($filters, $errors) = aptd_wt25_filter_from_request($_GET);
if (!empty($errors)) {
    http_response_code(422);
    exit(implode(' ', $errors));
}

try {
    $filters['page'] = 1;
    $report = aptd_wt25_build_report($mysqli, $filters, false);
    $rows = [];

    foreach ($report['data'] as $index => $row) {
        $rows[] = [
            $index + 1,
            $row['tanggal_label'],
            $row['no_rawat'],
            $row['nama_pasien'],
            $row['nama_poli'],
            $row['nama_dokter'],
            $row['jam_buka_poli'],
            $row['task_2'],
            $row['task_3'],
            $row['task_4'],
            $row['task_5'],
            $row['wt_poli'],
            $row['sumber_wt'],
            $row['status_daftar'],
            $row['task_99'],
            $row['status_batal_task99'],
        ];
    }

    list($spreadsheet, $sheet) = aptd_excel_create(
        'Indikator Waktu Tunggu Poli (Task ID 2-5)',
        'Periode ' . $filters['tanggal_awal'] . ' s.d. ' . $filters['tanggal_akhir'],
        'Detail WT Poli'
    );

    if (!$spreadsheet || !$sheet) {
        throw new RuntimeException('PhpSpreadsheet tidak tersedia.');
    }

    $sheet->unmergeCells('A1:F1');
    $sheet->unmergeCells('A2:F2');
    $sheet->mergeCells('A1:P1');
    $sheet->mergeCells('A2:P2');

    $statusLabels = [
        'semua' => 'Semua',
        'terkirim' => 'Batal - Terkirim',
        'belum_terkirim' => 'Batal - Belum Terkirim',
        'bukan_batal' => 'Bukan Batal',
    ];
    $sheet->setCellValue(
        'A3',
        'Poli: ' . ($filters['kd_poli'] !== '' ? $filters['kd_poli'] : 'Semua')
        . ' | Dokter: ' . ($filters['kd_dokter'] !== '' ? $filters['kd_dokter'] : 'Semua')
        . ' | Status Task 99: ' . $statusLabels[$filters['status_task99']]
        . ' | Pencarian: ' . ($filters['search'] !== '' ? $filters['search'] : '-')
    );
    $sheet->mergeCells('A3:P3');
    $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);

    aptd_excel_render_table($sheet, [
        'Nomor',
        'Tanggal',
        'No. Rawat',
        'Nama Pasien',
        'Nama Poli',
        'Nama Dokter',
        'Jam Buka Poli',
        'Task ID 2',
        'Task ID 3',
        'Task ID 4',
        'Task ID 5',
        'Waktu Tunggu Poli',
        'Sumber WT',
        'Status Daftar',
        'Task ID 99',
        'Status Task 99',
    ], $rows, 5);

    $lastDataRow = max(6, count($rows) + 5);
    $sheet->getStyle('A5:P' . $lastDataRow)->getAlignment()->setVertical(
        \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
    );
    $sheet->getStyle('B5:P' . $lastDataRow)->getAlignment()->setWrapText(true);
    $sheet->freezePane('A6');
    $sheet->setAutoFilter('A5:P' . $lastDataRow);

    $filename = 'indikator-waktu-tunggu-poli-task-2-5-' . $filters['tanggal_awal'] . '-sampai-' . $filters['tanggal_akhir'] . '.xlsx';
    aptd_excel_output($spreadsheet, $filename);
} catch (Throwable $exception) {
    error_log('Export Indikator WT Poli Task 2-5: ' . $exception->getMessage());
    http_response_code(500);
    exit('Export belum dapat dibuat. Silakan coba kembali atau hubungi Unit IT RSPI.');
}
