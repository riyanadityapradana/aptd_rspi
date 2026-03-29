<?php require_once('../config/koneksi.php'); ?>
<br>
<div class="row text-left">
	<div class="col">
		<h3 class="text-lef" style="color: #666666; margin-bottom: 5px;">DATA KUNJUNGAN PASIEN</h3>
		<hr style="height: 1px; background-image: linear-gradient(to right, rgba(0,0,0,0), rgba(102,102,102,1), rgba(0,0,0,0) ); margin-top: 0; margin-bottom: 10px;">
	</div>
</div>
<div class="row">
	<div class="col-sm-12" style="border-right: 1px solid #E5E5E5">
		<div class="dataTables_wrapper table-responsive-sm" style="padding-top: 0;">
				<div class="wrapper">
					<?php
					// Comprehensive poli mapping - mengelompokkan semua kode poli menjadi nama poli
					$mapping_poli = [
						'GIGI' => ['U0042', 'U0043', 'U0052', 'U0057', 'U0065'],
						'BEDAH' => ['U0015', 'U0065', 'U0064', 'U0054', 'U0070'],
						'ANAK' => ['U0068', 'U0069', 'U0067'],
						'THT' => ['U0011'],
						'PENYAKIT DALAM' => ['U0036', 'U0037', 'U0063', 'U0040', 'U0038', 'U0039'],
						'PARU' => ['U0019'],
						'SARAF' => ['U0049', 'U0050'],
						'MATA' => ['U0005', 'U0061'],
						'KANDUNGAN' => ['U0010', 'U0024', 'U0028', 'U0044', 'U0045', 'U0046', 'U0047', 'U0048', 'U0051', 'U0059', 'U0060', 'U0075', 'U0076'],
						'REHABILITASI MEDIK' => ['kfr'],
						//'JANTUNG' => ['U0012', 'U0032'],
						'JIWA' => ['U0018'],
						'ORTHOPEDI' => ['U0014', 'U0016'],
						'VAKSIN' => ['U0053'],
						'MCU' => ['U0071'],
						'HEMODIALISA' => ['U0023'],
						'IGD' => ['IGDK', 'U0009', 'U0013'],
						'REHAB MEDIK' => ['kfr'],
					];
					
					// Mapping jenis pembayar
					$penjamin = [
						'A09' => 'UMUM',
						'BPJ' => 'BPJS',
						'A92' => 'ASURANSI',
					];
					
					// Read filter values from POST
					$filter_poli = isset($_POST['poli']) ? trim($_POST['poli']) : 'PENYAKIT DALAM';
					$filter_month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
					$filter_year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

					// Special handling: if poli VAKSIN is selected, replace BPJS with Pancar Tour (kd_pj = A96)
					if(strtoupper($filter_poli) === 'VAKSIN'){
						$penjamin = [
							'A09' => 'UMUM',
							'A96' => 'Pancar Tour',
							'A92' => 'ASURANSI',
						];
					}
					?>
					<form id="filterForm" method="post" class="form-inline mb-2">
						<div class="form-group mr-2">
							<label for="poli">Poliklinik:&nbsp;</label>
							<select name="poli" id="poli" class="form-control form-control-sm ml-1">
								<?php
								foreach($mapping_poli as $poli_name => $codes){
									$sel = ($filter_poli === $poli_name) ? 'selected' : '';
									echo "<option value=\"".htmlspecialchars($poli_name)."\" $sel>".htmlspecialchars($poli_name)."</option>";
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
					<table class="table table-sm table-bordered table-hover" id="table4" style="width:100%;margin-top: 10px;">
					<thead class="thead-dark">
						<tr>
							<th style="text-align: center;">No.</th>
							<th>Poliklinik</th>
							<?php foreach($penjamin as $kd => $label){ echo "<th>".htmlspecialchars($label)."</th>"; } ?>
							<th>Jumlah Total</th>
						</tr>
					</thead>
					<tbody>
						<?php
							// Get poli codes for selected poli group
							$poli_codes = isset($mapping_poli[$filter_poli]) ? $mapping_poli[$filter_poli] : [];
							
							$data = [];
							
							// Query data for each jenis bayar
							if(!empty($poli_codes)){
								$poli_codes_str = "'" . implode("','", array_map(function($v){ return mysqli_real_escape_string($GLOBALS['mysqli'], $v); }, $poli_codes)) . "'";
								
								$whereParts = [
									"rp.kd_poli IN (".$poli_codes_str.")",
									"rp.stts = 'Sudah'",
									"rp.status_bayar = 'Sudah Bayar'",
									"rp.no_rkm_medis NOT IN (SELECT no_rkm_medis FROM pasien WHERE LOWER(nm_pasien) LIKE '%test%')"
								];
								
								// Date range from month+year
								if($filter_month && $filter_year){
									$start = sprintf('%04d-%02d-01',$filter_year,$filter_month);
									$end = date('Y-m-t', strtotime($start));
									$whereParts[] = "rp.tgl_registrasi BETWEEN '".$start."' AND '".$end."'";
								}
								
								// Get data for each payment type
								foreach($penjamin as $kd_pj => $label){
									$sql = "SELECT COUNT(*) as jml FROM reg_periksa rp WHERE rp.kd_pj = '".$kd_pj."' AND " . implode(' AND ', $whereParts);
									$result = mysqli_query($mysqli, $sql);
									if($result){
										$row = mysqli_fetch_assoc($result);
										$data[$kd_pj] = isset($row['jml']) ? (int)$row['jml'] : 0;
									} else {
										$data[$kd_pj] = 0;
									}
								}
								
								// Calculate total
								$total = array_sum($data);
							} else {
								$data = array_fill_keys(array_keys($penjamin), 0);
								$total = 0;
							}
						?>
						<tr>
							<td style="text-align: center;">1</td>
							<td><?php echo htmlspecialchars($filter_poli); ?></td>
							<?php foreach($penjamin as $kd => $label){
								$val = isset($data[$kd]) ? $data[$kd] : 0;
								echo '<td style="text-align: center;">'.htmlspecialchars($val).'</td>';
							} ?>
							<td style="text-align: center; font-weight: bold;"><?php echo $total; ?></td>
						</tr>
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
			formData.append('poli', $('#poli').val());
			formData.append('month', $('#month').val());
			formData.append('year', $('#year').val());
			formData.append('export', '1');

			$.ajax({
				type: 'POST',
				url: 'main_app.php?page=export_kunjungan',
				data: formData,
				processData: false,
				contentType: false,
				xhrFields: {
					responseType: 'blob'
				},
				success: function(data, status, xhr){
					var filename = 'Data_Kunjungan_' + new Date().toISOString().split('T')[0] + '.xlsx';
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
</div>

