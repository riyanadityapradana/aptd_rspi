-- Rekomendasi saja. Jangan dijalankan langsung pada database produksi.
-- Periksa EXPLAIN, ukuran tabel, beban tulis, dan jadwalkan maintenance terlebih dahulu.

-- Membantu pencarian pasien BPJS pertama per tanggal, poli, dan dokter.
CREATE INDEX idx_reg_task4_evaluasi
    ON reg_periksa (tgl_registrasi, kd_pj, stts, kd_poli, kd_dokter, no_reg, no_rawat);

-- Primary key saat ini (no_rawat, taskid) sudah baik untuk join dari reg_periksa.
-- Index berikut opsional jika EXPLAIN menunjukkan pemindaian taskid yang besar.
CREATE INDEX idx_mobilejkn_task4_waktu
    ON referensi_mobilejkn_bpjs_taskid (taskid, waktu, no_rawat);

-- Membantu pemilihan jadwal berdasarkan dokter, poli, dan hari.
CREATE INDEX idx_jadwal_dokter_poli_hari
    ON jadwal (kd_dokter, kd_poli, hari_kerja, jam_mulai);

