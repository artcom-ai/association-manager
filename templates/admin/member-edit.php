<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Members\Domain\Member $member */
/** @var \AssociationManager\Core\Fields\FieldDefinition[] $fields */
/** @var array<string, string> $values */
/** @var \AssociationManager\Core\Fields\Admin\FieldRenderer $renderer */
/** @var ?string $noticeType */
/** @var array<string, string> $fileDownloadUrls field key => gated download URL, only for TYPE_FILE fields with a current value */
/** @var array<string, string> $pendingDownloadUrls field key => gated download URL for a not-yet-approved replacement, only for fields with one pending */
?>
<div class="wrap">
    <h1>
        <?php esc_html_e('Edit Member Fields', 'association-manager'); ?>
        #<?php echo esc_html((string) $member->id); ?>
    </h1>

    <?php if ($noticeType === 'saved') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Fields saved.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'identity_saved') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Identity saved.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'invalid') : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Some fields were invalid. Please check and try again.', 'association-manager'); ?></p>
        </div>
    <?php elseif ($noticeType === 'linked') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('WP account linked.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'link_conflict') : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('That WP account is already linked to a different member.', 'association-manager'); ?></p>
        </div>
    <?php elseif ($noticeType === 'link_invalid') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('Please enter a valid WP user ID.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'reset_sent') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Password reset email sent.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'reset_failed') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('Could not send the password reset email.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'account_created') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Portal account created and setup email sent.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'account_created_email_failed') : ?>
        <div class="notice notice-warning"><p><?php esc_html_e('Portal account created, but the setup email could not be sent.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'account_invalid') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('Could not create a portal account with that email.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'pending_approved') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Change approved.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'pending_rejected') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Change rejected. The previous file is unaffected.', 'association-manager'); ?></p></div>
    <?php endif; ?>

    <h2><?php esc_html_e('Identity', 'association-manager'); ?></h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('association_manager_save_member_identity_' . $member->id); ?>
        <input type="hidden" name="action" value="association_manager_save_member_identity" />
        <input type="hidden" name="member_id" value="<?php echo esc_attr((string) $member->id); ?>" />

        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><label for="am-member-first-name"><?php esc_html_e('First name', 'association-manager'); ?></label></th>
                <td><input type="text" id="am-member-first-name" name="first_name" class="regular-text" value="<?php echo esc_attr($member->firstName ?? ''); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="am-member-last-name"><?php esc_html_e('Last name', 'association-manager'); ?></label></th>
                <td><input type="text" id="am-member-last-name" name="last_name" class="regular-text" value="<?php echo esc_attr($member->lastName ?? ''); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="am-member-email"><?php esc_html_e('Email', 'association-manager'); ?></label></th>
                <td><input type="email" id="am-member-email" name="email" class="regular-text" value="<?php echo esc_attr($member->email ?? ''); ?>" /></td>
            </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save identity', 'association-manager'), 'secondary', 'submit', false); ?>
    </form>

    <h2><?php esc_html_e('Member Portal access', 'association-manager'); ?></h2>
    <p>
        <?php if ($member->wpUserId !== null) : ?>
            <?php
            printf(
                /* translators: %d: WP user ID */
                esc_html__('Currently linked to WP user #%d.', 'association-manager'),
                (int) $member->wpUserId
            );
            ?>
        <?php else : ?>
            <?php esc_html_e('Not linked to a WP account - this member cannot access the Member Portal yet.', 'association-manager'); ?>
        <?php endif; ?>
    </p>

    <?php if ($member->wpUserId !== null) : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 1em;">
            <?php wp_nonce_field('association_manager_send_password_reset_' . $member->id); ?>
            <input type="hidden" name="action" value="association_manager_send_password_reset" />
            <input type="hidden" name="member_id" value="<?php echo esc_attr((string) $member->id); ?>" />
            <?php submit_button(__('Send password reset email', 'association-manager'), 'secondary', 'submit', false); ?>
        </form>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 1em;">
            <?php wp_nonce_field('association_manager_create_portal_account_' . $member->id); ?>
            <input type="hidden" name="action" value="association_manager_create_portal_account" />
            <input type="hidden" name="member_id" value="<?php echo esc_attr((string) $member->id); ?>" />
            <label>
                <?php esc_html_e('Account email', 'association-manager'); ?>
                <input type="email" name="account_email" value="<?php echo esc_attr($member->email ?? ''); ?>" required />
            </label>
            <?php submit_button(__('Create portal account & send setup email', 'association-manager'), 'secondary', 'submit', false); ?>
        </form>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('association_manager_link_wp_user_' . $member->id); ?>
        <input type="hidden" name="action" value="association_manager_link_wp_user" />
        <input type="hidden" name="member_id" value="<?php echo esc_attr((string) $member->id); ?>" />
        <label>
            <?php esc_html_e('Or link an existing WP user ID', 'association-manager'); ?>
            <input type="number" name="wp_user_id" min="1" step="1" value="<?php echo esc_attr((string) ($member->wpUserId ?? '')); ?>" />
        </label>
        <?php submit_button(__('Link account', 'association-manager'), 'secondary', 'submit', false); ?>
    </form>

    <?php if (empty($fields)) : ?>
        <p><?php esc_html_e('No custom fields are registered for members.', 'association-manager'); ?></p>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('association_manager_save_member_fields_' . $member->id); ?>
            <input type="hidden" name="action" value="association_manager_save_member_fields" />
            <input type="hidden" name="member_id" value="<?php echo esc_attr((string) $member->id); ?>" />

            <table class="form-table">
                <tbody>
                <?php foreach ($fields as $field) : ?>
                    <?php $renderer->render($field, $values[$field->key] ?? null, $fileDownloadUrls[$field->key] ?? null); ?>
                    <?php if (isset($pendingDownloadUrls[$field->key])) : ?>
                        <tr>
                            <th scope="row"></th>
                            <td>
                                <div class="notice notice-warning inline" style="margin: 0 0 1em;">
                                    <p>
                                        <?php esc_html_e('A replacement is awaiting approval:', 'association-manager'); ?>
                                        <a href="<?php echo esc_url($pendingDownloadUrls[$field->key]); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php esc_html_e('view pending file', 'association-manager'); ?>
                                        </a>
                                    </p>
                                    <p>
                                        <button type="submit" form="am-approve-<?php echo esc_attr($field->key); ?>" class="button button-primary"><?php esc_html_e('Approve', 'association-manager'); ?></button>
                                        <button type="submit" form="am-reject-<?php echo esc_attr($field->key); ?>" class="button"><?php esc_html_e('Reject', 'association-manager'); ?></button>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php submit_button(__('Save fields', 'association-manager')); ?>
        </form>

        <?php foreach ($pendingDownloadUrls as $pendingFieldKey => $pendingUrl) : ?>
            <form id="am-approve-<?php echo esc_attr($pendingFieldKey); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('association_manager_approve_pending_field_' . $member->id . '_' . $pendingFieldKey); ?>
                <input type="hidden" name="action" value="association_manager_approve_pending_field" />
                <input type="hidden" name="member_id" value="<?php echo esc_attr((string) $member->id); ?>" />
                <input type="hidden" name="field_key" value="<?php echo esc_attr($pendingFieldKey); ?>" />
            </form>
            <form id="am-reject-<?php echo esc_attr($pendingFieldKey); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('association_manager_reject_pending_field_' . $member->id . '_' . $pendingFieldKey); ?>
                <input type="hidden" name="action" value="association_manager_reject_pending_field" />
                <input type="hidden" name="member_id" value="<?php echo esc_attr((string) $member->id); ?>" />
                <input type="hidden" name="field_key" value="<?php echo esc_attr($pendingFieldKey); ?>" />
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
