<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/reservation.php";

$reservationModel = new Reservation($pdo);
$message = "";
$messageType = "error";
$dateInput = $_GET["date"] ?? "";
$selectedDate = is_string($dateInput) ? trim($dateInput) : "";
$userId = isset($_SESSION["user_id"]) ? require_login() : null;
$canBook = $userId !== null && ($_SESSION["role"] ?? "") === "student";//: يتحقق من أن المستخدم مسجّل الدخول وأن دوره طالب (student) قبل السماح بالحجز.

if ($selectedDate !== "") {
    $date = DateTime::createFromFormat("!Y-m-d", $selectedDate);
    if ($date === false || $date->format("Y-m-d") !== $selectedDate) {
        $message = "Please select a valid date.";
        $selectedDate = "";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($userId === null) {
        header("Location: login.php");
        exit;
    }

    $slotId = filter_var($_POST["slot_id"] ?? null, FILTER_VALIDATE_INT);
    $noteInput = $_POST["note"] ?? "";
    $note = is_string($noteInput) ? trim($noteInput) : "";

    if (!csrf_is_valid()) {
        $message = "Your session token is invalid. Refresh the page and try again.";
    } elseif (!is_string($noteInput)) {
        $message = "Please enter a valid note.";
    } elseif ($slotId === false || $slotId < 1) {
        $message = "Please select a valid time slot.";
    } elseif (mb_strlen($note) > 2000) {
        $message = "The note cannot exceed 2000 characters.";
    } else {
        try {
            $reservationModel->create($userId, $slotId, $note);
            header("Location: my_reservations.php?message=created");
            exit;
        } catch (DomainException $exception) {
            $message = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log("Reservation creation database error: " . $exception->getMessage());
            $message = "Unable to create the reservation. Please try again later.";
        }
    }
}

try {
    $slots = $reservationModel->availableSlots($selectedDate !== "" ? $selectedDate : null);
} catch (PDOException $exception) {
    error_log("Availability database error: " . $exception->getMessage());
    $slots = [];
    $message = "Unable to load available time slots. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>Find an Advisor | University Consultations</title>
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
            <a class="nav-optional" href="my_reservations.php">My reservations</a>
            <?php if ($userId !== null): ?>
                <a class="nav-cta" href="logout.php">Log out</a>
            <?php else: ?>
                <a class="nav-cta" href="login.php">Log in</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
<main class="page-wrap">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Your next step starts here</p>
            <h1>Meet with an advisor</h1>
            <p>Choose a time that works for you and get guidance on your academic journey.</p>
        </div>
        <a class="text-link" href="my_reservations.php">View my reservations <span aria-hidden="true">→</span></a>
    </div>

    <form class="filter-panel panel" method="get" action="availability.php">
        <div class="field">
            <label for="date">Find a date</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($selectedDate, ENT_QUOTES, "UTF-8") ?>">
        </div>
        <button type="submit">Search availability</button>
        <a class="text-link" href="availability.php">Clear date</a>
    </form>

    <?php if ($message !== ""): ?>
        <p class="notice error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>

    <?php if ($slots === [] && $message === ""): ?>
        <section class="empty-state">
            <span class="empty-icon" aria-hidden="true">◷</span>
            <h2>No available appointments</h2>
            <p>There are no future time slots with remaining capacity<?= $selectedDate !== "" ? " on this date" : "" ?>. Try another date soon.</p>
        </section>
    <?php else: ?>
        <div class="slot-grid">
            <?php foreach ($slots as $slot): ?>
                <article class="slot-card">
                    <p class="slot-date"><?= htmlspecialchars(date("D, M j, Y", strtotime($slot["slot_date"])), ENT_QUOTES, "UTF-8") ?></p>
                    <h2 class="slot-time">
                        <?= htmlspecialchars(date("g:i A", strtotime($slot["start_time"])), ENT_QUOTES, "UTF-8") ?>
                        <span aria-hidden="true">–</span>
                        <?= htmlspecialchars(date("g:i A", strtotime($slot["end_time"])), ENT_QUOTES, "UTF-8") ?>
                    </h2>
                    <div class="slot-professor">
                        <span class="avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($slot["advisor_name"], 0, 1)), ENT_QUOTES, "UTF-8") ?></span>
                        <span>Academic advisor<br><strong><?= htmlspecialchars($slot["advisor_name"], ENT_QUOTES, "UTF-8") ?></strong></span>
                    </div>
                    <div class="capacity">
                        <span>Places available</span>
                        <span><?= (int) $slot["remaining_capacity"] ?> of <?= (int) $slot["capacity"] ?></span>
                    </div>
                    <?php if ($canBook): ?>
                        <form class="slot-form" method="post" action="availability.php<?= $selectedDate !== "" ? "?date=" . rawurlencode($selectedDate) : "" ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                            <input type="hidden" name="slot_id" value="<?= (int) $slot["slot_id"] ?>">
                            <div class="field">
                                <label for="note-<?= (int) $slot["slot_id"] ?>">Note <span class="input-hint">(optional)</span></label>
                                <textarea id="note-<?= (int) $slot["slot_id"] ?>" name="note" maxlength="2000" placeholder="What would you like to discuss?"></textarea>
                            </div>
                            <button type="submit">Book this appointment <span aria-hidden="true">→</span></button>
                        </form>
                    <?php elseif ($userId !== null): ?>
                        <p class="notice">Only active student accounts can book an appointment.</p>
                    <?php else: ?>
                        <a class="button" href="login.php">Log in to book <span aria-hidden="true">→</span></a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<footer class="site-footer">University Consultations · Academic guidance for your next step</footer>
</body>
</html>
