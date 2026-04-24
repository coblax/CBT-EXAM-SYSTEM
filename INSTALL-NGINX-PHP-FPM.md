# Instalasi Produksi Nginx + PHP-FPM (16 Core / 16 GB)

Dokumen tunggal ini menjelaskan langkah instalasi WordPress + plugin CBT Exam System di server Linux dengan Nginx sebagai web server dan PHP-FPM sebagai runtime PHP. Tuning di dalamnya disiapkan untuk server `16 core / 16 GB RAM`, supaya setup utama cukup dibaca dari file ini saja.

## 0. Asumsi

- Sistem operasi: Ubuntu/Debian atau turunannya.
- Web root WordPress: `/var/www/wordpress`.
- Domain contoh: `ujian.example.sch.id`.
- User web server: `www-data`.
- Plugin berada di: `/var/www/wordpress/wp-content/plugins/cbt-exam-system`.
- Profil server target: `16 core / 16 GB RAM`.
- Mode baseline: Nginx, PHP-FPM, MySQL/MariaDB, Redis, dan WordPress berada dalam satu server.
- Stack minimum plugin:
  - WordPress `6.0+`
  - PHP `8.0+` sesuai header plugin
  - PHP `8.1+` direkomendasikan untuk instalasi dari repo ini, karena dependency Composer saat ini membutuhkan PHP `>=8.1`
  - MySQL/MariaDB kompatibel WordPress
  - Composer `2+`
  - Node.js `20+` bila asset frontend perlu dibuild di server
  - Redis opsional, tetapi sangat direkomendasikan untuk ujian serentak

Ganti semua nilai contoh seperti domain, nama database, user database, password, dan versi PHP sesuai server Anda. Jika jumlah peserta sangat besar atau ujian berjalan paralel banyak sesi, pisahkan database ke server sendiri agar beban MySQL tidak berebut RAM dengan PHP-FPM.

## 1. Install Paket Server

```bash
sudo apt update
sudo apt install -y nginx mysql-server redis-server redis-tools curl unzip git acl
sudo apt install -y php-fpm php-cli php-mysql php-curl php-mbstring php-xml php-zip php-gd php-intl php-bcmath php-redis
```

Cek service PHP-FPM yang tersedia:

```bash
systemctl list-unit-files 'php*-fpm.service' --no-legend
ls /run/php/php*-fpm.sock
php -v
php -m | grep -Ei 'redis|zip|xml|mbstring|gd|intl'
```

Jika `php-redis` belum terbaca oleh PHP-FPM, restart PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
```

Sesuaikan `php8.3-fpm` dengan unit yang ada di server Anda.

Install tool pendukung jika belum tersedia.

Composer:

```bash
sudo apt install -y composer
composer --version
```

Command install Composer di atas bebas dijalankan dari folder mana saja, karena Composer dipasang sebagai tool global. Nanti command `composer install --no-dev --optimize-autoloader` harus dijalankan dari folder plugin `/var/www/wordpress/wp-content/plugins/cbt-exam-system`, karena di sana file `composer.json` berada.

Node.js 20 untuk build asset frontend:

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

WP-CLI untuk aktivasi, rewrite, cron check, dan smoke test:

```bash
cd /tmp
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
php wp-cli.phar --info
sudo mv wp-cli.phar /usr/local/bin/wp
sudo chmod +x /usr/local/bin/wp
wp --info
```

Jika deployment memakai artifact release yang sudah berisi `vendor/` dan `public/build/`, Node.js tidak wajib dipasang di server produksi.

Pastikan timezone server sesuai dan NTP aktif:

```bash
sudo timedatectl set-timezone Asia/Jakarta
timedatectl status
```

Catatan alur: dokumen ini menaruh tuning produksi di bagian paling akhir. Tahap awal fokus membuat stack hidup dulu: database siap, Nginx melayani WordPress, WordPress aktif, plugin CBT aktif, lalu tuning `16 core / 16 GB RAM` diterapkan setelah smoke test dasar berhasil.

## 2. Siapkan Database

Masuk ke MySQL/MariaDB:

```bash
sudo mysql
```

Buat database dan user:

```sql
CREATE DATABASE wordpress_cbt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
CREATE USER 'wp_cbt'@'localhost' IDENTIFIED BY 'ganti_password_database_yang_kuat';
GRANT ALL PRIVILEGES ON wordpress_cbt.* TO 'wp_cbt'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Jika database berada di server berbeda, ganti `localhost` dengan host yang sesuai dan pastikan firewall mengizinkan koneksi dari server WordPress.

## 3. Install phpMyAdmin untuk Nginx (Opsional)

Bagian ini opsional. phpMyAdmin berguna untuk cek database lewat browser, tetapi jangan jadikan satu-satunya alat administrasi database di produksi. Command install phpMyAdmin bebas dijalankan dari folder mana saja.

Install paket:

```bash
sudo apt install -y phpmyadmin
```

Saat installer bertanya:

- Pilihan web server otomatis: jangan pilih `apache2`, karena server ini memakai Nginx.
- `Configure database for phpmyadmin with dbconfig-common?`: pilih `Yes` jika ingin paket membuat database kontrol phpMyAdmin.
- Masukkan password aplikasi phpMyAdmin bila diminta, atau biarkan installer membuat otomatis jika opsi itu tersedia.

Pastikan folder phpMyAdmin tersedia:

```bash
test -d /usr/share/phpmyadmin
ls -la /usr/share/phpmyadmin | head
```

Buat user MySQL khusus untuk login phpMyAdmin. Ini menghindari kebingungan karena user `root` MySQL pada Ubuntu/Debian sering memakai `auth_socket`, sehingga tidak bisa login dari phpMyAdmin dengan password biasa.

```bash
sudo mysql
```

```sql
CREATE USER 'pma_cbt_admin'@'localhost' IDENTIFIED BY 'ganti_password_phpmyadmin_yang_kuat';
GRANT ALL PRIVILEGES ON wordpress_cbt.* TO 'pma_cbt_admin'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

User `pma_cbt_admin` di atas hanya diberi akses ke database `wordpress_cbt`. Jika nama database Anda berbeda, ganti `wordpress_cbt.*` sesuai database WordPress yang dibuat pada tahap sebelumnya.

Nginx alias untuk phpMyAdmin ditambahkan pada tahap konfigurasi Nginx di bawah. Setelah Nginx direload, akses:

```text
https://ujian.example.sch.id/phpmyadmin/
```

Hardening yang disarankan:

- Pakai password kuat dan unik untuk user `pma_cbt_admin`.
- Jangan pakai user MySQL `root` untuk login phpMyAdmin.
- Batasi akses `/phpmyadmin/` lewat firewall, VPN, basic auth, atau allowlist IP admin jika server berada di internet publik.
- Hapus phpMyAdmin jika sudah tidak dibutuhkan:

```bash
sudo apt purge -y phpmyadmin
sudo apt autoremove -y
```

## 4. Konfigurasi Nginx

Cari socket PHP-FPM:

```bash
ls /run/php/php*-fpm.sock
```

Pastikan konfigurasi Nginx memuat folder `sites-enabled`:

```bash
sudo nginx -T | grep -E 'sites-enabled|conf.d'
```

Jika tidak ada baris `include /etc/nginx/sites-enabled/*;`, tambahkan include tersebut di dalam blok `http` pada `/etc/nginx/nginx.conf`. Tuning global Nginx detail ada di bagian paling akhir dokumen.

```nginx
http {
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

Buat server block WordPress:

```bash
sudo mkdir -p /var/www/wordpress
sudo chown www-data:www-data /var/www/wordpress
```

```bash
sudo nano /etc/nginx/sites-available/wordpress-cbt
```

Letakkan seluruh konfigurasi `server { ... }` di file `/etc/nginx/sites-available/wordpress-cbt`. Jangan masukkan blok `server { ... }` ini langsung ke `/etc/nginx/nginx.conf`, kecuali Anda memang sengaja mengelola site langsung dari file utama Nginx.

Blok phpMyAdmin harus berada di dalam `server { ... }` yang sama dengan WordPress, dan posisinya harus sebelum blok PHP umum `location ~ \.php$`.

Isi contoh:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name ujian.example.sch.id;

    root /var/www/wordpress;
    index index.php index.html;

    access_log /var/log/nginx/wordpress-cbt.access.log;
    error_log /var/log/nginx/wordpress-cbt.error.log;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location = /phpmyadmin {
        return 301 /phpmyadmin/;
    }

    location /phpmyadmin/ {
        alias /usr/share/phpmyadmin/;
        index index.php;
        try_files $uri $uri/ =404;
    }

    location ~ ^/phpmyadmin/(.+\.php)$ {
        alias /usr/share/phpmyadmin/$1;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 120;
        fastcgi_send_timeout 120;
        fastcgi_connect_timeout 60;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 120;
        fastcgi_send_timeout 120;
        fastcgi_connect_timeout 60;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~* ^/wp-content/plugins/cbt-exam-system/public/build/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
        access_log off;
    }

    location ~* \.(?:css|js|jpg|jpeg|gif|png|webp|svg|ico|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public";
        try_files $uri =404;
        access_log off;
    }

    location ~* /(?:uploads|files)/.*\.php$ {
        deny all;
    }

    location = /wp-config.php {
        deny all;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan site:

```bash
sudo rm -f /etc/nginx/sites-enabled/default
sudo ln -s /etc/nginx/sites-available/wordpress-cbt /etc/nginx/sites-enabled/wordpress-cbt
sudo nginx -t
sudo systemctl reload nginx
```

Jika memakai socket PHP-FPM berbeda, ganti semua `fastcgi_pass unix:/run/php/php8.3-fpm.sock;` sesuai hasil `ls /run/php/php*-fpm.sock`, termasuk blok phpMyAdmin.

## 5. Install WordPress

Pada tahap ini Nginx dan PHP-FPM sudah siap. Sekarang isi web root `/var/www/wordpress` dengan WordPress.

```bash
cd /var/www
sudo curl -L -o wordpress.tar.gz https://wordpress.org/latest.tar.gz
sudo tar -xzf wordpress.tar.gz
sudo rm wordpress.tar.gz
sudo chown -R www-data:www-data /var/www/wordpress
sudo find /var/www/wordpress -type d -exec chmod 755 {} \;
sudo find /var/www/wordpress -type f -exec chmod 644 {} \;
```

Buat `wp-config.php`:

```bash
cd /var/www/wordpress
sudo -u www-data cp wp-config-sample.php wp-config.php
sudo nano wp-config.php
```

Isi konfigurasi database:

```php
define('DB_NAME', 'wordpress_cbt');
define('DB_USER', 'wp_cbt');
define('DB_PASSWORD', 'ganti_password_database_yang_kuat');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
```

Ganti authentication keys dan salts dengan nilai unik:

```bash
curl -s https://api.wordpress.org/secret-key/1.1/salt/
```

Salin output command tersebut ke bagian keys/salts di `wp-config.php`.

Tambahkan konfigurasi runtime berikut sebelum baris `/* That's all, stop editing! */`:

```php
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
define('DISABLE_WP_CRON', true);
define('AUTOSAVE_INTERVAL', 120);
define('WP_POST_REVISIONS', 5);

define('WP_CACHE', true);
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_DATABASE', 1);
define('WP_REDIS_PREFIX', 'cbt_exam_system:');

define('CBT_RUNTIME_BUFFER_ENABLED', true);
define('CBT_RUNTIME_BUFFER_FALLBACK_TO_DB', true);
define('CBT_RUNTIME_REDIS_HOST', '127.0.0.1');
define('CBT_RUNTIME_REDIS_PORT', 6379);
define('CBT_RUNTIME_REDIS_DATABASE', 2);
define('CBT_RUNTIME_REDIS_DB', 2);
define('CBT_RUNTIME_REDIS_PREFIX', 'cbt_runtime:');
```

Jika Redis memakai password, tambahkan juga:

```php
define('WP_REDIS_PASSWORD', 'ganti_password_redis');
define('CBT_RUNTIME_REDIS_PASSWORD', 'ganti_password_redis');
```

Catatan: `CBT_RUNTIME_REDIS_DATABASE` dipakai runtime utama. `CBT_RUNTIME_REDIS_DB` disiapkan sebagai alias kompatibilitas untuk beberapa snapshot cache plugin.

## 6. Aktifkan HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d ujian.example.sch.id
```

Cek pembaruan sertifikat:

```bash
sudo certbot renew --dry-run
```

Untuk pelaksanaan ujian produksi, gunakan HTTPS. Jangan menjalankan halaman ujian siswa di HTTP polos.

## 7. Aktifkan Redis Object Cache WordPress

Jalankan Redis:

```bash
sudo systemctl enable --now redis-server
redis-cli ping
```

Output yang diharapkan:

```text
PONG
```

Untuk WordPress object cache, gunakan salah satu jalur berikut.

Jalur WP-CLI:

```bash
cd /var/www/wordpress
sudo -u www-data wp plugin install redis-cache --activate
sudo -u www-data wp redis enable
```

Jalur dashboard WordPress:

1. Masuk ke `Plugins`.
2. Install plugin `Redis Object Cache`.
3. Aktifkan plugin.
4. Klik `Enable Object Cache`.

Verifikasi:

```bash
cd /var/www/wordpress
sudo -u www-data wp eval 'var_dump(wp_using_ext_object_cache());'
test -f /var/www/wordpress/wp-content/object-cache.php
```

Output `wp_using_ext_object_cache()` harus `bool(true)`.

## 8. Install Plugin CBT Exam System

Jika repo/plugin belum ada, salin atau clone plugin ke folder WordPress:

```bash
cd /var/www/wordpress/wp-content/plugins
sudo -u www-data git clone <URL_REPO_ANDA> cbt-exam-system
```

Masuk ke folder plugin:

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
```

Install dependency PHP produksi:

```bash
composer install --no-dev --optimize-autoloader
```

Build asset frontend jika `public/build/manifest.json` belum tersedia atau source frontend berubah:

```bash
npm ci
npm run build
rm -rf node_modules
```

Pastikan manifest produksi ada:

```bash
test -f public/build/manifest.json
```

Rapikan permission:

```bash
sudo chown -R www-data:www-data /var/www/wordpress/wp-content/plugins/cbt-exam-system
sudo find /var/www/wordpress/wp-content/plugins/cbt-exam-system -type d -exec chmod 755 {} \;
sudo find /var/www/wordpress/wp-content/plugins/cbt-exam-system -type f -exec chmod 644 {} \;
```

## 9. Selesaikan Instalasi WordPress

Buka browser:

```text
https://ujian.example.sch.id/
```

Selesaikan wizard WordPress, buat akun administrator, lalu masuk ke dashboard.

Aktifkan plugin:

```bash
cd /var/www/wordpress
sudo -u www-data wp plugin activate cbt-exam-system
```

Atau aktifkan dari `Dashboard > Plugins`.

Saat aktivasi plugin:

- tabel CBT dibuat atau dimigrasikan
- role dan capability CBT disiapkan
- halaman frontend kanonik CBT dibuat/disinkronkan
- cron worker plugin dijadwalkan

## 10. Atur Permalink dan Cron

Aktifkan permalink:

```bash
cd /var/www/wordpress
sudo -u www-data wp rewrite structure '/%postname%/' --hard
sudo -u www-data wp rewrite flush --hard
```

Karena `DISABLE_WP_CRON` disarankan aktif, buat cron OS:

```bash
sudo crontab -u www-data -e
```

Tambahkan:

```cron
* * * * * /usr/bin/php /var/www/wordpress/wp-cron.php > /dev/null 2>&1
```

Cron ini penting untuk worker CBT seperti flush runtime, finalisasi attempt expired, security ingest, preflight, cohort index, dan warm readiness.

## 11. Setup Awal CBT

Di dashboard WordPress:

1. Buka `CBT Cache`.
2. Pastikan `Readiness = ready`, `Backend Hint = redis`, dan `Probe Status = passed`.
3. Jika belum siap, ikuti checklist di halaman `CBT Cache`.
4. Buka `CBT Branding` untuk identitas sekolah.
5. Buka `CBT Subjects` untuk membuat mata pelajaran.
6. Buka `CBT Users` untuk import guru/siswa.
7. Buka `CBT Questions` untuk import atau membuat bank soal.
8. Buka `CBT Exams` untuk membuat ujian, jadwal, target kelas, token, dan opsi randomisasi.
9. Buka halaman siswa:

```text
https://ujian.example.sch.id/cbt-ujian/
```

## 12. Smoke Test

Jalankan dari server:

```bash
curl -I https://ujian.example.sch.id/
curl -I https://ujian.example.sch.id/cbt-ujian/

# Opsional jika phpMyAdmin dipasang:
curl -I https://ujian.example.sch.id/phpmyadmin/

cd /var/www/wordpress
sudo -u www-data wp plugin status cbt-exam-system
sudo -u www-data wp option get cbt_exam_system_db_version
sudo -u www-data wp cron event list | grep cbt
sudo -u www-data wp eval 'var_dump(wp_using_ext_object_cache());'
```

Jalankan dari folder plugin:

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
test -f vendor/autoload.php
test -f public/build/manifest.json
```

Lakukan uji manual:

1. Login sebagai admin.
2. Buat subject kecil.
3. Buat user siswa.
4. Buat exam kecil dengan beberapa soal.
5. Publish exam.
6. Login dari `/cbt-ujian/` sebagai siswa.
7. Start attempt, isi jawaban, finish exam.
8. Cek hasil di `CBT Results`.

## 13. Catatan Cache dan CDN

Jangan cache halaman HTML ujian dan endpoint REST CBT dengan page cache agresif. Jika memakai CDN, reverse proxy, atau FastCGI cache, bypass minimal untuk:

```text
/wp-admin/*
/wp-login.php
/wp-json/cbt/v1/*
/cbt-ujian/*
/phpmyadmin/*
```

Static asset seperti CSS, JS, font, dan gambar boleh dicache. Nginx config di atas hanya memberi cache header untuk file statis.

## 14. Monitoring Saat Ujian

Pantau service utama selama ujian:

```bash
htop
free -h
df -h
systemctl status nginx php8.3-fpm mysql redis-server --no-pager
journalctl -u php8.3-fpm -n 100 --no-pager
journalctl -u nginx -n 100 --no-pager
tail -f /var/log/nginx/wordpress-cbt.error.log
tail -f /var/log/mysql/slow-query.log
redis-cli info memory
```

Indikator yang perlu cepat ditangani:

- RAM habis atau swap aktif terus-menerus.
- PHP-FPM mencapai `pm.max_children`.
- Error Nginx `502` atau `504`.
- Slow query MySQL muncul berulang saat ujian.
- Redis memory mendekati `maxmemory` dan eviction tinggi.
- Latency endpoint `/wp-json/cbt/v1/*` terasa lambat saat login/start/submit.

Jika server mulai berat saat ujian:

1. Turunkan traffic non-ujian dan matikan plugin WordPress yang tidak penting.
2. Pastikan CDN/page cache tidak mengenai REST CBT dan halaman `/cbt-ujian/`.
3. Naikkan `pm.max_children` hanya jika RAM masih longgar.
4. Jika RAM menipis, turunkan `pm.max_children` lebih dulu.
5. Cek slow query dan Redis readiness dari menu `CBT Cache`.

## 15. Troubleshooting Cepat

`502 Bad Gateway`

- Cek socket `fastcgi_pass` sesuai hasil `ls /run/php/php*-fpm.sock`.
- Cek `sudo systemctl status php8.3-fpm`.
- Cek log `/var/log/nginx/wordpress-cbt.error.log`.

`404` pada halaman atau REST API

- Pastikan permalink aktif.
- Jalankan `wp rewrite flush --hard`.
- Pastikan blok Nginx memakai `try_files $uri $uri/ /index.php?$args;`.

Frontend CBT blank atau asset tidak muncul

- Pastikan `public/build/manifest.json` ada.
- Jalankan `npm ci` lalu `npm run build` dari folder plugin.
- Pastikan permission folder plugin bisa dibaca `www-data`.

Redis masih fallback

- Pastikan `redis-cli ping` menghasilkan `PONG`.
- Pastikan `php -m | grep -i redis` menampilkan Redis.
- Pastikan `wp-content/object-cache.php` ada.
- Pastikan `wp_using_ext_object_cache()` bernilai `true`.
- Buka `CBT Cache` dan ikuti next step yang muncul.

phpMyAdmin 404 atau file PHP terunduh

- Pastikan paket `phpmyadmin` sudah terpasang dan folder `/usr/share/phpmyadmin` ada.
- Pastikan blok `location /phpmyadmin/` dan `location ~ ^/phpmyadmin/(.+\.php)$` ada di server block Nginx.
- Pastikan blok phpMyAdmin berada sebelum blok umum `location ~ \.php$`.
- Pastikan `fastcgi_pass` pada blok phpMyAdmin memakai socket PHP-FPM yang benar.

Import soal/user gagal

- Cek `upload_max_filesize`, `post_max_size`, dan `max_execution_time`.
- Pastikan ekstensi `zip`, `xml`, `mbstring`, `gd`, dan `intl` aktif.
- Cek permission `wp-content/uploads`.

Worker CBT tidak berjalan

- Pastikan cron OS untuk `wp-cron.php` aktif setiap menit.
- Jalankan `wp cron event list | grep cbt`.
- Cek apakah `DISABLE_WP_CRON` sudah diimbangi dengan cron OS.

## 16. Checklist Sebelum Tuning Produksi

- HTTPS aktif dan sertifikat valid.
- Jam server sinkron via NTP.
- Backup database dan `wp-content/uploads` tersedia.
- Redis object cache aktif dan CBT Cache readiness `ready`.
- `public/build/manifest.json` tersedia.
- Cron OS berjalan tiap menit.
- Tidak ada page cache untuk `/wp-json/cbt/v1/*` dan `/cbt-ujian/*`.
- phpMyAdmin dibatasi aksesnya atau dihapus jika tidak dibutuhkan saat ujian.
- Minimal satu simulasi login, start, autosave, finish, dan result sudah berhasil.
- Log Nginx, PHP-FPM, MySQL, dan Redis mudah dipantau saat ujian.
- Nilai bawaan PHP-FPM, MySQL, Redis, dan Nginx sudah dicatat supaya mudah rollback jika tuning akhir perlu dibatalkan.

## 17. Tuning Produksi Terakhir untuk 16 Core / 16 GB

Lakukan bagian ini setelah WordPress, plugin CBT, Redis object cache, cron, dan smoke test dasar sudah berhasil. Tujuannya supaya jika ada error saat tuning, Anda tahu stack dasarnya sudah hidup.

### 17.1 Tuning PHP-FPM

Edit pool PHP-FPM:

```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

Contoh baseline untuk server `16 core / 16 GB RAM` dengan MySQL dan Redis masih satu host:

```ini
pm = dynamic
pm.max_children = 80
pm.start_servers = 20
pm.min_spare_servers = 12
pm.max_spare_servers = 36
pm.max_requests = 500
request_terminate_timeout = 60s
request_slowlog_timeout = 5s
slowlog = /var/log/php-fpm-www-slow.log
```

Catatan sizing:

- `pm.max_children = 80` adalah titik awal aman untuk server 16 GB jika MySQL dan Redis masih satu host.
- Jika RAM sering hampir habis atau swap aktif, turunkan bertahap ke `64`, lalu `56`.
- Jika antrean PHP-FPM tinggi, CPU masih longgar, dan RAM masih aman, naikkan bertahap ke `96` atau `112`.
- Jika database sudah dipisah ke server lain, `pm.max_children` biasanya bisa dinaikkan lebih agresif karena RAM lokal tidak dipakai InnoDB besar.
- Jangan langsung menaikkan `memory_limit` terlalu besar; limit tinggi membuat risiko OOM lebih besar saat request import/report berjalan bersamaan.

Edit konfigurasi PHP:

```bash
sudo nano /etc/php/8.3/fpm/php.ini
```

Contoh nilai produksi:

```ini
memory_limit = 512M
max_execution_time = 120
max_input_time = 120
post_max_size = 64M
upload_max_filesize = 64M
max_file_uploads = 50
date.timezone = Asia/Jakarta

opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=30000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
realpath_cache_size=4096K
realpath_cache_ttl=600
```

Opsional, buat file slowlog agar PHP-FPM bisa menulis log request lambat:

```bash
sudo touch /var/log/php-fpm-www-slow.log
sudo chown www-data:adm /var/log/php-fpm-www-slow.log
sudo chmod 640 /var/log/php-fpm-www-slow.log
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
```

### 17.2 Tuning MySQL/MariaDB

Buat file konfigurasi khusus:

```bash
sudo nano /etc/mysql/conf.d/cbt-16core.cnf
```

Isi baseline:

```ini
[mysqld]
innodb_buffer_pool_size=6G
innodb_buffer_pool_instances=6
innodb_log_file_size=1G
innodb_log_buffer_size=128M
innodb_flush_log_at_trx_commit=2
innodb_flush_method=O_DIRECT
innodb_io_capacity=2000
innodb_io_capacity_max=4000
innodb_read_io_threads=8
innodb_write_io_threads=8

max_connections=350
thread_cache_size=128
table_open_cache=8192
table_definition_cache=4096

tmp_table_size=128M
max_heap_table_size=128M
sort_buffer_size=4M
join_buffer_size=4M

slow_query_log=1
slow_query_log_file=/var/log/mysql/slow-query.log
long_query_time=1
```

Restart database:

```bash
sudo systemctl restart mysql
sudo systemctl status mysql --no-pager
```

Catatan sizing:

- `innodb_buffer_pool_size=6G` cocok untuk single server 16 GB karena masih menyisakan RAM untuk PHP-FPM, Redis, Nginx, dan OS cache.
- Jika database dipisah ke server sendiri dengan RAM 16 GB, buffer pool bisa dinaikkan ke `10G` sampai `11G`.
- Jika server mulai swap, turunkan buffer pool ke `5G` dan turunkan `pm.max_children` PHP-FPM.

### 17.3 Tuning Redis

Edit konfigurasi Redis:

```bash
sudo nano /etc/redis/redis.conf
```

Pastikan nilai berikut ada atau disesuaikan:

```conf
supervised systemd
bind 127.0.0.1 ::1
protected-mode yes
timeout 0
tcp-keepalive 300
maxmemory 1536mb
maxmemory-policy noeviction
appendonly yes
appendfsync everysec
save 900 1
save 300 10
save 60 10000
```

Restart Redis:

```bash
sudo systemctl restart redis-server
redis-cli ping
redis-cli info memory | grep -E 'used_memory_human|maxmemory_human|maxmemory_policy'
```

Catatan Redis:

- `noeviction` dipilih agar key runtime ujian tidak dibuang diam-diam ketika memori penuh.
- `appendonly yes` dengan `appendfsync everysec` memberi perlindungan lebih baik jika Redis/service restart saat ujian.
- Jika Redis mendekati `maxmemory`, naikkan ke `2gb` hanya jika RAM server masih longgar, atau pisahkan Redis ke server sendiri.

### 17.4 Tuning Global Nginx

Edit konfigurasi global Nginx:

```bash
sudo nano /etc/nginx/nginx.conf
```

Pastikan nilai globalnya seperti ini. Jika file sudah berisi blok `events` dan `http`, sesuaikan nilainya di blok yang sudah ada, jangan membuat blok dobel.

```nginx
user www-data;
worker_processes auto;
worker_rlimit_nofile 65535;
pid /run/nginx.pid;

events {
    worker_connections 8192;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    keepalive_requests 1000;
    types_hash_max_size 4096;
    server_tokens off;

    gzip on;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript application/xml image/svg+xml;

    client_max_body_size 64m;
    client_body_timeout 30s;
    client_header_timeout 30s;
    send_timeout 120s;

    open_file_cache max=20000 inactive=60s;
    open_file_cache_valid 120s;
    open_file_cache_min_uses 2;
    open_file_cache_errors on;

    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

Validasi dan reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Setelah semua tuning diterapkan, ulangi smoke test dan pantau RAM, CPU, slow query MySQL, Redis memory, serta error Nginx/PHP-FPM saat simulasi beban.
