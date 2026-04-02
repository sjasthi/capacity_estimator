<?php
require 'db.php';
$db = db();

$results = [];
if (isset($_POST['run'])) {
    $base   = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $artId  = $db->query("SELECT artId  FROM arts  LIMIT 1")->fetch_assoc()['artId']  ?? 1;
    $teamId = $db->query("SELECT teamId FROM teams LIMIT 1")->fetch_assoc()['teamId'] ?? 1;

    $pages = [
        'index.php', 'arts.php', 'teams.php', 'iterations.php',
        'capacity.php', 'reports.php', 'import.php', 'export.php',
        "art.php?id=$artId", "team.php?id=$teamId",
    ];

    foreach ($pages as $path) {
        $ch = curl_init("$base/$path");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_NOBODY => true]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $results[$path] = $code;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Runner - CapacityHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="topbar">
        <div class="brand">
            <div class="brand-logo"><i class="fas fa-chart-line"></i></div>
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
            <a href="test.php"       class="nav-tab">Test</a>
        </div>
        <div class="user-menu">
            <div class="notification-icon"><i class="far fa-bell"></i></div>
            <div class="user-avatar">AD</div>
        </div>
    </div>
    <div class="container">

        <div class="page-header">
            <h1 class="page-title">Test Runner</h1>
            <p class="page-subtitle">Checks every page for 404 errors</p>
        </div>

        <form method="POST" style="margin-bottom:24px;">
            <button type="submit" name="run" class="btn">
                <i class="fas fa-play" style="margin-right:8px;"></i>Run Tests
            </button>
        </form>

        <?php if ($results): ?>
        <div class="data-panel">
            <div class="panel-header"><h2 class="panel-title">Results</h2></div>
            <table>
                <thead><tr><th>Page</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($results as $path => $code): ?>
                <tr>
                    <td><a href="<?= $path ?>" target="_blank" style="font-family:monospace;"><?= $path ?></a></td>
                    <td style="color:<?= $code === 200 ? '#059669' : '#dc2626' ?>;font-weight:700;">
                        <?= $code === 200 ? '✓ OK' : "✗ $code" ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>