<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Support\Payload;

final readonly class Payment
{
    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->id = Payload::string($payload, 'id');
        $this->paymentCurrency = Payload::string($payload, 'paymentCurrency');
        $this->paymentAmount1000 = Payload::int($payload, 'paymentAmount1000');
        $this->paymentMessageReceiverJid = Payload::string($payload, 'paymentMessageReceiverJid');
        $this->paymentTransactionTimestamp = Payload::int($payload, 'paymentTransactionTimestamp');
        $this->paymentStatus = PaymentStatus::from(Payload::int($payload, 'paymentStatus'));
        $this->paymentTxnStatus = PaymentTransactionStatus::from(Payload::int($payload, 'paymentTxnStatus'));
        $note = $payload['paymentNote'] ?? null;
        $this->paymentNote = is_string($note) ? $note : null;
    }

    public string $id;
    public string $paymentCurrency;
    public int $paymentAmount1000;
    public string $paymentMessageReceiverJid;
    public int $paymentTransactionTimestamp;
    public PaymentStatus $paymentStatus;
    public PaymentTransactionStatus $paymentTxnStatus;
    public ?string $paymentNote;
}
