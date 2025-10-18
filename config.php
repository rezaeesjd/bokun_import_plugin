<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// config.php
error_reporting(E_ALL);
// Your Bokun API credentials
if (!defined('BOKUN_API_KEY')) {
    $saved_api_key = get_option('bokun_api_key', '');
    define('BOKUN_API_KEY', $saved_api_key);
}

if (!defined('BOKUN_SECRET_KEY')) {
    $saved_secret_key = get_option('bokun_secret_key', '');
    define('BOKUN_SECRET_KEY', $saved_secret_key);
}

if (!defined('BOKUN_POST_TYPE')) {
    $selected_post_type = get_option('bokun_post_type', '');
    define('BOKUN_POST_TYPE', $selected_post_type);
}

// Bokun API endpoint details
if (!defined('BOKUN_API_BASE_URL')) {
    define('BOKUN_API_BASE_URL', 'https://api.bokun.io');
}

if (!defined('BOKUN_API_ACTIVITY_ENDPOINT_PATH')) {
    define('BOKUN_API_ACTIVITY_ENDPOINT_PATH', '/activity.json');
}

if (!defined('BOKUN_API_PRODUCT_LIST_ENDPOINT_PATH')) {
    define('BOKUN_API_PRODUCT_LIST_ENDPOINT_PATH', '/product-list.json');
}

if (!defined('BOKUN_API_BOOKING_API')) {
    define('BOKUN_API_BOOKING_API', '/booking.json/booking-search');
}
