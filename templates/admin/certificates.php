<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Certificates\Domain\CertificateTemplate[] $templates */
/** @var \AssociationManager\Modules\Certificates\Domain\Certificate[] $certificates */
/** @var ?string $noticeType */

$statusLabels = [
    'draft' => __('Draft', 'association-manager'),
    'issued' => __('Issued', 'association-manager'),
    'revoked' => __('Revoked', 'association-manager'),
];
?>
<div class="wrap">
    <h1><?php esc_html_e('Certificates', 'association-manager'); ?></h1>

    <?php if ($noticeType === 'created') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Certificate created as a draft.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'issued') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Certificate issued.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'revoked') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Certificate revoked.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'member_not_found') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('No member found with that ID.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'action_failed') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('That action could not be completed. Please try again.', 'association-manager'); ?></p></div>
    <?php endif; ?>

    <h2><?php esc_html_e('Create a certificate', 'association-manager'); ?></h2>
    <?php if (empty($templates)) : ?>
        <p><?php esc_html_e('No certificate templates configured yet.', 'association-manager'); ?></p>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('association_manager_create_certificate'); ?>
            <input type="hidden" name="action" value="association_manager_create_certificate" />
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
            <p class="description"><?php esc_html_e('This creates a draft certificate - it is not visible to the member until you click Issue below.', 'association-manager'); ?></p>
            <?php submit_button(__('Create draft', 'association-manager')); ?>
        </form>
    <?php endif; ?>

    <h2><?php esc_html_e('All certificates', 'association-manager'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Member ID', 'association-manager'); ?></th>
                <th><?php esc_html_e('Type', 'association-manager'); ?></th>
                <th><?php esc_html_e('Status', 'association-manager'); ?></th>
                <th><?php esc_html_e('Issued', 'association-manager'); ?></th>
                <th><?php esc_html_e('Actions', 'association-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($certificates)) : ?>
            <tr>
                <td colspan="5"><?php esc_html_e('No certificates yet.', 'association-manager'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($certificates as $certificate) : ?>
                <tr>
                    <td><?php echo esc_html((string) $certificate->memberId); ?></td>
                    <td><?php echo esc_html($certificate->typeKey); ?></td>
                    <td><?php echo esc_html($statusLabels[$certificate->status] ?? $certificate->status); ?></td>
                    <td><?php echo esc_html($certificate->issuedAt); ?></td>
                    <?php
                    $downloadUrl = wp_nonce_url(
                        add_query_arg(
                            [
                                'action' => 'association_manager_download_certificate',
                                'certificate_id' => $certificate->id,
                            ],
                            admin_url('admin-post.php')
                        ),
                        'association_manager_download_certificate_' . $certificate->id
                    );
                    ?>
                    <td>
                        <a href="<?php echo esc_url($downloadUrl); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Download', 'association-manager'); ?>
                        </a>
                        <?php if ($certificate->status === 'draft') : ?>
                            |
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                                <?php wp_nonce_field('association_manager_issue_certificate_' . $certificate->id); ?>
                                <input type="hidden" name="action" value="association_manager_issue_certificate" />
                                <input type="hidden" name="certificate_id" value="<?php echo esc_attr((string) $certificate->id); ?>" />
                                <button type="submit" class="button-link"><?php esc_html_e('Issue', 'association-manager'); ?></button>
                            </form>
                        <?php elseif ($certificate->status === 'issued') : ?>
                            |
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                                <?php wp_nonce_field('association_manager_revoke_certificate_' . $certificate->id); ?>
                                <input type="hidden" name="action" value="association_manager_revoke_certificate" />
                                <input type="hidden" name="certificate_id" value="<?php echo esc_attr((string) $certificate->id); ?>" />
                                <button type="submit" class="button-link"><?php esc_html_e('Revoke', 'association-manager'); ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
