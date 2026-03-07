# k6 Load Test - CBT 1000 Users

Script: `cbt_exam_1000_users.js`

## 1) Prasyarat

1. Install k6.
2. Siapkan akun siswa load test (disarankan unik 1 akun per VU).
3. Isi akun real di `students.json`.

Script default membaca file `students.json`.

Jika `k6` terpasang dari `snap` seperti `/snap/bin/k6`, biasanya ia hanya punya akses ke folder `home`. Jika repo Anda ada di `/var/www/...`, file script bisa terlihat oleh `ls` tetapi tetap gagal dibuka oleh `k6`.

Contoh cek:

```bash
type -a k6
```

Jika output menunjukkan `/snap/bin/k6`, pakai salah satu opsi berikut:

1. Copy script dan `students.json` ke folder di `home`, lalu jalankan dari sana.
2. Install `k6` non-snap agar bisa membaca file langsung dari `/var/www`.

Contoh workflow aman untuk `k6` snap:

```bash
mkdir -p /home/$USER/.tmp-k6
cp cbt_exam_1000_users.js /home/$USER/.tmp-k6/
cp students.json /home/$USER/.tmp-k6/

cd /home/$USER/.tmp-k6
k6 run cbt_exam_1000_users.js
```

## 2) Variabel Utama

- `BASE_URL` (required): contoh `http://127.0.0.1`
- `EXAM_ID` (sangat disarankan): jika kosong, script pilih exam pertama yang tersedia.
- `EXAM_TOKEN` (optional): isi jika exam wajib token.
- `VUS` (default `1000`)
- `ITERATIONS` (default `1`)
- `MAX_DURATION` (default `45m`)
- `REQUEST_TIMEOUT` (default adaptif: `30s` sampai `60s` tergantung `VUS`)
- `QUESTIONS_PER_USER` (default `0` = semua soal)
- `FINISH_EXAM` (default `1`)
- `THINK_MIN_MS` (default `100`)
- `THINK_MAX_MS` (default `250`)
- `SESSION_START_SPREAD_MS` (default adaptif): sebar start sesi (login) antar VU agar tidak nembak bersamaan.
- `POST_START_SPREAD_MS` (default adaptif): sebar request `get_questions` setelah `start_attempt`, penting untuk bootstrap 500-2000 user.
- `SUBMIT_PHASE_DELAY_MS` (default `0`): jeda global setelah `get_questions` sebelum mulai submit jawaban.
- `SUBMIT_PHASE_SPREAD_MS` (default `0`): sebar awal fase submit antar VU.
- `SUBMIT_MODE` (default `all`): `all` = submit normal, `none` = stop setelah `get_questions` (uji login/token/ambil soal saja).
- `ENABLE_BATCH_SUBMIT` (default `1`): `1` = pakai endpoint `submit_answers_batch`, `0` = pakai endpoint legacy `submit_answer`.
- `BATCH_WINDOW_MS` (default `2500`): window batch submit per peserta.
- `BATCH_MAX_ITEMS` (default `20`): jumlah jawaban maksimal per request batch.
- `STRICT_EXAM_ID` (default `0`): `1` = wajib pakai `EXAM_ID` persis, `0` = fallback ke exam tersedia jika ID tidak ketemu.
- `SKIP_EXAMS_REQUEST` (default `1` jika `EXAM_ID` diisi): lewati request `/exams` dan langsung pakai `EXAM_ID` yang diberikan.
- `START_ATTEMPT_RETRIES` (default adaptif): retry untuk timeout/429/5xx saat `start_attempt`.
- `GET_QUESTIONS_RETRIES` (default adaptif): retry untuk timeout/429/5xx saat `get_questions`.
- `RETRY_BACKOFF_MS` (default `1500`)
- `ENABLE_THRESHOLDS` (default `1`): `0` untuk mode debug supaya run tidak dianggap gagal saat KPI belum tercapai.
- `DEBUG_LOG` (default `0`): `1` untuk menampilkan alasan gagal sesi pada beberapa VU awal.
- `DEBUG_VU_LIMIT` (default `3`): jumlah VU awal yang menampilkan debug log.

## 3) Run Bertahap (Direkomendasikan)

### Smoke Test

```bash
k6 run \
  -e BASE_URL=http://127.0.0.1 \
  -e VUS=50 \
  -e EXAM_ID=1 \
  -e EXAM_TOKEN=ABCDEFGH \
  -e QUESTIONS_PER_USER=20 \
  cbt_exam_1000_users.js
```

### Soak 500 User

```bash
k6 run \
  -e BASE_URL=http://127.0.0.1 \
  -e VUS=500 \
  -e EXAM_ID=1 \
  -e EXAM_TOKEN=ABCDEFGH \
  -e QUESTIONS_PER_USER=40 \
  cbt_exam_1000_users.js
```

### Full 1000 User

```bash
k6 run \
  -e BASE_URL=http://127.0.0.1 \
  -e VUS=1000 \
  -e EXAM_ID=1 \
  -e EXAM_TOKEN=ABCDEFGH \
  -e SESSION_START_SPREAD_MS=90000 \
  -e POST_START_SPREAD_MS=30000 \
  -e REQUEST_TIMEOUT=60s \
  -e QUESTIONS_PER_USER=80 \
  -e ENABLE_BATCH_SUBMIT=1 \
  -e BATCH_WINDOW_MS=2500 \
  -e BATCH_MAX_ITEMS=20 \
  cbt_exam_1000_users.js
```

### Full 2000 User Batch Mode

```bash
k6 run \
  -e BASE_URL=http://127.0.0.1 \
  -e VUS=2000 \
  -e EXAM_ID=1 \
  -e EXAM_TOKEN=ABCDEFGH \
  -e SESSION_START_SPREAD_MS=120000 \
  -e POST_START_SPREAD_MS=45000 \
  -e REQUEST_TIMEOUT=75s \
  -e QUESTIONS_PER_USER=80 \
  -e ENABLE_BATCH_SUBMIT=1 \
  -e BATCH_WINDOW_MS=2500 \
  -e BATCH_MAX_ITEMS=20 \
  cbt_exam_1000_users.js
```

### Prioritas Login/Token/Ambil Soal Dulu

```bash
k6 run \
  -e BASE_URL=http://127.0.0.1 \
  -e VUS=400 \
  -e EXAM_ID=1 \
  -e EXAM_TOKEN=ABCDEFGH \
  -e QUESTIONS_PER_USER=240 \
  -e SESSION_START_SPREAD_MS=20000 \
  -e SUBMIT_PHASE_DELAY_MS=25000 \
  -e SUBMIT_PHASE_SPREAD_MS=45000 \
  cbt_exam_1000_users.js
```

### Uji Murni Bootstrap (Login + Token + Get Questions)

```bash
k6 run \
  -e BASE_URL=http://127.0.0.1 \
  -e VUS=400 \
  -e EXAM_ID=1 \
  -e EXAM_TOKEN=ABCDEFGH \
  -e SKIP_EXAMS_REQUEST=1 \
  -e SESSION_START_SPREAD_MS=30000 \
  -e POST_START_SPREAD_MS=10000 \
  -e START_ATTEMPT_RETRIES=1 \
  -e GET_QUESTIONS_RETRIES=1 \
  -e SUBMIT_MODE=none \
  cbt_exam_1000_users.js
```

### Debug Cepat (tanpa threshold fail)

```bash
k6 run \
  -e BASE_URL=http://127.0.0.1 \
  -e VUS=20 \
  -e EXAM_ID=1 \
  -e EXAM_TOKEN=ABCDEFGH \
  -e QUESTIONS_PER_USER=10 \
  -e ENABLE_THRESHOLDS=0 \
  -e DEBUG_LOG=1 \
  -e DEBUG_VU_LIMIT=3 \
  cbt_exam_1000_users.js
```

## 4) Target KPI Awal

- `http_req_failed` < 3%
- `http_req_duration p95` < 1200 ms
- `cbt_submit_answer_duration p95` < 900 ms
- `cbt_submit_answers_batch_duration p95` < 700 ms
- `exam_session_success` > 95%

## 5) Catatan Penting

- Jika akun siswa lebih sedikit dari `VUS`, script akan reuse akun (kurang ideal).
- Untuk hasil valid, gunakan akun unik per peserta simulasi.
- Untuk `VUS` besar, selalu isi `EXAM_ID`. Jika tidak, setiap VU tetap perlu memanggil `/exams` untuk memilih exam target.
- Untuk uji 500+ user, jangan mulai dari `SESSION_START_SPREAD_MS=0` kecuali memang sengaja ingin menguji burst ekstrem pada detik yang sama.
- Jalankan test di luar jam produksi untuk menghindari gangguan ujian real.
- Script submit jawaban sudah mendukung `multiple_choice`, `multiple_answer`, `true_false`, `true_false_matrix`, `short_answer`, dan `essay`.
- Untuk `true_false_matrix`, script mengirim jawaban lengkap per baris dalam format map seperti `{"1":"true","2":"false"}` agar sesuai payload API CBT.

## 6) Troubleshooting

### Error: `moduleSpecifier ... couldn't be found on local disk`

Penyebab paling umum di environment Ubuntu/WSL adalah `k6` dipasang dari `snap`, sementara script berada di `/var/www/...`.

Gejala:

- `ls` menampilkan `cbt_exam_1000_users.js`
- `k6 run cbt_exam_1000_users.js` tetap gagal
- path absolut ke `/var/www/...` juga tetap gagal

Perbaikan:

```bash
mkdir -p /home/$USER/.tmp-k6
cp /var/www/wordpress/wp-content/plugins/cbt-exam-system/performance/load-test/k6/cbt_exam_1000_users.js /home/$USER/.tmp-k6/
cp /var/www/wordpress/wp-content/plugins/cbt-exam-system/performance/load-test/k6/students.json /home/$USER/.tmp-k6/

cd /home/$USER/.tmp-k6

k6 run \
  -e BASE_URL=http://127.0.0.1 \
  -e VUS=1000 \
  -e EXAM_ID=3 \
  -e EXAM_TOKEN=MZ916U \
  -e SKIP_EXAMS_REQUEST=1 \
  -e SESSION_START_SPREAD_MS=90000 \
  -e POST_START_SPREAD_MS=30000 \
  -e START_ATTEMPT_RETRIES=2 \
  -e GET_QUESTIONS_RETRIES=2 \
  -e REQUEST_TIMEOUT=60s \
  -e SUBMIT_MODE=none \
  -e ENABLE_THRESHOLDS=0 \
  cbt_exam_1000_users.js
```
