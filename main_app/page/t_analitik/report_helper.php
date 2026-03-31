<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';

function aptd_month_labels_local()
{
    return [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
}

function aptd_filter_month_year()
{
    $month = isset($_POST['month']) ? (int) $_POST['month'] : (int) date('n');
    $year = isset($_POST['year']) ? (int) $_POST['year'] : (int) date('Y');

    if ($month < 1 || $month > 12) {
        $month = (int) date('n');
    }

    if ($year < 2020 || $year > ((int) date('Y') + 1)) {
        $year = (int) date('Y');
    }

    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));

    return [$month, $year, $start, $end];
}

function aptd_filter_date_range()
{
    $start = isset($_POST['start_date']) && $_POST['start_date'] !== '' ? $_POST['start_date'] : date('Y-m-01');
    $end = isset($_POST['end_date']) && $_POST['end_date'] !== '' ? $_POST['end_date'] : date('Y-m-d');

    if (!strtotime($start)) {
        $start = date('Y-m-01');
    }

    if (!strtotime($end)) {
        $end = date('Y-m-d');
    }

    if ($start > $end) {
        $temp = $start;
        $start = $end;
        $end = $temp;
    }

    return [$start, $end];
}

function aptd_service_options()
{
    return [
        'all' => 'Semua Layanan',
        'Ralan' => 'Rawat Jalan',
        'Ranap' => 'Rawat Inap',
    ];
}

function aptd_selected_service()
{
    $allowed = array_keys(aptd_service_options());
    $value = isset($_POST['service']) ? $_POST['service'] : 'all';
    return in_array($value, $allowed, true) ? $value : 'all';
}

function aptd_currency($value)
{
    return number_format((float) $value, 0, ',', '.');
}

function aptd_number($value)
{
    return number_format((float) $value, 0, ',', '.');
}

function aptd_render_shell($config)
{
    $title = isset($config['title']) ? $config['title'] : 'Analitik';
    $subtitle = isset($config['subtitle']) ? $config['subtitle'] : '';
    $filters = isset($config['filters']) ? $config['filters'] : '';
    $cards = isset($config['cards']) ? $config['cards'] : '';
    $panels = isset($config['panels']) ? $config['panels'] : '';
    $table = isset($config['table']) ? $config['table'] : '';

    echo '<br>';
    echo '<style>
    .analytics-wrap{display:grid;gap:18px}.analytics-hero,.analytics-panel,.analytics-card{background:#fff;border:1px solid rgba(120,155,220,.16);box-shadow:0 18px 36px rgba(74,101,145,.10);border-radius:22px}.analytics-hero{padding:24px;background:linear-gradient(135deg,#eef7ff,#ffffff 46%,#eefcf5)}.analytics-title{margin:0 0 8px;font-size:34px;font-weight:800;color:#21406c}.analytics-sub{margin:0;color:#587192;font-size:14px;max-width:780px}.analytics-filter{display:flex;flex-wrap:wrap;gap:12px;align-items:end;margin-top:18px}.analytics-filter .form-control,.analytics-filter .btn{border-radius:12px}.analytics-filter .btn-primary{background:linear-gradient(135deg,#2e86de,#1f5fae);border:none}.analytics-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px}.analytics-card{padding:18px}.analytics-card:nth-child(1){background:linear-gradient(135deg,#edf6ff,#fff)}.analytics-card:nth-child(2){background:linear-gradient(135deg,#eefcf5,#fff)}.analytics-card:nth-child(3){background:linear-gradient(135deg,#fff6ea,#fff)}.analytics-card:nth-child(4){background:linear-gradient(135deg,#f5f1ff,#fff)}.analytics-k{font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#6f84a4}.analytics-v{font-size:28px;font-weight:800;color:#1f3f6d;line-height:1.1}.analytics-s{margin-top:8px;font-size:12px;color:#60789d}.analytics-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:18px}.analytics-panel{padding:20px}.analytics-head{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-bottom:14px}.analytics-h{margin:0;font-size:20px;font-weight:800;color:#1e3d6a}.analytics-d{margin:4px 0 0;color:#6f84a4;font-size:13px}.analytics-pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#eaf4ff;color:#2d6ab0;font-size:12px;font-weight:700}.analytics-chart{position:relative;min-height:320px}.analytics-note{padding:14px 16px;border-radius:16px;background:#fff8e8;border:1px solid #f5db9a;color:#8a6816}.analytics-table thead th{text-align:center}.analytics-empty{padding:24px;text-align:center;color:#6c84a8}@media(max-width:991px){.analytics-grid{grid-template-columns:1fr}}@media(max-width:576px){.analytics-title{font-size:28px}.analytics-filter{flex-direction:column;align-items:stretch}}
    </style>';
    echo '<div class="analytics-wrap">';
    echo '<section class="analytics-hero">';
    echo '<h1 class="analytics-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p class="analytics-sub">' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</p>';
    echo $filters;
    echo '</section>';
    echo $cards;
    echo $panels;
    echo $table;
    echo '</div>';
}
?>
