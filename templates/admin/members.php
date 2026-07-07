<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Members\Admin\MembersListTable $table */
/** @var array{created: int, updated: int, errors: string[]}|false $importResult */
/** @var \AssociationManager\Modules\Members\Domain\StatusDefinition[] $statuses */
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Members', 'association-manager'); ?></h1>

    <a
        class="page-title-action"
        href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=association_manager_export_members'), 'association_manager_export_members')); ?>"
    >
        <?php esc_html_e('Export CSV', 'association-manager'); ?>
    </a>

    <hr class="wp-header-end" />

    <?php if ($importResult !== false) : ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: 1: created count, 2: updated count, 3: error count */
                    esc_html__('Import finished: %1$d created, %2$d updated, %3$d errors.', 'association-manager'),
                    (int) $importResult['created'],
                    (int) $importResult['updated'],
                    count($importResult['errors'])
                );
                ?>
            </p>
            <?php if ($importResult['errors'] !== []) : ?>
                <ul>
                    <?php foreach ($importResult['errors'] as $error) : ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="margin: 1em 0;">
        <?php wp_nonce_field('association_manager_import_members'); ?>
        <input type="hidden" name="action" value="association_manager_import_members" />
        <input type="file" name="import_file" accept=".csv" required />
        <?php submit_button(__('Import CSV', 'association-manager'), 'secondary', 'submit', false); ?>
    </form>

    <form method="get">
        <input type="hidden" name="page" value="association-manager-members" />
        <?php $table->search_box(__('Search member #', 'association-manager'), 'am-member-search'); ?>
        <?php $table->display(); ?>
    </form>
</div>

<table style="display:none;">
    <tbody id="am-quick-edit-template">
        <tr class="inline-edit-row">
            <td colspan="5">
                <fieldset>
                    <label>
                        <?php esc_html_e('Status', 'association-manager'); ?>
                        <select name="status" class="am-quick-edit-status">
                            <?php foreach ($statuses as $status) : ?>
                                <option value="<?php echo esc_attr($status->key); ?>"><?php echo esc_html($status->label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <?php esc_html_e('Membership type', 'association-manager'); ?>
                        <input type="text" name="membership_type" class="am-quick-edit-membership-type" />
                    </label>
                    <button type="button" class="button button-primary am-quick-edit-save"><?php esc_html_e('Update', 'association-manager'); ?></button>
                    <button type="button" class="button am-quick-edit-cancel"><?php esc_html_e('Cancel', 'association-manager'); ?></button>
                </fieldset>
            </td>
        </tr>
    </tbody>
</table>
