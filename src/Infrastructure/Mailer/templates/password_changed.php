<?php
declare(strict_types=1);

/**
 * This file is included by renderHtmlTemplate, so variables like $name are available in this scope.
 *
 * @var string $name
 */
?>
<p>Hello <?php echo \htmlspecialchars($name ?? 'User'); ?>,</p>
<p>This is to inform you that your password for your <?php echo \htmlspecialchars($appName ?? 'Application'); ?> account has been successfully changed.</p>
<p>If you did not make this change, please contact our support team immediately.</p>
<p>Thank you,</p>
<p>The <?php echo \htmlspecialchars($appName ?? 'Application'); ?> Team</p>
