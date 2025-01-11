<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\ApiException;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Statuses an order may move to from its current one.
     */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function listFor(User $user, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return Order::query()
            ->with(['items', 'user'])
            ->unless($user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @param  list<array{dish_id: int, quantity: int}>  $items
     */
    public function place(User $user, Restaurant $restaurant, array $items, ?string $notes = null): Order
    {
        $quantities = $this->mergeQuantities($items);

        return DB::transaction(function () use ($user, $restaurant, $quantities, $notes) {
            $dishes = Dish::query()
                ->whereIn('id', array_keys($quantities))
                ->where('restaurant_id', $restaurant->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $missing = array_values(array_diff(array_keys($quantities), $dishes->keys()->all()));
            if ($missing !== []) {
                throw new ApiException('Some dishes do not exist on this menu.', 'dish_not_found', 422, ['dish_ids' => $missing]);
            }

            $unavailable = $dishes->reject->available->pluck('name')->values()->all();
            if ($unavailable !== []) {
                throw new ApiException(
                    'Some dishes are currently unavailable: '.implode(', ', $unavailable).'.',
                    'dish_unavailable',
                    422,
                    ['dishes' => $unavailable],
                );
            }

            $order = Order::create([
                'user_id' => $user->id,
                'restaurant_id' => $restaurant->id,
                'status' => OrderStatus::Pending,
                'notes' => $notes,
            ]);

            $total = 0.0;

            foreach ($quantities as $dishId => $quantity) {
                $dish = $dishes[$dishId];
                $subtotal = round((float) $dish->price * $quantity, 3);
                $total += $subtotal;

                $order->items()->create([
                    'dish_id' => $dish->id,
                    'dish_name' => $dish->name,
                    'quantity' => $quantity,
                    'unit_price' => $dish->price,
                    'subtotal' => $subtotal,
                ]);
            }

            $order->update(['total' => round($total, 3)]);

            return $order->load('items');
        });
    }

    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        $allowed = self::TRANSITIONS[$order->status->value];

        if (! in_array($status->value, $allowed, true)) {
            throw new ApiException(
                "Cannot move an order from {$order->status->value} to {$status->value}.",
                'invalid_status_transition',
                409,
                ['allowed' => $allowed],
            );
        }

        $order->update(['status' => $status]);

        return $order->load('items');
    }

    /**
     * The same dish listed twice becomes one line with the summed quantity.
     *
     * @param  list<array{dish_id: int, quantity: int}>  $items
     * @return array<int, int>
     */
    private function mergeQuantities(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $id = (int) $item['dish_id'];
            $quantities[$id] = ($quantities[$id] ?? 0) + (int) $item['quantity'];
        }

        return $quantities;
    }
}
