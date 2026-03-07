# CBT Exam System

Plugin WordPress untuk ujian CBT berbasis web. Plugin ini menyediakan panel admin untuk mengelola mata pelajaran, bank soal, ujian, token, peserta, hasil, perawatan sistem, dan cache; serta antarmuka ujian berbasis JavaScript untuk peserta dengan autentikasi JWT.

Versi plugin saat ini: `1.5.0`

## Ringkasan Fitur

- Panel admin WordPress untuk:
  - mata pelajaran
  - ujian
  - token ujian
  - bank soal
  - hasil ujian
  - import user
  - cache dan Redis
  - maintenance/reset data
- Halaman ujian otomatis dibuat sebagai halaman `CBT Ujian` dengan slug `/cbt-ujian`.
- Shortcode halaman ujian: `[cbt_exam_frontend]`.
- Login dengan `email`, `username`, atau `NISN`.
- Satu sesi login aktif per akun untuk mencegah login ganda bersamaan.
- JWT untuk autentikasi API.
- Token ujian per exam dan token global 6 karakter dengan rotasi otomatis.
- Opsi token global bisa diisi otomatis di halaman ujian saat siswa mulai ujian.
- Dukungan 6 jenis soal:
  - `multiple_choice`
  - `multiple_answer`
  - `true_false`
  - `true_false_matrix`
  - `short_answer`
  - `essay`
- Penilaian otomatis untuk:
  - pilihan ganda
  - multiple answer
  - benar/salah
  - true/false matrix
  - short answer
- Essay diperiksa manual dari halaman hasil.
- Ujian mendukung:
  - durasi
  - status `draft`, `published`, `closed`
  - jadwal mulai dan selesai
  - target kelas
  - acak urutan soal
- Soal ujian dapat dipilih dari bank soal yang sudah ada dan dipreview sebelum disimpan.
- Import massal:
  - user dari `CSV/XLSX`
  - mata pelajaran dari `CSV/XLSX`
  - soal dari `CSV/XLSX/DOCX`
- Template Word untuk semua tipe soal dengan dukungan tempel gambar langsung ke dokumen.
- Antarmuka peserta mendukung:
  - autosave jawaban bertahap
  - submit batch
  - melanjutkan attempt
  - timer dan auto-finish saat waktu habis
  - penandaan soal ragu-ragu
  - penyimpanan state tampilan per attempt
  - preferensi tampilan lokal
  - kalkulator bawaan
- Sistem cache namespace untuk katalog, user, attempt, dan UI state.
- Integrasi Redis Object Cache WordPress dan Redis runtime buffer untuk beban ujian tinggi.
- Paket tuning performa dan load test `k6` sudah disediakan di folder `performance/`.

## Tabel Database

Plugin membuat tabel berikut saat aktivasi:

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

- `true_false_matrix` tidak memakai tabel detail terpisah; konfigurasi matrix disimpan pada payload soal.
- Plugin juga memastikan role, capability, dan halaman ujian tetap sinkron setiap plugin dimuat.

## Role dan Hak Akses

Plugin menyiapkan role kompatibel berikut:

- `guru_cbt`
- `siswa_cbt`
- `teacher`
- `student`

Capability utama:

- Admin:
  - kelola sistem
  - kelola user
  - kelola mata pelajaran
  - kelola ujian
  - kelola soal
  - lihat hasil
  - koreksi essay
  - ikut ujian
  - lihat hasil sendiri
- Guru:
  - kelola ujian
  - kelola soal
  - lihat hasil
  - koreksi essay
- Siswa:
  - ikut ujian
  - lihat hasil sendiri

Untuk kompatibilitas, capability juga dapat ditambahkan ke role WordPress bawaan seperti `administrator`, `editor`, dan `subscriber`.

## Persyaratan Sistem

- WordPress `6.0+`
- PHP `8.0+`
- MySQL / MariaDB
- Composer
- Ekstensi PHP untuk fitur impor spreadsheet:
  - `zip`
  - `xml`
  - `mbstring`
  - `gd`
- Ekstensi `redis` direkomendasikan jika ingin memakai runtime buffer Redis

Dependensi Composer:

- `firebase/php-jwt`
- `phpoffice/phpspreadsheet`

## Instalasi Cepat di WordPress yang Sudah Berjalan

1. Salin folder plugin ke:

```bash
wp-content/plugins/cbt-exam-system
```

2. Install dependency Composer:

```bash
cd wp-content/plugins/cbt-exam-system
composer install --no-dev
```

3. Jika instalasi lama sudah pernah memakai dependency sebelum fitur spreadsheet terbaru, jalankan:

```bash
composer require phpoffice/phpspreadsheet:^2.2 --update-no-dev
```

4. Aktifkan plugin dari dashboard WordPress.

5. Saat plugin aktif, sistem akan:
  - membuat tabel CBT
  - mendaftarkan role dan capability
  - membuat halaman frontend `CBT Ujian`
  - menambahkan shortcode `[cbt_exam_frontend]` ke halaman tersebut

6. Tambahkan JWT secret ke `wp-config.php`:

```php
define('CBT_JWT_SECRET', 'ganti-dengan-secret-panjang-acak');
```

7. Pastikan aplikasi yang mengakses API mengirim header:

```text
Authorization: Bearer <jwt_token>
```

Jika `CBT_JWT_SECRET` tidak diisi, plugin akan memakai `wp_salt('auth')` sebagai cadangan.

## Instalasi Server Ubuntu dengan Nginx, PHP-FPM, dan MySQL

Bagian ini ditujukan untuk instalasi server baru. Jika WordPress Anda sudah berjalan, Anda bisa langsung memakai bagian instalasi cepat di atas.

### 1. Instal paket server

```bash
sudo apt update
sudo apt install -y nginx mysql-server php8.3-fpm php8.3-mysql php8.3-curl php8.3-xml php8.3-mbstring php8.3-zip php8.3-gd unzip git composer
```

### 2. Siapkan database WordPress

```bash
sudo mysql -e "CREATE DATABASE wordpress_cbt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'wpuser'@'localhost' IDENTIFIED BY 'StrongPassword123';"
sudo mysql -e "GRANT ALL PRIVILEGES ON wordpress_cbt.* TO 'wpuser'@'localhost'; FLUSH PRIVILEGES;"
```

Catatan: jika WordPress dan MySQL berada di server yang sama, gunakan `'wpuser'@'localhost'`.

### 3. Pasang WordPress

```bash
cd /var/www
sudo wget https://wordpress.org/latest.zip
sudo unzip latest.zip
sudo rm -f latest.zip
sudo chown -R www-data:www-data /var/www/wordpress
```

### 4. Buat virtual host Nginx

Buat file `/etc/nginx/sites-available/cbt.domain.com`:

```nginx
server {
    listen 80;
    server_name cbt.domain.com localhost 127.0.0.1 192.168.1.10;

    root /var/www/wordpress;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Aktifkan site:

```bash
sudo rm -f /etc/nginx/sites-enabled/default
sudo ln -sfn /etc/nginx/sites-available/cbt.domain.com /etc/nginx/sites-enabled/cbt.domain.com
ls /run/php/
sudo nginx -t
sudo systemctl reload nginx
```

Jika socket `php8.3-fpm.sock` tidak ada, sesuaikan dengan socket yang tersedia, misalnya `php8.2-fpm.sock`, atau gunakan TCP:

```nginx
fastcgi_pass 127.0.0.1:9000;
```

Untuk uji lokal berbasis domain, tambahkan hosts entry:

```text
127.0.0.1 cbt.domain.com
```

### 5. Selesaikan panduan instalasi WordPress

Isi form instalasi WordPress:

- Database Name: `wordpress_cbt`
- Username: `wpuser`
- Password: `StrongPassword123`
- Database Host: `localhost`
- Table Prefix: `wp_`

### 6. Install plugin CBT

```bash
sudo mkdir -p /var/www/wordpress/wp-content/plugins
sudo cp -r ./wordpress/wp-content/plugins/cbt-exam-system /var/www/wordpress/wp-content/plugins/
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
sudo composer install --no-dev
sudo composer require phpoffice/phpspreadsheet:^2.2 --update-no-dev
sudo chown -R www-data:www-data /var/www/wordpress/wp-content/plugins/cbt-exam-system
```

Setelah itu, aktifkan plugin dari `wp-admin`.

### 7. Aktifkan HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d cbt.domain.com
```

## Alur Penggunaan yang Disarankan

1. Import user siswa dan guru terlebih dahulu.
2. Buat atau import mata pelajaran.
3. Isi bank soal secara manual atau import massal.
4. Buat ujian baru, atur:
   - mapel
   - durasi
   - status
   - jadwal
   - target kelas
   - acak soal
5. Pilih soal untuk ujian dari bank soal yang tersedia.
6. Jika perlu, atur token global dari menu `CBT Tokens`.
7. Arahkan siswa ke halaman `/cbt-ujian`.
8. Pantau progres di `CBT Results`.
9. Koreksi essay dari halaman hasil.

## Menu Admin

Menu utama plugin berada di `CBT Exams` dengan submenu:

- `Exams`
- `CBT Tokens`
- `CBT Maintenance`
- `CBT Cache`
- `Subjects / Mata Pelajaran`
- `User Import`
- `CBT Bank Soal`
- `CBT Results`

## Fitur Ujian

### Pengaturan exam

Setiap exam mendukung:

- judul
- deskripsi
- mapel
- durasi ujian
- status `draft`, `published`, `closed`
- jadwal mulai
- jadwal selesai
- target kelas peserta
- acak urutan soal

Soal exam dipilih dari bank soal yang sudah tersedia. Saat exam disimpan ulang, daftar soal akan disinkronkan dari pilihan tersebut.

### Token ujian

Plugin mendukung dua skema token:

- token per exam
- token global

Token global:

- panjang `6` karakter
- memakai huruf/angka yang tidak ambigu
- dapat dirotasi otomatis setiap `5` sampai `60` menit
- dapat digenerate ulang manual
- dapat diisi otomatis di frontend saat siswa mulai ujian

### Monitoring percobaan ujian

Halaman hasil mendukung:

- filter ujian
- filter status attempt
- filter kelas
- pencarian siswa
- pemantauan otomatis berkala
- reset login siswa
- reset attempt
- bulk reset attempt
- force complete attempt yang masih berjalan
- koreksi essay manual

## Antarmuka Peserta

Halaman ujian berbasis JavaScript dan dirender melalui shortcode `[cbt_exam_frontend]`.

Fitur yang tersedia:

- login dengan email, username, atau NISN
- penyimpanan token sesi di browser
- melanjutkan ujian yang sedang berjalan
- autosave jawaban dengan submit bertahap
- submit batch untuk mengurangi lonjakan request
- penanda soal ragu-ragu
- timer mundur
- auto-finish saat waktu habis
- state tampilan per attempt melalui endpoint `ui_state`
- preferensi UI lokal:
  - skala font
  - tema
  - posisi panel navigasi
  - posisi kalkulator
- kalkulator bawaan untuk peserta

## Jenis Soal yang Didukung

### 1. Multiple Choice

- Satu jawaban benar
- Import Word mendukung sampai `5` opsi
- Dapat memuat gambar pada soal dan opsi

### 2. Multiple Answer

- Lebih dari satu jawaban benar
- Import Word mendukung sampai `12` opsi

### 3. True/False

- Satu pernyataan dengan jawaban `true` atau `false`

### 4. True/False Matrix

- Satu soal berisi beberapa pernyataan
- Setiap pernyataan punya jawaban `true` atau `false`
- Import Word mendukung sampai `10` pernyataan per soal

### 5. Short Answer

- Mendukung placeholder `[INPUT_1]` sampai `[INPUT_8]`
- Maksimal `8` jawaban valid per soal
- Penilaian otomatis berdasarkan jawaban yang dinormalisasi

### 6. Essay

- Wajib memiliki acuan jawaban atau rubrik
- Penilaian manual dari halaman hasil
- Rubrik bisa berisi teks dan gambar

## Import Data

### Import user

Menu: `CBT Exams > User Import`

Format file:

- `CSV`
- `XLSX`

Kolom wajib:

- `name`
- `role`
- `username` + `password`
  atau
- `usernamepassword` / `username_password`
- salah satu dari:
  - `email`
  - `nisn`

Kolom opsional:

- `kode_kelas`
- `kode_ruang`
- `agama`
- `foto`

Catatan:

- Role yang didukung: `admin`, `guru`, `siswa`
- Alias lama seperti `administrator`, `teacher`, dan `student` juga ditangani
- Jika `email` kosong tetapi `nisn` ada, plugin membuat email default `nisn@student.sch.id`
- Import besar diproses bertahap untuk mengurangi timeout
- Untuk data besar, `CSV` umumnya lebih cepat daripada `XLSX`
- Selain import massal, halaman ini juga mendukung tambah, edit, hapus, filter, dan upload foto user secara manual

### Import mata pelajaran

Menu: `CBT Exams > Subjects / Mata Pelajaran`

Format file:

- `CSV`
- `XLSX`

Kolom template:

- `name`
- `code`
- `description`

Kolom `name` wajib ada.

### Import soal

Menu: `CBT Exams > CBT Bank Soal`

Format file:

- `CSV`
- `XLSX`
- `DOCX`

Header template spreadsheet:

- `subject_code`
- `exam_title`
- `question_type`
- `question_text`
- `points`
- `options`
- `correct_answer`
- `correct_text`

Tipe soal yang didukung saat import:

- `multiple_choice`
- `multiple_answer`
- `true_false`
- `true_false_matrix`
- `short_answer`
- `essay`

Fitur penting import soal:

- Template CSV dan XLSX resmi tersedia dari admin
- Template Word tersedia untuk semua tipe soal
- Template Word bisa dibuat dengan jumlah blok `10` sampai `100` soal
- Gambar yang ditempel di dokumen Word ikut terbaca saat import
- Untuk `essay`, acuan jawaban atau rubrik wajib diisi
- Untuk `short_answer`, gunakan placeholder `[INPUT_1]` sampai `[INPUT_8]`
- Untuk `true_false_matrix`, isi pasangan `PERNYATAAN_x` dan `KUNCI_x`

## Endpoint REST API

Semua endpoint selain login memerlukan header:

```text
Authorization: Bearer <jwt_token>
```

Daftar endpoint:

- `POST /wp-json/cbt/v1/login`
- `POST /wp-json/cbt/v1/logout`
- `GET /wp-json/cbt/v1/session`
- `GET /wp-json/cbt/v1/exams`
- `GET /wp-json/cbt/v1/subjects`
- `GET /wp-json/cbt/v1/questions?exam_id=<id>&attempt_id=<id_optional>`
- `POST /wp-json/cbt/v1/start_attempt`
- `POST /wp-json/cbt/v1/submit_answer`
- `POST /wp-json/cbt/v1/submit_answers_batch`
- `POST /wp-json/cbt/v1/finish_exam`
- `GET /wp-json/cbt/v1/ui_state`
- `POST /wp-json/cbt/v1/ui_state`
- `DELETE /wp-json/cbt/v1/ui_state?attempt_id=<id>`
- `GET /wp-json/cbt/v1/result?attempt_id=<id_optional>`

Endpoint yang punya perilaku penting:

- `login` menerima identifier `email`, `username`, atau `nisn`
- `start_attempt` mendukung `exam_token` dan `resume_only`
- `questions` mendukung `offset`, `limit`, `include_existing`, dan `include_answer_manifest`
- `submit_answers_batch` dipakai frontend untuk autosave dan batch submit
- `ui_state` menyimpan posisi dan state tampilan per attempt

## Cache dan Redis

Plugin memiliki dua lapisan optimasi:

- cache lintas request melalui WordPress object cache / transient
- runtime buffer Redis untuk menahan burst submit jawaban

Jika Redis belum siap:

- plugin tetap berjalan
- cache jatuh ke mode transient WordPress
- submit jawaban tetap bisa jatuh ke database

### Konfigurasi Redis Object Cache WordPress

Tambahkan ke `wp-config.php`:

```php
define('WP_CACHE', true);
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_DATABASE', 1);
define('WP_REDIS_PREFIX', 'cbt_exam_system:');

// Opsional:
define('WP_REDIS_PASSWORD', 'ganti-dengan-password-redis');
```

### Konfigurasi Redis Runtime Buffer

Tambahkan ke `wp-config.php`:

```php
define('CBT_RUNTIME_BUFFER_ENABLED', true);
define('CBT_RUNTIME_BUFFER_FALLBACK_TO_DB', true);
define('CBT_RUNTIME_REDIS_HOST', '127.0.0.1');
define('CBT_RUNTIME_REDIS_PORT', 6379);
define('CBT_RUNTIME_REDIS_DATABASE', 2);
define('CBT_RUNTIME_REDIS_PREFIX', 'cbt_runtime:');

// Opsional:
define('CBT_RUNTIME_REDIS_PASSWORD', 'ganti-dengan-password-runtime');
```

Saran kebijakan Redis runtime:

- `appendonly yes`
- `appendfsync everysec`
- `maxmemory-policy noeviction`

### Bootstrap Redis dari admin

Menu: `CBT Exams > CBT Cache`

Halaman ini menyediakan:

- status readiness Redis
- probe server Redis
- probe object cache
- status runtime buffer
- bootstrap Redis WordPress sekali klik
- rollback integrasi Redis dari sisi WordPress
- invalidate namespace cache
- inspeksi namespace, lock, dan UI state

Ubuntu quick start:

```bash
sudo apt update
sudo apt install -y redis-server redis-tools php-redis
sudo systemctl enable --now redis-server
redis-cli ping
php -m | grep -i redis

PHP_VER=$(php -v | head -n 1 | sed -E 's/^PHP ([0-9]+\.[0-9]+).*/\1/')
echo "Detected PHP version: ${PHP_VER}"
if systemctl list-unit-files "php${PHP_VER}-fpm.service" --no-legend 2>/dev/null | grep -q "php${PHP_VER}-fpm.service"; then
  sudo systemctl restart "php${PHP_VER}-fpm"
else
  systemctl list-unit-files "php*-fpm.service" --no-legend 2>/dev/null || true
fi
sudo systemctl restart nginx || sudo systemctl restart apache2
```

## Maintenance

Menu: `CBT Exams > CBT Maintenance`

Fungsi utama:

- reset seluruh data CBT dari database
- menghapus data:
  - subjects
  - exams
  - questions
  - options
  - attempts
  - answers
  - pengaturan token global
  - state cache plugin
  - UI state

Reset hanya bisa dijalankan setelah mengetik konfirmasi persis:

```text
RESET CBT
```

## Performa dan Load Test

Folder `performance/` berisi paket tuning dan uji beban untuk deployment web:

- `performance/README.md`
- `performance/server-tuning-2core-8gb.md`
- `performance/load-test/k6/`

Target yang didokumentasikan:

- baseline sampai `1000` siswa realtime
- dengan runtime Redis batch submit, target bertahap sampai `2000` siswa realtime
- baseline server awal: `2 vCPU` dan `8 GB RAM`

Saran alur tuning:

1. Terapkan tuning server.
2. Jalankan load test bertahap.
3. Cek bottleneck CPU, RAM, I/O, query lambat, dan error rate.
4. Fine-tune ulang berdasarkan hasil aktual.

## Catatan Penting

- Pastikan dependency Composer sudah terpasang sebelum memakai fitur import XLSX/DOCX.
- Jika ekstensi `zip` tidak aktif, template Word tidak bisa dibuat.
- Jika Redis belum siap, plugin tetap aman berjalan dalam mode cadangan, tetapi mode ini bukan pilihan utama untuk ujian serentak skala besar.
- Untuk deployment produksi, selalu set `CBT_JWT_SECRET` secara eksplisit.
- Pastikan reverse proxy, Nginx, atau web server tidak menghilangkan header `Authorization`.
