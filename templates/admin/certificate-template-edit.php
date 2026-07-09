<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var string $typeKey */
/** @var \AssociationManager\Modules\Certificates\Domain\CertificateTemplate|null $template */
/** @var ?string $noticeType */
?>
<div class="wrap">
    <h1>
        <?php esc_html_e('Edit Certificate Template', 'association-manager'); ?>
        &mdash; <?php echo esc_html($typeKey); ?>
    </h1>

    <p>
        <a href="<?php echo esc_url(remove_query_arg(['type_key', 'am_notice'])); ?>">
            &larr; <?php esc_html_e('Back to templates', 'association-manager'); ?>
        </a>
    </p>

    <?php if ($noticeType === 'saved') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Template saved.', 'association-manager'); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('association_manager_save_certificate_template_' . $typeKey); ?>
        <input type="hidden" name="action" value="association_manager_save_certificate_template" />
        <input type="hidden" name="type_key" value="<?php echo esc_attr($typeKey); ?>" />

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="am-cert-name"><?php esc_html_e('Name', 'association-manager'); ?></label></th>
                    <td>
                        <input
                            type="text"
                            id="am-cert-name"
                            name="name"
                            class="regular-text"
                            value="<?php echo esc_attr($template->name ?? ''); ?>"
                        />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="am-cert-html"><?php esc_html_e('HTML body', 'association-manager'); ?></label></th>
                    <td>
                        <textarea id="am-cert-html" name="html_body" class="large-text code" rows="16"><?php echo esc_textarea($template->htmlBody ?? ''); ?></textarea>
                        <p class="description"><?php esc_html_e('Rendered to PDF at issuance. Placeholders: {member_number}, {membership_type}, {issued_at}.', 'association-manager'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save template', 'association-manager')); ?>
    </form>
</div>
