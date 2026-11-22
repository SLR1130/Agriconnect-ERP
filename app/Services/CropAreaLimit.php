<?php

namespace App\Services;

use App\Models\Farm;
use Illuminate\Validation\ValidationException;

class CropAreaLimit
{
    private const SQUARE_METERS_PER_HECTARE = 10000;

    public function assertWithinFarm(?Farm $farm, mixed $cropArea, ?string $cropUnit): void
    {
        if ($farm?->area === null || $cropArea === null || $cropArea === '') {
            return;
        }

        $farmAreaInSquareMeters = $this->toSquareMeters((float) $farm->area, (string) $farm->area_unit);
        $cropAreaInSquareMeters = $this->toSquareMeters((float) $cropArea, (string) $cropUnit);

        if ($cropAreaInSquareMeters > $farmAreaInSquareMeters + 0.000001) {
            throw ValidationException::withMessages([
                'area_planted' => sprintf(
                    'The crop area (%s %s) cannot exceed this farm\'s registered area (%s %s).',
                    $this->format($cropArea),
                    $this->unitLabel((string) $cropUnit),
                    $this->format($farm->area),
                    $this->unitLabel((string) $farm->area_unit),
                ),
            ]);
        }
    }

    private function toSquareMeters(float $area, string $unit): float
    {
        return match (strtolower(trim($unit))) {
            'hectare', 'hectares', 'ha' => $area * self::SQUARE_METERS_PER_HECTARE,
            'square_meter', 'square_meters', 'square meter', 'square meters', 'sqm', 'm2' => $area,
            default => throw ValidationException::withMessages([
                'area_planted' => 'The farm or crop area has an unsupported measurement unit. Correct the registered farm area before continuing.',
            ]),
        };
    }

    private function unitLabel(string $unit): string
    {
        return match (strtolower(trim($unit))) {
            'hectare', 'hectares', 'ha' => 'hectares',
            'square_meter', 'square_meters', 'square meter', 'square meters', 'sqm', 'm2' => 'square meters',
            default => $unit,
        };
    }

    private function format(mixed $area): string
    {
        return rtrim(rtrim(number_format((float) $area, 2, '.', ''), '0'), '.');
    }
}
