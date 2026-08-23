<?php
/**
 * Standalone Machine & Biometric Endpoint Tester
 * Access at: https://wechechaconstruction.com/machine-test.php
 */

header('X-Robots-Tag: noindex, nofollow');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'] ?? 'wechechaconstruction.com';
$baseUrl = $protocol . $domain;

// Check ADMS endpoints
$endpoints = [
    'ADMS Push Receiver (iclock/cdata)' => '/iclock/cdata',
    'ADMS PHP Script (iclock/cdata.php)' => '/iclock/cdata.php',
    'Heartbeat Poller (iclock/getrequest)' => '/iclock/getrequest',
    'Push Poller (iclock/push)' => '/iclock/push',
    'Live Debugger Logger' => '/iclock/debug.php',
];

$results = [];
foreach ($endpoints as $label => $path) {
    $url = $baseUrl . $path . '?SN=TEST12345';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $results[] = [
        'label' => $label,
        'path' => $path,
        'http_code' => $httpCode,
        'response' => substr(trim((string)$response), 0, 100),
        'status' => ($httpCode == 200) ? 'OK' : 'Warning (' . $httpCode . ')',
    ];
}

$admsLogFile = __DIR__ . '/iclock/adms.log';
$logCount = 0;
$recentLogSnippet = 'No requests in log yet.';
if (file_exists($admsLogFile)) {
    $content = file_get_contents($admsLogFile);
    $lines = array_filter(explode("\n", $content));
    $logCount = count($lines);
    $recentLogSnippet = implode("\n", array_slice($lines, -30));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Machine Connection Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .log-terminal { background: #0f172a; color: #38bdf8; font-family: monospace; font-size: 13px; height: 320px; overflow-y: auto; padding: 15px; border-radius: 8px; }
    </style>
</head>
<body class="p-4">
    <div class="container" style="max-width: 950px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-0"><i class="fa-solid fa-microchip text-primary me-2"></i>Attendance Machine Connectivity Test</h3>
                <small class="text-muted">Direct Server Endpoint Diagnostic for ZKTeco / Biometric Hardware</small>
            </div>
            <div>
                <a href="/attendance/machine-test" class="btn btn-primary btn-sm rounded-pill px-3">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Open ERP Test Console
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-0 rounded-top-4">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-tower-broadcast text-success me-2"></i>Server ADMS Endpoints Health</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Endpoint Name</th>
                            <th>Path</th>
                            <th>HTTP Status</th>
                            <th>Response Sample</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $res): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($res['label']) ?></td>
                            <td><code><?= htmlspecialchars($res['path']) ?></code></td>
                            <td>
                                <?php if ($res['http_code'] == 200): ?>
                                    <span class="badge bg-success rounded-pill px-3">200 OK</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark rounded-pill px-3"><?= htmlspecialchars($res['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($res['response'] ?: '(Empty OK)') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-0 rounded-top-4">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sliders text-primary me-2"></i>Machine Setup Values</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">SERVER ADDRESS / IP</label>
                        <div class="h6 fw-bold text-primary mb-0">wechechaconstruction.com</div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">SERVER PORT</label>
                        <div class="h6 fw-bold text-dark mb-0">80 <span class="text-muted fw-normal">(or 443 for HTTPS)</span></div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted fw-bold">COMMUNICATION PROTOCOL</label>
                        <div class="h6 fw-bold text-success mb-0">ADMS / Cloud Server</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-0 rounded-top-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-terminal text-info me-2"></i>Latest Machine Raw Logs</h5>
                <small class="text-muted">Total logged lines: <?= $logCount ?></small>
            </div>
            <div class="card-body">
                <pre class="log-terminal mb-0"><?= htmlspecialchars($recentLogSnippet) ?></pre>
            </div>
        </div>
    </div>
</body>
</html>
