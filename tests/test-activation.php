<?php
/**
 * Test plugin activation and deactivation
 * 
 * @package WooMemberShare
 */

class Test_Activation extends WP_UnitTestCase {
    
    /**
     * Test plugin activation creates database tables
     */
    public function test_activation_creates_tables() {
        // Clean up first
        WMS_Activation::remove_database_tables();
        
        // Run activation
        $activation = new WMS_Activation();
        $activation->activate();
        
        // Check tables exist
        $this->assertTrue(WMS_Test_Helper::table_exists($GLOBALS['wpdb']->prefix . 'share_membership_groups'));
        $this->assertTrue(WMS_Test_Helper::table_exists($GLOBALS['wpdb']->prefix . 'share_membership_group_members'));
        $this->assertTrue(WMS_Test_Helper::table_exists($GLOBALS['wpdb']->prefix . 'share_membership_invitations'));
    }
    
    /**
     * Test activation sets default options
     */
    public function test_activation_sets_default_options() {
        // Clean up options first
        delete_option('membership_sharing_group_label_singular');
        delete_option('membership_sharing_group_label_plural');
        delete_option('membership_sharing_subaccount_label_singular');
        delete_option('membership_sharing_subaccount_label_plural');
        
        // Run activation
        $activation = new WMS_Activation();
        $activation->activate();
        
        // Check default options are set
        $this->assertEquals('Group', get_option('membership_sharing_group_label_singular'));
        $this->assertEquals('Groups', get_option('membership_sharing_group_label_plural'));
        $this->assertEquals('Member', get_option('membership_sharing_subaccount_label_singular'));
        $this->assertEquals('Members', get_option('membership_sharing_subaccount_label_plural'));
    }
    
    /**
     * Test database version is set correctly
     */
    public function test_database_version_set() {
        $activation = new WMS_Activation();
        $activation->activate();
        
        $version = get_option('woo_member_share_db_version');
        $this->assertEquals('1.0.0', $version);
    }
    
    /**
     * Test deactivation cleanup
     */
    public function test_deactivation() {
        $activation = new WMS_Activation();
        
        // Activate first
        $activation->activate();
        
        // Deactivate
        $activation->deactivate();
        
        // Tables should still exist (only removed on uninstall)
        $this->assertTrue(WMS_Test_Helper::table_exists($GLOBALS['wpdb']->prefix . 'share_membership_groups'));
    }
    
    /**
     * Test dependency checking
     */
    public function test_dependency_checking() {
        $plugin = woo_member_share();
        
        // This test assumes WooCommerce and WC Memberships are loaded in test environment
        $this->assertTrue($plugin->check_dependencies());
    }
    
    /**
     * Test table removal on uninstall
     */
    public function test_table_removal() {
        // Activate first to ensure tables exist
        $activation = new WMS_Activation();
        $activation->activate();
        
        // Remove tables
        WMS_Activation::remove_database_tables();
        
        // Check tables are removed
        $this->assertFalse(WMS_Test_Helper::table_exists($GLOBALS['wpdb']->prefix . 'share_membership_groups'));
        $this->assertFalse(WMS_Test_Helper::table_exists($GLOBALS['wpdb']->prefix . 'share_membership_group_members'));
        $this->assertFalse(WMS_Test_Helper::table_exists($GLOBALS['wpdb']->prefix . 'share_membership_invitations'));
        
        // Check options are removed
        $this->assertFalse(get_option('woo_member_share_db_version'));
        $this->assertFalse(get_option('membership_sharing_group_label_singular'));
    }
    
    /**
     * Clean up after each test
     */
    public function tearDown(): void {
        parent::tearDown();
        WMS_Test_Helper::cleanup_test_data();
    }
}
