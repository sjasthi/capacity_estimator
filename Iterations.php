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

// Handle Add PI
if (isset($_POST['add_pi'])) {
    $name      = trim($_POST['piName'] ?? '');
    $artId     = intval($_POST['artId'] ?? 0);
    $startDate = trim($_POST['startDate'] ?? '');
    $endDate   = trim($_POST['endDate']   ?? '');
    if ($name && $artId && $startDate && $endDate) {
        $stmt = $db->prepare("INSERT INTO program_increments (piName, artId, startDate, endDate) VALUES (?,?,?,?)");
        $stmt->bind_param("siss", $name, $artId, $startDate, $endDate);
        if ($stmt->execute()) {
            $message = "Program Increment \"$name\" added.";
            $messageType = 'success';
        } else {
            $message = "Error adding PI.";
            $messageType = 'error';
        }
    }
}

// Handle Edit PI
if (isset($_POST['edit_pi'])) {
    $id        = intval($_POST['piId']);
    $name      = trim($_POST['piName'] ?? '');
    $artId     = intval($_POST['artId'] ?? 0);
    $startDate = trim($_POST['startDate'] ?? '');
    $endDate   = trim($_POST['endDate']   ?? '');
    if ($id && $name && $artId) {
        $stmt = $db->prepare("UPDATE program_increments SET piName=?, artId=?, startDate=?, endDate=? WHERE piId=?");
        $stmt->bind_param("sissi", $name, $artId, $startDate, $endDate, $id);
        if ($stmt->execute()) {
            $message = "PI updated.";
            $messageType = 'success';
        }
    }
}

// Handle Delete PI
if (isset($_POST['delete_pi'])) {
    $id = intval($_POST['piId']);
    if ($id) {
        $stmt = $db->prepare("DELETE FROM program_increments WHERE piId=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "PI deleted.";
            $messageType = 'success';
        } else {
            $message = "Error: PI may have iterations attached.";
            $messageType = 'error';
        }
    }
}

// Handle Add Iteration
if (isset($_POST['add_iter'])) {
    $name      = trim($_POST['iterName']  ?? '');
    $piId      = intval($_POST['piId']    ?? 0);
    $startDate = trim($_POST['startDate'] ?? '');
    $endDate   = trim($_POST['endDate']   ?? '');
    if ($name && $piId && $startDate && $endDate) {
        $stmt = $db->prepare("INSERT INTO iterations (iterationName, piId, startDate, endDate) VALUES (?,?,?,?)");
        $stmt->bind_param("siss", $name, $piId, $startDate, $endDate);
        if ($stmt->execute()) {
            $message = "Iteration \"$name\" added.";
            $messageType = 'success';
        } else {
            $message = "Error adding iteration.";
            $messageType = 'error';
        }
    }
}

// Handle Edit Iteration
if (isset($_POST['edit_iter'])) {
    $id        = intval($_POST['iterId']);
    $name      = trim($_POST['iterName']  ?? '');
    $startDate = trim($_POST['startDate'] ?? '');
    $endDate   = trim($_POST['endDate']   ?? '');
    if ($id && $name) {
        $stmt = $db->prepare("UPDATE iterations SET iterationName=?, startDate=?, endDate=? WHERE iterationId=?");
        $stmt->bind_param("sssi", $name, $startDate, $endDate, $id);
        if ($stmt->execute()) {
            $message = "Iteration updated.";
            $messageType = 'success';
        }
    }
}

// Handle Delete Iteration
if (isset($_POST['delete_iter'])) {
    $id = intval($_POST['iterId']);
    if ($id) {
        $stmt = $db->prepare("DELETE FROM iterations WHERE iterationId=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Iteration deleted.";
            $messageType = 'success';
        } else {
            $message = "Error: Iteration may have capacity data attached.";
            $messageType = 'error';
        }
    }
}

$filterArtId  = intval($_GET['artId']    ?? 0);
$expandPiId   = intval($_GET['expand']   ?? 0);
$editPiId     = intval($_GET['editPi']   ?? 0);
$editIterId   = intval($_GET['editIter'] ?? 0);

// Load ARTs
$allArts = $db->query("SELECT artId, artName FROM arts ORDER BY artName");

// Load PIs
$whereSql = $filterArtId ? "WHERE pi.artId=$filterArtId" : "";
$pis = $db->query("
    SELECT pi.piId, pi.piName, pi.startDate, pi.endDate, a.artId, a.artName,
           COUNT(i.iterationId) as iterCount
    FROM program_increments pi
    JOIN arts a ON pi.artId = a.artId
    LEFT JOIN iterations i ON pi.piId = i.piId
    $whereSql
    GROUP BY pi.piId, pi.piName, pi.startDate, pi.endDate, a.artId, a.artName
    ORDER BY pi.startDate DESC
");

// Load iterations for expanded PI
$expandedIters = [];
if ($expandPiId) {
    $res = $db->query("SELECT iterationId, iterationName, startDate, endDate FROM iterations WHERE piId=$expandPiId ORDER BY startDate");
    while ($r = $res->fetch_assoc()) $expandedIters[] = $r;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Iterations - CapacityHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .inline-form { display: inline; }
        .btn-sm {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-edit   { background: #eff6ff; color: #1e40af; }
        .btn-edit:hover { background: #dbeafe; }
        .btn-danger { background: #fef2f2; color: #991b1b; }
        .btn-danger:hover { background: #fee2e2; }
        .btn-save   { background: #6366f1; color: white; }
        .btn-save:hover { background: #5558e3; }
        .btn-cancel { background: #f3f4f6; color: #374151; }
        .btn-cancel:hover { background: #e5e7eb; }
        .btn-green  { background: #d1fae5; color: #065f46; }
        .btn-green:hover { background: #a7f3d0; }
        .inline-edit-input, .inline-date, .inline-select {
            padding: 7px 10px;
            border: 1px solid #6366f1;
            border-radius: 6px;
            font-size: 13px;
            color: #111827;
            outline: none;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .inline-edit-input { width: 160px; }
        .inline-date       { width: 140px; }
        .inline-select     { width: 180px; background: white; }
        .add-panel {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 18px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .add-panel input, .add-panel select {
            padding: 9px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }
        .add-panel input[type="text"]  { width: 180px; }
        .add-panel input[type="date"]  { width: 150px; }
        .add-panel select              { width: 200px; }
        .add-panel input:focus, .add-panel select:focus {
            outline: none; border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .iters-subpanel {
            background: #f9fafb;
            border-top: 1px solid #f3f4f6;
        }
        .iters-subpanel table thead { background: #f0f0ff; }
        .iters-subpanel table th { font-size: 11px; padding: 10px 24px; }
        .iters-subpanel table td { padding: 11px 24px; }
        .error-message {
            padding: 12px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .filter-bar {
            padding: 20px 28px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .filter-bar label { font-size: 13px; font-weight: 600; color: #6b7280; }
        .filter-bar select {
            padding: 8px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            outline: none;
        }
        .filter-bar select:focus { border-color: #6366f1; }
        .date-range {
            font-size: 12px;
            color: #9ca3af;
        }
        .pi-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f5f3ff;
            color: #6d28d9;
            border: 1px solid #ddd6fe;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        .iter-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #eff6ff;
            color: #1e40af;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
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
            <a href="<?= htmlspecialchars($testLink) ?>" class="nav-tab" style="color: #8b5cf6;"><i class="fas fa-flask"></i> Test</a>
            </div>
        <div class="user-menu">
            <div class="notification-icon"><i class="far fa-bell"></i></div>
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
            <h1 class="page-title">Iterations</h1>
            <p class="page-subtitle">Manage Program Increments and their iterations</p>
        </div>

        <?php if ($message): ?>
        <div class="<?= $messageType === 'success' ? 'success-message' : 'error-message' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="data-panel">
            <!-- Filter bar -->
            <form method="GET" action="iterations.php">
                <div class="filter-bar">
                    <label><i class="fas fa-filter" style="margin-right:5px;"></i>Filter by ART:</label>
                    <select name="artId" onchange="this.form.submit()">
                        <option value="">All ARTs</option>
                        <?php $allArts->data_seek(0); while ($a = $allArts->fetch_assoc()): ?>
                        <option value="<?= $a['artId'] ?>" <?= $filterArtId == $a['artId'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['artName']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>

            <div class="panel-header" style="border-top:1px solid #e5e7eb;">
                <h2 class="panel-title">Program Increments</h2>
                <span style="font-size:13px;color:#6b7280;"><?= $pis->num_rows ?> total</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Program Increment</th>
                        <th>ART</th>
                        <th>Date Range</th>
                        <th>Iterations</th>
                        <th style="width:240px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $pis->data_seek(0); while ($pi = $pis->fetch_assoc()):
                    $isExpanded = ($expandPiId == $pi['piId']);
                    $isEditing  = ($editPiId   == $pi['piId']);
                ?>
                    <tr>
                    <?php if ($isEditing): ?>
                        <td colspan="4">
                            <form method="POST" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <input type="hidden" name="piId" value="<?= $pi['piId'] ?>">
                                <input class="inline-edit-input" type="text" name="piName" value="<?= htmlspecialchars($pi['piName']) ?>" placeholder="PI Name" required autofocus>
                                <select class="inline-select" name="artId" required>
                                    <?php $allArts->data_seek(0); while ($a = $allArts->fetch_assoc()): ?>
                                    <option value="<?= $a['artId'] ?>" <?= $pi['artId'] == $a['artId'] ? 'selected' : '' ?>><?= htmlspecialchars($a['artName']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <input class="inline-date" type="date" name="startDate" value="<?= $pi['startDate'] ?>">
                                <input class="inline-date" type="date" name="endDate"   value="<?= $pi['endDate'] ?>">
                                <button type="submit" name="edit_pi" class="btn-sm btn-save"><i class="fas fa-check"></i> Save</button>
                                <a href="iterations.php?<?= $filterArtId ? 'artId='.$filterArtId : '' ?>" class="btn-sm btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                            </form>
                        </td>
                        <td></td>
                    <?php else: ?>
                        <td>
                            <span class="pi-badge"><i class="fas fa-layer-group"></i><?= htmlspecialchars($pi['piName']) ?></span>
                        </td>
                        <td style="font-size:13px;color:#6b7280;"><?= htmlspecialchars($pi['artName']) ?></td>
                        <td class="date-range"><?= htmlspecialchars($pi['startDate']) ?> &rarr; <?= htmlspecialchars($pi['endDate']) ?></td>
                        <td><span style="font-size:13px;color:#6b7280;"><i class="fas fa-sync-alt" style="margin-right:4px;"></i><?= $pi['iterCount'] ?> iterations</span></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <a href="iterations.php?<?= $filterArtId ? 'artId='.$filterArtId.'&' : '' ?>expand=<?= $isExpanded ? 0 : $pi['piId'] ?>"
                                   class="btn-sm <?= $isExpanded ? 'btn-save' : 'btn-green' ?>">
                                    <i class="fas fa-<?= $isExpanded ? 'chevron-up' : 'list' ?>"></i>
                                    <?= $isExpanded ? 'Collapse' : 'Iterations' ?>
                                </a>
                                <a href="iterations.php?<?= $filterArtId ? 'artId='.$filterArtId.'&' : '' ?>editPi=<?= $pi['piId'] ?><?= $isExpanded ? '&expand='.$pi['piId'] : '' ?>"
                                   class="btn-sm btn-edit">
                                    <i class="fas fa-pencil-alt"></i> Edit
                                </a>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this PI and all its iterations?')">
                                    <input type="hidden" name="piId" value="<?= $pi['piId'] ?>">
                                    <button type="submit" name="delete_pi" class="btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    <?php endif; ?>
                    </tr>

                    <?php if ($isExpanded): ?>
                    <tr>
                        <td colspan="5" style="padding:0;">
                            <div class="iters-subpanel">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Iteration</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th style="width:180px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($expandedIters as $iter):
                                        $isEditIter = ($editIterId == $iter['iterationId']);
                                    ?>
                                        <tr>
                                        <?php if ($isEditIter): ?>
                                            <td colspan="3">
                                                <form method="POST" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                                    <input type="hidden" name="iterId" value="<?= $iter['iterationId'] ?>">
                                                    <input class="inline-edit-input" type="text" name="iterName"  value="<?= htmlspecialchars($iter['iterationName']) ?>" required autofocus style="width:160px;">
                                                    <input class="inline-date" type="date" name="startDate" value="<?= $iter['startDate'] ?>">
                                                    <input class="inline-date" type="date" name="endDate"   value="<?= $iter['endDate'] ?>">
                                                    <button type="submit" name="edit_iter" class="btn-sm btn-save"><i class="fas fa-check"></i> Save</button>
                                                    <a href="iterations.php?<?= $filterArtId ? 'artId='.$filterArtId.'&' : '' ?>expand=<?= $expandPiId ?>" class="btn-sm btn-cancel"><i class="fas fa-times"></i></a>
                                                </form>
                                            </td>
                                            <td></td>
                                        <?php else: ?>
                                            <td><span class="iter-badge"><i class="fas fa-sync-alt"></i><?= htmlspecialchars($iter['iterationName']) ?></span></td>
                                            <td class="date-range"><?= htmlspecialchars($iter['startDate']) ?></td>
                                            <td class="date-range"><?= htmlspecialchars($iter['endDate']) ?></td>
                                            <td>
                                                <div style="display:flex;gap:6px;">
                                                    <a href="iterations.php?<?= $filterArtId ? 'artId='.$filterArtId.'&' : '' ?>expand=<?= $expandPiId ?>&editIter=<?= $iter['iterationId'] ?>"
                                                       class="btn-sm btn-edit"><i class="fas fa-pencil-alt"></i> Edit</a>
                                                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this iteration?')">
                                                        <input type="hidden" name="iterId" value="<?= $iter['iterationId'] ?>">
                                                        <button type="submit" name="delete_iter" class="btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>

                                    <!-- Add iteration row -->
                                    <tr>
                                        <td colspan="4" style="padding:0;">
                                            <form method="POST">
                                                <input type="hidden" name="piId" value="<?= $expandPiId ?>">
                                                <div class="add-panel" style="border-top:1px dashed #e5e7eb;">
                                                    <i class="fas fa-plus-circle" style="color:#6366f1;font-size:16px;"></i>
                                                    <input type="text" name="iterName"  placeholder="Iteration name" required>
                                                    <input type="date" name="startDate" required>
                                                    <input type="date" name="endDate"   required>
                                                    <button type="submit" name="add_iter" class="btn" style="padding:8px 16px;font-size:13px;">
                                                        <i class="fas fa-plus" style="margin-right:5px;"></i>Add Iteration
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Add new PI -->
            <form method="POST">
                <div class="add-panel">
                    <i class="fas fa-plus-circle" style="color:#6366f1;font-size:18px;"></i>
                    <input type="text" name="piName" placeholder="New PI name..." required>
                    <select name="artId" required>
                        <option value="">— Select ART —</option>
                        <?php $allArts->data_seek(0); while ($a = $allArts->fetch_assoc()): ?>
                        <option value="<?= $a['artId'] ?>" <?= $filterArtId == $a['artId'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['artName']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <input type="date" name="startDate" required>
                    <input type="date" name="endDate"   required>
                    <button type="submit" name="add_pi" class="btn">
                        <i class="fas fa-plus" style="margin-right:6px;"></i>Add PI
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>