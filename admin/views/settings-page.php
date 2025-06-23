<?php
/**
 * Admin settings page template
 * 
 * @package WooMemberShare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Member Share Settings', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('woo_member_share_settings', 'woo_member_share_nonce'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="membership_sharing_group_label_singular">
                            <?php esc_html_e('Group Label (Singular)', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="membership_sharing_group_label_singular" 
                               name="membership_sharing_group_label_singular" 
                               value="<?php echo esc_attr(get_option('membership_sharing_group_label_singular', __('Group', WOO_MEMBER_SHARE_TEXT_DOMAIN))); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e('The singular form of the label used for groups (e.g., "Team", "Group").', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="membership_sharing_group_label_plural">
                            <?php esc_html_e('Group Label (Plural)', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="membership_sharing_group_label_plural" 
                               name="membership_sharing_group_label_plural" 
                               value="<?php echo esc_attr(get_option('membership_sharing_group_label_plural', __('Groups', WOO_MEMBER_SHARE_TEXT_DOMAIN))); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e('The plural form of the label used for groups (e.g., "Teams", "Groups").', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="membership_sharing_subaccount_label_singular">
                            <?php esc_html_e('Member Label (Singular)', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="membership_sharing_subaccount_label_singular" 
                               name="membership_sharing_subaccount_label_singular" 
                               value="<?php echo esc_attr(get_option('membership_sharing_subaccount_label_singular', __('Member', WOO_MEMBER_SHARE_TEXT_DOMAIN))); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e('The singular form of the label used for group members (e.g., "User", "Member").', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="membership_sharing_subaccount_label_plural">
                            <?php esc_html_e('Member Label (Plural)', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="membership_sharing_subaccount_label_plural" 
                               name="membership_sharing_subaccount_label_plural" 
                               value="<?php echo esc_attr(get_option('membership_sharing_subaccount_label_plural', __('Members', WOO_MEMBER_SHARE_TEXT_DOMAIN))); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php esc_html_e('The plural form of the label used for group members (e.g., "Users", "Members").', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button(__('Save Settings', WOO_MEMBER_SHARE_TEXT_DOMAIN)); ?>
    </form>
    
    <?php if (function_exists('wc_memberships') || class_exists('WC_Memberships')) : ?>
    <div class="wms-membership-stats">
        <h2><?php esc_html_e('Membership Statistics', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h2>
        
        <div class="wms-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div class="wms-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; text-align: center;">
                <h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e('Total Groups', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h3>
                <div class="wms-stat-number" id="total-groups" style="font-size: 2em; font-weight: bold; color: #2271b1;">-</div>
            </div>
            
            <div class="wms-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; text-align: center;">
                <h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e('Active Groups', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h3>
                <div class="wms-stat-number" id="active-groups" style="font-size: 2em; font-weight: bold; color: #00a32a;">-</div>
            </div>
            
            <div class="wms-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; text-align: center;">
                <h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e('Linked Memberships', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h3>
                <div class="wms-stat-number" id="linked-groups" style="font-size: 2em; font-weight: bold; color: #8c8f94;">-</div>
            </div>
            
            <div class="wms-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; text-align: center;">
                <h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e('Total Members', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h3>
                <div class="wms-stat-number" id="total-subaccounts" style="font-size: 2em; font-weight: bold; color: #2271b1;">-</div>
            </div>
            
            <div class="wms-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; text-align: center;">
                <h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e('Active Members', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></h3>
                <div class="wms-stat-number" id="active-subaccounts" style="font-size: 2em; font-weight: bold; color: #00a32a;">-</div>
            </div>
        </div>
        
        <div class="wms-admin-actions" style="margin: 20px 0;">
            <button type="button" id="sync-membership-status" class="button button-secondary">
                <?php esc_html_e('Sync Membership Status', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
            </button>
            <button type="button" id="refresh-stats" class="button button-secondary">
                <?php esc_html_e('Refresh Statistics', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
            </button>
        </div>
        
        <div id="wms-admin-messages" style="margin: 20px 0;"></div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Load initial stats
        loadMembershipStats();
        
        // Refresh stats button
        $('#refresh-stats').on('click', function() {
            loadMembershipStats();
        });
        
        // Sync membership status button
        $('#sync-membership-status').on('click', function() {
            var $button = $(this);
            $button.prop('disabled', true).text('<?php esc_html_e('Syncing...', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wms_sync_membership_status',
                    nonce: '<?php echo wp_create_nonce('wms_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        loadMembershipStats(); // Refresh stats after sync
                    } else {
                        showMessage('<?php esc_html_e('Sync failed. Please try again.', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>', 'error');
                    }
                },
                error: function() {
                    showMessage('<?php esc_html_e('Sync failed. Please try again.', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('<?php esc_html_e('Sync Membership Status', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>');
                }
            });
        });
        
        function loadMembershipStats() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wms_get_membership_stats',
                    nonce: '<?php echo wp_create_nonce('wms_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var stats = response.data;
                        $('#total-groups').text(stats.total_groups || 0);
                        $('#active-groups').text(stats.active_groups || 0);
                        $('#linked-groups').text(stats.linked_groups || 0);
                        $('#total-subaccounts').text(stats.total_subaccounts || 0);
                        $('#active-subaccounts').text(stats.active_subaccounts || 0);
                    }
                },
                error: function() {
                    $('.wms-stat-number').text('?');
                }
            });
        }
        
        function showMessage(message, type) {
            var $container = $('#wms-admin-messages');
            var alertClass = type === 'success' ? 'notice-success' : 'notice-error';
            var $notice = $('<div class="notice ' + alertClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $container.empty().append($notice);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        }
    });
    </script>
    <?php endif; ?>
</div>
