<?php
/**
 * Database access for the "animals" table.
 * Every query uses prepared statements (placeholders like :id), so user input
 * can never change the SQL command itself (no SQL injection).
 */
class Animal
{
    public const SPECIES  = ['dog', 'cat', 'other'];
    public const SEXES    = ['male', 'female', 'unknown'];
    public const STATUSES = ['available', 'reserved', 'adopted'];

    /** Columns that may be written by create/update. */
    private const COLUMNS = ['name', 'species', 'breed', 'age', 'sex', 'description', 'image_url', 'status'];

    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array $filters species (string), statuses (string[]), search (part of the name)
     */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['species'])) {
            $where[] = 'species = :species';
            $params['species'] = $filters['species'];
        }
        if (!empty($filters['statuses'])) {
            $placeholders = [];
            foreach (array_values($filters['statuses']) as $i => $status) {
                $placeholders[] = ":status$i";
                $params["status$i"] = $status;
            }
            $where[] = 'status IN (' . implode(', ', $placeholders) . ')';
        }
        if (!empty($filters['search'])) {
            $where[] = 'name LIKE :search';
            // Escape the LIKE wildcards so "%" and "_" are searched literally.
            $params['search'] = '%' . addcslashes($filters['search'], '%_\\') . '%';
        }

        $sql = 'SELECT * FROM animals';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM animals WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $data = array_intersect_key($data, array_flip(self::COLUMNS));
        $columns = array_keys($data);

        $sql = sprintf(
            'INSERT INTO animals (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', array_map(fn($c) => ":$c", $columns))
        );
        $this->db->prepare($sql)->execute($data);
        return (int) $this->db->lastInsertId();
    }

    /** Updates only the columns present in $data. */
    public function update(int $id, array $data): void
    {
        $data = array_intersect_key($data, array_flip(self::COLUMNS));
        if (!$data) {
            return;
        }
        $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($data)));
        $data['id'] = $id;
        $this->db->prepare("UPDATE animals SET $set WHERE id = :id")->execute($data);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM animals WHERE id = :id')->execute(['id' => $id]);
    }

    public function appointmentCount(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM appointments WHERE animal_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }
}
