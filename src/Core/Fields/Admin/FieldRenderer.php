<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Admin;

use AssociationManager\Core\Fields\FieldDefinition;

defined( 'ABSPATH' ) || exit;

/**
 * Renders one WP admin form-table row for a field definition + its
 * current value. Reused wherever a module needs an "automatic form" for
 * whatever fields are registered against an entity type.
 */
final class FieldRenderer {

    public function render( FieldDefinition $field, ?string $value ): void {
        $id   = 'am-field-' . $field->key;
        $name = 'custom_fields[' . $field->key . ']';
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr( $id ); ?>">
                    <?php echo esc_html( $field->label ); ?><?php echo $field->required ? ' *' : ''; ?>
                </label>
            </th>
            <td>
                <?php $this->renderInput( $field, $id, $name, $value ); ?>
                <?php if ( $field->helpText !== null ) : ?>
                    <p class="description"><?php echo esc_html( $field->helpText ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function renderInput( FieldDefinition $field, string $id, string $name, ?string $value ): void {
        switch ( $field->type ) {
            case FieldDefinition::TYPE_TEXTAREA:
                ?>
                <textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="large-text" rows="4"><?php echo esc_textarea( $value ?? '' ); ?></textarea>
                <?php
                break;

            case FieldDefinition::TYPE_SELECT:
                ?>
                <select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
                    <option value=""><?php esc_html_e( '-- Select --', 'association-manager' ); ?></option>
                    <?php foreach ( $field->options ?? [] as $optionValue => $optionLabel ) : ?>
                        <option value="<?php echo esc_attr( (string) $optionValue ); ?>" <?php selected( $value, (string) $optionValue ); ?>>
                            <?php echo esc_html( $optionLabel ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;

            case FieldDefinition::TYPE_CHECKBOX:
                ?>
                <input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value, '1' ); ?> />
                <?php
                break;

            case FieldDefinition::TYPE_FILE:
                ?>
                <input type="file" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" />
                <?php if ( $value !== null ) : ?>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %s: attachment ID */
                            esc_html__( 'Current attachment ID: %s', 'association-manager' ),
                            esc_html( $value )
                        );
                        ?>
                    </p>
                <?php endif; ?>
                <?php
                break;

            case FieldDefinition::TYPE_NUMBER:
                ?>
                <input
                    type="number"
                    id="<?php echo esc_attr( $id ); ?>"
                    name="<?php echo esc_attr( $name ); ?>"
                    value="<?php echo esc_attr( $value ?? '' ); ?>"
                    <?php
                    if ( $field->minValue !== null ) :
						?>
                        min="<?php echo esc_attr( (string) $field->minValue ); ?>"<?php endif; ?>
                    <?php
                    if ( $field->maxValue !== null ) :
						?>
                        max="<?php echo esc_attr( (string) $field->maxValue ); ?>"<?php endif; ?>
                />
                <?php
                break;

            case FieldDefinition::TYPE_DATE:
                ?>
                <input type="date" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ?? '' ); ?>" />
                <?php
                break;

            case FieldDefinition::TYPE_LOCATION:
                ?>
                <input
                    type="text"
                    id="<?php echo esc_attr( $id ); ?>"
                    name="<?php echo esc_attr( $name ); ?>"
                    value="<?php echo esc_attr( $value ?? '' ); ?>"
                    placeholder="37.9838,23.7275"
                    class="regular-text"
                />
                <p class="description"><?php esc_html_e( 'Format: latitude,longitude', 'association-manager' ); ?></p>
                <?php
                break;

            default:
                ?>
                <input
                    type="text"
                    id="<?php echo esc_attr( $id ); ?>"
                    name="<?php echo esc_attr( $name ); ?>"
                    value="<?php echo esc_attr( $value ?? '' ); ?>"
                    class="regular-text"
                    <?php
                    if ( $field->maxLength !== null ) :
						?>
                        maxlength="<?php echo esc_attr( (string) $field->maxLength ); ?>"<?php endif; ?>
                />
                <?php
        }
    }
}
