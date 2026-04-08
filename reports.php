<?php
require 'db.php';
$db = db();

$viewBy  = $_GET['viewBy']  ?? 'organization';
$scopeId = intval($_GET['scopeId'] ?? 0);
$period  = intval($_GET['period']  ?? 6);

// Load ARTs for dropdown
$allArts  = $db->query("SELECT artId, artName FROM arts ORDER BY artName");
$allTeams = [];
if ($viewBy == 'team') {
    $res = $db->query("SELECT t.teamId, t.teamName, a.artName FROM teams t JOIN arts a ON t.artId=a.artId ORDER BY a.artName, t.teamName");
    while ($r = $res->fetch_assoc()) $allTeams[] = $r;
}

$periodLimit = intval($period);

// --- Main trend data (bar + line charts) ---
if ($viewBy == 'art' && $scopeId > 0) {
    $sql = "
        SELECT i.iterationName, i.startDate, i.endDate, pi.piName,
               COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM iterations i
        JOIN program_increments pi ON i.piId = pi.piId
        JOIN teams t ON t.artId = pi.artId
        LEFT JOIN capacities c ON i.iterationId = c.iterationId AND c.teamId = t.teamId
        WHERE pi.artId = $scopeId
        GROUP BY i.iterationId, i.iterationName, i.startDate, i.endDate, pi.piName
        ORDER BY i.startDate DESC LIMIT $periodLimit";
} elseif ($viewBy == 'team' && $scopeId > 0) {
    $sql = "
        SELECT i.iterationName, i.startDate, i.endDate, pi.piName,
               COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM iterations i
        JOIN program_increments pi ON i.piId = pi.piId
        LEFT JOIN capacities c ON i.iterationId = c.iterationId AND c.teamId = $scopeId
        GROUP BY i.iterationId, i.iterationName, i.startDate, i.endDate, pi.piName
        ORDER BY i.startDate DESC LIMIT $periodLimit";
} else {
    $sql = "
        SELECT i.iterationName, i.startDate, i.endDate, pi.piName,
               COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM iterations i
        JOIN program_increments pi ON i.piId = pi.piId
        LEFT JOIN capacities c ON i.iterationId = c.iterationId
        GROUP BY i.iterationId, i.iterationName, i.startDate, i.endDate, pi.piName
        ORDER BY i.startDate DESC LIMIT $periodLimit";
}

$results = $db->query($sql);
$rows = [];
while ($r = $results->fetch_assoc()) $rows[] = $r;
$rows = array_reverse($rows); // oldest first for trend

// --- Pie chart: capacity split by ART (org view) or by Team (art view) ---
$pieData = [];
if ($viewBy == 'organization') {
    $res = $db->query("
        SELECT a.artName as label, COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM arts a
        LEFT JOIN teams t ON a.artId = t.artId
        LEFT JOIN capacities c ON t.teamId = c.teamId
        GROUP BY a.artId, a.artName
        HAVING capacity > 0
        ORDER BY capacity DESC
    ");
    while ($r = $res->fetch_assoc()) $pieData[] = $r;
} elseif ($viewBy == 'art' && $scopeId > 0) {
    $res = $db->query("
        SELECT t.teamName as label, COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM teams t
        LEFT JOIN capacities c ON t.teamId = c.teamId
        WHERE t.artId = $scopeId
        GROUP BY t.teamId, t.teamName
        HAVING capacity > 0
        ORDER BY capacity DESC
    ");
    while ($r = $res->fetch_assoc()) $pieData[] = $r;
} elseif ($viewBy == 'team' && $scopeId > 0) {
    // For team view pie shows PI breakdown
    $res = $db->query("
        SELECT pi.piName as label, COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM program_increments pi
        JOIN iterations i ON i.piId = pi.piId
        LEFT JOIN capacities c ON c.iterationId = i.iterationId AND c.teamId = $scopeId
        GROUP BY pi.piId, pi.piName
        HAVING capacity > 0
        ORDER BY capacity DESC
    ");
    while ($r = $res->fetch_assoc()) $pieData[] = $r;
}

// --- Team comparison bar (only for org or art view) ---
$teamCompare = [];
if ($viewBy == 'organization') {
    $res = $db->query("
        SELECT t.teamName as label, COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM teams t
        LEFT JOIN capacities c ON t.teamId = c.teamId
        GROUP BY t.teamId, t.teamName
        HAVING capacity > 0
        ORDER BY capacity DESC
        LIMIT 15
    ");
    while ($r = $res->fetch_assoc()) $teamCompare[] = $r;
} elseif ($viewBy == 'art' && $scopeId > 0) {
    $res = $db->query("
        SELECT t.teamName as label, COALESCE(SUM(c.storyPoints), 0) as capacity
        FROM teams t
        LEFT JOIN capacities c ON t.teamId = c.teamId
        WHERE t.artId = $scopeId
        GROUP BY t.teamId, t.teamName
        ORDER BY capacity DESC
    ");
    while ($r = $res->fetch_assoc()) $teamCompare[] = $r;
}

// Scope label
$scopeLabel = 'Organization';
if ($viewBy == 'art' && $scopeId > 0) {
    $r = $db->query("SELECT artName FROM arts WHERE artId=$scopeId")->fetch_assoc();
    $scopeLabel = $r ? htmlspecialchars($r['artName']) : 'ART';
} elseif ($viewBy == 'team' && $scopeId > 0) {
    $r = $db->query("SELECT teamName FROM teams WHERE teamId=$scopeId")->fetch_assoc();
    $scopeLabel = $r ? htmlspecialchars($r['teamName']) : 'Team';
}

// Encode data for JS
$jsLabels      = json_encode(array_column($rows, 'iterationName'));
$jsCapacity    = json_encode(array_map(fn($r) => floatval($r['capacity']), $rows));
$jsPieLabels   = json_encode(array_column($pieData, 'label'));
$jsPieData     = json_encode(array_map(fn($r) => floatval($r['capacity']), $pieData));
$jsTeamLabels  = json_encode(array_column($teamCompare, 'label'));
$jsTeamData    = json_encode(array_map(fn($r) => floatval($r['capacity']), $teamCompare));
$hasData       = !empty($rows);
$hasPie        = !empty($pieData);
$hasTeam       = !empty($teamCompare);

// --- PROTOTYPE: Inline link tester ---
$testResults = [];
$testRan = isset($_GET['test']);
if ($testRan) {
    $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $currentPage = basename($_SERVER['SCRIPT_NAME']) . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] && $_SERVER['QUERY_STRING'] !== 'test' ? '?' . preg_replace('/[&?]?test=?1?/', '', $_SERVER['QUERY_STRING']) : '');
    // Fetch this page's HTML to extract links
    $ch = curl_init("$base/$currentPage");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true]);
    $html = curl_exec($ch);
    curl_close($ch);
    // Extract all hrefs
    preg_match_all('/href=["\']((?!#|mailto:|javascript:|http)[^"\']+)["\']/i', $html, $matches);
    $links = array_unique($matches[1]);
    foreach ($links as $link) {
        $link = ltrim($link, '/');
        if (strpos($link, 'test=') !== false) continue; // skip test links
        $ch = curl_init("$base/$link");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => true]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $testResults[$link] = $code;
    }
}
// --- END PROTOTYPE ---
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reports - CapacityHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
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
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        .chart-panel {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }
        .chart-panel.full-width {
            grid-column: 1 / -1;
        }
        .chart-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .chart-title {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-title i { color: #6366f1; }
        .chart-body {
            padding: 24px;
            position: relative;
        }
        .chart-subtitle {
            font-size: 12px;
            color: #9ca3af;
            font-weight: 500;
        }
        .scope-badge {
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
        .stat-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-top: 1px solid #e5e7eb;
        }
        .stat-strip-item {
            padding: 14px 20px;
            text-align: center;
            border-right: 1px solid #e5e7eb;
        }
        .stat-strip-item:last-child { border-right: none; }
        .stat-strip-val {
            font-size: 20px;
            font-weight: 800;
            color: #6366f1;
        }
        .stat-strip-lbl {
            font-size: 11px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">
            <div class="brand-logo"><i class="fas fa-chart-line"></i></div>
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
            <a href="?test=1" class="nav-tab" style="color:#6366f1;font-weight:600;"><i class="fas fa-flask" style="margin-right:4px;"></i>Test</a>
        </div>
        <div class="user-menu">
            <div class="notification-icon"><i class="far fa-bell"></i></div>
            <div class="user-avatar">AD</div>
        </div>
    </div>

    <div class="container">

<?php if ($testRan): ?>
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:20px 24px;margin-bottom:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <strong style="font-size:15px;">
            <i class="fas fa-flask" style="color:#6366f1;margin-right:8px;"></i>
            Link Test Results
            <?php $pass=count(array_filter($testResults,fn($c)=>$c===200)); $fail=count($testResults)-$pass; ?>
            <span style="color:#059669;margin-left:10px;">✓ <?= $pass ?> passed</span>
            <?php if($fail): ?><span style="color:#dc2626;margin-left:8px;">✗ <?= $fail ?> failed</span><?php endif; ?>
        </strong>
        <a href="<?= strtok($_SERVER['REQUEST_URI'],'?') ?>" style="font-size:13px;color:#6b7280;text-decoration:none;">✕ Close</a>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <?php foreach($testResults as $link => $code): ?>
        <tr style="border-top:1px solid #f3f4f6;">
            <td style="padding:6px 0;font-family:monospace;color:#374151;">
                <a href="<?= htmlspecialchars($link) ?>" target="_blank" style="color:#6366f1;text-decoration:none;"><?= htmlspecialchars($link) ?></a>
            </td>
            <td style="padding:6px 0;text-align:right;font-weight:700;color:<?= $code===200?'#059669':'#dc2626' ?>;">
                <?= $code===200 ? '✓ OK' : "✗ $code" ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

        <div class="page-header">
            <h1 class="page-title">Capacity Reports</h1>
            <p class="page-subtitle">Visual capacity trends across iterations</p>
        </div>

        <!-- Filters -->
        <div class="data-panel" style="margin-bottom:24px;">
            <div class="panel-header">
                <h2 class="panel-title">Filters</h2>
                <span class="scope-badge">
                    <?php if ($viewBy == 'organization'): ?>
                        <i class="fas fa-globe" style="margin-right:4px;"></i> <?= $scopeLabel ?>
                    <?php elseif ($viewBy == 'art'): ?>
                        <i class="fas fa-rocket" style="margin-right:4px;"></i> <?= $scopeLabel ?>
                    <?php else: ?>
                        <i class="fas fa-users" style="margin-right:4px;"></i> <?= $scopeLabel ?>
                    <?php endif; ?>
                    &mdash; Last <?= $period ?> iterations
                </span>
            </div>
            <form method="GET" action="reports.php">
                <div class="filters-grid">
                    <div class="select-group">
                        <label>View By</label>
                        <select name="viewBy" onchange="this.form.submit()">
                            <option value="organization" <?= $viewBy=='organization'?'selected':'' ?>>Organization</option>
                            <option value="art"          <?= $viewBy=='art'         ?'selected':'' ?>>ART</option>
                            <option value="team"         <?= $viewBy=='team'        ?'selected':'' ?>>Team</option>
                        </select>
                    </div>
                    <div class="select-group">
                        <label>
                            <?= $viewBy=='art' ? 'Select ART' : ($viewBy=='team' ? 'Select Team' : 'Scope') ?>
                        </label>
                        <?php if ($viewBy == 'organization'): ?>
                            <select disabled><option>All (Organization)</option></select>
                        <?php elseif ($viewBy == 'art'): ?>
                            <select name="scopeId" onchange="this.form.submit()">
                                <option value="">— Select ART —</option>
                                <?php $allArts->data_seek(0); while ($art = $allArts->fetch_assoc()): ?>
                                <option value="<?= $art['artId'] ?>" <?= $scopeId==$art['artId']?'selected':'' ?>>
                                    <?= htmlspecialchars($art['artName']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        <?php elseif ($viewBy == 'team'): ?>
                            <select name="scopeId" onchange="this.form.submit()">
                                <option value="">— Select Team —</option>
                                <?php foreach ($allTeams as $team): ?>
                                <option value="<?= $team['teamId'] ?>" <?= $scopeId==$team['teamId']?'selected':'' ?>>
                                    <?= htmlspecialchars($team['artName']) ?> &rsaquo; <?= htmlspecialchars($team['teamName']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="select-group">
                        <label>Period</label>
                        <select name="period" onchange="this.form.submit()">
                            <option value="6"  <?= $period==6  ?'selected':'' ?>>Last 6 iterations</option>
                            <option value="12" <?= $period==12 ?'selected':'' ?>>Last 12 iterations</option>
                            <option value="24" <?= $period==24 ?'selected':'' ?>>Last 24 iterations</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn">
                            <i class="fas fa-chart-bar" style="margin-right:6px;"></i>Apply
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!$hasData): ?>
        <div class="data-panel">
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <p>No capacity data found for the selected filters.</p>
            </div>
        </div>
        <?php else:
            // Summary stats
            $caps      = array_column($rows, 'capacity');
            $avgCap    = count($caps) ? round(array_sum($caps) / count($caps), 1) : 0;
            $maxCap    = count($caps) ? max($caps) : 0;
            $lastCap   = end($caps);
            $firstCap  = reset($caps);
            $trend     = $firstCap > 0 ? round((($lastCap - $firstCap) / $firstCap) * 100, 1) : 0;
        ?>

        <!-- Row 1: Bar chart (full width) -->
        <div class="charts-grid">
            <div class="chart-panel full-width">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-bar"></i>
                        Capacity per Iteration
                    </div>
                    <span class="chart-subtitle"><?= $scopeLabel ?> &mdash; last <?= $period ?> iterations</span>
                </div>
                <div class="chart-body" style="height:300px;">
                    <canvas id="barChart"></canvas>
                </div>
                <div class="stat-strip">
                    <div class="stat-strip-item">
                        <div class="stat-strip-val"><?= number_format($avgCap, 1) ?> SP</div>
                        <div class="stat-strip-lbl">Average</div>
                    </div>
                    <div class="stat-strip-item">
                        <div class="stat-strip-val"><?= number_format($maxCap, 1) ?> SP</div>
                        <div class="stat-strip-lbl">Peak</div>
                    </div>
                    <div class="stat-strip-item">
                        <div class="stat-strip-val" style="color:<?= $trend >= 0 ? '#10b981' : '#ef4444' ?>">
                            <?= $trend >= 0 ? '+' : '' ?><?= $trend ?>%
                        </div>
                        <div class="stat-strip-lbl">Overall Trend</div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Line chart + Pie chart -->
            <div class="chart-panel">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        Capacity Trend
                    </div>
                    <span class="chart-subtitle">Over time</span>
                </div>
                <div class="chart-body" style="height:280px;">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>

            <?php if ($hasPie): ?>
            <div class="chart-panel">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-chart-pie"></i>
                        <?php if ($viewBy == 'organization'): ?>Capacity by ART
                        <?php elseif ($viewBy == 'art'): ?>Capacity by Team
                        <?php else: ?>Capacity by PI<?php endif; ?>
                    </div>
                    <span class="chart-subtitle">All time totals</span>
                </div>
                <div class="chart-body" style="height:280px;">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Row 3: Team comparison (org + art view only) -->
            <?php if ($hasTeam): ?>
            <div class="chart-panel full-width">
                <div class="chart-header">
                    <div class="chart-title">
                        <i class="fas fa-users"></i>
                        Team Comparison
                    </div>
                    <span class="chart-subtitle">Total capacity by team (all time)</span>
                </div>
                <div class="chart-body" style="height:280px;">
                    <canvas id="teamChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <?php endif; ?>
    </div>

    <script>
    const PURPLE  = 'rgba(99,102,241,0.85)';
    const PURPLE_B = 'rgba(99,102,241,1)';
    const GREEN   = 'rgba(16,185,129,0.85)';
    const GREEN_B  = 'rgba(16,185,129,1)';
    const ORANGE  = 'rgba(245,158,11,0.85)';
    const PIE_COLORS = [
        '#6366f1','#10b981','#f59e0b','#3b82f6','#ef4444',
        '#8b5cf6','#06b6d4','#f97316','#84cc16','#ec4899'
    ];

    const labels   = <?= $jsLabels ?>;
    const capacity = <?= $jsCapacity ?>;

    // Bar Chart
    <?php if ($hasData): ?>
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Story Points',
                data: capacity,
                backgroundColor: PURPLE,
                borderColor: PURPLE_B,
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y.toFixed(1)} SP`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f3f4f6' },
                    ticks: { callback: v => v + ' SP' }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // Line Chart
    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Story Points',
                data: capacity,
                borderColor: GREEN_B,
                backgroundColor: 'rgba(16,185,129,0.08)',
                borderWidth: 2.5,
                pointBackgroundColor: GREEN_B,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.35,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y.toFixed(1)} SP`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f3f4f6' },
                    ticks: { callback: v => v + ' SP' }
                },
                x: { grid: { display: false } }
            }
        }
    });
    <?php endif; ?>

    // Pie Chart
    <?php if ($hasPie): ?>
    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: <?= $jsPieLabels ?>,
            datasets: [{
                data: <?= $jsPieData ?>,
                backgroundColor: PIE_COLORS,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { font: { size: 12 }, padding: 14 }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.parsed.toFixed(1)} SP`
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Team Comparison Chart
    <?php if ($hasTeam): ?>
    new Chart(document.getElementById('teamChart'), {
        type: 'bar',
        data: {
            labels: <?= $jsTeamLabels ?>,
            datasets: [{
                label: 'Story Points',
                data: <?= $jsTeamData ?>,
                backgroundColor: PIE_COLORS,
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y.toFixed(1)} SP`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f3f4f6' },
                    ticks: { callback: v => v + ' SP' }
                },
                x: { grid: { display: false } }
            }
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>