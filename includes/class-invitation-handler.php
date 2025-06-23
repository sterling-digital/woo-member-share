<?php
/**
 * Invitation handler for processing invitation acceptance
 * 
 * @package WooMemberShare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles invitation processing and acceptance
 */
class WMS_Invitation_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize invitation hooks
     */
    private function init_hooks() {
        // Handle invitation acceptance
        add_action('init', array($this, 'handle_invitation_acceptance'));
        
        // Shortcode for invitation form
        add_shortcode('wms_invitation_form', array($this, 'invitation_form_shortcode'));
    }
    
    /**
     * Handle invitation acceptance from URL
     */
    public function handle_invitation_acceptance() {
        if (!isset($_GET['wms_action']) || $_GET['wms_action'] !== 'accept_invitation') {
            return;
        }
        
        if (!isset($_GET['token'])) {
            wp_die(__('Invalid invitation link.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        $token = sanitize_text_field($_GET['token']);
        $this->process_invitation_acceptance($token);
    }
    
    /**
     * Process invitation acceptance
     * 
     * @param string $token Invitation token
     */
    private function process_invitation_acceptance($token) {
        // Validate token format
        if (strlen($token) !== 64 || !ctype_alnum($token)) {
            wp_die(__('Invalid invitation token format.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        // Get invitation
        $invitation = WMS_Database::get_invitation_by_token($token);
        if (!$invitation) {
            wp_die(__('Invitation not found or has been revoked.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        // Check invitation status
        if ($invitation->status !== 'pending') {
            $this->show_invitation_status_page($invitation);
            return;
        }
        
        // Check expiration
        if (strtotime($invitation->expires_date) < time()) {
            // Mark as expired
            WMS_Database::update_invitation($invitation->id, array(
                'status' => 'expired'
            ));
            wp_die(__('This invitation has expired.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        // Get group information
        global $wpdb;
        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}share_membership_groups WHERE id = %d",
            $invitation->group_id
        ));
        
        if (!$group) {
            wp_die(__('Group not found.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        // Check if group is active
        if ($group->status !== 'active') {
            wp_die(__('This group is no longer active.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        // Show invitation acceptance page
        $this->show_invitation_acceptance_page($invitation, $group);
    }
    
    /**
     * Show invitation acceptance page
     * 
     * @param object $invitation Invitation data
     * @param object $group Group data
     */
    private function show_invitation_acceptance_page($invitation, $group) {
        // Get customer information
        $customer = get_user_by('id', $group->customer_user_id);
        
        // Check if user is logged in
        $current_user = wp_get_current_user();
        $is_logged_in = is_user_logged_in();
        
        // Check if logged in user email matches invitation
        $email_matches = $is_logged_in && $current_user->user_email === $invitation->email;
        
        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wms_accept_invitation'])) {
            $this->process_invitation_form($invitation, $group);
            return;
        }
        
        // Display invitation page
        $this->display_invitation_page($invitation, $group, $customer, $is_logged_in, $email_matches);
    }
    
    /**
     * Process invitation form submission
     * 
     * @param object $invitation Invitation data
     * @param object $group Group data
     */
    private function process_invitation_form($invitation, $group) {
        // Verify nonce
        if (!wp_verify_nonce($_POST['wms_invitation_nonce'], 'wms_accept_invitation_' . $invitation->id)) {
            wp_die(__('Security check failed.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        $action = sanitize_text_field($_POST['wms_accept_invitation']);
        
        if ($action === 'accept') {
            $this->accept_invitation($invitation, $group);
        } elseif ($action === 'decline') {
            $this->decline_invitation($invitation);
        }
    }
    
    /**
     * Accept invitation
     * 
     * @param object $invitation Invitation data
     * @param object $group Group data
     */
    private function accept_invitation($invitation, $group) {
        $current_user = wp_get_current_user();
        $user_id = null;
        
        // Handle user account creation/login
        if (!is_user_logged_in()) {
            // Check if user exists with this email
            $existing_user = get_user_by('email', $invitation->email);
            
            if ($existing_user) {
                // User exists but not logged in - redirect to login
                $login_url = wp_login_url(add_query_arg(array(
                    'wms_action' => 'accept_invitation',
                    'token' => $invitation->invitation_token
                ), home_url()));
                
                wp_redirect($login_url);
                exit;
            } else {
                // Create new user account
                $user_id = $this->create_user_account($invitation->email);
                if (!$user_id) {
                    wp_die(__('Failed to create user account.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
                }
                
                // Log in the new user
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
            }
        } else {
            // User is logged in
            if ($current_user->user_email !== $invitation->email) {
                wp_die(__('You must be logged in with the invited email address to accept this invitation.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
            }
            $user_id = $current_user->ID;
        }
        
        // Check if group has space
        $current_members = WMS_Database::get_group_members($group->id);
        if (count($current_members) >= $group->max_subaccounts) {
            wp_die(__('This group is full and cannot accept new members.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        // Add user to group
        $member_id = WMS_Database::add_group_member(array(
            'group_id' => $group->id,
            'user_id' => $user_id,
            'email' => $invitation->email,
            'member_type' => 'subaccount',
            'status' => 'active',
            'joined_date' => current_time('mysql'),
        ));
        
        if (!$member_id) {
            wp_die(__('Failed to add you to the group.', WOO_MEMBER_SHARE_TEXT_DOMAIN));
        }
        
        // Update invitation status
        WMS_Database::update_invitation($invitation->id, array(
            'status' => 'accepted',
            'accepted_date' => current_time('mysql'),
        ));
        
        // Show success page
        $this->show_success_page($group, $user_id);
    }
    
    /**
     * Decline invitation
     * 
     * @param object $invitation Invitation data
     */
    private function decline_invitation($invitation) {
        // Update invitation status
        WMS_Database::update_invitation($invitation->id, array(
            'status' => 'declined'
        ));
        
        // Show decline confirmation
        $this->show_decline_page();
    }
    
    /**
     * Create user account for invitation
     * 
     * @param string $email User email
     * @return int|false User ID on success, false on failure
     */
    private function create_user_account($email) {
        // Generate username from email
        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
        
        // Ensure username is unique
        $base_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }
        
        // Generate random password
        $password = wp_generate_password(12, false);
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            error_log('WMS: Failed to create user account: ' . $user_id->get_error_message());
            return false;
        }
        
        // Send password reset email
        wp_new_user_notification($user_id, null, 'user');
        
        return $user_id;
    }
    
    /**
     * Display invitation acceptance page
     * 
     * @param object $invitation Invitation data
     * @param object $group Group data
     * @param object $customer Customer data
     * @param bool $is_logged_in Whether user is logged in
     * @param bool $email_matches Whether logged in email matches invitation
     */
    private function display_invitation_page($invitation, $group, $customer, $is_logged_in, $email_matches) {
        // Get labels
        $group_label = WMS_Admin::get_label('group');
        $member_label = WMS_Admin::get_label('subaccount');
        
        // Start output buffering
        ob_start();
        
        get_header();
        ?>
        
        <div class="wms-invitation-page">
            <div class="container">
                <div class="wms-invitation-content">
                    
                    <div class="wms-invitation-header">
                        <h1><?php echo sprintf(__('You\'re Invited to Join a %s!', WOO_MEMBER_SHARE_TEXT_DOMAIN), esc_html($group_label)); ?></h1>
                    </div>
                    
                    <div class="wms-invitation-details">
                        <div class="wms-invitation-info">
                            <h2><?php echo sprintf(__('%s Details', WOO_MEMBER_SHARE_TEXT_DOMAIN), esc_html($group_label)); ?></h2>
                            
                            <table class="wms-invitation-table">
                                <tr>
                                    <td><strong><?php echo sprintf(__('%s Name:', WOO_MEMBER_SHARE_TEXT_DOMAIN), esc_html($group_label)); ?></strong></td>
                                    <td><?php echo esc_html($group->group_name); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo sprintf(__('%s Owner:', WOO_MEMBER_SHARE_TEXT_DOMAIN), esc_html($group_label)); ?></strong></td>
                                    <td><?php echo esc_html($customer->display_name); ?> (<?php echo esc_html($customer->user_email); ?>)</td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('Invited Email:', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></strong></td>
                                    <td><?php echo esc_html($invitation->email); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('Expires:', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></strong></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($invitation->expires_date))); ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <?php if (!$is_logged_in) : ?>
                            <div class="wms-login-notice">
                                <h3><?php esc_html_e('Account Required', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h3>
                                <p><?php esc_html_e('To accept this invitation, you\'ll need an account. We\'ll create one for you automatically when you accept.', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></p>
                            </div>
                        <?php elseif (!$email_matches) : ?>
                            <div class="wms-email-mismatch">
                                <h3><?php esc_html_e('Email Mismatch', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h3>
                                <p><?php echo sprintf(__('You\'re logged in as %s, but this invitation is for %s. Please log out and log in with the correct account, or create a new account.', WOO_MEMBER_SHARE_TEXT_DOMAIN), esc_html(wp_get_current_user()->user_email), esc_html($invitation->email)); ?></p>
                                <p><a href="<?php echo wp_logout_url(add_query_arg(array('wms_action' => 'accept_invitation', 'token' => $invitation->invitation_token), home_url())); ?>" class="button"><?php esc_html_e('Log Out & Continue', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></a></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="wms-invitation-actions">
                            <form method="post" class="wms-invitation-form">
                                <?php wp_nonce_field('wms_accept_invitation_' . $invitation->id, 'wms_invitation_nonce'); ?>
                                
                                <div class="wms-action-buttons">
                                    <button type="submit" name="wms_accept_invitation" value="accept" class="button button-primary button-large" <?php echo (!$is_logged_in || !$email_matches) ? 'disabled' : ''; ?>>
                                        <?php echo sprintf(__('Accept & Join %s', WOO_MEMBER_SHARE_TEXT_DOMAIN), esc_html($group_label)); ?>
                                    </button>
                                    
                                    <button type="submit" name="wms_accept_invitation" value="decline" class="button button-secondary button-large" onclick="return confirm('<?php esc_attr_e('Are you sure you want to decline this invitation?', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>')">
                                        <?php esc_html_e('Decline Invitation', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                                    </button>
                                </div>
                                
                                <?php if (!$is_logged_in || !$email_matches) : ?>
                                    <p class="wms-disabled-notice">
                                        <?php esc_html_e('Please resolve the account issue above to accept this invitation.', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                                    </p>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .wms-invitation-page {
            padding: 40px 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .wms-invitation-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .wms-invitation-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .wms-invitation-header h1 {
            margin: 0;
            font-size: 28px;
        }
        
        .wms-invitation-details {
            padding: 30px;
        }
        
        .wms-invitation-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .wms-invitation-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .wms-invitation-table td:first-child {
            width: 30%;
            background: #f8f9fa;
        }
        
        .wms-login-notice,
        .wms-email-mismatch {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .wms-login-notice h3,
        .wms-email-mismatch h3 {
            margin-top: 0;
            color: #856404;
        }
        
        .wms-invitation-actions {
            margin-top: 30px;
            text-align: center;
        }
        
        .wms-action-buttons {
            margin-bottom: 15px;
        }
        
        .wms-action-buttons .button {
            margin: 0 10px;
            padding: 15px 30px;
            font-size: 16px;
        }
        
        .wms-disabled-notice {
            color: #666;
            font-style: italic;
        }
        
        @media (max-width: 600px) {
            .wms-invitation-page {
                padding: 20px 10px;
            }
            
            .wms-invitation-details {
                padding: 20px;
            }
            
            .wms-action-buttons .button {
                display: block;
                margin: 10px 0;
                width: 100%;
            }
        }
        </style>
        
        <?php
        get_footer();
        
        // End output buffering and display
        $content = ob_get_clean();
        echo $content;
        exit;
    }
    
    /**
     * Show success page after accepting invitation
     * 
     * @param object $group Group data
     * @param int $user_id User ID
     */
    private function show_success_page($group, $user_id) {
        $group_label = WMS_Admin::get_label('group');
        
        ob_start();
        get_header();
        ?>
        
        <div class="wms-success-page">
            <div class="container">
                <div class="wms-success-content">
                    <div class="wms-success-header">
                        <h1><?php echo sprintf(__('Welcome to %s!', WOO_MEMBER_SHARE_TEXT_DOMAIN), esc_html($group->group_name)); ?></h1>
                    </div>
                    
                    <div class="wms-success-details">
                        <p><?php echo sprintf(__('Congratulations! You have successfully joined the %s "%s" and now have access to exclusive membership benefits.', WOO_MEMBER_SHARE_TEXT_DOMAIN), esc_html($group_label), esc_html($group->group_name)); ?></p>
                        
                        <div class="wms-next-steps">
                            <h3><?php esc_html_e('What\'s Next?', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h3>
                            <ul>
                                <li><?php esc_html_e('Explore your new membership benefits', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></li>
                                <li><?php esc_html_e('Visit your account dashboard to manage your profile', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></li>
                                <li><?php esc_html_e('Contact the group owner if you have any questions', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></li>
                            </ul>
                        </div>
                        
                        <div class="wms-action-links">
                            <a href="<?php echo wc_get_page_permalink('myaccount'); ?>" class="button button-primary"><?php esc_html_e('Go to My Account', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></a>
                            <a href="<?php echo home_url(); ?>" class="button button-secondary"><?php esc_html_e('Return to Homepage', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .wms-success-page {
            padding: 40px 20px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .wms-success-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .wms-success-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .wms-success-header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .wms-success-details {
            padding: 30px;
            text-align: center;
        }
        
        .wms-next-steps {
            margin: 30px 0;
            text-align: left;
        }
        
        .wms-next-steps ul {
            list-style-type: disc;
            padding-left: 20px;
        }
        
        .wms-next-steps li {
            margin: 10px 0;
        }
        
        .wms-action-links {
            margin-top: 30px;
        }
        
        .wms-action-links .button {
            margin: 0 10px;
            padding: 12px 24px;
        }
        
        @media (max-width: 600px) {
            .wms-action-links .button {
                display: block;
                margin: 10px 0;
                width: 100%;
            }
        }
        </style>
        
        <?php
        get_footer();
        
        $content = ob_get_clean();
        echo $content;
        exit;
    }
    
    /**
     * Show decline confirmation page
     */
    private function show_decline_page() {
        ob_start();
        get_header();
        ?>
        
        <div class="wms-decline-page">
            <div class="container">
                <div class="wms-decline-content">
                    <div class="wms-decline-header">
                        <h1><?php esc_html_e('Invitation Declined', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h1>
                    </div>
                    
                    <div class="wms-decline-details">
                        <p><?php esc_html_e('You have declined the invitation. No further action is required.', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></p>
                        
                        <div class="wms-action-links">
                            <a href="<?php echo home_url(); ?>" class="button button-primary"><?php esc_html_e('Return to Homepage', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .wms-decline-page {
            padding: 40px 20px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .wms-decline-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .wms-decline-header {
            background: #6c757d;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .wms-decline-header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .wms-decline-details {
            padding: 30px;
            text-align: center;
        }
        
        .wms-action-links {
            margin-top: 30px;
        }
        
        .wms-action-links .button {
            padding: 12px 24px;
        }
        </style>
        
        <?php
        get_footer();
        
        $content = ob_get_clean();
        echo $content;
        exit;
    }
    
    /**
     * Show invitation status page for non-pending invitations
     * 
     * @param object $invitation Invitation data
     */
    private function show_invitation_status_page($invitation) {
        $status_messages = array(
            'accepted' => __('This invitation has already been accepted.', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'declined' => __('This invitation has been declined.', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'expired' => __('This invitation has expired.', WOO_MEMBER_SHARE_TEXT_DOMAIN),
            'revoked' => __('This invitation has been revoked.', WOO_MEMBER_SHARE_TEXT_DOMAIN),
        );
        
        $message = isset($status_messages[$invitation->status]) 
            ? $status_messages[$invitation->status] 
            : __('This invitation is no longer valid.', WOO_MEMBER_SHARE_TEXT_DOMAIN);
        
        wp_die($message);
    }
    
    /**
     * Invitation form shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function invitation_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'token' => '',
        ), $atts);
        
        if (empty($atts['token'])) {
            return '<p>' . __('Invalid invitation token.', WOO_MEMBER_SHARE_TEXT_DOMAIN) . '</p>';
        }
        
        // Process the invitation with the provided token
        $this->process_invitation_acceptance($atts['token']);
        
        return '';
    }
}
