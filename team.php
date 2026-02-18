<?php
require 'db.php';
$db = db();
$teamId = intval($_GET['id']);

$team = $db->query("SELECT t.teamName, a.artId, a.artName FROM teams t JOIN arts a ON t.artId=a.artId WHERE t.teamId=$teamId")->fetch_assoc();
$iter = $db->query("SELECT iterationName FROM iterations ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['iterationName'] ?? 'N/A';
$capacity = $db->query("SELECT storyPoints FROM capacities c JOIN iterations i ON c.iterationId=i.iterationId WHERE c.teamId=$teamId AND i.iterationName='$iter'")->fetch_assoc()['storyPoints'] ?? 0;
$members = $db->query("SELECT p.name, p.email, tm.role, tm.allocationPct FROM team_members tm JOIN persons p ON tm.personId=p.personId WHERE tm.teamId=$teamId ORDER BY CASE tm.role WHEN 'Scrum Master' THEN 1 WHEN 'Product Owner' THEN 2 ELSE 3 END");

$calc = 0;
$memberCount = 0;
$m = $db->query("SELECT allocationPct FROM team_members WHERE teamId=$teamId");
while($r = $m->fetch_assoc()) {
    $calc += 3.75 * ($r['allocationPct'] / 100);
    $memberCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($team['teamName']) ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>📊 Capacity</h2>
        </div>
        <nav>
            <a href="index.php" class="nav-item">
                <span>🏠</span>
                <span>Dashboard</span>
            </a>
            <a href="reports.php" class="nav-item">
                <span>📈</span>
                <span>Reports</span>
            </a>
            <a href="import.php" class="nav-item">
                <span>📤</span>
                <span>Import</span>
            </a>
        </nav>
    </div>

    <div class="main">
        <div class="header">
            <div>
                <a href="art.php?id=<?= $team['artId'] ?>" class="back">← <?= htmlspecialchars($team['artName']) ?></a>
                <h1><?= htmlspecialchars($team['teamName']) ?></h1>
            </div>
            <div class="user">Admin</div>
        </div>

        <div class="cards">
            <div class="card purple">
                <div class="card-icon">🔄</div>
                <div>
                    <div class="card-label">Iteration</div>
                    <div class="card-value"><?= $iter ?></div>
                </div>
            </div>
            <div class="card blue">
                <div class="card-icon">👥</div>
                <div>
                    <div class="card-label">Members</div>
                    <div class="card-value"><?= $memberCount ?></div>
                </div>
            </div>
            <div class="card green">
                <div class="card-icon">⚡</div>
                <div>
                    <div class="card-label">Capacity</div>
                    <div class="card-value"><?= number_format($capacity, 0) ?> SP</div>
                </div>
            </div>
            <div class="card orange">
                <div class="card-icon">🎯</div>
                <div>
                    <div class="card-label">Calculated</div>
                    <div class="card-value"><?= number_format($calc, 2) ?> SP</div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="content-header">
                <h2>Team Members</h2>
                <input type="text" placeholder="Search..." class="search">
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
                            <div class="name">
                                <div class="avatar"><?= $initials ?></div>
                                <?= htmlspecialchars($member['name']) ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($member['email']) ?></td>
                        <td><span class="role <?= $roleClass ?>"><?= htmlspecialchars($member['role']) ?></span></td>
                        <td><?= $member['allocationPct'] ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>