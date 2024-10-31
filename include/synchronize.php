<?php

// Synchronize Woocommerce orders to Route4Me
if (!empty(get_option('r4me_api_key')) && get_option('r4me_auto_send') == 'yes'):
    add_action('woocommerce_thankyou', 'r4mwoo_sync_order');
endif;

function r4mwoo_sync_order($order_id)
{
    $order = wc_get_order($order_id);

    if (is_wp_error($order) || $order->has_status('failed')) {
        return;
    }

    if(r4mwoo_order_already_sent($order_id)){
        return;
    }

    $opSendOrderStatus = get_option('r4m_sync_status');
    
    if(!empty($opSendOrderStatus) && $opSendOrderStatus === "pending_payment" && !$order->has_status('pending')){
        return;
    }

    $address = r4mwoo_get_order_address($order);

    if (empty($address)) {
        return;
    }

    $api_key = get_option('r4me_api_key');

    $geocoderResponse = Route4Me::geocodeAddress($api_key, $address);

    if (is_wp_error($geocoderResponse)) {
        return;
    }

    // Add order

    $response = Route4Me::sendOrder($api_key, r4mwoo_get_post_params($geocoderResponse, $order));

    if (is_wp_error($response)) {
        return;
    }

    $response = json_decode(wp_remote_retrieve_body($response));

    if (empty($response->order_id)) {
        return;
    }

    //update post meta
    update_post_meta($order_id, 'route4me_order_id', (int) $response->order_id);
}
