# Backend pembayaran PhotoBooth

Laravel 12 / PHP 8.2. Sandbox tetap lingkungan default. Adapter produksi tersedia dengan pengaktifan eksplisit;
merchant pengguna masih menunggu aktivasi dan belum ada transaksi produksi dijalankan.

Panduan: [hosting dan produksi](docs/HOSTING.md) · [operasional iPhone/offline/pemulihan](docs/OPERATIONS.md).

## Setup sandbox lokal

1. `composer install` (pada mesin baru), salin `.env.example` ke `.env` bila belum ada, lalu `php artisan key:generate` untuk instalasi baru.
2. Buat database dan pengguna MySQL, lalu isi `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di `.env`. PHP memerlukan `pdo_mysql`. Template instalasi baru memakai MySQL; `.env` lokal yang sudah ada tidak berubah otomatis.
3. `python3 scripts/configure_sandbox.py`: masukkan Server Key sandbox secara privat.
   Token perangkat disimpan di `.local/device-token.txt`, hash SHA-256 di `.env`.
   Skrip selalu menetapkan lingkungan sandbox dan menonaktifkan produksi; token lama dipertahankan.
4. `php artisan config:clear` lalu `php artisan migrate`.
5. Jalankan server lokal; iPhone membutuhkan URL HTTPS yang dapat dijangkau. Hosting permanen memakai document root `public`.
6. Set Notification URL sandbox ke `https://HOST/api/midtrans/notifications`.
7. Admin iPhone → Koneksi pembayaran QRIS: URL `/api`, token perangkat, Sandbox. Simpan dan relaunch.
   Konfigurasi disimpan di Keychain. Scheme sandbox lama dapat menimpa konfigurasi Admin ketika Run dari Xcode.

Jangan kirim Server Key ke iPhone/chat/repo. Sandbox dibayar dengan [simulator Midtrans](https://docs.midtrans.com/docs/testing-payment-on-sandbox), bukan uang asli.

## API

Device routes memakai Bearer token. Satu hash token = satu scope perangkat. Jangan gunakan token sama untuk beberapa booth.

| Metode/rute (prefix /api) | Perilaku |
| --- | --- |
| GET /packages | Harga, jumlah foto dan salinan dari server |
| POST /orders | Snapshot order; Idempotency-Key = client_session_id; validasi expected_amount |
| POST /orders/{id}/payment | Idempotency-Key = UUID server; satu charge, ambigu hanya direkonsiliasi |
| GET /orders/{id}/payment-status | Verifikasi status langsung ke provider |
| GET /orders/{id}/qr | Proxy PNG, host lingkungan aktif saja, tanpa redirect |
| GET /orders-by-session/{clientID}/payment-status | Pemulihan baca-status berdasarkan ID lokal, scope perangkat; ditolak jika cetak pernah direservasi |
| POST /orders/{id}/fulfillment | Reservasi cetak atomik untuk paid saja; pemanggilan berikutnya ditolak agar respons hilang tidak menyebabkan cetak dua kali |
| POST /midtrans/notifications | Signature lalu query status provider; bukan Bearer |

Batas QR 2 menit sejak order_time yang dikirim ke Midtrans. Local countdown tidak menentukan pembayaran lunas.
ID, nominal IDR, tipe transaksi dan environment diverifikasi. Settlement tidak dapat diturunkan oleh notifikasi lama.
Lingkungan sandbox/production disimpan pada order; request lintas lingkungan ditolak.

`MIDTRANS_ENVIRONMENT=production` **dan** `MIDTRANS_PRODUCTION_ENABLED=true` diperlukan untuk produksi.
Ganti key dan konfigurasi iPhone hanya setelah akun diaktifkan. Gunakan deployment/database berbeda dari sandbox.

Pembayaran offline berjalan lokal pada satu iPhone: admin menerima uang, membuat kode terikat pesanan,
pelanggan menebus kode sekali. Transaksi tunai belum tersinkron ke backend. Jurnal iPhone menyimpan metadata,
pembayaran, reservasi cetak dan catatan petugas; foto tidak dipersistenkan untuk pemulihan setelah restart.
Pengembalian uang dan kasus cetak ambigu perlu ditangani petugas, tidak diproses otomatis oleh backend.

## Verifikasi

`php artisan test` memakai SQLite in-memory sesuai `phpunit.xml`; tes ini tidak memverifikasi koneksi atau perilaku MySQL. Verifikasi deployment MySQL dengan `php artisan migrate:status` dan alur sandbox pada database hosting. Provider difake, request yang tidak dimock diblokir. Mencakup autentikasi/scope,
harga, idempotensi charge, nominal, webhook, koneksi, expiry, produksi fail-closed dan reservasi fulfillment.
Lihat [VERIFICATION.md](docs/VERIFICATION.md) untuk hasil terbaru dan batas pengujian.

## Repository updates

Repository ini berisi backend Laravel saja. Aplikasi iOS berada di workspace terpisah.
Setelah pull di hosting, jalankan `composer install --no-dev --prefer-dist --optimize-autoloader`, `php artisan migrate --force`, lalu `php artisan config:cache`. Jangan menimpa `.env` atau database server.

## Petugas dan persediaan kode offline

Web `/petugas` memerlukan login; buat akun dengan `php artisan booth:staff` sesudah migrasi. 600 kode tetap (200 per paket) otomatis tersedia pada login pertama dan aplikasi iPhone terbaru. Tidak perlu impor. Status web dicatat manual oleh petugas; penebusan iPhone tetap offline. Harga: Biasa Rp15.000/1 lembar, Double Rp25.000/2 lembar, Triple Rp40.000/3 lembar, semuanya 6 foto. Gunakan satu iPhone booth aktif untuk stok ini. Pembaruan mempertahankan kode terpakai.
