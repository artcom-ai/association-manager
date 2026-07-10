<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Members\Domain\Member $member */
/** @var \AssociationManager\Modules\Documents\Domain\Document[] $documents */
/** @var \AssociationManager\Modules\Certificates\Domain\Certificate[] $certificates */
/** @var \AssociationManager\Modules\Notifications\Domain\QueuedNotification[] $notifications */
/** @var array<int, array{field: \AssociationManager\Core\Fields\FieldDefinition, value: ?string}> $customFields */
/** @var bool $canEditProfile */
/** @var \AssociationManager\Core\Fields\Admin\FieldRenderer $fieldRenderer */
/** @var array<string, string[]> $profileErrors */
/** @var array<int, array{field: \AssociationManager\Core\Fields\FieldDefinition, value: ?string, pending: ?string, downloadUrl: ?string, pendingDownloadUrl: ?string}> $editableFileFields */

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

        <?php if ($canEditProfile) : ?>
            <?php if (!empty($profileErrors)) : ?>
                <div class="am-portal-errors">
                    <ul>
                        <?php foreach ($profileErrors as $fieldErrors) : ?>
                            <?php foreach ($fieldErrors as $message) : ?>
                                <li><?php echo esc_html($message); ?></li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('association_manager_submit_profile'); ?>
                <input type="hidden" name="action" value="association_manager_submit_profile" />
                <table class="form-table">
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
                        <?php $fieldRenderer->render($row['field'], $row['value']); ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description"><?php esc_html_e('Fill in your details, then submit for review. An administrator will approve your membership.', 'association-manager'); ?></p>
                <p><button type="submit" class="button button-primary"><?php esc_html_e('Submit for approval', 'association-manager'); ?></button></p>
            </form>
        <?php else : ?>
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
        <?php endif; ?>
    </section>

    <?php if (!$canEditProfile) : ?>
    <section class="am-portal-file-fields">
        <h2><?php esc_html_e('Documents on file', 'association-manager'); ?></h2>
        <?php /* Only shown once the main Profile form above is locked (member already active) - during onboarding, file fields are already reachable there; see ADR-023 addendum. */ ?>
        <?php if (empty($editableFileFields)) : ?>
            <p><?php esc_html_e('No document fields are set up for members.', 'association-manager'); ?></p>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('association_manager_update_field_file'); ?>
                <input type="hidden" name="action" value="association_manager_update_field_file" />
                <table class="form-table">
                    <tbody>
                    <?php foreach ($editableFileFields as $row) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($row['field']->label); ?></th>
                            <td>
                                <?php if ($row['downloadUrl'] !== null) : ?>
                                    <p>
                                        <a href="<?php echo esc_url($row['downloadUrl']); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php esc_html_e('View current file', 'association-manager'); ?>
                                        </a>
                                    </p>
                                <?php else : ?>
                                    <p><?php esc_html_e('No file on file yet.', 'association-manager'); ?></p>
                                <?php endif; ?>
                                <?php if ($row['pendingDownloadUrl'] !== null) : ?>
                                    <p class="description">
                                        <?php esc_html_e('A replacement is awaiting admin approval -', 'association-manager'); ?>
                                        <a href="<?php echo esc_url($row['pendingDownloadUrl']); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php esc_html_e('view it', 'association-manager'); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                                <input type="file" name="custom_fields[<?php echo esc_attr($row['field']->key); ?>]" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="submit" class="button button-primary"><?php esc_html_e('Upload', 'association-manager'); ?></button></p>
            </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

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
