<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Certificates\Domain\CertificateTemplate[] $templates */
/** @var ?string $noticeType */
?>
<div class="wrap">
    <h1><?php esc_html_e('Issue Certificate', 'association-manager'); ?></h1>

    <?php if ($noticeType === 'issued') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Certificate issued.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'member_not_found') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('No member found with that ID.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'issue_failed') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('Could not issue the certificate. Please try again.', 'association-manager'); ?></p></div>
    <?php endif; ?>

    <?php if (empty($templates)) : ?>
        <p><?php esc_html_e('No certificate templates configured yet.', 'association-manager'); ?></p>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('association_manager_issue_certificate'); ?>
            <input type="hidden" name="action" value="association_manager_issue_certificate" />
            <table class="form-table">
                <tbody>
                <tr>
                    <th><label for="am-cert-member-id"><?php esc_html_e('Member ID', 'association-manager'); ?></label></th>
                    <td><input type="number" id="am-cert-member-id" name="member_id" min="1" step="1" required /></td>
                </tr>
                <tr>
                    <th><label for="am-cert-type"><?php esc_html_e('Certificate type', 'association-manager'); ?></label></th>
                    <td>
                        <select id="am-cert-type" name="type_key">
                            <?php foreach ($templates as $template) : ?>
                                <option value="<?php echo esc_attr($template->typeKey); ?>"><?php echo esc_html($template->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                </tbody>
            </table>
            <?php submit_button(__('Issue certificate', 'association-manager')); ?>
        </form>
    <?php endif; ?>
</div>
