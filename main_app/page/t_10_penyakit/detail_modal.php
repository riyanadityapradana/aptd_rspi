<style>
.penyakit-detail-link{display:inline-flex;min-width:34px;justify-content:center;padding:3px 8px;border-radius:8px;background:#eef6ff;color:#1f5fae;font-weight:700;text-decoration:none;cursor:pointer}
.penyakit-detail-link:hover{background:#dbeeff;color:#184b88;text-decoration:none}
#detailModalPenyakit10 .modal-header{background:#81a1c1;color:#fff}
#detailModalPenyakit10 .modal-header .close{color:#fff;text-shadow:none;opacity:.9}
#detailModalPenyakit10 table th,#detailModalPenyakit10 table td{font-size:12px;vertical-align:middle}
</style>
<div class="modal fade" id="detailModalPenyakit10" tabindex="-1" role="dialog" aria-labelledby="detailModalPenyakit10Label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalPenyakit10Label">Rincian Data Pasien</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detailModalPenyakit10Body">Loading...</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    if (window.__penyakit10DetailBound) {
        return;
    }
    window.__penyakit10DetailBound = true;

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.penyakit-detail-trigger');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        var mode = trigger.getAttribute('data-mode') || '';
        var kd = trigger.getAttribute('data-kd') || '';
        var penyakit = trigger.getAttribute('data-penyakit') || kd;
        var tglAwal = trigger.getAttribute('data-tgl-awal') || '';
        var tglAkhir = trigger.getAttribute('data-tgl-akhir') || '';
        var modalBody = document.getElementById('detailModalPenyakit10Body');
        var modalLabel = document.getElementById('detailModalPenyakit10Label');

        if (modalLabel) {
            modalLabel.textContent = 'Rincian ' + penyakit + ' (' + kd + ')';
        }
        if (modalBody) {
            modalBody.innerHTML = '<div class="text-center p-5">Loading...</div>';
        }
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery('#detailModalPenyakit10').modal('show');
        }

        var params = new URLSearchParams({
            mode: mode,
            kd_penyakit: kd,
            tgl_awal: tglAwal,
            tgl_akhir: tglAkhir
        });

        fetch('page/t_10_penyakit/detail_penyakit.php?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (response) {
                if (!modalBody) {
                    return;
                }
                if (response.status !== 'success') {
                    modalBody.innerHTML = '<div class="alert alert-danger">' + escapeHtml(response.message || 'Gagal mengambil data rincian.') + '</div>';
                    return;
                }
                if (!response.data || response.data.length === 0) {
                    modalBody.innerHTML = '<p class="text-center p-4">Tidak ada data rincian yang ditemukan.</p>';
                    return;
                }

                var html = '<p class="mb-2"><strong>Total Data Ditemukan: ' + response.data.length + '</strong></p>';
                html += '<div class="table-responsive"><table class="table table-sm table-bordered table-striped"><thead><tr>';
                Object.keys(response.data[0]).forEach(function (key) {
                    html += '<th>' + escapeHtml(key) + '</th>';
                });
                html += '</tr></thead><tbody>';
                response.data.forEach(function (row) {
                    html += '<tr>';
                    Object.keys(response.data[0]).forEach(function (key) {
                        html += '<td>' + escapeHtml(row[key]) + '</td>';
                    });
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                modalBody.innerHTML = html;
            })
            .catch(function (error) {
                if (modalBody) {
                    modalBody.innerHTML = '<div class="alert alert-danger">Gagal mengambil data rincian. ' + escapeHtml(error.message || '') + '</div>';
                }
            });
    });
})();
</script>
