<?php

namespace App\Services;

/**
 * Resolves the effective upload ceiling across the app, program, and PHP limits.
 * The post-body allowance reserves room for multipart headers and form fields.
 */
class ApplicationDocumentUploadPolicy
{
    private const POST_BODY_OVERHEAD_BYTES = 64 * 1024;

    public function maximumBytes(?int $requirementMaximum = null): int
    {
        $configured = (int) config('agricultural_documents.maximum_size_bytes', 10 * 1024 * 1024);
        $limits = [$configured > 0 ? $configured : 10 * 1024 * 1024];

        $uploadLimit = $this->parseIniBytes((string) ini_get('upload_max_filesize'));
        if ($uploadLimit > 0) {
            $limits[] = $uploadLimit;
        }

        $postLimit = $this->parseIniBytes((string) ini_get('post_max_size'));
        if ($postLimit > 0) {
            $limits[] = max(1, $postLimit - self::POST_BODY_OVERHEAD_BYTES);
        }

        if ($requirementMaximum !== null && $requirementMaximum > 0) {
            $limits[] = $requirementMaximum;
        }

        return max(1, min($limits));
    }

    public function maximumKilobytes(?int $requirementMaximum = null): int
    {
        return (int) ceil($this->maximumBytes($requirementMaximum) / 1024);
    }

    private function parseIniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0') {
            return 0;
        }

        if (! preg_match('/^(\d+(?:\.\d+)?)\s*([KMG])?B?$/i', $value, $matches)) {
            return 0;
        }

        $power = match (strtoupper($matches[2] ?? '')) {
            'K' => 1,
            'M' => 2,
            'G' => 3,
            default => 0,
        };

        return (int) round((float) $matches[1] * (1024 ** $power));
    }
}
