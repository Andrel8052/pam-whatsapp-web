<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class MembershipRequestActionOptions
{
    /**
     * @param string|list<string>|null $requesterIds
     * @param int|list<int>|null $sleep
     */
    public function __construct(public string|array|null $requesterIds = null, public int|array|null $sleep = [250, 500])
    {
    }

    /** @return array{requesterIds: string|list<string>|null, sleep: int|list<int>|null} */
    public function toBridge(): array
    {
        return ['requesterIds' => $this->requesterIds, 'sleep' => $this->sleep];
    }
}
