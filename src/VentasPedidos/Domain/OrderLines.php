<?php

namespace Simon\VentasPedidos\Domain;

use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class OrderLines
{
    public const int MAX_LINES = 100;

    public const int MAX_QUANTITY = 1000000;

    /** @var list<array{variant_id:int,quantity:int}> */
    public array $items;

    /** @param list<array{variant_id:int,quantity:int}> $lines */
    public function __construct(array $lines)
    {
        if (! array_is_list($lines) || $lines === [] || count($lines) > self::MAX_LINES) {
            throw new BusinessRuleViolation('La nota debe contener entre 1 y 100 líneas de artículos.');
        }

        $seen = [];

        foreach ($lines as $index => $line) {
            $position = $index + 1;

            if (! is_array($line) || ! array_key_exists('variant_id', $line) || ! array_key_exists('quantity', $line)
                || array_diff(array_keys($line), ['variant_id', 'quantity']) !== []) {
                throw new BusinessRuleViolation('La línea '.$position.' debe contener solamente artículo y cantidad.');
            }

            if (! is_int($line['variant_id']) || $line['variant_id'] < 1) {
                throw new BusinessRuleViolation('Seleccioná un artículo válido en la línea '.$position.'.');
            }

            if (! is_int($line['quantity']) || $line['quantity'] < 1 || $line['quantity'] > self::MAX_QUANTITY) {
                throw new BusinessRuleViolation('La cantidad de la línea '.$position.' debe ser un entero entre 1 y 1000000.');
            }

            if (isset($seen[$line['variant_id']])) {
                throw new BusinessRuleViolation('El artículo de la línea '.$position.' está repetido. Combiná sus cantidades en una sola línea.');
            }

            $seen[$line['variant_id']] = true;
        }

        $this->items = $lines;
    }
}
