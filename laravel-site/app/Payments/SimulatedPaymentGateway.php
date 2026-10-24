<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;

final class SimulatedPaymentGateway implements PaymentGateway
{
    public function process(string $method, array $details): array
    {
        if ($method !== 'card') {
            return ['success' => true, 'status' => 'Pending payment'];
        }

        $number = preg_replace('/\D/', '', (string) ($details['card_number'] ?? ''));
        if ($number !== '' && str_ends_with($number, '0000')) {
            return ['success' => false, 'status' => 'Payment failed'];
        }

        return ['success' => true, 'status' => 'Paid'];
    }

    public function hostedFieldsView(): ?string
    {
        return null;
    }
}
