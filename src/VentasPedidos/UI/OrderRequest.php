<?php

namespace Simon\VentasPedidos\UI;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Simon\InventarioTrazabilidad\Application\CatalogVariants;
use Simon\Shared\Application\AccessPolicy;
use Simon\VentasPedidos\Domain\OrderLines;
use Simon\VentasPedidos\Domain\OrderOrigin;

final class OrderRequest extends FormRequest
{
    public function authorize(AccessPolicy $access): bool
    {
        $id = $this->user()?->id;

        return $id !== null && ($access->allows($id, 'orders.create') || $access->allows($id, 'orders.manage'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'operation_id' => ['required', 'uuid'],
            'customer_id' => ['required', $this->integerInput(), 'integer', 'min:1'],
            'origin' => ['required', 'string', Rule::in(array_keys(OrderOrigin::labels()))],
            'delivery_address_id' => ['nullable', $this->integerInput(), 'integer', 'min:1'],
            'lines' => ['required', 'array', 'list', 'min:1', 'max:'.OrderLines::MAX_LINES],
            'lines.*' => ['required', 'array:variant_id,quantity'],
            'lines.*.variant_id' => ['required', $this->integerInput(), 'integer', 'min:1', 'distinct'],
            'lines.*.quantity' => ['required', $this->integerInput(), 'integer', 'min:1', 'max:'.OrderLines::MAX_QUANTITY],
        ];
    }

    /** @return list<Closure> */
    public function after(CatalogVariants $catalog): array
    {
        return [function (Validator $validator) use ($catalog): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lines = $this->input('lines');
            $missing = $catalog->missing(array_map(fn (array $line): int => (int) $line['variant_id'], $lines));

            foreach ($lines as $index => $line) {
                if (in_array((int) $line['variant_id'], $missing, true)) {
                    $validator->errors()->add('lines.'.$index.'.variant_id', 'El artículo de la línea '.($index + 1).' ya no está disponible en el catálogo.');
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'operation_id.required' => 'Falta la identificación del pedido. Iniciá una nota nueva.',
            'operation_id.uuid' => 'La identificación no es válida. Iniciá una nota nueva.',
            'customer_id.*' => 'Seleccioná un cliente válido.',
            'origin.*' => 'Seleccioná un origen válido: S, G o F.',
            'delivery_address_id.*' => 'Seleccioná una dirección válida.',
            'lines.required' => 'Agregá al menos un artículo al pedido.',
            'lines.array' => 'Revisá las líneas de artículos del pedido.',
            'lines.list' => 'Revisá el orden de las líneas del pedido.',
            'lines.min' => 'Agregá al menos un artículo al pedido.',
            'lines.max' => 'Una nota admite hasta 100 líneas de artículos.',
            'lines.*.required' => 'Completá la línea :position.',
            'lines.*.array' => 'La línea :position debe contener solamente artículo y cantidad.',
            'lines.*.variant_id.required' => 'Seleccioná un artículo en la línea :position.',
            'lines.*.variant_id.integer' => 'Seleccioná un artículo válido en la línea :position.',
            'lines.*.variant_id.min' => 'Seleccioná un artículo válido en la línea :position.',
            'lines.*.variant_id.distinct' => 'El artículo de la línea :position está repetido. Combiná sus cantidades en una sola línea.',
            'lines.*.quantity.required' => 'Ingresá la cantidad en la línea :position.',
            'lines.*.quantity.integer' => 'La cantidad de la línea :position debe ser un número entero.',
            'lines.*.quantity.min' => 'La cantidad de la línea :position debe ser mayor que cero.',
            'lines.*.quantity.max' => 'La cantidad de la línea :position no puede superar 1000000 pares.',
        ];
    }

    private function integerInput(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_int($value) && ! is_string($value)) {
                $fail('Ingresá un número entero, sin decimales ni valores lógicos.');
            }
        };
    }
}
