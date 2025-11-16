<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include the configuration
if( file_exists( BKNCPT_PLUGIN_DIR . "config.php" ) ) {
    include_once( BKNCPT_PLUGIN_DIR . "config.php" );
}
if( file_exists( BKNCPT_PLUGIN_DIR . "wp-dashboard.php" ) ) {
    include_once( BKNCPT_PLUGIN_DIR . "wp-dashboard.php" );
}
if( file_exists( BKNCPT_PLUGIN_DIR . "ajax-functions.php" ) ) {
    include_once( BKNCPT_PLUGIN_DIR . "ajax-functions.php" );
}

// Function to get product list title by ID
function bkncpt_get_product_list_title($listId) {
    // Construct the API request URL for product list by ID
    $api_url = BOKUN_API_BASE_URL . BOKUN_API_PRODUCT_LIST_ENDPOINT_PATH . '/' . $listId;

    // Set up headers for authentication
    $headers = array(
        'X-Bokun-Date' => gmdate('Y-m-d H:i:s'),
        'X-Bokun-AccessKey' => BOKUN_API_KEY,
    );

    // Generate the signature for authentication
    $signature = base64_encode(hash_hmac('sha1', gmdate('Y-m-d H:i:s') . BOKUN_API_KEY . 'GET' . '/product-list.json/' . $listId, BOKUN_SECRET_KEY, true));
    $headers['X-Bokun-Signature'] = $signature;

    // Set up HTTP request arguments
    $request_args = array(
        'headers' => $headers,
    );

    // Make the HTTP request
    $response = wp_remote_get($api_url, $request_args);

    // Check for errors
    if (is_wp_error($response)) {
        return 'Error retrieving product list title: ' . $response->get_error_message();
    }

    // Decode the JSON response
    $data = json_decode(wp_remote_retrieve_body($response), true);

    // Return the product list title
    return isset($data['title']) ? $data['title'] : 'Product list title not found';
}
// Function to get product lists
function bkncpt_get_product_lists() {
    // Construct the API request URL for product lists
    $api_url = BOKUN_API_BASE_URL . '/product-list.json/list';

    // Set up headers for authentication
    $headers = array(
        'X-Bokun-Date' => gmdate('Y-m-d H:i:s'),
        'X-Bokun-AccessKey' => BOKUN_API_KEY,
    );

    // Generate the signature for authentication
    $signature = base64_encode(hash_hmac('sha1', gmdate('Y-m-d H:i:s') . BOKUN_API_KEY . 'GET' . '/product-list.json/list', BOKUN_SECRET_KEY, true));
    $headers['X-Bokun-Signature'] = $signature;

    // Set up HTTP request arguments
    $request_args = array(
        'headers' => $headers,
    );

    // Make the HTTP request
    $response = wp_remote_get($api_url, $request_args);

    // Check for errors
    if (is_wp_error($response)) {
        return array('error' => 'Error retrieving product lists: ' . $response->get_error_message());
    }

    // Decode the JSON response
    $data = json_decode(wp_remote_retrieve_body($response), true);

    // Return the list of product lists
    return isset($data) ? $data : array();
}

// Function to display product lists dropdown
function bkncpt_display_product_lists_dropdown($selectedListId) {
    $productLists = bkncpt_get_product_lists();

    if (isset($productLists['error'])) {
        echo '<p>' . esc_attr($productLists['error']) . '</p>';
        return;
    }

    echo '<label for="product_list_id">Product List: </label>';
    echo '<select name="product_list_id" id="product_list_id">';
    
    foreach ($productLists as $productList) {
        $selected = selected($selectedListId, $productList['id'], false);
        echo '<option value="' . esc_attr($productList['id']) . '" ' . esc_attr($selected) . '>' . esc_html($productList['title']) . '</option>';
    }

    echo '</select>';
}

function bkncpt_get_activities_in_product_list($listId) {
    // Construct the API request URL for activities in the product list by ID
    $api_url = BOKUN_API_BASE_URL . BOKUN_API_PRODUCT_LIST_ENDPOINT_PATH . '/' . $listId;

    // Set up headers for authentication
    $headers = array(
        'X-Bokun-Date' => gmdate('Y-m-d H:i:s'),
        'X-Bokun-AccessKey' => BOKUN_API_KEY,
    );

    // Generate the signature for authentication
    $signature = base64_encode(hash_hmac('sha1', gmdate('Y-m-d H:i:s') . BOKUN_API_KEY . 'GET' . '/product-list.json/' . $listId, BOKUN_SECRET_KEY, true));
    $headers['X-Bokun-Signature'] = $signature;

    // Set up HTTP request arguments
    $request_args = array(
        'headers' => $headers,
    );

    // Make the HTTP request
    $response = wp_remote_get($api_url, $request_args);

    // Check for errors
    if (is_wp_error($response)) {
        return array('error' => 'Error retrieving activities in the product list: ' . $response->get_error_message());
    }

    // Decode the JSON response
    $data = json_decode(wp_remote_retrieve_body($response), true);

    // Check if "items" key exists and is not empty
    if (isset($data['items']) && !empty($data['items'])) {
        $activities = array();

        // Loop through each item and extract the activity details
        foreach ($data['items'] as $item) {
            if (isset($item['activity'])) {
                $activity_details = $item['activity'];

                // Add the complete activity details to the activities array
                $activities[] = $activity_details;
            }
        }

        return $activities;
    }

    return array('error' => 'No activities found in the product list');
}

function bkncpt_get_booking_list() {

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.bokun.io/booking.json/booking-search');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{\n  \"confirmationCode\": \"string\",\n  \"externalBookingReference\": \"string\",\n  \"externalBookingEntityName\": \"string\",\n  \"externalBookingEntityCode\": \"string\",\n  \"bookingLabelIds\": \"string\",\n  \"paymentId\": \"string\",\n  \"textFilter\": \"string\",\n  \"country\": \"string\",\n  \"city\": \"string\",\n  \"bookingStatuses\": [\n    \"string\"\n  ],\n  \"sortFields\": [\n    {\n      \"name\": \"string\",\n      \"order\": \"ASC\"\n    }\n  ],\n  \"bookingSource\": \"string\",\n  \"bookingChannelId\": 0,\n  \"extranetUserId\": 0,\n  \"agentId\": 0,\n  \"sellerId\": 0,\n  \"supplierId\": 0,\n  \"promoCodeId\": 0,\n  \"conversationAwaitingVendorId\": 0,\n  \"modifiedAfterCreation\": true,\n  \"conversationOpen\": true,\n  \"affiliateHubOnly\": true,\n  \"excludeAffiliateHub\": true,\n  \"page\": 0,\n  \"pageSize\": 0,\n  \"creationDateRange\": {\n    \"from\": \"2024-04-19T02:42:12.234Z\",\n    \"includeLower\": true,\n    \"includeUpper\": true,\n    \"to\": \"2024-04-19T02:42:12.234Z\"\n  },\n  \"startDateRange\": {\n    \"from\": \"2024-04-15T02:42:12.234Z\",\n    \"includeLower\": true,\n    \"includeUpper\": true,\n    \"to\": \"2024-04-25T02:42:12.234Z\"\n  },\n  \"lastModifiedDateRange\": {\n    \"from\": \"2024-04-15T02:42:12.234Z\",\n    \"includeLower\": true,\n    \"includeUpper\": true,\n    \"to\": \"2024-04-25T02:42:12.234Z\"\n  },\n  \"cancellationDateRange\": {\n    \"from\": \"2024-04-25T02:42:12.234Z\",\n    \"includeLower\": true,\n    \"includeUpper\": true,\n    \"to\": \"2024-04-25T02:42:12.234Z\"\n  }\n}");

    $headers = array();
    $headers[] = 'Accept: application/json';
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'X-Bokun-Date: 2024-05-22 02:42:41';
    $headers[] = 'X-Bokun-Signature: TtQV4XoXBFcOZy38T+1DQtJ5CKY=';
    $headers[] = 'X-Bokun-Accesskey: 30187a022ae44a2c8fe8dd630e80a6a0';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);
    echo '<pre>';
    print_r($result);
    die;
    // Construct the API request URL for activities in the product list by ID
    $api_url = BOKUN_API_BASE_URL . BOKUN_API_BOOKING_API;
    
    // Set up headers for authentication
    $headers = array(
        'X-Bokun-Date' => gmdate('Y-m-d H:i:s'),
        'X-Bokun-AccessKey' => BOKUN_API_KEY,
    );

    // Generate the signature for authentication
    echo $signature = base64_encode(hash_hmac('sha1', gmdate('Y-m-d H:i:s') . BOKUN_API_KEY . 'POST' . '/booking.json/booking-search', BOKUN_SECRET_KEY, false));
    $headers['X-Bokun-Signature'] = $signature;

    // Set up HTTP request arguments
    $request_args = array(
        'headers' => $headers,
    );

    // Make the HTTP request
    echo '<br/>';
    echo $api_url;
    echo '<pre>';
    print_r($request_args);
    echo '</pre>';
    $response = wp_remote_get($api_url, $request_args);
    

    // Check for errors
    if (is_wp_error($response)) {
        $a = array('error' => 'Error retrieving activities in the product list: ' . $response->get_error_message());
        echo '<pre>';
        print_r($a);
        echo '</pre>';
    }

    // Decode the JSON response
    $data = json_decode(wp_remote_retrieve_body($response), true);
    echo 'data';
    echo '<pre>';
    print_r($data);
    die;
    // Check if "items" key exists and is not empty
    if (isset($data['items']) && !empty($data['items'])) {
        $activities = array();

        // Loop through each item and extract the activity details
        foreach ($data['items'] as $item) {
            if (isset($item['activity'])) {
                $activity_details = $item['activity'];

                // Add the complete activity details to the activities array
                $activities[] = $activity_details;
            }
        }

        return $activities;
    }

    return array('error' => 'No activities found in the product list');
}
// Function to display the result on the admin page
function bkncpt_bokun_auth_check_page() {
    
    if( BOKUN_API_KEY == '' || BOKUN_SECRET_KEY == '') {
        header("Location: admin.php?page=bokun-manage-keys");
        die;
    } else {
        // Default product list ID
        $defaultProductListId = 66035;
        
        // Check if the form is submitted
        if (isset($_POST['product_list_id'])) {
            if ( ! wp_verify_nonce( $_POST['_wpnonce'], '_wpnonce_bokun-list' ) ) {
                return;
                // Anything that you want to display for unauthorized action
            } 
            // Use the submitted product list ID if available
            $productListId = sanitize_text_field($_POST['product_list_id']);
        } else {
            // Use the default product list ID
            $productListId = $defaultProductListId;
        }
        $allowfilelds = array(
            // 'id' => 'ID',
            'externalId' => "Externale Id",
            'productCategory' => "Product Category",
        );
        // Get product list title and activities
        $productListTitle = bkncpt_get_product_list_title($productListId);
        $activitiesInProductList = bkncpt_get_activities_in_product_list($productListId);

        echo '<div class="wrap">';
        echo '<h1>';
        esc_html_e( 'Bo heck', 'import-bokun-to-wp-ecommerce-and-custom-fileds' );
        echo '</h1>';

        // Add a form for dynamic product list ID
        echo '<form method="post" action="">';
        esc_html(wp_nonce_field('_wpnonce_bokun-list'));
        // Display product lists dropdown
        bkncpt_display_product_lists_dropdown($productListId);

        echo '<input type="submit" class="button page-title-action" value="'.esc_html( 'Retrieve Activities', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'">';
        echo '</form>';

        echo '<p>'.esc_html( 'Product List Title:', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'<strong>' . esc_attr(strtoupper($productListTitle)) . '</strong></p>';

        if (isset($activitiesInProductList['error'])) {
            echo '<p>' . esc_attr($activitiesInProductList['error']) . '</p>';
        } else {
            echo '<table id="activities-table" class="wp-list-table widefat fixed striped table-view-list ">';
            echo '<thead>';
            echo '<tr>';
            echo '<th id="cb" class="bkncpt-checkbox manage-column column-cb check-column"><input id="cb-select-all-1" class="select_all" type="checkbox"></th>';
            echo '<th class="manage-column column-cb" scope="col"><a href="javascript:;"><span>'.esc_html( 'Action', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '<th class="manage-column column-cb" scope="col"><a href="javascript:;"><span>'.esc_html( 'Product ID', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '<th class="manage-column column-cb" scope="col"><a href="javascript:;"><span>'.esc_html( 'Activity Title', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            
            // Get the list of fields dynamically from the first activity
            $firstActivity = reset($activitiesInProductList);
            if ($firstActivity) {
                foreach ($firstActivity as $field => $value) {
                    if(array_key_exists($field, $allowfilelds)) {                    
                        $field1 = $allowfilelds[$field];
                        echo '<th class="manage-column column-cb" scope="col"><a href="javascript:;"><span>' . esc_html($field1) . '</span></a></th>';
                    }
                }
            }
            echo '<th scope="col"><a href="javascript:;"><span>'.esc_html( 'View Details', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tfoot>';
            echo '<tr>';
            echo '<th id="cb" class="bkncpt-checkbox manage-column column-cb check-column"><input id="cb-select-all-1" class="select_all" type="checkbox"></th>';
            echo '<th scope="col"><a href="javascript:;"><span>'.esc_html( 'Action', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '<th scope="col"><a href="javascript:;"><span>'.esc_html( 'Product ID', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '<th scope="col"><a href="javascript:;"><span>'.esc_html( 'Activity Title', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';

            // Get the list of fields dynamically from the first activity
            $firstActivity = reset($activitiesInProductList);
            if ($firstActivity) {
                foreach ($firstActivity as $field => $value) {
                    if(array_key_exists($field, $allowfilelds)) {                    
                        $field1 = $allowfilelds[$field];
                        echo '<th scope="col"><a href="javascript:;"><span>' . esc_html($field1) . '</span></a></th>';
                    }
                }
            }
            echo '<th scope="col"><a href="javascript:;"><span>'.esc_html( 'View Details', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '</tr>';
            echo '</tfoot>';
            echo '<tbody id="the-list">';
            $rowCount = 0;
            foreach ($activitiesInProductList as $activity) {
                $rowCount++;

                echo '<tr>';
                echo '<td class="bokun_field_value"><input class="bokun_post_cb" type="checkbox" name="bokun_post[]" value="'.$activity['id'].'"></td>';
                echo '<td><button class="button page-title-action import-activity import-activity-' . esc_attr($activity['id']) . ' " data-activity-title="' . esc_attr($activity['title']) . '" data-activity-id="' . esc_attr($activity['id']) . '">'.esc_html( 'Import', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</button></td>';
                echo '<td>' . esc_html($activity['id']) . '</td>';
                echo '<td class="bokun_field_value">' . esc_html($activity['title']) . '</td>';
                // Display values for all fields
                $view_href = 'admin.php?page=bokun-item-details&productListId='.$productListId.'&id='.esc_attr($activity['id']);
                foreach ($activity as $field => $value) {
                    if(array_key_exists($field, $allowfilelds)) { 
                        
                        if ($field === 'photos' && is_array($value)) {
                            // If it's the 'photos' field and it's an array, extract and display URLs
                            echo '<td class="bokun_thum_container">';
                            foreach ($value as $photo) {
                                if (isset($photo['originalUrl'])) {
                                    //echo '<img src="' . esc_url($photo['originalUrl']) . '" alt="Photo" class="bokul_image">';
                                }
                            }
                            echo '</td>';
                        } else {
                            if(!is_array($value)) {}
                            echo '<td class="bokun_field_value">' . esc_html($value) . '</td>';                    
                            // Display other fields as usual
                            // echo '<td class="bokun_field_value">' . esc_html($value) . '</td>';
                        }
                    }
                }
                echo '<td><a href="'.$view_href.'" class="button page-title-action" >'.esc_html( 'View', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</a></td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';

            // Import All button and Drive sync action
            echo '<div class="bkncpt-bulk-actions">';
            echo '<button class="button import-all-activities" data-only="0" >'.esc_html( 'Import All', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</button>';
            echo ' <button class="button bkncpt-sync-drive" data-only="0">' . esc_html__( 'Sync Images to Google Drive', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ) . '</button>';
            echo '</div>';
            echo '<div class="progress-wrapper">';
            echo '            <progress class="bkncpt-progress-bar bkncpt-hide" max="100" value="0"></progress>';
            echo '            <span class="progress-text bkncpt-hide">0%</span>';
            echo '          </div>';
            
        }

        echo '</div>';
    }
}

function bkncpt_get_bokun_widget_html() {
    global $post;

    if (!$post instanceof WP_Post) {
        return '';
    }

    $custom_field_value = get_post_meta($post->ID, 'bk_actualId', true);
    if (!$custom_field_value) {
        return '';
    }

    $widget_html  = '<div class="bokunWidget" data-src="https://widgets.bokun.io/online-sales/0bae61d4-b5ab-4e33-833d-d912b558805c/experience-calendar/' . esc_attr($custom_field_value) . '"></div>';
    $widget_html .= '<noscript>' . esc_html__('Please enable javascript in your browser to book', 'import-bokun-to-wp-ecommerce-and-custom-fileds') . '</noscript>';

    return $widget_html;
}

add_shortcode('bokun_widget', 'bkncpt_get_bokun_widget_html');


// Enqueue DataTables library and script in the admin footer
function enqueue_bkncpt_scripts() {
    // Enqueue jQuery first (if not already enqueued)
    wp_enqueue_script('jquery');

    // Enqueue DataTables stylesheet
    wp_enqueue_style('datatables-style', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css', array(), '1.11.5');
    
    wp_enqueue_style('kncpt-style', plugin_dir_url(__FILE__) .'css/bokun-styles.css?rand='.wp_rand(), array(), '1.0.5');
    // Enqueue DataTables library
    wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', array('jquery'), '1.11.5', true);

    // Enqueue your custom script
    wp_enqueue_script('kncpt-import-script', plugin_dir_url(__FILE__) . 'bokun-import-script.js?rand='.wp_rand(), array('jquery', 'datatables'), '1.0', true);

    // Pass necessary variables to the script
    wp_localize_script('kncpt-import-script', 'bkncpt_import_script_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ajax-nonce')
    ));

    // Add script to initialize DataTables
    wp_add_inline_script('kncpt-import-script', 'jQuery(document).ready(function($) { $("#activities-table").DataTable(); });', 'after');
}
add_action('admin_enqueue_scripts', 'enqueue_bkncpt_scripts');

function enqueue_print_scripts() {
    
    if(BOKUN_API_KEY == '' || BOKUN_SECRET_KEY == '' || BOKUN_POST_TYPE == '') {
    ?>
        <style>
        .toplevel_page_bokun-auth-check ul.wp-submenu.wp-submenu-wrap li.wp-first-item {
            pointer-events: none;
        }
        </style>
    <?php
    }
}
add_action( 'wp_print_scripts', 'enqueue_print_scripts'  );

function bkncpt_bokun_booking_list() {
    
    if( BOKUN_API_KEY == '' || BOKUN_SECRET_KEY == '') {
        header("Location: admin.php?page=bokun-manage-keys");
        die;
    } else {
        // Default product list ID
        $defaultProductListId = $productListId = 66035;
        
        // Get product list title and activities
        $activitiesInProductList = bkncpt_get_booking_list();
        echo '<pre>';
        print_r($activitiesInProductList);
        die;

        echo '<div class="wrap">';
        echo '<h1>';
        esc_html_e( 'Bo heck', 'import-bokun-to-wp-ecommerce-and-custom-fileds' );
        echo '</h1>';

        // Add a form for dynamic product list ID
        echo '<form method="post" action="">';
        esc_html(wp_nonce_field('_wpnonce_bokun-list'));
        // Display product lists dropdown
        bkncpt_display_product_lists_dropdown($productListId);

        echo '<input type="submit" class="button page-title-action" value="'.esc_html( 'Retrieve Activities', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'">';
        echo '</form>';

        echo '<p>'.esc_html( 'Product List Title:', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'<strong>' . esc_attr(strtoupper($productListTitle)) . '</strong></p>';

        if (isset($activitiesInProductList['error'])) {
            echo '<p>' . esc_attr($activitiesInProductList['error']) . '</p>';
        } else {
            echo '<table id="activities-table" class="wp-list-table widefat fixed striped table-view-list ">';
            echo '<thead>';
            echo '<tr>';
            echo '<th id="cb" class="bkncpt-checkbox manage-column column-cb check-column"><input id="cb-select-all-1" class="select_all" type="checkbox"></th>';
            echo '<th class="manage-column column-cb" scope="col"><a href="javascript:;"><span>'.esc_html( 'Action', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '<th class="manage-column column-cb" scope="col"><a href="javascript:;"><span>'.esc_html( 'Product ID', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '<th class="manage-column column-cb" scope="col"><a href="javascript:;"><span>'.esc_html( 'Activity Title', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            
            // Get the list of fields dynamically from the first activity
            $firstActivity = reset($activitiesInProductList);
            if ($firstActivity) {
                foreach ($firstActivity as $field => $value) {
                    if(array_key_exists($field, $allowfilelds)) {                    
                        $field1 = $allowfilelds[$field];
                        echo '<th class="manage-column column-cb" scope="col"><a href="javascript:;"><span>' . esc_html($field1) . '</span></a></th>';
                    }
                }
            }
            echo '<th scope="col"><a href="javascript:;"><span>'.esc_html( 'View Details', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tfoot>';
            echo '<tr>';
            echo '<th id="cb" class="bkncpt-checkbox manage-column column-cb check-column"><input id="cb-select-all-1" class="select_all" type="checkbox"></th>';
            echo '<th scope="col"><a href="javascript:;"><span>'.esc_html( 'Action', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '<th scope="col"><a href="javascript:;"><span>'.esc_html( 'Product ID', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '<th scope="col"><a href="javascript:;"><span>'.esc_html( 'Activity Title', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';

            // Get the list of fields dynamically from the first activity
            $firstActivity = reset($activitiesInProductList);
            if ($firstActivity) {
                foreach ($firstActivity as $field => $value) {
                    if(array_key_exists($field, $allowfilelds)) {                    
                        $field1 = $allowfilelds[$field];
                        echo '<th scope="col"><a href="javascript:;"><span>' . esc_html($field1) . '</span></a></th>';
                    }
                }
            }
            echo '<th scope="col"><a href="javascript:;"><span>'.esc_html( 'View Details', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</span></a></th>';
            echo '</tr>';
            echo '</tfoot>';
            echo '<tbody id="the-list">';
            $rowCount = 0;
            foreach ($activitiesInProductList as $activity) {
                $rowCount++;

                echo '<tr>';
                echo '<td class="bokun_field_value"><input class="bokun_post_cb" type="checkbox" name="bokun_post[]" value="'.$activity['id'].'"></td>';
                echo '<td><button class="button page-title-action import-activity import-activity-' . esc_attr($activity['id']) . ' " data-activity-title="' . esc_attr($activity['title']) . '" data-activity-id="' . esc_attr($activity['id']) . '">'.esc_html( 'Import', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</button></td>';
                echo '<td>' . esc_html($activity['id']) . '</td>';
                echo '<td class="bokun_field_value">' . esc_html($activity['title']) . '</td>';
                // Display values for all fields
                $view_href = 'admin.php?page=bokun-item-details&productListId='.$productListId.'&id='.esc_attr($activity['id']);
                foreach ($activity as $field => $value) {
                    if(array_key_exists($field, $allowfilelds)) { 
                        
                        if ($field === 'photos' && is_array($value)) {
                            // If it's the 'photos' field and it's an array, extract and display URLs
                            echo '<td class="bokun_thum_container">';
                            foreach ($value as $photo) {
                                if (isset($photo['originalUrl'])) {
                                    //echo '<img src="' . esc_url($photo['originalUrl']) . '" alt="Photo" class="bokul_image">';
                                }
                            }
                            echo '</td>';
                        } else {
                            if(!is_array($value)) {}
                            echo '<td class="bokun_field_value">' . esc_html($value) . '</td>';                    
                            // Display other fields as usual
                            // echo '<td class="bokun_field_value">' . esc_html($value) . '</td>';
                        }
                    }
                }
                echo '<td><a href="'.$view_href.'" class="button page-title-action" >'.esc_html( 'View', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</a></td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';

            // Import All button and Drive sync action
            echo '<div class="bkncpt-bulk-actions">';
            echo '<button class="button import-all-activities" data-only="0" >'.esc_html( 'Import All', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ).'</button>';
            echo ' <button class="button bkncpt-sync-drive" data-only="0">' . esc_html__( 'Sync Images to Google Drive', 'import-bokun-to-wp-ecommerce-and-custom-fileds' ) . '</button>';
            echo '</div>';
            echo '<div class="progress-wrapper">';
            echo '            <progress class="bkncpt-progress-bar bkncpt-hide" max="100" value="0"></progress>';
            echo '            <span class="progress-text bkncpt-hide">0%</span>';
            echo '          </div>';
            
        }

        echo '</div>';
    }
}
