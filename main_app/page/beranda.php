<?php
$namaBeranda = isset($namaLogin) ? $namaLogin : 'Pengguna';
$levelBeranda = isset($levelLogin) ? $levelLogin : '-';
?>
<style>
    .beranda-shell {
        position: relative;
        padding: 18px 10px 70px;
    }

    .beranda-shell::before,
    .beranda-shell::after {
        content: "";
        position: absolute;
        border-radius: 999px;
        z-index: 0;
        pointer-events: none;
    }

    .beranda-shell::before {
        width: 260px;
        height: 260px;
        top: 10px;
        left: -70px;
        background: radial-gradient(circle, rgba(85, 170, 245, 0.26), rgba(85, 170, 245, 0));
    }

    .beranda-shell::after {
        width: 340px;
        height: 340px;
        right: -100px;
        bottom: 10px;
        background: radial-gradient(circle, rgba(255, 196, 92, 0.22), rgba(255, 196, 92, 0));
    }

    .beranda-layer {
        position: relative;
        z-index: 1;
    }

    .beranda-hero {
        overflow: hidden;
        border-radius: 28px;
        background: linear-gradient(135deg, rgba(255,255,255,0.94) 0%, rgba(231,245,255,0.94) 52%, rgba(200,230,255,0.96) 100%);
        box-shadow: 0 24px 60px rgba(37, 120, 191, 0.16);
        border: 1px solid rgba(255,255,255,0.75);
    }

    .beranda-copy {
        padding: 38px 34px;
    }

    .beranda-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 999px;
        background: rgba(255,255,255,0.85);
        color: #1b6eb3;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        box-shadow: 0 10px 20px rgba(37, 120, 191, 0.08);
    }

    .beranda-title {
        margin-top: 20px;
        margin-bottom: 14px;
        color: #1d3557;
        font-size: 44px;
        line-height: 1.08;
        font-weight: 800;
    }

    .beranda-title span {
        color: #0a84d6;
    }

    .beranda-subtitle {
        max-width: 680px;
        color: #45607b;
        font-size: 17px;
        line-height: 1.8;
        margin-bottom: 22px;
    }

    .beranda-user {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        border-radius: 18px;
        background: rgba(255,255,255,0.78);
        color: #1d3557;
        box-shadow: inset 0 0 0 1px rgba(130, 189, 240, 0.28);
        font-size: 14px;
    }

    .beranda-visual {
        min-height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 34px 22px;
        background: linear-gradient(160deg, rgba(71, 155, 224, 0.18), rgba(255,255,255,0.16));
    }

    .beranda-orbit {
        position: relative;
        width: 100%;
        max-width: 360px;
        aspect-ratio: 1 / 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .beranda-orbit::before,
    .beranda-orbit::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        border: 1px dashed rgba(29, 53, 87, 0.16);
    }

    .beranda-orbit::before {
        width: 100%;
        height: 100%;
    }

    .beranda-orbit::after {
        width: 74%;
        height: 74%;
    }

    .beranda-logo-wrap {
        position: relative;
        width: 210px;
        height: 210px;
        border-radius: 50%;
        background: radial-gradient(circle at top, #ffffff 0%, #edf8ff 60%, #dbefff 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 24px 45px rgba(19, 92, 150, 0.18);
    }

    .beranda-logo-wrap img {
        width: 112px;
        height: 112px;
        object-fit: contain;
    }

    .beranda-mini-card {
        position: absolute;
        padding: 14px 16px;
        min-width: 132px;
        border-radius: 18px;
        background: rgba(255,255,255,0.92);
        box-shadow: 0 16px 30px rgba(29, 53, 87, 0.12);
        color: #1d3557;
    }

    .beranda-mini-card strong {
        display: block;
        font-size: 22px;
        color: #0a84d6;
        line-height: 1.1;
    }

    .beranda-mini-card small {
        color: #5d7590;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .beranda-mini-top {
        top: 16px;
        right: -6px;
    }

    .beranda-mini-left {
        left: -14px;
        bottom: 42px;
    }

    .beranda-mini-bottom {
        right: 18px;
        bottom: -8px;
    }

    .beranda-grid {
        margin-top: 24px;
    }

    .beranda-card {
        height: 100%;
        padding: 22px 20px;
        border-radius: 22px;
        background: rgba(255,255,255,0.88);
        border: 1px solid rgba(255,255,255,0.75);
        box-shadow: 0 18px 38px rgba(29, 53, 87, 0.08);
    }

    .beranda-card .icon {
        width: 54px;
        height: 54px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        background: linear-gradient(135deg, #4aa3e4, #1d77c3);
        color: #fff;
        font-size: 22px;
        box-shadow: 0 14px 28px rgba(29, 119, 195, 0.24);
    }

    .beranda-card h4 {
        color: #1d3557;
        font-size: 20px;
        font-weight: 800;
        margin-bottom: 10px;
    }

    .beranda-card p {
        color: #58718b;
        line-height: 1.75;
        margin-bottom: 0;
        font-size: 14px;
    }

    .beranda-highlight {
        margin-top: 24px;
        padding: 22px 24px;
        border-radius: 24px;
        background: linear-gradient(130deg, rgba(16, 126, 203, 0.9), rgba(9, 83, 148, 0.92));
        color: #f6fbff;
        box-shadow: 0 24px 46px rgba(9, 83, 148, 0.22);
    }

    .beranda-highlight h5 {
        font-size: 22px;
        font-weight: 800;
        margin-bottom: 10px;
    }

    .beranda-highlight p {
        margin-bottom: 0;
        color: rgba(246, 251, 255, 0.9);
        line-height: 1.75;
    }

    @media (max-width: 991px) {
        .beranda-title {
            font-size: 34px;
        }

        .beranda-copy {
            padding: 30px 24px;
        }

        .beranda-mini-top,
        .beranda-mini-left,
        .beranda-mini-bottom {
            position: static;
            margin-top: 14px;
        }

        .beranda-orbit {
            display: block;
            text-align: center;
            max-width: 100%;
        }

        .beranda-logo-wrap {
            margin: 0 auto;
        }
    }

    @media (max-width: 575px) {
        .beranda-shell {
            padding: 8px 0 65px;
        }

        .beranda-title {
            font-size: 29px;
        }

        .beranda-subtitle {
            font-size: 15px;
        }

        .beranda-copy,
        .beranda-visual {
            padding: 24px 18px;
        }
    }
</style>

<section class="beranda-shell" id="home">
    <div class="beranda-layer">
        <div class="beranda-hero">
            <div class="row no-gutters align-items-center">
                <div class="col-lg-7">
                    <div class="beranda-copy">
                        <div class="beranda-badge">
                            <span class="glyphicon glyphicon-signal"></span>
                            Dashboard Utama RSPI
                        </div>
                        <h1 class="beranda-title">Satu pintu pemantauan <span>tarikan data layanan</span> rumah sakit.</h1>
                        <p class="beranda-subtitle">
                            Beranda ini dirancang sebagai titik awal untuk membaca kunjungan pasien, tren penyakit, dan laporan pelayanan secara lebih cepat, lebih rapi, dan lebih mudah dipahami oleh tiap pengguna.
                        </p>
                        <div class="beranda-user">
                            <span class="glyphicon glyphicon-user"></span>
                            <strong><?php echo htmlspecialchars($namaBeranda, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span style="opacity:.75;">masuk sebagai</span>
                            <strong><?php echo htmlspecialchars($levelBeranda, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="beranda-visual">
                        <div class="beranda-orbit">
                            <div class="beranda-logo-wrap">
                                <img src="../assets/assets-admin/img/logo1.png" alt="Logo RSPI">
                            </div>
                            <div class="beranda-mini-card beranda-mini-top">
                                <small>Monitoring</small>
                                <strong>24/7</strong>
                                <span>Tampilan laporan siap dibuka kapan saja.</span>
                            </div>
                            <div class="beranda-mini-card beranda-mini-left">
                                <small>Modul</small>
                                <strong>Rawat Jalan</strong>
                                <span>Kunjungan dan rekap tersedia terpusat.</span>
                            </div>
                            <div class="beranda-mini-card beranda-mini-bottom">
                                <small>Insight</small>
                                <strong>Penyakit</strong>
                                <span>Grafik dan tabel untuk analisis cepat.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row beranda-grid">
            <div class="col-md-4 mb-3">
                <div class="beranda-card">
                    <div class="icon"><span class="glyphicon glyphicon-list-alt"></span></div>
                    <h4>Kunjungan Pasien</h4>
                    <p>Gunakan menu kunjungan untuk melihat rekap rawat jalan, per poli, per kamar, dan kelompok usia dengan filter yang memudahkan penelusuran data pelayanan.</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="beranda-card">
                    <div class="icon"><span class="glyphicon glyphicon-plus-sign"></span></div>
                    <h4>Diagnosa & Penyakit</h4>
                    <p>Halaman penyakit membantu membaca 10 besar kasus layanan rawat jalan maupun rawat inap agar pengambilan keputusan klinis lebih terarah.</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="beranda-card">
                    <div class="icon"><span class="glyphicon glyphicon-stats"></span></div>
                    <h4>Pelaporan Cepat</h4>
                    <p>Setiap modul disusun agar data bisa dibaca lewat tabel maupun grafik, sehingga cocok untuk operasional harian maupun kebutuhan presentasi singkat.</p>
                </div>
            </div>
        </div>

        <div class="beranda-highlight">
            <h5>Selamat datang di Aplikasi Tarikan Data RSPI</h5>
            <p>Mulai dari menu di bagian atas untuk membuka laporan yang dibutuhkan. Jika hak akses berbeda antar pengguna, sistem hanya akan menampilkan menu yang relevan dengan level akun yang sedang aktif.</p>
        </div>
    </div>
</section>
