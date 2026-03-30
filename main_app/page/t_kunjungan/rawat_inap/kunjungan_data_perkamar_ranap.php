<br>
<div class="row text-left">
	<div class="col">
		<h3 class="text-lef" style="color: #666666; margin-bottom: 5px;">DATA KUNJUNGAN PASIEN RAWAT INAP PER KAMAR BERDASARKAN USIA</h3>
		<hr style="height: 1px; background-image: linear-gradient(to right, rgba(0,0,0,0), rgba(102,102,102,1), rgba(0,0,0,0) ); margin-top: 0; margin-bottom: 10px;">
	</div>
</div>
<div class="row">
	<div class="col-sm-12" style="border-right: 1px solid #E5E5E5">
		<div class="dataTables_wrapper table-responsive-sm" style="padding-top: 0;">
			<div class="wrapper">
				<?php
				// Database connection
				require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
$conn = $mysqli;

				// Kategori Usia
				$usia_categories = [
					'semua' => 'Semua',
					'0-6' => '0-6 Bulan',
					'7-24' => '7-24 Bulan',
					'2-5' => '2-5 Tahun',
					'5-12' => '5-12 Tahun',
					'12-17' => '12-17 Tahun',
					'18-59' => '18-59 Tahun',
					'60plus' => '60+ Tahun'
				];

				// Mapping jenis pembayar
				$penjamin = [
					'A09' => 'UMUM',
					'BPJ' => 'BPJS',
					'A92' => 'ASURANSI',
				];
				
				// Read filter values from POST
				$filter_bangsal = isset($_POST['bangsal']) ? trim($_POST['bangsal']) : '';
				$filter_month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
				$filter_year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
				$filter_usia = isset($_POST['usia']) ? trim($_POST['usia']) : 'semua';

				// Get list of bangsal
				$sql_bangsal = "SELECT kd_bangsal, nm_bangsal FROM bangsal ORDER BY nm_bangsal ASC";
				$result_bangsal = $conn->query($sql_bangsal);
				$bangsal_list = [];
				if ($result_bangsal) {
					while ($row = $result_bangsal->fetch_assoc()) {
						$bangsal_list[$row['kd_bangsal']] = $row['nm_bangsal'];
					}
				}

				// If no bangsal selected, use first one
				if (empty($filter_bangsal) && !empty($bangsal_list)) {
					$filter_bangsal = array_key_first($bangsal_list);
				}

				// Build age condition
				$age_condition = '';
				if ($filter_usia !== 'semua') {
					switch ($filter_usia) {
						case '0-6':
							$age_condition = "(TIMESTAMPDIFF(MONTH, p.tgl_lahir, r.tgl_registrasi) BETWEEN 0 AND 6)";
							break;
						case '7-24':
							$age_condition = "(TIMESTAMPDIFF(MONTH, p.tgl_lahir, r.tgl_registrasi) BETWEEN 7 AND 24)";
							break;
						case '2-5':
							$age_condition = "(YEAR(r.tgl_registrasi) - YEAR(p.tgl_lahir) - (DATE_FORMAT(r.tgl_registrasi, '%m%d') < DATE_FORMAT(p.tgl_lahir, '%m%d')) BETWEEN 2 AND 5)";
							break;
						case '5-12':
							$age_condition = "(YEAR(r.tgl_registrasi) - YEAR(p.tgl_lahir) - (DATE_FORMAT(r.tgl_registrasi, '%m%d') < DATE_FORMAT(p.tgl_lahir, '%m%d')) BETWEEN 5 AND 12)";
							break;
						case '12-17':
							$age_condition = "(YEAR(r.tgl_registrasi) - YEAR(p.tgl_lahir) - (DATE_FORMAT(r.tgl_registrasi, '%m%d') < DATE_FORMAT(p.tgl_lahir, '%m%d')) BETWEEN 12 AND 17)";
							break;
						case '18-59':
							$age_condition = "(YEAR(r.tgl_registrasi) - YEAR(p.tgl_lahir) - (DATE_FORMAT(r.tgl_registrasi, '%m%d') < DATE_FORMAT(p.tgl_lahir, '%m%d')) BETWEEN 18 AND 59)";
							break;
						case '60plus':
							$age_condition = "(YEAR(r.tgl_registrasi) - YEAR(p.tgl_lahir) - (DATE_FORMAT(r.tgl_registrasi, '%m%d') < DATE_FORMAT(p.tgl_lahir, '%m%d')) >= 60)";
							break;
					}
				}
				?>
				<form id="filterForm" method="post" class="form-inline mb-2">
					<div class="form-group mr-2">
						<label for="bangsal">Bangsal:&nbsp;</label>
						<select name="bangsal" id="bangsal" class="form-control form-control-sm ml-1">
							<?php
							foreach($bangsal_list as $kd => $nm){
								$sel = ($filter_bangsal === $kd) ? 'selected' : '';
								echo "<option value=\"".htmlspecialchars($kd)."\" $sel>".htmlspecialchars($nm)."</option>";
							}
							?>
						</select>
					</div>
					<div class="form-group mr-2">
						<label for="usia">Usia:&nbsp;</label>
						<select name="usia" id="usia" class="form-control form-control-sm ml-1">
							<?php
							foreach ($usia_categories as $key => $label) {
								$sel = ($filter_usia === $key) ? 'selected' : '';
								echo "<option value=\"$key\" $sel>" . htmlspecialchars($label) . "</option>";
							}
							?>
						</select>
					</div>
					<div class="form-group mr-2">
						<label for="month">Bulan:&nbsp;</label>
						<select name="month" id="month" class="form-control form-control-sm ml-1">
							<?php
							$months = [1=>"Januari",2=>"Februari",3=>"Maret",4=>"April",5=>"Mei",6=>"Juni",7=>"Juli",8=>"Agustus",9=>"September",10=>"Oktober",11=>"November",12=>"Desember"];
							foreach($months as $num=>$name){
								$sel = ($filter_month===$num)?'selected':'';
								echo "<option value=\"$num\" $sel>$name</option>";
							}
							?>
						</select>
					</div>
					<div class="form-group mr-2">
						<label for="year">Tahun:&nbsp;</label>
						<select name="year" id="year" class="form-control form-control-sm ml-1">
							<?php
							$startYear = 2020;
							$endYear = date('Y');
							for($y=$startYear;$y<=$endYear;$y++){
								$sel = ($filter_year===$y)?'selected':'';
								echo "<option value=\"$y\" $sel>$y</option>";
							}
							?>
						</select>
					</div>
					<button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
					<button type="button" class="btn btn-success btn-sm ml-2" id="btnExport">
						<i class="fa fa-file-excel"></i> Export Excel
					</button>
				</form>
				<table class="table table-sm table-bordered table-hover" id="table_perkamar" style="width:100%;margin-top: 10px;">
				<thead class="thead-dark">
					<tr>
						<th style="text-align: center;">No.</th>
						<th>Kode Kamar</th>
						<?php foreach($penjamin as $kd => $label){ echo "<th>".htmlspecialchars($label)."</th>"; } ?>
						<th>Jumlah Total</th>
					</tr>
				</thead>
				<tbody>
					<?php
						$data_kamar = [];
						$total_overall = 0;
						
						if (!empty($filter_bangsal)) {
							// Build WHERE clause
							$where_parts = [
								"r.status_lanjut = 'Ranap'",
								"b.kd_bangsal = '" . $conn->real_escape_string($filter_bangsal) . "'",
								"r.status_lanjut = 'Ranap'",
								"ki.stts_pulang NOT IN ('Pindah Kamar', '-', '')",
								"ki.stts_pulang IS NOT NULL"
							];

							// Add date range from month+year
							if($filter_month && $filter_year){
								$start = sprintf('%04d-%02d-01',$filter_year,$filter_month);
								$end = date('Y-m-t', strtotime($start));
								$where_parts[] = "r.tgl_registrasi BETWEEN '".$start."' AND '".$end."'";
							}

							// Add age condition
							if (!empty($age_condition)) {
								$where_parts[] = $age_condition;
							}

							$where_clause = implode(' AND ', $where_parts);

							// Get all kamar in this bangsal
							$sql_kamar = "SELECT k.kd_kamar FROM kamar k 
										LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
										WHERE b.kd_bangsal = '" . $conn->real_escape_string($filter_bangsal) . "'
										ORDER BY k.kd_kamar ASC";
							
							$result_kamar = $conn->query($sql_kamar);
							$row_num = 0;

							if ($result_kamar) {
								while ($row_kamar = $result_kamar->fetch_assoc()) {
									$kd_kamar = $row_kamar['kd_kamar'];
									//$nm_kamar = $row_kamar['nm_kamar'];
									
									// Get data for each payment type per kamar
									$kamar_data = [];
									$kamar_total = 0;

									foreach ($penjamin as $kd_pj => $label) {
										$sql = "SELECT COUNT(DISTINCT r.no_rawat) as jml 
											FROM pasien p
											INNER JOIN reg_periksa r ON p.no_rkm_medis = r.no_rkm_medis
											INNER JOIN kamar_inap ki ON r.no_rawat = ki.no_rawat
											INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
											INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
											WHERE " . $where_clause . " AND k.kd_kamar = '" . $conn->real_escape_string($kd_kamar) . "' AND r.kd_pj = '" . $conn->real_escape_string($kd_pj) . "'";
										
										$result = $conn->query($sql);
										if ($result) {
											$data_row = $result->fetch_assoc();
											$kamar_data[$kd_pj] = isset($data_row['jml']) ? (int)$data_row['jml'] : 0;
											$kamar_total += $kamar_data[$kd_pj];
										} else {
											$kamar_data[$kd_pj] = 0;
										}
									}

									// Only display kamar if has data
									if ($kamar_total > 0) {
										$row_num++;
										$total_overall += $kamar_total;
										?>
										<tr>
											<td style="text-align: center;"><?php echo $row_num; ?></td>
											<td><?php echo htmlspecialchars($kd_kamar); ?></td>
											<!-- <td><?php echo htmlspecialchars($nm_kamar); ?></td> -->
											<?php foreach($penjamin as $kd => $label){
												$val = isset($kamar_data[$kd]) ? $kamar_data[$kd] : 0;
												echo '<td style="text-align: center;">'.htmlspecialchars($val).'</td>';
											} ?>
											<td style="text-align: center; font-weight: bold;"><?php echo $kamar_total; ?></td>
										</tr>
										<?php
									}
								}
							}
						}
						
						// Display total row
						if ($total_overall > 0) {
							?>
							<tr style="background-color: #f8f9fa; font-weight: bold;">
								<td colspan="2" style="text-align: right;">TOTAL</td>
								<td></td>
								<?php 
								// Calculate totals per penjamin
								foreach ($penjamin as $kd_pj => $label) {
									$sql_total = "SELECT COUNT(DISTINCT r.no_rawat) as jml 
										FROM pasien p
										INNER JOIN reg_periksa r ON p.no_rkm_medis = r.no_rkm_medis
										INNER JOIN kamar_inap ki ON r.no_rawat = ki.no_rawat
										INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
										INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
										WHERE " . (empty($filter_bangsal) ? "1=1" : "b.kd_bangsal = '" . $conn->real_escape_string($filter_bangsal) . "'") . 
										" AND r.status_lanjut = 'Ranap'" .
										" AND ki.stts_pulang NOT IN ('Pindah Kamar', '-', '')" .
										" AND ki.stts_pulang IS NOT NULL" .
										" AND r.kd_pj = '" . $conn->real_escape_string($kd_pj) . "'";
									
									// Add date range
									if($filter_month && $filter_year){
										$start = sprintf('%04d-%02d-01',$filter_year,$filter_month);
										$end = date('Y-m-t', strtotime($start));
										$sql_total .= " AND r.tgl_registrasi BETWEEN '".$start."' AND '".$end."'";
									}

									// Add age condition
									if (!empty($age_condition)) {
										$sql_total .= " AND " . $age_condition;
									}

									$result_total = $conn->query($sql_total);
									if ($result_total) {
										$total_row = $result_total->fetch_assoc();
										$total_val = isset($total_row['jml']) ? (int)$total_row['jml'] : 0;
										echo '<td style="text-align: center; font-weight: bold;">'.htmlspecialchars($total_val).'</td>';
									}
								}
								?>
								<td style="text-align: center; font-weight: bold;"><?php echo $total_overall; ?></td>
							</tr>
							<?php
						}
					?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
	// Auto-submit filter form when any select changes
	$('#filterForm').on('change', 'select', function(){
		$('#filterForm').submit();
	});

	// Export to Excel
	$('#btnExport').on('click', function(){
		var formData = new FormData();
		formData.append('bangsal', $('#bangsal').val());
		formData.append('usia', $('#usia').val());
		formData.append('month', $('#month').val());
		formData.append('year', $('#year').val());
		formData.append('export', '1');

		$.ajax({
			type: 'POST',
			url: 'main_app.php?page=export_kunjungan_perkamar_usia_ranap',
			data: formData,
			processData: false,
			contentType: false,
			xhrFields: {
				responseType: 'blob'
			},
			success: function(data, status, xhr){
				var filename = 'Data_Kunjungan_PerKamar_' + new Date().toISOString().split('T')[0] + '.xlsx';
				var link = document.createElement('a');
				var url = URL.createObjectURL(data);
				link.href = url;
				link.download = filename;
				document.body.appendChild(link);
				link.click();
				document.body.removeChild(link);
				URL.revokeObjectURL(url);
			},
			error: function(){
				alert('Gagal export data');
			}
		});
	});
});
</script>

