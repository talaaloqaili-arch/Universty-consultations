<?php
require_once __DIR__ . "/bootstrap.php";

$userId = require_login();
$role = $_SESSION["role"] ?? "";

if (!in_array($role, ["admin", "advisor"], true)) {
    http_response_code(403);
    $forbidden = true;
} else {
    $forbidden = false;
}

$message = "";
$messageType = "error";
$advisors = [];

if (!$forbidden && $role === "admin") {
    try {
        $advisors = $pdo->query(
            "SELECT user_id, full_name
             FROM users
             WHERE role = 'advisor' AND is_active = 1
             ORDER BY full_name"
        )->fetchAll();
    } catch (PDOException $exception) {
        error_log("Advisor list database error: " . $exception->getMessage());
        $message = "Unable to load the advisor list. Please try again later.";
    }
}

if (!$forbidden && $_SERVER["REQUEST_METHOD"] === "POST") {
    $date = $_POST["slot_date"] ?? "";
    $startTime = $_POST["start_time"] ?? "";
    $endTime = $_POST["end_time"] ?? "";
    $capacityInput = $_POST["capacity"] ?? "";
    $advisorInput = $_POST["advisor_id"] ?? "";

    if (!csrf_is_valid()) {
        $message = "Your session token is invalid. Refresh the page and try again.";
    } elseif (
        !is_string($date)
        || !is_string($startTime)
        || !is_string($endTime)
        || !is_string($capacityInput)
        || !is_string($advisorInput)
    ) {
        $message = "Please enter valid appointment details.";
    } else {
        $dateObject = DateTime::createFromFormat("!Y-m-d", $date);
        $validDate = $dateObject !== false && $dateObject->format("Y-m-d") === $date;
        $validStart = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime) === 1;
        $validEnd = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $endTime) === 1;
        $capacity = filter_var($capacityInput, FILTER_VALIDATE_INT);

        if (!$validDate || !$validStart || !$validEnd || $endTime <= $startTime) {
            $message = "Choose a valid date and an end time later than the start time.";
        } elseif ($capacity === false || $capacity < 1 || $capacity > 100) {
            $message = "Appointment capacity must be between 1 and 100.";
        } else {
            $advisorId = $userId;
            if ($role === "admin") {
                $validatedAdvisorId = filter_var($advisorInput, FILTER_VALIDATE_INT);
                if ($validatedAdvisorId === false || $validatedAdvisorId < 1) {
                    $message = "Please select an academic advisor.";
                    $advisorId = 0;
                } else {
                    $advisorId = $validatedAdvisorId;
                }
            }

            if ($message === "") {
                try {
                    $advisorStatement = $pdo->prepare(
                        "SELECT user_id
                         FROM users
                         WHERE user_id = :user_id
                           AND role = 'advisor'
                           AND is_active = 1"
                    );
                    $advisorStatement->execute(["user_id" => $advisorId]);

                    if ($advisorStatement->fetchColumn() === false) {
                        $message = "The selected advisor account is unavailable.";
                    } else {
                        $futureStatement = $pdo->prepare(
                            "SELECT TIMESTAMP(:slot_date, :start_time) > NOW()"
                        );
                        $futureStatement->execute([
                            "slot_date" => $date,
                            "start_time" => $startTime,
                        ]);

                        if ((int) $futureStatement->fetchColumn() !== 1) {
                            $message = "The appointment must start in the future.";
                        } else {
                            $duplicateStatement = $pdo->prepare(
                                "SELECT slot_id
                                 FROM time_slots
                                 WHERE advisor_id = :advisor_id
                                   AND slot_date = :slot_date
                                   AND start_time = :start_time
                                   AND end_time = :end_time
                                 LIMIT 1"
                            );
                            $duplicateStatement->execute([
                                "advisor_id" => $advisorId,
                                "slot_date" => $date,
                                "start_time" => $startTime,
                                "end_time" => $endTime,
                            ]);

                            if ($duplicateStatement->fetchColumn() !== false) {
                                $message = "This appointment time has already been added.";
                            } else {
                                $insertStatement = $pdo->prepare(
                                    "INSERT INTO time_slots
                                        (advisor_id, slot_date, start_time, end_time, capacity, status)
                                     VALUES
                                        (:advisor_id, :slot_date, :start_time, :end_time, :capacity, 'available')"
                                );
                                $insertStatement->execute([
                                    "advisor_id" => $advisorId,
                                    "slot_date" => $date,
                                    "start_time" => $startTime,
                                    "end_time" => $endTime,
                                    "capacity" => $capacity,
                                ]);

                                header("Location: manage_slots.php?message=created");
                                exit;
                            }
                        }
                    }
                } catch (PDOException $exception) {
                    error_log("Appointment slot creation database error: " . $exception->getMessage());
                    $message = "Unable to add the appointment. Please try again later.";
                }
            }
        }
    }
}

if (!$forbidden && isset($_GET["message"]) && $_GET["message"] === "created") {
    $message = "The appointment time was added and is now available to students.";
    $messageType = "success";
}

$slots = [];
if (!$forbidden) {
    try {
        $slotSql = "
            SELECT s.slot_date, s.start_time, s.end_time, s.capacity, s.status,
                   u.full_name AS advisor_name
            FROM time_slots s
            JOIN users u ON u.user_id = s.advisor_id";
        $parameters = [];

        if ($role === "advisor") {
            $slotSql .= " WHERE s.advisor_id = :advisor_id";
            $parameters["advisor_id"] = $userId;
        }

        $slotSql .= " ORDER BY s.slot_date DESC, s.start_time DESC LIMIT 20";
        $slotStatement = $pdo->prepare($slotSql);
        $slotStatement->execute($parameters);
        $slots = $slotStatement->fetchAll();
    } catch (PDOException $exception) {
        error_log("Appointment list database error: " . $exception->getMessage());
        if ($message === "") {
            $message = "Unable to load appointment times. Please try again later.";
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>Manage appointment times | University Consultations</title>
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
            <a class="nav-optional" href="availability.php">Student availability</a>
            <a class="nav-cta" href="logout.php">Log out</a>
        </div>
    </nav>
</header>
<main class="page-wrap">
    <?php if ($forbidden): ?>
        <section class="empty-state">
            <span class="empty-icon" aria-hidden="true">!</span>
            <h1>Advisor access required</h1>
            <p>Only academic advisors and administrators can add appointment times.</p>
            <a class="button" href="dashboard.php">Back to dashboard</a>
        </section>
    <?php else: ?>
        <div class="page-heading">
            <div>
                <p class="eyebrow">Academic advising</p>
                <h1>Manage appointment times</h1>
                <p>Add future consultation times so students can find and book them.</p>
            </div>
        </div>

        <?php if ($message !== ""): ?>
            <p class="notice <?= $messageType ?>" role="<?= $messageType === "error" ? "alert" : "status" ?>"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></p>
        <?php endif; ?>

        <section class="panel form-panel">
            <h2>Add an appointment time</h2>
            <form class="slot-form" method="post" action="manage_slots.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                <?php if ($role === "admin"): ?>
                    <div class="field">
                        <label for="advisor_id">Academic advisor</label>
                        <select id="advisor_id" name="advisor_id" required>
                            <option value="">Select an advisor</option>
                            <?php foreach ($advisors as $advisor): ?>
                                <option value="<?= (int) $advisor["user_id"] ?>"><?= htmlspecialchars($advisor["full_name"], ENT_QUOTES, "UTF-8") ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($advisors === []): ?>
                            <span class="input-hint">Create or activate an account with the advisor role first.</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="field">
                    <label for="slot_date">Date</label>
                    <input type="date" id="slot_date" name="slot_date" min="<?= date("Y-m-d") ?>" required>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="start_time">Start time</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>
                    <div class="field">
                        <label for="end_time">End time</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                </div>
                <div class="field">
                    <label for="capacity">Student capacity</label>
                    <input type="number" id="capacity" name="capacity" min="1" max="100" value="1" required>
                    <span class="input-hint">Maximum 100 students for this time.</span>
                </div>
                <button type="submit" <?= $role === "admin" && $advisors === [] ? "disabled" : "" ?>>Add available time</button>
            </form>
        </section>

        <section class="managed-slots">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">Recently added</p>
                    <h2>Appointment times</h2>
                </div>
            </div>
            <?php if ($slots === []): ?>
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true">◷</span>
                    <h3>No appointment times yet</h3>
                    <p>Add a future time above; students can then book it from the availability page.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="reservation-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <?php if ($role === "admin"): ?><th>Advisor</th><?php endif; ?>
                                <th>Capacity</th>
                                <th>Availability</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slots as $slot): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date("F j, Y", strtotime($slot["slot_date"])), ENT_QUOTES, "UTF-8") ?></td>
                                    <td><?= htmlspecialchars(date("g:i A", strtotime($slot["start_time"])), ENT_QUOTES, "UTF-8") ?>
                                        – <?= htmlspecialchars(date("g:i A", strtotime($slot["end_time"])), ENT_QUOTES, "UTF-8") ?></td>
                                    <?php if ($role === "admin"): ?><td><?= htmlspecialchars($slot["advisor_name"], ENT_QUOTES, "UTF-8") ?></td><?php endif; ?>
                                    <td><?= (int) $slot["capacity"] ?></td>
                                    <td><span class="status"><?= htmlspecialchars(ucfirst($slot["status"]), ENT_QUOTES, "UTF-8") ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<footer class="site-footer">University Consultations · Academic guidance for your next step</footer>
</body>
</html>
