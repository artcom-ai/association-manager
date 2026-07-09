<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Members\Domain\Member $member */
/** @var \AssociationManager\Modules\Documents\Domain\Document[] $documents */
/** @var \AssociationManager\Modules\Certificates\Domain\Certificate[] $certificates */
?>
<div class="am-portal">
    <section class="am-portal-dashboard">
        <h2><?php esc_html_e('Membership', 'association-manager'); ?></h2>
        <table>
            <tbody>
            <tr>
                <th><?php esc_html_e('Member #', 'association-manager'); ?></th>
                <td><?php echo esc_html($member->memberNumber ?? '—'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Status', 'association-manager'); ?></th>
                <td><?php echo esc_html($member->status); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Membership type', 'association-manager'); ?></th>
                <td><?php echo esc_html($member->membershipType ?? '—'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Expires', 'association-manager'); ?></th>
                <td><?php echo esc_html($member->expiresAt ?? '—'); ?></td>
            </tr>
            </tbody>
        </table>
    </section>

    <section class="am-portal-documents">
        <h2><?php esc_html_e('Documents', 'association-manager'); ?></h2>
        <?php if (empty($documents)) : ?>
            <p><?php esc_html_e('No documents available.', 'association-manager'); ?></p>
        <?php else : ?>
            <ul>
                <?php foreach ($documents as $document) : ?>
                    <li>
                        <a href="<?php echo esc_url(wp_get_attachment_url($document->wpAttachmentId) ?: '#'); ?>" target="_blank">
                            <?php echo esc_html($document->title); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="am-portal-certificates">
        <h2><?php esc_html_e('Certificates', 'association-manager'); ?></h2>
        <?php if (empty($certificates)) : ?>
            <p><?php esc_html_e('No certificates issued yet.', 'association-manager'); ?></p>
        <?php else : ?>
            <ul>
                <?php foreach ($certificates as $certificate) : ?>
                    <li>
                        <a href="<?php echo esc_url(wp_get_attachment_url($certificate->wpAttachmentId) ?: '#'); ?>" target="_blank">
                            <?php echo esc_html($certificate->typeKey); ?> &mdash; <?php echo esc_html($certificate->issuedAt); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
