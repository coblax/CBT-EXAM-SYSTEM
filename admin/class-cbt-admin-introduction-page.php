<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Introduction_Page
{
    public static function can_view(): bool
    {
        return current_user_can('cbt_manage_exams');
    }

    public static function render(): void
    {
        if (!self::can_view()) {
            wp_die('Unauthorized');
        }

        $context = self::build_page_context();
        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/introduction/page.php';
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_page_context(): array
    {
        $feature_groups = self::get_feature_groups();
        $workflow_steps = self::get_workflow_steps();
        $workflow_guidance = self::get_workflow_guidance();
        $quick_links = self::build_quick_links($feature_groups);
        $quick_link_available_count = count(array_filter(
            $quick_links,
            static function (array $item): bool {
                return !empty($item['can_open']);
            }
        ));

        $section_nav = [
            ['id' => 'apa-itu', 'label' => 'Apa Itu'],
            ['id' => 'alur-pemakaian', 'label' => 'Alur'],
            ['id' => 'fitur-menu', 'label' => 'Fitur'],
            ['id' => 'workflow', 'label' => 'Workflow'],
            ['id' => 'quick-links', 'label' => 'Quick Links'],
        ];

        $overview_cards = [
            [
                'title' => 'Performa & Keandalan Tinggi',
                'description' => 'Mengusung arsitektur modern (Redis In-Memory, Adaptive Load, Auto-Heal Queue) untuk memastikan CBT tetap responsif. Dirancang mampu menahan lonjakan ribuan akses serentak secara native tanpa bottleneck.',
            ],
            [
                'title' => 'Alur Kerja Komprehensif',
                'description' => 'Menuntun user dari setup identitas sekolah, import peserta massif, preflight warming ujian, observabilitas saat ujian live, hingga pelaporan dan analisis butir soal (item analysis).',
            ],
            [
                'title' => 'Manajemen Konteks Peran',
                'description' => 'Struktur menu yang terisolir secara fungsional. Admin teknis mengelola cache & sistem, pengawas memantau live metrics CBT Exams, sedangkan penilai fokus pada Results & Analytics.',
            ],
        ];

        $hero_metrics = [
            ['value' => 'Redis', 'label' => 'Cache Engine'],
            ['value' => (string) count($feature_groups), 'label' => 'Modul Utama'],
            ['value' => (string) count($quick_links), 'label' => 'Area Kontrol'],
            ['value' => (string) $quick_link_available_count, 'label' => 'Akses Tersedia'],
        ];

        return compact(
            'feature_groups',
            'hero_metrics',
            'overview_cards',
            'quick_link_available_count',
            'quick_links',
            'section_nav',
            'workflow_guidance',
            'workflow_steps'
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_feature_groups(): array
    {
        return [
            [
                'id' => 'persiapan',
                'title' => 'Persiapan',
                'description' => 'Pilar utama sebelum pelaksanaan. Menyiapkan master data subjek, bank soal, dan profil operasional kepesertaan.',
                'items' => [
                    [
                        'slug' => 'cbt-setup',
                        'label' => 'CBT Branding',
                        'capability' => 'cbt_manage_exams',
                        'summary' => 'Konfigurasi identitas visual (logo, nama instansi) dan preferensi global antarmuka aplikasi peserta.',
                        'when_to_use' => 'Diawal rollout, atau pada pergantian jenjang/tahun akademik baru.',
                        'output' => 'Brand guide persisten pada antarmuka frontend peserta.',
                    ],
                    [
                        'slug' => 'cbt-security',
                        'label' => 'CBT Security',
                        'capability' => 'cbt_manage_exams',
                        'summary' => 'Pusat kontrol keamanan ujian. Mengatur flag Must Watch, meninjau anomaly, dan mengatur tingkat sensitivitas sesi (Locking & Refresh limiter).',
                        'when_to_use' => 'Ketika butuh perlindungan anti-kecurangan, lockdown exam, atau patroli aktivitas.',
                        'output' => 'Aturan pertahanan & security log yang komprehensif bagi Proktor.',
                    ],
                    [
                        'slug' => 'cbt-subjects',
                        'label' => 'CBT Subjects',
                        'capability' => 'manage_options',
                        'summary' => 'Direktori mata pelajaran/kursus yang menaungi koleksi soal serta materi ujian spesifik.',
                        'when_to_use' => 'Dipakai untuk taksonomi / struktur kurikulum soal baru.',
                        'output' => 'Klasifikasi mapel yang tertata rapi untuk integrasi pembuatan exam.',
                    ],
                    [
                        'slug' => 'cbt-user-import',
                        'label' => 'CBT Users',
                        'capability' => 'manage_options',
                        'summary' => 'Upload massal, rotasi password skala besar, dan standarisasi profile peserta (Kelas, Ruangan).',
                        'when_to_use' => 'Tahapan onboarding per semester, rotasi tingkat, & pembagian shift ruangan siswa.',
                        'output' => 'Kesiapan data otentikasi login para murid secara seragam.',
                    ],
                    [
                        'slug' => 'cbt-question-bank',
                        'label' => 'CBT Questions',
                        'capability' => 'cbt_manage_questions',
                        'summary' => 'Gudang butir evaluasi. Mendukung multiple choice, essay, matriks benar-salah, serta kaya format (persamaan matematis, gambar, integrasi rich-text).',
                        'when_to_use' => 'Tempat dewan guru/kontributor menyuplai, meninjau, dan merevisi isi bank soal harian.',
                        'output' => 'Pool pertanyaan hidup siap di-inject ke berbagai paket soal.',
                    ],
                ],
            ],
            [
                'id' => 'operasional',
                'title' => 'Operasional Ujian',
                'description' => 'Tahapan eskalasi. Melibatkan perakitan (bundling), penjabaran beban kerja, serta inisialisasi gate masuk ujian bagi para peserta.',
                'items' => [
                    [
                        'slug' => 'cbt-exams',
                        'label' => 'CBT Exams (Pusat Komando)',
                        'capability' => 'cbt_manage_exams',
                        'summary' => 'Dasbor interaktif operasional (Start-Attempt Gate, Auto-Warm, Adaptive Load Monitor). Menyusun ujian final dari bank soal dengan durasi/aturan konklusif.',
                        'when_to_use' => 'Pusat atensi selama ujian hidup (live). Menyusun exam dan mengontrol snapshot RAM per ujian.',
                        'output' => 'Penyajian sesi (exam-contract) ke siswa diiringi metrics latensi server waktu nyata (real-time).',
                    ],
                    [
                        'slug' => 'cbt-tokens',
                        'label' => 'CBT Tokens',
                        'capability' => 'cbt_manage_exams',
                        'summary' => 'Mekanisme Dynamic Password (Token sesi) berlapis dengan timer yang kadaluwarsa sebagai filter rilis exam.',
                        'when_to_use' => 'Tepat detik-detik saat sesi/ruangan akan digulirkan (pembukaan gerbang via proyektor).',
                        'output' => 'Alfanumerik sesaat sebagai kunci buka gembok paket soal.',
                    ],
                    [
                        'slug' => 'cbt-exam-cards',
                        'label' => 'CBT Administrative Documents',
                        'capability' => 'cbt_manage_users',
                        'summary' => 'Generasi bulk dokumen administrasi pra-pelaksanaan seperti kartu peserta, nomor meja, dan atribut cetak lapangan.',
                        'when_to_use' => 'Sebelum pelaksanaan untuk menyiapkan dokumen fisik dan distribusi tata usaha.',
                        'output' => 'PDF print layout untuk kartu peserta, nomor meja, label, dan dokumen pendukung.',
                    ],
                ],
            ],
            [
                'id' => 'monitoring-hasil',
                'title' => 'Monitoring & Evaluasi Data',
                'description' => 'Review pasca-pelaksanaan atau pantau arus pengerjaan. Analisa data kuantitatif mutlak hasil skor asesi.',
                'items' => [
                    [
                        'slug' => 'cbt-results',
                        'label' => 'CBT Results',
                        'capability' => 'cbt_view_results',
                        'summary' => 'Pemantauan persentase pengerjaan secara langsung dan rekaman detail jejak jawaban, per-siswa. Fasilitas Reset dan Skor Manual Essay.',
                        'when_to_use' => 'Selama ujian untuk pantau kendala siswa tersangkut, Pasca-ujian buat koreksi essai/baca nilai murni.',
                        'output' => 'Daftar nilai bulat serta penyelesaian keluhan sesi terjeda murid individual.',
                    ],
                    [
                        'slug' => 'cbt-analytics',
                        'label' => 'CBT Analytics (Item Analysis)',
                        'capability' => 'cbt_view_results',
                        'summary' => 'Penyelaman komputasi statistik (Distribusi Normal, Korelasi Biserial, Angka Kesukaran Butir P). Evaluasi reliabilitas dari sekumpulan tes dan soal.',
                        'when_to_use' => 'Pasca-pelaksanaan sebagai landasan rapat kurikulum bedah mutu bank soal evaluatif.',
                        'output' => 'Grafik komparasi mutu, outlier peserta, distribusi skor dan diagnosa instrumen ujian.',
                    ],
                    [
                        'slug' => 'cbt-report-exam',
                        'label' => 'CBT Report Exam',
                        'capability' => 'cbt_view_results',
                        'summary' => 'Ekspor format resmi yang memuat nilai kelas gabungan ke excel/tabel konsolidasi untuk sistem rapot sekolah.',
                        'when_to_use' => 'Langkah penghujung, final state rekapitulasi penilaian.',
                        'output' => 'Lampiran dan dokumen komprehensif penilaian final per kelas.',
                    ],
                ],
            ],
            [
                'id' => 'system-tools',
                'title' => 'Sistem/Admin Observability Tools',
                'description' => 'Konsol arsitektural infrastruktur performa. Jaga denyut server tetap terprediksi ditengah badai trafik.',
                'items' => [
                    [
                        'slug' => 'cbt-cache',
                        'label' => 'CBT Cache & Redis Engine',
                        'capability' => 'manage_options',
                        'summary' => 'Dashboard topologi Redis in-memory. Mengelola Snapshots, Auto-Heal queue depth, dan metrik Hit/Miss rate caching operasional.',
                        'when_to_use' => 'Diagnosa performansi harian, verifikasi status koneksi RAM-DB, penanganan anomali load server (Flushing/Re-sync).',
                        'output' => 'Tampilan X-Ray keadaan volatilitas memori CBT yang sedang bekerja meredam CPU database.',
                    ],
                    [
                        'slug' => 'cbt-maintenance',
                        'label' => 'CBT Maintenance',
                        'capability' => 'manage_options',
                        'summary' => 'Ruang sterilisasi & perbaikan sistem. Cleanup logs lawas, penghapusan orphans, dan sync master data inkonsisten.',
                        'when_to_use' => 'Operasi rutin pasca-semester, mengembalikan storage capacity, membuang payload obsolete/usang.',
                        'output' => 'Kestabilan database, kesehatan cron job, index data yang ringan dan pulih.',
                    ],
                    [
                        'slug' => 'cbt-test-hub',
                        'label' => 'CBT Test Hub',
                        'capability' => 'manage_options',
                        'summary' => 'Virtual Sandbox. Generator simulasi attempt palsu, inspektor API, dan panel eksperimen alur sebelum live ke produksi.',
                        'when_to_use' => 'Sebelum momen ujian masif sebagai sarana kalibrasi fitur dan verifikasi kesiapan komponen server.',
                        'output' => 'Fakta validitas fitur berjalan tanpa mendisrupsi records hasil rill murid.',
                    ],
                    [
                        'slug' => 'cbt-update',
                        'label' => 'CBT Engine Update',
                        'capability' => 'manage_options',
                        'summary' => 'Pusat kendali eksekusi migrasi skema tabel, integrasi patch minor struktur CBT, dan patch kompabilitas fungsionalitas.',
                        'when_to_use' => 'Wajib dikunjungi tepat paska rilis update plugin agar database kompatibel serempak.',
                        'output' => 'Migrasi engine aman sentosa bebas corupt data.',
                    ],
                    [
                        'slug' => 'cbt-developer',
                        'label' => 'CBT Developer',
                        'capability' => 'manage_options',
                        'summary' => 'Debugging tools mutakhir: Profiler, Query log, Payload introspection bagi sysadmin di sisi development.',
                        'when_to_use' => 'Saat environment staging, debug bottleneck, telusuri log errors developer-level.',
                        'output' => 'Verbose log teknis & kontrol eksperimental.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function get_workflow_steps(): array
    {
        return [
            [
                'step' => '1',
                'label' => 'Setup & Security',
                'summary' => 'Tentukan aturan main, profil keamanan, dan branding sistem. Tetapkan boundary pengontrol login multidevice.',
            ],
            [
                'step' => '2',
                'label' => 'Subjects & Users',
                'summary' => 'Mapping pondasi dasar berupa klasifikasi kurikulum, lalu injeksikan massa users siap ujian lengkap dengan lokus ruangnya.',
            ],
            [
                'step' => '3',
                'label' => 'Question Bank',
                'summary' => 'Authoring: Input master data evaluasi yang terstruktur dengan presenntasi multimedia siap pakai (equation/audio/image).',
            ],
            [
                'step' => '4',
                'label' => 'Exams (Preflight)',
                'summary' => 'Assembly test. Bangun parameter durasi, lalu server melakukan Preflight Warming (Auto-Heal) via Redis.',
            ],
            [
                'step' => '5',
                'label' => 'Token & Cards',
                'summary' => 'Pecah tiket ujian administrasi fisik dan gembok ujian lapis akhir menggunakan token dinamis waktu-rill (real-time).',
            ],
            [
                'step' => '6',
                'label' => 'Live Ops Monitoring',
                'summary' => 'Pemantauan konfiden lewat panel Exams (Adaptive Load heartbeat) dan Results (Akurasi penyelesaian tiap proktor).',
            ],
            [
                'step' => '7',
                'label' => 'Analytics & QA',
                'summary' => 'Biarkan Item Analysis bekerja pasca pengerjaan menyelidiki daya beda, index kesukaran, dan konsistensi soal.',
            ],
            [
                'step' => '8',
                'label' => 'Report & Archieve',
                'summary' => 'Rampungkan ke format spreadsheet resmi laporan. Backup data jika butuh dan bersihkan session dari menu Maintenance.',
            ],
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function get_workflow_guidance(): array
    {
        return [
            [
                'title' => 'Fase 1: Infrastruktur Database',
                'description' => 'Diawali merapikan CBT Subjects dan Users untuk mengunci kerangka sasaran peseta ujian kelak. Data Master harus stabil.',
            ],
            [
                'title' => 'Fase 2: Fabrikasi & Quality Assurance',
                'description' => 'Konten kreator (Guru) melengkapi Question Bank. Pastikan kualitas butir baik. Lakukan dry-run exam dengan CBT Test Hub bila diperlukan.',
            ],
            [
                'title' => 'Fase 3: Deployment & Observability',
                'description' => 'Set CBT Exams sebagai on-stage. Biarkan Redis mengambil alih Start-Queue throttling. Gunakan CBT Security guna menjaga integritas pengerjaan terlarang (Log Aktivitas Anomali).',
            ],
            [
                'title' => 'Fase 4: Pasca Produksi',
                'description' => 'CBT Analytics memberikan porsi terbesar bahan evaluatif kurikulum. Selanjutnya ekspor dengan CBT Report. Lakukan Maintenance untuk rotasi ujian baru.',
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $feature_groups
     * @return array<int,array<string,mixed>>
     */
    private static function build_quick_links(array $feature_groups): array
    {
        $quick_links = [];

        foreach ($feature_groups as $group) {
            foreach ((array) ($group['items'] ?? []) as $item) {
                $capability = (string) ($item['capability'] ?? '');
                $access_state = self::get_access_state($capability);

                $quick_links[] = [
                    'group_title' => (string) ($group['title'] ?? 'Menu'),
                    'label' => (string) ($item['label'] ?? 'Menu'),
                    'summary' => (string) ($item['summary'] ?? ''),
                    'url' => !empty($access_state['can_open']) ? admin_url('admin.php?page=' . (string) ($item['slug'] ?? '')) : '',
                    'can_open' => !empty($access_state['can_open']),
                    'access_label' => (string) ($access_state['label'] ?? 'Akses terbatas'),
                    'access_tone' => (string) ($access_state['tone'] ?? 'restricted'),
                    'access_hint' => (string) ($access_state['hint'] ?? ''),
                ];
            }
        }

        return $quick_links;
    }

    /**
     * @return array{can_open:bool,label:string,tone:string,hint:string}
     */
    private static function get_access_state(string $capability): array
    {
        if ($capability !== '' && current_user_can($capability)) {
            return [
                'can_open' => true,
                'label' => 'Tersedia',
                'tone' => 'available',
                'hint' => 'Menu ini bisa dibuka dari akun Anda.',
            ];
        }

        switch ($capability) {
            case 'manage_options':
                return [
                    'can_open' => false,
                    'label' => 'Admin only',
                    'tone' => 'admin',
                    'hint' => 'Menu ini khusus admin sistem atau pengelola utama plugin.',
                ];

            case 'cbt_view_results':
                return [
                    'can_open' => false,
                    'label' => 'Perlu akses hasil',
                    'tone' => 'restricted',
                    'hint' => 'Akun ini belum punya izin untuk membuka menu hasil atau pelaporan.',
                ];

            case 'cbt_manage_questions':
                return [
                    'can_open' => false,
                    'label' => 'Perlu akses bank soal',
                    'tone' => 'restricted',
                    'hint' => 'Akun ini belum punya izin untuk mengelola bank soal.',
                ];

            case 'cbt_manage_users':
                return [
                    'can_open' => false,
                    'label' => 'Perlu akses peserta',
                    'tone' => 'restricted',
                    'hint' => 'Akun ini belum punya izin untuk mengelola data peserta atau dokumen administrasi.',
                ];

            default:
                return [
                    'can_open' => false,
                    'label' => 'Akses terbatas',
                    'tone' => 'restricted',
                    'hint' => 'Menu ini memerlukan izin tambahan.',
                ];
        }
    }
}
