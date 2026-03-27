# CBT Exam System

Plugin WordPress untuk ujian CBT berbasis web dengan panel admin lengkap, frontend siswa berbasis Vite, REST API berbasis JWT di namespace `cbt/v1`, serta toolkit operasional untuk maintenance, cache, testing, dan load test.

README ini ditulis sebagai dokumen onboarding utama repo: cukup untuk memahami gambaran produk, setup lokal, workflow frontend, pengujian, dan area operasional penting tanpa harus membaca seluruh source terlebih dahulu.

## Kompatibilitas Minimum

- WordPress `6.0+`
- PHP `8.0+`
- Node.js `20+`
- Composer `2+`
- MySQL/MariaDB yang kompatibel dengan WordPress
- Redis bersifat opsional, dengan fallback ke transient/object cache WordPress bila tidak aktif

## Ringkasan Fitur

### 1. Admin akademik

- Menu admin untuk `CBT Exams`, `CBT Branding`, `CBT Security`, `CBT Subjects`, `CBT Users`, `CBT Questions`, `CBT Tokens`, `CBT Exam Cards`, `CBT Results`, `CBT Analytics`, `CBT Report Exam`, `CBT Test Hub`, `CBT Cache`, `CBT Maintenance`, dan `CBT Developer`
- Builder exam dengan status `draft` / `published` / `closed`, jadwal mulai-selesai, target kelas, token exam, randomisasi soal, dan randomisasi opsi
- CRUD subject, user, dan bank soal dari dashboard WordPress
- Monitoring hasil, grading essay manual, analytics, kartu ujian, dan report exam siap cetak

### 2. Frontend siswa

- Shortcode publik `[cbt_exam_frontend]`
- Halaman frontend kanonik dengan slug `cbt-ujian` yang dijaga otomatis oleh plugin
- Login siswa memakai `email`, `username`, atau `NISN`
- Start attempt baru atau `resume` attempt lama
- Timer ujian, autosave jawaban, batch submit, state resume, review, dan result
- Frontend dibangun dari `src/frontend/` dengan hasil produksi di `public/build/`

### 3. Security dan observability

- Opsi fullscreen wajib saat ujian
- Opsi blok `copy`, `cut`, dan `paste`
- Security log untuk event ujian
- Idle detection yang dapat dikonfigurasi dari menu CBT Security
- Event log frontend/server untuk skenario seperti `fullscreen_exit`, `tab_hidden`, `window_blur`, `page_leave`, `clipboard_blocked`, dan event sesi terkait ujian

### 4. Maintenance, cache, dan performa

- Reset database CBT
- Generator dataset uji dengan preset `Small`, `Medium`, dan `Large`
- Export pool siswa untuk load test
- Load test `k6` dari area maintenance
- Cache namespace, lock inspection, UI-state inspection, dan readiness Redis/object cache
- Fallback cache tetap berjalan walau Redis tidak aktif

### 5. Testing dan QA

- `PHPUnit` untuk unit/integration test PHP
- `Vitest` untuk unit test frontend JavaScript
- `Playwright` untuk flow E2E
- `CBT Test Hub` untuk mengelola unit checklist dan flow-check job dari panel admin
- Folder `performance/` berisi performance pack web dan script `k6`

## Interface Publik yang Perlu Diketahui

### Shortcode dan halaman frontend

- Shortcode utama: `[cbt_exam_frontend]`
- Halaman kanonik frontend: `cbt-ujian`
- Halaman kanonik memakai template minimal plugin agar shell CBT tampil lebih awal
- Shortcode pada halaman lain tetap bisa dipakai sebagai fallback

### REST API

Namespace REST publik plugin adalah `cbt/v1`.

Endpoint utama yang diregistrasikan saat ini:

- `POST /cbt/v1/login`
- `POST /cbt/v1/logout`
- `GET /cbt/v1/session`
- `GET /cbt/v1/exams`
- `GET /cbt/v1/subjects`
- `GET /cbt/v1/questions`
- `POST /cbt/v1/start_attempt`
- `POST /cbt/v1/submit_answer`
- `POST /cbt/v1/submit_answers_batch`
- `POST /cbt/v1/finish_exam`
- `POST /cbt/v1/security_event`
- `GET|POST|DELETE /cbt/v1/ui_state`
- `GET /cbt/v1/result`

### Mode asset frontend

Plugin mengenal tiga sumber asset frontend:

| Mode | Label | Fungsi |
| --- | --- | --- |
| `build` | `Production Build` | Mode default. WordPress membaca asset produksi dari `public/build/manifest.json`. |
| `dev` | `Vite Dev Server` | WordPress membaca asset langsung dari Vite dev server. Bisa diaktifkan lewat constant atau panel admin Developer. |
| `stable` | `Stable Test Mode` | Mode asset source yang dikenali service developer untuk kebutuhan test/internal tooling. |

## Instalasi dan Setup Lokal

### 1. Siapkan dependency

Jalankan dari root plugin ini:

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
composer install
npm install
npm run build
```

Catatan:

- `composer install` memasang dependency PHP seperti JWT, PhpSpreadsheet, PHPUnit, Brain Monkey, dan Mockery
- `npm install` memasang dependency frontend/testing seperti Vite, Vitest, Playwright, dan font package
- `npm run build` membuat asset produksi yang akan dibaca WordPress dari `public/build/`

### 2. Aktivasi plugin di WordPress

Setelah plugin diaktifkan:

- migrasi tabel CBT dijalankan
- role/capability diregistrasikan
- halaman frontend CBT dipastikan tersedia
- runtime admin, frontend, dan REST plugin akan di-bootstrap saat `plugins_loaded`

### 3. Pahami peran `public/build/manifest.json`

File `public/build/manifest.json` adalah manifest asset produksi yang dibaca WordPress saat mode frontend berada di `Production Build`.

Implikasinya:

- source yang diedit ada di `src/frontend/`
- hasil build harus ada di `public/build/`
- tanpa manifest yang valid, asset produksi frontend tidak bisa di-resolve dengan benar

## Workflow Development

### Struktur frontend Vite

- entry utama frontend: `src/frontend/main.js`
- source aplikasi frontend: `src/frontend/app/`
- stylesheet frontend: `src/frontend/styles/`
- hasil build produksi: `public/build/`

### Menyalakan Vite dev server

Ada dua cara:

1. Lewat constant di `wp-config.php`

```php
define('CBT_EXAM_FRONTEND_DEV_SERVER', 'http://127.0.0.1:5173');
```

2. Lewat panel admin `CBT Developer`

Catatan penting:

- jika mode `dev` aktif tetapi Vite dev server mati, frontend tidak auto-fallback ke build production
- mode `dev` dipakai untuk pengembangan aktif
- mode `build` dipakai untuk perilaku produksi

### Command development dan test

Command berikut berasal dari `package.json` dan `composer.json` repo ini.

#### Frontend build/dev

```bash
npm run dev
npm run build
npm run build:watch
```

#### Test JavaScript

```bash
npm run test:js
npm run test:js:watch
npm run test:js:coverage
```

#### Test PHP

```bash
composer test:php
```

#### Test E2E Playwright

```bash
npm run playwright:install:chromium
npm run test:e2e
npm run test:e2e:headed
npm run test:e2e:ui
npm run test:e2e:recovery
```

Untuk Playwright, set `CBT_E2E_BASE_URL` bila URL WordPress lokal Anda bukan default `http://localhost/wordpress`.

Contoh:

```bash
CBT_E2E_BASE_URL=http://localhost/wordpress npm run test:e2e
```

## Peta Menu Admin

| Menu | Fungsi utama |
| --- | --- |
| `Introduction` | Ringkasan awal area plugin dan onboarding admin |
| `CBT Exams` | Builder exam, jadwal, target kelas, randomisasi, token exam, dan manajemen exam |
| `CBT Branding` | Branding sekolah, logo, dan identitas visual CBT |
| `CBT Security` | Pengaturan security ujian dan observability/security log |
| `CBT Subjects` | CRUD subject dan import subject |
| `CBT Users` | CRUD user, import user, foto, filter, dan manajemen akun |
| `CBT Questions` | Bank soal, import template, preview, sinkronisasi, dan editing multi-tipe |
| `CBT Tokens` | Token ujian/global token management |
| `CBT Exam Cards` | Cetak kartu ujian |
| `CBT Results` | Monitoring attempt, review jawaban, grading essay, dan aksi operasional hasil |
| `CBT Analytics` | Analitik hasil ujian dan insight attempt |
| `CBT Report Exam` | Report exam siap cetak / PDF |
| `CBT Test Hub` | Unit checklist, smoke-flow, dan flow-check job |
| `CBT Cache` | Readiness cache, namespace invalidation, lock/UI-state inspection, dan Redis bootstrap/rollback |
| `CBT Maintenance` | Reset database, seed test data, export siswa load test, dan runner `k6` |
| `CBT Developer` | Pengaturan source asset frontend, health dev server, dan alat bantu developer |

## Kemampuan Operasional Penting

### Import/export dan template

- Import user mendukung `CSV` dan `XLSX`
- Template user dapat diunduh dari admin
- Import soal mendukung `CSV`, `XLSX`, dan `DOCX`
- Template soal tersedia untuk beberapa format Word dan spreadsheet

### Enam tipe soal yang didukung

Plugin saat ini mendukung tepat 6 tipe soal:

1. `multiple_choice`
2. `multiple_answer`
3. `true_false`
4. `true_false_matrix`
5. `short_answer`
6. `essay`

### Exam, token, hasil, dan cetak

- exam mendukung schedule window, status, target kelas, randomisasi, dan kalkulator
- token exam tersedia dari area token/admin exam
- essay diperiksa manual dari area results
- report exam dan exam card tersedia sebagai output print-ready

### Cache, Redis, dan performance pack

- sistem cache plugin memakai namespace invalidation
- plugin dapat berjalan di mode persistent object cache atau transient fallback
- area `CBT Cache` membantu cek readiness Redis/object cache
- folder `performance/` berisi panduan tuning server web dan script `k6`
- maintenance juga menyediakan runner load test berbasis `k6` dari panel admin

## Testing dan Fixture Lokal/Dev

Bagian ini sengaja eksplisit untuk memudahkan QA dan pengembangan lokal. Jangan perlakukan kredensial berikut sebagai akun produksi.

### Kredensial seed data yang terverifikasi

- password default dataset: `Skills39`
- akun test siswa khusus: `coblax` / `223611`
- akun test admin khusus: `cbtadmin` / `223611`

Kredensial ini berasal dari service maintenance plugin dan dipakai untuk alur seed/test fixture lokal.

### Area test di repo

- `tests/php/` untuk bootstrap dan unit test PHP
- `tests/js/unit/` untuk unit test frontend
- `tests/js/setup/vitest.setup.js` untuk setup Vitest
- `tests/e2e/` untuk flow E2E dan helper fixture
- `playwright.config.js` untuk konfigurasi runner Playwright
- `phpunit.xml.dist` untuk konfigurasi PHPUnit

## Struktur Repo Ringkas

| Path | Peran |
| --- | --- |
| `cbt-exam-system.php` | Bootstrap plugin, include class utama, hook aktivasi/deaktivasi, dan init runtime |
| `admin/` | Page, service, action, view, dan logic panel admin |
| `includes/` | Runtime inti plugin seperti auth, REST, frontend bridge, cache, UI state, activator, dan deactivator |
| `src/frontend/` | Source frontend Vite untuk experience siswa |
| `tests/` | Test PHP, JS, E2E, dan helper fixture |
| `performance/` | Dokumen tuning performa dan artefak/script load test |
| `templates/` | Template frontend dan template file import/download |

## Catatan Implementasi dan Batasan

- frontend kanonik mengirim no-cache behavior agar halaman ujian tidak mudah ter-cache
- mode `dev` tidak auto-fallback ke `Production Build` saat dev server gagal
- Redis bersifat opsional; plugin tetap punya fallback cache
- `README.md` ini hanya mendokumentasikan fitur yang terkonfirmasi dari source repo saat ini
- dokumen ini sengaja fokus pada onboarding teknis dan operasional, bukan materi marketing

## Quick Start Singkat

Kalau Anda baru masuk ke repo ini dan ingin cepat jalan:

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
2. buka menu `CBT Exams`
3. cek halaman frontend `cbt-ujian`
4. bila ingin develop frontend aktif, pindah ke `Vite Dev Server` atau set `CBT_EXAM_FRONTEND_DEV_SERVER`
