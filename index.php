<?php
require 'db.php';
$db = db();

// === Inline Link Tester ===
$testRan = false;
$testResults = [];
$pass = 0;
$fail = 0;
$closeUrl = '';
$testLink = '';

// Build test link (preserves existing params)
$testParams = $_GET;
$testParams['test'] = '1';
$testLink = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($testParams);

if (isset($_GET['test']) && $_GET['test'] == '1') {
    $testRan = true;
    
    // Build close URL (remove test param)
    $closeParams = $_GET;
    unset($closeParams['test']);
    $closeUrl = basename($_SERVER['PHP_SELF']);
    if (!empty($closeParams)) {
        $closeUrl .= '?' . http_build_query($closeParams);
    }
    
    // Build URL to fetch (current page without test param)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']) . '/' . basename($_SERVER['PHP_SELF']);
    $fetchParams = $_GET;
    unset($fetchParams['test']);
    $fetchUrl = $scheme . '://' . $host . $path;
    if (!empty($fetchParams)) {
        $fetchUrl .= '?' . http_build_query($fetchParams);
    }
    
    // Fetch own HTML
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fetchUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $html = curl_exec($ch);
    curl_close($ch);
    
    // Parse links
    $links = [];
    if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
        $links = array_unique($matches[1]);
    }
    
    // Base URL for relative links
    $baseUrl = $scheme . '://' . $host . dirname($_SERVER['PHP_SELF']) . '/';
    
    foreach ($links as $link) {
        // Skip external, anchors, mailto, javascript, already has test
        if (preg_match('/^(https?:\/\/|#|mailto:|javascript:|tel:)/i', $link)) {
            continue;
        }
        if (strpos($link, 'test=') !== false) {
            continue;
        }
        if (empty(trim($link))) {
            continue;
        }
        
        // Build full URL
        $checkUrl = $baseUrl . $link;
        
        // Make GET request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $checkUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $ok = ($httpCode >= 200 && $httpCode < 400);
        if ($ok) {
            $pass++;
        } else {
            $fail++;
        }
        
        $testResults[] = array(
            'link' => $link,
            'code' => $httpCode,
            'ok' => $ok
        );
    }
}
// === End Inline Link Tester ===

$pi = $db->query("SELECT piName FROM program_increments ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['piName'] ?? 'N/A';
$iter = $db->query("SELECT iterationName FROM iterations ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['iterationName'] ?? 'N/A';
$total = $db->query("SELECT SUM(c.storyPoints) as total FROM capacities c JOIN iterations i ON c.iterationId=i.iterationId WHERE i.iterationName='$iter'")->fetch_assoc()['total'] ?? 0;
$arts = $db->query("SELECT a.artId, a.artName, COUNT(DISTINCT t.teamId) as teams, COALESCE(SUM(c.storyPoints), 0) as capacity FROM arts a LEFT JOIN teams t ON a.artId=t.artId LEFT JOIN capacities c ON t.teamId=c.teamId LEFT JOIN iterations i ON c.iterationId=i.iterationId WHERE i.iterationName='$iter' GROUP BY a.artId");
$artCount = $arts->num_rows;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CapacityHub - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .test-panel { background: #fefce8; border: 2px solid #facc15; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; }
        .test-panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .test-panel-title { font-size: 16px; font-weight: 700; color: #854d0e; display: flex; align-items: center; gap: 8px; }
        .test-panel-close { background: #fef3c7; color: #92400e; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .test-panel-close:hover { background: #fde68a; }
        .test-summary { display: flex; gap: 16px; margin-bottom: 16px; }
        .test-stat { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; }
        .test-stat.pass { background: #dcfce7; color: #166534; }
        .test-stat.fail { background: #fee2e2; color: #991b1b; }
        .test-results { max-height: 300px; overflow-y: auto; background: white; border: 1px solid #e5e7eb; border-radius: 8px; }
        .test-result-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
        .test-result-row:last-child { border-bottom: none; }
        .test-result-link { color: #374151; font-family: monospace; word-break: break-all; }
        .test-result-status { padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; white-space: nowrap; }
        .test-result-status.ok { background: #dcfce7; color: #166534; }
        .test-result-status.error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">
            <div class="brand-logo">
                <i class="fas fa-chart-line"></i>
            </div>
            CapacityHub
        </div>
        <div class="nav-tabs">
            <a href="index.php" class="nav-tab">Dashboard</a>
            <a href="arts.php" class="nav-tab">ARTs</a>
            <a href="teams.php" class="nav-tab">Teams</a>
            <a href="iterations.php" class="nav-tab">Iterations</a>
            <a href="capacity.php" class="nav-tab">Capacity Entry</a>
            <a href="reports.php" class="nav-tab">Reports</a>
            <a href="import.php" class="nav-tab">Import</a>
            <a href="export.php" class="nav-tab">Export</a>
            <a href="<?= htmlspecialchars($testLink) ?>" class="nav-tab" style="color: #8b5cf6;"><i class="fas fa-flask"></i> Test</a>
        </div>
        <div class="user-menu">
            <div class="notification-icon">
                <i class="far fa-bell"></i>
            </div>
            <div class="user-avatar">AD</div>
        </div>
    </div>

    <div class="container">

        <?php if ($testRan): ?>
        <div class="test-panel">
            <div class="test-panel-header">
                <div class="test-panel-title">
                    <i class="fas fa-flask"></i>
                    Link Test Results
                </div>
                <a href="<?= htmlspecialchars($closeUrl) ?>" class="test-panel-close">
                    <i class="fas fa-times"></i> Close
                </a>
            </div>
            <div class="test-summary">
                <div class="test-stat pass"><i class="fas fa-check-circle"></i> <?= $pass ?> Passed</div>
                <div class="test-stat fail"><i class="fas fa-times-circle"></i> <?= $fail ?> Failed</div>
            </div>
            <div class="test-results">
                <?php foreach ($testResults as $result): ?>
                <div class="test-result-row">
                    <span class="test-result-link"><?= htmlspecialchars($result['link']) ?></span>
                    <span class="test-result-status <?= $result['ok'] ? 'ok' : 'error' ?>">
                        <?= $result['ok'] ? '✓ ' . $result['code'] : '✗ ' . $result['code'] ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($testResults)): ?>
                <div class="test-result-row">
                    <span style="color: #6b7280;">No internal links found to test.</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <h1 class="page-title">Welcome back, Admin</h1>
            <p class="page-subtitle">Here's what's happening with your capacity today</p>
        </div>

        <div class="metrics-row">
            <div class="metric-box purple">
                <div class="metric-header">
                    <div class="metric-label">CURRENT PROGRAM INCREMENT</div>
                    <div class="metric-icon"><i class="far fa-calendar-alt"></i></div>
                </div>
                <div class="metric-value"><?= htmlspecialchars($pi) ?></div>
            </div>
            <div class="metric-box blue">
                <div class="metric-header">
                    <div class="metric-label">CURRENT ITERATION</div>
                    <div class="metric-icon"><i class="fas fa-sync-alt"></i></div>
                </div>
                <div class="metric-value"><?= htmlspecialchars($iter) ?></div>
            </div>
            <div class="metric-box green">
                <div class="metric-header">
                    <div class="metric-label">TOTAL CAPACITY</div>
                    <div class="metric-icon"><i class="fas fa-bolt"></i></div>
                </div>
                <div class="metric-value"><?= number_format($total, 0) ?> SP</div>
            </div>
            <div class="metric-box orange">
                <div class="metric-header">
                    <div class="metric-label">ACTIVE ARTS</div>
                    <div class="metric-icon"><i class="fas fa-layer-group"></i></div>
                </div>
                <div class="metric-value"><?= $artCount ?></div>
            </div>
        </div>

        <div class="data-panel">
            <div class="panel-header">
                <h2 class="panel-title">Agile Release Trains</h2>
                <input type="text" class="search-input" placeholder="Search trains...">
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Train</th>
                        <th>Teams</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($art = $arts->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="team-info">
                                <div class="team-logo"><i class="fas fa-rocket"></i></div>
                                <div class="team-details">
                                    <h4><?= htmlspecialchars($art['artName']) ?></h4>
                                    <p>Release Train</p>
                                </div>
                            </div>
                        </td>
                        <td><?= $art['teams'] ?> teams</td>
                        <td><span class="capacity-pill"><?= number_format($art['capacity'], 0) ?> SP</span></td>
                        <td><span class="status-dot">Active</span></td>
                        <td>
                            <a href="art.php?id=<?= $art['artId'] ?>" class="view-link">
                                View details <i class="fas fa-arrow-right"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>