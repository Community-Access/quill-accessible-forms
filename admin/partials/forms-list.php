<?php
/**
 * Forms List Page Template
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$forms = BAF_Form::get_all();
?>
<div class="wrap baf-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'BITS Accessible Forms', 'bits-accessible-forms' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=baf-form-builder' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Add New', 'bits-accessible-forms' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( empty( $forms ) ) : ?>
        <div class="baf-no-forms">
            <h2><?php esc_html_e( 'No forms yet', 'bits-accessible-forms' ); ?></h2>
            <p><?php esc_html_e( 'Create your first accessible form to get started.', 'bits-accessible-forms' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=baf-form-builder' ) ); ?>" class="button button-primary button-hero">
                <?php esc_html_e( 'Create Your First Form', 'bits-accessible-forms' ); ?>
            </a>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped" role="table">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-title"><?php esc_html_e( 'Title', 'bits-accessible-forms' ); ?></th>
                    <th scope="col" class="manage-column column-shortcode"><?php esc_html_e( 'Shortcode', 'bits-accessible-forms' ); ?></th>
                    <th scope="col" class="manage-column column-entries"><?php esc_html_e( 'Entries', 'bits-accessible-forms' ); ?></th>
                    <th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'bits-accessible-forms' ); ?></th>
                    <th scope="col" class="manage-column column-date"><?php esc_html_e( 'Date', 'bits-accessible-forms' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $forms as $form ) : ?>
                <tr>
                    <td class="column-title">
                        <strong>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=baf-form-builder&form_id=' . $form->id ) ); ?>">
                                <?php echo esc_html( $form->title ?: __( '(No title)', 'bits-accessible-forms' ) ); ?>
                            </a>
                        </strong>
                        <div class="row-actions">
                            <span class="edit">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=baf-form-builder&form_id=' . $form->id ) ); ?>">
                                    <?php esc_html_e( 'Edit', 'bits-accessible-forms' ); ?>
                                </a> |
                            </span>
                            <span class="entries">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=baf-entries&form_id=' . $form->id ) ); ?>">
                                    <?php esc_html_e( 'Entries', 'bits-accessible-forms' ); ?>
                                </a> |
                            </span>
                            <span class="duplicate">
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=baf-forms&action=duplicate&form_id=' . $form->id ), 'baf_duplicate_form' ) ); ?>">
                                    <?php esc_html_e( 'Duplicate', 'bits-accessible-forms' ); ?>
                                </a> |
                            </span>
                            <span class="trash">
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=baf-forms&action=trash&form_id=' . $form->id ), 'baf_trash_form' ) ); ?>" class="submitdelete">
                                    <?php esc_html_e( 'Trash', 'bits-accessible-forms' ); ?>
                                </a>
                            </span>
                        </div>
                    </td>
                    <td class="column-shortcode">
                        <code>[baf_form id="<?php echo esc_attr( $form->id ); ?>"]</code>
                        <button type="button" class="button-link baf-copy-shortcode" data-shortcode='[baf_form id="<?php echo esc_attr( $form->id ); ?>"]' aria-label="<?php esc_attr_e( 'Copy shortcode to clipboard', 'bits-accessible-forms' ); ?>">
                            <?php esc_html_e( 'Copy', 'bits-accessible-forms' ); ?>
                        </button>
                    </td>
                    <td class="column-entries">
                        <?php echo esc_html( $form->get_entry_count() ); ?>
                    </td>
                    <td class="column-status">
                        <span class="baf-status baf-status-<?php echo esc_attr( $form->status ); ?>">
                            <?php
                            $statuses = array(
                                'draft'    => __( 'Draft', 'bits-accessible-forms' ),
                                'active'   => __( 'Active', 'bits-accessible-forms' ),
                                'inactive' => __( 'Inactive', 'bits-accessible-forms' ),
                            );
                            echo esc_html( $statuses[ $form->status ] ?? $form->status );
                            ?>
                        </span>
                    </td>
                    <td class="column-date">
                        <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $form->created_at ) ) ); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
