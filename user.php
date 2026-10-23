<?php
//كلاس User يحتوي على وظائف تسجيل الدخول، تسجيل المستخدمين الجدد، استرجاع الأقسام، وتغيير حالة الحسابات.
final class User
{
    public function __construct(private PDO $db)//: يستقبل كائن PDO للاتصال بقاعدة البيانات.
    {
    }

    public function login(string $email, string $password): ?array
    {
        $statement = $this->db->prepare(
            "SELECT user_id, full_name, email, password_hash, role, is_active
             FROM users
             WHERE email = :email
             LIMIT 1"
        );
        $statement->execute(["email" => $email]);
        $user = $statement->fetch();
//يرجع null إذا لم يكن المستخدم موجوداً، أو إذا كان الحساب معطلاً، أو إذا كانت كلمة المرور غير صحيحة.Null في 3 حالات
        if (
            $user === false//المستخدم غير موجود H
            || (int) $user["is_active"] !== 1 // او معطل المستخدم غير موجد
            || !password_verify($password, $user["password_hash"])//كلمة المرور غير صحيحة
        ) {
            return null;
        }
//يحذف كلمة المرور وحالة الحساب من المصفوفة قبل إرجاعها، لأسباب أمنية.
        unset($user["password_hash"], $user["is_active"]);

        return $user;//يرجع مصفوفة تحتوي على user_id وfull_name وemail وrole.
    }

    public function getDepartments(): array
    {
        $statement = $this->db->query(
            "SELECT department_id, department_name
             FROM departments
             ORDER BY department_name"
        );

        return $statement->fetchAll();
    }

    public function all(): array
    {
        $statement = $this->db->query(
            "SELECT u.user_id, u.full_name, u.email, u.role, u.is_active, d.department_name
             FROM users u
             LEFT JOIN departments d ON d.department_id = u.department_id
             ORDER BY u.user_id DESC"
        );

        return $statement->fetchAll();
    }

    public function setActive(int $userId, bool $isActive, int $currentAdminId): void
    {
        if ($userId < 1) {
            throw new DomainException("Choose a valid user.");
        }
        if ($userId === $currentAdminId && !$isActive) {
            throw new DomainException("You cannot deactivate your own account.");
        }

        $statement = $this->db->prepare(
            "UPDATE users
             SET is_active = :is_active
             WHERE user_id = :user_id
               AND role IN ('student', 'advisor')
               AND is_active <> :current_status"
        );
        $status = $isActive ? 1 : 0;
        $statement->execute([
            "is_active" => $status,
            "user_id" => $userId,
            "current_status" => $status,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new DomainException(
                "Only student and advisor accounts with a different status can be changed."
            );
        }
    }

    public function register(
        string $fullName,
        string $email,
        string $password,
        int $departmentId
    ): bool
    {
        $statement = $this->db->prepare(
            "INSERT INTO users (full_name, email, password_hash, department_id)
             VALUES (:full_name, :email, :password_hash, :department_id)"
        );

        try {
            return $statement->execute([
                "full_name" => $fullName,
                "email" => $email,
                "password_hash" => password_hash($password, PASSWORD_DEFAULT),
                "department_id" => $departmentId,
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return false;
            }

            throw $exception;
        }
    }
}
