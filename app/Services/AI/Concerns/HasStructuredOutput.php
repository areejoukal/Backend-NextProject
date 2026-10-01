<?php

namespace App\Services\AI\Concerns;

use App\Services\AI\Exceptions\AIInvalidResponseException;

trait HasStructuredOutput
{
    /**
     * التحقق من أن JSON المُستلم يحتوي على الحقول المطلوبة.
     */
    protected function validateSchema(array $data, array $schema): array
    {
        $errors = [];

        foreach ($schema as $field => $rules) {
            $isRequired = $rules['required'] ?? true;
            $type = $rules['type'] ?? 'string';

            if (!array_key_exists($field, $data)) {
                if ($isRequired) {
                    $errors[] = "Missing field: {$field}";
                }
                continue;
            }

            $value = $data[$field];

            if (!$this->matchesType($value, $type)) {
                $errors[] = "Field '{$field}' expected {$type}, got " . gettype($value);
            }

            // التحقق من قائمة
            if ($type === 'array' && isset($rules['items'])) {
                foreach ($value as $i => $item) {
                    if (!$this->matchesType($item, $rules['items'])) {
                        $errors[] = "Item {$i} in '{$field}' expected {$rules['items']}";
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw AIInvalidResponseException::schemaValidationFailed($errors);
        }

        return $data;
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && !array_is_list($value),
            'null' => is_null($value),
            default => true,
        };
    }

    /**
     * تعقيم نص عربي: إزالة أي backticks أو زخرفة غير مرغوبة.
     */
    protected function cleanArabicText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}