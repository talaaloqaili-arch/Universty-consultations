<?php

final class TimeSlot
{
    public function __construct(private PDO $db)
    {
    }
//رجع كل المرشدين (الفعّالين ) مرتبين بالاسم.
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
//يرجع كل المواعيد مع اسم المرشد
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
        //يحسب بـ الاستعلام الفرعي عدد كل الحجوزات ( reservation_count)
        //عدد العناصر المعلقة/المؤكدة ( active_reservations).

        return $statement->fetchAll();
    }
    //إنشاء وإضافة موعد متاح جديد
    public function create(
        //قبل إدخال الموعد في قاعدة البيانات، تمر العملية بعدة شروط تأكيد تمنع الأخطاء:
        int $advisorId,
        string $date,
        string $startTime,
        string $endTime,
        int $capacity
    ): void {
        $this->validateDetails($advisorId, $date, $startTime, $endTime, $capacity);//يتحقق من صيغة المدخلات.
        $this->ensureFutureStart($date, $startTime);//يتحقق من أن الموعد في المستقبل.
        $this->ensureAdvisor($advisorId, true);//يتحقق من أن المرشد موجود وفعّال.
        $this->ensureNoOverlap($advisorId, $date, $startTime, $endTime);//يتحقق من عدم وجود موعد متداخل لنفس المرشد.
//. حفظ البيانات في قاعدة البيانات
//تجهيز استعلام SQL
        $statement = $this->db->prepare(
            "INSERT INTO time_slots (advisor_id, slot_date, start_time, end_time, capacity, status)
             VALUES (?, ?, ?, ?, ?, 'available')"
        );
        //: تُنفذ الاستعلام بإدخال القيم المرسلة
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
//فتح المعاملة (beginTransaction) والقفل (FOR UPDATE)
        $this->db->beginTransaction();
//يتم جلب بيانات الموعد الحالي وقفل صفه في قاعدة البيانات مؤقتاً لمنع أي تعديلات متزامنة تحدث في نفس اللحظة من مستخدمين آخرين.
        try {
            $slotStatement = $this->db->prepare(
                "SELECT * FROM time_slots WHERE slot_id = ? FOR UPDATE"
            );
            $slotStatement->execute([$slotId]);
            $currentSlot = $slotStatement->fetch();
            if ($currentSlot === false) {//يتحقق من وجود الموعد.
                throw new DomainException("Time slot not found.");
            }
            //حساب وفحص الحجوزات المرتبطة
            $countsStatement = $this->db->prepare(
                //يتم حساب إجمالي الحجوزات المرتبطة بهذا الموعد
                "SELECT COUNT(*) AS total,
                        COALESCE(SUM(status IN ('pending', 'confirmed')), 0) AS active
                 FROM reservations WHERE slot_id = ?"
                 //عدد الحجوزات النشطة الحالية فقط
            );
            $countsStatement->execute([$slotId]);
            $counts = $countsStatement->fetch();
            //راقبة تغيير الجدول الزمني 
            $scheduleChanged = $date !== $currentSlot["slot_date"]
                || substr($startTime, 0, 5) !== substr($currentSlot["start_time"], 0, 5)
                || substr($endTime, 0, 5) !== substr($currentSlot["end_time"], 0, 5)
                || $advisorId !== (int) $currentSlot["advisor_id"];
         //منع تعديل المواعيد المحجوزة:
            if ((int) $counts["total"] > 0 && $scheduleChanged) {
                throw new DomainException(
                    "Date, time, and advisor cannot change after reservations have been recorded."
                );
            }
            //لا يمكن تقليل السعة الاستيعابية للموعد لتصبح أقل من عدد الطلاب الحجزين بالفعل بحالات معلقة أو مؤكدة
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
            $this->db->commit();//: إذا نجحت كل العمليات، يتم حفظ التغييرات في قاعدة البيانات.
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();//: إذا حدث خطأ، يتم التراجع عن أي تغييرات تم إجراؤها في قاعدة البيانات.
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
            );//: يتم قفل الصف في قاعدة البيانات مؤقتاً لمنع أي تعديلات متزامنة تحدث في نفس اللحظة من مستخدمين آخرين.
            $slotStatement->execute([$slotId]);
            if ($slotStatement->fetchColumn() === false) {
                throw new DomainException("Time slot not found.");
            }
//يتحقق من وجود أي حجوزات مرتبطة بالموعد قبل حذفه.
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
            $this->db->commit();//:  تُثبت التعديلات إذا نجحت كل العمليات، يتم حفظ التغييرات في قاعدة البيانات.
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }
//التحقق من صحة وصيغة كافة المدخلات
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
