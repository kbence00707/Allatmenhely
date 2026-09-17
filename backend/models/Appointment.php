<?php
/**
 * Database access for the "appointments" table.
 * Appointments are always returned together with the animal's name and species
 * (JOIN), so staff can see which animal the visitor wants to meet.
 */
class Appointment
{
    public const STATUSES = ['pending', 'confirmed', 'completed', 'cancelled'];

    /** Statuses that "occupy" a time slot for an animal. */
    public const ACTIVE_STATUSES = ['pending', 'confirmed'];

    private const COLUMNS = [
        'animal_id', 'visitor_name', 'visitor_email', 'visitor_phone',
        'appointment_date', 'appointment_time', 'note', 'status',
    ];

    private const SELECT = '
        SELECT ap.*, an.name AS animal_name, an.species AS animal_species
        FROM appointments ap
        JOIN animals an ON an.id = ap.animal_id';

    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array $filters status, animal_id, from (date), to (date), sort ('asc' or 'desc')
     */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'ap.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['animal_id'])) {
            $where[] = 'ap.animal_id = :animal_id';
            $params['animal_id'] = $filters['animal_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'ap.appointment_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'ap.appointment_date <= :to';
            $params['to'] = $filters['to'];
        }

        $sql = self::SELECT;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        // Chronological order. The direction is chosen from a fixed list, never taken directly from user input.
        $direction = ($filters['sort'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY ap.appointment_date $direction, ap.appointment_time $direction, ap.id $direction";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'format'], $stmt->fetchAll());
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . ' WHERE ap.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->format($row) : null;
    }

    public function create(array $data): int
    {
        $data = array_intersect_key($data, array_flip(self::COLUMNS));
        $columns = array_keys($data);

        $sql = sprintf(
            'INSERT INTO appointments (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', array_map(fn($c) => ":$c", $columns))
        );
        $this->db->prepare($sql)->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $data = array_intersect_key($data, array_flip(self::COLUMNS));
        if (!$data) {
            return;
        }
        $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($data)));
        $data['id'] = $id;
        $this->db->prepare("UPDATE appointments SET $set WHERE id = :id")->execute($data);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM appointments WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Is this animal already booked (pending/confirmed) at the same date and time?
     * $excludeId lets an appointment be edited without "colliding with itself".
     */
    public function isSlotTaken(int $animalId, string $date, string $time, ?int $excludeId = null): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM appointments
             WHERE animal_id = :animal_id
               AND appointment_date = :date
               AND appointment_time = :time
               AND status IN ('pending', 'confirmed')
               AND id <> :exclude_id"
        );
        $stmt->execute([
            'animal_id'  => $animalId,
            'date'       => $date,
            'time'       => $time,
            'exclude_id' => $excludeId ?? 0,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** "10:00:00" -> "10:00" for easier display. */
    private function format(array $row): array
    {
        $row['appointment_time'] = substr($row['appointment_time'], 0, 5);
        return $row;
    }
}
