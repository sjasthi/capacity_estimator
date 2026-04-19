<?php
require 'db.php';
$db = db();
// === Inline Link Tester ===
$testRan = false;
$testResults = [];
$pass = 0;
$fail = 0;
$closeUrl = '';
$testLink = '';

$testParams = $_GET;
$testParams['test'] = '1';
$testLink = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($testParams);

if (isset($_GET['test']) && $_GET['test'] == '1') {
    $testRan = true;
    $closeParams = $_GET;
    unset($closeParams['test']);
    $closeUrl = basename($_SERVER['PHP_SELF']);
    if (!empty($closeParams)) {
        $closeUrl .= '?' . http_build_query($closeParams);
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']) . '/' . basename($_SERVER['PHP_SELF']);
    $fetchParams = $_GET;
    unset($fetchParams['test']);
    $fetchUrl = $scheme . '://' . $host . $path;
    if (!empty($fetchParams)) {
        $fetchUrl .= '?' . http_build_query($fetchParams);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fetchUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $html = curl_exec($ch);
    curl_close($ch);
    $links = [];
    if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
        $links = array_unique($matches[1]);
    }
    $baseUrl = $scheme . '://' . $host . dirname($_SERVER['PHP_SELF']) . '/';
    foreach ($links as $link) {
        if (preg_match('/^(https?:\/\/|#|mailto:|javascript:|tel:)/i', $link)) continue;
        if (strpos($link, 'test=') !== false) continue;
        if (empty(trim($link))) continue;
        $checkUrl = $baseUrl . $link;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $checkUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ok = ($httpCode >= 200 && $httpCode < 400);
        if ($ok) { $pass++; } else { $fail++; }
        $testResults[] = array('link' => $link, 'code' => $httpCode, 'ok' => $ok);
    }
}
// === End Inline Link Tester ===


$message = '';
$messageType = '';

// Step tracking
$selectedArtId  = intval($_POST['artId'] ?? 0);
$selectedTeamId = intval($_POST['teamId'] ?? 0);
$selectedPiId   = intval($_POST['piId'] ?? 0);
$selectedIterId = intval($_POST['iterationId'] ?? 0);

// Handle final capacity submission
if (isset($_POST['submit_capacity']) && $selectedTeamId && $selectedIterId) {
    $totalSP     = 0;
    $memberCount = intval($_POST['member_count'] ?? 0);

    for ($i = 0; $i < $memberCount; $i++) {
        $load    = floatval($_POST["load_$i"] ?? 0);
        $timeOff = floatval($_POST["timeoff_$i"] ?? 0);
        $sp      = (8 - $timeOff) * ($load / 100);
        $totalSP += $sp;
    }

    $stmt = $db->prepare("INSERT INTO capacities (teamId, iterationId, storyPoints, createdAt) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iid", $selectedTeamId, $selectedIterId, $totalSP);
    if ($stmt->execute()) {
        $message     = "Capacity of " . number_format($totalSP, 2) . " SP submitted successfully.";
        $messageType = 'success';
    } else {
        $message     = "Error saving capacity. Please try again.";
        $messageType = 'error';
    }
}

// Load ARTs
$arts = $db->query("SELECT artId, artName FROM arts ORDER BY artName");

// Load Teams for selected ART
$teams = null;
if ($selectedArtId) {
    $teams = $db->query("SELECT teamId, teamName FROM teams WHERE artId=$selectedArtId ORDER BY teamName");
}

// Load PIs for selected ART
$pis = null;
if ($selectedArtId) {
    $pis = $db->query("SELECT piId, piName FROM program_increments WHERE artId=$selectedArtId ORDER BY startDate DESC");
}

// Load Iterations for selected PI
$iterations = null;
$iterInfo   = '';
if ($selectedPiId) {
    $iterations = $db->query("SELECT iterationId, iterationName, startDate, endDate FROM iterations WHERE piId=$selectedPiId ORDER BY startDate");
}

// Load selected iteration date info
if ($selectedIterId) {
    $iterRow = $db->query("SELECT iterationName, startDate, endDate FROM iterations WHERE iterationId=$selectedIterId")->fetch_assoc();
    if ($iterRow) {
        $iterInfo = htmlspecialchars($iterRow['iterationName']) . ' &nbsp;&mdash;&nbsp; ' . $iterRow['startDate'] . ' to ' . $iterRow['endDate'];
    }
}

// Load members for selected team
$members = [];
if ($selectedTeamId) {
    $result = $db->query("
        SELECT p.name, p.email, tm.role, tm.allocationPct
        FROM team_members tm
        JOIN persons p ON tm.personId = p.personId
        WHERE tm.teamId = $selectedTeamId
        ORDER BY CASE tm.role
            WHEN 'Scrum Master' THEN 1
            WHEN 'Product Owner' THEN 2
            WHEN 'Release Train Engineer' THEN 3
            ELSE 4 END, p.name
    ");
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
}

// Load submission history for selected team
$history = [];
if ($selectedTeamId) {
    $result = $db->query("
        SELECT c.storyPoints, c.createdAt, i.iterationName, pi.piName
        FROM capacities c
        JOIN iterations i ON c.iterationId = i.iterationId
        JOIN program_increments pi ON i.piId = pi.piId
        WHERE c.teamId = $selectedTeamId
        ORDER BY c.createdAt DESC
        LIMIT 10
    ");
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
}

// Pre-calculate SP per member using submitted or default values
$totalSP    = 0;
$memberCalc = [];
foreach ($members as $i => $m) {
    $load    = floatval($_POST["load_$i"] ?? $m['allocationPct']);
    $timeOff = floatval($_POST["timeoff_$i"] ?? 0);
    $sp      = (8 - $timeOff) * ($load / 100);
    $memberCalc[] = ['load' => $load, 'timeOff' => $timeOff, 'sp' => $sp];
    $totalSP += $sp;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Capacity Entry - CapacityHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .entry-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
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
        .iter-info {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
            min-height: 18px;
        }
        .member-table input[type="number"] {
            width: 85px;
            padding: 7px 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
            color: #111827;
        }
        .member-table input[type="number"]:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .sp-cell {
            font-weight: 700;
            color: #059669;
            font-size: 15px;
        }
        .total-row td {
            font-weight: 700;
            color: #4338ca;
            font-size: 15px;
            background: #f5f3ff;
            border-top: 2px solid #6366f1 !important;
            padding-top: 16px;
            padding-bottom: 16px;
        }
        .submit-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 28px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .total-summary {
            display: flex;
            align-items: baseline;
            gap: 10px;
        }
        .total-label {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }
        .total-sp-display {
            font-size: 28px;
            font-weight: 800;
            color: #6366f1;
        }
        .placeholder-state {
            padding: 60px 28px;
            text-align: center;
            color: #9ca3af;
        }
        .placeholder-state i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
            color: #d1d5db;
        }
        .placeholder-state p { font-size: 15px; }
        .info-bar {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .alert-message {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .history-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .btn-secondary {
            padding: 8px 16px;
            background: transparent;
            color: #6b7280;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-secondary:hover { border-color: #6366f1; color: #6366f1; }
        .section-divider {
            border-top: 1px solid #e5e7eb;
            padding: 20px 28px 16px;
            background: #f9fafb;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
            .test-panel { background: #fefce8; border: 2px solid #facc15; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; }
        .test-panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .test-panel-title { font-size: 16px; font-weight: 700; color: #854d0e; display: flex; align-items: center; gap: 8px; }
        .test-panel-close { background: #fef3c7; color: #92400e; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .test-panel-close:hover { background: #fde68a; }
        .test-summary { display: flex; gap: 16px; margin-bottom: 16px; }
        .test-stat { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; }
        .test-stat.pass { background: #dcfce7; color: #166534; }
        .test-stat.fail { background: #fee2e2; color: #991b1b; }
        .test-results { max-height: 300px; overflow-y: auto; background: white; border: 1px solid #e5e7eb; border-radius: 8px; }
        .test-result-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
        .test-result-row:last-child { border-bottom: none; }
        .test-result-link { color: #374151; font-family: monospace; word-break: break-all; }
        .test-result-status { padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; white-space: nowrap; }
        .test-result-status.ok { background: #dcfce7; color: #166534; }
        .test-result-status.error { background: #fee2e2; color: #991b1b; }
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
            <a href="index.php"      class="nav-tab">Dashboard</a>
            <a href="arts.php"       class="nav-tab">ARTs</a>
            <a href="teams.php"      class="nav-tab">Teams</a>
            <a href="iterations.php" class="nav-tab">Iterations</a>
            <a href="capacity.php"   class="nav-tab">Capacity Entry</a>
            <a href="reports.php"    class="nav-tab">Reports</a>
            <a href="import.php"     class="nav-tab">Import</a>
            <a href="export.php"     class="nav-tab">Export</a>
            <a href="<?= htmlspecialchars($testLink) ?>" class="nav-tab" style="color: #8b5cf6;"><i class="fas fa-flask"></i> Test</a>
            </div>
        <div class="user-menu">
            <div class="notification-icon">
                <i class="far fa-bell"></i>
            </div>
            <div class="user-avatar">AD</div>
        </div>
    </div>

    <div class="container">

        <?php if ($testRan): ?>
        <div class="test-panel">
            <div class="test-panel-header">
                <div class="test-panel-title"><i class="fas fa-flask"></i> Link Test Results</div>
                <a href="<?= htmlspecialchars($closeUrl) ?>" class="test-panel-close"><i class="fas fa-times"></i> Close</a>
            </div>
            <div class="test-summary">
                <div class="test-stat pass"><i class="fas fa-check-circle"></i> <?= $pass ?> Passed</div>
                <div class="test-stat fail"><i class="fas fa-times-circle"></i> <?= $fail ?> Failed</div>
            </div>
            <div class="test-results">
                <?php foreach ($testResults as $result): ?>
                <div class="test-result-row">
                    <span class="test-result-link"><?= htmlspecialchars($result['link']) ?></span>
                    <span class="test-result-status <?= $result['ok'] ? 'ok' : 'error' ?>"><?= $result['ok'] ? '+ ' . $result['code'] : 'x ' . $result['code'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($testResults)): ?>
                <div class="test-result-row"><span style="color: #6b7280;">No internal links found to test.</span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>


        <div class="page-header">
            <h1 class="page-title">Capacity Entry</h1>
            <p class="page-subtitle">Select an ART, Team, and Iteration to enter capacity</p>
        </div>

        <?php if ($message): ?>
        <div class="alert-message alert-<?= $messageType ?>">
            <i class="fas fa-<?= $messageType == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
        <?php endif; ?>

        <div class="info-bar">
            <i class="fas fa-info-circle"></i>
            Formula: <strong style="margin:0 4px;">(8 - Time Off Days) &times; Default Load%</strong> &mdash; 2 weeks = 8 SP &nbsp;|&nbsp; each day missed = -1 SP &nbsp;|&nbsp; 8 hours = 1 SP.
        </div>

        <div class="data-panel" style="margin-bottom: 24px;">

            <form method="POST" action="capacity.php">

                <!-- Selection -->
                <div class="panel-header">
                    <h2 class="panel-title">Select ART, Team &amp; Iteration</h2>
                </div>
                <div style="padding: 24px 28px 28px;">
                    <div class="entry-grid">

                        <div class="select-group">
                            <label><i class="fas fa-rocket" style="margin-right:6px;"></i>Agile Release Train</label>
                            <select name="artId" onchange="this.form.submit()">
                                <option value="">— Select ART —</option>
                                <?php $arts->data_seek(0); while ($art = $arts->fetch_assoc()): ?>
                                <option value="<?= $art['artId'] ?>" <?= $selectedArtId == $art['artId'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($art['artName']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="select-group">
                            <label><i class="fas fa-users" style="margin-right:6px;"></i>Scrum Team</label>
                            <select name="teamId" <?= !$teams ? 'disabled' : '' ?> onchange="this.form.submit()">
                                <option value="">— Select Team —</option>
                                <?php if ($teams): $teams->data_seek(0); while ($team = $teams->fetch_assoc()): ?>
                                <option value="<?= $team['teamId'] ?>" <?= $selectedTeamId == $team['teamId'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($team['teamName']) ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <div class="select-group">
                            <label><i class="far fa-calendar-alt" style="margin-right:6px;"></i>Program Increment</label>
                            <select name="piId" <?= !$pis ? 'disabled' : '' ?> onchange="this.form.submit()">
                                <option value="">— Select PI —</option>
                                <?php if ($pis): $pis->data_seek(0); while ($pi = $pis->fetch_assoc()): ?>
                                <option value="<?= $pi['piId'] ?>" <?= $selectedPiId == $pi['piId'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pi['piName']) ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <div class="select-group">
                            <label><i class="fas fa-sync-alt" style="margin-right:6px;"></i>Iteration</label>
                            <select name="iterationId" <?= !$iterations ? 'disabled' : '' ?> onchange="this.form.submit()">
                                <option value="">— Select Iteration —</option>
                                <?php if ($iterations): $iterations->data_seek(0); while ($iter = $iterations->fetch_assoc()): ?>
                                <option value="<?= $iter['iterationId'] ?>" <?= $selectedIterId == $iter['iterationId'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($iter['iterationName']) ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <?php if ($iterInfo): ?>
                            <div class="iter-info"><?= $iterInfo ?></div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <!-- Member Table -->
                <?php if ($selectedTeamId && $selectedIterId && count($members) > 0): ?>

                <div class="section-divider">
                    <i class="fas fa-table" style="margin-right:6px;color:#6366f1;"></i>
                    Team Members &mdash; Adjust Time Off &amp; Default Load, then Submit
                </div>

                <table class="member-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Default Load %</th>
                            <th>Time Off (days)</th>
                            <th>Story Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $i => $m):
                            $roleClass = $m['role'] == 'Scrum Master' ? 'sm' : ($m['role'] == 'Product Owner' ? 'po' : 'dev');
                            $names    = explode(' ', $m['name']);
                            $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));
                            $calc     = $memberCalc[$i];
                        ?>
                        <tr>
                            <td>
                                <div class="team-info">
                                    <div class="avatar"><?= $initials ?></div>
                                    <div class="team-details">
                                        <h4><?= htmlspecialchars($m['name']) ?></h4>
                                        <p><?= htmlspecialchars($m['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="role-badge <?= $roleClass ?>"><?= htmlspecialchars($m['role']) ?></span></td>
                            <td>
                                <input type="number" name="load_<?= $i ?>"
                                    value="<?= $calc['load'] ?>"
                                    min="0" max="100" step="5"
                                    onchange="this.form.submit()">
                            </td>
                            <td>
                                <input type="number" name="timeoff_<?= $i ?>"
                                    value="<?= $calc['timeOff'] ?>"
                                    min="0" max="10" step="1"
                                    onchange="this.form.submit()">
                            </td>
                            <td class="sp-cell"><?= number_format($calc['sp'], 2) ?> SP</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="4" style="text-align:right; padding-right:28px;">Total Capacity</td>
                            <td class="sp-cell"><?= number_format($totalSP, 2) ?> SP</td>
                        </tr>
                    </tfoot>
                </table>

                <input type="hidden" name="member_count" value="<?= count($members) ?>">

                <div class="submit-row">
                    <div class="total-summary">
                        <span class="total-label">Total:</span>
                        <span class="total-sp-display"><?= number_format($totalSP, 2) ?></span>
                        <span style="font-size:14px;color:#6b7280;">story points</span>
                    </div>
                    <div style="display:flex;gap:12px;align-items:center;">
                        <a href="capacity.php" class="btn-secondary">
                            <i class="fas fa-undo"></i> Start Over
                        </a>
                        <button type="submit" name="submit_capacity" value="1" class="btn">
                            <i class="fas fa-check" style="margin-right:8px;"></i>Submit Capacity
                        </button>
                    </div>
                </div>

                <?php elseif ($selectedTeamId && $selectedIterId && count($members) == 0): ?>
                <div class="placeholder-state">
                    <i class="fas fa-user-slash"></i>
                    <p>No members found for this team. Import team composition first.</p>
                </div>

                <?php else: ?>
                <div class="placeholder-state">
                    <i class="fas fa-hand-point-up"></i>
                    <p>Select an ART, Team, Program Increment, and Iteration above to load team members.</p>
                </div>
                <?php endif; ?>

            </form>
        </div>

        <!-- Submission History -->
        <?php if (count($history) > 0): ?>
        <div class="data-panel">
            <div class="panel-header">
                <h2 class="panel-title">Submission History</h2>
                <span class="history-badge">Last 10 entries for this team</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Program Increment</th>
                        <th>Iteration</th>
                        <th>Capacity</th>
                        <th>Submitted At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($h['piName']) ?></td>
                        <td><?= htmlspecialchars($h['iterationName']) ?></td>
                        <td><span class="capacity-pill"><?= number_format($h['storyPoints'], 2) ?> SP</span></td>
                        <td style="color:#6b7280;font-size:13px;"><?= htmlspecialchars($h['createdAt'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>