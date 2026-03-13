<?php
/**
 * Simple Toko Pro - Account Profile Management Class
 * * Class ini menangani tampilan dan logika update halaman profil member.
 */
class Simple_Toko_Profile {

    public static function init() {
        add_action('wp_ajax_st_update_profile', array(__CLASS__, 'handle_update_profile'));
    }

    // --- TAMPILAN HALAMAN PROFIL MEMBER ---
    public static function render_profile_page() {
        if (!is_user_logged_in()) {
            return; // Harusnya sudah diamankan di render_account_page
        }

        $user = wp_get_current_user();
        $profile_update_success = isset($_GET['profile_update']) && $_GET['profile_update'] == 'success';
        
        // Ambil data user meta (contoh: nomor WA)
        $wa_number = get_user_meta($user->ID, 'st_wa_number', true);
        
        ?>
        <h2 style="margin-top:0;">Detail Akun & Profil</h2>

        <?php if ($profile_update_success): ?>
            <p style="background:#dcfce7; color:#16a34a; padding:10px; border-radius:6px; font-weight:bold; text-align:center;">
                Perubahan profil berhasil disimpan!
            </p>
        <?php endif; ?>

        <form id="st-profile-form" action="" method="post" style="max-width:500px; margin-top:30px;">
            <input type="hidden" name="action" value="st_update_profile">
            <?php wp_nonce_field('st_update_profile_nonce', 'st_profile_nonce'); ?>

            <div style="margin-bottom:15px;">
                <label for="st_display_name" style="display:block; margin-bottom:5px; font-weight:bold;">Nama Lengkap</label>
                <input type="text" id="st_display_name" name="st_display_name" value="<?php echo esc_attr($user->display_name); ?>" class="st-input" required>
            </div>

            <div style="margin-bottom:15px;">
                <label for="st_user_email" style="display:block; margin-bottom:5px; font-weight:bold;">Email (Tidak dapat diubah)</label>
                <input type="email" id="st_user_email" value="<?php echo esc_attr($user->user_email); ?>" class="st-input" disabled style="background:#f3f4f6;">
            </div>

            <div style="margin-bottom:15px;">
                <label for="st_wa_number" style="display:block; margin-bottom:5px; font-weight:bold;">Nomor WhatsApp</label>
                <input type="text" id="st_wa_number" name="st_wa_number" value="<?php echo esc_attr($wa_number); ?>" class="st-input" placeholder="Contoh: 08123456789">
            </div>

            <h3 style="margin-top:40px; border-bottom:1px solid #eee; padding-bottom:10px;">Ganti Password (Opsional)</h3>

            <div style="margin-bottom:15px;">
                <label for="st_new_password" style="display:block; margin-bottom:5px; font-weight:bold;">Password Baru</label>
                <input type="password" id="st_new_password" name="st_new_password" class="st-input" placeholder="Biarkan kosong jika tidak ingin ganti">
            </div>
            
            <div style="margin-bottom:20px;">
                <label for="st_confirm_password" style="display:block; margin-bottom:5px; font-weight:bold;">Konfirmasi Password Baru</label>
                <input type="password" id="st_confirm_password" name="st_confirm_password" class="st-input">
            </div>

            <div id="st-profile-message" style="margin-bottom:15px;"></div>

            <button type="submit" class="st-btn-primary" style="padding:12px 25px;">Simpan Perubahan</button>
        </form>

        <script>
        jQuery(document).ready(function($) {
            $('#st-profile-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var btn = form.find('button[type="submit"]');
                var messageBox = $('#st-profile-message');

                var newPass = $('#st_new_password').val();
                var confPass = $('#st_confirm_password').val();

                if (newPass && newPass !== confPass) {
                    messageBox.html('<p style="background:#fee2e2; color:#dc2626; padding:10px; border-radius:6px;">Konfirmasi password tidak cocok!</p>');
                    return;
                }

                btn.text('Menyimpan...').prop('disabled', true);
                messageBox.empty();

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        if (response.success) {
                            // Redirect to clear form data and show success message via URL parameter
                            window.location.href = window.location.href.split('&')[0] + '&st_tab=profile&profile_update=success';
                        } else {
                            messageBox.html('<p style="background:#fee2e2; color:#dc2626; padding:10px; border-radius:6px;">Gagal: ' + response.data + '</p>');
                        }
                        btn.text('Simpan Perubahan').prop('disabled', false);
                    },
                    error: function() {
                        messageBox.html('<p style="background:#fee2e2; color:#dc2626; padding:10px; border-radius:6px;">Terjadi kesalahan jaringan.</p>');
                        btn.text('Simpan Perubahan').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    // --- HANDLER AJAX UNTUK UPDATE PROFIL ---
    public static function handle_update_profile() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Anda harus login untuk mengakses ini.');
        }

        if (!isset($_POST['st_profile_nonce']) || !wp_verify_nonce($_POST['st_profile_nonce'], 'st_update_profile_nonce')) {
            wp_send_json_error('Security check failed.');
        }

        $user_id = get_current_user_id();
        $user_data = array('ID' => $user_id);

        $display_name = sanitize_text_field($_POST['st_display_name']);
        $wa_number = sanitize_text_field($_POST['st_wa_number']);
        $new_password = sanitize_text_field($_POST['st_new_password']);
        $confirm_password = sanitize_text_field($_POST['st_confirm_password']);

        // 1. Update Nama Tampilan
        if ($display_name) {
            $user_data['display_name'] = $display_name;
        }

        // 2. Update Password (jika diisi)
        if ($new_password) {
            if ($new_password !== $confirm_password) {
                wp_send_json_error('Password baru dan konfirmasi tidak cocok.');
            }
            $user_data['user_pass'] = $new_password;
        }

        // 3. Update Profil WordPress
        $updated_user = wp_update_user($user_data);

        if (is_wp_error($updated_user)) {
            wp_send_json_error($updated_user->get_error_message());
        }

        // 4. Update Custom Meta (Nomor WA)
        update_user_meta($user_id, 'st_wa_number', $wa_number);

        wp_send_json_success('Profil berhasil diperbarui.');
    }
}

// Inisialisasi Class
Simple_Toko_Profile::init();