<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, string[]> $errors */
/** @var string $login */
/** @var string $redirect */
?>
<div class="am-login">
    <?php if (!empty($errors)) : ?>
        <div class="am-login-errors">
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
        <?php wp_nonce_field('association_manager_login'); ?>
        <input type="hidden" name="action" value="association_manager_login" />
        <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>" />

        <p>
            <label for="am-login-username"><?php esc_html_e('Email or Username', 'association-manager'); ?></label><br />
            <input type="text" id="am-login-username" name="log" value="<?php echo esc_attr($login); ?>" required />
        </p>
        <p>
            <label for="am-login-password"><?php esc_html_e('Password', 'association-manager'); ?></label><br />
            <input type="password" id="am-login-password" name="pwd" required />
        </p>
        <p>
            <label>
                <input type="checkbox" name="remember" value="1" />
                <?php esc_html_e('Remember me', 'association-manager'); ?>
            </label>
        </p>

        <p><button type="submit" class="button button-primary"><?php esc_html_e('Log In', 'association-manager'); ?></button></p>
    </form>

    <p><a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Forgot your password?', 'association-manager'); ?></a></p>
</div>
