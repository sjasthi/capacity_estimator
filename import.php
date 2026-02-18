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
                    // Get or create ART
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
                    
                    // Get or create PI
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
                    
                    // Insert iteration
                    $stmt = $db->prepare("INSERT INTO iterations (iterationName, piId, startDate, endDate) VALUES (?,?,?,?)");
                    $stmt->bind_param("siss", $row['Iteration'], $piId, $row['Iteration Start Date'], $row['Iteration End Date']);
                    $stmt->execute();
                    $count++;
                    
                } elseif ($type == 'composition') {
                    // Get or create ART
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
                    
                    // Get or create Team
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
                    
                    // Get or create Person
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
                    
                    // Allocation
                    $alloc = $row['Role'] == 'Developer' ? 100 : ($row['Role'] == 'Product Owner' ? 60 : 50);
                    
                    // Insert team member
                    $stmt = $db->prepare("INSERT INTO team_members (teamId, personId, role, allocationPct) VALUES (?,?,?,?)");
                    $stmt->bind_param("iisi", $teamId, $personId, $row['Role'], $alloc);
                    $stmt->execute();
                    $count++;
                    
                } elseif ($type == 'capacity') {
                    // Get team ID
                    $stmt = $db->prepare("SELECT t.teamId FROM teams t JOIN arts a ON t.artId=a.artId WHERE a.artName=? AND t.teamName=?");
                    $stmt->bind_param("ss", $row['ART Name'], $row['Team Name']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows == 0) continue;
                    $teamId = $result->fetch_assoc()['teamId'];
                    
                    // Get iteration ID
                    $stmt = $db->prepare("SELECT i.iterationId FROM iterations i JOIN program_increments pi ON i.piId=pi.piId JOIN arts a ON pi.artId=a.artId WHERE a.artName=? AND pi.piName=? AND i.iterationName=?");
                    $stmt->bind_param("sss", $row['ART Name'], $row['Program Increment'], $row['Iteration']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows == 0) continue;
                    $iterationId = $result->fetch_assoc()['iterationId'];
                    
                    // Insert capacity
                    $stmt = $db->prepare("INSERT INTO capacities (teamId, iterationId, storyPoints) VALUES (?,?,?)");
                    $stmt->bind_param("iid", $teamId, $iterationId, $row['capacity']);
                    $stmt->execute();
                    $count++;
                }
            } catch (Exception $e) {
                // Skip errors
            }
        }
        fclose($handle);
        $message = "✓ Imported $count rows";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Import</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo"><h2>📊 Capacity</h2></div>
        <nav>
            <a href="index.php" class="nav-item"><span>🏠</span><span>Dashboard</span></a>
            <a href="reports.php" class="nav-item"><span>📈</span><span>Reports</span></a>
            <a href="import.php" class="nav-item active"><span>📤</span><span>Import</span></a>
        </nav>
    </div>

    <div class="main">
        <div class="header">
            <h1>Import Data</h1>
            <div class="user">Admin</div>
        </div>

        <?php if ($message): ?>
        <div class="content">
            <div style="padding:12px; background:#d1fae5; color:#065f46; border-radius:8px;">
                <?= htmlspecialchars($message) ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="content">
            <h2>Iteration Schedule</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="file" accept=".csv" class="file-input" required>
                <input type="hidden" name="type" value="iterations">
                <button type="submit" class="btn">Upload</button>
            </form>
        </div>

        <div class="content">
            <h2>Team Composition</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="file" accept=".csv" class="file-input" required>
                <input type="hidden" name="type" value="composition">
                <button type="submit" class="btn">Upload</button>
            </form>
        </div>

        <div class="content">
            <h2>Capacity Data</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="file" accept=".csv" class="file-input" required>
                <input type="hidden" name="type" value="capacity">
                <button type="submit" class="btn">Upload</button>
            </form>
        </div>
    </div>
</body>
</html>