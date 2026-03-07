# CBT Exam System (WordPress Plugin)

## Features

- Custom tables: `wp_cbt_subjects`, `wp_cbt_exams`, `wp_cbt_questions`, `wp_cbt_options`, `wp_cbt_attempts`, `wp_cbt_answers`, `wp_cbt_question_multiple_choice`, `wp_cbt_question_multiple_answer`, `wp_cbt_question_true_false`, `wp_cbt_question_short_answer`, `wp_cbt_question_essay`
- Roles: `teacher`, `student`
- Admin menu: Exams, Subjects / Mata Pelajaran, Questions / Pertanyaan, Q: Multiple Choice, Q: Multiple Answer, Q: True/False, Q: Short Answer, Q: Essay, Results, User Import
- REST API: login, list subjects, list exams, list questions, start attempt, submit answer, finish exam, result
- JWT auth via `firebase/php-jwt`
- XLSX parser via `phpoffice/phpspreadsheet`
- Random question order per attempt
- Auto scoring: `multiple_choice`, `multiple_answer`, `true_false`, `short_answer`
- Essay manual grading from Results page
- Bulk import users from CSV/XLSX (Excel-compatible template)
- Bulk import subjects from CSV/XLSX (Excel-compatible template)
- Input soal manual bertab per jenis (multiple choice, multiple answer, true/false, short answer, essay), fokus Subject + Jenis Soal
- Submenu `Q:*` untuk mode khusus per jenis soal; list, input manual, dan import otomatis terkunci ke tipe submenu aktif
- Manual input constraints: multiple choice maks 5 opsi (1 jawaban benar), multiple answer maks 12 opsi (bisa multi jawaban), true/false dropdown, essay punya kolom jawaban/acuan
- Soal, opsi jawaban, dan acuan essay bisa menampung gambar (paste/upload) via WordPress editor
- Saat tab jenis soal diklik, panel cara input jawaban otomatis terseleksi sesuai jenisnya
- Bulk import questions from CSV/XLSX/DOCX (Word) with multi-row template support

## Installation

1. Copy folder `cbt-exam-system` to `wp-content/plugins/`.
2. Install dependencies:

```bash
cd wp-content/plugins/cbt-exam-system
composer install --no-dev
```

For XLSX import support, ensure PHP extensions `zip`, `xml`, `mbstring`, and `gd` are enabled.

If dependency was installed before this update, run:

```bash
composer require phpoffice/phpspreadsheet:^2.2 --update-no-dev
```

3. Activate plugin from WordPress Admin.
4. Ensure your app sends Authorization header:

```text
Authorization: Bearer <jwt_token>
```

## Redis Object Cache Setup

Redis object cache tetap memakai jalur standar WordPress untuk cache baca lintas request. Di luar itu, plugin CBT sekarang juga bisa membuka koneksi Redis runtime terpisah khusus buffering jawaban aktif. Jika salah satu jalur Redis belum aktif, plugin tetap fallback ke path yang lebih aman.

Untuk mode ujian serentak beban tinggi, plugin sekarang juga mendukung **CBT runtime Redis** terpisah untuk buffer jawaban aktif. Runtime Redis ini tidak menggantikan MySQL sebagai source of truth final; ia hanya menahan burst write sebelum flush ke tabel `wp_cbt_answers`.

Recommended production steps:

1. Install and start Redis service on the WordPress host or on a reachable Redis host.
2. Install PHP Redis client/extension yang didukung environment Anda.
3. Install dan aktifkan plugin/drop-in Redis Object Cache WordPress sampai file `wp-content/object-cache.php` tersedia.
4. Tambahkan konfigurasi Redis di `wp-config.php`:

```php
define('WP_CACHE', true);
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_DATABASE', 1);
define('WP_REDIS_PREFIX', 'cbt_exam_system:');

// Optional jika Redis memakai password:
define('WP_REDIS_PASSWORD', 'replace-with-your-redis-password');
```

Ubuntu quick start:

```bash
sudo apt update
sudo apt install -y redis-server redis-tools php-redis
sudo systemctl enable --now redis-server
redis-cli ping
php -m | grep -i redis

# Deteksi versi PHP CLI aktif lalu restart PHP-FPM jika unitnya ada:
PHP_VER=$(php -v | head -n 1 | sed -E 's/^PHP ([0-9]+\.[0-9]+).*/\1/')
echo "Detected PHP version: ${PHP_VER}"
if systemctl list-unit-files "php${PHP_VER}-fpm.service" --no-legend 2>/dev/null | grep -q "php${PHP_VER}-fpm.service"; then
  sudo systemctl restart "php${PHP_VER}-fpm"
else
  echo "Service php${PHP_VER}-fpm tidak ditemukan. Cek unit PHP-FPM yang tersedia:"
  systemctl list-unit-files "php*-fpm.service" --no-legend 2>/dev/null || true
  echo "Jika kosong, kemungkinan server ini tidak memakai PHP-FPM."
fi
sudo systemctl restart nginx || sudo systemctl restart apache2
```

Notes:

- `WP_REDIS_DATABASE` sebaiknya dipisah dari aplikasi WordPress lain.
- `WP_REDIS_PREFIX` sebaiknya unik per site untuk mencegah collision key.
- Pastikan `WP_REDIS_DISABLED` tidak bernilai `true`.
- Fallback transient tetap didukung, tetapi tidak direkomendasikan untuk ujian serentak dengan trafik tinggi.

## CBT Runtime Redis

CBT runtime Redis memakai konstanta terpisah. Jika tidak diisi, host/port/password akan mengikuti `WP_REDIS_*`, sementara database default akan memakai `WP_REDIS_DATABASE + 1`.

Tambahkan di `wp-config.php` bila ingin mengaktifkan jalur batch write:

```php
define('CBT_RUNTIME_BUFFER_ENABLED', true);
define('CBT_RUNTIME_BUFFER_FALLBACK_TO_DB', true);
define('CBT_RUNTIME_REDIS_HOST', '127.0.0.1');
define('CBT_RUNTIME_REDIS_PORT', 6379);
define('CBT_RUNTIME_REDIS_DATABASE', 2);
define('CBT_RUNTIME_REDIS_PREFIX', 'cbt_runtime:');

// Optional:
define('CBT_RUNTIME_REDIS_PASSWORD', 'replace-with-your-runtime-password');
```

Recommended Redis runtime policy:

- `appendonly yes`
- `appendfsync everysec`
- `maxmemory-policy noeviction`

Verification:

1. Buka `CBT Exams > CBT Cache`.
2. Jika ingin menyiapkan sisi WordPress lebih cepat, gunakan tombol `Bootstrap Redis Sekali Klik` pada halaman tersebut. Tombol ini akan mencoba menulis config Redis ke `wp-config.php`, install/activate plugin `Redis Object Cache`, dan enable `object-cache.php` jika server Redis sudah reachable dan permission filesystem WordPress mengizinkan.
3. Jika ingin membatalkan integrasi Redis dari sisi WordPress, gunakan tombol `Batalkan Redis Sekali Klik` pada halaman yang sama. Tombol ini akan menghapus blok config Redis milik CBT, menghapus `object-cache.php` hanya jika itu drop-in Redis yang valid, lalu menonaktifkan plugin `Redis Object Cache`.
4. Tombol rollback tidak mematikan service Redis OS. Jika service Redis juga ingin dimatikan, lakukan dari server secara manual.
5. Pastikan `Readiness = ready`.
6. Pastikan `Backend Hint = redis`.
7. Pastikan `Probe Status = passed`.
8. Pastikan warning fallback tidak lagi muncul di halaman admin.

## REST Endpoints

- `POST /wp-json/cbt/v1/login`
- `GET /wp-json/cbt/v1/subjects`
- `GET /wp-json/cbt/v1/exams`
- `GET /wp-json/cbt/v1/questions?exam_id=<id>&attempt_id=<id_optional>`
- `POST /wp-json/cbt/v1/start_attempt`
- `POST /wp-json/cbt/v1/submit_answer`
- `POST /wp-json/cbt/v1/submit_answers_batch`
- `POST /wp-json/cbt/v1/finish_exam`
- `GET /wp-json/cbt/v1/result?attempt_id=<id_optional>`

## Notes

- Set a custom JWT secret in `wp-config.php` for production:

```php
define('CBT_JWT_SECRET', 'replace-with-a-random-long-secret');
```

- If `CBT_JWT_SECRET` is not set, plugin uses `wp_salt('auth')`.

## Performance & Load Test

- Paket tuning server + load test web tersedia di folder:
  - `performance/README.md`
  - `performance/server-tuning-2core-8gb.md`
  - `performance/load-test/k6/`

## Bulk User Import (Excel/CSV)

1. Open `CBT Exams > User Import`.
2. Click `Download Template CSV` atau `Download Template XLSX`.
3. Fill the template in Excel.
4. Upload file dari halaman yang sama (CSV atau XLSX).

Supported format:

- `.csv` (delimiter comma `,` atau semicolon `;`)
- `.xlsx`

Required columns:

- `name`
- `email`
- `username`
- `password`
- `role` (`student` or `teacher`)

## Bulk Subject Import (Excel/CSV)

1. Open `CBT Exams > Subjects / Mata Pelajaran`.
2. Click `Download Template CSV` atau `Download Template XLSX`.
3. Fill data subject.
4. Upload file dari halaman yang sama (CSV atau XLSX).

Required columns:

- `name` (minimal)
- `code` (optional)
- `description` (optional)

## Bulk Question Import (Excel/Word/CSV)

1. Open `CBT Exams > Questions / Pertanyaan` (semua tipe) atau submenu `CBT Exams > Q:*` untuk tipe tertentu.
2. On tab `Import Excel / Word`, pilih tipe soal (atau otomatis terkunci jika dari submenu `Q:*`), lalu pilih `Subject` dan `Exam` utama.
3. Download template `CSV`, `XLSX`, atau `Word (.docx)`.
4. Isi template (bisa banyak soal sekaligus, termasuk banyak soal dengan jenis yang sama).
5. Upload file dan klik `Import Questions`.

Catatan format Word (`.docx`):

- Default template Word dipakai untuk `multiple_choice`.
- Opsi maksimal `5` (`PILIHAN_1` sampai `PILIHAN_5`).
- `JAWABAN` diisi nomor opsi (`1`..`5`).
- Gambar bisa ditempel langsung di blok soal, lalu otomatis ikut ke `question_text` saat import.
- Untuk tab selain `Multiple Choice`, file yang didukung hanya `CSV/XLSX`.

Kolom template:

- `subject_code` (optional, jika kosong pakai subject/exam utama)
- `exam_title` (optional, jika kosong pakai exam utama)
- `question_type` (required)
- `question_text` (required)
- `points` (optional, default `1`)
- `options` (untuk pilihan ganda, pisahkan opsi dengan `||`)
- `correct_answer` (contoh: `A`, `A,C`, `true`)
- `correct_text` (untuk short answer)
