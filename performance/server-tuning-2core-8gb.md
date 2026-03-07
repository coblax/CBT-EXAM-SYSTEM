# Server Tuning (2 vCPU / 8 GB RAM)

Dokumen ini untuk WordPress + plugin CBT dengan target ujian realtime banyak user.

## 1) Baseline Arsitektur

Gunakan stack berikut:
- Nginx (reverse proxy + static assets)
- PHP-FPM 8.1+ (dynamic process manager)
- MySQL 8 / MariaDB 10.6+ (InnoDB)
- Redis untuk object cache WordPress

Pisahkan service kalau bisa (DB di host terpisah akan jauh lebih stabil), tapi untuk single VM 2c/8GB tetap bisa jika tuning ketat.

## 2) WordPress (wp-config.php)

Tambahkan:

```php
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
define('WP_CACHE', true);
define('DISABLE_WP_CRON', true);
define('AUTOSAVE_INTERVAL', 120);
define('WP_POST_REVISIONS', 5);
```

Aktifkan real cron OS:

```bash
* * * * * php /path/to/wordpress/wp-cron.php > /dev/null 2>&1
```

## 3) PHP-FPM (2 core)

Contoh awal (`www.conf`), sesuaikan setelah observasi memory real:

```ini
pm = dynamic
pm.max_children = 28
pm.start_servers = 6
pm.min_spare_servers = 4
pm.max_spare_servers = 10
pm.max_requests = 500
request_terminate_timeout = 60s
```

Catatan:
- Jika OOM, turunkan `pm.max_children`.
- Jika antrean FPM tinggi dan RAM masih aman, naikkan bertahap.

## 4) OPcache

Contoh `php.ini`:

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=30000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
```

## 5) MySQL / MariaDB (InnoDB)

Contoh baseline (`my.cnf`) untuk single host:

```ini
[mysqld]
innodb_buffer_pool_size=3G
innodb_buffer_pool_instances=2
innodb_log_file_size=512M
innodb_flush_log_at_trx_commit=2
innodb_flush_method=O_DIRECT
innodb_io_capacity=800
innodb_read_io_threads=4
innodb_write_io_threads=4

max_connections=250
thread_cache_size=64
table_open_cache=4096

tmp_table_size=64M
max_heap_table_size=64M
```

Penting:
- Pastikan storage SSD/NVMe.
- NTP harus sinkron (timer ujian sensitif waktu).

## 6) Redis Object Cache

Tujuan section ini: WordPress harus benar-benar memakai Redis sebagai external object cache, bukan hanya menyalakan `WP_CACHE`.

Catatan: baseline `2 vCPU / 8 GB` pada dokumen ini cocok untuk tahap awal sampai sekitar `1000` user bertahap. Untuk target `2000` user serentak dengan batch submit Redis runtime, gunakan single node yang sudah di-upgrade, minimal kelas `8 vCPU / 16-32 GB`, storage `NVMe`, dan Redis aktif.

Runbook minimum:

1. Install dan jalankan service Redis.
2. Install PHP Redis extension/client yang didukung stack Anda.
3. Install dan aktifkan plugin/drop-in Redis Object Cache WordPress.
4. Tambahkan konfigurasi berikut ke `wp-config.php`:

```php
define('WP_CACHE', true);
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_DATABASE', 1);
define('WP_REDIS_PREFIX', 'cbt_exam_system:');

// Optional jika Redis memakai password:
define('WP_REDIS_PASSWORD', 'replace-with-your-redis-password');
```

Jika memakai CBT runtime Redis untuk buffer jawaban, tambahkan juga:

```php
define('CBT_RUNTIME_BUFFER_ENABLED', true);
define('CBT_RUNTIME_BUFFER_FALLBACK_TO_DB', true);
define('CBT_RUNTIME_REDIS_HOST', '127.0.0.1');
define('CBT_RUNTIME_REDIS_PORT', 6379);
define('CBT_RUNTIME_REDIS_DATABASE', 2);
define('CBT_RUNTIME_REDIS_PREFIX', 'cbt_runtime:');
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

5. Pastikan `wp-content/object-cache.php` sudah terpasang/aktif.
6. Jika satu host dipakai banyak site/aplikasi, pakai database Redis dan prefix yang berbeda.

Verifikasi setelah aktivasi:

- `wp_using_ext_object_cache()` harus bernilai `true`
- halaman `CBT Exams > CBT Cache` harus menunjukkan:
  - `Readiness = ready`
  - `Backend Hint = redis`
  - `Probe Status = passed`
- warning fallback tidak boleh muncul lagi di admin
- object cache hit rate tinggi
- tidak ada eviction agresif saat ujian
- jika perlu rollback dari sisi WordPress, gunakan tombol `Batalkan Redis Sekali Klik` di halaman `CBT Cache`; tombol ini tidak mematikan service Redis OS

Catatan:

- Jika `WP_CACHE=1` tetapi `object-cache.php` belum ada, CBT akan tetap masuk status non-ready.
- Jika Redis belum aktif atau gagal konek, plugin CBT tetap jalan dengan fallback transient, tetapi mode ini bukan rekomendasi untuk ujian serentak.

## 7) Nginx

Aktifkan keepalive dan cache static:

```nginx
keepalive_timeout 65;
sendfile on;
tcp_nopush on;
tcp_nodelay on;
gzip on;
gzip_types text/css application/javascript application/json;
```

Untuk static plugin assets:
- cache header 7-30 hari
- gunakan HTTP/2 jika memungkinkan

## 8) Plugin CBT (yang sudah dioptimasi)

Yang sudah disiapkan pada kode:
- Index komposit tambahan pada tabel attempts/answers/questions/options/exams
- `get_questions` tanpa N+1 options query
- `submit_answer` pakai upsert (`ON DUPLICATE KEY UPDATE`) lebih efisien daripada `REPLACE`
- JWT decode di-cache per request
- autosave frontend dituning agar tidak burst berlebihan

## 9) Monitoring Minimum Saat Ujian

Wajib monitor realtime:
- CPU usage per core
- RAM free + swap
- PHP-FPM active/idle process + queue
- MySQL: QPS, slow query, row lock wait
- Nginx: 4xx/5xx rate

Trigger alert cepat:
- error API `submit_answer` > 2%
- p95 latency endpoint CBT > 1.2s
- DB CPU > 85% selama > 3 menit

## 10) Verifikasi Index DB

Jalankan:

```sql
SHOW INDEX FROM wp_cbt_attempts;
SHOW INDEX FROM wp_cbt_answers;
SHOW INDEX FROM wp_cbt_questions;
SHOW INDEX FROM wp_cbt_options;
SHOW INDEX FROM wp_cbt_exams;
```

## 11) Strategi Capacity Bertahap

Jangan langsung 1000 user jika belum ada baseline.

Urutan tes:
1. 200 user
2. 500 user
3. 800 user
4. 1000 user

Lanjut tahap berikutnya hanya jika:
- error rate stabil rendah
- p95 latency masih sesuai target
- tidak ada bottleneck kritis di DB/FPM
