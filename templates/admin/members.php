<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Members\Domain\Member[] $members */
?>
<div class="wrap">
    <h1><?php esc_html_e('Members', 'association-manager'); ?></h1>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('ID', 'association-manager'); ?></th>
                <th><?php esc_html_e('Member #', 'association-manager'); ?></th>
                <th><?php esc_html_e('Status', 'association-manager'); ?></th>
                <th><?php esc_html_e('Membership type', 'association-manager'); ?></th>
                <th><?php esc_html_e('Expires', 'association-manager'); ?></th>
                <th><?php esc_html_e('Actions', 'association-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($members)): ?>
            <tr>
                <td colspan="6"><?php esc_html_e('No members yet.', 'association-manager'); ?></td>
            </tr>
        <?php else: ?>
            <?php foreach ($members as $member): ?>
                <tr>
                    <td><?php echo esc_html((string) $member->id); ?></td>
                    <td><?php echo esc_html($member->memberNumber ?? '—'); ?></td>
                    <td><?php echo esc_html($member->status); ?></td>
                    <td><?php echo esc_html($member->membershipType ?? '—'); ?></td>
                    <td><?php echo esc_html($member->expiresAt ?? '—'); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=association-manager-member-edit&id=' . $member->id)); ?>">
                            <?php esc_html_e('Edit fields', 'association-manager'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
