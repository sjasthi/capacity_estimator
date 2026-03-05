<?php
require 'db.php';
$db = db();
$artId = intval($_GET['id']);

$art = $db->query("SELECT artName FROM arts WHERE artId=$artId")->fetch_assoc();
$iter = $db->query("SELECT iterationName FROM iterations ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['iterationName'] ?? 'N/A';
$capacity = $db->query("SELECT SUM(c.storyPoints) as total FROM capacities c JOIN teams t ON c.teamId=t.teamId JOIN iterations i ON c.iterationId=i.iterationId WHERE t.artId=$artId AND i.iterationName='$iter'")->fetch_assoc()['total'] ?? 0;
$teams = $db->query("SELECT t.teamId, t.teamName, COUNT(tm.teamMemberId) as members, COALESCE(c.storyPoints, 0) as capacity FROM teams t LEFT JOIN team_members tm ON t.teamId=tm.teamId LEFT JOIN capacities c ON t.teamId=c.teamId LEFT JOIN iterations i ON c.iterationId=i.iterationId AND i.iterationName='$iter' WHERE t.artId=$artId GROUP BY t.teamId, t.teamName");
$teamCount = $teams->num_rows;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($art['artName']) ?> - CapacityHub</title>
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
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>

        <div class="page-header">
            <h1 class="page-title"><?= htmlspecialchars($art['artName']) ?></h1>
            <p class="page-subtitle">Release Train Overview</p>
        </div>

        <div class="metrics-row">
            <div class="metric-box purple">
                <div class="metric-header">
                    <div class="metric-label">CURRENT ITERATION</div>
                    <div class="metric-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                </div>
                <div class="metric-value"><?= htmlspecialchars($iter) ?></div>
            </div>
            <div class="metric-box blue">
                <div class="metric-header">
                    <div class="metric-label">TOTAL TEAMS</div>
                    <div class="metric-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="metric-value"><?= $teamCount ?></div>
            </div>
            <div class="metric-box green">
                <div class="metric-header">
                    <div class="metric-label">ART CAPACITY</div>
                    <div class="metric-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
                <div class="metric-value"><?= number_format($capacity, 0) ?> SP</div>
            </div>
        </div>

        <div class="data-panel">
            <div class="panel-header">
                <h2 class="panel-title">Teams</h2>
                <input type="text" class="search-input" placeholder="Search teams...">
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>Members</th>
                        <th>Capacity</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($team = $teams->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="team-info">
                                <div class="team-logo">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="team-details">
                                    <h4><?= htmlspecialchars($team['teamName']) ?></h4>
                                    <p>Scrum Team</p>
                                </div>
                            </div>
                        </td>
                        <td><?= $team['members'] ?> members</td>
                        <td><span class="capacity-pill"><?= number_format($team['capacity'], 0) ?> SP</span></td>
                        <td>
                            <a href="team.php?id=<?= $team['teamId'] ?>" class="view-link">
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