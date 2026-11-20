<?php
// CLI only. --fixtures uses connection-local TEMPORARY tables; --live reads SIMRS.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require $root . '/config/akses.php';
require $root . '/main_app/page/t_klinis/rekap_ranap_operasi_helper.php';
require $root . '/main_app/page/t_klinis/rekap_ranap_operasi_excel.php';
$checks = 0;
function ro_check($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
}
function ro_reject(array $input) {
    try { aptd_ranap_operasi_filters($input); } catch (InvalidArgumentException $e) { ro_check(true, 'Filter ditolak'); return; }
    ro_check(false, 'Filter invalid diterima');
}
// MariaDB cannot reference a TEMPORARY table twice in one query. These two
// identical connection-local copies let the production predicates run unchanged.
class RoFixtureConnection {
    private $db;
    public function __construct($db) { $this->db=$db; }
    public function prepare($sql) {
        return $this->db->prepare(str_replace(
            ['FROM kamar_inap newer','EXISTS (SELECT 1 FROM operasi o'],
            ['FROM ro_kamar_newer newer','EXISTS (SELECT 1 FROM ro_operasi_exists o'], $sql));
    }
    public function __get($key) { return $this->db->$key; }
    public function get_warnings() { return $this->db->get_warnings(); }
}
foreach ([['start_date'=>'2026-02-30'], ['start_date'=>"2026-01-01\0"], ['start_date'=>'0000-01-01'], ['end_date'=>''], ['start_date'=>'2026-12-01','end_date'=>'2026-01-01'], ['date_type'=>'other'], ['date_type'=>[]], ['kd_bangsal'=>[]], ['kd_bangsal'=>'ABCDEF']] as $input) { ro_reject($input); }
ro_check(aptd_ranap_operasi_filters([])['start_date'] === date('Y-m-01'), 'Default bulan berjalan');
ro_check(aptd_ranap_operasi_filters(['start_date'=>'2024-02-29'])['start_date'] === '2024-02-29', 'Tahun kabisat');
foreach (['admin', 'medis'] as $role) {
    ro_check(aptd_can_access($role, 'rekap_ranap_operasi') && aptd_can_access($role, 'export_rekap_ranap_operasi'), 'Akses bawaan ' . $role);
}
foreach (['users', 'manajemen', 'keuangan', 'perawat'] as $role) {
    ro_check(!aptd_can_access($role, 'rekap_ranap_operasi') && !aptd_can_access($role, 'export_rekap_ranap_operasi'), 'Role lain tertutup');
}
ro_check(in_array('export_rekap_ranap_operasi', aptd_extra_access_pages(['rekap_ranap_operasi']), true), 'Akses tambahan membawa ekspor');
ro_check(aptd_direct_report_pages('page/t_klinis/export_rekap_ranap_operasi.php', []) === ['export_rekap_ranap_operasi'], 'Guard direct export');
ro_check(isset(aptd_extra_menu_catalog()['rekap_ranap_operasi']), 'Menu masuk katalog tambahan');

if (in_array('--fixtures', $argv, true)) {
    require $root . '/config/koneksi.php';
    $tables = [
        'reg_periksa' => 'no_rawat VARCHAR(17) PRIMARY KEY, no_rkm_medis VARCHAR(15), tgl_registrasi DATE, kd_pj CHAR(3), INDEX(tgl_registrasi)',
        'pasien' => 'no_rkm_medis VARCHAR(15) PRIMARY KEY, nm_pasien VARCHAR(40)',
        'kamar_inap' => 'no_rawat VARCHAR(17), kd_kamar VARCHAR(15), tgl_masuk DATE, jam_masuk TIME, tgl_keluar DATE, jam_keluar TIME NULL, stts_pulang VARCHAR(40), PRIMARY KEY(no_rawat,tgl_masuk,jam_masuk), INDEX(tgl_keluar)',
        'kamar' => 'kd_kamar VARCHAR(15) PRIMARY KEY, kd_bangsal CHAR(5)',
        'bangsal' => 'kd_bangsal CHAR(5) PRIMARY KEY, nm_bangsal VARCHAR(30), status CHAR(1)',
        'operasi' => 'no_rawat VARCHAR(17), tgl_operasi DATETIME, kode_paket VARCHAR(15), PRIMARY KEY(no_rawat,tgl_operasi,kode_paket)',
        'paket_operasi' => 'kode_paket VARCHAR(15) PRIMARY KEY, nm_perawatan VARCHAR(80)',
    ];
    $created = [];
    try {
        foreach ($tables as $table => $columns) { $mysqli->query("CREATE TEMPORARY TABLE `$table` ($columns) ENGINE=InnoDB"); $created[] = $table; }
        $mysqli->query("INSERT INTO bangsal VALUES ('A','Bangsal A','1'),('B','Bangsal B','1'),('OLD','Bangsal Lama','0')");
        $mysqli->query("INSERT INTO kamar VALUES ('A1','A'),('B1','B'),('OLD1','OLD')");
        $mysqli->query("INSERT INTO pasien VALUES ('000001','=Nama <uji>'),('000002','Pasien Uji')");
        $mysqli->query("INSERT INTO paket_operasi VALUES ('OP1','Operasi A'),('OP2','Operasi B'),('OP3','Operasi A')");
        foreach (['MOVE'=>'BPJ','TIE'=>'A09','MULTI'=>'XYZ','ACTIVE'=>'BPJ','NOOP'=>'A09','SAME'=>'BPJ','OLD'=>'A09','LONG'=>'BPJ','ZERO'=>'A09'] as $rawat => $payer) {
            $mysqli->query("INSERT INTO reg_periksa VALUES ('$rawat','000001','2026-10-01','$payer')");
            if ($rawat !== 'NOOP') { $mysqli->query("INSERT INTO operasi VALUES ('$rawat','2026-10-02 10:00:00','OP1')"); }
            $room = $rawat === 'OLD' ? 'OLD1' : 'A1';
            $status = $rawat === 'ACTIVE' ? '-' : 'Sehat';
            $exit = in_array($rawat, ['ACTIVE','ZERO']) ? '0000-00-00' : '2026-10-05';
            $mysqli->query("INSERT INTO kamar_inap VALUES ('$rawat','$room','2026-10-01','08:00:00','$exit','10:00:00','$status')");
        }
        $mysqli->query("UPDATE kamar_inap SET stts_pulang='Pindah Kamar' WHERE no_rawat='MOVE'");
        $mysqli->query("INSERT INTO kamar_inap VALUES ('MOVE','B1','2026-10-05','11:00:00','2026-10-08','09:00:00','Sehat'),('TIE','B1','2026-10-02','08:00:00','2026-10-05','10:00:00','Sehat'),('MULTI','B1','2026-10-06','08:00:00','2026-11-01','10:00:00','Sehat')");
        $mysqli->query("INSERT INTO operasi VALUES ('MOVE','2026-10-03 10:00:00','OP2'),('MOVE','2026-10-04 10:00:00','OP3')");
        for ($i=0; $i<30; $i++) {
            $name = str_pad('Tindakan ' . sprintf('%02d', $i), 70, ' panjang');
            $stmt = $mysqli->prepare('INSERT INTO paket_operasi VALUES (?,?)');
            $code = 'LONG' . $i; $stmt->bind_param('ss', $code, $name); $stmt->execute(); $stmt->close();
            $mysqli->query("INSERT INTO operasi VALUES ('LONG','2026-10-02 10:00:00','$code')");
        }
        foreach (['ro_kamar_newer'=>'kamar_inap','ro_operasi_exists'=>'operasi'] as $copy=>$original) {
            $mysqli->query("CREATE TEMPORARY TABLE `$copy` LIKE `$original`"); $created[]=$copy;
            $mysqli->query("INSERT INTO `$copy` SELECT * FROM `$original`");
        }
        $fixtureDb=new RoFixtureConnection($mysqli);
        $filters = ['start_date'=>'2026-10-01','end_date'=>'2026-10-31','date_type'=>'masuk','kd_bangsal'=>''];
        $report = aptd_ranap_operasi_report($fixtureDb, $filters);
        $byId = array_column($report['rows'], null, 'no_rawat');
        ro_check($report['totals'] === ['BPJS'=>3,'Umum'=>2,'Asuransi'=>1,'Total'=>6], 'Jenis bayar dan episode unik, RM sama tetap dihitung per no_rawat');
        ro_check(!isset($byId['ACTIVE']) && !isset($byId['ZERO']) && !isset($byId['NOOP']), 'Aktif, tanggal nol, dan tanpa operasi tidak masuk');
        ro_check($byId['MOVE']['nama_ruangan']==='B1' && $byId['MOVE']['lama_rawat']===7, 'Mutasi dan lama rawat');
        ro_check($byId['MOVE']['nama_tindakan']==='Operasi A, Operasi B', 'Gabung tindakan unik');
        ro_check($byId['TIE']['nama_ruangan']==='B1', 'Tie timestamp memilih tanggal masuk terakhir');
        ro_check($byId['MULTI']['nama_ruangan']==='B1', 'Kamar terbaru dipilih di luar periode');
        ro_check(strlen($byId['LONG']['nama_tindakan'])>2000 && strpos($byId['LONG']['nama_tindakan'],'Tindakan 29')!==false, 'GROUP_CONCAT tidak terpotong');
        ro_check(count(aptd_ranap_operasi_bangsal($mysqli))===2, 'Opsi hanya bangsal aktif');
        ro_check(count($report['summary'])===3, 'Bangsal historis tetap masuk semua bangsal');
        $filtered = aptd_ranap_operasi_report($fixtureDb, array_merge($filters,['kd_bangsal'=>'A']));
        ro_check(!in_array('MOVE',array_column($filtered['rows'],'no_rawat'),true) && !in_array('TIE',array_column($filtered['rows'],'no_rawat'),true), 'Filter bangsal diterapkan setelah kamar akhir');
        ro_check(aptd_ranap_operasi_report($fixtureDb,array_merge($filters,['kd_bangsal'=>"'OR1"]))['totals']['Total']===0, 'Kode filter tidak menjadi SQL');
        $exits = aptd_ranap_operasi_report($fixtureDb,array_merge($filters,['date_type'=>'keluar']));
        ro_check($exits['totals']['Total']===5 && !in_array('MULTI',array_column($exits['rows'],'no_rawat'),true), 'Filter tanggal keluar tidak mengambil kamar lama');
        $book = aptd_ranap_operasi_workbook($report,aptd_ranap_operasi_bangsal($mysqli),'2026-11-20 12:00:00 WITA');
        ro_check($book->getSheetCount()===2, 'Dua sheet Excel');
        ro_check($book->getSheet(1)->getCell('A6')->getValue()==='000001' && $book->getSheet(1)->getCell('A6')->getDataType()==='s', 'Nol depan RM');
        ro_check($book->getSheet(1)->getCell('B6')->getDataType()==='s', 'Nama berawalan formula disimpan teks');
        ro_check($book->getSheet(0)->getCell('E9')->getValue()===6, 'Total Excel sama dengan laporan');
        $file = tempnam(sys_get_temp_dir(), 'ar186-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);
            $read = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            ro_check($read->getSheet(1)->getHighestRow()===11 && $read->getSheet(0)->getCell('E9')->getValue()===6,'XLSX dapat dibaca ulang');
            $read->disconnectWorksheets();
        } finally { unlink($file); $book->disconnectWorksheets(); }
        $empty = aptd_ranap_operasi_report($fixtureDb,array_merge($filters,['start_date'=>'2030-01-01','end_date'=>'2030-01-31']));
        ro_check($empty['rows']===[] && $empty['totals']['Total']===0, 'Hasil kosong tidak kembali ke semua data');
        echo "Fixture: temporary tables pada koneksi pengujian saja.\n";
    } finally {
        foreach (array_reverse($created) as $table) { $mysqli->query("DROP TEMPORARY TABLE `$table`"); }
    }
}

if (in_array('--live', $argv, true)) {
    require_once $root . '/config/koneksi.php';
    foreach (['masuk','keluar'] as $basis) {
        $filters = aptd_ranap_operasi_filters(['start_date'=>'2026-10-01','end_date'=>'2026-10-31','date_type'=>$basis]);
        $report = aptd_ranap_operasi_report($mysqli,$filters);
        ro_check($report['totals']['Total']===count($report['rows']), 'Live total sama dengan rincian');
        ro_check($report['query_seconds']<2, 'Live query satu bulan di bawah 2 detik');
        // Independent reference: sort all eligible stay records in PHP, then filter final stay.
        $candidates = aptd_ranap_operasi_select($mysqli,
            "SELECT rp.no_rawat, ki.kd_kamar, k.kd_bangsal, ki.tgl_keluar, ki.jam_keluar, ki.tgl_masuk, ki.jam_masuk
             FROM reg_periksa rp JOIN kamar_inap ki ON ki.no_rawat=rp.no_rawat JOIN kamar k ON k.kd_kamar=ki.kd_kamar
             WHERE rp.tgl_registrasi <= ? AND ki.stts_pulang NOT IN ('-', 'Pindah Kamar') AND ki.tgl_keluar>'0000-00-00'
             AND EXISTS (SELECT 1 FROM operasi o WHERE o.no_rawat=rp.no_rawat)
             AND " . ($basis==='masuk' ? 'rp.tgl_registrasi >= ?' : 'ki.tgl_keluar >= ?') . "
             ORDER BY rp.no_rawat, ki.tgl_keluar DESC, COALESCE(ki.jam_keluar,'00:00:00') DESC, ki.tgl_masuk DESC, ki.jam_masuk DESC", [$filters['end_date'],$filters['start_date']]);
        $reference = [];
        foreach ($candidates as $row) { if (!isset($reference[$row['no_rawat']])) { $reference[$row['no_rawat']]=$row; } }
        if ($basis==='keluar') { $reference=array_filter($reference,function($r)use($filters){return $r['tgl_keluar']<=$filters['end_date'];}); }
        $expected = array_keys($reference); sort($expected);
        $actual = array_column($report['rows'],'no_rawat'); sort($actual);
        ro_check($expected===$actual, 'Live cohort cocok referensi independen');
        foreach ($report['rows'] as $row) {
            ro_check($reference[$row['no_rawat']]['kd_kamar']===$row['nama_ruangan'],'Live kamar akhir cocok');
        }
        foreach (array_unique(array_column($report['rows'],'kd_bangsal')) as $ward) {
            $wardReport=aptd_ranap_operasi_report($mysqli,array_merge($filters,['kd_bangsal'=>$ward]));
            $expectedWard=array_values(array_filter($report['rows'],function($r)use($ward){return $r['kd_bangsal']===$ward;}));
            ro_check($wardReport['rows']===$expectedWard,'Live filter bangsal konsisten');
        }
        echo $basis . ': ' . count($report['rows']) . ' episode; ' . round($report['query_seconds'],4) . " detik\n";
    }
}
echo 'PASS ' . $checks . " pemeriksaan AR-186\n";
