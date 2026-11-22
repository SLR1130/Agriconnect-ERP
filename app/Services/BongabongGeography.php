<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class BongabongGeography
{
    private ?array $feature = null;

    public function barangays(): array
    {
        return [
            'Anilao', 'Aplaya', 'Bagumbayan I', 'Bagumbayan II', 'Batangan', 'Bukal',
            'Camantigue', 'Carmundo', 'Cawayan', 'Dayhagan', 'Formon', 'Hagan', 'Hagupit',
            'Ipil', 'Kaligtasan', 'Labasan', 'Labonan', 'Libertad', 'Lisap', 'Luna',
            'Malitbog', 'Mapang', 'Masaguisi', 'Mina de Oro', 'Morente', 'Ogbot',
            'Orconuma', 'Polusahi', 'Poblacion', 'Sagana', 'San Isidro', 'San Jose',
            'San Juan', 'Santa Cruz', 'Sigange', 'Tawas',
        ];
    }

    /** @return list<array{latitude: float, longitude: float}> */
    public function boundaryForMap(): array
    {
        return array_map(fn (array $position): array => [
            'latitude' => (float) $position[1],
            'longitude' => (float) $position[0],
        ], $this->ring());
    }

    public function containsPoint(float $latitude, float $longitude): bool
    {
        $ring = $this->ring();
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$longitudeI, $latitudeI] = $ring[$i];
            [$longitudeJ, $latitudeJ] = $ring[$j];
            $crossesLatitude = ($latitudeI > $latitude) !== ($latitudeJ > $latitude);
            $intersectionLongitude = (($longitudeJ - $longitudeI) * ($latitude - $latitudeI) / (($latitudeJ - $latitudeI) ?: 1e-12)) + $longitudeI;

            if ($crossesLatitude && $longitude < $intersectionLongitude) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** @param list<array{latitude: mixed, longitude: mixed}> $vertices */
    public function containsFarmOutline(array $vertices): bool
    {
        $count = count($vertices);
        if ($count < 3 || $count > 80) {
            return false;
        }

        $points = [];
        foreach ($vertices as $vertex) {
            if (! is_array($vertex)
                || ! isset($vertex['latitude'], $vertex['longitude'])
                || ! is_numeric($vertex['latitude'])
                || ! is_numeric($vertex['longitude'])) {
                return false;
            }

            $point = [(float) $vertex['longitude'], (float) $vertex['latitude']];
            if (! $this->containsPoint($point[1], $point[0])) {
                return false;
            }
            $points[] = $point;
        }

        if (abs($this->signedArea($points)) < 1e-10 || $this->hasSelfIntersection($points)) {
            return false;
        }

        foreach ($points as $index => $point) {
            $next = $points[($index + 1) % $count];
            foreach ([0.25, 0.5, 0.75] as $fraction) {
                $longitude = $point[0] + (($next[0] - $point[0]) * $fraction);
                $latitude = $point[1] + (($next[1] - $point[1]) * $fraction);
                if (! $this->containsPoint($latitude, $longitude)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function hasSelfIntersection(array $points): bool
    {
        $count = count($points);
        for ($first = 0; $first < $count; $first++) {
            $firstNext = ($first + 1) % $count;
            for ($second = $first + 1; $second < $count; $second++) {
                $secondNext = ($second + 1) % $count;
                if ($first === $second || $firstNext === $second || $secondNext === $first) {
                    continue;
                }
                if ($this->segmentsIntersect($points[$first], $points[$firstNext], $points[$second], $points[$secondNext])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function segmentsIntersect(array $a, array $b, array $c, array $d): bool
    {
        $orientation = fn (array $p, array $q, array $r): float => (($q[1] - $p[1]) * ($r[0] - $q[0])) - (($q[0] - $p[0]) * ($r[1] - $q[1]));
        $onSegment = fn (array $p, array $q, array $r): bool => min($p[0], $r[0]) <= $q[0] && $q[0] <= max($p[0], $r[0])
            && min($p[1], $r[1]) <= $q[1] && $q[1] <= max($p[1], $r[1]);

        $o1 = $orientation($a, $b, $c);
        $o2 = $orientation($a, $b, $d);
        $o3 = $orientation($c, $d, $a);
        $o4 = $orientation($c, $d, $b);

        if ((($o1 > 0 && $o2 < 0) || ($o1 < 0 && $o2 > 0))
            && (($o3 > 0 && $o4 < 0) || ($o3 < 0 && $o4 > 0))) {
            return true;
        }

        return ($o1 === 0.0 && $onSegment($a, $c, $b))
            || ($o2 === 0.0 && $onSegment($a, $d, $b))
            || ($o3 === 0.0 && $onSegment($c, $a, $d))
            || ($o4 === 0.0 && $onSegment($c, $b, $d));
    }

    private function signedArea(array $points): float
    {
        $sum = 0.0;
        $count = count($points);
        for ($index = 0; $index < $count; $index++) {
            $next = ($index + 1) % $count;
            $sum += ($points[$index][0] * $points[$next][1]) - ($points[$next][0] * $points[$index][1]);
        }

        return $sum / 2;
    }

    private function ring(): array
    {
        return $this->feature()['geometry']['coordinates'][0];
    }

    private function feature(): array
    {
        if ($this->feature === null) {
            $path = resource_path('data/bongabong-municipality-2020.geojson');
            $document = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
            $this->feature = $document['features'][0];
        }

        return $this->feature;
    }
}
