<?php
/*
Plugin Name: WooCommerce Reward System
Description: Adds a reward system to WooCommerce.
Version: 1.0
Author: Your Name
 */

//displaying error lines
error_reporting(E_ALL);
ini_set('display_errors', 1);

// initial value of user_point setup
// Hook into user registration to set initial points

//------------------------------------------Setting intial value for user point------------------------------------------
function set_initial_points_on_registration($user_id)
{
    // Set the initial points value for new users
    $initial_points = 0; // You can set this to any initial value you prefer
    // Store the initial points in user_meta
    update_user_meta($user_id, 'user_points', $initial_points);
}

add_action('user_register', 'set_initial_points_on_registration');

// ---------------------------------------Enqueue Style Sheet----------------------------------------------------------
function enqueue_reward_system_styles()
{
    wp_enqueue_style('reward-system-css', plugins_url('css/reward-system.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'enqueue_reward_system_styles');

// ---------------------------------------Enqueue Scripts----------------------------------------------------------

function enqueue_jquery()
{
    wp_enqueue_script('jquery');
}

add_action('wp_enqueue_scripts', 'enqueue_jquery');

//------------------------------------ User Points Management Functions-----------------------------------------------
function get_user_points($user_id)
{
    return get_user_meta($user_id, 'user_points', true);
}

function add_user_points($user_id, $points)
{
    $current_points = get_user_points($user_id);
    update_user_meta($user_id, 'user_points', $current_points + $points);
}

function add_user_points_for_order($user_id, $order_id, $points)
{
    $order_points = get_user_meta($user_id, 'order_points', true);
    if (!$order_points) {
        $order_points = array();
    }
    // Store points earned for the specific order
    $order_points[$order_id] = $points;
    update_user_meta($user_id, 'order_points', $order_points);
}

function deduct_user_points($user_id, $points)
{
    $current_points = get_user_points($user_id);

    if ($current_points >= $points) {
        update_user_meta($user_id, 'user_points', $current_points - $points);
        return true;
    }
    return false;
}

// --------------------------------------Points and Earnings Updates--------------------------------------------------------
function update_user_points_on_purchase($order_id)
{
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $order_total = $order->get_total();
    $points_earned = floatval($order_total); // 1 point per $1 spent

    add_user_points($user_id, $points_earned);
    // Add points earned for the specific order
    add_user_points_for_order($user_id, $order_id, $points_earned);
}
add_action('woocommerce_order_status_processing', 'update_user_points_on_purchase');
// add_action('woocommerce_order_status_completed', 'update_user_points_on_purchase');

// --------------------------Cart and Checkout Page Customization----------------------------------------------------------
function display_cart_reward_section()
{
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $points = intval(get_user_points($user_id));
        echo '<div class="reward-section">';
        if ($points > 20) {
            echo '<p>You have ' . $points . ' points. You can avail up to $' . ($points / get_option('reward_points_conversion')) . ' discount by redeeming those.</p>';
            echo '<a class="btn-custom" href="http://localhost/wordpress/index.php/checkout/" onclick="redeemPoints()" class="redeem-points-button">Redeem my points</a>';
            echo '</div>';
        } else {
            echo '<p>You have ' . $points . ' points. You need Atleast 20 Point to redeem the coupon.';
            echo '</div>';
        }
    } else {
        echo '<div class="reward-section">';
        echo '<p>You can earn points on every transaction.';
        echo '<a class=button-custom href="http://localhost/wordpress/wp-login.php" class="signup-reward-program-button">Signup for Reward Program</a></p>';
        echo '</div>';
    }
}
add_action('woocommerce_before_cart', 'display_cart_reward_section');

function display_checkout_reward_section()
{
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $points = intval(get_user_points($user_id));

        // echo '<div class="reward-section">';
        // echo '<p>You have ' . $points . ' points. You can avail up to $' . ($points / get_option('reward_points_conversion')) . ' discount by redeeming those.</p>';
        // echo '<a href="#" onclick="redeemPoints()" class="redeem-points-button">Redeem my points</a>';
        // echo '</div>';
    } else {
        echo '<div class="reward-section alpha">';
        echo '<p>You can earn points on every transaction.';
        echo '<a href="http://localhost/wordpress/wp-login.php" class="signup-reward-program-button">Signup for Reward Program</a></p>';
        echo '</div>';
    }
}
add_action('woocommerce_review_order_before_payment', 'display_checkout_reward_section');

// ----------------------------Account Page Adding Points--------------------------------

// Append user's points to the end of the account page content
function append_user_points_to_account_page_content($content)
{

    if (is_account_page()) {
        $user_id = get_current_user_id();
        $login = is_user_logged_in();

        $points = intval(get_user_points($user_id)); // Use your existing function to get the user's points
        $user_display_name = get_the_author_meta('display_name', $user_id);

        $points_text = '<div class="reward-section"><p style="font-size:26px"><b>Points: </b> <i>Mr ' . $user_display_name . '</i> Your Rewards Points Are: ' . $points . '</p>
</div>';
        $points_text .= $content;

    }

    return $points_text;

}

add_filter('the_content', 'append_user_points_to_account_page_content');

// ------------------------------------------------------------------------------------------

//---------------------- Append User Points to Order Confirmation Email--------------------------------------------------------------
function add_points_to_order_email($order, $sent_to_admin, $plain_text, $email)
{
    // Get the user's points balance
    $user_id = $order->get_user_id();
    $user_points = get_user_points($user_id);

    // Retrieve the order-specific points from user meta
    $order_id = $order->get_id();
    $order_points = get_user_meta($user_id, 'order_points', true);
    $points_earned = isset($order_points[$order_id]) ? $order_points[$order_id] : 0;

    // Customize the points earned message
    $all_points_message = 'Totel Points : ' . $user_points;
    $specific_points_message = 'Points Earned From This Order: ' . $points_earned;

    // Add the points message to the order email
    if ($plain_text) {
        echo $all_points_message . "\n";
    } else {
        echo '<div class="reward-section"><p>' . $all_points_message . '</p><p>' . $specific_points_message . '</p></div>';
    }
}
add_filter('woocommerce_email_customer_details', 'add_points_to_order_email', 10, 4);

// ----------------------------------------------------------------------------------------

//-----------------------------------------Adding Points on Recipt Page-------------------------------------------

// Modify the function that displays points on the "Order Received" page to get points earned for the specific order.

function append_user_points_to_order_received($content)
{
    if (is_wc_endpoint_url('order-received')) {
        $user_id = get_current_user_id();
        $order_id = wc_get_order_id_by_order_key(wc_clean($_GET['key']));

        $order_points = get_user_meta($user_id, 'order_points', true);
        $points = 0;

        if (isset($order_points[$order_id])) {
            $points = $order_points[$order_id];
        }

        $user_display_name = get_the_author_meta('display_name', $user_id);
        $points_text = '<div class="reward-section">';
        $points_text .= '<p>Your Rewards Points for this order: ' . $points . '</p>';
        $points_text .= '</div>';
        $content = $points_text . $content;
    }
    return $content;
}
add_filter('the_content', 'append_user_points_to_order_received');

// ------------------------------------Admin Coversion page--------------------------------------------------

function reward_settings_page()
{
    ?>
<div class="wrap">
    <h2>Reward System Settings</h2>
    <form method="post" action="options.php">
        <?php
settings_fields('reward_settings');
    do_settings_sections('reward-settings');
    submit_button();
    ?>
    </form>
</div>
<?php
}

function reward_section_callback()
{
    echo '<p>Configure the reward system settings.</p>';
}

function reward_points_conversion_callback()
{
    $conversion = get_option('reward_points_conversion', '20');
    echo '<input type="text" name="reward_points_conversion" value="' . esc_attr($conversion) . '" /> points = $1';
}

function save_reward_settings()
{
    if (isset($_POST['reward_points_conversion'])) {
        $conversion = sanitize_text_field($_POST['reward_points_conversion']);
        update_option('reward_points_conversion', $conversion);
    }
}

function add_reward_settings_menu()
{
    add_menu_page('Reward Settings', 'Reward Settings', 'manage_options', 'reward-settings', 'reward_settings_page');
}
add_action('admin_menu', 'add_reward_settings_menu');

function register_reward_settings()
{
    register_setting('reward_settings', 'reward_points_conversion');
    add_settings_section('reward_section', 'Reward Settings', 'reward_section_callback', 'reward-settings');
    add_settings_field('reward_points_conversion', 'Points to Dollar Conversion', 'reward_points_conversion_callback', 'reward-settings', 'reward_section');
}
add_action('admin_init', 'register_reward_settings');

//----------------------------------------------------------------------

//---------------------------------------------- Discount Coupon Management--------------------------------------------------

function generate_and_apply_discount_coupon($user_id, $cart_total)
{
    $coupon_code = 'REWARD_' . $user_id . '_' . time();
    // $discount_amount = $cart_total * 0.5; // 1 point = $0.5 discount
    $discount_amount = $cart_total; // 1 point = $0.5 discount

    $coupon = array(
        'post_title' => $coupon_code,
        'post_content' => '',
        'post_status' => 'publish',
        'post_author' => $user_id,
        'post_type' => 'shop_coupon',
    );

    $new_coupon_id = wp_insert_post($coupon);

    update_post_meta($new_coupon_id, 'discount_type', 'fixed_cart');
    update_post_meta($new_coupon_id, 'coupon_amount', $discount_amount);

    WC()->cart->apply_coupon($coupon_code);

    $new_cart_total = WC()->cart->get_cart_total();

    return array(
        'coupon_code' => $coupon_code,
        'new_cart_total' => $new_cart_total,
    );
}

// ------------------------------------------------------------------------------------

// Implement Discount Coupon Management
function redeem_points_for_discount()
{
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $points = intval(get_user_points($user_id));

        if ($points >= 20) {
            // Check if the user has at least 20 points to redeem for a $1 discount
            $discount_amount = $points / get_option('reward_points_conversion'); // Calculate the discount amount in dollars
            $discount_amount = $points / get_option('reward_points_conversion'); // Calculate the discount amount in dollars
            $remaining_points = $points % get_option('reward_points_conversion'); // Calculate the remaining points

            // Generate and apply the discount coupon
            $coupon_info = generate_and_apply_discount_coupon($user_id, $discount_amount);

            // Deduct the redeemed points and update the remaining points
            deduct_user_points($user_id, $points - $remaining_points);

            // Display a success message to the user
            echo '<div class="success-message">';
            echo 'You have redeemed ' . $points . ' points for a $' . $discount_amount . ' discount.';
            echo '</div>';
        } else {
            // Display a message if the user doesn't have enough points to redeem
            echo '<div class="reward-section">';
            echo 'You need at least 20 points to redeem for a discount.';
            echo '</div>';
        }
    }
}
add_action('woocommerce_review_order_before_payment', 'redeem_points_for_discount');

// AJAX action to redeem points
add_action('wp_ajax_redeem_points_action', 'redeem_points_action');
add_action('wp_ajax_nopriv_redeem_points_action', 'redeem_points_action');

// AJAX action to redeem points
function redeem_points_action()
{
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $points = intval(get_user_points($user_id));

        if ($points >= 20) {
            // Check if the user has at least 20 points to redeem for a $1 discount
            $discount_amount = $points / get_option('reward_points_conversion'); // Calculate the discount amount in dollars
            $remaining_points = $points % get_option('reward_points_conversion'); // Calculate the remaining points

            // Generate and apply the discount coupon
            $coupon_info = generate_and_apply_discount_coupon($user_id, $discount_amount);

            // Deduct the redeemed points and update the remaining points
            deduct_user_points($user_id, $points - $remaining_points);

            // Redirect to the cart page
            wp_redirect(wc_get_cart_url());
            exit;
        }
    }
}
?>