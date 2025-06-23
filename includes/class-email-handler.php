<?php
/**
 * Email handler for invitation system
 * 
 * @package WooMemberShare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles all email functionality for the plugin
 */
class WMS_Email_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize email hooks
     */
    private function init_hooks() {
        // Email template filters
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
        
        // Custom email actions
        add_action('wms_send_invitation_email', array($this, 'send_invitation_email'), 10, 3);
        add_action('wms_send_reminder_email', array($this, 'send_reminder_email'), 10, 2);
        
        // Scheduled email cleanup
        add_action('wms_cleanup_expired_invitations', array($this, 'cleanup_expired_invitations'));
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('wms_cleanup_expired_invitations')) {
            wp_schedule_event(time(), 'daily', 'wms_cleanup_expired_invitations');
        }
    }
    
    /**
     * Set email content type to HTML
     */
    public function set_html_content_type() {
        return 'text/html';
    }
    
    /**
     * Send invitation email
     * 
     * @param string $email Recipient email
     * @param string $token Invitation token
     * @param array $group_data Group information
     * @return bool Success status
     */
    public function send_invitation_email($email, $token, $group_data) {
        // Get customer information
        $customer = get_user_by('id', $group_data['customer_user_id']);
        if (!$customer) {
            return false;
        }
        
        // Generate invitation URL
        $invitation_url = $this->get_invitation_url($token);
        
        // Get email template
        $template_data = array(
            'customer_name' => $customer->display_name,
            'customer_email' => $customer->user_email,
            'group_name' => $group_data['group_name'],
            'invitation_url' => $invitation_url,
            'expires_date' => date_i18n(get_option('date_format'), strtotime('+30 days')),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
        );
        
        $subject = $this->get_email_subject('invitation', $template_data);
        $message = $this->get_email_template('invitation', $template_data);
        $headers = $this->get_email_headers();
        
        // Send email
        $sent = wp_mail($email, $subject, $message, $headers);
        
        // Log email attempt
        if ($sent) {
            error_log("WMS: Invitation email sent to {$email} for group {$group_data['group_name']}");
        } else {
            error_log("WMS: Failed to send invitation email to {$email} for group {$group_data['group_name']}");
        }
        
        return $sent;
    }
    
    /**
     * Send reminder email for pending invitations
     * 
     * @param string $email Recipient email
     * @param string $token Invitation token
     * @return bool Success status
     */
    public function send_reminder_email($email, $token) {
        // Get invitation data
        $invitation = WMS_Database::get_invitation_by_token($token);
        if (!$invitation) {
            return false;
        }
        
        // Get group data
        global $wpdb;
        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}share_membership_groups WHERE id = %d",
            $invitation->group_id
        ));
        
        if (!$group) {
            return false;
        }
        
        // Get customer information
        $customer = get_user_by('id', $group->customer_user_id);
        if (!$customer) {
            return false;
        }
        
        // Generate invitation URL
        $invitation_url = $this->get_invitation_url($token);
        
        // Get email template
        $template_data = array(
            'customer_name' => $customer->display_name,
            'customer_email' => $customer->user_email,
            'group_name' => $group->group_name,
            'invitation_url' => $invitation_url,
            'expires_date' => date_i18n(get_option('date_format'), strtotime($invitation->expires_date)),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
        );
        
        $subject = $this->get_email_subject('reminder', $template_data);
        $message = $this->get_email_template('reminder', $template_data);
        $headers = $this->get_email_headers();
        
        // Send email
        $sent = wp_mail($email, $subject, $message, $headers);
        
        // Log email attempt
        if ($sent) {
            error_log("WMS: Reminder email sent to {$email} for group {$group->group_name}");
        } else {
            error_log("WMS: Failed to send reminder email to {$email} for group {$group->group_name}");
        }
        
        return $sent;
    }
    
    /**
     * Generate invitation URL
     * 
     * @param string $token Invitation token
     * @return string Invitation URL
     */
    private function get_invitation_url($token) {
        return add_query_arg(
            array(
                'wms_action' => 'accept_invitation',
                'token' => $token,
            ),
            home_url()
        );
    }
    
    /**
     * Get email subject based on type
     * 
     * @param string $type Email type (invitation, reminder)
     * @param array $data Template data
     * @return string Email subject
     */
    private function get_email_subject($type, $data) {
        $group_label = WMS_Admin::get_label('group');
        
        switch ($type) {
            case 'invitation':
                $subject = sprintf(
                    __('You\'re invited to join %s\'s %s on %s', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                    $data['customer_name'],
                    $group_label,
                    $data['site_name']
                );
                break;
                
            case 'reminder':
                $subject = sprintf(
                    __('Reminder: Your invitation to join %s\'s %s expires soon', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                    $data['customer_name'],
                    $group_label
                );
                break;
                
            default:
                $subject = sprintf(
                    __('Invitation from %s', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                    $data['site_name']
                );
        }
        
        // Allow filtering of subject
        return apply_filters('wms_email_subject', $subject, $type, $data);
    }
    
    /**
     * Get email template based on type
     * 
     * @param string $type Email type (invitation, reminder)
     * @param array $data Template data
     * @return string Email HTML content
     */
    private function get_email_template($type, $data) {
        // Check for custom template file
        $template_file = WOO_MEMBER_SHARE_PLUGIN_DIR . "templates/emails/{$type}.php";
        
        if (file_exists($template_file)) {
            ob_start();
            include $template_file;
            $content = ob_get_clean();
        } else {
            // Use default template
            $content = $this->get_default_template($type, $data);
        }
        
        // Allow filtering of template content
        return apply_filters('wms_email_template', $content, $type, $data);
    }
    
    /**
     * Get default email template
     * 
     * @param string $type Email type
     * @param array $data Template data
     * @return string Email HTML content
     */
    private function get_default_template($type, $data) {
        $group_label = WMS_Admin::get_label('group');
        $member_label = WMS_Admin::get_label('subaccount');
        
        $header = $this->get_email_header($data);
        $footer = $this->get_email_footer($data);
        
        switch ($type) {
            case 'invitation':
                $content = sprintf('
                    <h2>%s</h2>
                    <p>%s</p>
                    <p><strong>%s:</strong> %s</p>
                    <p><strong>%s:</strong> %s</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="%s" style="background-color: #0073aa; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">%s</a>
                    </div>
                    <p style="color: #666; font-size: 14px;">%s</p>
                    <p style="color: #666; font-size: 14px;">%s</p>
                ',
                    sprintf(__('You\'re invited to join a %s!', WOO_MEMBER_SHARE_TEXT_DOMAIN), $group_label),
                    sprintf(__('%s has invited you to join their %s "%s" and share access to exclusive membership benefits.', WOO_MEMBER_SHARE_TEXT_DOMAIN), $data['customer_name'], $group_label, $data['group_name']),
                    sprintf(__('%s Owner', WOO_MEMBER_SHARE_TEXT_DOMAIN), $group_label),
                    $data['customer_name'] . ' (' . $data['customer_email'] . ')',
                    sprintf(__('%s Name', WOO_MEMBER_SHARE_TEXT_DOMAIN), $group_label),
                    $data['group_name'],
                    esc_url($data['invitation_url']),
                    sprintf(__('Accept Invitation & Join %s', WOO_MEMBER_SHARE_TEXT_DOMAIN), $group_label),
                    sprintf(__('This invitation will expire on %s.', WOO_MEMBER_SHARE_TEXT_DOMAIN), $data['expires_date']),
                    __('If you don\'t want to join this group, you can safely ignore this email.', WOO_MEMBER_SHARE_TEXT_DOMAIN)
                );
                break;
                
            case 'reminder':
                $content = sprintf('
                    <h2>%s</h2>
                    <p>%s</p>
                    <p><strong>%s:</strong> %s</p>
                    <p><strong>%s:</strong> %s</p>
                    <p><strong>%s:</strong> %s</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="%s" style="background-color: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">%s</a>
                    </div>
                    <p style="color: #666; font-size: 14px;">%s</p>
                ',
                    __('Invitation Reminder', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                    sprintf(__('This is a reminder that you have a pending invitation to join %s\'s %s.', WOO_MEMBER_SHARE_TEXT_DOMAIN), $data['customer_name'], $group_label),
                    sprintf(__('%s Owner', WOO_MEMBER_SHARE_TEXT_DOMAIN), $group_label),
                    $data['customer_name'] . ' (' . $data['customer_email'] . ')',
                    sprintf(__('%s Name', WOO_MEMBER_SHARE_TEXT_DOMAIN), $group_label),
                    $data['group_name'],
                    __('Expires', WOO_MEMBER_SHARE_TEXT_DOMAIN),
                    $data['expires_date'],
                    esc_url($data['invitation_url']),
                    sprintf(__('Accept Before It Expires', WOO_MEMBER_SHARE_TEXT_DOMAIN)),
                    __('If you don\'t want to join this group, you can safely ignore this email.', WOO_MEMBER_SHARE_TEXT_DOMAIN)
                );
                break;
                
            default:
                $content = '<p>' . __('You have received an invitation.', WOO_MEMBER_SHARE_TEXT_DOMAIN) . '</p>';
        }
        
        return $header . $content . $footer;
    }
    
    /**
     * Get email header
     * 
     * @param array $data Template data
     * @return string Email header HTML
     */
    private function get_email_header($data) {
        return sprintf('
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>%s</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background-color: #ffffff; padding: 30px; border: 1px solid #dee2e6; }
                    .footer { background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 5px 5px; font-size: 12px; color: #666; }
                    a { color: #0073aa; }
                    .button { background-color: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>%s</h1>
                </div>
                <div class="content">
        ',
            esc_html($data['site_name']),
            esc_html($data['site_name'])
        );
    }
    
    /**
     * Get email footer
     * 
     * @param array $data Template data
     * @return string Email footer HTML
     */
    private function get_email_footer($data) {
        return sprintf('
                </div>
                <div class="footer">
                    <p>%s<br>
                    <a href="%s">%s</a></p>
                    <p>%s</p>
                </div>
            </body>
            </html>
        ',
            sprintf(__('This email was sent from %s', WOO_MEMBER_SHARE_TEXT_DOMAIN), esc_html($data['site_name'])),
            esc_url($data['site_url']),
            esc_html($data['site_url']),
            __('If you received this email by mistake, please ignore it.', WOO_MEMBER_SHARE_TEXT_DOMAIN)
        );
    }
    
    /**
     * Get email headers
     * 
     * @return array Email headers
     */
    private function get_email_headers() {
        $headers = array();
        
        // Set from address
        $from_email = get_option('admin_email');
        $from_name = get_bloginfo('name');
        
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = sprintf('From: %s <%s>', $from_name, $from_email);
        
        // Allow filtering of headers
        return apply_filters('wms_email_headers', $headers);
    }
    
    /**
     * Cleanup expired invitations
     */
    public function cleanup_expired_invitations() {
        $cleaned = WMS_Database::cleanup_expired_invitations();
        
        if ($cleaned > 0) {
            error_log("WMS: Cleaned up {$cleaned} expired invitations");
        }
    }
    
    /**
     * Send test email (for admin testing)
     * 
     * @param string $email Test email address
     * @return bool Success status
     */
    public function send_test_email($email) {
        $template_data = array(
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'group_name' => 'Test Group',
            'invitation_url' => home_url('?test=1'),
            'expires_date' => date_i18n(get_option('date_format'), strtotime('+30 days')),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
        );
        
        $subject = '[TEST] ' . $this->get_email_subject('invitation', $template_data);
        $message = $this->get_email_template('invitation', $template_data);
        $headers = $this->get_email_headers();
        
        return wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * Get email statistics
     * 
     * @return array Email statistics
     */
    public function get_email_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total invitations sent
        $stats['total_invitations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}share_membership_invitations"
        );
        
        // Pending invitations
        $stats['pending_invitations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}share_membership_invitations WHERE status = 'pending'"
        );
        
        // Accepted invitations
        $stats['accepted_invitations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}share_membership_invitations WHERE status = 'accepted'"
        );
        
        // Expired invitations
        $stats['expired_invitations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}share_membership_invitations WHERE status = 'expired'"
        );
        
        return $stats;
    }
}
