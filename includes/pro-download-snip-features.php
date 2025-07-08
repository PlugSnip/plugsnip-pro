<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * This file contains all the premium features specific to the Download Snip plugin.
 * It is loaded only when the PlugSnip Pro Suite plugin is active AND licensed,
 * and the free Download Snip plugin is also active.
 */

// --- 1. Unlimited Products Feature ---
// This feature is handled by the main Download Snip plugin checking for
// the `is_download_snip_pro_active()` function.
// No direct code is needed here, as the free plugin's `has_reached_limit()`
// method already contains the logic to bypass the limit if `is_download_snip_pro_active()` returns true.
// The modification needed was in the free `download-snip.php` file itself.

// --- 2. Stripe Integration ---
// This section will add Stripe payment gateway options to Download Snip.
// It involves adding new settings fields, modifying the shortcode output,
// and creating a new webhook listener for Stripe.

/**
 * Adds a new settings section and fields for Stripe to the Download Snip settings page.
 * This hooks into the existing 'admin_init' action of the free Download Snip plugin.
 */
add_action( 'admin_init', 'ds_pro_add_stripe_settings' );
function ds_pro_add_stripe_settings() {
    // Get the Freemius instance to ensure we only show Stripe options if licensed.
    $fs = plugsnip_pro_fs();

    if ( $fs->is_paying() ) {
        // Add a new settings section for Stripe.
        // The 'download-snip-settings' is the slug of the free plugin's settings page.
        add_settings_section(
            'ds_stripe_section',
            esc_html__( 'Stripe Settings (Pro)', 'download-snip' ),
            'ds_stripe_section_callback',
            'download-snip-settings'
        );

        // Add fields for Stripe Publishable Key.
        add_settings_field(
            'ds_stripe_publishable_key',
            esc_html__( 'Stripe Publishable Key', 'download-snip' ),
            'ds_stripe_publishable_key_callback',
            'download-snip-settings',
            'ds_stripe_section'
        );

        // Add fields for Stripe Secret Key.
        add_settings_field(
            'ds_stripe_secret_key',
            esc_html__( 'Stripe Secret Key', 'download-snip' ),
            'ds_stripe_secret_key_callback',
            'download-snip-settings',
            'ds_stripe_section'
        );

        // Add field for Stripe Webhook Secret (for IPN-like functionality).
        add_settings_field(
            'ds_stripe_webhook_secret',
            esc_html__( 'Stripe Webhook Secret', 'download-snip' ),
            'ds_stripe_webhook_secret_callback',
            'download-snip-settings',
            'ds_stripe_section'
        );

        // Register the new settings fields to be saved.
        // We need to hook into the free plugin's settings sanitization.
        add_filter( 'ds_sanitize_settings', 'ds_pro_sanitize_stripe_settings' );
    }
}

/**
 * Callback for the Stripe settings section description.
 */
function ds_stripe_section_callback() {
    echo '<p>' . esc_html__( 'Configure your Stripe API keys for professional on-site checkout.', 'download-snip' ) . '</p>';
    echo '<p>' . esc_html__( 'Webhook URL for Stripe: ', 'download-snip' ) . '<code>' . esc_url( home_url( '/?ds_action=stripe_webhook' ) ) . '</code></p>';
    echo '<p class="description">' . esc_html__( 'Add this URL to your Stripe Dashboard under Developers > Webhooks.', 'download-snip' ) . '</p>';
}

/**
 * Renders the input field for the Stripe Publishable Key.
 */
function ds_stripe_publishable_key_callback() {
    $options = get_option( 'ds_settings' ); // Using the free plugin's settings option for simplicity
    $key = isset( $options['stripe_publishable_key'] ) ? $options['stripe_publishable_key'] : '';
    echo '<input type="text" name="ds_settings[stripe_publishable_key]" value="' . esc_attr( $key ) . '" size="40" placeholder="pk_test_..." />';
}

/**
 * Renders the input field for the Stripe Secret Key.
 */
function ds_stripe_secret_key_callback() {
    $options = get_option( 'ds_settings' );
    $key = isset( $options['stripe_secret_key'] ) ? $options['stripe_secret_key'] : '';
    echo '<input type="text" name="ds_settings[stripe_secret_key]" value="' . esc_attr( $key ) . '" size="40" placeholder="sk_test_..." />';
}

/**
 * Renders the input field for the Stripe Webhook Secret.
 */
function ds_stripe_webhook_secret_callback() {
    $options = get_option( 'ds_settings' );
    $key = isset( $options['stripe_webhook_secret'] ) ? $options['stripe_webhook_secret'] : '';
    echo '<input type="text" name="ds_settings[stripe_webhook_secret]" value="' . esc_attr( $key ) . '" size="40" placeholder="whsec_..." />';
}

/**
 * Sanitizes the new Stripe settings fields.
 * This hooks into a filter in the free plugin's `ds_sanitize_settings` function.
 * You will need to add a filter in your free `settings-page.php` for this to work.
 *
 * Example filter in `settings-page.php` (inside `ds_sanitize_settings` function):
 * `$sanitized_input = apply_filters( 'ds_sanitize_settings', $sanitized_input, $input );`
 */
function ds_pro_sanitize_stripe_settings( $sanitized_input, $input ) {
    if ( isset( $input['stripe_publishable_key'] ) ) {
        $sanitized_input['stripe_publishable_key'] = sanitize_text_field( $input['stripe_publishable_key'] );
    }
    if ( isset( $input['stripe_secret_key'] ) ) {
        $sanitized_input['stripe_secret_key'] = sanitize_text_field( $input['stripe_secret_key'] );
    }
    if ( isset( $input['stripe_webhook_secret'] ) ) {
        $sanitized_input['stripe_webhook_secret'] = sanitize_text_field( $input['stripe_webhook_secret'] );
    }
    return $sanitized_input;
}

// --- 3. Modify Shortcode to Offer Stripe Option ---
/**
 * Filters the shortcode output to allow for Stripe payment option.
 * This will require a filter in the free plugin's `shortcode.php`
 *
 * Example filter in `shortcode.php` (at the end of `ds_render_shortcode`):
 * `return apply_filters( 'ds_render_shortcode_output', ob_get_clean(), $post_id, $settings );`
 */
add_filter( 'ds_render_shortcode_output', 'ds_pro_render_stripe_button', 10, 3 );
function ds_pro_render_stripe_button( $html, $post_id, $settings ) {
    $fs = plugsnip_pro_fs();

    if ( ! $fs->is_paying() ) {
        return $html; // If not licensed, return original HTML (PayPal button)
    }

    $stripe_publishable_key = isset( $settings['stripe_publishable_key'] ) ? $settings['stripe_publishable_key'] : '';
    $stripe_secret_key = isset( $settings['stripe_secret_key'] ) ? $settings['stripe_secret_key'] : ''; // Not used on frontend
    $product_title = get_the_title( $post_id );
    $price = get_post_meta( $post_id, '_ds_price', true );
    $currency_code = isset( $settings['currency'] ) ? $settings['currency'] : 'USD';

    // Only show Stripe button if keys are configured.
    if ( empty( $stripe_publishable_key ) ) {
        return $html; // Fallback to PayPal if Stripe not configured.
    }

    // Enqueue Stripe.js script
    wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', [], null, true );
    wp_enqueue_script( 'ds-pro-stripe-script', PLUGSNIP_PRO_PLUGIN_DIR . 'admin/js/stripe-checkout.js', ['jquery', 'stripe-js'], PLUGSNIP_PRO_VERSION, true );
    wp_localize_script( 'ds-pro-stripe-script', 'dsProStripe', [
        'publishableKey' => $stripe_publishable_key,
        'productTitle'   => $product_title,
        'price'          => $price,
        'currency'       => $currency_code,
        'productId'      => $post_id,
        'ajaxurl'        => admin_url( 'admin-ajax.php' ), // For creating Stripe Checkout session
        'returnUrl'      => ! empty( $settings['thank_you_page'] ) ? get_permalink( $settings['thank_you_page'] ) : home_url('/'),
        'nonce'          => wp_create_nonce( 'ds_pro_stripe_nonce' ), // <--- ADDED THIS LINE
    ]);

    ob_start();
    ?>
    <div class="ds-payment-options">
        <p class="ds-payment-choice"><?php esc_html_e( 'Choose your payment method:', 'download-snip' ); ?></p>
        <!-- PayPal button (original) -->
        <?php echo $html; // Render the original PayPal button ?>

        <!-- Stripe button -->
        <button type="button" class="ds-stripe-buy-button ds-buy-button" data-product-id="<?php echo esc_attr( $post_id ); ?>">
            <?php /* translators: 1: The price of the product, 2: The currency code (e.g., USD). */ ?>
            <?php printf( esc_html__( 'Pay with Card for %1$s %2$s', 'download-snip' ), $price, $currency_code ); ?>
        </button>
    </div>
    <?php
    return ob_get_clean();
}

// --- 4. Handle Stripe Checkout Session Creation (AJAX) ---
/**
 * Handles the AJAX request to create a Stripe Checkout Session.
 * This is called from the frontend when the Stripe button is clicked.
 */
add_action( 'wp_ajax_ds_pro_create_stripe_checkout', 'ds_pro_create_stripe_checkout' );
add_action( 'wp_ajax_nopriv_ds_pro_create_stripe_checkout', 'ds_pro_create_stripe_checkout' ); // Allow non-logged-in users
function ds_pro_create_stripe_checkout() {
    // Verify nonce for security (important for AJAX actions)
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'ds_pro_stripe_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
    }

    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    if ( ! $product_id ) {
        wp_send_json_error( [ 'message' => 'Invalid product ID.' ], 400 );
    }

    $settings = get_option( 'ds_settings' );
    $stripe_secret_key = isset( $settings['stripe_secret_key'] ) ? $settings['stripe_secret_key'] : '';
    $product_title = get_the_title( $product_id );
    $price = get_post_meta( $product_id, '_ds_price', true );
    $currency_code = isset( $settings['currency'] ) ? strtolower( $settings['currency'] ) : 'usd'; // Stripe expects lowercase currency

    if ( empty( $stripe_secret_key ) || empty( $product_title ) || empty( $price ) ) {
        wp_send_json_error( [ 'message' => 'Stripe configuration or product data missing.' ], 500 );
    }

    // Include Stripe PHP library (you'll need to download and include this in your Pro plugin)
    require_once PLUGSNIP_PRO_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php'; // Example path

    try {
        // Set Stripe API key
        \Stripe\Stripe::setApiKey( $stripe_secret_key );

        // Create a Checkout Session
        $checkout_session = \Stripe\Checkout\Session::create([
            'line_items' => [[
                'price_data' => [
                    'currency'     => $currency_code,
                    'product_data' => [
                        'name' => $product_title,
                    ],
                    'unit_amount'  => round( $price * 100 ), // Price in cents
                ],
                'quantity'   => 1,
            ]],
            'mode'        => 'payment',
            'success_url' => add_query_arg( 'ds_payment_status', 'success', $settings['thank_you_page'] ? get_permalink( $settings['thank_you_page'] ) : home_url('/') ),
            'cancel_url'  => get_permalink( $product_id ), // Or current page
            'metadata'    => [
                'product_id' => $product_id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'product_id' => $product_id,
                ],
            ],
        ]);

        wp_send_json_success( [ 'sessionId' => $checkout_session->id ] );

    } catch ( Exception $e ) {
        error_log( 'Stripe Checkout Session Error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => 'Failed to create checkout session: ' . $e->getMessage() ], 500 );
    }
}

// --- 5. Handle Stripe Webhook (IPN-like functionality) ---
/**
 * Listens for Stripe webhook events and processes them.
 * This acts like PayPal's IPN listener for Stripe.
 */
add_action( 'init', 'ds_pro_stripe_webhook_listener' );
function ds_pro_stripe_webhook_listener() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['ds_action'] ) && 'stripe_webhook' === $_GET['ds_action'] ) {
        ds_pro_handle_stripe_webhook();
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
}

/**
 * Processes the Stripe webhook event.
 */
function ds_pro_handle_stripe_webhook() {
    $fs = plugsnip_pro_fs();
    if ( ! $fs->is_paying() ) {
        status_header( 403 ); // Forbidden if Pro not active
        exit();
    }

    $settings = get_option( 'ds_settings' );
    $stripe_secret_key = isset( $settings['stripe_secret_key'] ) ? $settings['stripe_secret_key'] : '';
    $stripe_webhook_secret = isset( $settings['stripe_webhook_secret'] ) ? $settings['stripe_webhook_secret'] : '';

    if ( empty( $stripe_secret_key ) || empty( $stripe_webhook_secret ) ) {
        error_log( 'Stripe Webhook Error: Missing API keys or webhook secret.' );
        status_header( 500 );
        exit();
    }

    // Include Stripe PHP library
    require_once PLUGSNIP_PRO_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php'; // Example path

    \Stripe\Stripe::setApiKey( $stripe_secret_key );

    $payload = @file_get_contents( 'php://input' );
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
    $event = null;

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload, $sig_header, $stripe_webhook_secret
        );

    } catch ( \UnexpectedValueException $e ) {
        // Invalid payload
        error_log( 'Stripe Webhook Error: Invalid payload - ' . $e->getMessage() );
        status_header( 400 );
        exit();
    } catch ( \Stripe\Exception\SignatureVerificationException $e ) {
        // Invalid signature
        error_log( 'Stripe Webhook Error: Invalid signature - ' . $e->getMessage() );
        status_header( 400 );
        exit();
    }

    // Handle the event
    switch ( $event->type ) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            $product_id = isset( $session->metadata->product_id ) ? absint( $session->metadata->product_id ) : 0;
            $customer_email = isset( $session->customer_details->email ) ? sanitize_email( $session->customer_details->email ) : '';
            $amount_total = isset( $session->amount_total ) ? (float) $session->amount_total / 100 : 0.0; // Convert cents to dollars
            $currency = isset( $session->currency ) ? strtoupper( $session->currency ) : '';

            // Perform final checks (e.g., product ID, amount, currency)
            $expected_price = (float) get_post_meta( $product_id, '_ds_price', true );
            $settings_currency = isset($settings['currency']) ? $settings['currency'] : '';

            if ( $product_id && $customer_email && $amount_total >= $expected_price && $currency === $settings_currency ) {
                // Fulfill the order (reuse the free plugin's fulfillment function)
                // You might want to add a check here to prevent double fulfillment if webhook retries.
                ds_fulfill_order( $product_id, $customer_email );
            } else {
                error_log( 'Stripe Webhook Fulfillment Failed: Data mismatch for product ' . $product_id );
            }
            break;
        // ... handle other event types
        default:
            // Unexpected event type
            error_log( 'Stripe Webhook: Received unexpected event type ' . $event->type );
    }

    status_header( 200 ); // Respond with 200 OK to Stripe
    exit();
}
