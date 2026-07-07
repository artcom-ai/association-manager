<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var \AssociationManager\Modules\Payments\Domain\Payment[] $payments */
?>
<div class="wrap">
    <h1><?php esc_html_e('Payments', 'association-manager'); ?></h1>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('ID', 'association-manager'); ?></th>
                <th><?php esc_html_e('Member ID', 'association-manager'); ?></th>
                <th><?php esc_html_e('Amount', 'association-manager'); ?></th>
                <th><?php esc_html_e('Status', 'association-manager'); ?></th>
                <th><?php esc_html_e('Method', 'association-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($payments)): ?>
            <tr>
                <td colspan="5"><?php esc_html_e('No payments yet.', 'association-manager'); ?></td>
            </tr>
        <?php else: ?>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?php echo esc_html((string) $payment->id); ?></td>
                    <td><?php echo esc_html((string) $payment->memberId); ?></td>
                    <td><?php echo esc_html(number_format($payment->amountCents / 100, 2) . ' ' . $payment->currency); ?></td>
                    <td><?php echo esc_html($payment->status); ?></td>
                    <td><?php echo esc_html($payment->method ?? '—'); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
