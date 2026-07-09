<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Certificates\Domain\CertificateTemplate[] $templates */
?>
<div class="wrap">
    <h1><?php esc_html_e('Certificate Templates', 'association-manager'); ?></h1>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Type', 'association-manager'); ?></th>
                <th><?php esc_html_e('Name', 'association-manager'); ?></th>
                <th><?php esc_html_e('Actions', 'association-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($templates)) : ?>
            <tr>
                <td colspan="3"><?php esc_html_e('No templates yet.', 'association-manager'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($templates as $template) : ?>
                <tr>
                    <td><?php echo esc_html($template->typeKey); ?></td>
                    <td><?php echo esc_html($template->name); ?></td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(['type_key' => $template->typeKey])); ?>">
                            <?php esc_html_e('Edit', 'association-manager'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
