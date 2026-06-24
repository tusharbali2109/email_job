<?php
include 'db.php';

$csvData = [];
$csvHeaders = [];
$dbColumns = ['id', 'name', 'email', 'contact', 'company', 'status'];
$mapping = $_POST['mapping'] ?? [];

// Step 1: Show CSV upload and preview
if($_FILES['csv']['tmp_name'] && !$mapping){
    $file = fopen($_FILES['csv']['tmp_name'], "r");
    $csvHeaders = fgetcsv($file); // Get headers
    
    // Get preview rows
    for($i = 0; $i < 5; $i++){
        if($row = fgetcsv($file, 1000, ",")){
            $csvData[] = $row;
        }
    }
    fclose($file);
    
    // Store CSV file temporarily
    $tempFile = 'temp_' . time() . '.csv';
    move_uploaded_file($_FILES['csv']['tmp_name'], $tempFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CSV Column Mapping — ReachOut</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0c0f14;
    --surface: #141820;
    --surface2: #1c2230;
    --border: #252d3d;
    --accent: #4fffb0;
    --text: #e8edf5;
    --muted: #6b7a99;
  }
  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    padding: 20px;
  }
  .container { max-width: 1200px; margin: 0 auto; }
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 20px; }
  .card-title { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; margin-bottom: 20px; }
  .mapping-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .mapping-column { background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 16px; }
  .mapping-label { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px; }
  .mapping-items { display: flex; flex-direction: column; gap: 8px; }
  .mapping-item {
    display: flex;
    align-items: center;
    padding: 10px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 13px;
  }
  .mapping-item.mapped { background: rgba(79,255,176,0.05); border-color: var(--accent); }
  select {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    padding: 8px;
    font-size: 12px;
    margin-left: auto;
    cursor: pointer;
  }
  .preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    margin-top: 12px;
  }
  .preview-table th {
    background: var(--surface2);
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid var(--border);
    font-weight: 600;
    color: var(--accent);
  }
  .preview-table td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--border);
    color: var(--muted);
  }
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: all 0.15s; }
  .btn-primary { background: var(--accent); color: var(--bg); }
  .btn-primary:hover { background: #6bffc0; }
  .btn-secondary { background: var(--surface2); color: var(--text); border: 1px solid var(--border); }
  .btn-secondary:hover { border-color: var(--accent); }
  .btn-group { display: flex; gap: 12px; margin-top: 20px; }
  @media (max-width: 768px) {
    .mapping-container { grid-template-columns: 1fr; }
    .btn { width: 100%; justify-content: center; }
  }
</style>
</head>
<body>
<div class="container">
  <div class="card">
    <div class="card-title">📋 Map Your CSV Columns</div>
    
    <div style="background:rgba(79,255,176,0.05);border-left:3px solid var(--accent);padding:12px;border-radius:4px;margin-bottom:16px;font-size:13px;">
      <strong>Instructions:</strong> Select which CSV column maps to which database column. Make sure each database column is mapped to the correct CSV column.
    </div>

    <form method="post">
      <input type="hidden" name="temp_file" value="<?= $tempFile ?>">
      
      <div class="mapping-container">
        <!-- CSV Columns (Left) -->
        <div class="mapping-column">
          <div class="mapping-label">📄 Your CSV Columns</div>
          <div class="mapping-items">
            <?php foreach($csvHeaders as $idx => $header): ?>
            <div class="mapping-item">
              <strong><?= htmlspecialchars(trim($header)) ?></strong>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Database Columns (Right) with Mapping -->
        <div class="mapping-column">
          <div class="mapping-label">🗄️ Database Columns (Map To)</div>
          <div class="mapping-items">
            <?php foreach($dbColumns as $dbCol): 
              if($dbCol === 'id' || $dbCol === 'status') continue; // Skip these
            ?>
            <div class="mapping-item">
              <span><?= htmlspecialchars($dbCol) ?></span>
              <select name="mapping[<?= htmlspecialchars($dbCol) ?>]" required>
                <option value="">-- Select --</option>
                <?php foreach($csvHeaders as $csvCol): ?>
                <option value="<?= htmlspecialchars(trim($csvCol)) ?>">
                  <?= htmlspecialchars(trim($csvCol)) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Preview -->
      <div style="margin-top:24px;">
        <div class="mapping-label">📊 Preview (First 5 Rows)</div>
        <table class="preview-table">
          <thead>
            <tr>
              <?php foreach($csvHeaders as $header): ?>
              <th><?= htmlspecialchars(trim($header)) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach($csvData as $row): ?>
            <tr>
              <?php foreach($row as $cell): ?>
              <td><?= htmlspecialchars(substr($cell, 0, 20)) ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="btn-group">
        <button type="button" class="btn btn-secondary" onclick="history.back()">← Back</button>
        <button type="submit" class="btn btn-primary">✅ Import with Mapping</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
<?php
    exit;
}

// Step 2: Process import with mapping
if($mapping && isset($_POST['temp_file'])){
    $tempFile = $_POST['temp_file'];
    
    if(!file_exists($tempFile)){
        die("Error: Temporary file not found");
    }
    
    $file = fopen($tempFile, "r");
    $headers = fgetcsv($file);
    
    // Create reverse mapping (CSV column index -> database column name)
    $columnMap = [];
    foreach($mapping as $dbCol => $csvCol){
        $csvIndex = array_search($csvCol, $headers);
        if($csvIndex !== false){
            $columnMap[$csvIndex] = $dbCol;
        }
    }
    
    $imported = 0;
    $failed = 0;
    
    while(($row = fgetcsv($file, 1000, ",")) !== FALSE){
        $data = [];
        
        foreach ($columnMap as $csvIdx => $dbCol) {
            if (isset($row[$csvIdx])) {
                $data[$dbCol] = trim($row[$csvIdx]);
            }
        }

        if (!empty($data['name']) && !empty($data['email'])) {
            $cols     = array_keys($data);
            $colStr   = implode(',', $cols) . ',status';
            $placeholders = implode(',', array_fill(0, count($cols), '?')) . ",'pending'";
            try {
                $ins = $pdo->prepare("INSERT INTO companies ($colStr) VALUES ($placeholders)");
                $ins->execute(array_values($data));
                $imported++;
            } catch (Exception $e) {
                $failed++;
            }
        }
    }
    
    fclose($file);
    unlink($tempFile); // Delete temp file
    
    // Redirect back with success message
    header("Location: index.php?import_success=$imported&import_failed=$failed");
    exit;
}

// Default: Show simple upload
header("Location: index.php");
?>
