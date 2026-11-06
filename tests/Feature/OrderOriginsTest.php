<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Simon\Acceso\Application\ManageAccess;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;
use Simon\VentasPedidos\Application\ListOrders;
use Simon\VentasPedidos\Application\RegisterOrder;
use Tests\TestCase;

class OrderOriginsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, User> */
    private array $sellers;

    private int $customerId;

    private int $variantId;

    private int $nextQuantity = 7;

    /** @var array<string,int> */
    private array $quantitiesByToken = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();

        foreach (['S', 'G', 'F'] as $origin) {
            $user = User::factory()->create(['sales_identifier' => $origin]);
            $this->grant($user, ['orders.create']);
            $this->sellers[$origin] = $user;
        }

        $this->customerId = app(ManageCustomers::class)->register($this->admin->id, 'Cliente de orígenes', '20-12345678-9', 'Crédito privado');
        $this->variantId = app(PrepareInventory::class)->variant($this->admin->id, 'ORIGIN-01', 'Botín', 'Negro', '40');
    }

    /** @param list<string> $permissions */
    private function grant(User $user, array $permissions): void
    {
        $roleId = app(ManageAccess::class)->saveRole($this->admin->id, null, 'Rol de origen '.$user->id, $permissions);
        DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId]);
    }

    /** @return array{operation_id:string,customer_id:int,origin:string,lines:list<array{variant_id:int,quantity:int}>} */
    private function requestData(string $origin): array
    {
        return [
            'operation_id' => (string) Str::uuid(), 'customer_id' => $this->customerId, 'origin' => $origin,
            'lines' => [['variant_id' => $this->variantId, 'quantity' => $this->nextQuantity++]],
        ];
    }

    private function order(User $actor, string $origin, ?string $token = null): int
    {
        $token ??= (string) Str::uuid();
        $quantity = $this->quantitiesByToken[$token] ??= $this->nextQuantity++;

        return app(RegisterOrder::class)->handle($actor->id, $token, $this->customerId, $origin, [['variant_id' => $this->variantId, 'quantity' => $quantity]]);
    }

    public function test_each_origin_is_registered_and_retrieved_with_its_correct_label(): void
    {
        $ids = [];

        foreach (['S' => 'S · Sandra', 'G' => 'G · Gustavo', 'F' => 'F · Fábrica'] as $origin => $label) {
            $this->actingAs($this->sellers[$origin])->from(route('operations.orders'))
                ->post(route('operations.order'), $this->requestData($origin))->assertSessionHasNoErrors();
            $id = DB::table('business_orders')->where('origin', $origin)->value('id');
            $ids[$origin] = $id;
            $this->assertDatabaseHas('business_orders', ['id' => $id, 'origin' => $origin, 'seller_id' => $this->sellers[$origin]->id]);
            $result = app(ListOrders::class)->handle($this->admin->id, $origin);
            $this->assertSame([$id], array_column($result['orders'], 'id'));
            $this->assertSame($origin, $result['orders'][0]->origin);
            $this->actingAs($this->admin)->get(route('operations.orders', ['origin' => $origin]))->assertOk()
                ->assertViewHas('originFilter', $origin)->assertViewHas('originLabels', fn (array $labels): bool => $labels[$origin] === $label)
                ->assertSee('Origen seleccionado: '.$label)->assertSee('fi-main-sidebar', false)->assertSee('fi-badge', false);
        }

        $this->assertCount(3, $ids);
        $this->get(route('operations.orders'))->assertOk()->assertViewHas('orders', fn (array $orders): bool => count($orders) === 3);
    }

    public function test_filter_selects_orders_and_their_lines_without_mixing_other_origins(): void
    {
        $ids = [];

        foreach ($this->sellers as $origin => $seller) {
            $ids[$origin] = $this->order($seller, $origin);
        }

        $this->actingAs($this->admin);

        foreach ($ids as $origin => $id) {
            $response = $this->get(route('operations.orders', ['origin' => $origin]))->assertOk()
                ->assertViewHas('orders', fn (array $orders): bool => array_column($orders, 'id') === [$id])
                ->assertViewHas('lines', fn (array $lines): bool => array_unique(array_column($lines, 'order_id')) === [$id]);

            foreach ($ids as $otherOrigin => $otherId) {
                if ($otherOrigin !== $origin) {
                    $response->assertDontSee(DB::table('business_orders')->where('id', $otherId)->value('number'));
                }
            }
        }

        $this->get(route('operations.orders', ['origin' => '']))->assertOk()->assertViewHas('originFilter', null)
            ->assertViewHas('orders', fn (array $orders): bool => count($orders) === 3);
    }

    public function test_a_filter_never_expands_the_sellers_access_even_for_the_same_origin(): void
    {
        $ownId = $this->order($this->sellers['S'], 'S');
        $otherSameOrigin = $this->order($this->admin, 'S');
        $otherOrigin = $this->order($this->sellers['G'], 'G');
        $query = app(ListOrders::class);

        foreach ([null, 'S', 'G', 'F'] as $origin) {
            $result = $query->handle($this->sellers['S']->id, $origin);
            $expected = $origin === null || $origin === 'S' ? [$ownId] : [];
            $this->assertSame($expected, array_column($result['orders'], 'id'));
            $this->assertSame($expected, array_values(array_unique(array_column($result['lines'], 'order_id'))));
        }

        $response = $this->actingAs($this->sellers['S'])->get(route('operations.orders', ['origin' => 'G', 'seller_id' => $this->admin->id]))
            ->assertOk()->assertViewHas('orders', [])->assertSee('No hay notas de pedido para esta consulta.');
        $response->assertDontSee(DB::table('business_orders')->where('id', $otherSameOrigin)->value('number'));
        $response->assertDontSee(DB::table('business_orders')->where('id', $otherOrigin)->value('number'));
        $this->assertDatabaseCount('business_orders', 3);
    }

    public function test_each_seller_cannot_impersonate_another_origin_through_http_or_direct_calls(): void
    {
        foreach (['S' => 'G', 'G' => 'F', 'F' => 'S'] as $origin => $forgedOrigin) {
            $this->actingAs($this->sellers[$origin])->from(route('operations.orders'))->post(route('operations.order'), [
                ...$this->requestData($forgedOrigin), 'seller_id' => $this->sellers[$forgedOrigin]->id,
            ])->assertSessionHasErrors('operation');

            try {
                $this->order($this->sellers[$origin], $forgedOrigin);
                $this->fail('Un vendedor no puede registrar un pedido con otro origen.');
            } catch (BusinessRuleViolation) {
                $this->assertDatabaseCount('business_orders', 0);
                $this->assertDatabaseCount('business_order_lines', 0);
            }
        }
    }

    public function test_registration_origin_options_follow_the_identifier_and_management_permission(): void
    {
        foreach ($this->sellers as $origin => $seller) {
            $response = $this->actingAs($seller)->get(route('operations.orders'))->assertOk();
            $xpath = $this->xpath($response->getContent());
            $options = $xpath->query('//form[@data-order-form]//select[@name="origin"]/option');
            $this->assertSame(1, $options->length);
            $this->assertSame($origin, $options->item(0)->getAttribute('value'));
        }

        $response = $this->actingAs($this->admin)->get(route('operations.orders'))->assertOk();
        $this->assertSame(3, $this->xpath($response->getContent())->query('//form[@data-order-form]//select[@name="origin"]/option[@value!=""]')->length);

        foreach (['S', 'G', 'F'] as $origin) {
            $id = $this->order($this->admin, $origin);
            $this->assertDatabaseHas('business_orders', ['id' => $id, 'seller_id' => $this->admin->id, 'origin' => $origin]);
        }
    }

    public function test_invalid_origins_and_filter_shapes_are_rejected_without_writes(): void
    {
        $this->actingAs($this->admin);

        foreach (['X', 's', 'S,G', 'S OR 1=1', ['S']] as $origin) {
            $this->getJson(route('operations.orders', ['origin' => $origin]))->assertUnprocessable()->assertJsonValidationErrors('origin');
            $this->getJson(route('operations.snapshot', ['section' => 'orders', 'origin' => $origin]))->assertUnprocessable()->assertJsonValidationErrors('origin');
            $this->postJson(route('operations.order'), [...$this->requestData('S'), 'origin' => $origin])->assertUnprocessable()->assertJsonValidationErrors('origin');
        }

        foreach (['X', 's', ''] as $origin) {
            foreach ([fn () => $this->order($this->admin, $origin), fn () => app(ListOrders::class)->handle($this->admin->id, $origin)] as $operation) {
                try {
                    $operation();
                    $this->fail('La regla de orígenes también se exige sin HTTP.');
                } catch (BusinessRuleViolation) {
                    $this->assertDatabaseCount('business_orders', 0);
                }
            }
        }

        $this->assertDatabaseCount('business_order_lines', 0);
    }

    public function test_refresh_url_keeps_the_applied_origin_and_returns_only_that_origin(): void
    {
        $this->order($this->sellers['S'], 'S');
        $id = $this->order($this->sellers['G'], 'G');
        $response = $this->actingAs($this->admin)->get(route('operations.orders', ['origin' => 'G']))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('G', $xpath->query('//select[@name="origin"]/option[@selected]')->item(0)->getAttribute('value'));
        $refreshUrl = $xpath->query('//*[@data-refresh-url]')->item(0)->getAttribute('data-refresh-url');
        parse_str(parse_url($refreshUrl, PHP_URL_QUERY), $query);
        $this->assertSame('orders', $query['section']);
        $this->assertSame('G', $query['origin']);
        $this->get($refreshUrl)->assertOk()->assertSee('G · Gustavo')->assertDontSee('<form', false)
            ->assertViewHas('orders', fn (array $orders): bool => array_column($orders, 'id') === [$id]);
    }

    public function test_read_permissions_allow_filtering_but_never_allow_registration(): void
    {
        $id = $this->order($this->sellers['F'], 'F');

        foreach (['inventory.view', 'inventory.dispatch', 'dispatch.authorize'] as $permission) {
            $reader = User::factory()->create();
            $this->grant($reader, [$permission]);
            $result = app(ListOrders::class)->handle($reader->id, 'F');
            $this->assertSame([$id], array_column($result['orders'], 'id'));
            $this->actingAs($reader)->get(route('operations.orders', ['origin' => 'F']))->assertOk()->assertSee('F · Fábrica');
            $this->post(route('operations.order'), $this->requestData('F'))->assertForbidden();
        }

        $this->assertDatabaseCount('business_orders', 1);
    }

    public function test_a_name_does_not_grant_access_or_supply_a_missing_seller_identifier(): void
    {
        $user = User::factory()->create(['name' => 'Sandra']);
        $this->actingAs($user)->get(route('operations.orders', ['origin' => 'S']))->assertForbidden();

        try {
            app(ListOrders::class)->handle($user->id, 'S');
            $this->fail('El nombre no concede permiso de consulta.');
        } catch (PermissionDenied) {
            $this->assertDatabaseCount('business_orders', 0);
        }

        $this->grant($user, ['orders.create']);
        $this->post(route('operations.order'), $this->requestData('S'))->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('business_orders', 0);
    }

    public function test_retry_cannot_reclassify_a_confirmed_order(): void
    {
        $token = (string) Str::uuid();
        $id = $this->order($this->admin, 'S', $token);
        $this->assertSame($id, $this->order($this->admin, 'S', $token));

        try {
            $this->order($this->admin, 'G', $token);
            $this->fail('Reutilizar el token no cambia el origen histórico.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('business_orders', ['id' => $id, 'origin' => 'S']);
            $this->assertDatabaseCount('business_orders', 1);
            $this->assertDatabaseCount('business_order_lines', 1);
        }
    }

    public function test_origin_filter_runs_before_the_recent_order_limit(): void
    {
        $id = $this->order($this->sellers['S'], 'S');
        $rows = [];

        for ($index = 0; $index < 201; $index++) {
            $rows[] = [
                'registration_id' => (string) Str::uuid(), 'request_digest' => hash('sha256', 'fixture-'.$index),
                'number' => 'F-TEST-'.$index, 'customer_id' => $this->customerId, 'seller_id' => $this->admin->id,
                'origin' => 'F', 'status' => 'Confirmado', 'created_at' => '2026-01-01 08:00:00',
            ];
        }

        DB::table('business_orders')->insert($rows);
        $query = app(ListOrders::class);
        $this->assertCount(200, $query->handle($this->admin->id)['orders']);
        $filtered = $query->handle($this->admin->id, 'S');
        $this->assertSame([$id], array_column($filtered['orders'], 'id'));
        $this->assertSame([$id], array_column($filtered['lines'], 'order_id'));
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }
}
