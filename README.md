# CBT Exam System

Plugin WordPress untuk ujian CBT berbasis web: panel admin lengkap, frontend siswa dan dashboard pengawas berbasis Vite, REST API berbasis JWT di namespace `cbt/v1`, serta toolkit operasional untuk update, maintenance, cache, testing, dan load test.

README ini adalah dokumen onboarding utama repo. Isinya cukup untuk memahami gambaran produk, setup lokal, workflow frontend, pengujian, dan area operasional penting tanpa harus membaca seluruh source lebih dulu.

Versi plugin saat dokumen ini diperbarui: **`3.2.7`** (lihat header `cbt-exam-system.php`).

## Dokumen Lain di Repo

| Dokumen | Isi |
| --- | --- |
| [`INSTALL-NGINX-PHP-FPM.md`](INSTALL-NGINX-PHP-FPM.md) | Instalasi server produksi Nginx + PHP-FPM + MySQL + Redis, `wp-config.php`, smoke test, backup, dan tuning `16 core / 16 GB` |
| [`performance/README.md`](performance/README.md) | Performance pack web, tuning baseline `2 vCPU / 8 GB`, dan script `k6` |
| [`admin/README.md`](admin/README.md) | Handbook arsitektur folder `admin/`: menu, capability, hook `admin_post`/`wp_ajax`, dan peta modul |
| [`BUG-NOTES-QUESTION-ORDER.md`](BUG-NOTES-QUESTION-ORDER.md) | Aturan sinkronisasi urutan soal acak saat soal ditambah/dihapus pada exam yang sedang berjalan |

## Kompatibilitas Minimum

- WordPress `6.0+`
- PHP `8.1+` (header plugin menulis `8.0`, tetapi dependency Composer seperti PhpSpreadsheet 2.x dan PHPUnit 10 membutuhkan `>=8.1`)
- Ekstensi PHP `sodium` atau `openssl` untuk enkripsi password siswa
- Node.js `20+`
- Composer `2+`
- MySQL/MariaDB yang kompatibel dengan WordPress
- Redis bersifat opsional tetapi sangat direkomendasikan untuk ujian serentak; tanpa Redis plugin fallback ke transient/object cache WordPress dan MySQL

## Ringkasan Fitur

### 1. Admin akademik

- Menu admin lengkap di bawah `CBT Exams` (lihat [Peta Menu Admin](#peta-menu-admin))
- Builder exam dengan status `draft` / `published` / `closed`, jadwal mulai-selesai, target kelas/ruang, token exam, randomisasi soal, randomisasi opsi, dan kalkulator
- Bank soal dengan 11 tipe soal, editor rumus matematika (KaTeX), revisi soal, dan impor dari template Word (`DOCX`)
- CRUD subject dan user, termasuk impor `CSV`/`XLSX`
- Monitoring hasil, grading essay manual atau berbantuan AI, analytics, dokumen administrasi ujian, report exam siap cetak, dan catatan kejadian ujian
- Updater plugin dari GitHub Releases dengan preflight, backup, dan rollback

### 2. Frontend siswa

- Shortcode `[cbt_exam_frontend]` di halaman kanonik `cbt-ujian` yang dijaga otomatis oleh plugin
- Login siswa memakai `email`, `username`, atau `NISN`
- Start attempt baru atau `resume` attempt lama
- Opening shell untuk `Mulai Ujian` / `Lanjutkan Ujian` memakai retry countdown, progress monotonic, idempotency key, dan status ringan agar siswa tidak merasa freeze saat server padat
- First question window lewat `bootstrap_light=1` agar soal awal tampil cepat tanpa hydrate runtime penuh
- Timer ujian, autosave jawaban, batch submit, tanda ragu-ragu, review, dan result
- **Antrian jawaban tahan banting**: jawaban disimpan dulu di IndexedDB (`cbt_exam_answer_queue_v1`, fallback `localStorage`) lalu disinkronkan ke server dengan status `pending` → `syncing` → `acked`, sehingga jawaban tidak hilang saat koneksi putus atau halaman di-refresh
- **Service worker** untuk precache asset build frontend (hanya di halaman siswa dan mode `Production Build`)
- Cache respons API read-only di sisi browser dan **prewarm media** soal (gambar/audio/video) agar perpindahan soal lebih cepat
- Render rumus matematika (KaTeX) dan kalkulator bawaan
- Attempt yang waktunya habis ditampilkan sebagai `Diproses` dan difinalisasi background worker sebelum menjadi `Selesai`

### 3. Dashboard pengawas

- Shortcode `[cbt_exam_supervisor_frontend]` di halaman kanonik `pengawas` yang juga dibuat otomatis saat aktivasi
- Login memakai akun guru (`guru_cbt` / `teacher`) atau admin
- Panel `Live Roster`, `Must Watch`, `Butuh Tindakan`, `Daftar Hadir`, ringkasan attempt, dan log security per exam
- Presence live siswa (online / stale / last seen) berbasis heartbeat, disimpan di Redis runtime bila tersedia
- Detail attempt per siswa dan aksi **reset login** siswa langsung dari dashboard
- Telemetri kesiapan server: availability Redis, gate status, queue depth, dan finalisasi background

### 4. Security dan observability

- Opsi fullscreen wajib dan blok `copy` / `cut` / `paste` saat ujian
- Idle detection yang dapat dikonfigurasi dari `CBT Security`
- Event log frontend/server untuk `fullscreen_exit`, `tab_hidden`, `window_blur`, `page_leave`, `clipboard_blocked`, dan event sesi ujian
- Security log dengan mode Redis-first ingest dan fallback MySQL langsung bila Redis tidak tersedia
- Redis Monitor di `CBT Security` untuk melihat mode live/ingest/persist, backlog, dead-letter, last enqueue, dan last flush
- Guard user-agent dan rate limiting pada jalur autentikasi
- Metrik operasional untuk `start_attempt`, `start_attempt_status`, entry flow (`login_to_exam_list`, `start/resume_to_first_question`), dan submit flow
- Password siswa yang perlu dicetak (kartu peserta, export load test) disimpan terenkripsi (`sodium-secretbox` atau `aes-256-gcm`), bukan plaintext

### 5. Maintenance, cache, dan performa

- Reset database CBT, plus pembersihan key Redis plugin (`cbt_*`) dari panel snapshot di `CBT Exams`
- Generator dataset uji dengan preset `Small`, `Medium`, dan `Large`
- Export pool siswa dan runner load test `k6` dari area maintenance
- Cache namespace, lock inspection, UI-state inspection, dan readiness Redis/object cache
- Student Cohort Index untuk filter kelas/ruang, preflight, readiness, dan target siswa exam tanpa scan user besar di hot path
- One-Click Pra Ujian memakai snapshot target siswa yang stabil selama job berjalan
- Warm Login Readiness sebagai queue background bertahap untuk memanaskan snapshot login per kelas/ruang/exam
- Adaptive load service yang menaikkan/menurunkan mode beban berdasarkan sinyal runtime
- Snapshot auto-heal queue yang membangun ulang snapshot exam yang perlu diperbaiki secara bertahap di background

### 6. Testing dan QA

- `PHPUnit` untuk unit/integration test PHP (`tests/php/`, 140+ file test)
- `Vitest` untuk unit test frontend JavaScript (`tests/js/`, 60+ file test)
- `Playwright` untuk flow E2E (`tests/e2e/`)
- `CBT Test Hub` untuk unit checklist dan flow-check job dari panel admin
- Budget ukuran bundle frontend dicek otomatis setiap `npm run build`

## Interface Publik yang Perlu Diketahui

### Shortcode dan halaman frontend

| Halaman kanonik | Shortcode | Pengguna |
| --- | --- | --- |
| `cbt-ujian` | `[cbt_exam_frontend]` | Siswa |
| `pengawas` | `[cbt_exam_supervisor_frontend]` | Guru / admin (pengawas) |

- Kedua halaman dibuat atau diperbaiki otomatis oleh plugin (saat aktivasi dan saat sinkronisasi halaman).
- Halaman kanonik memakai template minimal plugin dan header no-cache agar shell CBT tampil lebih awal dan tidak ter-cache.
- Shortcode pada halaman lain tetap bisa dipakai sebagai fallback.

### REST API

Semua endpoint berada di namespace `cbt/v1` dan memakai token JWT (`Authorization: Bearer ...`) kecuali `login`/`logout`.

Endpoint siswa/guru:

| Method | Endpoint | Catatan |
| --- | --- | --- |
| `POST` | `/login` | Login siswa/guru/admin, mengembalikan JWT |
| `POST` | `/logout` | |
| `GET` | `/session` | Mendukung `bootstrap_light=1` untuk snapshot sesi ringan |
| `GET` | `/exams` | Daftar exam yang tersedia |
| `GET` | `/subjects` | |
| `GET` | `/questions` | Mendukung `bootstrap_light=1` untuk first question window |
| `POST` | `/start_attempt` | Menerima `idempotency_key` opsional |
| `POST` | `/start_attempt_status` | Status-only untuk polling queue, refresh status, dan resume ringan |
| `POST` | `/submit_answer` | |
| `POST` | `/submit_answers_batch` | |
| `POST` | `/answer_sync_token` | Token sinkronisasi jawaban per attempt untuk antrian jawaban |
| `POST` | `/finish_exam` | |
| `POST` | `/security_event` | |
| `POST` | `/native_security_event` | |
| `POST` | `/entry_flow_metric` | |
| `POST` | `/submit_flow_metric` | |
| `GET` `POST` `DELETE` | `/ui_state` | |
| `GET` | `/result` | |

Endpoint pengawas (role guru/teacher/admin):

- `GET /cbt/v1/supervisor_dashboard`
- `GET /cbt/v1/supervisor_attempt_detail`
- `POST /cbt/v1/supervisor_reset_login`

Endpoint admin-only:

- `GET /cbt/v1/security_observability_snapshot`
- `GET /cbt/v1/security_logs_page`
- `POST /cbt/v1/security_ingest_admin_action`
- `GET /cbt/v1/diagnostics/exam-cache-test`

Jawaban benar tidak pernah dikirim ke siswa; payload soal selalu disanitasi, termasuk pada jalur `bootstrap_light=1`.

### Role dan capability

Saat aktivasi plugin mendaftarkan role berikut:

| Role | Capability CBT |
| --- | --- |
| `administrator` | Semua capability CBT (`cbt_manage_system`, `cbt_manage_users`, `cbt_manage_subjects`, `cbt_manage_exams`, `cbt_manage_questions`, `cbt_view_results`, `cbt_grade_essay`, ...) |
| `guru_cbt` (dan `teacher` untuk kompatibilitas) | `cbt_manage_exams`, `cbt_manage_questions`, `cbt_view_results`, `cbt_grade_essay` |
| `siswa_cbt` (dan `student` untuk kompatibilitas) | `cbt_take_exam`, `cbt_view_own_result` |

Capability guru juga ditambahkan ke role `editor`, dan capability siswa ke role `subscriber`, agar sekolah yang memakai role bawaan WordPress tetap bisa memakai plugin.

## Peta Menu Admin

| Menu | Capability | Fungsi utama |
| --- | --- | --- |
| `Introduction` | `cbt_manage_exams` | Ringkasan awal area plugin dan onboarding admin |
| `CBT Exams` | `cbt_manage_exams` | Builder exam, jadwal, target kelas/ruang, randomisasi, token exam, preview, dan cetak naskah |
| `CBT Branding` | `cbt_manage_exams` | Branding sekolah, logo, dan identitas visual CBT |
| `CBT Security` | `cbt_manage_exams` | Pengaturan security ujian, idle detection, security log, dan Redis Monitor |
| `CBT Subjects` | `manage_options` | CRUD dan impor subject (`CSV`/`XLSX`) |
| `CBT Users` | `manage_options` | CRUD user, impor (`CSV`/`XLSX`), foto, filter kelas/ruang, dan manajemen akun |
| `CBT Questions` | `cbt_manage_questions` | Bank soal 11 tipe, editor rumus, impor DOCX, preview, revisi, dan sinkronisasi ke exam |
| `CBT Tokens` | `cbt_manage_exams` | Token ujian dan global token |
| `CBT Administrative Documents` | `cbt_manage_users` | Cetak `Kartu Peserta`, `Nomor Meja`, `Daftar Hadir Peserta Ujian`, dan `Berita Acara Pelaksanaan` |
| `CBT Results` | `cbt_view_results` | Monitoring attempt, review jawaban, grading essay manual/AI, dan aksi operasional hasil |
| `CBT Analytics` | `cbt_view_results` | Analitik hasil ujian dan insight attempt |
| `CBT Report Exam` | `cbt_view_results` | Report exam siap cetak/PDF dan pencatatan kejadian ujian |
| `CBT Test Hub` | `manage_options` | Unit checklist, smoke-flow, dan flow-check job |
| `CBT Cache` | `manage_options` | Readiness cache, namespace invalidation, lock/UI-state inspection, dan Redis bootstrap/rollback |
| `CBT Update` | `manage_options` | Update plugin dari GitHub Releases, preflight checklist, riwayat update, backup, dan rollback |
| `CBT Maintenance` | `manage_options` | Reset database, seed test data, export siswa load test, dan runner `k6` |
| `CBT Developer` | `manage_options` | Sumber asset frontend, health Vite dev server, dan alat bantu developer |

## Kemampuan Operasional Penting

### Tipe soal

Plugin mendukung 11 tipe soal:

| Tipe | Label |
| --- | --- |
| `multiple_choice` | Multiple Choice |
| `multiple_answer` | Multiple Answer |
| `true_false` | True/False |
| `true_false_matrix` | True/False Matrix |
| `short_answer` | Short Answer |
| `essay` | Essay |
| `ordering` | Ordering |
| `matching` | Matching |
| `cloze_dropdown` | Cloze Dropdown |
| `categorization` | Categorization |
| `table_completion` | Table Completion |

Setiap tipe punya tabel detail sendiri (`cbt_question_*`) dan riwayat perubahan disimpan di `cbt_question_revisions`.

### Impor dan template

- Impor soal **hanya menerima `DOCX`** dari template Word resmi CBT. Template dapat diunduh dari `CBT Questions`, tersedia per tipe soal maupun gabungan. Gambar di dalam DOCX ikut diekstrak dan divalidasi MIME type-nya.
- Impor user dan subject menerima `CSV` dan `XLSX`. Template user tersedia di `templates/user-import-template.csv` dan dapat diunduh dari admin.

### Grading essay berbantuan AI

- Essay tetap bisa dinilai manual dari `CBT Results`.
- Opsional: grading AI dengan provider **Gemini** (default) atau **OpenAI**. API key dan model diatur dari panel `CBT Results`; job grading berjalan bertahap dan dapat dihentikan.

### Dokumen administrasi, report, dan kejadian ujian

- `CBT Administrative Documents` mencetak kartu peserta (dengan pilihan field tampil), nomor meja (dengan nomor awal dan padding), daftar hadir, dan berita acara per kelas/ruang.
- `CBT Report Exam` menyediakan report exam siap cetak dan pencatatan kejadian ujian (tabel `cbt_exam_incidents`) dengan kategori Kehadiran, Pelanggaran/Kecurangan, Aktivitas di Ruangan, Gangguan Teknis, Kondisi Peserta, dan Lainnya.

### Update plugin dari admin

`CBT Update` membaca release terbaru dari GitHub (`coblax/CBT-EXAM-SYSTEM`), lalu:

1. menampilkan release summary dan menjalankan preflight checklist (manifest, versi, tabel database yang wajib ada)
2. membuat backup plugin sebelum update (maksimal 5 backup, retensi 30 hari)
3. memasang paket `cbt-exam-system.zip` dari asset release
4. menyimpan riwayat update dan menyediakan rollback ke backup sebelumnya

## Operasional Ujian Massal

Bagian ini merangkum fitur yang paling relevan untuk ujian dengan banyak peserta serentak.

### Login readiness dan preflight

- `Student Cohort Index` menyimpan index siswa ringan berbasis tabel MySQL agar filter kelas/ruang, readiness, dan target siswa exam tidak harus scan semua WordPress user.
- Jika cohort index siap, One-Click Pra Ujian mengambil snapshot `target_student_ids` sekali di awal job dan memakai snapshot yang sama untuk batch berikutnya.
- Jika cohort index masih building, jalur massal ditahan agar tidak jatuh ke scan canonical besar yang memperlambat admin.
- Jika cohort index tidak tersedia sama sekali, sistem fallback ke jalur canonical untuk kompatibilitas.
- `Warm Login Readiness` memproses pemanasan login snapshot di background queue tunggal, dengan progress `ready / target`, batch kecil, dan lock worker.

### Mulai dan lanjut ujian

- Resume attempt aktif diprioritaskan sebelum gate start baru.
- `start_attempt_status` dibuat ringan dan tidak melakukan hydrate runtime penuh.
- `start_attempt` memakai idempotency key opsional sehingga retry dalam shell yang sama tidak membuat attempt atau queue ticket baru.
- Opening shell memakai retry countdown dengan jitter, progress monotonic setelah `attempt_id` terdeteksi, dan opening state `resume_lookup`, `attempt_creating`, `bootstrap_session`, `bootstrap_questions`, `ready`, `completed`, serta `terminal_error`.
- Jika exam belum aktif, belum mulai, sudah berakhir, atau belum dipublikasikan, frontend menampilkan pesan final dengan saran kembali ke daftar exam, bukan retry tanpa arah.

### Jawaban dan koneksi tidak stabil

- Setiap jawaban masuk dulu ke antrian lokal di browser, lalu dikirim per batch ke server memakai `answer_sync_token`.
- Jawaban yang gagal terkirim ditandai `failed_retryable` dan dicoba ulang; jawaban yang sudah dikonfirmasi server ditandai `acked`.
- Refresh halaman atau koneksi putus sementara tidak menghilangkan jawaban yang belum tersinkron.

### Security log dan live monitoring

- Jika Redis-first security ingest aktif dan Redis Stream tersedia, event siswa masuk ke Redis Stream lalu worker batch mem-persist ke MySQL.
- Jika Redis tidak tersedia atau stream gagal, event langsung masuk MySQL agar audit tidak hilang.
- Raw log MySQL dipakai untuk history/audit, sedangkan live observability admin dan dashboard pengawas membaca summary Redis saat tersedia.

### Background worker penting

- Expired attempt tidak difinalisasi sinkron di request siswa/admin. UI menampilkan `Diproses`, lalu worker background menyelesaikan attempt menjadi `completed`.
- Worker penting mencakup finalisasi expired attempt, security ingest flush, cohort index rebuild, login readiness warm queue, preflight, auto-warm availability, adaptive load, dan snapshot auto-heal queue.
- Semua worker dirancang idempotent dan memakai lock agar tidak berjalan paralel tanpa kontrol. Pastikan WP-Cron berjalan (idealnya lewat cron sistem, lihat `INSTALL-NGINX-PHP-FPM.md`).

## Konfigurasi `wp-config.php`

Semua konstanta berikut opsional. Contoh lengkap untuk produksi ada di [`INSTALL-NGINX-PHP-FPM.md`](INSTALL-NGINX-PHP-FPM.md).

| Konstanta | Fungsi |
| --- | --- |
| `CBT_JWT_SECRET` | Secret penandatangan JWT. Fallback ke `wp_salt('auth')`. Sangat disarankan diisi string acak panjang di produksi. |
| `CBT_ENCRYPTION_KEY` | Kunci enkripsi password siswa yang disimpan. Fallback ke `wp_salt('auth')` / `AUTH_KEY`. Jika kunci atau salt berubah, password tersimpan tidak bisa didekripsi lagi. |
| `WP_REDIS_*` | Konfigurasi Redis object cache (plugin Redis Object Cache). |
| `CBT_RUNTIME_REDIS_HOST`, `_PORT`, `_DATABASE`, `_PASSWORD`, `_PREFIX`, `_TIMEOUT` | Redis untuk runtime buffer jawaban, snapshot, dan presence pengawas. Host yang diawali `/` dianggap Unix socket. |
| `CBT_RUNTIME_BUFFER_ENABLED`, `CBT_RUNTIME_BUFFER_FALLBACK_TO_DB` | Mengaktifkan runtime buffer Redis dan fallback ke database. |
| `CBT_REDIS_HOST`, `_PORT`, `_DATABASE`, `_PASSWORD` | Redis untuk gate `start_attempt`. Catatan: login snapshot dan metrik operasional membaca **environment variable** `CBT_REDIS_HOST`, `CBT_REDIS_PORT`, `CBT_REDIS_DB`, `CBT_REDIS_PASSWORD`, `CBT_REDIS_TIMEOUT` (bukan konstanta), dengan default Redis lokal. |
| `CBT_RUNTIME_SETTINGS` | Override setting runtime (array). |
| `CBT_EXAM_FRONTEND_DEV_SERVER` | Paksa frontend membaca asset dari Vite dev server, misalnya `http://127.0.0.1:5173`. |
| `CBT_RAW_QUESTIONS_DISABLED` | Mematikan jalur pengambilan soal mentah. |
| `CBT_CACHE_NAMESPACE_PRUNE_RETENTION` | Retensi pruning namespace cache. |

## Instalasi dan Setup Lokal

Untuk instalasi server produksi, ikuti [`INSTALL-NGINX-PHP-FPM.md`](INSTALL-NGINX-PHP-FPM.md).

### 1. Siapkan dependency

Jalankan dari root plugin:

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
composer install
npm install
npm run build
```

- `composer install` memasang dependency PHP: `firebase/php-jwt`, `phpoffice/phpspreadsheet`, serta PHPUnit, Brain Monkey, dan Mockery untuk test
- `npm install` memasang Vite, Vitest, Playwright, KaTeX, Chart.js, dan font package
- `npm run build` membuat asset produksi di `public/build/` lalu menjalankan `bin/check-frontend-budgets.mjs`; build gagal jika bundle login/CSS awal melewati budget

### 2. Aktivasi plugin di WordPress

Saat plugin diaktifkan:

- tabel CBT dibuat/dimigrasi (skema referensi ada di `sql/cbt_schema.sql`)
- role dan capability diregistrasikan
- halaman `cbt-ujian` dan `pengawas` dipastikan tersedia
- runtime admin, frontend, dan REST di-bootstrap saat `plugins_loaded`

### 3. Pahami peran `public/build/manifest.json`

`public/build/manifest.json` adalah manifest asset produksi yang dibaca WordPress saat mode frontend `Production Build`. Source yang diedit ada di `src/`, hasil build harus ada di `public/build/`, dan tanpa manifest yang valid asset frontend tidak bisa di-resolve.

## Workflow Development

### Struktur frontend Vite

Vite membangun tiga entry:

| Entry | Source | Dipakai di |
| --- | --- | --- |
| `frontend` | `src/frontend/main.js` | Frontend siswa dan dashboard pengawas |
| `adminMath` | `src/admin/math-main.js` | Editor rumus di admin |
| `adminAnalytics` | `src/admin/analytics-main.js` | Grafik di `CBT Analytics` |

Struktur `src/frontend/app/`:

| Folder | Isi |
| --- | --- |
| `core/` | API client, sesi auth, bootstrap, lifecycle, security logging, idle detection, service worker, heartbeat |
| `shell/` | Bootstrap shell siswa |
| `stages/` | Stage login, konfirmasi, ujian, review, dan result |
| `exam/` | Render soal, input jawaban, navigasi, sinkronisasi jawaban, finish flow, prewarm media |
| `storage/` | Antrian jawaban, cache soal, cache API read-only, state ragu-ragu dan UI attempt |
| `supervisor/` | Runtime dashboard pengawas |
| `features/` | Kalkulator |

`src/shared/` berisi render matematika yang dipakai admin dan frontend. Stylesheet frontend ada di `src/frontend/styles/`.

### Mode asset frontend

| Mode | Label | Fungsi |
| --- | --- | --- |
| `build` | `Production Build` | Default. Asset dibaca dari `public/build/manifest.json`. Service worker hanya aktif di mode ini. |
| `dev` | `Vite Dev Server` | Asset dibaca langsung dari Vite dev server. Diaktifkan lewat `CBT_EXAM_FRONTEND_DEV_SERVER` atau panel `CBT Developer`. |
| `stable` | `Stable Test Mode` | Mode asset untuk kebutuhan test/internal tooling. |

Jika mode `dev` aktif tetapi Vite dev server mati, frontend **tidak** auto-fallback ke build produksi.

Script `bin/cbt-vite-dev` dan `bin/cbt-vite-build-watch` membantu menjalankan Vite dev server / build watch di background (dengan file PID dan log di direktori temp).

### Command development dan test

```bash
# Frontend
npm run dev
npm run build
npm run build:watch

# Test JavaScript
npm run test:js
npm run test:js:watch
npm run test:js:coverage

# Test PHP
composer test:php

# Test E2E Playwright
npm run playwright:install:chromium
npm run test:e2e:check
npm run test:e2e
npm run test:e2e:headed
npm run test:e2e:ui
npm run test:e2e:recovery
```

Untuk Playwright, set `CBT_E2E_BASE_URL` ke root WordPress lokal. `test:e2e`, `test:e2e:headed`, dan `test:e2e:ui` menjalankan readiness check lebih dulu (URL login WordPress, halaman CBT, dan fixture Bulk Test Data). Jika halaman CBT bukan homepage, set `CBT_E2E_FRONTEND_URL`. `CBT_E2E_WP_BASE_URL` dapat dipakai untuk override root WordPress admin. Readiness check bisa dilewati dengan `CBT_E2E_SKIP_READINESS_CHECK=1` atau argumen `--skip-readiness`.

```bash
CBT_E2E_BASE_URL=http://localhost/wordpress npm run test:e2e
CBT_E2E_BASE_URL=http://localhost/wordpress npm run test:e2e:check
CBT_E2E_BASE_URL=http://localhost/wordpress CBT_E2E_FRONTEND_URL=http://localhost/wordpress/ujian npm run test:e2e
CBT_E2E_SKIP_READINESS_CHECK=1 npm run test:e2e -- new-question-types.spec.js
```

### Release plugin ke GitHub

Release resmi dibuat dari tag Git, dijalankan dari root folder plugin (folder yang berisi `package.json`, `cbt-exam-system.php`, dan `.git`):

```bash
npm run release:check                 # build + validasi isi paket release
npm run release:plugin -- 3.2.8 --dry-run
npm run release:plugin -- 3.2.8
npm run release:plugin -- 3.2.8 --notes "Ringkasan perubahan"
npm run release:plugin -- 3.2.8 --notes-file CHANGELOG.md
```

`release:plugin` memastikan branch `main`, worktree bersih, remote `origin` tersedia, versi baru lebih tinggi dari versi saat ini, dan tag belum ada. Jika valid, script memperbarui versi di `cbt-exam-system.php`, membuat commit `chore(release): vX.Y.Z`, membuat annotated tag, lalu push branch dan tag.

Setelah tag ter-push, workflow `.github/workflows/release-plugin.yml` menjalankan `npm ci`, `npm run build`, `npm run release:package-check`, `composer install --no-dev`, lalu membuat `cbt-exam-system.zip` dan `cbt-update-manifest.json` dan mengunggahnya ke GitHub Release. Kedua file inilah yang dibaca menu `CBT Update` di server sekolah.

## Testing dan Fixture Lokal/Dev

Kredensial berikut hanya untuk dataset seed lokal/dev dari `CBT Maintenance`. Jangan dipakai sebagai akun produksi.

- password default dataset: `Skills39`
- akun test siswa khusus: `coblax` / `223611`
- akun test admin khusus: `cbtadmin` / `223611`

Lokasi test di repo:

- `tests/php/` untuk bootstrap dan unit test PHP
- `tests/js/unit/` untuk unit test frontend, dengan setup di `tests/js/setup/vitest.setup.js`
- `tests/e2e/` untuk spec E2E, runner flow (`run-*.mjs`), helper, dan fixture
- `playwright.config.js` dan `phpunit.xml.dist` untuk konfigurasi runner

## Struktur Repo Ringkas

| Path | Peran |
| --- | --- |
| `cbt-exam-system.php` | Bootstrap plugin, include class utama, hook aktivasi/deaktivasi, dan init runtime |
| `admin/` | Page, service, action, helper, dan view panel admin (lihat `admin/README.md`) |
| `includes/` | Runtime inti: auth/JWT, REST, frontend bridge, cache, Redis, worker background, supervisor, updater, AI grading |
| `src/frontend/` | Source frontend siswa dan pengawas |
| `src/admin/`, `src/shared/` | Asset admin (editor rumus, analytics) dan render matematika bersama |
| `public/build/` | Hasil build Vite yang dibaca WordPress |
| `templates/` | Template halaman frontend dan template impor (DOCX soal, CSV user) |
| `sql/cbt_schema.sql` | Referensi skema tabel CBT |
| `bin/` | Script release, cek paket, cek budget frontend, runner E2E, dan helper Vite |
| `tests/` | Test PHP, JS, dan E2E |
| `performance/` | Panduan tuning dan script load test `k6` |
| `.github/workflows/` | Workflow build dan publikasi release |

## Catatan Implementasi dan Batasan

- Halaman frontend kanonik mengirim header no-cache; jangan cache halaman `cbt-ujian`, `pengawas`, atau REST `cbt/v1` di CDN/reverse proxy.
- Mode `dev` tidak auto-fallback ke `Production Build` saat dev server gagal.
- Redis opsional; tanpa Redis plugin tetap berjalan dengan fallback cache dan MySQL, tetapi kapasitas ujian serentak lebih rendah.
- Mengganti `CBT_ENCRYPTION_KEY` atau salt WordPress membuat password siswa tersimpan tidak bisa dibaca untuk dicetak; reset password siswa setelahnya.
- README ini hanya mendokumentasikan fitur yang terkonfirmasi dari source repo.

## Quick Start Singkat

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
composer install
npm install
npm run build
composer test:php
npm run test:js
```

Lalu:

1. aktifkan plugin di WordPress
2. atur identitas sekolah di `CBT Branding`
3. isi subject, user, dan bank soal, lalu buat exam di `CBT Exams`
4. buka halaman `cbt-ujian` untuk siswa dan `pengawas` untuk guru pengawas
5. bila ingin develop frontend, pindah ke `Vite Dev Server` di `CBT Developer` atau set `CBT_EXAM_FRONTEND_DEV_SERVER`
