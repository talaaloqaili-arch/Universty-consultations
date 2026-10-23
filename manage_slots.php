<?php
require_once __DIR__ . "/admin_auth.php";
require_once __DIR__ . "/time_slot.php";

$slotModel = new TimeSlot($pdo);
$message = "";
$error = "";
$slots = [];
$advisors = [];
$activeAdvisors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_is_valid()) {
        $error = "Your session token is invalid. Refresh the page and try again.";
    } else {
        try {
            $action = $_POST["action"] ?? "";
            $advisorId = filter_var($_POST["advisor_id"] ?? null, FILTER_VALIDATE_INT);
            $capacity = filter_var($_POST["capacity"] ?? null, FILTER_VALIDATE_INT);
            $slotId = filter_var($_POST["slot_id"] ?? null, FILTER_VALIDATE_INT);
            $date = is_string($_POST["slot_date"] ?? null) ? trim($_POST["slot_date"]) : "";
            $startTime = is_string($_POST["start_time"] ?? null) ? trim($_POST["start_time"]) : "";
            $endTime = is_string($_POST["end_time"] ?? null) ? trim($_POST["end_time"]) : "";

            if ($action === "add_slot") {
                if ($advisorId === false || $advisorId === null || $capacity === false || $capacity === null) {
                    throw new DomainException("Enter a valid advisor and capacity.");
                }
                $slotModel->create($advisorId, $date, $startTime, $endTime, $capacity);
                $message = "Time slot added successfully.";
            } elseif ($action === "update_slot") {
                if ($slotId === false || $slotId === null
                    || $advisorId === false || $advisorId === null
                    || $capacity === false || $capacity === null) {
                    throw new DomainException("Choose a valid time slot, advisor, and capacity.");
                }
                $slotModel->update($slotId, $advisorId, $date, $startTime, $endTime, $capacity);
                $message = "Time slot updated successfully.";
            } elseif ($action === "delete_slot") {
                if ($slotId === false || $slotId === null) {
                    throw new DomainException("Select a valid time slot to delete.");
                }
                $slotModel->delete($slotId);
                $message = "Time slot deleted successfully.";
            } else {
                throw new DomainException("Unknown time-slot action.");
            }
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log("Time-slot administration database error: " . $exception->getMessage());
            $error = "The time-slot change could not be saved. Please try again later.";
        }
    }
}

try {
    $advisors = $slotModel->advisors();
    $activeAdvisors = array_values(array_filter(
        $advisors,
        static fn (array $advisor): bool => (int) $advisor["is_active"] === 1
    ));
    $slots = $slotModel->all();
} catch (PDOException $exception) {
    error_log("Time-slot list database error: " . $exception->getMessage());
    $error = "Time slots could not be loaded. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102a43">
    <title>Manage appointment times | University Consultations</title>
    <link rel="stylesheet" href="assets.css?v=5">
</head>
<body>
<header class="site-header">
    <nav class="nav-wrap" aria-label="Main navigation">
        <a class="brand" href="admin_dashboard.php">
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span>University Consultations<small>Academic advising</small></span>
        </a>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a class="nav-optional" href="availability.php">Student availability</a>
            <a class="nav-cta" href="logout.php">Log out</a>
        </div>
    </nav>
</header>
<main class="page-wrap">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Academic advising</p>
            <h1>Manage appointment times</h1>
            <p>Create, update, and delete time slots while preserving reservation records.</p>
        </div>
        <a class="text-link" href="admin_dashboard.php">← Back to dashboard</a>
    </div>

    <?php if ($message !== ""): ?>
        <p class="notice success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>
    <?php if ($error !== ""): ?>
        <p class="notice error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>

    <section class="panel form-panel slot-management-form">
        <div>
            <p class="eyebrow">Availability</p>
            <h2>Add a new time slot</h2>
        </div>
        <?php if ($activeAdvisors === []): ?>
            <p class="notice error">There are no active advisor accounts. Activate an advisor before adding a time slot.</p>
        <?php else: ?>
            <form class="slot-form" action="manage_slots.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                <input type="hidden" name="action" value="add_slot">
                <div class="field">
                    <label for="new_advisor_id">Advisor</label>
                    <select id="new_advisor_id" name="advisor_id" required>
                        <?php foreach ($activeAdvisors as $advisor): ?>
                            <option value="<?= (int) $advisor["user_id"] ?>"><?= htmlspecialchars($advisor["full_name"], ENT_QUOTES, "UTF-8") ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="slot_date">Appointment date</label>
                    <input type="date" id="slot_date" name="slot_date" min="<?= date("Y-m-d") ?>" required>
                </div>
                <div class="form-row">
                    <div class="field"><label for="start_time">Start time</label><input type="time" id="start_time" name="start_time" required></div>
                    <div class="field"><label for="end_time">End time</label><input type="time" id="end_time" name="end_time" required></div>
                </div>
                <div class="field">
                    <label for="capacity">Student capacity</label>
                    <input type="number" id="capacity" name="capacity" min="1" value="1" required>
                    <span class="input-hint">Pending and confirmed reservations count toward capacity.</span>
                </div>
                <button type="submit">Add available time <span aria-hidden="true">→</span></button>
            </form>
        <?php endif; ?>
    </section>

    <section class="managed-slots">
        <div class="page-heading">
            <div><p class="eyebrow">Schedule</p><h2>Time slots</h2></div>
            <span class="input-hint"><?= count($slots) ?> total</span>
        </div>
        <?php if ($slots === []): ?>
            <div class="empty-state"><h3>No time slots added yet</h3><p>Add an appointment time above and it will appear here for students to book.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="reservation-table">
                    <thead><tr><th>Advisor</th><th>Date</th><th>Start</th><th>End</th><th>Capacity</th><th>Reservations</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($slots as $slot): ?>
                        <?php $slotId = (int) $slot["slot_id"]; ?>
                        <tr>
                            <td>
                                <select name="advisor_id" form="slot-edit-<?= $slotId ?>" required>
                                    <?php foreach ($advisors as $advisor): ?>
                                        <option value="<?= (int) $advisor["user_id"] ?>" <?= (int) $advisor["user_id"] === (int) $slot["advisor_id"] ? "selected" : "" ?>>
                                            <?= htmlspecialchars($advisor["full_name"] . ((int) $advisor["is_active"] === 1 ? "" : " (inactive)"), ENT_QUOTES, "UTF-8") ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="date" name="slot_date" value="<?= htmlspecialchars($slot["slot_date"], ENT_QUOTES, "UTF-8") ?>" min="<?= date("Y-m-d") ?>" form="slot-edit-<?= $slotId ?>" required></td>
                            <td><input type="time" name="start_time" value="<?= htmlspecialchars(substr($slot["start_time"], 0, 5), ENT_QUOTES, "UTF-8") ?>" form="slot-edit-<?= $slotId ?>" required></td>
                            <td><input type="time" name="end_time" value="<?= htmlspecialchars(substr($slot["end_time"], 0, 5), ENT_QUOTES, "UTF-8") ?>" form="slot-edit-<?= $slotId ?>" required></td>
                            <td><input type="number" name="capacity" min="<?= max(1, (int) $slot["active_reservations"]) ?>" value="<?= (int) $slot["capacity"] ?>" form="slot-edit-<?= $slotId ?>" required></td>
                            <td><?= (int) $slot["active_reservations"] ?> active / <?= (int) $slot["reservation_count"] ?> total</td>
                            <td>
                                <div class="admin-actions slot-actions">
                                    <form id="slot-edit-<?= $slotId ?>" method="post" action="manage_slots.php">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                                        <input type="hidden" name="action" value="update_slot">
                                        <input type="hidden" name="slot_id" value="<?= $slotId ?>">
                                        <button type="submit">Save</button>
                                    </form>
                                    <form method="post" action="manage_slots.php" onsubmit="return confirm('Delete this slot? Slots with any reservation history cannot be deleted.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>">
                                        <input type="hidden" name="action" value="delete_slot">
                                        <input type="hidden" name="slot_id" value="<?= $slotId ?>">
                                        <button type="submit" class="button-danger" <?= (int) $slot["reservation_count"] > 0 ? "disabled" : "" ?>>Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<footer class="site-footer">University Consultations · Academic guidance for your next step</footer>
</body>
</html>
