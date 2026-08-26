<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Exception;

final class OperationException extends WhatsAppException
{
    public function __construct(
        public readonly string $operation,
        public readonly ?string $target,
        public readonly int $attempt,
        \Throwable $previous,
    ) {
        parent::__construct(sprintf(
            'WhatsApp operation %s%s failed on attempt %d: %s',
            $operation,
            $target === null ? '' : ' for '.$target,
            $attempt,
            $previous->getMessage(),
        ), previous: $previous);
    }
}
