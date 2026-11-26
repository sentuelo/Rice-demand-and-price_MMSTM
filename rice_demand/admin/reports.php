<?php
include_once "../conn.php";
include_once "../demand_helper.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$reportMessage = '';
$reportSuccess = false;

//MONTH FILTER
$selectedMonth = trim($_GET['month'] ?? ($_POST['month'] ?? ''));
if ($selectedMonth === '' || !preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

// Compute first/last day of selected month
$monthStart = $selectedMonth . '-01';
$monthEnd   = date('Y-m-t', strtotime($monthStart));
$today      = date('Y-m-d');

$defaultReportDate = ($today >= $monthStart && $today <= $monthEnd) ? $today : $monthStart;

// HANDLE SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $report_date   = trim($_POST['report_date'] ?? '');
    $active_filter = trim($_POST['active_filter'] ?? 'All');
    $postMonth     = trim($_POST['month'] ?? $selectedMonth);

    // Ensure posted month is valid; use it for both lookup and date checks
    if (!preg_match('/^\d{4}-\d{2}$/', $postMonth)) {
        $postMonth = $selectedMonth;
    }
    $mStart = $postMonth . '-01';
    $mEnd   = date('Y-m-t', strtotime($mStart));

    if ($report_date === '') {
        $reportMessage = 'Please enter the report date.';
        $reportSuccess = false;
    } elseif ($report_date < $mStart || $report_date > $mEnd) {
        $reportMessage = "Report date must be within the selected month ({$postMonth}).";
        $reportSuccess = false;
    } else {
        // Fetch vendor reports FOR THE SELECTED MONTH only
        $lookup = $conn->prepare("
            SELECT w.weekly_id, w.demand_level, w.variety,
            w.beginning_stock, w.quantity_sold,
            r.price_kilo, r.price_sacks
            FROM tb_weekly w
            LEFT JOIN tb_rice r ON r.vendors_id = w.vendors_id AND r.variety = w.variety
            WHERE DATE_FORMAT(w.week_start, '%Y-%m') = :m
            ORDER BY w.weekly_id DESC
        ");
        $lookup->execute([':m' => $postMonth]);
        $vendorReports = $lookup->fetchAll(PDO::FETCH_ASSOC);

        // Sort by sell
        usort($vendorReports, function ($a, $b) {
            $begA = (float)($a['beginning_stock'] ?? 0);
            $begB = (float)($b['beginning_stock'] ?? 0);
            $pctA = $begA > 0 ? ((float)$a['quantity_sold'] / $begA) : 0;
            $pctB = $begB > 0 ? ((float)$b['quantity_sold'] / $begB) : 0;
            if (abs($pctA - $pctB) < 0.0000001) {
                return (int)($b['weekly_id'] ?? 0) - (int)($a['weekly_id'] ?? 0);
            }
            return ($pctA < $pctB) ? 1 : -1;
        });

        if ($active_filter === 'All') {
            $source       = $vendorReports[0] ?? null;
            $demand_level = $source['demand_level'] ?? '';
        } else {
            $source = null;
            foreach ($vendorReports as $vr) {
                if (trim((string)($vr['demand_level'] ?? '')) === $active_filter) {
                    $source = $vr;
                    break;
                }
            }
            $demand_level = $active_filter;
        }

        if (!$source) {
            $reportMessage = "No vendor report found for {$postMonth} with the selected demand level.";
            $reportSuccess = false;
        } else {
            try {
                // Insert new row
                $ins = $conn->prepare("
                    INSERT INTO tb_admin_reports
                        (variety, price_kilo, price_sacks, demand_level, report_date)
                    VALUES (:v, :pk, :ps, :dl, :rd)
                ");
                $ins->execute([
                    ':v'  => $source['variety'],
                    ':pk' => $source['price_kilo'],
                    ':ps' => $source['price_sacks'],
                    ':dl' => $demand_level,
                    ':rd' => $report_date,
                ]);

                // Redirect with success flag
                header('Location: reports.php?success=1&month=' . urlencode($postMonth));
                exit;
            } catch (PDOException $e) {
                $reportMessage = 'Database error: ' . $e->getMessage();
                $reportSuccess = false;
            }
        }
    }

    // keep selected month after POST
    $selectedMonth = $postMonth;
    $monthStart = $selectedMonth . '-01';
    $monthEnd   = date('Y-m-t', strtotime($monthStart));
}

//CHECK FOR SUCCESS ALERT DATA
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $reportSuccess = true;
    $reportMessage = "Weekly report submitted successfully.";
}

// FETCH VENDOR REPORTS for display (filtered by month)
$stmt = $conn->prepare("
    SELECT
    w.weekly_id,
    w.week_start,
    w.week_end,
    w.demand_level,
    w.variety,
    w.vendors_id,
    w.beginning_stock,
    w.quantity_sold,
    COALESCE(s.stall_name, CONCAT(v.f_name, ' ', v.l_name), 'Unknown Vendor') AS vendor_name,
    r.price_kilo,
    r.price_sacks
    FROM tb_weekly w
    LEFT JOIN tb_stall s ON w.stall_id = s.stall_id
    LEFT JOIN tb_vendors v ON w.vendors_id = v.vendors_id
    LEFT JOIN tb_rice  r ON r.vendors_id = w.vendors_id AND r.variety = w.variety
    WHERE DATE_FORMAT(w.week_start, '%Y-%m') = :m
    ORDER BY w.weekly_id DESC
");
$stmt->execute([':m' => $selectedMonth]);
$vendorReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sort by sell-through % high -> low
usort($vendorReports, function ($a, $b) {
    $begA = (float)($a['beginning_stock'] ?? 0);
    $begB = (float)($b['beginning_stock'] ?? 0);
    $pctA = $begA > 0 ? ((float)$a['quantity_sold'] / $begA) : 0;
    $pctB = $begB > 0 ? ((float)$b['quantity_sold'] / $begB) : 0;
    if (abs($pctA - $pctB) < 0.0000001) {
        return (int)($b['weekly_id'] ?? 0) - (int)($a['weekly_id'] ?? 0);
    }
    return ($pctA < $pctB) ? 1 : -1;
});

// Admin's own history (filtered by month on report_date)
// Ordered by date DESC then report_id DESC so newest duplicate shows first.
$stmt = $conn->prepare("
    SELECT * FROM tb_admin_reports
    WHERE DATE_FORMAT(report_date, '%Y-%m') = :m
    ORDER BY report_date DESC, report_id DESC
");
$stmt->execute([':m' => $selectedMonth]);
$myAdminReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Badge helper
function demand_badge_class(?string $dl): string {
    $dl = trim((string)$dl);
    if ($dl === 'High Demand')   return 'badge-high';
    if ($dl === 'Low Demand')    return 'badge-low';
    return 'badge-medium';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report page</title>
    <link rel="stylesheet" href="../css/style.css">
    
</head>

<body class="admin-body">
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">👤</div>
                <div class="brand-text"><h2>Welcome</h2><span>Admin</span></div>
            </div>

            <nav class="nav">
                <a href="../admin/dashboard.php" class="nav-item">
                    <span class="nav-icon">📊</span><span>Dashboard</span>
                </a>
                <a href="../admin/reports.php" class="nav-item active">
                    <span class="nav-icon">📄</span><span>Reports</span>
                </a>
                <a href="../admin/stall.php" class="nav-item">
                    <span class="nav-icon">🏪</span><span>Stalls</span>
                </a>
                <a href="../admin/rice.php" class="nav-item">
                    <span class="nav-icon">🌾</span><span>Rice Varieties</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="../logout.php" class="logout-btn"><span>Log out</span></a>
            </div>
        </aside>

        <main class="main">
            <header class="page-head">
                <div><h1>Reports</h1></div>
            </header>

            <section class="panel">
                <div class="panel-head">
                    <div><h2>Submit Report</h2></div>
                    <form method="GET" action="reports.php" class="month-form" style="display:flex;align-items:center;gap:8px;">
                        <label for="monthPicker" style="font-weight:600;">Filter by Month:</label>
                        <input type="month" id="monthPicker" name="month"
                               value="<?= htmlspecialchars($selectedMonth) ?>"
                               onchange="this.form.submit()">
                    </form>
                </div>

                <!-- SUCCESS / ERROR ALERT MESSAGE -->
                <?php if ($reportMessage !== ''): ?>
                    <div class="alert <?= $reportSuccess ? 'alert-success' : 'alert-error' ?>">
                        <?= $reportMessage ?>
                    </div>
                <?php endif; ?>

                <div class="filter-bar" id="filterBar">
                    <button type="button" class="filter-btn active" data-filter="All">All</button>
                    <button type="button" class="filter-btn" data-filter="High Demand">High Demand</button>
                    <button type="button" class="filter-btn" data-filter="Medium Demand">Medium Demand</button>
                    <button type="button" class="filter-btn" data-filter="Low Demand">Low Demand</button>
                </div>

                <div class="table-wrap">
                    <table class="data-table" id="vendorReportsTable">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th>Variety</th>
                                <th>Beg.</th>
                                <th>Sold</th>
                                <th>Sold %</th>
                                <th>Price / Kilo</th>
                                <th>Price / Sacks</th>
                                <th>Demand</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($vendorReports) === 0): ?>
                                <tr><td colspan="8" style="text-align:center;">No vendor reports for <?= htmlspecialchars($selectedMonth) ?>.</td></tr>
                            <?php else: ?>
                                <?php foreach ($vendorReports as $r): ?>
                                    <?php
                                        $dl = trim((string)($r['demand_level'] ?? ''));
                                        if ($dl === 'High Demand')       $cat = 'High Demand';
                                        elseif ($dl === 'Low Demand')    $cat = 'Low Demand';
                                        elseif ($dl === 'Medium Demand') $cat = 'Medium Demand';
                                        else                             $cat = 'Medium Demand';

                                        $beg  = (float)($r['beginning_stock'] ?? 0);
                                        $sold = (float)($r['quantity_sold'] ?? 0);
                                        $pct  = $beg > 0 ? ($sold / $beg) : 0;
                                        $pctLabel = number_format($pct * 100, 1) . '%';
                                    ?>
                                    <tr data-demand="<?= htmlspecialchars($cat) ?>">
                                        <td><?= htmlspecialchars($r['vendor_name'] ?? '—') ?></td>
                                        <td><strong><?= htmlspecialchars($r['variety'] ?? '—') ?></strong></td>
                                        <td><?= (int)$beg ?></td>
                                        <td><strong><?= (int)$sold ?></strong></td>
                                        <td>
                                            <span class="pct-tag <?= demand_badge_class($cat) ?>">
                                                <?= htmlspecialchars($pctLabel) ?>
                                            </span>
                                        </td>
                                        <td>₱<?= number_format((float)($r['price_kilo'] ?? 0), 2) ?></td>
                                        <td>₱<?= number_format((float)($r['price_sacks'] ?? 0), 2) ?></td>
                                        <td>
                                            <span class="badge <?= demand_badge_class($cat) ?>">
                                                <?= htmlspecialchars($cat) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="filter-empty" id="filterEmpty" style="display:none;">
                        theres no have report for this category
                    </div>
                </div>

                <form method="POST" action="reports.php" id="reportForm" class="form-grid" style="margin-top:16px;">
                    <input type="hidden" name="active_filter" id="activeFilterInput" value="All">
                    <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">

                    <div class="field">
                        <label>Report Date</label>
                        <input type="date" name="report_date"
                               min="<?= htmlspecialchars($monthStart) ?>"
                               max="<?= htmlspecialchars($monthEnd) ?>"
                               value="<?= htmlspecialchars($defaultReportDate) ?>"
                               required>
                        <small style="color:#666;">Only dates within <?= htmlspecialchars($selectedMonth) ?> are allowed.</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="submit_report" class="btn-primary">Submit Report</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div><h2>My Submitted Reports — <?= htmlspecialchars($selectedMonth) ?></h2></div>
                </div>

                <?php if (count($myAdminReports) === 0): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>No reports submitted for <?= htmlspecialchars($selectedMonth) ?>.</p>
                    </div>
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
                                <?php foreach ($myAdminReports as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['report_date']) ?></td>
                                        <td><strong><?= htmlspecialchars($r['variety']) ?></strong></td>
                                        <td>₱<?= htmlspecialchars($r['price_kilo']) ?></td>
                                        <td>₱<?= htmlspecialchars($r['price_sacks']) ?></td>
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
        </main>
    </div>

    <script src="../js/reports.js"></script>
</body>
</html>