# Admin Architecture & File Inventory Handbook

Folder `admin/` adalah layer admin WordPress untuk plugin CBT Exam System. Semua hal yang berhubungan dengan panel admin plugin berada di sini: registrasi menu, rendering halaman admin, handler `admin_post` dan `wp_ajax`, builder context untuk view, sampai template HTML/PHP yang dipakai untuk menampilkan antarmuka operator, guru, proktor, dan admin sistem.

Dokumen ini ditulis sebagai handbook internal yang jauh lebih lengkap daripada inventaris singkat. Target utamanya adalah developer, contributor, reviewer, dan agent yang perlu memahami struktur `admin/` secara sistematis tanpa harus membaca puluhan ribu baris source lebih dulu.

## Tujuan Handbook Ini

README ini dibuat untuk menjawab pertanyaan-pertanyaan berikut:

- Di mana menu admin diregistrasikan?
- Bagaimana flow request admin berjalan dari menu sampai view?
- Modul admin apa saja yang ada, dan file apa yang menyusunnya?
- Hook `admin_post` dan `wp_ajax` apa saja yang tersedia?
- File terbesar dan area paling kompleks ada di mana?
- Kalau ingin menambah fitur, mengubah tampilan, atau melacak bug capability/hook, harus mulai dari file apa?

README ini **mendokumentasikan isi folder `admin/`**. Dokumen ini **tidak** berusaha menjadi dokumentasi penuh untuk frontend Vite, REST route, atau seluruh runtime plugin di luar konteks admin.

## Audience Utama

Dokumen ini paling cocok untuk:

- developer baru yang baru masuk ke repo
- contributor yang ingin menambah halaman atau action admin
- reviewer yang perlu memahami struktur modul sebelum code review
- agent/tooling yang perlu cepat menavigasi file admin
- maintainer yang sedang melacak permission issue, action flow, atau hotspot file besar

## Yang Didokumentasikan

- struktur file `admin/`
- konvensi `page`, `service`, `actions`, `helper`, `views`
- bootstrap admin dan menu map
- capability map dan slug halaman
- hook inventory `admin_post` dan `wp_ajax`
- inventaris modul admin dalam urutan menu aktual
- view non-standar seperti `preview.php`, `print.php`, dan partial
- hotspot files dan area yang paling kompleks untuk dirawat
- task map: “kalau mau mengubah X, mulai dari mana?”

## Yang Sengaja Tidak Dibahas Mendalam

- detail seluruh private method satu per satu
- markup/CSS inline di setiap view secara line-by-line
- frontend Vite non-admin di `src/frontend/`
- seluruh REST behavior di `includes/class-cbt-rest.php`
- semua dependency WordPress/non-WordPress di luar hubungan langsung dengan admin

## Snapshot Folder Saat Dokumen Ini Ditulis

Berikut snapshot ukuran `admin/` yang sudah diverifikasi dari repo saat ini:

| Item | Nilai |
| --- | --- |
| Total file PHP di `admin/` | `50` file |
| File `class-cbt-admin-*.php` | `49` file |
| Bootstrap admin tambahan | `class-cbt-admin.php` |
| File `page` | `15` |
| File `service` | `14` |
| File `actions` | `13` |
| File `helper/settings` | `6` |
| File view di `admin/views/` | `19` |
| Total line source PHP admin | `33,526` line |
| Total line view admin | `31,442` line |
| Hook `admin_post` | `61` |
| Hook `wp_ajax` | `5` |

Angka di atas penting karena memberi konteks: folder `admin/` sendiri sudah cukup besar untuk diperlakukan sebagai subsystem yang serius, bukan sekadar kumpulan file tampilan.

## Cara Memakai Dokumen Ini

Ada beberapa cara membaca handbook ini tergantung kebutuhan:

1. Kalau Anda baru pertama kali masuk ke folder admin:
   Baca bagian `Peta Besar Folder`, `Arsitektur Request Admin`, lalu lompat ke `Matrix Referensi`.

2. Kalau Anda sedang mencari titik masuk satu modul:
   Langsung buka bagian `Handbook Per Modul` lalu cari modul sesuai nama menu admin.

3. Kalau Anda sedang melacak action atau form submit:
   Baca `class-cbt-admin.php` di bagian `Infrastruktur Lintas Modul`, lalu lanjut ke `Appendix A — Hook Inventory`.

4. Kalau Anda sedang memburu capability atau slug:
   Baca `class-cbt-admin-menu.php`, lalu lihat `Appendix B — Capability & Slug Map`.

5. Kalau Anda sedang mencari hotspot atau file paling kompleks:
   Baca `Matrix Hotspot` dan `Appendix D — Largest Files & Hotspot Map`.

6. Kalau Anda ingin tahu file mana yang harus dibuka untuk tugas spesifik:
   Lihat `Appendix E — Where To Edit`.

## Daftar Isi Besar

- [Peta Besar Folder](#peta-besar-folder)
- [Arsitektur Request Admin](#arsitektur-request-admin)
- [Infrastruktur Lintas Modul](#infrastruktur-lintas-modul)
- [Matrix Referensi](#matrix-referensi)
- [Handbook Per Modul](#handbook-per-modul)
- [Deep Dive View Non-Standar](#deep-dive-view-non-standar)
- [Appendix A — Hook Inventory Lengkap](#appendix-a--hook-inventory-lengkap)
- [Appendix B — Capability--Slug Map](#appendix-b--capability--slug-map)
- [Appendix C — File Index Lengkap](#appendix-c--file-index-lengkap)
- [Appendix D — Largest Files & Hotspot Map](#appendix-d--largest-files--hotspot-map)
- [Appendix E — Where To Edit](#appendix-e--where-to-edit)

## Peta Besar Folder

Secara praktis, folder `admin/` bisa dipandang sebagai lima lapis utama:

| Lapisan | Contoh file | Peran |
| --- | --- | --- |
| Bootstrap | `class-cbt-admin.php` | Registrasi hook admin dan request map utama |
| Menu | `class-cbt-admin-menu.php` | Registrasi menu/submenu dan redirect halaman lama |
| Page | `class-cbt-admin-*-page.php` | Gate capability, render entrypoint, load view |
| Service | `class-cbt-admin-*-service.php` | Business logic, query, context builder, mutasi utama |
| Actions | `class-cbt-admin-*-actions.php` | Adapter tipis dari hook menuju service/helper |
| Helper/Settings | `*-helper.php`, `*-import-helper.php`, `*-sync-helper.php`, `*-settings.php` | Utility atau subsystem kecil di dalam modul |
| Views | `admin/views/**` | Template halaman admin, preview, print, partial |

### Konvensi Penamaan yang Dipakai

Konvensi penamaan di folder ini cukup konsisten dan penting dipahami:

#### `*-page.php`

Umumnya berisi:

- method `render()`
- pengecekan capability seperti `can_manage_*()` atau `can_view_*()`
- pemanggilan `build_page_context(...)`
- `extract($context, EXTR_SKIP)`
- `require CBT_EXAM_SYSTEM_PATH . 'admin/views/...';`

Artinya: bila Anda ingin tahu “halaman admin ini dimulai dari mana?”, hampir selalu mulai dari file `page`.

#### `*-service.php`

Umumnya berisi:

- pengecekan scope seperti `is_admin_scope()`
- method `build_page_context(...)`
- operasi mutasi utama seperti save/delete/reset/print/export/start/cancel
- normalisasi input, query WPDB, dan pembentukan payload view

Artinya: bila Anda ingin tahu “logika bisnis modul ini ada di mana?”, paling sering jawabannya adalah `service`.

#### `*-actions.php`

Umumnya berisi:

- method `handle_*()`
- delegasi tipis ke `service` atau `helper`
- kadang capability guard tambahan sebelum delegasi

Artinya: bila Anda ingin tahu “hook action ini mendarat di file mana?”, mulai dari `actions`.

#### `*-helper.php`

Dipakai ketika modul membutuhkan utilitas tambahan yang:

- terlalu spesifik untuk dipindah ke generic utility global
- terlalu detail untuk diletakkan langsung di service utama
- dipakai ulang oleh lebih dari satu subflow di modul yang sama

Contoh paling jelas ada di modul `questions`, `results`, dan `exams`.

#### `*-import-helper.php`

Pola ini muncul saat modul punya pipeline import khusus dan lebih kompleks.

Di repo ini contoh terbesarnya adalah:

- `class-cbt-admin-questions-import-helper.php`

File ini memisahkan pipeline import/template soal dari CRUD inti pertanyaan.

#### `*-sync-helper.php`

Pola ini muncul saat modul punya alur sinkronisasi/backfill/propagasi yang terlalu spesifik.

Di repo ini contoh utamanya adalah:

- `class-cbt-admin-questions-sync-helper.php`

#### `*-settings.php`

Dipakai untuk shared settings object lintas flow.

Di repo ini contohnya:

- `class-cbt-admin-branding-settings.php`

#### `views/**`

Template view adalah sisi presentasi admin.

Sebagian besar modul memakai pola satu halaman utama:

- `views/<modul>/page.php`

Tetapi beberapa modul punya tampilan non-standar:

- `preview.php` untuk preview
- `print.php` untuk output print-ready
- `partials/*.php` untuk fragmen view yang dirender terpisah

## Arsitektur Request Admin

Bagian ini adalah peta mental paling penting untuk memahami aliran request di folder admin.

### Flow Umum Halaman Admin

Flow standar halaman admin biasanya seperti ini:

1. Menu atau submenu diregistrasikan di `class-cbt-admin-menu.php`.
2. WordPress memanggil callback render dari `add_menu_page()` atau `add_submenu_page()`.
3. Callback itu biasanya menunjuk ke `CBT_Admin_<Module>_Page::render()`.
4. File `page` mengecek permission modul.
5. File `page` meminta context ke `CBT_Admin_<Module>_Service::build_page_context(...)`.
6. Context di-`extract(...)`.
7. Template di `admin/views/...` dirender.

Contoh nyata:

- `CBT_Admin_Exams_Page::render()`:
  - jika ada `preview_exam_id`, ia masuk ke flow preview
  - jika tidak, ia memanggil `build_page_context(...)`
- `CBT_Admin_Results_Page::render()`:
  - selalu memanggil `CBT_Admin_Results_Service::build_page_context($_GET)`
- `CBT_Admin_Setup_Page::render()`:
  - memanggil `CBT_Admin_Setup_Service::build_page_context($_GET)` lalu merender view setup

### Flow Umum Action / Form Submit

Flow standar action admin biasanya seperti ini:

1. Hook didaftarkan di `class-cbt-admin.php`.
2. Hook menunjuk ke method di `*-actions.php` atau kadang langsung ke `service`.
3. `actions` melakukan delegasi tipis.
4. `service/helper` melakukan validasi, query, side effect, redirect, atau output print/download.

Contoh nyata:

- `admin_post_cbt_save_subject` -> `CBT_Admin_Subjects_Actions::handle_save_subject()`
- `handle_save_subject()` -> logic ada di service subjects
- `admin_post_cbt_import_questions` -> `CBT_Admin_Questions_Actions::handle_import_questions()`
- `handle_import_questions()` -> diteruskan ke `CBT_Admin_Questions_Import_Helper::handle_import_questions()`

### Flow Umum AJAX

Flow AJAX di folder admin relatif sedikit tetapi penting:

1. Hook `wp_ajax_*` didaftarkan di bootstrap admin.
2. Hook ini biasanya dipakai untuk state admin yang perlu roundtrip cepat tanpa reload penuh.
3. Di repo ini AJAX dipakai terutama untuk:
   - sync builder exam
   - progress save exam
   - polling load test jobs

### Pola Capability Gate

Capability guard paling sering terjadi di dua tempat:

1. Di file `page`, sebelum context dibangun.
2. Di file `actions` atau `service`, sebelum mutasi dilakukan.

Pola yang umum:

- `current_user_can('manage_options')`
- `current_user_can('cbt_manage_exams')`
- `current_user_can('cbt_manage_questions')`
- `current_user_can('cbt_manage_users')`
- `current_user_can('cbt_view_results')`

Pola wrapper yang sering dipakai:

- `can_manage_exams()`
- `can_manage_questions()`
- `can_manage_users()`
- `can_manage_maintenance()`
- `can_manage_cache()`
- `can_manage_test_hub()`
- `can_view_results()`
- `can_view()`

Maknanya:

- bila Anda sedang memburu “kenapa halaman ini unauthorized?”, buka file `page` dan `service`
- bila Anda sedang memburu “kenapa form submit gagal padahal halaman bisa dibuka?”, buka `actions` dan `service`

### Pola Context Builder

Hampir semua modul view-heavy memakai `build_page_context(...)`.

Konsekuensi praktis:

- semua data yang dipakai view seharusnya bisa dilacak dari method ini
- filter query string biasanya diproses di sini
- notice/error yang lewat `$_GET` biasanya di-normalisasi di sini
- view cenderung menjadi template tebal yang menerima variabel siap pakai

### Pola Render View

Pola render yang dominan:

```php
$context = CBT_Admin_Module_Service::build_page_context($_GET);
extract($context, EXTR_SKIP);
require CBT_EXAM_SYSTEM_PATH . 'admin/views/module/page.php';
```

Implikasi untuk contributor:

- kalau mau tambah field view, periksa apakah datanya sudah dibawa oleh service
- kalau variabel view “muncul dari udara”, kemungkinan ia berasal dari `extract($context, EXTR_SKIP)`

### Pola Delegasi Action Tipis

Folder admin sengaja memakai action classes yang tipis untuk banyak modul.

Contoh:

- `CBT_Admin_Exams_Actions`
- `CBT_Admin_Questions_Actions`
- `CBT_Admin_Results_Actions`
- `CBT_Admin_Maintenance_Actions`
- `CBT_Admin_Test_Hub_Actions`

Pola ini berguna karena:

- bootstrap admin jadi konsisten
- hook langsung menunjuk ke class yang bernama sesuai modul
- logic besar tetap terkumpul di service/helper, bukan tercecer di bootstrap

### View Non-Standar yang Perlu Diingat

Tidak semua modul admin berakhir di `page.php`. Ada empat pola non-standar yang penting:

1. Preview render:
   - `views/exams/preview.php`
2. Print render:
   - `views/exam-cards/print.php`
   - `views/report-exam/print.php`
3. Partial render:
   - `views/maintenance/partials/load-test-jobs.php`
4. Self-contained page class:
   - `class-cbt-admin-introduction-page.php` menyimpan context builder sendiri, tidak punya `service` terpisah

## Infrastruktur Lintas Modul

Bagian ini membahas file yang menjadi index seluruh admin subsystem.

## 1. `class-cbt-admin.php`

File ini adalah bootstrap utama area admin plugin.

### Fungsi Utama

- mendaftarkan menu admin lewat `admin_menu`
- mendaftarkan redirect halaman lama lewat `admin_init`
- mendaftarkan runtime notice cache lewat `admin_notices`
- menjadi pusat registrasi `admin_post_*`
- menjadi pusat registrasi `wp_ajax_*`

### Kenapa File Ini Penting

Kalau Anda hanya boleh membuka satu file untuk memetakan request admin, pilih file ini.

Alasan:

- di sinilah semua hook admin dikumpulkan
- hampir semua flow mutasi bisa ditelusuri dari sini
- hook map ini memperlihatkan pemisahan modul dengan sangat jelas

### Cara Membaca File Ini

Ada tiga cara membaca `class-cbt-admin.php`:

1. Sebagai index hook:
   Lihat semua `add_action(...)` untuk tahu hook apa saja yang ada.

2. Sebagai index modul:
   Perhatikan class target di setiap hook untuk tahu modul terkait.

3. Sebagai peta request write-path:
   Semua save/delete/import/print/export/reset/start/cancel biasanya bisa dilacak dari sini.

### Ringkasan Hook di Bootstrap Admin

| Kelompok | Jumlah |
| --- | --- |
| `admin_post_*` | `61` |
| `wp_ajax_*` | `5` |
| Total write/async entrypoint | `66` |

### Ringkasan Hook per Modul

| Modul | `admin_post` | `wp_ajax` | Total |
| --- | ---: | ---: | ---: |
| Subjects | 6 | 0 | 6 |
| Exams | 2 | 4 | 6 |
| Tokens | 1 | 0 | 1 |
| Setup | 3 | 0 | 3 |
| Developer | 3 | 0 | 3 |
| Test Hub | 3 | 0 | 3 |
| Cache | 1 | 0 | 1 |
| Maintenance | 10 | 1 | 11 |
| Questions | 13 | 0 | 13 |
| Results | 7 | 0 | 7 |
| Report Exam | 4 | 0 | 4 |
| Exam Cards | 1 | 0 | 1 |
| Users | 7 | 0 | 7 |

### Kapan Harus Mulai dari File Ini

Mulai dari `class-cbt-admin.php` bila Anda sedang:

- menambahkan action form baru
- mencari nama hook yang memicu handler tertentu
- memburu bug “submit tidak pernah sampai”
- memburu action yang menghasilkan redirect tak terduga
- mencari apakah sebuah flow memakai `admin_post` atau `wp_ajax`

## 2. `class-cbt-admin-menu.php`

File ini adalah peta navigasi admin plugin.

### Fungsi Utama

- mendaftarkan menu utama `CBT Exams`
- mendaftarkan semua submenu plugin
- mendefinisikan slug halaman
- mendefinisikan capability akses per halaman
- menangani redirect halaman lama yang sudah tidak dipakai

### Kenapa File Ini Penting

Kalau Anda ingin tahu:

- nama menu yang terlihat di dashboard
- slug halaman admin
- capability akses per modul
- callback render class untuk tiap submenu

jawabannya hampir selalu ada di sini.

### Menu Matrix Aktual

| Urutan | Label Menu | Slug | Capability | Callback Render |
| ---: | --- | --- | --- | --- |
| 1 | `Introduction` | `cbt-introduction` | `cbt_manage_exams` | `CBT_Admin_Introduction_Page::render` |
| 2 | `CBT Exams` | `cbt-exams` | `cbt_manage_exams` | `CBT_Admin_Exams_Page::render` |
| 3 | `CBT Branding` | `cbt-setup` | `cbt_manage_exams` | `CBT_Admin_Setup_Page::render` |
| 4 | `CBT Security` | `cbt-security` | `cbt_manage_exams` | `CBT_Admin_Security_Page::render` |
| 5 | `CBT Subjects` | `cbt-subjects` | `manage_options` | `CBT_Admin_Subjects_Page::render` |
| 6 | `CBT Users` | `cbt-user-import` | `manage_options` | `CBT_Admin_Users_Page::render` |
| 7 | `CBT Questions` | `cbt-question-bank` | `cbt_manage_questions` | `CBT_Admin_Questions_Page::render` |
| 8 | `CBT Tokens` | `cbt-tokens` | `cbt_manage_exams` | `CBT_Admin_Tokens_Page::render` |
| 9 | `CBT Exam Cards` | `cbt-exam-cards` | `cbt_manage_users` | `CBT_Admin_Exam_Cards_Page::render` |
| 10 | `CBT Results` | `cbt-results` | `cbt_view_results` | `CBT_Admin_Results_Page::render` |
| 11 | `CBT Analytics` | `cbt-analytics` | `cbt_view_results` | `CBT_Admin_Analytics_Page::render` |
| 12 | `CBT Report Exam` | `cbt-report-exam` | `cbt_view_results` | `CBT_Admin_Report_Exam_Page::render` |
| 13 | `CBT Test Hub` | `cbt-test-hub` | `manage_options` | `CBT_Admin_Test_Hub_Page::render` |
| 14 | `CBT Cache` | `cbt-cache` | `manage_options` | `CBT_Admin_Cache_Page::render` |
| 15 | `CBT Maintenance` | `cbt-maintenance` | `manage_options` | `CBT_Admin_Maintenance_Page::render` |
| 16 | `CBT Developer` | `cbt-developer` | `manage_options` | `CBT_Admin_Developer_Page::render` |

### Special Case di Menu

- menu utama `cbt-exams` dibuat dengan `add_menu_page(...)`
- submenu `cbt-exams` asli sempat dihapus dengan `remove_submenu_page('cbt-exams', 'cbt-exams')`
- lalu ditambahkan ulang sebagai submenu biasa agar urutan menu lebih rapi

### Redirect Halaman Lama

`redirect_removed_admin_pages()` menangani slug lama berikut:

- `cbt-questions-mc`
- `cbt-questions-ma`
- `cbt-questions-tf`
- `cbt-questions-sa`
- `cbt-questions-essay`

Semua diarahkan ke:

- `admin.php?page=cbt-subjects`

Penting dicatat karena ini berarti:

- modul pertanyaan dulunya pernah punya halaman terpisah per tipe
- sekarang pendekatannya sudah dikonsolidasikan ke flow yang lebih terpusat

## 3. `class-cbt-admin-branding-settings.php`

File ini adalah utilitas branding/settings lintas admin.

### Public Method Map

- `option_key()`
- `get_settings()`
- `get_print_context()`

### Peran Praktis

- menyimpan source of truth konfigurasi branding
- dipakai oleh setup/admin dan output cetak
- menjadi titik yang tepat bila Anda perlu mengubah field branding yang dipakai lintas modul

### Kapan Harus Mulai dari Sini

Mulai dari file ini bila Anda sedang:

- menambah field branding baru
- mengubah payload branding yang dipakai view setup
- mengubah konteks branding yang dipakai print output

## Matrix Referensi

Bagian ini adalah peta referensi cepat sebelum masuk ke deep dive per modul.

### Matrix Jumlah File

| Kategori | Jumlah | Catatan |
| --- | ---: | --- |
| Bootstrap admin | 1 | `class-cbt-admin.php` |
| Menu registry | 1 | `class-cbt-admin-menu.php` |
| `page` | 15 | Satu modul = satu render entrypoint |
| `service` | 14 | Tidak semua modul punya service |
| `actions` | 13 | Tidak semua modul butuh action class |
| `helper/settings` | 6 | Helper khusus modul dan branding settings |
| View | 19 | Termasuk `page.php`, `preview.php`, `print.php`, partial |
| Total file admin yang didokumentasikan | 56 | PHP infra/modul + view |

### Matrix View Non-Standar

| File View | Tipe | Dipakai Oleh | Fungsi |
| --- | --- | --- | --- |
| `views/exams/preview.php` | Preview | Exams | Preview exam tanpa masuk halaman daftar utama |
| `views/exam-cards/print.php` | Print | Exam Cards | Output cetak kartu ujian |
| `views/report-exam/print.php` | Print | Report Exam | Output report exam siap cetak |
| `views/maintenance/partials/load-test-jobs.php` | Partial | Maintenance | Fragmen HTML daftar job load test |

### Matrix Hotspot PHP

| File | Line | Kenapa Hot |
| --- | ---: | --- |
| `class-cbt-admin-maintenance-service.php` | `5881` | Reset DB, seed data, load test, export, polling status |
| `class-cbt-admin-analytics-service.php` | `3315` | Context analitik besar dan query insight |
| `class-cbt-admin-exams-service.php` | `3271` | Builder exam, preview, selection sync, save progress |
| `class-cbt-admin-test-hub-service.php` | `2666` | Checklist test, runner, queue flow-check |
| `class-cbt-admin-questions-import-helper.php` | `2095` | Pipeline import soal dan template downloads |
| `class-cbt-admin-users-service.php` | `1695` | CRUD/import users dan metadata |
| `class-cbt-admin-questions-service.php` | `1579` | Context dan CRUD pertanyaan |
| `class-cbt-admin-questions-helper.php` | `1374` | Utility utama pertanyaan |
| `class-cbt-admin-report-exam-service.php` | `1322` | Print/report/incident flow |
| `class-cbt-admin-results-service.php` | `1290` | Results, grading, reset, force-complete |

### Matrix Hotspot View

| File | Line | Kenapa Hot |
| --- | ---: | --- |
| `views/exams/page.php` | `5018` | Halaman builder exam paling besar |
| `views/developer/page.php` | `3349` | Diagnostics dan tooling developer sangat padat |
| `views/results/page.php` | `3151` | Monitoring hasil dan review attempt |
| `views/questions/page.php` | `2817` | Form/edit/import pertanyaan sangat besar |
| `views/maintenance/page.php` | `2730` | Reset, seed, load test, export, status |
| `views/setup/page.php` | `2426` | Branding, security, log observability |
| `views/analytics/page.php` | `2172` | Insight analitik dan tab visual |
| `views/report-exam/page.php` | `1608` | Report exam dan incident UI |
| `views/test-hub/page.php` | `1531` | Checklist/runners/test context |
| `views/users/page.php` | `1358` | CRUD/import/filter user |

### Modul yang Paling Perlu Perhatian Saat Refactor

Secara praktis, modul berikut adalah hotspot arsitektural:

- `maintenance`
- `exams`
- `questions`
- `results`
- `test-hub`
- `analytics`
- `developer`

Kalau ada perubahan besar di salah satu area ini, kemungkinan besar README ini juga perlu diperbarui.

## Handbook Per Modul

Bagian ini adalah inti handbook. Urutannya mengikuti menu admin aktual.

## 1. Introduction

### Tujuan Modul

Modul `Introduction` adalah halaman orientasi awal untuk pengguna/admin. Ia tidak fokus pada mutasi data, melainkan pada:

- pengantar plugin
- grouping fitur
- workflow besar penggunaan plugin
- quick links ke modul lain

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `Introduction` |
| Slug | `cbt-introduction` |
| Capability | `cbt_manage_exams` |
| Callback render | `CBT_Admin_Introduction_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-introduction-page.php` | Page class yang juga memegang context builder internal |
| `views/introduction/page.php` | Halaman pengantar dan quick links |

### Render & Context Flow

Flow modul ini sedikit berbeda dari mayoritas modul karena tidak punya `service` terpisah:

1. `CBT_Admin_Introduction_Page::render()` dipanggil dari menu.
2. `can_view()` memeriksa capability.
3. `build_page_context()` dipanggil dari class yang sama.
4. `views/introduction/page.php` dirender.

### Public Method Map

- `can_view()`
- `render()`

Method `build_page_context()` bersifat private, jadi modul ini benar-benar self-contained di class page.

### Tanggung Jawab Utama

- menyiapkan `feature_groups`
- menyiapkan `workflow_steps`
- menyiapkan `workflow_guidance`
- menyiapkan `quick_links`
- menyiapkan kartu overview dan hero metrics

### View yang Dipakai

- `views/introduction/page.php`

View ini bukan view terkecil. Dengan ukuran `712` line, introduction page sebenarnya cukup kaya dan lebih mirip dashboard onboarding daripada halaman kosong.

### Titik Masuk Contributor

Mulai dari:

1. `class-cbt-admin-introduction-page.php`
2. `views/introduction/page.php`

### Special Cases

- modul ini tidak punya `actions`
- modul ini tidak punya `service`
- seluruh flow ditahan di page class sendiri

## 2. Exams

### Tujuan Modul

Modul `Exams` adalah pusat builder exam dan salah satu subsystem admin paling besar di repo ini. Ia menangani:

- daftar exam
- form builder exam
- preview exam
- save/delete exam
- sync selection state builder
- progress save exam besar

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Exams` |
| Slug | `cbt-exams` |
| Capability | `cbt_manage_exams` |
| Callback render | `CBT_Admin_Exams_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-exams-page.php` | Render halaman exam dan branch preview |
| `class-cbt-admin-exams-service.php` | Logic utama builder, context, save/delete, sync dan progress |
| `class-cbt-admin-exams-actions.php` | Adapter hook untuk save/delete/AJAX builder |
| `class-cbt-admin-exams-helper.php` | Formatter ringkasan/render summary kecil |
| `views/exams/page.php` | UI utama builder exam |
| `views/exams/preview.php` | UI preview exam |

### Render & Context Flow

Flow modul exam punya dua jalur utama:

#### Jalur halaman utama

1. `CBT_Admin_Exams_Page::render()`
2. Capability check lewat `CBT_Admin_Exams_Service::can_manage_exams()`
3. `build_page_context($_GET)`
4. `views/exams/page.php`

#### Jalur preview

1. `CBT_Admin_Exams_Page::render()`
2. Jika `preview_exam_id` ada di query string:
3. `build_preview_context($_GET)`
4. `views/exams/preview.php`

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_save_exam` | `CBT_Admin_Exams_Actions::handle_save_exam()` | Simpan exam |
| `admin_post_cbt_delete_exam` | `CBT_Admin_Exams_Actions::handle_delete_exam()` | Hapus exam |
| `wp_ajax_cbt_sync_exam_builder_selection` | `CBT_Admin_Exams_Actions::handle_sync_exam_builder_selection()` | Sinkronisasi pilihan builder |
| `wp_ajax_cbt_clear_exam_builder_selection` | `CBT_Admin_Exams_Actions::handle_clear_exam_builder_selection()` | Bersihkan selection state |
| `wp_ajax_cbt_start_exam_save_progress` | `CBT_Admin_Exams_Actions::handle_start_exam_save_progress()` | Mulai save progress bertahap |
| `wp_ajax_cbt_continue_exam_save_progress` | `CBT_Admin_Exams_Actions::handle_continue_exam_save_progress()` | Lanjutkan save progress bertahap |

### Responsibility Clusters

#### A. Permission & Scope

Public method terkait:

- `can_manage_exams()`
- `is_admin_scope()`

Tugas:

- menentukan siapa yang boleh masuk modul
- membedakan admin scope dan non-admin scope bila context perlu dibatasi

#### B. Page Context & Preview Context

Public method terkait:

- `build_page_context()`
- `build_preview_context()`

Tugas:

- menyiapkan filter, state list, payload builder, dan data view
- menyiapkan data khusus preview

#### C. Write Path Exam

Public method terkait:

- `handle_save_exam()`
- `handle_delete_exam()`

Tugas:

- simpan exam baru/perubahan exam
- hapus exam

#### D. Builder Selection & Progress

Public method terkait:

- `handle_sync_exam_builder_selection()`
- `handle_clear_exam_builder_selection()`
- `handle_start_exam_save_progress()`
- `handle_continue_exam_save_progress()`
- `get_exam_builder_selection_transient_key()`
- `get_exam_builder_selected_question_ids()`
- `save_exam_builder_selected_question_ids()`
- `clear_exam_builder_selection_state()`

Tugas:

- menyimpan state builder yang tidak nyaman ditaruh langsung di form biasa
- mendukung flow save besar yang perlu progress bertahap

#### E. List State & Normalization

Public method terkait:

- `get_exam_list_state_from_request()`
- `add_exam_list_state_args()`
- `normalize_standard_list_per_page()`
- `normalize_exam_builder_question_per_page()`
- `split_target_kelas_csv()`
- `normalize_target_kelas_csv()`
- `to_datetime_local()`
- `from_datetime_local()`

Tugas:

- menjaga state filter/list
- normalisasi target kelas
- normalisasi tanggal/waktu lokal

#### F. Helper Lintas Flow

Public method terkait:

- `sync_exam_questions_from_sources_for_internal_use()`
- `get_distinct_user_meta_values()`

`class-cbt-admin-exams-helper.php` menambah formatter:

- `build_question_panel_summary()`
- `format_schedule()`
- `format_target_kelas_summary()`
- `format_status_duration_summary()`
- `format_selected_questions_summary()`
- `format_exam_list_target_kelas_display()`

### View Notes

- `views/exams/page.php` adalah view admin terbesar di repo dengan `5018` line
- `views/exams/preview.php` adalah view preview khusus dengan `455` line

### Titik Masuk Contributor

Kalau tugas Anda adalah:

- mengubah daftar/filter/builder exam:
  mulai dari `class-cbt-admin-exams-service.php` lalu `views/exams/page.php`
- mengubah preview:
  mulai dari `build_preview_context()` lalu `views/exams/preview.php`
- mengubah AJAX builder:
  mulai dari `class-cbt-admin-exams-actions.php` lalu method `handle_*` terkait di service
- mengubah ringkasan kecil:
  mulai dari `class-cbt-admin-exams-helper.php`

### Special Cases

- modul ini punya branch preview
- modul ini sangat bergantung pada AJAX untuk builder
- modul ini menyimpan state selection builder terpisah dari submit biasa

## 3. Setup

### Tujuan Modul

Modul `Setup` adalah pusat konfigurasi branding dan security ujian.

Ia menjadi tempat untuk:

- konfigurasi branding sekolah/program
- konfigurasi fullscreen, clipboard blocking, security logging, dan idle detection
- menampilkan security log dan must-watch attempts dari sisi admin

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Branding` |
| Slug | `cbt-setup` |
| Capability | `cbt_manage_exams` |
| Callback render | `CBT_Admin_Setup_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-setup-page.php` | Render halaman setup dan panel tambahan |
| `class-cbt-admin-setup-service.php` | Security settings, branding context, log context |
| `class-cbt-admin-setup-actions.php` | Save branding, save security, manage security logs |
| `views/setup/page.php` | UI setup/branding/security/log |

### Render & Context Flow

1. `CBT_Admin_Setup_Page::render()`
2. Capability check lewat `CBT_Admin_Setup_Service::can_manage_exams()`
3. `build_page_context($_GET)`
4. View setup dirender

Selain `render()`, page class juga punya:

- `render_security_log_must_watch_panel()`

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_save_setup_branding` | `CBT_Admin_Setup_Actions::handle_save_setup_branding()` | Simpan branding |
| `admin_post_cbt_save_security_settings` | `CBT_Admin_Security_Actions::handle_save_security_settings()` | Simpan security config |
| `admin_post_cbt_save_setup_security` | `CBT_Admin_Security_Actions::handle_save_setup_security()` | Alias kompatibilitas save security lama |
| `admin_post_cbt_manage_security_logs` | `CBT_Admin_Security_Actions::handle_manage_security_logs()` | Kelola security logs |

### Public Method Map

`class-cbt-admin-setup-service.php`:

- `can_manage_exams()`
- `is_admin_scope()`
- `security_option_key()`
- `get_security_settings()`
- `build_page_context()`

### Responsibility Clusters

#### A. Branding Context

Service setup membaca branding lewat:

- `CBT_Admin_Branding_Settings::get_settings()`

Artinya:

- setup adalah konsumen utama branding settings
- branding itself disimpan di utilitas lintas modul

#### B. Security Settings

Service setup menangani:

- `force_fullscreen`
- `block_copy_paste`
- `log_security_events`
- `detect_idle_during_exam`
- `idle_threshold_minutes`

#### C. Security Observability

Service setup juga menyiapkan:

- event definitions dari `CBT_Security_Log`
- `must_watch_attempts`
- recent security logs

### View Notes

- `views/setup/page.php` berukuran `2426` line
- ini berarti setup bukan halaman kecil; ia membawa branding, control security, dan observability sekaligus

### Titik Masuk Contributor

- ubah field branding:
  buka `class-cbt-admin-branding-settings.php`, `class-cbt-admin-setup-actions.php`, dan `views/setup/page.php`
- ubah security options:
  buka `get_security_settings()` dan `views/setup/page.php`
- ubah panel must-watch/log:
  buka `build_page_context()` lalu view setup

### Special Cases

- setup bergantung pada subsystem luar-modul seperti `CBT_Security_Log`
- page class punya render helper tambahan, tidak hanya `render()`

## 4. Subjects

### Tujuan Modul

Modul `Subjects` mengelola CRUD dan import subject/mapel yang dipakai sebagai dasar pengelompokan exam dan bank soal.

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Subjects` |
| Slug | `cbt-subjects` |
| Capability | `manage_options` |
| Callback render | `CBT_Admin_Subjects_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-subjects-page.php` | Render halaman subjects |
| `class-cbt-admin-subjects-service.php` | Context daftar/import state dan helper import |
| `class-cbt-admin-subjects-actions.php` | Save/delete/import/download template |
| `views/subjects/page.php` | UI subjects |

### Render & Context Flow

1. `CBT_Admin_Subjects_Page::render()`
2. `CBT_Admin_Subjects_Service::can_manage_subjects()`
3. `build_page_context($_GET)`
4. `views/subjects/page.php`

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_save_subject` | `handle_save_subject()` | Simpan subject |
| `admin_post_cbt_delete_subject` | `handle_delete_subject()` | Hapus subject |
| `admin_post_cbt_bulk_delete_subjects` | `handle_bulk_delete_subjects()` | Bulk delete |
| `admin_post_cbt_import_subjects` | `handle_import_subjects()` | Import subject |
| `admin_post_cbt_download_subject_template` | `handle_download_subject_template()` | Download template CSV |
| `admin_post_cbt_download_subject_template_xlsx` | `handle_download_subject_template_xlsx()` | Download template XLSX |

### Public Method Map

`class-cbt-admin-subjects-service.php`:

- `can_manage_subjects()`
- `is_admin_scope()`
- `build_page_context()`
- `normalize_standard_list_per_page()`
- `prepare_runtime_for_bulk_import()`
- `start_import()`
- `continue_import()`
- `get_subject_import_state_for_current_user()`

### Titik Masuk Contributor

- ubah daftar/filter/import state:
  buka `build_page_context()`
- ubah CRUD subject:
  buka `class-cbt-admin-subjects-actions.php`
- ubah template UI:
  buka `views/subjects/page.php`

### Special Cases

- subjects punya flow import bertahap sendiri
- download template dipegang oleh actions, bukan helper terpisah

## 5. Users

### Tujuan Modul

Modul `Users` mengelola user CBT: CRUD manual, import massal, bulk action, template download, dan metadata seperti kelas, ruang, agama, foto, dan role.

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Users` |
| Slug | `cbt-user-import` |
| Capability | `manage_options` |
| Callback render | `CBT_Admin_Users_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-users-page.php` | Render halaman users |
| `class-cbt-admin-users-service.php` | Context, CRUD manual, import, parsing, lookup, template |
| `class-cbt-admin-users-actions.php` | Adapter hook users |
| `views/users/page.php` | UI users/import/manual edit |

### Render & Context Flow

1. `CBT_Admin_Users_Page::render()`
2. `CBT_Admin_Users_Service::can_manage_users()`
3. `build_page_context($_GET)`
4. `views/users/page.php`

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_import_users` | `handle_import_users()` | Import users |
| `admin_post_cbt_create_user_manual` | `handle_create_user_manual()` | Buat user manual |
| `admin_post_cbt_update_user_manual` | `handle_update_user_manual()` | Update user manual |
| `admin_post_cbt_delete_user_manual` | `handle_delete_user_manual()` | Hapus user manual |
| `admin_post_cbt_bulk_delete_users` | `handle_bulk_delete_users()` | Bulk delete users |
| `admin_post_cbt_download_user_template` | `handle_download_user_template()` | Download template CSV |
| `admin_post_cbt_download_user_template_xlsx` | `handle_download_user_template_xlsx()` | Download template XLSX |

### Responsibility Clusters

#### A. Permission & Context

Public method terkait:

- `can_manage_users()`
- `is_admin_scope()`
- `build_page_context()`

#### B. CRUD Manual

Public method terkait:

- `handle_create_user_manual()`
- `handle_update_user_manual()`
- `handle_delete_user_manual()`
- `handle_bulk_delete_users()`

#### C. Import Pipeline

Public method terkait:

- `handle_import_users()`
- `parse_user_csv()`
- `parse_user_xlsx()`
- `upsert_user_from_row()`
- `build_user_import_lookup()`

#### D. Template & Reference Data

Public method terkait:

- `handle_download_user_template()`
- `handle_download_user_template_xlsx()`
- `get_supported_agama_options()`
- `normalize_supported_agama()`
- `humanize_role()`
- `get_distinct_user_meta_values()`

### View Notes

- `views/users/page.php` berukuran `1358` line
- artinya CRUD manual dan import ditangani dalam satu view yang cukup besar

### Titik Masuk Contributor

- ubah import:
  mulai dari `handle_import_users()`, `parse_user_csv()`, `parse_user_xlsx()`
- ubah validasi role/agama:
  mulai dari `normalize_supported_agama()` dan `humanize_role()`
- ubah UI form/manual edit:
  buka `views/users/page.php`

### Special Cases

- modul users memegang parsing file sendiri; tidak dipisah ke helper terpisah
- modul ini salah satu service menengah-besar di area admin

## 6. Questions

### Tujuan Modul

Modul `Questions` adalah subsystem bank soal dan salah satu area admin paling kompleks. Ia menangani:

- halaman bank soal
- CRUD pertanyaan
- helper normalisasi tipe soal
- import pipeline
- template download
- sinkronisasi snapshot soal sumber

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Questions` |
| Slug | `cbt-question-bank` |
| Capability | `cbt_manage_questions` |
| Callback render | `CBT_Admin_Questions_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-questions-page.php` | Render halaman bank soal |
| `class-cbt-admin-questions-service.php` | Context utama dan CRUD inti |
| `class-cbt-admin-questions-actions.php` | Hook adapter save/delete/import/download |
| `class-cbt-admin-questions-helper.php` | Utility utama tipe soal, preview, rich text, option parsing |
| `class-cbt-admin-questions-import-helper.php` | Import pipeline dan template downloads |
| `class-cbt-admin-questions-sync-helper.php` | Sinkronisasi/backfill snapshot/source question |
| `views/questions/page.php` | UI bank soal, editing, import, preview, dan type switching |

### Render & Context Flow

1. `CBT_Admin_Questions_Page::render()`
2. `CBT_Admin_Questions_Service::can_manage_questions()`
3. `build_page_context($_GET)`
4. `views/questions/page.php`

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_save_question` | `handle_save_question()` | Simpan soal |
| `admin_post_cbt_delete_question` | `handle_delete_question()` | Hapus soal |
| `admin_post_cbt_bulk_delete_questions` | `handle_bulk_delete_questions()` | Bulk delete |
| `admin_post_cbt_import_questions` | `handle_import_questions()` | Import soal |
| `admin_post_cbt_download_question_template_word` | `handle_download_question_template_word()` | Template Word generic |
| `admin_post_cbt_download_question_template_word_mc` | `handle_download_question_template_word_mc()` | Template Word MC |
| `admin_post_cbt_download_question_template_word_ma` | `handle_download_question_template_word_ma()` | Template Word MA |
| `admin_post_cbt_download_question_template_word_sa` | `handle_download_question_template_word_sa()` | Template Word SA |
| `admin_post_cbt_download_question_template_word_tf` | `handle_download_question_template_word_tf()` | Template Word TF |
| `admin_post_cbt_download_question_template_word_tfm` | `handle_download_question_template_word_tfm()` | Template Word TFM |
| `admin_post_cbt_download_question_template_word_essay` | `handle_download_question_template_word_essay()` | Template Word Essay |

### Responsibility Clusters

#### A. Permission & Main Context

`class-cbt-admin-questions-service.php`:

- `can_manage_questions()`
- `is_admin_scope()`
- `build_page_context()`

Fungsi:

- menentukan scope admin/non-admin
- menyiapkan payload halaman bank soal

#### B. CRUD Inti Pertanyaan

`class-cbt-admin-questions-service.php`:

- `handle_save_question()`
- `handle_delete_question()`
- `handle_bulk_delete_questions()`
- `get_question_delete_state_for_current_user()`

Fungsi:

- save/update pertanyaan
- hapus satu/banyak pertanyaan
- state delete per user

#### C. Utility Tipe Soal & Preview

`class-cbt-admin-questions-helper.php`:

- `normalize_question_page_slug()`
- `forced_question_type_for_page()`
- `build_attempt_answer_preview()`
- `build_short_answer_progress_slots()`
- `normalize_short_answer_compare_value()`
- `normalize_short_answer_values()`
- `normalize_short_answer_payload()`
- `normalize_true_false_matrix_config()`
- `normalize_true_false_matrix_payload()`
- `ensure_subject_question_bank_exam()`
- `question_type_detail_tables()`
- `save_question_type_detail()`
- `get_question_type_detail()`
- `normalize_true_false_value()`
- `get_question_type_label()`
- `get_admin_student_preview_css()`
- `render_admin_student_preview_card()`
- `sanitize_editor_html()`
- `render_editor_html()`
- `parse_options()`
- `has_non_empty_html_content()`
- `normalize_optional_rich_text()`
- `decode_attempt_selected_option_ids()`

Fungsi:

- menyimpan knowledge spesifik tipe soal
- mengelola payload short answer dan true/false matrix
- menyiapkan preview dan rich text normalization

#### D. Import Pipeline & Template

`class-cbt-admin-questions-import-helper.php`:

- `handle_import_questions()`
- `handle_download_question_template_word()`
- `handle_download_question_template_word_mc()`
- `handle_download_question_template_word_ma()`
- `handle_download_question_template_word_sa()`
- `handle_download_question_template_word_tf()`
- `handle_download_question_template_word_tfm()`
- `handle_download_question_template_word_essay()`
- `build_options_raw_from_import()`
- `map_import_question_type()`
- `get_question_import_state_for_current_user()`

Fungsi:

- import file
- template download per tipe
- normalisasi type alias dan option raw dari file import

#### E. Sinkronisasi Bank Soal

`class-cbt-admin-questions-sync-helper.php`:

- `is_bank_question_snapshot()`
- `get_question_sync_snapshot()`
- `question_snapshots_are_legacy_descendant_match()`
- `propagate_bank_question_update()`
- `run_question_source_backfill()`
- `apply_source_snapshot_to_question()`

Fungsi:

- menjaga hubungan source question dan salinan turunannya
- backfill snapshot/sumber
- propagate perubahan dari bank soal

### View Notes

- `views/questions/page.php` berukuran `2817` line
- ini adalah salah satu view terpadat di admin karena menggabungkan editing, import, dan switching tipe soal dalam satu halaman

### Titik Masuk Contributor

- ubah CRUD inti:
  mulai dari `class-cbt-admin-questions-service.php`
- ubah perilaku tipe soal:
  mulai dari `class-cbt-admin-questions-helper.php`
- ubah import atau template:
  mulai dari `class-cbt-admin-questions-import-helper.php`
- ubah sinkronisasi ke source question:
  mulai dari `class-cbt-admin-questions-sync-helper.php`
- ubah UI besar:
  buka `views/questions/page.php`

### Special Cases

- modul ini dibelah ke empat class utama selain page
- modul ini adalah salah satu area paling penting untuk menjaga dokumentasi tetap up to date

## 7. Tokens

### Tujuan Modul

Modul `Tokens` mengelola token exam/global exam token.

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Tokens` |
| Slug | `cbt-tokens` |
| Capability | `cbt_manage_exams` |
| Callback render | `CBT_Admin_Tokens_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-tokens-page.php` | Render halaman token |
| `class-cbt-admin-tokens-service.php` | Context token |
| `class-cbt-admin-tokens-actions.php` | Simpan global exam token |
| `views/tokens/page.php` | UI token |

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_save_global_exam_token` | `handle_save_global_exam_token()` | Simpan token global |

### Public Method Map

`class-cbt-admin-tokens-service.php`:

- `can_manage_exams()`
- `is_admin_scope()`
- `build_page_context()`

### Titik Masuk Contributor

- ubah tampilan token:
  buka `views/tokens/page.php`
- ubah payload halaman:
  buka `build_page_context()`
- ubah save path:
  buka `class-cbt-admin-tokens-actions.php`

### Special Cases

- salah satu modul paling kecil dan lurus di folder admin

## 8. Exam Cards

### Tujuan Modul

Modul `Exam Cards` menyusun daftar siswa untuk kartu ujian dan menghasilkan output cetak.

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Exam Cards` |
| Slug | `cbt-exam-cards` |
| Capability | `cbt_manage_users` |
| Callback render | `CBT_Admin_Exam_Cards_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-exam-cards-page.php` | Render halaman exam cards |
| `class-cbt-admin-exam-cards-service.php` | Context halaman dan context print |
| `class-cbt-admin-exam-cards-actions.php` | Action cetak kartu |
| `views/exam-cards/page.php` | UI exam cards |
| `views/exam-cards/print.php` | Output cetak kartu |

### Render & Print Flow

Flow halaman:

1. `render()`
2. capability check
3. `build_page_context($_GET)`
4. `views/exam-cards/page.php`

Flow cetak:

1. `admin_post_cbt_print_exam_cards`
2. `handle_print_exam_cards()`
3. `build_print_context($_POST)`
4. render `views/exam-cards/print.php`

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_print_exam_cards` | `handle_print_exam_cards()` | Cetak kartu ujian |

### Public Method Map

`class-cbt-admin-exam-cards-service.php`:

- `can_manage_users()`
- `build_page_context()`
- `build_print_context()`
- `get_exam_card_students()`
- `get_exam_card_schedule_rows()`
- `format_exam_card_schedule_line()`
- `generate_exam_card_password()`

### Titik Masuk Contributor

- ubah filter/daftar siswa:
  buka `build_page_context()` dan `get_exam_card_students()`
- ubah cetak:
  buka `build_print_context()` dan `views/exam-cards/print.php`
- ubah jadwal yang ditampilkan:
  buka `get_exam_card_schedule_rows()` dan `format_exam_card_schedule_line()`

### Special Cases

- modul ini punya write flow yang tidak menyimpan data, tetapi menghasilkan output print
- password kartu dapat di-generate dari service

## 9. Results

### Tujuan Modul

Modul `Results` menangani monitoring hasil ujian dan operasi langsung pada attempt.

Area ini mencakup:

- daftar hasil
- review attempt
- grading essay
- reset login
- reset attempt
- extra time
- force complete
- bulk actions terkait hasil

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Results` |
| Slug | `cbt-results` |
| Capability | `cbt_view_results` |
| Callback render | `CBT_Admin_Results_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-results-page.php` | Render halaman results |
| `class-cbt-admin-results-service.php` | Context results dan operasi attempt |
| `class-cbt-admin-results-actions.php` | Adapter hook results |
| `class-cbt-admin-results-helper.php` | Helper progress/review/remaining time |
| `views/results/page.php` | UI results dan review attempt |

### Render & Context Flow

1. `CBT_Admin_Results_Page::render()`
2. `CBT_Admin_Results_Service::can_view_results()`
3. `build_page_context($_GET)`
4. `views/results/page.php`

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_grade_essay` | `handle_grade_essay()` | Grade essay |
| `admin_post_cbt_reset_user_login` | `handle_reset_user_login()` | Reset login user |
| `admin_post_cbt_reset_attempt` | `handle_reset_attempt()` | Reset attempt |
| `admin_post_cbt_extend_attempt_time` | `handle_extend_attempt_time()` | Tambah waktu |
| `admin_post_cbt_force_complete_attempt` | `handle_force_complete_attempt()` | Force complete satu attempt |
| `admin_post_cbt_bulk_reset_attempts` | `handle_bulk_reset_attempts()` | Bulk reset |
| `admin_post_cbt_bulk_force_complete_attempts` | `handle_bulk_force_complete_attempts()` | Bulk force complete |

### Responsibility Clusters

#### A. Permission & Context

`class-cbt-admin-results-service.php`:

- `can_view_results()`
- `is_admin_scope()`
- `build_page_context()`

#### B. Attempt Operations

`class-cbt-admin-results-service.php`:

- `handle_grade_essay()`
- `handle_extend_attempt_time()`
- `handle_reset_attempt()`
- `handle_reset_user_login()`
- `handle_force_complete_attempt()`
- `handle_bulk_reset_attempts()`
- `handle_bulk_force_complete_attempts()`

#### C. Review Helpers

`class-cbt-admin-results-helper.php`:

- `calculate_attempt_remaining_seconds()`
- `format_attempt_remaining_label()`
- `build_attempt_answer_progress_map()`
- `render_attempt_answer_progress_table_html()`
- `summarize_attempt_answer_progress_items()`

### View Notes

- `views/results/page.php` berukuran `3151` line
- results adalah view padat karena menggabungkan table, filter, detail review, dan action attempt

### Titik Masuk Contributor

- ubah filter/payload results:
  buka `build_page_context()`
- ubah operasi attempt:
  buka `class-cbt-admin-results-service.php`
- ubah render progress/review kecil:
  buka `class-cbt-admin-results-helper.php`
- ubah UI:
  buka `views/results/page.php`

### Special Cases

- modul ini write-heavy walau nama menunya “results”
- banyak action penting di sini memengaruhi status attempt secara langsung

## 10. Analytics

### Tujuan Modul

Modul `Analytics` menampilkan insight analitik hasil ujian.

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Analytics` |
| Slug | `cbt-analytics` |
| Capability | `cbt_view_results` |
| Callback render | `CBT_Admin_Analytics_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-analytics-page.php` | Render halaman analytics |
| `class-cbt-admin-analytics-service.php` | Context analitik, URL helpers, tab normalization |
| `views/analytics/page.php` | UI analytics |

### Render & Context Flow

1. `CBT_Admin_Analytics_Page::render()`
2. `CBT_Admin_Analytics_Service::can_view()`
3. `build_page_context($_GET)`
4. `views/analytics/page.php`

### Public Method Map

`class-cbt-admin-analytics-service.php`:

- `can_view()`
- `is_admin_scope()`
- `build_page_context()`
- `build_analytics_url()`
- `build_results_url()`
- `normalize_tab()`

### Responsibility Clusters

#### A. Access & Tab State

- `can_view()`
- `is_admin_scope()`
- `normalize_tab()`

#### B. Analytics Context

- `build_page_context()`

#### C. Cross-Link Helpers

- `build_analytics_url()`
- `build_results_url()`

### View Notes

- `views/analytics/page.php` berukuran `2172` line
- walau service public method map-nya pendek, view analitik sendiri cukup besar

### Titik Masuk Contributor

- ubah tab/filter/URL:
  buka `normalize_tab()`, `build_analytics_url()`, `build_results_url()`
- ubah data insight:
  buka `build_page_context()`
- ubah tampilan:
  buka `views/analytics/page.php`

### Special Cases

- modul ini tidak punya `actions` class terpisah
- fokusnya murni page + service + view

## 11. Report Exam

### Tujuan Modul

Modul `Report Exam` menangani pelaporan exam dan incident report yang terkait exam.

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Report Exam` |
| Slug | `cbt-report-exam` |
| Capability | `cbt_view_results` |
| Callback render | `CBT_Admin_Report_Exam_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-report-exam-page.php` | Render halaman report exam |
| `class-cbt-admin-report-exam-service.php` | Context halaman, print context, incident flow |
| `class-cbt-admin-report-exam-actions.php` | Export print dan incident actions |
| `views/report-exam/page.php` | UI report exam |
| `views/report-exam/print.php` | Output print report exam |

### Render & Print Flow

Flow halaman:

1. `render()`
2. `build_page_context($_GET)`
3. `views/report-exam/page.php`

Flow print:

1. `admin_post_cbt_export_exam_report_pdf`
2. `handle_export_exam_report_pdf()`
3. `build_print_context($_POST)`
4. render `views/report-exam/print.php`

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_export_exam_report_pdf` | `handle_export_exam_report_pdf()` | Export/print report exam |
| `admin_post_cbt_save_exam_incident` | `handle_save_exam_incident()` | Simpan incident |
| `admin_post_cbt_update_exam_incident` | `handle_update_exam_incident()` | Update incident |
| `admin_post_cbt_delete_exam_incident` | `handle_delete_exam_incident()` | Hapus incident |

### Responsibility Clusters

#### A. Page Context & Print Context

`class-cbt-admin-report-exam-service.php`:

- `build_page_context()`
- `build_print_context()`
- `normalize_report_exam_tab()`

#### B. Incident Context Helpers

- `get_report_incident_context_from_request()`
- `get_report_incident_current_staff_row()`
- `get_report_incident_exam_kelas_options()`
- `get_report_incident_student_rows()`
- `get_report_incident_ruang_options()`
- `get_report_incident_student_row_by_id()`
- `format_report_incident_datetime()`
- `get_report_incident_scope_filters()`
- `resolve_report_incident_submission()`

#### C. Supervisor/Filter Helpers

- `extract_report_supervisor_input()`
- `report_supervisor_role_options()`
- `normalize_report_supervisor_role()`
- `build_report_supervisor_role_labels()`
- `get_accessible_exam_filter_rows()`
- `get_accessible_exam_row()`

#### D. Report Row Builders

- `get_exam_report_rows()`
- `get_exam_report_incident_rows()`
- `get_distinct_user_meta_values()`
- `resolve_student_default_photo()`

### View Notes

- `views/report-exam/page.php` berukuran `1608` line
- `views/report-exam/print.php` berukuran `473` line

### Titik Masuk Contributor

- ubah filter/tab/context halaman:
  buka `build_page_context()` dan `normalize_report_exam_tab()`
- ubah print report:
  buka `build_print_context()` dan `views/report-exam/print.php`
- ubah incident flow:
  buka `handle_save_exam_incident()`, `handle_update_exam_incident()`, `handle_delete_exam_incident()`, lalu telusuri service

### Special Cases

- modul ini bukan hanya “print report”
- modul ini juga memegang incident report, sehingga write path-nya lebih kompleks dari yang terlihat dari nama menunya

## 12. Test Hub

### Tujuan Modul

Modul `Test Hub` adalah pusat QA internal dan runner checklist test.

Ia memegang:

- settings test hub
- unit checklist context
- runner definition
- run unit test suite
- queue flow-check job

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Test Hub` |
| Slug | `cbt-test-hub` |
| Capability | `manage_options` |
| Callback render | `CBT_Admin_Test_Hub_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-test-hub-page.php` | Render halaman test hub |
| `class-cbt-admin-test-hub-service.php` | Context checklist, settings, runner, flow job |
| `class-cbt-admin-test-hub-actions.php` | Save settings, run suite, queue flow-check |
| `views/test-hub/page.php` | UI test hub |

### Render & Context Flow

1. `CBT_Admin_Test_Hub_Page::render()`
2. `CBT_Admin_Test_Hub_Service::can_manage_test_hub()`
3. `build_unit_test_context($_GET)`
4. `views/test-hub/page.php`

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_save_test_hub_settings` | `handle_save_settings()` | Simpan settings test hub |
| `admin_post_cbt_run_unit_test_suite` | `handle_run_unit_test_suite()` | Jalankan unit test suite |
| `admin_post_cbt_queue_flow_check_job` | `handle_queue_flow_check_job()` | Queue flow-check job |

### Responsibility Clusters

#### A. Settings

- `get_settings()`
- `sanitize_settings_input()`
- `save_settings()`
- `test_hub_page_url()`

#### B. Unit Checklist Context

- `build_unit_test_context()`
- `normalize_unit_test_tab()`
- `normalize_unit_test_scope()`

#### C. Runner / Queue

- `handle_run_unit_test_suite()`
- `handle_queue_flow_check_job()`
- `handle_save_settings()`

### View Notes

- `views/test-hub/page.php` berukuran `1531` line

### Titik Masuk Contributor

- ubah setting base URL / form settings:
  buka `get_settings()` dan `handle_save_settings()`
- ubah checklist/tab:
  buka `build_unit_test_context()`
- ubah queue flow-check:
  buka `handle_queue_flow_check_job()`

### Special Cases

- maintenance punya redirect khusus ke test hub bila tab maintenance tertentu dipakai
- modul ini menjadi jembatan antara admin UI dan runner test internal

## 13. Cache

### Tujuan Modul

Modul `Cache` menangani observability dan operasi cache untuk plugin.

Area utama:

- cache readiness
- probe cache/runtime
- next steps recommendation
- bootstrap Redis object cache
- rollback Redis integration
- namespace inspection

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Cache` |
| Slug | `cbt-cache` |
| Capability | `manage_options` |
| Callback render | `CBT_Admin_Cache_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-cache-page.php` | Render halaman cache dan runtime notice |
| `class-cbt-admin-cache-service.php` | Context cache, readiness, probe, bootstrap, rollback |
| `class-cbt-admin-cache-actions.php` | Action cache terpusat |
| `views/cache/page.php` | UI cache |

### Render & Context Flow

1. `CBT_Admin_Cache_Page::render()`
2. `CBT_Admin_Cache_Service::can_manage_cache()`
3. `build_page_context($_GET)`
4. `views/cache/page.php`

Runtime notice:

- `CBT_Admin_Cache_Page::render_runtime_notice()`
- context-nya dihasilkan oleh `get_runtime_notice_context()`

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_cache_action` | `handle_cache_action()` | Semua action cache admin |

### Public Method Map

`class-cbt-admin-cache-service.php`:

- `can_manage_cache()`
- `build_page_context()`
- `get_runtime_notice_context()`
- `cache_readiness_meta()`
- `cache_probe_meta()`
- `cache_server_probe_meta()`
- `cache_next_steps()`
- `cache_readiness_summary()`
- `cache_boolean_label()`
- `cache_scalar_label()`
- `bootstrap_redis_wordpress()`
- `should_render_redis_rollback_action()`
- `rollback_redis_wordpress()`
- `cache_namespace_group_meta()`

### Titik Masuk Contributor

- ubah readiness/probe summary:
  buka `cache_readiness_meta()`, `cache_probe_meta()`, `cache_server_probe_meta()`
- ubah CTA/next steps:
  buka `cache_next_steps()`
- ubah bootstrap/rollback flow:
  buka `bootstrap_redis_wordpress()` dan `rollback_redis_wordpress()`
- ubah notice:
  buka `render_runtime_notice()` dan `get_runtime_notice_context()`

### Special Cases

- satu hook `cbt_cache_action` menjadi multiplexer action cache
- cache page punya runtime notice tambahan di luar render halaman penuh

## 14. Maintenance

### Tujuan Modul

Modul `Maintenance` adalah modul admin paling besar dan paling operasional. Ia menangani:

- reset database CBT
- generate dataset uji
- load test jobs
- artifact download
- export student pool untuk load test
- polling status load test via AJAX

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Maintenance` |
| Slug | `cbt-maintenance` |
| Capability | `manage_options` |
| Callback render | `CBT_Admin_Maintenance_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-maintenance-page.php` | Render halaman maintenance, redirect tertentu, render partial load-test jobs |
| `class-cbt-admin-maintenance-service.php` | Logic reset/seed/load-test/export/status/artifact |
| `class-cbt-admin-maintenance-actions.php` | Adapter hook maintenance |
| `views/maintenance/page.php` | UI maintenance utama |
| `views/maintenance/partials/load-test-jobs.php` | Partial HTML daftar job load test |

### Render & Context Flow

1. `CBT_Admin_Maintenance_Page::render()`
2. Capability check lewat `can_manage_maintenance()`
3. Jika tab `unit_test` dipakai:
   - request dialihkan ke halaman `cbt-test-hub`
4. Jika tidak:
   - `build_page_context($_GET)`
   - partial `load_test_jobs_html` dirender lewat `render_load_test_jobs_markup(...)`
   - view maintenance utama dimuat

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_reset_database` | `handle_reset_database()` | Reset database CBT |
| `admin_post_cbt_generate_test_dataset` | `handle_generate_test_dataset()` | Generate dataset uji |
| `admin_post_cbt_start_load_test` | `handle_start_load_test()` | Start load test |
| `admin_post_cbt_cancel_load_test` | `handle_cancel_load_test()` | Cancel job load test |
| `admin_post_cbt_delete_load_test_job` | `handle_delete_load_test_job()` | Hapus satu job |
| `admin_post_cbt_clear_load_test_jobs` | `handle_clear_load_test_jobs()` | Bersihkan histori job |
| `admin_post_cbt_download_load_test_artifact` | `handle_download_load_test_artifact()` | Download artifact |
| `admin_post_cbt_export_load_test_students_json` | `handle_export_load_test_students_json()` | Export student pool JSON |
| `admin_post_cbt_export_load_test_students_csv` | `handle_export_load_test_students_csv()` | Export student pool CSV |
| `admin_post_cbt_export_load_test_students_xlsx` | `handle_export_load_test_students_xlsx()` | Export student pool XLSX |
| `wp_ajax_cbt_load_test_jobs` | `handle_load_test_jobs_ajax()` | Polling daftar/status job |

### Responsibility Clusters

#### A. Permission & Seed Metadata

Public method terkait:

- `can_manage_maintenance()`
- `get_seed_special_student_username()`
- `get_seed_special_student_password()`
- `get_seed_special_admin_username()`
- `get_seed_special_admin_password()`
- `get_seed_default_password()`
- `get_seed_recovery_fixture_exam_title()`
- `get_seed_flow_check_fixture_exam_titles()`
- `get_seed_fixture_exam_title()`

Fungsi:

- capability guard
- source of truth akun seed/fixture
- source of truth nama exam fixture tertentu

#### B. Page Context

Public method terkait:

- `build_page_context()`

Fungsi:

- menyiapkan semua tab maintenance
- status progress reset/seed
- status job load test
- payload artifact/export

#### C. Reset & Seed

Public method terkait:

- `handle_reset_database()`
- `handle_generate_test_dataset()`

Fungsi:

- reset data CBT
- generate dataset uji

#### D. Load Test Job Lifecycle

Public method terkait:

- `handle_start_load_test()`
- `handle_cancel_load_test()`
- `handle_delete_load_test_job()`
- `handle_clear_load_test_jobs()`
- `handle_load_test_jobs_ajax()`

Fungsi:

- start/cancel/delete/clear job load test
- polling status job dari UI admin

#### E. Artifact, Summary, dan Status Helpers

Public method terkait:

- `normalize_load_test_job()`
- `get_load_test_job_artifacts()`
- `read_load_test_job_summary()`
- `read_load_test_log_tail()`
- `get_load_test_status_meta()`
- `format_load_test_datetime()`
- `get_load_test_job_selection_label()`

#### F. Student Pool Export

Public method terkait:

- `handle_export_load_test_students_json()`
- `handle_export_load_test_students_csv()`
- `handle_export_load_test_students_xlsx()`

### View Notes

- `class-cbt-admin-maintenance-service.php` adalah file PHP admin terbesar di repo: `5881` line
- `views/maintenance/page.php` juga termasuk view terbesar: `2730` line
- `views/maintenance/partials/load-test-jobs.php` adalah partial terpisah untuk daftar job load test

### Titik Masuk Contributor

- ubah reset flow:
  buka `handle_reset_database()`
- ubah dataset uji:
  buka `handle_generate_test_dataset()` dan method seed metadata
- ubah load test job:
  buka cluster load-test methods
- ubah artifact/status display:
  buka helper status/artifact dan partial `load-test-jobs.php`

### Special Cases

- modul ini punya redirect ke Test Hub dari tab tertentu
- modul ini satu-satunya modul yang merender partial HTML terpisah di page class
- modul ini sangat besar dan paling butuh disiplin dokumentasi

## 15. Developer

### Tujuan Modul

Modul `Developer` menangani source asset frontend dan tooling developer internal.

Area utamanya:

- mode asset source
- constant override
- dev server health
- launcher status
- debug context
- diagnostics context
- manifest/build status

### Menu & Akses

| Item | Nilai |
| --- | --- |
| Label menu | `CBT Developer` |
| Slug | `cbt-developer` |
| Capability | `manage_options` |
| Callback render | `CBT_Admin_Developer_Page::render()` |

### File Inventory

| File | Peran |
| --- | --- |
| `class-cbt-admin-developer-page.php` | Render halaman developer |
| `class-cbt-admin-developer-service.php` | Source resolution, health, launchers, diagnostics, settings |
| `class-cbt-admin-developer-actions.php` | Save/check/stop dev server |
| `views/developer/page.php` | UI developer/detailed diagnostics |

### Render & Context Flow

1. `CBT_Admin_Developer_Page::render()`
2. `CBT_Admin_Developer_Service::can_manage()`
3. `build_page_context($_GET)`
4. `views/developer/page.php`

### Hook & Actions

| Hook | Target | Fungsi |
| --- | --- | --- |
| `admin_post_cbt_save_developer_settings` | `handle_save_settings()` | Simpan setting source asset/dev server |
| `admin_post_cbt_check_developer_dev_server` | `handle_check_dev_server()` | Cek health dev server |
| `admin_post_cbt_stop_developer_dev_server` | `handle_stop_dev_server()` | Hentikan dev server |

### Responsibility Clusters

#### A. Settings & Override

- `can_manage()`
- `option_key()`
- `get_settings()`
- `has_constant_override()`
- `get_constant_override_url()`
- `sanitize_settings_input()`
- `save_settings()`
- `developer_page_url()`

#### B. Asset Source Resolution

- `resolve_frontend_asset_source()`
- `get_build_manifest_status()`
- `get_frontend_page_url()`

#### C. Debug / Diagnostics Context

- `resolve_frontend_debug_context()`
- `resolve_frontend_diagnostics_context()`
- `get_storage_debug_config()`

#### D. Launcher & Process Flow

- `get_dev_server_launcher_status()`
- `get_build_watch_launcher_status()`
- `ensure_dev_server_available()`
- `stop_dev_server()`
- `ensure_build_watch_available()`
- `stop_build_watch()`

#### E. Health & Page Context

- `get_dev_server_health()`
- `build_page_context()`

### View Notes

- `views/developer/page.php` berukuran `3349` line
- developer page adalah salah satu halaman terpadat karena menyatukan settings, diagnostics, launcher status, dan manifest info

### Titik Masuk Contributor

- ubah pemilihan source asset:
  buka `resolve_frontend_asset_source()`
- ubah health check:
  buka `get_dev_server_health()`
- ubah launcher flow:
  buka cluster launcher methods
- ubah diagnostics UI:
  buka `views/developer/page.php`

### Special Cases

- modul ini berhubungan erat dengan frontend, tetapi tetap berada di admin layer
- ada konsep `constant override`, `build`, `dev`, dan state terkait debugging

## Deep Dive View Non-Standar

Bagian ini mengumpulkan file view yang sering terlupa karena tidak mengikuti pola `page.php` biasa.

## 1. `views/exams/preview.php`

### Dipakai Oleh

- `CBT_Admin_Exams_Page::render()`

### Trigger

- query string `preview_exam_id`

### Fungsi

- menampilkan preview exam tanpa memakai layout daftar exam utama

### Kenapa Penting

- kalau ada bug “preview berbeda dari builder”, file ini harus dicek terpisah
- preview tidak otomatis berbagi semua flow dengan `views/exams/page.php`

## 2. `views/exam-cards/print.php`

### Dipakai Oleh

- `CBT_Admin_Exam_Cards_Actions::handle_print_exam_cards()`

### Trigger

- `admin_post_cbt_print_exam_cards`

### Fungsi

- menghasilkan output cetak kartu ujian

### Kenapa Penting

- bukan halaman admin biasa
- context berasal dari `build_print_context($_POST)`

## 3. `views/report-exam/print.php`

### Dipakai Oleh

- `CBT_Admin_Report_Exam_Actions::handle_export_exam_report_pdf()`

### Trigger

- `admin_post_cbt_export_exam_report_pdf`

### Fungsi

- menghasilkan output print-ready report exam

### Kenapa Penting

- flow print রিপোর্ট terpisah dari halaman report utama
- sering menjadi titik perubahan jika format output cetak berubah

## 4. `views/maintenance/partials/load-test-jobs.php`

### Dipakai Oleh

- `CBT_Admin_Maintenance_Page::render_load_test_jobs_markup()`

### Trigger

- dirender sebagai partial ke dalam context maintenance
- juga relevan untuk flow polling/pembaruan status job

### Fungsi

- merender daftar job load test dan status ringkasnya

### Kenapa Penting

- ini bukan halaman penuh
- kalau daftar job load test rusak tetapi halaman maintenance utama baik-baik saja, file ini sering menjadi tersangka utama

## Appendix A — Hook Inventory Lengkap

Bagian ini adalah daftar lengkap hook admin yang didaftarkan di bootstrap admin, dikelompokkan per modul.

## Subjects

### `admin_post`

- `cbt_save_subject` -> `CBT_Admin_Subjects_Actions::handle_save_subject()`
- `cbt_delete_subject` -> `CBT_Admin_Subjects_Actions::handle_delete_subject()`
- `cbt_bulk_delete_subjects` -> `CBT_Admin_Subjects_Actions::handle_bulk_delete_subjects()`
- `cbt_import_subjects` -> `CBT_Admin_Subjects_Actions::handle_import_subjects()`
- `cbt_download_subject_template` -> `CBT_Admin_Subjects_Actions::handle_download_subject_template()`
- `cbt_download_subject_template_xlsx` -> `CBT_Admin_Subjects_Actions::handle_download_subject_template_xlsx()`

## Exams

### `admin_post`

- `cbt_save_exam` -> `CBT_Admin_Exams_Actions::handle_save_exam()`
- `cbt_delete_exam` -> `CBT_Admin_Exams_Actions::handle_delete_exam()`

### `wp_ajax`

- `cbt_sync_exam_builder_selection` -> `CBT_Admin_Exams_Actions::handle_sync_exam_builder_selection()`
- `cbt_clear_exam_builder_selection` -> `CBT_Admin_Exams_Actions::handle_clear_exam_builder_selection()`
- `cbt_start_exam_save_progress` -> `CBT_Admin_Exams_Actions::handle_start_exam_save_progress()`
- `cbt_continue_exam_save_progress` -> `CBT_Admin_Exams_Actions::handle_continue_exam_save_progress()`

## Tokens

### `admin_post`

- `cbt_save_global_exam_token` -> `CBT_Admin_Tokens_Actions::handle_save_global_exam_token()`

## Setup

### `admin_post`

- `cbt_save_setup_branding` -> `CBT_Admin_Setup_Actions::handle_save_setup_branding()`
- `cbt_save_security_settings` -> `CBT_Admin_Security_Actions::handle_save_security_settings()`
- `cbt_save_setup_security` -> `CBT_Admin_Security_Actions::handle_save_setup_security()` (alias kompatibilitas)
- `cbt_manage_security_logs` -> `CBT_Admin_Security_Actions::handle_manage_security_logs()`

## Developer

### `admin_post`

- `cbt_save_developer_settings` -> `CBT_Admin_Developer_Actions::handle_save_settings()`
- `cbt_check_developer_dev_server` -> `CBT_Admin_Developer_Actions::handle_check_dev_server()`
- `cbt_stop_developer_dev_server` -> `CBT_Admin_Developer_Actions::handle_stop_dev_server()`

## Test Hub

### `admin_post`

- `cbt_save_test_hub_settings` -> `CBT_Admin_Test_Hub_Actions::handle_save_settings()`
- `cbt_run_unit_test_suite` -> `CBT_Admin_Test_Hub_Actions::handle_run_unit_test_suite()`
- `cbt_queue_flow_check_job` -> `CBT_Admin_Test_Hub_Actions::handle_queue_flow_check_job()`

## Cache

### `admin_post`

- `cbt_cache_action` -> `CBT_Admin_Cache_Actions::handle_cache_action()`

## Maintenance

### `admin_post`

- `cbt_reset_database` -> `CBT_Admin_Maintenance_Actions::handle_reset_database()`
- `cbt_generate_test_dataset` -> `CBT_Admin_Maintenance_Actions::handle_generate_test_dataset()`
- `cbt_start_load_test` -> `CBT_Admin_Maintenance_Actions::handle_start_load_test()`
- `cbt_cancel_load_test` -> `CBT_Admin_Maintenance_Actions::handle_cancel_load_test()`
- `cbt_delete_load_test_job` -> `CBT_Admin_Maintenance_Actions::handle_delete_load_test_job()`
- `cbt_clear_load_test_jobs` -> `CBT_Admin_Maintenance_Actions::handle_clear_load_test_jobs()`
- `cbt_download_load_test_artifact` -> `CBT_Admin_Maintenance_Actions::handle_download_load_test_artifact()`
- `cbt_export_load_test_students_json` -> `CBT_Admin_Maintenance_Actions::handle_export_load_test_students_json()`
- `cbt_export_load_test_students_csv` -> `CBT_Admin_Maintenance_Actions::handle_export_load_test_students_csv()`
- `cbt_export_load_test_students_xlsx` -> `CBT_Admin_Maintenance_Actions::handle_export_load_test_students_xlsx()`

### `wp_ajax`

- `cbt_load_test_jobs` -> `CBT_Admin_Maintenance_Actions::handle_load_test_jobs_ajax()`

## Questions

### `admin_post`

- `cbt_save_question` -> `CBT_Admin_Questions_Actions::handle_save_question()`
- `cbt_delete_question` -> `CBT_Admin_Questions_Actions::handle_delete_question()`
- `cbt_bulk_delete_questions` -> `CBT_Admin_Questions_Actions::handle_bulk_delete_questions()`
- `cbt_import_questions` -> `CBT_Admin_Questions_Actions::handle_import_questions()`
- `cbt_download_question_template_word` -> `CBT_Admin_Questions_Actions::handle_download_question_template_word()`
- `cbt_download_question_template_word_mc` -> `CBT_Admin_Questions_Actions::handle_download_question_template_word_mc()`
- `cbt_download_question_template_word_ma` -> `CBT_Admin_Questions_Actions::handle_download_question_template_word_ma()`
- `cbt_download_question_template_word_sa` -> `CBT_Admin_Questions_Actions::handle_download_question_template_word_sa()`
- `cbt_download_question_template_word_tf` -> `CBT_Admin_Questions_Actions::handle_download_question_template_word_tf()`
- `cbt_download_question_template_word_tfm` -> `CBT_Admin_Questions_Actions::handle_download_question_template_word_tfm()`
- `cbt_download_question_template_word_essay` -> `CBT_Admin_Questions_Actions::handle_download_question_template_word_essay()`

## Results

### `admin_post`

- `cbt_grade_essay` -> `CBT_Admin_Results_Actions::handle_grade_essay()`
- `cbt_reset_user_login` -> `CBT_Admin_Results_Actions::handle_reset_user_login()`
- `cbt_reset_attempt` -> `CBT_Admin_Results_Actions::handle_reset_attempt()`
- `cbt_extend_attempt_time` -> `CBT_Admin_Results_Actions::handle_extend_attempt_time()`
- `cbt_force_complete_attempt` -> `CBT_Admin_Results_Actions::handle_force_complete_attempt()`
- `cbt_bulk_reset_attempts` -> `CBT_Admin_Results_Actions::handle_bulk_reset_attempts()`
- `cbt_bulk_force_complete_attempts` -> `CBT_Admin_Results_Actions::handle_bulk_force_complete_attempts()`

## Report Exam

### `admin_post`

- `cbt_export_exam_report_pdf` -> `CBT_Admin_Report_Exam_Actions::handle_export_exam_report_pdf()`
- `cbt_save_exam_incident` -> `CBT_Admin_Report_Exam_Actions::handle_save_exam_incident()`
- `cbt_update_exam_incident` -> `CBT_Admin_Report_Exam_Actions::handle_update_exam_incident()`
- `cbt_delete_exam_incident` -> `CBT_Admin_Report_Exam_Actions::handle_delete_exam_incident()`

## Exam Cards

### `admin_post`

- `cbt_print_exam_cards` -> `CBT_Admin_Exam_Cards_Actions::handle_print_exam_cards()`

## Users

### `admin_post`

- `cbt_import_users` -> `CBT_Admin_Users_Actions::handle_import_users()`
- `cbt_create_user_manual` -> `CBT_Admin_Users_Actions::handle_create_user_manual()`
- `cbt_update_user_manual` -> `CBT_Admin_Users_Actions::handle_update_user_manual()`
- `cbt_delete_user_manual` -> `CBT_Admin_Users_Actions::handle_delete_user_manual()`
- `cbt_bulk_delete_users` -> `CBT_Admin_Users_Actions::handle_bulk_delete_users()`
- `cbt_download_user_template` -> `CBT_Admin_Users_Actions::handle_download_user_template()`
- `cbt_download_user_template_xlsx` -> `CBT_Admin_Users_Actions::handle_download_user_template_xlsx()`

## Appendix B — Capability / Slug Map

Bagian ini memampatkan relasi menu, slug, dan capability dalam format yang bisa dipindai cepat.

| Modul | Label | Slug | Capability |
| --- | --- | --- | --- |
| Introduction | `Introduction` | `cbt-introduction` | `cbt_manage_exams` |
| Exams | `CBT Exams` | `cbt-exams` | `cbt_manage_exams` |
| Setup | `CBT Branding` | `cbt-setup` | `cbt_manage_exams` |
| Security | `CBT Security` | `cbt-security` | `cbt_manage_exams` |
| Subjects | `CBT Subjects` | `cbt-subjects` | `manage_options` |
| Users | `CBT Users` | `cbt-user-import` | `manage_options` |
| Questions | `CBT Questions` | `cbt-question-bank` | `cbt_manage_questions` |
| Tokens | `CBT Tokens` | `cbt-tokens` | `cbt_manage_exams` |
| Exam Cards | `CBT Exam Cards` | `cbt-exam-cards` | `cbt_manage_users` |
| Results | `CBT Results` | `cbt-results` | `cbt_view_results` |
| Analytics | `CBT Analytics` | `cbt-analytics` | `cbt_view_results` |
| Report Exam | `CBT Report Exam` | `cbt-report-exam` | `cbt_view_results` |
| Test Hub | `CBT Test Hub` | `cbt-test-hub` | `manage_options` |
| Cache | `CBT Cache` | `cbt-cache` | `manage_options` |
| Maintenance | `CBT Maintenance` | `cbt-maintenance` | `manage_options` |
| Developer | `CBT Developer` | `cbt-developer` | `manage_options` |

## Appendix C — File Index Lengkap

Bagian ini memberi indeks seluruh file yang relevan di `admin/` dan `admin/views/`.

## A. Bootstrap & Infrastruktur

| File | Tipe | Catatan |
| --- | --- | --- |
| `class-cbt-admin.php` | Bootstrap | Index hook admin dan AJAX |
| `class-cbt-admin-menu.php` | Menu | Registrasi menu/submenu dan redirect halaman lama |
| `class-cbt-admin-branding-settings.php` | Settings | Shared branding settings |

## B. PHP Modules

| File | Modul | Kategori |
| --- | --- | --- |
| `class-cbt-admin-introduction-page.php` | Introduction | Page |
| `class-cbt-admin-exams-page.php` | Exams | Page |
| `class-cbt-admin-exams-service.php` | Exams | Service |
| `class-cbt-admin-exams-actions.php` | Exams | Actions |
| `class-cbt-admin-exams-helper.php` | Exams | Helper |
| `class-cbt-admin-setup-page.php` | Setup | Page |
| `class-cbt-admin-setup-service.php` | Setup | Service |
| `class-cbt-admin-setup-actions.php` | Setup | Actions |
| `class-cbt-admin-subjects-page.php` | Subjects | Page |
| `class-cbt-admin-subjects-service.php` | Subjects | Service |
| `class-cbt-admin-subjects-actions.php` | Subjects | Actions |
| `class-cbt-admin-users-page.php` | Users | Page |
| `class-cbt-admin-users-service.php` | Users | Service |
| `class-cbt-admin-users-actions.php` | Users | Actions |
| `class-cbt-admin-questions-page.php` | Questions | Page |
| `class-cbt-admin-questions-service.php` | Questions | Service |
| `class-cbt-admin-questions-actions.php` | Questions | Actions |
| `class-cbt-admin-questions-helper.php` | Questions | Helper |
| `class-cbt-admin-questions-import-helper.php` | Questions | Import Helper |
| `class-cbt-admin-questions-sync-helper.php` | Questions | Sync Helper |
| `class-cbt-admin-tokens-page.php` | Tokens | Page |
| `class-cbt-admin-tokens-service.php` | Tokens | Service |
| `class-cbt-admin-tokens-actions.php` | Tokens | Actions |
| `class-cbt-admin-exam-cards-page.php` | Exam Cards | Page |
| `class-cbt-admin-exam-cards-service.php` | Exam Cards | Service |
| `class-cbt-admin-exam-cards-actions.php` | Exam Cards | Actions |
| `class-cbt-admin-results-page.php` | Results | Page |
| `class-cbt-admin-results-service.php` | Results | Service |
| `class-cbt-admin-results-actions.php` | Results | Actions |
| `class-cbt-admin-results-helper.php` | Results | Helper |
| `class-cbt-admin-analytics-page.php` | Analytics | Page |
| `class-cbt-admin-analytics-service.php` | Analytics | Service |
| `class-cbt-admin-report-exam-page.php` | Report Exam | Page |
| `class-cbt-admin-report-exam-service.php` | Report Exam | Service |
| `class-cbt-admin-report-exam-actions.php` | Report Exam | Actions |
| `class-cbt-admin-test-hub-page.php` | Test Hub | Page |
| `class-cbt-admin-test-hub-service.php` | Test Hub | Service |
| `class-cbt-admin-test-hub-actions.php` | Test Hub | Actions |
| `class-cbt-admin-cache-page.php` | Cache | Page |
| `class-cbt-admin-cache-service.php` | Cache | Service |
| `class-cbt-admin-cache-actions.php` | Cache | Actions |
| `class-cbt-admin-maintenance-page.php` | Maintenance | Page |
| `class-cbt-admin-maintenance-service.php` | Maintenance | Service |
| `class-cbt-admin-maintenance-actions.php` | Maintenance | Actions |
| `class-cbt-admin-developer-page.php` | Developer | Page |
| `class-cbt-admin-developer-service.php` | Developer | Service |
| `class-cbt-admin-developer-actions.php` | Developer | Actions |

## C. Views

| File | Modul | Kategori View |
| --- | --- | --- |
| `views/introduction/page.php` | Introduction | Page |
| `views/exams/page.php` | Exams | Page |
| `views/exams/preview.php` | Exams | Preview |
| `views/setup/page.php` | Setup | Page |
| `views/subjects/page.php` | Subjects | Page |
| `views/users/page.php` | Users | Page |
| `views/questions/page.php` | Questions | Page |
| `views/tokens/page.php` | Tokens | Page |
| `views/exam-cards/page.php` | Exam Cards | Page |
| `views/exam-cards/print.php` | Exam Cards | Print |
| `views/results/page.php` | Results | Page |
| `views/analytics/page.php` | Analytics | Page |
| `views/report-exam/page.php` | Report Exam | Page |
| `views/report-exam/print.php` | Report Exam | Print |
| `views/test-hub/page.php` | Test Hub | Page |
| `views/cache/page.php` | Cache | Page |
| `views/maintenance/page.php` | Maintenance | Page |
| `views/maintenance/partials/load-test-jobs.php` | Maintenance | Partial |
| `views/developer/page.php` | Developer | Page |

## Appendix D — Largest Files & Hotspot Map

Bagian ini memberi panduan cepat area yang paling mahal untuk dipahami atau diubah.

## Top PHP Hotspots

| Ranking | File | Line | Catatan |
| ---: | --- | ---: | --- |
| 1 | `class-cbt-admin-maintenance-service.php` | `5881` | Modul maintenance adalah hotspot terbesar |
| 2 | `class-cbt-admin-analytics-service.php` | `3315` | Analitik punya context/service sangat besar |
| 3 | `class-cbt-admin-exams-service.php` | `3271` | Builder exam dan state logic |
| 4 | `class-cbt-admin-test-hub-service.php` | `2666` | Checklist/runners/queue QA |
| 5 | `class-cbt-admin-questions-import-helper.php` | `2095` | Import/template pertanyaan |
| 6 | `class-cbt-admin-users-service.php` | `1695` | CRUD + import users |
| 7 | `class-cbt-admin-questions-service.php` | `1579` | Context & CRUD bank soal |
| 8 | `class-cbt-admin-questions-helper.php` | `1374` | Utility tipe soal |
| 9 | `class-cbt-admin-report-exam-service.php` | `1322` | Report/incident/service |
| 10 | `class-cbt-admin-results-service.php` | `1290` | Actions hasil/attempt |

## Top View Hotspots

| Ranking | File | Line | Catatan |
| ---: | --- | ---: | --- |
| 1 | `views/exams/page.php` | `5018` | View terbesar di admin |
| 2 | `views/developer/page.php` | `3349` | Diagnostics view sangat padat |
| 3 | `views/results/page.php` | `3151` | Results + review attempt |
| 4 | `views/questions/page.php` | `2817` | Edit/import bank soal |
| 5 | `views/maintenance/page.php` | `2730` | Reset/seed/load-test UI |
| 6 | `views/setup/page.php` | `2426` | Branding + security + log UI |
| 7 | `views/analytics/page.php` | `2172` | Insight analytics |
| 8 | `views/report-exam/page.php` | `1608` | Report & incident UI |
| 9 | `views/test-hub/page.php` | `1531` | Checklist/runners UI |
| 10 | `views/users/page.php` | `1358` | CRUD/import/filter user UI |

## Apa Artinya Untuk Contributor

- perubahan besar di `maintenance`, `exams`, `questions`, `results`, `analytics`, `developer`, atau `test-hub` hampir selalu perlu review lebih hati-hati
- kalau sebuah bug terasa “UI only”, tetap cek service terkait, karena banyak view admin sangat tebal tetapi context-nya juga kompleks

## Appendix E — Where To Edit

Bagian ini adalah peta tugas-ke-file.

## 1. Menambah halaman admin baru

Mulai dari:

- `class-cbt-admin-menu.php`
- `class-cbt-admin.php`
- buat pasangan `page/service/actions/views` baru bila diperlukan

Urutan kerja:

1. tambahkan submenu di menu registry
2. buat page class
3. buat service bila modul punya logic/context sendiri
4. buat view
5. kalau ada form submit, daftarkan hook di bootstrap admin

## 2. Menambah `admin_post` baru

Mulai dari:

- `class-cbt-admin.php`

Lalu:

- tentukan modul action class yang menerima request
- buat method `handle_*()` di `actions` atau `service`
- pastikan capability guard dan redirect/output sesuai flow modul

## 3. Menambah `wp_ajax` baru

Mulai dari:

- `class-cbt-admin.php`

Pilih modul yang paling dekat dengan flow AJAX-nya. Di repo ini contoh AJAX tipikal ada di:

- Exams
- Maintenance

## 4. Mengubah capability sebuah halaman

Mulai dari:

- `class-cbt-admin-menu.php`
- file `page` modul terkait
- file `service/actions` bila ada guard tambahan

Periksa dua lapis:

1. capability di menu
2. capability di render/action/service

## 5. Mengubah query dan payload yang dipakai halaman

Mulai dari:

- `class-cbt-admin-<module>-service.php`

Cari:

- `build_page_context(...)`

Kalau view tidak menunjukkan data yang Anda harapkan, kemungkinan data itu belum dibawa oleh context builder.

## 6. Mengubah tampilan halaman admin

Mulai dari:

- `admin/views/<module>/page.php`

Tetapi selalu cek dulu:

- service/context builder modul

agar Anda tahu variabel apa yang memang tersedia di view.

## 7. Mengubah preview atau print output

Mulai dari view non-standar berikut:

- `views/exams/preview.php`
- `views/exam-cards/print.php`
- `views/report-exam/print.php`

Lalu cek builder context-nya di service/action yang memanggil view tersebut.

## 8. Mengubah import pipeline

Mulai dari modul yang memang punya import flow besar:

- Subjects:
  `class-cbt-admin-subjects-service.php`
- Users:
  `class-cbt-admin-users-service.php`
- Questions:
  `class-cbt-admin-questions-import-helper.php`

## 9. Mengubah bank soal atau tipe soal

Mulai dari:

- `class-cbt-admin-questions-service.php`
- `class-cbt-admin-questions-helper.php`
- `class-cbt-admin-questions-import-helper.php`
- `class-cbt-admin-questions-sync-helper.php`

Urutan exact file bergantung apakah perubahan Anda menyentuh:

- CRUD
- normalisasi tipe
- import
- sinkronisasi source question

## 10. Mengubah builder exam

Mulai dari:

- `class-cbt-admin-exams-service.php`
- `class-cbt-admin-exams-actions.php`
- `views/exams/page.php`

Kalau perubahan menyentuh preview:

- cek juga `views/exams/preview.php`

## 11. Mengubah hasil, grading, atau operasi attempt

Mulai dari:

- `class-cbt-admin-results-service.php`
- `class-cbt-admin-results-actions.php`
- `class-cbt-admin-results-helper.php`
- `views/results/page.php`

## 12. Mengubah report exam atau incident report

Mulai dari:

- `class-cbt-admin-report-exam-service.php`
- `class-cbt-admin-report-exam-actions.php`
- `views/report-exam/page.php`
- `views/report-exam/print.php`

## 13. Mengubah tooling QA / Test Hub

Mulai dari:

- `class-cbt-admin-test-hub-service.php`
- `class-cbt-admin-test-hub-actions.php`
- `views/test-hub/page.php`

## 14. Mengubah cache/Redis admin tooling

Mulai dari:

- `class-cbt-admin-cache-service.php`
- `class-cbt-admin-cache-actions.php`
- `views/cache/page.php`

## 15. Mengubah seed data, reset, atau load test

Mulai dari:

- `class-cbt-admin-maintenance-service.php`
- `class-cbt-admin-maintenance-actions.php`
- `class-cbt-admin-maintenance-page.php`
- `views/maintenance/page.php`
- `views/maintenance/partials/load-test-jobs.php`

## 16. Mengubah asset source / dev server flow

Mulai dari:

- `class-cbt-admin-developer-service.php`
- `class-cbt-admin-developer-actions.php`
- `views/developer/page.php`

## Catatan Penutup

Folder `admin/` di plugin ini bukan sekadar kumpulan halaman admin, tetapi subsystem lengkap dengan:

- request map sendiri
- capability map sendiri
- context builders besar
- write path yang jelas
- view layer yang juga cukup kompleks

Kalau Anda menjaga handbook ini tetap sejalan dengan perubahan di folder `admin/`, waktu onboarding contributor baru dan biaya navigasi bug/refactor akan turun drastis. Untuk repo sebesar ini, dokumentasi internal bukan pelengkap; ia sudah menjadi bagian dari infrastruktur pemeliharaan.
