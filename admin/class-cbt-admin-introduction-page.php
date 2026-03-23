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
                'title' => 'Tujuan Plugin',
                'description' => 'CBT Exam System membantu sekolah mengelola ujian berbasis komputer dari tahap persiapan data, perakitan exam, pembagian token, sampai pembacaan hasil dan analitik.',
            ],
            [
                'title' => 'Siapa yang Memakai',
                'description' => 'Operator sekolah, admin, guru mapel, proktor, dan pengawas dapat memakai menu yang berbeda sesuai izin masing-masing untuk menjaga alur kerja tetap rapi.',
            ],
            [
                'title' => 'Hasil Akhir',
                'description' => 'Data ujian tersusun, peserta bisa login dan mengerjakan exam, panitia dapat memantau hasil, lalu sekolah memperoleh rekap dan insight untuk evaluasi.',
            ],
        ];

        $hero_metrics = [
            ['value' => (string) count($workflow_steps), 'label' => 'langkah utama'],
            ['value' => (string) count($feature_groups), 'label' => 'kelompok fitur'],
            ['value' => (string) count($quick_links), 'label' => 'menu dipetakan'],
            ['value' => (string) $quick_link_available_count, 'label' => 'quick link tersedia'],
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
                'description' => 'Menu yang dipakai untuk menyiapkan fondasi sistem sebelum exam dibagikan ke siswa.',
                'items' => [
                    [
                        'slug' => 'cbt-setup',
                        'label' => 'CBT Setup',
                        'capability' => 'cbt_manage_exams',
                        'summary' => 'Mengatur identitas sistem, branding, dan pengaturan inti operasional plugin.',
                        'when_to_use' => 'Dipakai saat awal implementasi plugin, saat mengganti branding, atau saat meninjau pengaturan pengawasan dan keamanan.',
                        'output' => 'Konfigurasi plugin siap dipakai sebagai dasar seluruh proses CBT.',
                    ],
                    [
                        'slug' => 'cbt-subjects',
                        'label' => 'CBT Subjects',
                        'capability' => 'manage_options',
                        'summary' => 'Mengelola mapel atau subject yang menjadi pengelompokan utama exam dan soal.',
                        'when_to_use' => 'Dipakai sebelum membuat exam atau saat perlu merapikan struktur mapel.',
                        'output' => 'Daftar subject/mapel yang konsisten untuk exam dan bank soal.',
                    ],
                    [
                        'slug' => 'cbt-user-import',
                        'label' => 'CBT Users',
                        'capability' => 'manage_options',
                        'summary' => 'Mengimpor dan mengelola akun peserta beserta metadata seperti kelas, ruang, dan identitas lainnya.',
                        'when_to_use' => 'Dipakai saat awal semester, saat ada siswa baru, atau saat perlu pembaruan data peserta.',
                        'output' => 'Data user peserta yang siap dipakai untuk exam, kartu ujian, dan pelaporan.',
                    ],
                    [
                        'slug' => 'cbt-question-bank',
                        'label' => 'CBT Questions',
                        'capability' => 'cbt_manage_questions',
                        'summary' => 'Mengelola bank soal sebagai sumber utama butir soal yang akan dipakai di exam.',
                        'when_to_use' => 'Dipakai saat menyusun, mengimpor, dan merawat soal lintas exam.',
                        'output' => 'Bank soal yang siap dipilih dan disalin ke exam siswa.',
                    ],
                ],
            ],
            [
                'id' => 'operasional',
                'title' => 'Operasional Ujian',
                'description' => 'Menu yang dipakai saat menyiapkan pelaksanaan exam untuk peserta.',
                'items' => [
                    [
                        'slug' => 'cbt-exams',
                        'label' => 'CBT Exams',
                        'capability' => 'cbt_manage_exams',
                        'summary' => 'Membuat exam, mengatur jadwal, memilih soal, dan menentukan aturan pengerjaan untuk peserta.',
                        'when_to_use' => 'Dipakai setelah subject, users, dan question bank siap untuk merakit exam final.',
                        'output' => 'Exam siap tayang lengkap dengan soal, durasi, dan pengaturan pesertanya.',
                    ],
                    [
                        'slug' => 'cbt-tokens',
                        'label' => 'CBT Tokens',
                        'capability' => 'cbt_manage_exams',
                        'summary' => 'Mengatur token agar akses masuk exam lebih terkontrol saat hari pelaksanaan.',
                        'when_to_use' => 'Dipakai menjelang ujian atau saat sesi exam membutuhkan token terpisah.',
                        'output' => 'Token aktif yang bisa diumumkan ke peserta sesuai sesi ujian.',
                    ],
                    [
                        'slug' => 'cbt-exam-cards',
                        'label' => 'CBT Exam Cards',
                        'capability' => 'cbt_manage_users',
                        'summary' => 'Mencetak kartu ujian atau ringkasan identitas peserta untuk kebutuhan administrasi lapangan.',
                        'when_to_use' => 'Dipakai sebelum hari ujian ketika panitia perlu membagikan kartu atau daftar identitas peserta.',
                        'output' => 'Kartu atau print-out peserta yang siap dipakai di ruang ujian.',
                    ],
                ],
            ],
            [
                'id' => 'monitoring-hasil',
                'title' => 'Monitoring & Hasil',
                'description' => 'Menu untuk memantau pelaksanaan, memeriksa jawaban, dan membaca performa exam setelah pengerjaan.',
                'items' => [
                    [
                        'slug' => 'cbt-results',
                        'label' => 'CBT Results',
                        'capability' => 'cbt_view_results',
                        'summary' => 'Melihat hasil attempt, memeriksa jawaban essay, mengoreksi, dan melakukan tindakan operasional pada attempt tertentu.',
                        'when_to_use' => 'Dipakai saat ujian berjalan untuk monitoring attempt, dan setelah ujian untuk review atau penanganan kasus.',
                        'output' => 'Data hasil per siswa dan per attempt yang siap diverifikasi atau ditindak lanjuti.',
                    ],
                    [
                        'slug' => 'cbt-analytics',
                        'label' => 'CBT Analytics',
                        'capability' => 'cbt_view_results',
                        'summary' => 'Membaca performa exam, item analysis, distribusi nilai, dan pola hasil siswa secara lebih mendalam.',
                        'when_to_use' => 'Dipakai setelah data hasil cukup stabil dan sekolah ingin melihat insight kualitas exam dan performa peserta.',
                        'output' => 'Insight analitik untuk evaluasi soal, exam, dan hasil belajar.',
                    ],
                    [
                        'slug' => 'cbt-report-exam',
                        'label' => 'CBT Report Exam',
                        'capability' => 'cbt_view_results',
                        'summary' => 'Menyusun ringkasan laporan exam untuk kebutuhan rekap, tindak lanjut, atau dokumentasi resmi.',
                        'when_to_use' => 'Dipakai setelah pemeriksaan hasil selesai dan panitia perlu laporan yang lebih formal atau terstruktur.',
                        'output' => 'Rekap dan laporan exam yang siap dibagikan atau dicetak.',
                    ],
                ],
            ],
            [
                'id' => 'system-tools',
                'title' => 'Sistem/Admin Tools',
                'description' => 'Menu teknis yang dipakai admin sistem untuk kestabilan, troubleshooting, atau pengembangan.',
                'items' => [
                    [
                        'slug' => 'cbt-cache',
                        'label' => 'CBT Cache',
                        'capability' => 'manage_options',
                        'summary' => 'Memeriksa kesiapan cache, backend performa, dan kondisi runtime yang mempengaruhi kecepatan sistem.',
                        'when_to_use' => 'Dipakai saat ingin memastikan performa tetap sehat atau ketika sistem terasa melambat.',
                        'output' => 'Status cache dan arahan teknis untuk menjaga performa.',
                    ],
                    [
                        'slug' => 'cbt-maintenance',
                        'label' => 'CBT Maintenance',
                        'capability' => 'manage_options',
                        'summary' => 'Menjalankan pekerjaan maintenance, dataset test, reset tertentu, dan utilitas operasional tingkat admin.',
                        'when_to_use' => 'Dipakai saat troubleshooting, simulasi, cleanup, atau operasi pemeliharaan sistem.',
                        'output' => 'Sistem yang lebih siap, data bantu untuk testing, atau proses maintenance yang terdokumentasi.',
                    ],
                    [
                        'slug' => 'cbt-developer',
                        'label' => 'CBT Developer',
                        'capability' => 'manage_options',
                        'summary' => 'Menyediakan alat bantu pengembangan, dev server, dan utilitas teknis untuk tim pengembang atau admin teknis.',
                        'when_to_use' => 'Dipakai hanya saat debugging, pengembangan, atau verifikasi integrasi teknis.',
                        'output' => 'Lingkungan pengembangan yang lebih mudah dipantau dan dikendalikan.',
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
                'label' => 'Setup',
                'summary' => 'Mulai dari konfigurasi dasar plugin, branding, dan pengaturan inti agar semua halaman lain memiliki fondasi yang benar.',
            ],
            [
                'step' => '2',
                'label' => 'Subjects',
                'summary' => 'Susun mapel atau kategori utama agar exam dan soal punya struktur yang konsisten.',
            ],
            [
                'step' => '3',
                'label' => 'Users',
                'summary' => 'Pastikan data peserta lengkap, terutama kelas dan identitas yang akan dipakai di ujian serta pelaporan.',
            ],
            [
                'step' => '4',
                'label' => 'Questions',
                'summary' => 'Isi bank soal sebagai sumber utama butir yang nanti dipakai dalam berbagai exam.',
            ],
            [
                'step' => '5',
                'label' => 'Exams',
                'summary' => 'Rakit exam, atur jadwal, pilih soal, dan set aturan pengerjaan sesuai kebutuhan ujian.',
            ],
            [
                'step' => '6',
                'label' => 'Tokens',
                'summary' => 'Siapkan token jika sesi ujian memerlukan kontrol akses tambahan saat peserta login.',
            ],
            [
                'step' => '7',
                'label' => 'Exam Cards',
                'summary' => 'Cetak kartu atau identitas peserta jika dibutuhkan untuk administrasi ruang ujian.',
            ],
            [
                'step' => '8',
                'label' => 'Results',
                'summary' => 'Monitor attempt, cek jawaban, grading essay, dan tindak lanjuti kasus operasional selama atau setelah ujian.',
            ],
            [
                'step' => '9',
                'label' => 'Analytics',
                'summary' => 'Baca insight hasil exam untuk melihat performa siswa, kualitas soal, dan area evaluasi.',
            ],
            [
                'step' => '10',
                'label' => 'Report Exam',
                'summary' => 'Tutup alur dengan rekap atau laporan exam yang lebih formal untuk dokumentasi dan tindak lanjut.',
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
                'title' => 'Sebelum ujian dibuka',
                'description' => 'Fokus pada Setup, Subjects, Users, Questions, dan Exams. Pastikan data master dan soal sudah benar sebelum token dibagikan.',
            ],
            [
                'title' => 'Menjelang sesi berlangsung',
                'description' => 'Gunakan Tokens dan Exam Cards untuk kesiapan lapangan. Ini membantu panitia mengontrol akses dan administrasi peserta.',
            ],
            [
                'title' => 'Saat ujian berjalan',
                'description' => 'Fokus utama ada di Results untuk memantau status attempt, intervensi kasus, dan penanganan jawaban yang perlu review manual.',
            ],
            [
                'title' => 'Setelah ujian selesai',
                'description' => 'Gunakan Analytics saat ingin membaca pola performa dan kualitas butir soal, lalu gunakan Report Exam untuk rekap formal.',
            ],
            [
                'title' => 'Menu teknis admin',
                'description' => 'Cache, Maintenance, dan Developer sebaiknya dipakai hanya saat perlu troubleshooting, tuning performa, atau kebutuhan teknis tertentu.',
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
                    'hint' => 'Akun ini belum punya izin untuk mengelola data peserta atau kartu ujian.',
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
