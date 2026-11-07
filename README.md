# Rasa POS

Ikon UI menggunakan Ionicons 8 yang dibundel bersama aset lokal aplikasi; tidak memuat ikon dari CDN.

POS web dan pemesanan menu pelanggan berbahasa Indonesia dengan tampilan Soft UI/Neumorphism. Menu web dan transaksi kasir tersimpan sebagai transaksi yang sama, sehingga pesanan pelanggan terlihat pada daftar kasir dan alur dapur.

## Menjalankan aplikasi

Prasyarat: PHP 8.2+, ekstensi PDO SQLite dan GD, Composer, dan Node.js/npm. Ekstensi GD digunakan untuk memproses dan mengompres unggahan logo/foto menu.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
php artisan migrate --seed
npm run build
php artisan serve
```

Buka `http://127.0.0.1:8000`. Database bawaan adalah SQLite (`database/database.sqlite`). Seeder hanya menambahkan menu contoh; tidak membuat akun atau mengimpor data pelanggan.

Saat pertama kali, buat akun pengelola satu kali melalui `/admin/pengaturan-awal`. Setelah masuk ke `/kasir`, buka shift dan atur kategori/menu. Buat satuan dan bahan, masukkan stok per batch, lalu tambahkan bahan dan jumlahnya ke resep menu yang perlu dilacak. Pemesanan pelanggan tersedia di halaman utama; pembayaran dicatat di kasir. Transaksi kasir dapat dibayar tunai atau transfer. Pesanan dapur bergerak dari antrean ke sedang dibuat lalu selesai.

Shift hanya dapat ditutup setelah semua pesanan yang belum dibayar pada shift tersebut dibayar atau dibatalkan. Shift tutup juga menghentikan penerimaan pesanan online sampai shift berikutnya dibuka.

## Cakupan saat ini

- Katalog menu publik, pencarian/filter kategori, keranjang yang dibuka melalui tombol, serta dialog konfirmasi rincian sebelum pesanan dikirim. Pelanggan dapat menambahkan menu tanpa login; pengiriman tetap menunggu shift kasir aktif.
- Login, pembuatan akun pengelola pertama yang dibatasi satu kali, pembukaan/penutupan shift, dan pencatatan transaksi.
- Peran pengelola dan kasir: pengelola mengatur katalog, persediaan, pembelian, pengeluaran, retur, laporan, dan akun; kasir menggunakan transaksi, shift, serta pesanan. Akun nonaktif ditolak dan setidaknya satu pengelola aktif harus dipertahankan.
- Promo menu berbasis jumlah minimum dengan sasaran semua menu/pelanggan atau pilihan tertentu; diskon terbaik per menu diterapkan dan kupon (bila ada) dihitung setelah promo. Nilai promo dan kupon disimpan terpisah sebagai snapshot transaksi.
- Menu dapat dihapus permanen apabila tidak terkait transaksi, konsumsi karyawan, atau paket. Resep, varian, foto, dan kaitan promo khusus ikut dihapus; promo khusus yang kehilangan semua menu tidak berlaku untuk menu mana pun. Menu lama yang sudah tersembunyi tidak otomatis ditampilkan kembali.
- Paket menu dengan harga paket yang divalidasi lebih rendah dari jumlah harga komponen. Stok bahan diambil dari gabungan resep menu komponennya dan resep transaksi tetap tersimpan sebagai snapshot.
- Barcode unik pada menu dan varian produk eceran dapat dipindai dari kasir menggunakan pemindai yang bertindak sebagai input keyboard; kode dinormalisasi dan divalidasi server sebelum disimpan.
- Pengaturan identitas toko (nama, alamat, nomor telepon, dan pesan footer) ditampilkan pada katalog serta kerangka aplikasi dan hanya dapat diubah pengelola.
- Pengelola dapat mengunggah foto menu serta logo toko (JPG, PNG, atau WebP, maksimal 5 MB); gambar dikodekan ulang ke format terkompresi dan dibatasi dimensinya sebelum disimpan. Warna utama/aksen/latar dan teks hero katalog pelanggan juga dapat disesuaikan dari pengaturan toko. Foto menu tampil pada katalog, kasir, dan logo pada navigasi serta struk; unggahan memerlukan ekstensi PHP GD aktif.
- Template label barcode untuk kertas lembar atau roll memvalidasi ukuran dan jarak cetak; pengelola dapat menyiapkan cetak menu dan varian produk ber-barcode, maksimal 500 label per pekerjaan. Pemindaian dan render label memakai Code 128; cetak fisik tetap mengikuti pengaturan printer/browser.
- Stok opname bahan menyimpan stok sistem, jumlah fisik, petugas, waktu, dan selisih per satuan; konfirmasi membuat mutasi yang dapat ditelusuri, menambah stok lebih pada batch penyesuaian, dan mengurangi stok kurang lewat FIFO tanpa membuat batch negatif. Riwayat yang telah disimpan tidak dapat diedit dari UI.
- Cycle count memungkinkan pengelola membuat tugas untuk bahan tertentu pada tanggal dan Shift 1 atau Shift 2. Petugas mengirim seluruh hitungan saat shift kasir dengan nomor yang sama aktif; stok sistem disnapshot saat pengiriman, sehingga usahakan penghitungan dan pengiriman berdekatan. Approval pengelola menerapkan selisih hasil hitung terhadap stok tersedia terbaru secara atomik, bukan menimpa transaksi yang terjadi setelah hitung. Bila stok hasil penyesuaian akan negatif atau melebihi batas, approval ditolak dan tugas dapat dikembalikan untuk hitung ulang. Penambahan stok hasil penyesuaian tidak memiliki HPP batch otomatis. Tugas belum berulang otomatis dan hanya mencakup bahan resep.
- Nomor shift dipilih saat kasir dibuka. Jam Shift 1/2 di pengaturan toko hanya informasi, termasuk bila melewati tengah malam; tidak membatasi waktu buka. Shift lama tetap disimpan tanpa nomor dan harus ditutup lalu dibuka kembali untuk mengerjakan tugas cycle count.
- Pengelola dapat mengatur akses setiap modul untuk setiap kasir melalui Pengguna; akses mencakup tindakan yang tersedia dalam modul tersebut, bukan hak baca-saja. Pengaturan toko, pengguna, pembuatan dan persetujuan tugas cycle count tetap khusus pengelola. Kasir yang sudah ada mempertahankan akses bawaan sampai pengaturan aksesnya disimpan.
- Audit metadata aksi perubahan POS (pengguna, waktu, rute, objek, metode, dan status HTTP) dengan pencarian/filter/ekspor CSV; payload formulir dan kata sandi tidak direkam.
- Kategori/menu, ketersediaan menu, pencatatan pembayaran, uang kembalian, serta alur dapur.
- Ruang kasir memakai navigasi modul kiri yang dapat diciutkan/diperluas; satu modul utama ditampilkan pada satu waktu. Tombol Buka kasir tersedia bagi akun karyawan/pengelola; kontrol buka/tutup shift berada di modul Kasir, sedangkan statistik berada di Ringkasan.
- Layar transaksi kasir menyembunyikan navigasi situs untuk fokus kerja; tombol Dashboard membuka kartu modul, sedangkan shortcut Pesanan menampilkan jumlah order menunggu yang diperbarui berkala.
- Ringkasan keranjang kasir hanya menampilkan produk, harga, qty, dan total. Tombol Proses transaksi membuka dialog identitas/jenis pesanan, metode pembayaran, pilihan uang pas atau nominal cepat Rp20.000/Rp50.000/Rp100.000, input manual, serta perhitungan kembalian atau kekurangan bayar.
- Penambahan menu memakai popup. Editor menu menampilkan resep dasar dan varian menu; resep varian menambahkan bahan di atas resep dasar, dengan harga tambahan, estimasi HPP, serta tabel jumlah dan HPP per bahan. Varian dipilih sebelum item masuk ke keranjang online maupun POS, dan pilihan/harga/resep bahan disimpan sebagai snapshot transaksi.
- Modul konsumsi karyawan memakai alur keranjang tanpa pembayaran, membatasi penerima ke akun kasir aktif, memotong bahan resep, dan mencatat HPP aktual sebagai biaya konsumsi terpisah dari penjualan dan kas shift.
- Ringkasan dapat difilter per bulan dan menampilkan 10 menu terlaris serta 10 menu dengan penjualan terendah, termasuk menu aktif yang belum terjual. Grafik harian membandingkan pemasukan transaksi lunas, pembayaran pengeluaran, dan target omzet. Target omzet harian memakai HPP bahan yang benar-benar terpakai (termasuk konsumsi karyawan) ditambah total tagihan pengeluaran operasional bulan tersebut dan cadangan 5%, lalu dibagi jumlah hari kalender pada bulan yang dipilih. Jika HPP bahan tidak lengkap, target dinyatakan belum dapat dihitung.
- Pengelola dapat memulai ulang periode Ringkasan dengan mengetik RESET dan menyetujui konfirmasi. Statistik, grafik, dan ekspor Ringkasan hanya mencakup periode terbaru, termasuk saat memilih bulan terdahulu. Pengeluaran dan pembayarannya harus dicatat setelah reset dan bertanggal pada atau setelah tanggal reset. Catatan transaksi, pengeluaran, stok, laporan, dan audit tidak dihapus; indikator pesanan aktif tetap mencakup semua pesanan yang belum selesai.
- Tombol Selesaikan semuanya pada Pesanan menandai seluruh pesanan dapur aktif di semua halaman sebagai selesai setelah konfirmasi; status pembayaran tetap seperti semula. Formulir fitur dan rincian pesanan dapat dibuka sesuai kebutuhan.
- Modul pengeluaran operasional mencakup kategori/subkategori, nominal, status belum dibayar/sebagian/lunas, dan pembayaran bertahap; dipakai sebagai biaya bulan berdasarkan tanggal tagihan, terpisah dari pembayaran kas shift.
- Form bahan mendukung supplier opsional, stok awal, total harga bahan, dan kalkulasi HPP per satuan; tabel stok menampilkan biaya satuan. Menu menunjukkan estimasi HPP dan margin dari biaya stok resep yang masih tersedia, dan menyatakan belum lengkap jika biaya bahan tidak diketahui.
- Input nominal memakai awalan Rp dan pemisah ribuan titik; nilai yang dikirim ke server tetap berupa angka.
- Sebelum transaksi POS disimpan, kasir meninjau item, diskon, charge, total, metode pembayaran, uang diterima, dan estimasi kembalian. Setelah transaksi tunai/transfer berhasil atau pesanan online dibayar, aplikasi membuka struk dan dialog cetak browser/perangkat. Bluetooth web tersedia sebagai upaya best-effort pada perangkat/browser BLE/GATT yang mendukung; Chrome pada iPhone tidak menyediakan Web Bluetooth. Dukungan ESC/POS pada printer VSC belum diverifikasi karena nomor model dan protokol belum diketahui, sehingga dialog cetak perangkat menjadi fallback.
- Meja makan dengan QR unik; pemindaian QR mengikat sesi pemesan pada meja dan ID meja dari formulir pelanggan tidak dipercaya.
- Profil pelanggan tersimpan dan dikenali berdasarkan nomor telepon yang dinormalisasi.
- Poin loyalitas tercatat sebagai buku mutasi: pengelola mengatur nominal belanja bersih per poin dan nilai tukarnya, tanpa tarif bawaan; poin didapat hanya setelah pembayaran lunas, dan penukaran saat ini hanya di POS. Retur terkonfirmasi (refund maupun klaim penggantian) mengoreksi perolehan secara proporsional; jika poin sudah dipakai, saldo boleh negatif dan dipulihkan oleh perolehan berikutnya.
- Kupon aktif dengan potongan persentase atau nominal. Nilai diskon dihitung ulang di server, dibatasi subtotal, dan tersimpan sebagai snapshot pada transaksi.
- Produk eceran dengan SKU, kategori, varian, satuan, harga/barcode, stok batch terpisah dari bahan resep, penerimaan dan stok opname. Stok tersedia mengecualikan batch kedaluwarsa; penjualan mengurangi FIFO dan mengambil HPP batch yang diketahui. Penerimaan manual opsional tetap tidak mencatat utang pemasok.
- Pemasok dan pembelian bahan resep atau produk eceran dengan harga/jumlah, diskon opsional, status menunggu-konfirmasi/menunggu-barang/diterima, cicilan tunai/transfer, serta riwayat pembelian. Satu dokumen mengikuti jenis pembelian legacy (bahan atau produk); stok bertambah satu kali saat barang diterima dan dicatat sebagai batch yang dapat dilacak.
- Retur pembelian bahan atau produk yang sudah diterima dengan batas jumlah kumulatif. Konfirmasi mengurangi batch asal tanpa mengizinkan stok negatif; refund pemasok tidak mengubah saldo utang, pengurangan utang menurunkan tagihan tanpa dicatat sebagai pembayaran kas, dan penggantian membuat batch stok masuk baru.
- Kategori dan subkategori pengeluaran, pencatatan biaya dengan nomor referensi/keterangan, riwayat filter tanggal/kategori/status, serta pembayaran bertahap tunai/transfer dengan sisa tagihan dan status terhitung server. Pengeluaran dicatat terpisah dari kas shift penjualan; nominal yang dicatat berkontribusi pada target omzet, sedangkan statistik kas keluar memakai pembayaran aktual.
- Retur penjualan untuk item transaksi lunas, dengan jumlah kumulatif dibatasi kuantitas terjual dan nilai refund dibagi proporsional setelah diskon transaksi. Refund menunggu konfirmasi; klaim penggantian non-tunai hanya dicatat, tidak membuat order/dapur baru dan tidak mengubah stok.
- Laporan operasional untuk penjualan lunas, pembelian dan saldo pemasok, arus pembayaran aktual, posisi stok bahan per batch, kartu mutasi bahan, serta jumlah dan nilai kotor item terjual. Refund penjualan/pemasok hanya dihitung setelah dikonfirmasi; pengurangan utang dan klaim penggantian tidak dihitung sebagai kas.
- HPP transaksi mengambil biaya per batch yang benar-benar dikonsumsi dan disimpan sebagai snapshot saat pembayaran. Laporan laba kotor membedakan transaksi dengan HPP lengkap/tidak lengkap dan menahan total laba periode jika ada transaksi yang belum lengkap; biaya operasional tidak dikurangkan. Penerimaan stok manual dapat mencatat biaya satuan secara opsional.
- Pengelola dapat mengatur charge PJK dan OPR dalam persen atau nominal tetap; keduanya diterapkan server-side pada pesanan kasir dan pelanggan berdasarkan subtotal setelah diskon. Tidak ada tarif bawaan, dan setiap kode/nama/tarif/nilai charge disalin ke transaksi agar perubahan setelan tidak mengubah tagihan lama. Laporan laba mengecualikan PJK dari pendapatan usaha, menganggap OPR sebagai pendapatan layanan, dan mengalokasikan refund di luar PJK secara proporsional.
- Pengelola dapat mendaftarkan rekening transfer tanpa mengisi rekening contoh/default. Kasir dapat memilih rekening tujuan saat mencatat transfer POS atau pembayaran pesanan pelanggan; laporan arus kas menunjukkan bank yang dipilih. Rekening yang pernah dipakai dipertahankan dan dapat dinonaktifkan tanpa menghapus referensi transaksi lama.
- Waktu pembayaran transaksi dicatat terpisah dari waktu order, sehingga pembayaran pesanan online yang terlambat masuk pada tanggal pembayaran yang benar di laporan arus kas.
- Pengelola dapat memilih jenis notifikasi baru untuk masing-masing peran; secara bawaan stok rendah hanya dikirim ke pengelola, sedangkan jenis lain ke kedua peran. Pilihan tidak menghapus riwayat. Stok bahan diperiksa ketika tab staf aktif melakukan pembaruan, sekitar setiap 20 detik, dan notifikasi dikirim sekali saat terdeteksi di bawah minimum; status kembali siap setelah stok terpantau pulih. Perubahan yang terjadi sepenuhnya di antara dua pemeriksaan mungkin tidak terdeteksi. Di lonceng, petugas dapat mengaktifkan atau mematikan suara untuk perangkat/browser masing-masing. Suara hanya untuk notifikasi baru yang diterima tab; browser mungkin meminta interaksi lagi setelah navigasi dan tidak dapat menjamin suara ketika tab ditutup, perangkat senyap, atau browser menangguhkan tab.
- Ekspor CSV untuk laporan operasional dengan seluruh hasil filter; sel teks diawali perlindungan formula dan berkas menggunakan BOM UTF-8.
- Ekspor CSV ringkasan bulanan pengelola memuat detail item transaksi lunas, pemasukan transaksi, pengeluaran operasional, pembayaran aktual per hari, target harian, dan persentase pencapaian.
- Persediaan bahan per batch, resep menu, tanggal kedaluwarsa, peringatan stok minimum, dan pemakaian FIFO saat pembayaran berhasil. Pesanan menyimpan snapshot resep; menu tanpa resep tidak menggunakan pelacakan stok. Pesanan online tidak mereservasi stok: ketersediaan diperiksa saat pemesanan dan sekali lagi saat pembayaran.
- Riwayat transaksi dengan pencarian invoice/pelanggan/menu dan filter pembayaran, dapur, serta tanggal.
- Nilai transaksi dihitung ulang dari harga menu di server; pembuatan pesanan dan perubahan status memakai transaksi basis data serta validasi.

Transaksi historis yang dibuat sebelum pencatatan biaya batch atau dari resep yang tidak lengkap akan tetap berstatus HPP belum lengkap; aplikasi tidak menebak biaya dari resep saat ini. Target omzet memakai HPP bahan yang keluar dari stok selama bulan terpilih, bukan nilai pembelian persediaan yang masih tersimpan. Charge PJK/OPR dan program poin belum aktif sebelum dikonfigurasi pengelola. Penukaran poin online belum tersedia sampai integrasi OTP dipilih dan dikonfigurasi. Ringkasan refund mengikuti tanggal retur, sedangkan baris transaksi laba kotor menampilkan pendapatan usaha/HPP saat pembayaran sebelum refund. Kupon belum memiliki batas pemakaian atau masa berlaku karena aturan tersebut tidak terbukti pada alur lama. Integrasi pembayaran online, multi-cabang, dan integrasi pengantaran tetap di luar cakupan.

## Pemeriksaan

Panduan persiapan InfinityFree, backup manual database dan foto/logo, serta uji pemulihan terisolasi tersedia di [docs/deployment/infinityfree.md](docs/deployment/infinityfree.md). Persiapan ini belum berarti deployment atau kompatibilitas produksi sudah diverifikasi.

```powershell
php artisan test
php vendor\bin\pint --test
npm run build
```
