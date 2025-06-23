<?php
/**
 * Admin functionality handler
 * 
 * @package WooMemberShare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles admin functionality for the plugin
 */
class WMS_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Product data panels
        add_filter('woocommerce_product_data_tabs', array($this, 'add_product_data_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_product_data_panel'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_data'));
        
        // Variation settings
        add_action('woocommerce_product_after_variable_attributes', array($this, 'add_variation_settings'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_settings'), 10, 2);
        
        // Admin styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Settings initialization
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Member Share Settings', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            __('Member Share', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'manage_woocommerce',
            'woo-member-share-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'woocommerce',
            __('Group Management', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            __('Group Management', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'manage_woocommerce',
            'woo-member-share-groups',
            array($this, 'groups_page')
        );
    }
    
    /**
     * Add product data tab
     */
    public function add_product_data_tab($tabs) {
        $tabs['member_share'] = array(
            'label'  => __('Member Share', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'target' => 'member_share_product_data',
            'class'  => array('show_if_membership'),
        );
        return $tabs;
    }
    
    /**
     * Add product data panel
     */
    public function add_product_data_panel() {
        ?>
        <div id="member_share_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                woocommerce_wp_checkbox(array(
                    'id'          => '_enable_membership_sharing',
                    'label'       => __('Enable membership sharing', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                    'description' => __('Allow customers to share this membership with additional users.', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                ));
                
                woocommerce_wp_radio(array(
                    'id'          => '_subaccount_limit_type',
                    'label'       => __('Subaccount allocation', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                    'options'     => array(
                        'fixed'         => __('Fixed limit', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                        'quantity_based' => __('Quantity-based', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                    ),
                    'default'     => 'fixed',
                    'description' => __('Choose how subaccount slots are allocated.', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                ));
                
                woocommerce_wp_text_input(array(
                    'id'          => '_subaccount_limit',
                    'label'       => __('Subaccount limit', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                    'description' => __('Number of subaccounts allowed (for fixed allocation only).', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                    'type'        => 'number',
                    'custom_attributes' => array(
                        'min' => '0',
                        'max' => '100',
                    ),
                ));
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save product data
     */
    public function save_product_data($post_id) {
        if (!current_user_can('edit_product', $post_id)) {
            return;
        }
        
        // Enable sharing
        $enable_sharing = isset($_POST['_enable_membership_sharing']) ? 'yes' : 'no';
        update_post_meta($post_id, '_enable_membership_sharing', $enable_sharing);
        
        // Allocation type
        if (isset($_POST['_subaccount_limit_type'])) {
            update_post_meta($post_id, '_subaccount_limit_type', sanitize_text_field($_POST['_subaccount_limit_type']));
        }
        
        // Subaccount limit
        if (isset($_POST['_subaccount_limit'])) {
            $limit = intval($_POST['_subaccount_limit']);
            update_post_meta($post_id, '_subaccount_limit', max(0, $limit));
        }
    }
    
    /**
     * Add variation settings
     */
    public function add_variation_settings($loop, $variation_data, $variation) {
        ?>
        <div class="woo-member-share-variation-settings">
            <h4><?php esc_html_e('Member Share Settings', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h4>
            
            <?php
            woocommerce_wp_checkbox(array(
                'id'            => '_variation_enable_sharing[' . $variation->ID . ']',
                'name'          => '_variation_enable_sharing[' . $variation->ID . ']',
                'label'         => __('Enable sharing for this variation', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                'description'   => __('Allow customers to share this membership variation.', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                'value'         => get_post_meta($variation->ID, '_variation_enable_sharing', true),
            ));
            ?>
            
            <p class="form-field">
                <label for="_variation_subaccount_limit_type_<?php echo $variation->ID; ?>">
                    <?php esc_html_e('Subaccount allocation', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                </label>
                <select name="_variation_subaccount_limit_type[<?php echo $variation->ID; ?>]" 
                        id="_variation_subaccount_limit_type_<?php echo $variation->ID; ?>">
                    <?php
                    $current_type = get_post_meta($variation->ID, '_variation_subaccount_limit_type', true);
                    $options = array(
                        'fixed'         => __('Fixed limit', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                        'quantity_based' => __('Quantity-based', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                    );
                    
                    foreach ($options as $value => $label) {
                        echo '<option value="' . esc_attr($value) . '"' . selected($current_type, $value, false) . '>';
                        echo esc_html($label);
                        echo '</option>';
                    }
                    ?>
                </select>
            </p>
            
            <?php
            woocommerce_wp_text_input(array(
                'id'            => '_variation_subaccount_limit[' . $variation->ID . ']',
                'name'          => '_variation_subaccount_limit[' . $variation->ID . ']',
                'label'         => __('Subaccount limit', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                'description'   => __('Number of subaccounts allowed (for fixed allocation only).', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                'type'          => 'number',
                'value'         => get_post_meta($variation->ID, '_variation_subaccount_limit', true),
                'custom_attributes' => array(
                    'min' => '0',
                    'max' => '100',
                ),
            ));
            ?>
        </div>
        <?php
    }
    
    /**
     * Save variation settings
     */
    public function save_variation_settings($variation_id, $i) {
        if (!current_user_can('edit_product', $variation_id)) {
            return;
        }
        
        // Enable sharing
        $enable_sharing = isset($_POST['_variation_enable_sharing'][$variation_id]) ? 'yes' : 'no';
        update_post_meta($variation_id, '_variation_enable_sharing', $enable_sharing);
        
        // Allocation type
        if (isset($_POST['_variation_subaccount_limit_type'][$variation_id])) {
            $type = sanitize_text_field($_POST['_variation_subaccount_limit_type'][$variation_id]);
            update_post_meta($variation_id, '_variation_subaccount_limit_type', $type);
        }
        
        // Subaccount limit
        if (isset($_POST['_variation_subaccount_limit'][$variation_id])) {
            $limit = intval($_POST['_variation_subaccount_limit'][$variation_id]);
            update_post_meta($variation_id, '_variation_subaccount_limit', max(0, $limit));
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages and product edit pages
        if (strpos($hook, 'woo-member-share') !== false || $hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_style(
                'woo-member-share-admin',
                WOO_MEMBER_SHARE_PLUGIN_URL . 'admin/assets/admin.css',
                array(),
                WOO_MEMBER_SHARE_VERSION
            );
            
            wp_enqueue_script(
                'woo-member-share-admin',
                WOO_MEMBER_SHARE_PLUGIN_URL . 'admin/assets/admin.js',
                array('jquery'),
                WOO_MEMBER_SHARE_VERSION,
                true
            );
        }
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('woo_member_share_settings', 'membership_sharing_group_label_singular');
        register_setting('woo_member_share_settings', 'membership_sharing_group_label_plural');
        register_setting('woo_member_share_settings', 'membership_sharing_subaccount_label_singular');
        register_setting('woo_member_share_settings', 'membership_sharing_subaccount_label_plural');
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['woo_member_share_nonce'], 'woo_member_share_settings')) {
            $this->save_settings();
        }
        
        include WOO_MEMBER_SHARE_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
    
    /**
     * Groups management page
     */
    public function groups_page() {
        $groups = WMS_Database::get_all_groups();
        include WOO_MEMBER_SHARE_PLUGIN_DIR . 'admin/views/groups-page.php';
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        $settings = array(
            'membership_sharing_group_label_singular',
            'membership_sharing_group_label_plural',
            'membership_sharing_subaccount_label_singular',
            'membership_sharing_subaccount_label_plural',
        );
        
        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                update_option($setting, sanitize_text_field($_POST[$setting]));
            }
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>';
            esc_html_e('Settings saved successfully.', WOO_MEMBER_SHARE_TEXT_DOMAIN);
            echo '</p></div>';
        });
    }
    
    /**
     * Get label setting
     */
    public static function get_label($type, $plural = false) {
        $suffix = $plural ? '_plural' : '_singular';
        $option_name = 'membership_sharing_' . $type . '_label' . $suffix;
        
        $defaults = array(
            'group_singular' => __('Group', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'group_plural' => __('Groups', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'subaccount_singular' => __('Member', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'subaccount_plural' => __('Members', WOO_MEMBER_SHARE_TEXT_DOMAIN),
        );
        
        $default_key = $type . ($plural ? '_plural' : '_singular');
        $default = isset($defaults[$default_key]) ? $defaults[$default_key] : ucfirst($type);
        
        return get_option($option_name, $default);
    }
}
