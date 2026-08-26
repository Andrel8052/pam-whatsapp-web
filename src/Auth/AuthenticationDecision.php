<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Auth;

use JsonException;

final readonly class AuthenticationDecision
{
    public function __construct(
        public bool $failed = false,
        public bool $restart = false,
        public mixed $failureEventPayload = null,
    ) {
        if (!$failed && $restart) {
            throw new \InvalidArgumentException('Authentication restart requires a failed decision.');
        }
    }

    /** @param array{failed?: mixed, restart?: mixed, failureEventPayload?: mixed} $result */
    public static function fromResult(array $result): self
    {
        $failed = $result['failed'] ?? false;
        $restart = $result['restart'] ?? false;
        if (!is_bool($failed) || !is_bool($restart)) {
            throw new \UnexpectedValueException('Authentication decision flags must be boolean.');
        }

        return new self($failed, $restart, $result['failureEventPayload'] ?? null);
    }

    public function failureMessage(): string
    {
        if (is_string($this->failureEventPayload) && $this->failureEventPayload !== '') {
            return $this->failureEventPayload;
        }
        if (is_scalar($this->failureEventPayload)) {
            return (string) $this->failureEventPayload;
        }
        if ($this->failureEventPayload !== null) {
            try {
                return json_encode($this->failureEventPayload, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
            }
        }

        return 'Authentication failed.';
    }
}
