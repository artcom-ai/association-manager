<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var string $eventKey */
/** @var \AssociationManager\Modules\Notifications\Domain\NotificationTemplate|null $template */
/** @var ?string $noticeType */
?>
<div class="wrap">
    <h1>
        <?php esc_html_e('Edit Notification Template', 'association-manager'); ?>
        &mdash; <?php echo esc_html($eventKey); ?>
    </h1>

    <p>
        <a href="<?php echo esc_url(remove_query_arg(['event_key', 'am_notice'])); ?>">
            &larr; <?php esc_html_e('Back to templates', 'association-manager'); ?>
        </a>
    </p>

    <?php if ($noticeType === 'saved') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Template saved.', 'association-manager'); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('association_manager_save_notification_template_' . $eventKey); ?>
        <input type="hidden" name="action" value="association_manager_save_notification_template" />
        <input type="hidden" name="event_key" value="<?php echo esc_attr($eventKey); ?>" />

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="am-template-subject"><?php esc_html_e('Subject', 'association-manager'); ?></label></th>
                    <td>
                        <input
                            type="text"
                            id="am-template-subject"
                            name="subject"
                            class="regular-text"
                            value="<?php echo esc_attr($template->subject ?? ''); ?>"
                        />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="am-template-body"><?php esc_html_e('Body', 'association-manager'); ?></label></th>
                    <td>
                        <textarea id="am-template-body" name="body" class="large-text" rows="8"><?php echo esc_textarea($template->body ?? ''); ?></textarea>
                        <p class="description"><?php esc_html_e('Available placeholders depend on the event; e.g. {member_number}, {membership_type}, {expires_at}.', 'association-manager'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save template', 'association-manager')); ?>
    </form>
</div>
