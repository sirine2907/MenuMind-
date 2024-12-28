<?php

namespace Tests\Unit;

use App\Support\AllergenResolver;
use PHPUnit\Framework\TestCase;

class AllergenResolverTest extends TestCase
{
    public function test_it_maps_synonyms_to_canonical_slugs(): void
    {
        $this->assertSame(['peanuts'], AllergenResolver::resolve('peanut'));
        $this->assertSame(['dairy'], AllergenResolver::resolve(['Milk', 'lactose']));
        $this->assertSame(['tree-nuts', 'peanuts'], AllergenResolver::resolve('nuts'));
        $this->assertSame(['crustaceans', 'molluscs'], AllergenResolver::resolve('shellfish'));
    }

    public function test_it_accepts_comma_separated_input_and_deduplicates(): void
    {
        $this->assertSame(['gluten', 'peanuts'], AllergenResolver::resolve('wheat, gluten ,peanuts,'));
    }

    public function test_unknown_terms_pass_through_as_slugs(): void
    {
        $this->assertSame(['lupin'], AllergenResolver::resolve(['Lupin']));
        $this->assertSame([], AllergenResolver::resolve(null));
    }
}
