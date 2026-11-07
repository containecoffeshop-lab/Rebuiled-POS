# Persiapan InfinityFree dan backup manual

Dokumen ini untuk persiapan, **bukan bukti aplikasi sudah kompatibel atau aman untuk produksi di InfinityFree**. Jangan masukkan transaksi nyata sampai instalasi staging, keamanan, transaksi database, unggahan, dan pemulihan backup diuji di akun hosting yang dipilih. Jangan mengunggah database lokal atau data pelanggan tanpa keputusan pemilik.

## Batasan dan pemeriksaan awal

Menurut [panduan InfinityFree untuk Laravel](https://forum.infinityfree.com/t/how-to-install-a-laravel-site-on-infinityfree/118578), paket gratis tidak menyediakan shell/Artisan, cron, worker antrean atau symlink, dan memiliki batas sumber daya serta jumlah berkas. Pastikan PHP hosting mendukung PHP 8.2+, ekstensi Laravel, PDO MySQL, dan GD termasuk dukungan format gambar yang diperlukan. Periksa kapasitas, batas unggahan, HTTPS, dan koneksi FTP terenkripsi yang benar-benar tersedia pada akun. Jika akses FTP tidak terenkripsi, jangan gunakan jaringan tidak tepercaya dan pertimbangkan hosting dengan SFTP.

Laravel harus melindungi `.env`, kode, database, log, dan backup dari akses publik. InfinityFree menyatakan document root tetap `htdocs`; panduan hosting tersebut mengusulkan aturan rewrite. **Jangan mengandalkan rewrite saja sebagai jaminan keamanan**: verifikasi pada staging bahwa `/.env`, `/storage/logs/laravel.log`, `/database/database.sqlite`, berkas aplikasi, backup, serta variasi URL yang relevan tidak dapat diakses atau diunduh. Jangan menggunakan hosting ini untuk data nyata jika pembatasan tidak dapat dibuktikan. Hanya foto menu dan logo di `public/uploads/menu` dan `public/uploads/store` yang memang publik; jangan taruh data privat di sana.

Siapkan dependensi PHP produksi dan aset Vite di salinan build terpisah di komputer; jangan unggah `node_modules`, file pengembangan, `.git`, database lokal, test, log, atau cache lokal. Konfirmasi seluruh dependensi benar-benar terunggah dan sesuai batas ukuran/inode hosting. Bangun untuk PHP dan ekstensi yang sama dengan server, lalu verifikasi konfigurasi produksi secara lokal. Jangan simpan `.env` produksi, password atau `APP_KEY` di Git atau percakapan. Gunakan `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, dan hostname MySQL yang diberikan penyedia. Laravel memerlukan direktori `storage` dan `bootstrap/cache` yang dapat ditulis; periksa izin seminimal mungkin.

Database lokal aplikasi saat ini SQLite, sedangkan InfinityFree memakai MySQL. **Dump SQLite tidak dapat langsung diimpor sebagai dump MySQL.** Buat dan uji skema MySQL menggunakan migrasi pada MySQL lokal/terisolasi dengan versi yang kompatibel, lalu ekspor skema tersebut ke phpMyAdmin staging. Jangan jalankan seed, impor atau migrasi di database berisi data tanpa backup dan rencana perubahan. Jika perlu memindahkan data yang sudah ada, konversi dan rekonsiliasi harus direncanakan serta diuji terpisah.

Unggahan aplikasi ini disimpan langsung di `public/uploads`, bukan di Laravel `storage/app/public`, sehingga tidak perlu `storage:link` untuk foto/logo. Uji pembuatan dan pembacaan foto serta logo, termasuk setelah deploy ulang. Uji pula login, transaksi paralel, pesanan, stok, shift, laporan, dan pemulihan pada MySQL staging. Pengujian SQLite tidak membuktikan perilaku MySQL sama. Karena tidak ada cron di paket gratis, backup terjadwal tidak tersedia tanpa layanan terpisah; panduan ini hanya manual.

## Backup manual MySQL dan foto/logo

1. Pilih waktu operasional berhenti; hentikan semua penjualan, pesanan online, perubahan stok, pengaturan, dan unggahan selama backup agar database dan gambar sesuai. Pastikan tidak ada permintaan yang masih berjalan.
2. Masuk ke phpMyAdmin menggunakan akses hosting pribadi. Pilih database POS yang benar, **Export/Ekspor**, format SQL, sertakan struktur dan data seluruh tabel termasuk `migrations`, kemudian unduh file. Jangan mencampurkan backup ini dengan database aplikasi lain.
3. Melalui FTP/file manager yang sesuai, unduh seluruh isi `public/uploads/menu` dan `public/uploads/store` dari instalasi yang sama. Pertahankan nama file dan struktur folder. Folder yang belum ada menandakan belum ada unggahan di lokasi tersebut. Verifikasi ukuran, jumlah file dan hasil transfer; jangan memasukkan log atau `.env` ke folder publik.
4. Catat tanggal, versi aplikasi/commit, nama database, dan pasangan arsip SQL serta foto. Simpan di lokasi privat terenkripsi dengan akses terbatas dan salinan di lokasi terpisah. Isi dump memuat data sensitif pelanggan dan transaksi; jangan kirim ke chat atau commit ke Git. Simpan `APP_KEY` dan kredensial secara terpisah di pengelola rahasia yang aman; `APP_KEY` perlu dipertahankan untuk membaca data terenkripsi jika ada.
5. Akhiri penghentian operasi hanya setelah kedua bagian berhasil diunduh. Lakukan backup secara rutin dan sebelum perubahan besar; tidak ada jaminan otomatis.

Untuk SQLite lokal, phpMyAdmin tidak berlaku. Hentikan aplikasi dan proses penulis, kemudian gunakan alat backup SQLite yang konsisten, atau salin database beserta file jurnal/WAL terkait setelah seluruh koneksi ditutup. Pasangkan hasilnya dengan `public/uploads/menu` dan `public/uploads/store` dari waktu yang sama. Jangan menimpa database kerja untuk mencoba pemulihan.

## Uji pemulihan aman

1. Siapkan **database MySQL kosong terpisah dan instalasi staging** dengan versi aplikasi yang sesuai dengan backup. Jangan impor ke database toko yang sedang berjalan; impor dapat menimpa data. Pastikan staging tidak terbuka untuk pelanggan umum dan tidak mengirim notifikasi eksternal.
2. Di phpMyAdmin pilih database staging kosong, lalu **Import/Impor** file SQL backup. Periksa bahwa impor selesai tanpa error dan seluruh tabel termasuk `migrations` tersedia. Jika ada batas ukuran impor, hentikan dan ikuti metode resmi penyedia; jangan memecah dump secara sembarangan.
3. Upload folder foto/logo yang berpasangan ke `public/uploads/menu` dan `public/uploads/store` pada instalasi staging yang sama. Gunakan konfigurasi dan `APP_KEY` yang aman serta sesuai kebutuhan backup, tanpa menyalinnya ke folder publik.
4. Bandingkan jumlah transaksi/stok dan beberapa invoice dengan catatan backup; periksa login, menu, foto, logo, laporan, serta alur transaksi dan stok dengan data uji. Jangan anggap backup layak dipakai bila ada impor gagal atau gambar hilang. Catat hasil, tanggal, versi, dan masalah yang ditemukan.
5. Pemulihan ke toko aktif adalah operasi terpisah yang menggantikan data setelah waktu backup. Lakukan hanya dengan persetujuan pemilik, jadwal penghentian operasional, dan backup terkini; jangan menggabungkan data berbeda secara manual.

Prosedur ini belum diuji pada akun InfinityFree tertentu. Ketersediaan FTP aman, keamanan aturan web server, kompatibilitas PHP/MySQL, dan hasil uji pemulihan harus diverifikasi sebelum go-live.
