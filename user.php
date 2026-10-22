<?php

final class User
{
    public function __construct(private PDO $db)
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

        if (
            $user === false
            || (int) $user["is_active"] !== 1
            || !password_verify($password, $user["password_hash"])
        ) {
            return null;
        }

        unset($user["password_hash"], $user["is_active"]);

        return $user;
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
