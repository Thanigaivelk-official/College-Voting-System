<?php
/**
 * College Voting System - Form & Input Validation Utilities
 */

declare(strict_types=1);

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string $field, string $label): self {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val === '') {
            $this->errors[$field] = "{$label} is required.";
        }
        return $this;
    }

    public function email(string $field, string $label = 'Email'): self {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label} must be a valid email address.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label): self {
        $val = (string)($this->data[$field] ?? '');
        if ($val !== '' && mb_strlen($val) < $min) {
            $this->errors[$field] = "{$label} must be at least {$min} characters.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label): self {
        $val = (string)($this->data[$field] ?? '');
        if ($val !== '' && mb_strlen($val) > $max) {
            $this->errors[$field] = "{$label} cannot exceed {$max} characters.";
        }
        return $this;
    }

    public function matches(string $field1, string $field2, string $label): self {
        $val1 = (string)($this->data[$field1] ?? '');
        $val2 = (string)($this->data[$field2] ?? '');
        if ($val1 !== $val2) {
            $this->errors[$field2] = "{$label} does not match.";
        }
        return $this;
    }

    public function passwordStrength(string $field, string $label = 'Password'): self {
        $val = (string)($this->data[$field] ?? '');
        if ($val === '') return $this;

        if (strlen($val) < 8) {
            $this->errors[$field] = "{$label} must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Z]/', $val) || !preg_match('/[a-z]/', $val) || !preg_match('/[0-9]/', $val)) {
            $this->errors[$field] = "{$label} must contain at least one uppercase letter, one lowercase letter, and one number.";
        }
        return $this;
    }

    public function integer(string $field, string $label, ?int $min = null, ?int $max = null): self {
        $val = $this->data[$field] ?? null;
        if ($val === null || $val === '') return $this;

        if (!filter_var($val, FILTER_VALIDATE_INT)) {
            $this->errors[$field] = "{$label} must be a valid number.";
            return $this;
        }

        $intVal = (int)$val;
        if ($min !== null && $intVal < $min) {
            $this->errors[$field] = "{$label} must be at least {$min}.";
        }
        if ($max !== null && $intVal > $max) {
            $this->errors[$field] = "{$label} cannot be greater than {$max}.";
        }
        return $this;
    }

    public function inArray(string $field, array $allowed, string $label): self {
        $val = $this->data[$field] ?? '';
        if ($val !== '' && !in_array($val, $allowed, true)) {
            $this->errors[$field] = "{$label} selection is invalid.";
        }
        return $this;
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    public function firstError(): ?string {
        if (empty($this->errors)) {
            return null;
        }
        return reset($this->errors);
    }

    public function addError(string $field, string $message): self {
        $this->errors[$field] = $message;
        return $this;
    }

    /**
     * Helper to validate uploaded image safely
     */
    public static function validateImageUpload(array $file): ?string {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // File is optional
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return "File upload failed with error code: " . $file['error'];
        }

        $maxSize = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $maxSize) {
            return "Uploaded photo exceeds maximum allowed size of 2MB.";
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes, true)) {
            return "Invalid file type. Only JPG, PNG, and WebP images are allowed.";
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExts, true)) {
            return "Invalid file extension.";
        }

        return null;
    }
}
