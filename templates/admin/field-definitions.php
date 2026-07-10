<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

use AssociationManager\Core\Fields\Admin\FieldDefinitionsPage;

/** @var \AssociationManager\Core\Fields\FieldDefinition[] $fields */
/** @var ?string $noticeType */

$visibilityLabels = [
    'public' => __('Public', 'association-manager'),
    'private' => __('Members only', 'association-manager'),
    'admin' => __('Admin only', 'association-manager'),
];
?>
<div class="wrap">
    <h1>
        <?php esc_html_e('Member Fields', 'association-manager'); ?>
        <a href="<?php echo esc_url(add_query_arg(['page' => FieldDefinitionsPage::SLUG, 'new' => 1], admin_url('admin.php'))); ?>" class="page-title-action">
            <?php esc_html_e('Add New Field', 'association-manager'); ?>
        </a>
    </h1>

    <p class="description"><?php esc_html_e('These fields appear on the Member Portal profile and the admin Edit Fields screen for every member.', 'association-manager'); ?></p>

    <?php if ($noticeType === 'saved') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Field saved.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'deleted') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Field deleted.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'key_taken') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('A field with that key already exists.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'invalid') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('Please check the field key, label, and type.', 'association-manager'); ?></p></div>
    <?php endif; ?>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Label', 'association-manager'); ?></th>
                <th><?php esc_html_e('Key', 'association-manager'); ?></th>
                <th><?php esc_html_e('Type', 'association-manager'); ?></th>
                <th><?php esc_html_e('Required', 'association-manager'); ?></th>
                <th><?php esc_html_e('Visibility', 'association-manager'); ?></th>
                <th><?php esc_html_e('Order', 'association-manager'); ?></th>
                <th><?php esc_html_e('Actions', 'association-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($fields)) : ?>
            <tr>
                <td colspan="7"><?php esc_html_e('No fields yet.', 'association-manager'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($fields as $field) : ?>
                <tr>
                    <td><?php echo esc_html($field->label); ?></td>
                    <td><code><?php echo esc_html($field->key); ?></code></td>
                    <td><?php echo esc_html($field->type); ?></td>
                    <td><?php echo $field->required ? esc_html__('Yes', 'association-manager') : esc_html__('No', 'association-manager'); ?></td>
                    <td><?php echo esc_html($visibilityLabels[$field->visibility] ?? $field->visibility); ?></td>
                    <td><?php echo esc_html((string) $field->order); ?></td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(['page' => FieldDefinitionsPage::SLUG, 'field_key' => $field->key], admin_url('admin.php'))); ?>">
                            <?php esc_html_e('Edit', 'association-manager'); ?>
                        </a>
                        |
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js(__('Delete this field? Every stored value for it will be deleted too.', 'association-manager')); ?>');">
                            <?php wp_nonce_field('association_manager_delete_field_definition_' . $field->key); ?>
                            <input type="hidden" name="action" value="association_manager_delete_field_definition" />
                            <input type="hidden" name="field_key" value="<?php echo esc_attr($field->key); ?>" />
                            <button type="submit" class="button-link"><?php esc_html_e('Delete', 'association-manager'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
