<?php

namespace App;

use InvalidArgumentException;

class DesignRegistry
{
    private const array DESIGNS = [
        'lotus' => [
            'label' => 'Lotus',
            'pages' => ['home' => 'sites/lotus/Home', 'about' => 'sites/lotus/About', 'schedule' => 'sites/lotus/Schedule', 'portal' => 'sites/lotus/Portal'],
        ],
        'balance' => [
            'label' => 'Balance',
            'pages' => ['home' => 'sites/balance/Home', 'about' => 'sites/balance/About', 'schedule' => 'sites/balance/Schedule', 'portal' => 'sites/balance/Portal'],
        ],
    ];

    /** @return array<string, string> */
    public function options(): array
    {
        return array_map(fn (array $design): string => $design['label'], self::DESIGNS);
    }

    public function component(string $design, string $page): string
    {
        return self::DESIGNS[$design]['pages'][$page]
            ?? throw new InvalidArgumentException('Unregistered public studio design or page.');
    }
}
