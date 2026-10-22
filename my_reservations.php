<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/reservation.php";

$userId = require_login();
$reservationModel = new Reservation($pdo);
$message = "";
$messageType = "success";

if (isset($_GET["message"]) && $_GET["message"] === "created") {
    $message = "Your reservation was submitted with Pending status.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $reservationId = filter_var($_POST["reservation_id"] ?? null, FILTER_VALIDATE_INT);

    if (!csrf_is_valid()) {
        $message = "Your session token is invalid. Refresh the page and try again.";
        $messageType = "error";
    } elseif ($reservationId === false || $reservationId < 1) {
        $message = "Please select a valid reservation.";
        $messageType = "error";
    } else {
        try {
            $reservationModel->cancel($userId, $reservationId);
            header("Location: my_reservations.php?message=cancelled");
            exit;
        } catch (DomainException $exception) {
            $message = $exception->getMessage();
            $messageType = "error";
        } catch (PDOException $exception) {
            error_log("Reservation cancellation database error: " . $exception->getMessage());
            $message = "Unable to cancel this reservation. Please try again later.";
            $messageType = "error";
        }
    }
}

if (isset($_GET["message"]) && $_GET["message"] === "cancelled") {
    $message = "Your reservation was cancelled.";
}

try {
    $reservations = $reservationModel->forUser($userId);
} catch (PDOException $exception) {
    error_log("Reservation listing database error: " . $exception->getMessage());
    $reservations = [];
    $message = "Unable to load your reservations. Please try again later.";
    $messageType = "error";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>My Reservations | University Consultations</title>
    <link rel="stylesheet" href="assets.css">
</head>
<body>
<header class="site-header">
    <nav class="nav-wrap" aria-label="Main navigation">
        <a class="brand" href="dashboard.php">
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span>University Consultations<small>Academic advising</small></span>
        </a>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a class="nav-optional" href="availability.php">Find an advisor</a>
            <a class="nav-cta" href="logout.php">Log out</a>
        </div>
    </nav>
</header>
<main class="page-wrap">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Stay on track</p>
            <h1>My reservations</h1>
            <p>Review your upcoming consultations and keep track of each appointment.</p>
        </div>
        <a class="button" href="availability.php">Book an appointment <span aria-hidden="true">→</span></a>
    </div>

    <?php if ($message !== ""): ?>
        <p class="notice <?= $messageType ?>" role="<?= $messageType === "error" ? "alert" : "status" ?>"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>

    <?php if ($reservations === []): ?>
        <section class="empty-state">
            <span class="empty-icon" aria-hidden="true">▦</span>
            <h2>No reservations yet</h2>
            <p>Your booked consultations will appear here. Find an advisor and choose a time to get started.</p>
            <a class="button" href="availability.php">Explore available times</a>
        </section>
    <?php else: ?>
        <div class="table-wrap">
            <table class="reservation-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Academic advisor</th>
                        <th>Status</th>
                        <th>Note</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td><?= htmlspecialchars(date("F j, Y", strtotime($reservation["slot_date"])), ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars(date("g:i A", strtotime($reservation["start_time"])), ENT_QUOTES, "UTF-8") ?>
                                – <?= htmlspecialchars(date("g:i A", strtotime($reservation["end_time"])), ENT_QUOTES, "UTF-8") ?></td>
                            <td><?= htmlspecialchars($reservation["advisor_name"], ENT_QUOTES, "UTF-8") ?></td>
                            <td><span class="status status-<?= htmlspecialchars($reservation["status"], ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars(ucfirst($reservation["status"]), ENT_QUOTES, "UTF-8") ?></span></td>
                            <td><?= htmlspecialchars($reservation["note"] ?? "", ENT_QUOTES, "UTF-8") ?: "—" ?></td>
                            <td>
                                <?php if (
                                    (int) $reservation["can_cancel"] === 1
                                    && in_array($reservation["status"], ["pending", "confirmed"], true)
                                ): ?>
                                    <form method="post" action="my_reservations.php">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                                        <input type="hidden" name="reservation_id" value="<?= (int) $reservation["reservation_id"] ?>">
                                        <button class="button-danger" type="submit">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    <span aria-label="No action available">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
<footer class="site-footer">University Consultations · Academic guidance for your next step</footer>
</body>
</html>
