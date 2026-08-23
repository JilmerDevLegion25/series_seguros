<?php

namespace App\Support\Security;

final readonly class SensitiveValueSanitizer
{
    public static function maskPreservingLastTwo(string $value): string
    {
        $length = strlen($value);

        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(0, $length - 2)).substr($value, -2);
    }

    public static function sanitizeFreeText(string $value): string
    {
        $sanitized = preg_replace(
            '/\b(app[_-]?key|otp[_-]?mac[_-]?key|api[_-]?key|secret|token|password|passwd|authorization|bearer)\b\s*[:=]?\s*([^\s,;]+)/i',
            '$1=[redacted]',
            $value,
        ) ?? $value;

        $sanitized = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $sanitized) ?? $sanitized;
        $sanitized = preg_replace_callback(
            '/\+[1-9][0-9]{7,14}\b|\b3[0-9]{9}\b/',
            static fn (array $matches): string => self::maskPreservingLastTwo($matches[0]),
            $sanitized,
        ) ?? $sanitized;
        $sanitized = preg_replace('/\b(cedula|cedula|cc|identidad)\b\s*[:#=\-]?\s*[0-9]{6,10}\b/i', '$1=[redacted]', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\botp\b\s*[:#=\-]?\s*[0-9]{6}\b/i', 'OTP=[redacted]', $sanitized) ?? $sanitized;

        return $sanitized;
    }
}
