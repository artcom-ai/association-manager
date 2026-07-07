<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Events\Domain\Event[] $events */
?>
<div class="am-events">
    <?php if (empty($events)): ?>
        <p><?php esc_html_e('No upcoming events.', 'association-manager'); ?></p>
    <?php else: ?>
        <ul class="am-events__list">
            <?php foreach ($events as $event): ?>
                <li class="am-events__entry">
                    <span class="am-events__title"><?php echo esc_html($event->title); ?></span>
                    <span class="am-events__starts"><?php echo esc_html($event->startsAt); ?></span>
                    <?php if ($event->location !== null): ?>
                        <span class="am-events__location"><?php echo esc_html($event->location); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
