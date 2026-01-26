<?php
declare(strict_types=1);

/**
 * This file is included by renderHtmlTemplate, so variables like $name are available in this scope.

 *
 * @var string $name
 * @var string $appName
 * @var string $siteUrl
 */
?>
<p>Hello <?php echo \htmlspecialchars($name ?? 'User'); ?>,</p>
<p>Welcome to <?php echo \htmlspecialchars($appName ?? 'Our Application'); ?>! We are excited to have you on board.</p>
<p>You can now log in to your account and start exploring.</p>
<div class="button-wrapper">
    <a href="<?php echo \htmlspecialchars($siteUrl ?? ''); ?>/login" class="button">Login Now</a>
</div>
<p>If you have any questions, feel free to contact our support team.</p>
<p>Thank you,</p>
<p>The <?php echo \htmlspecialchars($appName ?? 'Application'); ?> Team</p>
