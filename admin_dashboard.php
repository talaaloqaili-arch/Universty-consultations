<?php
require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/user.php";
require_once __DIR__ . "/reservation.php";

$stats = null;
$error = "";
$message = "";
$reservations = [];
$users = [];
$userModel = new User($pdo);
$reservationModel = new Reservation($pdo);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_is_valid()) {
        $error = "Your session token is invalid. Refresh the page and try again.";
    } else {
        try {
            $action = is_string($_POST["action"] ?? null) ? $_POST["action"] : "";

            if ($action === "reservation_status") {
                $reservationId = filter_var($_POST["reservation_id"] ?? null, FILTER_VALIDATE_INT);
                $newStatus = is_string($_POST["status"] ?? null) ? $_POST["status"] : "";
                if ($reservationId === false || $reservationId === null) {
                    throw new DomainException("Choose a valid reservation and status.");
                }
                $reservationModel->updateStatus($reservationId, $newStatus);
                $message = "Reservation status updated successfully.";
            } elseif ($action === "user_activation") {
                $userId = filter_var($_POST["user_id"] ?? null, FILTER_VALIDATE_INT);
                $isActive = filter_var($_POST["is_active"] ?? null, FILTER_VALIDATE_INT);
                if ($userId === false || $userId === null || $isActive === false
                    || $isActive === null || !in_array($isActive, [0, 1], true)) {
                    throw new DomainException("Choose a valid user and activation status.");
                }
                $userModel->setActive($userId, $isActive === 1, (int) $_SESSION["user_id"]);
                $message = $isActive === 1 ? "User account activated." : "User account deactivated.";
            } else {
                throw new DomainException("Unknown administration action.");
            }
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log("Admin dashboard action database error: " . $exception->getMessage());
            $error = "The requested change could not be saved. Please try again later.";
        }
    }
}

try {
    $reservationStats = $reservationModel->statistics();
    $reservations = $reservationModel->all();
    $users = $userModel->all();

    $stats = [
        "total" => (int) $reservationStats["total"],
        "pending" => (int) $reservationStats["pending"],
        "confirmed" => (int) $reservationStats["confirmed"],
        "rejected" => (int) $reservationStats["rejected"],
        "cancelled" => (int) $reservationStats["cancelled"],
        "users" => count($users),
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
    <link rel="stylesheet" href="assets.css?v=5">
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
    <?php endif; ?>
    <?php if ($message !== ""): ?>
        <p class="notice success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>
    <?php if ($stats !== null): ?>
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

        <section class="managed-slots admin-data-section">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">Reservation records</p>
                    <h2>All reservations</h2>
                </div>
                <span class="input-hint"><?= count($reservations) ?> total</span>
            </div>
            <?php if ($reservations === []): ?>
                <div class="empty-state"><p>No reservations have been created yet.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="reservation-table">
                        <thead>
                            <tr><th>ID</th><th>Student</th><th>Advisor</th><th>Appointment</th><th>Note</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?= (int) $reservation["reservation_id"] ?></td>
                                    <td><?= htmlspecialchars($reservation["student_name"] . " (" . $reservation["student_email"] . ")", ENT_QUOTES, "UTF-8") ?></td>
                                    <td><?= htmlspecialchars($reservation["advisor_name"], ENT_QUOTES, "UTF-8") ?></td>
                                    <td><?= htmlspecialchars($reservation["slot_date"] . " · " . date("g:i A", strtotime($reservation["start_time"])) . "–" . date("g:i A", strtotime($reservation["end_time"])), ENT_QUOTES, "UTF-8") ?></td>
                                    <td><?= htmlspecialchars((string) ($reservation["note"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
                                    <td><span class="status status-<?= htmlspecialchars($reservation["status"], ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars(ucfirst($reservation["status"]), ENT_QUOTES, "UTF-8") ?></span></td>
                                    <td>
                                        <?php if ($reservation["status"] === "pending"): ?>
                                            <div class="admin-actions">
                                                <?php foreach (["confirmed" => "Confirm", "rejected" => "Reject"] as $status => $label): ?>
                                                    <form method="post" action="admin_dashboard.php">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                                                        <input type="hidden" name="action" value="reservation_status">
                                                        <input type="hidden" name="reservation_id" value="<?= (int) $reservation["reservation_id"] ?>">
                                                        <input type="hidden" name="status" value="<?= $status ?>">
                                                        <button type="submit" class="<?= $status === "rejected" ? "button-danger" : "" ?>"><?= $label ?></button>
                                                    </form>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="input-hint">No further status changes</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="managed-slots admin-data-section">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">Account directory</p>
                    <h2>Registered users</h2>
                </div>
                <span class="input-hint"><?= count($users) ?> total</span>
            </div>
            <?php if ($users === []): ?>
                <div class="empty-state"><p>No users have registered yet.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="reservation-table">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Account</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= (int) $user["user_id"] ?></td>
                                    <td><?= htmlspecialchars($user["full_name"], ENT_QUOTES, "UTF-8") ?></td>
                                    <td><?= htmlspecialchars($user["email"], ENT_QUOTES, "UTF-8") ?></td>
                                    <td><?= htmlspecialchars(ucfirst($user["role"]), ENT_QUOTES, "UTF-8") ?></td>
                                    <td><?= htmlspecialchars((string) ($user["department_name"] ?? "—"), ENT_QUOTES, "UTF-8") ?></td>
                                    <td><?= (int) $user["is_active"] === 1 ? "Active" : "Inactive" ?></td>
                                    <td>
                                        <?php if (in_array($user["role"], ["student", "advisor"], true)): ?>
                                            <form method="post" action="admin_dashboard.php">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                                                <input type="hidden" name="action" value="user_activation">
                                                <input type="hidden" name="user_id" value="<?= (int) $user["user_id"] ?>">
                                                <input type="hidden" name="is_active" value="<?= (int) $user["is_active"] === 1 ? "0" : "1" ?>">
                                                <button type="submit" class="<?= (int) $user["is_active"] === 1 ? "button-danger" : "" ?>" <?= (int) $user["user_id"] === (int) $_SESSION["user_id"] ? "disabled" : "" ?>>
                                                    <?= (int) $user["is_active"] === 1 ? "Deactivate" : "Activate" ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="input-hint">Admin accounts are protected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
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
                    //306 إلى 331: Code Chart.js.
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