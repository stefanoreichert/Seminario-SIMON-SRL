<?php

namespace Simon\ClientesAdministracion\Domain;

use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class CustomerProfile
{
    public string $name;

    public string $cuit;

    public ?string $phone;

    public ?string $email;

    public function __construct(string $name, string $cuit, ?string $phone = null, ?string $email = null)
    {
        $this->name = trim($name);
        $this->cuit = preg_replace('/[\s-]+/', '', $cuit);
        $this->phone = trim($phone ?? '') === '' ? null : trim($phone);
        $this->email = trim($email ?? '') === '' ? null : trim($email);

        if ($this->name === '' || mb_strlen($this->name) > 255 || $this->cuit === '' || mb_strlen($this->cuit) > 32) {
            throw new BusinessRuleViolation('El cliente debe tener nombre y CUIT único obligatorio, dentro de los largos permitidos.');
        }

        if ($this->phone !== null && mb_strlen($this->phone) > 50) {
            throw new BusinessRuleViolation('El teléfono debe tener como máximo 50 caracteres.');
        }

        if ($this->email !== null && (mb_strlen($this->email) > 255 || filter_var($this->email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new BusinessRuleViolation('Ingresá un correo electrónico válido.');
        }
    }
}
