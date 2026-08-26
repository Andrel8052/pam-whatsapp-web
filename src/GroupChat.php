<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use DateTimeImmutable;
use DateTimeZone;
use Pam\WhatsApp\Contract\Session;
use Pam\WhatsApp\Support\Payload;

final class GroupChat extends Chat
{
    public readonly ?ContactId $owner;
    public readonly DateTimeImmutable $createdAt;
    public private(set) string $description;

    /** @var list<GroupParticipant> */
    public readonly array $participants;

    /** @param array<string, mixed> $payload */
    public function __construct(Session $session, array $payload)
    {
        parent::__construct($session, $payload);
        $metadata = Payload::object($payload['groupMetadata'] ?? [], 'Group metadata');
        $owner = $metadata['owner'] ?? null;
        $this->owner = $owner === null ? null : ContactId::fromPayload(Payload::object($owner, 'Group owner'));
        $this->createdAt = (new DateTimeImmutable('@'.Payload::int($metadata, 'creation')))
            ->setTimezone(new DateTimeZone('UTC'));
        $this->description = Payload::string($metadata, 'desc');
        $this->participants = array_map(
            static fn (array $participant): GroupParticipant => new GroupParticipant($participant),
            Payload::objects($metadata['participants'] ?? [], 'Group participants'),
        );
    }

    /**
     * @param list<string> $participantIds
     * @return array<string, AddParticipantResult>|string
     */
    public function addParticipants(array $participantIds, ?AddParticipantsOptions $options = null): array|string
    {
        $value = $this->session->invoke('addGroupParticipants', [
            $this->id->serialized,
            $participantIds,
            ($options ?? new AddParticipantsOptions())->toBridge(),
        ]);
        if (is_string($value)) {
            return $value;
        }
        $results = [];
        foreach (Payload::object($value, 'Add-participants result') as $participantId => $result) {
            $results[$participantId] = AddParticipantResult::fromPayload(
                Payload::object($result, 'Participant result'),
            );
        }

        return $results;
    }

    /** @param list<string> $participantIds */
    public function removeParticipants(array $participantIds): ParticipantActionResult
    {
        return $this->participantAction(GroupParticipantAction::Remove, $participantIds);
    }

    /** @param list<string> $participantIds */
    public function promoteParticipants(array $participantIds): ParticipantActionResult
    {
        return $this->participantAction(GroupParticipantAction::Promote, $participantIds);
    }

    /** @param list<string> $participantIds */
    public function demoteParticipants(array $participantIds): ParticipantActionResult
    {
        return $this->participantAction(GroupParticipantAction::Demote, $participantIds);
    }

    public function setSubject(string $subject): bool
    {
        $success = $this->session->invoke('setGroupSubject', [$this->id->serialized, $subject]) === true;
        if ($success) {
            $this->name = $subject;
        }

        return $success;
    }

    public function setDescription(string $description): bool
    {
        $success = $this->session->invoke('setGroupDescription', [$this->id->serialized, $description]) === true;
        if ($success) {
            $this->description = $description;
        }

        return $success;
    }

    public function setAddMembersAdminsOnly(bool $adminsOnly = true): bool
    {
        return $this->setGroupSetting(GroupSetting::AddMembers, $adminsOnly);
    }

    public function setMessagesAdminsOnly(bool $adminsOnly = true): bool
    {
        return $this->setGroupSetting(GroupSetting::Messages, $adminsOnly);
    }

    public function setInfoAdminsOnly(bool $adminsOnly = true): bool
    {
        return $this->setGroupSetting(GroupSetting::Info, $adminsOnly);
    }

    public function deletePicture(): bool
    {
        return $this->session->invoke('deleteGroupPicture', [$this->id->serialized]) === true;
    }

    public function setPicture(MessageMedia $media): bool
    {
        return $this->session->invoke('setGroupPicture', [$this->id->serialized, $media->toBridge()['media']]) === true;
    }

    public function getInviteCode(): ?string
    {
        $value = $this->session->invoke('getGroupInviteCode', [$this->id->serialized]);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function revokeInvite(): void
    {
        $this->session->invoke('revokeGroupInvite', [$this->id->serialized]);
    }

    public function leave(): void
    {
        $this->session->invoke('leaveGroup', [$this->id->serialized]);
    }

    /** @return list<GroupMembershipRequest> */
    public function getGroupMembershipRequests(): array
    {
        return array_map(
            static fn (array $payload): GroupMembershipRequest => new GroupMembershipRequest($payload),
            Payload::objects(
                $this->session->invoke('getGroupMembershipRequests', [$this->id->serialized]),
                'Group membership requests',
            ),
        );
    }

    /** @return list<MembershipRequestActionResult> */
    public function approveGroupMembershipRequests(?MembershipRequestActionOptions $options = null): array
    {
        return $this->membershipRequestAction(MembershipRequestAction::Approve, $options);
    }

    /** @return list<MembershipRequestActionResult> */
    public function rejectGroupMembershipRequests(?MembershipRequestActionOptions $options = null): array
    {
        return $this->membershipRequestAction(MembershipRequestAction::Reject, $options);
    }

    /** @param list<string> $participantIds */
    private function participantAction(GroupParticipantAction $action, array $participantIds): ParticipantActionResult
    {
        $result = Payload::object(
            $this->session->invoke('modifyGroupParticipants', [$this->id->serialized, $action->value, $participantIds]),
            'Participant action result',
        );

        return new ParticipantActionResult(Payload::int($result, 'status'));
    }

    private function setGroupSetting(GroupSetting $setting, bool $adminsOnly): bool
    {
        return $this->session->invoke('setGroupSetting', [$this->id->serialized, $setting->value, $adminsOnly]) === true;
    }

    /** @return list<MembershipRequestActionResult> */
    private function membershipRequestAction(
        MembershipRequestAction $action,
        ?MembershipRequestActionOptions $options,
    ): array {
        return array_map(
            static fn (array $payload): MembershipRequestActionResult => MembershipRequestActionResult::fromPayload($payload),
            Payload::objects($this->session->invoke('membershipRequestAction', [
                $this->id->serialized,
                $action->value,
                ($options ?? new MembershipRequestActionOptions())->toBridge(),
            ]), 'Membership action results'),
        );
    }
}
