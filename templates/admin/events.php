<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Events\Domain\Event[] $events */
?>
<div class="wrap">
    <h1><?php esc_html_e('Events', 'association-manager'); ?></h1>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('ID', 'association-manager'); ?></th>
                <th><?php esc_html_e('Title', 'association-manager'); ?></th>
                <th><?php esc_html_e('Starts', 'association-manager'); ?></th>
                <th><?php esc_html_e('Status', 'association-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($events)): ?>
            <tr>
                <td colspan="4"><?php esc_html_e('No events yet.', 'association-manager'); ?></td>
            </tr>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td><?php echo esc_html((string) $event->id); ?></td>
                    <td><?php echo esc_html($event->title); ?></td>
                    <td><?php echo esc_html($event->startsAt); ?></td>
                    <td><?php echo esc_html($event->status); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
