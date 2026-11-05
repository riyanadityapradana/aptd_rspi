# Akses tambahan per user

Admin > CRUD User menyediakan Akses Tambahan pada form tambah dan edit.
Level tetap menentukan akses bawaan. Pilihan menu menambah akses tersebut,
termasuk ekspor dan detail terkait; mengosongkan pilihan mengembalikan user ke
akses bawaan level. Beranda dan seluruh aturan level yang lama tetap berlaku.
Admin selalu memiliki akses penuh. Pengelolaan user tidak dapat diberikan
melalui pilihan menu tambahan.

Pilihan disimpan sebagai daftar ID submenu dalam kolom nullable
`tb_users.akses_menu` (TEXT). NULL atau daftar kosong berarti tanpa tambahan.
Katalog berasal dari `config/menu.php`, yang juga digunakan oleh navbar.
Perubahan akses dan level dibaca pada permintaan berikutnya, sehingga tidak
memerlukan login ulang. Sesi akun yang dihapus tidak dapat membuka laporan.
Aturan tindakan input, edit claim, kalkulasi, dan import tetap mengikuti level;
pemberian akses membuka laporan tidak mengubah kewenangan tindakan tersebut.

Migrasi hanya melalui CLI, tidak dijalankan otomatis saat membuka halaman:

```text
C:\xampp\php\php.exe maintenance/user_access_schema.php --plan
C:\xampp\php\php.exe maintenance/user_access_schema.php --check
C:\xampp\php\php.exe maintenance/user_access_schema.php --apply
```

Migrasi menambah satu kolom NULL dan tidak mengubah isi maupun level akun lama.
Kolom dapat dibiarkan bila kode dikembalikan ke versi lama. Endpoint laporan
langsung diperiksa melalui koneksi aplikasi; helper yang bukan endpoint tidak
dapat dijadikan jalan masuk. Parameter mode detail/ekspor penyakit diperiksa
terhadap submenu yang sesuai.

```text
C:\xampp\php\php.exe tests/user_access_test.php
C:\xampp\php\php.exe tests/user_access_test.php --db
C:\xampp\php\php.exe tests/login_security_test.php
```

Mode --db menggunakan TEMPORARY TABLE tb_users pada koneksi pengujian, yang
menutupi tabel asli hanya pada koneksi tersebut. Tidak ada akun atau akses user
produksi yang diubah oleh tes. Tabel sementara otomatis hilang saat koneksi
ditutup.
