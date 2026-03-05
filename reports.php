<?php
require 'db.php';
$db = db();

// Filter inputs
$viewBy  = $_GET['viewBy'] ?? 'organization';
$scopeId = intval($_GET['scopeId'] ?? 0);
$period  = intval($_GET['period'] ?? 6);

// Load ARTs and Teams for filter dropdowns
$allArts  = $db->query("SELECT artId, artName FROM arts ORDER BY artName");
$allTeams = [];
if ($viewBy == 'team') {
    $teamResult = $db->query("SELECT t.teamId, t.teamName, a.artName FROM teams t JOIN arts a ON t.artId=a.artId ORDER BY a.artName, t.teamName");
    while ($r = $teamResult->fetch_assoc()) $allTeams[] = $r;
}

// Build query based on viewBy
$periodLimit = intval($period);

if ($viewBy == 'organization') {
    $sql = "
        SELECT i.iterationName, i.startDate, i.endDate, pi.piName,
               COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM iterations i
        JOIN program_increments pi ON i.piId = pi.piId
        LEFT JOIN capacities c ON i.iterationId = c.iterationId
        GROUP BY i.iterationId, i.iterationName, i.startDate, i.endDate, pi.piName
        ORDER BY i.startDate DESC
        LIMIT $periodLimit
    ";
} elseif ($viewBy == 'art' && $scopeId > 0) {
    $sql = "
        SELECT i.iterationName, i.startDate, i.endDate, pi.piName,
               COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM iterations i
        JOIN program_increments pi ON i.piId = pi.piId
        JOIN teams t ON t.artId = pi.artId
        LEFT JOIN capacities c ON i.iterationId = c.iterationId AND c.teamId = t.teamId
        WHERE pi.artId = $scopeId
        GROUP BY i.iterationId, i.iterationName, i.startDate, i.endDate, pi.piName
        ORDER BY i.startDate DESC
        LIMIT $periodLimit
    ";
} elseif ($viewBy == 'team' && $scopeId > 0) {
    $sql = "
        SELECT i.iterationName, i.startDate, i.endDate, pi.piName,
               COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM iterations i
        JOIN program_increments pi ON i.piId = pi.piId
        LEFT JOIN capacities c ON i.iterationId = c.iterationId AND c.teamId = $scopeId
        GROUP BY i.iterationId, i.iterationName, i.startDate, i.endDate, pi.piName
        ORDER BY i.startDate DESC
        LIMIT $periodLimit
    ";
} else {
    // Default: org level
    $sql = "
        SELECT i.iterationName, i.startDate, i.endDate, pi.piName,
               COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM iterations i
        JOIN program_increments pi ON i.piId = pi.piId
        LEFT JOIN capacities c ON i.iterationId = c.iterationId
        GROUP BY i.iterationId, i.iterationName, i.startDate, i.endDate, pi.piName
        ORDER BY i.startDate DESC
        LIMIT $periodLimit
    ";
}

$results = $db->query($sql);
$rows = [];
while ($r = $results->fetch_assoc()) $rows[] = $r;

// Reverse so oldest is first (for trend — change = current minus previous)
$rows = array_reverse($rows);

// Compute scope label for results panel title
$scopeLabel = 'Organization';
if ($viewBy == 'art' && $scopeId > 0) {
    $r = $db->query("SELECT artName FROM arts WHERE artId=$scopeId")->fetch_assoc();
    $scopeLabel = $r ? htmlspecialchars($r['artName']) : 'ART';
} elseif ($viewBy == 'team' && $scopeId > 0) {
    $r = $db->query("SELECT teamName FROM teams WHERE teamId=$scopeId")->fetch_assoc();
    $scopeLabel = $r ? htmlspecialchars($r['teamName']) : 'Team';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reports - CapacityHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .select-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .select-group label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .select-group select {
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            color: #111827;
            background: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            cursor: pointer;
            width: 100%;
        }
        .select-group select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .select-group select:disabled {
            background-color: #f9fafb;
            color: #9ca3af;
            cursor: not-allowed;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: 200px 260px 200px auto;
            gap: 16px;
            align-items: end;
            padding: 24px 28px;
        }
        .scope-label {
            display: inline-block;
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .empty-state {
            padding: 60px 28px;
            text-align: center;
            color: #9ca3af;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
            color: #d1d5db;
        }
        .pi-label {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 2px;
        }
        .trend-bar-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .trend-bar-bg {
            flex: 1;
            height: 8px;
            background: #f3f4f6;
            border-radius: 4px;
            overflow: hidden;
            max-width: 160px;
        }
        .trend-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            border-radius: 4px;
        }
    </style>
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
            <a href="capacity.php" class="nav-tab">Capacity Entry</a>
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

        <!-- Filters -->
        <div class="data-panel" style="margin-bottom: 24px;">
            <div class="panel-header">
                <h2 class="panel-title">Filters</h2>
            </div>
            <form method="GET" action="reports.php">
                <div class="filters-grid">

                    <!-- View By -->
                    <div class="select-group">
                        <label>View By</label>
                        <select name="viewBy" onchange="this.form.submit()">
                            <option value="organization" <?= $viewBy == 'organization' ? 'selected' : '' ?>>Organization</option>
                            <option value="art"          <?= $viewBy == 'art'          ? 'selected' : '' ?>>ART</option>
                            <option value="team"         <?= $viewBy == 'team'         ? 'selected' : '' ?>>Team</option>
                        </select>
                    </div>

                    <!-- Scope selector (ART or Team) -->
                    <div class="select-group">
                        <label>
                            <?php if ($viewBy == 'art'): ?>Select ART
                            <?php elseif ($viewBy == 'team'): ?>Select Team
                            <?php else: ?>Scope<?php endif; ?>
                        </label>
                        <?php if ($viewBy == 'organization'): ?>
                            <select disabled>
                                <option>All (Organization)</option>
                            </select>
                        <?php elseif ($viewBy == 'art'): ?>
                            <select name="scopeId" onchange="this.form.submit()">
                                <option value="">— Select ART —</option>
                                <?php $allArts->data_seek(0); while ($art = $allArts->fetch_assoc()): ?>
                                <option value="<?= $art['artId'] ?>" <?= $scopeId == $art['artId'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($art['artName']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        <?php elseif ($viewBy == 'team'): ?>
                            <select name="scopeId" onchange="this.form.submit()">
                                <option value="">— Select Team —</option>
                                <?php foreach ($allTeams as $team): ?>
                                <option value="<?= $team['teamId'] ?>" <?= $scopeId == $team['teamId'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($team['artName']) ?> &rsaquo; <?= htmlspecialchars($team['teamName']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- Period -->
                    <div class="select-group">
                        <label>Period</label>
                        <select name="period" onchange="this.form.submit()">
                            <option value="6"  <?= $period == 6  ? 'selected' : '' ?>>Last 6 iterations</option>
                            <option value="12" <?= $period == 12 ? 'selected' : '' ?>>Last 12 iterations</option>
                            <option value="24" <?= $period == 24 ? 'selected' : '' ?>>Last 24 iterations</option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn">
                            <i class="fas fa-chart-bar" style="margin-right:6px;"></i>Generate
                        </button>
                    </div>

                </div>
            </form>
        </div>

        <!-- Results -->
        <div class="data-panel">
            <div class="panel-header">
                <h2 class="panel-title">Results</h2>
                <span class="scope-label">
                    <?php if ($viewBy == 'organization'): ?>
                        <i class="fas fa-globe" style="margin-right:4px;"></i> Organization
                    <?php elseif ($viewBy == 'art'): ?>
                        <i class="fas fa-rocket" style="margin-right:4px;"></i> <?= $scopeLabel ?>
                    <?php else: ?>
                        <i class="fas fa-users" style="margin-right:4px;"></i> <?= $scopeLabel ?>
                    <?php endif; ?>
                    &mdash; Last <?= $period ?> iterations
                </span>
            </div>

            <?php if (empty($rows)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <p>No capacity data found for the selected filters.</p>
            </div>
            <?php else:
                $maxCap = max(array_column($rows, 'capacity')) ?: 1;
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Program Increment</th>
                        <th>Iteration</th>
                        <th>Dates</th>
                        <th>Capacity</th>
                        <th>Trend</th>
                        <th>Change</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $prev = null;
                    foreach ($rows as $row):
                        $cap         = floatval($row['capacity']);
                        $change      = $prev !== null ? $cap - $prev : null;
                        $changeClass = $change === null ? 'neutral' : ($change > 0 ? 'positive' : ($change < 0 ? 'negative' : 'neutral'));
                        $changeText  = $change === null ? '—' : ($change > 0 ? '+' . number_format($change, 2) : number_format($change, 2));
                        $barWidth    = $maxCap > 0 ? round(($cap / $maxCap) * 100) : 0;
                        $prev        = $cap;
                    ?>
                    <tr>
                        <td style="color:#6b7280;font-size:13px;"><?= htmlspecialchars($row['piName']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['iterationName']) ?></strong>
                        </td>
                        <td style="color:#9ca3af;font-size:12px;">
                            <?= htmlspecialchars($row['startDate']) ?> &rarr; <?= htmlspecialchars($row['endDate']) ?>
                        </td>
                        <td><span class="capacity-pill"><?= number_format($cap, 2) ?> SP</span></td>
                        <td>
                            <div class="trend-bar-wrap">
                                <div class="trend-bar-bg">
                                    <div class="trend-bar-fill" style="width:<?= $barWidth ?>%;"></div>
                                </div>
                                <span style="font-size:12px;color:#6b7280;min-width:32px;"><?= $barWidth ?>%</span>
                            </div>
                        </td>
                        <td><span class="<?= $changeClass ?>"><?= $changeText ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>