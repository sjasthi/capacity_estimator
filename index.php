//test
<?php
require 'db.php';
$db = db();

$pi = $db->query("SELECT piName FROM program_increments ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['piName'] ?? 'N/A';
$iter = $db->query("SELECT iterationName FROM iterations ORDER BY endDate DESC LIMIT 1")->fetch_assoc()['iterationName'] ?? 'N/A';
$iterEscaped = $db->real_escape_string($iter);

$total = $db->query("SELECT SUM(c.storyPoints) as total FROM capacities c JOIN iterations i ON c.iterationId=i.iterationId WHERE i.iterationName='$iterEscaped'")->fetch_assoc()['total'] ?? 0;

$artsResult = $db->query("
    SELECT a.artId, a.artName, 
           COUNT(DISTINCT t.teamId) as teams, 
           COALESCE(SUM(c.storyPoints), 0) as capacity 
    FROM arts a 
    LEFT JOIN teams t ON a.artId = t.artId 
    LEFT JOIN capacities c ON t.teamId = c.teamId 
    LEFT JOIN iterations i ON c.iterationId = i.iterationId 
        AND i.iterationName = '$iterEscaped'
    GROUP BY a.artId, a.artName
");

$arts = [];
while ($row = $artsResult->fetch_assoc()) {
    $arts[] = $row;
}
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
                    <div class="card-value"><?= htmlspecialchars($pi) ?></div>
                </div>
            </div>
            <div class="card blue">
                <div class="card-icon">🔄</div>
                <div>
                    <div class="card-label">Iteration</div>
                    <div class="card-value"><?= htmlspecialchars($iter) ?></div>
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
                    <div class="card-value"><?= count($arts) ?></div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="content-header">
                <h2>Agile Release Trains</h2>
                <input type="text" placeholder="Search..." class="search" id="artSearch">
            </div>
            <table id="artsTable">
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
                    <?php if (empty($arts)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:#888; padding:24px;">No ARTs found. Import data to get started.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($arts as $art): ?>
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
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.getElementById('artSearch').addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#artsTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>