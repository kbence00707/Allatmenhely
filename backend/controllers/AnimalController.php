<?php
/**
 * Handles the /api/animals endpoints.
 *
 * Visitors (not logged in) only see animals with status "available" or "reserved".
 * Staff (logged in) see every animal and can create, edit and delete them.
 */
class AnimalController
{
    private const PUBLIC_STATUSES = ['available', 'reserved'];

    public function __construct(private Animal $animals)
    {
    }

    /** GET /api/animals?species=dog&search=bod&status=available */
    public function index(): void
    {
        $v = (new Validator($_GET, partial: true))
            ->oneOf('species', Animal::SPECIES)
            ->string('search', max: 100)
            ->oneOf('status', Auth::isStaff() ? Animal::STATUSES : self::PUBLIC_STATUSES);
        $query = $v->validate();

        $filters = [
            'species' => $query['species'] ?? null,
            'search'  => $query['search'] ?? null,
        ];
        if (!empty($query['status'])) {
            $filters['statuses'] = [$query['status']];
        } elseif (!Auth::isStaff()) {
            $filters['statuses'] = self::PUBLIC_STATUSES;
        }

        Response::json(['data' => $this->animals->findAll($filters)]);
    }

    /** GET /api/animals/{id} */
    public function show(int $id): void
    {
        $animal = $this->animals->find($id);
        if (!$animal || (!Auth::isStaff() && !in_array($animal['status'], self::PUBLIC_STATUSES, true))) {
            throw new HttpException(404, 'Az állat nem található.');
        }
        Response::json(['data' => $animal]);
    }

    /** POST /api/animals (staff only) */
    public function store(): void
    {
        // Default values for fields that were not sent
        $input = Request::json() + ['sex' => 'unknown', 'status' => 'available'];
        $data = $this->validate($input, partial: false);

        $id = $this->animals->create($data);
        Response::json([
            'message' => 'Az állat sikeresen létrehozva.',
            'data'    => $this->animals->find($id),
        ], 201);
    }

    /** PUT /api/animals/{id} (staff only) - only the fields that are sent are changed */
    public function update(int $id): void
    {
        $this->findOrFail($id);

        $data = $this->validate(Request::json(), partial: true);
        if (!$data) {
            throw new HttpException(422, 'Nincs módosítandó adat.');
        }

        $this->animals->update($id, $data);
        Response::json([
            'message' => 'Az állat adatai sikeresen módosítva.',
            'data'    => $this->animals->find($id),
        ]);
    }

    /** DELETE /api/animals/{id} (staff only) */
    public function destroy(int $id): void
    {
        $this->findOrFail($id);

        $count = $this->animals->appointmentCount($id);
        if ($count > 0) {
            throw new HttpException(409,
                "Az állathoz $count időpontfoglalás tartozik, ezért nem törölhető. " .
                'Állítsa az állapotát „örökbefogadva” értékre, vagy előbb törölje az időpontokat.'
            );
        }

        $this->animals->delete($id);
        Response::json(['message' => 'Az állat sikeresen törölve.']);
    }

    private function findOrFail(int $id): array
    {
        $animal = $this->animals->find($id);
        if (!$animal) {
            throw new HttpException(404, 'Az állat nem található.');
        }
        return $animal;
    }

    private function validate(array $input, bool $partial): array
    {
        return (new Validator($input, $partial))
            ->string('name', required: true, max: 100)
            ->oneOf('species', Animal::SPECIES, required: true)
            ->string('breed', max: 100)
            ->integer('age', min: 0, max: 40)
            ->oneOf('sex', Animal::SEXES, required: true)
            ->string('description', max: 5000)
            // A relative path (pictures/bodri.jpg) or a http(s) link, max. 255 characters.
            ->pattern('image_url', '#^(?=.{1,255}$)(https?://[^\s"\'<>]+|[\w\-./]+)$#',
                'A kép útvonala legyen relatív útvonal (pl. pictures/bodri.jpg) vagy http(s) link.')
            ->oneOf('status', Animal::STATUSES, required: true)
            ->validate();
    }
}
