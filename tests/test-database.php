<?php
/**
 * Test database operations
 * 
 * @package WooMemberShare
 */

class Test_Database extends WP_UnitTestCase {
    
    /**
     * Setup test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Ensure database tables exist
        $activation = new WMS_Activation();
        $activation->activate();
    }
    
    /**
     * Test group creation
     */
    public function test_create_group() {
        $user_id = WMS_Test_Helper::create_test_user();
        $product_id = WMS_Test_Helper::create_test_product();
        $variation_id = WMS_Test_Helper::create_test_variation($product_id);
        
        $group_data = array(
            'customer_user_id' => $user_id,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'membership_id' => 123,
            'group_name' => 'Test Group',
            'max_subaccounts' => 5,
        );
        
        $group_id = WMS_Database::create_group($group_data);
        
        $this->assertIsInt($group_id);
        $this->assertGreaterThan(0, $group_id);
        
        // Verify group was created correctly
        $group = WMS_Database::get_group_by_customer_variation($user_id, $variation_id);
        $this->assertNotNull($group);
        $this->assertEquals('Test Group', $group->group_name);
        $this->assertEquals(5, $group->max_subaccounts);
    }
    
    /**
     * Test group creation with invalid data
     */
    public function test_create_group_invalid_data() {
        // Test with missing required fields
        $group_id = WMS_Database::create_group(array());
        $this->assertFalse($group_id);
        
        // Test with missing customer_user_id
        $group_id = WMS_Database::create_group(array(
            'variation_id' => 123,
            'group_name' => 'Test Group',
        ));
        $this->assertFalse($group_id);
    }
    
    /**
     * Test get customer groups
     */
    public function test_get_customer_groups() {
        $user_id = WMS_Test_Helper::create_test_user();
        
        // Create multiple groups for the same customer
        $group1_id = WMS_Test_Helper::create_test_group(array(
            'customer_user_id' => $user_id,
            'group_name' => 'Group 1',
        ));
        
        $group2_id = WMS_Test_Helper::create_test_group(array(
            'customer_user_id' => $user_id,
            'group_name' => 'Group 2',
        ));
        
        $groups = WMS_Database::get_customer_groups($user_id);
        
        $this->assertCount(2, $groups);
        $this->assertEquals('Group 2', $groups[0]->group_name); // Should be ordered by created_date DESC
        $this->assertEquals('Group 1', $groups[1]->group_name);
    }
    
    /**
     * Test invitation creation and token generation
     */
    public function test_create_invitation() {
        $group_id = WMS_Test_Helper::create_test_group();
        
        $invitation_data = array(
            'group_id' => $group_id,
            'email' => 'test@example.com',
        );
        
        $invitation_id = WMS_Database::create_invitation($invitation_data);
        
        $this->assertIsInt($invitation_id);
        $this->assertGreaterThan(0, $invitation_id);
        
        // Verify invitation was created with token
        $invitation = WMS_Database::get_invitation_by_token('test-token');
        $this->assertNull($invitation); // Should be null since we didn't provide a specific token
        
        // Get invitation directly and check token
        global $wpdb;
        $invitation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}share_membership_invitations WHERE id = %d",
            $invitation_id
        ));
        
        $this->assertNotNull($invitation);
        $this->assertEquals('test@example.com', $invitation->email);
        $this->assertEquals(64, strlen($invitation->invitation_token)); // Token should be 64 characters
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $invitation->invitation_token); // Only alphanumeric
    }
    
    /**
     * Test group member operations
     */
    public function test_group_member_operations() {
        $group_id = WMS_Test_Helper::create_test_group();
        
        // Add member
        $member_data = array(
            'group_id' => $group_id,
            'email' => 'member@example.com',
            'member_type' => 'subaccount',
            'status' => 'pending',
        );
        
        $member_id = WMS_Database::add_group_member($member_data);
        $this->assertIsInt($member_id);
        
        // Get members
        $members = WMS_Database::get_group_members($group_id);
        $this->assertCount(1, $members);
        $this->assertEquals('member@example.com', $members[0]->email);
        
        // Update member
        $update_result = WMS_Database::update_group_member($member_id, array(
            'status' => 'active',
        ));
        $this->assertTrue($update_result);
        
        // Verify update
        $members = WMS_Database::get_group_members($group_id, 'active');
        $this->assertCount(1, $members);
        
        // Remove member
        $remove_result = WMS_Database::remove_group_member($member_id);
        $this->assertTrue($remove_result);
        
        // Verify removal
        $members = WMS_Database::get_group_members($group_id);
        $this->assertCount(0, $members);
    }
    
    /**
     * Test expired invitations cleanup
     */
    public function test_cleanup_expired_invitations() {
        $group_id = WMS_Test_Helper::create_test_group();
        
        // Create an expired invitation
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'share_membership_invitations',
            array(
                'group_id' => $group_id,
                'email' => 'expired@example.com',
                'invitation_token' => 'expired-token-123',
                'status' => 'pending',
                'sent_date' => date('Y-m-d H:i:s', strtotime('-40 days')),
                'expires_date' => date('Y-m-d H:i:s', strtotime('-10 days')),
            )
        );
        
        // Run cleanup
        $cleaned = WMS_Database::cleanup_expired_invitations();
        $this->assertEquals(1, $cleaned);
        
        // Verify invitation status changed
        $invitation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}share_membership_invitations WHERE email = %s",
            'expired@example.com'
        ));
        
        $this->assertEquals('expired', $invitation->status);
    }
    
    /**
     * Clean up after each test
     */
    public function tearDown(): void {
        parent::tearDown();
        WMS_Test_Helper::cleanup_test_data();
    }
}
