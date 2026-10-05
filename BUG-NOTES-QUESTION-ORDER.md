# Bug Notes: Random Attempt Question Order

Dokumen ini mengunci aturan sinkronisasi urutan soal untuk exam dengan `randomize_questions = 1`, terutama ketika attempt sedang aktif lalu guru menambah atau menghapus soal.

## Tujuan

Perubahan daftar soal pada exam aktif tidak boleh membuat urutan soal yang sudah berjalan menjadi acak ulang.

Yang harus tetap benar:

- urutan lama pada attempt aktif tetap dipertahankan
- soal baru masuk di bagian akhir
- soal yang dihapus keluar dari navigasi aktif, tetapi history numbering tetap konsisten
- nomor soal di exam, review siswa, result siswa, dan admin result harus sama

## Invariant Utama

### 1. Snapshot DB adalah sumber canonical

- `cbt_attempts.question_order` adalah snapshot historis utama.
- Redis runtime hanya mirror atau fallback jika snapshot DB kosong.
- Jangan merge urutan Redis ke atas urutan DB sebagai sumber kebenaran baru.

### 2. Attempt random aktif tidak boleh diacak ulang

- Untuk attempt `in_progress`, urutan aktif lama harus dipertahankan apa adanya.
- Reconcile hanya boleh:
  - membuang `question_id` lama yang sudah tidak aktif dari navigasi aktif
  - menambahkan `question_id` baru yang belum pernah ada ke bagian akhir
- Soal baru boleh diurutkan di antara sesama soal baru, tetapi soal lama tidak boleh digeser ulang.

### 3. Tambah soal

- Soal aktif lama tetap pada urutan lama.
- Soal baru di-append ke belakang active order.
- Jika source soal pernah keluar lalu ditambahkan lagi, perlakukan sebagai item baru di ekor, bukan mengambil posisi lama.

### 4. Hapus soal

- Jika soal punya history atau jawaban, archive dengan `is_active = 0`.
- Jika tidak punya history, boleh dihapus fisik.
- Nomor historis tidak boleh di-renumber ulang hanya karena ada soal yang hilang.

### 5. Numbering authoritative

- Backend harus membentuk:
  - `canonical_question_order_ids`
  - `active_question_order_ids`
  - `display_number_map`
- `question_order_signature` dihitung dari `active_question_order_ids + display_number_map`.
- `question_number` tidak boleh lagi dibentuk dari `index + 1` pada jalur attempt active, review, atau result.

### 6. Frontend hanya menerima kontrak order yang valid

- Frontend menganggap `question_order_ids + question_manifest.question_number + question_order_signature` sebagai satu paket authoritative.
- Cache order browser hanya boleh dipakai jika signature cocok.
- Jika kontrak invalid atau signature bentrok, frontend harus menolak patch order dan meminta reload manual.

## Bug Penting yang Sudah Terjadi

### A. First refresh setelah tambah soal mengacak nomor

Penyebab:

- frontend sempat mencampur payload baru dengan state lama
- atau backend mengirim kontrak order yang belum stabil

Solusi yang dipakai:

- frontend validasi kontrak authoritative lebih tegas
- anchor tetap by `question_id`
- payload invalid ditolak dengan sticky notice, bukan diheuristik

### B. Soal baru sudah di akhir, tetapi urutan soal lama ikut berubah

Penyebab:

- reconcile attempt aktif memperlakukan soal "recent" terlalu agresif
- akibatnya soal yang sudah ada di snapshot lama bisa ikut tersusun ulang saat sync berikutnya

Solusi yang dipakai:

- pertahankan seluruh `existing_question_order_ids` yang masih aktif
- hanya `question_id` yang benar-benar belum ada di snapshot lama yang di-append ke belakang

### C. Heartbeat session memicu reshuffle baru

Penyebab:

- `get_session()` pernah mengambil row attempt tanpa `question_order` dan `option_order`
- helper sync menganggap snapshot kosong lalu membentuk urutan baru

Solusi yang dipakai:

- `get_session()` wajib mengambil `a.question_order` dan `a.option_order`

### D. Soal yang dihapus tetap tampil di peserta yang sedang ujian

Penyebab:

- snapshot kontrak (`cbt_attempt_contract:*`) dan snapshot sesi (`cbt_attempt_session:*`) di Redis membekukan daftar soal attempt
- `ensure_attempt_snapshots_cover_exam_questions()` hanya membuang snapshot saat exam **mendapat** soal baru, tidak saat soal dihapus/dinonaktifkan
- akibatnya soal yang diarsipkan diambil ulang lewat `append_missing_questions_by_ids()` dan tetap bisa dijawab, sementara heartbeat terus melaporkan jumlah soal lama

Solusi yang dipakai:

- sekali per revisi exam, snapshot dibuang bila **himpunan** soal kontrak berbeda dari soal aktif exam (tambah maupun hapus)
- snapshot sesi juga dibuang bila jumlah soalnya berbeda, bila kontraknya tidak ada (pertukaran hapus-satu-tambah-satu tidak terlihat dari jumlah), atau bila signature-nya bentrok dengan kontrak

### E. Duplikat soal tidak sampai ke peserta yang sedang ujian

Penyebab:

- `handle_duplicate_question()` memasukkan salinan aktif ke exam yang sama tetapi hanya menghangatkan snapshot, tanpa menaikkan revisi exam
- siswa yang baru mulai melihat salinan itu, siswa yang sedang ujian tidak

Solusi yang dipakai:

- setiap jalur admin yang mengubah anggota soal exam wajib memanggil `CBT_Cache::invalidate_exam(s)` sebelum warm snapshot

### F. Notifikasi perubahan soal mudah terlewat atau hilang

Penyebab:

- penambahan/perubahan soal hanya berupa toast 5 detik tanpa nomor soal
- penghapusan soal tidak diumumkan sama sekali
- peringatan "muat ulang halaman" hilang begitu siswa pindah soal, padahal signature yang sama sudah diblokir sehingga tidak akan muncul lagi
- dialog "Selesai ujian" yang sudah terbuka tetap memakai ringkasan lama setelah soal ditambah

Solusi yang dipakai: lihat bagian **Notifikasi ke Peserta** di bawah.

## Notifikasi ke Peserta

- Perubahan soal di tengah ujian selalu diumumkan sebagai notifikasi sticky, bukan toast singkat.
- Isi notifikasi menyebut jumlah dan nomor soal per jenis perubahan: `N soal baru ditambahkan: No. …`, `N soal berubah: No. …`, `N soal dihapus: No. …` (maksimal 5 nomor, sisanya `dan N lainnya`).
- Notifikasi soal baru (`kind: added-questions`) bertahan sampai semua soal baru sudah dibuka, dan punya tombol `Buka soal No. X` ke soal baru pertama yang belum dibuka.
- Notifikasi lain hilang saat siswa berpindah soal; tanda `!` di navigasi tetap ada sampai soal itu dibuka.
- Revisi tanpa perubahan soal (mis. guru mengubah pengaturan exam) tidak boleh menghapus notifikasi soal baru yang belum dibuka.
- Peringatan muat ulang (`kind: manual-reload`) tidak boleh hilang karena navigasi.
- Bila dialog "Selesai ujian" terbuka saat soal ditambah/dihapus, dialog ditutup agar siswa tidak mengonfirmasi ringkasan lama.
- Selama finish berjalan (`examLockedForPendingFinish` / `isFinishing`) data soal tidak boleh diganti; refresh diulang heartbeat berikutnya bila finish gagal.

## Jangan Dilanggar Lagi

- Jangan hitung `question_number` dari posisi array untuk attempt aktif, review, atau result.
- Jangan `shuffle()` ulang attempt `in_progress` hanya karena ada question revision.
- Jangan gunakan `created_at` untuk menggeser ulang soal lama yang sudah ada dalam snapshot attempt.
- Jangan kosongkan runtime attempt state tanpa memastikan snapshot DB tetap lengkap dibaca oleh jalur session dan questions.
- Jangan percaya cache browser untuk struktur order jika `question_order_signature` tidak cocok.
- Jangan hanya memeriksa soal yang **bertambah** saat memvalidasi snapshot attempt di Redis; soal yang hilang sama pentingnya.
- Jangan mengubah anggota soal exam dari admin tanpa menaikkan revisi exam (`CBT_Cache::invalidate_exam`).
- Jangan mengumumkan perubahan soal hanya lewat toast yang hilang sendiri.

## File Kunci

- `includes/class-cbt-rest.php`
- `includes/class-cbt-rest-question-snapshots.php` (`ensure_attempt_snapshots_cover_exam_questions`)
- `includes/class-cbt-runtime.php`
- `admin/class-cbt-admin-questions-service.php`
- `src/frontend/app/exam/question-runtime.js`
- `src/frontend/app/stages/exam.js` (render notifikasi revisi)
- `src/frontend/app/core/session-heartbeat.js`
- `src/frontend/app/core/exam-session.js`
- `src/frontend/app/storage/question-cache.js`
- `src/frontend/app/storage/attempt-ui-state.js`

## Checklist Cepat Setelah Menyentuh Area Ini

1. Tambah 1 soal pada exam acak dengan attempt aktif.
2. Pastikan urutan lama tetap, soal baru masuk di belakang.
3. Hapus 1-2 soal dan pastikan nomor historis tidak dirapatkan ulang.
4. Reload tab siswa dan bandingkan dengan tab lama.
5. Cek review/result siswa dan admin result memakai nomor yang sama.
6. Cek heartbeat session tidak memicu reshuffle pada first sync.
7. Pastikan soal yang dihapus benar-benar hilang dari tab siswa yang sedang ujian dalam satu heartbeat (dengan Redis aktif).
8. Duplikat satu soal di exam aktif dan pastikan siswa yang sedang ujian menerimanya.
9. Pastikan notifikasi menyebut nomor soal, tetap tampil setelah pindah soal sampai soal baru dibuka, dan tombol `Buka soal No. X` bekerja.
10. Buka dialog "Selesai ujian", tambah soal dari admin, dan pastikan dialog tertutup dengan notifikasi soal baru.
