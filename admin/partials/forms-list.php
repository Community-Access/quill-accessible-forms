<?php
/**
 * Forms List Page Template
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$forms = QAF_Form::get_all();
?>
<div class="wrap qaf-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'QUILL Accessible Forms', 'quill-accessible-forms' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=qaf-form-builder' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Add New', 'quill-accessible-forms' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( empty( $forms ) ) : ?>
        <div class="qaf-no-forms">
            <h2><?php esc_html_e( 'No forms yet', 'quill-accessible-forms' ); ?></h2>
            <p><?php esc_html_e( 'Create your first accessible form to get started.', 'quill-accessible-forms' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=qaf-form-builder' ) ); ?>" class="button button-primary button-hero">
                <?php esc_html_e( 'Create Your First Form', 'quill-accessible-forms' ); ?>
            </a>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped" role="table">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-title"><?php esc_html_e( 'Title', 'quill-accessible-forms' ); ?></th>
                    <th scope="col" class="manage-column column-shortcode"><?php esc_html_e( 'Shortcode', 'quill-accessible-forms' ); ?></th>
                    <th scope="col" class="manage-column column-entries"><?php esc_html_e( 'Entries', 'quill-accessible-forms' ); ?></th>
                    <th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'quill-accessible-forms' ); ?></th>
                    <th scope="col" class="manage-column column-date"><?php esc_html_e( 'Date', 'quill-accessible-forms' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $forms as $form ) : ?>
                <tr>
                    <td class="column-title">
                        <strong>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=qaf-form-builder&form_id=' . $form->id ) ); ?>">
                                <?php echo esc_html( $form->title ?: __( '(No title)', 'quill-accessible-forms' ) ); ?>
                            </a>
                        </strong>
                        <div class="row-actions">
                            <span class="edit">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=qaf-form-builder&form_id=' . $form->id ) ); ?>">
                                    <?php esc_html_e( 'Edit', 'quill-accessible-forms' ); ?>
                                </a> |
                            </span>
                            <span class="entries">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=qaf-entries&form_id=' . $form->id ) ); ?>">
                                    <?php esc_html_e( 'Entries', 'quill-accessible-forms' ); ?>
                                </a> |
                            </span>
                            <span class="duplicate">
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=qaf-forms&action=duplicate&form_id=' . $form->id ), 'qaf_duplicate_form' ) ); ?>">
                                    <?php esc_html_e( 'Duplicate', 'quill-accessible-forms' ); ?>
                                </a> |
                            </span>
                            <span class="trash">
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=qaf-forms&action=trash&form_id=' . $form->id ), 'qaf_trash_form' ) ); ?>" class="submitdelete">
                                    <?php esc_html_e( 'Trash', 'quill-accessible-forms' ); ?>
                                </a>
                            </span>
                        </div>
                    </td>
                    <td class="column-shortcode">
                        <code>[qaf_form id="<?php echo esc_attr( $form->id ); ?>"]</code>
                        <button type="button" class="button-link qaf-copy-shortcode" data-shortcode='[qaf_form id="<?php echo esc_attr( $form->id ); ?>"]' aria-label="<?php esc_attr_e( 'Copy shortcode to clipboard', 'quill-accessible-forms' ); ?>">
                            <?php esc_html_e( 'Copy', 'quill-accessible-forms' ); ?>
                        </button>
                    </td>
                    <td class="column-entries">
                        <?php echo esc_html( $form->get_entry_count() ); ?>
                    </td>
                    <td class="column-status">
                        <span class="qaf-status qaf-status-<?php echo esc_attr( $form->status ); ?>">
                            <?php
                            $statuses = array(
                                'draft'    => __( 'Draft', 'quill-accessible-forms' ),
                                'active'   => __( 'Active', 'quill-accessible-forms' ),
                                'inactive' => __( 'Inactive', 'quill-accessible-forms' ),
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
