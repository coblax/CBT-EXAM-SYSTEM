# Panduan Instalasi: WordPress + CBT Exam System (Nginx + PHP-FPM)

Dokumen ini menjelaskan langkah-langkah instalasi WordPress beserta plugin **CBT Exam System** di server Linux menggunakan **Nginx** sebagai web server dan **PHP-FPM** sebagai runtime PHP. Tuning di dalamnya disiapkan untuk server **16 core / 16 GB RAM**.

Dokumen ini ditulis agar bisa diikuti oleh siapa saja, bukan hanya pengembang plugin. Setiap langkah disusun berurutan — langkah berikutnya bergantung pada langkah sebelumnya.

---

## Ringkasan Alur Instalasi

Berikut gambaran besar tahapan yang akan dilalui. Ikuti urutan ini dari atas ke bawah:

```text
┌─────────────────────────────────────────────┐
│  1. Prasyarat Server                        │  ← Timezone, update OS
│  2. Install Paket Dasar                     │  ← Nginx, PHP-FPM, MySQL, Redis
│  3. Install Tool Pendukung                  │  ← Composer, Node.js, WP-CLI
│  4. Siapkan Database MySQL                  │  ← Buat database & user
│  5. Konfigurasi Nginx                       │  ← Server block WordPress
│  6. Install phpMyAdmin (Opsional)           │  ← Manajemen database via browser
│  7. Install WordPress                       │  ← Download, wp-config.php
│  8. Aktifkan HTTPS                          │  ← Sertifikat SSL / Cloudflare Tunnel
│  9. Aktifkan Redis Object Cache             │  ← Plugin Redis Cache
│ 10. Install Plugin CBT Exam System          │  ← Clone, Composer, build asset
│ 11. Aktifkan Plugin & Selesaikan WordPress  │  ← Aktivasi, permalink, cron
│ 12. Setup Awal CBT                          │  ← Branding, subject, user, soal
│ 13. Smoke Test                              │  ← Verifikasi semuanya jalan
│ 14. Backup & Restore                        │  ← Backup database & file
│ 15. Catatan Cache & CDN                     │  ← Apa yang boleh/tidak dicache
│ 16. Monitoring Saat Ujian                   │  ← Pantau resource server
│ 17. Checklist Sebelum Tuning                │  ← Pastikan semua siap
│ 18. Tuning Produksi (16 Core / 16 GB)       │  ← PHP-FPM, MySQL, Redis, Nginx
│ 19. Troubleshooting                         │  ← Solusi masalah umum
└─────────────────────────────────────────────┘
```

---

## Asumsi & Persyaratan

| Item | Nilai |
|------|-------|
| Sistem operasi | Ubuntu/Debian atau turunannya |
| Web root WordPress | `/var/www/wordpress` |
| Domain contoh | `ujian.example.sch.id` |
| User web server | `www-data` |
| Folder plugin | `/var/www/wordpress/wp-content/plugins/cbt-exam-system` |
| Profil server | 16 core / 16 GB RAM |
| Mode | Single server (Nginx + PHP-FPM + MySQL + Redis) |

**Stack minimum plugin:**

- WordPress `6.0+`
- PHP `8.0+` (minimum), **PHP `8.1+` direkomendasikan** karena dependency Composer membutuhkan `>=8.1`
- MySQL/MariaDB kompatibel WordPress
- Composer `2+`
- Node.js `20+` (hanya jika asset frontend perlu dibuild di server)
- Redis — opsional, tetapi **sangat direkomendasikan** untuk ujian serentak

> **Catatan:** Ganti semua nilai contoh (domain, nama database, user, password, versi PHP) sesuai server Anda. Jika peserta sangat banyak atau banyak sesi paralel, pertimbangkan memisahkan database ke server sendiri.

---

## 1. Prasyarat Server

Sebelum menginstall apa pun, pastikan server dalam kondisi siap.

**Atur timezone dan aktifkan sinkronisasi waktu (NTP):**

Timezone yang benar penting karena plugin CBT menggunakan waktu server untuk jadwal ujian, durasi attempt, dan validasi token.

```bash
sudo timedatectl set-timezone Asia/Jakarta
timedatectl status
```

Pastikan output menunjukkan `NTP synchronized: yes`. Jika belum:

```bash
sudo apt install -y systemd-timesyncd
sudo systemctl enable --now systemd-timesyncd
timedatectl status
```

**Update repository OS:**

```bash
sudo apt update && sudo apt upgrade -y
```

---

## 2. Install Paket Dasar

Install Nginx, MySQL, Redis, dan PHP-FPM beserta ekstensi yang dibutuhkan plugin:

```bash
sudo apt install -y nginx mysql-server redis-server redis-tools curl unzip git acl
sudo apt install -y php-fpm php-cli php-mysql php-curl php-mbstring php-xml \
  php-zip php-gd php-intl php-bcmath php-redis php-imagick
```

**Verifikasi instalasi:**

```bash
# Cek versi PHP dan service PHP-FPM yang terinstall
php -v
systemctl list-unit-files 'php*-fpm.service' --no-legend

# Cek socket PHP-FPM (catat path ini, akan dipakai di konfigurasi Nginx)
ls /run/php/php*-fpm.sock

# Cek ekstensi PHP yang dibutuhkan plugin dan fitur export/media
php -m | grep -Ei 'redis|zip|xml|mbstring|gd|intl|imagick|Zend OPcache'
```

Jika `redis` atau `Zend OPcache` belum muncul di daftar ekstensi, restart PHP-FPM:

```bash
# Sesuaikan versi, misalnya php8.3-fpm
sudo systemctl restart php8.3-fpm
```

---

## 3. Install Tool Pendukung

### 3.1 Composer (Wajib)

Composer dipakai untuk menginstall dependency PHP plugin.

```bash
sudo apt install -y composer
composer --version
```

### 3.2 Node.js 20 (Kondisional)

Node.js dibutuhkan **hanya jika** Anda perlu mem-build asset frontend di server. Jika Anda men-deploy dari release/artifact yang sudah membawa folder `public/build/`, Node.js tidak wajib.

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

### 3.3 WP-CLI (Sangat Direkomendasikan)

WP-CLI mempermudah aktivasi plugin, pengaturan permalink, dan troubleshooting dari terminal.

```bash
cd /tmp
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
php wp-cli.phar --info
sudo mv wp-cli.phar /usr/local/bin/wp
sudo chmod +x /usr/local/bin/wp
wp --info
```

---

## 4. Siapkan Database MySQL

Buat database dan user khusus untuk WordPress. Jangan pakai user `root` MySQL untuk aplikasi.

```bash
sudo mysql
```

```sql
CREATE DATABASE wordpress_cbt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
CREATE USER 'wp_cbt'@'localhost' IDENTIFIED BY 'ganti_password_database_yang_kuat';
GRANT ALL PRIVILEGES ON wordpress_cbt.* TO 'wp_cbt'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> **Catatan:** Jika database berada di server berbeda, ganti `localhost` dengan host yang sesuai dan pastikan firewall mengizinkan koneksi dari server WordPress.

---

## 5. Konfigurasi Nginx

Tahap ini menyiapkan Nginx untuk melayani WordPress. Pastikan Anda sudah mencatat path socket PHP-FPM dari Langkah 2.

### 5.1 Pastikan Nginx memuat sites-enabled

```bash
sudo nginx -T | grep -E 'sites-enabled|conf.d'
```

Jika tidak ada baris `include /etc/nginx/sites-enabled/*;`, tambahkan di dalam blok `http` pada `/etc/nginx/nginx.conf`:

```nginx
http {
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

### 5.2 Buat folder web root

```bash
sudo mkdir -p /var/www/wordpress
sudo chown www-data:www-data /var/www/wordpress
```

### 5.3 Buat server block

```bash
sudo nano /etc/nginx/sites-available/wordpress-cbt
```

> **Penting:** Letakkan konfigurasi `server { ... }` di file terpisah ini, jangan langsung di `/etc/nginx/nginx.conf`.

Isi file:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name ujian.example.sch.id;

    root /var/www/wordpress;
    index index.php index.html;

    access_log /var/log/nginx/wordpress-cbt.access.log;
    error_log /var/log/nginx/wordpress-cbt.error.log;

    # Permalink WordPress
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # === phpMyAdmin (opsional — hapus blok ini jika tidak install phpMyAdmin di Langkah 6) ===
    location = /phpmyadmin {
        return 301 /phpmyadmin/;
    }

    location /phpmyadmin/ {
        alias /usr/share/phpmyadmin/;
        index index.php;
        try_files $uri $uri/ =404;
    }

    location ~* ^/phpmyadmin/(.+\.(?:css|js|png|jpe?g|gif|ico|svg|woff2?|ttf|eot|map))$ {
        alias /usr/share/phpmyadmin/$1;
        expires 7d;
        add_header Cache-Control "public";
        access_log off;
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
    # === Akhir blok phpMyAdmin ===

    # PHP handler — pastikan HTTP_AUTHORIZATION diteruskan (wajib untuk login CBT)
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 120;
        fastcgi_send_timeout 120;
        fastcgi_connect_timeout 60;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # Cache asset build CBT (vite manifest hash = immutable)
    location ~* ^/wp-content/plugins/cbt-exam-system/public/build/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
        access_log off;
    }

    # Cache static asset umum
    location ~* \.(?:css|js|jpg|jpeg|gif|png|webp|svg|ico|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public";
        try_files $uri =404;
        access_log off;
    }

    # Blokir eksekusi PHP di folder upload
    location ~* /(?:uploads|files)/.*\.php$ {
        deny all;
    }

    # Lindungi wp-config.php
    location = /wp-config.php {
        deny all;
    }

    # Blokir dotfiles (kecuali .well-known untuk SSL)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> **Penting:** Ganti semua `php8.3-fpm.sock` sesuai versi PHP di server Anda.

### 5.4 Aktifkan site

```bash
sudo rm -f /etc/nginx/sites-enabled/default
sudo ln -s /etc/nginx/sites-available/wordpress-cbt /etc/nginx/sites-enabled/wordpress-cbt
sudo nginx -t
sudo systemctl reload nginx
```

---

## 6. Install phpMyAdmin (Opsional)

phpMyAdmin berguna untuk mengelola database lewat browser. Bagian ini opsional dan bisa dilewati.

### 6.1 Install paket

```bash
sudo apt install -y phpmyadmin
```

Saat installer bertanya:
- **Web server**: jangan pilih `apache2` (kita pakai Nginx)
- **Configure database with dbconfig-common**: pilih `Yes`
- **Password**: masukkan password aplikasi phpMyAdmin atau biarkan auto-generate

### 6.2 Buat user MySQL untuk phpMyAdmin

User `root` MySQL pada Ubuntu sering memakai `auth_socket`, sehingga tidak bisa login dari phpMyAdmin. Buat user khusus:

```bash
sudo mysql
```

```sql
CREATE USER 'pma_cbt_admin'@'localhost' IDENTIFIED BY 'ganti_password_phpmyadmin_yang_kuat';
GRANT ALL PRIVILEGES ON wordpress_cbt.* TO 'pma_cbt_admin'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 6.3 Verifikasi Nginx dan akses phpMyAdmin

Blok Nginx untuk phpMyAdmin sudah termasuk dalam server block di **Langkah 5.3**. Jika Anda melewati blok tersebut saat konfigurasi Nginx, tambahkan sekarang lalu reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Akses phpMyAdmin:

```text
https://ujian.example.sch.id/phpmyadmin/
```

### 6.4 Hardening phpMyAdmin

- Pakai password kuat untuk user `pma_cbt_admin`.
- Jangan login dengan user MySQL `root`.
- Batasi akses `/phpmyadmin/` lewat firewall, VPN, atau allowlist IP admin.
- Hapus phpMyAdmin jika sudah tidak dibutuhkan:

```bash
sudo apt purge -y phpmyadmin
sudo apt autoremove -y
```


---

## 7. Install WordPress

Nginx sudah siap melayani request. Sekarang isi web root dengan WordPress.

### 7.1 Download dan ekstrak

```bash
cd /var/www
sudo curl -L -o wordpress.tar.gz https://wordpress.org/latest.tar.gz
sudo tar -xzf wordpress.tar.gz
sudo rm wordpress.tar.gz
sudo chown -R www-data:www-data /var/www/wordpress
sudo find /var/www/wordpress -type d -exec chmod 755 {} \;
sudo find /var/www/wordpress -type f -exec chmod 644 {} \;
```

### 7.2 Buat wp-config.php

Ada dua cara: lewat **browser** (lebih mudah) atau lewat **terminal**. Pilih salah satu.

---

#### Opsi A: Via Browser (Direkomendasikan untuk Pemula)

WordPress menyediakan wizard instalasi yang otomatis membuat `wp-config.php`. Cukup buka browser dan ikuti langkah di layar.

1. Buka browser, akses server Anda:

```text
http://ujian.example.sch.id/
```

> Jika HTTPS belum aktif (Langkah 8 belum dilakukan), gunakan `http://` dulu. Jika domain belum diarahkan ke server, gunakan IP server: `http://IP_SERVER/`.

2. WordPress akan menampilkan halaman **"Let's go!"** — klik tombolnya.

3. Isi form database sesuai yang dibuat di Langkah 4:

| Field | Isi |
|-------|-----|
| Database Name | `wordpress_cbt` |
| Username | `wp_cbt` |
| Password | *(password dari Langkah 4)* |
| Database Host | `localhost` |
| Table Prefix | `wp_` (biarkan default) |

4. Klik **"Submit"**, lalu **"Run the installation"**.

5. Isi informasi situs (judul, admin user, password, email), lalu klik **"Install WordPress"**.

6. Setelah berhasil, login ke dashboard WordPress.

> **Catatan:** Wizard ini otomatis men-generate authentication keys/salts yang unik. Anda tidak perlu menjalankan command `curl` untuk generate salt secara manual.

WordPress sudah berjalan. Sekarang **tambahkan konfigurasi runtime** (langkah di bawah tetap wajib untuk kedua opsi).

---

#### Opsi B: Via Terminal

Jika Anda lebih nyaman bekerja dari terminal, atau server tidak bisa diakses via browser saat ini:

```bash
cd /var/www/wordpress
sudo -u www-data cp wp-config-sample.php wp-config.php
sudo nano wp-config.php
```

**Isi konfigurasi database** (sesuaikan dengan user/password dari Langkah 4):

```php
define('DB_NAME', 'wordpress_cbt');
define('DB_USER', 'wp_cbt');
define('DB_PASSWORD', 'ganti_password_database_yang_kuat');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
```

**Ganti authentication keys/salts** dengan nilai unik:

```bash
curl -s https://api.wordpress.org/secret-key/1.1/salt/
```

Salin seluruh output command tersebut, lalu ganti blok placeholder keys/salts di `wp-config.php`.

---

#### Konfigurasi Tambahan (Wajib — Berlaku untuk Opsi A dan B)

**Tambahkan konfigurasi runtime** berikut **sebelum** baris `/* That's all, stop editing! */`:

```php
/* === WordPress Performance === */
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
define('DISABLE_WP_CRON', true);        // Akan diganti cron OS di Langkah 11
define('AUTOSAVE_INTERVAL', 120);
define('WP_POST_REVISIONS', 5);

/* === HTTPS Proxy Header (wajib jika memakai Cloudflare Tunnel / reverse proxy SSL) === */
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

/* === Redis Object Cache (diaktifkan di Langkah 9) === */
define('WP_CACHE', true);

// Opsi A — Unix socket, direkomendasikan untuk single server.
define('WP_REDIS_HOST', '/var/run/redis/redis.sock'); // Unix socket, direkomendasikan untuk single server
define('WP_REDIS_PORT', 0);                           // Port tidak dipakai saat host berupa socket
define('WP_REDIS_DATABASE', 1);
define('WP_REDIS_PREFIX', 'cbt_exam_system:');

/* === CBT Runtime Buffer (Redis DB terpisah untuk data ujian realtime) === */
define('CBT_RUNTIME_BUFFER_ENABLED', true);
define('CBT_RUNTIME_BUFFER_FALLBACK_TO_DB', true);
define('CBT_RUNTIME_REDIS_HOST', '/var/run/redis/redis.sock');
define('CBT_RUNTIME_REDIS_PORT', 0);
define('CBT_RUNTIME_REDIS_DATABASE', 2);
define('CBT_RUNTIME_REDIS_DB', 2);       // Alias kompatibilitas
define('CBT_RUNTIME_REDIS_PREFIX', 'cbt_runtime:');

/* === CBT Auth & Gate Redis (default sama dengan Runtime, bisa dipisah jika perlu) === */
define('CBT_REDIS_HOST', '/var/run/redis/redis.sock');
define('CBT_REDIS_PORT', 0);
define('CBT_REDIS_DATABASE', 2);

/*
 * Opsi B — TCP localhost, pakai ini jika Unix socket belum dipakai
 * atau Redis berada di host/port TCP.
 *
 * define('WP_REDIS_HOST', '127.0.0.1');
 * define('WP_REDIS_PORT', 6379);
 * define('CBT_RUNTIME_REDIS_HOST', '127.0.0.1');
 * define('CBT_RUNTIME_REDIS_PORT', 6379);
 * define('CBT_REDIS_HOST', '127.0.0.1');
 * define('CBT_REDIS_PORT', 6379);
 */

/* === CBT JWT Secret (wajib untuk produksi) === */
// Jika tidak didefinisikan, plugin fallback ke wp_salt('auth').
// Mendefinisikan secret sendiri mencegah semua token siswa invalid
// saat salt WordPress berubah (misal reinstall atau migrasi).
// Buat nilai acak dengan: openssl rand -base64 48
define('CBT_JWT_SECRET', 'ganti_dengan_random_string_panjang_64_karakter');
```

Jika Redis memakai password, tambahkan juga:

```php
define('WP_REDIS_PASSWORD', 'ganti_password_redis');
define('CBT_RUNTIME_REDIS_PASSWORD', 'ganti_password_redis');
define('CBT_REDIS_PASSWORD', 'ganti_password_redis');
```

> **Catatan:** Konfigurasi Redis di atas sudah ditulis sekarang supaya tidak perlu mengedit `wp-config.php` dua kali. Redis sendiri diaktifkan di **Langkah 9**.
> Jika Redis berada di server berbeda, ganti host Redis kembali ke IP/hostname Redis dan pakai port `6379`. Unix socket hanya berlaku jika PHP-FPM dan Redis berada di server yang sama.

---

## 8. Aktifkan HTTPS

HTTPS **wajib** untuk ujian produksi. Jangan jalankan halaman ujian siswa di HTTP polos.

Pilih salah satu metode sesuai infrastruktur Anda:

### Opsi A: Cloudflare Tunnel (Direkomendasikan)

Jika Anda menggunakan **Cloudflare Tunnel** (`cloudflared`), sertifikat SSL dikelola otomatis oleh Cloudflare. Nginx cukup listen di port 80 (HTTP) — tunnel yang mengenkripsi koneksi ke client. Anda **tidak perlu** membuka port 80/443 di firewall server.

**Prasyarat:** Domain Anda sudah ditambahkan ke akun Cloudflare dan nameserver sudah diarahkan ke Cloudflare.

> **Penting:** Jika SSL berhenti di Cloudflare/Tunnel/reverse proxy, pastikan blok `HTTPS Proxy Header` sudah ditambahkan di `wp-config.php` pada Langkah 7.2. Tanpa itu, WordPress bisa mengira request masih HTTP dan memicu redirect loop, mixed content, atau URL asset yang salah.

**Langkah 1 — Install `cloudflared`:**

```bash
# Tambah repository Cloudflare
sudo mkdir -p --mode=0755 /usr/share/keyrings
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | sudo tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/cloudflared.list

sudo apt update
sudo apt install -y cloudflared
cloudflared --version
```

**Langkah 2 — Login ke akun Cloudflare:**

```bash
cloudflared tunnel login
```

Perintah ini akan menampilkan URL. Buka URL tersebut di browser, pilih domain yang akan dipakai, lalu otorisasi. Setelah berhasil, file credential tersimpan di `~/.cloudflared/cert.pem`.

**Langkah 3 — Buat tunnel baru:**

```bash
cloudflared tunnel create cbt-ujian
```

Output akan menampilkan **Tunnel ID** (format UUID). Catat ID ini. File credential tunnel tersimpan di `~/.cloudflared/<TUNNEL_ID>.json`.

Verifikasi tunnel sudah terdaftar dan file credential tersedia:

```bash
cloudflared tunnel list
cloudflared tunnel info cbt-ujian
test -f ~/.cloudflared/<TUNNEL_ID>.json && echo "Credential tunnel OK"
```

Pastikan output `cloudflared tunnel list` menampilkan nama `cbt-ujian`, lalu ganti `<TUNNEL_ID>` pada command `test -f` dengan UUID tunnel yang muncul dari perintah `create`.

**Langkah 4 — Buat DNS record:**

Perintah ini otomatis membuat CNAME record di Cloudflare yang mengarah ke tunnel Anda:

```bash
cloudflared tunnel route dns cbt-ujian ujian.example.sch.id
```

> Ganti `ujian.example.sch.id` dengan subdomain/domain Anda. Anda bisa mengecek hasilnya di dashboard Cloudflare pada menu DNS.

**Langkah 5 — Buat file konfigurasi tunnel:**

```bash
nano ~/.cloudflared/config.yml
```

Isi dengan (ganti `<TUNNEL_ID>` dengan ID dari Langkah 3):

```yaml
tunnel: <TUNNEL_ID>
credentials-file: /root/.cloudflared/<TUNNEL_ID>.json

ingress:
  - hostname: ujian.example.sch.id
    service: http://localhost:80
  - service: http_status:404
```

**Langkah 6 — Test tunnel secara manual (opsional):**

```bash
cloudflared tunnel run cbt-ujian
```

Buka browser dan cek apakah `https://ujian.example.sch.id/` sudah bisa diakses. Tekan `Ctrl+C` untuk menghentikan setelah selesai test.

**Langkah 7 — Jalankan sebagai service (agar otomatis jalan saat boot):**

```bash
sudo cloudflared service install
sudo systemctl enable --now cloudflared
sudo systemctl status cloudflared --no-pager
```

**Verifikasi:**

```text
https://ujian.example.sch.id/
```

### Opsi B: Certbot (SSL Langsung di Server)

Jika server langsung menghadap internet tanpa tunnel/proxy:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d ujian.example.sch.id
```

Verifikasi auto-renewal:

```bash
sudo certbot renew --dry-run
```

---

**Setelah HTTPS aktif** (dengan cara apa pun), buka browser dan pastikan WordPress bisa diakses:

```text
https://ujian.example.sch.id/
```

Jika sebelumnya Anda memilih **Opsi B** di Langkah 7.2 (terminal), WordPress akan menampilkan wizard instalasi saat pertama kali dibuka. Selesaikan wizard tersebut (buat akun administrator) sebelum lanjut. Jika sudah memakai **Opsi A** di Langkah 7.2 (browser), wizard sudah selesai — cukup pastikan situs bisa diakses via HTTPS.

---

## 9. Aktifkan Redis Object Cache

Redis mempercepat WordPress dengan menyimpan query database yang sering diakses di memori. Plugin CBT juga menggunakan Redis untuk buffer jawaban realtime saat ujian.

### 9.1 Jalankan Redis

```bash
sudo systemctl enable --now redis-server
redis-cli ping
```

Output yang diharapkan: `PONG`

Jika ingin memakai Redis lewat TCP localhost seperti konfigurasi bawaan, pastikan command berikut juga berhasil:

```bash
redis-cli -h 127.0.0.1 -p 6379 PING
```

Untuk TCP localhost, gunakan konfigurasi Redis di `wp-config.php` Opsi B:

```php
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('CBT_RUNTIME_REDIS_HOST', '127.0.0.1');
define('CBT_RUNTIME_REDIS_PORT', 6379);
define('CBT_REDIS_HOST', '127.0.0.1');
define('CBT_REDIS_PORT', 6379);
```

### 9.2 Opsi Rekomendasi: Redis Unix Socket

Karena panduan ini memakai mode **single server** (PHP-FPM dan Redis di mesin yang sama), gunakan Redis Unix socket agar PHP tidak melewati network stack TCP `127.0.0.1` untuk setiap operasi Redis.

**Langkah 1 — Pastikan direktori `/var/run/redis/` ada dan dimiliki Redis:**

```bash
ls -la /var/run/ | grep redis
```

Output yang diharapkan:
```
drwxr-xr-x  2 redis redis  ... redis/
```

Jika direktori tidak ada atau owner-nya bukan `redis`, jalankan:

```bash
sudo mkdir -p /var/run/redis
sudo chown redis:redis /var/run/redis
sudo chmod 755 /var/run/redis
```

**Langkah 2 — Set path socket dan permission di konfigurasi Redis:**

```bash
sudo nano /etc/redis/redis.conf
```

Di dalam file, cari baris `unixsocket` dengan `Ctrl+W` lalu ketik `unixsocket`. Baris ini biasanya sudah ada tapi **dikomentari dengan `#`**. Hapus `#` dan **pastikan nilainya persis seperti di bawah** — termasuk nama file `redis.sock` (bukan `redis-server.sock`):

```conf
unixsocket /var/run/redis/redis.sock
unixsocketperm 770
```

> **Penting:** Di Ubuntu/Debian, Redis secara default menggunakan nama socket `redis-server.sock` dan permission `700`. Kedua baris di atas **wajib diset secara eksplisit** agar socket bernama `redis.sock` dengan permission `770` sehingga PHP-FPM (via group `redis`) bisa mengaksesnya.

> Jika kedua baris tidak ditemukan sama sekali, tambahkan di bagian bawah file sebelum baris terakhir.

Simpan file: `Ctrl+X` → `Y` → `Enter`.

**Langkah 3 — Berikan akses socket ke PHP-FPM dan restart service:**

```bash
sudo usermod -aG redis www-data
sudo systemctl restart redis-server

# Sesuaikan versi PHP-FPM jika bukan php8.3-fpm
sudo systemctl restart php8.3-fpm
```

**Langkah 4 — Verifikasi socket muncul dengan nama dan permission yang benar:**

```bash
ls -la /var/run/redis/
```

Output yang diharapkan — nama `redis.sock`, permission `srwxrwx---`:
```
-rw-rw----  1 redis redis  ... redis-server.pid
srwxrwx---  1 redis redis  ... redis.sock
```

Jika output berbeda, diagnosis sesuai kasusnya:

**Kasus A — Socket ada tapi namanya `redis-server.sock` (bukan `redis.sock`):**
Baris `unixsocket` di `redis.conf` belum terbaca. Cek:
```bash
# Harus ada output — jika kosong, baris masih ter-comment, ulangi Langkah 2
sudo grep -n "^unixsocket " /etc/redis/redis.conf
```
Setelah diperbaiki, restart Redis: `sudo systemctl restart redis-server`

**Kasus B — Socket ada tapi permission-nya `srwx------` (700), bukan `srwxrwx---` (770):**
Baris `unixsocketperm 770` di `redis.conf` belum terbaca. Cek:
```bash
# Harus ada output "unixsocketperm 770" — jika kosong atau nilainya berbeda, ulangi Langkah 2
sudo grep -n "^unixsocketperm" /etc/redis/redis.conf
```
Setelah diperbaiki, restart Redis: `sudo systemctl restart redis-server`

**Langkah 5 — Verifikasi koneksi dapat diakses:**

```bash
redis-cli -s /var/run/redis/redis.sock PING
sudo -u www-data redis-cli -s /var/run/redis/redis.sock PING
```

Kedua perintah harus menghasilkan `PONG`.

Jika perintah `sudo -u www-data ...` menghasilkan `Permission denied`:

```bash
# Cek apakah www-data sudah masuk group redis
groups www-data

# Jika redis tidak muncul di output, tambahkan ulang lalu restart PHP-FPM:
sudo usermod -aG redis www-data
sudo systemctl restart php8.3-fpm
```

> **Catatan:** Plugin CBT otomatis memakai mode Unix socket jika host Redis di `wp-config.php` dimulai dengan `/`, misalnya `/var/run/redis/redis.sock`. Jangan tulis `unix:///var/run/redis/redis.sock` untuk konstanta CBT. Pastikan nilai `CBT_RUNTIME_REDIS_HOST` dan `WP_REDIS_HOST` di `wp-config.php` adalah `/var/run/redis/redis.sock` (dimulai dengan `/`, bukan IP). Jika verifikasi socket masih gagal setelah semua langkah di atas, kembali dulu ke TCP localhost Opsi B agar Redis tetap jalan.

### 9.3 Install plugin Redis Object Cache

**Via WP-CLI (direkomendasikan):**

```bash
cd /var/www/wordpress
sudo -u www-data wp plugin install redis-cache --activate
sudo -u www-data wp redis enable
```

**Via dashboard WordPress:**

1. Masuk ke `Plugins > Add New`.
2. Cari dan install `Redis Object Cache`.
3. Aktifkan plugin.
4. Klik `Enable Object Cache`.

### 9.4 Verifikasi

```bash
cd /var/www/wordpress
sudo -u www-data wp eval 'var_dump(wp_using_ext_object_cache());'
test -f /var/www/wordpress/wp-content/object-cache.php && echo "object-cache.php OK"
```

Output `wp_using_ext_object_cache()` harus `bool(true)`.

---

## 10. Install Plugin CBT Exam System

### 10.1 Clone atau salin plugin

```bash
cd /var/www/wordpress/wp-content/plugins
sudo -u www-data git clone https://github.com/coblax/CBT-EXAM-SYSTEM cbt-exam-system
```

### 10.2 Install dependency PHP (Composer)

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
sudo chown -R www-data:www-data .
sudo -u www-data env HOME=/tmp composer install --no-dev --optimize-autoloader
```

> **Penting tentang ownership:** Selalu jalankan Composer dengan user yang sama dengan pemilik folder plugin (`www-data`). Jangan campur `sudo composer install` dengan `sudo -u www-data composer install` — ini menyebabkan file `vendor/` berbeda ownership dan Composer gagal di kemudian hari.

### 10.3 Build asset frontend

Langkah ini **wajib** jika folder `public/build/` belum ada atau file `public/build/manifest.json` tidak ditemukan. Jika Anda men-deploy dari release yang sudah membawa `public/build/manifest.json`, langkah ini boleh dilewati.

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
sudo -u www-data env HOME=/tmp npm ci
sudo -u www-data env HOME=/tmp npm run build
```

Verifikasi build berhasil:

```bash
test -f public/build/manifest.json && echo "Build OK" || echo "BUILD GAGAL - jangan lanjut!"
```

> **Jika build gagal**, jangan lanjutkan ke aktivasi. Cek versi Node.js (`node -v`) dan pastikan minimal versi 20.

Setelah build berhasil, folder `node_modules` boleh dihapus untuk menghemat disk:

```bash
rm -rf node_modules
```

### 10.4 Rapikan permission

Jalankan **setelah** Composer dan build asset selesai:

```bash
sudo chown -R www-data:www-data /var/www/wordpress/wp-content/plugins/cbt-exam-system
sudo find /var/www/wordpress/wp-content/plugins/cbt-exam-system -type d -exec chmod 755 {} \;
sudo find /var/www/wordpress/wp-content/plugins/cbt-exam-system -type f -exec chmod 644 {} \;
sudo find /var/www/wordpress/wp-content/plugins/cbt-exam-system/vendor/bin -type f -exec chmod 755 {} \; 2>/dev/null || true
```

### 10.5 Unit Test (Opsional — hanya untuk staging)

Di server **produksi**, unit test tidak perlu dijalankan. Lanjut ke Langkah 11.

Jika ingin menjalankan unit test di staging, ikuti langkah-langkah berikut:

**Langkah 1 — Install PHPUnit (PHP Unit Test):**

Pada Langkah 10.2 sebelumnya, Composer dijalankan dengan `--no-dev` sehingga package test tidak diinstall. Jalankan ulang **tanpa** `--no-dev` agar PHPUnit, Brain Monkey, dan Mockery tersedia:

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
sudo chown -R www-data:www-data .
sudo -u www-data env HOME=/tmp composer install --optimize-autoloader
```

Verifikasi PHPUnit terinstall:

```bash
vendor/bin/phpunit --version
```

**Langkah 2 — Install Vitest (JS Unit Test):**

Vitest sudah termasuk dalam `devDependencies` di `package.json`. Jika `node_modules` sudah ada dari Langkah 10.3, Vitest sudah tersedia. Jika `node_modules` sudah dihapus, install ulang:

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
sudo -u www-data env HOME=/tmp npm ci
```

Verifikasi Vitest terinstall:

```bash
./node_modules/.bin/vitest --version
```

**Langkah 3 — Jalankan test:**

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system

# PHP Unit Test (PHPUnit + Brain Monkey + Mockery)
sudo -u www-data env HOME=/tmp composer test:php

# JS Unit Test (Vitest + jsdom)
sudo -u www-data env HOME=/tmp npm run test:js
```

**Langkah 4 — Kembalikan ke mode produksi:**

Setelah test selesai, hapus dependency dev agar folder `vendor/` tetap ringan:

```bash
sudo -u www-data env HOME=/tmp composer install --no-dev --optimize-autoloader
```

### 10.6 Playwright E2E & CBT Test Hub (Opsional — hanya untuk staging)

Plugin CBT memiliki **Test Hub** di dashboard admin (`CBT Test Hub`) yang bisa menjalankan unit test dan **flow check E2E** (end-to-end) menggunakan Playwright. Fitur ini berguna untuk memverifikasi alur ujian secara menyeluruh: login → start → autosave → finish → result.

> **Catatan:** Bagian ini hanya untuk server staging/development. Di server produksi, Test Hub tetap bisa menjalankan unit test (PHPUnit/Vitest), tetapi flow check E2E membutuhkan browser headless yang tidak lazim dipasang di produksi.

**Langkah 1 — Install dependency sistem untuk Chromium headless:**

Playwright membutuhkan library sistem untuk menjalankan browser. Di Ubuntu/Debian:

```bash
sudo apt update

sudo apt install -y libnss3 libatk1.0-0t64 libatk-bridge2.0-0t64 libcups2t64 libdrm2 \
  libxkbcommon0 libxcomposite1 libxdamage1 libxrandr2 libgbm1 libpango-1.0-0 \
  libcairo2 libasound2t64 libxshmfence1
```

**Langkah 2 — Install Chromium via Playwright:**

Plugin menyimpan browser Chromium di folder lokal `.playwright-browsers` agar tidak mengganggu sistem:

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
sudo -u www-data env HOME=/tmp npm run playwright:install:chromium
```

Verifikasi:

```bash
ls .playwright-browsers/
```

Harus ada folder `chromium-*` di dalamnya.

**Langkah 3 — Konfigurasi base URL:**

Playwright perlu tahu URL WordPress yang bisa diakses. Set via environment variable atau dari dashboard **CBT Test Hub > Settings**:

```bash
# Via environment variable (untuk CLI)
export CBT_E2E_BASE_URL="https://ujian.example.sch.id"
```

Atau via dashboard: buka `CBT Test Hub`, klik tab **Settings**, isi **E2E Base URL**.

**Langkah 4 — Jalankan E2E test:**

**Via CLI:**

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system

# Jalankan semua E2E test
sudo -u www-data env HOME=/tmp PLAYWRIGHT_BROWSERS_PATH=.playwright-browsers \
  CBT_E2E_BASE_URL="https://ujian.example.sch.id" npm run test:e2e

# Jalankan flow check spesifik (contoh: recovery)
sudo -u www-data env HOME=/tmp PLAYWRIGHT_BROWSERS_PATH=.playwright-browsers \
  CBT_E2E_BASE_URL="https://ujian.example.sch.id" npm run test:e2e:recovery
```

**Via dashboard CBT Test Hub:**

1. Buka `CBT Test Hub` di dashboard admin.
2. Pilih tab area yang ingin ditest (Recovery, Sync, Auth, Timer, dll).
3. Klik **Queue Checklist Flow Check** untuk mengantrekan flow E2E ke background job.
4. Hasil flow check bisa dilihat langsung di panel Test Hub.

> **Penting:** Flow check dari Test Hub membutuhkan `proc_open` aktif di PHP (default aktif). Jika dinonaktifkan di `php.ini` (`disable_functions`), runner test tidak bisa berjalan.

---

## 11. Aktifkan Plugin & Selesaikan Konfigurasi

### 11.1 Aktifkan plugin CBT

```bash
cd /var/www/wordpress
sudo -u www-data wp plugin activate cbt-exam-system
```

Atau aktifkan dari `Dashboard > Plugins`.

Saat aktivasi, plugin otomatis:
- Membuat/migrasi tabel database CBT
- Menyiapkan role dan capability CBT
- Membuat halaman frontend kanonik CBT
- Menjadwalkan cron worker plugin

### 11.2 Atur permalink

Permalink harus aktif agar URL halaman dan REST API bekerja dengan benar.

```bash
cd /var/www/wordpress
sudo -u www-data env HOME=/tmp wp rewrite structure '/%postname%/' --hard
sudo -u www-data env HOME=/tmp wp rewrite flush --hard
```

> **Penjelasan:** `env HOME=/tmp` mencegah error WP-CLI saat user `www-data` tidak punya home directory yang writable.

### 11.3 Atur cron OS

Di `wp-config.php` sebelumnya kita menonaktifkan cron bawaan WordPress (`DISABLE_WP_CRON = true`) karena cron bawaan hanya berjalan saat ada pengunjung — tidak reliable untuk ujian. Sebagai gantinya, kita buat cron OS yang berjalan setiap menit.

```bash
sudo crontab -u www-data -e
```

Tambahkan baris ini, lalu simpan:

```cron
* * * * * /usr/bin/php /var/www/wordpress/wp-cron.php > /dev/null 2>&1
```

Verifikasi:

```bash
sudo crontab -u www-data -l
```

Cek event cron CBT sudah terdaftar:

```bash
cd /var/www/wordpress
sudo -u www-data env HOME=/tmp wp cron event list | grep -E 'cbt|hook|next_run'
```

> **Mengapa cron ini penting?** Worker CBT seperti flush runtime buffer, finalisasi attempt expired, security ingest, preflight check, dan warm readiness semuanya bergantung pada cron ini. Jika tidak berjalan, jawaban siswa bisa terlambat disimpan dan attempt expired tidak terproses.

---

## 12. Setup Awal CBT

Setelah plugin aktif, lakukan konfigurasi awal dari dashboard WordPress:

| No | Menu | Tujuan |
|----|------|--------|
| 1 | **CBT Cache** | Pastikan `Readiness = ready`, `Backend Hint = redis`, `Probe Status = passed` |
| 2 | **CBT Branding** | Atur identitas sekolah (logo, nama, warna) |
| 3 | **CBT Subjects** | Buat mata pelajaran |
| 4 | **CBT Users** | Import atau buat akun guru dan siswa |
| 5 | **CBT Questions** | Import atau buat bank soal |
| 6 | **CBT Exams** | Buat ujian, atur jadwal, target kelas, token, randomisasi |

Halaman ujian siswa bisa diakses di:

```text
https://ujian.example.sch.id/cbt-ujian/
```

### Jadikan halaman CBT sebagai homepage

Agar siswa langsung melihat halaman ujian saat membuka domain utama (tanpa harus mengetik `/cbt-ujian/`):

**Via dashboard:**

1. Buka `Settings > Reading`.
2. Pilih `A static page` pada "Your homepage displays".
3. Pilih halaman `CBT Ujian` sebagai Homepage.
4. Simpan.

**Via WP-CLI:**

```bash
cd /var/www/wordpress
FRONT_PAGE_ID=$(sudo -u www-data env HOME=/tmp wp option get cbt_exam_system_frontend_page_id)
sudo -u www-data env HOME=/tmp wp option update show_on_front page
sudo -u www-data env HOME=/tmp wp option update page_on_front "$FRONT_PAGE_ID"
sudo -u www-data env HOME=/tmp wp rewrite flush --hard
```

---

## 13. Smoke Test

Jalankan pengecekan ini untuk memastikan semua komponen berjalan sebelum lanjut ke tuning.

### 13.1 Test dari terminal

```bash
# Test akses HTTPS
curl -I https://ujian.example.sch.id/

# Test halaman CBT (jika belum dijadikan homepage)
curl -I https://ujian.example.sch.id/cbt-ujian/

# Cek status plugin
cd /var/www/wordpress
sudo -u www-data env HOME=/tmp wp plugin status cbt-exam-system
sudo -u www-data env HOME=/tmp wp option get cbt_exam_system_db_version
sudo -u www-data env HOME=/tmp wp cron event list | grep cbt
sudo -u www-data env HOME=/tmp wp eval 'var_dump(wp_using_ext_object_cache());'
```

### 13.2 Test file plugin

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
test -f vendor/autoload.php && echo "Composer OK" || echo "GAGAL: vendor/autoload.php tidak ada"
test -f public/build/manifest.json && echo "Build OK" || echo "GAGAL: manifest.json tidak ada"
```

### 13.3 Test manual (wajib sebelum ujian nyata)

1. Login sebagai admin WordPress.
2. Buat 1 subject kecil.
3. Buat 1 user siswa.
4. Buat 1 exam kecil (3-5 soal).
5. Publish exam.
6. Login sebagai siswa dari halaman ujian.
7. Start attempt, isi jawaban, finish exam.
8. Cek hasil di `CBT Results`.

> **Jika semua test di atas berhasil**, stack dasar sudah hidup. Lanjutkan ke langkah berikutnya.

---

## 14. Backup & Restore

Backup adalah langkah krusial yang sering terlupakan. Lakukan backup **sebelum dan sesudah** setiap periode ujian.

### 14.1 Backup database

```bash
# Backup seluruh database WordPress + data CBT
mysqldump -u wp_cbt -p wordpress_cbt --single-transaction --routines --triggers \
  > /var/backups/wordpress_cbt_$(date +%Y%m%d_%H%M%S).sql
```

> **Penting:** Gunakan `--single-transaction` agar backup tidak mengunci tabel saat ujian berlangsung.

### 14.2 Backup file

```bash
# Backup wp-content (plugin, upload, tema)
sudo tar -czf /var/backups/wp-content_$(date +%Y%m%d_%H%M%S).tar.gz \
  -C /var/www/wordpress wp-content

# Backup wp-config.php secara terpisah
sudo cp /var/www/wordpress/wp-config.php \
  /var/backups/wp-config_$(date +%Y%m%d_%H%M%S).php
```

### 14.3 Otomasi backup harian (direkomendasikan)

Buat script backup dan jadwalkan via cron:

```bash
sudo nano /usr/local/bin/backup-cbt.sh
```

Isi:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/cbt"
mkdir -p "$BACKUP_DIR"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Database
mysqldump -u wp_cbt -p'GANTI_PASSWORD' wordpress_cbt \
  --single-transaction --routines --triggers \
  | gzip > "$BACKUP_DIR/db_$TIMESTAMP.sql.gz"

# File
tar -czf "$BACKUP_DIR/files_$TIMESTAMP.tar.gz" \
  -C /var/www/wordpress wp-content wp-config.php

# Hapus backup lebih dari 14 hari
find "$BACKUP_DIR" -name "*.gz" -mtime +14 -delete

echo "Backup selesai: $TIMESTAMP"
```

```bash
sudo chmod +x /usr/local/bin/backup-cbt.sh
```

Jadwalkan backup harian pukul 02:00:

```bash
sudo crontab -e
```

Tambahkan:

```text
0 2 * * * /usr/local/bin/backup-cbt.sh >> /var/log/backup-cbt.log 2>&1
```

### 14.4 Restore (jika diperlukan)

```bash
# Restore database
gunzip < /var/backups/cbt/db_YYYYMMDD_HHMMSS.sql.gz | mysql -u wp_cbt -p wordpress_cbt

# Restore file
sudo tar -xzf /var/backups/cbt/files_YYYYMMDD_HHMMSS.tar.gz -C /var/www/wordpress
sudo chown -R www-data:www-data /var/www/wordpress/wp-content
```

> **Tips:** Simpan salinan backup ke lokasi eksternal (cloud storage, external drive) untuk perlindungan dari kegagalan hardware.

---

## 15. Catatan Cache & CDN

Jangan cache halaman HTML ujian dan endpoint REST CBT. Jika memakai CDN, reverse proxy, atau FastCGI cache, **bypass** minimal untuk:

```text
/wp-admin/*
/wp-login.php
/wp-json/cbt/v1/*
/cbt-ujian/*
/phpmyadmin/*
```

Static asset (CSS, JS, font, gambar) **boleh** dicache. Konfigurasi Nginx di atas sudah memberi cache header untuk file statis.

---

## 16. Monitoring Saat Ujian

Pantau service utama selama ujian berlangsung:

```bash
# Resource server
htop
free -h
df -h

# Status service
systemctl status nginx php8.3-fpm mysql redis-server --no-pager

# Log realtime
journalctl -u php8.3-fpm -n 100 --no-pager
journalctl -u nginx -n 100 --no-pager
tail -f /var/log/nginx/wordpress-cbt.error.log
tail -f /var/log/mysql/slow-query.log

# Redis memory
redis-cli info memory
```

**Tanda-tanda yang perlu ditangani segera:**

| Masalah | Tindakan |
|---------|----------|
| RAM habis / swap aktif terus | Turunkan `pm.max_children` PHP-FPM |
| PHP-FPM mencapai `pm.max_children` | Naikkan jika RAM masih longgar |
| Error Nginx `502` atau `504` | Cek socket PHP-FPM dan log |
| Slow query MySQL berulang | Cek index tabel, restart jika perlu |
| Redis mendekati `maxmemory` | Naikkan limit atau pisahkan server |
| Latency REST API CBT tinggi | Matikan plugin WP yang tidak penting |

---

## 17. Checklist Sebelum Tuning Produksi

Sebelum menerapkan tuning, pastikan semua item berikut sudah terpenuhi:

- [ ] HTTPS aktif dan sertifikat valid
- [ ] Jam server sinkron via NTP
- [ ] Backup database dan `wp-content/uploads` tersedia
- [ ] Redis object cache aktif dan CBT Cache readiness `ready`
- [ ] Redis Unix socket bisa diakses oleh `www-data` jika Redis satu server dengan PHP-FPM
- [ ] OPcache tersedia (`php -m` menampilkan `Zend OPcache`)
- [ ] `public/build/manifest.json` tersedia
- [ ] Cron OS berjalan tiap menit
- [ ] Tidak ada page cache untuk `/wp-json/cbt/v1/*` dan `/cbt-ujian/*`
- [ ] phpMyAdmin dibatasi aksesnya atau dihapus jika tidak dibutuhkan
- [ ] Minimal satu simulasi login → start → autosave → finish → result berhasil
- [ ] Log Nginx, PHP-FPM, MySQL, dan Redis mudah dipantau
- [ ] Nilai bawaan PHP-FPM, MySQL, Redis, dan Nginx sudah dicatat (untuk rollback)

---

## 18. Tuning Produksi (16 Core / 16 GB RAM)

Lakukan bagian ini **setelah** semua checklist di Langkah 17 terpenuhi. Tujuannya: jika ada error saat tuning, Anda tahu stack dasarnya sudah berjalan.

### 18.1 Tuning PHP-FPM

Edit pool PHP-FPM:

```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

Baseline untuk 16 core / 16 GB (MySQL dan Redis satu host):

```ini
pm = dynamic
pm.max_children = 64
pm.start_servers = 16
pm.min_spare_servers = 8
pm.max_spare_servers = 24
pm.max_requests = 500
request_terminate_timeout = 60s
request_slowlog_timeout = 5s
slowlog = /var/log/php-fpm-www-slow.log
```

**Panduan penyesuaian:**

| Kondisi | Tindakan |
|---------|----------|
| RAM sering hampir habis / swap aktif | Turunkan `max_children` ke 56 → 48 |
| Antrean PHP-FPM tinggi, CPU & RAM longgar | Naikkan `max_children` ke 80 → 96 |
| Database sudah dipisah ke server lain | `max_children` bisa lebih agresif |

Edit konfigurasi PHP:

```bash
sudo nano /etc/php/8.3/fpm/php.ini
```

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
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=30000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
realpath_cache_size=4096K
realpath_cache_ttl=600
```

**Catatan OPcache production:**
- `opcache.validate_timestamps=0` membuat PHP-FPM tidak mengecek perubahan file PHP di setiap request. Ini bagus untuk throughput produksi.
- Setelah update WordPress/plugin/theme, wajib reload/restart PHP-FPM agar kode baru terbaca.
- Untuk server development/staging yang sering edit file langsung di server, gunakan `opcache.validate_timestamps=1` dan `opcache.revalidate_freq=2`.
- JIT tidak diaktifkan sebagai default karena workload WordPress/CBT banyak I/O ke MySQL/Redis; uji terpisah jika ingin membandingkan.

Buat file slowlog:

```bash
sudo touch /var/log/php-fpm-www-slow.log
sudo chown www-data:adm /var/log/php-fpm-www-slow.log
sudo chmod 640 /var/log/php-fpm-www-slow.log
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
```

**Verifikasi OPcache aktif di PHP-FPM:**

```bash
php-fpm8.3 -i | grep -Ei 'opcache.enable|opcache.memory_consumption|opcache.max_accelerated_files|opcache.validate_timestamps'
```

Output yang diharapkan:
- `opcache.enable => On`
- `opcache.memory_consumption => 256`
- `opcache.max_accelerated_files => 30000`
- `opcache.validate_timestamps => Off`

### 18.2 Tuning MySQL/MariaDB

```bash
sudo nano /etc/mysql/conf.d/cbt-16core.cnf
```

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

max_connections=200
thread_cache_size=128
table_open_cache=8192
table_definition_cache=4096

tmp_table_size=64M
max_heap_table_size=64M
sort_buffer_size=2M
join_buffer_size=2M

slow_query_log=1
slow_query_log_file=/var/log/mysql/slow-query.log
long_query_time=1
```

**Catatan sizing:**
- `innodb_buffer_pool_size=6G` menyisakan RAM untuk PHP-FPM, Redis, Nginx, dan OS
- `max_connections=200` cukup untuk 64 PHP-FPM child
- Jika database dipisah ke server sendiri (16 GB), buffer pool bisa naik ke 10-11G
- Jika server mulai swap, turunkan buffer pool ke 5G

```bash
sudo systemctl restart mysql
sudo systemctl status mysql --no-pager
```

**Verifikasi MySQL/MariaDB:**

```bash
# Pastikan service hidup dan menerima koneksi lokal
sudo mysqladmin ping

# Pastikan database WordPress bisa diakses
sudo mysql -e "SHOW DATABASES LIKE 'wordpress_cbt';"

# Cek variabel tuning utama sudah aktif setelah restart
sudo mysql -e "
SHOW VARIABLES
WHERE Variable_name IN (
  'innodb_buffer_pool_size',
  'innodb_buffer_pool_instances',
  'innodb_log_file_size',
  'innodb_log_buffer_size',
  'innodb_flush_log_at_trx_commit',
  'innodb_flush_method',
  'innodb_io_capacity',
  'innodb_io_capacity_max',
  'innodb_read_io_threads',
  'innodb_write_io_threads',
  'max_connections',
  'thread_cache_size',
  'table_open_cache',
  'table_definition_cache',
  'tmp_table_size',
  'max_heap_table_size',
  'slow_query_log',
  'slow_query_log_file',
  'long_query_time'
);
"

# Ringkasan cepat angka yang paling penting
sudo mysql -e "
SELECT
  ROUND(@@global.innodb_buffer_pool_size / 1024 / 1024 / 1024, 2) AS buffer_pool_gb,
  @@global.max_connections AS max_connections,
  @@global.table_open_cache AS table_open_cache,
  @@global.slow_query_log AS slow_query_log,
  @@global.long_query_time AS long_query_time;
"

# Pastikan slow query log path bisa ditulis/dibaca
sudo ls -ld /var/log/mysql
sudo test -e /var/log/mysql/slow-query.log && sudo ls -lh /var/log/mysql/slow-query.log || echo "Slow log belum dibuat; normal jika belum ada query lambat."
```

Output yang diharapkan:
- `sudo mysqladmin ping` menampilkan `mysqld is alive`
- `innodb_buffer_pool_size` sekitar `6442450944` byte (`6G`)
- `max_connections` bernilai `200`
- `slow_query_log` bernilai `ON`
- `long_query_time` bernilai `1.000000`

Panduan ini memakai service `mysql`, jadi command utama yang dipakai tetap:

```bash
sudo systemctl restart mysql
sudo systemctl status mysql --no-pager
```

> **Catatan:** Pada sebagian distro yang benar-benar memakai MariaDB, nama service bisa `mariadb`. Gunakan `mariadb` hanya jika `systemctl status mysql` tidak ditemukan.

### 18.3 Tuning Redis

```bash
sudo nano /etc/redis/redis.conf
```

```conf
supervised systemd
bind 127.0.0.1 ::1
protected-mode yes
unixsocket /var/run/redis/redis.sock
unixsocketperm 770
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

**Catatan:**
- `noeviction` — key runtime ujian tidak akan dibuang diam-diam saat memori penuh
- `appendonly yes` + `appendfsync everysec` — perlindungan jika Redis restart saat ujian
- `unixsocket` + `unixsocketperm 770` — jalur cepat lokal untuk PHP-FPM, sesuai konfigurasi Redis di `wp-config.php`
- Jika mendekati `maxmemory`, naikkan ke `2gb` jika RAM server longgar

```bash
sudo systemctl restart redis-server
redis-cli ping
redis-cli -s /var/run/redis/redis.sock PING
sudo -u www-data redis-cli -s /var/run/redis/redis.sock PING
redis-cli info memory | grep -E 'used_memory_human|maxmemory_human|maxmemory_policy'
```

### 18.4 Tuning Global Nginx

```bash
sudo nano /etc/nginx/nginx.conf
```

Sesuaikan nilai di blok yang sudah ada (jangan buat blok dobel):

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

```bash
sudo nginx -t
sudo systemctl reload nginx
```

> **Setelah semua tuning diterapkan**, ulangi smoke test (Langkah 13) dan pantau RAM, CPU, slow query, Redis memory, serta error log saat simulasi beban.

---

## 19. Troubleshooting

### 502 Bad Gateway

- Cek socket `fastcgi_pass` sesuai hasil `ls /run/php/php*-fpm.sock`.
- Cek `sudo systemctl status php8.3-fpm`.
- Cek log `/var/log/nginx/wordpress-cbt.error.log`.

### 404 pada halaman atau REST API

- Pastikan permalink aktif: `wp rewrite flush --hard`.
- Pastikan Nginx memakai `try_files $uri $uri/ /index.php?$args;`.

### Frontend CBT blank / asset tidak muncul

- Pastikan `public/build/manifest.json` ada di folder plugin.
- Jalankan `npm ci` lalu `npm run build` dari folder plugin.
- Pastikan permission folder plugin bisa dibaca `www-data`.

### Composer gagal "Could not delete ... vendor/..."

Penyebab: folder `vendor/` dibuat oleh user berbeda.

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
sudo chown -R www-data:www-data .
sudo -u www-data env HOME=/tmp composer install --no-dev --optimize-autoloader
```

Jika masih gagal, hapus `vendor/` lalu install ulang:

```bash
cd /var/www/wordpress/wp-content/plugins/cbt-exam-system
sudo rm -rf vendor
sudo -u www-data env HOME=/tmp composer install --no-dev --optimize-autoloader
```

### Redis masih fallback

- `redis-cli ping` harus menghasilkan `PONG`.
- Jika memakai Unix socket, `redis-cli -s /var/run/redis/redis.sock PING` dan `sudo -u www-data redis-cli -s /var/run/redis/redis.sock PING` harus menghasilkan `PONG`.
- `php -m | grep -i redis` harus menampilkan `redis`.
- File `wp-content/object-cache.php` harus ada.
- `wp_using_ext_object_cache()` harus bernilai `true`.
- Buka `CBT Cache` di dashboard dan ikuti next step yang muncul.
- Jika status CBT masih menampilkan `127.0.0.1:6379`, cek kembali konstanta Redis di `wp-config.php`; untuk Unix socket nilai host harus `/var/run/redis/redis.sock`.

### phpMyAdmin 404 atau file PHP terunduh

- Pastikan paket `phpmyadmin` terinstall dan folder `/usr/share/phpmyadmin` ada.
- Pastikan blok `location /phpmyadmin/` dan `location ~ ^/phpmyadmin/(.+\.php)$` ada di server block Nginx dan posisinya **sebelum** blok umum `location ~ \.php$`.
- Pastikan `fastcgi_pass` pada blok phpMyAdmin memakai socket PHP-FPM yang benar.

### phpMyAdmin "Cannot log in to the MySQL server"

phpMyAdmin memakai user MySQL, bukan user Linux. Gunakan user yang dibuat di Langkah 6 (`pma_cbt_admin`). Jika ingin login dengan username Linux (misal `coblax`), buat user MySQL dengan nama yang sama:

```bash
sudo mysql
```

```sql
CREATE USER IF NOT EXISTS 'coblax'@'localhost' IDENTIFIED BY 'ganti_password_mysql_yang_kuat';
ALTER USER 'coblax'@'localhost' IDENTIFIED BY 'ganti_password_mysql_yang_kuat';
GRANT ALL PRIVILEGES ON wordpress_cbt.* TO 'coblax'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Login siswa berhasil tapi saat pilih exam kembali ke login

Gejala: request ke `/wp-json/cbt/v1/exams`, `/session`, atau `/start_attempt` mendapat `401`.

**Penyebab paling umum:** header `Authorization` tidak diteruskan ke PHP-FPM. Pastikan blok PHP di Nginx memiliki:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

Setelah mengubah Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

**Cek juga URL konsisten.** Jangan campur akses lewat `http://192.168.x.x`, `https://domain`, dan `www.domain`:

```bash
cd /var/www/wordpress
sudo -u www-data env HOME=/tmp wp option get home
sudo -u www-data env HOME=/tmp wp option get siteurl
```

**Cara debug dari browser:**

1. Buka DevTools > Network.
2. Login siswa, lalu pilih exam.
3. Cari request `/wp-json/cbt/v1/exams`, `/session`, atau `/start_attempt`.
4. Jika `401` + "Authorization token not found" → tambahkan `fastcgi_param HTTP_AUTHORIZATION` di Nginx.
5. Jika `401` + "Invalid or expired token" → pastikan salt/key di `wp-config.php` tidak berubah dan jam server benar.
6. Jika "Sesi login ini sudah digantikan oleh login lain" → reset login siswa dari dashboard admin CBT.

### Import soal/user gagal

- Cek `upload_max_filesize`, `post_max_size`, dan `max_execution_time` di php.ini.
- Pastikan ekstensi `zip`, `xml`, `mbstring`, `gd`, dan `intl` aktif.
- Cek permission `wp-content/uploads`.

### Worker CBT tidak berjalan

- Pastikan cron OS untuk `wp-cron.php` aktif setiap menit (Langkah 11.3).
- Jalankan `wp cron event list | grep cbt`.
- Pastikan `DISABLE_WP_CRON` di `wp-config.php` diimbangi dengan cron OS.
