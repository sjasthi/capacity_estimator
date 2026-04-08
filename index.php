<?php
require 'db.php';
$db = db();

$pi = $db->query("SELECT piName FROM program_increments ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['piName'] ?? 'N/A';
$iter = $db->query("SELECT iterationName FROM iterations ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['iterationName'] ?? 'N/A';
$total = $db->query("SELECT SUM(c.storyPoints) as total FROM capacities c JOIN iterations i ON c.iterationId=i.iterationId WHERE i.iterationName='$iter'")->fetch_assoc()['total'] ?? 0;
$arts = $db->query("SELECT a.artId, a.artName, COUNT(DISTINCT t.teamId) as teams, COALESCE(SUM(c.storyPoints), 0) as capacity FROM arts a LEFT JOIN teams t ON a.artId=t.artId LEFT JOIN capacities c ON t.teamId=c.teamId LEFT JOIN iterations i ON c.iterationId=i.iterationId WHERE i.iterationName='$iter' GROUP BY a.artId");
$artCount = $arts->num_rows;

// --- PROTOTYPE: Inline link tester ---
$testResults = [];
$testRan = isset($_GET['test']);
if ($testRan) {
    $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $currentPage = basename($_SERVER['SCRIPT_NAME']) . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] && $_SERVER['QUERY_STRING'] !== 'test' ? '?' . preg_replace('/[&?]?test=?1?/', '', $_SERVER['QUERY_STRING']) : '');
    // Fetch this page's HTML to extract links
    $ch = curl_init("$base/$currentPage");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true]);
    $html = curl_exec($ch);
    curl_close($ch);
    // Extract all hrefs
    preg_match_all('/href=["\']((?!#|mailto:|javascript:|http)[^"\']+)["\']/i', $html, $matches);
    $links = array_unique($matches[1]);
    foreach ($links as $link) {
        $link = ltrim($link, '/');
        if (strpos($link, 'test=') !== false) continue; // skip test links
        $ch = curl_init("$base/$link");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => true]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $testResults[$link] = $code;
    }
}
// --- END PROTOTYPE ---
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CapacityHub - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
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
            <a href="index.php"      class="nav-tab">Dashboard</a>
            <a href="arts.php"       class="nav-tab">ARTs</a>
            <a href="teams.php"      class="nav-tab">Teams</a>
            <a href="iterations.php" class="nav-tab">Iterations</a>
            <a href="capacity.php"   class="nav-tab">Capacity Entry</a>
            <a href="reports.php"    class="nav-tab">Reports</a>
            <a href="import.php"     class="nav-tab">Import</a>
            <a href="export.php"     class="nav-tab">Export</a>
            <a href="?test=1" class="nav-tab" style="color:#6366f1;font-weight:600;"><i class="fas fa-flask" style="margin-right:4px;"></i>Test</a>
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
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:20px 24px;margin-bottom:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <strong style="font-size:15px;">
            <i class="fas fa-flask" style="color:#6366f1;margin-right:8px;"></i>
            Link Test Results
            <?php $pass=count(array_filter($testResults,fn($c)=>$c===200)); $fail=count($testResults)-$pass; ?>
            <span style="color:#059669;margin-left:10px;">✓ <?= $pass ?> passed</span>
            <?php if($fail): ?><span style="color:#dc2626;margin-left:8px;">✗ <?= $fail ?> failed</span><?php endif; ?>
        </strong>
        <a href="<?= strtok($_SERVER['REQUEST_URI'],'?') ?>" style="font-size:13px;color:#6b7280;text-decoration:none;">✕ Close</a>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <?php foreach($testResults as $link => $code): ?>
        <tr style="border-top:1px solid #f3f4f6;">
            <td style="padding:6px 0;font-family:monospace;color:#374151;">
                <a href="<?= htmlspecialchars($link) ?>" target="_blank" style="color:#6366f1;text-decoration:none;"><?= htmlspecialchars($link) ?></a>
            </td>
            <td style="padding:6px 0;text-align:right;font-weight:700;color:<?= $code===200?'#059669':'#dc2626' ?>;">
                <?= $code===200 ? '✓ OK' : "✗ $code" ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
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
                    <div class="metric-icon">
                        <i class="far fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="metric-value"><?= htmlspecialchars($pi) ?></div>
            </div>
            <div class="metric-box blue">
                <div class="metric-header">
                    <div class="metric-label">CURRENT ITERATION</div>
                    <div class="metric-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                </div>
                <div class="metric-value"><?= htmlspecialchars($iter) ?></div>
            </div>
            <div class="metric-box green">
                <div class="metric-header">
                    <div class="metric-label">TOTAL CAPACITY</div>
                    <div class="metric-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
                <div class="metric-value"><?= number_format($total, 0) ?> SP</div>
            </div>
            <div class="metric-box orange">
                <div class="metric-header">
                    <div class="metric-label">ACTIVE ARTS</div>
                    <div class="metric-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
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
                                <div class="team-logo">
                                    <i class="fas fa-rocket"></i>
                                </div>
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
                                View details
                                <i class="fas fa-arrow-right"></i>
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