<?php
/**
 * Database operations handler
 * 
 * @package WooMemberShare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles all database operations for the plugin
 */
class WMS_Database {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor intentionally left empty
        // Database operations are static methods
    }
    
    /**
     * Get group by customer and variation
     * 
     * @param int $customer_user_id Customer user ID
     * @param int $variation_id Variation ID
     * @return object|null Group object or null if not found
     */
    public static function get_group_by_customer_variation($customer_user_id, $variation_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_groups';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE customer_user_id = %d AND variation_id = %d",
            $customer_user_id,
            $variation_id
        );
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Get all groups for a customer
     * 
     * @param int $customer_user_id Customer user ID
     * @return array Array of group objects
     */
    public static function get_customer_groups($customer_user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_groups';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE customer_user_id = %d ORDER BY created_date DESC",
            $customer_user_id
        );
        
        return $wpdb->get_results($sql);
    }

    /**
     * Get group by ID
     *
     * @param int $group_id Group ID
     * @return object|null Group object or null if not found
     */
    public static function get_group_by_id($group_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'share_membership_groups';

        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $group_id
        );

        return $wpdb->get_row($sql);
    }
    
    /**
     * Create a new group
     * 
     * @param array $data Group data
     * @return int|false Group ID on success, false on failure
     */
    public static function create_group($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_groups';
        
        $defaults = array(
            'customer_user_id' => 0,
            'product_id' => 0,
            'variation_id' => 0,
            'membership_id' => 0,
            'group_name' => '',
            'max_subaccounts' => 0,
            'current_subaccounts' => 0,
            'created_date' => current_time('mysql'),
            'status' => 'active'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['customer_user_id']) || empty($data['variation_id']) || empty($data['group_name'])) {
            return false;
        }
        
        $result = $wpdb->insert(
            $table_name,
            $data,
            array(
                '%d', // customer_user_id
                '%d', // product_id
                '%d', // variation_id
                '%d', // membership_id
                '%s', // group_name
                '%d', // max_subaccounts
                '%d', // current_subaccounts
                '%s', // created_date
                '%s'  // status
            )
        );
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update group
     * 
     * @param int $group_id Group ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public static function update_group($group_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_groups';
        
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $group_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete group
     * 
     * @param int $group_id Group ID
     * @return bool True on success, false on failure
     */
    public static function delete_group($group_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_groups';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $group_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get group members
     * 
     * @param int $group_id Group ID
     * @param string $status Member status filter (optional)
     * @return array Array of member objects
     */
    public static function get_group_members($group_id, $status = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_group_members';
        
        $sql = "SELECT * FROM $table_name WHERE group_id = %d";
        $params = array($group_id);
        
        if (!empty($status)) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY invited_date DESC";
        
        $prepared_sql = $wpdb->prepare($sql, $params);
        
        return $wpdb->get_results($prepared_sql);
    }
    
    /**
     * Add member to group
     * 
     * @param array $data Member data
     * @return int|false Member ID on success, false on failure
     */
    public static function add_group_member($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_group_members';
        
        $defaults = array(
            'group_id' => 0,
            'user_id' => null,
            'email' => '',
            'member_type' => 'subaccount',
            'status' => 'pending',
            'joined_date' => null,
            'invited_date' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['group_id']) || empty($data['email'])) {
            return false;
        }
        
        $result = $wpdb->insert(
            $table_name,
            $data,
            array(
                '%d', // group_id
                '%d', // user_id
                '%s', // email
                '%s', // member_type
                '%s', // status
                '%s', // joined_date
                '%s'  // invited_date
            )
        );
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update group member
     * 
     * @param int $member_id Member ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public static function update_group_member($member_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_group_members';
        
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $member_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Remove member from group
     * 
     * @param int $member_id Member ID
     * @return bool True on success, false on failure
     */
    public static function remove_group_member($member_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_group_members';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $member_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Create invitation
     * 
     * @param array $data Invitation data
     * @return int|false Invitation ID on success, false on failure
     */
    public static function create_invitation($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_invitations';
        
        $defaults = array(
            'group_id' => 0,
            'email' => '',
            'invitation_token' => '',
            'status' => 'pending',
            'sent_date' => current_time('mysql'),
            'expires_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'accepted_date' => null
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Generate secure token if not provided
        if (empty($data['invitation_token'])) {
            $data['invitation_token'] = self::generate_invitation_token();
        }
        
        // Validate required fields
        if (empty($data['group_id']) || empty($data['email'])) {
            return false;
        }
        
        $result = $wpdb->insert(
            $table_name,
            $data,
            array(
                '%d', // group_id
                '%s', // email
                '%s', // invitation_token
                '%s', // status
                '%s', // sent_date
                '%s', // expires_date
                '%s'  // accepted_date
            )
        );
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get invitation by token
     * 
     * @param string $token Invitation token
     * @return object|null Invitation object or null if not found
     */
    public static function get_invitation_by_token($token) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_invitations';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE invitation_token = %s",
            $token
        );
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Get invitation by ID
     * 
     * @param int $invitation_id Invitation ID
     * @return object|null Invitation object or null if not found
     */
    public static function get_invitation_by_id($invitation_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_invitations';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $invitation_id
        );
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Update invitation
     * 
     * @param int $invitation_id Invitation ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public static function update_invitation($invitation_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_invitations';
        
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $invitation_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Generate secure invitation token
     * 
     * @return string 64-character alphanumeric token
     */
    private static function generate_invitation_token() {
        global $wpdb;
        
        $max_attempts = 3;
        $attempts = 0;
        
        do {
            $token = wp_generate_password(64, false, false);
            
            // Check for collisions
            $table_name = $wpdb->prefix . 'share_membership_invitations';
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE invitation_token = %s",
                $token
            ));
            
            if ($existing == 0) {
                return $token;
            }
            
            $attempts++;
        } while ($attempts < $max_attempts);
        
        // Fallback: use timestamp + random string
        return wp_generate_password(32, false, false) . time();
    }
    
    /**
     * Cleanup expired invitations
     * 
     * @return int Number of cleaned up invitations
     */
    public static function cleanup_expired_invitations() {
        global $wpdb;
        
        // Update expired invitations
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}share_membership_invitations 
             SET status = 'expired' 
             WHERE status = 'pending' AND expires_date < %s",
            current_time('mysql')
        ));
        
        return $updated;
    }
    
    /**
     * Get user groups (groups where user is a member)
     * 
     * @param int $user_id User ID
     * @return array Array of group objects
     */
    public static function get_user_groups($user_id) {
        global $wpdb;
        
        $groups_table = $wpdb->prefix . 'share_membership_groups';
        $members_table = $wpdb->prefix . 'share_membership_group_members';
        
        $sql = $wpdb->prepare(
            "SELECT g.* FROM $groups_table g 
             INNER JOIN $members_table m ON g.id = m.group_id 
             WHERE m.user_id = %d AND m.status = 'active'
             ORDER BY g.created_date DESC",
            $user_id
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get all groups (for admin management)
     * 
     * @param string $status Status filter (optional)
     * @return array Array of group objects
     */
    public static function get_all_groups($status = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'share_membership_groups';
        
        $sql = "SELECT * FROM $table_name";
        $params = array();
        
        if (!empty($status)) {
            $sql .= " WHERE status = %s";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_date DESC";
        
        if (!empty($params)) {
            $prepared_sql = $wpdb->prepare($sql, $params);
            return $wpdb->get_results($prepared_sql);
        }
        
        return $wpdb->get_results($sql);
    }
}
