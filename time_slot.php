<?php

final class TimeSlot
{
    public function __construct(private PDO $db)
    {
    }

    public function advisors(): array
    {
        $statement = $this->db->query(
            "SELECT user_id, full_name, is_active
             FROM users
             WHERE role = 'advisor'
             ORDER BY full_name"
        );

        return $statement->fetchAll();
    }

    public function all(): array
    {
        $statement = $this->db->query(
            "SELECT ts.*, u.full_name AS advisor_name,
                    (SELECT COUNT(*) FROM reservations r WHERE r.slot_id = ts.slot_id) AS reservation_count,
                    (SELECT COUNT(*) FROM reservations r
                     WHERE r.slot_id = ts.slot_id AND r.status IN ('pending', 'confirmed')) AS active_reservations
             FROM time_slots ts
             JOIN users u ON u.user_id = ts.advisor_id
             ORDER BY ts.slot_date DESC, ts.start_time DESC"
        );

        return $statement->fetchAll();
    }

    public function create(
        int $advisorId,
        string $date,
        string $startTime,
        string $endTime,
        int $capacity
    ): void {
        $this->validateDetails($advisorId, $date, $startTime, $endTime, $capacity);
        $this->ensureFutureStart($date, $startTime);
        $this->ensureAdvisor($advisorId, true);
        $this->ensureNoOverlap($advisorId, $date, $startTime, $endTime);

        $statement = $this->db->prepare(
            "INSERT INTO time_slots (advisor_id, slot_date, start_time, end_time, capacity, status)
             VALUES (?, ?, ?, ?, ?, 'available')"
        );
        $statement->execute([$advisorId, $date, $startTime, $endTime, $capacity]);
    }

    public function update(
        int $slotId,
        int $advisorId,
        string $date,
        string $startTime,
        string $endTime,
        int $capacity
    ): void {
        if ($slotId < 1) {
            throw new DomainException("Choose a valid time slot.");
        }
        $this->validateDetails($advisorId, $date, $startTime, $endTime, $capacity);
        $this->ensureFutureStart($date, $startTime);

        $this->db->beginTransaction();

        try {
            $slotStatement = $this->db->prepare(
                "SELECT * FROM time_slots WHERE slot_id = ? FOR UPDATE"
            );
            $slotStatement->execute([$slotId]);
            $currentSlot = $slotStatement->fetch();
            if ($currentSlot === false) {
                throw new DomainException("Time slot not found.");
            }

            $countsStatement = $this->db->prepare(
                "SELECT COUNT(*) AS total,
                        COALESCE(SUM(status IN ('pending', 'confirmed')), 0) AS active
                 FROM reservations WHERE slot_id = ?"
            );
            $countsStatement->execute([$slotId]);
            $counts = $countsStatement->fetch();

            $scheduleChanged = $date !== $currentSlot["slot_date"]
                || substr($startTime, 0, 5) !== substr($currentSlot["start_time"], 0, 5)
                || substr($endTime, 0, 5) !== substr($currentSlot["end_time"], 0, 5)
                || $advisorId !== (int) $currentSlot["advisor_id"];

            if ((int) $counts["total"] > 0 && $scheduleChanged) {
                throw new DomainException(
                    "Date, time, and advisor cannot change after reservations have been recorded."
                );
            }
            if ($capacity < (int) $counts["active"]) {
                throw new DomainException(
                    "Capacity cannot be lower than the number of pending and confirmed reservations."
                );
            }

            $this->ensureAdvisor(
                $advisorId,
                $advisorId !== (int) $currentSlot["advisor_id"]
            );
            $this->ensureNoOverlap($advisorId, $date, $startTime, $endTime, $slotId);

            $update = $this->db->prepare(
                "UPDATE time_slots
                 SET advisor_id = ?, slot_date = ?, start_time = ?, end_time = ?, capacity = ?
                 WHERE slot_id = ?"
            );
            $update->execute([$advisorId, $date, $startTime, $endTime, $capacity, $slotId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function delete(int $slotId): void
    {
        if ($slotId < 1) {
            throw new DomainException("Select a valid time slot to delete.");
        }

        $this->db->beginTransaction();

        try {
            $slotStatement = $this->db->prepare(
                "SELECT slot_id FROM time_slots WHERE slot_id = ? FOR UPDATE"
            );
            $slotStatement->execute([$slotId]);
            if ($slotStatement->fetchColumn() === false) {
                throw new DomainException("Time slot not found.");
            }

            $reservationStatement = $this->db->prepare(
                "SELECT COUNT(*) FROM reservations WHERE slot_id = ?"
            );
            $reservationStatement->execute([$slotId]);
            if ((int) $reservationStatement->fetchColumn() > 0) {
                throw new DomainException(
                    "This time slot cannot be deleted because reservation history is attached to it."
                );
            }

            $deleteStatement = $this->db->prepare("DELETE FROM time_slots WHERE slot_id = ?");
            $deleteStatement->execute([$slotId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function validateDetails(
        int $advisorId,
        string $date,
        string $startTime,
        string $endTime,
        int $capacity
    ): void {
        $dateValue = DateTime::createFromFormat("!Y-m-d", $date);
        $startValue = DateTime::createFromFormat("!H:i", $startTime);
        $endValue = DateTime::createFromFormat("!H:i", $endTime);

        if ($advisorId < 1 || $dateValue === false || $dateValue->format("Y-m-d") !== $date
            || $startValue === false || $startValue->format("H:i") !== $startTime
            || $endValue === false || $endValue->format("H:i") !== $endTime
            || $startTime >= $endTime || $capacity < 1) {
            throw new DomainException(
                "Enter valid time-slot details and a capacity of at least one."
            );
        }
    }

    private function ensureFutureStart(string $date, string $startTime): void
    {
        if (strtotime($date . " " . $startTime) <= time()) {
            throw new DomainException("A time slot must start in the future.");
        }
    }

    private function ensureAdvisor(int $advisorId, bool $mustBeActive): void
    {
        $statement = $this->db->prepare(
            "SELECT is_active FROM users WHERE user_id = ? AND role = 'advisor'"
        );
        $statement->execute([$advisorId]);
        $isActive = $statement->fetchColumn();

        if ($isActive === false || ($mustBeActive && (int) $isActive !== 1)) {
            throw new DomainException(
                $mustBeActive ? "Select an active advisor." : "Select a valid advisor."
            );
        }
    }

    private function ensureNoOverlap(
        int $advisorId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeSlotId = null
    ): void {
        $sql = "SELECT COUNT(*) FROM time_slots
                WHERE advisor_id = ? AND slot_date = ?
                  AND start_time < ? AND end_time > ?";
        $parameters = [$advisorId, $date, $endTime, $startTime];
        if ($excludeSlotId !== null) {
            $sql .= " AND slot_id <> ?";
            $parameters[] = $excludeSlotId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        if ((int) $statement->fetchColumn() > 0) {
            throw new DomainException(
                "This advisor already has an overlapping time slot on that date."
            );
        }
    }
}
