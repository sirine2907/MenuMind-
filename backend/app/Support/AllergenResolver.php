<?php

namespace App\Support;

use Illuminate\Support\Str;

// Turns what people type ("peanut", "milk", "nuts") into the allergen slugs we store.
class AllergenResolver
{
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

    // Accepts "peanuts,gluten" or ['peanuts', 'gluten'] and returns unique slugs
    public static function resolve($input): array
    {
        if (empty($input)) {
            return [];
        }

        $words = is_array($input) ? $input : explode(',', $input);
        $slugs = [];

        foreach ($words as $word) {
            $key = Str::slug(trim($word));

            if ($key === '') {
                continue;
            }

            foreach (self::SYNONYMS[$key] ?? [$key] as $slug) {
                if (! in_array($slug, $slugs)) {
                    $slugs[] = $slug;
                }
            }
        }

        return $slugs;
    }
}
