<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\MessageContent;

final readonly class ContactList implements MessageContent
{
    /** @var list<string> */
    public array $contactIds;

    /** @param list<Contact|string> $contacts */
    public function __construct(array $contacts)
    {
        if ($contacts === []) {
            throw new \InvalidArgumentException('A contact list must contain at least one contact.');
        }
        $ids = array_map(
            static fn (Contact|string $contact): string => $contact instanceof Contact
                ? $contact->id->serialized
                : $contact,
            $contacts,
        );
        foreach ($ids as $id) {
            if ($id === '') {
                throw new \InvalidArgumentException('Contact ids must be non-empty.');
            }
        }
        $this->contactIds = $ids;
    }

    public function toBridge(): array
    {
        return ['kind' => ContentKind::ContactList->value, 'contactIds' => $this->contactIds];
    }
}
