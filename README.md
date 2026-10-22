# AbsensiKu (PHP + MySQL)
1. Salin folder ini ke `htdocs/absensiku` (XAMPP) lalu jalankan Apache dan MySQL.
2. Import `database.sql` lewat phpMyAdmin (atau `mysql -u root < database.sql`).
3. Cek koneksi di `config.php`, lalu buka http://localhost/absensiku/
4. Login admin: `admin` / `password` (role Admin / HR). Karyawan: `andi`, `budi`, `siti`, `rudi`, `dewi` / `password`.
5. Admin: ubah koordinat di Lokasi Perusahaan (tombol "Pakai lokasi saya"), lalu daftarkan wajah di Registrasi Wajah.
Kamera dan GPS hanya jalan di localhost atau HTTPS. Model wajah dimuat dari CDN, jadi perlu internet.
