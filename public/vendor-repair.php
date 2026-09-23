<?php
/**
 * vendor-repair.php  —  Emergency vendor repair script
 * Access at: https://wechechaconstruction.com/vendor-repair.php
 *
 * Runs OUTSIDE Laravel so it works even when the vendor dir is broken.
 * Runs composer install to fully restore the vendor directory,
 * then composer update for the tesseract_ocr package.
 *
 * DELETE or restrict access to this file after use for security.
 */

// ── Security: simple token check ──────────────────────────────────────────
$token = $_GET['token'] ?? '';
if ($token !== 'wc-repair-2024') {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;color:red">403 — Missing or wrong ?token=</h2>');
}

// ── Setup ─────────────────────────────────────────────────────────────────
$base = dirname(__DIR__); // /home/wecheccc/public_html

putenv('HOME=/tmp');
putenv('COMPOSER_HOME=/tmp/.composer');
putenv('COMPOSER_CACHE_DIR=/tmp/.composer/cache');

$steps = [];

// ── Step 1: composer install (restores dev packages like filp/whoops) ─────
$out1 = []; $code1 = 0;
exec("cd {$base} && composer install --no-interaction --ignore-platform-reqs 2>&1", $out1, $code1);
$steps[] = [
    'label'  => 'composer install (restore vendor/)',
    'output' => implode("\n", $out1),
    'ok'     => $code1 === 0,
];

// ── Step 2: composer update for the new package ───────────────────────────
$out2 = []; $code2 = 0;
exec("cd {$base} && composer update thiagoalessio/tesseract_ocr --no-interaction --optimize-autoloader 2>&1", $out2, $code2);
$steps[] = [
    'label'  => 'composer update thiagoalessio/tesseract_ocr',
    'output' => implode("\n", $out2),
    'ok'     => $code2 === 0,
];

// ── Step 3: Clear Laravel caches ─────────────────────────────────────────
$out3 = []; $code3 = 0;
exec("cd {$base} && php artisan config:clear 2>&1 && php artisan route:clear 2>&1 && php artisan view:clear 2>&1", $out3, $code3);
$steps[] = [
    'label'  => 'php artisan cache:clear',
    'output' => implode("\n", $out3),
    'ok'     => true,
];

// ── Render ────────────────────────────────────────────────────────────────
$allOk = array_reduce($steps, fn($c, $s) => $c && $s['ok'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Repair</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 32px auto; padding: 0 16px; }
        h1 { color: <?= $allOk ? '#065f46' : '#991b1b' ?>; }
        .step { margin-bottom: 24px; }
        .step h3 { margin: 0 0 6px; font-size: 15px; }
        pre { background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 8px;
              max-height: 300px; overflow: auto; font-size: 12px; white-space: pre-wrap; word-break: break-all; }
        .ok pre  { background: #f0fdf4; border-color: #bbf7d0; }
        .err pre { background: #fef2f2; border-color: #fecaca; }
        a.btn { display:inline-block; margin-top:16px; padding:10px 20px; background:#7c3aed;
                color:#fff; text-decoration:none; border-radius:6px; font-weight:bold; margin-right:8px; }
    </style>
</head>
<body>
<h1><?= $allOk ? '✅ Vendor Repair Complete' : '⚠️ Vendor Repair — Some Steps Failed' ?></h1>
<p><?= date('Y-m-d H:i:s') ?></p>

<?php foreach ($steps as $step): ?>
<div class="step <?= $step['ok'] ? 'ok' : 'err' ?>">
    <h3><?= $step['ok'] ? '✅' : '❌' ?> <?= htmlspecialchars($step['label']) ?></h3>
    <pre><?= htmlspecialchars(trim($step['output']) ?: '(no output)') ?></pre>
</div>
<?php endforeach; ?>

<a class="btn" href="/deploy-from-github">🚀 Run Full Deploy</a>
<a class="btn" style="background:#2563eb" href="/dashboard">🏠 Dashboard</a>

<p style="color:#9ca3af;font-size:12px;margin-top:32px;">
    ⚠️ Security reminder: delete or restrict <code>vendor-repair.php</code> after use.
</p>
</body>
</html>
