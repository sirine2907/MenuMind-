<?php

namespace Database\Seeders;

use App\Models\Allergen;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    private const ALLERGENS = [
        ['gluten', 'Gluten', '🌾'],
        ['crustaceans', 'Crustaceans', '🦐'],
        ['eggs', 'Eggs', '🥚'],
        ['fish', 'Fish', '🐟'],
        ['peanuts', 'Peanuts', '🥜'],
        ['soy', 'Soy', '🌱'],
        ['dairy', 'Dairy', '🥛'],
        ['tree-nuts', 'Tree nuts', '🌰'],
        ['celery', 'Celery', '🥬'],
        ['mustard', 'Mustard', '🟡'],
        ['sesame', 'Sesame', '⚪'],
        ['molluscs', 'Molluscs', '🦑'],
    ];

    private const CATEGORIES = [
        'starters' => 'Starters',
        'soups' => 'Soups',
        'mains' => 'Traditional Mains',
        'salads' => 'Salads',
        'pizzas' => 'Pizzas',
        'desserts' => 'Desserts',
        'drinks' => 'Drinks',
    ];

    /**
     * [category, name, description, price TND, vegetarian, vegan, spicy 0-3, allergens]
     */
    private const DISHES = [
        ['starters', "Brik à l'Œuf", 'Crispy malsouka pastry folded around a runny egg, tuna, capers and parsley. The Tunisian classic, eaten by hand.', 6.5, false, false, 0, ['gluten', 'eggs', 'fish']],
        ['starters', 'Potato & Cheese Brik', 'Golden malsouka pastry filled with mashed potato, onion, parsley and melted cheese.', 5.5, true, false, 0, ['gluten', 'dairy']],
        ['starters', 'Fricassé Tunisien', 'Small fried bread roll stuffed with tuna, potato, boiled egg, olives and a touch of harissa.', 4.5, false, false, 1, ['gluten', 'eggs', 'fish']],
        ['starters', 'Kafteji', 'Fried peppers, tomatoes, pumpkin and potatoes chopped together with a fried egg. A Tunis street-food favourite.', 9.0, true, false, 1, ['eggs']],
        ['starters', 'Houmous & Tabouna Bread', 'Chickpea and tahini dip with olive oil and cumin, served with warm wood-fired tabouna bread.', 7.5, true, true, 0, ['sesame', 'gluten']],
        ['starters', 'Ojja Légumes', 'Eggs poached in a spicy tomato and green pepper sauce with garlic, caraway and courgettes. Served with bread.', 11.5, true, false, 2, ['eggs', 'gluten']],

        ['soups', 'Lablabi', 'Hearty chickpea soup with cumin, garlic, harissa and olive oil, poured over torn bread and topped with a soft egg.', 6.0, true, false, 2, ['gluten', 'eggs']],
        ['soups', 'Chorba Frik', 'Lamb soup with cracked green wheat (frik), tomato, celery and fresh coriander. A Ramadan staple.', 7.5, false, false, 1, ['gluten', 'celery']],

        ['mains', 'Couscous à l’Agneau', 'Hand-rolled semolina couscous with slow-cooked lamb shoulder, chickpeas, carrots, pumpkin and turnips in a red broth.', 24.0, false, false, 1, ['gluten', 'celery']],
        ['mains', 'Couscous au Poisson (Sfax style)', 'Fine couscous steamed over a fragrant broth with grouper, potatoes and peppers, the way it is made in Sfax.', 28.0, false, false, 1, ['gluten', 'fish', 'celery']],
        ['mains', 'Couscous aux Sept Légumes', 'Vegan couscous with seven seasonal vegetables and chickpeas cooked in a vegetable broth, served with harissa on the side.', 17.5, true, true, 1, ['gluten', 'celery']],
        ['mains', 'Couscous Mesfouf', 'Sweet steamed couscous with dates, raisins and pomegranate, finished with butter and roasted peanuts.', 9.0, true, false, 0, ['gluten', 'dairy', 'peanuts']],
        ['mains', 'Mloukhia', 'Beef slow-cooked for six hours in a deep green sauce of jute leaf powder, olive oil and bay leaves. Served with bread.', 21.0, false, false, 0, ['gluten']],
        ['mains', 'Kamounia', 'Tender beef and liver stew with plenty of cumin and garlic, served with crusty bread.', 19.5, false, false, 1, []],
        ['mains', 'Ojja Merguez', 'Eggs poached in a fiery tomato and pepper sauce with grilled lamb merguez sausages.', 14.0, false, false, 2, ['eggs']],
        ['mains', 'Riz Djerbien', 'Djerba-style steamed rice with lamb, spinach, chickpeas, parsley and dried mint.', 18.0, false, false, 0, ['celery']],
        ['mains', 'Tajine Malsouka', 'Tunisian baked tajine of chicken, eggs and cheese wrapped in crisp malsouka sheets.', 16.5, false, false, 0, ['gluten', 'eggs', 'dairy']],
        ['mains', 'Grilled Catch of the Day', 'Whole fresh fish from the La Goulette market grilled over charcoal, with Slata Mechouia and fries.', 32.0, false, false, 0, ['fish']],
        ['mains', 'Seafood Spaghetti', 'Spaghetti with shrimp, squid and mussels in a spicy tomato and harissa sauce.', 26.0, false, false, 2, ['gluten', 'crustaceans', 'molluscs']],

        ['salads', 'Salade Tunisienne', 'Diced tomato, cucumber, onion and pepper with tuna, boiled egg, olives and olive oil.', 8.5, false, false, 0, ['fish', 'eggs']],
        ['salads', 'Slata Mechouia', 'Charcoal-grilled peppers, tomatoes and garlic crushed with olive oil and caraway, topped with a boiled egg.', 7.0, true, false, 2, ['eggs']],
        ['salads', 'Ommek Houria', 'Mashed spiced carrot salad with garlic, caraway, harissa and olives. Vegan and naturally gluten-free.', 6.5, true, true, 2, []],
        ['salads', 'Mediterranean Cheese Salad', 'Mixed greens, feta, black olives, cherry tomatoes and toasted walnuts with a lemon dressing.', 10.5, true, false, 0, ['dairy', 'tree-nuts', 'mustard']],

        ['pizzas', 'Pizza Margherita', 'Tomato sauce, mozzarella, basil and olive oil on a thin wood-fired crust.', 15.0, true, false, 0, ['gluten', 'dairy']],
        ['pizzas', 'Pizza Végétarienne', 'Tomato, mozzarella, roasted peppers, mushrooms, artichokes and olives.', 17.0, true, false, 0, ['gluten', 'dairy']],
        ['pizzas', 'Pizza Thon & Harissa', 'Tomato, mozzarella, tuna, olives, capers and a swirl of harissa.', 18.0, false, false, 1, ['gluten', 'dairy', 'fish']],
        ['pizzas', 'Pizza Merguez', 'Tomato, mozzarella, spicy merguez, onions and green peppers.', 20.0, false, false, 2, ['gluten', 'dairy']],

        ['desserts', 'Makroudh', 'Semolina diamonds filled with date paste, fried and soaked in orange-blossom honey syrup. From Kairouan.', 4.0, true, false, 0, ['gluten']],
        ['desserts', 'Baklawa Tunisienne', 'Layers of buttery pastry with almonds and pistachios in a rose-water syrup.', 6.5, true, false, 0, ['gluten', 'tree-nuts', 'dairy']],
        ['desserts', 'Mhalbia', 'Silky rice and milk pudding with rose geranium water, topped with crushed peanuts and pistachios.', 5.5, true, false, 0, ['dairy', 'peanuts', 'tree-nuts']],
        ['desserts', 'Bambalouni', 'Sidi Bou Said doughnut, fried to order and rolled in sugar. Best with mint tea.', 3.0, true, true, 0, ['gluten']],

        ['drinks', 'Mint Tea with Pine Nuts', 'Green tea brewed with fresh mint and sugar, served with toasted pine nuts.', 3.5, true, true, 0, ['tree-nuts']],
        ['drinks', 'Citronnade', 'Fresh lemonade blended with lemon zest and a hint of orange blossom.', 4.0, true, true, 0, []],
        ['drinks', 'Fresh Strawberry Juice', 'Seasonal strawberries blended with a little sugar and ice.', 5.0, true, true, 0, []],
        ['drinks', 'Café Turc', 'Strong Turkish-style coffee with orange blossom water.', 2.5, true, true, 0, []],
    ];

    public function run(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Dar El Menu',
            'slug' => 'dar-el-menu',
            'description' => 'Traditional Tunisian home cooking in La Marsa: couscous, brik, ojja and pastries made from family recipes.',
            'address' => '12 Rue Habib Bourguiba, La Marsa, Tunis',
            'phone' => '+216 71 740 123',
            'currency' => 'TND',
        ]);

        $allergens = collect(self::ALLERGENS)->mapWithKeys(fn (array $a) => [
            $a[0] => Allergen::create(['slug' => $a[0], 'name' => $a[1], 'icon' => $a[2]])->id,
        ]);

        $categories = collect(array_keys(self::CATEGORIES))->mapWithKeys(fn (string $slug, int $i) => [
            $slug => Category::create([
                'restaurant_id' => $restaurant->id,
                'slug' => $slug,
                'name' => self::CATEGORIES[$slug],
                'position' => $i,
            ])->id,
        ]);

        foreach (self::DISHES as [$category, $name, $description, $price, $vegetarian, $vegan, $spicy, $dishAllergens]) {
            $dish = Dish::create([
                'restaurant_id' => $restaurant->id,
                'category_id' => $categories[$category],
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $description,
                'price' => $price,
                'is_vegetarian' => $vegetarian,
                'is_vegan' => $vegan,
                'spicy_level' => $spicy,
                'available' => true,
            ]);

            $dish->allergens()->sync($allergens->only($dishAllergens)->values());
        }

        // One seasonal dish is off the menu, to show availability handling.
        Dish::where('slug', 'fresh-strawberry-juice')->update(['available' => false]);
    }
}
