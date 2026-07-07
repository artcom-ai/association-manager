<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Services;

use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\FieldValidationException;
use AssociationManager\Core\Fields\Repositories\FieldValueRepositoryInterface;

defined('ABSPATH') || exit;

final class FieldValueService
{
    public function __construct(
        private readonly FieldRegistry $registry,
        private readonly FieldValueRepositoryInterface $repository,
        private readonly FieldValidator $validator,
    ) {
    }

    /**
     * Validates only the keys present in $submittedValues (partial-update
     * semantics). Unknown keys (not registered for this entity type) are
     * silently ignored rather than rejected.
     *
     * @param array<string, mixed> $submittedValues
     * @return array<string, string[]>
     */
    public function validate(string $entityType, array $submittedValues): array
    {
        $errors = [];

        foreach ($submittedValues as $key => $value) {
            $field = $this->registry->get($entityType, $key);

            if ($field === null) {
                continue;
            }

            $fieldErrors = $this->validator->validate($field, $value);

            if ($fieldErrors !== []) {
                $errors[$key] = $fieldErrors;
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $submittedValues
     */
    public function save(string $entityType, int $entityId, array $submittedValues): void
    {
        $errors = $this->validate($entityType, $submittedValues);

        if ($errors !== []) {
            throw new FieldValidationException($errors);
        }

        foreach ($submittedValues as $key => $value) {
            if ($this->registry->get($entityType, $key) === null) {
                continue;
            }

            $this->repository->set($entityType, $entityId, $key, $this->normalize($value));
        }
    }

    /**
     * @return array<string, string>
     */
    public function valuesFor(string $entityType, int $entityId): array
    {
        return $this->repository->allFor($entityType, $entityId);
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $fileData
     */
    public function handleFileUpload(string $entityType, int $entityId, string $fieldKey, array $fileData): int
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $_FILES[$fieldKey] = $fileData;

        $attachmentId = media_handle_upload($fieldKey, 0);

        if (is_wp_error($attachmentId)) {
            throw new FieldValidationException([$fieldKey => [$attachmentId->get_error_message()]]);
        }

        $this->repository->set($entityType, $entityId, $fieldKey, (string) $attachmentId);

        return $attachmentId;
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
