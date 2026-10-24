<?php
require_once __DIR__ . '/vaksinasi_haji_helper.php';
if (!defined('K_TCPDF_THROW_EXCEPTION_ERROR')) {
    define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
}
require_once dirname(__DIR__, 4) . '/assets/report/tcpdf/tcpdf.php';

class AptdVaksinasiHajiPdf extends TCPDF
{
    public $hospital = [];
    public $printedAt = '';

    public function Header()
    {
        $logo = dirname(__DIR__, 4) . '/assets/report/vaksinasi-haji-logo.jpg';
        if (is_file($logo)) {
            $this->Image($logo, 12, 8, 25, 25, 'JPG');
        }
        $this->SetTextColor(25, 35, 45);
        $this->SetFont('dejavusans', 'B', 14);
        $this->SetXY(40, 9);
        $this->MultiCell(245, 7, mb_strtoupper($this->hospital['nama_instansi'], 'UTF-8'), 0, 'C');
        $this->SetFont('dejavusans', '', 8.5);
        $address = implode(', ', array_filter([
            $this->hospital['alamat_instansi'], $this->hospital['kabupaten'], $this->hospital['propinsi'],
        ]));
        $this->SetX(40);
        $this->MultiCell(245, 5, $address, 0, 'C');
        $this->SetX(40);
        $this->MultiCell(245, 5, $this->hospital['kontak'], 0, 'C');
        $this->SetX(40);
        $this->MultiCell(245, 5, $this->hospital['email'], 0, 'C');
        $this->SetDrawColor(45, 55, 65);
        $this->SetLineWidth(0.5);
        $this->Line(12, 37, 285, 37);
        $this->SetLineWidth(0.2);
        $this->Line(12, 38, 285, 38);
    }

    public function Footer()
    {
        $this->SetY(-11);
        $this->SetFont('dejavusans', '', 7);
        $this->SetTextColor(85, 95, 105);
        $this->Cell(180, 5, 'APTD RSPI | Dicetak: ' . $this->printedAt . ' WITA');
        $this->Cell(93, 5, 'Halaman ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

function aptd_vh_build_pdf(array $report, array $filters, array $paymentOptions, array $hospital)
{
    $pdf = new AptdVaksinasiHajiPdf('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->hospital = $hospital;
    $pdf->printedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Makassar')))->format('d-m-Y H:i:s');
    $pdf->SetCreator('APTD RSPI');
    $pdf->SetAuthor($hospital['nama_instansi']);
    $pdf->SetTitle('Laporan Data Vaksinasi Haji');
    $pdf->SetMargins(12, 44, 12);
    $pdf->SetAutoPageBreak(true, 16);
    $pdf->setFontSubsetting(true);
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->AddPage();

    $selectedPayment = $filters['jenis_bayar'] === ''
        ? 'Semua Jenis Bayar (A09, A96)'
        : $paymentOptions[$filters['jenis_bayar']] . ' (' . $filters['jenis_bayar'] . ')';
    $html = '<h2 style="text-align:center;font-size:13pt">LAPORAN DATA VAKSINASI HAJI</h2>';
    $html .= '<p>Periode: <b>' . aptd_vh_h($filters['tanggal_awal'] . ' s.d. ' . $filters['tanggal_akhir'])
        . '</b><br>Jenis bayar: <b>' . aptd_vh_h($selectedPayment) . '</b><br>Poliklinik: U0053 | Status: Sudah Bayar</p>';
    $html .= '<p><b>Total Kunjungan: ' . number_format($report['total_kunjungan'], 0, ',', '.')
        . ' | Pasien Unik: ' . number_format($report['total_pasien'], 0, ',', '.') . '</b><br>';
    $parts = [];
    foreach ($paymentOptions as $code => $label) {
        $parts[] = aptd_vh_h($label . ' (' . $code . ')') . ': ' . number_format($report['per_bayar'][$code], 0, ',', '.');
    }
    $html .= implode(' | ', $parts) . '</p>';
    $headers = [
        ['No.', 8], ['No. Rawat', 37], ['Tanggal Registrasi', 25], ['Jam', 18], ['No. RM', 22],
        ['Nama Pasien', 53], ['Poliklinik', 45], ['Jenis Bayar', 35], ['Status Bayar', 30],
    ];
    $html .= '<table border="1" cellpadding="4" cellspacing="0" style="font-size:7.5pt"><thead><tr style="background-color:#edf1f4;font-weight:bold">';
    foreach ($headers as $header) {
        $html .= '<th width="' . $header[1] . 'mm">' . aptd_vh_h($header[0]) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($report['rows'] as $index => $row) {
        $cells = [
            $index + 1, $row['no_rawat'], $row['tgl_registrasi'], $row['jam_reg'], $row['no_rkm_medis'],
            $row['nm_pasien'], $row['nm_poli'] . ' (' . $row['kd_poli'] . ')',
            $row['jenis_bayar'] . ' (' . $row['kd_pj'] . ')', $row['status_bayar'],
        ];
        $html .= '<tr nobr="true">';
        foreach ($cells as $column => $value) {
            $html .= '<td width="' . $headers[$column][1] . 'mm">' . aptd_vh_h($value) . '</td>';
        }
        $html .= '</tr>';
    }
    if (empty($report['rows'])) {
        $html .= '<tr><td colspan="9" align="center">Tidak ada data vaksinasi haji pada filter ini.</td></tr>';
    }
    $html .= '</tbody></table><p><b>Total laporan: ' . number_format($report['total_kunjungan'], 0, ',', '.')
        . ' kunjungan; ' . number_format($report['total_pasien'], 0, ',', '.') . ' pasien unik.</b></p>';
    $pdf->writeHTML($html, true, false, true, false, '');
    return $pdf->Output('', 'S');
}
