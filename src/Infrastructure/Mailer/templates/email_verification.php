<?php
declare(strict_types=1);

/**
 * @var string $siteUrl
 * @var string $appName
 * @var string $name
 * @var string $verificationLink
 */
?>
<p>Olá, <?= \htmlspecialchars($name ?? 'User'); ?>,</p>
<p>Obrigado por se registrar em <?= \htmlspecialchars($appName ?? 'Our Application'); ?>!</p>
<p>Para ativar sua conta, por favor, verifique seu endereço de e-mail clicando no link abaixo:</p>
<div class="button-wrapper">
    <a href="<?= \htmlspecialchars($verificationLink ?? ''); ?>" class="button">Verificar E-mail</a>
</div>
<p>Se o botão acima não funcionar, você pode copiar e colar o seguinte link no seu navegador:</p>
<p><?= \htmlspecialchars($verificationLink ?? ''); ?></p>
<p>Este link expirará em breve.</p>
<p>Se você não se registrou em <?= \htmlspecialchars($appName ?? 'Our Application'); ?>, por favor, ignore este e-mail.</p>
<p>Obrigado,</p>
<p>A Equipe <?= \htmlspecialchars($appName ?? 'Application'); ?></p>
