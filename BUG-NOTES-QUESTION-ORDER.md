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

## Jangan Dilanggar Lagi

- Jangan hitung `question_number` dari posisi array untuk attempt aktif, review, atau result.
- Jangan `shuffle()` ulang attempt `in_progress` hanya karena ada question revision.
- Jangan gunakan `created_at` untuk menggeser ulang soal lama yang sudah ada dalam snapshot attempt.
- Jangan kosongkan runtime attempt state tanpa memastikan snapshot DB tetap lengkap dibaca oleh jalur session dan questions.
- Jangan percaya cache browser untuk struktur order jika `question_order_signature` tidak cocok.

## File Kunci

- `includes/class-cbt-rest.php`
- `includes/class-cbt-runtime.php`
- `src/frontend/app/exam/question-runtime.js`
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
