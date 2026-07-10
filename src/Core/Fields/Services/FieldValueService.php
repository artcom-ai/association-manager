<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Services;

use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\FieldValidationException;
use AssociationManager\Core\Fields\Repositories\FieldValueRepositoryInterface;

defined( 'ABSPATH' ) || exit;

final class FieldValueService {

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
    public function validate( string $entityType, array $submittedValues ): array {
        $errors = [];

        foreach ( $submittedValues as $key => $value ) {
            $field = $this->registry->get( $entityType, $key );

            if ( $field === null ) {
                continue;
            }

            $fieldErrors = $this->validator->validate( $field, $value );

            if ( $fieldErrors !== [] ) {
                $errors[ $key ] = $fieldErrors;
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $submittedValues
     */
    public function save( string $entityType, int $entityId, array $submittedValues ): void {
        $errors = $this->validate( $entityType, $submittedValues );

        if ( $errors !== [] ) {
            throw new FieldValidationException( $errors );
        }

        foreach ( $submittedValues as $key => $value ) {
            if ( $this->registry->get( $entityType, $key ) === null ) {
                continue;
            }

            $this->repository->set( $entityType, $entityId, $key, $this->normalize( $value ) );
        }
    }

    /**
     * @return array<string, string>
     */
    public function valuesFor( string $entityType, int $entityId ): array {
        return $this->repository->allFor( $entityType, $entityId );
    }

    /**
     * A form rendering TYPE_FILE fields via FieldRenderer needs both this
     * and save() called together - a plain text field's value arrives in
     * $submittedValues via $_POST, but a file field's value arrives via
     * $_FILES instead, in PHP's own array-named-input shape (name/type/
     * tmp_name/error/size each keyed by field key, not one sub-array per
     * field). This is the one place that shape gets untangled, so every
     * caller (the admin Edit Fields page, the Portal profile form) gets
     * identical handling instead of duplicating it.
     *
     * A field key present in $uploadedFiles is excluded from the save()
     * pass below - handleFileUpload() already persisted its value
     * directly, so passing it through save() too would just be a
     * redundant duplicate write of the same value.
     *
     * @param array<string, mixed> $submittedValues
     * @param array{name?: mixed, type?: mixed, tmp_name?: mixed, error?: mixed, size?: mixed}|null $rawFileUploads the "custom_fields" sub-array of $_FILES, or null if the form had no file inputs at all - typed loosely on purpose, since this is raw HTTP input, not a shape PHP or a caller can actually guarantee
     */
    public function saveWithUploads( string $entityType, int $entityId, array $submittedValues, ?array $rawFileUploads ): void {
        $uploadedFiles = $this->reshapeFileUploads( $rawFileUploads );

        foreach ( $uploadedFiles as $fieldKey => $fileData ) {
            $this->handleFileUpload( $entityType, $entityId, $fieldKey, $fileData );
        }

        $this->save( $entityType, $entityId, array_diff_key( $submittedValues, $uploadedFiles ) );
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $fileData
     */
    public function handleFileUpload( string $entityType, int $entityId, string $fieldKey, array $fileData ): int {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $_FILES[ $fieldKey ] = $fileData;

        $attachmentId = media_handle_upload( $fieldKey, 0 );

        if ( is_wp_error( $attachmentId ) ) {
            throw new FieldValidationException( [ $fieldKey => [ $attachmentId->get_error_message() ] ] );
        }

        $this->repository->set( $entityType, $entityId, $fieldKey, (string) $attachmentId );

        return $attachmentId;
    }

    /**
     * @param array{name?: mixed, type?: mixed, tmp_name?: mixed, error?: mixed, size?: mixed}|null $rawFileUploads
     * @return array<string, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private function reshapeFileUploads( ?array $rawFileUploads ): array {
        if ( $rawFileUploads === null || ! isset( $rawFileUploads['name'] ) || ! is_array( $rawFileUploads['name'] ) ) {
            return [];
        }

        $files = [];

        foreach ( $rawFileUploads['name'] as $key => $name ) {
            $error = $rawFileUploads['error'][ $key ] ?? UPLOAD_ERR_NO_FILE;

            // Browsers submit an empty file input as an entry with no
            // name and UPLOAD_ERR_NO_FILE, not by omitting the key -
            // this is "the member didn't choose a file", not an error.
            if ( $name === '' || $error === UPLOAD_ERR_NO_FILE ) {
                continue;
            }

            $files[ $key ] = [
                'name'     => $name,
                'type'     => $rawFileUploads['type'][ $key ] ?? '',
                'tmp_name' => $rawFileUploads['tmp_name'][ $key ] ?? '',
                'error'    => $error,
                'size'     => $rawFileUploads['size'][ $key ] ?? 0,
            ];
        }

        return $files;
    }

    private function normalize( mixed $value ): ?string {
        if ( $value === null ) {
            return null;
        }

        if ( is_bool( $value ) ) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
