<?php
defined( 'ABSPATH' ) || exit;

class Simple_Midtrans_API {
    public static function request( $params, $server_key, $is_sandbox ) {
        $base_url = $is_sandbox ? 'https://api.sandbox.midtrans.com/v2' : 'https://api.midtrans.com/v2';
        $url      = $base_url . '/charge';

        $headers = array(
            'Authorization' => 'Basic ' . base64_encode( $server_key . ':' ),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'X-Override-Notification' => home_url( '/?st_listener=midtrans' )
        );

        $response = wp_remote_post( $url, array(
            'headers' => $headers,
            'body'    => json_encode( $params ),
            'timeout' => 45
        ));

        if ( is_wp_error( $response ) ) return $response;
        
        $body = json_decode( wp_remote_retrieve_body( $response ) );
        
        // Debugging jika perlu
        if ( isset($body->status_code) && $body->status_code > 202 ) {
            return new WP_Error( 'midtrans_api_error', $body->status_message );
        }

        return $body;
    }
}