<?php
function r4mwoo_enqueue_scripts() {
    wp_enqueue_style( 'r4mwoo_style', R4MWOO_PATH . '/css/style.css', false, '1.0.3' );
    
    wp_enqueue_script('thickbox');
    wp_enqueue_style('thickbox');

    if(get_post_type() == 'shop_order'){
    	wp_enqueue_script( 'r4mwoo_script', R4MWOO_PATH . '/js/script.js', array('jquery'), '1.0.4', true);
    }

    global $pagenow;
    if($pagenow === 'options-general.php' && !empty($_GET['page']) && $_GET['page'] === 'r4m-settings'){
        wp_enqueue_script( 'r4mwoo_settings_script', R4MWOO_PATH . '/js/r4m-settings-scripts.js', array('jquery'), '1.0.0', true);
    }

}
add_action( 'admin_enqueue_scripts', 'r4mwoo_enqueue_scripts' );