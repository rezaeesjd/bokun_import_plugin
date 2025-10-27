<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// ajax-functions.php

// Include necessary files
if( file_exists( BKNCPT_PLUGIN_DIR . "config.php" ) ) {
    include_once( BKNCPT_PLUGIN_DIR . "config.php" );
}

function bkncpt_google_credentials_ready() {
    return BOKUN_GOOGLE_SERVICE_ACCOUNT_EMAIL && BOKUN_GOOGLE_PRIVATE_KEY && BOKUN_GOOGLE_DRIVE_PARENT_FOLDER_ID;
}

function bkncpt_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function bkncpt_get_google_access_token() {
    if (!function_exists('openssl_sign')) {
        return new WP_Error('missing_openssl', __('OpenSSL extension is required to sign Google credentials.', 'import-bokun-to-wp-ecommerce-and-custom-fileds'));
    }

    if (!bkncpt_google_credentials_ready()) {
        return new WP_Error('missing_credentials', __('Google Drive credentials are not configured.', 'import-bokun-to-wp-ecommerce-and-custom-fileds'));
    }

    $private_key = BOKUN_GOOGLE_PRIVATE_KEY;
    $pkey_resource = openssl_pkey_get_private($private_key);

    if (!$pkey_resource) {
        return new WP_Error('invalid_private_key', __('Unable to parse Google private key.', 'import-bokun-to-wp-ecommerce-and-custom-fileds'));
    }

    $now = time();
    $header = array('alg' => 'RS256', 'typ' => 'JWT');
    $claims = array(
        'iss'   => BOKUN_GOOGLE_SERVICE_ACCOUNT_EMAIL,
        'scope' => 'https://www.googleapis.com/auth/drive',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    );

    $header_encoded = bkncpt_base64url_encode(wp_json_encode($header));
    $claims_encoded = bkncpt_base64url_encode(wp_json_encode($claims));
    $signature_input = $header_encoded . '.' . $claims_encoded;

    $signature = '';
    $signed = openssl_sign($signature_input, $signature, $pkey_resource, 'sha256');
    openssl_free_key($pkey_resource);

    if (!$signed) {
        return new WP_Error('signing_failed', __('Failed to sign Google authentication request.', 'import-bokun-to-wp-ecommerce-and-custom-fileds'));
    }

    $jwt = $signature_input . '.' . bkncpt_base64url_encode($signature);

    $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
        'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
        'body'    => array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ),
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['access_token'])) {
        $message = isset($body['error_description']) ? $body['error_description'] : __('Unable to retrieve Google access token.', 'import-bokun-to-wp-ecommerce-and-custom-fileds');
        return new WP_Error('token_error', $message);
    }

    return $body['access_token'];
}

function bkncpt_drive_find_folder($token, $parent_id, $name) {
    $query = sprintf("name='%s' and mimeType='application/vnd.google-apps.folder' and '%s' in parents and trashed=false", str_replace("'", "\\'", $name), $parent_id);
    $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name)&pageSize=1';
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($body['files'][0]['id'])) {
        return $body['files'][0];
    }

    return false;
}

function bkncpt_drive_create_folder($token, $parent_id, $name) {
    $payload = array(
        'name'     => $name,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents'  => array($parent_id),
    );

    $response = wp_remote_post('https://www.googleapis.com/drive/v3/files', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ),
        'body'    => wp_json_encode($payload),
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['id'])) {
        return new WP_Error('folder_creation_failed', __('Unable to create Google Drive folder.', 'import-bokun-to-wp-ecommerce-and-custom-fileds'));
    }

    return $body;
}

function bkncpt_drive_ensure_folder($token, $parent_id, $name) {
    $existing = bkncpt_drive_find_folder($token, $parent_id, $name);
    if (is_wp_error($existing)) {
        return $existing;
    }
    if ($existing) {
        return array('id' => $existing['id'], 'created' => false);
    }

    $created = bkncpt_drive_create_folder($token, $parent_id, $name);
    if (is_wp_error($created)) {
        return $created;
    }

    return array('id' => $created['id'], 'created' => true);
}

function bkncpt_drive_find_file($token, $folder_id, $file_name) {
    $query = sprintf("name='%s' and '%s' in parents and trashed=false", str_replace("'", "\\'", $file_name), $folder_id);
    $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name,md5Checksum)&pageSize=1';
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($body['files'][0])) {
        return $body['files'][0];
    }

    return false;
}

function bkncpt_drive_upload_file($token, $folder_id, $file_name, $file_contents) {
    $file_type = wp_check_filetype($file_name);
    $mime = !empty($file_type['type']) ? $file_type['type'] : 'application/octet-stream';
    $boundary = 'bkncpt' . wp_generate_uuid4();
    $delimiter = '------' . $boundary;

    $metadata = array(
        'name'    => $file_name,
        'parents' => array($folder_id),
    );

    $body  = "--{$delimiter}\r\n";
    $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
    $body .= wp_json_encode($metadata) . "\r\n";
    $body .= "--{$delimiter}\r\n";
    $body .= "Content-Type: {$mime}\r\n\r\n";
    $body .= $file_contents . "\r\n";
    $body .= "--{$delimiter}--";

    $response = wp_remote_post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'multipart/related; boundary=' . $delimiter,
        ),
        'body'    => $body,
        'timeout' => 60,
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code < 200 || $response_code >= 300) {
        return new WP_Error('upload_failed', __('Unable to upload file to Google Drive.', 'import-bokun-to-wp-ecommerce-and-custom-fileds'));
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

function bkncpt_drive_prepare_filename($url, $activity, $index) {
    $path = parse_url($url, PHP_URL_PATH);
    $basename = $path ? wp_basename($path) : '';
    if (!$basename || strpos($basename, '.') === false) {
        $title = isset($activity['title']) ? $activity['title'] : 'activity';
        $basename = sanitize_file_name($title . '-' . $activity['id'] . '-' . ($index + 1) . '.jpg');
    } else {
        $basename = sanitize_file_name($basename);
    }

    if ($basename === '') {
        $basename = 'activity-' . $activity['id'] . '-' . ($index + 1) . '.jpg';
    }

    return $basename;
}

function bkncpt_sync_activity_images_to_drive($activity, $token, $parent_id) {
    $summary = array(
        'folders_created'  => 0,
        'folders_existing' => 0,
        'files_uploaded'   => 0,
        'files_skipped'    => 0,
        'errors'           => array(),
    );

    $activity_id = isset($activity['id']) ? $activity['id'] : '';
    $title = isset($activity['title']) ? $activity['title'] : __('Activity', 'import-bokun-to-wp-ecommerce-and-custom-fileds');
    $folder_name = sanitize_text_field($title) . ' - ' . $activity_id;

    $folder = bkncpt_drive_ensure_folder($token, $parent_id, $folder_name);
    if (is_wp_error($folder)) {
        $summary['errors'][] = sprintf(__('Activity %s: %s', 'import-bokun-to-wp-ecommerce-and-custom-fileds'), $activity_id, $folder->get_error_message());
        return $summary;
    }

    if (!empty($folder['created'])) {
        $summary['folders_created']++;
    } else {
        $summary['folders_existing']++;
    }

    if (empty($activity['photos']) || !is_array($activity['photos'])) {
        return $summary;
    }

    foreach ($activity['photos'] as $index => $photo) {
        if (empty($photo['originalUrl'])) {
            continue;
        }

        $image_url = esc_url_raw($photo['originalUrl']);
        $response = wp_remote_get($image_url, array('timeout' => 60));
        if (is_wp_error($response)) {
            $summary['errors'][] = sprintf(__('Failed to download image for activity %1$s: %2$s', 'import-bokun-to-wp-ecommerce-and-custom-fileds'), $activity_id, $response->get_error_message());
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        if ($body === '') {
            $summary['errors'][] = sprintf(__('Image response was empty for activity %s.', 'import-bokun-to-wp-ecommerce-and-custom-fileds'), $activity_id);
            continue;
        }

        $file_name = bkncpt_drive_prepare_filename($image_url, $activity, $index);
        $existing = bkncpt_drive_find_file($token, $folder['id'], $file_name);
        if (is_wp_error($existing)) {
            $summary['errors'][] = sprintf(__('Unable to check existing files for activity %1$s: %2$s', 'import-bokun-to-wp-ecommerce-and-custom-fileds'), $activity_id, $existing->get_error_message());
            continue;
        }

        if ($existing) {
            $summary['files_skipped']++;
            continue;
        }

        $upload = bkncpt_drive_upload_file($token, $folder['id'], $file_name, $body);
        if (is_wp_error($upload)) {
            $summary['errors'][] = sprintf(__('Upload failed for activity %1$s image %2$s: %3$s', 'import-bokun-to-wp-ecommerce-and-custom-fileds'), $activity_id, $file_name, $upload->get_error_message());
            continue;
        }

        $summary['files_uploaded']++;
    }

    return $summary;
}

// Function to extract and save Google Place information
function bkncpt_extract_and_save_google_place($data, $product_id) {
    if (is_array($data)) {
        foreach ($data as $field => $value) {
            // Check if the field is an array (nested fields)
            if (is_array($value)) {
                // Iterate through nested fields
                foreach ($value as $nestedField => $nestedValue) {
                    // Check if the nested field is an array (sub-nested fields)
                    if (is_array($nestedValue)) {
                        // Iterate through sub-nested fields
                        foreach ($nestedValue as $subNestedField => $subNestedValue) {
                            if (is_array($subNestedValue)) {
                                // Iterate through extra-nested fields
                                foreach ($subNestedValue as $extraNestedField => $extraNestedValue) {
                                    $meta_key = 'bk_googlePlace_' . $field . '_' . $nestedField . '_' . $subNestedField . '_' . $extraNestedField;
                                    update_post_meta($product_id, $meta_key, sanitize_text_field($extraNestedValue));
                                }
                            } else {
                                // Save sub-nested field
                                $meta_key = 'bk_googlePlace_' . $field . '_' . $nestedField . '_' . $subNestedField;
                                update_post_meta($product_id, $meta_key, sanitize_text_field($subNestedValue));
                            }
                        }
                    } else {
                        // Save non-sub-nested field
                        $meta_key = 'bk_googlePlace_' . $field . '_' . $nestedField;
                        update_post_meta($product_id, $meta_key, sanitize_text_field($nestedValue));
                    }
                }
            } else {
                // Save non-nested field
                $meta_key = 'bk_googlePlace_' . $field;
                update_post_meta($product_id, $meta_key, sanitize_text_field($value));
            }
        }
    }
}

function bkncpt_add_custom_box_details() {
    if( BOKUN_POST_TYPE == '' ) { return false; }
    $screens = [ BOKUN_POST_TYPE ];
    foreach ( $screens as $screen ) {
        add_meta_box(
            'wporg_box_id_details',                 // Unique ID
            'BOKUN Details',      // Box title
            'bkncpt_custom_box_details',  // Content callback, must be of type callable
            $screen                            // Post type
        );
    }
}
add_action( 'add_meta_boxes', 'bkncpt_add_custom_box_details' );
function bkncpt_custom_box_details( $post ) {
    $bkncpt_key_list = get_post_meta( $post->ID );
    $bkncpt_key_list_array = array();
    foreach ( $bkncpt_key_list as $key => $values ) {
        $bkncpt_key_list_array[] = $key;
    }
    $meta_list_with_key = array();
    foreach ( $bkncpt_key_list_array as $array_key => $field_name ) {
        $field_details = get_post_meta( $post->ID, $field_name, true);
        $bkncpt_bk_values = maybe_unserialize( $field_details );
        $meta_list_with_key[$field_name] = $bkncpt_bk_values;
    }

    $activity = $meta_list_with_key;    
    ?>
    <table>
    <?php
    wp_nonce_field('bkncpt-save-metabox-nonce', 'bkncpt-save-metabox-nonce');
    ?>
    <table class="wp-list-table widefat fixed striped table-view-list bkncpt bkncpt_view_table">
        <?php
        foreach ($activity as $field_key => $value) {
            $field = str_replace('bk_','',$field_key);

            if(!is_array($value)) {

                echo '<tr>';
                echo '<td class="bokun_field_value">' . esc_html($field) . '</td>';                    
                echo '<td class="bokun_field_value"><span class="bokin-content">' . esc_html(strip_tags($value)) . '</span></td>';                    
                echo '</tr>';
            } else {

                echo '<tr>';
                echo '<td class="bokun_field_value">' . esc_html($field) . '</td>';                    
                echo '<td class="bokun_field_value">';
                
                if( esc_html($field) == 'cancellationPolicy' ):
                    echo '<table>';
                    echo '<tr>';
                        echo '<th><strong>ID</strong></th>';
                        echo '<td>'.$value["id"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>TITLE</strong></th>';
                        echo '<td>'.$value["title"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>TAX</strong></th>';
                        echo '<td>'.$value["tax"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>Default Policy</strong></th>';
                        echo '<td>'.$value["defaultPolicy"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>Policy Type</strong></th>';
                        echo '<td>'.$value["policyType"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>Simple Cut off Hours</strong></th>';
                        echo '<td>'.$value["simpleCutoffHours"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>policy Type Enum</strong></th>';
                        echo '<td>'.$value["policyTypeEnum"].'</td>';
                    echo '</tr>';
                    
                    echo '<tr>';
                        echo '<th><strong>Penalty Rules</strong></th>';
                        echo '<td><table>';
                        if(isset($value["penaltyRules"]) && !empty($value["penaltyRules"])) {
                            foreach ($value["penaltyRules"] as $p_key => $p_value) {
                                echo '<tr>';
                                echo '<td>ID = ' .$p_value['id'].'</td>';
                                echo '</tr>';
                                echo '<tr>';
                                echo '<td>Cut Off Hours = ' .$p_value['cutoffHours'].'</td>';
                                echo '</tr>';
                                echo '<tr>';
                                echo '<td>Charge = ' .$p_value['charge'].'</td>';
                                echo '</tr>';
                                echo '<tr>';
                                echo '<td>Charge Type = ' .$p_value['chargeType'].'</td>';
                                echo '</tr>';
                                echo '<tr>';
                                echo '<td>Percentage = ' .$p_value['percentage'].'</td>';
                                echo '</tr>';
                                echo '<tr><td><br/><td></tr>';
                            }
                        }
                        echo '</table></td>';
                    echo '</tr>';
                    echo '</table>';                                                                   
                endif;
                
                if( esc_html($field) == 'mainContactFields' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'requiredCustomerFields' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'keywords' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $v_key => $v_value) {
                            echo '<span class="badge badge-primary">'.$v_value.'</span>';
                        }
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'flags' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'languages' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $v_key => $v_value) {
                            echo '<span class="badge badge-primary">'.$v_value.'</span>';
                        }
                        
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'paymentCurrencies' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'customFields' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'tagGroups' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'categories' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'videos' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;
                
                
                if( esc_html($field) == 'vendor' ):
                    echo '<table>';
                    echo '<tr>';
                        echo '<th><strong>ID</strong></th>';
                        echo '<td>'.$value["id"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>TITLE</strong></th>';
                        echo '<td>'.$value["title"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>Currency Code</strong></th>';
                        echo '<td>'.$value["currencyCode"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>Show Invoice Id On Ticket</strong></th>';
                        echo '<td>'.$value["showInvoiceIdOnTicket"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>Show Agent Details On Ticket</strong></th>';
                        echo '<td>'.$value["showAgentDetailsOnTicket"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>Show Payments On Invoice</strong></th>';
                        echo '<td>'.$value["showPaymentsOnInvoice"].'</td>';
                    echo '</tr>';
                    echo '<tr>';
                        echo '<th><strong>Company Email Is Default</strong></th>';
                        echo '<td>'.$value["companyEmailIsDefault"].'</td>';
                    echo '</tr>';
                    
                    echo '</table>';                                                                   
                endif;
                
                if( esc_html($field) == 'supportedAccessibilityTypes' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'startPoints' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $a_key => $a_value) {
                            echo '<table>';
                            echo '<tr>';
                                echo '<th><strong>ID</strong></th>';
                                echo '<td>'.$a_value["id"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Type</strong></th>';
                                echo '<td>'.$a_value["type"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Title</strong></th>';
                                echo '<td>'.$a_value["title"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Code</strong></th>';
                                echo '<td>'.$a_value["code"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Pickup Ticket Description</strong></th>';
                                echo '<td>'.$a_value["pickupTicketDescription"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Dropoff Ticket Description</strong></th>';
                                echo '<td>'.$a_value["dropoffTicketDescription"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Address</strong></th>';
                                echo '<td>';
                                echo $a_value["address"]['addressLine1'].'<br/>';
                                echo $a_value["address"]['addressLine2'];
                                echo $a_value["address"]['addressLine3'];
                                echo $a_value["address"]['city'].'<br/>';
                                echo $a_value["address"]['state'].'<br/>';
                                echo $a_value["address"]['postalCode'].'<br/>';
                                echo $a_value["address"]['countryCode'].'<br/>';
                                echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</table>';                                                                   
                    } else {
                        echo 'No Information Available';
                    } 
                endif;

                if( esc_html($field) == 'bookingQuestions' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'passengerFields' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'inclusions' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $v_key => $v_value) {
                            echo '<span class="badge badge-primary">'.$v_value.'</span>';
                        }
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'exclusions' ):
                    if(isset($value) && !empty($value)) {
                        
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'knowBeforeYouGoItems' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $v_key => $v_value) {
                            echo '<span class="badge badge-primary">'.$v_value.'</span>';
                        }
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'locationCode' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $v_key => $v_value) {
                            echo '<span class="badge badge-primary">'.$v_value.'</span>';
                        }
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'googlePlace' ):
                    if(isset($value) && !empty($value)) {
                    
                        echo '<table>';
                        echo '<tr>';
                            echo '<th><strong>Country</strong></th>';
                            echo '<td>'.$value["country"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>Country Code</strong></th>';
                            echo '<td>'.$value["countryCode"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>City</strong></th>';
                            echo '<td>'.$value["city"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>City Code</strong></th>';
                            echo '<td>'.$value["cityCode"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>Latitude</strong></th>';
                            echo '<td>'.$value["geoLocationCenter"]['lat'].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>Longitude</strong></th>';
                            echo '<td>'.$value["geoLocationCenter"]['lng'].'</td>';
                        echo '</tr>';
                        echo '</table>';                                                                   
                    } else {
                        echo 'No Information Available';
                    } 
                endif;
                
                if( esc_html($field) == 'tripadvisorReview' ):
                    if(isset($value) && !empty($value)) {
                    
                        echo '<table>';
                        echo '<tr>';
                            echo '<th><strong>Url</strong></th>';
                            echo '<td><a href="'.$value["url"].'">'.$value["url"].'</a></td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>Name</strong></th>';
                            echo '<td>'.$value["name"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>Rating</strong></th>';
                            echo '<td>'.$value["rating"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>Ranking</strong></th>';
                            echo '<td>'.$value["ranking"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>Number of Reviews</strong></th>';
                            echo '<td>'.$value["numReviews"].'</td>';
                        echo '</tr>';
                        
                        echo '</table>';                                                                   
                    } else {
                        echo 'No Information Available';
                    } 
                endif;

                if( esc_html($field) == 'dayOptions' ):
                    if(isset($value) && !empty($value)) {
                        
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;
                
                if( esc_html($field) == 'activityCategories' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $v_key => $v_value) {
                            echo '<span class="badge badge-primary">'.$v_value.'</span>';
                        }
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;
                
                if( esc_html($field) == 'activityAttributes' ):
                    if(isset($value) && !empty($value)) {
                        
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'guidanceTypes' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $a_key => $a_value) {
                            echo '<table>';
                            echo '<tr>';
                                echo '<th><strong>ID</strong></th>';
                                echo '<td>'.$a_value["id"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Guidance Type</strong></th>';
                                echo '<td>'.$a_value["guidanceType"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Languages</strong></th>';
                                echo '<td>';
                                foreach ($a_value["languages"] as $v_key => $v_value) {
                                    echo '<span class="badge badge-primary">'.$v_value.'</span>';
                                }
                                echo '</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Display Languages</strong></th>';
                                echo '<td>';
                                foreach ($a_value["displayLanguages"] as $v_key => $v_value) {
                                    echo '<span class="badge badge-primary">'.$v_value.'</span>';
                                }
                                echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</table>';                                                                   
                    } else {
                        echo 'No Information Available';
                    } 
                endif;

                if( esc_html($field) == 'rates' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $a_key => $a_value) {
                            echo '<table>';
                            echo '<tr>';
                                echo '<th><strong>ID</strong></th>';
                                echo '<td>'.$a_value["id"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Title</strong></th>';
                                echo '<td>'.$a_value["title"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Description</strong></th>';
                                echo '<td>'.$a_value["description"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Priced Per Person</strong></th>';
                                echo '<td>'.$a_value["pricedPerPerson"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Minimum Person Booking</strong></th>';
                                echo '<td>'.$a_value["minPerBooking"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Maximum Person Booking</strong></th>';
                                echo '<td>'.$a_value["maxPerBooking"].'</td>';
                            echo '</tr>';                                                            
                        }                                                       
                        echo '</table>';
                        echo '<br/>';
                    } else {
                        echo 'No Information Available';
                    } 
                endif;

                if( esc_html($field) == 'nextDefaultPriceMoney' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $v_key => $v_value) {
                            echo '<span class="badge badge-primary">'.$v_key." = ".$v_value.'</span>';
                        }
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'pickupFlags' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;
                
                if( esc_html($field) == 'pickupTimeByLocations' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;
                
                if( esc_html($field) == 'pickupPlaceGroups' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;
                
                if( esc_html($field) == 'dropoffFlags' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;
                
                if( esc_html($field) == 'dropoffPlaceGroups' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'pricingCategories' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $a_key => $a_value) {
                            echo '<table>';
                            echo '<tr>';
                                echo '<th><strong>ID</strong></th>';
                                echo '<td>'.$a_value["id"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Title</strong></th>';
                                echo '<td>'.$a_value["title"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Category</strong></th>';
                                echo '<td>'.$a_value["ticketCategory"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Occupancy</strong></th>';
                                echo '<td>'.$a_value["occupancy"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Group Size</strong></th>';
                                echo '<td>'.$a_value["groupSize"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Age Qualified</strong></th>';
                                echo '<td>'.$a_value["ageQualified"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Minimum Age</strong></th>';
                                echo '<td>'.$a_value["minAge"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Maximum Age</strong></th>';
                                echo '<td>'.$a_value["maxAge"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Dependent</strong></th>';
                                echo '<td>'.$a_value["dependent"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Master Category Id</strong></th>';
                                echo '<td>'.$a_value["masterCategoryId"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Max Person Master</strong></th>';
                                echo '<td>'.$a_value["maxPerMaster"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Sum Dependent Categories</strong></th>';
                                echo '<td>'.$a_value["sumDependentCategories"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Max Dependent Sum</strong></th>';
                                echo '<td>'.$a_value["maxDependentSum"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Internal Use Only</strong></th>';
                                echo '<td>'.$a_value["internalUseOnly"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Default Category</strong></th>';
                                echo '<td>'.$a_value["defaultCategory"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Full Title</strong></th>';
                                echo '<td><span class="badge badge-primary">'.$a_value["fullTitle"].'</span></td>';
                            echo '</tr>';
                                                                                        
                        }                                                       
                        echo '</table>';
                        echo '<br/>';
                    } else {
                        echo 'No Information Available';
                    } 
                endif;

                if( esc_html($field) == 'activityPriceCatalogs' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'agendaItems' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $a_key => $a_value) {
                            echo '<table>';
                            echo '<tr>';
                                echo '<th><strong>ID</strong></th>';
                                echo '<td>'.$a_value["id"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Title</strong></th>';
                                echo '<td>'.$a_value["title"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Excerpt</strong></th>';
                                echo '<td>'.$a_value["excerpt"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Body</strong></th>';
                                echo '<td>'.nl2br($a_value["body"]).'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Day</strong></th>';
                                echo '<td>'.$a_value["day"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Location</strong></th>';
                                echo '<td>';
                                echo $a_value["location"]['address'];
                                echo $a_value["location"]['city'];
                                echo $a_value["location"]['countryCode'];
                                echo $a_value["location"]['postCode'];
                                echo $a_value["location"]['latitude'];
                                echo $a_value["location"]['longitude'];
                                echo $a_value["location"]['wholeAddress'];
                                echo '</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Address</strong></th>';
                                echo '<td>'.$a_value["address"].'</td>';
                            echo '</tr>';
                                                                                        
                        }                                                       
                        echo '</table>';
                        echo '<br/>';
                    } else {
                        echo 'No Information Available';
                    } 
                endif;

                if( esc_html($field) == 'startTimes' ):
                    if(isset($value) && !empty($value)) {
                        foreach ($value as $a_key => $a_value) {
                            echo '<table>';
                            echo '<tr>';
                                echo '<th><strong>ID</strong></th>';
                                echo '<td>'.$a_value["id"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Label</strong></th>';
                                echo '<td>'.$a_value["label"].'</td>';
                            echo '</tr>';
                            echo '<tr>';
                                echo '<th><strong>Hour</strong></th>';
                                echo '<td>'.$a_value["hour"].":".$a_value["minute"].'</td>';
                            echo '</tr>';
                                                                                        
                        }                                                       
                        echo '</table>';
                        echo '<br/>';
                    } else {
                        echo 'No Information Available';
                    } 
                endif;

                if( esc_html($field) == 'bookableExtras' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'seasonalOpeningHours' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'displaySettings' ):
                    if(isset($value) && !empty($value)) {
                        
                        echo '<table>';
                        echo '<tr>';
                            echo '<th><strong>Show Pickup Places</strong></th>';
                            echo '<td>'.$value["showPickupPlaces"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>Show Route Map</strong></th>';
                            echo '<td>'.$value["showRouteMap"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>Select Rate Based On Start Time</strong></th>';
                            echo '<td>'.$value["selectRateBasedOnStartTime"].'</td>';
                        echo '</tr>';                                                                                                                    
                                                                        
                        echo '</table>';
                        echo '<br/>';
                    } else {
                        echo 'No Information Available';
                    } 
                endif;

                if( esc_html($field) == 'bookingLabels' ):
                    if(isset($value) && !empty($value)) {
                    } else {
                        echo 'No Information Available';
                    }                                                                
                endif;

                if( esc_html($field) == 'actualVendor' ):
                    if(isset($value) && !empty($value)) {
                        
                        echo '<table>';
                        echo '<tr>';
                            echo '<th><strong>ID</strong></th>';
                            echo '<td>'.$value["id"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>Title</strong></th>';
                            echo '<td>'.$value["title"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>CurrencyCode</strong></th>';
                            echo '<td>'.$value["currencyCode"].'</td>';
                        echo '</tr>';                                                                                                                    
                        echo '<tr>';
                            echo '<th><strong>Show Invoice Id On Ticket</strong></th>';
                            echo '<td>'.$value["showInvoiceIdOnTicket"].'</td>';
                        echo '</tr>';                                                                                                                    
                        echo '<tr>';
                            echo '<th><strong>Show Agent Details On Ticket</strong></th>';
                            echo '<td>'.$value["showAgentDetailsOnTicket"].'</td>';
                        echo '</tr>';                                                                                                                    
                        echo '<tr>';
                            echo '<th><strong>Show Payments On Invoice</strong></th>';
                            echo '<td>'.$value["showPaymentsOnInvoice"].'</td>';
                        echo '</tr>';
                        echo '<tr>';
                            echo '<th><strong>Company Email Is Default</strong></th>';
                            echo '<td>'.$value["companyEmailIsDefault"].'</td>';
                        echo '</tr>';
                                                                        
                        echo '</table>';
                        echo '<br/>';
                    } else {
                        echo 'No Information Available';
                    } 
                endif;
                

                echo '</td>';
                echo '</tr>';
            }
        }
        ?>
    </table>
    <?php
}
function bkncpt_add_custom_box() {
    if( BOKUN_POST_TYPE == '' ) { return false; }
    $screens = [ BOKUN_POST_TYPE ];
    foreach ( $screens as $screen ) {
        add_meta_box(
            'wporg_box_id',                 // Unique ID
            'BOKUN Fields',      // Box title
            'bkncpt_custom_box_html',  // Content callback, must be of type callable
            $screen                            // Post type
        );
    }
}
add_action( 'add_meta_boxes', 'bkncpt_add_custom_box' );

function bkncpt_custom_box_html( $post ) {
    $bkncpt_bk_values = get_post_meta( $post->ID);
    $bkncpty_bk_keys = array();
    ?>
    <table>
    <?php
    wp_nonce_field('bkncpt-save-metabox-nonce', 'bkncpt-save-metabox-nonce');
    foreach ($bkncpt_bk_values as $bk_post_key => $bk_post_value) {
        $bkncpty_bk_keys[] = $bk_post_key;
    }
    $sr = 1;
    if(isset($bkncpty_bk_keys) && !empty($bkncpty_bk_keys)) {
        foreach ($bkncpty_bk_keys as $bk_post_key => $bk_post_key_name) {
            $bkncpt_data = get_post_meta( $post->ID, $bk_post_key_name, true);
            $bk_data_final = maybe_unserialize( $bkncpt_data );
            if (is_array($bk_data_final) || is_object($bk_data_final)) {
                $output_value = 'Array';
            } else {
                $output_value = $bk_data_final;
            }            
            ?>
            <tr>
                <td><label for="wporg_field"><?php echo esc_attr($sr++) ." . ".esc_attr($bk_post_key_name) ?>: </label></td>
                <td><input type="text" name="<?php echo esc_attr($bk_post_key_name) ?>" class="form-required" value="<?php echo esc_attr($output_value) ?>" aria-required="true"></td>
            </tr>        
            <?php
        }
    }
    ?>
    </table>
    <?php
    
}

function bkncpt_save_custom_metabox(){

    global $post;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if (!isset($_POST['bkncpt-save-metabox-nonce'])) {
        return;
    }

    // Verify nonce
    if (!wp_verify_nonce($_POST['bkncpt-save-metabox-nonce'], 'bkncpt-save-metabox-nonce')) {
        return;
    }

    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    if(!isset($post) && empty($post)) {
        return;
    }
    $bkncpt_bk_values = get_post_meta( $post->ID );
    if(isset($bkncpt_bk_values) && !empty($bkncpt_bk_values)) {
        foreach ($bkncpt_bk_values as $bk_post_key => $bk_post_value) {
            if (is_array($bk_post_value[0]) || is_object($bk_post_value[0])) {
                $output_value = '11';
            } else {
                $output_value = $bk_post_value[0];
            }
            update_post_meta($post->ID, $bk_post_key, sanitize_text_field($_POST[$bk_post_key]));
        }
    }
    
}
 
add_action('save_post', 'bkncpt_save_custom_metabox');
// Function to process activity data and update post meta
function bkncpt_process_activity_data($activity, $product_id) {
    // Add all fields as custom fields with "bk_" prefix
    foreach ($activity as $field => $value) {
        // Use "bk_" prefix for custom fields
        $meta_key = 'bk_' . $field;
        if( $field == 'agendaItems' ) {
            bkncpt_addUpdate_agendaItems( $field , $value, $product_id);
        } else {
            // Check if the value is an array or an object (e.g., repeater field)
            if (is_array($value) || is_object($value)) {
                // Convert array or object to a serialized string and save it as a custom field
                update_post_meta($product_id, $meta_key, maybe_serialize($value));                
            } else {
                // Save other types of values directly as custom fields
                update_post_meta($product_id, $meta_key, $value);
            }
        }
    }
    // Debug log for activity data
    error_log("Activity Data: " . var_export($activity, true));
    // Add product images
    if (isset($activity['photos']) && is_array($activity['photos'])) {
        $gallery_images = array();

        foreach ($activity['photos'] as $photo) {
            if (isset($photo['originalUrl'])) {
                $photo['originalUrl'];
                $attachment_id = bkncpt_bokun_import_attach_image_from_url($photo['originalUrl'], $product_id);
                
                if ($attachment_id) {
                    $gallery_images[] = $attachment_id;
                }
            }
        }
        // echo '<pre>';
        // print_r($gallery_images);
        // echo '</pre>';
        
        // Set the first image as the featured image
        if (!empty($gallery_images)) {
            set_post_thumbnail($product_id, $gallery_images[0]);
        }

        // Add the gallery images to the product
        update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_images));
    }
        // Extract and save meeting point information
        if (isset($activity['startPoints'])) {
            $startPoints = maybe_unserialize($activity['startPoints']);

            if (!empty($startPoints) && is_array($startPoints)) {
                foreach ($startPoints as $index => $meetingPoint) {
                    // Assuming there is only one meeting point in the array

                    // Debug log for meeting point title
                    if (isset($meetingPoint['title'])) {
                        error_log("Meeting Point Title ($index): " . var_export($meetingPoint['title'], true));

                        // Save meeting point title as a separate custom field
                        $result = update_post_meta($product_id, 'bk_meetingpointtitle_' . $index, sanitize_text_field($meetingPoint['title']));
                        error_log("Update Result ($index) for Meeting Point Title: " . var_export($result, true));
                    } else {
                        error_log("Meeting Point Title ($index) is not set in the array.");
                    }

                    // Save other meeting point details as custom fields (adjust keys accordingly)
                    foreach ($meetingPoint as $field => $value) {
                        if ($field !== 'title') {
                            // Check if the field is an array (nested fields)
                            if (is_array($value)) {
                                // Iterate through nested fields
                                foreach ($value as $nestedField => $nestedValue) {
                                    // Check if the nested field is an array (sub-nested fields)
                                    if (is_array($nestedValue)) {
                                        // Iterate through sub-nested fields
                                        foreach ($nestedValue as $subNestedField => $subNestedValue) {
                                            $result = update_post_meta($product_id, 'bk_meetingpoint_' . $field . '_' . $nestedField . '_' . $subNestedField . '_' . $index, sanitize_text_field($subNestedValue));
                                            error_log("Update Result ($index) for $field - $nestedField - $subNestedField: " . var_export($result, true));
                                        }
                                    } else {
                                        // Save non-sub-nested field
                                        $result = update_post_meta($product_id, 'bk_meetingpoint_' . $field . '_' . $nestedField . '_' . $index, sanitize_text_field($nestedValue));
                                        error_log("Update Result ($index) for $field - $nestedField: " . var_export($result, true));
                                    }
                                }
                            } else {
                                // Save non-nested field
                                $result = update_post_meta($product_id, 'bk_meetingpoint_' . $field . '_' . $index, sanitize_text_field($value));
                                error_log("Update Result ($index) for $field: " . var_export($result, true));
                            }
                        }
                    }
                }
            } else {
                // Handle the case where $startPoints is not an array or empty
                error_log("Start Points is not an array or is empty.");
                update_post_meta($product_id, 'bk_meetingpointtitle', $activity['startPoints']);
            }
        }
         

// Assuming $activity is your array containing 'googlePlace'
if (isset($activity['googlePlace'])) {
    $googlePlace = $activity['googlePlace'];
    bkncpt_extract_and_save_google_place($googlePlace, $product_id);
}

    // Add additional fields to post content
    $content = '';

    // Include, exclude, requirements, and attention
    $content .= isset($activity['included']) ? '<strong>Included:</strong> ' . $activity['included'] . '<br>' : '';
    $content .= isset($activity['excluded']) ? '<strong>Excluded:</strong> ' . $activity['excluded'] . '<br>' : '';
    $content .= isset($activity['requirements']) ? '<strong>Requirements:</strong> ' . $activity['requirements'] . '<br>' : '';
    $content .= isset($activity['attention']) ? '<strong>Attention:</strong> ' . $activity['attention'] . '<br>' : '';

    // Append the content to the product post
    if (!empty($content)) {
        $content .= '<br><br>';
        $content .= get_post_field('post_content', $product_id); // Get existing content
        wp_update_post(array('ID' => $product_id, 'post_content' => $content)); // Update content
    }
}
// Function to import a single activity

function bkncpt_addUpdate_agendaItems( $field, $value, $product_id ) {
    if(isset($value) && !empty($value)) {
        foreach ($value as $agend_key => $agenda_value) {
            $product_id;
            $prefix_agendaItems = 'bk_agendaItems_';
            $postfix_key = '_'.$agend_key;
            // Save other types of values directly as custom fields
            update_post_meta($product_id, $prefix_agendaItems.'id'.$postfix_key, sanitize_text_field($agenda_value['id']));
            update_post_meta($product_id, $prefix_agendaItems.'index'.$postfix_key, sanitize_text_field($agenda_value['index']));
            update_post_meta($product_id, $prefix_agendaItems.'title'.$postfix_key, sanitize_text_field($agenda_value['title']));
            update_post_meta($product_id, $prefix_agendaItems.'excerpt'.$postfix_key, sanitize_text_field($agenda_value['excerpt']));
            update_post_meta($product_id, $prefix_agendaItems.'body'.$postfix_key, sanitize_text_field($agenda_value['body']));
            update_post_meta($product_id, $prefix_agendaItems.'day'.$postfix_key, sanitize_text_field($agenda_value['day']));
            update_post_meta($product_id, $prefix_agendaItems.'address'.$postfix_key, sanitize_text_field($agenda_value['address']));
            update_post_meta($product_id, $prefix_agendaItems.'keyPhoto'.$postfix_key, sanitize_text_field($agenda_value['keyPhoto']));
            update_post_meta($product_id, $prefix_agendaItems.'flags'.$postfix_key, maybe_unserialize($agenda_value['flags']));
            update_post_meta($product_id, $prefix_agendaItems.'location_address'.$postfix_key, sanitize_text_field($agenda_value['location']['address']));
            update_post_meta($product_id, $prefix_agendaItems.'location_city'.$postfix_key, sanitize_text_field($agenda_value['location']['city']));
            update_post_meta($product_id, $prefix_agendaItems.'location_countryCode'.$postfix_key, sanitize_text_field($agenda_value['location']['countryCode']));
            update_post_meta($product_id, $prefix_agendaItems.'location_postCode'.$postfix_key, sanitize_text_field($agenda_value['location']['postCode']));
            update_post_meta($product_id, $prefix_agendaItems.'location_latitude'.$postfix_key, sanitize_text_field($agenda_value['location']['latitude']));
            update_post_meta($product_id, $prefix_agendaItems.'location_longitude'.$postfix_key, sanitize_text_field($agenda_value['location']['longitude']));
            update_post_meta($product_id, $prefix_agendaItems.'location_zoomLevel'.$postfix_key, sanitize_text_field($agenda_value['location']['zoomLevel']));
            update_post_meta($product_id, $prefix_agendaItems.'location_origin'.$postfix_key, sanitize_text_field($agenda_value['location']['origin']));
            update_post_meta($product_id, $prefix_agendaItems.'location_originId'.$postfix_key, sanitize_text_field($agenda_value['location']['originId']));
            update_post_meta($product_id, $prefix_agendaItems.'location_wholeAddress'.$postfix_key, sanitize_text_field($agenda_value['location']['wholeAddress']));            
        }
    }
    
}

function bkncpt_import_activity($activity) {
    // Return response of Inserted product
    $bkncpt_response = array();
    $activity_title = sanitize_text_field($activity['title']);
    $next_default_price = isset($activity['nextDefaultPrice']) ? sanitize_text_field($activity['nextDefaultPrice']) : '';
    $post_type = (BOKUN_POST_TYPE) ? BOKUN_POST_TYPE : 'post';
    // Prepare post data for wp_insert_post
    $post_data = array(
        'post_title'   => $activity_title,
        'post_type'    => $post_type,
        'post_status'  => 'publish',
        'post_content' => isset($activity['description']) ? $activity['description'] : '',
        'post_excerpt' => isset($activity['excerpt']) ? $activity['excerpt'] : '',
    );

    // Add more fields based on the provided planning
    $meta_input = array(
        '_sku'                          => isset($activity['externalId']) ? sanitize_text_field($activity['externalId']) : '',
        '_inventoryLocal'               => isset($activity['inventoryLocal']) ? absint($activity['inventoryLocal']) : 0,
        '_inventorySupportsPricing'     => isset($activity['inventorySupportsPricing']) ? sanitize_text_field($activity['inventorySupportsPricing']) : '',
        '_inventorySupportsAvailability' => isset($activity['inventorySupportsAvailability']) ? sanitize_text_field($activity['inventorySupportsAvailability']) : '',
        '_regular_price'                => isset($next_default_price) ? $next_default_price : 0, // Set nextDefaultPrice as regular price
        // Add more fields as needed
    );

    $post_data['meta_input'] = $meta_input;

    // Insert the product post
    $product_id = wp_insert_post($post_data);

    if (is_wp_error($product_id)) {
        echo "Error importing activity: ". esc_attr($activity_title). esc_attr($product_id->get_error_message());
        wp_die();
    }
    // Process activity data
    bkncpt_process_activity_data($activity, $product_id);
    $bkncpt_response['product_id'] = $product_id;
    $bkncpt_response['product_title'] = $activity_title;
    return $bkncpt_response;
}

// Function to attach image from URL and return attachment ID
function bkncpt_bokun_import_attach_image_from_url($image_url, $parent_post_id) {
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $image_name = 'bkncpt_'.$parent_post_id.'_.png';
    $unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name );
    $filename = basename($unique_file_name);
    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }
  
    $a = file_put_contents($file, $image_data);
  
    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit',
    );

    $attach_id = wp_insert_attachment($attachment, $file, $parent_post_id);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return $attach_id;
}


// Function to import all activities through AJAX
function bkncpt_import_all_activities() {

    if ( ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
        return;
    }
    $response = array();
    // Get the selected product list ID from the AJAX request
    $productListId = isset($_POST['product_list_id']) ? sanitize_text_field($_POST['product_list_id']) : 66035;
    $import_type = isset($_POST['import_type']) ? sanitize_text_field($_POST['import_type']) : 'bulk';    
    $data_only = isset($_POST['data_only']) ? sanitize_text_field($_POST['data_only']) : 0;
    $selected_boheck_array = isset($_POST['selected_boheck']) ? (array) $_POST['selected_boheck'] : array();
    
    $activitiesInProductList = bkncpt_get_activities_in_product_list($productListId);
    // echo '<pre>';
    // print_r($activitiesInProductList);
    // echo '</pre>';
    // die;
    // Alert Response collection of Inserted Data
    $alert_response = array();
    
    // Perform the import action for all activities here
    // For example, loop through all activities and create products
    foreach ($activitiesInProductList as $activity) {
        // Import each activity and get the product ID
        // Get ID of data to check How many record in insert
        $activityIdFromBokun = sanitize_text_field($activity['id']);        
        if($import_type == 'single') {
            $activityId = sanitize_text_field($_POST['activityId']);
            if( $activityId === $activityIdFromBokun ) {
                $alert_response = bkncpt_import_activity($activity);
                $response['status'] = true;
                $response['msg'] = $alert_response['product_title']. ' Imported Successfully';
                break;
            }
        } else {
            if( $data_only ) {
                if( in_array( $activityIdFromBokun, $selected_boheck_array ) ) {
                    $alert_response[] = bkncpt_import_activity($activity);
                }
            } else {
                $alert_response[] = bkncpt_import_activity($activity);
            }
        }
        // Use $product_id for any additional processing if needed
    }


    // echo "Imported ".count($alert_response)." items succesfully.\n";
    // if(isset($alert_response) && !empty($alert_response)) {
    //     $alert_series = 1;
    //     foreach ($alert_response as $alert_key => $alert_value) {
    //         echo $alert_series.". ".$alert_value['product_title']."\n";
    //         $alert_series++;
    //     }
    // }
    // $response['status'] = true;
    // $response['msg'] = 'my msg';
    echo json_encode($response);
    wp_die();
}

function bkncpt_sync_drive_images() {

    if ( ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
        wp_send_json_error(array('message' => __('Invalid request.', 'import-bokun-to-wp-ecommerce-and-custom-fileds')));
    }

    if (!bkncpt_google_credentials_ready()) {
        wp_send_json_error(array('message' => __('Google Drive credentials are missing. Please configure them in Manage Keys.', 'import-bokun-to-wp-ecommerce-and-custom-fileds')));
    }

    $product_list_id = isset($_POST['product_list_id']) ? sanitize_text_field($_POST['product_list_id']) : 66035;
    $data_only = isset($_POST['data_only']) ? absint($_POST['data_only']) : 0;
    $selected_ids = isset($_POST['selected_boheck']) ? (array) $_POST['selected_boheck'] : array();
    $selected_ids = array_map('sanitize_text_field', $selected_ids);

    $activities = bkncpt_get_activities_in_product_list($product_list_id);
    if (isset($activities['error'])) {
        wp_send_json_error(array('message' => $activities['error']));
    }

    $token = bkncpt_get_google_access_token();
    if (is_wp_error($token)) {
        wp_send_json_error(array('message' => $token->get_error_message()));
    }

    $summary = array(
        'activities_processed' => 0,
        'folders_created'      => 0,
        'folders_existing'     => 0,
        'files_uploaded'       => 0,
        'files_skipped'        => 0,
        'errors'               => array(),
    );

    foreach ($activities as $activity) {
        $activity_id = isset($activity['id']) ? (string) $activity['id'] : '';

        if (!empty($selected_ids)) {
            if (!in_array($activity_id, $selected_ids, true)) {
                continue;
            }
        } elseif ($data_only) {
            // Nothing selected but data_only set means skip processing.
            continue;
        }

        $summary['activities_processed']++;

        $result = bkncpt_sync_activity_images_to_drive($activity, $token, BOKUN_GOOGLE_DRIVE_PARENT_FOLDER_ID);
        $summary['folders_created'] += $result['folders_created'];
        $summary['folders_existing'] += $result['folders_existing'];
        $summary['files_uploaded'] += $result['files_uploaded'];
        $summary['files_skipped'] += $result['files_skipped'];
        if (!empty($result['errors'])) {
            $summary['errors'] = array_merge($summary['errors'], $result['errors']);
        }
    }

    if ($summary['activities_processed'] === 0) {
        $summary['message'] = __('No activities matched the current selection.', 'import-bokun-to-wp-ecommerce-and-custom-fileds');
    } else {
        $summary['message'] = __('Image sync completed.', 'import-bokun-to-wp-ecommerce-and-custom-fileds');
        if (!empty($summary['errors'])) {
            $summary['message'] .= ' ' . __('Some items could not be processed. Check logs for details.', 'import-bokun-to-wp-ecommerce-and-custom-fileds');
        }
    }

    wp_send_json_success($summary);
}

function in_progress_bar() {
    add_action('wp_footer', 'my_custom_footer_action');

}

function my_custom_footer_action() {
    // Your footer content or script here
    echo '<!-- My custom footer content -->';
    ?>
    <script>
        // JavaScript code here
        console.log('This message is logged from the footer script');
    </script>
    <?php
}
// Hook for handling AJAX requests


// Hook for handling AJAX requests
#add_action('wp_ajax_import_single_activity', 'import_single_activity');
add_action('wp_ajax_bkncpt_import_single_activity', 'bkncpt_import_all_activities');
add_action('wp_ajax_bkncpt_import_all_activities', 'bkncpt_import_all_activities');
add_action('wp_ajax_bkncpt_sync_drive_images', 'bkncpt_sync_drive_images');

	
add_action( 'init', 'bkncpt_custom_post_custom_article' );
// The custom function to register a custom article post type
function bkncpt_custom_post_custom_article() {
    // Set the labels. This variable is used in the $args array
    $labels = array(
        'name'               => __( 'Custom Tour' ),
        'singular_name'      => __( 'Custom Tour' ),
        'add_new'            => __( 'Add New Custom Tour' ),
        'add_new_item'       => __( 'Add New Custom Tour' ),
        'edit_item'          => __( 'Edit Custom Tour' ),
        'new_item'           => __( 'New Custom Tour' ),
        'all_items'          => __( 'All Custom Tour' ),
        'view_item'          => __( 'View Custom Tour' ),
        'search_items'       => __( 'Search Custom Tour' ),
        'featured_image'     => 'Poster',
        'set_featured_image' => 'Add Tour'
    );
// The arguments for our post type, to be entered as parameter 2 of register_post_type()
    $args = array(
        'labels'            => $labels,
        'description'       => 'Holds our custom article post specific data',
        'public'            => true,
        'menu_position'     => 5,
        'supports'          => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
        'has_archive'       => true,
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => true,
        'query_var'         => true,
    );
    // Call the actual WordPress function
    // Parameter 1 is a name for the post type
    // Parameter 2 is the $args array
    register_post_type('tour', $args);
}