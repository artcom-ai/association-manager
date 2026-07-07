<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<int, array{member_number: ?string, membership_type: ?string, joined_at: ?string}> $entries */
?>
<div class="am-directory">
    <?php if (empty($entries)): ?>
        <p><?php esc_html_e('No members to display yet.', 'association-manager'); ?></p>
    <?php else: ?>
        <ul class="am-directory__list">
            <?php foreach ($entries as $entry): ?>
                <li class="am-directory__entry">
                    <span class="am-directory__number"><?php echo esc_html($entry['member_number'] ?? '—'); ?></span>
                    <span class="am-directory__type"><?php echo esc_html($entry['membership_type'] ?? '—'); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
