<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Repositories;

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Modules\Notifications\Domain\NotificationTemplate;

defined('ABSPATH') || exit;

final class NotificationTemplateRepository implements NotificationTemplateRepositoryInterface
{
    public function find(string $eventKey, string $channel): ?NotificationTemplate
    {
        global $wpdb;

        $table = DatabaseManager::table('notification_templates');

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE event_key = %s AND channel = %s",
                $eventKey,
                $channel
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return NotificationTemplate[]
     */
    public function all(): array
    {
        global $wpdb;

        $table = DatabaseManager::table('notification_templates');

        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY event_key ASC", ARRAY_A);

        return array_map(fn (array $row): NotificationTemplate => $this->hydrate($row), $rows ?: []);
    }

    public function save(NotificationTemplate $template): void
    {
        global $wpdb;

        $table = DatabaseManager::table('notification_templates');
        $now = current_time('mysql');

        $existing = $this->find($template->eventKey, $template->channel);

        if ($existing === null) {
            $wpdb->insert(
                $table,
                [
                    'event_key' => $template->eventKey,
                    'channel' => $template->channel,
                    'subject' => $template->subject,
                    'body' => $template->body,
                    'updated_at' => $now,
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

            return;
        }

        $wpdb->update(
            $table,
            [
                'subject' => $template->subject,
                'body' => $template->body,
                'updated_at' => $now,
            ],
            ['id' => $existing->id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    private function hydrate(array $row): NotificationTemplate
    {
        return new NotificationTemplate(
            id: (int) $row['id'],
            eventKey: $row['event_key'],
            channel: $row['channel'],
            subject: $row['subject'],
            body: $row['body'],
        );
    }
}
