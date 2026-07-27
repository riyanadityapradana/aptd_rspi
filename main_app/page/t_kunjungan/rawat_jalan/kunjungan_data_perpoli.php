<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/poli_specialty_helper.php';
?>
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
					$specialtyGroups = aptd_poli_specialty_mapping($mysqli);
					
					// Mapping jenis pembayar
					$penjamin = [
						'A09' => 'UMUM',
						'BPJ' => 'BPJS',
						'A92' => 'ASURANSI',
					];
					
					list($filter_month, $filter_year) = aptd_poli_specialty_period(
						isset($_POST['month']) ? $_POST['month'] : date('n'),
						isset($_POST['year']) ? $_POST['year'] : date('Y')
					);
					$summaryRows = aptd_poli_specialty_monthly_summary(
						$mysqli,
						$specialtyGroups,
						$filter_month,
						$filter_year,
						$penjamin
					);
					$grandTotals = array_fill_keys(array_keys($penjamin), 0);
					$grandTotal = 0;
					foreach ($summaryRows as $summaryRow) {
						foreach (array_keys($penjamin) as $payerCode) {
							$grandTotals[$payerCode] += isset($summaryRow['counts'][$payerCode])
								? (int) $summaryRow['counts'][$payerCode]
								: 0;
						}
						$grandTotal += (int) $summaryRow['total'];
					}
					?>
					<form id="filterForm" method="post" class="form-inline mb-2">
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
						<?php if (empty($summaryRows)): ?>
							<tr>
								<td colspan="6" style="text-align: center;">Tidak ada data kunjungan pada periode ini.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($summaryRows as $index => $summaryRow): ?>
								<tr>
									<td style="text-align: center;"><?php echo $index + 1; ?></td>
									<td><?php echo htmlspecialchars($summaryRow['nama_poli'], ENT_QUOTES, 'UTF-8'); ?></td>
									<?php foreach ($penjamin as $payerCode => $label): ?>
										<td style="text-align: center;">
											<?php echo isset($summaryRow['counts'][$payerCode]) ? (int) $summaryRow['counts'][$payerCode] : 0; ?>
										</td>
									<?php endforeach; ?>
									<td style="text-align: center; font-weight: bold;"><?php echo (int) $summaryRow['total']; ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
					<tfoot>
						<tr style="background-color:#f8f9fa;font-weight:bold;">
							<td colspan="2" style="text-align:right;">Grand Total</td>
							<?php foreach ($penjamin as $payerCode => $label): ?>
								<td style="text-align:center;"><?php echo (int) $grandTotals[$payerCode]; ?></td>
							<?php endforeach; ?>
							<td style="text-align:center;"><?php echo (int) $grandTotal; ?></td>
						</tr>
					</tfoot>
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
					var filename = 'Data_Kunjungan_PerPoli_' + new Date().toISOString().split('T')[0] + '.xlsx';
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

