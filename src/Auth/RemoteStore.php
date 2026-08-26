<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Auth;

interface RemoteStore
{
    public function sessionExists(RemoteStoreOptions $options): bool;

    public function extract(RemoteStoreOptions $options): void;

    public function save(RemoteStoreOptions $options): void;

    public function delete(RemoteStoreOptions $options): void;
}
