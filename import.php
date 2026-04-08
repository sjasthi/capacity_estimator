<?php
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
    <title>Import Data - CapacityHub</title>
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
            <div class="notification-icon">
                <i class="far fa-bell"></i>
            </div>
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