<?php

namespace DFrame\Application;

class Validator
{
    /**
     * Validation errors collected after make()
     * @var array<string, list<string>>
     */
    private array $errors = [];

    /**
     * The first validation error encountered (preserve order)
     * @var string|null
     */
    private ?string $firstError = null;

    /* ========================================================
     * BASIC RULES
     * ======================================================== */

    public static function required($value): bool
    {
        return !empty($value) || $value === '0';
    }

    public static function email($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function minLength($value, int $min): bool
    {
        return mb_strlen((string) $value) >= $min;
    }

    public static function maxLength($value, int $max): bool
    {
        return mb_strlen((string) $value) <= $max;
    }

    public static function numeric($value): bool
    {
        return is_numeric($value);
    }

    public static function integer($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public static function boolean($value): bool
    {
        if (is_bool($value)) return true;
        return in_array($value, ['true', 'false', '1', '0', 1, 0], true);
    }

    public static function alpha($value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z]+$/', $value);
    }

    public static function alphaNum($value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9]+$/', $value);
    }

    public static function inList($value, string $param): bool
    {
        return in_array($value, explode(',', $param), true);
    }

    public static function notInList($value, string $param): bool
    {
        return !in_array($value, explode(',', $param), true);
    }

    public static function url($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public static function date($value): bool
    {
        return strtotime($value) !== false;
    }

    /* ========================================================
     * FILE UPLOAD RULES
     * ======================================================== */

    public static function isFile($value): bool
    {
        return is_array($value)
            && isset($value['error'], $value['tmp_name'])
            && $value['error'] === UPLOAD_ERR_OK
            && is_uploaded_file($value['tmp_name']);
    }

    public static function isImage($value): bool
    {
        if (!self::isFile($value)) return false;

        $mime = mime_content_type($value['tmp_name']);
        return in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/svg+xml',
        ], true);
    }

    public static function mimes($value, string $param): bool
    {
        if (!self::isFile($value)) return false;

        $allowed = explode(',', strtolower($param));
        $ext = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));

        return in_array($ext, $allowed, true);
    }

    public static function mimeTypes($value, string $param): bool
    {
        if (!self::isFile($value)) return false;

        $allowed = explode(',', strtolower($param));
        $mime = strtolower(mime_content_type($value['tmp_name']));

        return in_array($mime, $allowed, true);
    }

    public static function maxFile($value, int $maxKB): bool
    {
        if (!self::isFile($value)) return false;

        return ($value['size'] / 1024) <= $maxKB;
    }

    public static function betweenFile($value, string $param): bool
    {
        if (!self::isFile($value)) return false;

        [$min, $max] = array_map('intval', explode(',', $param));
        $sizeKB = $value['size'] / 1024;

        return $sizeKB >= $min && $sizeKB <= $max;
    }

    /* ========================================================
     * VALIDATION ENGINE
     * ======================================================== */

    public function make(array $data, array $rules, array $messages = []): void
    {
        $this->errors = [];
        $this->firstError = null;

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $ruleList = explode('|', $ruleString);

            foreach ($ruleList as $rule) {

                $param = null;
                $ruleName = $rule;

                if (strpos($rule, ':') !== false) {
                    [$ruleName, $param] = explode(':', $rule, 2);
                }

                /** FILE RULES FIRST - only when value looks like an uploaded file */
                $fileRuleCheck = null;
                if (self::isFile($value)) {
                    $fileRuleCheck = match ($ruleName) {
                        'file'       => true,
                        'image'      => self::isImage($value),
                        'mimes'      => self::mimes($value, (string)$param),
                        'mimetypes'  => self::mimeTypes($value, (string)$param),
                        'max'        => self::maxFile($value, (int)$param),
                        'between'    => self::betweenFile($value, (string)$param),
                        default      => null
                    };
                }

                if ($fileRuleCheck !== null) {
                    $valid = $fileRuleCheck;
                } else {
                    /** NORMAL RULES */
                    $valid = match ($ruleName) {
                        'required' => self::required($value),
                        'email' => self::email($value),
                        'string' => is_string($value),
                        'min' => self::minLength($value, (int)$param),
                        'max' => self::maxLength($value, (int)$param),
                        'numeric' => self::numeric($value),
                        'integer' => self::integer($value),
                        'boolean' => self::boolean($value),
                        'alpha' => self::alpha($value),
                        'alpha_num' => self::alphaNum($value),
                        'in' => self::inList($value, (string)$param),
                        'not_in' => self::notInList($value, (string)$param),
                        'date' => self::date($value),
                        'url' => self::url($value),
                        'array' => is_array($value),
                        default => throw new \Exception("Validation rule '$ruleName' does not exist."),
                    };
                }

                /** Store error */
                if (!$valid) {
                    $key = $field . '.' . $ruleName;
                    $msg = $messages[$key] ?? "$field validation failed for $ruleName";
                    $this->errors[$field][] = $msg;

                    if ($this->firstError === null) {
                        $this->firstError = $msg;
                    }
                }
            }
        }
    }

    /* ========================================================
     * RESULTS
     * ======================================================== */

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): ?string
    {
        return $this->firstError;
    }
}
