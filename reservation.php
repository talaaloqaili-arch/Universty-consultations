<?php

final class Reservation
{
    public function __construct(private PDO $db)
    {
    }
//جلب قائمة المواعيد المتاحة (القابلة للحجز) من قاعدة البيانات، مع إمكانية التصفية حسب تاريخ معين إذا تم تمريره.
    public function availableSlots(?string $date = null): array
    {//جلب تفاصيل الموعد والمرشدselect
        //عن طريق ربط جدول time_slots بجدول users
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
              AND u.is_active = 1
              AND TIMESTAMP(s.slot_date, s.start_time) > NOW()
              AND s.capacity > (
                  SELECT COUNT(*)
                  FROM reservations r
                  WHERE r.slot_id = s.slot_id
                    AND r.status IN ('pending', 'confirmed')
              )";
//حساب السعة المتبقية
      //تقوم الدالة بحساب عدد الحجوزات الفعلية الحالية (التي حالتها pending أو confirmed) لهذا الموعد، وتطرحها من السعة الإجمالية (capacity) لتعرف كم مقعداً متاحاً للمطالعة أو الحجز. 
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
//انشاء حجز جديد
    public function create(int $userId, int $slotId, string $note): void
    {
        $this->db->beginTransaction();

        try {//يقفل صف المستخدم ( FOR UPDATE) ويتأكد أنه طالب فعَال.
            $userStatement = $this->db->prepare(
                "SELECT role, is_active FROM users WHERE user_id = ? FOR UPDATE"
            );
            $userStatement->execute([$userId]);
            $user = $userStatement->fetch();
            if ($user === false || $user["role"] !== "student" || (int) $user["is_active"] !== 1) {
                throw new DomainException("Only active student accounts can create reservations.");
            }
//سطر 60 إلى 71: يقفل الصفحة الرسمية ويتأكد أنه availableلا يتجاوز السعة عند الطلبات المتزامنة.
            $slotStatement = $this->db->prepare(
                "SELECT slot_id, slot_date, start_time, capacity, status
                 FROM time_slots
                 WHERE slot_id = :slot_id
                 FOR UPDATE"
            );
            $slotStatement->execute(["slot_id" => $slotId]);
            $slot = $slotStatement->fetch();

            if ($slot === false || $slot["status"] !== "available") {//يتأكد أن الموعد موجود وحالته available
                throw new DomainException("This time slot is no longer available.");//ذا اختل أي شرط من هذه الشروط، يتم إيقاف العملية فوراً وإطلاق استثناء (DomainException) يمنع إنشاء الحجز برسالة خطأ واضحة.
            }
            //يتأكد أن الموعد لم يبدأ بعد

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
//سطر 84 إلى 98: منع الحظر المكرر
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
            //الة fetchColumn() تبحث عن قيمة العمود (reservation_id). فإذا وجدت نتيجة (أي أن الاستعلام لم يعيد false) فهذا يعني أن الطالب حجز هذا الموعد مسبقاً.
            if ($existingStatement->fetchColumn() !== false) {
                // اذا مش حاجز بكون  فولس يتأكد أن الطالب لم يحجز هذا الموعد مسبقاً
                throw new DomainException(
                    "You already have a reservation record for this time slot."
                );
            }
//سطر 100 إلى 109: يتأكد أن السعة لم تكتمل.
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
//سطر 111 إلى 121: يدخل المراقبة بحالة pendingثم commit.
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

    public function all(): array
    {
        $statement = $this->db->query(
            "SELECT r.reservation_id, r.status, r.note, r.created_at,
                    student.full_name AS student_name, student.email AS student_email,
                    advisor.full_name AS advisor_name, s.slot_date, s.start_time, s.end_time
             FROM reservations r
             JOIN users student ON student.user_id = r.student_id
             JOIN time_slots s ON s.slot_id = r.slot_id
             JOIN users advisor ON advisor.user_id = s.advisor_id
             ORDER BY s.slot_date DESC, s.start_time DESC, r.reservation_id DESC"
        );

        return $statement->fetchAll();
    }

    public function statistics(): array
    {
        $statement = $this->db->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'pending'), 0) AS pending,
                    COALESCE(SUM(status = 'confirmed'), 0) AS confirmed,
                    COALESCE(SUM(status = 'rejected'), 0) AS rejected,
                    COALESCE(SUM(status = 'cancelled'), 0) AS cancelled
             FROM reservations"
        );

        return $statement->fetch();
    }

    public function updateStatus(int $reservationId, string $newStatus): void
    {
        if ($reservationId < 1 || !in_array($newStatus, ["confirmed", "rejected"], true)) {
            throw new DomainException("Choose a valid reservation and status.");
        }

        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare(
                "SELECT r.status, s.slot_date, s.start_time
                 FROM reservations r
                 JOIN time_slots s ON s.slot_id = r.slot_id
                 WHERE r.reservation_id = ?
                 FOR UPDATE"
            );
            $statement->execute([$reservationId]);
            $reservation = $statement->fetch();

            if ($reservation === false || $reservation["status"] !== "pending") {
                throw new DomainException("Only pending reservations can be confirmed or rejected.");
            }
            if ($newStatus === "confirmed"
                && strtotime($reservation["slot_date"] . " " . $reservation["start_time"]) <= time()) {
                throw new DomainException("A reservation cannot be confirmed after its time slot has started.");
            }

            $update = $this->db->prepare(
                "UPDATE reservations
                 SET status = ?
                 WHERE reservation_id = ? AND status = 'pending'"
            );
            $update->execute([$newStatus, $reservationId]);
            if ($update->rowCount() !== 1) {
                throw new DomainException("The reservation was changed by another request. Refresh and try again.");
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
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
