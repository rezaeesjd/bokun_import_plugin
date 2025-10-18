<?php
/*
Plugin Name: Import Bokun to WP Ecommerce and Custom Fileds
Plugin URI: 
Description: Integrates Bokun tours into Ecommerce and any Custom Post Type using API and Custom Fields.
Version: 1.0.0
Author: WebSage Solutions
Author URI: https://www.websagesolutions.com/
Domain Path: /languages
Text Domain: import-bokun-to-wp-ecommerce-and-custom-fileds
Plugin URI: https://www.websagesolutions.com/bokun-plugin-download/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: KNCPT, bokun, woocommerce, integration, tours
Requires at least: 5.0
Requires PHP: 7.0
Stable tag: 1.0.0
Contributors: Hitesh, Sajjad
*/

define('BKNCPT_PLUGIN', '/import-bokun-to-wp-ecommerce-and-custom-fileds/');

// directory define
define( 'BKNCPT_PLUGIN_DIR', WP_PLUGIN_DIR.BKNCPT_PLUGIN);
define( 'BKNCPT_INCLUDES_DIR', BKNCPT_PLUGIN_DIR.'includes/' );

if( file_exists( BKNCPT_PLUGIN_DIR . "main.php" ) ) {
    include_once( BKNCPT_PLUGIN_DIR . "main.php" );
}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'bokun_plugin_action_links' );
function bokun_plugin_action_links( $actions ) {
    $actions[] = '<a href="'. esc_url( get_admin_url(null, 'admin.php?page=bokun-manage-keys') ) .'">Settings</a>';
    return $actions;
}

register_activation_hook(__FILE__, 'bkncpt_plugin_activate');
add_action('admin_init', 'bkncpt_plugin_redirect');

function bkncpt_plugin_activate() {
    update_option('bkncpt_is_active', true);
}

function bkncpt_plugin_redirect() {
    
    if (get_option('bkncpt_is_active')) {
        delete_option('bkncpt_is_active');
        wp_redirect("admin.php?page=bokun-auth-check");
        exit;
    }
}