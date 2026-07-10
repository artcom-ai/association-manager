<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

use AssociationManager\Core\Fields\Admin\FieldDefinitionsPage;
use AssociationManager\Core\Fields\FieldDefinition;

/** @var \AssociationManager\Core\Fields\FieldDefinition|null $field */
/** @var bool $isNew */
/** @var ?string $noticeType */

$types = [
    FieldDefinition::TYPE_TEXT => __('Text', 'association-manager'),
    FieldDefinition::TYPE_TEXTAREA => __('Textarea', 'association-manager'),
    FieldDefinition::TYPE_NUMBER => __('Number', 'association-manager'),
    FieldDefinition::TYPE_DATE => __('Date', 'association-manager'),
    FieldDefinition::TYPE_SELECT => __('Select', 'association-manager'),
    FieldDefinition::TYPE_CHECKBOX => __('Checkbox', 'association-manager'),
    FieldDefinition::TYPE_FILE => __('File', 'association-manager'),
    FieldDefinition::TYPE_LOCATION => __('Location (lat,lng)', 'association-manager'),
];

$visibilities = [
    FieldDefinition::VISIBILITY_PUBLIC => __('Public', 'association-manager'),
    FieldDefinition::VISIBILITY_PRIVATE => __('Members only', 'association-manager'),
    FieldDefinition::VISIBILITY_ADMIN => __('Admin only', 'association-manager'),
];

$optionsText = '';
if ($field !== null && $field->options !== null) {
    foreach ($field->options as $value => $label) {
        $optionsText .= "{$value}|{$label}\n";
    }
}
?>
<div class="wrap">
    <h1><?php echo $isNew ? esc_html__('Add Field', 'association-manager') : esc_html__('Edit Field', 'association-manager'); ?></h1>

    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . FieldDefinitionsPage::SLUG)); ?>">
            &larr; <?php esc_html_e('Back to fields', 'association-manager'); ?>
        </a>
    </p>

    <?php if (!$isNew && $field === null) : ?>
        <div class="notice notice-error"><p><?php esc_html_e('That field no longer exists.', 'association-manager'); ?></p></div>
        <?php return; ?>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('association_manager_save_field_definition'); ?>
        <input type="hidden" name="action" value="association_manager_save_field_definition" />
        <input type="hidden" name="is_new" value="<?php echo $isNew ? '1' : '0'; ?>" />

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="am-field-key"><?php esc_html_e('Key', 'association-manager'); ?></label></th>
                    <td>
                        <?php if ($isNew) : ?>
                            <input type="text" id="am-field-key" name="field_key" class="regular-text" pattern="[a-z0-9_]+" required />
                            <p class="description"><?php esc_html_e('Lowercase letters, numbers, underscores only. Cannot be changed after saving.', 'association-manager'); ?></p>
                        <?php else : ?>
                            <code><?php echo esc_html($field->key); ?></code>
                            <input type="hidden" name="field_key" value="<?php echo esc_attr($field->key); ?>" />
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="am-field-label"><?php esc_html_e('Label', 'association-manager'); ?></label></th>
                    <td><input type="text" id="am-field-label" name="label" class="regular-text" value="<?php echo esc_attr($field->label ?? ''); ?>" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="am-field-type"><?php esc_html_e('Type', 'association-manager'); ?></label></th>
                    <td>
                        <select id="am-field-type" name="type">
                            <?php foreach ($types as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($field->type ?? FieldDefinition::TYPE_TEXT, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Required', 'association-manager'); ?></th>
                    <td><label><input type="checkbox" name="required" value="1" <?php checked($field->required ?? false, true); ?> /> <?php esc_html_e('Member must fill this in before submitting for approval', 'association-manager'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="am-field-visibility"><?php esc_html_e('Visibility', 'association-manager'); ?></label></th>
                    <td>
                        <select id="am-field-visibility" name="visibility">
                            <?php foreach ($visibilities as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($field->visibility ?? FieldDefinition::VISIBILITY_ADMIN, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Who can see this field on the Portal/Directory. Always visible to admins regardless.', 'association-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="am-field-order"><?php esc_html_e('Order', 'association-manager'); ?></label></th>
                    <td><input type="number" id="am-field-order" name="order" value="<?php echo esc_attr((string) ($field->order ?? 0)); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="am-field-help"><?php esc_html_e('Help text', 'association-manager'); ?></label></th>
                    <td><input type="text" id="am-field-help" name="help_text" class="regular-text" value="<?php echo esc_attr($field->helpText ?? ''); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="am-field-options"><?php esc_html_e('Options', 'association-manager'); ?></label></th>
                    <td>
                        <textarea id="am-field-options" name="options" class="large-text code" rows="4"><?php echo esc_textarea($optionsText); ?></textarea>
                        <p class="description"><?php esc_html_e('Only used for Select fields. One option per line, format: value|Label', 'association-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="am-field-min-length"><?php esc_html_e('Min / Max length', 'association-manager'); ?></label></th>
                    <td>
                        <input type="number" id="am-field-min-length" name="min_length" value="<?php echo esc_attr($field->minLength !== null ? (string) $field->minLength : ''); ?>" style="width:100px" />
                        &ndash;
                        <input type="number" name="max_length" value="<?php echo esc_attr($field->maxLength !== null ? (string) $field->maxLength : ''); ?>" style="width:100px" />
                        <p class="description"><?php esc_html_e('Text/Textarea fields only. Leave blank for no limit.', 'association-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="am-field-min-value"><?php esc_html_e('Min / Max value', 'association-manager'); ?></label></th>
                    <td>
                        <input type="number" step="any" id="am-field-min-value" name="min_value" value="<?php echo esc_attr($field->minValue !== null ? (string) $field->minValue : ''); ?>" style="width:100px" />
                        &ndash;
                        <input type="number" step="any" name="max_value" value="<?php echo esc_attr($field->maxValue !== null ? (string) $field->maxValue : ''); ?>" style="width:100px" />
                        <p class="description"><?php esc_html_e('Number fields only. Leave blank for no limit.', 'association-manager'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save field', 'association-manager')); ?>
    </form>
</div>
