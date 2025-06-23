<?php
/**
 * Membership handler for WooCommerce Memberships integration
 * 
 * @package WooMemberShare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles integration with WooCommerce Memberships
 */
class WMS_Membership_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize membership hooks
     */
    private function init_hooks() {
        // WooCommerce Memberships integration hooks
        add_action('wc_memberships_user_membership_status_changed', array($this, 'handle_membership_status_change'), 10, 3);
        add_action('wc_memberships_user_membership_saved', array($this, 'handle_membership_saved'), 10, 2);
        add_action('wc_memberships_user_membership_deleted', array($this, 'handle_membership_deleted'), 10, 1);
        add_action('wc_memberships_grant_membership_access_from_order', array($this, 'handle_membership_granted'), 10, 2);
        
        // Filter membership access for subaccounts
        add_filter('wc_memberships_is_user_active_member', array($this, 'check_subaccount_membership'), 10, 4);
        add_filter('wc_memberships_user_has_member_discount', array($this, 'check_subaccount_discount'), 10, 4);
        
        // Order completion hooks for membership creation
        add_action('woocommerce_order_status_completed', array($this, 'process_membership_order'), 20); // After frontend handler
        add_action('woocommerce_payment_complete', array($this, 'process_membership_order'), 20);
        
        // Membership content access hooks
        add_filter('wc_memberships_user_can_view_post', array($this, 'check_subaccount_content_access'), 10, 4);
        add_filter('wc_memberships_user_can_view_product', array($this, 'check_subaccount_product_access'), 10, 4);
        add_filter('wc_memberships_user_can_purchase_product', array($this, 'check_subaccount_purchase_access'), 10, 4);
        
        // Admin hooks for membership management
        add_action('wp_ajax_wms_sync_membership_status', array($this, 'ajax_sync_membership_status'));
        add_action('wp_ajax_wms_get_membership_stats', array($this, 'ajax_get_membership_stats'));
    }
    
    /**
     * Handle membership status changes
     * 
     * @param WC_Memberships_User_Membership $user_membership User membership object
     * @param string $old_status Previous status
     * @param string $new_status New status
     */
    public function handle_membership_status_change($user_membership, $old_status, $new_status) {
        $user_id = $user_membership->get_user_id();
        $plan_id = $user_membership->get_plan_id();
        
        // Get all groups for this customer
        $groups = WMS_Database::get_customer_groups($user_id);
        
        foreach ($groups as $group) {
            // Check if this group is associated with this membership
            if ($this->is_group_membership_match($group, $user_membership)) {
                $this->sync_group_status($group, $new_status);
            }
        }
        
        // Log the status change
        error_log("WMS: Membership status changed for user {$user_id}, plan {$plan_id}: {$old_status} -> {$new_status}");
    }
    
    /**
     * Handle membership saved (created or updated)
     * 
     * @param WC_Memberships_User_Membership $user_membership User membership object
     * @param array $args Additional arguments
     */
    public function handle_membership_saved($user_membership, $args) {
        $user_id = $user_membership->get_user_id();
        $plan_id = $user_membership->get_plan_id();
        
        // Update membership ID in groups if not set
        $groups = WMS_Database::get_customer_groups($user_id);
        
        foreach ($groups as $group) {
            if ($group->membership_id == 0 && $this->is_group_membership_match($group, $user_membership)) {
                WMS_Database::update_group($group->id, array(
                    'membership_id' => $user_membership->get_id()
                ));
            }
        }
    }
    
    /**
     * Handle membership deletion
     * 
     * @param WC_Memberships_User_Membership $user_membership User membership object
     */
    public function handle_membership_deleted($user_membership) {
        $user_id = $user_membership->get_user_id();
        
        // Get all groups for this customer
        $groups = WMS_Database::get_customer_groups($user_id);
        
        foreach ($groups as $group) {
            if ($group->membership_id == $user_membership->get_id()) {
                // Set group status to expired
                WMS_Database::update_group($group->id, array(
                    'status' => 'expired'
                ));
            }
        }
    }
    
    /**
     * Handle membership granted from order
     * 
     * @param WC_Memberships_User_Membership $user_membership User membership object
     * @param array $args Additional arguments
     */
    public function handle_membership_granted($user_membership, $args) {
        $this->handle_membership_saved($user_membership, $args);
    }
    
    /**
     * Check if user is active member (including subaccounts)
     * 
     * @param bool $is_active_member Current active status
     * @param int $user_id User ID
     * @param int|string $membership_plan Membership plan ID or slug
     * @param int $since Optional timestamp
     * @return bool Whether user has active membership
     */
    public function check_subaccount_membership($is_active_member, $user_id, $membership_plan, $since = null) {
        // If already active, return true
        if ($is_active_member) {
            return true;
        }
        
        // Check if user is a subaccount with access to this membership
        return $this->user_has_subaccount_access($user_id, $membership_plan);
    }
    
    /**
     * Check if user has member discount (including subaccounts)
     * 
     * @param bool $has_discount Current discount status
     * @param int $user_id User ID
     * @param int|string $membership_plan Membership plan ID or slug
     * @param int $product_id Product ID
     * @return bool Whether user has member discount
     */
    public function check_subaccount_discount($has_discount, $user_id, $membership_plan, $product_id) {
        // If already has discount, return true
        if ($has_discount) {
            return true;
        }
        
        // Check if user is a subaccount with access to this membership
        return $this->user_has_subaccount_access($user_id, $membership_plan);
    }
    
    /**
     * Check content access for subaccounts
     * 
     * @param bool $can_view Current view permission
     * @param int $user_id User ID
     * @param int $post_id Post ID
     * @param WC_Memberships_Membership_Plan $membership_plan Membership plan
     * @return bool Whether user can view content
     */
    public function check_subaccount_content_access($can_view, $user_id, $post_id, $membership_plan) {
        // If already can view, return true
        if ($can_view) {
            return true;
        }
        
        // Check if user is a subaccount with access to this membership
        return $this->user_has_subaccount_access($user_id, $membership_plan->get_id());
    }
    
    /**
     * Check product access for subaccounts
     * 
     * @param bool $can_view Current view permission
     * @param int $user_id User ID
     * @param int $product_id Product ID
     * @param WC_Memberships_Membership_Plan $membership_plan Membership plan
     * @return bool Whether user can view product
     */
    public function check_subaccount_product_access($can_view, $user_id, $product_id, $membership_plan) {
        // If already can view, return true
        if ($can_view) {
            return true;
        }
        
        // Check if user is a subaccount with access to this membership
        return $this->user_has_subaccount_access($user_id, $membership_plan->get_id());
    }
    
    /**
     * Check purchase access for subaccounts
     * 
     * @param bool $can_purchase Current purchase permission
     * @param int $user_id User ID
     * @param int $product_id Product ID
     * @param WC_Memberships_Membership_Plan $membership_plan Membership plan
     * @return bool Whether user can purchase product
     */
    public function check_subaccount_purchase_access($can_purchase, $user_id, $product_id, $membership_plan) {
        // If already can purchase, return true
        if ($can_purchase) {
            return true;
        }
        
        // Check if user is a subaccount with access to this membership
        return $this->user_has_subaccount_access($user_id, $membership_plan->get_id());
    }
    
    /**
     * Check if user has subaccount access to membership
     * 
     * @param int $user_id User ID
     * @param int|string $membership_plan Membership plan ID or slug
     * @return bool Whether user has subaccount access
     */
    private function user_has_subaccount_access($user_id, $membership_plan) {
        // Get user's groups where they are an active member
        $user_groups = WMS_Database::get_user_groups($user_id);
        
        foreach ($user_groups as $group) {
            // Check if group is active
            if ($group->status !== 'active') {
                continue;
            }
            
            // Get the customer's membership for this group
            $customer_membership = $this->get_customer_membership_for_group($group);
            
            if ($customer_membership && $this->membership_matches_plan($customer_membership, $membership_plan)) {
                // Check if customer's membership is active
                if ($customer_membership->is_active()) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get customer membership for a group
     * 
     * @param object $group Group object
     * @return WC_Memberships_User_Membership|null Customer membership or null
     */
    private function get_customer_membership_for_group($group) {
        if (!function_exists('wc_memberships_get_user_memberships')) {
            return null;
        }
        
        // Get customer's memberships
        $customer_memberships = wc_memberships_get_user_memberships($group->customer_user_id);
        
        // If we have a specific membership ID, try to find it
        if ($group->membership_id > 0) {
            foreach ($customer_memberships as $membership) {
                if ($membership->get_id() == $group->membership_id) {
                    return $membership;
                }
            }
        }
        
        // Try to match by product/variation
        foreach ($customer_memberships as $membership) {
            if ($this->is_group_membership_match($group, $membership)) {
                return $membership;
            }
        }
        
        return null;
    }
    
    /**
     * Check if membership matches plan
     * 
     * @param WC_Memberships_User_Membership $membership User membership
     * @param int|string $plan_identifier Plan ID or slug
     * @return bool Whether membership matches plan
     */
    private function membership_matches_plan($membership, $plan_identifier) {
        $plan = $membership->get_plan();
        
        if (is_numeric($plan_identifier)) {
            return $plan->get_id() == $plan_identifier;
        } else {
            return $plan->get_slug() === $plan_identifier;
        }
    }
    
    /**
     * Check if group matches membership
     * 
     * @param object $group Group object
     * @param WC_Memberships_User_Membership $membership User membership
     * @return bool Whether group matches membership
     */
    private function is_group_membership_match($group, $membership) {
        // Direct membership ID match
        if ($group->membership_id > 0 && $group->membership_id == $membership->get_id()) {
            return true;
        }
        
        // Try to match by product/variation
        $plan = $membership->get_plan();
        $plan_product_ids = $plan->get_product_ids();
        
        // Check if group's product/variation is in the plan
        if (in_array($group->product_id, $plan_product_ids) || in_array($group->variation_id, $plan_product_ids)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Sync group status with membership status
     * 
     * @param object $group Group object
     * @param string $membership_status Membership status
     */
    private function sync_group_status($group, $membership_status) {
        $group_status = 'active';
        
        switch ($membership_status) {
            case 'active':
                $group_status = 'active';
                break;
            case 'expired':
            case 'cancelled':
                $group_status = 'expired';
                break;
            case 'paused':
            case 'pending':
                $group_status = 'suspended';
                break;
            default:
                $group_status = 'suspended';
        }
        
        // Update group status if changed
        if ($group->status !== $group_status) {
            WMS_Database::update_group($group->id, array(
                'status' => $group_status
            ));
            
            error_log("WMS: Group {$group->id} status updated to {$group_status}");
        }
    }
    
    /**
     * Process membership order completion
     * 
     * @param int $order_id Order ID
     */
    public function process_membership_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return;
        }
        
        // Get customer's groups
        $groups = WMS_Database::get_customer_groups($customer_id);
        
        foreach ($groups as $group) {
            if ($group->membership_id == 0) {
                // Try to find and link the membership
                $this->link_group_to_membership($group, $order);
            }
        }
    }
    
    /**
     * Link group to membership
     * 
     * @param object $group Group object
     * @param WC_Order $order Order object
     */
    private function link_group_to_membership($group, $order) {
        if (!function_exists('wc_memberships_get_user_memberships')) {
            return;
        }
        
        $customer_memberships = wc_memberships_get_user_memberships($group->customer_user_id);
        
        foreach ($customer_memberships as $membership) {
            if ($this->is_group_membership_match($group, $membership)) {
                // Link the membership
                WMS_Database::update_group($group->id, array(
                    'membership_id' => $membership->get_id()
                ));
                
                error_log("WMS: Linked group {$group->id} to membership {$membership->get_id()}");
                break;
            }
        }
    }
    
    /**
     * AJAX handler for syncing membership status
     */
    public function ajax_sync_membership_status() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'wms_admin_nonce') || !current_user_can('manage_woocommerce')) {
            wp_die(__('Security check failed.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        $synced_count = 0;
        $groups = WMS_Database::get_all_groups();
        
        foreach ($groups as $group) {
            $customer_membership = $this->get_customer_membership_for_group($group);
            
            if ($customer_membership) {
                $this->sync_group_status($group, $customer_membership->get_status());
                $synced_count++;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Synced %d groups with membership status.', WOO_MEMBER_SHARE_TEXT_DOMAIN), $synced_count),
            'synced_count' => $synced_count
        ));
    }
    
    /**
     * AJAX handler for getting membership statistics
     */
    public function ajax_get_membership_stats() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'wms_admin_nonce') || !current_user_can('manage_woocommerce')) {
            wp_die(__('Security check failed.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        $stats = $this->get_membership_statistics();
        
        wp_send_json_success($stats);
    }
    
    /**
     * Get membership statistics
     * 
     * @return array Membership statistics
     */
    public function get_membership_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total groups
        $stats['total_groups'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}share_membership_groups"
        );
        
        // Active groups
        $stats['active_groups'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}share_membership_groups WHERE status = 'active'"
        );
        
        // Groups with linked memberships
        $stats['linked_groups'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}share_membership_groups WHERE membership_id > 0"
        );
        
        // Total subaccounts
        $stats['total_subaccounts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}share_membership_group_members WHERE member_type = 'subaccount'"
        );
        
        // Active subaccounts
        $stats['active_subaccounts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}share_membership_group_members WHERE member_type = 'subaccount' AND status = 'active'"
        );
        
        return $stats;
    }
    
    /**
     * Get membership access summary for user
     * 
     * @param int $user_id User ID
     * @return array Access summary
     */
    public function get_user_access_summary($user_id) {
        $summary = array(
            'direct_memberships' => array(),
            'shared_memberships' => array(),
            'total_access_count' => 0
        );
        
        if (!function_exists('wc_memberships_get_user_memberships')) {
            return $summary;
        }
        
        // Get direct memberships
        $direct_memberships = wc_memberships_get_user_memberships($user_id);
        foreach ($direct_memberships as $membership) {
            if ($membership->is_active()) {
                $summary['direct_memberships'][] = array(
                    'id' => $membership->get_id(),
                    'plan_name' => $membership->get_plan()->get_name(),
                    'status' => $membership->get_status(),
                    'type' => 'direct'
                );
            }
        }
        
        // Get shared memberships through groups
        $user_groups = WMS_Database::get_user_groups($user_id);
        foreach ($user_groups as $group) {
            $customer_membership = $this->get_customer_membership_for_group($group);
            if ($customer_membership && $customer_membership->is_active()) {
                $summary['shared_memberships'][] = array(
                    'group_id' => $group->id,
                    'group_name' => $group->group_name,
                    'plan_name' => $customer_membership->get_plan()->get_name(),
                    'status' => $customer_membership->get_status(),
                    'type' => 'shared'
                );
            }
        }
        
        $summary['total_access_count'] = count($summary['direct_memberships']) + count($summary['shared_memberships']);
        
        return $summary;
    }
}
