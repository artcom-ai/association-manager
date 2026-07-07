<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Notifications\Domain\NotificationTemplate[] $templates */
?>
<div class="wrap">
    <h1><?php esc_html_e('Notification Templates', 'association-manager'); ?></h1>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Event', 'association-manager'); ?></th>
                <th><?php esc_html_e('Channel', 'association-manager'); ?></th>
                <th><?php esc_html_e('Subject', 'association-manager'); ?></th>
                <th><?php esc_html_e('Actions', 'association-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($templates)) : ?>
            <tr>
                <td colspan="4"><?php esc_html_e('No templates yet.', 'association-manager'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($templates as $template) : ?>
                <tr>
                    <td><?php echo esc_html($template->eventKey); ?></td>
                    <td><?php echo esc_html($template->channel); ?></td>
                    <td><?php echo esc_html($template->subject); ?></td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(['event_key' => $template->eventKey])); ?>">
                            <?php esc_html_e('Edit', 'association-manager'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
