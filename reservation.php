<?php

final class Reservation
{
    public function __construct(private PDO $db)
    {
    }

    public function availableSlots(?string $date = null): array
    {
        $sql = "
            SELECT s.slot_id, s.slot_date, s.start_time, s.end_time, s.capacity,
                   u.full_name AS advisor_name,
                   s.capacity - (
                       SELECT COUNT(*)
                       FROM reservations r
                       WHERE r.slot_id = s.slot_id
                         AND r.status IN ('pending', 'confirmed')
                   ) AS remaining_capacity
            FROM time_slots s
            JOIN users u ON u.user_id = s.advisor_id
            WHERE s.status = 'available'
              AND TIMESTAMP(s.slot_date, s.start_time) > NOW()
              AND s.capacity > (
                  SELECT COUNT(*)
                  FROM reservations r
                  WHERE r.slot_id = s.slot_id
                    AND r.status IN ('pending', 'confirmed')
              )";

        $parameters = [];
        if ($date !== null) {
            $sql .= " AND s.slot_date = :slot_date";
            $parameters["slot_date"] = $date;
        }

        $sql .= " ORDER BY s.slot_date, s.start_time";

        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function create(int $userId, int $slotId, string $note): void
    {
        $this->db->beginTransaction();

        try {
            $slotStatement = $this->db->prepare(
                "SELECT slot_id, slot_date, start_time, capacity, status
                 FROM time_slots
                 WHERE slot_id = :slot_id
                 FOR UPDATE"
            );
            $slotStatement->execute(["slot_id" => $slotId]);
            $slot = $slotStatement->fetch();

            if ($slot === false || $slot["status"] !== "available") {
                throw new DomainException("This time slot is no longer available.");
            }

            $startsInFuture = $this->db->prepare(
                "SELECT TIMESTAMP(:slot_date, :start_time) > NOW()"
            );
            $startsInFuture->execute([
                "slot_date" => $slot["slot_date"],
                "start_time" => $slot["start_time"],
            ]);
            if ((int) $startsInFuture->fetchColumn() !== 1) {
                throw new DomainException("This time slot has already started.");
            }

            $existingStatement = $this->db->prepare(
                "SELECT reservation_id
                 FROM reservations
                 WHERE student_id = :student_id AND slot_id = :slot_id
                 LIMIT 1"
            );
            $existingStatement->execute([
                "student_id" => $userId,
                "slot_id" => $slotId,
            ]);
            if ($existingStatement->fetchColumn() !== false) {
                throw new DomainException(
                    "You already have a reservation record for this time slot."
                );
            }

            $capacityStatement = $this->db->prepare(
                "SELECT COUNT(*)
                 FROM reservations
                 WHERE slot_id = :slot_id
                   AND status IN ('pending', 'confirmed')"
            );
            $capacityStatement->execute(["slot_id" => $slotId]);
            if ((int) $capacityStatement->fetchColumn() >= (int) $slot["capacity"]) {
                throw new DomainException("This time slot has reached its capacity.");
            }

            $insertStatement = $this->db->prepare(
                "INSERT INTO reservations (student_id, slot_id, status, note)
                 VALUES (:student_id, :slot_id, 'pending', :note)"
            );
            $insertStatement->execute([
                "student_id" => $userId,
                "slot_id" => $slotId,
                "note" => $note === "" ? null : $note,
            ]);

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function forUser(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT r.reservation_id, r.slot_id, r.status, r.note, r.created_at,
                    s.slot_date, s.start_time, s.end_time,
                    u.full_name AS advisor_name,
                    TIMESTAMP(s.slot_date, s.start_time) > NOW() AS can_cancel
             FROM reservations r
             JOIN time_slots s ON s.slot_id = r.slot_id
             JOIN users u ON u.user_id = s.advisor_id
             WHERE r.student_id = :student_id
             ORDER BY s.slot_date DESC, s.start_time DESC"
        );
        $statement->execute(["student_id" => $userId]);

        return $statement->fetchAll();
    }

    public function cancel(int $userId, int $reservationId): void
    {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare(
                "SELECT r.status,
                        TIMESTAMP(s.slot_date, s.start_time) > NOW() AS can_cancel
                 FROM reservations r
                 JOIN time_slots s ON s.slot_id = r.slot_id
                 WHERE r.reservation_id = :reservation_id
                   AND r.student_id = :student_id
                 FOR UPDATE"
            );
            $statement->execute([
                "reservation_id" => $reservationId,
                "student_id" => $userId,
            ]);
            $reservation = $statement->fetch();

            if ($reservation === false) {
                throw new DomainException("Reservation not found.");
            }
            if (!in_array($reservation["status"], ["pending", "confirmed"], true)) {
                throw new DomainException("Only pending or confirmed reservations can be cancelled.");
            }
            if ((int) $reservation["can_cancel"] !== 1) {
                throw new DomainException("Reservations cannot be cancelled after the time slot begins.");
            }

            $updateStatement = $this->db->prepare(
                "UPDATE reservations
                 SET status = 'cancelled'
                 WHERE reservation_id = :reservation_id AND student_id = :student_id"
            );
            $updateStatement->execute([
                "reservation_id" => $reservationId,
                "student_id" => $userId,
            ]);

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }
}
