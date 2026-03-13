<?php
defined( 'ABSPATH' ) || exit;

class Simple_Toko_Direct {
    
    // Daftar Payment Channel LENGKAP
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
        add_action('wp_head', array($this, 'print_fix_styles'));
        add_shortcode('st_landing_form', array($this, 'render_landing_form'));
        add_action('wp_ajax_st_process_direct', array($this, 'process_direct_order'));
        add_action('wp_ajax_nopriv_st_process_direct', array($this, 'process_direct_order'));
        add_action('wp_footer', array($this, 'print_landing_scripts'));
    }

    public function print_fix_styles() {
        ?>
        <style>
            /* Layout Width Fix */
            .st-landing-wrapper { max-width: 800px; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.08); margin: 0 auto; }
            
            /* Previous Styles */
            body.single-st_product .post-thumbnail, body.single-st_product .entry-thumbnail, 
            body.single-st_product .wp-post-image, body.single-st_product .featured-image { display: none !important; }
            body.single-st_product .st-single-left img { display: block !important; opacity: 1 !important; visibility: visible !important; }
            .st-landing-header { text-align: center; margin-bottom: 25px; border-bottom: 2px dashed #f1f5f9; padding-bottom: 20px; }
            .st-landing-prod-img { width: 100px; height: 100px; border-radius: 10px; object-fit: cover; margin-bottom: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .st-landing-total { background: #f0fdf4; color: #166534; padding: 15px; border-radius: 8px; margin: 20px 0; font-weight: bold; display: flex; justify-content: space-between; font-size: 18px; border: 1px solid #bbf7d0; }
            
            /* New Styling for Grouped Payments */
            .st-pay-item.st-group-trigger { margin-bottom: 15px; }
            .st-pay-item.active { border: 2px solid #2563eb !important; background: #eff6ff !important; }
            .st-va-list-expanded { padding: 15px; border: 1px dashed #ccc; border-radius: 8px; margin-top: -10px; margin-bottom: 15px; }
            .st-sub-item:has(input:checked) { border-color: #2563eb !important; background: #eff6ff !important; box-shadow: 0 0 0 1px #2563eb; }
            
            /* Quantity Styles */
            .st-qty-options { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
            .st-qty-options label {
                padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer;
                transition: all 0.2s; font-weight: 500; font-size: 14px;
            }
            .st-qty-options input:checked + label {
                background: #eff6ff; border-color: #2563eb; color: #2563eb; font-weight: bold;
            }
            .st-qty-options input { display: none; }
            
            /* Breakdown Styles */
            .st-calc-row { display: flex; justify-content: space-between; margin-top: 5px; color: #64748b; font-size: 14px; }
            .st-total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 16px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0; }
        </style>
        <?php
    }

    public function render_landing_form($atts) {
        $a = shortcode_atts(array(
            'id' => 0, 
            'qty_options' => '[]'
        ), $atts);
        
        $pid = intval($a['id']);
        if(!$pid) return '<p>Pilih produk terlebih dahulu.</p>';
        
        $price = get_post_meta($pid, '_st_price', true);
        $weight = get_post_meta($pid, '_st_weight', true) ?: 1000;
        $type = get_post_meta($pid, '_st_type', true) ?: 'physical';
        $img = get_the_post_thumbnail_url($pid, 'thumbnail') ?: 'https://via.placeholder.com/100';
        $title = get_the_title($pid);

        $qty_options = json_decode(html_entity_decode($a['qty_options']), true);
        if (empty($qty_options)) {
            $qty_options = array(
                array('qty_value' => 1, 'qty_label' => '1 Buah'),
                array('qty_value' => 2, 'qty_label' => '2 Buah (Hemat)'),
            );
        }

        $qris_data = $this->payment_channels['qris'];
        $va_data = array_filter($this->payment_channels, function($key) {
            return $key !== 'qris';
        }, ARRAY_FILTER_USE_KEY);

        ob_start();
        ?>
        <div class="st-landing-wrapper" id="st-landing-area-<?php echo $pid; ?>">
            <div class="st-landing-header">
                <img src="<?php echo $img; ?>" class="st-landing-prod-img">
                <h3 style="margin:0; font-size:18px; line-height:1.4;"><?php echo $title; ?></h3>
                <div style="color:#64748b; font-weight:bold; margin-top:5px;">Rp <?php echo number_format($price,0,',','.'); ?></div>
            </div>

            <form class="st-direct-form" data-id="<?php echo $pid; ?>" data-price="<?php echo $price; ?>" data-weight="<?php echo $weight; ?>">
                <div class="st-row">
                    <div class="st-form-group"><label>Nama Lengkap</label><input type="text" name="st_name" required class="st-input"></div>
                    <div class="st-form-group"><label>WhatsApp</label><input type="text" name="st_phone" required class="st-input"></div>
                </div>
                <div class="st-form-group"><label>Email</label><input type="email" name="st_email" required class="st-input"></div>

                <div class="st-form-group">
                    <label>Pilih Kuantitas</label>
                    <div class="st-qty-options">
                        <?php foreach($qty_options as $i => $opt): ?>
                        <input type="radio" id="st_qty_<?php echo $i; ?>" name="st_qty" value="<?php echo intval($opt['qty_value']); ?>" <?php checked($i, 0); ?> required>
                        <label for="st_qty_<?php echo $i; ?>"><?php echo esc_html($opt['qty_label']); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if($type !== 'digital'): ?>
                <div class="st-direct-shipping">
                    <h4 style="margin:20px 0 10px; border-bottom:1px solid #eee; padding-bottom:5px;">Alamat Pengiriman</h4>
                    <div class="st-row">
                        <div class="st-form-group">
                            <label>Provinsi</label>
                            <select name="st_prov_id" class="st-input st-select st_prov_trigger"><option value="">Memuat...</option></select>
                            <input type="hidden" name="st_prov_name">
                        </div>
                        <div class="st-form-group">
                            <label>Kota/Kab</label>
                            <select name="st_city_id" class="st-input st-select st_city_trigger" disabled><option value="">-</option></select>
                            <input type="hidden" name="st_city_name">
                        </div>
                    </div>
                    <div class="st-row">
                        <div class="st-form-group">
                            <label>Kecamatan</label>
                            <select name="st_dist_id" class="st-input st-select st_dist_trigger" disabled><option value="">-</option></select>
                            <input type="hidden" name="st_dist_name">
                        </div>
                        <div class="st-form-group"><label>Kode Pos</label><input type="text" name="st_pos" class="st-input"></div>
                    </div>
                    <div class="st-form-group"><label>Alamat Lengkap</label><textarea name="st_addr" rows="2" class="st-input" required placeholder="Nama Jalan, RT/RW..."></textarea></div>
                    
                    <div class="st-form-group">
                        <label>Pilih Kurir</label>
                        <select name="st_courier" class="st-input st-select st_courier_trigger">
                            <option value="jne">JNE</option><option value="jnt">J&T</option><option value="pos">POS</option>
                        </select>
                    </div>
                    <div class="st_shipping_results" style="margin-top:10px;"></div>
                    <input type="hidden" name="st_shipping_cost" class="st_shipping_cost" value="0">
                    <input type="hidden" name="st_shipping_service" class="st_shipping_service">
                </div>
                <?php else: ?>
                    <input type="hidden" name="is_digital" value="1">
                    <div style="background:#eff6ff; padding:10px; border-radius:6px; color:#2563eb; text-align:center; font-size:13px; margin:20px 0;">Produk Digital (Dikirim via Email/WA)</div>
                <?php endif; ?>

                <h4 style="margin:20px 0 10px; border-bottom:1px solid #eee; padding-bottom:5px;">Pembayaran</h4>
                <div class="st-payment-list">
                    
                    <?php // OPSI 1: QRIS
                    if(get_option('st_enable_qris')): ?>
                    <label class="st-pay-item st-qris-trigger st-pay-trigger">
                        <input type="radio" name="st_method_final" value="qris" data-type="qris" required>
                        <div class="st-pay-icon"><img src="<?php echo $qris_data['icon']; ?>"></div>
                        <span class="st-pay-label"><?php echo $qris_data['label']; ?></span>
                    </label>
                    <?php endif; ?>

                    <?php // OPSI 2: TRANSFER BANK (TRIGGER GROUP)
                    $is_va_enabled = false;
                    foreach($va_data as $key => $data) {
                        if(get_option('st_enable_'.$key)) { $is_va_enabled = true; break; }
                    }
                    if($is_va_enabled): ?>
                    <label class="st-pay-item st-transfer-trigger st-pay-trigger" id="st-transfer-trigger">
                        <input type="radio" name="st_method_final" value="bank_trigger" data-type="group" required>
                        <div class="st-pay-icon" style="width:30px;"><span class="dashicons dashicons-bank"></span></div>
                        <span class="st-pay-label">Transfer Bank / Virtual Account</span>
                    </label>
                    <?php endif; ?>

                    <?php // DAFTAR BANK VA (Tersembunyi Awalnya - Menggunakan nama sementara) ?>
                    <div class="st-va-list-expanded" id="st-va-list-expanded" style="display:none;">
                        <?php foreach($va_data as $key => $data): if(get_option('st_enable_'.$key)): ?>
                        <label class="st-pay-item st-sub-item" data-method="<?php echo $key; ?>" style="padding: 8px 10px; margin-bottom: 5px;">
                            <input type="radio" name="st_temp_method" value="<?php echo $key; ?>" data-type="va">
                            <div class="st-pay-icon" style="width:30px;"><img src="<?php echo $data['icon']; ?>"></div>
                            <span class="st-pay-label" style="font-size:14px;"><?php echo $data['label']; ?></span>
                        </label>
                        <?php endif; endforeach; ?>
                    </div>
                    
                </div>
                <input type="hidden" name="st_final_method" id="st_final_method" required value="">

                
                <div class="st-total-breakdown" style="margin-top:20px;">
                    <div class="st-calc-row"><span>Subtotal Produk:</span> <span id="display_subtotal_direct">Rp 0</span></div>
                    <div class="st-calc-row"><span>Ongkir:</span> <span id="display_ongkir_direct">Rp 0</span></div>
                    <div class="st-calc-row"><span>Biaya Layanan (Fee):</span> <span id="display_fee_direct">Rp 0</span></div>
                </div>

                <div class="st-landing-total">
                    <span>Total Bayar:</span>
                    <span class="st-display-total">Rp <?php echo number_format($price,0,',','.'); ?></span>
                </div>

                <button type="submit" class="st-btn-primary st-block">BELI SEKARANG</button>
            </form>
            <div class="st-result-area" style="margin-top:20px;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    // --- AJAX PROSES (FIX PENYIMPANAN DAN TAMPILAN INSTRUKSI FINAL) ---
    public function process_direct_order() {
        $pid = intval($_POST['pid']);
        $price = get_post_meta($pid, '_st_price', true);
        $name = sanitize_text_field($_POST['st_name']);
        $email = sanitize_email($_POST['st_email']);
        $phone = sanitize_text_field($_POST['st_phone']);
        $method = sanitize_text_field($_POST['st_final_method']);
        $ongkir = intval($_POST['st_shipping_cost']);
        $is_digital = isset($_POST['is_digital']);
        $qty = intval($_POST['st_qty']);

        if($qty < 1) $qty = 1;

        if(empty($method) || $method === 'bank_trigger') wp_send_json_error('Mohon pilih metode pembayaran yang spesifik.');

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

        $addr_str = 'Digital Order';
        if(!$is_digital) {
            $addr_str = sanitize_text_field($_POST['st_addr']) . ', Kec. ' . sanitize_text_field($_POST['st_dist_name']) . ', ' . sanitize_text_field($_POST['st_city_name']) . ', Prov. ' . sanitize_text_field($_POST['st_prov_name']);
            if($ongkir <= 0) wp_send_json_error('Mohon pilih opsi ongkos kirim.');
        }

        $subtotal = $price * $qty;
        $gross_before_fee = $subtotal + $ongkir;
        $fee = ($method == 'qris') ? ceil($gross_before_fee * 0.017) : 5000;
        $total = $gross_before_fee + $fee;

        $order_id = wp_insert_post(array('post_type'=>'st_order', 'post_title'=>'Direct #'.time().' - '.$name, 'post_status'=>'publish'));
        
        // --- PENYIMPANAN DATA LENGKAP HARGA DAN PELANGGAN (DIPASTIKAN TERSIMPAN) ---
        update_post_meta($order_id, '_st_total', $total);
        update_post_meta($order_id, '_st_method', $method);
        update_post_meta($order_id, '_st_address', $addr_str);
        update_post_meta($order_id, '_st_qty', $qty);
        update_post_meta($order_id, '_st_email', $email);
        update_post_meta($order_id, '_st_phone', $phone);
        update_post_meta($order_id, '_st_product_id', $pid);
        update_post_meta($order_id, '_st_subtotal', $subtotal);
        update_post_meta($order_id, '_st_ongkir', $ongkir);
        update_post_meta($order_id, '_st_fee', $fee);
        // --- END PENYIMPANAN DATA LENGKAP ---
        
        if(!session_id()) session_start();
        $_SESSION['st_orders'][] = $order_id;

        if(class_exists('Simple_Midtrans_API')) {
            $server_key = get_option('st_server_key');
            $is_sandbox = get_option('st_is_sandbox');
            
            $params = array(
                'transaction_details' => array('order_id'=>$order_id.'-'.time(), 'gross_amount'=>ceil($total)),
                'customer_details' => array('first_name'=>$name, 'email'=>$email, 'phone'=>$phone),
                'item_details' => array(
                    array('id'=>'TOTAL', 'price'=>ceil($total), 'quantity'=>1, 'name'=>'Total Pembayaran')
                )
            );

            if ($method == 'qris') { $params['payment_type'] = 'qris'; $params['qris'] = array('acquirer' => 'gopay'); } 
            elseif ($method == 'mandiri') { $params['payment_type'] = 'echannel'; $params['echannel'] = array('bill_info1'=>'Payment','bill_info2'=>'Online'); }
            elseif ($method == 'permata') { $params['payment_type'] = 'permata'; } 
            else { $params['payment_type'] = 'bank_transfer'; $params['bank_transfer'] = array('bank' => $method); }

            $res = Simple_Midtrans_API::request($params, $server_key, $is_sandbox);
            
            if(isset($res->status_code) && in_array($res->status_code, ['200','201'])) {
                
                // Ambil label pembayaran yang jelas (FIX P3)
                $payment_label = $this->payment_channels[$method]['label'] ?? strtoupper($method);

                $html = '<div style="text-align:center; padding:30px; background:#fff; border:1px solid #ddd; border-radius:10px; margin-bottom:20px;">';
                $html .= '<h3 style="color:#16a34a; margin-top:0;">Order Berhasil!</h3>';
                $html .= '<p>Segera lakukan pembayaran sebelum expired.</p>';
                $html .= '<p>Total Bayar: <b>Rp '.number_format($total,0,',','.').'</b></p>';
                
                $va = '-';
                if($method == 'qris') { 
                    $html .= '<p>Pembayaran via: <span style="font-weight:bold;">QRIS</span></p>';
                    $html .= '<p>Scan QRIS di bawah ini:</p><img src="'.$res->actions[0]->url.'" style="width:200px; margin:10px auto; display:block;">'; 
                } elseif($method == 'mandiri') { 
                    $va = $res->bill_key;
                    $html .= '<p>Pembayaran via: <span style="font-weight:bold;">'.$payment_label.'</span></p>';
                    $html .= '<p>Biller Code: <b>'.$res->biller_code.'</b></p><p>Bill Key (Nomor VA):</p><h2 style="background:#f1f5f9; padding:15px; border-radius:5px; font-size:20px;">'.$va.'</h2>';
                } else { 
                    $va = $res->permata_va_number ?? ($res->va_numbers[0]->va_number ?? '-');
                    $html .= '<p>Pembayaran via: <span style="font-weight:bold;">'.$payment_label.'</span></p>';
                    $html .= '<p>Nomor Virtual Account ('.strtoupper($method).'):</p><h2 style="background:#f1f5f9; padding:15px; border-radius:5px; font-size:20px;">'.$va.'</h2>';
                }
                
                update_post_meta($order_id, '_st_payment_code', $va);
                $html .= '<p style="margin-top:20px;"><a href="/akun" class="st-btn-primary">Lihat Status Pesanan</a></p></div>';
                
                wp_send_json_success(array('html'=>$html));
            } else {
                wp_send_json_error('Gagal Payment Gateway: '.($res->status_message??'Unknown'));
            }
        } else {
            wp_send_json_error('Plugin Utama tidak aktif.');
        }
    }

    public function print_landing_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($){
            function rp(n){ return 'Rp ' + new Intl.NumberFormat('id-ID').format(n); }
            
            // --- VARIABEL GLOBAL ---
            var vaList = $('#st-va-list-expanded');
            var finalMethod = $('#st_final_method');
            var form = $('.st-direct-form');
            var basePrice = parseInt(form.data('price'));
            
            // --- FUNGSI UTAMA PERHITUNGAN HARGA ---
            function updateTotal() {
                var selectedQty = parseInt($('input[name="st_qty"]:checked').val()) || 1;
                var ongkir = parseInt(form.find('.st_shipping_cost').val()) || 0;
                var methodVal = finalMethod.val();
                var methodType = (methodVal === 'qris') ? 'qris' : 'va'; 
                
                var subtotal = basePrice * selectedQty;
                var total = subtotal + ongkir;
                var fee = 0;
                
                if (methodVal !== '' && methodVal !== 'bank_trigger') {
                    if(methodType === 'qris') fee = total * 0.017;
                    else if(methodType === 'va') fee = 5000;
                } else {
                    fee = 0;
                }
                
                var final_total = total + fee;

                // DISPLAY BREAKDOWN
                form.closest('.st-landing-wrapper').find('#display_subtotal_direct').text( rp(subtotal) );
                form.closest('.st-landing-wrapper').find('#display_ongkir_direct').text( rp(ongkir) );
                form.closest('.st-landing-wrapper').find('#display_fee_direct').text( rp(fee) );
                form.closest('.st-landing-wrapper').find('.st-display-total').text( rp(final_total) );
            }
            
            // --- LISTENER KUANTITAS & PAYMENT ---
            $('input[name="st_qty"]').on('change', updateTotal);
            
            // Listener Grouping Payment
            $('input[name="st_method_final"]').on('change', function() {
                var selectedGroup = $(this).val();
                
                $('.st-pay-trigger').removeClass('active');
                
                if (selectedGroup === 'qris') {
                    vaList.slideUp(200);
                    
                    $('input[name="st_temp_method"]').prop('checked', false);
                    $('.st-sub-item').removeClass('active');
                    
                    finalMethod.val('qris');
                    $(this).closest('.st-pay-item').addClass('active');
                } else if (selectedGroup === 'bank_trigger') {
                    vaList.slideDown(200);
                    finalMethod.val('');
                    $(this).closest('.st-pay-item').addClass('active');
                }
                updateTotal();
            });
            
            // Listener pemilihan VA di dalam list yang tersembunyi
            $('input[name="st_temp_method"]').on('change', function() {
                $('.st-sub-item').removeClass('active');
                $(this).closest('.st-sub-item').addClass('active');

                $('#st-transfer-trigger input').prop('checked', true);

                finalMethod.val($(this).val());
                updateTotal();
            });

            // Pastikan st_final_method terisi sebelum submit
            form.submit(function(e) {
                var finalVal = finalMethod.val();
                if (!finalVal || finalVal === 'bank_trigger') {
                    e.preventDefault();
                    alert('Mohon pilih metode pembayaran yang spesifik (QRIS atau salah satu Bank VA).');
                    vaList.slideDown(200);
                    return false;
                }
            });
            // --- END LOGIC PAYMENT ---


            // --- LOGIC ONGKIR (FIX PELAPORAN ERROR) ---
            $('.st-direct-form').each(function(){
                var form = $(this);
                var pid = form.data('id');
                var weight = parseInt(form.data('weight'));

                var prov = form.find('.st_prov_trigger');
                var city = form.find('.st_city_trigger');
                var dist = form.find('.st_dist_trigger');
                var cour = form.find('.st_courier_trigger');
                var resArea = form.find('.st_shipping_results');

                if(prov.length > 0) {
                    // PANGGIL PROVINSI
                    $.get('<?php echo admin_url('admin-ajax.php'); ?>?action=st_get_locations&type=province', function(res){
                        if(res.success && res.data && res.data.length > 0){
                            var o='<option value="">Pilih Provinsi</option>'; 
                            $.each(res.data,function(i,v){o+='<option value="'+v.id+'">'+v.name+'</option>'}); 
                            prov.html(o).prop('disabled', false);
                        } else { 
                            prov.html('<option value="">!!! ERROR: Cek API Key RajaOngkir !!!</option>').prop('disabled', true).css('color', 'red'); 
                            console.error('API RajaOngkir gagal memuat data provinsi. Cek kunci API di pengaturan admin.');
                        }
                    });
                    
                    // Listener City, District, Courier calls
                    prov.change(function(){
                        form.find('input[name="st_prov_name"]').val( $(this).find('option:selected').text() );
                        city.html('<option>Loading...</option>').prop('disabled',true);
                        $.get('<?php echo admin_url('admin-ajax.php'); ?>?action=st_get_locations&type=city&id='+$(this).val(), function(res){
                            var o='<option value="">Pilih Kota</option>'; 
                            if(res.success && res.data && res.data.length > 0) {
                                $.each(res.data,function(i,v){o+='<option value="'+v.id+'">'+v.name+'</option>'}); 
                                city.html(o).prop('disabled',false);
                            } else {
                                city.html('<option value="">GAGAL MEMUAT KOTA</option>').prop('disabled', true);
                            }
                        });
                    });
                    city.change(function(){
                        form.find('input[name="st_city_name"]').val( $(this).find('option:selected').text() );
                        dist.html('<option>Loading...</option>').prop('disabled',true);
                        $.get('<?php echo admin_url('admin-ajax.php'); ?>?action=st_get_locations&type=district&id='+$(this).val(), function(res){
                            var o='<option value="">Pilih Kecamatan</option>'; 
                            if(res.success && res.data && res.data.length > 0) {
                                $.each(res.data,function(i,v){o+='<option value="'+v.id+'">'+v.name+'</option>'}); 
                                dist.html(o).prop('disabled',false);
                            } else {
                                dist.html('<option value="">GAGAL MEMUAT KECAMATAN</option>').prop('disabled', true);
                            }
                        });
                    });
                    dist.change(function(){ form.find('input[name="st_dist_name"]').val( $(this).find('option:selected').text() ); calcShipping(); });
                    cour.change(calcShipping);

                    function calcShipping() {
                        var d = dist.val(); var c = cour.val();
                        if(!d || !c) return;
                        resArea.html('Checking...');
                        $.post('<?php echo admin_url('admin-ajax.php'); ?>',{action:'st_check_shipping',destination:d,weight:weight,courier:c},function(res){
                            if(res.success){
                                var h=''; $.each(res.data,function(i,v){ 
                                    h+='<div class="st-ship-opt" data-c="'+v.cost+'" data-s="'+v.code.toUpperCase()+' '+v.service+'" style="border:1px solid #ddd; padding:8px; margin-bottom:5px; cursor:pointer; font-size:13px;"><b>'+v.service+'</b> ('+v.etd+' hari) - '+rp(v.cost)+'</div>'; 
                                }); 
                                resArea.html(h);
                            } else { resArea.html('Gagal cek ongkir atau tidak ada layanan kurir tersedia.'); }
                        });
                    }

                    resArea.on('click', '.st-ship-opt', function(){
                        resArea.find('.st-ship-opt').css('background','transparent');
                        $(this).css('background','#eff6ff');
                        var cost = parseInt($(this).data('c'));
                        var srv = $(this).data('s');
                        form.find('.st_shipping_cost').val(cost);
                        form.find('.st_shipping_service').val(srv);
                        updateTotal();
                    });
                }
                
                setTimeout(updateTotal, 100); 
            });
        });
        </script>
        <?php
    }
}

new Simple_Toko_Direct();

// ===============================================
// ELEMENTOR WIDGET CLASS
// ===============================================
if ( class_exists( '\Elementor\Widget_Base' ) ) {
    
    // Asumsi class Repeater sudah ada atau dimuat oleh Elementor core
    
    class Elementor_ST_Direct_Checkout extends \Elementor\Widget_Base {
        public function get_name() { return 'st_direct_checkout'; }
        public function get_title() { return 'ST Direct Form'; }
        public function get_icon() { return 'eicon-cart-solid'; }
        public function get_categories() { return [ 'st_category' ]; } 

        protected function register_controls() {
            $this->start_controls_section('section_content', ['label' => 'Pengaturan Produk']);
            
            $products = get_posts(array('post_type'=>'st_product','posts_per_page'=>-1));
            $opts = array(0 => 'Pilih Produk');
            if($products){ foreach($products as $p) { $opts[$p->ID] = $p->post_title; } }

            $this->add_control('product_id', [
                'label' => 'Pilih Produk',
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $opts,
                'default' => 0
            ]);
            
            // <<< KONTROL REPEATER KUANTITAS >>>
            $repeater = new \Elementor\Repeater();
            $repeater->add_control('qty_value', [
                'label' => 'Kuantitas (Angka)',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 1,
                'min' => 1,
            ]);
            $repeater->add_control('qty_label', [
                'label' => 'Label Tampilan',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '1 Buah',
            ]);
            
            $this->add_control('quantity_options', [
                'label' => 'Pilihan Kuantitas',
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                    ['qty_value' => 1, 'qty_label' => '1 Buah'],
                    ['qty_value' => 2, 'qty_label' => '2 Buah (Hemat)'],
                ],
                'title_field' => '{{{ qty_label }}} ({{{ qty_value }}})',
            ]);
            // <<< END KONTROL REPEATER KUANTITAS >>>
            
            $this->end_controls_section();
        }

        protected function render() {
            $settings = $this->get_settings_for_display();
            $id = $settings['product_id'];
            
            $qty_options = json_encode($settings['quantity_options']); 
            
            if($id) { 
                echo do_shortcode('[st_landing_form id="'.$id.'" qty_options="'.esc_attr($qty_options).'"]'); 
            } else { 
                echo '<div style="padding:20px; background:#f1f1f1; text-align:center; border:1px dashed #ccc;">Silahkan pilih produk di pengaturan widget.</div>'; 
            }
        }
    }
}