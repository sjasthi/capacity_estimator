<?php
require 'db.php';
$db = db();

$results = $db->query("SELECT i.iterationName, SUM(c.storyPoints) as capacity FROM iterations i LEFT JOIN capacities c ON i.iterationId=c.iterationId GROUP BY i.iterationId ORDER BY i.startDate DESC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
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
            <a href="reports.php" class="nav-item active">
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
            <h1>Capacity Trend Report</h1>
            <div class="user">Admin</div>
        </div>

        <div class="content">
            <div class="content-header">
                <h2>Filters</h2>
            </div>
            <div class="filters">
                <div>
                    <label>View by</label>
                    <select>
                        <option>Organization</option>
                        <option>ART</option>
                        <option>Team</option>
                    </select>
                </div>
                <div>
                    <label>Select</label>
                    <select>
                        <option>All</option>
                        <option>ART 1</option>
                        <option>ART 2</option>
                    </select>
                </div>
                <div>
                    <label>Period</label>
                    <select>
                        <option>Last 6 iterations</option>
                        <option>Last 12 iterations</option>
                    </select>
                </div>
                <button class="btn">Generate</button>
            </div>
        </div>

        <div class="content">
            <div class="content-header">
                <h2>Results</h2>
                <button class="btn-export">Export CSV</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Iteration</th>
                        <th>Capacity</th>
                        <th>Change</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $prev = null;
                    while($row = $results->fetch_assoc()): 
                        $change = $prev ? $row['capacity'] - $prev : 0;
                        $changeClass = $change > 0 ? 'positive' : ($change < 0 ? 'negative' : 'neutral');
                        $changeText = $change > 0 ? '+'.number_format($change, 0) : ($change < 0 ? number_format($change, 0) : '—');
                        $prev = $row['capacity'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['iterationName']) ?></td>
                        <td><span class="badge"><?= number_format($row['capacity'], 0) ?> SP</span></td>
                        <td><span class="<?= $changeClass ?>"><?= $changeText ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>