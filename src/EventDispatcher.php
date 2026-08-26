<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final class EventDispatcher
{
    /** @var array<int, list<callable>> */
    private array $listeners = [];

    public function on(EventType $type, callable $listener): void
    {
        $this->listeners[$type->value][] = $listener;
    }

    public function dispatch(EventType $type, object $event): void
    {
        foreach ($this->listeners[$type->value] ?? [] as $listener) {
            $listener($event);
        }
    }
}
