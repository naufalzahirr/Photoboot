# Memasang backend PhotoBooth di hosting

Status: berkas siap dipasang; belum dipublikasikan ke hosting pengguna. Akun Midtrans produksi masih menunggu aktivasi.
Gunakan subdomain khusus, misalnya `booth-api.domainanda.com`. Jangan mengirim kredensial hosting/Server Key melalui chat.

## Hosting dan database

Hosting perlu PHP >=8.2, Composer, MySQL, ekstensi PHP `pdo_mysql`, dan HTTPS valid.
Template `.env.example` memakai MySQL untuk instalasi baru.
Document root wajib folder `backend/public`; `.env`, database, `.local`, dan source backend tidak boleh dapat diunduh publik.
Direktori `storage` dan `bootstrap/cache` harus bisa ditulis pengguna PHP.
Lihat [panduan deployment Laravel 12](https://laravel.com/framework/docs/12.x/deployment).

Arsip sumber tersedia di `dist/photobooth-backend.zip` (buat ulang dengan `python3 scripts/package_backend.py`). Ekstrak di direktori aplikasi hosting, lalu pasang dependency dengan Composer. Arsip tidak berisi secret, database, atau vendor.

Upload isi `backend` tanpa `.env`, `.local`, database lokal, log, `vendor`, dan cache hasil pengembangan.
Jika hosting tidak memiliki Composer, bangun dependency di lingkungan yang kompatibel lalu upload `vendor` juga.
Jangan menimpa `.env`, `storage`, atau database server saat memperbarui aplikasi.

Dari terminal direktori aplikasi di hosting:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Perintah `cp`/`key:generate` hanya untuk instalasi baru. Simpan `APP_KEY` yang sudah ada untuk pembaruan.
Sebelum migrasi, buat database MySQL dan pengguna database melalui panel hosting.
Hubungkan pengguna tersebut ke database dengan hak akses untuk membuat/mengubah tabel dan membaca/menulis data.
Gunakan nama database dan pengguna lengkap dari panel (termasuk prefix akun jika ada).
`DB_HOST` dan `DB_PORT` harus mengikuti informasi penyedia hosting; gunakan InnoDB untuk mendukung transaksi dan penguncian baris.

Atur `.env` secara privat:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://booth-api.domainanda.com
MIDTRANS_ENVIRONMENT=sandbox
MIDTRANS_PRODUCTION_ENABLED=false
MIDTRANS_SERVER_KEY=ISI_KEY_SANDBOX_SECARA_PRIVAT
BOOTH_DEVICE_TOKEN_HASH=ISI_SHA256_TOKEN_PERANGKAT
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=NAMA_DATABASE
DB_USERNAME=USER_DATABASE
DB_PASSWORD="PASSWORD_DATABASE"
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

Gunakan nilai token/hash/key yang telah dikonfigurasi lokal secara privat jika ingin perangkat lama tetap terhubung.
Jangan unggah `.local/device-token.txt` ke area publik. Jangan mengganti token sembarangan saat ada pesanan belum selesai:
identitas perangkat backend bergantung pada hash token.
Untuk token baru, hasilkan acak minimal 32 karakter; hash SHA-256 disimpan backend, token asli di Keychain perangkat.
Satu token untuk satu booth. Konfigurasi saat ini melayani satu scope perangkat per deployment.

Kemudian jalankan:

```sh
php artisan config:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`php artisan migrate --force` membuat tabel aplikasi dalam database yang sudah dibuat; perintah ini tidak membuat database MySQL itu sendiri.
Untuk memeriksa koneksi dan status tabel, jalankan `php artisan migrate:status` setelah konfigurasi selesai.

Jika instalasi sebelumnya memakai SQLite, ubah `DB_CONNECTION` dan seluruh `DB_*` terkait di `.env` server secara manual.
Menyalin kode baru tidak mengubah `.env` yang sudah ada. Mengganti koneksi dan menjalankan migrasi hanya membuat skema;
data pesanan SQLite tidak otomatis dipindahkan ke MySQL. Simpan backup SQLite dan rencanakan pemindahan data tersendiri jika riwayat perlu dipertahankan.

Atur backup database rutin dan uji restore. Database sandbox dan produksi sebaiknya terpisah.
Migrasi dari Mac ke hosting tanpa membawa database akan membuat pesanan sandbox lama tidak ditemukan;
selesaikan kasus aktif dahulu, lalu buat pesanan baru setelah berpindah URL.

## Hubungkan iPhone dan webhook

1. Midtrans Sandbox → Payment Notification URL: `https://booth-api.domainanda.com/api/midtrans/notifications`.
2. iPhone Admin → Koneksi pembayaran QRIS: URL `https://booth-api.domainanda.com/api`, lingkungan Sandbox, token perangkat.
3. Simpan; tutup aplikasi dan buka dari ikon Home Screen. Jangan launch memakai scheme dengan URL tunnel lama.
4. Uji `/up` mengembalikan HTTP 200; `/api/packages` tanpa token harus 401; `/.env` tidak boleh terbuka.
5. Jalankan pembayaran simulator sampai foto tercetak dan pastikan webhook diterima. Matikan backend Mac untuk membuktikan hosting mandiri.

## Beralih ke produksi setelah Midtrans menyetujui akun

Aktivasi akun dan layanan Core API/QRIS harus selesai; lihat [persyaratan Core API Midtrans](https://docs.midtrans.com/docs/custom-interface-core-api).
Jangan mengaktifkan produksi hanya untuk menghilangkan tulisan Sandbox.

Pada deployment produksi, masukkan Server Key produksi secara privat dan atur:

```dotenv
MIDTRANS_ENVIRONMENT=production
MIDTRANS_PRODUCTION_ENABLED=true
```

Jalankan `php artisan config:cache`, pasang webhook di dashboard **Production**, lalu pilih **Produksi** di Admin iPhone.
Backend memakai `https://api.midtrans.com/v2`, sedangkan sandbox tetap memakai `https://api.sandbox.midtrans.com/v2`.
Lingkungan setiap order disimpan; order sandbox tidak dapat diproses sebagai order produksi.
QR proxy hanya menerima host Midtrans untuk lingkungan aktif. Referensi [QRIS Midtrans](https://docs.midtrans.com/reference/qris).

Lakukan satu transaksi nyata bernominal kecil yang Anda setujui, cek settlement di dashboard,
foto/cetak satu kali, dan cek jurnal. Belum ada transaksi uang asli yang dijalankan oleh perubahan ini.
Harga QRIS/salinan dikonfigurasi pada `config/photobooth.php`; sesudah mengubahnya jalankan config:cache dan muat ulang aplikasi.
Pertahankan 3 frame bawaan sampai aset tambahan siap dan hasil cetaknya diperiksa.

## Hal yang tidak dikerjakan otomatis

Pembelian hosting/domain, pemasangan SSL pada panel, aktivasi merchant, refund nyata,
perubahan harga produksi, dan unggah ke hosting tidak dijalankan dari workspace ini.
Nama penyedia/domain serta akses deployment belum tersedia pada sesi ini.
Tidak ada worker queue khusus diperlukan untuk alur sinkron saat ini.

## Web petugas untuk kode offline

Buka `https://photo.silap.smkn4tpi.sch.id/petugas` setelah kode terbaru diunggah.
Web ini hanya pencatatan petugas; iPhone menebus kode secara lokal tanpa internet. Status web tidak tersinkron otomatis.

Setelah memperbarui backend (jangan menimpa `.env` atau `APP_KEY`):

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan config:clear
php artisan migrate --force
php artisan booth:staff
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`booth:staff` meminta email dan password secara interaktif (minimal 12 karakter). Gunakan akun berbeda per petugas jika perlu; jalankan perintah lagi untuk membuat akun lain atau mengganti password akun yang sama.
Pastikan `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://photo.silap.smkn4tpi.sch.id`, dan `SESSION_SECURE_COOKIE=true` pada hosting HTTPS.
Tidak ada pendaftaran akun publik. Jangan mengirim password melalui chat atau memasukkannya ke GitHub.

1. Pasang build iPhone terbaru. Aplikasi otomatis memasang 600 kode tetap: 200 Biasa Rp15.000/1 lembar, 200 Double Rp25.000/2 lembar, 200 Triple Rp40.000/3 lembar. Semua paket enam foto.
2. Login `/petugas`. Daftar 600 kode yang identik otomatis dipasang pada kunjungan pertama, tanpa impor atau pembuatan batch manual.
3. Pilih paket dan kode tersedia → terima uang → **Sudah bayar · Bagikan**. Setelah pelanggan memakai kode di iPhone, petugas menekan **Tandai sudah dipakai**.

Pembaruan/restart tidak mengaktifkan kembali kode terpakai. Daftar lama tetap disimpan; 600 kode bawaan ditambahkan tanpa menghapus riwayat.
Status web bersifat manual. iPhone tidak memerlukan internet. Jangan uninstall/menghapus data iPhone: tindakan itu menghapus riwayat pemakaian lokal. Gunakan stok bersama ini pada satu iPhone booth aktif karena dua iPhone offline tidak dapat saling mencegah penebusan kode yang sama.
Daftar permanen berada pada konfigurasi backend di luar folder public dan konstanta aplikasi. Jangan regenerasi daftar saat deployment; keduanya harus tetap identik. Jangan publikasikan source daftar kode kepada pelanggan.

## QR download foto digital

Pembaruan backend dan build iPad diperlukan; backend lama tidak memiliki endpoint upload ini.
Arsip terbaru dibuat dengan `python3 scripts/package_backend.py`, tersedia di `dist/photobooth-backend.zip`.
Unggah source tanpa menimpa `.env`, `APP_KEY`, `storage`, atau database. Kemudian:

```sh
php artisan config:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- `APP_URL` harus URL HTTPS hosting yang sama dengan URL API di iPad. Token perangkat memakai konfigurasi yang sudah ada; fitur download tidak memerlukan Midtrans produksi.
- Endpoint `POST /api/photos` membutuhkan token perangkat dan menerima JPEG 1200 × 1800 maksimal 8 MiB (JSON base64). Atur `post_max_size` PHP minimal `16M`, `memory_limit` minimal `128M`, dan batas request web server minimal 16 MiB. Upload tidak memerlukan `storage:link`.
- Foto disimpan privat di `storage/app/private/photo-downloads`, bukan folder publik. Database hanya menyimpan ID pesanan/perangkat, checksum, dan masa berlaku; nama pelanggan tidak diunggah.
- `/foto/{id}` membutuhkan URL bertanda tangan yang dibuat backend. URL polos, perubahan parameter, dan tautan kedaluwarsa ditolak. Link berlaku 7 hari dan jangan dibagikan ke pihak lain selain pelanggan.
- Pasang cron hosting setiap menit (ganti path dan PHP sesuai hosting): `* * * * * cd /path/backend && php artisan schedule:run >> /dev/null 2>&1`. Scheduler menghapus file kedaluwarsa setiap hari. Bila cron belum tersedia, jalankan `php artisan booth:prune-photos` secara rutin; link tetap berhenti berlaku walau file belum dibersihkan.
- Perkiraan kapasitas: jumlah sesi per hari × ukuran JPEG × 7 hari, ditambah ruang cadangan dan backup hosting. Jangan mengganti `APP_KEY` karena tautan aktif bergantung pada key tersebut.

Uji di iPad: selesaikan sesi → Simpan dulu → tunggu QR → scan dari HP memakai jaringan seluler → download JPG. Putus internet iPad dan pastikan foto lokal masih ada, lalu coba upload dari Admin setelah internet pulih. Update ini belum diunggah otomatis ke hosting.


### Tombol QR hanya loading sebentar lalu muncul lagi

Ini menandakan upload belum berhasil; QR baru tersedia setelah hosting mengembalikan tautan valid. Build terbaru mempertahankan tombol/status dan menampilkan penyebab HTTP:

- **404/405**: periksa URL API (harus berakhir `/api`), unggah backend terbaru, jalankan migrasi dan bersihkan/bangun ulang route cache.
- **401/403**: token perangkat iPad tidak sesuai dengan hash token hosting.
- **413**: naikkan batas request web server/PHP agar menerima JSON minimal 16 MiB.
- **500/503**: periksa migrasi tabel `photo_downloads`, izin tulis storage, dan log Laravel melalui akses petugas hosting.
- Gangguan koneksi: periksa internet iPad; foto lokal tetap tersimpan untuk percobaan berikutnya.

Pemeriksaan baca-saja pada 20 September 2026: `/api/packages` merespons 401 tanpa token (endpoint aktif), sedangkan GET dan OPTIONS `/api/photos` merespons 404. Ini menunjukkan route upload belum tersedia pada deployment yang diperiksa. Sesudah update, `php artisan route:list --path=photos` harus menampilkan `POST api/photos`; GET pada endpoint POST biasanya membalas 405. Jangan menyalin token atau log sensitif ke chat.
