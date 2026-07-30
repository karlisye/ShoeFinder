<?php

namespace App\Domain\Catalogue\Colours;

use App\Models\FilterColour;

final class FilterColourSuggester
{
    private const TERMS = [
        'black' => ['black'],
        'white' => ['white', 'egret', 'chalk'],
        'grey' => ['grey', 'gray', 'anthracite', 'graphite', 'lunar rock'],
        'beige' => [
            'beige',
            'cream',
            'vanilla',
            'sail',
            'taupe',
            'oyster',
            'phantom',
            'orewood',
            'bone',
        ],
        'brown' => ['brown', 'gum'],
        'red' => ['red', 'burgundy'],
        'orange' => ['orange'],
        'yellow' => ['yellow'],
        'green' => ['green'],
        'blue' => ['blue', 'navy'],
        'purple' => ['purple', 'violet'],
        'pink' => ['pink', 'rose'],
        'silver' => ['silver', 'metallic'],
        'gold' => ['gold'],
        'multicolour' => [
            'multicolour',
            'multi-color',
            'multi colour',
            'rainbow',
        ],
    ];

    /**
     * @return array<int, string>
     */
    public function codesFor(string $value): array
    {
        $value = mb_strtolower($value);

        return array_keys(array_filter(
            self::TERMS,
            fn (array $needles): bool => collect($needles)
                ->contains(
                    fn (string $needle): bool => str_contains(
                        $value,
                        $needle,
                    ),
                ),
        ));
    }

    /**
     * @return array<int, int>
     */
    public function idsFor(string $value): array
    {
        $codes = $this->codesFor($value);

        if ($codes === []) {
            return [];
        }

        return FilterColour::query()
            ->where('active', true)
            ->whereIn('code', $codes)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();
    }
}
