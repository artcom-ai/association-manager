<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Members\Domain\Member $member */
/** @var \AssociationManager\Core\Fields\FieldDefinition[] $fields */
/** @var array<string, string> $values */
/** @var \AssociationManager\Core\Fields\Admin\FieldRenderer $renderer */
/** @var ?string $noticeType */
?>
<div class="wrap">
    <h1>
        <?php esc_html_e('Edit Member Fields', 'association-manager'); ?>
        #<?php echo esc_html((string) $member->id); ?>
    </h1>

    <?php if ($noticeType === 'saved') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Fields saved.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'invalid') : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Some fields were invalid. Please check and try again.', 'association-manager'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($fields)) : ?>
        <p><?php esc_html_e('No custom fields are registered for members.', 'association-manager'); ?></p>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('association_manager_save_member_fields_' . $member->id); ?>
            <input type="hidden" name="action" value="association_manager_save_member_fields" />
            <input type="hidden" name="member_id" value="<?php echo esc_attr((string) $member->id); ?>" />

            <table class="form-table">
                <tbody>
                <?php foreach ($fields as $field) : ?>
                    <?php $renderer->render($field, $values[$field->key] ?? null); ?>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php submit_button(__('Save fields', 'association-manager')); ?>
        </form>
    <?php endif; ?>
</div>
