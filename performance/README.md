# CBT Performance Pack (Web)

Paket ini fokus ke performa web ujian CBT (bukan Android), khusus target beban tinggi:
- Siswa serentak: sampai 1000 user realtime
- Dengan Redis runtime batch submit: target bertahap sampai 2000 user realtime
- Baseline awal: server 2 vCPU, RAM 8 GB

Isi folder:
- `server-tuning-2core-8gb.md`: checklist tuning WordPress/PHP-FPM/MySQL/Nginx/Redis.
- `load-test/k6/cbt_exam_1000_users.js`: script beban ujian end-to-end.
- `load-test/k6/students.sample.json`: contoh dataset akun siswa untuk load test.

Saran workflow:
1. Terapkan tuning server dulu.
2. Jalankan load test bertahap (200 -> 500 -> 800 -> 1000 user).
3. Cek bottleneck (CPU, RAM, disk I/O, slow query, error rate).
4. Fine-tune ulang berdasarkan hasil.
