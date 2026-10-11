<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

class ResolveStudioLocalTime
{
    public function handle(string $local, string $timezone, ?string $offset = null, string $field = 'local_start'): CarbonImmutable
    {
        $wall = CarbonImmutable::createFromFormat('!Y-m-d H:i', $local, 'UTC');
        $zone = new DateTimeZone($timezone);
        $transitions = $zone->getTransitions($wall->getTimestamp() - 172800, $wall->getTimestamp() + 172800);
        $candidates = [];
        foreach (array_unique(array_column($transitions ?: [], 'offset')) as $seconds) {
            $candidate = $wall->subSeconds($seconds);
            $zoned = $candidate->setTimezone($zone);
            if ($zoned->format('Y-m-d H:i') === $local && ($offset === null || $zoned->format('P') === $offset)) {
                $candidates[] = $candidate;
            }
        }
        if (count($candidates) !== 1) {
            $field = $field === 'local_start' && $offset !== null ? 'utc_offset' : $field;
            throw ValidationException::withMessages([$field => "Vrijeme {$local} u zoni {$timezone} ne postoji ili je dvostruko. Ispravite vrijeme pojavljivanja ili izričito odaberite UTC pomak (+01:00 / +02:00)."]);
        }

        return $candidates[0];
    }
}
