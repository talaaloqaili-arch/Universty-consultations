<?php
require_once __DIR__ . "/admin_auth.php";

$stats = null;
$error = "";

try {
    $reservationStats = $pdo->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'pending'), 0) AS pending,
                COALESCE(SUM(status = 'confirmed'), 0) AS confirmed,
                COALESCE(SUM(status = 'rejected'), 0) AS rejected,
                COALESCE(SUM(status = 'cancelled'), 0) AS cancelled
         FROM reservations"
    )->fetch();
    $totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    $stats = [
        "total" => (int) $reservationStats["total"],
        "pending" => (int) $reservationStats["pending"],
        "confirmed" => (int) $reservationStats["confirmed"],
        "rejected" => (int) $reservationStats["rejected"],
        "cancelled" => (int) $reservationStats["cancelled"],
        "users" => $totalUsers,
    ];
} catch (PDOException $exception) {
    error_log("Admin dashboard database error: " . $exception->getMessage());
    $error = "Dashboard statistics could not be loaded. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>Admin dashboard | University Consultations</title>
    <link rel="stylesheet" href="assets.css?v=3">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<header class="site-header">
    <nav class="nav-wrap" aria-label="Main navigation">
        <a class="brand" href="admin_dashboard.php">
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span>University Consultations<small>Administration</small></span>
        </a>
        <div class="nav-links">
            <a href="manage_slots.php">Manage appointment times</a>
            <a class="nav-optional" href="availability.php">Student availability</a>
            <a class="nav-cta" href="logout.php">Log out</a>
        </div>
    </nav>
</header>
<main class="page-wrap">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Dashboard overview</h1>
            <p>Monitor student accounts and the status of academic advising appointments.</p>
        </div>
        <a class="button" href="manage_slots.php">Manage appointment times <span aria-hidden="true">→</span></a>
    </div>

    <?php if ($error !== ""): ?>
        <p class="notice error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p>
    <?php elseif ($stats !== null): ?>
        <div class="admin-overview">
            <section class="admin-stats" aria-label="System statistics">
                <article class="admin-stat">
                    <span class="admin-stat-icon" aria-hidden="true">▦</span>
                    <p>Total reservations</p>
                    <strong><?= $stats["total"] ?></strong>
                </article>
                <article class="admin-stat">
                    <span class="admin-stat-icon pending-icon" aria-hidden="true">◷</span>
                    <p>Pending</p>
                    <strong><?= $stats["pending"] ?></strong>
                </article>
                <article class="admin-stat">
                    <span class="admin-stat-icon confirmed-icon" aria-hidden="true">✓</span>
                    <p>Confirmed</p>
                    <strong><?= $stats["confirmed"] ?></strong>
                </article>
                <article class="admin-stat">
                    <span class="admin-stat-icon users-icon" aria-hidden="true">♙</span>
                    <p>Registered users</p>
                    <strong><?= $stats["users"] ?></strong>
                </article>
            </section>

            <section class="admin-chart panel">
                <div>
                    <p class="eyebrow">Reservation activity</p>
                    <h2>Reservations by status</h2>
                </div>
                <div class="admin-chart-canvas">
                    <canvas id="reservationsChart" aria-label="Reservations grouped by status"></canvas>
                </div>
            </section>
        </div>
    <?php endif; ?>
</main>
<footer class="site-footer">University Consultations · Administration</footer>
<?php if ($stats !== null): ?>
<script>
    const chartElement = document.getElementById("reservationsChart");
    if (typeof Chart !== "undefined" && chartElement) {
        new Chart(chartElement, {
            type: "doughnut",
            data: {
                labels: ["Pending", "Confirmed", "Rejected", "Cancelled"],
                datasets: [{
                    data: <?= json_encode(
                        [$stats["pending"], $stats["confirmed"], $stats["rejected"], $stats["cancelled"]],
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    ) ?>,
                    backgroundColor: ["#f2a541", "#2b7a78", "#d95d5d", "#9aa9b8"],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: "bottom" }
                }
            }
        });
    }
</script>
<?php endif; ?>
</body>
</html>