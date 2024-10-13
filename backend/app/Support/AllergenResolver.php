<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Maps free-text allergen mentions ("peanut", "milk", "nuts", "Gluten")
 * onto the canonical slugs stored in the `allergens` table. Customers and
 * the LLM rarely use our exact vocabulary, so this keeps filters forgiving.
 */
final class AllergenResolver
{
    /** @var array<string, list<string>> */
    private const SYNONYMS = [
        'peanut' => ['peanuts'],
        'peanuts' => ['peanuts'],
        'groundnut' => ['peanuts'],
        'arachide' => ['peanuts'],
        'nut' => ['tree-nuts', 'peanuts'],
        'nuts' => ['tree-nuts', 'peanuts'],
        'tree-nut' => ['tree-nuts'],
        'tree-nuts' => ['tree-nuts'],
        'almond' => ['tree-nuts'],
        'almonds' => ['tree-nuts'],
        'pistachio' => ['tree-nuts'],
        'hazelnut' => ['tree-nuts'],
        'walnut' => ['tree-nuts'],
        'gluten' => ['gluten'],
        'wheat' => ['gluten'],
        'semolina' => ['gluten'],
        'dairy' => ['dairy'],
        'milk' => ['dairy'],
        'lactose' => ['dairy'],
        'cheese' => ['dairy'],
        'egg' => ['eggs'],
        'eggs' => ['eggs'],
        'fish' => ['fish'],
        'tuna' => ['fish'],
        'shellfish' => ['crustaceans', 'molluscs'],
        'crustacean' => ['crustaceans'],
        'crustaceans' => ['crustaceans'],
        'shrimp' => ['crustaceans'],
        'prawn' => ['crustaceans'],
        'mollusc' => ['molluscs'],
        'molluscs' => ['molluscs'],
        'squid' => ['molluscs'],
        'sesame' => ['sesame'],
        'soy' => ['soy'],
        'soya' => ['soy'],
        'celery' => ['celery'],
        'mustard' => ['mustard'],
    ];

    /**
     * @param  string|array<int, string>|null  $input  comma separated string or list
     * @return list<string>
     */
    public static function resolve(string|array|null $input): array
    {
        if ($input === null || $input === '' || $input === []) {
            return [];
        }

        $terms = is_array($input) ? $input : explode(',', $input);
        $slugs = [];

        foreach ($terms as $term) {
            $key = Str::slug(trim((string) $term));

            if ($key === '') {
                continue;
            }

            foreach (self::SYNONYMS[$key] ?? [$key] as $slug) {
                $slugs[$slug] = true;
            }
        }

        return array_keys($slugs);
    }
}
