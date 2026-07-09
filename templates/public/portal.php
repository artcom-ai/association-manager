<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Members\Domain\Member $member */
/** @var \AssociationManager\Modules\Documents\Domain\Document[] $documents */
/** @var \AssociationManager\Modules\Certificates\Domain\Certificate[] $certificates */
/** @var \AssociationManager\Modules\Notifications\Domain\QueuedNotification[] $notifications */
/** @var array<int, array{field: \AssociationManager\Core\Fields\FieldDefinition, value: ?string}> $customFields */

$notificationStatusLabels = [
    'pending' => __('Pending', 'association-manager'),
    'sent' => __('New', 'association-manager'),
    'failed' => __('Failed', 'association-manager'),
    'read' => __('Read', 'association-manager'),
];
?>
<div class="am-portal">
    <section class="am-portal-profile">
        <h2><?php esc_html_e('Profile', 'association-manager'); ?></h2>
        <table>
            <tbody>
            <tr>
                <th><?php esc_html_e('Member #', 'association-manager'); ?></th>
                <td><?php echo esc_html($member->memberNumber ?? '—'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Email', 'association-manager'); ?></th>
                <td><?php echo esc_html($member->email ?? '—'); ?></td>
            </tr>
            <?php foreach ($customFields as $row) : ?>
                <tr>
                    <th><?php echo esc_html($row['field']->label); ?></th>
                    <td><?php echo esc_html($row['value'] ?? '—'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description"><?php esc_html_e('To update your profile, please contact the association.', 'association-manager'); ?></p>
    </section>

    <section class="am-portal-status">
        <h2><?php esc_html_e('Membership status', 'association-manager'); ?></h2>
        <table>
            <tbody>
            <tr>
                <th><?php esc_html_e('Status', 'association-manager'); ?></th>
                <td><?php echo esc_html($member->status); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Membership type', 'association-manager'); ?></th>
                <td><?php echo esc_html($member->membershipType ?? '—'); ?></td>
            </tr>
            </tbody>
        </table>
    </section>

    <section class="am-portal-summary">
        <h2><?php esc_html_e('Membership summary', 'association-manager'); ?></h2>
        <table>
            <tbody>
            <tr>
                <th><?php esc_html_e('Joined', 'association-manager'); ?></th>
                <td><?php echo esc_html($member->joinedAt ?? '—'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Approved', 'association-manager'); ?></th>
                <td><?php echo esc_html($member->approvedAt ?? '—'); ?></td>
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

    <section class="am-portal-notifications">
        <h2><?php esc_html_e('Notifications', 'association-manager'); ?></h2>
        <?php if (empty($notifications)) : ?>
            <p><?php esc_html_e('No notifications yet.', 'association-manager'); ?></p>
        <?php else : ?>
            <ul>
                <?php foreach ($notifications as $notification) : ?>
                    <li>
                        <strong><?php echo esc_html($notificationStatusLabels[$notification->status] ?? $notification->status); ?></strong>
                        &mdash; <?php echo esc_html($notification->subject); ?>
                        &mdash; <?php echo esc_html($notification->scheduledAt); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
