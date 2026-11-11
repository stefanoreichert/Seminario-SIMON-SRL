<?php

namespace Simon\VentasPedidos\UI;

use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\PermissionDenied;
use Simon\VentasPedidos\Application\ManageOrders;
use Simon\VentasPedidos\Domain\OrderStatus;

trait AuthorizesOrderEditor
{
    private function authorizeEditor(): void
    {
        $user = auth()->user();
        abort_unless($user && (bool) $user->fresh()?->is_active, 403);
        $access = app(AccessPolicy::class);
        $manage = $access->allows($user->id, 'orders.manage');
        abort_unless($manage || $access->allows($user->id, 'orders.create'), 403);

        if ($this->orderId !== null) {
            try {
                $order = app(ManageOrders::class)->view($user->id, $this->orderId);
            } catch (PermissionDenied $exception) {
                abort(403, $exception->getMessage());
            }
            abort_unless(($manage || $order['seller_id'] === $user->id) && $order['status'] === OrderStatus::Draft->value, 403);
        }
    }
}
