/**
 * admin/js/stripe-checkout.js
 *
 * This JavaScript file handles the client-side logic for Stripe Checkout.
 * It listens for clicks on the Stripe "Pay with Card" button,
 * creates a Stripe Checkout Session via an AJAX call to the WordPress backend,
 * and then redirects the user to the Stripe hosted checkout page.
 */

jQuery(document).ready(function($) {
    // Ensure Stripe.js is loaded before proceeding.
    // The `dsProStripe` object is localized from PHP (pro-download-snip-features.php).
    if (typeof Stripe === 'undefined' || typeof dsProStripe === 'undefined') {
        console.error('Stripe.js or dsProStripe object not loaded.');
        return;
    }

    // Initialize Stripe with your publishable key.
    const stripe = Stripe(dsProStripe.publishableKey);

    // Listen for clicks on the Stripe "Pay with Card" button.
    // The button has the class 'ds-stripe-buy-button'.
    $(document).on('click', '.ds-stripe-buy-button', function(e) {
        e.preventDefault(); // Prevent default button action (e.g., form submission).

        const productId = $(this).data('product-id'); // Get the product ID from the button's data attribute.

        // Show a loading indicator (optional, but good for UX).
        $(this).text('Processing...').prop('disabled', true);
        $(this).addClass('ds-loading-button'); // Add a loading class for styling.

        // Use the nonce passed securely from PHP via wp_localize_script.
        const ajaxNonce = dsProStripe.nonce; // <--- UPDATED: Using the nonce from dsProStripe object.

        // Make an AJAX call to your WordPress backend to create a Stripe Checkout Session.
        $.ajax({
            url: dsProStripe.ajaxurl, // WordPress AJAX URL.
            type: 'POST',
            data: {
                action: 'ds_pro_create_stripe_checkout', // The AJAX action defined in PHP.
                product_id: productId,
                nonce: ajaxNonce // Pass the nonce for verification.
            },
            success: function(response) {
                if (response.success) {
                    // If the session is created successfully, redirect to Stripe Checkout.
                    stripe.redirectToCheckout({
                        sessionId: response.data.sessionId
                    }).then(function (result) {
                        if (result.error) {
                            // If `redirectToCheckout` fails due to a browser or network error, display the localized error message.
                            alert(result.error.message);
                            console.error('Stripe Checkout Error:', result.error.message);
                            // Re-enable button on error
                            resetButton($(e.target));
                        }
                    });
                } else {
                    // Handle server-side errors (e.g., API key missing, product data invalid).
                    alert('Error: ' + (response.data.message || 'Failed to create checkout session.'));
                    console.error('AJAX Error:', response.data.message);
                    // Re-enable button on error
                    resetButton($(e.target));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Handle AJAX communication errors.
                alert('Network error: Could not connect to the server.');
                console.error('AJAX Network Error:', textStatus, errorThrown);
                // Re-enable button on error
                resetButton($(e.target));
            }
        });
    });

    /**
     * Resets the button text and state after a loading operation.
     * @param {jQuery} $button The jQuery object of the button to reset.
     */
    function resetButton($button) {
        // Restore original button text (this is a simplified example,
        // in a real app you might store the original text).
        const originalText = $button.data('original-text') || 'Pay with Card'; // Fallback text
        $button.text(originalText).prop('disabled', false);
        $button.removeClass('ds-loading-button');
    }

    // Store original button text on load for resetting later.
    $('.ds-stripe-buy-button').each(function() {
        $(this).data('original-text', $(this).text());
    });
});
