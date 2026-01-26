<?php
declare(strict_types=1);

/**
 * This file is included by renderHtmlTemplate, so variables like $name, $token, etc., are available in this scope.
 *
 * @var string $name
 * @var string $code
 * @var string $appName
 */
?>
<p>Hello <?php echo \htmlspecialchars($name ?? 'User'); ?>,</p>
<p>You recently requested to reset your password for your account.</p>
<p>Please use the following 6-digit code to reset your password:</p>
<div style="text-align: center; margin: 20px 0;">
  <p style="font-size: 24px; font-weight: bold; color: #191919; letter-spacing: 5px;"><?php echo \htmlspecialchars($code ?? ''); ?></p>
</div>
<p>This code is valid for 1 hour. Please enter it on the password reset validation screen.</p>
<p>If you did not request a password reset, please ignore this email.</p>
<p>Thank you,</p>
<p>The <?php echo \htmlspecialchars($appName ?? 'Application'); ?> Team</p>