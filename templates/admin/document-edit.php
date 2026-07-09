<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Documents\Domain\Document|null $document */
/** @var array<string, string> $visibilityOptions */
/** @var ?string $noticeType */
?>
<div class="wrap">
    <h1><?php esc_html_e('Edit Document', 'association-manager'); ?></h1>

    <p>
        <a href="<?php echo esc_url(remove_query_arg(['edit_id', 'am_notice'])); ?>">
            &larr; <?php esc_html_e('Back to documents', 'association-manager'); ?>
        </a>
    </p>

    <?php if ($document === null) : ?>
        <div class="notice notice-error"><p><?php esc_html_e('Document not found.', 'association-manager'); ?></p></div>
    <?php else : ?>
        <?php if ($noticeType === 'updated') : ?>
            <div class="notice notice-success"><p><?php esc_html_e('Document updated.', 'association-manager'); ?></p></div>
        <?php elseif ($noticeType === 'update_failed') : ?>
            <div class="notice notice-error"><p><?php esc_html_e('Please enter a title.', 'association-manager'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('association_manager_update_document_' . $document->id); ?>
            <input type="hidden" name="action" value="association_manager_update_document" />
            <input type="hidden" name="document_id" value="<?php echo esc_attr((string) $document->id); ?>" />
            <table class="form-table">
                <tbody>
                <tr>
                    <th><label for="am-doc-title"><?php esc_html_e('Title', 'association-manager'); ?></label></th>
                    <td><input type="text" id="am-doc-title" name="title" value="<?php echo esc_attr($document->title); ?>" required /></td>
                </tr>
                <tr>
                    <th><label for="am-doc-description"><?php esc_html_e('Description', 'association-manager'); ?></label></th>
                    <td><textarea id="am-doc-description" name="description"><?php echo esc_textarea($document->description ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="am-doc-category"><?php esc_html_e('Category', 'association-manager'); ?></label></th>
                    <td><input type="text" id="am-doc-category" name="category" value="<?php echo esc_attr($document->category ?? ''); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="am-doc-visibility"><?php esc_html_e('Visibility', 'association-manager'); ?></label></th>
                    <td>
                        <select id="am-doc-visibility" name="visibility">
                            <?php foreach ($visibilityOptions as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($document->visibility, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                </tbody>
            </table>
            <p class="description"><?php esc_html_e('To replace the uploaded file itself, delete this document and upload a new one.', 'association-manager'); ?></p>
            <?php submit_button(__('Save changes', 'association-manager')); ?>
        </form>
    <?php endif; ?>
</div>
