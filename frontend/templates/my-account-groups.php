<?php
/**
 * My Account Groups Template
 * 
 * @package WooMemberShare
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$group_label_singular = WMS_Admin::get_label('group');
$group_label_plural = WMS_Admin::get_label('group', true);
$member_label_singular = WMS_Admin::get_label('subaccount');
$member_label_plural = WMS_Admin::get_label('subaccount', true);
?>

<div class="woo-member-share-groups">
    
    <?php if (empty($groups)) : ?>
        <div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
            <p><?php 
                /* translators: %s: Group label plural */
                echo esc_html(sprintf(__('You don\'t have any %s yet. Purchase a membership with sharing enabled to create your first group.', WOO_MEMBER_SHARE_TEXT_DOMAIN), strtolower($group_label_plural))); 
            ?></p>
        </div>
    <?php else : ?>
        
        <h2><?php 
            /* translators: %s: Group label plural */
            echo esc_html(sprintf(__('Your %s', WOO_MEMBER_SHARE_TEXT_DOMAIN), $group_label_plural)); 
        ?></h2>
        
        <?php foreach ($groups as $group) : 
            $members = WMS_Database::get_group_members($group->id);
            $customer_is_member = false;
            foreach ($members as $member) {
                if ($member->user_id == $user_id) {
                    $customer_is_member = true;
                    break;
                }
            }
        ?>
            
            <div class="wms-group-container" data-group-id="<?php echo esc_attr($group->id); ?>">
                <div class="wms-group-header">
                    <h3 class="wms-group-title">
                        <?php echo esc_html($group->group_name); ?>
                        <span class="wms-member-count">
                            (<?php echo count($members); ?>/<?php echo esc_html($group->max_subaccounts); ?> <?php echo esc_html(strtolower($member_label_plural)); ?>)
                        </span>
                    </h3>
                    
                    <div class="wms-group-actions">
                        <button type="button" class="button wms-toggle-group" data-target="group-details-<?php echo esc_attr($group->id); ?>">
                            <?php esc_html_e('Manage', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                        </button>
                    </div>
                </div>
                
                <div id="group-details-<?php echo esc_attr($group->id); ?>" class="wms-group-details" style="display: none;">
                    
                    <!-- Group Status -->
                    <div class="wms-group-status">
                        <p><strong><?php esc_html_e('Status:', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></strong> 
                            <span class="status-<?php echo esc_attr($group->status); ?>">
                                <?php echo esc_html(ucfirst($group->status)); ?>
                            </span>
                        </p>
                    </div>
                    
                    <!-- Customer Join/Leave Controls -->
                    <div class="wms-customer-controls">
                        <?php if ($customer_is_member) : ?>
                            <form method="post" class="wms-action-form">
                                <?php wp_nonce_field('wms_group_action', 'wms_nonce'); ?>
                                <input type="hidden" name="wms_action" value="leave_group">
                                <input type="hidden" name="group_id" value="<?php echo esc_attr($group->id); ?>">
                                <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to leave this group? You will lose access to membership benefits.', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>')">
                                    <?php 
                                    /* translators: %s: Group label singular */
                                    echo esc_html(sprintf(__('Leave %s', WOO_MEMBER_SHARE_TEXT_DOMAIN), $group_label_singular)); 
                                    ?>
                                </button>
                            </form>
                        <?php else : ?>
                            <form method="post" class="wms-action-form">
                                <?php wp_nonce_field('wms_group_action', 'wms_nonce'); ?>
                                <input type="hidden" name="wms_action" value="join_group">
                                <input type="hidden" name="group_id" value="<?php echo esc_attr($group->id); ?>">
                                <button type="submit" class="button button-secondary">
                                    <?php 
                                    /* translators: %s: Group label singular */
                                    echo esc_html(sprintf(__('Join %s', WOO_MEMBER_SHARE_TEXT_DOMAIN), $group_label_singular)); 
                                    ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Group Settings -->
                    <div class="wms-group-settings">
                        <h4><?php 
                            /* translators: %s: Group label singular */
                            echo esc_html(sprintf(__('%s Settings', WOO_MEMBER_SHARE_TEXT_DOMAIN), $group_label_singular)); 
                        ?></h4>
                        
                        <form method="post" class="wms-rename-form">
                            <?php wp_nonce_field('wms_group_action', 'wms_nonce'); ?>
                            <input type="hidden" name="wms_action" value="rename_group">
                            <input type="hidden" name="group_id" value="<?php echo esc_attr($group->id); ?>">
                            
                            <p>
                                <label for="group_name_<?php echo esc_attr($group->id); ?>">
                                    <?php 
                                    /* translators: %s: Group label singular */
                                    echo esc_html(sprintf(__('%s Name:', WOO_MEMBER_SHARE_TEXT_DOMAIN), $group_label_singular)); 
                                    ?>
                                </label>
                                <input type="text" 
                                       id="group_name_<?php echo esc_attr($group->id); ?>" 
                                       name="group_name" 
                                       value="<?php echo esc_attr($group->group_name); ?>" 
                                       class="regular-text" 
                                       required>
                                <button type="submit" class="button button-secondary">
                                    <?php esc_html_e('Rename', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                                </button>
                            </p>
                        </form>
                    </div>
                    
                    <!-- Member Management -->
                    <div class="wms-member-management">
                        <h4><?php 
                            /* translators: %s: Member label plural */
                            echo esc_html(sprintf(__('%s Management', WOO_MEMBER_SHARE_TEXT_DOMAIN), $member_label_plural)); 
                        ?></h4>
                        
                        <!-- Current Members -->
                        <?php if (!empty($members)) : ?>
                            <div class="wms-current-members">
                                <h5><?php 
                                    /* translators: %s: Member label plural */
                                    echo esc_html(sprintf(__('Current %s', WOO_MEMBER_SHARE_TEXT_DOMAIN), $member_label_plural)); 
                                ?></h5>
                                
                                <table class="wms-members-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Email', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></th>
                                            <th><?php esc_html_e('Type', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></th>
                                            <th><?php esc_html_e('Status', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></th>
                                            <th><?php esc_html_e('Joined', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></th>
                                            <th><?php esc_html_e('Actions', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $member) : ?>
                                            <tr>
                                                <td><?php echo esc_html($member->email); ?></td>
                                                <td>
                                                    <?php if ($member->member_type === 'customer') : ?>
                                                        <span class="member-type-customer"><?php esc_html_e('Owner', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?></span>
                                                    <?php else : ?>
                                                        <span class="member-type-subaccount"><?php echo esc_html(ucfirst($member_label_singular)); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="member-status status-<?php echo esc_attr($member->status); ?>">
                                                        <?php echo esc_html(ucfirst($member->status)); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($member->joined_date) {
                                                        echo esc_html(date_i18n(get_option('date_format'), strtotime($member->joined_date)));
                                                    } else {
                                                        esc_html_e('Pending', WOO_MEMBER_SHARE_TEXT_DOMAIN);
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($member->member_type === 'subaccount') : ?>
                                                        <form method="post" class="wms-remove-member-form" style="display: inline;">
                                                            <?php wp_nonce_field('wms_group_action', 'wms_nonce'); ?>
                                                            <input type="hidden" name="wms_action" value="remove_member">
                                                            <input type="hidden" name="member_id" value="<?php echo esc_attr($member->id); ?>">
                                                            <button type="submit" 
                                                                    class="button button-small button-link-delete" 
                                                                    onclick="return confirm('<?php esc_attr_e('Are you sure you want to remove this member?', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>')">
                                                                <?php esc_html_e('Remove', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Invite New Member -->
                        <?php if (count($members) < $group->max_subaccounts) : ?>
                            <div class="wms-invite-member">
                                <h5><?php 
                                    /* translators: %s: Member label singular */
                                    echo esc_html(sprintf(__('Invite New %s', WOO_MEMBER_SHARE_TEXT_DOMAIN), $member_label_singular)); 
                                ?></h5>
                                
                                <form method="post" class="wms-invite-form">
                                    <?php wp_nonce_field('wms_group_action', 'wms_nonce'); ?>
                                    <input type="hidden" name="wms_action" value="invite_member">
                                    <input type="hidden" name="group_id" value="<?php echo esc_attr($group->id); ?>">
                                    
                                    <p>
                                        <label for="invite_email_<?php echo esc_attr($group->id); ?>">
                                            <?php esc_html_e('Email Address:', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                                        </label>
                                        <input type="email" 
                                               id="invite_email_<?php echo esc_attr($group->id); ?>" 
                                               name="invite_email" 
                                               placeholder="<?php esc_attr_e('Enter email address', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>" 
                                               class="regular-text" 
                                               required>
                                        <button type="submit" class="button button-primary">
                                            <?php esc_html_e('Send Invitation', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>
                                        </button>
                                    </p>
                                </form>
                                
                                <p class="description">
                                    <?php 
                                    $remaining_slots = $group->max_subaccounts - count($members);
                                    /* translators: 1: Number of remaining slots, 2: Member label plural */
                                    echo esc_html(sprintf(_n('You can invite %1$d more %2$s.', 'You can invite %1$d more %2$s.', $remaining_slots, WOO_MEMBER_SHARE_TEXT_DOMAIN), $remaining_slots, strtolower($member_label_plural))); 
                                    ?>
                                </p>
                            </div>
                        <?php else : ?>
                            <div class="wms-group-full">
                                <p class="description">
                                    <?php 
                                    /* translators: %s: Member label plural */
                                    echo esc_html(sprintf(__('This group has reached its maximum capacity of %s.', WOO_MEMBER_SHARE_TEXT_DOMAIN), strtolower($member_label_plural))); 
                                    ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php endforeach; ?>
        
    <?php endif; ?>
    
</div>

<style>
/* Basic styling for the groups interface */
.woo-member-share-groups {
    margin: 20px 0;
}

.wms-group-container {
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 20px;
    background: #fff;
}

.wms-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
}

.wms-group-title {
    margin: 0;
    font-size: 18px;
}

.wms-member-count {
    font-size: 14px;
    color: #666;
    font-weight: normal;
}

.wms-group-details {
    padding: 20px;
}

.wms-group-details h4,
.wms-group-details h5 {
    margin-top: 25px;
    margin-bottom: 10px;
    color: #333;
}

.wms-group-details h4:first-child,
.wms-group-details h5:first-child {
    margin-top: 0;
}

.wms-customer-controls {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.wms-group-settings {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.wms-members-table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0;
}

.wms-members-table th,
.wms-members-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.wms-members-table th {
    background: #f1f1f1;
    font-weight: 600;
}

.member-type-customer {
    color: #0073aa;
    font-weight: 600;
}

.member-type-subaccount {
    color: #666;
}

.member-status.status-active {
    color: #46b450;
    font-weight: 600;
}

.member-status.status-pending {
    color: #ffb900;
    font-weight: 600;
}

.member-status.status-revoked {
    color: #dc3232;
    font-weight: 600;
}

.status-active {
    color: #46b450;
}

.status-suspended {
    color: #ffb900;
}

.status-expired {
    color: #dc3232;
}

.wms-invite-member {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    margin-top: 15px;
}

.wms-group-full {
    background: #fff3cd;
    padding: 15px;
    border-radius: 4px;
    margin-top: 15px;
    border: 1px solid #ffeaa7;
}

.wms-action-form,
.wms-rename-form,
.wms-invite-form {
    margin: 10px 0;
}

.wms-remove-member-form {
    margin: 0;
}

@media (max-width: 768px) {
    .wms-group-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .wms-group-actions {
        margin-top: 10px;
    }
    
    .wms-members-table {
        font-size: 14px;
    }
    
    .wms-members-table th,
    .wms-members-table td {
        padding: 6px 8px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.wms-toggle-group').on('click', function() {
        var target = $(this).data('target');
        var details = $('#' + target);
        
        if (details.is(':visible')) {
            details.slideUp();
            $(this).text('<?php esc_html_e('Manage', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>');
        } else {
            details.slideDown();
            $(this).text('<?php esc_html_e('Hide', WOO_MEMBER_SHARE_TEXT_DOMAIN); ?>');
        }
    });
});
</script>
