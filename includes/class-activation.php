<?php
/**
 * Plugin activation and deactivation handler
 * 
 * @package WooMemberShare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles plugin activation and deactivation
 */
class WMS_Activation {
    
    /**
     * Database version option name
     */
    const DB_VERSION_OPTION = 'woo_member_share_db_version';
    
    /**
     * Current database version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check WordPress version
        if (!$this->check_wordpress_version()) {
            wp_die(__('Woo Member Share requires WordPress 5.0 or higher.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        // Check PHP version
        if (!$this->check_php_version()) {
            wp_die(__('Woo Member Share requires PHP 7.4 or higher.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        // Check dependencies
        if (!$this->check_dependencies()) {
            wp_die(__('Woo Member Share requires both WooCommerce and WooCommerce Memberships to be installed and activated.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        // Create database tables
        $this->create_database_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log successful activation
        error_log('Woo Member Share: Plugin activated successfully');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('Woo Member Share: Plugin deactivated');
    }
    
    /**
     * Check WordPress version
     */
    private function check_wordpress_version() {
        global $wp_version;
        return version_compare($wp_version, '5.0', '>=');
    }
    
    /**
     * Check PHP version
     */
    private function check_php_version() {
        return version_compare(PHP_VERSION, '7.4', '>=');
    }
    
    /**
     * Check if required dependencies are active
     */
    private function check_dependencies() {
        return (
            class_exists('WooCommerce') && 
            class_exists('WC_Memberships')
        );
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        // Check if we need to create/update tables
        $installed_version = get_option(self::DB_VERSION_OPTION, '0');
        
        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $charset_collate = $wpdb->get_charset_collate();
            
            // Groups table
            $sql_groups = "CREATE TABLE {$wpdb->prefix}share_membership_groups (
                id int(11) NOT NULL AUTO_INCREMENT,
                customer_user_id bigint(20) unsigned NOT NULL,
                product_id bigint(20) unsigned NOT NULL,
                variation_id bigint(20) unsigned NOT NULL,
                membership_id bigint(20) unsigned NOT NULL,
                group_name varchar(255) NOT NULL,
                max_subaccounts int(11) NOT NULL,
                current_subaccounts int(11) DEFAULT 0,
                created_date datetime NOT NULL,
                status enum('active','suspended','expired') DEFAULT 'active',
                PRIMARY KEY (id),
                KEY idx_customer (customer_user_id),
                KEY idx_product (product_id),
                KEY idx_variation (variation_id),
                KEY idx_membership (membership_id),
                KEY idx_status (status),
                UNIQUE KEY unique_customer_variation (customer_user_id, variation_id),
                FOREIGN KEY (customer_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE RESTRICT,
                FOREIGN KEY (product_id) REFERENCES {$wpdb->posts}(ID) ON DELETE RESTRICT,
                FOREIGN KEY (variation_id) REFERENCES {$wpdb->posts}(ID) ON DELETE RESTRICT,
                FOREIGN KEY (membership_id) REFERENCES {$wpdb->posts}(ID) ON DELETE RESTRICT
            ) $charset_collate;";
            
            // Group members table
            $sql_members = "CREATE TABLE {$wpdb->prefix}share_membership_group_members (
                id int(11) NOT NULL AUTO_INCREMENT,
                group_id int(11) NOT NULL,
                user_id bigint(20) unsigned NULL,
                email varchar(255) NOT NULL,
                member_type enum('customer','subaccount') NOT NULL,
                status enum('active','pending','revoked') DEFAULT 'pending',
                joined_date datetime NULL,
                invited_date datetime NOT NULL,
                PRIMARY KEY (id),
                KEY idx_group (group_id),
                KEY idx_user (user_id),
                KEY idx_email (email),
                KEY idx_status (status),
                FOREIGN KEY (group_id) REFERENCES {$wpdb->prefix}share_membership_groups(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
            ) $charset_collate;";
            
            // Invitations table
            $sql_invitations = "CREATE TABLE {$wpdb->prefix}share_membership_invitations (
                id int(11) NOT NULL AUTO_INCREMENT,
                group_id int(11) NOT NULL,
                email varchar(255) NOT NULL,
                invitation_token varchar(255) NOT NULL,
                status enum('pending','accepted','declined','expired','revoked') DEFAULT 'pending',
                sent_date datetime NOT NULL,
                expires_date datetime NOT NULL,
                accepted_date datetime NULL,
                PRIMARY KEY (id),
                UNIQUE KEY idx_token (invitation_token),
                KEY idx_email (email),
                KEY idx_status (status),
                KEY idx_expires (expires_date),
                FOREIGN KEY (group_id) REFERENCES {$wpdb->prefix}share_membership_groups(id) ON DELETE CASCADE
            ) $charset_collate;";
            
            // Execute table creation
            $results = array();
            $results[] = dbDelta($sql_groups);
            $results[] = dbDelta($sql_members);
            $results[] = dbDelta($sql_invitations);
            
            // Check for errors
            $errors = array();
            foreach ($results as $result) {
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                }
            }
            
            if (!empty($errors)) {
                error_log('Woo Member Share: Database creation errors: ' . implode(', ', $errors));
                wp_die(__('Failed to create database tables. Please check error log.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
            }
            
            // Update database version
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
            
            error_log('Woo Member Share: Database tables created successfully');
        }
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'membership_sharing_group_label_singular' => __('Group', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'membership_sharing_group_label_plural' => __('Groups', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'membership_sharing_subaccount_label_singular' => __('Member', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'membership_sharing_subaccount_label_plural' => __('Members', WOO_MEMBER_SHARE_TEXT_DOMAIN),
        );
        
        foreach ($default_options as $option_name => $default_value) {
            if (false === get_option($option_name)) {
                add_option($option_name, $default_value);
            }
        }
    }
    
    /**
     * Remove database tables (for uninstall only)
     */
    public static function remove_database_tables() {
        global $wpdb;
        
        // Drop tables in reverse order due to foreign key constraints
        $tables = array(
            $wpdb->prefix . 'share_membership_invitations',
            $wpdb->prefix . 'share_membership_group_members',
            $wpdb->prefix . 'share_membership_groups'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Remove options
        delete_option(self::DB_VERSION_OPTION);
        delete_option('membership_sharing_group_label_singular');
        delete_option('membership_sharing_group_label_plural');
        delete_option('membership_sharing_subaccount_label_singular');
        delete_option('membership_sharing_subaccount_label_plural');
    }
}
