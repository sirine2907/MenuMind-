<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(OrderService $orders): void
    {
        // Indexing happens in one pass afterwards (`menumind:reindex`), which is
        // much faster than one Typesense call per seeded row.
        config(['typesense.enabled' => false]);

        $admin = User::create([
            'name' => 'Leila Trabelsi',
            'email' => 'admin@menumind.tn',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);

        $demo = User::create([
            'name' => 'Yasmine Ben Ali',
            'email' => 'demo@menumind.tn',
            'password' => 'password',
            'role' => UserRole::Customer,
        ]);

        $others = collect([
            ['Karim Jaziri', 'karim@example.tn'],
            ['Amel Gharbi', 'amel@example.tn'],
        ])->map(fn ($u) => User::create(['name' => $u[0], 'email' => $u[1], 'password' => 'password', 'role' => UserRole::Customer]));

        $this->call([MenuSeeder::class, DocumentSeeder::class]);

        $restaurant = Restaurant::current();
        $dish = fn (string $slug) => Dish::where('slug', $slug)->value('id');

        $history = [
            [$demo, [['couscous-a-lagneau', 2], ['brik-a-loeuf', 2], ['citronnade', 2]], OrderStatus::Delivered, null],
            [$demo, [['lablabi', 1], ['makroudh', 3]], OrderStatus::Preparing, 'Extra harissa on the side please.'],
            [$others[0], [['pizza-merguez', 1], ['cafe-turc', 1]], OrderStatus::Confirmed, null],
            [$others[1], [['couscous-aux-sept-legumes', 2], ['ommek-houria', 1]], OrderStatus::Pending, 'Vegan, please no butter.'],
            [$others[0], [['grilled-catch-of-the-day', 1], ['salade-tunisienne', 1]], OrderStatus::Ready, null],
        ];

        foreach ($history as [$user, $items, $status, $notes]) {
            $order = $orders->place(
                $user,
                $restaurant,
                array_map(fn ($i) => ['dish_id' => $dish($i[0]), 'quantity' => $i[1]], $items),
                $notes,
            );
            $order->update(['status' => $status]);
        }

        $this->command?->info("Seeded {$restaurant->name}: demo login demo@menumind.tn / password, admin {$admin->email} / password");
    }
}
