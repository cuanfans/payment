<?php
defined( 'ABSPATH' ) || exit;

class Simple_Toko_Payment_Page_API {
    
    // ==========================================
    // PANEL CONFIGURATION
    // ==========================================
    private $api_secret = 'PAYMENT_SECRET_KEY_123'; // The API Key that the client website must send
    
    // Kredensial PayPal untuk Eksekusi Kartu Kredit (ACDC)
    private $paypal_env = 'sandbox'; // Ubah ke 'live' jika sudah siap rilis
    private $paypal_client_id = 'YOUR_PAYPAL_CLIENT_ID_HERE';
    private $paypal_secret = 'YOUR_PAYPAL_SECRET_HERE';
    
    public function __construct() {
        // Register database structure (Custom Post Type)
        add_action('init', array($this, 'register_payment_database'));
        // Register API Endpoint to receive payload
        add_action('rest_api_init', array($this, 'register_payment_endpoints'));
        // Intercept URL to render the Dynamic Payment Page
        add_action('template_redirect', array($this, 'render_dynamic_payment_page'));
        // Process the payment from the dynamic page via AJAX
        add_action('wp_ajax_st_process_central_payment', array($this, 'process_payment_action'));
        add_action('wp_ajax_nopriv_st_process_central_payment', array($this, 'process_payment_action'));
    }

    // ==========================================
    // 1. DATABASE STRUCTURE
    // ==========================================
    public function register_payment_database() {
        register_post_type('st_payment_order', array(
            'public' => false,
            'show_ui' => true,
            'label' => 'Payment Links',
            'supports' => array('title'),
            'menu_icon' => 'dashicons-money-alt'
        ));
    }

    // ==========================================
    // 2. CLIENT API ENDPOINT
    // ==========================================
    public function register_payment_endpoints() {
        register_rest_route('st-payment/v1', '/create-link', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_dynamic_payment_link'),
            'permission_callback' => '__return_true'
        ));
    }

    // ==========================================
    // 3. PAYLOAD RECEIVER & LINK GENERATOR
    // ==========================================
    public function create_dynamic_payment_link($request) {
        $params = $request->get_json_params();
        
        // Security Validation (API Key)
        if(empty($params['api_key']) || $params['api_key'] !== $this->api_secret) {
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Access Denied: Invalid API Key.'), 401);
        }

        // Capture Payload
        $client_order_id = sanitize_text_field($params['client_order_id']);
        $amount = floatval($params['amount']);
        $currency = isset($params['currency']) ? sanitize_text_field($params['currency']) : 'USD';
        $customer_name = sanitize_text_field($params['customer_name']);
        $customer_email = sanitize_email($params['customer_email']);
        
        if(!$client_order_id || !$amount) {
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Incomplete payload: client_order_id and amount are required.'), 400);
        }

        // Generate Unique Token for the URL
        $token = wp_generate_password(32, false);
        
        // Save to Database
        $post_id = wp_insert_post(array(
            'post_type' => 'st_payment_order',
            'post_title' => 'Invoice: ' . $client_order_id,
            'post_status' => 'publish'
        ));

        update_post_meta($post_id, '_pay_client_order_id', $client_order_id);
        update_post_meta($post_id, '_pay_amount', $amount);
        update_post_meta($post_id, '_pay_currency', $currency);
        update_post_meta($post_id, '_pay_customer_name', $customer_name);
        update_post_meta($post_id, '_pay_email', $customer_email);
        update_post_meta($post_id, '_pay_token', $token);
        update_post_meta($post_id, '_pay_status', 'PENDING');
        
        if(!empty($params['redirect_url'])) update_post_meta($post_id, '_pay_redirect', esc_url_raw($params['redirect_url']));
        if(!empty($params['webhook_url'])) update_post_meta($post_id, '_pay_webhook', esc_url_raw($params['webhook_url']));

        // Create Payment Link
        $payment_link = home_url('/?st_pay_token=' . $token);

        return new WP_REST_Response(array(
            'status' => 'success',
            'data' => array(
                'payment_id' => $post_id,
                'client_order_id' => $client_order_id,
                'payment_link' => $payment_link,
                'token' => $token
            )
        ), 200);
    }

    // ==========================================
    // 4. DYNAMIC PAYMENT PAGE RENDERER (HTML/CSS)
    // ==========================================
    public function render_dynamic_payment_page() {
        if(!isset($_GET['st_pay_token']) || empty($_GET['st_pay_token'])) return;

        $token = sanitize_text_field($_GET['st_pay_token']);
        
        // Search for invoice in database based on token
        $query = new WP_Query(array(
            'post_type' => 'st_payment_order',
            'meta_key' => '_pay_token',
            'meta_value' => $token,
            'posts_per_page' => 1
        ));

        if(!$query->have_posts()) {
            wp_die('The payment link is invalid or has expired.', 'Invalid Link', array('response' => 404));
        }

        $query->the_post();
        $post_id = get_the_ID();
        $status = get_post_meta($post_id, '_pay_status', true);
        
        if($status === 'COMPLETED') {
            wp_die('This order has already been paid and completed.', 'Transaction Completed', array('response' => 200));
        }

        // Pull variables from database to display in HTML
        $amount = get_post_meta($post_id, '_pay_amount', true);
        $currency = get_post_meta($post_id, '_pay_currency', true);
        $client_order_id = get_post_meta($post_id, '_pay_client_order_id', true);
        $email = get_post_meta($post_id, '_pay_email', true);

        // Render Full HTML (Overrides WordPress Theme)
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Secure Payment - <?php echo esc_html($client_order_id); ?></title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
                .checkout-box { background: #fff; width: 100%; max-width: 450px; padding: 40px 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
                .header { text-align: center; margin-bottom: 25px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; }
                .amount { font-size: 36px; font-weight: bold; color: #0f172a; margin: 10px 0; }
                .form-group { margin-bottom: 18px; }
                .form-group label { display: block; margin-bottom: 6px; font-size: 14px; color: #475569; font-weight: 500; }
                .form-control { width: 100%; padding: 14px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 15px; color: #0f172a; transition: border-color 0.2s; }
                .form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
                .row { display: flex; gap: 15px; }
                .row .form-group { flex: 1; }
                .btn-pay { width: 100%; padding: 16px; background: #0f172a; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s; margin-top: 10px; }
                .btn-pay:hover { background: #1e293b; }
                .btn-pay:disabled { background: #94a3b8; cursor: not-allowed; }
                .secure-badge { text-align: center; font-size: 13px; color: #64748b; margin-top: 20px; display: flex; align-items: center; justify-content: center; gap: 6px; }
            </style>
        </head>
        <body>
            <div class="checkout-box">
                <div class="header">
                    <div style="color: #64748b; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Order Summary</div>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 5px; color: #334155;">Invoice: <?php echo esc_html($client_order_id); ?></div>
                    <div class="amount"><?php echo esc_html($currency); ?> <?php echo number_format($amount, 2, '.', ','); ?></div>
                    <div style="font-size: 14px; color: #64748b;"><?php echo esc_html($email); ?></div>
                </div>

                <form id="central-payment-form">
                    <input type="hidden" name="action" value="st_process_central_payment">
                    <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                    
                    <div class="form-group">
                        <label>Card Information</label>
                        <input type="text" class="form-control" name="cc_number" placeholder="0000 0000 0000 0000" maxlength="19" required>
                    </div>
                    
                    <div class="row">
                        <div class="form-group">
                            <input type="text" class="form-control" name="cc_exp" placeholder="MM / YY" maxlength="7" required>
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" name="cc_cvc" placeholder="CVC" maxlength="4" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Cardholder Name</label>
                        <input type="text" class="form-control" name="cc_name" placeholder="Full name on card" required>
                    </div>

                    <button type="submit" class="btn-pay" id="btn-submit">Pay <?php echo esc_html($currency); ?> <?php echo number_format($amount, 2, '.', ','); ?></button>
                    
                    <div class="secure-badge">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Secured & Encrypted Checkout
                    </div>
                    
                    <div id="payment-message" style="margin-top: 15px; text-align: center; font-weight: bold; font-size: 14px; padding: 10px; border-radius: 6px; display: none;"></div>
                </form>
            </div>

            <script>
                // Simple formatting for card number
                $('input[name="cc_number"]').on('input', function() {
                    var val = $(this).val().replace(/\D/g, '');
                    var newVal = '';
                    for(var i = 0; i < val.length; i++) {
                        if(i > 0 && i % 4 === 0) newVal += ' ';
                        newVal += val[i];
                    }
                    $(this).val(newVal);
                });

                // Simple formatting for expiry
                $('input[name="cc_exp"]').on('input', function() {
                    var val = $(this).val().replace(/\D/g, '');
                    if(val.length > 2) {
                        $(this).val(val.substring(0,2) + ' / ' + val.substring(2,4));
                    } else {
                        $(this).val(val);
                    }
                });

                $('#central-payment-form').on('submit', function(e) {
                    e.preventDefault();
                    var btn = $('#btn-submit');
                    var msg = $('#payment-message');
                    btn.text('Processing Payment...').prop('disabled', true);
                    msg.hide().removeClass('error success');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            if(response.success) {
                                msg.css({'background':'#dcfce7', 'color':'#16a34a'}).text('Payment Successful! Redirecting...').slideDown();
                                if(response.data.redirect_url) {
                                    setTimeout(function(){ window.location.href = response.data.redirect_url; }, 2000);
                                } else {
                                    btn.text('Payment Completed');
                                }
                            } else {
                                msg.css({'background':'#fee2e2', 'color':'#dc2626'}).text(response.data).slideDown();
                                btn.text('Pay <?php echo esc_html($currency); ?> <?php echo number_format($amount, 2, '.', ','); ?>').prop('disabled', false);
                            }
                        },
                        error: function() {
                            msg.css({'background':'#fee2e2', 'color':'#dc2626'}).text('A server error occurred during processing.').slideDown();
                            btn.text('Pay <?php echo esc_html($currency); ?> <?php echo number_format($amount, 2, '.', ','); ?>').prop('disabled', false);
                        }
                    });
                });
            </script>
        </body>
        </html>
        <?php
        exit; // Stop WordPress theme execution to purely render this page
    }

    // ==========================================
    // 5. PAYMENT EXECUTION & WEBHOOK PROCESSOR (FULL PHP PAYPAL API)
    // ==========================================
    public function process_payment_action() {
        $token = sanitize_text_field($_POST['token']);
        
        // Search for order data based on form token
        $query = new WP_Query(array('post_type' => 'st_payment_order', 'meta_key' => '_pay_token', 'meta_value' => $token, 'posts_per_page' => 1));
        
        if(!$query->have_posts()) wp_send_json_error('Invalid session or token.');
        $query->the_post();
        $post_id = get_the_ID();

        $amount = get_post_meta($post_id, '_pay_amount', true);
        $currency = get_post_meta($post_id, '_pay_currency', true);
        $client_order_id = get_post_meta($post_id, '_pay_client_order_id', true);

        // Bersihkan data form kartu dari spasi dan karakter tidak perlu
        $cc_number = str_replace(' ', '', sanitize_text_field($_POST['cc_number']));
        $cc_exp = sanitize_text_field($_POST['cc_exp']); // Format input: "MM / YY"
        $cc_cvc = sanitize_text_field($_POST['cc_cvc']);
        $cc_name = sanitize_text_field($_POST['cc_name']);

        // Ubah format Expiry dari "MM / YY" menjadi format PayPal "YYYY-MM"
        $exp_parts = explode('/', str_replace(' ', '', $cc_exp));
        if(count($exp_parts) !== 2) wp_send_json_error('Invalid expiry date format.');
        $exp_month = $exp_parts[0];
        $exp_year = '20' . $exp_parts[1]; // Asumsi tahun 2000-an
        $formatted_exp = $exp_year . '-' . $exp_month;

        // -------------------------------------------------------------
        // STEP A: DAPATKAN ACCESS TOKEN PAYPAL
        // -------------------------------------------------------------
        $base_url = ($this->paypal_env === 'sandbox') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $auth_string = base64_encode($this->paypal_client_id . ':' . $this->paypal_secret);

        $token_response = wp_remote_post($base_url . '/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . $auth_string,
                'Content-Type'  => 'application/x-www-form-urlencoded'
            ),
            'body' => 'grant_type=client_credentials',
            'timeout' => 15
        ));

        if(is_wp_error($token_response)) {
            wp_send_json_error('Failed to connect to PayPal Authentication Server.');
        }

        $token_body = json_decode(wp_remote_retrieve_body($token_response));
        if(empty($token_body->access_token)) {
            wp_send_json_error('PayPal Authentication Failed. Please check API Credentials in config.');
        }
        $access_token = $token_body->access_token;

        // -------------------------------------------------------------
        // STEP B: EKSEKUSI PEMBAYARAN KARTU (PAYPAL ORDERS API)
        // -------------------------------------------------------------
        $order_payload = array(
            'intent' => 'CAPTURE',
            'purchase_units' => array(
                array(
                    'reference_id' => $client_order_id,
                    'amount' => array(
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', '')
                    )
                )
            ),
            'payment_source' => array(
                'card' => array(
                    'name' => $cc_name,
                    'number' => $cc_number,
                    'expiry' => $formatted_exp,
                    'security_code' => $cc_cvc
                )
            )
        );

        $payment_response = wp_remote_post($base_url . '/v2/checkout/orders', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json'
            ),
            'body' => json_encode($order_payload),
            'timeout' => 30
        ));

        if(is_wp_error($payment_response)) {
            wp_send_json_error('Failed to communicate with PayPal Order Processing.');
        }

        $payment_body = json_decode(wp_remote_retrieve_body($payment_response));

        // Pengecekan status keberhasilan dari PayPal
        if(!isset($payment_body->status) || $payment_body->status !== 'COMPLETED') {
            $error_message = 'Payment declined by card issuer.';
            if(isset($payment_body->details[0]->description)) {
                $error_message .= ' Reason: ' . $payment_body->details[0]->description;
            } elseif (isset($payment_body->message)) {
                $error_message .= ' Reason: ' . $payment_body->message;
            }
            wp_send_json_error($error_message);
        }
        
        // Simpan ID Transaksi dari PayPal
        $paypal_transaction_id = $payment_body->id;
        update_post_meta($post_id, '_pay_paypal_transaction_id', $paypal_transaction_id);

        // -------------------------------------------------------------
        // STEP C: UPDATE STATUS LOKAL & KIRIM WEBHOOK KE KLIEN
        // -------------------------------------------------------------
        
        // Update Payment Status to Completed
        update_post_meta($post_id, '_pay_status', 'COMPLETED');
        
        // Send Notification (Webhook) back to the Client Website
        $webhook_url = get_post_meta($post_id, '_pay_webhook', true);
        
        if(!empty($webhook_url)) {
            $webhook_payload = array(
                'order_id' => $client_order_id,
                'status' => 'COMPLETED',
                'transaction_id' => $paypal_transaction_id
            );
            // Send POST request to the client's webhook endpoint
            wp_remote_post($webhook_url, array(
                'body' => json_encode($webhook_payload),
                'headers' => array('Content-Type' => 'application/json'),
                'timeout' => 15
            ));
        }

        // Retrieve Redirect URL to be processed by JavaScript (AJAX Success)
        $redirect_url = get_post_meta($post_id, '_pay_redirect', true);

        wp_send_json_success(array(
            'message' => 'Payment completed successfully',
            'redirect_url' => $redirect_url
        ));
    }
}

new Simple_Toko_Payment_Page_API();
