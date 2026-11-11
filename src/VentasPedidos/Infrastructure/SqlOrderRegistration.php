<?php

namespace Simon\VentasPedidos\Infrastructure;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Simon\ClientesAdministracion\Application\DeliveryAddresses;
use Simon\InventarioTrazabilidad\Application\CatalogVariants;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Application\OrderQueries;
use Simon\VentasPedidos\Application\OrderRegistration;
use Simon\VentasPedidos\Application\OrderWorkflow;
use Simon\VentasPedidos\Domain\OrderStatus;

final class SqlOrderRegistration implements OrderQueries, OrderRegistration
{
    public function __construct(private readonly DeliveryAddresses $addresses, private readonly CatalogVariants $catalog, private readonly OrderWorkflow $workflow) {}

    public function register(string $operationId, int $customerId, int $sellerId, string $origin, array $lines, ?int $deliveryAddressId = null, bool $asDraft = false): int
    {
        $identity = [$customerId, $sellerId, $origin, $lines];

        if ($deliveryAddressId !== null) {
            $identity[] = $deliveryAddressId;
        }

        if ($asDraft) {
            $identity[] = OrderStatus::Draft->value;
        }

        $digest = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($operationId, $customerId, $sellerId, $origin, $lines, $digest, $deliveryAddressId, $asDraft): int {
                DB::table('inventory_audit_head')->where('id', 1)->increment('lock_revision');
                DB::table('access_assignment_lock')->where('id', 1)->increment('revision');
                $previous = DB::table('business_orders')->where('registration_id', $operationId)->first();

                if ($previous !== null) {
                    if ($previous->request_digest !== $digest) {
                        throw new BusinessRuleViolation('La identificación ya pertenece a otro pedido.');
                    }

                    return $previous->id;
                }

                $this->workflow->assertNoDuplicate($customerId, $lines);

                if ($this->catalog->missing(array_column($lines, 'variant_id')) !== []) {
                    throw new BusinessRuleViolation('Uno de los artículos ya no existe en el catálogo. Revisá todas las líneas antes de confirmar.');
                }

                $destination = $deliveryAddressId === null ? null : $this->addresses->snapshot($customerId, $deliveryAddressId);
                $orderId = DB::table('business_orders')->insertGetId([
                    'registration_id' => $operationId, 'request_digest' => $digest,
                    'customer_id' => $customerId, 'seller_id' => $sellerId, 'origin' => $origin,
                    'status' => ($asDraft ? OrderStatus::Draft : OrderStatus::Confirmed)->value, 'created_at' => now(),
                    'delivery_address_id' => $deliveryAddressId,
                    'delivery_destination' => $destination === null ? null : json_encode($destination, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]);
                DB::table('business_orders')->where('id', $orderId)->update(['number' => 'NP-'.str_pad((string) $orderId, 6, '0', STR_PAD_LEFT)]);

                foreach ($lines as $line) {
                    DB::table('business_order_lines')->insert(['order_id' => $orderId, ...$line, 'dispatched' => 0]);
                }

                return $orderId;
            }, 5);
        } catch (QueryException $exception) {
            throw new BusinessRuleViolation('No se pudo registrar el pedido. Revisá el cliente y los artículos.', previous: $exception);
        }
    }

    /** @return list<object> */
    public function orders(?int $sellerId = null, ?string $origin = null): array
    {
        return DB::table('business_orders')->when($sellerId !== null, fn ($query) => $query->where('seller_id', $sellerId))
            ->when($origin !== null, fn ($query) => $query->where('origin', $origin))
            ->orderByDesc('id')->limit(200)->get()->map(function (object $order): object {
                $order->delivery_destination = $order->delivery_destination === null ? null : json_decode($order->delivery_destination, true, flags: JSON_THROW_ON_ERROR);

                return $order;
            })->all();
    }

    public function authorizationPage(string $term, array $customerIds, int $page): array
    {
        $orders = DB::table('business_orders')->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Partial->value]);
        if ($term !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
            $orders->where(fn ($where) => $where->whereRaw("number LIKE ? ESCAPE '!'", [$pattern])->orWhereIntegerInRaw('customer_id', $customerIds));
        }
        $total = $orders->count();
        $pages = max(1, (int) ceil($total / 20));
        $page = min($page, $pages);

        return [
            'items' => $orders->select('id', 'number', 'customer_id', 'origin', 'status')->orderByDesc('id')->offset(($page - 1) * 20)->limit(20)->get()->all(),
            'total' => $total, 'page' => $page, 'pages' => $pages,
        ];
    }

    public function authorizationOrder(int $id): ?object
    {
        return DB::table('business_orders')->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Partial->value])
            ->where('id', $id)->first(['id', 'number', 'customer_id', 'origin', 'status']);
    }

    /** @param list<int> $orderIds
     * @return list<object>
     */
    public function lines(array $orderIds): array
    {
        return DB::table('business_order_lines')->join('business_orders', 'business_orders.id', '=', 'business_order_lines.order_id')
            ->whereIn('order_id', $orderIds)->select('business_order_lines.*', 'business_orders.customer_id', 'business_orders.delivery_destination', 'business_orders.status')
            ->get()->map(function (object $line): object {
                $line->delivery_destination = $line->delivery_destination === null ? null : json_decode($line->delivery_destination, true, flags: JSON_THROW_ON_ERROR);
                $line->pending = OrderStatus::from($line->status)->acceptsDispatch() ? $line->quantity - $line->dispatched : 0;

                return $line;
            })->all();
    }
}
