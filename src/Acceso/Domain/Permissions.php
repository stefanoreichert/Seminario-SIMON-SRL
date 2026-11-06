<?php

namespace Simon\Acceso\Domain;

final class Permissions
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'access.manage' => 'Gestionar usuarios, roles y permisos',
            'clients.manage' => 'Gestionar clientes e historial crediticio',
            'catalog.manage' => 'Gestionar modelos, colores y talles',
            'orders.create' => 'Registrar y gestionar pedidos propios con identificador de vendedor',
            'orders.manage' => 'Registrar y gestionar pedidos de cualquier origen',
            'dispatch.authorize' => 'Validar facturación y crédito para despachos',
            'production.manage' => 'Registrar tandas en proceso',
            'inventory.receive' => 'Dar de alta productos terminados',
            'inventory.opening' => 'Cargar existencias iniciales',
            'inventory.dispatch' => 'Confirmar despachos y retiros',
            'inventory.view' => 'Consultar pedidos y existencias',
            'audit.view' => 'Consultar auditoría',
        ];
    }
}
