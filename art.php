hi
test
<?php
require 'db.php';
$db = db();
$artId = intval($_GET['id']);

$art = $db->query("SELECT artName FROM arts WHERE artId=$artId")->fetch_assoc();
$iter = $db->query("SELECT iterationName FROM iterations ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['iterationName'] ?? 'N/A';
$capacity = $db->query("SELECT SUM(c.storyPoints) as total FROM capacities c JOIN teams t ON c.teamId=t.teamId JOIN iterations i ON c.iterationId=i.iterationId WHERE t.artId=$artId AND i.iterationName='$iter'")->fetch_assoc()['total'] ?? 0;
$teams = $db->query("SELECT t.teamId, t.teamName, COUNT(tm.teamMemberId) as members, COALESCE(SUM(c.storyPoints), 0) as capacity FROM teams t LEFT JOIN team_members tm ON t.teamId=tm.teamId LEFT JOIN capacities c ON t.teamId=c.teamId LEFT JOIN iterations i ON c.iterationId=i.iterationId AND i.iterationName='$iter' WHERE t.artId=$artId GROUP BY t.teamId");
$teamCount = $teams->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($art['artName']) ?></title>
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
                <a href="index.php" class="back">← Dashboard</a>
                <h1><?= htmlspecialchars($art['artName']) ?></h1>
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
                    <div class="card-label">Teams</div>
                    <div class="card-value"><?= $teamCount ?></div>
                </div>
            </div>
            <div class="card green">
                <div class="card-icon">⚡</div>
                <div>
                    <div class="card-label">Capacity</div>
                    <div class="card-value"><?= number_format($capacity, 0) ?> SP</div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="content-header">
                <h2>Teams</h2>
                <input type="text" placeholder="Search..." class="search">
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
                    <?php 
                    $db->data_seek(0); 
                    while($team = $teams->fetch_assoc()): 
                    ?>
                    <tr>
                        <td>
                            <div class="name">
                                <span class="emoji">🎯</span>
                                <?= htmlspecialchars($team['teamName']) ?>
                            </div>
                        </td>
                        <td><?= $team['members'] ?></td>
                        <td><span class="badge"><?= number_format($team['capacity'], 0) ?> SP</span></td>
                        <td><a href="team.php?id=<?= $team['teamId'] ?>" class="link">View →</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>