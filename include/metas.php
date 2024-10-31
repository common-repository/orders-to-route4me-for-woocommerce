<?php 

function r4mwoo_meta_boxes() {
    add_meta_box( 'r4me_meta', __('Add to Route4Me orders', 'route4me'), 'r4mwoo_meta_callback', 'shop_order', 'side', 'low' );
}

add_action( 'add_meta_boxes', 'r4mwoo_meta_boxes' );


function r4mwoo_meta_callback( $post ) {  
    
    //Check if current user can manage Woocommerce
    if(current_user_can( 'manage_woocommerce' ) === false){
        return;
    } 

    // Check if Route4Me key is set
   if (get_option('r4me_api_key') === false): ?>
     <p><?php _e('Route4Me API Key is not inserted.', 'route4me')?> <br> <a href="<?php echo admin_url('options-general.php?page=r4m-settings'); ?>"><?php _e('Please, Insert the API key') ?></a></p>
   <?php return; endif; ?>
   
   <?php if('auto-draft' !== get_post_status( $post->ID )): 
    $r4m_order_id = get_post_meta( get_the_ID(), 'route4me_order_id', true );
    ?> 
    <div class="r4me_meta_wrapper">
        <?php if(!empty($r4m_order_id)): ?>
        <div class="r4m-exported-note">
            <p>This order is already sent to Route4me. </p><p><a class="button tips r4m-lookup-lg" id="r4me_lookup" data-order_id="<?php echo esc_attr($r4m_order_id); ?>" data-tip="<?php _e('Lookup the Route4Me order status.', 'route4me'); ?>" data-nonce="<?php echo wp_create_nonce('r4me_lookup'); ?>"><img src="<?php echo R4MWOO_PATH . '/img/r4m.png'; ?>"> Order Status</a><img class="r4me_list_spinner" src="<?php echo R4MWOO_PATH . '/img/spinner.gif'; ?>" /></p>
        </div>
        <?php endif; ?>
        <div class="r4me-schedule">
            <label><?php _e('Scheduled For', 'route4me'); ?> <span class="woocommerce-help-tip" data-tip="<?php _e('Choose a date you wish the order to be scheduled for.', 'route4me'); ?>"></span></label>
            <input id="r4me_schedule_date" name="r4me_schedule_date" class="r4meDatepicker" type="text" value="<?= r4mwoo_get_tomorrow() ?>" data-date-format="dd/mm/yyyy"/>
            <small id="r4me_schedule_date_required" class="text-danger hidden"><?php _e('This field is required', 'route4me'); ?> </small>
        </div>
        <div id="r4me_response"></div>                
        <button id="r4me_order_btn" class="button button-primary" data-nonce="<?php echo wp_create_nonce('r4me_export'); ?>" data-id="<?php echo $post->ID; ?>"><?php _e('Add to Route4Me', 'route4me'); ?></button>
    </div>
    <?php else: ?>
    <p><?php _e('Please, create the order first.', 'route4me')?> </p>
    <?php endif; 
} 

