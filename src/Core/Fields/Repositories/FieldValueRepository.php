<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Repositories;

use AssociationManager\Database\DatabaseManager;

defined('ABSPATH') || exit;

final class FieldValueRepository implements FieldValueRepositoryInterface
{
    public function get(string $entityType, int $entityId, string $fieldKey): ?string
    {
        global $wpdb;

        $table = DatabaseManager::table('field_values');

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT value FROM {$table} WHERE entity_type = %s AND entity_id = %d AND field_key = %s",
            $entityType,
            $entityId,
            $fieldKey
        ));

        return $value === null ? null : (string) $value;
    }

    /**
     * @return array<string, string>
     */
    public function allFor(string $entityType, int $entityId): array
    {
        global $wpdb;

        $table = DatabaseManager::table('field_values');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT field_key, value FROM {$table} WHERE entity_type = %s AND entity_id = %d",
                $entityType,
                $entityId
            ),
            ARRAY_A
        );

        $values = [];

        foreach ($rows ?: [] as $row) {
            $values[$row['field_key']] = $row['value'];
        }

        return $values;
    }

    public function set(string $entityType, int $entityId, string $fieldKey, ?string $value): void
    {
        global $wpdb;

        $table = DatabaseManager::table('field_values');

        if ($value === null) {
            $wpdb->delete(
                $table,
                ['entity_type' => $entityType, 'entity_id' => $entityId, 'field_key' => $fieldKey],
                ['%s', '%d', '%s']
            );

            return;
        }

        $now = current_time('mysql');

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (entity_type, entity_id, field_key, value, created_at, updated_at)
             VALUES (%s, %d, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)",
            $entityType,
            $entityId,
            $fieldKey,
            $value,
            $now,
            $now
        ));
    }
}
