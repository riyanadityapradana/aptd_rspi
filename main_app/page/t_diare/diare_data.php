     <div class="row text-left">
		<div class="col">
			<br><h3 class="text-lef" style="color: #666666">DATA PASIEN DIARE</h3>
			<hr style="height: 1px; background-image: linear-gradient(to right, rgba(0,0,0,0), rgba(102,102,102,1), rgba(0,0,0,0) )">
		</div>
	</div>
	<div class="row">
		<div class="col-sm-12" style="border-right: 1px solid #E5E5E5">
			<div class="panel-heading">
				<!-- <a href="?unit=pegawai&action=input" class="btn btn-outline-info btn-sm"><i class="fa fa-plus"></i>+ Tambah Data</a> -->
			</div>
			<div class="dataTables_wrapper table-responsive-sm" style="padding-top: 10px">
					<div class="wrapper">
						<?php
						// Read filter values from POST (defaults to Jan 2026)
						$filter_status = isset($_POST['status']) ? trim($_POST['status']) : 'all';
						$filter_month = isset($_POST['month']) ? intval($_POST['month']) : 1;
						$filter_year = isset($_POST['year']) ? intval($_POST['year']) : 2026;
						?>
						<form id="filterForm" method="post" class="form-inline mb-2">
							<div class="form-group mr-2">
								<label for="status">Status Pulang:&nbsp;</label>
								<select name="status" id="status" class="form-control form-control-sm ml-1">
									<option value="all">-- Semua --</option>
									<?php
									$statuses = ['Membaik','Rujuk','Meninggal','Dirawat','Atas Permintaan Sendiri','Atas Persetujuan Dokter','Pindah Kamar','Pulang Paksa','Sehat','Sembuh'];
									foreach($statuses as $s){
										$sel = ($filter_status===$s)?'selected':'';
										echo "<option value=\"".htmlspecialchars($s)."\" $sel>".htmlspecialchars($s)."</option>";
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
						<table class="table table-sm table-bordered table-hover" id="table4" style="width:100%">
						<thead class="thead-dark">
							<tr>
								<th style="text-align: center;">No.</th>
								<th>No RM</th>
								<th>Nama Pasien</th>
								<th>Jenis Kelamin</th>
								<th>NIK</th>
								<th>Tanggal Lahir</th>
								<th>Alamat</th>
								<th>No Rawat</th>
								<th>Tgl Registrasi</th>
								<th>Tgl Masuk</th>
								<th>Tgl Keluar</th>
								<th>Status Pulang</th>
								<th>Kamar/Bangsal</th>
								<th>Lama Rawat</th>
								<th>Diagnosa Awal</th>
								<th>Diagnosa Akhir</th>
							</tr>
						</thead>
						<body>
							<?php
								// Build dynamic query based on filters
								$filter_status = isset($_POST['status']) ? trim($_POST['status']) : 'all';
								$filter_month = isset($_POST['month']) ? intval($_POST['month']) : 1;
								$filter_year = isset($_POST['year']) ? intval($_POST['year']) : 2026;
								$whereParts = array();
								// date range from month+year (if provided)
								if($filter_month && $filter_year){
									$start = sprintf('%04d-%02d-01',$filter_year,$filter_month);
									$end = date('Y-m-t', strtotime($start));
									$whereParts[] = "rp.tgl_registrasi BETWEEN '".$start."' AND '".$end."'";
								} else {
									$whereParts[] = "rp.tgl_registrasi BETWEEN '2026-01-01' AND '2026-01-31'";
								}
								// diagnosis conditions
								$whereParts[] = "( LOWER(ki.diagnosa_awal) LIKE '%diare%' OR LOWER(ki.diagnosa_akhir) LIKE '%diare%' OR LOWER(ki.diagnosa_awal) LIKE '%gea%' OR LOWER(ki.diagnosa_akhir) LIKE '%gea%' OR LOWER(ki.diagnosa_awal) LIKE '%disentri%' OR LOWER(ki.diagnosa_akhir) LIKE '%disentri%')";
								// status filter
								if($filter_status !== 'all' && $filter_status !== ''){
									$status_esc = mysqli_real_escape_string($mysqli, $filter_status);
									$whereParts[] = "ki.stts_pulang = '".$status_esc."'";
								}
								$sql = "SELECT p.no_rkm_medis, p.nm_pasien, p.jk, p.no_ktp AS nik, p.tgl_lahir, p.alamat, rp.no_rawat, rp.tgl_registrasi, ki.tgl_masuk, ki.tgl_keluar, ki.stts_pulang, ki.lama, CONCAT(k.kd_kamar, ' ', b.nm_bangsal) AS kamar, ki.diagnosa_awal, ki.diagnosa_akhir FROM kamar_inap ki JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal";
								if(count($whereParts)>0){
									$sql .= ' WHERE '.implode(' AND ', $whereParts);
								}
								$sql .= ' ORDER BY rp.tgl_registrasi DESC';
								$query = mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli));
								$n=1;
								while ($row=mysqli_fetch_array($query)) {
								$no_rawat = $row['no_rawat'];
								$nn=$n++;
							?>
							<tr>
								<td width="30px"><?php echo $nn ?></td>
								<td><?php echo $row['no_rkm_medis'] ?></td>
								<td><?php echo $row['nm_pasien'] ?></td>
								<td><?php echo $row['jk'] ?></td>
								<td><?php echo $row['nik'] ?></td>
								<td><?php echo $row['tgl_lahir'] ?></td>
								<td><?php echo $row['alamat'] ?></td>
								<td><?php echo $row['no_rawat'] ?></td>
								<td><?php echo $row['tgl_registrasi'] ?></td>
								<td><?php echo $row['tgl_masuk'] ?></td>
								<td><?php echo $row['tgl_keluar'] ?></td>
								<td><?php echo $row['stts_pulang'] ?></td>							<td><?php echo $row['kamar'] ?></td>								<td><?php echo $row['lama'] ?></td>
								<td><?php echo $row['diagnosa_awal'] ?></td>
								<td><?php echo $row['diagnosa_akhir'] ?></td>
							</tr>
                                   <!-- Modal Hapus data -->
							<div id="mod_remove_<?=$row['no_rawat']?>" class="modal fade" role="dialog">
								<div class="modal-dialog modal-lg" align="center">
									<div class="modal-content">
										<div class="modal-body">
											<strong>Yakin ingin menghapus data <?php echo $row['nm_pasien'] ?> ?&nbsp;&nbsp;</strong>
											<a href="file_hapus/hapus_pasien.php?id=<?=$row['no_rawat']?>" class="btn btn-danger btn-sm" style="width: 60px">Ya</a>
											<button type="button" class="btn btn-success btn-sm" data-dismiss="modal" style="width: 60px">Batal</button>
										</div>
									</div>
								</div>
							</div>
							<!-- End Modal Hapus Data -->
							<?php
								}
							?>
						</body>
					</table>
				</div>
			</div>
		</div>
		<!-- modal detail -->
		<div class="modal fade" id="mydetail" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
			<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
				<div class="modal-content" id="detail_pasien">
				</div>
			</div>
		</div>
		<!-- akhir modal -->

		<!-- Modal Detail Pasien -->
		<div class="modal fade" id="modalDetailPasien" tabindex="-1" role="dialog" aria-labelledby="modalDetailPasienLabel">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="modalDetailPasienLabel">Detail Data Pasien</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="form-group">
							<label for="detail_nm_pasien">Nama Lengkap</label>
							<input type="text" class="form-control" id="detail_nm_pasien" readonly>
						</div>
						<div class="form-group">
							<label for="detail_nik">NIK</label>
							<input type="text" class="form-control" id="detail_nik" readonly>
						</div>
						<div class="form-group">
							<label for="detail_tgl_registrasi">Tanggal Registrasi</label>
							<input type="text" class="form-control" id="detail_tgl_registrasi" readonly>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
					</div>
				</div>
			</div>
		</div>
		<!-- Akhir Modal Detail Pasien -->
		<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
		<script>
		$(document).ready(function(){
			$('.btn-detail-pasien').on('click', function(){
				var no_rawat = $(this).data('no_rawat');
				var nm_pasien = $(this).data('nm_pasien');
				var nik = $(this).data('nik');
				var tgl_registrasi = $(this).data('tgl_registrasi');
				$('#detail_nm_pasien').val(nm_pasien);
				$('#detail_nik').val(nik);
				$('#detail_tgl_registrasi').val(tgl_registrasi);
			});

			// Auto-submit filter form when any select changes
			$('#filterForm').on('change', 'select', function(){
				$('#filterForm').submit();
			});

			// Export to Excel
			$('#btnExport').on('click', function(){
				var status = $('#status').val();
				var month = $('#month').val();
				var year = $('#year').val();
				
				// Create form and submit
				var form = $('<form method="POST" action="page/t_diare/export_diare.php"></form>');
				form.append($('<input type="hidden" name="status" value="' + status + '">'));
				form.append($('<input type="hidden" name="month" value="' + month + '">'));
				form.append($('<input type="hidden" name="year" value="' + year + '">'));
				$('body').append(form);
				form.submit();
				form.remove();
			});
		});
		</script>
	</div>