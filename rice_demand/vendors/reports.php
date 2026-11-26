<?php
include_once "../conn.php";
include_once "../demand_helper.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$vendor_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT vendors_id, f_name, l_name
    FROM tb_vendors
    WHERE vendors_id = :id
    LIMIT 1
");
$stmt->execute([':id' => $vendor_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

$firstName  = trim($vendor['f_name'] ?? '');
$lastName   = trim($vendor['l_name'] ?? '');
$vendorName = trim($firstName . ' ' . $lastName);
if ($vendorName === '') $vendorName = 'Vendor';

$initials = strtoupper(
    ($firstName !== '' ? $firstName[0] : '')
    . ($lastName !== '' ? $lastName[0] : '')
);
if ($initials === '') $initials = 'V';

//RICE VARIETIES 
$riceStmt = $conn->prepare("
    SELECT rice_id, variety, quantity, date_added
    FROM tb_rice
    WHERE vendors_id = :id
    ORDER BY variety ASC
");
$riceStmt->execute([':id' => $vendor_id]);
$riceVarieties = $riceStmt->fetchAll(PDO::FETCH_ASSOC);

// Map variety => max sacks (registered quantity)
$varietyLimits = [];
foreach ($riceVarieties as $rv) {
    $varietyLimits[$rv['variety']] = (int)$rv['quantity'];
}

//HANDLE VENDOR SUBMIT (PRG pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $beginning_stock = trim($_POST['beginning_stock'] ?? '');
    $variety         = trim($_POST['variety'] ?? '');
    $remaining_stock = trim($_POST['remaining_stock'] ?? '');
    $quantity_sold   = trim($_POST['quantity_sold'] ?? '');
    $week_start      = trim($_POST['week_start'] ?? '');
    $week_end        = trim($_POST['week_end'] ?? '');

    $errorMsg = '';

    if ($beginning_stock === '' || $variety === '' || $remaining_stock === '' ||
        $quantity_sold === '' || $week_start === '' || $week_end === '') {
        $errorMsg = 'Please fill in all report fields.';
    }
    elseif (!is_numeric($beginning_stock) || (int)$beginning_stock <= 0) {
        $errorMsg = 'Beginning stock must be a valid positive number.';
    }
    elseif (!is_numeric($quantity_sold) || (float)$quantity_sold < 0) {
        $errorMsg = 'Quantity sold must be a valid number.';
    }
    elseif (!is_numeric($remaining_stock) || (float)$remaining_stock < 0) {
        $errorMsg = 'Remaining stock must be a valid number.';
    }
    elseif (!isset($varietyLimits[$variety])) {
        $errorMsg = 'Selected rice variety is not registered under your account.';
    }
    else {
        $maxSacks = $varietyLimits[$variety];
        $beg      = (int)$beginning_stock;
        $sold     = (float)$quantity_sold;
        $rem      = (float)$remaining_stock;

        if ($beg > $maxSacks) {
            $errorMsg = "Beginning stock for {$variety} cannot exceed {$maxSacks} sack(s) — this is the quantity you registered in My Rice.";
        }
        elseif ($sold > $beg) {
            $errorMsg = "Quantity sold cannot exceed the beginning stock ({$beg} sack(s)).";
        }
        elseif (abs(($beg - $sold) - $rem) > 0.0001) {
            $errorMsg = "Remaining stock must equal beginning stock minus quantity sold (" . ($beg - $sold) . ").";
        }
    }

    if ($errorMsg !== '') {
        $_SESSION['flash_error']   = $errorMsg;
        $_SESSION['flash_success'] = false;
    } else {
        // Demand uses BOTH beginning stock and quantity sold
        $demand_level = calculate_demand_level($quantity_sold, $beginning_stock);

        try {
            $ins = $conn->prepare("
                INSERT INTO tb_weekly
                (vendors_id, beginning_stock, variety, remaining_stock,
                quantity_sold, week_start, week_end, demand_level)
                VALUES (:vid, :b, :v, :r, :q, :ws, :we, :dl)
            ");
            $ins->execute([
                ':vid' => $vendor_id,
                ':b'   => $beginning_stock,
                ':v'   => $variety,
                ':r'   => $remaining_stock,
                ':q'   => $quantity_sold,
                ':ws'  => $week_start,
                ':we'  => $week_end,
                ':dl'  => $demand_level,
            ]);
            $_SESSION['flash_error']   = '';
            $_SESSION['flash_success'] = true;
            $_SESSION['flash_message'] = 'Weekly report submitted successfully.';
        } catch (PDOException $e) {
            $_SESSION['flash_error']   = 'Database error: ' . $e->getMessage();
            $_SESSION['flash_success'] = false;
        }
    }

    header('Location: reports.php');
    exit;
}

// READ FLASH MESSAGE
$reportMessage = '';
$reportSuccess = false;
if (isset($_SESSION['flash_error']) || isset($_SESSION['flash_message'])) {
    if (!empty($_SESSION['flash_message'])) {
        $reportMessage = $_SESSION['flash_message'];
        $reportSuccess = !empty($_SESSION['flash_success']);
    } elseif (!empty($_SESSION['flash_error'])) {
        $reportMessage = $_SESSION['flash_error'];
        $reportSuccess = false;
    }
    unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['flash_message']);
}

//VENDOR HISTORY (fetch, then sort by sell-through % high -> low)
$stmt = $conn->prepare("SELECT * FROM tb_weekly WHERE vendors_id = :id ORDER BY week_start DESC");
$stmt->execute([':id' => $vendor_id]);
$myReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

usort($myReports, function ($a, $b) {
    $begA = (float)($a['beginning_stock'] ?? 0);
    $begB = (float)($b['beginning_stock'] ?? 0);

    $pctA = $begA > 0 ? ((float)$a['quantity_sold'] / $begA) : 0;
    $pctB = $begB > 0 ? ((float)$b['quantity_sold'] / $begB) : 0;

    if (abs($pctA - $pctB) < 0.0000001) {
        // tie: newest week first
        return strcmp($b['week_start'] ?? '', $a['week_start'] ?? '');
    }
    return ($pctA < $pctB) ? 1 : -1;
});

//ADMIN REPORT HISTORY
$adminStmt = $conn->prepare("SELECT * FROM tb_admin_reports ORDER BY report_date DESC");
$adminStmt->execute();
$adminReports = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Page</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">👤</div>
                <div class="brand-text">
                    <h2 title="<?= htmlspecialchars($vendorName) ?>"><?= htmlspecialchars($vendorName) ?></h2>
                    <span>Vendor</span>
                </div>
            </div>
            <nav class="nav">
                <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span>Dashboard</span></a>
                <a href="rice.php" class="nav-item"><span class="nav-icon">🌾</span><span>My Rice</span></a>
                <a href="reports.php" class="nav-item active"><span class="nav-icon">📄</span><span>Reports</span></a>
            </nav>
            <div class="sidebar-footer">
                <a href="../logout.php" class="logout-btn"><span>Log out</span></a>
            </div>
        </aside>

        <main class="main">
            <section class="panel">
                <div class="panel-head">
                    <div><h2>Submit New Report</h2></div>
                </div>
                <?php if ($reportMessage !== ''): ?>
                    <div class="alert <?= $reportSuccess ? 'alert-success' : 'alert-error' ?>">
                        <?= htmlspecialchars($reportMessage) ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="reports.php" class="form-grid" id="reportForm">
                    <div class="field">
                        <label>Variety of Rice</label>
                        <select name="variety" id="varietySelect" required>
                            <option value="">Select variety</option>
                            <?php foreach ($riceVarieties as $rv): ?>
                                <option value="<?= htmlspecialchars($rv['variety']) ?>"
                                        data-max="<?= (int)$rv['quantity'] ?>"
                                        data-date-added="<?= htmlspecialchars($rv['date_added'] ?? '') ?>">
                                    <?= htmlspecialchars($rv['variety']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="stockHint" style="color:#666;"></small>
                    </div>
                    <div class="field">
                        <label>Beginning Stock</label>
                        <input type="number" name="beginning_stock" id="beginningStock"
                        min="1" step="1" required readonly>
                        <small id="begHint" style="color:#666;"></small>
                    </div>
                    <div class="field">
                        <label>Quantity Sold</label>
                        <input type="number" name="quantity_sold" id="quantitySold"
                        min="0" step="1" required>
                        <small id="soldHint" style="color:#666;"></small>
                    </div>
                    <div class="field">
                        <label>Remaining Stock</label>
                        <input type="number" name="remaining_stock" id="remainingStock"
                        min="0" step="1" required readonly>
                    </div>
                    <div class="field">
                        <label>Week Start</label>
                        <input type="date" name="week_start" id="weekStart" required>
                        <small id="wsHint" style="color:#666;"></small>
                    </div>
                    <div class="field">
                        <label>Week End</label>
                        <input type="date" name="week_end" id="weekEnd" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="submit_report" class="btn-primary">Submit Report</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div><h2>My Report History</h2></div>
                </div>
                <?php if (count($myReports) === 0): ?>
                    <div class="empty-state"><div class="empty-icon">📭</div><p>No reports yet.</p></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Week Start</th>
                                    <th>Week End</th>
                                    <th>Beg.</th>
                                    <th>Variety</th>
                                    <th>Rem.</th>
                                    <th>Sold</th>
                                    <th>Sold %</th>
                                    <th>Demand</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myReports as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['week_start']) ?></td>
                                        <td><?= htmlspecialchars($r['week_end']) ?></td>
                                        <td><?= htmlspecialchars($r['beginning_stock']) ?></td>
                                        <td><strong><?= htmlspecialchars($r['variety']) ?></strong></td>
                                        <td><?= htmlspecialchars($r['remaining_stock']) ?></td>
                                        <td><strong><?= htmlspecialchars($r['quantity_sold']) ?></strong></td>
                                        <td>
                                            <span class="pct-tag <?= demand_badge_class($r['demand_level'] ?? '') ?>">
                                                <?= htmlspecialchars(format_sell_through_percent($r['quantity_sold'], $r['beginning_stock'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= demand_badge_class($r['demand_level'] ?? '') ?>">
                                                <?= htmlspecialchars($r['demand_level'] ?? '—') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div><h2>Admin Report History</h2></div>
                </div>
                <?php if (count($adminReports) === 0): ?>
                    <div class="empty-state"><div class="empty-icon">📭</div><p>No admin reports yet.</p></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Report Date</th>
                                    <th>Variety</th>
                                    <th>Price/Kilo</th>
                                    <th>Price/Sack</th>
                                    <th>Demand</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminReports as $ar): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ar['report_date']) ?></td>
                                        <td><strong><?= htmlspecialchars($ar['variety']) ?></strong></td>
                                        <td>₱<?= htmlspecialchars($ar['price_kilo']) ?></td>
                                        <td>₱<?= htmlspecialchars($ar['price_sacks']) ?></td>
                                        <td>
                                            <span class="badge <?= demand_badge_class($ar['demand_level'] ?? '') ?>">
                                                <?= htmlspecialchars($ar['demand_level'] ?? '—') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="../js/venports.js"></script>

</body>

</html>