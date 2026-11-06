<?php

namespace Simon\VentasPedidos\UI;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Simon\ClientesAdministracion\Application\DeliveryAddresses;

final readonly class OrderFormData
{
    public function __construct(private DeliveryAddresses $addresses) {}

    /**
     * @param  array<string,mixed>|null  $order
     * @return array{revision:string,token:string,customer:string,address:string,origin:string,lines:list<array{variant_id:string,quantity:string}>,addresses:list<array{id:int,customer_id:int,label:string,street:string,locality:string,province:string,postal_code:?string,country:?string,references:?string,is_active:bool}>}
     */
    public function make(Request $request, string $newToken, ?array $order = null): array
    {
        $old = $request->session()->getOldInput();
        if ((string) ($old['form_order_id'] ?? '') !== (string) ($order['id'] ?? '')) {
            $old = [];
        }

        $defaults = $order === null ? [] : [
            'customer_id' => $order['customer_id'], 'delivery_address_id' => $order['delivery_address_id'],
            'origin' => $order['origin'], 'lines' => $order['lines'],
        ];
        $old = [...$defaults, ...$old];
        $token = $this->text($old['operation_id'] ?? '');
        $customer = $this->text($old['customer_id'] ?? '');
        $lines = isset($old['lines']) && is_array($old['lines']) && $old['lines'] !== [] ? array_values($old['lines']) : [['variant_id' => '', 'quantity' => '']];

        return [
            'revision' => $this->text($old['revision'] ?? $order['revision'] ?? ''),
            'token' => Str::isUuid($token) ? $token : $newToken,
            'customer' => $customer, 'address' => $this->text($old['delivery_address_id'] ?? ''),
            'origin' => $this->text($old['origin'] ?? ''),
            'lines' => array_map(fn (mixed $line): array => [
                'variant_id' => is_array($line) ? $this->text($line['variant_id'] ?? '') : '',
                'quantity' => is_array($line) ? $this->text($line['quantity'] ?? '') : '',
            ], $lines),
            'addresses' => ctype_digit($customer) && (int) $customer > 0 ? $this->addresses->forCustomer((int) $customer) : [],
        ];
    }

    private function text(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }
}
