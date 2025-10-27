<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add button to check authentication
add_action('admin_menu', 'bkncpt_add_auth_check_button');
function bkncpt_add_auth_check_button() {
    add_menu_page(
        'Import',
        'Bokun Auth Check',
        'manage_options',
        'bokun-auth-check',
        'bkncpt_bokun_auth_check_page'
    );
    
    add_submenu_page(
        'bokun-auth-check',
        '',
        'Import',
        'manage_options',
        'bokun-auth-check'
    );
    add_submenu_page(
        'bokun-auth-check',
        'Booking',
        'Booking',
        'manage_options',
        'bokun-booking-list',
        'bkncpt_bokun_booking_list'
    );
    add_submenu_page(
        'bokun-auth-check',
        'Import',
        'Manage Keys',
        'manage_options',
        'bokun-manage-keys',
        'bkncpt_manage_keys_page'
    );
    // View Details Page
    add_submenu_page(
        '',
        'View Item',
        '',
        'manage_options',
        'bokun-item-details',
        'view_bkncpt_data_details'
    );
    
}
// Step 5: Retrieve ID in View Page
function view_bkncpt_data_details() {

    if (isset($_GET['id'])) {
        $activityId = $_GET['id'];
        $productListId = (isset($_GET['productListId'])) ? $_GET['productListId'] : 66035;
        $activitiesInProductList = bkncpt_get_activities_in_product_list($productListId);        

        // For example, loop through all activities and create products
        foreach ($activitiesInProductList as $activity) {
            // Import each activity and get the product ID
            // Get ID of data to check How many record in insert
            $activityIdFromBokun = sanitize_text_field($activity['id']);        
                
                if( $activityId === $activityIdFromBokun ) {

        ?>
        <div class="wrap">
            <h1>View Details</h1>
            
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <div class="postbox">
                                <h2 class="hndle ui-sortable-handle"><span>Details</span></h2>
                                <div class="inside">
                                    <table class="wp-list-table widefat fixed striped table-view-list bkncpt bkncpt_view_table">
                                        <?php
                                        foreach ($activity as $field => $value) {
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
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="postbox-container-1" class="postbox-container postbox-container-bkncpty">
                        <div class="meta-box-sortables ui-sortable">
                            <div class="postbox">
                                <h2 class="hndle ui-sortable-handle"><span>Details</span></h2>
                                <div class="inside">
                                    <table class="wp-list-table widefat fixed striped table-view-list bkncpt bkncpt_view_table">
                                        <?php
                                        foreach ($activity as $field => $value) {
                                            if ($field === 'photos' && is_array($value)) {
                                                // If it's the 'photos' field and it's an array, extract and display URLs
                                                echo '<tr>';
                                                echo '<td class="">';
                                                foreach ($value as $photo) {
                                                    if (isset($photo['originalUrl'])) {
                                                        echo '<img src="' . esc_url($photo['originalUrl']) . '" alt="Photo" class="bokul_image">';
                                                    }
                                                }
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        }
                                        ?>
                                    </table> 
                                </div>
                            </div>
                        </div>
                    </div>
                <br class="clear">
            </div>
            </div>
        <?php
                break;
            }
            
        }
        
        // Additional logic for displaying item details based on ID
    } else {
        echo '<h2>No item ID specified</h2>';
    }
}

// Function to display the Manage Keys page
function bkncpt_manage_keys_page() {
    // Check if the reset keys button is clicked
    if (isset($_POST['reset_keys'])) {

        if ( ! wp_verify_nonce( $_POST['_wpnonce'], '_wpnonce_bokun-api' ) ) {
            return;
        }
        // Reset and erase the saved keys
        update_option('bokun_api_key', '');
        update_option('bokun_secret_key', '');
        update_option('bokun_post_type', '');
        update_option('bkncpt_google_service_account_email', '');
        update_option('bkncpt_google_private_key', '');
        update_option('bkncpt_google_drive_parent_folder_id', '');
        echo '<div class="updated"><p>Keys reset successfully!</p></div>';
        header("Location: admin.php?page=bokun-manage-keys");
        die;
    }

    // Load the saved keys from options
    $api_key = get_option('bokun_api_key', '');
    $secret_key = get_option('bokun_secret_key', '');
    $bokun_post_type = get_option('bokun_post_type', '');
    $google_service_account_email = get_option('bkncpt_google_service_account_email', '');
    $google_private_key = get_option('bkncpt_google_private_key', '');
    $google_drive_parent_folder = get_option('bkncpt_google_drive_parent_folder_id', '');
    
    // Check if the form is submitted
    if (isset($_POST['submit'])) {

        if ( ! wp_verify_nonce( $_POST['_wpnonce'], '_wpnonce_bokun-api' ) ) {
            return;
        }
        // Save the new keys
        $api_key = sanitize_text_field($_POST['api_key']);
        $secret_key = sanitize_text_field($_POST['secret_key']);
        $bokun_post_type = sanitize_text_field($_POST['bokun_post_type']);
        $google_service_account_email = sanitize_email($_POST['google_service_account_email']);
        $google_private_key = sanitize_textarea_field($_POST['google_private_key']);
        $google_drive_parent_folder = sanitize_text_field($_POST['google_drive_parent_folder']);

        // Update options with the new keys
        update_option('bokun_api_key', $api_key);
        update_option('bokun_secret_key', $secret_key);
        update_option('bokun_post_type', $bokun_post_type);
        update_option('bkncpt_google_service_account_email', $google_service_account_email);
        update_option('bkncpt_google_private_key', $google_private_key);
        update_option('bkncpt_google_drive_parent_folder_id', $google_drive_parent_folder);

        echo '<div class="updated"><p>Keys updated successfully!</p></div>';
        header("Location: admin.php?page=bokun-auth-check");
        die;
    }

    $custom_post_types = get_post_types(array('_builtin' => false));
    
    // Display the form with current keys as placeholders
    echo '<div class="wrap">';
    echo '<h1>Manage Bokun API Keys</h1>';
            echo '<div class="notice notice-info is-dismissible">';
    echo '<p><strong>Notice:</strong> Please add a new API on your <a href="https://extranet.bokun.io/api-keys" target="_blank">Bokun dashboard</a>, and copy the API Key and Secret Key. Paste the keys into the form below.</p>';
        echo '</div>';
    echo '<form method="post" action="" id="bokul_api_form" >';    
    esc_html(wp_nonce_field('_wpnonce_bokun-api'));
    echo '<table class="form-table" role="presentation">
            <tbody>
                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="user_login">API Key: <span class="description">(required)</span></label>
                    </th>
                    <td>
                        <input name="api_key" type="text" value="' . esc_attr($api_key) . '" aria-required="true" autocapitalize="none" autocorrect="off" autocomplete="off" placeholder="Enter your API key" required>
                    </td>
                </tr>
                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="user_login">Secret Key: <span class="description">(required)</span></label>
                    </th>
                    <td>
                        <input name="secret_key" type="text" value="' . esc_attr($secret_key) . '" aria-required="true" autocapitalize="none" autocorrect="off" autocomplete="off" placeholder="Enter your Secret key" required>
                    </td>
                </tr>
                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="user_login">Select Post Type: <span class="description">(required) 1</span></label>
                    </th>
                    <td>
                    <select name="bokun_post_type" id="bokun_post_type" required>
                        ';
                        
                        $custom_post_types = get_post_types(array('_builtin' => false));
                        $custom_post_types['post'] = 'Default Post';
                        $custom_post_types['product'] = 'Woocommerce Product';
                        $custom_post_types['post'] = 'Blog';
                        echo '<option value="">'.esc_html('Select Post Type').'</option>';
                        foreach ($custom_post_types as $post_key => $post_type) {
                            echo '<option value="'.esc_html($post_key).'" '.selected($bokun_post_type, $post_key).'>'.esc_html(ucfirst($post_type)).'</option>';
                        }                        
                echo '</select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th scope="row">
                        <label for="google_service_account_email">Google Service Account Email:</label>
                    </th>
                    <td>
                        <input name="google_service_account_email" type="email" value="' . esc_attr($google_service_account_email) . '" autocapitalize="none" autocorrect="off" autocomplete="off" placeholder="service-account@project.iam.gserviceaccount.com">
                        <p class="description">Used to authenticate with Google Drive.</p>
                    </td>
                </tr>
                <tr class="form-field">
                    <th scope="row">
                        <label for="google_private_key">Google Private Key:</label>
                    </th>
                    <td>
                        <textarea name="google_private_key" rows="8" cols="50" placeholder="-----BEGIN PRIVATE KEY-----
..." autocomplete="off">' . esc_textarea($google_private_key) . '</textarea>
                        <p class="description">Paste the private key from your Google service account JSON.</p>
                    </td>
                </tr>
                <tr class="form-field">
                    <th scope="row">
                        <label for="google_drive_parent_folder">Google Drive Parent Folder ID:</label>
                    </th>
                    <td>
                        <input name="google_drive_parent_folder" type="text" value="' . esc_attr($google_drive_parent_folder) . '" autocapitalize="none" autocorrect="off" autocomplete="off" placeholder="Root folder ID for product images">
                        <p class="description">All product folders will be created inside this Drive folder.</p>
                    </td>
                </tr>
            </tbody>
        </table>';
    echo '<p class="submit"><input type="submit" name="submit" id="" class="button button-primary" value="Save Keys"></p>';
    echo '</form>';

    // Display the reset keys button
    echo '<form method="post" action="">';
    esc_html(wp_nonce_field('_wpnonce_bokun-api'));
    echo '<input type="submit" name="reset_keys" class="button" value="Reset Keys">';
    echo '</form>';

    echo '</div>';
}
?>
