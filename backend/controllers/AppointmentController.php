<?php
/**
 * Handles the /api/appointments endpoints.
 *
 * Visitors can only CREATE an appointment (book a visit).
 * Everything else (listing, viewing, editing, deleting) is for staff only.
 */
class AppointmentController
{
    private const PHONE_REGEX = '/^[0-9 +()\/-]{6,30}$/';

    public function __construct(
        private Appointment $appointments,
        private Animal $animals,
        private array $config
    ) {
    }

    /** GET /api/appointments?status=pending&animal_id=1&from=2026-01-01&to=2026-12-31&upcoming=1&sort=asc */
    public function index(): void
    {
        $filters = (new Validator($_GET, partial: true))
            ->oneOf('status', Appointment::STATUSES)
            ->integer('animal_id', min: 1)
            ->date('from')
            ->date('to')
            ->oneOf('sort', ['asc', 'desc'])
            ->oneOf('upcoming', ['0', '1'])
            ->validate();

        // upcoming=1 -> only today and later
        if (($filters['upcoming'] ?? null) === '1' && empty($filters['from'])) {
            $filters['from'] = date('Y-m-d');
        }

        Response::json(['data' => $this->appointments->findAll($filters)]);
    }

    /** GET /api/appointments/{id} */
    public function show(int $id): void
    {
        Response::json(['data' => $this->findOrFail($id)]);
    }

    /** POST /api/appointments (public: visitors book here) */
    public function store(): void
    {
        $isStaff = Auth::isStaff();

        $v = $this->validator(Request::json(), partial: false);
        if ($isStaff) {
            // Only staff may choose the status; visitors' bookings are always "pending".
            $v->oneOf('status', Appointment::STATUSES);
        }

        $animalId = $v->get('animal_id');
        if ($animalId !== null) {
            $animal = $this->animals->find($animalId);
            if (!$animal) {
                $v->addError('animal_id', 'Nincs ilyen állat.');
            } elseif (!$isStaff && $animal['status'] !== 'available') {
                $v->addError('animal_id', 'Ez az állat jelenleg nem foglalható.');
            }
        }

        if (!$isStaff && $v->get('appointment_date') !== null && $v->get('appointment_time') !== null) {
            $this->checkVisitingHours($v, $v->get('appointment_date'), $v->get('appointment_time'));
        }

        $data = array_filter($v->validate(), fn($value) => $value !== null);
        $data['status'] ??= 'pending';

        if (in_array($data['status'], Appointment::ACTIVE_STATUSES, true)
            && $this->appointments->isSlotTaken($data['animal_id'], $data['appointment_date'], $data['appointment_time'])) {
            throw new HttpException(409, 'Erre az időpontra ez az állat már foglalt. Kérjük, válasszon másik időpontot.');
        }

        $id = $this->appointments->create($data);
        Response::json([
            'message' => 'Az időpontfoglalás sikeresen rögzítve. Munkatársaink hamarosan felveszik Önnel a kapcsolatot.',
            'data'    => $this->appointments->find($id),
        ], 201);
    }

    /** PUT /api/appointments/{id} (staff only) - e.g. {"status": "confirmed"} */
    public function update(int $id): void
    {
        $existing = $this->findOrFail($id);

        $v = $this->validator(Request::json(), partial: true)
            ->oneOf('status', Appointment::STATUSES, required: true);

        $animalId = $v->get('animal_id');
        if ($animalId !== null && !$this->animals->find($animalId)) {
            $v->addError('animal_id', 'Nincs ilyen állat.');
        }

        $data = $v->validate();
        if (!$data) {
            throw new HttpException(422, 'Nincs módosítandó adat.');
        }

        // If the animal, date, time or status changes, make sure the slot is still free.
        $slotFields = ['animal_id', 'appointment_date', 'appointment_time', 'status'];
        if (array_intersect_key($data, array_flip($slotFields))) {
            $merged = array_merge($existing, $data);
            $time = strlen($merged['appointment_time']) === 5 ? $merged['appointment_time'] . ':00' : $merged['appointment_time'];

            if (in_array($merged['status'], Appointment::ACTIVE_STATUSES, true)
                && $this->appointments->isSlotTaken((int) $merged['animal_id'], $merged['appointment_date'], $time, $id)) {
                throw new HttpException(409, 'Erre az időpontra ez az állat már foglalt.');
            }
        }

        $this->appointments->update($id, $data);
        Response::json([
            'message' => 'Az időpont sikeresen módosítva.',
            'data'    => $this->appointments->find($id),
        ]);
    }

    /** DELETE /api/appointments/{id} (staff only) */
    public function destroy(int $id): void
    {
        $this->findOrFail($id);
        $this->appointments->delete($id);
        Response::json(['message' => 'Az időpont sikeresen törölve.']);
    }

    private function findOrFail(int $id): array
    {
        $appointment = $this->appointments->find($id);
        if (!$appointment) {
            throw new HttpException(404, 'Az időpont nem található.');
        }
        return $appointment;
    }

    private function validator(array $input, bool $partial): Validator
    {
        return (new Validator($input, $partial))
            ->integer('animal_id', required: true, min: 1)
            ->string('visitor_name', required: true, max: 100)
            ->email('visitor_email', required: true)
            ->pattern('visitor_phone', self::PHONE_REGEX, 'Érvénytelen telefonszám (pl. +36 30 123 4567).')
            ->date('appointment_date', required: true)
            ->time('appointment_time', required: true)
            ->string('note', max: 1000);
    }

    /** Visitors may only book in the future and within opening hours. */
    private function checkVisitingHours(Validator $v, string $date, string $time): void
    {
        $opening = $this->config['opening_time'];
        $closing = $this->config['closing_time'];

        if (new DateTime("$date $time") <= new DateTime()) {
            $v->addError('appointment_date', 'Csak jövőbeli időpontra lehet foglalni.');
        } elseif ($date > date('Y-m-d', strtotime('+6 months'))) {
            $v->addError('appointment_date', 'Legfeljebb 6 hónappal előre lehet foglalni.');
        }

        $hhmm = substr($time, 0, 5);
        if ($hhmm < $opening || $hhmm >= $closing) {
            $v->addError('appointment_time', "Látogatási időpontot $opening és $closing között lehet foglalni.");
        }
    }
}
