<?php

namespace App\Contracts;

interface PrivateApplicationDocumentScanner
{
    /** @return array{status:'clean'|'infected'|'unavailable'|'failed',engine:?string} */
    public function scan(string $absolutePath): array;

    public function available(): bool;
}
