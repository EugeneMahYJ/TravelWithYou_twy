<?php

namespace App\Contracts;

interface PaymentGateway
{
    public function process(string $method, array $details): array;

    public function hostedFieldsView(): ?string;
}
