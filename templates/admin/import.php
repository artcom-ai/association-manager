<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Importers\ImportSourceInterface[] $sources */
/** @var array<string, mixed>|false $report */
/** @var ?string $noticeType */
?>
<div class="wrap">
    <h1><?php esc_html_e('Import Members', 'association-manager'); ?></h1>

    <?php if ($noticeType === 'no_source') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('No import source selected.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'unknown_source') : ?>
        <div class="notice notice-error"><p><?php esc_html_e('That import source is not registered.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'dry_run_done') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Dry run complete - nothing was written. Review the report below.', 'association-manager'); ?></p></div>
    <?php elseif ($noticeType === 'commit_done') : ?>
        <div class="notice notice-success"><p><?php esc_html_e('Import committed. Review the report below.', 'association-manager'); ?></p></div>
    <?php endif; ?>

    <?php if (empty($sources)) : ?>
        <p><?php esc_html_e('No import sources are registered.', 'association-manager'); ?></p>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('association_manager_run_import'); ?>
            <input type="hidden" name="action" value="association_manager_run_import" />
            <table class="form-table">
                <tbody>
                <tr>
                    <th><label for="am-import-source"><?php esc_html_e('Source', 'association-manager'); ?></label></th>
                    <td>
                        <select id="am-import-source" name="source">
                            <?php foreach ($sources as $source) : ?>
                                <option value="<?php echo esc_attr($source->key()); ?>"><?php echo esc_html($source->label()); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                </tbody>
            </table>
            <p class="description"><?php esc_html_e('Dry run shows what would happen without writing anything. Commit actually creates/updates members - running it again on the same data is safe and will not create duplicates.', 'association-manager'); ?></p>
            <?php submit_button(__('Preview (dry run)', 'association-manager'), 'secondary', 'mode', false, ['name' => 'mode', 'value' => 'dry_run']); ?>
            &nbsp;
            <?php submit_button(__('Commit import', 'association-manager'), 'primary', 'mode', false, ['name' => 'mode', 'value' => 'commit']); ?>
        </form>
    <?php endif; ?>

    <?php if (is_array($report)) : ?>
        <h2><?php esc_html_e('Last report', 'association-manager'); ?></h2>
        <table class="widefat striped" style="max-width: 700px;">
            <tbody>
            <tr>
                <th><?php esc_html_e('Mode', 'association-manager'); ?></th>
                <td><?php echo $report['dryRun'] ? esc_html__('Dry run', 'association-manager') : esc_html__('Committed', 'association-manager'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Source', 'association-manager'); ?></th>
                <td><?php echo esc_html((string) $report['sourceSystem']); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Rows found', 'association-manager'); ?></th>
                <td><?php echo esc_html((string) $report['total']); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('To be created / created', 'association-manager'); ?></th>
                <td><?php echo esc_html((string) $report['created']); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('To be updated / updated', 'association-manager'); ?></th>
                <td><?php echo esc_html((string) $report['updated']); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Missing required fields', 'association-manager'); ?></th>
                <td><?php echo empty($report['missing']) ? '&mdash;' : esc_html(implode(', ', $report['missing'])); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Unmapped source fields', 'association-manager'); ?></th>
                <td><?php echo empty($report['unmapped']) ? '&mdash;' : esc_html(implode(', ', $report['unmapped'])); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Conflicts', 'association-manager'); ?></th>
                <td>
                    <?php if (empty($report['conflicts'])) : ?>
                        &mdash;
                    <?php else : ?>
                        <ul>
                            <?php foreach ($report['conflicts'] as $conflict) : ?>
                                <li>
                                    <?php
                                    printf(
                                        /* translators: 1: source user id, 2: comma-separated list of changed fields */
                                        esc_html__('User %1$s: %2$s', 'association-manager'),
                                        esc_html((string) $conflict['sourceUserId']),
                                        esc_html(implode('; ', $conflict['conflicts']))
                                    );
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
            </tr>
            </tbody>
        </table>
    <?php endif; ?>
</div>
