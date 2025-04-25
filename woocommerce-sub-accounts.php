<?php
/**
 * Plugin Name: WooCommerce Sub-Accounts
 * Description: Allows users to create sub-accounts under their main account and assign products to them at checkout.
 * Version: 1.2.0
 * Author: <a href="mailto:theprodeveloper789@gmail.com">The Pro Developer</a>
 * Text Domain: wc-subaccounts
 */
 
if (!defined('ABSPATH')) exit;

// Enqueue Scripts and Styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('subaccount-checkout-style', plugin_dir_url(__FILE__) . 'assets/css/subaccount-style.css');
    wp_enqueue_script('subaccount-checkout-js', plugin_dir_url(__FILE__) . 'assets/js/subaccount.js', array('jquery'), null, true);
    wp_enqueue_script('qrcode-generator', 'https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js', array(), null, true);
    wp_localize_script('subaccount-checkout-js', 'ajax_object_for_Qrcode', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
});

//add_action('woocommerce_checkout_create_order', 'subaccount_create_user_and_save_meta', 20, 2);
add_action('woocommerce_checkout_update_order_meta', 'subaccount_save_to_order_meta', 20, 2);
add_action('woocommerce_before_order_notes', 'subaccount_checkout_fields');

function custom_replace_place_order_button($button_html) {
    // Modify the button HTML
    $custom_button = '<button type="button" class="button alt" id="place_order_custom" style="    padding: 12px 0;
    margin-top: 10px;
">Place Order</button>';
    
    return $custom_button;
}
add_filter('woocommerce_order_button_html', 'custom_replace_place_order_button');

function subaccount_checkout_fields($checkout) {
    $cart = WC()->cart->get_cart(); // Get cart items
    if (!empty($cart)) {
        $first_item = reset($cart); // Get the first item in the cart
        $first_product_id = $first_item['product_id']; // Get product ID
    }

    $current_user_id = get_current_user_id();
    $types = ['pet' => 'mi mascota', 'child' => 'Mi hijo/hija', 'family' => 'Otro miembro de la familia'];
    $main_user = wp_get_current_user();
    ?>
    <div class="subaccount-wrapper">
        <div style="display:none">
            <div id="qrcode" style="position: relative; width: 203px; height: 203px; background-color: white; margin: 0 auto;padding:7px;border-radius:10px;"></div>
        </div>
		<input type="hidden" id="qr_code_scanning_link" name="qr_code_scanning_link" value="<?php echo (get_field('qr_code_scanning_link', 'option')['url']??'');?>">
        <input type="hidden" id="Uid" value="<?php echo $main_user->ID;?>">
        <input type="hidden" id="product_id" value="<?php echo $first_product_id;?>">
        <input type="hidden" id="dummy_qr_code" value="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/img/dummy-qr.png'); ?>">
        <input type="hidden" id="qr_code_url" name="qr_code_url" value="">
        <h3>¿Para quién estás comprando?</h3> <!-- Who are you purchasing for?-->
        <select style="margin-bottom: 10px;" name="subaccount_type" id="subaccount_type" required>
            <option value="self">Mí mismo</option> <!--Myself -->
            <?php foreach ($types as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>

        <div id="subaccount_sections_wrapper">
            <?php foreach ($types as $key => $label):
                $subaccounts = get_user_meta($current_user_id, 'subaccounts_' . $key, true);
                $subaccounts = is_array($subaccounts) ? $subaccounts : [];
            ?>
                <div class="subaccount-sublist sublist-<?php echo esc_attr($key); ?>" style="display:none;">
                    <h3 style="margin-bottom: 10px;">Existente <?php echo esc_html($label); ?> Subcuenta : </h3><!--Existing Sub Account-->
                    <?php if (!empty($subaccounts)):
                        foreach ($subaccounts as $sub_id):
                            $sub_user = get_userdata($sub_id);
                            if ($sub_user): ?>
                                <label><input type="radio" name="selected_subaccount" value="<?php echo esc_attr($sub_id); ?>"> <?php echo esc_html($sub_user->display_name); ?></label><br>
                            <?php endif;
                        endforeach;
                    endif; ?>
                    <a href="#" class="add-new-subaccount-btn" data-type="<?php echo esc_attr($key); ?>">+ &nbsp;  Agregar nuevo <?php echo esc_html($label); ?></a><!-- Add New -->
                </div>
            <?php endforeach; ?>
        </div>

        <div id="subaccountModal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close">&times;</span>
               <h4>Agregar nueva subcuenta</h4><!--Add New Subaccount-->
                 <label for="modal_subaccount_name">Nombre:</label> <!--Name-->
                <input type="text" id="modal_subaccount_name" name="modal_subaccount_name" placeholder="Introduce el nombre">
                <input type="hidden" id="modal_subaccount_type">
                <button type="button" id="save_subaccount_btn">Ahorrar</button>
            </div>
        </div>
    </div>
    <?php
}

add_action('wp_ajax_create_subaccount_from_checkout', 'create_subaccount_from_checkout');
function create_subaccount_from_checkout() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'User not logged in']);//El usuario no ha iniciado sesión
		wp_send_json_error(['message' => 'El usuario no ha iniciado sesión']);
    }

    $main_user = wp_get_current_user();
    $main_email = $main_user->user_email;
    $type = sanitize_text_field($_POST['subaccount_type']);
    $modal_name = sanitize_text_field($_POST['modal_subaccount_name']);

    if (empty($modal_name)) {
        //wp_send_json_error(['message' => 'Missing subaccount name']);//Nombre de subcuenta faltante
		wp_send_json_error(['message' => 'Nombre de subcuenta faltante']);
    }

    $slug_name = str_replace(' ', '_', strtolower($modal_name));
    preg_match('/(.*?)@(.*)/', $main_email, $matches);

    $base_email = $matches[1];
    $domain_email = $matches[2]; 

    $new_email = $base_email . '+' . $slug_name . '@' . $domain_email;
    $suffix = 1;
    while (email_exists($new_email)) {
        $new_email = $base_email . '+' . $slug_name . $suffix . '@' . $domain_email;
        $suffix++;
    }

    $parent_id = get_current_user_id();
    $unique_username = sanitize_user(strtolower($modal_name . '_' . $parent_id));
	$password = wp_generate_password();
    if (username_exists($unique_username)) {
        $suffix = 1;
        while (username_exists($unique_username . $suffix)) {
            $suffix++;
        }
        $unique_username = $unique_username . $suffix;
    }
    $user_id = wp_create_user($unique_username, $password, $new_email);

    if (is_wp_error($user_id)) {
        $error_msg = $user_id->get_error_message();
        
        // Show a user-friendly error
        if ($user_id->get_error_code() === 'existing_user_login') {
            //wp_send_json_error(['message' => 'Username already exists. Please choose a different name.']);
			wp_send_json_error(['message' => 'El nombre de usuario ya existe. Elija otro.']);
        } else {
            wp_send_json_error(['message' => $error_msg]);
        }
        return;
    }
    wp_update_user([
            'ID' => $user_id,
            'display_name' => $modal_name,
            'nickname' => $modal_name
        ]);

    update_user_meta($user_id, 'is_subaccount', 1);
    update_user_meta($user_id, 'subaccount_type', $type);
    update_user_meta($user_id, 'parent_account', $main_user->ID);

    $existing_subs = get_user_meta($main_user->ID, 'subaccounts_' . $type, true);
    $existing_subs = is_array($existing_subs) ? $existing_subs : [];
    $existing_subs[] = $user_id;
    update_user_meta($main_user->ID, 'subaccounts_' . $type, $existing_subs);

    //wp_mail($main_email, 'New Sub Account Created', "A new subaccount has been created.\nEmail: $new_email\nPassword: $password");
	wp_mail($main_email, 'Nueva subcuenta creada', "Se ha creado una nueva subcuenta.\nCorreo electrónico: $new_email\nContraseña: $password");

    wp_send_json_success(['user_id' => $user_id]);
}

function subaccount_save_to_order_meta($order_id, $data) {
    if (!empty($_POST['subaccount_type'])) {
        update_post_meta($order_id, 'purchased_for', sanitize_text_field($_POST['subaccount_type']));
    }
}
// Save Subaccount Data After Order
add_action('woocommerce_checkout_update_order_meta', function($order_id) {
    if (isset($_POST['subaccount_type']) && $_POST['subaccount_type'] !== 'self') {
        $type = sanitize_text_field($_POST['subaccount_type']);
        $selected = sanitize_text_field($_POST['subaccount_name'] ?? '');
        update_post_meta($order_id, '_subaccount_type', $type);
        update_post_meta($order_id, '_subaccount_name', $selected);

        $user_id = get_current_user_id();
        $subaccounts = get_user_meta($user_id, 'subaccounts_data', true);
        if (!is_array($subaccounts)) $subaccounts = [];
        if (!in_array($selected, $subaccounts[$type] ?? [])) {
            $subaccounts[$type][] = $selected;
            update_user_meta($user_id, 'subaccounts_data', $subaccounts);
        }
    }
});

// Validate input
add_action('woocommerce_checkout_process', 'subaccount_process_checkout');
function subaccount_process_checkout() {
    $type = $_POST['subaccount_type'] ?? 'self';
    //print_r($_POST);exit;
    if ($type !== 'self' && empty($_POST['subaccount_type']) && empty($_POST['selected_subaccount'])) {
        //print_r($_POST);exit;
        //wc_add_notice(__('Please select or add a name for the sub-account.'), 'error');
		wc_add_notice(__('Seleccione o agregue un nombre para la subcuenta.'), 'error');

    }
}

// Handle creation
add_action('woocommerce_checkout_create_order', 'subaccount_handle_creation', 10, 2);
function subaccount_handle_creation($order, $data) {
    $user = wp_get_current_user();
    $main_user_id = $user->ID;
    $main_user_email = $user->user_email;
    $type = sanitize_text_field($_POST['subaccount_type'] ?? 'self');

    if ($type === 'self') return;

    $selected = sanitize_text_field($_POST['selected_subaccount'] ?? '');

    if (!empty($selected)) {
        $order->update_meta_data('sub_account_name', $selected);
        $order->update_meta_data('sub_account_type', $type);
        do_action('subaccount_generate_qrcode', $selected, $order->get_id());
        return;
    }

    $name = sanitize_text_field($_POST['subaccount_name']);
    $base_email = str_replace('@', '+' . str_replace(' ', '_', $name) . '@', $main_user_email);
    $final_email = $base_email;
    $i = 1;
    while (email_exists($final_email)) {
        $final_email = str_replace('@', $i . '@', $base_email);
        $i++;
    }
    $password = wp_generate_password(8, true);
    $new_user_id = wp_create_user($name, $password, $final_email);
    update_user_meta($new_user_id, 'parent_user_id', $main_user_id);

    $existing = get_user_meta($main_user_id, 'sub_accounts_list_' . $type, true);
    $arr = $existing ? explode(',', $existing) : [];
    $arr[] = $name;
    update_user_meta($main_user_id, 'sub_accounts_list_' . $type, implode(',', array_unique($arr)));

    $order->update_meta_data('sub_account_name', $name);
    $order->update_meta_data('sub_account_type', $type);
    $order->update_meta_data('sub_account_email', $final_email);

    //wp_mail($main_user_email, 'New Sub Account Created', "A new sub-account has been created.\n\nEmail: $final_email\nPassword: $password", ['Content-Type: text/plain; charset=UTF-8']);
	wp_mail($main_user_email, 'Nueva subcuenta creada', "Se ha creado una nueva subcuenta.\n\nCorreo electrónico: $final_email\nContraseña: $password", ['Content-Type: text/plain; charset=UTF-8']);
    do_action('subaccount_generate_qrcode', $name, $order->get_id());
}

// Generate QR Code
add_action('subaccount_generate_qrcode', 'subaccount_generate_qrcode_handler', 10, 2);
function subaccount_generate_qrcode_handler($subaccount_name, $order_id) {
    $order = wc_get_order($order_id);
    $qr_data = 'QR-' . sanitize_title($subaccount_name) . '-' . $order_id;
    $svg = '<svg height="100" width="100"><text x="10" y="50">' . esc_html($qr_data) . '</text></svg>';
    $upload_dir = wp_upload_dir();
    $filename = 'qr-code-' . $order_id . '.svg';
    $filepath = $upload_dir['path'] . '/' . $filename;
    file_put_contents($filepath, $svg);
    $url = $upload_dir['url'] . '/' . $filename;
    update_post_meta($order_id, 'subaccount_qrcode_url', $url);
}



add_action('wp_ajax_savesub_qr_code', 'savesub_qr_code');
add_action('wp_ajax_nopriv_savesub_qr_code', 'savesub_qr_code');

function savesub_qr_code() {
    $loged_in_user_id = get_current_user_id();
    $_POST['identifier'];
    if (isset($_POST['qr_code_image']) && isset($_POST['product_id']) && isset($_POST['qr_code_data'])) {
        if(!is_user_logged_in()){
            wp_send_json_success(['logged_in' => false]);
            exit;
        } else {
            $user_id = get_current_user_id();
        }

        $qr_code_svg = $_POST['qr_code_image']; // Expecting SVG string data here
        $qr_code_svg = stripslashes($qr_code_svg); 
        $product_id  = intval($_POST['product_id']);
        $upload_dir  = wp_upload_dir();
        $strtotimenow = strtotime('now');
        $filename    = "qr_code_{$strtotimenow}.svg"; // Save as SVG file
        $file_path   = $upload_dir['path'] . '/' . $filename;

        // Ensure the SVG starts with <svg> tag
        if (strpos($qr_code_svg, '<svg') !== false) {
            // Save the SVG string as a file
            if (file_put_contents($file_path, $qr_code_svg)) {
                // Get the URL for the saved SVG file
                $upload_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path);
                $timestamp = date('Y-m-d H:i:s');                
                $data = array(
                    'product_id'     => $product_id,
                    'user_id'        => $user_id,
                    'timestamp'      => $timestamp,
                    'qrcode_enabled' => 1,
                    'qr_code_url'    => $upload_url
                );
                set_transient("qrcode_posttype_data", $data);
                wp_send_json_success(['url' => $upload_url]);
            } else {
                wp_send_json_error('Failed to save QR code SVG.');
            }
        } else {
            wp_send_json_error('Invalid SVG data.');
        }
    } else {
        wp_send_json_error('Invalid data.');
    }
}

add_action('woocommerce_checkout_order_processed', 'attach_order_to_qrcode_post');

function attach_order_to_qrcode_post($order_id) {
    
    $loged_in_user_id = get_current_user_id();
    $qrcode_posttype_data = get_transient("qrcode_posttype_data");
    if(isset($qrcode_posttype_data) && $qrcode_posttype_data['user_id'] == $loged_in_user_id){
        // Prepare post data for the new QR code post
        $post_data = array(
            'post_title'    => 'QR Code for Product ' . $qrcode_posttype_data['product_id'] . ' And User ' . $qrcode_posttype_data['user_id'],
            'post_content'  => '',
            'post_status'   => 'publish',
            'post_author'   => $qrcode_posttype_data['user_id'],
            'post_type'     => 'qrcode'
        );

        // Insert the new QR code post
        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            // Save additional meta data
            $meta_data = array(
                'product_id'     => $qrcode_posttype_data['product_id'],
                'user_id'        => $qrcode_posttype_data['user_id'],
                'qr_code_url'    => $qrcode_posttype_data['qr_code_url'],
                'timestamp'      => $qrcode_posttype_data['timestamp'],
                'qrcode_enabled' => 1,
                'order_id'       => $order_id
            );

            foreach ($meta_data as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
            //engrave_data
            update_field("qrcode_post_id", $post_id , $order_id);
            // Delete the transient as the post was successfully created
            delete_transient("qrcode_posttype_data");
        }

    }
}

add_action('show_user_profile', 'show_subaccounts_in_admin');
add_action('edit_user_profile', 'show_subaccounts_in_admin');

function show_subaccounts_in_admin($user) {
    // Only show for parent accounts (i.e. main accounts)
    //$types = ['pet' => 'My Pets', 'child' => 'My Children', 'family' => 'Other Family Members'];
	$types = ['pet' => 'Mis Mascotas', 'child' => 'Mis hijos/hijas', 'family' => 'Otros miembros de la familia'];

    //echo '<h2>Subaccounts</h2>';
	echo '<h2>Subcuentas</h2>';

    echo '<table class="form-table">';

    foreach ($types as $key => $label) {
        $subaccount_ids = get_user_meta($user->ID, 'subaccounts_' . $key, true);
        $subaccount_ids = is_array($subaccount_ids) ? $subaccount_ids : [];

        echo '<tr>';
        echo '<th><label>' . esc_html($label) . '</label></th>';
        echo '<td>';

        if (!empty($subaccount_ids)) {
            echo '<ul>';
            foreach ($subaccount_ids as $sub_id) {
                $sub_user = get_userdata($sub_id);
                if ($sub_user) {
                    $edit_url = get_edit_user_link($sub_id);
                    echo '<li><a href="' . esc_url($edit_url) . '" target="_blank">' . esc_html($sub_user->display_name) . ' (' . esc_html($sub_user->user_email) . ')</a></li>';
                }
            }
            echo '</ul>';
        } else {
            echo '<em>No ' . strtolower($label) . ' found.</em>';
        }

        echo '</td>';
        echo '</tr>';
    }

    echo '</table>';
}
//woocommerce_admin_order_data_after_order_details
add_action('woocommerce_admin_order_data_after_order_meta', function($order){

	$order = wc_get_order( $order_id );
    $sub_type = $order->get_meta( 'sub_account_type' );
	$sub_name = $order->get_meta( 'sub_account_name' );
	// Check if it's a numeric user ID
	if (is_numeric($sub_name)) {
		$user = get_userdata((int)$sub_name);
		if ($user) {
			$sub_name = $user->display_name;
		}
	}
    if (!$sub_type || !$sub_name) return;

    echo '<div class="order_data_column">';
	//echo "<p>This product is purchased for <strong>". esc_html(ucfirst($sub_type)) ."</strong> and their name is <strong>". esc_html($sub_name) ."</strong>.</p>";
	echo "<p>Este producto ha sido comprado para tu <strong>". esc_html(ucfirst($sub_type)) ."</strong> y su nombre es <strong>". esc_html($sub_name) ."</strong>.</p>";
    echo '</div>';
});
add_action( 'woocommerce_thankyou', 'subaccount_info_display_on_thankyou', 100, 1 );
function subaccount_info_display_on_thankyou($order_id) {
	
	$order = wc_get_order( $order_id );
    $sub_type = $order->get_meta( 'sub_account_type' );
	$sub_name = $order->get_meta( 'sub_account_name' );
	// Check if it's a numeric user ID
	if (is_numeric($sub_name)) {
		$user = get_userdata((int)$sub_name);
		if ($user) {
			$sub_name = $user->display_name;
		}
	}
    if (!$sub_type || !$sub_name) return;

    echo '<section class="woocommerce-subaccount-info" style="margin-top: 20px;">';
    //echo "<p>This product is purchased for your <strong>". esc_html(ucfirst($sub_type)) ."</strong> and their name is <strong>". esc_html($sub_name) ."</strong>.</p>";
	echo "<p>Este producto ha sido comprado para tu <strong>". esc_html(ucfirst($sub_type)) ."</strong> y su nombre es <strong>". esc_html($sub_name) ."</strong>.</p>";
    echo '</section>';
}


add_action('woocommerce_email_order_meta', function($order, $sent_to_admin, $plain_text, $email){

	$order = wc_get_order( $order->get_id() );
    $sub_type = $order->get_meta( 'sub_account_type' );
	$sub_name = $order->get_meta( 'sub_account_name' );
	// Check if it's a numeric user ID
	if (is_numeric($sub_name)) {
		$user = get_userdata((int)$sub_name);
		if ($user) {
			$sub_name = $user->display_name;
		}
	}
    if (!$sub_type || !$sub_name) return;

    //echo "<p>This product is purchased for your <strong>". esc_html(ucfirst($sub_type)) ."</strong> and their name is <strong>". esc_html($sub_name) ."</strong>.</p>";
	echo "<p>Este producto ha sido comprado para tu <strong>". esc_html(ucfirst($sub_type)) ."</strong> y su nombre es <strong>". esc_html($sub_name) ."</strong>.</p>";
	
}, 20, 4);


add_action('woocommerce_account_dashboard', 'show_subaccount_notice_on_account_page');

function show_subaccount_notice_on_account_page() {
    $user_id = get_current_user_id();

    // Only show if this is a subaccount
    $is_sub = get_user_meta($user_id, 'is_subaccount', true);
	
	
    if (!$is_sub) return;
	
    $parent_id = get_user_meta($user_id, 'parent_account', true);
    $type = get_user_meta($user_id, 'subaccount_type', true);
	
    if (!$parent_id || !$type) return;

    $parent_user = get_userdata($parent_id);
    if (!$parent_user) return;

    $type_label = ucfirst($type);
    $parent_name = $parent_user->display_name;

    echo '<div class="woocommerce-message" role="alert" style="margin-top: 20px;">';
    echo 'This is a <strong>' . esc_html($type_label) . '</strong> subaccount linked to <strong>' . esc_html($parent_name) . '</strong>.';
    echo '</div>';
}

