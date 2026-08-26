<?php

declare(strict_types=1);

final readonly class MatrixCertification
{
    public function __construct(private string $matrixPath) {}

    /** @return array<string, true> */
    public function knownEntries(): array
    {
        $entries = [];
        foreach ($this->symbols() as $symbol) {
            if (!is_string($symbol['id'] ?? null)) continue;
            $entries['symbol:'.$symbol['id']] = true;
            $members = $symbol['members'] ?? null;
            if (!is_array($members)) continue;
            foreach ($members as $member) {
                if (!is_array($member) || !is_string($member['id'] ?? null)) continue;
                $entries['member:'.$symbol['id'].':'.$member['id']] = true;
            }
        }

        return $entries;
    }

    /** @return list<string> */
    public function propertyCoverage(string $symbolId): array
    {
        return array_values($this->propertyContract($symbolId)['properties']);
    }

    public function assertProperties(object $value, string $symbolId): void
    {
        $contract = $this->propertyContract($symbolId);
        $phpSymbol = $contract['phpSymbol'];
        if (!$value instanceof $phpSymbol) {
            throw new RuntimeException("Value does not implement {$phpSymbol} for {$symbolId}.");
        }
        $reflection = new ReflectionObject($value);
        $observedTypes = [];
        foreach ($contract['properties'] as $phpProperty => $entry) {
            if (!$reflection->hasProperty($phpProperty)) {
                throw new RuntimeException("Value lacks {$entry}.");
            }
            $property = $reflection->getProperty($phpProperty);
            if (!$property->isPublic() || !$property->isInitialized($value)) {
                throw new RuntimeException("Property is not publicly initialized: {$entry}.");
            }
            $observedTypes[$entry] = get_debug_type($property->getValue($value));
        }
        if (count($observedTypes) !== count($contract['properties'])) {
            throw new RuntimeException("Property audit was incomplete for {$symbolId}.");
        }
    }

    /** @return list<string> */
    public function enumCoverage(): array
    {
        $coverage = [];
        foreach ($this->symbols() as $symbol) {
            if (!is_string($symbol['id'] ?? null)) continue;
            $members = $symbol['members'] ?? null;
            if (!is_array($members)) continue;
            foreach ($members as $member) {
                if (!is_array($member) || ($member['kind'] ?? null) !== 4 || !is_string($member['id'] ?? null)) {
                    continue;
                }
                $coverage[] = 'member:'.$symbol['id'].':'.$member['id'];
            }
        }

        return $coverage;
    }

    /** @return list<string> */
    public function symbolCoverage(): array
    {
        $coverage = [];
        foreach ($this->symbols() as $symbol) {
            if (is_string($symbol['id'] ?? null)) $coverage[] = 'symbol:'.$symbol['id'];
        }

        return $coverage;
    }

    /** @return list<string> */
    public function incompleteMemberCoverage(): array
    {
        return [
            'member:Broadcast:1:getChat',
            'member:Broadcast:1:getContact',
            'member:Broadcast:2:id',
            'member:Broadcast:2:msgs',
            'member:Broadcast:2:timestamp',
            'member:Broadcast:2:totalCount',
            'member:Broadcast:2:unreadCount',
            'member:Channel:1:fetchMessages',
            'member:Channel:1:getSubscribers',
            'member:Channel:1:mute',
            'member:Channel:1:sendMessage',
            'member:Channel:1:sendSeen',
            'member:Channel:1:setDescription',
            'member:Channel:1:setSubject',
            'member:Channel:1:unmute',
            'member:Channel:2:description',
            'member:Channel:2:id',
            'member:Channel:2:isChannel',
            'member:Channel:2:isGroup',
            'member:Channel:2:isMuted',
            'member:Channel:2:isReadOnly',
            'member:Channel:2:lastMessage',
            'member:Channel:2:muteExpiration',
            'member:Channel:2:name',
            'member:Channel:2:timestamp',
            'member:Channel:2:unreadCount',
            'member:Chat:1:archive',
            'member:Chat:1:markUnread',
            'member:Chat:1:mute',
            'member:Chat:1:pin',
            'member:Chat:1:sendStateRecording',
            'member:Chat:1:syncHistory',
            'member:Chat:1:unarchive',
            'member:Chat:1:unmute',
            'member:Chat:1:unpin',
            'member:Client:1:archiveChat',
            'member:Client:1:getBroadcastById',
            'member:Client:1:getGroupMembershipRequests',
            'member:Client:1:getInviteInfo',
            'member:Client:1:markChatUnread',
            'member:Client:1:muteChat',
            'member:Client:1:pinChat',
            'member:Client:1:sendPresenceAvailable',
            'member:Client:1:sendPresenceUnavailable',
            'member:Client:1:sendSeen',
            'member:Client:1:syncHistory',
            'member:Client:1:unarchiveChat',
            'member:Client:1:unmuteChat',
            'member:Client:1:unpinChat',
            'member:Client:3:chat_archived',
            'member:Client:3:group_update',
            'member:Contact:1:block',
            'member:Contact:1:unblock',
            'member:GroupChat:1:getGroupMembershipRequests',
            'member:GroupChat:1:getInviteCode',
            'member:GroupChat:1:setDescription',
            'member:GroupChat:1:setSubject',
            'member:GroupChat:2:createdAt',
            'member:GroupChat:2:demoteParticipants',
            'member:GroupChat:2:description',
            'member:GroupChat:2:owner',
            'member:GroupChat:2:participants',
            'member:GroupChat:2:promoteParticipants',
            'member:GroupNotification:1:getChat',
            'member:GroupNotification:1:getContact',
            'member:GroupNotification:1:getRecipients',
            'member:GroupNotification:1:reply',
            'member:GroupNotification:2:author',
            'member:GroupNotification:2:body',
            'member:GroupNotification:2:chatId',
            'member:GroupNotification:2:id',
            'member:GroupNotification:2:recipientIds',
            'member:GroupNotification:2:timestamp',
            'member:GroupNotification:2:type',
            'member:Message:1:getPollVotes',
            'member:Order:2:createdAt',
            'member:Order:2:currency',
            'member:Order:2:products',
            'member:Order:2:subtotal',
            'member:Order:2:total',
            'member:Payment:2:id',
            'member:Payment:2:paymentAmount1000',
            'member:Payment:2:paymentCurrency',
            'member:Payment:2:paymentMessageReceiverJid',
            'member:Payment:2:paymentNote',
            'member:Payment:2:paymentStatus',
            'member:Payment:2:paymentTransactionTimestamp',
            'member:Payment:2:paymentTxnStatus',
            'member:PollVote:2:interractedAtTs',
            'member:PollVote:2:parentMessage',
            'member:PollVote:2:selectedOptions',
            'member:PollVote:2:voter',
            'member:Product:1:getData',
            'member:Product:2:currency',
            'member:Product:2:id',
            'member:Product:2:name',
            'member:Product:2:price',
            'member:Product:2:quantity',
            'member:Product:2:thumbnailUrl',
        ];
    }

    public function assertSymbolContracts(): void
    {
        foreach ($this->symbols() as $symbol) {
            $symbolId = $symbol['id'] ?? null;
            $phpSymbol = $symbol['phpSymbol'] ?? null;
            if (!is_string($symbolId) || !is_string($phpSymbol) || $phpSymbol === '') {
                throw new RuntimeException('Matrix symbol mapping is invalid.');
            }
            if (!class_exists($phpSymbol) && !interface_exists($phpSymbol) && !enum_exists($phpSymbol)) {
                throw new RuntimeException("Mapped PHP symbol is unavailable: {$symbolId} => {$phpSymbol}");
            }
            $reflection = new ReflectionClass($phpSymbol);
            if ($reflection->getName() !== $phpSymbol) {
                throw new RuntimeException("Mapped PHP symbol resolves ambiguously: {$symbolId} => {$phpSymbol}");
            }
        }
    }

    public function assertEnumContracts(): void
    {
        foreach ($this->symbols() as $symbol) {
            $members = $symbol['members'] ?? null;
            if (!is_array($members)) continue;
            $enumMembers = array_values(array_filter(
                $members,
                static fn (mixed $member): bool => is_array($member) && ($member['kind'] ?? null) === 4,
            ));
            if ($enumMembers === []) continue;
            $symbolId = $symbol['id'] ?? null;
            $phpSymbol = $symbol['phpSymbol'] ?? null;
            if (!is_string($symbolId) || !is_string($phpSymbol) || !enum_exists($phpSymbol)) {
                throw new RuntimeException('Mapped enum symbol is unavailable.');
            }
            $reflection = new ReflectionEnum($phpSymbol);
            if (!$reflection->isBacked() || $reflection->getBackingType()?->getName() !== 'int') {
                throw new RuntimeException("Mapped enum is not integer-backed: {$symbolId}");
            }
            $values = [];
            foreach ($reflection->getCases() as $case) {
                if (!$case instanceof ReflectionEnumBackedCase || !is_int($case->getBackingValue())) {
                    throw new RuntimeException("Mapped enum case is not integer-backed: {$symbolId}::{$case->getName()}");
                }
                $values[] = $case->getBackingValue();
            }
            if ($values !== range(1, count($values))) {
                throw new RuntimeException("Mapped enum values are not sequential from one: {$symbolId}");
            }
            foreach ($enumMembers as $member) {
                $phpMember = $member['phpMember'] ?? null;
                if (!is_string($phpMember)) {
                    throw new RuntimeException("Mapped enum case name is invalid: {$symbolId}");
                }
                if (!$reflection->hasCase($phpMember)) {
                    throw new RuntimeException("Mapped enum case is unavailable: {$symbolId}::{$phpMember}");
                }
            }
        }
    }

    /** @return array{phpSymbol: class-string, properties: array<string, string>} */
    private function propertyContract(string $symbolId): array
    {
        foreach ($this->symbols() as $symbol) {
            if (($symbol['id'] ?? null) !== $symbolId) continue;
            $phpSymbol = $symbol['phpSymbol'] ?? null;
            if (!is_string($phpSymbol) || (!class_exists($phpSymbol) && !interface_exists($phpSymbol))) {
                throw new RuntimeException("Mapped PHP symbol is unavailable: {$symbolId}");
            }
            $reflection = new ReflectionClass($phpSymbol);
            $properties = [];
            $members = $symbol['members'] ?? null;
            if (!is_array($members)) {
                throw new RuntimeException("Matrix members are invalid: {$symbolId}");
            }
            foreach ($members as $member) {
                if (!is_array($member)
                    || ($member['kind'] ?? null) !== 2
                    || !is_string($member['id'] ?? null)
                    || !is_string($member['phpMember'] ?? null)
                ) continue;
                $phpMember = $member['phpMember'];
                if (!$reflection->hasProperty($phpMember) || !$reflection->getProperty($phpMember)->isPublic()) continue;
                $properties[$phpMember] = 'member:'.$symbolId.':'.$member['id'];
            }

            return ['phpSymbol' => $phpSymbol, 'properties' => $properties];
        }

        throw new RuntimeException("Unknown API matrix symbol: {$symbolId}");
    }

    /** @return list<array<string, mixed>> */
    private function symbols(): array
    {
        $payload = file_get_contents($this->matrixPath);
        if (!is_string($payload)) throw new RuntimeException('Unable to read API matrix.');
        $matrix = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($matrix) || !is_array($matrix['symbols'] ?? null)) {
            throw new RuntimeException('API matrix symbols are invalid.');
        }

        $symbols = [];
        foreach ($matrix['symbols'] as $symbol) {
            if (!is_array($symbol)) continue;
            $normalized = [];
            foreach ($symbol as $key => $value) {
                if (is_string($key)) $normalized[$key] = $value;
            }
            $symbols[] = $normalized;
        }

        return $symbols;
    }
}
