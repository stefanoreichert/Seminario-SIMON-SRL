<?php

namespace Simon\VentasPedidos\Domain;

final class OrderFingerprint
{
    /** @param list<array{variant_id:int,quantity:int}> $lines */
    public static function make(int $customerId, array $lines): string
    {
        $items = (new OrderLines($lines))->items;
        usort($items, fn (array $left, array $right): int => $left['variant_id'] <=> $right['variant_id']);
        $items = array_map(fn (array $line): array => [$line['variant_id'], $line['quantity']], $items);

        return hash('sha256', json_encode([$customerId, $items], JSON_THROW_ON_ERROR));
    }
}
