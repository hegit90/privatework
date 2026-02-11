<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Input Validation
 */
class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $validated = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->validate();
    }

    /**
     * Perform validation
     */
    private function validate(): void
    {
        foreach ($this->rules as $field => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : $rules;
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->validateRule($field, $value, $rule);
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    /**
     * Validate a single rule
     */
    private function validateRule(string $field, mixed $value, string $rule): void
    {
        $params = [];

        if (str_contains($rule, ':')) {
            [$rule, $param] = explode(':', $rule, 2);
            $params = explode(',', $param);
        }

        $method = 'validate' . ucfirst($rule);

        if (method_exists($this, $method)) {
            $this->$method($field, $value, $params);
        }
    }

    /**
     * Required validation
     */
    private function validateRequired(string $field, mixed $value, array $params): void
    {
        if ($value === null || $value === '') {
            $this->addError($field, "The {$field} field is required.");
        }
    }

    /**
     * Email validation
     */
    private function validateEmail(string $field, mixed $value, array $params): void
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "The {$field} must be a valid email address.");
        }
    }

    /**
     * Min length validation
     */
    private function validateMin(string $field, mixed $value, array $params): void
    {
        $min = (int)$params[0];

        if (is_string($value) && strlen($value) < $min) {
            $this->addError($field, "The {$field} must be at least {$min} characters.");
        }

        if (is_numeric($value) && $value < $min) {
            $this->addError($field, "The {$field} must be at least {$min}.");
        }
    }

    /**
     * Max length validation
     */
    private function validateMax(string $field, mixed $value, array $params): void
    {
        $max = (int)$params[0];

        if (is_string($value) && strlen($value) > $max) {
            $this->addError($field, "The {$field} must not exceed {$max} characters.");
        }

        if (is_numeric($value) && $value > $max) {
            $this->addError($field, "The {$field} must not exceed {$max}.");
        }
    }

    /**
     * Numeric validation
     */
    private function validateNumeric(string $field, mixed $value, array $params): void
    {
        if ($value && !is_numeric($value)) {
            $this->addError($field, "The {$field} must be a number.");
        }
    }

    /**
     * Integer validation
     */
    private function validateInteger(string $field, mixed $value, array $params): void
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, "The {$field} must be an integer.");
        }
    }

    /**
     * Alpha validation
     */
    private function validateAlpha(string $field, mixed $value, array $params): void
    {
        if ($value && !ctype_alpha(str_replace(' ', '', $value))) {
            $this->addError($field, "The {$field} must contain only letters.");
        }
    }

    /**
     * Alphanumeric validation
     */
    private function validateAlphanumeric(string $field, mixed $value, array $params): void
    {
        if ($value && !ctype_alnum(str_replace(' ', '', $value))) {
            $this->addError($field, "The {$field} must contain only letters and numbers.");
        }
    }

    /**
     * Confirmation validation
     */
    private function validateConfirmed(string $field, mixed $value, array $params): void
    {
        $confirmField = $field . '_confirmation';
        $confirmValue = $this->data[$confirmField] ?? null;

        if ($value !== $confirmValue) {
            $this->addError($field, "The {$field} confirmation does not match.");
        }
    }

    /**
     * In array validation
     */
    private function validateIn(string $field, mixed $value, array $params): void
    {
        if ($value && !in_array($value, $params)) {
            $this->addError($field, "The selected {$field} is invalid.");
        }
    }

    /**
     * Date validation
     */
    private function validateDate(string $field, mixed $value, array $params): void
    {
        if ($value && !strtotime($value)) {
            $this->addError($field, "The {$field} must be a valid date.");
        }
    }

    /**
     * URL validation
     */
    private function validateUrl(string $field, mixed $value, array $params): void
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, "The {$field} must be a valid URL.");
        }
    }

    /**
     * Unique validation (requires database)
     */
    private function validateUnique(string $field, mixed $value, array $params): void
    {
        if (!$value) return;

        $table = $params[0] ?? null;
        $column = $params[1] ?? $field;
        $except = $params[2] ?? null;

        if (!$table) {
            throw new \Exception("Table name required for unique validation");
        }

        $db = app(Database::class);
        $query = $db->table($table)->where($column, $value);

        if ($except) {
            $query->where('id', '!=', $except);
        }

        if ($query->first()) {
            $this->addError($field, "The {$field} has already been taken.");
        }
    }

    /**
     * Add validation error
     */
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get validated data
     */
    public function validated(): array
    {
        return $this->validated;
    }
}
