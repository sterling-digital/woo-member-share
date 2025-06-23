<?php
/**
 * Groups management page
 *
 * @package WooMemberShare
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$group_label_plural   = WMS_Admin::get_label( 'group', true );
$member_label_plural  = WMS_Admin::get_label( 'subaccount', true );
?>
<div class="wrap">
    <h1><?php echo esc_html( sprintf( __( '%s Management', WOO_MEMBER_SHARE_TEXT_DOMAIN ), $group_label_plural ) ); ?></h1>

    <table class="widefat fixed striped">
        <thead>
        <tr>
            <th><?php esc_html_e( 'ID', WOO_MEMBER_SHARE_TEXT_DOMAIN ); ?></th>
            <th><?php esc_html_e( 'Group Name', WOO_MEMBER_SHARE_TEXT_DOMAIN ); ?></th>
            <th><?php esc_html_e( 'Owner', WOO_MEMBER_SHARE_TEXT_DOMAIN ); ?></th>
            <th><?php echo esc_html( ucfirst( $member_label_plural ) ); ?></th>
            <th><?php esc_html_e( 'Status', WOO_MEMBER_SHARE_TEXT_DOMAIN ); ?></th>
            <th><?php esc_html_e( 'Created', WOO_MEMBER_SHARE_TEXT_DOMAIN ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if ( ! empty( $groups ) ) : ?>
            <?php foreach ( $groups as $group ) : ?>
                <tr>
                    <td><?php echo esc_html( $group->id ); ?></td>
                    <td><?php echo esc_html( $group->group_name ); ?></td>
                    <td>
                        <?php
                        $owner = get_user_by( 'id', $group->customer_user_id );
                        echo esc_html( $owner ? $owner->display_name : '' );
                        ?>
                    </td>
                    <td>
                        <?php
                        $members = WMS_Database::get_group_members( $group->id );
                        echo esc_html( count( $members ) . '/' . $group->max_subaccounts );
                        ?>
                    </td>
                    <td><?php echo esc_html( ucfirst( $group->status ) ); ?></td>
                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $group->created_date ) ) ); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="6"><?php esc_html_e( 'No groups found.', WOO_MEMBER_SHARE_TEXT_DOMAIN ); ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
