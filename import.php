<?php
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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require 'db.php';
    $db = db();
    
    $type = $_POST['type'];
    $file = $_FILES['file']['tmp_name'];
    
    if (($handle = fopen($file, 'r')) !== FALSE) {
        $header = fgetcsv($handle);
        $count = 0;
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row = array_combine($header, $data);
            
            try {
                if ($type == 'iterations') {
                    $stmt = $db->prepare("SELECT artId FROM arts WHERE artName=?");
                    $stmt->bind_param("s", $row['ART Name']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $artId = $result->fetch_assoc()['artId'];
                    } else {
                        $stmt = $db->prepare("INSERT INTO arts (artName) VALUES (?)");
                        $stmt->bind_param("s", $row['ART Name']);
                        $stmt->execute();
                        $artId = $db->insert_id;
                    }
                    
                    $stmt = $db->prepare("SELECT piId FROM program_increments WHERE piName=? AND artId=?");
                    $stmt->bind_param("si", $row['Program Increment'], $artId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $piId = $result->fetch_assoc()['piId'];
                    } else {
                        $stmt = $db->prepare("INSERT INTO program_increments (piName, artId, startDate, endDate) VALUES (?,?,?,?)");
                        $stmt->bind_param("siss", $row['Program Increment'], $artId, $row['Iteration Start Date'], $row['Iteration End Date']);
                        $stmt->execute();
                        $piId = $db->insert_id;
                    }
                    
                    $stmt = $db->prepare("INSERT INTO iterations (iterationName, piId, startDate, endDate) VALUES (?,?,?,?)");
                    $stmt->bind_param("siss", $row['Iteration'], $piId, $row['Iteration Start Date'], $row['Iteration End Date']);
                    $stmt->execute();
                    $count++;
                    
                } elseif ($type == 'composition') {
                    $stmt = $db->prepare("SELECT artId FROM arts WHERE artName=?");
                    $stmt->bind_param("s", $row['ART Name']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $artId = $result->fetch_assoc()['artId'];
                    } else {
                        $stmt = $db->prepare("INSERT INTO arts (artName) VALUES (?)");
                        $stmt->bind_param("s", $row['ART Name']);
                        $stmt->execute();
                        $artId = $db->insert_id;
                    }
                    
                    $stmt = $db->prepare("SELECT teamId FROM teams WHERE teamName=? AND artId=?");
                    $stmt->bind_param("si", $row['Scrum Team Name'], $artId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $teamId = $result->fetch_assoc()['teamId'];
                    } else {
                        $stmt = $db->prepare("INSERT INTO teams (teamName, artId) VALUES (?,?)");
                        $stmt->bind_param("si", $row['Scrum Team Name'], $artId);
                        $stmt->execute();
                        $teamId = $db->insert_id;
                    }
                    
                    $stmt = $db->prepare("SELECT personId FROM persons WHERE email=?");
                    $stmt->bind_param("s", $row['Team Member Email']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $personId = $result->fetch_assoc()['personId'];
                    } else {
                        $stmt = $db->prepare("INSERT INTO persons (name, email) VALUES (?,?)");
                        $stmt->bind_param("ss", $row['Team Member Name'], $row['Team Member Email']);
                        $stmt->execute();
                        $personId = $db->insert_id;
                    }
                    
                    $alloc = $row['Role'] == 'Developer' ? 100 : ($row['Role'] == 'Product Owner' ? 60 : 50);
                    
                    $stmt = $db->prepare("INSERT INTO team_members (teamId, personId, role, allocationPct) VALUES (?,?,?,?)");
                    $stmt->bind_param("iisi", $teamId, $personId, $row['Role'], $alloc);
                    $stmt->execute();
                    $count++;
                    
                } elseif ($type == 'capacity') {
                    $stmt = $db->prepare("SELECT t.teamId FROM teams t JOIN arts a ON t.artId=a.artId WHERE a.artName=? AND t.teamName=?");
                    $stmt->bind_param("ss", $row['ART Name'], $row['Team Name']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows == 0) continue;
                    $teamId = $result->fetch_assoc()['teamId'];
                    
                    $stmt = $db->prepare("SELECT i.iterationId FROM iterations i JOIN program_increments pi ON i.piId=pi.piId JOIN arts a ON pi.artId=a.artId WHERE a.artName=? AND pi.piName=? AND i.iterationName=?");
                    $stmt->bind_param("sss", $row['ART Name'], $row['Program Increment'], $row['Iteration']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows == 0) continue;
                    $iterationId = $result->fetch_assoc()['iterationId'];
                    
                    $stmt = $db->prepare("INSERT INTO capacities (teamId, iterationId, storyPoints) VALUES (?,?,?)");
                    $stmt->bind_param("iid", $teamId, $iterationId, $row['capacity']);
                    $stmt->execute();
                    $count++;
                }
            } catch (Exception $e) {}
        }
        fclose($handle);
        $message = "✓ Successfully imported $count rows";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Import Data - CapacityHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
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
            <a href="index.php" class="nav-tab">Dashboard</a>
            <a href="arts.php" class="nav-tab">ARTs</a>
            <a href="teams.php" class="nav-tab">Teams</a>
            <a href="iterations.php" class="nav-tab">Iterations</a>
            <a href="capacity.php" class="nav-tab">Capacity Entry</a>
            <a href="reports.php" class="nav-tab">Reports</a>
            <a href="import.php" class="nav-tab">Import</a>
            <a href="export.php" class="nav-tab">Export</a>
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
                    <span class="test-result-status <?= $result['ok'] ? 'ok' : 'error' ?>"><?= $result['ok'] ? '✓ ' . $result['code'] : '✗ ' . $result['code'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($testResults)): ?>
                <div class="test-result-row"><span style="color: #6b7280;">No internal links found to test.</span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <h1 class="page-title">Import Data</h1>
            <p class="page-subtitle">Upload CSV files to populate capacity data</p>
        </div>

        <?php if ($message): ?>
        <div class="success-message">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="data-panel">
            <div class="panel-header">
                <h2 class="panel-title">Iteration Schedule</h2>
            </div>
            <div style="padding: 24px 28px;">
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="file" accept=".csv" class="file-input" required>
                    <input type="hidden" name="type" value="iterations">
                    <button type="submit" class="btn">Upload Iterations</button>
                </form>
            </div>
        </div>

        <div class="data-panel">
            <div class="panel-header">
                <h2 class="panel-title">Team Composition</h2>
            </div>
            <div style="padding: 24px 28px;">
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="file" accept=".csv" class="file-input" required>
                    <input type="hidden" name="type" value="composition">
                    <button type="submit" class="btn">Upload Team Composition</button>
                </form>
            </div>
        </div>

        <div class="data-panel">
            <div class="panel-header">
                <h2 class="panel-title">Capacity Data</h2>
            </div>
            <div style="padding: 24px 28px;">
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="file" accept=".csv" class="file-input" required>
                    <input type="hidden" name="type" value="capacity">
                    <button type="submit" class="btn">Upload Capacity</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>