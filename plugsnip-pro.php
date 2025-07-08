<?php
/**
 * Plugin Name:       PlugSnip Pro Suite
 * Plugin URI:        https://plugsnip.com/products/plugsnip-suite/
 * Description:       Unlocks all Pro features for Download Snip, Content Snip, and Event Snip.
 * Version:           1.0.0
 * Author:            PlugSnip.com
 * Author URI:        https://plugsnip.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plugsnip-pro
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define constants for the Pro plugin's version and directory path.
define( 'PLUGSNIP_PRO_VERSION', '1.0.0' );
define( 'PLUGSNIP_PRO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// --- Freemius SDK Inclusion ---
// IMPORTANT: You will need to download the Freemius SDK from your Freemius dashboard
// and place it in the 'freemius/' directory within your 'plugsnip-pro' plugin.
// The 'start.php' file is the main entry point for the Freemius SDK.
// Uncomment the line below once you have the Freemius SDK in place.
// require_once PLUGSNIP_PRO_PLUGIN_DIR . 'freemius/start.php';

// Declare a global variable to hold the Freemius instance.
// This allows you to access Freemius methods throughout your plugin.
global $plugsnip_pro_fs;

// Check if the Freemius initialization function already exists to prevent conflicts.
if ( ! function_exists( 'plugsnip_pro_fs' ) ) {
    /**
     * Initializes the Freemius SDK.
     * This function is crucial for setting up Freemius for your plugin.
     *
     * @return Freemius The Freemius SDK instance.
     */
    function plugsnip_pro_fs() {
        global $plugsnip_pro_fs;

        // If the Freemius instance hasn't been set up yet, initialize it.
        if ( ! isset( $plugsnip_pro_fs ) ) {
            // This is where you would include the actual Freemius SDK 'start.php' file.
            // Ensure the path is correct relative to this file.
            // require_once dirname(__FILE__) . '/freemius/start.php';

            // Initialize Freemius with your plugin's specific details.
            // You get 'id' and 'public_key' from your Freemius dashboard after registering your plugin.
            $plugsnip_pro_fs = fs_dynamic_init( array(
                'id'             => 'YOUR_FREEMIUS_PLUGIN_ID', // <--- IMPORTANT: Replace with your actual Freemius Plugin ID
                'slug'           => 'plugsnip-pro', // The slug of your Pro plugin directory.
                'type'           => 'plugin', // It's a plugin.
                'public_key'     => 'YOUR_FREEMIUS_PUBLIC_KEY', // <--- IMPORTANT: Replace with your actual Freemius Public Key
                'is_premium'     => true, // This tells Freemius it's a premium plugin.
                'has_addons'     => false, // Set to true if you plan to sell add-ons *for this Pro plugin*.
                'has_paid_plans' => true, // Yes, it has paid plans.
                'menu'           => array(
                    'slug'       => 'plugsnip-pro-settings', // This is the main slug for Freemius's own menu items.
                    'first-path' => 'admin.php?page=plugsnip-pro-settings', // The URL for the main Freemius settings page.
                    'account'    => true, // Show the Freemius account menu item (for license management).
                    'support'    => true, // Show the Freemius support menu item.
                ),
            ) );
        }

        return $plugsnip_pro_fs;
    }

    // Call the Freemius initialization function.
    plugsnip_pro_fs();
}

// --- Licensing Check using Freemius ---
/**
 * Checks if the PlugSnip Pro Suite is active and licensed.
 * This is the central function that your individual free Snip plugins will call.
 *
 * @return bool True if Pro is active and licensed, false otherwise.
 */
function plugsnip_is_pro_active() {
    $fs = plugsnip_pro_fs(); // Get the Freemius instance.
    // Use Freemius's built-in method to check if the user is on a paying plan.
    // `$fs->is_paying()` returns true if the user has any active paid plan.
    // You could also use `$fs->is_plan('your_premium_plan_id')` if you have multiple paid plans
    // and want to check for a specific one.
    return $fs->is_paying(); // This is the core license check.
}

// These functions act as "bridges" for your individual free Snip plugins.
// Each free plugin (Download Snip, Content Snip, Event Snip) will call its respective
// `is_xxx_pro_active()` function to determine if Pro features should be enabled.
// The `! function_exists()` check prevents errors if the Pro plugin is deactivated.
if ( ! function_exists( 'is_download_snip_pro_active' ) ) {
    function is_download_snip_pro_active() {
        return plugsnip_is_pro_active();
    }
}
if ( ! function_exists( 'is_content_snip_pro_active' ) ) {
    function is_content_snip_pro_active() {
        return plugsnip_is_pro_active();
    }
}
if ( ! function_exists( 'is_event_snip_pro_active' ) ) {
    function is_event_snip_pro_active() {
        return plugsnip_is_pro_active();
    }
}
// --- End Licensing Check ---


/**
 * Main PlugSnip_Pro class to manage all premium features.
 * This class orchestrates the loading of specific Pro features for each Snip plugin.
 */
class PlugSnip_Pro {

    private static $_instance = null; // Holds the single instance of the class (Singleton pattern).

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        // Hook into 'plugins_loaded' to ensure all free plugins are loaded before
        // we attempt to load their respective Pro features.
        add_action( 'plugins_loaded', [ $this, 'load_pro_features' ] );
        // Add a custom admin menu page for your own Pro dashboard/settings.
        // Freemius adds its own menu items, but you might want a central hub.
        add_action( 'admin_menu', [ $this, 'add_custom_admin_menu' ] );
    }

    /**
     * Conditionally loads Pro features for each active Snip plugin.
     */
    public function load_pro_features() {
        $fs = plugsnip_pro_fs(); // Get the Freemius instance.

        // Only load Pro features if the user has a valid, paying license.
        if ( $fs->is_paying() ) {
            // Check if Download Snip (free version) is active by checking its defined constant.
            // This assumes your free Download Snip plugin defines 'DOWNLOAD_SNIP_VERSION'.
            if ( defined( 'DOWNLOAD_SNIP_VERSION' ) ) {
                // If active and licensed, include the file containing Download Snip's Pro features.
                // You would create this file (e.g., 'includes/pro-download-snip-features.php')
                // and put all Download Snip's premium code there (e.g., Stripe integration, unlimited products logic).
                require_once PLUGSNIP_PRO_PLUGIN_DIR . 'includes/pro-download-snip-features.php';
                // If you have a class for these features, you would instantiate it here.
                // new PlugSnip_Pro_Download_Snip_Features();
            }

            // Similarly, check and load Pro features for Content Snip.
            // You would uncomment and implement this once you have the Content Snip free plugin.
            // if ( defined( 'CONTENT_SNIP_VERSION' ) ) {
            //     require_once PLUGSNIP_PRO_PLUGIN_DIR . 'includes/pro-content-snip-features.php';
            //     // new PlugSnip_Pro_Content_Snip_Features();
            // }

            // And for Event Snip.
            // if ( defined( 'EVENT_SNIP_VERSION' ) ) {
            //     require_once PLUGSNIP_PRO_PLUGIN_DIR . 'includes/pro-event-snip-features.php';
            //     // new PlugSnip_Pro_Event_Snip_Features();
            // }
        }
    }

    /**
     * Adds a custom top-level admin menu page for the PlugSnip Pro Suite.
     * This page can serve as a central dashboard for Pro users.
     */
    public function add_custom_admin_menu() {
        add_menu_page(
            esc_html__( 'PlugSnip Pro Dashboard', 'plugsnip-pro' ), // Page title
            esc_html__( 'PlugSnip Pro', 'plugsnip-pro' ), // Menu title
            'manage_options', // Capability required to access this menu item
            'plugsnip-pro-dashboard', // Unique slug for this menu page
            [ $this, 'render_pro_dashboard_page' ], // Callback function to render content
            'dashicons-star-filled', // Icon for the menu item (a star indicates premium)
            15 // Position in the admin menu (adjust as needed)
        );
    }

    /**
     * Renders the content of the PlugSnip Pro Suite Dashboard page.
     * This page provides an overview of the license status and active Pro features.
     */
    public function render_pro_dashboard_page() {
        $fs = plugsnip_pro_fs(); // Get the Freemius instance to check license status.
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'PlugSnip Pro Suite Dashboard', 'plugsnip-pro' ); ?></h1>
            <p><?php esc_html_e( 'Welcome to your PlugSnip Pro Suite! Here you can manage global Pro features and see your license status.', 'plugsnip-pro' ); ?></p>

            <?php if ( $fs->is_paying() ) : // Check if the user is currently on a paying plan via Freemius ?>
                <div class="notice notice-success inline"><p><strong><?php esc_html_e( 'License Status:', 'plugsnip-pro' ); ?></strong> <?php esc_html_e( 'Active and Valid!', 'plugsnip-pro' ); ?></p></div>
            <?php else : ?>
                <div class="notice notice-error inline"><p><strong><?php esc_html_e( 'License Status:', 'plugsnip-pro' ); ?></strong> <?php esc_html_e( 'Not active. Please activate your license to unlock all features.', 'plugsnip-pro' ); ?></p></div>
                <p><a href="<?php echo esc_url( $fs->get_upgrade_url() ); ?>" class="button button-primary"><?php esc_html_e( 'Upgrade / Activate License', 'plugsnip-pro' ); ?></a></p>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Active Pro Features:', 'plugsnip-pro' ); ?></h2>
            <ul>
                <?php if ( defined( 'DOWNLOAD_SNIP_VERSION' ) && $fs->is_paying() ) : ?>
                    <li><?php esc_html_e( 'Unlimited Products for Download Snip', 'plugsnip-pro' ); ?></li>
                    <li><?php esc_html_e( 'Stripe Integration for Download Snip', 'plugsnip-pro' ); ?> (Requires implementation in `pro-download-snip-features.php`)</li>
                    <!-- Add more Download Snip Pro features here as you implement them -->
                <?php endif; ?>
                <?php // if ( defined( 'CONTENT_SNIP_VERSION' ) && $fs->is_paying() ) : ?>
                    <!-- <li>Pro features for Content Snip...</li> -->
                <?php // endif; ?>
                <?php // if ( defined( 'EVENT_SNIP_VERSION' ) && $fs->is_paying() ) : ?>
                    <!-- <li>Pro features for Event Snip...</li> -->
                <?php // endif; ?>
                <?php if ( ! $fs->is_paying() ) : ?>
                    <li><em><?php esc_html_e( 'Upgrade to unlock all premium features!', 'plugsnip-pro' ); ?></em></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }
}

// Initialize the Pro plugin by getting its instance.
// This is hooked to 'plugins_loaded' with a default priority.
// It's important to run this after all other plugins have had a chance to load.
function plugsnip_pro_init() {
    return PlugSnip_Pro::instance();
}
add_action( 'plugins_loaded', 'plugsnip_pro_init' );

