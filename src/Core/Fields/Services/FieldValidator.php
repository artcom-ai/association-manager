<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Services;

use AssociationManager\Core\Fields\FieldDefinition;

defined('ABSPATH') || exit;

/**
 * Pure validation - one field, one value, no storage or WordPress calls.
 */
final class FieldValidator
{
    /**
     * @return string[]
     */
    public function validate(FieldDefinition $field, mixed $value): array
    {
        $isEmpty = $value === null || $value === '';

        if ($field->required && $isEmpty) {
            return ["{$field->label} is required."];
        }

        if ($isEmpty) {
            return [];
        }

        return match ($field->type) {
            FieldDefinition::TYPE_TEXT, FieldDefinition::TYPE_TEXTAREA => $this->validateLength($field, (string) $value),
            FieldDefinition::TYPE_NUMBER => $this->validateNumber($field, $value),
            FieldDefinition::TYPE_DATE => $this->validateDate($field, (string) $value),
            FieldDefinition::TYPE_SELECT => $this->validateSelect($field, $value),
            FieldDefinition::TYPE_CHECKBOX => $this->validateCheckbox($field, $value),
            FieldDefinition::TYPE_FILE => $this->validateFile($field, $value),
            FieldDefinition::TYPE_LOCATION => $this->validateLocation($field, (string) $value),
            default => [],
        };
    }

    /**
     * @return string[]
     */
    private function validateLength(FieldDefinition $field, string $value): array
    {
        $errors = [];
        $length = mb_strlen($value);

        if ($field->minLength !== null && $length < $field->minLength) {
            $errors[] = "{$field->label} must be at least {$field->minLength} characters.";
        }

        if ($field->maxLength !== null && $length > $field->maxLength) {
            $errors[] = "{$field->label} must be at most {$field->maxLength} characters.";
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private function validateNumber(FieldDefinition $field, mixed $value): array
    {
        if (!is_numeric($value)) {
            return ["{$field->label} must be a number."];
        }

        $errors = [];
        $number = (float) $value;

        if ($field->minValue !== null && $number < $field->minValue) {
            $errors[] = "{$field->label} must be at least {$field->minValue}.";
        }

        if ($field->maxValue !== null && $number > $field->maxValue) {
            $errors[] = "{$field->label} must be at most {$field->maxValue}.";
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private function validateDate(FieldDefinition $field, string $value): array
    {
        return strtotime($value) === false ? ["{$field->label} must be a valid date."] : [];
    }

    /**
     * @return string[]
     */
    private function validateSelect(FieldDefinition $field, mixed $value): array
    {
        if ($field->options !== null && !array_key_exists((string) $value, $field->options)) {
            return ["{$field->label} must be one of the allowed values."];
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function validateCheckbox(FieldDefinition $field, mixed $value): array
    {
        return in_array($value, ['0', '1', 0, 1, true, false], true)
            ? []
            : ["{$field->label} must be a boolean value."];
    }

    /**
     * @return string[]
     */
    private function validateFile(FieldDefinition $field, mixed $value): array
    {
        return (is_numeric($value) && (int) $value > 0)
            ? []
            : ["{$field->label} must be a valid uploaded file."];
    }

    /**
     * @return string[]
     */
    private function validateLocation(FieldDefinition $field, string $value): array
    {
        if (!preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $value, $matches)) {
            return ["{$field->label} must be in \"latitude,longitude\" format."];
        }

        $lat = (float) $matches[1];
        $lng = (float) $matches[2];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return ["{$field->label} coordinates are out of range."];
        }

        return [];
    }
}
