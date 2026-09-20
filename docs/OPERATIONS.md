# Operasional PhotoBooth

## Persiapan perangkat

Jalankan build terbaru. Tekan logo MOMENT STUDIO selama 5 detik untuk Admin.
Jika belum pernah menyimpan PIN, buat PIN 6–8 angka. Tidak ada PIN bawaan untuk instalasi baru.
PIN yang sebelumnya disimpan tetap berlaku; ubah melalui Admin bila perlu.
Setelah lima PIN salah, akses dikunci 30 detik, termasuk setelah relaunch.

Admin tersedia pada Debug dan Release. Pilih Epson L5190, muat kertas 4R (4×6 inci),
jadikan ukuran/jenis kertas printer sesuai media, lalu lakukan satu test print.
Semua paket mengambil enam foto: Cetak Biasa Rp15.000/1 lembar, Double Rp25.000/2 lembar, Triple Rp40.000/3 lembar. Setiap lembar 4R berisi dua strip. Pembaruan harga mereset override paket lama sekali.
Atur countdown, retake, harga lokal dan salinan melalui Admin.
Tersedia 16 desain PNG dari arsip pengguna. Foto 1–3 berada di strip kiri, foto 4–6 di strip kanan. Potong vertikal di tengah setelah dicetak. Desain satu strip diulang di kedua sisi; proporsi asli dipertahankan dengan ruang putih bila perlu.
Stok kode yang dibuat untuk paket lama (3/4 foto atau jumlah lembar berbeda) tidak sesuai dengan paket baru. Siapkan batch baru untuk enam foto sebelum membuka booth.

## Persediaan kode pembayaran manual

QRIS bertanda **Belum tersedia** dan tidak dapat ditekan. Paket dan harga menggunakan pengaturan lokal Admin.

1. Pasang aplikasi terbaru pada satu iPhone booth. Stok 600 kode dipasang otomatis: 200 untuk setiap paket (Biasa/Double/Triple).
2. Login web `/petugas`. Stok identik tersedia otomatis; tidak perlu impor, ekspor, atau membuat batch.
3. Sesudah menerima uang, pilih kode sesuai paket → **Sudah bayar · Bagikan**. Berikan kode tersebut kepada pelanggan.
4. Pelanggan memilih paket → frame → Pembayaran manual → masukkan kode → Gunakan kode → Masuk ke sesi foto.
5. Petugas menandai **Sudah dipakai** di web secara manual. iPhone tetap offline.

Kode tidak kedaluwarsa sebelum ditebus; pemakaian sekali disimpan lokal secara atomik bersama pembayaran. Restart/pembaruan biasa mempertahankan riwayat. Lima kode salah mengunci penebusan selama 30 detik.
Jangan uninstall aplikasi atau menghapus datanya selama stok beredar. Satu stok bawaan untuk satu iPhone booth aktif; dua iPhone offline tidak berbagi status pemakaian. Pembaruan tidak menghapus batch/riwayat lama.
Daftar hanya ditampilkan kepada petugas yang login. iPhone dan printer tetap membutuhkan jaringan lokal untuk AirPrint, tanpa internet.

## Pemulihan pembayaran setelah aplikasi tertutup

Jangan uninstall aplikasi atau menghapus datanya. Buka Admin di Home → **Pemulihan transaksi**.
Cocokkan ID pesanan dan bukti pembayaran dengan pelanggan.

- **Belum pernah dikirim ke printer:** tekan **Periksa pembayaran & lanjutkan sesi**.
  QRIS memerlukan internet untuk verifikasi ulang ke backend/Midtrans; offline menggunakan jurnal pembayaran lokal.
  Tidak ada charge baru. Tutup Admin untuk melanjutkan. Foto diambil ulang karena foto mentah tidak disimpan permanen.
- **Pernah dikirim ke printer:** pemulihan foto otomatis diblokir. Periksa Pusat Cetak, printer,
  dan kertas yang sudah keluar dahulu. Jika hasil akhir masih tersedia dalam riwayat cetak (maksimal 15 menit pada proses aplikasi yang sama),
  gunakan reprint admin dengan alasan. Setelah restart, foto hasil akhir tidak dijamin tersedia.
- Jika foto diterima atau pengembalian uang sudah ditangani petugas, pilih **Tutup kasus setelah ditangani petugas**
  dan tulis catatan. Tombol ini hanya mencatat; tidak melakukan refund atau cetak otomatis.

Jurnal tersimpan di Application Support tanpa foto. Stok kode mentah disimpan di jurnal agar petugas dapat mengekspor ulang daftar melalui Admin; berkas memakai proteksi data iOS.
Reservasi cetak QRIS juga disimpan backend sebelum pengiriman. Respons yang hilang tidak boleh memicu pengiriman ulang otomatis.
Jurnal rusak atau gagal disimpan menahan operasi pembayaran/cetak untuk ditangani petugas.
Pemulihan terbatas perangkat yang sama; bukan pemulihan setelah uninstall, perpindahan perangkat, atau restore backup lama.
Token perangkat jangan digunakan pada dua booth. Arsip kasus tertutup dipertahankan lokal; UI menampilkan maksimal 50 kasus terbuka terbaru.

## QRIS yang tetap tersimpan di iPhone

Admin → **Koneksi pembayaran QRIS** → isi URL HTTPS berakhiran `/api`, token perangkat,
dan lingkungan Sandbox. Token bukan Server Key Midtrans. Simpan lalu tutup/buka ulang aplikasi.
Konfigurasi disimpan di Keychain dan tersedia saat aplikasi dibuka dari ikon Home Screen.
Scheme `PhotoBooth Sandbox` juga menyimpan konfigurasi saat diluncurkan dari Xcode;
setelah mengganti URL lewat Admin, gunakan scheme biasa agar URL lama tidak menimpanya.

QRIS tetap perlu internet dan backend aktif. Menyimpan konfigurasi tidak membuat QRIS menjadi offline.
Batas QR 2 menit sejak pembuatan pesanan. Status backend menentukan lunas/kedaluwarsa,
bukan hitung mundur lokal. Jangan bayar sandbox dengan uang asli.

## Uji penerimaan sebelum pelanggan memakai booth

- Offline: kode salah → ditolak; kode benar → foto/cetak; kode dipakai lagi → ditolak.
- Restart setelah kode ditebus sebelum foto: Admin dapat melanjutkan pesanan sama tanpa bayar.
- QRIS: kartu bertanda Belum tersedia tidak dapat ditekan dan tidak membuat pembayaran online.
- Restart setelah kirim cetak: tidak boleh otomatis cetak ulang; periksa antrean bersama petugas.
- Internet terputus: pembayaran manual tetap bekerja dari sesi baru.
- Paper out, printer mati, 4R, crop, jumlah salinan, dan penerimaan fisik diuji ulang dengan build terbaru.
- Debug dan Release harus dibangun dan diuji di perangkat. Build Release terbaru belum terverifikasi dalam sesi pengembangan ini.

Panduan hosting: [HOSTING.md](HOSTING.md). Aktivasi produksi menunggu persetujuan Midtrans.
