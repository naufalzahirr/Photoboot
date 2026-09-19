# Operasional PhotoBooth

## Persiapan perangkat

Jalankan build terbaru. Tekan logo MOMENT STUDIO selama 5 detik untuk Admin.
Jika belum pernah menyimpan PIN, buat PIN 6–8 angka. Tidak ada PIN bawaan untuk instalasi baru.
PIN yang sebelumnya disimpan tetap berlaku; ubah melalui Admin bila perlu.
Setelah lima PIN salah, akses dikunci 30 detik, termasuk setelah relaunch.

Admin tersedia pada Debug dan Release. Pilih Epson L5190, muat kertas 4R (4×6 inci),
jadikan ukuran/jenis kertas printer sesuai media, lalu lakukan satu test print.
Salinan mengikuti paket (Basic 1, Double 2, Express 1 secara default).
Atur countdown, retake, harga lokal dan salinan melalui Admin; katalog QRIS mengikuti backend.
Tiga frame bawaan tetap tersedia. Frame desain tambahan belum dimasukkan karena aset belum disiapkan.

## Bayar offline ke petugas

1. Di layar paket pilih **Bayar ke petugas**, pilih paket dan frame.
2. Terima pembayaran sesuai total di layar. Ini pembayaran manual, bukan QRIS tanpa internet.
3. Tekan lama logo → masukkan PIN Admin → **Pembayaran offline**.
4. Tekan **Uang sudah diterima · Buat kode**, lalu konfirmasi.
5. Catat kode 8 karakter yang muncul, tutup Admin, berikan kepada pelanggan.
6. Pelanggan memasukkan kode dan menekan **Gunakan kode** → sesi foto → cetak.

Kode berlaku 15 menit, sekali pakai, hanya pada perangkat dan pesanan yang sama.
Kode disimpan sebagai hash; pembuatan kode baru membatalkan kode lama.
Lima percobaan salah mengunci penebusan selama 30 detik. Restart tidak menghapus kunci ini.
Tidak ada QRIS dibuat untuk pesanan offline, sehingga pembayaran manual tidak bersaing dengan QR aktif.
Jika aplikasi tertutup sebelum kode digunakan, pulihkan pesanan offline yang sama melalui Admin.
Kode yang masih berlaku dapat dipakai; jika sudah kedaluwarsa, petugas memeriksa bukti pembayaran sebelum membuat pengganti.
Jika internet gagal saat katalog dimuat, hanya pilihan petugas tersedia. Katalog terakhir dipakai;
pada perangkat tanpa cache, paket bawaan dipakai. Periksa nominal sebelum menerima uang.
Offline tidak mengirim data transaksi ke Midtrans atau hosting. Internet tidak diperlukan,
tetapi iPhone dan printer tetap memerlukan jaringan lokal untuk AirPrint.

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

Jurnal tersimpan di Application Support, hanya metadata tanpa foto/kode mentah.
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
- QRIS sandbox: simulasi bayar → force quit sebelum foto → Admin memverifikasi → lanjut.
- Restart setelah kirim cetak: tidak boleh otomatis cetak ulang; periksa antrean bersama petugas.
- Internet terputus: QRIS tidak dianggap lunas; pilihan offline bekerja dari sesi baru.
- Paper out, printer mati, 4R, crop, jumlah salinan, dan penerimaan fisik diuji ulang dengan build terbaru.
- Debug dan Release harus dibangun dan diuji di perangkat. Build Release terbaru belum terverifikasi dalam sesi pengembangan ini.

Panduan hosting: [HOSTING.md](HOSTING.md). Aktivasi produksi menunggu persetujuan Midtrans.
