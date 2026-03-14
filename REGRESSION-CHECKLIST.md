# CBT Exam Regression Checklist

Checklist ini dipakai setiap selesai mengubah flow berikut:
- builder exam
- tambah soal / edit soal / sync soal
- frontend attempt / resume / autosave
- CBT Result / review jawaban
- cache / revision refresh / runtime answer restore

## Data Uji Minimal

Siapkan 1 exam uji dengan kondisi berikut:
- minimal 30 soal agar mencakup lebih dari 1 question window
- campuran `multiple_choice` dan `multiple_answer`
- 1 akun siswa untuk attempt aktif
- siswa sudah menjawab soal tersebar di nomor awal, tengah, dan akhir
- contoh: jawab nomor 1-5, 11-15, 21-25, 28-30

## Catatan Hasil

Isi ringkas setiap run:
- tanggal:
- branch / commit:
- exam yang diuji:
- akun siswa:
- hasil: lulus / gagal
- catatan:

## Smoke Check

### 1. Start dan Resume Attempt

Langkah:
1. Login sebagai siswa.
2. Buka exam yang punya attempt aktif.
3. Resume attempt tanpa hard refresh.

Expected:
- nomor soal yang pernah dijawab langsung hijau
- badge jawaban di navigasi langsung muncul
- pilihan jawaban pada soal yang dibuka langsung terpilih
- tidak perlu `F5` atau `Ctrl + F5`

### 2. Navigasi Jawaban Lebih Dari 20 Soal

Langkah:
1. Pastikan siswa sudah menjawab soal di nomor `21+`.
2. Resume attempt.
3. Scroll navigasi sampai nomor `21+`.
4. Klik beberapa soal di rentang `21-30`.

Expected:
- nomor `21+` yang sudah dijawab tidak hanya hijau
- badge jawaban `A/B/...` juga muncul di navigasi
- pilihan pada panel soal langsung terisi
- tidak muncul kasus "badge hanya sampai nomor 20"

## High Risk Flows

### 3. Tambah Soal Saat Attempt Masih Berjalan

Langkah:
1. Dengan siswa masih punya attempt aktif, login admin.
2. Buka `CBT Exams`.
3. Tambahkan 1 soal baru ke exam yang sedang dikerjakan.
4. Kembali ke frontend siswa tanpa reload paksa.

Expected:
- frontend mendeteksi perubahan soal otomatis
- soal lama tetap punya jawaban dan badge navigasi
- soal baru bertambah di akhir / sesuai urutan baru
- soal baru default belum dijawab
- jawaban lama tidak hilang

### 4. Edit Soal Dari Preview Exam

Langkah:
1. Buka `Preview Soal Exam`.
2. Klik `Edit Soal`.
3. Ubah teks soal, simpan.
4. Cek kembali jumlah soal dan frontend siswa.

Expected:
- jumlah soal exam tidak berkurang
- soal tidak pindah keluar dari exam
- frontend tetap bisa membaca jawaban attempt lama
- result tetap menghitung total soal dengan benar

### 5. Sync/Revisi Soal Tanpa Hard Refresh

Langkah:
1. Dengan frontend siswa masih terbuka, lakukan perubahan soal di admin.
2. Tunggu heartbeat / revision refresh otomatis.
3. Cek nomor soal, badge jawaban, dan opsi terpilih.

Expected:
- status merah `berubah` boleh muncul jika memang ada revisi
- setelah reconcile, jawaban lama tetap menempel
- badge jawaban di navigasi tetap ada
- opsi radio/checkbox tetap sesuai

## Result Consistency

### 6. CBT Result Tetap Konsisten

Langkah:
1. Setelah tambah soal atau edit soal, buka `CBT Result`.
2. Bandingkan dengan frontend siswa.

Expected:
- jumlah soal sesuai versi attempt yang aktif
- soal lama yang sudah dijawab tetap muncul di review
- jawaban siswa tidak kosong untuk soal yang sebelumnya sudah terjawab
- progress answered/unanswered sesuai frontend

### 7. Admin Result Tetap Konsisten

Langkah:
1. Buka halaman hasil di admin.
2. Cek detail attempt yang sama.

Expected:
- jumlah soal sesuai
- review item tidak hilang
- jawaban siswa dan status benar/salah tetap terbaca

## UI Regression

### 8. Warna Navigasi Soal

Expected:
- `terjawab` = hijau
- `ragu-ragu` = kuning
- `belum dijawab` = normal
- soal aktif tetap biru
- soal `berubah` tetap mengikuti perilaku merah existing

### 9. Badge Ragu-ragu

Expected:
- chip header soal memakai `!`
- ukuran kecil dan tidak merusak layout
- toggle ragu-ragu tetap bekerja

### 10. Preview dan Back Navigation Admin

Expected:
- `Kembali ke Daftar Exam` benar-benar kembali ke tab daftar
- tombol `Edit Soal` dari preview membuka editor yang valid
- tidak muncul pesan `Sorry, you are not allowed to access this page`

## Release Gate

Sebelum anggap perubahan aman, semua poin berikut harus lolos:
- resume attempt tanpa hard refresh
- badge jawaban muncul untuk soal `1-10`, `11-20`, dan `21+`
- tambah soal tidak menghapus jawaban lama
- edit soal tidak mengurangi jumlah soal exam
- CBT Result dan admin result tetap konsisten
- tidak ada syntax error pada file yang diubah

## Recommended Fast Run

Kalau sedang buru-buru, minimal jalankan:
1. Resume attempt aktif tanpa refresh paksa.
2. Cek badge jawaban di nomor `1`, `12`, `24`, `30`.
3. Tambah 1 soal ke exam.
4. Cek lagi frontend siswa tanpa hard refresh.
5. Cek CBT Result.
