<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, string[]> $errors */
/** @var string $email */
/** @var string $firstName */
/** @var string $lastName */
/** @var string $redirect */
?>
<div class="am-register">
    <?php if (!empty($errors)) : ?>
        <div class="am-register-errors am-notice am-notice-error">
            <ul>
                <?php foreach ($errors as $fieldErrors) : ?>
                    <?php foreach ($fieldErrors as $message) : ?>
                        <li><?php echo esc_html($message); ?></li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('association_manager_register'); ?>
        <input type="hidden" name="action" value="association_manager_register" />
        <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>" />

        <p>
            <label for="am-register-email"><?php esc_html_e('Email', 'association-manager'); ?></label><br />
            <input type="email" id="am-register-email" name="email" value="<?php echo esc_attr($email); ?>" required />
        </p>
        <p>
            <label for="am-register-first-name"><?php esc_html_e('First Name', 'association-manager'); ?></label><br />
            <input type="text" id="am-register-first-name" name="first_name" value="<?php echo esc_attr($firstName); ?>" required />
        </p>
        <p>
            <label for="am-register-last-name"><?php esc_html_e('Last Name', 'association-manager'); ?></label><br />
            <input type="text" id="am-register-last-name" name="last_name" value="<?php echo esc_attr($lastName); ?>" required />
        </p>
        <p>
            <label for="am-register-password"><?php esc_html_e('Password', 'association-manager'); ?></label><br />
            <input type="password" id="am-register-password" name="password" required minlength="8" />
        </p>

        <p><button type="submit" class="button button-primary am-button am-button-primary"><?php esc_html_e('Register', 'association-manager'); ?></button></p>
    </form>
</div>
