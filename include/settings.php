<?php
// Add Route4Me menu page to Wordpress Settings
add_action('admin_menu', 'r4me_plugin_menu');

function r4me_plugin_menu()
{
    add_options_page(__('Route4Me Options', 'route4me'), 'Route4Me', 'manage_woocommerce', 'r4m-settings', 'r4me_plugin_options');
}

function r4me_plugin_options()
{

    //manage_options
    if (!current_user_can('manage_woocommerce')) {
        return __('You do not have sufficient permissions to access this page.');
    }

    // variables for the field and option names
    $opt_key_name = 'r4me_api_key';
    $opt_sync_name = 'r4me_auto_send';
    $opt_alias_name = 'r4me_order_alias';
    $opt_sync_status_name = 'r4m_sync_status';
    $hidden_field = 'r4me_submit_hidden';

    // Read in existing option value from database
    $opt_key_val = get_option($opt_key_name);
    $opt_sync_val = get_option($opt_sync_name);
    $opt_alias_val = get_option($opt_alias_name);
    $opt_sync_status_val = get_option($opt_sync_status_name);

    // Verify nonce
    if (isset($_POST[$hidden_field]) && wp_verify_nonce($_POST[$hidden_field], basename(__FILE__))) {
        // Read their posted value
        $opt_key_val = sanitize_key($_POST[$opt_key_name]);
        $opt_sync_val = sanitize_key($_POST[$opt_sync_name]);
        $opt_alias_val = sanitize_text_field($_POST[$opt_alias_name]);
        $opt_sync_status_val = sanitize_text_field($_POST[$opt_sync_status_name]);

        if (!empty($opt_key_val)) {

            // Check key is valid before inserting anything into the database
            $isValidKey = Route4Me::isValidKey($opt_key_val);

            if ($isValidKey->status):
                // Save the posted value in the database
                update_option($opt_key_name, $opt_key_val);
                update_option($opt_sync_name, $opt_sync_val);
                update_option($opt_alias_name, $opt_alias_val);
                update_option($opt_sync_status_name, $opt_sync_status_val);

                // Put a "settings saved" message on the screen

                ?>
			<div class="updated"><p><strong><?php _e('Settings saved.', 'route4me');?></strong></p></div>
	        <?php elseif ($isValidKey->code === 403): ?>
            <div class="error"><p><strong><?php _e('Error validating the Account. Make sure you have Orders enabled in your Route4Me account. For more info, contact at contact@route4me.com', 'route4me');?></strong></p></div>
        <?php else: ?>
	        <div class="error"><p><strong><?php _e('API Key is invalid or something went wrong. Try again, if no luck contact at contact@route4me.com', 'route4me');?></strong></p></div>
        <?php
        endif;
        } else {?>
        <div class="error"><p><strong><?php _e('API key is required!', 'route4me');?></strong></p></div>
    <?php }
    }

    // Now display the settings editing screen

    echo '<div class="wrap">';

    // header
    echo "<h1>" . __('Route4Me Plugin Settings', 'route4me') . "</h1>";

    // If no API key display promo message
    if (get_option('r4me_api_key') === false) {
        echo '<h4>' . __('Route4Me is the best route planner in the world.', 'route4me') . ' <a href="https://route4me.com/platform/marketplace/pricing" target="_blank">' . __('Begin your 7-day trial', 'route4me') . '</a></h4>';
    }

    // settings form

    ?>

<form name="r4me_settings_form" method="post" action="">
<?php wp_nonce_field(basename(__FILE__), $hidden_field);?>
<table class="form-table">
    <tbody>
        <tr>
            <th scope="row"><?php _e("Route4Me API key", 'route4me');?></th>
            <td>
                <input type="password" name="<?php echo $opt_key_name; ?>" value="<?php esc_attr_e($opt_key_val);?>" size="40">
                <p class="description" id="r4me-api-key-description"><?php _e("Login to your Route4Me account and get the key on Route4Me API page", "route4me")?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e("Auto Send Orders to Route4Me", 'route4me');?></th>
            <td>
                <select name="<?php echo $opt_sync_name; ?>" id="<?php echo $opt_sync_name; ?>">
                    <option value="yes">Yes</option>
                    <option value="no" <?php if ($opt_sync_val != "yes") {
        echo "selected";
    }
    ?>>No</option>
                </select>
                <p class="description" id="r4me-sync-description"><?php _e("Choose to synchronize orders to Route4Me", "route4me");?></p>
            </td>
        </tr>
        <tr id="r4m_send_by_order_status_section" <?php echo ($opt_sync_val != 'yes' ? 'class="r4m_hidden"' : ''); ?>>
            <th scope="row"><?php _e("Auto send orders by status to Route4Me", 'route4me');?></th>
            <td>
                <select name="<?php echo $opt_sync_status_name; ?>">
                    <option value="processing">Auto send Processing orders</option>
                    <option value="pending_payment" <?php if ($opt_sync_status_val === "pending_payment") {
        echo "selected";
    }
    ?>>Auto send Pending Payment orders</option>
                </select>
                <p class="description" id="r4me-sync-description"><?php _e("Choose which orders to automatically send to Route4Me", "route4me");?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _e("Order alias", 'route4me');?></th>
            <td>
                <input type="text" name="<?php echo $opt_alias_name; ?>" value="<?php echo (!empty($opt_alias_val) ? esc_attr($opt_alias_val) : '[order_id] from [site_name] (Woocommerce)'); ?>" size="42">
                <p class="description" id="r4me-alias-description"><?php _e("You can use variables: [order_id] and [site_name]", "route4me");?></p>
            </td>
        </tr>
    </tbody>
</table>

<p class="submit">
<input type="submit" name="Submit" id="submit" class="button button-primary" value="<?php _e('Save Changes', 'route4me');?>" />
</p>

</form>
</div>
<?php
}