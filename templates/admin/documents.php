<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Documents\Domain\Document[] $documents */
/** @var array<string, string> $visibilityOptions */
/** @var ?string $noticeType */
?>
<div class="wrap">
    <h1><?php esc_html_e('Documents', 'association-manager'); ?></h1>

    <?php if ($noticeType === 'uploaded') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Document uploaded.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'upload_failed') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('Upload failed. Please try again.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'deleted') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Document deleted.', 'association-manager'); ?></p></div>
    <?php endif; ?>

    <h2><?php esc_html_e('Upload a document', 'association-manager'); ?></h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('association_manager_upload_document'); ?>
        <input type="hidden" name="action" value="association_manager_upload_document" />
        <table class="form-table">
            <tbody>
            <tr>
                <th><label for="am-doc-title"><?php esc_html_e('Title', 'association-manager'); ?></label></th>
                <td><input type="text" id="am-doc-title" name="title" required /></td>
            </tr>
            <tr>
                <th><label for="am-doc-description"><?php esc_html_e('Description', 'association-manager'); ?></label></th>
                <td><textarea id="am-doc-description" name="description"></textarea></td>
            </tr>
            <tr>
                <th><label for="am-doc-category"><?php esc_html_e('Category', 'association-manager'); ?></label></th>
                <td><input type="text" id="am-doc-category" name="category" /></td>
            </tr>
            <tr>
                <th><label for="am-doc-visibility"><?php esc_html_e('Visibility', 'association-manager'); ?></label></th>
                <td>
                    <select id="am-doc-visibility" name="visibility">
                        <?php foreach ($visibilityOptions as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="am-doc-file"><?php esc_html_e('File', 'association-manager'); ?></label></th>
                <td><input type="file" id="am-doc-file" name="am_document_file" required /></td>
            </tr>
            </tbody>
        </table>
        <?php submit_button(__('Upload', 'association-manager')); ?>
    </form>

    <h2><?php esc_html_e('All documents', 'association-manager'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Title', 'association-manager'); ?></th>
                <th><?php esc_html_e('Category', 'association-manager'); ?></th>
                <th><?php esc_html_e('Visibility', 'association-manager'); ?></th>
                <th><?php esc_html_e('Actions', 'association-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($documents)) : ?>
            <tr>
                <td colspan="4"><?php esc_html_e('No documents yet.', 'association-manager'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($documents as $document) : ?>
                <tr>
                    <td><?php echo esc_html($document->title); ?></td>
                    <td><?php echo esc_html($document->category ?? '—'); ?></td>
                    <td><?php echo esc_html($visibilityOptions[$document->visibility] ?? $document->visibility); ?></td>
                    <td>
                        <a href="<?php echo esc_url(wp_get_attachment_url($document->wpAttachmentId) ?: '#'); ?>" target="_blank">
                            <?php esc_html_e('Download', 'association-manager'); ?>
                        </a>
                        |
                        <a href="<?php echo esc_url(add_query_arg(['edit_id' => $document->id])); ?>">
                            <?php esc_html_e('Edit', 'association-manager'); ?>
                        </a>
                        |
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                            <?php wp_nonce_field('association_manager_delete_document_' . $document->id); ?>
                            <input type="hidden" name="action" value="association_manager_delete_document" />
                            <input type="hidden" name="document_id" value="<?php echo esc_attr((string) $document->id); ?>" />
                            <button type="submit" class="button-link"><?php esc_html_e('Delete', 'association-manager'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
