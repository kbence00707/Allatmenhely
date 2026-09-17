<?php
/**
 * Database access for the "users" table (shelter employees).
 */
class User
{
    public function __construct(private PDO $db)
    {
    }

    /** Includes password_hash - only used for checking the password at login. */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /** Safe version without the password hash - can be sent to the frontend. */
    public function findPublic(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, email, created_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $name, string $email, string $password): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)'
        );
        $stmt->execute([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updatePassword(int $id, string $password): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }
}
