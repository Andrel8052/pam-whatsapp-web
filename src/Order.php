<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Support\Payload;

final readonly class Order
{
    /** @var list<Product> */
    public array $products;

    /** @param array<string, mixed> $payload */
    public function __construct(Session $session, array $payload)
    {
        $this->products = array_map(
            static fn (array $product): Product => Product::fromPayload($session, $product),
            Payload::objects($payload['products'] ?? [], 'Order products'),
        );
        $this->subtotal = Payload::string($payload, 'subtotal');
        $this->total = Payload::string($payload, 'total');
        $this->currency = Payload::string($payload, 'currency');
        $this->createdAt = Payload::int($payload, 'createdAt');
    }

    public string $subtotal;
    public string $total;
    public string $currency;
    public int $createdAt;
}
