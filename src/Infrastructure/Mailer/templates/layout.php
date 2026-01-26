<?php
declare(strict_types=1);

/**
 * This file is included by renderHtmlTemplate, so variables are available in this scope.
 *
 * @var string $subject
 * @var string $contentHtml
 * @var string $siteUrl
 * @var string $appName
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo \htmlspecialchars((string) $subject); ?></title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #ffffff;
      color: #3c3c3c;
      padding: 20px;
    }
    .container {
      max-width: 600px;
      margin: 0 auto;
      padding: 20px;
      background-color: #ffffff;
      border: 1px solid #dddddd;
      border-radius: 8px;
    }
    .logo {
      text-align: center;
      margin-bottom: 20px;
    }
    .logo img {
      max-width: 150px;
    }
    .header h1 {
      color: #191919;
      margin: 0;
      text-align: center;
    }
    .content {
      font-size: 16px;
      line-height: 1.6;
      margin-top: 20px;
    }
    .footer {
      text-align: center;
      margin-top: 30px;
      font-size: 12px;
      color: #999999;
    }
    .button {
      display: inline-block;
      padding: 12px 28px;
      background-color: #191919;
      color: #ffffff !important;
      text-decoration: none;
      border-radius: 12px;
      font-weight: bold;
      margin: 20px 0;
    }
    .button-wrapper {
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">
      <img src="<?php echo $siteUrl; ?>/img/logo.png" alt="Logo">
    </div>
    <div class="header">
      <h1><?php echo \htmlspecialchars((string) $subject); ?></h1>
    </div>
    <div class="content">
      <?php echo $contentHtml; ?>
    </div>
    <div class="footer">
      <p>&copy; <?php echo \date('Y'); ?> <?php echo $appName; ?>. All rights reserved.</p>
    </div>
  </div>
</body>
</html>