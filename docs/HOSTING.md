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
