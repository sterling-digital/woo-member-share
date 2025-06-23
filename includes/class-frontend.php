<?php
/**
 * Frontend functionality handler
 * 
 * @package WooMemberShare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles frontend functionality for customers
 */
class WMS_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize frontend hooks
     */
    private function init_hooks() {
        // My Account integration
        add_filter('woocommerce_account_menu_items', array($this, 'add_account_menu_item'));
        add_action('woocommerce_account_member-groups_endpoint', array($this, 'groups_endpoint_content'));
        add_action('init', array($this, 'add_account_endpoint'));
        
        // Handle form submissions
        add_action('wp_loaded', array($this, 'handle_group_actions'));
        
        // Order completion hooks
        add_action('woocommerce_order_status_completed', array($this, 'process_order_completion'));
        add_action('woocommerce_payment_complete', array($this, 'process_order_completion'));
        
        // Frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    /**
     * Add groups endpoint to My Account
     */
    public function add_account_endpoint() {
        add_rewrite_endpoint('member-groups', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Add groups menu item to My Account
     */
    public function add_account_menu_item($items) {
        // Only show for customers who have sharing-enabled memberships
        if (!$this->customer_has_groups()) {
            return $items;
        }
        
        $group_label = WMS_Admin::get_label('group', true);
        
        // Insert before logout
        $logout = $items['customer-logout'];
        unset($items['customer-logout']);
        
        $items['member-groups'] = $group_label;
        $items['customer-logout'] = $logout;
        
        return $items;
    }
    
    /**
     * Check if customer has groups or sharing-enabled products
     */
    private function customer_has_groups() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        
        // Check if user has existing groups
        $groups = WMS_Database::get_customer_groups($user_id);
        if (!empty($groups)) {
            return true;
        }
        
        // Check if user has purchased sharing-enabled products
        $orders = wc_get_orders(array(
            'customer' => $user_id,
            'status' => array('completed', 'processing'),
            'limit' => -1,
        ));
        
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($this->is_sharing_enabled_product($product)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if product has sharing enabled
     */
    private function is_sharing_enabled_product($product) {
        if (!$product) {
            return false;
        }
        
        if ($product->is_type('variable')) {
            // Check variations
            $variations = $product->get_available_variations();
            foreach ($variations as $variation_data) {
                $variation = wc_get_product($variation_data['variation_id']);
                if ($variation && get_post_meta($variation->get_id(), '_variation_enable_sharing', true) === 'yes') {
                    return true;
                }
            }
        } else {
            // Simple product
            return get_post_meta($product->get_id(), '_enable_membership_sharing', true) === 'yes';
        }
        
        return false;
    }
    
    /**
     * Groups endpoint content
     */
    public function groups_endpoint_content() {
        if (!is_user_logged_in()) {
            echo '<p>' . esc_html__('Please log in to manage your groups.', WOO_MEMBER_SHARE_TEXT_DOMAIN) . '</p>';
            return;
        }
        
        $user_id = get_current_user_id();
        $groups = WMS_Database::get_customer_groups($user_id);
        
        include WOO_MEMBER_SHARE_PLUGIN_DIR . 'frontend/templates/my-account-groups.php';
    }
    
    /**
     * Handle group management form submissions
     */
    public function handle_group_actions() {
        if (!isset($_POST['wms_action']) || !wp_verify_nonce($_POST['wms_nonce'], 'wms_group_action')) {
            return;
        }
        
        if (!is_user_logged_in()) {
            return;
        }
        
        $action = sanitize_text_field($_POST['wms_action']);
        $user_id = get_current_user_id();
        
        switch ($action) {
            case 'invite_member':
                $this->handle_invite_member($user_id);
                break;
                
            case 'remove_member':
                $this->handle_remove_member($user_id);
                break;
                
            case 'rename_group':
                $this->handle_rename_group($user_id);
                break;
                
            case 'join_group':
                $this->handle_join_group($user_id);
                break;
                
            case 'leave_group':
                $this->handle_leave_group($user_id);
                break;
        }
        
        // Redirect to prevent form resubmission
        wp_safe_redirect(wc_get_account_endpoint_url('member-groups'));
        exit;
    }
    
    /**
     * Handle member invitation
     */
    private function handle_invite_member($user_id) {
        if (!isset($_POST['group_id']) || !isset($_POST['invite_email'])) {
            wc_add_notice(__('Invalid invitation data.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        $group_id = intval($_POST['group_id']);
        $email = sanitize_email($_POST['invite_email']);
        
        // Verify group ownership
        $group = WMS_Database::get_group_by_id($group_id);
        if (!$group || $group->customer_user_id != $user_id) {
            wc_add_notice(__('You do not have permission to manage this group.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        // Validate email
        if (!is_email($email)) {
            wc_add_notice(__('Please enter a valid email address.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        // Check if email is already invited or is the customer's email
        $customer = get_user_by('id', $user_id);
        if ($customer && $customer->user_email === $email) {
            wc_add_notice(__('You cannot invite yourself.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        // Check current member count
        $current_members = WMS_Database::get_group_members($group_id);
        if (count($current_members) >= $group->max_subaccounts) {
            wc_add_notice(__('This group has reached its member limit.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        // Check if email already exists in group
        foreach ($current_members as $member) {
            if ($member->email === $email) {
                wc_add_notice(__('This email is already invited to the group.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
                return;
            }
        }
        
        // Create invitation
        $invitation_id = WMS_Database::create_invitation(array(
            'group_id' => $group_id,
            'email' => $email,
        ));
        
        if ($invitation_id) {
            // Add member to group as pending
            WMS_Database::add_group_member(array(
                'group_id' => $group_id,
                'email' => $email,
                'member_type' => 'subaccount',
                'status' => 'pending',
            ));
            
            // Send invitation email
            $invitation = WMS_Database::get_invitation_by_token(
                WMS_Database::get_invitation_by_id($invitation_id)->invitation_token
            );
            
            if ($invitation) {
                $group_data = array(
                    'customer_user_id' => $group->customer_user_id,
                    'group_name' => $group->group_name,
                );
                
                // Initialize email handler and send email
                $email_handler = new WMS_Email_Handler();
                $email_sent = $email_handler->send_invitation_email($email, $invitation->invitation_token, $group_data);
                
                if ($email_sent) {
                    wc_add_notice(__('Invitation sent successfully.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'success');
                } else {
                    wc_add_notice(__('Invitation created but email failed to send. Please try again.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'warning');
                }
            } else {
                wc_add_notice(__('Invitation sent successfully.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'success');
            }
        } else {
            wc_add_notice(__('Failed to send invitation. Please try again.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
        }
    }
    
    /**
     * Handle member removal
     */
    private function handle_remove_member($user_id) {
        if (!isset($_POST['member_id'])) {
            wc_add_notice(__('Invalid member ID.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        $member_id = intval($_POST['member_id']);
        
        // Get member details and verify group ownership
        global $wpdb;
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT gm.*, g.customer_user_id 
             FROM {$wpdb->prefix}share_membership_group_members gm 
             JOIN {$wpdb->prefix}share_membership_groups g ON gm.group_id = g.id 
             WHERE gm.id = %d",
            $member_id
        ));
        
        if (!$member || $member->customer_user_id != $user_id) {
            wc_add_notice(__('You do not have permission to remove this member.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        if (WMS_Database::remove_group_member($member_id)) {
            wc_add_notice(__('Member removed successfully.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'success');
        } else {
            wc_add_notice(__('Failed to remove member. Please try again.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
        }
    }
    
    /**
     * Handle group renaming
     */
    private function handle_rename_group($user_id) {
        if (!isset($_POST['group_id']) || !isset($_POST['group_name'])) {
            wc_add_notice(__('Invalid group data.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        $group_id = intval($_POST['group_id']);
        $group_name = sanitize_text_field($_POST['group_name']);
        
        if (empty($group_name)) {
            wc_add_notice(__('Group name cannot be empty.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        // Verify group ownership
        $group = WMS_Database::get_group_by_id($group_id);
        if (!$group || $group->customer_user_id != $user_id) {
            wc_add_notice(__('You do not have permission to rename this group.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        if (WMS_Database::update_group($group_id, array('group_name' => $group_name))) {
            wc_add_notice(__('Group renamed successfully.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'success');
        } else {
            wc_add_notice(__('Failed to rename group. Please try again.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
        }
    }
    
    /**
     * Handle customer joining their own group
     */
    private function handle_join_group($user_id) {
        if (!isset($_POST['group_id'])) {
            wc_add_notice(__('Invalid group ID.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        $group_id = intval($_POST['group_id']);
        
        // Verify group ownership
        $group = WMS_Database::get_group_by_id($group_id);
        if (!$group || $group->customer_user_id != $user_id) {
            wc_add_notice(__('You do not have permission to join this group.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        // Check if already a member
        $members = WMS_Database::get_group_members($group_id);
        foreach ($members as $member) {
            if ($member->user_id == $user_id) {
                wc_add_notice(__('You are already a member of this group.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
                return;
            }
        }
        
        // Add customer as active member
        $customer = get_user_by('id', $user_id);
        if ($customer) {
            $member_id = WMS_Database::add_group_member(array(
                'group_id' => $group_id,
                'user_id' => $user_id,
                'email' => $customer->user_email,
                'member_type' => 'customer',
                'status' => 'active',
                'joined_date' => current_time('mysql'),
            ));
            
            if ($member_id) {
                wc_add_notice(__('You have successfully joined the group.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'success');
            } else {
                wc_add_notice(__('Failed to join group. Please try again.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            }
        }
    }
    
    /**
     * Handle customer leaving their own group
     */
    private function handle_leave_group($user_id) {
        if (!isset($_POST['group_id'])) {
            wc_add_notice(__('Invalid group ID.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        $group_id = intval($_POST['group_id']);
        
        // Find customer's membership in the group
        $members = WMS_Database::get_group_members($group_id);
        $member_id = null;
        
        foreach ($members as $member) {
            if ($member->user_id == $user_id) {
                $member_id = $member->id;
                break;
            }
        }
        
        if (!$member_id) {
            wc_add_notice(__('You are not a member of this group.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
            return;
        }
        
        if (WMS_Database::remove_group_member($member_id)) {
            wc_add_notice(__('You have successfully left the group.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'success');
        } else {
            wc_add_notice(__('Failed to leave group. Please try again.', WOO_MEMBER_SHARE_TEXT_DOMAIN), 'error');
        }
    }
    
    /**
     * Process order completion to create groups
     */
    public function process_order_completion($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return;
        }
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            
            if ($product->is_type('variation')) {
                $this->create_group_for_variation($customer_id, $product, $item);
            } elseif ($this->is_sharing_enabled_product($product)) {
                $this->create_group_for_simple_product($customer_id, $product, $item);
            }
        }
    }
    
    /**
     * Create group for variation
     */
    private function create_group_for_variation($customer_id, $variation, $item) {
        $variation_id = $variation->get_id();
        $parent_id = $variation->get_parent_id();
        
        if (get_post_meta($variation_id, '_variation_enable_sharing', true) !== 'yes') {
            return;
        }
        
        // Check if group already exists
        $existing_group = WMS_Database::get_group_by_customer_variation($customer_id, $variation_id);
        if ($existing_group) {
            return;
        }
        
        // Get settings
        $limit_type = get_post_meta($variation_id, '_variation_subaccount_limit_type', true);
        $fixed_limit = get_post_meta($variation_id, '_variation_subaccount_limit', true);
        
        // Calculate max subaccounts
        $max_subaccounts = 0;
        if ($limit_type === 'quantity_based') {
            $max_subaccounts = $item->get_quantity();
        } elseif ($limit_type === 'fixed') {
            $max_subaccounts = intval($fixed_limit);
        }
        
        // Create group
        $group_name = $variation->get_name() . ' ' . WMS_Admin::get_label('group');
        
        $group_id = WMS_Database::create_group(array(
            'customer_user_id' => $customer_id,
            'product_id' => $parent_id,
            'variation_id' => $variation_id,
            'membership_id' => 0, // TODO: Get actual membership ID in Phase 4
            'group_name' => $group_name,
            'max_subaccounts' => $max_subaccounts,
        ));
        
        // Auto-add customer to group
        if ($group_id) {
            $customer = get_user_by('id', $customer_id);
            if ($customer) {
                WMS_Database::add_group_member(array(
                    'group_id' => $group_id,
                    'user_id' => $customer_id,
                    'email' => $customer->user_email,
                    'member_type' => 'customer',
                    'status' => 'active',
                    'joined_date' => current_time('mysql'),
                ));
            }
        }
    }
    
    /**
     * Create group for simple product
     */
    private function create_group_for_simple_product($customer_id, $product, $item) {
        $product_id = $product->get_id();
        
        // Use product ID as variation ID for simple products
        $existing_group = WMS_Database::get_group_by_customer_variation($customer_id, $product_id);
        if ($existing_group) {
            return;
        }
        
        // Get settings
        $limit_type = get_post_meta($product_id, '_subaccount_limit_type', true);
        $fixed_limit = get_post_meta($product_id, '_subaccount_limit', true);
        
        // Calculate max subaccounts
        $max_subaccounts = 0;
        if ($limit_type === 'quantity_based') {
            $max_subaccounts = $item->get_quantity();
        } elseif ($limit_type === 'fixed') {
            $max_subaccounts = intval($fixed_limit);
        }
        
        // Create group
        $group_name = $product->get_name() . ' ' . WMS_Admin::get_label('group');
        
        $group_id = WMS_Database::create_group(array(
            'customer_user_id' => $customer_id,
            'product_id' => $product_id,
            'variation_id' => $product_id, // Use product ID for simple products
            'membership_id' => 0, // TODO: Get actual membership ID in Phase 4
            'group_name' => $group_name,
            'max_subaccounts' => $max_subaccounts,
        ));
        
        // Auto-add customer to group
        if ($group_id) {
            $customer = get_user_by('id', $customer_id);
            if ($customer) {
                WMS_Database::add_group_member(array(
                    'group_id' => $group_id,
                    'user_id' => $customer_id,
                    'email' => $customer->user_email,
                    'member_type' => 'customer',
                    'status' => 'active',
                    'joined_date' => current_time('mysql'),
                ));
            }
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_account_page()) {
            wp_enqueue_style(
                'woo-member-share-frontend',
                WOO_MEMBER_SHARE_PLUGIN_URL . 'frontend/assets/frontend.css',
                array(),
                WOO_MEMBER_SHARE_VERSION
            );
            
            wp_enqueue_script(
                'woo-member-share-frontend',
                WOO_MEMBER_SHARE_PLUGIN_URL . 'frontend/assets/frontend.js',
                array('jquery'),
                WOO_MEMBER_SHARE_VERSION,
                true
            );
            
            wp_localize_script('woo-member-share-frontend', 'wms_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wms_frontend_nonce'),
                'labels' => array(
                    'group_singular' => WMS_Admin::get_label('group'),
                    'group_plural' => WMS_Admin::get_label('group', true),
                    'member_singular' => WMS_Admin::get_label('subaccount'),
                    'member_plural' => WMS_Admin::get_label('subaccount', true),
                ),
            ));
        }
    }
}
