<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->customer = User::factory()->create();
    }

    public function test_guests_cannot_place_orders(): void
    {
        $this->postJson('/api/orders', ['items' => []])->assertUnauthorized();
    }

    public function test_a_customer_places_an_order_and_the_total_is_computed_server_side(): void
    {
        $couscous = Dish::factory()->for($this->restaurant)->priced(24)->create();
        $brik = Dish::factory()->for($this->restaurant)->priced(6.5)->create();
        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['dish_id' => $couscous->id, 'quantity' => 2],
                ['dish_id' => $brik->id, 'quantity' => 1],
                ['dish_id' => $brik->id, 'quantity' => 2],
            ],
            'notes' => 'Ring twice',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', 67.5)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.1.quantity', 3)
            ->assertJsonPath('data.items.1.subtotal', 19.5);

        $this->assertDatabaseHas('orders', ['user_id' => $this->customer->id, 'notes' => 'Ring twice']);
    }

    public function test_unavailable_dishes_cannot_be_ordered(): void
    {
        $dish = Dish::factory()->for($this->restaurant)->unavailable()->create(['name' => 'Strawberry Juice']);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/orders', ['items' => [['dish_id' => $dish->id, 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'dish_unavailable')
            ->assertJsonPath('error.details.dishes', ['Strawberry Juice']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unknown_dishes_are_rejected(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/orders', ['items' => [['dish_id' => 4242, 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'dish_not_found')
            ->assertJsonPath('error.details.dish_ids', [4242]);
    }

    public function test_order_payload_is_validated(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/orders', ['items' => [['dish_id' => 'abc', 'quantity' => 0]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('items.0.dish_id', 'error.details')
            ->assertJsonValidationErrorFor('items.0.quantity', 'error.details');
    }

    public function test_customers_only_see_their_own_orders(): void
    {
        $mine = Order::factory()->for($this->customer)->for($this->restaurant)->create();
        $theirs = Order::factory()->for($this->restaurant)->create();
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/orders/{$mine->id}")->assertOk();
        $this->getJson("/api/orders/{$theirs->id}")->assertForbidden();
    }

    public function test_admins_see_every_order_with_customer_details(): void
    {
        Order::factory()->count(3)->for($this->restaurant)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['customer' => ['id', 'name', 'email']]]]);
    }

    public function test_admins_move_orders_through_valid_status_transitions_only(): void
    {
        $order = Order::factory()->for($this->restaurant)->create(['status' => OrderStatus::Pending]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.status_label', 'Confirmed');

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_status_transition')
            ->assertJsonPath('error.details.allowed', ['preparing', 'cancelled']);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'teleported'])
            ->assertUnprocessable();
    }

    public function test_customers_cannot_change_order_status(): void
    {
        $order = Order::factory()->for($this->customer)->for($this->restaurant)->create();
        Sanctum::actingAs($this->customer);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'delivered'])->assertForbidden();
    }
}
