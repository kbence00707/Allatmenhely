<?php
/**
 * Server-side input validation.
 *
 * Usage:
 *   $v = new Validator($input);
 *   $v->string('name', required: true, max: 100);
 *   $clean = $v->validate();   // throws HTTP 422 with all error messages if something is wrong
 *
 * "Partial" mode is used for updates (PUT): fields that were not sent are
 * simply left unchanged instead of being reported as missing.
 */
class Validator
{
    private array $errors = [];
    private array $clean = [];

    public function __construct(private array $data, private bool $partial = false)
    {
    }

    /**
     * Common check for every rule.
     * Returns the trimmed value, or null if the rule should stop here.
     */
    private function value(string $field, bool $required): mixed
    {
        $present = array_key_exists($field, $this->data);
        $value = $this->data[$field] ?? null;
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            if ($required && (!$this->partial || $present)) {
                $this->errors[$field] = 'Ez a mező kötelező.';
            } elseif ($present || !$this->partial) {
                $this->clean[$field] = null; // optional field left empty
            }
            return null;
        }
        return $value;
    }

    public function string(string $field, bool $required = false, int $max = 255): self
    {
        $value = $this->value($field, $required);
        if ($value === null) {
            return $this;
        }
        if (!is_string($value)) {
            $this->errors[$field] = 'Szöveget kell megadni.';
        } elseif (mb_strlen($value) > $max) {
            $this->errors[$field] = "Legfeljebb $max karakter lehet.";
        } else {
            $this->clean[$field] = $value;
        }
        return $this;
    }

    public function integer(string $field, bool $required = false, int $min = 0, int $max = PHP_INT_MAX): self
    {
        $value = $this->value($field, $required);
        if ($value === null) {
            return $this;
        }
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false || is_bool($value)) {
            $this->errors[$field] = 'Egész számot kell megadni.';
        } elseif ($int < $min || $int > $max) {
            $this->errors[$field] = "Az értéknek $min és $max között kell lennie.";
        } else {
            $this->clean[$field] = $int;
        }
        return $this;
    }

    public function oneOf(string $field, array $allowed, bool $required = false): self
    {
        $value = $this->value($field, $required);
        if ($value === null) {
            return $this;
        }
        if (!in_array($value, $allowed, true)) {
            $this->errors[$field] = 'Érvénytelen érték. Lehetséges értékek: ' . implode(', ', $allowed) . '.';
        } else {
            $this->clean[$field] = $value;
        }
        return $this;
    }

    public function email(string $field, bool $required = false): self
    {
        $value = $this->value($field, $required);
        if ($value === null) {
            return $this;
        }
        if (!is_string($value) || mb_strlen($value) > 255 || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Érvénytelen e-mail cím.';
        } else {
            $this->clean[$field] = $value;
        }
        return $this;
    }

    /** Checks a string against a regular expression. */
    public function pattern(string $field, string $regex, string $message, bool $required = false): self
    {
        $value = $this->value($field, $required);
        if ($value === null) {
            return $this;
        }
        if (!is_string($value) || !preg_match($regex, $value)) {
            $this->errors[$field] = $message;
        } else {
            $this->clean[$field] = $value;
        }
        return $this;
    }

    /** Date in YYYY-MM-DD format (this is what <input type="date"> sends). */
    public function date(string $field, bool $required = false): self
    {
        $value = $this->value($field, $required);
        if ($value === null) {
            return $this;
        }
        $date = is_string($value) ? DateTime::createFromFormat('!Y-m-d', $value) : false;
        if (!$date || $date->format('Y-m-d') !== $value) {
            $this->errors[$field] = 'Érvénytelen dátum (formátum: ÉÉÉÉ-HH-NN).';
        } else {
            $this->clean[$field] = $value;
        }
        return $this;
    }

    /** Time in HH:MM or HH:MM:SS format (this is what <input type="time"> sends). Stored as HH:MM:SS. */
    public function time(string $field, bool $required = false): self
    {
        $value = $this->value($field, $required);
        if ($value === null) {
            return $this;
        }
        if (!is_string($value) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value)) {
            $this->errors[$field] = 'Érvénytelen időpont (formátum: ÓÓ:PP).';
        } else {
            $this->clean[$field] = strlen($value) === 5 ? "$value:00" : $value;
        }
        return $this;
    }

    /** Lets controllers add their own error messages (e.g. "date is in the past"). */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /** The already validated value of a field (null if missing or invalid). */
    public function get(string $field): mixed
    {
        return $this->clean[$field] ?? null;
    }

    /** Returns only the validated fields, or throws HTTP 422 with every error message. */
    public function validate(): array
    {
        if ($this->errors) {
            throw new HttpException(422, 'Hibás vagy hiányzó adatok.', $this->errors);
        }
        return $this->clean;
    }
}
