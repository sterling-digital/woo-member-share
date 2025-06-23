<?php
/**
 * Test helper class for Woo Member Share
 * 
 * @package WooMemberShare
 */

/**
 * Test helper utilities
 */
class WMS_Test_Helper {
    
    /**
     * Create a test product with membership
     * 
     * @param array $args Product arguments
     * @return int Product ID
     */
    public static function create_test_product($args = array()) {
        $defaults = array(
            'post_title' => 'Test Membership Product',
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_input' => array(
                '_enable_membership_sharing' => 'yes',
                '_subaccount_limit_type' => 'fixed',
                '_subaccount_limit' => 5,
            ),
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $product_id = wp_insert_post($args);
        
        // Set as membership product
        wp_set_object_terms($product_id, 'membership', 'product_type');
        
        return $product_id;
    }
    
    /**
     * Create a test variation
     * 
     * @param int $parent_id Parent product ID
     * @param array $args Variation arguments
     * @return int Variation ID
     */
    public static function create_test_variation($parent_id, $args = array()) {
        $defaults = array(
            'post_title' => 'Test Variation',
            'post_type' => 'product_variation',
            'post_parent' => $parent_id,
            'post_status' => 'publish',
            'meta_input' => array(
                '_variation_enable_sharing' => 'yes',
                '_variation_subaccount_limit_type' => 'fixed',
                '_variation_subaccount_limit' => 3,
            ),
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return wp_insert_post($args);
    }
    
    /**
     * Create a test user
     * 
     * @param array $args User arguments
     * @return int User ID
     */
    public static function create_test_user($args = array()) {
        $defaults = array(
            'user_login' => 'testuser_' . wp_generate_password(8, false),
            'user_email' => 'test_' . wp_generate_password(8, false) . '@example.com',
            'user_pass' => 'testpass123',
            'role' => 'customer',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return wp_insert_user($args);
    }
    
    /**
     * Create a test group
     * 
     * @param array $args Group arguments
     * @return int|false Group ID on success, false on failure
     */
    public static function create_test_group($args = array()) {
        $defaults = array(
            'customer_user_id' => self::create_test_user(),
            'product_id' => self::create_test_product(),
            'variation_id' => 0,
            'membership_id' => 0,
            'group_name' => 'Test Group',
            'max_subaccounts' => 5,
            'current_subaccounts' => 0,
            'status' => 'active',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // If no variation specified, create one
        if (empty($args['variation_id'])) {
            $args['variation_id'] = self::create_test_variation($args['product_id']);
        }
        
        return WMS_Database::create_group($args);
    }
    
    /**
     * Create a test invitation
     * 
     * @param array $args Invitation arguments
     * @return int|false Invitation ID on success, false on failure
     */
    public static function create_test_invitation($args = array()) {
        $defaults = array(
            'group_id' => self::create_test_group(),
            'email' => 'invited_' . wp_generate_password(8, false) . '@example.com',
            'status' => 'pending',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return WMS_Database::create_invitation($args);
    }
    
    /**
     * Clean up test data
     * 
     * @param array $data_types Types of data to clean up
     */
    public static function cleanup_test_data($data_types = array('users', 'products', 'groups', 'invitations')) {
        global $wpdb;
        
        if (in_array('invitations', $data_types)) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}share_membership_invitations");
        }
        
        if (in_array('groups', $data_types)) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}share_membership_group_members");
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}share_membership_groups");
        }
        
        if (in_array('products', $data_types)) {
            // Remove test products
            $test_products = get_posts(array(
                'post_type' => array('product', 'product_variation'),
                'post_status' => 'any',
                'numberposts' => -1,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_enable_membership_sharing',
                        'value' => 'yes',
                    ),
                    array(
                        'key' => '_variation_enable_sharing',
                        'value' => 'yes',
                    ),
                ),
            ));
            
            foreach ($test_products as $product) {
                wp_delete_post($product->ID, true);
            }
        }
        
        if (in_array('users', $data_types)) {
            // Remove test users (be careful not to remove admin users)
            $test_users = get_users(array(
                'role' => 'customer',
                'meta_query' => array(
                    array(
                        'key' => 'test_user',
                        'value' => 'yes',
                        'compare' => '=',
                    ),
                ),
            ));
            
            foreach ($test_users as $user) {
                wp_delete_user($user->ID);
            }
        }
    }
    
    /**
     * Assert database table exists
     * 
     * @param string $table_name Table name
     * @return bool True if table exists
     */
    public static function table_exists($table_name) {
        global $wpdb;
        
        $table = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        return $table === $table_name;
    }
    
    /**
     * Get table row count
     * 
     * @param string $table_name Table name
     * @return int Row count
     */
    public static function get_table_count($table_name) {
        global $wpdb;
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }
}
