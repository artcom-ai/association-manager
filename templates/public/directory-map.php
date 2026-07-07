<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<int, array{label: string, lat: float, lng: float}> $points */
?>
<div
    id="am-directory-map"
    class="am-directory-map"
    data-points="<?php echo esc_attr(wp_json_encode($points)); ?>"
    style="height: 400px;"
></div>
