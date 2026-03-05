<?php
require 'db.php';
$db = db();

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
        </div>
        <div class="user-menu">
            <div class="notification-icon">
                <i class="far fa-bell"></i>
            </div>
            <div class="user-avatar">AD</div>
        </div>
    </div>

    <div class="container">
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