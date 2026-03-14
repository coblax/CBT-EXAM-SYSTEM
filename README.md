# CBT Exam System

Plugin WordPress untuk ujian CBT berbasis web dengan panel admin penuh, REST API berbasis JWT, frontend siswa berbasis JavaScript, dan toolkit operasional untuk ujian serentak.

Versi plugin saat ini: `1.5.0`

## Ringkasan

Plugin ini sudah mencakup alur inti CBT dari hulu ke hilir:

- pengelolaan subject, user, bank soal, exam, token, hasil, kartu ujian, dan report dari dashboard WordPress
- frontend siswa dengan login `email`, `username`, atau `NISN`
- 6 tipe soal dengan auto grading untuk tipe objektif dan koreksi manual untuk essay
- kontrol operasional untuk reset attempt, tambah waktu, force complete, cache invalidation, Redis bootstrap, generate dataset uji, sampai load test `k6`

## Highlight Fitur Saat Ini

### Admin dan Operasional

- Menu admin lengkap: `CBT Exams`, `CBT Tokens`, `CBT Setup`, `CBT Maintenance`, `CBT Cache`, `CBT Subjects`, `CBT Users`, `CBT Exam Cards`, `CBT Questions`, `CBT Results`, `CBT Report Exam`.
- Builder exam modern dengan dua panel (`Form Exam` dan `Daftar Exam`), checklist target kelas, sinkronisasi pilihan soal, preview soal, dan progress overlay saat update exam besar sedang diproses.
- Filter admin otomatis dengan partial refresh pada daftar exam, daftar subject, daftar user, dan katalog soal di builder exam.
- Branding CBT dari admin: nama sekolah, `logo_1`, dan `logo_2` untuk frontend, kartu ujian, dan report print-ready.
- Monitoring hasil dengan auto refresh 10 detik, timer remaining time per attempt, detail jawaban full-width, dan kontrol operasional tanpa keluar dari halaman hasil.

### Ujian, Soal, dan Penilaian

- Exam mendukung subject, durasi, status `draft/published/closed`, jadwal mulai-selesai, target kelas, token per exam, dan randomisasi urutan soal.
- Global token 6 karakter dapat diatur manual, dirotasi otomatis setiap 5-60 menit, diregenerate manual, dan di-auto-apply di frontend.
- Bank soal mendukung status aktif/nonaktif (`is_active`) sehingga soal bisa dinonaktifkan tanpa dihapus.
- Saat soal bank dipakai di exam, plugin menjaga linkage ke soal sumber agar perubahan soal bank bisa tersinkron ke salinan soal exam terkait.
- Penilaian otomatis tersedia untuk `multiple_choice`, `multiple_answer`, `true_false`, `true_false_matrix`, dan `short_answer`.
- Soal `essay` diperiksa manual dari halaman hasil.

### Frontend Siswa

- Halaman ujian otomatis dibuat sebagai halaman `CBT Ujian` dengan shortcode `[cbt_exam_frontend]`.
- Login dengan `email`, `username`, atau `NISN`, memakai JWT untuk autentikasi REST API.
- Satu sesi login aktif per akun untuk mencegah login ganda bersamaan.
- Resume attempt, autosave per soal, batch submit, timer dari server, auto finish saat waktu habis, penanda soal ragu-ragu, dan kalkulator bawaan.
- UI state per attempt disimpan ke server, sementara preferensi lokal seperti font, tema, panel navigasi, dan posisi kalkulator tetap disimpan di browser.
- Soal yang sudah dimuat disimpan ke `sessionStorage`, `localStorage`, dan `IndexedDB`, lalu dipadukan kembali saat reload agar perpindahan dan resume attempt lebih stabil.
- Runtime frontend sudah mendukung prefetch soal bertahap, no-cache headers pada halaman shortcode, dan review jawaban arsip saat attempt lama berisi soal yang kini nonaktif.

### Hasil, Cetak, dan Report

- `CBT Results` mendukung filter exam, status, kelas, pencarian siswa, auto refresh, reset login, reset attempt, bulk reset, bulk force complete, tambah waktu ujian, dan koreksi essay.
- `CBT Report Exam` menghasilkan tampilan print-ready untuk `Print / Save PDF` dengan filter exam + kelas, kolom nilai, dan 1 sampai 3 petugas bertanda tangan (`Pengawas`, `Teknisi Ruang`, `Proktor`).
- `CBT Exam Cards` menghasilkan layout A4 6 kartu per halaman dengan filter kelas, ruang, dan pencarian siswa.
- Kartu ujian memuat `NISN`, `username`, `password`, `kelas`, `ruang`, `agama`, foto, dan jadwal ujian.
- Jika password plaintext siswa belum tersedia, plugin membuat password 6 digit otomatis saat generate kartu agar kartu bisa langsung dicetak.

### Maintenance, Cache, dan Performa

- `CBT Maintenance` mendukung reset database CBT dengan progress bertahap.
- Generator dataset uji tersedia dengan preset `Small`, `Medium`, dan `Large`, lengkap dengan subject, exam, bank soal, guru, siswa, kelas, dan ruang uji.
- Dataset uji selalu membuat akun khusus `coblax / 223611`, serta password default dataset `Skills39`.
- Load test terintegrasi dari admin dengan preset `Smoke 50`, `Load 200`, `Load 500`, dan `Load 1000`, termasuk polling status job, ringkasan metrik, dan download artefak.
- Export pool siswa untuk load test tersedia dalam format `JSON`, `CSV`, dan `XLSX`.
- `CBT Cache` menyediakan readiness check Redis, probe server, probe runtime buffer, invalidate namespace, inspeksi lock, inspeksi UI state, bootstrap Redis sekali klik, dan rollback konfigurasi Redis dari sisi WordPress.
- Jika Redis belum siap, plugin tetap berjalan dengan fallback transient/object cache WordPress.

## Menu Admin

| Menu | Fungsi |
| --- | --- |
| `CBT Exams` | Builder exam, daftar exam, filter exam, sinkronisasi soal, dan ringkasan attempt aktif |
| `CBT Tokens` | Token global semua exam, rotasi otomatis, dan auto-apply token di frontend |
| `CBT Setup` | Nama sekolah, `logo_1`, `logo_2`, dan branding print/frontend |
| `CBT Maintenance` | Reset database CBT, generate dataset uji, dan load test terintegrasi |
| `CBT Cache` | Readiness Redis, bootstrap/rollback Redis, namespace cache, locks, dan UI state |
| `CBT Subjects` | CRUD subject, import `CSV/XLSX`, filter otomatis, dan bulk delete |
| `CBT Users` | CRUD user, import `CSV/XLSX`, upload foto, filter role/kelas/ruang/agama, dan bulk delete |
| `CBT Exam Cards` | Cetak kartu peserta ujian print-ready |
| `CBT Questions` | CRUD bank soal, import `CSV/XLSX/DOCX`, template Word, dan sinkronisasi soal sumber |
| `CBT Results` | Monitoring attempt, detail jawaban, essay grading, reset login/attempt, extra time, dan force complete |
| `CBT Report Exam` | Report nilai print-ready dengan tanda tangan petugas |

## Fitur Detail

### 1. Exam dan Attempt

- Exam mendukung:
  - subject
  - durasi
  - status `draft`, `published`, `closed`
  - jadwal mulai dan selesai
  - target kelas
  - randomisasi soal
  - token per exam
- Attempt mendukung:
  - start baru atau `resume_only`
  - penyimpanan urutan soal per attempt
  - tambahan waktu (`extra_time_minutes`)
  - review hasil setelah selesai
- Runtime berusaha menjaga urutan soal tetap stabil walaupun struktur soal exam berubah di tengah jalan.

### 2. Tipe Soal

Plugin mendukung 6 tipe soal:

- `multiple_choice`
- `multiple_answer`
- `true_false`
- `true_false_matrix`
- `short_answer`
- `essay`

Catatan penting:

- `multiple_choice`: 1 jawaban benar, template Word mendukung sampai 5 opsi.
- `multiple_answer`: lebih dari 1 jawaban benar, template Word mendukung sampai 12 opsi.
- `true_false_matrix`: satu soal dengan beberapa pernyataan `true/false`, template Word mendukung sampai 10 pernyataan.
- `short_answer`: mendukung placeholder `[INPUT_1]` sampai `[INPUT_8]` dan sampai 8 jawaban valid per soal.
- `essay`: mendukung rubrik/acuan jawaban, dicek manual dari halaman hasil.

### 3. Import Data

#### User

- Format: `CSV`, `XLSX`
- Kolom wajib: `name`, `username`, `password`, `role`, dan salah satu `email` atau `nisn`
- Kolom opsional: `email`, `nisn`, `kode_kelas`, `kode_ruang`, `agama`, `foto`
- Jika `email` kosong/tidak valid tetapi `nisn` ada, sistem membuat email default `nisn@student.sch.id`
- Untuk role siswa, foto default otomatis dipasang saat foto tidak tersedia
- Selain import massal, halaman `CBT Users` juga mendukung tambah, edit, hapus, upload foto, dan bulk delete manual

#### Subject

- Format: `CSV`, `XLSX`
- Template resmi dapat diunduh dari admin
- Import subject skala besar diproses bertahap dengan progress otomatis

#### Soal

- Format: `CSV`, `XLSX`, `DOCX`
- Template Word tersedia untuk semua tipe soal di folder `templates/`
- Gambar yang ditempel langsung ke dokumen Word ikut terbaca saat import
- Import besar dan bulk delete soal diproses bertahap dengan progress otomatis

## Frontend dan REST API

Semua endpoint selain login memerlukan header:

```text
Authorization: Bearer <jwt_token>
```

Endpoint utama:

- `POST /wp-json/cbt/v1/login`
- `POST /wp-json/cbt/v1/logout`
- `GET /wp-json/cbt/v1/session`
- `GET /wp-json/cbt/v1/exams`
- `GET /wp-json/cbt/v1/subjects`
- `GET /wp-json/cbt/v1/questions`
- `POST /wp-json/cbt/v1/start_attempt`
- `POST /wp-json/cbt/v1/submit_answer`
- `POST /wp-json/cbt/v1/submit_answers_batch`
- `POST /wp-json/cbt/v1/finish_exam`
- `GET|POST|DELETE /wp-json/cbt/v1/ui_state`
- `GET /wp-json/cbt/v1/result`

Perilaku penting endpoint:

- `login` menerima identifier `email`, `username`, atau `nisn`
- `questions` mendukung `offset`, `limit`, `include_existing`, dan `include_answer_manifest`
- `start_attempt` mendukung `exam_token` dan `resume_only`
- payload timer dari server sudah menyertakan `remaining_seconds`, `server_now`, dan `extra_time_minutes`
- runtime dapat mendefer scoring submit saat server sedang sibuk agar alur start exam dan ambil soal tetap lebih prioritas

## Tabel Database

Saat aktivasi, plugin membuat tabel:

- `wp_cbt_subjects`
- `wp_cbt_exams`
- `wp_cbt_questions`
- `wp_cbt_options`
- `wp_cbt_question_multiple_choice`
- `wp_cbt_question_multiple_answer`
- `wp_cbt_question_true_false`
- `wp_cbt_question_short_answer`
- `wp_cbt_question_essay`
- `wp_cbt_attempts`
- `wp_cbt_answers`

Catatan:

- `true_false_matrix` tidak memakai tabel detail terpisah; payload matrix disimpan pada detail soal.
- `wp_cbt_questions` memakai `source_question_id` untuk linkage ke bank soal sumber dan `is_active` untuk aktif/nonaktif.
- `wp_cbt_attempts` memakai `question_order` dan `extra_time_minutes`.
- Plugin juga menjaga role, capability, halaman frontend, dan migrasi schema tetap sinkron saat plugin dimuat.

## Role dan Capability

Role kompatibel yang disiapkan:

- `guru_cbt`
- `siswa_cbt`
- `teacher`
- `student`

Capability utama:

- Admin: kelola sistem, user, subject, exam, soal, hasil, essay, dan ikut ujian
- Guru: kelola exam, soal, lihat hasil, koreksi essay
- Siswa: ikut ujian dan lihat hasil sendiri

Capability juga ditambahkan ke role WordPress umum seperti `administrator`, `editor`, dan `subscriber` untuk kompatibilitas yang lebih mudah.

## Persyaratan Sistem

- WordPress `6.0+`
- PHP `8.0+`
- MySQL atau MariaDB
- Composer
- Ekstensi PHP untuk import spreadsheet:
  - `zip`
  - `xml`
  - `mbstring`
  - `gd`
- Ekstensi `redis` direkomendasikan jika ingin memakai object cache dan runtime buffer Redis

Dependensi Composer:

- `firebase/php-jwt`
- `phpoffice/phpspreadsheet`

## Instalasi Cepat

1. Salin plugin ke:

```bash
wp-content/plugins/cbt-exam-system
```

2. Install dependency Composer:

```bash
cd wp-content/plugins/cbt-exam-system
composer install --no-dev
```

3. Aktifkan plugin dari dashboard WordPress.

4. Tambahkan secret JWT ke `wp-config.php`:

```php
define('CBT_JWT_SECRET', 'ganti-dengan-secret-panjang-acak');
```

Jika `CBT_JWT_SECRET` tidak diisi, plugin akan memakai `wp_salt('auth')` sebagai fallback.

5. Setelah aktif, plugin akan:

- membuat tabel CBT
- mendaftarkan role dan capability
- membuat halaman frontend `CBT Ujian`
- menambahkan shortcode `[cbt_exam_frontend]` ke halaman tersebut

## Redis dan Cache

Plugin memiliki dua lapisan optimasi:

- cache lintas request melalui object cache WordPress atau transient fallback
- runtime Redis buffer untuk beban submit dan state runtime yang lebih berat

Contoh konfigurasi object cache WordPress:

```php
define('WP_CACHE', true);
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_DATABASE', 1);
define('WP_REDIS_PREFIX', 'cbt_exam_system:');
```

Contoh konfigurasi runtime Redis buffer:

```php
define('CBT_RUNTIME_BUFFER_ENABLED', true);
define('CBT_RUNTIME_BUFFER_FALLBACK_TO_DB', true);
define('CBT_RUNTIME_REDIS_HOST', '127.0.0.1');
define('CBT_RUNTIME_REDIS_PORT', 6379);
define('CBT_RUNTIME_REDIS_DATABASE', 2);
define('CBT_RUNTIME_REDIS_PREFIX', 'cbt_runtime:');
```

`CBT Cache` sudah menyediakan:

- readiness banner
- configuration snapshot
- probe object cache dan runtime
- one-click bootstrap Redis Object Cache WordPress
- rollback Redis integration
- invalidate namespace global, exam, user, dan attempt
- inspeksi namespace, locks, dan UI state

## Generate Dataset Uji

Preset bawaan:

- `Small`: 5 subject, 10 exam, 200 soal, 60 siswa, 6 guru, 6 kelas, 3 ruang
- `Medium`: 10 subject, 30 exam, 900 soal, 300 siswa, 18 guru, 12 kelas, 6 ruang
- `Large`: 20 subject, 80 exam, 3200 soal, 1200 siswa, 48 guru, 24 kelas, 12 ruang

Catatan:

- dataset memproses reset + seed secara bertahap
- akun khusus yang selalu dibuat: `coblax / 223611`
- password default dataset: `Skills39`

## Load Test dan Performance Pack

Folder `performance/` berisi:

- `performance/README.md`
- `performance/server-tuning-2core-8gb.md`
- `performance/load-test/k6/cbt_exam_1000_users.js`
- `performance/load-test/k6/students.sample.json`

Fitur load test dari admin:

- pilih exam target
- pilih preset `Smoke 50`, `Load 200`, `Load 500`, `Load 1000`
- polling status job selama run aktif
- lihat ringkasan metrik hasil run
- download artefak stdout, stderr, summary, dan dataset siswa

Target dokumentasi performa saat ini:

- baseline web: sampai 1000 siswa realtime
- dengan runtime Redis batch submit: target bertahap sampai 2000 siswa realtime
- baseline server awal: 2 vCPU, 8 GB RAM

## Alur Penggunaan yang Disarankan

1. Import atau buat `CBT Users` lebih dulu.
2. Buat atau import `CBT Subjects`.
3. Isi `CBT Questions` secara manual atau import massal.
4. Buat exam di `CBT Exams`, pilih kelas peserta, atur jadwal, durasi, dan randomisasi bila perlu.
5. Atur token dari `CBT Tokens` bila exam memakai token global.
6. Arahkan siswa ke halaman `/cbt-ujian`.
7. Pantau progress siswa di `CBT Results`.
8. Gunakan `CBT Report Exam` dan `CBT Exam Cards` saat kebutuhan administrasi cetak muncul.

## Catatan Penting

- Pastikan dependency Composer sudah terpasang sebelum memakai fitur `XLSX` dan `DOCX`.
- Reverse proxy atau web server tidak boleh membuang header `Authorization`.
- Frontend shortcode sudah mengirim header no-cache agar halaman login dan ujian siswa tidak mudah ter-cache agresif.
- Jika Redis belum siap, plugin tetap aman berjalan, tetapi mode fallback bukan opsi utama untuk ujian serentak skala besar.
