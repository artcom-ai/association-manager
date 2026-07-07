<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Repositories;

defined('ABSPATH') || exit;

interface FieldValueRepositoryInterface
{
    public function get(string $entityType, int $entityId, string $fieldKey): ?string;

    /**
     * @return array<string, string>
     */
    public function allFor(string $entityType, int $entityId): array;

    public function set(string $entityType, int $entityId, string $fieldKey, ?string $value): void;
}
