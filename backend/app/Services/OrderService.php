<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\ApiException;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    // Which statuses an order can move to from its current status
    private const NEXT_STATUSES = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function listFor(User $user, ?string $status = null, int $perPage = 15)
    {
        $query = Order::with(['items', 'user'])->latest('id');

        // customers only see their own orders, admins see everything
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function place(User $user, Restaurant $restaurant, array $items, ?string $notes = null): Order
    {
        // if the same dish is sent twice we add the quantities together
        $quantities = [];
        foreach ($items as $item) {
            $dishId = (int) $item['dish_id'];
            $quantities[$dishId] = ($quantities[$dishId] ?? 0) + (int) $item['quantity'];
        }

        return DB::transaction(function () use ($user, $restaurant, $quantities, $notes) {
            $dishes = Dish::whereIn('id', array_keys($quantities))
                ->where('restaurant_id', $restaurant->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $missing = array_values(array_diff(array_keys($quantities), $dishes->keys()->all()));
            if (count($missing) > 0) {
                throw new ApiException('Some dishes do not exist on this menu.', 'dish_not_found', 422, ['dish_ids' => $missing]);
            }

            $unavailable = $dishes->where('available', false)->pluck('name')->values()->all();
            if (count($unavailable) > 0) {
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

            // the total is always calculated here, never trusted from the client
            $total = 0;
            foreach ($quantities as $dishId => $quantity) {
                $dish = $dishes[$dishId];
                $subtotal = round($dish->price * $quantity, 3);
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
        $allowed = self::NEXT_STATUSES[$order->status->value];

        if (! in_array($status->value, $allowed)) {
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
}
