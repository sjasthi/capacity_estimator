<?php
require 'db.php';
$db = db();

$pi = $db->query("SELECT piName FROM program_increments ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['piName'] ?? 'N/A';
$iter = $db->query("SELECT iterationName FROM iterations ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['iterationName'] ?? 'N/A';
$total = $db->query("SELECT SUM(c.storyPoints) as total FROM capacities c JOIN iterations i ON c.iterationId=i.iterationId WHERE i.iterationName='$iter'")->fetch_assoc()['total'] ?? 0;
$arts = $db->query("SELECT a.artId, a.artName, COUNT(DISTINCT t.teamId) as teams, COALESCE(SUM(c.storyPoints), 0) as capacity FROM arts a LEFT JOIN teams t ON a.artId=t.artId LEFT JOIN capacities c ON t.teamId=c.teamId LEFT JOIN iterations i ON c.iterationId=i.iterationId WHERE i.iterationName='$iter' GROUP BY a.artId");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capacity Estimator</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>📊 Capacity</h2>
        </div>
        <nav>
            <a href="index.php" class="nav-item active">
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
            <h1>Dashboard</h1>
            <div class="user">Admin</div>
        </div>

        <div class="cards">
            <div class="card purple">
                <div class="card-icon">📅</div>
                <div>
                    <div class="card-label">Current PI</div>
                    <div class="card-value"><?= $pi ?></div>
                </div>
            </div>
            <div class="card blue">
                <div class="card-icon">🔄</div>
                <div>
                    <div class="card-label">Iteration</div>
                    <div class="card-value"><?= $iter ?></div>
                </div>
            </div>
            <div class="card green">
                <div class="card-icon">⚡</div>
                <div>
                    <div class="card-label">Total Capacity</div>
                    <div class="card-value"><?= number_format($total, 0) ?> SP</div>
                </div>
            </div>
            <div class="card orange">
                <div class="card-icon">🎯</div>
                <div>
                    <div class="card-label">ARTs</div>
                    <div class="card-value"><?= $arts->num_rows ?></div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="content-header">
                <h2>Agile Release Trains</h2>
                <input type="text" placeholder="Search..." class="search">
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ART</th>
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
                            <div class="name">
                                <span class="emoji">🚀</span>
                                <?= htmlspecialchars($art['artName']) ?>
                            </div>
                        </td>
                        <td><?= $art['teams'] ?></td>
                        <td><span class="badge"><?= number_format($art['capacity'], 0) ?> SP</span></td>
                        <td><span class="status">Active</span></td>
                        <td><a href="art.php?id=<?= $art['artId'] ?>" class="link">View →</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>