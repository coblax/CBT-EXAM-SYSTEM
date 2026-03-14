# Bug Notes: Add/Remove Question with Active Attempt

Tanggal catatan: 2026-03-14

Dokumen ini dibuat sebagai pegangan saat bug terkait `tambah soal`, `kurangi soal`, `history soal nonaktif`, dan `sinkronisasi jawaban` muncul lagi.

## Ruang Masalah

Bug ini muncul saat:
- exam sudah punya `attempt` aktif
- admin menambah soal ke exam
- admin mengurangi soal dari exam
- frontend siswa masih terbuka atau resume ulang attempt yang sama

Area yang terdampak:
- navigasi soal di frontend
- badge jawaban `A/B/...`
- pilihan jawaban yang tercentang
- panel `History Soal Nonaktif`
- `CBT Result`
- admin result / review jawaban

## Gejala yang Pernah Muncul

- Jawaban lama hilang di frontend, tetapi masih terlihat di `CBT Result`.
- Semua nomor soal menjadi merah setelah revisi soal.
- Nomor soal hijau, tetapi pilihan jawaban belum muncul sampai user klik-klik soal atau `Ctrl + F5`.
- Badge jawaban hanya muncul sampai nomor 20, sementara nomor 21 ke atas hanya hijau.
- Setelah kurangi soal, nomor yang sudah nonaktif masih ikut tampil di navigasi utama.
- Setelah nomor nonaktif disembunyikan, nomor aktif malah di-renumber ulang sehingga membingungkan peserta.
- Setelah tambah soal, seluruh soal aktif bisa terlihat seperti gelombang baru, misalnya dari 20 soal menjadi tampil `21-45`.
- `History Soal Nonaktif` sempat hilang lagi saat urutan soal attempt dibaca dari sumber yang berbeda.

## Akar Masalah yang Ditemukan

### 1. `attempt.question_order` punya lebih dari satu sumber

Sumber yang terlibat:
- `cbt_attempts.question_order` di database
- runtime Redis di `CBT_Runtime`
- cache frontend browser

Kalau tiga sumber ini tidak sinkron, gejalanya bisa acak:
- history nonaktif hilang
- navigasi masih membawa soal yang sudah nonaktif
- nomor soal melompat
- jawaban lama tampak hilang padahal data jawaban masih ada

### 2. Frontend memisahkan status answered dan detail jawaban

Status hijau bisa muncul hanya dari `answered_question_ids`, tetapi detail badge `A/B/...` dan opsi terpilih butuh payload jawaban yang lebih lengkap.

Kalau restore cache/refresh revisi tidak lengkap:
- nomor jadi hijau
- tapi opsi tetap kosong
- badge jawaban tidak muncul

### 3. Save exam saat ada attempt history tidak boleh memperlakukan soal lama sebagai soal baru

Saat exam sudah punya attempt:
- soal lama aktif harus dipakai ulang atau di-link ulang jika memang masih mewakili source yang sama
- soal lama tidak boleh di-overwrite penuh
- soal lama tidak boleh gampang di-clone massal

Kalau logic matching gagal:
- semua soal aktif bisa dianggap baru
- nomor aktif langsung lompat, contoh `21-45`
- history dan numbering jadi rusak

### 4. Review helper sempat terlalu patuh pada snapshot lama

Jika helper hanya mengikuti snapshot lama dan tidak menambahkan soal baru yang belum ada di order lama:
- result/review bisa kehilangan soal baru
- atau sebaliknya tetap membawa soal lama yang seharusnya hanya ada di history

## Aturan Canonical yang Harus Dipertahankan

Ini aturan yang saat ini dianggap benar dan jangan diubah tanpa cek regresi:

1. `cbt_attempts.question_order` di database adalah snapshot historis canonical.
2. Runtime Redis hanya mirror atau fallback, bukan sumber utama yang boleh mengganti histori.
3. Navigasi utama frontend hanya menampilkan soal aktif hasil reconcile.
4. `History Soal Nonaktif` berasal dari snapshot canonical dikurangi soal aktif yang sekarang masih tampil.
5. Nomor soal yang ditampilkan ke peserta harus mengikuti nomor asli snapshot attempt, bukan `index + 1` dari list aktif saat ini.
6. Saat tambah soal pada exam yang sudah punya attempt, soal baru di-append ke snapshot canonical, bukan mengganti nomor lama.
7. Saat kurangi soal, soal yang punya jawaban/history di-archive (`is_active = 0`), bukan dihapus membabi buta.
8. Saat exam punya attempt history, save builder harus mencoba match ke soal aktif lama sebelum membuat clone baru.
9. Restore frontend tidak boleh lebih percaya pada cache lama dibanding payload order dari server untuk revisi yang sama.

## Hotspot File dan Function

### Backend REST

File: `includes/class-cbt-rest.php`

Function penting:
- `resolve_attempt_question_payload()`
- `reconcile_in_progress_question_order()`
- `merge_attempt_question_order_ids()`
- `build_attempt_review_items()`
- `order_questions_by_attempt_sequence()`
- `append_missing_attempt_questions()`
- `append_missing_attempt_review_questions()`

Peran:
- menentukan soal aktif yang tampil di frontend
- menjaga history soal nonaktif tetap ikut di snapshot
- menentukan numbering soal
- menentukan review item/result

### Admin Save Builder

File: `admin/class-cbt-admin.php`

Function penting:
- `run_exam_save_sync_batch()`
- `save_exam_with_source_questions()`
- `link_existing_exam_question_to_source()`
- `apply_source_snapshot_to_question()`
- `archive_or_delete_exam_questions()`
- `exam_has_attempt_records()`

Peran:
- sync bank soal ke exam
- memilih kapan pakai ulang soal lama
- memilih kapan buat soal baru
- memilih kapan archive vs delete

### Frontend

File: `public/js/cbt-frontend.js`

Function penting:
- `mergeQuestionCacheSnapshots()`
- `choosePreferredQuestionOrderSnapshot()`
- `applyQuestionsResponse()`
- `refreshAttemptQuestionRevision()`
- `renderArchivedReviewHistorySection()`
- `getQuestionDisplayNumber()`

Peran:
- restore cache browser
- menerima urutan soal dari server
- menampilkan nomor soal stabil
- menampilkan history nonaktif

## Pola Debugging Saat Bug Muncul Lagi

Urutan cek yang paling aman:

1. Cek apakah admin save membuat clone baru untuk soal lama.
2. Cek apakah `cbt_attempts.question_order` masih berisi snapshot historis yang benar.
3. Cek apakah runtime Redis menyimpan order yang lebih pendek atau berbeda.
4. Cek apakah endpoint `questions` mengirim:
   - `question_order_ids` aktif yang benar
   - `archived_review_items` yang benar
5. Cek apakah frontend memakai `question_order_ids` baru atau malah memulihkan cache lama.
6. Cek apakah nomor yang tampil berasal dari `question_number`, bukan `index + 1`.

## Pertanyaan Diagnosis yang Paling Berguna

Kalau bug muncul lagi, jawab dulu pertanyaan ini:

1. Apakah bug muncul setelah `tambah soal`, `kurangi soal`, atau keduanya?
2. Apakah jawaban hilang di frontend saja, atau di result/admin result juga?
3. Apakah nomor aktif berubah total?
4. Apakah `History Soal Nonaktif` hilang, kosong, atau hanya tidak sinkron?
5. Apakah gejala hilang setelah reload biasa, hard refresh, atau tetap ada?
6. Apakah exam yang sama sudah pernah telanjur mengalami clone massal sebelumnya?

## Gejala -> Dugaan Cepat

Kalau gejalanya seperti ini, biasanya penyebab awalnya:

- Navigasi hijau tapi opsi kosong:
  frontend restore/cache tidak lengkap

- Semua nomor merah:
  revision reconcile berjalan, tetapi manifest lama dan baru sedang bentrok

- Nomor aktif lompat jadi `21-45` setelah tambah 5 soal:
  save builder gagal match soal aktif lama dan membuat clone massal

- `History Soal Nonaktif` hilang setelah tambah soal:
  snapshot order canonical kalah oleh order runtime yang lebih pendek

- Soal nonaktif masih ikut di navigasi utama:
  `question_order_ids` aktif yang dikirim ke frontend masih tercampur history

- Result membawa jumlah soal yang aneh:
  helper review atau progress membaca snapshot lama tanpa filter aktif/historis yang tepat

## Kondisi yang Dianggap Benar

### Jika tambah soal

Contoh:
- semula 20 soal
- tambah 5 soal

Expected:
- navigasi utama menjadi 25 soal aktif
- nomor lama tetap 1-20
- soal baru mendapat nomor lanjutan 21-25
- history nonaktif yang lama tetap ada jika memang sudah pernah ada
- jawaban lama tetap menempel

### Jika kurangi soal

Contoh:
- semula 25 soal
- 5 soal dihapus dari exam aktif

Expected:
- navigasi utama hanya menampilkan 20 soal aktif
- nomor aktif tetap mengikuti nomor asli attempt dan boleh ada gap
- soal yang dihapus pindah ke `History Soal Nonaktif` jika punya history
- jawaban soal nonaktif tetap terlihat di history/result

## Hal yang Jangan Dilakukan Lagi

- Jangan jadikan runtime Redis sebagai sumber utama order attempt.
- Jangan renumber nomor aktif dengan `index + 1` saat exam sudah punya attempt berjalan.
- Jangan overwrite penuh soal lama jika exam sudah punya history attempt.
- Jangan menghapus soal historis yang masih dirujuk jawaban/attempt.
- Jangan anggap cache browser yang lebih panjang selalu lebih benar daripada payload server.

## File Pegangan Tambahan

Untuk uji cepat setelah perubahan, lihat juga:
- `REGRESSION-CHECKLIST.md`

Checklist itu dipakai untuk validasi.
Dokumen ini dipakai untuk memahami pola bug dan hotspot teknisnya.
