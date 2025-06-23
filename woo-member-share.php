<?php
/**
 * Plugin Name: Woo Member Share
 * Plugin URI: https://sterlingdigital.com
 * Description: Extend WooCommerce Memberships to allow customers to share their membership access with additional users through a group-based system.
 * Version: 1.0.0
 * Author: Mike Sterling
 * Author URI: https://sterlingdigital.com
 * Text Domain: woo-member-share
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WOO_MEMBER_SHARE_VERSION', '1.0.0');
define('WOO_MEMBER_SHARE_PLUGIN_FILE', __FILE__);
define('WOO_MEMBER_SHARE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_MEMBER_SHARE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_MEMBER_SHARE_TEXT_DOMAIN', 'woo-member-share');

/**
 * Main plugin class
 */
final class Woo_Member_Share {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Database manager instance
     */
    public $database = null;
    
    /**
     * Admin manager instance
     */
    public $admin = null;
    
    /**
     * Frontend manager instance
     */
    public $frontend = null;
    
    /**
     * Email handler instance
     */
    public $email_handler = null;
    
    /**
     * Invitation handler instance
     */
    public $invitation_handler = null;
    
    /**
     * Membership handler instance
     */
    public $membership_handler = null;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->includes();
        $this->init();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'plugins_loaded'));
        add_action('admin_notices', array($this, 'dependency_notices'));
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once WOO_MEMBER_SHARE_PLUGIN_DIR . 'includes/class-activation.php';
        require_once WOO_MEMBER_SHARE_PLUGIN_DIR . 'includes/class-database.php';
        require_once WOO_MEMBER_SHARE_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WOO_MEMBER_SHARE_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once WOO_MEMBER_SHARE_PLUGIN_DIR . 'includes/class-email-handler.php';
        require_once WOO_MEMBER_SHARE_PLUGIN_DIR . 'includes/class-invitation-handler.php';
        require_once WOO_MEMBER_SHARE_PLUGIN_DIR . 'includes/class-membership-handler.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init() {
        // Always initialize database
        $this->database = new WMS_Database();
        
        // Initialize email and invitation handlers
        $this->email_handler = new WMS_Email_Handler();
        $this->invitation_handler = new WMS_Invitation_Handler();
        
        // Initialize admin and frontend if at least WooCommerce is available
        if (class_exists('WooCommerce')) {
            $this->admin = new WMS_Admin();
            $this->frontend = new WMS_Frontend();
            
            // Initialize membership handler if WooCommerce Memberships is available
            if (function_exists('wc_memberships') || class_exists('WC_Memberships') || class_exists('WC_Memberships_Loader')) {
                $this->membership_handler = new WMS_Membership_Handler();
            }
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $activation = new WMS_Activation();
        $activation->activate();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        $activation = new WMS_Activation();
        $activation->deactivate();
    }
    
    /**
     * Plugins loaded hook
     */
    public function plugins_loaded() {
        // Load text domain
        load_plugin_textdomain(
            WOO_MEMBER_SHARE_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
        
        // Re-initialize admin features if WooCommerce becomes available
        if (class_exists('WooCommerce') && !$this->admin) {
            $this->admin = new WMS_Admin();
        }
    }
    
    /**
     * Check if required dependencies are active
     */
    public function check_dependencies() {
        $woocommerce_active = class_exists('WooCommerce');
        $memberships_active = function_exists('wc_memberships') || class_exists('WC_Memberships') || class_exists('WC_Memberships_Loader');
        
        return $woocommerce_active && $memberships_active;
    }
    
    /**
     * Show dependency notices
     */
    public function dependency_notices() {
        $woocommerce_active = class_exists('WooCommerce');
        $memberships_active = function_exists('wc_memberships') || class_exists('WC_Memberships') || class_exists('WC_Memberships_Loader');
        
        if (!$woocommerce_active && !$memberships_active) {
            $message = __('Woo Member Share requires both WooCommerce and WooCommerce Memberships to be installed and activated.', WOO_MEMBER_SHARE_TEXT_DOMAIN);
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        } elseif (!$woocommerce_active) {
            $message = __('Woo Member Share requires WooCommerce to be installed and activated.', WOO_MEMBER_SHARE_TEXT_DOMAIN);
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        } elseif (!$memberships_active) {
            $message = __('Woo Member Share requires WooCommerce Memberships to be installed and activated.', WOO_MEMBER_SHARE_TEXT_DOMAIN);
            echo '<div class="notice notice-warning"><p>' . esc_html($message) . ' <strong>Note:</strong> Some features will work without it for development purposes.</p></div>';
        }
    }
    
    /**
     * Deactivate plugin if dependencies not met
     */
    public function deactivate_plugin() {
        deactivate_plugins(plugin_basename(__FILE__));
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

/**
 * Initialize the plugin
 */
function woo_member_share() {
    return Woo_Member_Share::instance();
}

// Start the plugin
woo_member_share();
