<?php
/**
 * Plugin Name: Orders to Route4Me for WooCommerce
 * Plugin URI: https://route4me.com
 * Description: Send WooCommerce orders to Route4Me for instantaneous order to route integration
 * Version: 1.1.1
 * Requires at least: 4.4
 * Tested up to: 6.1.1
 * Author: Route4Me
 * Author URI: https://route4me.com
 * Text Domain: route4me
 * WC requires at least: 3.0.0
 * WC tested up to: 7.1.0
 */

// don't load directly
if (!defined('ABSPATH')) {
    die('-1');
}

/**
 * Current Route4Me Woo version
 */
if (!defined('R4MEWOO_VERSION')) {
    define('R4MEWOO_VERSION', '1.1.1');
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'r4mwoo_plugin_activate');
}
function r4mwoo_plugin_activate()
{
    ?>
    <div class="error"><p><strong>
    <?php _e('Orders to Route4Me for WooCommerce requires the WooCommerce plugin to be installed and active.', 'route4me');?>
    </strong></p></div>
    <?php
}

if (!defined('R4MWOO_PATH')) {
    define('R4MWOO_PATH', plugin_dir_url(__FILE__));
}

require_once 'class.route4me.php';
require_once 'include/metas.php';
require_once 'include/settings.php';
require_once 'include/enqueue-scripts.php';
require_once 'include/synchronize.php';

function r4mwoo_order_alias($order_id)
{
    $r4me_alias = esc_html(get_option('r4me_order_alias'));

    if (empty($r4me_alias)) {
        $r4me_alias = '#' . $order_id . ' from ' . get_bloginfo('name') . ' (Woocommerce)';
    }

    if (strpos($r4me_alias, '[order_id]') !== false) {
        $r4me_alias = str_replace('[order_id]', '#' . $order_id, $r4me_alias);
    }

    if (strpos($r4me_alias, '[site_name]') !== false) {
        $r4me_alias = str_replace('[site_name]', get_bloginfo('name'), $r4me_alias);
    }

    return $r4me_alias;
}

function r4mwoo_get_order_address(WC_Order $order)
{
    $address = null;
    if (!empty($order->get_shipping_address_1())) {
        $address = $order->get_shipping_address_1();
        if (!empty($order->get_shipping_city())) {
            $city = $order->get_shipping_city();
            if (strpos($address, $city) === false) {
                $address .= ', ' . $city;
            }
        }
        if (!empty($order->get_shipping_postcode())) {
            $zip = $order->get_shipping_postcode();
            if (strpos($address, $zip) === false) {
                $address .= ', ' . $zip;
            }
        }
        $shipping_country = $order->get_shipping_country();
        $shipping_state = $order->get_shipping_state();
        if (!empty($shipping_state)) {
            $state = r4mwoo_get_full_state($shipping_state, $shipping_country);
            if (strpos($address, $state) === false) {
                $address .= ', ' . $state;
            }
        }
        if (!empty($shipping_country)) {
            $country = r4mwoo_get_full_country($shipping_country);
            if (strpos($address, $country) === false) {
                $address .= ', ' . $country;
            }
        }
    } else if (!empty($order->get_billing_address_1())) {
        $address = $order->get_billing_address_1();
        if (!empty($order->get_billing_city())) {
            $city = $order->get_billing_city();
            if (strpos($address, $city) === false) {
                $address .= ', ' . $city;
            }
        }
        if (!empty($order->get_billing_postcode())) {
            $zip = $order->get_billing_postcode();
            if (strpos($address, $zip) === false) {
                $address .= ', ' . $zip;
            }
        }
        $billing_country = $order->get_billing_country();
        $billing_state = $order->get_billing_state();
        if (!empty($billing_state)) {
            $state = r4mwoo_get_full_state($billing_state, $billing_country);
            if (strpos($address, $state) === false) {
                $address .= ', ' . $state;
            }
        }
        if (!empty($billing_country)) {
            $country = r4mwoo_get_full_country($billing_country);
            if (strpos($address, $country) === false) {
                $address .= ', ' . $country;
            }
        }
    }

    return $address;
}

function r4mwoo_get_order_custom_data($order)
{
    //Custom data
    $custom_data = array();

    $orderId = $order->get_id();

    //Order Origin
    $custom_data['order_origin'] = get_bloginfo('name');

    //Company
    $shippingCompany = $order->get_shipping_company();
    if (!empty($shippingCompany)) {
        $custom_data['shipping_company'] = sanitize_text_field($shippingCompany);
    }

    if (!empty($order->get_shipping_address_2())) {
        $custom_data['address_2'] = $order->get_shipping_address_2();
    }

    if (!empty($order->get_payment_method_title())) {
        $custom_data['payment_method'] = sanitize_text_field($order->get_payment_method_title());
    }

    if (!empty($order->get_discount_total())) {
        $custom_data['discount_total'] = sanitize_text_field($order->get_discount_total());
    }

    $order_fees = $order->get_fees();
    if (!empty($order_fees)) {
        foreach ($order_fees as $fee) {
            $custom_data[$fee->get_name()] = $fee->get_total();
        }
    }

    if (!empty($order->get_shipping_tax())) {
        $custom_data['shipping_tax'] = $order->get_shipping_tax();
    }

    if (!empty($order->get_shipping_total())) {
        $custom_data['shipping_total'] = $order->get_shipping_total();
    }

    if (!empty($order->get_total())) {
        $custom_data['total'] = $order->get_total();
    }

    if (!empty($order->get_total_discount())) {
        $custom_data['total_discount'] = $order->get_total_discount();
    }

    if (!empty($order->get_total_tax())) {
        $custom_data['total_tax'] = $order->get_total_tax();
    }

    //customer provided note
    $customerNote = $order->get_customer_note();
    if (!empty($customerNote)) {
        $custom_data['customer_provided_note'] = sanitize_text_field($customerNote);
    }

    $order_notes = wc_get_order_notes(['order_id' => $orderId]);
    $note_count = 0;
    foreach ($order_notes as $note) {
        if (!empty($note->customer_note) && $note->customer_note === true && !empty($note->content)) {
            $note_count++;
            $custom_data['note_' . $note_count . '_to_customer'] = $note->content;
        }
    }

    // Push product name and quantity to custom data
    $items = $order->get_items();
    $c = 0;
    foreach ($items as $item) {
        $c++;
        $product = $item->get_product();
        $custom_data['product_' . $c . '_name'] = $product->get_name();
        $custom_data['product_' . $c . '_quantity'] = (int) $item->get_quantity();
        $custom_data['product_' . $c . '_id'] = $product->get_id();
        $custom_data['product_' . $c . '_sku'] = $product->get_sku();
        $custom_data['product_' . $c . '_total_amount'] = $item->get_total();
        $custom_data['product_' . $c . '_total_tax'] = $item->get_total_tax();
        if(!empty($product->get_weight())){
            $custom_data['product_' . $c . '_weight'] = $product->get_weight();
        }
    }

    $meta_data = $order->get_meta_data();
    if (!empty($meta_data)) {
        foreach ($meta_data as $meta) {
            if (!empty($meta->key) && !empty($meta->value)) {
                $custom_data[$meta->key] = $meta->value;
            }
        }
    }

    return $custom_data;
}

// Get Route4Me order ID attached to the order
function r4mwoo_get_order_id($postID)
{
    return get_post_meta($postID, 'route4me_order_id');
}

// Check date is valid
function r4mwoo_is_valid_date($date)
{
    $d = DateTime::createFromFormat('m/d/Y', $date);
    return $d && $d->format('m/d/Y') === $date;
}

//Check ID is valid
function r4mwoo_is_valid_id($id)
{
    if (ctype_digit($id) && $id > 0) {
        return true;
    }
}

// Column head
function r4mwoo_order_column_head($columns)
{
    $columns['route4me_column'] = "Route4Me";
    return $columns;
}

// Column content
function r4mwoo_order_column_content($column, $post_ID)
{
    if ($column == 'route4me_column') {
        $order_id = implode(r4mwoo_get_order_id($post_ID));
        if (!empty($order_id)):
            echo __('Exported', 'route4me');
            echo '<a class="button tips" id="r4me_lookup" data-order_id="' . esc_attr($order_id) . '" data-tip="' . __('Lookup the Route4Me order status.', 'route4me') . '" data-nonce="' . wp_create_nonce('r4me_lookup') . '"><img src="' . R4MWOO_PATH . '/img/favicon.png"></a>';
            echo '<img class="r4me_list_spinner" src="' . R4MWOO_PATH . '/img/spinner.gif" />';
        else:
            echo __('Not exported', 'route4me');
        endif;
    }
}

if (get_option('r4me_api_key') != false) {
    add_filter('manage_edit-shop_order_columns', 'r4mwoo_order_column_head', 11);
    add_action('manage_shop_order_posts_custom_column', 'r4mwoo_order_column_content', 10, 2);
}

// Route4Me Order Lookup
add_action('wp_ajax_r4mwoo_lookup_action', 'r4mwoo_lookup_action');

function r4mwoo_lookup_action()
{
    check_admin_referer('r4me_lookup', 'lookup_nonce');

    if (empty(get_option('r4me_api_key'))) {
        echo '<div class="error">' . __("Route4Me API key is not set", "route4me") . '</div>';
        die();
    } elseif (!r4mwoo_is_valid_id($_POST['r4me_order_id'])) {
        echo '<div class="error">' . __("Order ID is not valid", "route4me") . '</div>';
        die();
    } elseif (!current_user_can('manage_woocommerce')) {
        echo '<div class="error">' . __("You do not have sufficient permissions to take this action", "route4me") . '</div>';
        die();
    }

    $order = (int) $_POST['r4me_order_id'];
    $api_key = get_option('r4me_api_key');
    $html = '';

    $lookup = Route4Me::orderLookup($api_key, $order);

    if (is_wp_error($lookup)) {
        echo '<div class="error">' . __("Error while processing the request", "route4me") . '</div>';
        die();
    }

    $lookup = json_decode(wp_remote_retrieve_body($lookup));

    if (empty($lookup->order_id)) {
        echo '<div class="error">' . __("Couldn't retrieve the order details. It might be deleted on Route4Me!", "route4me");
        die();
    }

    $html .= '<div class="r4me_lookup_response">';
    $html .= '<ul>';
    if (isset($lookup->address_alias)):
        $html .= '<li><strong>Alias</strong>: ' . sanitize_text_field($lookup->address_alias);
    endif;
    if (isset($lookup->EXT_FIELD_first_name) && isset($lookup->EXT_FIELD_last_name)):
        $html .= '<li><strong>Name</strong>: ' . sanitize_text_field($lookup->EXT_FIELD_first_name) . ' ' . sanitize_text_field($lookup->EXT_FIELD_last_name) . '</li>';
    endif;
    if (isset($lookup->address_1)):
        $html .= '<li><strong>Address</strong>: ' . sanitize_text_field($lookup->address_1) . '</li>';
    endif;
    if (isset($lookup->EXT_FIELD_email)):
        $html .= '<li><strong>Email</strong>: ' . sanitize_text_field($lookup->EXT_FIELD_email) . '</li>';
    endif;
    if (isset($lookup->EXT_FIELD_phone)):
        $html .= '<li><strong>Phone</strong>: ' . sanitize_text_field($lookup->EXT_FIELD_phone) . '</li>';
    endif;
    if (isset($lookup->day_scheduled_for_YYMMDD)):
        $html .= '<li><strong>Scheduled for</strong>: ' . sanitize_text_field($lookup->day_scheduled_for_YYMMDD) . '</li>';
    endif;

    $html .= '</ul>';
    $html .= '<table><thead><tr>';
    $html .= '<th data-field="validated">Validated</th>';
    $html .= '<th data-field="pending">Pending</th>';
    $html .= '<th data-field="accepted">Accepted</th>';
    $html .= '<th data-field="started">Started</th>';
    $html .= '<th data-field="completed">Completed</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody><tr>';
    $html .= '<td>' . ($lookup->is_validated === true ? '<i class="dashicons dashicons-yes"></i>' : '<i class="dashicons dashicons-no-alt"></i>') . '</td>';
    $html .= '<td>' . ($lookup->is_pending === true ? '<i class="dashicons dashicons-yes"></i>' : '<i class="dashicons dashicons-no-alt"></i>') . '</td>';
    $html .= '<td>' . ($lookup->is_accepted === true ? '<i class="dashicons dashicons-yes"></i>' : '<i class="dashicons dashicons-no-alt"></i>') . '</td>';
    $html .= '<td>' . ($lookup->is_started === true ? '<i class="dashicons dashicons-yes"></i>' : '<i class="dashicons dashicons-no-alt"></i>') . '</td>';
    $html .= '<td>' . ($lookup->is_completed === true ? '<i class="dashicons dashicons-yes"></i>' : '<i class="dashicons dashicons-no-alt"></i>') . '</td>';

    $html .= '</tr></tbody></table></div>';

    echo $html;

    die();
}

// Add single order function
add_action('wp_ajax_r4mwoo_add_order_action', 'r4mwoo_add_order_action');

function r4mwoo_add_order_action()
{
    check_admin_referer('r4me_export', 'export_nonce');

    if (get_option('r4me_api_key') === false) {
        wp_die(__("Route4Me Api key is not set", "route4me"));
    } elseif (!r4mwoo_is_valid_id($_POST['postID'])) {
        wp_die(__("Order ID is not valid", "route4me"));
    } elseif (!current_user_can('manage_woocommerce')) {
        wp_die(__('You do not have sufficient permissions to take this action', 'route4me'));
    } elseif (empty($_POST['scheduledDate'])) {
        wp_die(__('Please, schedule the order for a date!', 'route4me'));
    } elseif (!r4mwoo_is_valid_date($_POST['scheduledDate'])) {
        wp_die(__('The date is not valid!', 'route4me'));
    }

    $postId = (int) $_POST['postID'];

    if ('auto-draft' === get_post_status($postId)) {
        wp_die(__("Draft orders can't be sent!", "route4me"));
    }

    $order = wc_get_order($postId);

    $api_key = get_option('r4me_api_key');

    if (is_wp_error($order)) {
        wp_die(__("Error occured!", "route4me"));
    }

    $address = r4mwoo_get_order_address($order);

    if (empty($address)) {
        wp_die(__("Order billing and shipping information is empty!", "route4me"));
    }

    $geocoderResponse = Route4Me::geocodeAddress($api_key, $address);

    if (is_wp_error($geocoderResponse)) {
        wp_die($geocoderResponse->get_error_message());
    }

    // Format Date
    $scheduled_date = $_POST['scheduledDate'];
    if (!empty($scheduled_date)) {
        $scheduled_date = date('Y-m-d', strtotime($scheduled_date));
    }

    $orderAlias = r4mwoo_order_alias($postId);

    // Add order
    $orderParameters = array(
        "address_1" => $geocoderResponse->address,
        "address_2" => $order->get_shipping_address_2(),
        "cached_lat" => $geocoderResponse->lat,
        "cached_lng" => $geocoderResponse->lng,
        "address_alias" => $orderAlias,
        "day_scheduled_for_YYMMDD" => $scheduled_date,
        "address_city" => r4mwoo_get_city($order),
        "EXT_FIELD_first_name" => r4mwoo_get_first_name($order),
        "EXT_FIELD_last_name" => r4mwoo_get_last_name($order),
        "EXT_FIELD_email" => $order->get_billing_email(),
        "EXT_FIELD_phone" => $order->get_billing_phone(),
        "EXT_FIELD_custom_data" => r4mwoo_get_order_custom_data($order),
    );

    $response = Route4Me::sendOrder($api_key, $orderParameters);

    if (is_wp_error($response)) {
        wp_die(__("Bad response. Please, try again later or contact Route4Me.", "route4me"));
    }

    $response = json_decode(wp_remote_retrieve_body($response));

    if (empty($response->order_id)) {
        wp_die(__("Bad response. Please, try again later or contact Route4Me.", "route4me"));
    }

    //update post meta
    update_post_meta($postId, 'route4me_order_id', (int) $response->order_id);

    // Output response
    ?>
	<div class="r4me_success_message"><?php printf(__(' &quot;%1$s&quot; successfully added to your Route4Me orders. Order ID on Route4Me - %2$s', 'route4me'), $orderAlias, (int) $response->order_id);?></div>

<?php

    die();
}

// Bulk Actions
add_action('admin_print_footer_scripts', 'r4mwoo_custom_bulk_admin');

function r4mwoo_custom_bulk_admin()
{

    global $post_type;

    if ($post_type == 'shop_order' && current_user_can('manage_woocommerce')) {
        ?>
    <script type="text/javascript">
     ;(function ($) {
     	$(document).ready(function(){
            $('select#bulk-action-selector-top, select#bulk-action-selector-bottom').addClass('r4me_bulk_select').attr('data-nonce', '<?php echo wp_create_nonce("r4me_geo_nonce"); ?>');
	   	 	$('<option>').val('r4me_export').text('<?php _e("Send to Route4Me", "route4me");?>').appendTo("select#bulk-action-selector-top");
        	$('<option>').val('r4me_export').text('<?php _e("Send to Route4Me", "route4me");?>').appendTo("select#bulk-action-selector-bottom");
     		$('body').append('<div id="r4me_lookup_show"></div><div id="r4me_preloader"></div>');
    	});
    }(jQuery));
    </script>
    <?php
}
}

// Bulk order function
add_action('wp_ajax_r4mwoo_bulk_order_action', 'r4mwoo_bulk_order_action');

function r4mwoo_bulk_order_action()
{

    check_admin_referer('r4me_geo_nonce', 'bulk_geo_nonce');

    if (empty(get_option('r4me_api_key'))) {
        echo '<div class="error">' . __("Route4Me Api key is not set", "route4me") . '</div>';
        die();
    } elseif (!current_user_can('manage_woocommerce')) {
        echo '<div class="error">' . __("You do not have sufficient permissions to take this action", "route4me") . '</div>';
        die();
    }

    $html = '<div class="r4me_final_bulk_form">';

    $orders = !empty($_POST['orders']) ? (array) $_POST['orders'] : null;

    $api_key = get_option('r4me_api_key');

    if (!empty($orders)):
        $selected_orders = [];
        $exported_orders = [];
        foreach ($orders as $order):
            $order_id = $order['id'];

            if (!r4mwoo_is_valid_id($order_id)) {
                continue;
            }

            $orderObject = wc_get_order($order_id);

            if (is_wp_error($orderObject) || empty($orderObject)) {
                continue;
            }

            if (!empty($order['exported']) && $order['exported'] === 'true') {
                $exported_orders[] = '#' . $order_id;
            }

            $selected_orders[$order_id] = r4mwoo_get_order_address($orderObject);
        endforeach;
        if (!empty($exported_orders)) {
            $html .= '<div class="error"><p>Some orders (' . implode(', ', $exported_orders) . ') are already exported</p></div>';
        }
        $geocoder_response = Route4Me::bulkGeocodeOrders($api_key, $selected_orders);

        if (is_wp_error($geocoder_response)) {
            die('<div class="error"><p>' . $geocoder_response->get_error_message() . '</p></div>');
        }

        if (empty($geocoder_response)) {
            die('<div class="error"><p>' . __('Please, make sure shipping addresses aren\'t empty', 'route4me') . '</p></div>');
        }

        $html .= '<div class="r4me-hidden-inputs">';
        foreach ($geocoder_response as $order_id => $geocoder_addr) {
            $html .= '<input type="text" class="hidden" data-order_id="' . (int) $order_id . '" data-lat="' . esc_attr($geocoder_addr->lat) . '" data-lng="' . esc_attr($geocoder_addr->lng) . '" value="' . esc_attr($geocoder_addr->address) . '" readonly="readonly"/>';
        }
        $html .= '</div>'; //.r4me-hidden-inputs

        // Schedule
        $tomorrow = new DateTime('tomorrow');
        $tomorrow = $tomorrow->format('m/d/Y');

        $html .= '<div class="r4me_metabox-row">';
        $html .= '<div class="metabox-desc r4me_metaflota">';
        $html .= '<label><strong>' . __('Scheduled For', 'route4me') . '</strong></label>';
        $html .= '<div class="small-desc">' . __('Choose a date you wish the order(s) to be scheduled for.', 'route4me') . '</div>';
        $html .= '</div>';
        $html .= '<div class="metabox-field r4me_metaflota">';
        $html .= '<input id="r4me_bulk_date" name="r4me_bulk_date" class="r4meDatepicker" type="text" data-date-format="mm/dd/yyyy" value="' . $tomorrow . '"/>';
        $html .= '</div><div class="clr"></div>';
        $html .= '</div>';

        $html .= '<div class="r4me_button_container"><button id="r4me_final_bulk_export" class="button button-large button-primary" data-nonce="' . wp_create_nonce('r4me_bulk') . '">' . __("Add to Route4Me", "route4me") . '</button></div>';

        $html .= '</div>'; //.r4me_final_bulk_form

        // if select is empty
    else:
        $html .= '<div class="error"><p>' . __('Please, select orders first!', 'route4me') . '</p></div>';
    endif;

    $html .= '</div>';

    echo $html;
    die();
}

// export bulk order function
add_action('wp_ajax_r4mwoo_add_bulk_action', 'r4mwoo_add_bulk_action');

function r4mwoo_add_bulk_action()
{

    check_admin_referer('r4me_bulk', 'bulk_nonce');

    if (empty(get_option('r4me_api_key'))) {
        echo '<div class="error"><p>' . __("Route4Me API key is not set", "route4me") . '</p></div>';
        die();
    } elseif (!current_user_can('manage_woocommerce')) {
        echo '<div class="error"><p>' . __("You do not have sufficient permissions to take this action", "route4me") . '</p></div>';
        die();
    } elseif (empty($_POST['order_date'])) {
        echo '<div class="error"><p>' . __("Please, schedule the order for a date!", "route4me") . '</p></div>';
        die();
    } elseif (!r4mwoo_is_valid_date($_POST['order_date'])) {
        echo '<div class="error"><p>' . __("The date is not valid!", "route4me") . '</p></div>';
        die();
    } elseif (empty($_POST['order_data'])) {
        echo '<div class="error"><p>' . __("Order data is empty. Make sure orders have shipping addresses assigned!", "route4me") . '</p></div>';
        die();
    }

    $api_key = get_option('r4me_api_key');

    $scheduled_date = date('Y-m-d', strtotime($_POST['order_date']));

    echo '<div class="r4me_bulk_note">';

    foreach ($_POST['order_data'] as $order_item):

        if (!r4mwoo_is_valid_id($order_item['order'])) {
            continue;
        }

        if (empty($order_item['address']) || empty($order_item['lng']) || empty($order_item['lat'])) {
            continue;
        }

        $orderId = (int) $order_item['order'];
        $geocoded_addr = sanitize_text_field($order_item['address']);
        $geocoded_lng = (float) $order_item['lng'];
        $geocoded_lat = (float) $order_item['lat'];

        $order = wc_get_order($orderId);

        if (is_wp_error($order)) {
            echo '<div class="error">' . sprintf(__('Error while exporting - #%s', 'route4me'), $orderId) . '</div>';
            continue;
        }

        $orderAlias = r4mwoo_order_alias($order->get_id());

        // Add order
        $orderParameters = array(
            "address_1" => $geocoded_addr,
            "address_2" => $order->get_shipping_address_2(),
            "cached_lat" => $geocoded_lat,
            "cached_lng" => $geocoded_lng,
            "address_alias" => $orderAlias,
            "day_scheduled_for_YYMMDD" => $scheduled_date,
            "address_city" => r4mwoo_get_city($order),
            "EXT_FIELD_first_name" => r4mwoo_get_first_name($order),
            "EXT_FIELD_last_name" => r4mwoo_get_last_name($order),
            "EXT_FIELD_email" => $order->get_billing_email(),
            "EXT_FIELD_phone" => $order->get_billing_phone(),
            "EXT_FIELD_custom_data" => r4mwoo_get_order_custom_data($order),
        );

        $response = Route4Me::sendOrder($api_key, $orderParameters);

        if (is_wp_error($response)) {
            echo '<div class="error">' . sprintf(__('Error while exporting - #%s', 'route4me'), $orderId) . '</div>';
            continue;
        }

        $response = json_decode(wp_remote_retrieve_body($response));

        if (empty($response->order_id)) {
            echo '<div class="error">' . sprintf(__('Error while exporting - #%s', 'route4me'), $orderId) . '</div>';
            continue;
        }

        update_post_meta($orderId, 'route4me_order_id', (int) $response->order_id);
        echo '<div class="updated">' . sprintf(__(' &quot;%s&quot; successfully exported', 'route4me'), $orderAlias) . '</div>';

    endforeach;

    echo '</div>'; //.r4me_bulk_note

    die();
}

// Add action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'r4mwoo_add_action_links');

function r4mwoo_add_action_links($links)
{
    $mylinks = array(
        '<a href="' . admin_url('options-general.php?page=r4m-settings') . '">Settings</a>',
        '<a href="https://support.route4me.com/route4me-plugin-for-the-woocommerce-e-commerce-platform/">Support</a>',
    );

    return array_merge($links, $mylinks);
}

function r4mwoo_get_tomorrow($format = null)
{
    $tomorrow = new DateTime('tomorrow');
    $format = (empty($format) ? 'm/d/Y' : $format);
    return $tomorrow->format($format);
}

function r4mwoo_get_first_name($order)
{
    if (!empty($order->get_shipping_first_name())) {
        return $order->get_shipping_first_name();
    }
    if (!empty($order->get_billing_first_name())) {
        return $order->get_billing_first_name();
    }
}

function r4mwoo_get_last_name($order)
{
    if (!empty($order->get_shipping_last_name())) {
        return $order->get_shipping_last_name();
    }
    if (!empty($order->get_billing_last_name())) {
        return $order->get_billing_last_name();
    }
}

function r4mwoo_get_city($order)
{
    if (!empty($order->get_shipping_city())) {
        return $order->get_shipping_city();
    }
    if (!empty($order->get_billing_city())) {
        return $order->get_billing_city();
    }
}

function r4mwoo_order_already_sent($order_id)
{
    return !empty(get_post_meta($order_id, 'route4me_order_id', true));
}

function r4mwoo_get_post_params($geocoder_response, $order)
{
    return array(
        "address_1" => $geocoder_response->address,
        "address_2" => $order->get_shipping_address_2(),
        "cached_lat" => $geocoder_response->lat,
        "cached_lng" => $geocoder_response->lng,
        "address_alias" => r4mwoo_order_alias($order->get_id()),
        "day_scheduled_for_YYMMDD" => r4mwoo_get_tomorrow('Y-m-d'),
        "address_city" => r4mwoo_get_city($order),
        "EXT_FIELD_first_name" => r4mwoo_get_first_name($order),
        "EXT_FIELD_last_name" => r4mwoo_get_last_name($order),
        "EXT_FIELD_email" => $order->get_billing_email(),
        "EXT_FIELD_phone" => $order->get_billing_phone(),
        "EXT_FIELD_custom_data" => r4mwoo_get_order_custom_data($order),
    );
}

function r4mwoo_get_full_state($state, $country)
{
    if (!empty($state)) {
        $country_states = WC()->countries->get_states($country);
        if (!empty($country_states[$state])) {
            if (is_array($country_states[$state])) {
                return reset($country_states[$state]);
            }
            return $country_states[$state];
        }
        return $state;
    }
}

function r4mwoo_get_full_country($country)
{
    return (!empty(WC()->countries->countries[$country]) ? WC()->countries->countries[$country] : $country);
}