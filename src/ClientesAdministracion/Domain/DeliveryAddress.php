<?php

namespace Simon\ClientesAdministracion\Domain;

use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class DeliveryAddress
{
    public string $label;

    public string $street;

    public string $locality;

    public string $province;

    public ?string $postalCode;

    public ?string $country;

    public ?string $references;

    public function __construct(string $label, string $street, string $locality, string $province, ?string $postalCode = null, ?string $country = null, ?string $references = null)
    {
        foreach (['label' => [$label, 100], 'street' => [$street, 255], 'locality' => [$locality, 100], 'province' => [$province, 100]] as $field => [$value, $maximum]) {
            $value = trim($value);

            if ($value === '' || mb_strlen($value) > $maximum) {
                throw new BusinessRuleViolation('Completá etiqueta, domicilio, localidad y provincia dentro de los largos permitidos.');
            }

            $this->{$field} = $value;
        }

        foreach (['postalCode' => [$postalCode, 20], 'country' => [$country, 100], 'references' => [$references, 1000]] as $field => [$value, $maximum]) {
            $value = trim($value ?? '');

            if (mb_strlen($value) > $maximum) {
                throw new BusinessRuleViolation('El código postal, país o las referencias exceden el largo permitido.');
            }

            $this->{$field} = $value === '' ? null : $value;
        }
    }

    /** @return array{label:string,street:string,locality:string,province:string,postal_code:?string,country:?string,references:?string} */
    public function toArray(): array
    {
        return [
            'label' => $this->label, 'street' => $this->street, 'locality' => $this->locality, 'province' => $this->province,
            'postal_code' => $this->postalCode, 'country' => $this->country, 'references' => $this->references,
        ];
    }
}
