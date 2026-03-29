<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Users_Service
{
    private const USER_META_PLAIN_PASSWORD = 'cbt_plain_password';
    private const DEFAULT_STUDENT_PHOTO_RELATIVE_PATH = 'public/images/default-student-avatar.svg';
    private const USER_IMPORT_PHOTO_RUNTIME_DIR = 'cbt-runtime/user-import-photo-workspaces';
    private const USER_IMPORT_PHOTO_UPLOAD_DIR = 'cbt-user-import-photos';
    private const USER_IMPORT_ALLOWED_PHOTO_MIMES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    public static function can_manage_users(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_users');
    }

    public static function is_admin_scope(): bool
    {
        return current_user_can('manage_options') || current_user_can('cbt_manage_system');
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';
        $import_token = isset($query['cbt_import_token']) ? sanitize_key((string) wp_unslash((string) $query['cbt_import_token'])) : '';
        $search = isset($query['cbt_user_q']) ? sanitize_text_field(wp_unslash((string) $query['cbt_user_q'])) : '';
        $filter_role = isset($query['cbt_user_role']) ? sanitize_text_field(wp_unslash((string) $query['cbt_user_role'])) : '';
        $filter_kelas = isset($query['cbt_user_kelas']) ? sanitize_text_field(wp_unslash((string) $query['cbt_user_kelas'])) : '';
        $filter_ruang = isset($query['cbt_user_ruang']) ? sanitize_text_field(wp_unslash((string) $query['cbt_user_ruang'])) : '';
        $filter_agama = isset($query['cbt_user_agama']) ? self::normalize_supported_agama((string) wp_unslash((string) $query['cbt_user_agama'])) : '';
        $per_page = isset($query['cbt_user_per_page'])
            ? self::normalize_user_list_per_page(absint(wp_unslash((string) $query['cbt_user_per_page'])))
            : 20;
        $current_page = isset($query['cbt_user_paged']) ? max(1, absint(wp_unslash((string) $query['cbt_user_paged']))) : 1;
        $editing_user_id = isset($query['edit_user']) ? absint(wp_unslash((string) $query['edit_user'])) : 0;
        $editing_user = null;
        if ($editing_user_id > 0) {
            $editing_user = get_user_by('id', $editing_user_id);
            if (!($editing_user instanceof WP_User)) {
                $editing_user = null;
            }
        }
        $kelas_options = self::get_distinct_user_meta_values('kode_kelas');
        $ruang_options = self::get_distinct_user_meta_values('kode_ruang');
        $agama_options = self::get_supported_agama_options();
        $per_page_options = [20, 50, 100, 150, 200];
        $import_batch_size = self::get_user_import_batch_size();
        $users_page_data = self::get_cbt_users_paginated($search, $filter_role, $filter_kelas, $filter_ruang, $filter_agama, $per_page, $current_page);
        $users = $users_page_data['items'];
        $current_page = $users_page_data['page'];
        $total_pages = $users_page_data['total_pages'];
        $total_users = $users_page_data['total'];
        $import_state = null;
        $import_total = 0;
        $import_offset = 0;
        $import_created = 0;
        $import_updated = 0;
        $import_failed = 0;
        $import_progress_percent = 0.0;
        $import_is_running = false;
        $import_continue_url = '';
        if ($import_token !== '') {
            $import_state = self::get_user_import_state_for_current_user($import_token);
            if (is_array($import_state)) {
                $import_total = max(0, isset($import_state['total']) ? (int) $import_state['total'] : 0);
                $import_offset = max(0, isset($import_state['offset']) ? (int) $import_state['offset'] : 0);
                if ($import_total > 0 && $import_offset > $import_total) {
                    $import_offset = $import_total;
                }
                $import_created = max(0, isset($import_state['created']) ? (int) $import_state['created'] : 0);
                $import_updated = max(0, isset($import_state['updated']) ? (int) $import_state['updated'] : 0);
                $import_failed = max(0, isset($import_state['failed']) ? (int) $import_state['failed'] : 0);
                $import_progress_percent = $import_total > 0
                    ? round(((float) $import_offset / (float) $import_total) * 100, 2)
                    : 0.0;
                $import_is_running = $import_total > 0 && $import_offset < $import_total;
                $import_continue_url = add_query_arg(
                    [
                        'action' => 'cbt_import_users',
                        'cbt_import_token' => $import_token,
                    ],
                    admin_url('admin-post.php')
                );
            } elseif ($notice === '' && $error === '') {
                $error = 'Sesi import tidak ditemukan atau sudah berakhir. Silakan upload ulang file.';
            }
        }
        $is_editing_user = $editing_user instanceof WP_User;
        $default_user_tab = 'list';
        if ($total_users === 0) {
            $default_user_tab = 'form';
        }
        if (is_array($import_state)) {
            $default_user_tab = 'import';
        }
        if ($is_editing_user) {
            $default_user_tab = 'form';
        }
        $user_tab_is_forced = $is_editing_user || is_array($import_state);
        $user_reset_url = admin_url('admin.php?page=cbt-user-import');
        $user_clear_edit_args = [
            'page' => 'cbt-user-import',
            'cbt_user_per_page' => $per_page,
            'cbt_user_paged' => $current_page,
        ];
        if ($search !== '') {
            $user_clear_edit_args['cbt_user_q'] = $search;
        }
        if ($filter_role !== '') {
            $user_clear_edit_args['cbt_user_role'] = $filter_role;
        }
        if ($filter_kelas !== '') {
            $user_clear_edit_args['cbt_user_kelas'] = $filter_kelas;
        }
        if ($filter_ruang !== '') {
            $user_clear_edit_args['cbt_user_ruang'] = $filter_ruang;
        }
        if ($filter_agama !== '') {
            $user_clear_edit_args['cbt_user_agama'] = $filter_agama;
        }
        $user_clear_edit_url = add_query_arg($user_clear_edit_args, admin_url('admin.php'));
        $pagination_args = [
            'page' => 'cbt-user-import',
            'cbt_user_per_page' => $per_page,
        ];
        if ($search !== '') {
            $pagination_args['cbt_user_q'] = $search;
        }
        if ($filter_role !== '') {
            $pagination_args['cbt_user_role'] = $filter_role;
        }
        if ($filter_kelas !== '') {
            $pagination_args['cbt_user_kelas'] = $filter_kelas;
        }
        if ($filter_ruang !== '') {
            $pagination_args['cbt_user_ruang'] = $filter_ruang;
        }
        if ($filter_agama !== '') {
            $pagination_args['cbt_user_agama'] = $filter_agama;
        }
        $pagination_links = [];
        if ($total_pages > 1) {
            $pagination_links = paginate_links([
                'base' => add_query_arg(array_merge($pagination_args, ['cbt_user_paged' => '%#%']), admin_url('admin.php')),
                'format' => '',
                'current' => $current_page,
                'total' => $total_pages,
                'type' => 'array',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'end_size' => 1,
                'mid_size' => 1,
            ]);
        }
        $editing_role = 'siswa';
        $editing_nisn = '';
        $editing_kelas = '';
        $editing_ruang = '';
        $editing_agama_form = '';
        $editing_foto = '';
        if ($is_editing_user) {
            $editing_role_raw = isset($editing_user->roles[0]) ? (string) $editing_user->roles[0] : '';
            $editing_role = self::role_for_form($editing_role_raw);
            $editing_nisn = self::normalize_user_nisn((string) get_user_meta((int) $editing_user->ID, 'nisn', true));
            $editing_kelas = (string) get_user_meta((int) $editing_user->ID, 'kode_kelas', true);
            $editing_ruang = (string) get_user_meta((int) $editing_user->ID, 'kode_ruang', true);
            $editing_agama = (string) get_user_meta((int) $editing_user->ID, 'agama', true);
            $editing_agama_form = self::normalize_supported_agama($editing_agama);
            $editing_foto = (string) get_user_meta((int) $editing_user->ID, 'foto', true);
        }
        $is_admin_scope = self::is_admin_scope();

        return compact(
            'agama_options',
            'current_page',
            'default_user_tab',
            'editing_agama_form',
            'editing_foto',
            'editing_kelas',
            'editing_nisn',
            'editing_role',
            'editing_ruang',
            'editing_user',
            'editing_user_id',
            'error',
            'filter_agama',
            'filter_kelas',
            'filter_role',
            'filter_ruang',
            'import_batch_size',
            'import_continue_url',
            'import_created',
            'import_failed',
            'import_is_running',
            'import_offset',
            'import_progress_percent',
            'import_state',
            'import_token',
            'import_total',
            'import_updated',
            'is_admin_scope',
            'is_editing_user',
            'kelas_options',
            'notice',
            'pagination_links',
            'per_page',
            'per_page_options',
            'ruang_options',
            'search',
            'total_pages',
            'total_users',
            'user_clear_edit_url',
            'user_reset_url',
            'user_tab_is_forced',
            'users'
        );
    }

    public static function handle_create_user_manual(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_create_user_manual');

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $nisn = isset($_POST['nisn']) ? self::normalize_user_nisn((string) wp_unslash($_POST['nisn'])) : '';
        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username']), true) : '';
        $raw_password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $role_input = isset($_POST['role']) ? strtolower(sanitize_text_field(wp_unslash($_POST['role']))) : 'siswa';
        $kode_kelas = isset($_POST['kode_kelas']) ? sanitize_text_field(wp_unslash($_POST['kode_kelas'])) : '';
        $kode_ruang = isset($_POST['kode_ruang']) ? sanitize_text_field(wp_unslash($_POST['kode_ruang'])) : '';
        $agama = isset($_POST['agama']) ? self::normalize_supported_agama((string) wp_unslash($_POST['agama'])) : '';
        if ($name === '' || $username === '' || !is_email($email)) {
            self::redirect_user_import_with_error('Nama, username, dan email valid wajib diisi.');
        }

        if (username_exists($username)) {
            self::redirect_user_import_with_error('Username sudah terdaftar.');
        }
        if (email_exists($email)) {
            self::redirect_user_import_with_error('Email sudah terdaftar.');
        }

        $role = self::map_import_role($role_input);
        if ($role === 'administrator' && !self::is_admin_scope()) {
            self::redirect_user_import_with_error('Hanya admin yang bisa membuat user role admin.');
        }
        $nisn_validation_error = self::validate_user_nisn_for_role($nisn, $role);
        if ($nisn_validation_error !== '') {
            self::redirect_user_import_with_error($nisn_validation_error);
        }
        $foto_upload = self::handle_user_photo_upload('foto_file');
        if ($foto_upload['status'] === 'error') {
            self::redirect_user_import_with_error('Gagal upload foto: ' . (string) $foto_upload['error']);
        }
        $foto = self::resolve_manual_create_user_photo($role, $foto_upload);

        $generated_password = false;
        $password = $raw_password;
        if ($password === '') {
            $password = wp_generate_password(12, true, true);
            $generated_password = true;
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => $name,
            'role' => $role,
        ]);

        if (is_wp_error($user_id)) {
            self::redirect_user_import_with_error('Gagal membuat user: ' . $user_id->get_error_message());
        }

        if ($kode_kelas !== '') {
            update_user_meta((int) $user_id, 'kode_kelas', $kode_kelas);
        }
        if ($kode_ruang !== '') {
            update_user_meta((int) $user_id, 'kode_ruang', $kode_ruang);
        }
        if ($agama !== '') {
            update_user_meta((int) $user_id, 'agama', $agama);
        }
        if ($nisn !== '') {
            update_user_meta((int) $user_id, 'nisn', $nisn);
        }
        if ($foto !== '') {
            update_user_meta((int) $user_id, 'foto', $foto);
        }
        update_user_meta((int) $user_id, self::USER_META_PLAIN_PASSWORD, $password);

        $msg = 'User berhasil dibuat.';
        if ($generated_password) {
            $msg .= ' Password otomatis: ' . $password;
        }

        wp_safe_redirect(admin_url('admin.php?page=cbt-user-import&cbt_msg=' . rawurlencode($msg)));
        exit;
    }

    public static function handle_update_user_manual(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_update_user_manual');

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $nisn = isset($_POST['nisn']) ? self::normalize_user_nisn((string) wp_unslash($_POST['nisn'])) : '';
        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username']), true) : '';
        $raw_password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $role_input = isset($_POST['role']) ? strtolower(sanitize_text_field(wp_unslash($_POST['role']))) : 'siswa';
        $kode_kelas = isset($_POST['kode_kelas']) ? sanitize_text_field(wp_unslash($_POST['kode_kelas'])) : '';
        $kode_ruang = isset($_POST['kode_ruang']) ? sanitize_text_field(wp_unslash($_POST['kode_ruang'])) : '';
        $agama = isset($_POST['agama']) ? self::normalize_supported_agama((string) wp_unslash($_POST['agama'])) : '';
        $hapus_foto = isset($_POST['hapus_foto']) && sanitize_text_field(wp_unslash($_POST['hapus_foto'])) === '1';

        if ($user_id <= 0) {
            self::redirect_user_import_with_error('User tidak valid.');
        }
        if ($name === '' || $username === '' || !is_email($email)) {
            self::redirect_user_import_with_error('Nama, username, dan email valid wajib diisi.');
        }

        $user = get_user_by('id', $user_id);
        if (!($user instanceof WP_User)) {
            self::redirect_user_import_with_error('User tidak ditemukan.');
        }

        $existing_username = get_user_by('login', $username);
        if ($existing_username instanceof WP_User && (int) $existing_username->ID !== $user_id) {
            self::redirect_user_import_with_error('Username sudah terdaftar.');
        }

        $existing_email = get_user_by('email', $email);
        if ($existing_email instanceof WP_User && (int) $existing_email->ID !== $user_id) {
            self::redirect_user_import_with_error('Email sudah terdaftar.');
        }

        $role = self::map_import_role($role_input);
        if ($role === 'administrator' && !self::is_admin_scope()) {
            self::redirect_user_import_with_error('Hanya admin yang bisa mengubah user jadi admin.');
        }
        $nisn_validation_error = self::validate_user_nisn_for_role($nisn, $role, $user_id);
        if ($nisn_validation_error !== '') {
            self::redirect_user_import_with_error($nisn_validation_error);
        }
        $foto_upload = self::handle_user_photo_upload('foto_file');
        if ($foto_upload['status'] === 'error') {
            self::redirect_user_import_with_error('Gagal upload foto: ' . (string) $foto_upload['error']);
        }

        if ($username !== (string) $user->user_login) {
            global $wpdb;
            $login_updated = $wpdb->update(
                $wpdb->users,
                [
                    'user_login' => $username,
                    'user_nicename' => sanitize_title($username),
                ],
                ['ID' => $user_id],
                ['%s', '%s'],
                ['%d']
            );
            if ($login_updated === false) {
                self::redirect_user_import_with_error('Gagal mengubah username.');
            }
            clean_user_cache($user_id);
        }

        $updated = wp_update_user([
            'ID' => $user_id,
            'user_email' => $email,
            'display_name' => $name,
            'role' => $role,
        ]);
        if (is_wp_error($updated)) {
            self::redirect_user_import_with_error('Gagal update user: ' . $updated->get_error_message());
        }

        if ($raw_password !== '') {
            wp_set_password($raw_password, $user_id);
            update_user_meta($user_id, self::USER_META_PLAIN_PASSWORD, $raw_password);
        }

        if ($kode_kelas !== '') {
            update_user_meta($user_id, 'kode_kelas', $kode_kelas);
        } else {
            delete_user_meta($user_id, 'kode_kelas');
        }

        if ($kode_ruang !== '') {
            update_user_meta($user_id, 'kode_ruang', $kode_ruang);
        } else {
            delete_user_meta($user_id, 'kode_ruang');
        }
        if ($agama !== '') {
            update_user_meta($user_id, 'agama', $agama);
        } else {
            delete_user_meta($user_id, 'agama');
        }
        if ($nisn !== '') {
            update_user_meta($user_id, 'nisn', $nisn);
        } else {
            delete_user_meta($user_id, 'nisn');
        }

        self::apply_manual_update_user_photo($user_id, $foto_upload, $hapus_foto);

        if (self::is_student_role($role)) {
            $current_foto = trim((string) get_user_meta($user_id, 'foto', true));
            if ($current_foto === '') {
                update_user_meta($user_id, 'foto', self::get_default_student_photo_url());
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=cbt-user-import&cbt_msg=' . rawurlencode('User berhasil diupdate.')));
        exit;
    }

    public static function handle_delete_user_manual(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        $user_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('cbt_delete_user_manual_' . $user_id);
        $search = isset($_GET['cbt_user_q']) ? sanitize_text_field(wp_unslash($_GET['cbt_user_q'])) : '';
        $filter_role = isset($_GET['cbt_user_role']) ? sanitize_text_field(wp_unslash($_GET['cbt_user_role'])) : '';
        $filter_kelas = isset($_GET['cbt_user_kelas']) ? sanitize_text_field(wp_unslash($_GET['cbt_user_kelas'])) : '';
        $filter_ruang = isset($_GET['cbt_user_ruang']) ? sanitize_text_field(wp_unslash($_GET['cbt_user_ruang'])) : '';
        $filter_agama = isset($_GET['cbt_user_agama']) ? self::normalize_supported_agama((string) wp_unslash($_GET['cbt_user_agama'])) : '';
        $per_page = isset($_GET['cbt_user_per_page'])
            ? self::normalize_user_list_per_page(absint(wp_unslash($_GET['cbt_user_per_page'])))
            : 20;
        $current_page = isset($_GET['cbt_user_paged']) ? max(1, absint(wp_unslash($_GET['cbt_user_paged']))) : 1;

        $redirect_args = [
            'page' => 'cbt-user-import',
            'cbt_user_per_page' => $per_page,
            'cbt_user_paged' => $current_page,
        ];
        if ($search !== '') {
            $redirect_args['cbt_user_q'] = $search;
        }
        if ($filter_role !== '') {
            $redirect_args['cbt_user_role'] = $filter_role;
        }
        if ($filter_kelas !== '') {
            $redirect_args['cbt_user_kelas'] = $filter_kelas;
        }
        if ($filter_ruang !== '') {
            $redirect_args['cbt_user_ruang'] = $filter_ruang;
        }
        if ($filter_agama !== '') {
            $redirect_args['cbt_user_agama'] = $filter_agama;
        }

        if ($user_id <= 0) {
            self::redirect_user_import_with_error('User tidak valid.');
        }
        if ($user_id === get_current_user_id()) {
            self::redirect_user_import_with_error('User login saat ini tidak boleh dihapus.');
        }

        $user = get_user_by('id', $user_id);
        if (!($user instanceof WP_User)) {
            self::redirect_user_import_with_error('User tidak ditemukan.');
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted = wp_delete_user($user_id);
        if (!$deleted) {
            self::redirect_user_import_with_error('Gagal menghapus user.');
        }

        $redirect_args['cbt_msg'] = 'User berhasil dihapus.';
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    public static function handle_bulk_delete_users(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_bulk_delete_users');

        $search = isset($_POST['cbt_user_q']) ? sanitize_text_field(wp_unslash($_POST['cbt_user_q'])) : '';
        $filter_role = isset($_POST['cbt_user_role']) ? sanitize_text_field(wp_unslash($_POST['cbt_user_role'])) : '';
        $filter_kelas = isset($_POST['cbt_user_kelas']) ? sanitize_text_field(wp_unslash($_POST['cbt_user_kelas'])) : '';
        $filter_ruang = isset($_POST['cbt_user_ruang']) ? sanitize_text_field(wp_unslash($_POST['cbt_user_ruang'])) : '';
        $filter_agama = isset($_POST['cbt_user_agama']) ? self::normalize_supported_agama((string) wp_unslash($_POST['cbt_user_agama'])) : '';
        $per_page = isset($_POST['cbt_user_per_page'])
            ? self::normalize_user_list_per_page(absint(wp_unslash($_POST['cbt_user_per_page'])))
            : 20;
        $current_page = isset($_POST['cbt_user_paged']) ? max(1, absint(wp_unslash($_POST['cbt_user_paged']))) : 1;
        $bulk_mode = isset($_POST['bulk_mode']) ? sanitize_text_field(wp_unslash($_POST['bulk_mode'])) : 'selected';

        $redirect_args = [
            'page' => 'cbt-user-import',
        ];
        if ($search !== '') {
            $redirect_args['cbt_user_q'] = $search;
        }
        if ($filter_role !== '') {
            $redirect_args['cbt_user_role'] = $filter_role;
        }
        if ($filter_kelas !== '') {
            $redirect_args['cbt_user_kelas'] = $filter_kelas;
        }
        if ($filter_ruang !== '') {
            $redirect_args['cbt_user_ruang'] = $filter_ruang;
        }
        if ($filter_agama !== '') {
            $redirect_args['cbt_user_agama'] = $filter_agama;
        }
        $redirect_args['cbt_user_per_page'] = $per_page;
        $redirect_args['cbt_user_paged'] = $current_page;

        $target_user_ids = [];
        if ($bulk_mode === 'all_filtered') {
            $target_user_ids = self::get_cbt_user_ids($search, $filter_role, $filter_kelas, $filter_ruang, $filter_agama);
        } else {
            $raw_user_ids = isset($_POST['user_ids']) && is_array($_POST['user_ids']) ? wp_unslash($_POST['user_ids']) : [];
            $target_user_ids = array_map('absint', $raw_user_ids);
        }

        $target_user_ids = array_values(array_unique(array_filter($target_user_ids)));
        $current_user_id = get_current_user_id();
        $target_user_ids = array_values(array_filter($target_user_ids, static function ($user_id) use ($current_user_id): bool {
            return (int) $user_id !== (int) $current_user_id;
        }));

        if (empty($target_user_ids)) {
            $redirect_args['cbt_err'] = 'Tidak ada user yang dipilih untuk dihapus.';
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';

        $deleted_count = 0;
        foreach ($target_user_ids as $user_id) {
            $deleted = wp_delete_user((int) $user_id);
            if ($deleted) {
                $deleted_count++;
            }
        }

        if ($deleted_count > 0) {
            $redirect_args['cbt_msg'] = sprintf('User berhasil dihapus: %d.', $deleted_count);
        } else {
            $redirect_args['cbt_err'] = 'Tidak ada user yang berhasil dihapus.';
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    public static function handle_import_users(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        self::prepare_runtime_for_bulk_user_import();

        $token = isset($_GET['cbt_import_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_import_token'])) : '';
        if ($token !== '') {
            self::continue_user_import($token);
        }

        check_admin_referer('cbt_import_users');

        if (!isset($_FILES['user_file']) || !is_array($_FILES['user_file'])) {
            self::redirect_user_import_with_error('File tidak ditemukan.');
        }

        $file = $_FILES['user_file'];
        $tmp_path = $file['tmp_name'] ?? '';
        $original_name = $file['name'] ?? '';
        $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($error_code !== UPLOAD_ERR_OK || !$tmp_path) {
            self::redirect_user_import_with_error('Upload file gagal.');
        }

        $extension = strtolower((string) pathinfo((string) $original_name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            self::redirect_user_import_with_error('Format file harus CSV atau XLSX.');
        }

        $parsed = ($extension === 'xlsx')
            ? self::parse_user_xlsx($tmp_path)
            : self::parse_user_csv($tmp_path);
        if (is_wp_error($parsed)) {
            self::redirect_user_import_with_error($parsed->get_error_message());
        }
        if (!is_array($parsed) || empty($parsed)) {
            self::redirect_user_import_with_error('Tidak ada data user yang bisa diproses.');
        }
        $rows_validation = self::validate_user_import_rows($parsed);
        if (is_wp_error($rows_validation)) {
            self::redirect_user_import_with_error($rows_validation->get_error_message());
        }

        $photo_references = self::collect_user_import_photo_references($parsed);
        $token = strtolower((string) wp_generate_password(24, false, false));
        $photo_package = self::prepare_user_import_photo_package($token, $photo_references, $_FILES['user_photo_zip'] ?? null);
        if (is_wp_error($photo_package)) {
            self::redirect_user_import_with_error($photo_package->get_error_message());
        }
        $state_key = self::get_user_import_state_key($token);
        $rows_key = self::get_user_import_rows_key($token);
        $state = [
            'total' => count($parsed),
            'offset' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'user_id' => get_current_user_id(),
            'started_at' => time(),
        ];
        if (is_array($photo_package) && !empty($photo_package)) {
            $state['photo_package'] = $photo_package;
        }

        $rows_saved = set_transient($rows_key, array_values($parsed), 12 * HOUR_IN_SECONDS);
        $state_saved = set_transient($state_key, $state, 12 * HOUR_IN_SECONDS);
        if (!$rows_saved || !$state_saved) {
            if (is_array($photo_package) && !empty($photo_package)) {
                self::cleanup_user_import_photo_workspace_from_package($photo_package);
            }
            self::clear_user_import_transients($token);
            self::redirect_user_import_with_error('Gagal menyiapkan sesi import. Coba gunakan file CSV atau kurangi ukuran batch.');
        }
        wp_safe_redirect(add_query_arg([
            'page' => 'cbt-user-import',
            'cbt_import_token' => $token,
        ], admin_url('admin.php')));
        exit;
    }

    public static function handle_download_user_template(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_download_user_template');

        $file = CBT_EXAM_SYSTEM_PATH . 'templates/user-import-template.csv';
        if (!file_exists($file)) {
            wp_die('Template file not found.');
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cbt-user-import-template.csv"');
        header('Content-Length: ' . (string) filesize($file));
        readfile($file);
        exit;
    }

    public static function handle_download_user_template_xlsx(): void
    {
        if (!self::can_manage_users()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_download_user_template_xlsx');

        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet') || !class_exists('\\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx')) {
            wp_die('Library XLSX belum terpasang. Jalankan composer install pada plugin CBT.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(
            [
                ['name', 'email', 'nisn', 'username', 'password', 'role', 'kode_kelas', 'kode_ruang', 'agama', 'foto_file'],
                ['Budi Santoso', '', '1000000001', 'budi.santoso', 'Password123', 'siswa', 'X-IPA-1', 'LAB-1', 'Islam', '1000000001.jpg'],
                ['Siti Aminah', 'siti@student.sch.id', '1000000002', 'siti.aminah', 'Password123', 'siswa', 'X-IPA-1', 'LAB-1', 'Islam', '1000000002.jpg'],
                ['Pak Andi', 'andi@school.sch.id', '', 'andi.guru', 'Password123', 'guru', '', '', '', ''],
            ],
            null,
            'A1'
        );

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="cbt-user-import-template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * @param array<string,mixed> $file
     * @return array<int,array<string,string>>|WP_Error
     */
    public static function parse_user_csv(string $tmp_path)
    {
        $handle = fopen($tmp_path, 'rb');
        if ($handle === false) {
            return new WP_Error('csv_open_failed', 'Gagal membuka file CSV.');
        }

        $first_line = fgets($handle);
        if ($first_line === false) {
            fclose($handle);
            return new WP_Error('csv_empty', 'File CSV kosong.');
        }

        $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            return new WP_Error('csv_empty', 'File CSV kosong.');
        }

        $header = self::normalize_user_import_header($header);
        $header_check = self::validate_user_import_header($header);
        if (is_wp_error($header_check)) {
            fclose($handle);
            return $header_check;
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (!is_array($data)) {
                continue;
            }

            if (count(array_filter($data, static fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $idx => $col) {
                $row[$col] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        if (empty($rows)) {
            return new WP_Error('csv_no_data', 'Tidak ada data user di CSV.');
        }

        return $rows;
    }

    /**
     * @return array<int,array<string,string>>|WP_Error
     */
    public static function parse_user_xlsx(string $tmp_path)
    {
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return new WP_Error(
                'xlsx_library_missing',
                'Library XLSX belum terpasang. Jalankan composer install pada plugin CBT.'
            );
        }

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp_path);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            if (method_exists($reader, 'setReadEmptyCells')) {
                $reader->setReadEmptyCells(false);
            }
            $spreadsheet = $reader->load($tmp_path);
            $sheet = $spreadsheet->getActiveSheet();
            $raw_rows = $sheet->toArray('', false, false, false);
        } catch (Throwable $exception) {
            return new WP_Error('xlsx_read_failed', 'Gagal membaca file XLSX.');
        }

        if (!is_array($raw_rows) || empty($raw_rows)) {
            return new WP_Error('xlsx_empty', 'File XLSX kosong.');
        }

        $header = array_shift($raw_rows);
        if (!is_array($header)) {
            return new WP_Error('xlsx_header_invalid', 'Header XLSX tidak valid.');
        }

        $header = self::normalize_user_import_header($header);
        $header_check = self::validate_user_import_header($header);
        if (is_wp_error($header_check)) {
            return $header_check;
        }

        $rows = [];
        foreach ($raw_rows as $data) {
            if (!is_array($data)) {
                continue;
            }

            if (count(array_filter($data, static fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $idx => $col) {
                $row[$col] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
            }
            $rows[] = $row;
        }

        if (empty($rows)) {
            return new WP_Error('xlsx_no_data', 'Tidak ada data user di XLSX.');
        }

        return $rows;
    }

    /**
     * @param array<int,string> $header
     * @return array<int,string>
     */
    private static function normalize_user_import_header(array $header): array
    {
        $normalized = [];
        foreach ($header as $col) {
            $key = strtolower(trim((string) $col));
            $key = preg_replace('/[^a-z0-9_]+/', '_', $key);
            $key = trim($key ?? '', '_');
            if ($key === 'nama') {
                $key = 'name';
            } elseif ($key === 'email_address') {
                $key = 'email';
            } elseif ($key === 'username_login') {
                $key = 'username';
            } elseif ($key === 'kelas') {
                $key = 'kode_kelas';
            } elseif ($key === 'ruang') {
                $key = 'kode_ruang';
            } elseif ($key === 'photo_file') {
                $key = 'foto_file';
            }
            $normalized[] = $key;
        }

        return $normalized;
    }

    /**
     * @param array<int,string> $header
     * @return bool|WP_Error
     */
    private static function validate_user_import_header(array $header)
    {
        if (in_array('foto', $header, true)) {
            return new WP_Error(
                'legacy_photo_header',
                'Kolom header `foto` sudah tidak didukung. Unduh template terbaru yang memakai kolom `foto_file`.'
            );
        }

        $required = ['name', 'email', 'nisn', 'username', 'password', 'role', 'foto_file'];
        foreach ($required as $column) {
            if (!in_array($column, $header, true)) {
                return new WP_Error('missing_header', 'Kolom header template wajib lengkap: name, email, nisn, username, password, role, foto_file.');
            }
        }

        return true;
    }

    /**
     * @param array<string,string> $row
     * @param array<string,mixed> $import_lookup
     */
    public static function upsert_user_from_row(array $row, array &$import_lookup = [], array &$photo_package = []): string
    {
        $name = sanitize_text_field((string) ($row['name'] ?? ''));
        $email = sanitize_email((string) ($row['email'] ?? ''));
        $nisn = self::normalize_user_nisn((string) ($row['nisn'] ?? ''));
        $username = sanitize_user((string) ($row['username'] ?? ''), true);
        $password = (string) ($row['password'] ?? '');
        $role_raw = strtolower(trim((string) ($row['role'] ?? 'siswa')));
        $kode_kelas = sanitize_text_field((string) ($row['kode_kelas'] ?? ''));
        $kode_ruang = sanitize_text_field((string) ($row['kode_ruang'] ?? ''));
        $agama = self::normalize_supported_agama((string) ($row['agama'] ?? ''));
        $foto_file = self::normalize_user_import_photo_reference((string) ($row['foto_file'] ?? ''));
        $role = self::map_import_role($role_raw);

        if ($name === '' && $username !== '') {
            $name = $username;
        }

        if (self::is_student_role($role) && $nisn === '') {
            return 'failed';
        }

        if ($username === '' || (!is_email($email) && $nisn === '')) {
            return 'failed';
        }

        if (!is_email($email) && $nisn !== '') {
            $email = sanitize_email($nisn . '@student.sch.id');
        }
        if (!is_email($email)) {
            $email = sanitize_email($username . '@example.local');
        }

        if ($role === 'administrator' && !self::is_admin_scope()) {
            $role = 'siswa_cbt';
        }

        $user_id = self::resolve_user_import_existing_id($email, $username, $nisn, $import_lookup);
        $nisn_user_id = $nisn !== '' ? self::find_user_id_by_nisn($nisn) : 0;
        if ($nisn_user_id > 0 && $user_id > 0 && $nisn_user_id !== $user_id) {
            return 'failed';
        }
        if ($user_id <= 0 && $nisn_user_id > 0) {
            $user_id = $nisn_user_id;
        }
        $foto = self::resolve_user_import_photo_url($foto_file, $photo_package);
        if ($foto_file !== '' && $foto === '') {
            return 'failed';
        }

        if ($user_id > 0) {
            $updated = wp_update_user([
                'ID' => $user_id,
                'user_email' => $email,
                'display_name' => $name,
                'role' => $role,
            ]);
            if (is_wp_error($updated)) {
                return 'failed';
            }

            if ($password !== '') {
                wp_set_password($password, $user_id);
                update_user_meta($user_id, self::USER_META_PLAIN_PASSWORD, $password);
            }

            if ($kode_kelas !== '') {
                update_user_meta($user_id, 'kode_kelas', $kode_kelas);
            } else {
                delete_user_meta($user_id, 'kode_kelas');
            }
            if ($kode_ruang !== '') {
                update_user_meta($user_id, 'kode_ruang', $kode_ruang);
            } else {
                delete_user_meta($user_id, 'kode_ruang');
            }
            if ($agama !== '') {
                update_user_meta($user_id, 'agama', $agama);
            } else {
                delete_user_meta($user_id, 'agama');
            }
            if ($nisn !== '') {
                update_user_meta($user_id, 'nisn', $nisn);
            } else {
                delete_user_meta($user_id, 'nisn');
            }

            if ($foto !== '') {
                update_user_meta($user_id, 'foto', $foto);
            } elseif (self::is_student_role($role) && trim((string) get_user_meta($user_id, 'foto', true)) === '') {
                update_user_meta($user_id, 'foto', self::get_default_student_photo_url());
            }

            self::register_user_import_lookup($import_lookup, $user_id, $email, $username, $nisn, $name !== '' ? $name : $username);
            return 'updated';
        }

        if ($password === '') {
            $password = wp_generate_password(12, true, true);
        }

        $foto = self::resolve_student_default_photo($role, $foto);

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => $name,
            'role' => $role,
        ]);

        if (is_wp_error($user_id)) {
            return 'failed';
        }

        if ($kode_kelas !== '') {
            update_user_meta((int) $user_id, 'kode_kelas', $kode_kelas);
        }
        if ($kode_ruang !== '') {
            update_user_meta((int) $user_id, 'kode_ruang', $kode_ruang);
        }
        if ($agama !== '') {
            update_user_meta((int) $user_id, 'agama', $agama);
        }
        if ($nisn !== '') {
            update_user_meta((int) $user_id, 'nisn', $nisn);
        }
        if ($foto !== '') {
            update_user_meta((int) $user_id, 'foto', $foto);
        }
        update_user_meta((int) $user_id, self::USER_META_PLAIN_PASSWORD, $password);

        self::register_user_import_lookup($import_lookup, (int) $user_id, $email, $username, $nisn, $name !== '' ? $name : $username);

        return 'created';
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array<string,mixed>
     */
    public static function build_user_import_lookup(array $rows, int $offset, int $target_end): array
    {
        $emails = [];
        $logins = [];
        $nisns = [];
        $display_names = [];
        $import_lookup = [];

        $target_end = max($offset, $target_end);
        for ($index = $offset; $index < $target_end; $index++) {
            $row = isset($rows[$index]) && is_array($rows[$index]) ? $rows[$index] : [];
            $identity = self::extract_user_import_identity($row);
            $email = (string) ($identity['email'] ?? '');
            $nisn = (string) ($identity['nisn'] ?? '');
            $username = (string) ($identity['username'] ?? '');
            $display_name = (string) ($identity['display_name'] ?? '');
            if ($email !== '') {
                $emails[self::normalize_user_import_lookup_key($email)] = $email;
            }
            if ($nisn !== '') {
                $nisns[self::normalize_user_import_lookup_key($nisn)] = $nisn;
            }
            if ($username !== '') {
                $logins[self::normalize_user_import_lookup_key($username)] = $username;
            }
            if ($display_name !== '') {
                $display_names[self::normalize_user_import_lookup_key($display_name)] = $display_name;
            }
        }

        if (!empty($emails) || !empty($logins)) {
            $email_placeholders = !empty($emails)
                ? implode(',', array_fill(0, count($emails), '%s'))
                : '';
            $login_placeholders = !empty($logins)
                ? implode(',', array_fill(0, count($logins), '%s'))
                : '';

            $query_parts = [];
            $query_params = [];
            if ($email_placeholders !== '') {
                $query_parts[] = "user_email IN ({$email_placeholders})";
                $query_params = array_merge($query_params, array_values($emails));
            }
            if ($login_placeholders !== '') {
                $query_parts[] = "user_login IN ({$login_placeholders})";
                $query_params = array_merge($query_params, array_values($logins));
            }

            if (!empty($query_parts)) {
                global $wpdb;
                $query_sql = "SELECT ID, user_email, user_login, display_name
                              FROM {$wpdb->users}
                              WHERE " . implode(' OR ', $query_parts);
                $prepared_sql = $wpdb->prepare($query_sql, $query_params);
                $rows = $wpdb->get_results($prepared_sql, ARRAY_A);
                foreach ((array) $rows as $row) {
                    $user_id = isset($row['ID']) ? (int) $row['ID'] : 0;
                    if ($user_id <= 0) {
                        continue;
                    }
                    self::register_user_import_lookup(
                        $import_lookup,
                        $user_id,
                        (string) ($row['user_email'] ?? ''),
                        (string) ($row['user_login'] ?? ''),
                        self::normalize_user_nisn((string) get_user_meta($user_id, 'nisn', true)),
                        (string) ($row['display_name'] ?? '')
                    );
                }
            }
        }

        foreach ($nisns as $nisn) {
            $user_id = self::find_user_id_by_nisn($nisn);
            if ($user_id <= 0) {
                continue;
            }

            $user = get_user_by('id', $user_id);
            self::register_user_import_lookup(
                $import_lookup,
                $user_id,
                $user instanceof WP_User ? (string) $user->user_email : '',
                $user instanceof WP_User ? (string) $user->user_login : '',
                $nisn,
                $user instanceof WP_User ? (string) $user->display_name : ''
            );
        }

        return $import_lookup;
    }

    /**
     * @return array{email:string,nisn:string,username:string,display_name:string}
     */
    private static function extract_user_import_identity(array $row): array
    {
        $email = sanitize_email((string) ($row['email'] ?? ''));
        $nisn = self::normalize_user_nisn((string) ($row['nisn'] ?? ''));
        $username = sanitize_user((string) ($row['username'] ?? ''), true);
        $display_name = sanitize_text_field((string) ($row['name'] ?? ''));

        return [
            'email' => $email,
            'nisn' => $nisn,
            'username' => $username,
            'display_name' => $display_name,
        ];
    }

    /**
     * @param array<string,mixed> $lookup
     */
    private static function resolve_user_import_existing_id(string $email, string $username, string $nisn, array &$lookup): int
    {
        $email_key = self::normalize_user_import_lookup_key($email);
        $nisn_key = self::normalize_user_import_lookup_key($nisn);
        $username_key = self::normalize_user_import_lookup_key($username);

        if ($nisn_key !== '' && isset($lookup['nisns'][$nisn_key])) {
            return (int) $lookup['nisns'][$nisn_key];
        }
        if ($email_key !== '' && isset($lookup['emails'][$email_key])) {
            return (int) $lookup['emails'][$email_key];
        }
        if ($username_key !== '' && isset($lookup['logins'][$username_key])) {
            return (int) $lookup['logins'][$username_key];
        }

        if ($nisn !== '') {
            $user_id = self::find_user_id_by_nisn($nisn);
            if ($user_id > 0) {
                self::register_user_import_lookup($lookup, $user_id, $email, $username, $nisn, '');
                return $user_id;
            }
        }

        $user_id = (int) email_exists($email);
        if ($user_id > 0) {
            self::register_user_import_lookup($lookup, $user_id, $email, $username, $nisn, '');
            return $user_id;
        }

        $user_id = (int) username_exists($username);
        if ($user_id > 0) {
            self::register_user_import_lookup($lookup, $user_id, $email, $username, $nisn, '');
            return $user_id;
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $lookup
     */
    private static function register_user_import_lookup(array &$lookup, int $user_id, string $email, string $username, string $nisn, string $display_name): void
    {
        if ($user_id <= 0) {
            return;
        }

        if (!isset($lookup['emails'])) {
            $lookup['emails'] = [];
        }
        if (!isset($lookup['nisns'])) {
            $lookup['nisns'] = [];
        }
        if (!isset($lookup['logins'])) {
            $lookup['logins'] = [];
        }
        if (!isset($lookup['names'])) {
            $lookup['names'] = [];
        }

        $email_key = self::normalize_user_import_lookup_key($email);
        $nisn_key = self::normalize_user_import_lookup_key($nisn);
        $username_key = self::normalize_user_import_lookup_key($username);
        $display_key = self::normalize_user_import_lookup_key($display_name);

        if ($email_key !== '') {
            $lookup['emails'][$email_key] = $user_id;
        }
        if ($nisn_key !== '') {
            $lookup['nisns'][$nisn_key] = $user_id;
        }
        if ($username_key !== '') {
            $lookup['logins'][$username_key] = $user_id;
        }
        if ($display_key !== '') {
            $lookup['names'][$display_key] = $user_id;
        }
    }

    private static function normalize_user_import_lookup_key(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * @return array<int,string>
     */
    public static function get_supported_agama_options(): array
    {
        return [
            'Islam',
            'Kristen Protestan',
            'Katolik',
            'Hindu',
            'Buddha',
            'Khonghucu',
        ];
    }

    public static function normalize_supported_agama(string $agama): string
    {
        $clean = sanitize_text_field($agama);
        if ($clean === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/', ' ', strtolower(trim($clean)));
        if (!is_string($normalized)) {
            $normalized = strtolower(trim($clean));
        }

        $aliases = [
            'islam' => 'Islam',
            'muslim' => 'Islam',
            'kristen' => 'Kristen Protestan',
            'protestan' => 'Kristen Protestan',
            'kristen protestan' => 'Kristen Protestan',
            'katolik' => 'Katolik',
            'katholik' => 'Katolik',
            'hindu' => 'Hindu',
            'buddha' => 'Buddha',
            'budha' => 'Buddha',
            'khonghucu' => 'Khonghucu',
            'konghucu' => 'Khonghucu',
        ];
        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        return in_array($clean, self::get_supported_agama_options(), true) ? $clean : '';
    }

    private static function normalize_user_nisn(string $raw): string
    {
        $normalized = preg_replace('/\D+/', '', (string) $raw);
        return is_string($normalized) ? $normalized : '';
    }

    private static function find_user_id_by_nisn(string $nisn): int
    {
        $normalized_nisn = self::normalize_user_nisn($nisn);
        if ($normalized_nisn === '') {
            return 0;
        }

        $user_ids = get_users([
            'fields' => 'ids',
            'number' => 2,
            'meta_key' => 'nisn',
            'meta_value' => $normalized_nisn,
        ]);
        if (!is_array($user_ids) || empty($user_ids)) {
            return 0;
        }

        return (int) $user_ids[0];
    }

    private static function validate_user_nisn_for_role(string $nisn, string $role, int $exclude_user_id = 0): string
    {
        $normalized_nisn = self::normalize_user_nisn($nisn);
        if (self::is_student_role($role) && $normalized_nisn === '') {
            return 'NISN wajib diisi untuk user siswa.';
        }

        if ($normalized_nisn === '') {
            return '';
        }

        $existing_user_id = self::find_user_id_by_nisn($normalized_nisn);
        if ($existing_user_id > 0 && $existing_user_id !== $exclude_user_id) {
            return 'NISN sudah terdaftar pada user lain.';
        }

        return '';
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return bool|WP_Error
     */
    private static function validate_user_import_rows(array $rows)
    {
        $seen_emails = [];
        $seen_usernames = [];
        $seen_nisns = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $row_number = $index + 2;
            $role = self::map_import_role(strtolower(trim((string) ($row['role'] ?? 'siswa'))));
            $nisn = self::normalize_user_nisn((string) ($row['nisn'] ?? ''));
            $username = sanitize_user((string) ($row['username'] ?? ''), true);
            $email = sanitize_email((string) ($row['email'] ?? ''));

            if ($username !== '') {
                if (isset($seen_usernames[$username])) {
                    return new WP_Error(
                        'import_duplicate_username',
                        sprintf('Baris %1$d: username %2$s duplikat dengan baris %3$d pada file import.', $row_number, $username, (int) $seen_usernames[$username])
                    );
                }
                $seen_usernames[$username] = $row_number;
            }

            if (is_email($email)) {
                $email_key = strtolower($email);
                if (isset($seen_emails[$email_key])) {
                    return new WP_Error(
                        'import_duplicate_email',
                        sprintf('Baris %1$d: email %2$s duplikat dengan baris %3$d pada file import.', $row_number, $email, (int) $seen_emails[$email_key])
                    );
                }
                $seen_emails[$email_key] = $row_number;
            }

            if (self::is_student_role($role) && $nisn === '') {
                return new WP_Error('import_row_invalid', sprintf('Baris %d: NISN wajib diisi untuk user siswa.', $row_number));
            }

            if ($nisn === '') {
                continue;
            }

            if (isset($seen_nisns[$nisn])) {
                return new WP_Error(
                    'import_duplicate_nisn',
                    sprintf('Baris %1$d: NISN %2$s duplikat dengan baris %3$d pada file import.', $row_number, $nisn, (int) $seen_nisns[$nisn])
                );
            }
            $seen_nisns[$nisn] = $row_number;

            $nisn_user_id = self::find_user_id_by_nisn($nisn);
            if ($nisn_user_id <= 0) {
                continue;
            }

            $email_user_id = is_email($email) ? (int) email_exists($email) : 0;
            $username_user_id = $username !== '' ? (int) username_exists($username) : 0;
            if (($email_user_id > 0 && $email_user_id !== $nisn_user_id) || ($username_user_id > 0 && $username_user_id !== $nisn_user_id)) {
                return new WP_Error(
                    'import_nisn_conflict',
                    sprintf('Baris %1$d: NISN %2$s sudah terhubung ke user lain. Samakan username/email dengan user yang sama atau gunakan NISN berbeda.', $row_number, $nisn)
                );
            }
        }

        return true;
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @return array<string,array{name:string,rows:array<int,int>}>
     */
    private static function collect_user_import_photo_references(array $rows): array
    {
        $references = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $photo_file = self::normalize_user_import_photo_reference((string) ($row['foto_file'] ?? ''));
            if ($photo_file === '') {
                continue;
            }

            $key = self::normalize_user_import_photo_manifest_key($photo_file);
            if (!isset($references[$key])) {
                $references[$key] = [
                    'name' => $photo_file,
                    'rows' => [],
                ];
            }

            $references[$key]['rows'][] = $index + 2;
        }

        return $references;
    }

    private static function prepare_user_import_photo_package(string $token, array $references, $uploaded_photo_zip)
    {
        if (empty($references)) {
            return [];
        }

        if (!is_array($uploaded_photo_zip)) {
            return new WP_Error(
                'import_photo_zip_missing',
                'Kolom `foto_file` terisi, tetapi file ZIP Foto belum diupload. Upload ZIP yang berisi foto sesuai nama file pada kolom `foto_file`.'
            );
        }

        $error_code = isset($uploaded_photo_zip['error']) ? (int) $uploaded_photo_zip['error'] : UPLOAD_ERR_NO_FILE;
        $tmp_path = isset($uploaded_photo_zip['tmp_name']) ? (string) $uploaded_photo_zip['tmp_name'] : '';
        if ($error_code === UPLOAD_ERR_NO_FILE || $tmp_path === '') {
            return new WP_Error(
                'import_photo_zip_missing',
                'Kolom `foto_file` terisi, tetapi file ZIP Foto belum diupload. Upload ZIP yang berisi foto sesuai nama file pada kolom `foto_file`.'
            );
        }

        if ($error_code !== UPLOAD_ERR_OK) {
            return new WP_Error('import_photo_zip_upload_failed', 'Upload ZIP Foto gagal.');
        }

        if (!class_exists('ZipArchive')) {
            return new WP_Error(
                'import_photo_zip_unavailable',
                'ZIP Foto belum bisa diproses karena ekstensi PHP Zip/ZipArchive tidak tersedia di server.'
            );
        }

        $photo_package = self::build_user_import_photo_package_from_zip($token, $uploaded_photo_zip);
        if (is_wp_error($photo_package)) {
            return $photo_package;
        }

        $references_check = self::validate_user_import_photo_references($references, (array) ($photo_package['manifest'] ?? []));
        if (is_wp_error($references_check)) {
            self::cleanup_user_import_photo_workspace_from_package($photo_package);
            return $references_check;
        }

        return $photo_package;
    }

    private static function build_user_import_photo_package_from_zip(string $token, array $uploaded_photo_zip)
    {
        $original_name = isset($uploaded_photo_zip['name']) ? (string) $uploaded_photo_zip['name'] : '';
        $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));
        if ($extension !== 'zip') {
            return new WP_Error('import_photo_zip_format_invalid', 'ZIP Foto harus berformat .zip.');
        }

        $workspace = self::create_user_import_photo_workspace($token);
        if (is_wp_error($workspace)) {
            return $workspace;
        }

        $workspace_root = (string) $workspace;
        $zip_path = self::append_path_segment(self::append_path_segment($workspace_root, 'source'), 'user-photos.zip');
        $extract_dir = self::append_path_segment($workspace_root, 'extract');

        $stored = self::store_user_import_uploaded_file((string) ($uploaded_photo_zip['tmp_name'] ?? ''), $zip_path);
        if (!$stored) {
            self::delete_directory_tree($workspace_root);
            return new WP_Error('import_photo_zip_store_failed', 'Gagal menyimpan ZIP Foto ke workspace import.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($zip_path);
        if ($opened !== true) {
            self::delete_directory_tree($workspace_root);
            return new WP_Error('import_photo_zip_open_failed', 'ZIP Foto tidak dapat dibuka. Pastikan file ZIP tidak rusak.');
        }

        $manifest = [];
        $is_success = false;

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry_name = (string) $zip->getNameIndex($index);
                $normalized_entry_name = self::normalize_user_import_photo_zip_entry_name($entry_name);
                if ($normalized_entry_name === '') {
                    continue;
                }

                if (self::has_user_import_photo_path_traversal($normalized_entry_name)) {
                    return new WP_Error(
                        'import_photo_zip_unsafe_path',
                        sprintf('ZIP Foto mengandung path tidak aman: %s.', $entry_name)
                    );
                }

                $basename = self::normalize_user_import_photo_reference($normalized_entry_name);
                if ($basename === '') {
                    return new WP_Error('import_photo_zip_invalid_name', 'ZIP Foto mengandung nama file yang tidak valid.');
                }

                $manifest_key = self::normalize_user_import_photo_manifest_key($basename);
                if (isset($manifest[$manifest_key])) {
                    return new WP_Error(
                        'import_photo_zip_duplicate_name',
                        sprintf('ZIP Foto mengandung nama file ganda `%s`. Gunakan basename unik untuk setiap foto.', $basename)
                    );
                }

                $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
                if (!isset(self::USER_IMPORT_ALLOWED_PHOTO_MIMES[$extension])) {
                    return new WP_Error(
                        'import_photo_zip_invalid_type',
                        sprintf('ZIP Foto hanya boleh berisi JPG, JPEG, PNG, GIF, atau WEBP. File bermasalah: %s.', $basename)
                    );
                }

                $destination_path = self::append_path_segment(
                    $extract_dir,
                    self::build_user_import_workspace_photo_filename($manifest_key, $basename)
                );
                $extracted = self::extract_user_import_photo_zip_entry($zip, $entry_name, $destination_path);
                if (is_wp_error($extracted)) {
                    return $extracted;
                }

                $file_check = self::validate_user_import_photo_file($destination_path, $basename);
                if (is_wp_error($file_check)) {
                    return $file_check;
                }

                $manifest[$manifest_key] = [
                    'basename' => $basename,
                    'path' => $destination_path,
                ];
            }

            if (empty($manifest)) {
                return new WP_Error('import_photo_zip_empty', 'ZIP Foto tidak berisi file gambar yang valid.');
            }

            $is_success = true;

            return [
                'token' => $token,
                'workspace' => $workspace_root,
                'manifest' => $manifest,
                'uploaded_urls' => [],
            ];
        } finally {
            $zip->close();
            if (!$is_success) {
                self::delete_directory_tree($workspace_root);
            }
        }
    }

    /**
     * @param array<string,array{name:string,rows:array<int,int>}> $references
     * @param array<string,array<string,string>> $manifest
     * @return bool|WP_Error
     */
    private static function validate_user_import_photo_references(array $references, array $manifest)
    {
        foreach ($references as $reference) {
            $photo_name = isset($reference['name']) ? (string) $reference['name'] : '';
            $manifest_key = self::normalize_user_import_photo_manifest_key($photo_name);
            if ($manifest_key === '' || isset($manifest[$manifest_key])) {
                continue;
            }

            $row_number = isset($reference['rows'][0]) ? (int) $reference['rows'][0] : 0;
            return new WP_Error(
                'import_photo_file_missing',
                sprintf('Baris %1$d: file foto `%2$s` tidak ditemukan di ZIP Foto.', $row_number, $photo_name)
            );
        }

        return true;
    }

    private static function normalize_user_import_photo_reference(string $raw_reference): string
    {
        $reference = trim($raw_reference);
        if ($reference === '') {
            return '';
        }

        $reference = str_replace('\\', '/', $reference);
        $basename = trim((string) basename($reference));
        if ($basename === '' || $basename === '.' || $basename === '..') {
            return '';
        }

        return $basename;
    }

    private static function normalize_user_import_photo_manifest_key(string $reference): string
    {
        return strtolower(self::normalize_user_import_photo_reference($reference));
    }

    private static function normalize_user_import_photo_zip_entry_name(string $entry_name): string
    {
        $normalized = trim(str_replace('\\', '/', $entry_name));
        if ($normalized === '' || substr($normalized, -1) === '/') {
            return '';
        }

        return $normalized;
    }

    private static function has_user_import_photo_path_traversal(string $entry_name): bool
    {
        if ($entry_name === '') {
            return false;
        }

        if (substr($entry_name, 0, 1) === '/' || preg_match('/^[A-Za-z]:\//', $entry_name) === 1) {
            return true;
        }

        return strpos($entry_name, '../') !== false;
    }

    private static function resolve_user_import_photo_url(string $photo_file, array &$photo_package): string
    {
        $reference = self::normalize_user_import_photo_reference($photo_file);
        if ($reference === '') {
            return '';
        }

        $manifest_key = self::normalize_user_import_photo_manifest_key($reference);
        $uploaded_urls = isset($photo_package['uploaded_urls']) && is_array($photo_package['uploaded_urls'])
            ? $photo_package['uploaded_urls']
            : [];
        if (isset($uploaded_urls[$manifest_key]) && is_string($uploaded_urls[$manifest_key]) && $uploaded_urls[$manifest_key] !== '') {
            return esc_url_raw($uploaded_urls[$manifest_key]);
        }

        $manifest = isset($photo_package['manifest']) && is_array($photo_package['manifest'])
            ? $photo_package['manifest']
            : [];
        if (!isset($manifest[$manifest_key]) || !is_array($manifest[$manifest_key])) {
            return '';
        }

        $source_path = isset($manifest[$manifest_key]['path']) ? (string) $manifest[$manifest_key]['path'] : '';
        $basename = isset($manifest[$manifest_key]['basename']) ? (string) $manifest[$manifest_key]['basename'] : $reference;
        if ($source_path === '' || !is_file($source_path)) {
            return '';
        }

        $token = isset($photo_package['token']) ? sanitize_key((string) $photo_package['token']) : '';
        if ($token === '') {
            return '';
        }

        $upload = self::copy_user_import_photo_to_public_uploads($token, $manifest_key, $basename, $source_path);
        if (is_wp_error($upload)) {
            return '';
        }

        if (!isset($photo_package['uploaded_urls']) || !is_array($photo_package['uploaded_urls'])) {
            $photo_package['uploaded_urls'] = [];
        }
        $photo_package['uploaded_urls'][$manifest_key] = (string) $upload['url'];

        return esc_url_raw((string) $upload['url']);
    }

    private static function copy_user_import_photo_to_public_uploads(string $token, string $manifest_key, string $basename, string $source_path)
    {
        $uploads = self::get_wp_uploads_paths();
        if (is_wp_error($uploads)) {
            return $uploads;
        }

        $relative_dir = self::USER_IMPORT_PHOTO_UPLOAD_DIR . '/' . sanitize_key($token);
        $target_dir = self::append_path_segment((string) $uploads['basedir'], $relative_dir);
        if (!self::ensure_directory($target_dir)) {
            return new WP_Error('import_photo_public_dir_failed', 'Gagal menyiapkan folder upload foto import users.');
        }

        $stored_basename = self::sanitize_user_import_photo_storage_basename($manifest_key, $basename);
        $target_path = self::append_path_segment($target_dir, $stored_basename);
        if (!is_file($target_path)) {
            if (!@copy($source_path, $target_path)) {
                return new WP_Error('import_photo_public_store_failed', sprintf('Gagal menyalin file foto `%s` ke uploads publik.', $basename));
            }
        }

        return [
            'path' => $target_path,
            'url' => self::build_user_import_photo_public_url((string) $uploads['baseurl'], $relative_dir, $stored_basename),
        ];
    }

    private static function validate_user_import_photo_file(string $path, string $basename)
    {
        if (!is_file($path)) {
            return new WP_Error('import_photo_file_missing', sprintf('File foto `%s` tidak ditemukan setelah diekstrak dari ZIP.', $basename));
        }

        $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
        if (!isset(self::USER_IMPORT_ALLOWED_PHOTO_MIMES[$extension])) {
            return new WP_Error(
                'import_photo_file_extension_invalid',
                sprintf('File foto `%s` tidak memakai ekstensi gambar yang didukung.', $basename)
            );
        }

        $detected_mime = '';
        if (function_exists('getimagesize')) {
            $image_info = @getimagesize($path);
            if (is_array($image_info) && isset($image_info['mime']) && is_string($image_info['mime'])) {
                $detected_mime = strtolower($image_info['mime']);
            }
        }
        if ($detected_mime === '' && function_exists('finfo_open') && function_exists('finfo_file')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $path);
                @finfo_close($finfo);
                if (is_string($mime)) {
                    $detected_mime = strtolower(trim($mime));
                }
            }
        }
        if ($detected_mime === '' && function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if (is_string($mime)) {
                $detected_mime = strtolower(trim($mime));
            }
        }

        if ($detected_mime === '') {
            return new WP_Error(
                'import_photo_file_type_unknown',
                sprintf('File foto `%s` tidak dapat diverifikasi sebagai gambar yang valid.', $basename)
            );
        }

        if (!self::is_allowed_user_import_photo_mime($extension, $detected_mime)) {
            return new WP_Error(
                'import_photo_file_type_invalid',
                sprintf('File `%1$s` di ZIP bukan gambar %2$s yang valid.', $basename, strtoupper($extension))
            );
        }

        return true;
    }

    private static function is_allowed_user_import_photo_mime(string $extension, string $detected_mime): bool
    {
        $expected_mime = strtolower((string) (self::USER_IMPORT_ALLOWED_PHOTO_MIMES[$extension] ?? ''));
        if ($expected_mime === '') {
            return false;
        }

        if ($detected_mime === $expected_mime) {
            return true;
        }

        if (($extension === 'jpg' || $extension === 'jpeg') && in_array($detected_mime, ['image/jpeg', 'image/pjpeg'], true)) {
            return true;
        }

        return false;
    }

    private static function create_user_import_photo_workspace(string $token)
    {
        $uploads = self::get_wp_uploads_paths();
        if (is_wp_error($uploads)) {
            return $uploads;
        }

        $runtime_root = self::append_path_segment((string) $uploads['basedir'], self::USER_IMPORT_PHOTO_RUNTIME_DIR);
        if (!self::ensure_directory($runtime_root)) {
            return new WP_Error('import_photo_workspace_root_failed', 'Gagal menyiapkan folder workspace import foto users.');
        }
        self::protect_runtime_directory($runtime_root);

        $workspace_root = self::append_path_segment($runtime_root, sanitize_key($token));
        self::delete_directory_tree($workspace_root);
        if (!self::ensure_directory($workspace_root)) {
            return new WP_Error('import_photo_workspace_failed', 'Gagal membuat folder kerja import foto users.');
        }

        $source_dir = self::append_path_segment($workspace_root, 'source');
        $extract_dir = self::append_path_segment($workspace_root, 'extract');
        if (!self::ensure_directory($source_dir) || !self::ensure_directory($extract_dir)) {
            self::delete_directory_tree($workspace_root);
            return new WP_Error('import_photo_workspace_failed', 'Gagal membuat folder kerja internal untuk import foto users.');
        }

        self::protect_runtime_directory($workspace_root);

        return $workspace_root;
    }

    private static function get_wp_uploads_paths()
    {
        if (!function_exists('wp_upload_dir')) {
            return new WP_Error('uploads_unavailable', 'Folder uploads WordPress tidak tersedia.');
        }

        $uploads = wp_upload_dir();
        if (!is_array($uploads)) {
            return new WP_Error('uploads_invalid', 'Konfigurasi folder uploads WordPress tidak valid.');
        }

        $basedir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';
        $baseurl = isset($uploads['baseurl']) ? (string) $uploads['baseurl'] : '';
        $error = isset($uploads['error']) ? (string) $uploads['error'] : '';

        if ($error !== '') {
            return new WP_Error('uploads_error', sanitize_text_field($error));
        }
        if ($basedir === '' || $baseurl === '') {
            return new WP_Error('uploads_invalid', 'Folder uploads WordPress tidak lengkap.');
        }

        return [
            'basedir' => self::normalize_filesystem_path($basedir),
            'baseurl' => rtrim($baseurl, '/'),
        ];
    }

    private static function store_user_import_uploaded_file(string $source_path, string $destination_path): bool
    {
        if ($source_path === '') {
            return false;
        }

        $destination_dir = dirname($destination_path);
        if (!self::ensure_directory($destination_dir)) {
            return false;
        }

        if (function_exists('is_uploaded_file') && @is_uploaded_file($source_path)) {
            if (@move_uploaded_file($source_path, $destination_path)) {
                return true;
            }
        }

        if (@rename($source_path, $destination_path)) {
            return true;
        }

        return @copy($source_path, $destination_path);
    }

    private static function extract_user_import_photo_zip_entry(ZipArchive $zip, string $entry_name, string $destination_path)
    {
        $stream = $zip->getStream($entry_name);
        if (!is_resource($stream)) {
            return new WP_Error('import_photo_zip_stream_failed', sprintf('Gagal membaca file `%s` dari ZIP Foto.', $entry_name));
        }

        $destination_dir = dirname($destination_path);
        if (!self::ensure_directory($destination_dir)) {
            fclose($stream);
            return new WP_Error('import_photo_zip_extract_dir_failed', 'Gagal menyiapkan folder ekstraksi ZIP Foto.');
        }

        $target = @fopen($destination_path, 'wb');
        if (!is_resource($target)) {
            fclose($stream);
            return new WP_Error('import_photo_zip_extract_failed', sprintf('Gagal menulis file `%s` dari ZIP Foto.', $entry_name));
        }

        $copied_bytes = @stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        if ($copied_bytes === false || $copied_bytes <= 0) {
            @unlink($destination_path);
            return new WP_Error('import_photo_zip_extract_failed', sprintf('Gagal mengekstrak file `%s` dari ZIP Foto.', $entry_name));
        }

        return true;
    }

    private static function cleanup_user_import_photo_workspace_from_package(array $photo_package): void
    {
        $workspace = isset($photo_package['workspace']) ? (string) $photo_package['workspace'] : '';
        if ($workspace !== '') {
            self::delete_directory_tree($workspace);
        }
    }

    private static function build_user_import_workspace_photo_filename(string $manifest_key, string $basename): string
    {
        $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
        $safe_base = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($basename, PATHINFO_FILENAME));
        $safe_base = is_string($safe_base) ? trim($safe_base, '-_.') : '';
        if ($safe_base === '') {
            $safe_base = 'photo';
        }

        return substr(sha1($manifest_key), 0, 12) . '-' . $safe_base . ($extension !== '' ? '.' . $extension : '');
    }

    private static function sanitize_user_import_photo_storage_basename(string $manifest_key, string $basename): string
    {
        $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($basename, PATHINFO_FILENAME));
        $name = is_string($name) ? trim($name, '-_.') : '';
        if ($name === '') {
            $name = 'photo';
        }

        return substr(sha1($manifest_key), 0, 8) . '-' . $name . ($extension !== '' ? '.' . $extension : '');
    }

    private static function build_user_import_photo_public_url(string $baseurl, string $relative_dir, string $basename): string
    {
        $relative_parts = array_filter(explode('/', trim($relative_dir, '/')), static function ($segment): bool {
            return $segment !== '';
        });
        $relative_parts[] = $basename;
        $encoded_parts = array_map(static function (string $segment): string {
            return rawurlencode($segment);
        }, $relative_parts);

        return esc_url_raw(rtrim($baseurl, '/') . '/' . implode('/', $encoded_parts));
    }

    private static function ensure_directory(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (is_dir($path)) {
            return true;
        }

        return @mkdir($path, 0755, true) || is_dir($path);
    }

    private static function protect_runtime_directory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $index_path = self::append_path_segment($directory, 'index.php');
        if (!file_exists($index_path)) {
            @file_put_contents($index_path, "<?php\n// Silence is golden.\n");
        }

        $htaccess_path = self::append_path_segment($directory, '.htaccess');
        if (!file_exists($htaccess_path)) {
            @file_put_contents($htaccess_path, "Options -Indexes\nDeny from all\n");
        }
    }

    private static function delete_directory_tree(string $path): void
    {
        $normalized_path = self::normalize_filesystem_path($path);
        if ($normalized_path === '' || !file_exists($normalized_path)) {
            return;
        }

        if (is_file($normalized_path) || is_link($normalized_path)) {
            @unlink($normalized_path);
            return;
        }

        $items = @scandir($normalized_path);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                self::delete_directory_tree(self::append_path_segment($normalized_path, $item));
            }
        }

        @rmdir($normalized_path);
    }

    private static function normalize_filesystem_path(string $path): string
    {
        return str_replace('\\', '/', rtrim($path, "/\\"));
    }

    private static function append_path_segment(string $base_path, string $segment): string
    {
        $base = self::normalize_filesystem_path($base_path);
        $part = ltrim(str_replace('\\', '/', $segment), '/');
        if ($base === '') {
            return $part;
        }
        if ($part === '') {
            return $base;
        }

        return $base . '/' . $part;
    }

    private static function map_import_role(string $raw_role): string
    {
        switch ($raw_role) {
            case 'admin':
            case 'administrator':
            case 'admin_cbt':
                return 'administrator';

            case 'guru':
            case 'guru_cbt':
            case 'teacher':
            case 'editor':
                return 'guru_cbt';

            case 'siswa':
            case 'siswa_cbt':
            case 'student':
            case 'subscriber':
            default:
                return 'siswa_cbt';
        }
    }

    public static function humanize_role(string $role): string
    {
        switch ($role) {
            case 'administrator':
            case 'admin_cbt':
                return 'admin';
            case 'guru_cbt':
            case 'editor':
            case 'teacher':
                return 'guru';
            case 'siswa_cbt':
            case 'subscriber':
            case 'student':
                return 'siswa';
            default:
                return $role;
        }
    }

    private static function role_for_form(string $role): string
    {
        $normalized = self::humanize_role($role);
        if (in_array($normalized, ['admin', 'guru', 'siswa'], true)) {
            return $normalized;
        }

        return 'siswa';
    }

    private static function is_student_role(string $role): bool
    {
        $normalized = strtolower(trim($role));
        return in_array($normalized, ['siswa', 'siswa_cbt', 'subscriber', 'student'], true);
    }

    private static function get_default_student_photo_url(): string
    {
        return esc_url_raw(CBT_EXAM_SYSTEM_URL . self::DEFAULT_STUDENT_PHOTO_RELATIVE_PATH);
    }

    private static function resolve_student_default_photo(string $role, string $foto): string
    {
        $clean_foto = esc_url_raw(trim($foto));
        if ($clean_foto !== '') {
            return $clean_foto;
        }
        if (!self::is_student_role($role)) {
            return '';
        }

        return self::get_default_student_photo_url();
    }

    private static function normalize_user_list_per_page(int $requested): int
    {
        $allowed = [20, 50, 100, 150, 200];
        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        return 20;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function build_cbt_user_query_args(
        string $search = '',
        string $role_filter = '',
        string $kode_kelas = '',
        string $kode_ruang = '',
        string $agama = ''
    ): array {
        $args = [
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        $role_filter = strtolower(trim($role_filter));
        if ($role_filter === 'admin') {
            $args['role__in'] = ['administrator', 'admin_cbt'];
        } elseif ($role_filter === 'guru') {
            $args['role__in'] = ['guru_cbt', 'editor'];
        } elseif ($role_filter === 'siswa') {
            $args['role__in'] = ['siswa_cbt', 'subscriber'];
        } else {
            $args['role__in'] = ['administrator', 'guru_cbt', 'siswa_cbt', 'editor', 'subscriber'];
        }

        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $meta_query = [];
        $kode_kelas = trim($kode_kelas);
        $kode_ruang = trim($kode_ruang);
        $agama = self::normalize_supported_agama($agama);
        if ($kode_kelas !== '') {
            $meta_query[] = [
                'key' => 'kode_kelas',
                'value' => $kode_kelas,
                'compare' => '=',
            ];
        }
        if ($kode_ruang !== '') {
            $meta_query[] = [
                'key' => 'kode_ruang',
                'value' => $kode_ruang,
                'compare' => '=',
            ];
        }
        if ($agama !== '') {
            $meta_query[] = [
                'key' => 'agama',
                'value' => $agama,
                'compare' => '=',
            ];
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    /**
     * @return array{items: WP_User[], total: int, total_pages: int, page: int, per_page: int}
     */
    private static function get_cbt_users_paginated(
        string $search = '',
        string $role_filter = '',
        string $kode_kelas = '',
        string $kode_ruang = '',
        string $agama = '',
        int $per_page = 20,
        int $page = 1
    ): array {
        $per_page = self::normalize_user_list_per_page($per_page);
        $page = max(1, $page);

        $args = self::build_cbt_user_query_args($search, $role_filter, $kode_kelas, $kode_ruang, $agama);
        $args['number'] = $per_page;
        $args['offset'] = ($page - 1) * $per_page;
        $args['count_total'] = true;

        $query = new WP_User_Query($args);
        $items = $query->get_results();
        $total = isset($query->total_users) ? (int) $query->total_users : 0;
        $total_pages = max(1, (int) ceil($total / $per_page));

        if ($total > 0 && $page > $total_pages) {
            $page = $total_pages;
            $args['offset'] = ($page - 1) * $per_page;
            $query = new WP_User_Query($args);
            $items = $query->get_results();
        }

        return [
            'items' => is_array($items) ? $items : [],
            'total' => $total,
            'total_pages' => $total_pages,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * @return int[]
     */
    private static function get_cbt_user_ids(
        string $search = '',
        string $role_filter = '',
        string $kode_kelas = '',
        string $kode_ruang = '',
        string $agama = ''
    ): array {
        $args = self::build_cbt_user_query_args($search, $role_filter, $kode_kelas, $kode_ruang, $agama);
        $args['fields'] = 'ids';
        $user_ids = get_users($args);
        if (!is_array($user_ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $user_ids), static function ($item): bool {
            return $item > 0;
        }));
    }

    /**
     * @return string[]
     */
    public static function get_distinct_user_meta_values(string $meta_key): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT DISTINCT meta_value
             FROM {$wpdb->usermeta}
             WHERE meta_key = %s
               AND meta_value IS NOT NULL
               AND TRIM(meta_value) <> ''
             ORDER BY meta_value ASC",
            $meta_key
        );

        $rows = $wpdb->get_col($query);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map('sanitize_text_field', $rows), static function ($value) {
            return $value !== '';
        }));
    }

    private static function redirect_user_import_with_error(string $message): void
    {
        wp_safe_redirect(admin_url('admin.php?page=cbt-user-import&cbt_err=' . rawurlencode($message)));
        exit;
    }

    private static function handle_user_photo_upload(string $field_name): array
    {
        if (!isset($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
            return [
                'status' => 'empty',
                'url' => '',
                'error' => '',
            ];
        }

        $file = $_FILES[$field_name];
        $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error_code === UPLOAD_ERR_NO_FILE) {
            return [
                'status' => 'empty',
                'url' => '',
                'error' => '',
            ];
        }

        if ($error_code !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return [
                'status' => 'error',
                'url' => '',
                'error' => 'Upload foto gagal.',
            ];
        }

        if (!defined('ABSPATH')) {
            return [
                'status' => 'error',
                'url' => '',
                'error' => 'Path upload tidak valid.',
            ];
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $allowed_mimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        $upload = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => $allowed_mimes,
        ]);

        if (!is_array($upload) || !empty($upload['error']) || empty($upload['url'])) {
            $upload_error = is_array($upload) && !empty($upload['error']) ? sanitize_text_field((string) $upload['error']) : 'Upload foto gagal.';
            return [
                'status' => 'error',
                'url' => '',
                'error' => $upload_error,
            ];
        }

        return [
            'status' => 'uploaded',
            'url' => esc_url_raw((string) $upload['url']),
            'error' => '',
        ];
    }

    /**
     * @param array{status:string,url?:string,error?:string} $foto_upload
     */
    private static function resolve_manual_create_user_photo(string $role, array $foto_upload): string
    {
        $foto = '';
        if (($foto_upload['status'] ?? '') === 'uploaded') {
            $foto = esc_url_raw((string) ($foto_upload['url'] ?? ''));
        }

        return self::resolve_student_default_photo($role, $foto);
    }

    /**
     * @param array{status:string,url?:string,error?:string} $foto_upload
     */
    private static function apply_manual_update_user_photo(int $user_id, array $foto_upload, bool $hapus_foto): void
    {
        if (($foto_upload['status'] ?? '') === 'uploaded') {
            update_user_meta($user_id, 'foto', esc_url_raw((string) ($foto_upload['url'] ?? '')));
            return;
        }

        if ($hapus_foto) {
            delete_user_meta($user_id, 'foto');
        }
    }

    private static function prepare_runtime_for_bulk_user_import(): void
    {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');
    }

    private static function get_user_import_batch_size(): int
    {
        $batch_size = (int) apply_filters('cbt_user_import_batch_size', 220);
        if ($batch_size < 25) {
            return 25;
        }
        if ($batch_size > 500) {
            return 500;
        }

        return $batch_size;
    }

    private static function get_user_import_max_batch_seconds(): float
    {
        $seconds = (float) apply_filters('cbt_user_import_batch_max_seconds', 14.0);
        if ($seconds < 2.0) {
            return 2.0;
        }
        if ($seconds > 25.0) {
            return 25.0;
        }

        return $seconds;
    }

    private static function get_user_import_state_key(string $token): string
    {
        return 'cbt_user_import_' . $token;
    }

    private static function get_user_import_rows_key(string $token): string
    {
        return 'cbt_user_import_rows_' . $token;
    }

    private static function clear_user_import_transients(string $token): void
    {
        $state = get_transient(self::get_user_import_state_key($token));
        if (is_array($state) && isset($state['photo_package']) && is_array($state['photo_package'])) {
            self::cleanup_user_import_photo_workspace_from_package($state['photo_package']);
        }

        delete_transient(self::get_user_import_state_key($token));
        delete_transient(self::get_user_import_rows_key($token));
    }

    private static function get_user_import_state_for_current_user(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_user_import_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            return null;
        }

        return $state;
    }

    private static function continue_user_import(string $token): void
    {
        $state_key = self::get_user_import_state_key($token);
        $rows_key = self::get_user_import_rows_key($token);
        $state = get_transient($state_key);
        if (!is_array($state)) {
            self::redirect_user_import_with_error('Sesi import berakhir. Silakan upload ulang file import.');
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            self::clear_user_import_transients($token);
            self::redirect_user_import_with_error('Sesi import tidak valid untuk user saat ini.');
        }

        $rows = get_transient($rows_key);
        if (!is_array($rows) || empty($rows)) {
            // Backward compatibility: older state stored rows in the same transient.
            if (isset($state['rows']) && is_array($state['rows']) && !empty($state['rows'])) {
                $rows = array_values($state['rows']);
                set_transient($rows_key, $rows, 12 * HOUR_IN_SECONDS);
                unset($state['rows']);
                set_transient($state_key, $state, 12 * HOUR_IN_SECONDS);
            }
        }
        if (!is_array($rows) || empty($rows)) {
            self::clear_user_import_transients($token);
            self::redirect_user_import_with_error('Data batch import tidak ditemukan. Silakan upload ulang file.');
        }

        $rows = array_values($rows);
        $total = isset($state['total']) ? (int) $state['total'] : count($rows);
        $offset = isset($state['offset']) ? (int) $state['offset'] : 0;
        $created = isset($state['created']) ? (int) $state['created'] : 0;
        $updated = isset($state['updated']) ? (int) $state['updated'] : 0;
        $failed = isset($state['failed']) ? (int) $state['failed'] : 0;
        $photo_package = isset($state['photo_package']) && is_array($state['photo_package'])
            ? $state['photo_package']
            : [];

        if ($total <= 0 || empty($rows)) {
            self::clear_user_import_transients($token);
            self::redirect_user_import_with_error('Data import user kosong.');
        }

        if ($offset < 0) {
            $offset = 0;
        }
        if ($offset > $total) {
            $offset = $total;
        }

        $batch_size = self::get_user_import_batch_size();
        $max_batch_seconds = self::get_user_import_max_batch_seconds();
        $target_end = min($offset + $batch_size, $total);
        $end = $offset;
        $batch_started_at = microtime(true);
        $import_lookup = self::build_user_import_lookup($rows, $offset, $target_end);
        $cache_invalidation_prev = null;
        if (function_exists('wp_suspend_cache_invalidation')) {
            $cache_invalidation_prev = wp_suspend_cache_invalidation(true);
        }

        for ($index = $offset; $index < $target_end; $index++) {
            $row = isset($rows[$index]) && is_array($rows[$index]) ? $rows[$index] : [];

            try {
                $result = self::upsert_user_from_row($row, $import_lookup, $photo_package);
            } catch (Throwable $exception) {
                $result = 'failed';
            }

            if ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            } else {
                $failed++;
            }

            $end = $index + 1;
            if (($end - $offset) >= 1 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                break;
            }
        }
        if ($cache_invalidation_prev !== null && function_exists('wp_suspend_cache_invalidation')) {
            wp_suspend_cache_invalidation((bool) $cache_invalidation_prev);
        }

        if ($end < $offset) {
            $end = $offset;
        }
        $state['offset'] = $end;
        $state['created'] = $created;
        $state['updated'] = $updated;
        $state['failed'] = $failed;
        if (!empty($photo_package)) {
            $state['photo_package'] = $photo_package;
        }

        if ($end < $total) {
            $state_saved = set_transient($state_key, $state, 12 * HOUR_IN_SECONDS);
            if (!$state_saved) {
                self::clear_user_import_transients($token);
                self::redirect_user_import_with_error('Gagal menyimpan progres import. Silakan coba import ulang (disarankan format CSV).');
            }
            wp_safe_redirect(add_query_arg([
                'page' => 'cbt-user-import',
                'cbt_import_token' => $token,
            ], admin_url('admin.php')));
            exit;
        }

        self::clear_user_import_transients($token);

        $msg = sprintf(
            'Import selesai. Total: %d, Created: %d, Updated: %d, Failed: %d',
            $total,
            $created,
            $updated,
            $failed
        );

        wp_safe_redirect(admin_url('admin.php?page=cbt-user-import&cbt_msg=' . rawurlencode($msg)));
        exit;
    }
}
