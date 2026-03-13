<?php
/**
 * Plugin Name: Simple Toko Pro
 * Description: Toko Online Lengkap. Real Midtrans, RajaOngkir, Akun, Produk Digital, Webhook Fix.
 * Version: 1.1 (Updated with Direct Module)
 * Author: Paspay Dev
 */

defined( 'ABSPATH' ) || exit;

// 1. INCLUDE FILE
if ( file_exists( plugin_dir_path( __FILE__ ) . 'midtrans-api.php' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'midtrans-api.php';
}

// >>> 2. INCLUDE FILE MODUL BARU <<<
if ( file_exists( plugin_dir_path( __FILE__ ) . 'st-direct-checkout.php' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'st-direct-checkout.php';
}

if ( file_exists( plugin_dir_path( __FILE__ ) . 'st-account-profile.php' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'st-account-profile.php';
}

class Simple_Toko_Pro {

    private $payment_channels = array(
        'qris'       => array('label' => 'QRIS (GoPay/Shopee/Dana)', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a2/Logo_QRIS.svg/1200px-Logo_QRIS.svg.png', 'type' => 'qris'),
        'bca'        => array('label' => 'BCA Virtual Account', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/Bank_Central_Asia.svg/2560px-Bank_Central_Asia.svg.png', 'type' => 'va'),
        'bni'        => array('label' => 'BNI Virtual Account', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f0/Bank_Negara_Indonesia_logo_%282004%29.svg/2560px-Bank_Negara_Indonesia_logo_%282004%29.svg.png', 'type' => 'va'),
        'bri'        => array('label' => 'BRI Virtual Account', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/68/BANK_BRI_logo.svg/1280px-BANK_BRI_logo.svg.png', 'type' => 'va'),
        'mandiri'    => array('label' => 'Mandiri Bill Payment', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ad/Bank_Mandiri_logo_2016.svg/1200px-Bank_Mandiri_logo_2016.svg.png', 'type' => 'va'),
        'permata'    => array('label' => 'Permata Virtual Account', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/f/ff/Permata_Bank_%282024%29.svg', 'type' => 'va'),
        'cimb'       => array('label' => 'CIMB Niaga VA', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/38/CIMB_Niaga_logo.svg/2560px-CIMB_Niaga_logo.svg.png', 'type' => 'va'),
        'danamon'    => array('label' => 'Danamon VA', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/Danamon_%282024%29.svg/2560px-Danamon_%282024%29.svg.png', 'type' => 'va'),
        'bsi'        => array('label' => 'BSI Virtual Account', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a0/Bank_Syariah_Indonesia.svg/1200px-Bank_Syariah_Indonesia.svg.png', 'type' => 'va'),
        'seabank'    => array('label' => 'SeaBank VA', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ac/SeaBank.svg/2560px-SeaBank.svg.png', 'type' => 'va'),
    );

    public function __construct() {
        // >>> 3. INSTANCE MODUL DIRECT (Agar fungsi AJAX/Shortcode di file terpisah jalan) <<<
        if(class_exists('Simple_Toko_Direct')) {
            new Simple_Toko_Direct();
        }

        // --- FIX PENTING: Ubah 'init' ke 'plugins_loaded' agar session start lebih awal ---
        add_action( 'plugins_loaded', array( $this, 'start_session' ), 1 );
        
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_product_meta' ) );
        add_action( 'save_post', array( $this, 'save_product_meta' ) );
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_order_details_meta' ) );
        
        // Shortcodes
        add_shortcode( 'st_shop', array( $this, 'render_shop_page' ) );
        add_shortcode( 'st_cart', array( $this, 'render_cart_page' ) );
        add_shortcode( 'st_checkout', array( $this, 'render_checkout_page' ) );
        add_shortcode( 'st_account', array( $this, 'render_account_page' ) );

        // Ajax standard
        add_action( 'wp_ajax_st_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_st_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_st_remove_cart', array( $this, 'ajax_remove_cart' ) );
        add_action( 'wp_ajax_nopriv_st_remove_cart', array( $this, 'ajax_remove_cart' ) );
        add_action( 'wp_ajax_st_process_checkout', array( $this, 'process_checkout' ) );
        add_action( 'wp_ajax_nopriv_st_process_checkout', array( $this, 'process_checkout' ) );

        // AJAX RAJAONGKIR
        add_action( 'wp_ajax_st_get_locations', array( $this, 'ajax_get_locations' ) );
        add_action( 'wp_ajax_nopriv_st_get_locations', array( $this, 'ajax_get_locations' ) );
        add_action( 'wp_ajax_st_check_shipping', array( $this, 'ajax_check_shipping' ) );
        add_action( 'wp_ajax_nopriv_st_check_shipping', array( $this, 'ajax_check_shipping' ) );

        // Assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'print_footer_html_scripts' ), 99 );
        
        add_filter( 'the_content', array( $this, 'filter_single_product_content' ) );
        add_action( 'init', array( $this, 'fix_permalinks' ), 99 );
        
        // WEBHOOK
        add_action( 'init', array( $this, 'webhook_listener' ) );
    }

    public function start_session() {
        // --- FIX PENTING: Cek session ID dulu ---
        if ( ! session_id() ) {
            // Jika header sudah dikirim oleh plugin/tema lain, JANGAN start session dan JANGAN error (silent)
            if ( headers_sent() ) {
                return; 
            }
            // Gunakan @ untuk menekan warning jika ada sisa masalah kecil
            @session_start();
        }
        
        // Inisialisasi variabel session jika belum ada
        if ( ! isset( $_SESSION['st_cart'] ) ) $_SESSION['st_cart'] = array();
        if ( ! isset( $_SESSION['st_orders'] ) ) $_SESSION['st_orders'] = array();
    }

    public function fix_permalinks() {
        if ( ! get_option( 'st_permalinks_flushed_v18' ) ) {
            flush_rewrite_rules();
            update_option( 'st_permalinks_flushed_v18', true );
        }
    }

    // --- CPT ---
    public function register_post_types() {
        register_taxonomy( 'st_cat', 'st_product', array( 'label' => 'Kategori', 'hierarchical' => true, 'show_admin_column' => true, 'rewrite' => array('slug' => 'kategori-produk') ));
        register_post_type( 'st_product', array( 
            'labels' => array( 'name' => 'Produk', 'singular_name' => 'Produk' ), 
            'public' => true, 
            'has_archive' => true, 
            'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ), 
            'menu_icon' => 'dashicons-cart',
            'rewrite' => array('slug' => 'produk') 
        ));
        register_post_type( 'st_order', array( 'labels' => array( 'name' => 'Pesanan', 'singular_name' => 'Pesanan' ), 'public' => false, 'show_ui' => true, 'menu_icon' => 'dashicons-list-view', 'supports' => array( 'title', 'custom-fields' ), 'capabilities' => array( 'create_posts' => false ), 'map_meta_cap' => true ));
    }

    public function add_product_meta() {
        add_meta_box( 'st_data', 'Data Produk', function($post){
            $price = get_post_meta($post->ID, '_st_price', true);
            $weight = get_post_meta($post->ID, '_st_weight', true);
            $type = get_post_meta($post->ID, '_st_type', true);
            ?> 
            <p><label>Tipe Produk:</label> 
                <select name="st_type" style="width:100%">
                    <option value="physical" <?php selected($type, 'physical'); ?>>Produk Fisik (Perlu Ongkir)</option>
                    <option value="digital" <?php selected($type, 'digital'); ?>>Produk Digital (Tanpa Ongkir)</option>
                </select>
            </p>
            <p><label>Harga (Rp):</label> <input type="number" name="st_price" value="<?php echo esc_attr($price); ?>" style="width:100%"></p> 
            <p><label>Berat (Gram) - <i>Khusus Fisik</i>:</label> <input type="number" name="st_weight" value="<?php echo esc_attr($weight); ?>" placeholder="1000" style="width:100%"></p> 
            <?php
        }, 'st_product', 'side', 'high' );
    }

    public function save_product_meta( $post_id ) {
        if ( isset($_POST['st_price']) ) update_post_meta( $post_id, '_st_price', sanitize_text_field($_POST['st_price']) );
        if ( isset($_POST['st_weight']) ) update_post_meta( $post_id, '_st_weight', sanitize_text_field($_POST['st_weight']) );
        if ( isset($_POST['st_type']) ) update_post_meta( $post_id, '_st_type', sanitize_text_field($_POST['st_type']) );
    }

    // --- ADMIN SETTINGS ---
    public function add_settings_page() { add_submenu_page( 'edit.php?post_type=st_product', 'Pengaturan', 'Pengaturan', 'manage_options', 'st_settings', array( $this, 'render_settings' ) ); }
    public function register_settings() {
        register_setting( 'st_opts', 'st_server_key' ); register_setting( 'st_opts', 'st_client_key' ); 
        register_setting( 'st_opts', 'st_merchant_id' ); register_setting( 'st_opts', 'st_is_sandbox' );
        register_setting( 'st_opts', 'st_ro_key' ); register_setting( 'st_opts', 'st_origin_dist' ); 
        foreach($this->payment_channels as $key => $val) { register_setting( 'st_opts', 'st_enable_' . $key ); }
    }
    public function render_settings() {
        ?>
        <div class="wrap"><h1>Pengaturan Toko Ultimate</h1><form method="post" action="options.php"><?php settings_fields( 'st_opts' ); ?>
            <div style="background:#fff;padding:20px;border:1px solid #ddd;margin-bottom:20px;"><h2>1. RajaOngkir Komerce</h2><table class="form-table"><tr><th>API Key</th><td><input type="text" name="st_ro_key" value="<?php echo esc_attr(get_option('st_ro_key')); ?>" class="regular-text"></td></tr><tr><th>ID Kecamatan Asal</th><td><input type="number" name="st_origin_dist" value="<?php echo esc_attr(get_option('st_origin_dist')); ?>" class="regular-text"></td></tr></table></div>
            <div style="background:#fff;padding:20px;border:1px solid #ddd;margin-bottom:20px;"><h2>2. Midtrans API (WAJIB DIISI)</h2><table class="form-table"><tr><th>Merchant ID</th><td><input type="text" name="st_merchant_id" value="<?php echo esc_attr(get_option('st_merchant_id')); ?>"></td></tr><tr><th>Server Key</th><td><input type="text" name="st_server_key" value="<?php echo esc_attr(get_option('st_server_key')); ?>"></td></tr><tr><th>Client Key</th><td><input type="text" name="st_client_key" value="<?php echo esc_attr(get_option('st_client_key')); ?>"></td></tr><tr><th>Sandbox?</th><td><input type="checkbox" name="st_is_sandbox" value="1" <?php checked(1,get_option('st_is_sandbox')); ?>> Ya</td></tr></table></div>
            <div style="background:#fff;padding:20px;border:1px solid #ddd;"><h2>3. Payment Channels</h2><table class="form-table"><?php foreach($this->payment_channels as $k=>$d): ?><tr><th style="width:50px;"><img src="<?php echo $d['icon']; ?>" style="height:20px;"></th><td><label><input type="checkbox" name="st_enable_<?php echo $k; ?>" value="1" <?php checked(1,get_option('st_enable_'.$k)); ?>> <?php echo $d['label']; ?></label></td></tr><?php endforeach; ?></table></div>
        <?php submit_button(); ?></form></div>
        <?php
    }

    // --- ACCOUNT PAGE (FIX PENGALIHAN/REDIRECT DENGAN PAGE ID) ---
    public function render_account_page() {
        if (!is_user_logged_in()) {
            return $this->render_custom_login_page();
        }

        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;

        // Ambil ID halaman tempat shortcode dieksekusi untuk dijadikan basis URL
        $current_page_id = get_the_ID();
        $current_page_url = get_permalink($current_page_id);
        
        // Cek URL saat ini untuk mencari parameter yang sudah ada
        $base_args = [
            'st_tab' => isset($_GET['st_tab']) ? sanitize_key($_GET['st_tab']) : 'orders',
            'st_ppp' => isset($_GET['st_ppp']) ? intval($_GET['st_ppp']) : null,
            'st_paged' => isset($_GET['st_paged']) ? intval($_GET['st_paged']) : null,
        ];
        
        $view_order_id = isset($_GET['st_view_order']) ? intval($_GET['st_view_order']) : 0;
        $current_tab = $base_args['st_tab'];

        // Jika sedang melihat rincian pesanan, langsung tampilkan rincian
        if ($view_order_id && get_post_type($view_order_id) == 'st_order') {
            return $this->render_single_order_details($view_order_id);
        }

        // --- PENGATURAN PAGINASI & PENCARIAN ---
        $default_per_page = 5;
        $per_page_options = [5, 10, 20, 25, 50];
        
        $posts_per_page = $base_args['st_ppp'] ?: $default_per_page;
        $paged = $base_args['st_paged'] ?: 1;
        $search_id = isset($_GET['st_order_id']) ? intval($_GET['st_order_id']) : 0;

        $args = array(
            'post_type'      => 'st_order',
            'post_status'    => 'any',
            'author'         => $user_id,
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ($search_id) {
            $args['p'] = $search_id;
            $args['posts_per_page'] = 1;
            unset($args['paged']);
        }

        $order_query = new WP_Query($args);
        // --- END PENGATURAN PAGINASI & PENCARIAN ---
        
        ob_start();
        ?>
        <div class="st-wrapper" style="display:flex; gap:30px; margin:20px 0;">
            
            <div class="st-account-sidebar" style="flex:0 0 250px; background:#fff; padding:20px; border:1px solid #e2e8f0; border-radius:12px; height:fit-content;">
                <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Area Member</h3>
                <ul style="list-style:none; margin:0; padding:0;">
                    <?php
                    $nav_items = [
                        'orders' => 'Riwayat Pesanan',
                        'profile' => 'Detail Akun & Profil',
                    ];
                    
                    // Base URL untuk Navigasi (Memaksa menyertakan page_id)
                    $base_nav_url = add_query_arg('page_id', $current_page_id, get_permalink($current_page_id));
                    $base_nav_url = remove_query_arg(['st_view_order', 'st_order_id', 'st_ppp', 'st_paged'], $base_nav_url);


                    foreach ($nav_items as $key => $label) {
                        $url = add_query_arg('st_tab', $key, $base_nav_url);
                        $active_style = ($current_tab === $key) ? 'background:#f1f5f9; color:#2563eb; font-weight:bold;' : 'color:#475569;';
                        echo '<li><a href="'.esc_url($url).'" style="display:block; padding:10px; margin-bottom:5px; border-radius:6px; text-decoration:none; transition:background 0.2s; '.$active_style.'">'.$label.'</a></li>';
                    }
                    ?>
                    <li><a href="<?php echo esc_url(wp_logout_url(get_permalink($current_page_id))); ?>" style="display:block; padding:10px; margin-top:15px; border-top:1px solid #eee; color:#dc2626; text-decoration:none;">Logout</a></li>
                </ul>
            </div>

            <div class="st-account-content" style="flex:1; background:#fff; padding:30px; border:1px solid #e2e8f0; border-radius:12px;">
                <?php
                if ($current_tab === 'profile') {
                    Simple_Toko_Profile::render_profile_page();
                } else {
                    ?>
                    <h2 style="margin-top:0;">Riwayat Pesanan Anda</h2>
                    
                    <form method="get" style="display:flex; gap:10px; margin-bottom:30px; align-items:center;" action="<?php echo esc_url($current_page_url); ?>">
                        <input type="hidden" name="st_tab" value="orders">
                        <input type="hidden" name="page_id" value="<?php echo $current_page_id; ?>"> <?php if($posts_per_page != $default_per_page): ?>
                            <input type="hidden" name="st_ppp" value="<?php echo $posts_per_page; ?>">
                        <?php endif; ?>
                        
                        <input type="text" name="st_order_id" placeholder="Cari ID Pesanan (Contoh: 123)" class="st-input" style="max-width:300px; flex-grow:1;" value="<?php echo esc_attr($search_id ?: ''); ?>">
                        
                        <button type="submit" class="st-btn-primary" style="width:auto;">Cari</button>

                        <div style="margin-left:10px; display:flex; align-items:center;">
                            <label for="st_ppp" style="margin-right:5px; font-size:14px;">Tampil:</label>
                            <select name="st_ppp" id="st_ppp" onchange="this.form.submit()" style="padding:8px; border:1px solid #ccc; border-radius:4px;">
                                <?php foreach($per_page_options as $option): ?>
                                    <option value="<?php echo $option; ?>" <?php selected($posts_per_page, $option); ?>><?php echo $option; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="st_paged" value="1">
                        </div>
                    </form>
                    
                    <?php if($order_query->have_posts()): ?>
                        <div class="st-order-list">
                            <?php while($order_query->have_posts()): $order_query->the_post();
                                $oid = get_the_ID();
                                
                                $tot = get_post_meta($oid, '_st_total', true);
                                $method = get_post_meta($oid, '_st_method', true);
                                $status = get_post_meta($oid, '_st_status', true) ?: 'pending';
                                
                                $color_map = array(
                                    'settlement' => '#16a34a', 'capture' => '#16a34a', 'pending' => '#ea580c',
                                    'failed' => '#dc2626', 'expire' => '#dc2626', 'cancel' => '#dc2626',
                                );
                                $color = $color_map[$status] ?? '#64748b';
                                
                                // Base URL View: Sertakan page_id dan parameter pagination
                                $view_url_args = [
                                    'st_view_order' => $oid, 
                                    'st_tab' => 'orders', 
                                    'page_id' => $current_page_id,
                                ];
                                if ($posts_per_page != $default_per_page) { $view_url_args['st_ppp'] = $posts_per_page; }
                                
                                $view_url = add_query_arg($view_url_args, get_permalink($current_page_id));
                            ?>
                            <div class="st-order-item" style="border:1px solid #eee; padding:15px; margin-bottom:15px; border-radius:8px;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:10px; align-items:center;">
                                    <strong>Order #<?php echo $oid; ?></strong>
                                    <span style="font-weight:bold; color:<?php echo $color; ?>; text-transform:uppercase; font-size:12px; padding:4px 8px; border-radius:4px; border:1px solid <?php echo $color; ?>;"><?php echo strtoupper($status); ?></span>
                                </div>
                                <p style="margin:5px 0;">Total: <strong>Rp <?php echo number_format($tot,0,',','.'); ?></strong> via <?php echo strtoupper($method); ?></p>
                                
                                <a href="<?php echo esc_url($view_url); ?>" style="display:inline-block; margin-top:10px; font-size:14px; color:#2563eb; text-decoration:none;">Lihat Rincian & Bayar &rarr;</a>
                            </div>
                            <?php endwhile; wp_reset_postdata(); ?>
                        </div>

                        <?php
                        $total_pages = $order_query->max_num_pages;
                        if ($total_pages > 1 && !$search_id) {
                            echo '<div class="st-pagination" style="text-align:center; margin-top:30px;">';
                            
                            // Base URL Pagination
                            $base_pagination_args = ['st_tab' => 'orders', 'page_id' => $current_page_id, 'st_ppp' => $posts_per_page];
                            
                            // Previous button
                            if ($paged > 1) {
                                $prev_url = add_query_arg('st_paged', $paged - 1, add_query_arg($base_pagination_args, get_permalink($current_page_id)));
                                echo '<a href="'.esc_url($prev_url).'" style="margin:0 5px; padding:8px 15px; border:1px solid #ccc; border-radius:4px; text-decoration:none; color:#1e293b;">&laquo; Sebelumnya</a>';
                            } else {
                                echo '<span style="margin:0 5px; padding:8px 15px; border:1px solid #eee; border-radius:4px; color:#94a3b8;">&laquo; Sebelumnya</span>';
                            }
                            
                            // Page numbers (simplified)
                            echo '<span style="margin:0 10px; font-weight:bold;">Halaman ' . $paged . ' dari ' . $total_pages . '</span>';
                            
                            // Next button
                            if ($paged < $total_pages) {
                                $next_url = add_query_arg('st_paged', $paged + 1, add_query_arg($base_pagination_args, get_permalink($current_page_id)));
                                echo '<a href="'.esc_url($next_url).'" style="margin:0 5px; padding:8px 15px; border:1px solid #ccc; border-radius:4px; text-decoration:none; color:#1e293b;">Berikutnya &raquo;</a>';
                            } else {
                                echo '<span style="margin:0 5px; padding:8px 15px; border:1px solid #eee; border-radius:4px; color:#94a3b8;">Berikutnya &raquo;</span>';
                            }
                            
                            echo '</div>';
                        }
                        ?>
                        
                    <?php else: ?>
                        <?php if ($search_id): ?>
                            <p>Tidak ditemukan pesanan dengan ID #<?php echo esc_html($search_id); ?> atau pesanan tersebut bukan milik Anda.</p>
                        <?php else: ?>
                            <p>Belum ada riwayat pesanan.</p>
                        <?php endif; ?>
                    <?php endif;
                }
                ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }
   
    // --- FUNGSI BARU: TAMPILAN LOGIN KUSTOM (P3) ---
    private function render_custom_login_page() {
        ob_start();
        
        $login_error = isset($_GET['login_error']) ? 'Login gagal. Email atau password salah.' : '';
        $redirect_to = get_permalink(); // Redirect kembali ke halaman Akun setelah login
        
        ?>
        <div class="st-wrapper" style="display:flex; justify-content:center; padding:50px 0;">
            <div style="background:#fff; padding:40px; border:1px solid #e2e8f0; border-radius:12px; width:100%; max-width:400px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <h2 style="margin-top:0; text-align:center; color:#1e293b;">Masuk ke Akun Member</h2>
                <p style="text-align:center; color:#64748b;">Akses riwayat pesanan dan detail profil Anda.</p>

                <?php if ($login_error): ?>
                    <p style="background:#fee2e2; color:#dc2626; padding:10px; border-radius:6px; text-align:center;"><?php echo $login_error; ?></p>
                <?php endif; ?>

                <form action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>" method="post">
                    
                    <div style="margin-bottom:15px;">
                        <label for="st_log_user" style="display:block; margin-bottom:5px; font-weight:bold;">Email / Username</label>
                        <input type="text" name="log" id="st_log_user" class="st-input" required style="width:100%;">
                    </div>
                    
                    <div style="margin-bottom:20px;">
                        <label for="st_log_pass" style="display:block; margin-bottom:5px; font-weight:bold;">Password</label>
                        <input type="password" name="pwd" id="st_log_pass" class="st-input" required style="width:100%;">
                    </div>
                    
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_to); ?>" />
                    
                    <button type="submit" name="wp-submit" class="st-btn-primary" style="width:100%; padding:12px; font-size:16px;">Masuk</button>
                    
                    <p style="text-align:center; margin-top:20px; font-size:14px;">
                        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" style="color:#2563eb; text-decoration:none;">Lupa Password?</a>
                    </p>
                </form>
            </div>
        </div>
        <?php 
        // Menggunakan hook untuk menangani error login agar kembali ke shortcode
        add_filter('login_url', function($login_url, $redirect) use ($redirect_to) {
            return add_query_arg('redirect_to', urlencode($redirect_to), $login_url);
        }, 10, 2);

        // Tambahkan hook untuk menangani kegagalan login
        add_action('wp_login_failed', function($username) use ($redirect_to) {
            $referrer = wp_get_referer();
            if (strpos($referrer, 'st_account')) { // Pastikan redirect hanya jika datang dari halaman Akun
                wp_redirect(add_query_arg('login_error', '1', $redirect_to));
                exit;
            }
        });
        
        return ob_get_clean();
    }

    // --- FUNGSI BARU: MENAMPILKAN RINCIAN PESANAN TUNGGAL (FIX DISPLAY PRODUK & HARGA) ---
    private function render_single_order_details($order_id) {
        // Data yang diambil:
        $tot = get_post_meta($order_id, '_st_total', true);
        $method = get_post_meta($order_id, '_st_method', true);
        $addr_str = get_post_meta($order_id, '_st_address', true) ?: 'Data alamat tidak ditemukan.';
        $va = get_post_meta($order_id, '_st_payment_code', true);
        $status = get_post_meta($order_id, '_st_status', true) ?: 'pending';
        $email = get_post_meta($order_id, '_st_email', true) ?: 'N/A';
        $phone = get_post_meta($order_id, '_st_phone', true) ?: 'N/A';
        
        // <<< DATA HARGA RINCI BARU >>>
        $subtotal = get_post_meta($order_id, '_st_subtotal', true) ?: 0;
        $ongkir = get_post_meta($order_id, '_st_ongkir', true) ?: 0;
        $fee = get_post_meta($order_id, '_st_fee', true) ?: 0;
        // <<< END DATA HARGA RINCI BARU >>>
        
        // Data Produk yang tersimpan di order meta:
        $pid_direct = get_post_meta($order_id, '_st_product_id', true);
        $qty_direct = get_post_meta($order_id, '_st_qty', true);
        $cart_items = get_post_meta($order_id, '_st_cart_items', true);
        
        // Penamaan Status Profesional
        $status_label = strtoupper($status);
        $status_message = '';
        
        $color = '#64748b';
        if ($status == 'settlement' || $status == 'capture') {
            $status_label = 'Sedang Diproses';
            $status_message = '**Pembayaran telah diterima** dan pesanan Anda sedang kami proses. Terima kasih.';
            $color = '#16a34a';
        } elseif ($status == 'pending') {
            $status_label = 'Menunggu Pembayaran';
            $status_message = 'Mohon segera selesaikan pembayaran. Instruksi pembayaran tersedia di bawah.';
            $color = '#ea580c';
        } elseif (in_array($status, ['failed', 'expire', 'cancel'])) {
            $status_label = 'Dibatalkan/Gagal';
            $status_message = 'Pesanan ini telah dibatalkan atau kadaluarsa. Silakan buat pesanan baru.';
            $color = '#dc2626';
        }
        
        $back_url = remove_query_arg('st_view_order', get_permalink());

        // --- LOGIKA MENAMPILKAN DETAIL PRODUK ---
        $product_display = '';
        
        if ($pid_direct) {
            $product_name = get_the_title($pid_direct) ?: 'Produk Tidak Ditemukan (ID: ' . $pid_direct . ')';
            $product_display .= '<ul style="list-style:disc; margin-left:20px;">';
            $product_display .= '<li>' . esc_html($product_name) . ' x ' . esc_html($qty_direct) . '</li>';
            $product_display .= '</ul>';
        } elseif (!empty($cart_items) && is_array($cart_items)) {
            $product_display .= '<ul style="list-style:disc; margin-left:20px;">';
            foreach ($cart_items as $item_pid => $item_qty) {
                $item_name = get_the_title($item_pid) ?: 'Produk Tidak Ditemukan (ID: ' . $item_pid . ')';
                $product_display .= '<li>' . esc_html($item_name) . ' x ' . esc_html($item_qty) . '</li>';
            }
            $product_display .= '</ul>';
        } else {
             $product_display = '<p style="color:#eab308; font-style: italic;">Data item produk tidak ditemukan pada pesanan ini.</p>';
        }
        // --- END LOGIKA MENAMPILKAN DETAIL PRODUK ---

        ob_start();
        ?>
        <div class="st-wrapper">
            <div class="st-account-box" style="background:#fff; padding:30px; border:1px solid #e2e8f0; border-radius:12px;">
                <a href="<?php echo esc_url($back_url); ?>" style="display:inline-block; margin-bottom:20px; font-size:14px; text-decoration:none;">&larr; Kembali ke Riwayat Pesanan</a>
                
                <h2 style="margin:0;">Rincian Pesanan #<?php echo $order_id; ?></h2>
                <div style="margin-top:15px; padding:15px; border-radius:8px; border:1px solid <?php echo $color; ?>; background:<?php echo $color; ?>10;">
                    <strong style="color:<?php echo $color; ?>; font-size:16px;">Status: <?php echo $status_label; ?></strong>
                    <p style="margin:5px 0 0; color:#475569;"><?php echo wpautop(wp_kses_post($status_message)); ?></p>
                </div>

                <div style="display:flex; gap:30px; margin-top:20px; flex-wrap:wrap;">
                    
                    <div style="flex:1; min-width:300px;">
                        <h4 style="border-bottom:1px solid #eee; padding-bottom:5px; margin-top:0;">Ringkasan & Pembayaran</h4>
                        
                        <table style="width:100%; font-size:14px;">
                            <tr><th style="text-align:left; padding:5px 0;">Subtotal Produk:</th><td style="text-align:right;">Rp <?php echo number_format($subtotal,0,',','.'); ?></td></tr>
                            <tr><th style="text-align:left; padding:5px 0;">Ongkos Kirim:</th><td style="text-align:right;">Rp <?php echo number_format($ongkir,0,',','.'); ?></td></tr>
                            <tr><th style="text-align:left; padding:5px 0;">Biaya Layanan (Fee):</th><td style="text-align:right;">Rp <?php echo number_format($fee,0,',','.'); ?></td></tr>
                            
                            <tr><th style="text-align:left; padding:5px 0; border-top:1px solid #ddd;">Total Bayar:</th><td style="text-align:right; border-top:1px solid #ddd;"><strong style="font-size:16px;">Rp <?php echo number_format($tot,0,',','.'); ?></strong></td></tr>
                            
                            <tr><th style="text-align:left; padding:5px 0; border-top:1px solid #eee;">Metode:</th><td style="text-align:right; border-top:1px solid #eee;"><?php echo strtoupper($method); ?></td></tr>
                        </table>
                        
                        <?php // Tampilkan instruksi pembayaran jika status pending
                        if ($status == 'pending'): ?>
                            <h4 style="border-bottom:1px solid #eee; padding-bottom:5px; margin-top:20px; color:<?php echo $color; ?>;">Instruksi Pembayaran</h4>
                            <div style="padding:15px; background:#f8fafc; border:1px dashed #ccc; border-radius:6px; text-align:center;">
                                <p style="margin-top:0; font-size:14px;">Segera bayar ke:</p>
                                
                                <?php if ($method == 'qris'): ?>
                                    <p style="font-weight:bold;">Pembayaran QRIS</p>
                                    <p style="font-size:13px; color:#475569;">Gunakan aplikasi Bank/E-Wallet Anda untuk scan QRIS yang ditampilkan saat checkout. Kode pembayaran/VA tidak ditampilkan untuk QRIS. Jika Anda menutup halaman tersebut, mohon hubungi Admin untuk link pembayaran ulang.</p>
                                    
                                <?php elseif ($va): ?>
                                    <p style="font-weight:bold; margin-bottom:5px;"><?php echo strtoupper($method); ?> Virtual Account/Bill Key:</p>
                                    <h3 style="background:#fff; padding:10px; border:1px solid #ddd; border-radius:4px; font-size:20px; margin-top:0;"><?php echo $va; ?></h3>
                                <?php else: ?>
                                    <p>Detail pembayaran tidak tersedia. Mohon hubungi Admin.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                    
                    <div style="flex:1; min-width:300px;">
                        <h4 style="border-bottom:1px solid #eee; padding-bottom:5px; margin-top:0;">Detail Pelanggan & Pengiriman</h4>
                        <p style="margin-bottom:5px;"><strong>Kontak Pelanggan:</strong><br>WA: <?php echo esc_html($phone); ?><br>Email: <?php echo esc_html($email); ?></p>
                        <p><strong>Alamat Pengiriman:</strong><br><?php echo nl2br(esc_html($addr_str)); ?></p>
                        
                        <h4 style="border-bottom:1px solid #eee; padding-bottom:5px; margin-top:20px;">Produk Dipesan</h4>
                        <?php echo $product_display; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    // --- END FUNGSI BARU ---

    

    // --- FRONTEND SINGLE ---
    public function filter_single_product_content( $content ) {
        if ( is_singular( 'st_product' ) && in_the_loop() && is_main_query() ) {
            $id = get_the_ID(); $price = get_post_meta($id, '_st_price', true); $img = get_the_post_thumbnail_url($id, 'large');
            $type = get_post_meta($id, '_st_type', true) ?: 'physical';
            $type_label = ($type == 'digital') ? '<span class="st-badge st-badge-digital">Produk Digital</span>' : '<span class="st-badge st-badge-physical">Fisik</span>';
            $c = '<div class="st-single-layout"><div class="st-single-left">'.($img?'<img src="'.$img.'">':'').'</div><div class="st-single-right">'.$type_label.'<h1 class="st-single-title">'.get_the_title().'</h1><div class="st-single-price">Rp '.number_format($price,0,',','.').'</div><div class="st-single-desc">'.$content.'</div><button class="st-btn-add st-btn-primary st-btn-lg" data-id="'.$id.'">+ Masukkan Keranjang</button></div></div>';
            
            $cats = get_the_terms($id, 'st_cat');
            if($cats) {
                $ids = wp_list_pluck($cats, 'term_id');
                $rel = new WP_Query(array('post_type'=>'st_product','post__not_in'=>array($id),'posts_per_page'=>4,'tax_query'=>array(array('taxonomy'=>'st_cat','field'=>'term_id','terms'=>$ids))));
                if($rel->have_posts()){ $c .= '<div class="st-related-area"><h3>Produk Sejenis</h3><div class="st-grid">'; while($rel->have_posts()){ $rel->the_post(); $rp=get_post_meta(get_the_ID(),'_st_price',true); $ri=get_the_post_thumbnail_url(get_the_ID(),'medium')?:'https://via.placeholder.com/300'; $c.='<div class="st-card"><div class="st-card-img-wrap"><a href="'.get_permalink().'"><img src="'.$ri.'" class="st-card-img"></a></div><div class="st-card-body"><h4 class="st-title"><a href="'.get_permalink().'">'.get_the_title().'</a></h4><span class="st-price">Rp '.number_format($rp,0,',','.').'</span><button class="st-btn-add" data-id="'.get_the_ID().'">+ Keranjang</button></div></div>'; } $c.='</div></div>'; wp_reset_postdata(); }
            }
            return $c;
        }
        return $content;
    }

    public function render_shop_page() {
        ob_start();
        $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
        $cat = isset($_GET['kategori']) ? $_GET['kategori'] : '';
        $search = isset($_GET['cari']) ? sanitize_text_field($_GET['cari']) : '';
        $args = array('post_type' => 'st_product', 'posts_per_page' => 12, 'paged' => $paged, 's' => $search);
        if($sort == 'price_low') { $args['meta_key'] = '_st_price'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'ASC'; }
        elseif($sort == 'price_high') { $args['meta_key'] = '_st_price'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'DESC'; }
        else { $args['orderby'] = 'date'; $args['order'] = 'DESC'; }
        if($cat) { $args['tax_query'] = array( array('taxonomy' => 'st_cat', 'field' => 'slug', 'terms' => $cat) ); }
        $query = new WP_Query($args); $categories = get_terms( 'st_cat' );
        ?>
        <div class="st-wrapper"><div class="st-shop-layout"><aside class="st-sidebar"><div class="st-widget"><h3>Filter & Cari</h3><form method="get" style="margin-bottom:15px;"><input type="text" name="cari" placeholder="Cari..." value="<?php echo esc_attr($search); ?>" class="st-search-input"></form><label class="st-label">Kategori</label><select onchange="location=this.value;" class="st-select"><option value="?kategori=&sort=<?php echo $sort; ?>">Semua Kategori</option><?php foreach($categories as $c): ?><option value="?kategori=<?php echo $c->slug; ?>&sort=<?php echo $sort; ?>" <?php selected($cat, $c->slug); ?>><?php echo $c->name; ?></option><?php endforeach; ?></select><label class="st-label" style="margin-top:10px;">Urutkan</label><select onchange="location=this.value;" class="st-select"><option value="?sort=newest" <?php selected($sort, 'newest'); ?>>Terbaru</option><option value="?sort=price_low" <?php selected($sort, 'price_low'); ?>>Harga Terendah</option><option value="?sort=price_high" <?php selected($sort, 'price_high'); ?>>Harga Tertinggi</option></select></div></aside><main class="st-content"><?php if($query->have_posts()): ?><div class="st-grid"><?php while($query->have_posts()): $query->the_post(); $price = get_post_meta(get_the_ID(), '_st_price', true); $img = get_the_post_thumbnail_url(get_the_ID(), 'medium')?:'https://via.placeholder.com/400?text=Produk'; ?><div class="st-card"><div class="st-card-img-wrap"><a href="<?php the_permalink(); ?>"><img src="<?php echo esc_url($img); ?>" class="st-card-img"></a></div><div class="st-card-body"><h4 class="st-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4><span class="st-price">Rp <?php echo number_format($price, 0, ',', '.'); ?></span><button class="st-btn-add" data-id="<?php echo get_the_ID(); ?>">+ Keranjang</button></div></div><?php endwhile; ?></div><div class="st-pagination"><?php echo paginate_links( array( 'total' => $query->max_num_pages ) ); ?></div><?php else: ?><div style="padding:50px; text-align:center;"><h3>Tidak ditemukan</h3></div><?php endif; wp_reset_postdata(); ?></main></div></div>
        <?php return ob_get_clean();
    }

    // --- CHECKOUT ---
    public function render_checkout_page() {
        if ( empty( $_SESSION['st_cart'] ) ) return '<div class="st-wrapper"><p>Keranjang kosong.</p></div>';
        $cart = $_SESSION['st_cart']; $subtotal = 0; $total_weight = 0; $has_physical = false;
        foreach($cart as $pid => $qty) { 
            $p = get_post_meta($pid, '_st_price', true); $w = get_post_meta($pid, '_st_weight', true); $type = get_post_meta($pid, '_st_type', true) ?: 'physical';
            $subtotal += ($p * $qty);
            if($type !== 'digital') { $has_physical = true; $w = $w ? intval($w) : 1000; $total_weight += ($w * $qty); }
        }
        ob_start();
        ?>
        <div class="st-wrapper"><h2>Checkout</h2><form id="st-checkout-form"><input type="hidden" name="st_total_weight" id="st_total_weight" value="<?php echo $total_weight; ?>"><input type="hidden" name="st_subtotal" id="st_subtotal" value="<?php echo $subtotal; ?>"><input type="hidden" name="st_has_physical" id="st_has_physical" value="<?php echo $has_physical ? '1' : '0'; ?>"><div class="st-checkout-layout"><div class="st-col-left"><div class="st-checkout-box"><h3>Data Pembeli</h3><div class="st-row"><div class="st-form-group"><label>Nama Lengkap</label><input type="text" name="st_name" required class="st-input"></div><div class="st-form-group"><label>No. WhatsApp</label><input type="text" name="st_phone" required class="st-input"></div></div><div class="st-form-group"><label>Email</label><input type="email" name="st_email" required class="st-input"></div>
        <?php if($has_physical): ?>
        <div id="st-shipping-section"><h4 style="margin:25px 0 10px;border-bottom:1px solid #eee;padding-bottom:5px;">Alamat</h4><div class="st-row"><div class="st-form-group"><label>Provinsi</label><select name="st_prov_id" id="st_prov_id" required class="st-input st-select"><option value="">Memuat...</option></select><input type="hidden" name="st_prov_name" id="st_prov_name"></div><div class="st-form-group"><label>Kota/Kabupaten</label><select name="st_city_id" id="st_city_id" required class="st-input st-select" disabled><option value="">Pilih Provinsi dulu</option></select><input type="hidden" name="st_city_name" id="st_city_name"></div></div><div class="st-row"><div class="st-form-group"><label>Kecamatan</label><select name="st_dist_id" id="st_dist_id" required class="st-input st-select" disabled><option value="">Pilih Kota dulu</option></select><input type="hidden" name="st_dist_name" id="st_dist_name"></div><div class="st-form-group"><label>Kode Pos</label><input type="text" name="st_pos" class="st-input"></div></div><div class="st-form-group"><label>Alamat Jalan</label><textarea name="st_addr" rows="2" required class="st-input"></textarea></div><h4 style="margin:20px 0 10px;">Pengiriman (<?php echo $total_weight; ?>g)</h4><div class="st-form-group"><label>Kurir</label><select id="st_courier_code" class="st-input st-select"><option value="jne">JNE</option><option value="jnt">J&T</option><option value="pos">POS</option></select></div><div id="st_shipping_results" style="margin-top:10px;"></div></div>
        <?php else: ?><p style="background:#eff6ff;padding:10px;border-radius:6px;color:#2563eb;margin-top:20px;">Produk Digital - Tanpa Ongkir.</p><?php endif; ?>
        <input type="hidden" name="st_shipping_cost" id="st_shipping_cost" value="0"><input type="hidden" name="st_shipping_service" id="st_shipping_service" value=""></div></div><div class="st-col-right"><div class="st-checkout-box" style="margin-bottom:20px;"><h3>Ringkasan</h3><ul class="st-summary-list"><?php foreach($cart as $pid => $qty): $p = get_post_meta($pid, '_st_price', true); $thumb = get_the_post_thumbnail_url($pid, 'thumbnail')?:'https://via.placeholder.com/50'; $type = get_post_meta($pid, '_st_type', true) ?: 'physical'; ?><li class="st-summary-item"><img src="<?php echo $thumb; ?>" class="st-summary-img"><div class="st-summary-details"><div class="st-summary-title"><?php echo get_the_title($pid); ?></div><div class="st-summary-meta"><?php echo ($type=='digital'?'<span style="color:#2563eb;font-size:10px;border:1px solid #2563eb;padding:2px 4px;border-radius:3px;">DIGITAL</span> ':''); echo $qty; ?> x Rp <?php echo number_format($p, 0, ',', '.'); ?></div></div><div class="st-summary-subtotal">Rp <?php echo number_format($p*$qty, 0, ',', '.'); ?></div></li><?php endforeach; ?></ul><div class="st-calc-row"><span>Subtotal</span> <span>Rp <?php echo number_format($subtotal,0,',','.'); ?></span></div><?php if($has_physical): ?><div class="st-calc-row"><span>Ongkir</span> <span id="display_ongkir">Rp 0</span></div><?php endif; ?><div class="st-calc-row"><span>Fee</span> <span id="display_fee">Rp 0</span></div><div class="st-total-row"><span>Total</span> <span id="display_total">Rp <?php echo number_format($subtotal,0,',','.'); ?></span></div></div><div class="st-checkout-box"><h3>Pembayaran</h3><div class="st-payment-list"><?php foreach($this->payment_channels as $key => $data): if( get_option('st_enable_'.$key) ): ?><label class="st-pay-item"><input type="radio" name="st_method" value="<?php echo $key; ?>" data-type="<?php echo $data['type']; ?>" required><div class="st-pay-icon"><img src="<?php echo $data['icon']; ?>"></div><span class="st-pay-label"><?php echo $data['label']; ?></span></label><?php endif; endforeach; ?></div><button type="submit" id="st-btn-pay" class="st-btn-primary" style="margin-top:20px;font-size:16px;">BAYAR SEKARANG</button></div></div></div></form><div id="st-checkout-result" style="margin-top:20px;"></div></div>
        <?php return ob_get_clean();
    }
    
    // --- ADMIN ORDER META BOX (P3 FIX) ---
    public function add_order_details_meta() {
        add_meta_box( 
            'st_order_details', 
            'Rincian Pesanan Pelanggan', 
            array( $this, 'render_order_details_meta_box' ), 
            'st_order', 
            'normal', 
            'high' 
        );
    }
    
    public function render_order_details_meta_box( $post ) {
        $order_id = $post->ID;
        
        $tot = get_post_meta($order_id, '_st_total', true);
        $subtotal = get_post_meta($order_id, '_st_subtotal', true) ?: 0;
        $ongkir = get_post_meta($order_id, '_st_ongkir', true) ?: 0;
        $fee = get_post_meta($order_id, '_st_fee', true) ?: 0;
        
        $method = get_post_meta($order_id, '_st_method', true);
        $status = get_post_meta($order_id, '_st_status', true) ?: 'pending';
        $addr_str = get_post_meta($order_id, '_st_address', true) ?: 'Data alamat tidak ditemukan.';
        $va = get_post_meta($order_id, '_st_payment_code', true) ?: '-';
        $qty = get_post_meta($order_id, '_st_qty', true) ?: '1'; // Hanya untuk Direct Checkout, tapi tetap ditampilkan
        $email = get_post_meta($order_id, '_st_email', true) ?: 'N/A';
        $phone = get_post_meta($order_id, '_st_phone', true) ?: 'N/A';
        $pid = get_post_meta($order_id, '_st_product_id', true); // Hanya untuk Direct Checkout
        $cart_items = get_post_meta($order_id, '_st_cart_items', true); // Untuk Checkout Standar

        $product_info = '';
        if ($pid) { // Direct Checkout
            $product_name = get_the_title($pid) ?: 'Produk Tidak Ditemukan';
            $product_info .= '<p style="margin-bottom:5px;"><strong>Produk:</strong> ' . esc_html($product_name) . ' (ID #' . esc_html($pid) . ')</p>';
            $product_info .= '<p><strong>Kuantitas:</strong> ' . esc_html($qty) . '</p>';
        } elseif (!empty($cart_items) && is_array($cart_items)) { // Standard Checkout
            $product_info .= '<p style="font-weight:bold; margin-bottom:5px;">Daftar Item:</p><ul style="list-style:disc; margin-left:20px;">';
            foreach ($cart_items as $item_pid => $item_qty) {
                $item_name = get_the_title($item_pid) ?: 'Produk Tidak Ditemukan';
                $product_info .= '<li>' . esc_html($item_name) . ' (ID #' . $item_pid . ') x ' . esc_html($item_qty) . '</li>';
            }
            $product_info .= '</ul>';
        } else {
            $product_info = '<p style="color:#eab308;">Data item tidak ditemukan. Mungkin order lama atau order digital tanpa ID produk.</p>';
        }
        
        $color_map = ['settlement' => '#16a34a', 'capture' => '#16a34a', 'pending' => '#ea580c', 'failed' => '#dc2626', 'expire' => '#dc2626', 'cancel' => '#dc2626'];
        $color = $color_map[$status] ?? '#64748b';
        
        ?>
        <div style="display:flex; gap:30px; flex-wrap:wrap; background:#f8fafc; padding:20px; border-radius:8px;">
            <div style="flex:1; min-width:250px; border-right: 1px solid #eee; padding-right: 20px;">
                <h4>Status Pesanan</h4>
                <p style="font-size:18px; font-weight:bold; color:<?php echo $color; ?>; margin-top:5px;"><?php echo strtoupper($status); ?></p>
                <hr style="border:0; border-top:1px solid #eee; margin:15px 0;">
                
                <h4>Data Pelanggan</h4>
                <p><strong>Email:</strong> <?php echo esc_html($email); ?></p>
                <p><strong>WhatsApp:</strong> <?php echo esc_html($phone); ?></p>
            </div>
            
            <div style="flex:1; min-width:250px;">
                <h4>Rincian Pembayaran</h4>
                <table style="width:100%; font-size:14px; margin-bottom:10px;">
                    <tr><th style="text-align:left; padding:5px 0;">Metode:</th><td style="text-align:right;"><strong><?php echo strtoupper($method); ?></strong></td></tr>
                    <tr><th style="text-align:left; padding:5px 0;">Kode VA/Bill Key:</th><td style="text-align:right; font-weight:bold;"><?php echo esc_html($va); ?></td></tr>
                </table>
                <hr style="border:0; border-top:1px solid #eee; margin:15px 0;">
                
                <h4>Detail Harga (Final)</h4>
                <table style="width:100%; font-size:14px;">
                    <tr><th style="text-align:left; padding:5px 0;">Subtotal Produk:</th><td style="text-align:right;">Rp <?php echo number_format($subtotal,0,',','.'); ?></td></tr>
                    <tr><th style="text-align:left; padding:5px 0;">Ongkos Kirim:</th><td style="text-align:right;">Rp <?php echo number_format($ongkir,0,',','.'); ?></td></tr>
                    <tr><th style="text-align:left; padding:5px 0;">Biaya Layanan (Fee):</th><td style="text-align:right;">Rp <?php echo number_format($fee,0,',','.'); ?></td></tr>
                    <tr><th style="text-align:left; padding:5px 0; border-top:1px solid #ddd;">Total Akhir:</th><td style="text-align:right; border-top:1px solid #ddd;"><strong style="font-size:16px;">Rp <?php echo number_format($tot,0,',','.'); ?></strong></td></tr>
                </table>
            </div>
        </div>
        
        <h4 style="margin-top:20px;">Detail Item</h4>
        <?php echo $product_info; ?>

        <h4 style="margin-top:20px;">Alamat Pengiriman</h4>
        <div style="padding:15px; border:1px solid #ddd; border-radius:4px; background:#fff;">
            <?php echo nl2br(esc_html($addr_str)); ?>
        </div>
        <?php
    }
    
    
    // --- API & PROCESS ---
    private function call_ro($endpoint) {
        $key = get_option('st_ro_key'); $url = "https://rajaongkir.komerce.id/api/v1/" . $endpoint;
        $res = wp_remote_get($url, array('headers' => array('key' => $key)));
        return json_decode(wp_remote_retrieve_body($res), true);
    }
    public function ajax_get_locations() {
        $type = $_GET['type'];
        if($type == 'province') $data = $this->call_ro("destination/province");
        elseif($type == 'city') $data = $this->call_ro("destination/city/" . $_GET['id']);
        elseif($type == 'district') $data = $this->call_ro("destination/district/" . $_GET['id']);
        wp_send_json_success($data['data'] ?? []);
    }
    public function ajax_check_shipping() {
        $res = wp_remote_post("https://rajaongkir.komerce.id/api/v1/calculate/district/domestic-cost", array('headers'=>array('key'=>get_option('st_ro_key'),'Content-Type'=>'application/x-www-form-urlencoded'),'body'=>array('origin'=>get_option('st_origin_dist'),'destination'=>$_POST['destination'],'weight'=>$_POST['weight'],'courier'=>$_POST['courier'],'price'=>'lowest')));
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if(isset($data['data'])) wp_send_json_success($data['data']); else wp_send_json_error();
    }

    public function webhook_listener() {
    if ( isset($_GET['st_listener']) && $_GET['st_listener'] == 'midtrans' ) {
        
        // 1. Ambil Data JSON Mentah dari Midtrans
        $json_result = file_get_contents('php://input');

        // 2. FORWARD KE URL LAIN (Mode: Non-Blocking)
        // 'blocking' => false membuat script TIDAK menunggu balasan dari mitraindonesia.
        // Script akan langsung lanjut ke baris 'http_response_code(200)' di bawahnya.
        wp_remote_post( 'https://101payasia.com/callback/midtrans', array(
            'method'    => 'POST',
            'headers'   => array( 'Content-Type' => 'application/json' ),
            'body'      => $json_result, // Kirim ulang data mentah apa adanya
            'timeout'   => 5,            
            'blocking'  => false,        // <--- KUNCI AGAR PROSES CEPAT
            'sslverify' => false
        ));

        // 3. LANGSUNG KIRIM 200 OK KE MIDTRANS
        // Midtrans akan menerima ini secara instan karena forwarding di atas tidak ditunggu.
        http_response_code( 200 );
        echo "OK";
        exit;
        }
    }

    public function process_checkout() {
        $name = sanitize_text_field($_POST['st_name']); 
        $method = sanitize_text_field($_POST['st_method']);
        $ongkir = intval($_POST['st_shipping_cost']);
        $has_physical = $_POST['st_has_physical'] === '1';
        $email = sanitize_email($_POST['st_email']);
        $phone = sanitize_text_field($_POST['st_phone']);
        
        // --- LOGIKA OTOMATIS MEMBUAT USER ---
        $user_id = email_exists($email);
        if(!$user_id) {
            $username = sanitize_user(explode('@', $email)[0]);
            $password = wp_generate_password(12, true, true);
            $i = 1;
            $original_username = $username;
            while(username_exists($username)) {
                $username = $original_username . $i;
                $i++;
            }
            $user_id = wp_create_user($username, $password, $email);
            if(!is_wp_error($user_id)) {
                 wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
            }
        }
        // --- END USER ---

        $full_addr = 'Digital Order';
        if($has_physical) {
            // PERBAIKAN: Mengumpulkan alamat lengkap dan provinsi
            $full_addr = sanitize_text_field($_POST['st_addr']) 
                        . ', Kec. ' . sanitize_text_field($_POST['st_dist_name']) 
                        . ', ' . sanitize_text_field($_POST['st_city_name']) 
                        . ', Prov. ' . sanitize_text_field($_POST['st_prov_name']);
        }
        
        if(empty($method)) wp_send_json_error('Pilih pembayaran.');
        $cart = $_SESSION['st_cart']; 
        $subtotal = 0; 
        foreach($cart as $pid => $qty) { 
            $subtotal += (get_post_meta($pid, '_st_price', true) * $qty); 
        }
        
        // --- PERHITUNGAN RINCI HARGA ---
        $gross_amount_before_fee = $subtotal + $ongkir;
        $is_qris = ($method == 'qris');
        $fee = $is_qris ? ceil($gross_amount_before_fee * 0.017) : 5000; // Pembulatan ke atas
        $gross_amount = $gross_amount_before_fee + $fee; // Total
        // --- END PERHITUNGAN RINCI HARGA ---

        $order_id = wp_insert_post(array('post_type' => 'st_order', 'post_title' => 'Order #'.time().' - '.$name, 'post_status' => 'publish'));
        
        // --- PENYIMPANAN DATA LENGKAP HARGA DAN PELANGGAN (FIX UTAMA) ---
        update_post_meta($order_id, '_st_total', $gross_amount);
        update_post_meta($order_id, '_st_method', $method);
        update_post_meta($order_id, '_st_address', $full_addr);
        update_post_meta($order_id, '_st_email', $email);
        update_post_meta($order_id, '_st_phone', $phone);
        update_post_meta($order_id, '_st_cart_items', $cart);
        
        update_post_meta($order_id, '_st_subtotal', $subtotal); // << BARU: Subtotal
        update_post_meta($order_id, '_st_ongkir', $ongkir);     // << BARU: Ongkir
        update_post_meta($order_id, '_st_fee', $fee);            // << BARU: Fee
        // --- END FIX ---
        
        $server_key = get_option('st_server_key'); $is_sandbox = get_option('st_is_sandbox');
        $params = array(
            'transaction_details' => array('order_id' => $order_id . '-' . time(), 'gross_amount' => ceil($gross_amount)),
            'customer_details'    => array('first_name' => $name, 'email' => $_POST['st_email'], 'phone' => $_POST['st_phone']),
            'item_details'        => array(array('id'=>'ITEM1','price'=>ceil($gross_amount),'quantity'=>1,'name'=>'Total Pembayaran'))
        );

        if ($method == 'qris') { $params['payment_type'] = 'qris'; $params['qris'] = array('acquirer' => 'gopay'); } 
        elseif ($method == 'mandiri') { $params['payment_type'] = 'echannel'; $params['echannel'] = array('bill_info1' => 'Payment', 'bill_info2' => 'Online Store'); } 
        elseif ($method == 'permata') { $params['payment_type'] = 'permata'; } 
        else { $params['payment_type'] = 'bank_transfer'; $params['bank_transfer'] = array('bank' => $method); }

        if ( class_exists('Simple_Midtrans_API') ) {
            $response = Simple_Midtrans_API::request( $params, $server_key, $is_sandbox );
            if( isset($response->status_code) && in_array($response->status_code, ['200', '201']) ) {
                unset($_SESSION['st_cart']); $_SESSION['st_orders'][] = $order_id;
                $html = '<div style="text-align:center; padding:30px; background:#fff; border:1px solid #ddd; border-radius:10px;">';
                $html .= '<h3 style="color:#16a34a">Pesanan Berhasil Dibuat!</h3><p>Total Bayar: <strong>Rp '.number_format($gross_amount,0,',','.').'</strong></p>';
                if($method == 'qris') { $html .= '<p>Scan QRIS di bawah ini:</p><img src="'.$response->actions[0]->url.'" style="width:200px; margin:10px auto; display:block;">'; } 
                elseif($method == 'mandiri') { $html .= '<p>Biller Code: <strong>'.$response->biller_code.'</strong></p><p>Bill Key: <strong>'.$response->bill_key.'</strong></p>'; update_post_meta($order_id, '_st_payment_code', $response->bill_key); } 
                else { $va = isset($response->permata_va_number) ? $response->permata_va_number : (isset($response->va_numbers[0]->va_number) ? $response->va_numbers[0]->va_number : '-'); $html .= '<p>Nomor Virtual Account ('.$method.'):</p><h2 style="background:#f1f5f9; padding:15px; border-radius:5px;">'.$va.'</h2>'; update_post_meta($order_id, '_st_payment_code', $va); }
                $html .= '<br><a href="/akun" class="st-btn-primary">Lihat Status Pesanan</a></div>';
                wp_send_json_success(array('html' => $html));
            } else { $err = isset($response->status_message) ? $response->status_message : 'Gagal'; wp_send_json_error('Midtrans Error: ' . $err); }
        } else { wp_send_json_error('API Class Missing'); }
    }

    // --- ASSETS & JS ---
    public function enqueue_assets() {
        wp_enqueue_script('jquery'); wp_register_style( 'st-style', false ); wp_enqueue_style( 'st-style' );
        $css = "
        /* Global & Reset */
        .st-wrapper { max-width: 1100px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1e293b; } 
        /* Grid & Card */
        .st-shop-layout { display: flex; gap: 30px; margin-top: 20px; align-items: flex-start; } .st-sidebar { width: 260px; flex-shrink: 0; } .st-widget { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; } .st-search-input, .st-select, .st-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; } .st-content { flex-grow: 1; } .st-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; } .st-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; transition: transform 0.2s; } .st-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); } .st-card-img-wrap { padding-top: 100%; position: relative; } .st-card-img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; } .st-card-body { padding: 15px; } .st-title { font-size: 15px; margin: 0 0 5px; height: 40px; overflow: hidden; line-height: 1.3; font-weight: 600; } .st-title a { text-decoration: none; color: #333; } .st-price { font-weight: bold; color: #059669; display: block; margin-bottom: 10px; } .st-btn-add { background: #fff; color: #2563eb; border: 1px solid #2563eb; padding: 8px; border-radius: 6px; width: 100%; cursor: pointer; font-weight: 600; transition:0.2s; } .st-btn-add:hover { background: #2563eb; color: #fff; }
        /* Single Product */
        .st-single-layout { display: flex; gap: 30px; margin-bottom: 40px; background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; } .st-single-left { flex: 1; } .st-single-left img { width: 100%; border-radius: 8px; } .st-single-right { flex: 1.2; } .st-single-title { margin-top: 0; font-size: 24px; } .st-single-price { font-size: 20px; color: #059669; font-weight: bold; margin-bottom: 20px; } .st-single-desc { line-height: 1.6; color: #475569; margin-bottom: 20px; } .st-badge { display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: bold; border-radius: 4px; margin-bottom: 10px; text-transform: uppercase; } .st-badge-digital { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; } .st-badge-physical { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; } .st-related-area h3 { border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        /* Checkout */
        .st-checkout-layout { display: flex; gap: 30px; margin-top: 20px; align-items: flex-start; } .st-col-left { flex: 2; } .st-col-right { flex: 1; min-width: 320px; position: sticky; top: 20px; } .st-checkout-box { background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; } .st-row { display: flex; gap: 15px; } .st-row .st-form-group { flex: 1; } .st-form-group { margin-bottom: 15px; } .st-summary-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; align-items: center; } .st-summary-img { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; background: #eee; flex-shrink: 0; } .st-summary-details { flex: 1; } .st-summary-subtotal { font-weight: bold; } .st-calc-row { display: flex; justify-content: space-between; margin-top: 10px; color: #64748b; } .st-total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 18px; margin-top: 15px; padding-top: 10px; border-top: 2px solid #e2e8f0; } .st-payment-list { display: flex; flex-direction: column; gap: 10px; } .st-pay-item { display: flex; align-items: center; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: 0.2s; background: #fff; } .st-pay-item:has(input:checked) { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 1px #2563eb; } .st-pay-icon { width: 40px; margin-right: 15px; margin-left: 15px; } .st-pay-icon img { width: 100%; } .st-ship-opt { border:1px solid #eee; padding:10px; margin-bottom:5px; border-radius:6px; cursor:pointer; display:flex; justify-content:space-between; } .st-ship-opt:hover { background:#f8fafc; } .st-ship-opt.selected { border-color:#2563eb; background:#eff6ff; } .st-btn-primary { background: #2563eb; color: white; padding: 15px; border: none; width: 100%; border-radius: 8px; font-weight: bold; cursor: pointer; display:block; text-align:center; text-decoration:none; transition:0.2s; } .st-btn-primary:hover { background: #1d4ed8; }
        /* Floating & Side Cart */
        .st-float-btn { position: fixed; bottom: 30px; right: 30px; background: #2563eb; color: #fff; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 9998; box-shadow: 0 10px 20px rgba(37,99,235,0.3); transition: transform 0.2s; } .st-float-btn:hover { transform: scale(1.1); } .st-cart-count { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; font-size: 12px; font-weight: bold; } .st-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998; display: none; backdrop-filter: blur(2px); } .st-side-cart { position: fixed; top: 0; right: -400px; width: 380px; height: 100%; background: #fff; z-index: 9999; transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1); padding: 0; box-sizing: border-box; display:flex; flex-direction:column; box-shadow: -5px 0 25px rgba(0,0,0,0.1); } .st-side-cart.open { right: 0; } .st-cart-header { padding: 20px; border-bottom: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc; } .st-cart-header h3 { margin:0; font-size:18px; } .st-close-cart { border:none; background:none; font-size:24px; cursor:pointer; color:#64748b; } .st-mini-list { list-style: none; padding: 20px; margin: 0; flex:1; overflow-y:auto; } .st-mini-list li { display: flex; gap: 15px; margin-bottom: 20px; align-items: flex-start; padding-bottom: 20px; border-bottom: 1px solid #f1f5f9; position: relative; } .st-mini-img { width: 70px; height: 70px; border-radius: 8px; object-fit: cover; background: #f1f5f9; } .st-mini-info h4 { margin: 0 0 5px; font-size: 14px; line-height: 1.4; padding-right: 20px; } .st-mini-info div { font-size: 13px; color: #64748b; } .st-remove-mini { position: absolute; top: 0; right: 0; background: none; border: none; color: #cbd5e1; cursor: pointer; font-size: 20px; line-height: 1; padding: 0; } .st-remove-mini:hover { color: #ef4444; } .st-cart-footer { padding: 20px; border-top: 1px solid #e2e8f0; background: #fff; } @media (max-width: 768px) { .st-shop-layout, .st-checkout-layout, .st-row, .st-single-layout { flex-direction: column; } .st-sidebar, .st-col-left, .st-col-right { width: 100%; } .st-side-cart { width: 85%; } }";
        wp_add_inline_style( 'st-style', $css );
    }

    public function print_footer_html_scripts() { 
        $count = isset($_SESSION['st_cart']) ? array_sum($_SESSION['st_cart']) : 0;
        ?>
        <div class="st-float-btn" id="st-cart-trigger"><span class="st-cart-count" id="st-float-count"><?php echo $count; ?></span><svg width="24" height="24" fill="white" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg></div>
        <div class="st-backdrop" id="st-backdrop"></div><div class="st-side-cart" id="st-side-cart"><div class="st-cart-header"><h3>Keranjang</h3><button class="st-close-cart" style="border:none;background:none;font-size:24px;cursor:pointer;">&times;</button></div><div id="st-cart-body-content" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;"><?php echo $this->get_mini_cart_html(); ?></div></div>
        <script>
        jQuery(document).ready(function($){
            function rp(n){ return 'Rp ' + new Intl.NumberFormat('id-ID').format(n); }
            function openCart(){$('#st-backdrop').fadeIn();$('#st-side-cart').addClass('open');}
            function closeCart(){$('#st-side-cart').removeClass('open');$('#st-backdrop').fadeOut();}
            $('#st-cart-trigger').click(openCart); $('.st-close-cart, #st-backdrop').click(closeCart);
            $(document).on('click','.st-btn-add',function(e){ e.preventDefault(); var btn=$(this); var t=btn.text(); btn.text('...').prop('disabled',true); $.post('<?php echo admin_url('admin-ajax.php'); ?>',{action:'st_add_to_cart',product_id:btn.data('id')},function(r){ if(r.success){$('#st-float-count').text(r.data.count);$('#st-cart-body-content').html(r.data.html);btn.text('✔');setTimeout(function(){btn.text(t).prop('disabled',false);openCart();},500);}}); });
            $(document).on('click','.st-remove-mini',function(){ $.post('<?php echo admin_url('admin-ajax.php'); ?>',{action:'st_remove_cart',product_id:$(this).data('id')},function(r){if(r.success){$('#st-float-count').text(r.data.count);$('#st-cart-body-content').html(r.data.html);}}); });
            if($('#st_prov_id').length){
                $.get('<?php echo admin_url('admin-ajax.php'); ?>?action=st_get_locations&type=province', function(res){ if(res.success){ var o='<option value="">Pilih Provinsi</option>'; $.each(res.data,function(i,v){o+='<option value="'+v.id+'">'+v.name+'</option>'}); $('#st_prov_id').html(o); } });
                $('#st_prov_id').change(function(){ var id=$(this).val(); $('#st_prov_name').val($("#st_prov_id option:selected").text()); $('#st_city_id').html('<option>Loading...</option>').prop('disabled',true); $.get('<?php echo admin_url('admin-ajax.php'); ?>?action=st_get_locations&type=city&id='+id,function(res){ var o='<option value="">Pilih Kota</option>'; $.each(res.data,function(i,v){o+='<option value="'+v.id+'">'+v.name+'</option>'}); $('#st_city_id').html(o).prop('disabled',false); }); });
                $('#st_city_id').change(function(){ var id=$(this).val(); $('#st_city_name').val($("#st_city_id option:selected").text()); $('#st_dist_id').html('<option>Loading...</option>').prop('disabled',true); $.get('<?php echo admin_url('admin-ajax.php'); ?>?action=st_get_locations&type=district&id='+id,function(res){ var o='<option value="">Pilih Kecamatan</option>'; $.each(res.data,function(i,v){o+='<option value="'+v.id+'">'+v.name+'</option>'}); $('#st_dist_id').html(o).prop('disabled',false); }); });
                $('#st_dist_id').change(function(){ $('#st_dist_name').val($("#st_dist_id option:selected").text()); calc(); });
                $('#st_courier_code').change(calc);
                function calc(){ var d=$('#st_dist_id').val(), c=$('#st_courier_code').val(), w=$('#st_total_weight').val(); if(!d||!c)return; $('#st_shipping_results').html('Checking...'); $.post('<?php echo admin_url('admin-ajax.php'); ?>',{action:'st_check_shipping',destination:d,weight:w,courier:c},function(res){ if(res.success){ var h=''; $.each(res.data,function(i,v){ h+='<div class="st-ship-opt" data-c="'+v.cost+'" data-s="'+v.code.toUpperCase()+' '+v.service+'"><div><b>'+v.code.toUpperCase()+' '+v.service+'</b><br><small>'+v.description+'</small></div><div>'+rp(v.cost)+'</div></div>'; }); $('#st_shipping_results').html(h); } else { $('#st_shipping_results').html('Error/Tidak tersedia'); } }); }
                $(document).on('click','.st-ship-opt',function(){ $('.st-ship-opt').removeClass('selected'); $(this).addClass('selected'); $('#st_shipping_cost').val($(this).data('c')); $('#st_shipping_service').val($(this).data('s')); $('#display_ongkir').text(rp($(this).data('c'))); updTot(); });
            }
            $('input[name="st_method"]').change(updTot);
            function updTot(){ var s=parseInt($('#st_subtotal').val()), o=parseInt($('#st_shipping_cost').val())||0, t=s+o, f=0; var m=$('input[name="st_method"]:checked').data('type'); if(m=='qris') f=t*0.017; else if(m=='va') f=5000; $('#display_fee').text(rp(f)); $('#display_total').text(rp(t+f)); }
            $('#st-checkout-form').submit(function(e){ e.preventDefault(); var btn=$('#st-btn-pay'); btn.text('Memproses...').prop('disabled',true); $.ajax({ url:'<?php echo admin_url('admin-ajax.php'); ?>', type:'POST', data:$(this).serialize()+'&action=st_process_checkout', success:function(res){ if(res.success){ $('#st-checkout-result').html(res.data.html); $('.st-checkout-layout').slideUp(); $('#st-float-count').text('0'); } else { alert(res.data); btn.text('BAYAR SEKARANG').prop('disabled',false); } }}); });
        });
        </script>
    <?php }

    // --- HELPER CART ---
    private function get_mini_cart_html() {
        $cart = $_SESSION['st_cart']; $html = ''; 
        if ( empty($cart) ) { $html .= '<div style="padding:20px;text-align:center;"><p>Kosong.</p></div>'; } 
        else { $html .= '<ul class="st-mini-list">'; foreach($cart as $pid => $qty) { $p = get_post_meta($pid, '_st_price', true); $title = get_the_title($pid); $img = get_the_post_thumbnail_url($pid, 'thumbnail')?:'https://via.placeholder.com/50'; $html .= '<li><img src="'.$img.'" class="st-mini-img"><div class="st-mini-info"><h4>'.$title.'</h4><div>'.$qty.' x '.number_format($p).'</div></div><button class="st-remove-mini" data-id="'.$pid.'">&times;</button></li>'; } $html .= '</ul><div class="st-cart-footer"><a href="/checkout" class="st-btn-primary st-block">Checkout</a></div>'; }
        return $html;
    }
    public function ajax_add_to_cart() { $pid = intval($_POST['product_id']); if(!isset($_SESSION['st_cart'][$pid])) $_SESSION['st_cart'][$pid]=0; $_SESSION['st_cart'][$pid]++; wp_send_json_success(array('count' => array_sum($_SESSION['st_cart']), 'html'  => $this->get_mini_cart_html())); }
    public function ajax_remove_cart() { $pid = intval($_POST['product_id']); if(isset($_SESSION['st_cart'][$pid])) unset($_SESSION['st_cart'][$pid]); wp_send_json_success(array('count' => array_sum($_SESSION['st_cart']), 'html'  => $this->get_mini_cart_html())); }
    public function render_cart_page() { return '<div class="st-wrapper"><h2>Keranjang</h2><div id="st-cart-page-content">'. $this->get_mini_cart_html() .'</div></div>'; }
}

new Simple_Toko_Pro();

// >>> 4. DAFTARKAN WIDGET ELEMENTOR DI HOOK YANG SAMA <<<
add_action( 'elementor/init', function() {
    if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;
    
    // Widget Lama
    class Elementor_ST_Shop extends \Elementor\Widget_Base { public function get_name() { return 'st_shop'; } public function get_title() { return 'Toko: Katalog'; } public function get_icon() { return 'eicon-products'; } public function get_categories() { return [ 'st_category' ]; } protected function render() { echo do_shortcode('[st_shop]'); } }
    class Elementor_ST_Cart extends \Elementor\Widget_Base { public function get_name() { return 'st_cart'; } public function get_title() { return 'Toko: Keranjang'; } public function get_icon() { return 'eicon-cart'; } public function get_categories() { return [ 'st_category' ]; } protected function render() { echo do_shortcode('[st_cart]'); } }
    class Elementor_ST_Checkout extends \Elementor\Widget_Base { public function get_name() { return 'st_checkout'; } public function get_title() { return 'Toko: Checkout'; } public function get_icon() { return 'eicon-checkout'; } public function get_categories() { return [ 'st_category' ]; } protected function render() { echo do_shortcode('[st_checkout]'); } }
    
    // Daftarkan Kategori
    add_action( 'elementor/elements/categories_registered', function( $m ) { $m->add_category( 'st_category', [ 'title' => 'Simple Toko Ultimate', 'icon'  => 'fa fa-shopping-bag' ] ); });
    
    // Daftarkan Widget
    add_action( 'elementor/widgets/register', function( $m ) { 
        $m->register( new Elementor_ST_Shop() ); 
        $m->register( new Elementor_ST_Cart() ); 
        $m->register( new Elementor_ST_Checkout() );
        
        // >>> DAFTARKAN WIDGET BARU DARI FILE MODUL (Jika class ada) <<<
        if(class_exists('Elementor_ST_Direct_Checkout')) {
            $m->register( new Elementor_ST_Direct_Checkout() );
        }
    });
});