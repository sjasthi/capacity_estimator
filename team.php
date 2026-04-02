<?php
require 'db.php';
$db = db();
$teamId   = intval($_GET['id']);
$team     = $db->query("SELECT t.teamName, a.artId, a.artName FROM teams t JOIN arts a ON t.artId=a.artId WHERE t.teamId=$teamId")->fetch_assoc();
$iter     = $db->query("SELECT iterationName FROM iterations ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['iterationName'] ?? 'N/A';
$capacity = $db->query("SELECT storyPoints FROM capacities c JOIN iterations i ON c.iterationId=i.iterationId WHERE c.teamId=$teamId AND i.iterationName='$iter'")->fetch_assoc()['storyPoints'] ?? 0;
$members  = $db->query("SELECT p.name, p.email, tm.role, tm.allocationPct FROM team_members tm JOIN persons p ON tm.personId=p.personId WHERE tm.teamId=$teamId ORDER BY CASE tm.role WHEN 'Scrum Master' THEN 1 WHEN 'Product Owner' THEN 2 ELSE 3 END");
$calc = 0; $memberCount = 0;
$m = $db->query("SELECT allocationPct FROM team_members WHERE teamId=$teamId");
while ($r = $m->fetch_assoc()) { $calc += 8 * ($r['allocationPct'] / 100); $memberCount++; }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($team['teamName'] ?? 'Team') ?> - CapacityHub</title>
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
            <a href="test.php"       class="nav-tab">Test</a>
        </div>
        <div class="user-menu">
            <div class="notification-icon">
                <i class="far fa-bell"></i>
            </div>
            <div class="user-avatar">AD</div>
        </div>
    </div>
    <div class="container">
<a href="art.php?id=<?= $team['artId'] ?>" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to <?= htmlspecialchars($team['artName']) ?>
        </a>

        <div class="page-header">
            <h1 class="page-title"><?= htmlspecialchars($team['teamName']) ?></h1>
            <p class="page-subtitle">Team Overview</p>
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
                    <div class="metric-label">TEAM MEMBERS</div>
                    <div class="metric-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="metric-value"><?= $memberCount ?></div>
            </div>
            <div class="metric-box green">
                <div class="metric-header">
                    <div class="metric-label">CAPACITY</div>
                    <div class="metric-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
                <div class="metric-value"><?= number_format($capacity, 0) ?> SP</div>
            </div>
            <div class="metric-box orange">
                <div class="metric-header">
                    <div class="metric-label">CALCULATED</div>
                    <div class="metric-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
                <div class="metric-value"><?= number_format($calc, 2) ?> SP</div>
            </div>
        </div>

        <div class="data-panel">
            <div class="panel-header">
                <h2 class="panel-title">Team Members</h2>
                <input type="text" class="search-input" placeholder="Search members...">
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Allocation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($member = $members->fetch_assoc()): 
                        $names = explode(' ', $member['name']);
                        $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));
                        $roleClass = $member['role'] == 'Scrum Master' ? 'sm' : ($member['role'] == 'Product Owner' ? 'po' : 'dev');
                    ?>
                    <tr>
                        <td>
                            <div class="team-info">
                                <div class="avatar"><?= $initials ?></div>
                                <div class="team-details">
                                    <h4><?= htmlspecialchars($member['name']) ?></h4>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($member['email']) ?></td>
                        <td><span class="role-badge <?= $roleClass ?>"><?= htmlspecialchars($member['role']) ?></span></td>
                        <td><?= $member['allocationPct'] ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>