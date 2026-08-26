<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Contract;

use Pam\WhatsApp\ParticipantActionResult;

interface ChangeParticipantsPermissions
{
    /**
     * @param list<string> $participantIds
     * @return list<ParticipantActionResult>
     */
    public function __invoke(array $participantIds): array;
}
