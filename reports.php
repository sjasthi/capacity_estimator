<?php
require 'db.php';
$db = db();

$results = $db->query("SELECT i.iterationName, COALESCE(SUM(c.storyPoints), 0) as capacity FROM iterations i LEFT JOIN capacities c ON i.iterationId=c.iterationId GROUP BY i.iterationId ORDER BY i.startDate DESC LIMIT 6");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reports - CapacityHub</title>
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
            <a href="index.php" class="nav-tab">Dashboard</a>
            <a href="reports.php" class="nav-tab active">Reports</a>
            <a href="import.php" class="nav-tab">Import</a>
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
            <h1 class="page-title">Capacity Trend Report</h1>
            <p class="page-subtitle">Analyze capacity trends across iterations</p>
        </div>

        <div class="data-panel">
            <div class="panel-header">
                <h2 class="panel-title">Filters</h2>
            </div>
            <div style="padding: 24px 28px;">
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
                    <button class="btn">Generate Report</button>
                </div>
            </div>
        </div>

        <div class="data-panel">
            <div class="panel-header">
                <h2 class="panel-title">Results</h2>
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
                        <td><span class="capacity-pill"><?= number_format($row['capacity'], 0) ?> SP</span></td>
                        <td><span class="<?= $changeClass ?>"><?= $changeText ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>