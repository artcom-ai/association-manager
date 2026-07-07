<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Core\Pagination\PaginatedResult $result */
/** @var \AssociationManager\Core\Fields\FieldDefinition[] $customFields */
/** @var string $searchParamName */
/** @var string $pageParamName */
/** @var ?string $currentSearch */

$coreLabels = [
    'member_number' => __('Member #', 'association-manager'),
    'membership_type' => __('Membership type', 'association-manager'),
    'joined_at' => __('Joined', 'association-manager'),
    'status' => __('Status', 'association-manager'),
    'expires_at' => __('Expires', 'association-manager'),
];

$labelFor = static function (string $key) use ($coreLabels, $customFields): string {
    if (isset($coreLabels[$key])) {
        return $coreLabels[$key];
    }

    foreach ($customFields as $field) {
        if ($field->key === $key) {
            return $field->label;
        }
    }

    return $key;
};
?>
<div class="am-directory">
    <form method="get" class="am-directory__search">
        <input
            type="text"
            name="<?php echo esc_attr($searchParamName); ?>"
            value="<?php echo esc_attr($currentSearch ?? ''); ?>"
            placeholder="<?php esc_attr_e('Search member #', 'association-manager'); ?>"
        />
        <button type="submit"><?php esc_html_e('Search', 'association-manager'); ?></button>
    </form>

    <?php if (empty($result->items)) : ?>
        <p><?php esc_html_e('No members to display yet.', 'association-manager'); ?></p>
    <?php else : ?>
        <ul class="am-directory__list">
            <?php foreach ($result->items as $entry) : ?>
                <li class="am-directory__entry">
                    <?php foreach ($entry as $key => $value) : ?>
                        <span class="am-directory__field am-directory__field--<?php echo esc_attr($key); ?>">
                            <strong><?php echo esc_html($labelFor($key)); ?>:</strong>
                            <?php echo esc_html((string) ($value ?? '—')); ?>
                        </span>
                    <?php endforeach; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($result->totalPages() > 1) : ?>
            <div class="am-directory__pagination">
                <?php for ($p = 1; $p <= $result->totalPages(); $p++) : ?>
                    <?php
                    $args = [$pageParamName => $p];

                    if ($currentSearch !== null && $currentSearch !== '') {
                        $args[$searchParamName] = $currentSearch;
                    }
                    ?>
                    <a
                        href="<?php echo esc_url(add_query_arg($args)); ?>"
                        <?php echo $p === $result->page ? 'class="am-directory__page--current"' : ''; ?>
                    >
                        <?php echo esc_html((string) $p); ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
