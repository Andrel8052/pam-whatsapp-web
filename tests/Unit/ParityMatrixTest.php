<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;

final class ParityMatrixTest extends TestCase
{
    public function testCertificationCommandAcceptsValidEvidenceAndRejectsFailedReports(): void
    {
        $root = dirname(__DIR__, 2);
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/bin/parity').' certify ';
        $output = [];
        $status = -1;
        exec($command.escapeshellarg($root.'/tests/Fixtures/certification-report.json').' --dry-run 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
        self::assertStringContainsString('Validated 1 certified matrix entries', implode("\n", $output));

        $output = [];
        exec($command.escapeshellarg($root.'/tests/Fixtures/failed-certification-report.json').' --dry-run 2>&1', $output, $status);
        self::assertSame(1, $status);
        self::assertStringContainsString('contains failed checks', implode("\n", $output));
    }

    /** @throws JsonException */
    public function testCertificationFixtureReferencesOnlyKnownMatrixEntries(): void
    {
        $root = dirname(__DIR__, 2);
        $matrix = json_decode((string) file_get_contents($root.'/api-matrix.json'), true, flags: JSON_THROW_ON_ERROR);
        $report = json_decode(
            (string) file_get_contents($root.'/tests/Fixtures/certification-report.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($matrix);
        self::assertIsArray($report);
        self::assertSame(2, $report['schemaVersion'] ?? null);
        self::assertSame($matrix['baseline']['version'] ?? null, $report['baseline']['version'] ?? null);
        self::assertSame($matrix['baseline']['commit'] ?? null, $report['baseline']['commit'] ?? null);

        $known = [];
        foreach ($matrix['symbols'] ?? [] as $symbol) {
            if (!is_array($symbol) || !is_string($symbol['id'] ?? null)) continue;
            $known['symbol:'.$symbol['id']] = true;
            foreach ($symbol['members'] ?? [] as $member) {
                if (!is_array($member) || !is_string($member['id'] ?? null)) continue;
                $known['member:'.$symbol['id'].':'.$member['id']] = true;
            }
        }
        foreach ($report['checks'] ?? [] as $check) {
            if (!is_array($check)) continue;
            foreach ($check['covers'] ?? [] as $entry) {
                self::assertIsString($entry);
                self::assertArrayHasKey($entry, $known);
            }
        }
        self::assertIsArray($matrix['certificationEvidence'] ?? null);
        self::assertCount(751, $matrix['certificationEvidence']);
        self::assertSame([
            [
                'reportSha256' => 'eaa0077b8d227c4874dc3644664548b4752a4ec63b6ffb4ce79233e664438a7b',
                'completedAt' => '2026-08-24T21:17:13+00:00',
                'whatsappWeb' => '1.34.7',
                'coveredEntries' => 426,
            ],
            [
                'reportSha256' => 'dbf1faaeb0d30bf35fbde9af2826e0ec7b4ef3c1d520b0284901a66a38881b7d',
                'completedAt' => '2026-08-25T22:30:37+00:00',
                'whatsappWeb' => '2.3000.1046002285',
                'coveredEntries' => 95,
            ],
            [
                'reportSha256' => '78ef93fc3c7a1dbd9267f569883f3c6a3c9f61d0b5080273251e2832d4b73608',
                'completedAt' => '2026-08-25T22:33:38+00:00',
                'whatsappWeb' => '2.3000.1046002285',
                'coveredEntries' => 181,
            ],
            [
                'reportSha256' => '6bd9eb410cac51767c8cc89f1259247587adc6eca142bcdce7bb581491280009',
                'completedAt' => '2026-08-25T22:49:28+00:00',
                'whatsappWeb' => '2.3000.1046002285',
                'coveredEntries' => 219,
            ],
            [
                'reportSha256' => 'f5cfda10af61811f0217820cd882b8a75721bb1c4c57ff9d6fd7ad016ce36198',
                'completedAt' => '2026-08-25T22:51:19+00:00',
                'whatsappWeb' => '2.3000.1046002285',
                'coveredEntries' => 208,
            ],
            [
                'reportSha256' => '57675d6eb53194e2a75e4ab08c16005fc2ba81efd49f768463719bcff5e7df91',
                'completedAt' => '2026-08-25T23:17:39+00:00',
                'whatsappWeb' => '2.3000.1046012414',
                'coveredEntries' => 189,
            ],
            [
                'reportSha256' => 'f5de2aefc21f3fcd09dd00b212ab1a6e4cb0fc4f5c89824ae83d554da4d5a077',
                'completedAt' => '2026-08-25T23:23:21+00:00',
                'whatsappWeb' => '1.34.7',
                'coveredEntries' => 525,
            ],
            [
                'reportSha256' => '8cb3436f8e21bfca78bf980cb15cafece18f5efb8ead04ed65e0ec52d925b810',
                'completedAt' => '2026-08-25T23:34:01+00:00',
                'whatsappWeb' => '1.34.7',
                'coveredEntries' => 534,
            ],
        ], $matrix['certifications'] ?? null);
    }

    /** @throws JsonException */
    public function testAuthenticatedCertificationReferencesOnlyKnownMatrixEntries(): void
    {
        $root = dirname(__DIR__, 2);
        $matrix = json_decode((string) file_get_contents($root.'/api-matrix.json'), true, flags: JSON_THROW_ON_ERROR);
        $scenario = (string) file_get_contents($root.'/tests/integration/authenticated.php');
        self::assertIsArray($matrix);

        $known = [];
        foreach ($matrix['symbols'] ?? [] as $symbol) {
            if (!is_array($symbol) || !is_string($symbol['id'] ?? null)) continue;
            $known['symbol:'.$symbol['id']] = true;
            foreach ($symbol['members'] ?? [] as $member) {
                if (!is_array($member) || !is_string($member['id'] ?? null)) continue;
                $known['member:'.$symbol['id'].':'.$member['id']] = true;
            }
        }

        preg_match_all('/member:[A-Za-z0-9_]+:[0-9]+:[A-Za-z0-9_]+/', $scenario, $matches);
        $references = array_values(array_unique($matches[0]));
        self::assertNotEmpty($references);
        foreach ($references as $reference) {
            self::assertArrayHasKey($reference, $known);
        }

        self::assertContains('member:Events:4:STATE_CHANGED', $references);
        self::assertContains('member:Events:4:LOADING_SCREEN', $references);
        self::assertContains('member:Events:4:GROUP_UPDATE', $references);
        self::assertContains('member:Events:4:MESSAGE_REACTION', $references);
        self::assertContains('member:Contact:1:getCountryCode', $references);
        self::assertContains('member:Contact:1:getFormattedNumber', $references);
        self::assertContains('member:Contact:1:getBroadcast', $references);
        self::assertContains('member:Client:1:on', $references);
        self::assertContains('member:Client:1:destroy', $references);
        self::assertContains('member:Chat:1:sendMessage', $references);
        self::assertContains('member:Chat:1:sendSeen', $references);
        self::assertContains('member:Message:1:reply', $references);
        self::assertContains('member:Message:1:pin', $references);
        self::assertContains('member:Message:1:unpin', $references);
        self::assertContains('member:LocalAuth:1:constructor', $references);
        self::assertContains('member:AuthStrategy:1:setup', $references);
        self::assertContains('member:AuthStrategy:1:beforeBrowserInitialized', $references);
        self::assertContains('member:AuthStrategy:1:afterBrowserInitialized', $references);
        self::assertContains('member:AuthStrategy:1:getAuthEventPayload', $references);
        self::assertContains('member:AuthStrategy:1:afterAuthReady', $references);
        self::assertContains('member:AuthStrategy:1:destroy', $references);
        self::assertContains('member:Client:1:archiveChat', $references);
        self::assertContains('member:Client:1:muteChat', $references);
        self::assertContains('member:Client:1:sendPresenceUnavailable', $references);
        self::assertContains('member:Chat:1:markUnread', $references);
        self::assertContains('member:Contact:1:block', $references);
        self::assertContains('member:Contact:1:unblock', $references);
        self::assertContains('member:GroupNotification:1:reply', $references);
        self::assertContains('member:GroupChat:2:promoteParticipants', $references);
        self::assertContains('member:GroupChat:2:demoteParticipants', $references);
        self::assertStringContainsString("certificationPropertyCoverage('LocalAuth')", $scenario);
        self::assertContains('member:RemoteAuth:1:constructor', $references);
        self::assertContains('member:AuthStrategy:1:logout', $references);
        self::assertContains('member:AuthStrategy:1:disconnect', $references);
        self::assertContains('member:Store:1:sessionExists', $references);
        self::assertContains('member:Store:1:extract', $references);
        self::assertContains('member:Store:1:save', $references);
        self::assertContains('member:Store:1:delete', $references);
        self::assertStringContainsString("certificationPropertyCoverage('RemoteAuth')", $scenario);
        self::assertStringContainsString("certificationPropertyCoverage('BusinessContact')", $scenario);
        self::assertStringContainsString("certificationPropertyCoverage('BusinessCategory')", $scenario);
        self::assertStringContainsString("certificationPropertyCoverage('BusinessHours')", $scenario);
        self::assertStringContainsString("certificationPropertyCoverage('BusinessHoursOfDay')", $scenario);
    }

    /** @throws JsonException */
    public function testDeterministicContractCertificationProducesPassingKnownCoverage(): void
    {
        $root = dirname(__DIR__, 2);
        $output = [];
        $status = -1;
        exec(
            escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/integration/contracts.php').' 2>&1',
            $output,
            $status,
        );
        self::assertSame(0, $status, implode("\n", $output));
        $report = json_decode(implode("\n", $output), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertSame(28, $report['summary']['passed'] ?? null);
        self::assertSame(0, $report['summary']['failed'] ?? null);
        self::assertSame(0, $report['summary']['skipped'] ?? null);
        $enumCheck = array_values(array_filter(
            $report['checks'] ?? [],
            static fn (mixed $check): bool => is_array($check) && ($check['name'] ?? null) === 'enums.public-contract',
        ));
        self::assertCount(1, $enumCheck);
        self::assertCount(105, $enumCheck[0]['covers'] ?? []);
        $symbolCheck = array_values(array_filter(
            $report['checks'] ?? [],
            static fn (mixed $check): bool => is_array($check) && ($check['name'] ?? null) === 'symbols.public-contract',
        ));
        self::assertCount(1, $symbolCheck);
        self::assertCount(81, $symbolCheck[0]['covers'] ?? []);

        $matrix = json_decode((string) file_get_contents($root.'/api-matrix.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($matrix);
        $known = [];
        foreach ($matrix['symbols'] ?? [] as $symbol) {
            if (!is_array($symbol) || !is_string($symbol['id'] ?? null)) continue;
            $known['symbol:'.$symbol['id']] = true;
            foreach ($symbol['members'] ?? [] as $member) {
                if (!is_array($member) || !is_string($member['id'] ?? null)) continue;
                $known['member:'.$symbol['id'].':'.$member['id']] = true;
            }
        }
        foreach ($report['checks'] ?? [] as $check) {
            if (!is_array($check)) continue;
            foreach ($check['covers'] ?? [] as $entry) {
                self::assertIsString($entry);
                self::assertArrayHasKey($entry, $known);
            }
        }
    }

    /** @throws JsonException */
    public function testDeterministicAndAuthenticatedCertificationCanCoverTheEntireMatrix(): void
    {
        $root = dirname(__DIR__, 2);
        $matrix = json_decode((string) file_get_contents($root.'/api-matrix.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($matrix);
        $known = [];
        $symbols = [];
        foreach ($matrix['symbols'] ?? [] as $symbol) {
            if (!is_array($symbol) || !is_string($symbol['id'] ?? null)) continue;
            $symbolId = $symbol['id'];
            $symbols[$symbolId] = $symbol;
            $known['symbol:'.$symbolId] = true;
            foreach ($symbol['members'] ?? [] as $member) {
                if (!is_array($member) || !is_string($member['id'] ?? null)) continue;
                $known['member:'.$symbolId.':'.$member['id']] = true;
            }
        }

        $output = [];
        $status = -1;
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tests/integration/contracts.php').' 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
        $report = json_decode(implode("\n", $output), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        $covered = [];
        foreach ($report['checks'] ?? [] as $check) {
            if (!is_array($check) || ($check['status'] ?? null) !== 1) continue;
            foreach ($check['covers'] ?? [] as $entry) {
                if (is_string($entry)) $covered[$entry] = true;
            }
        }
        self::assertCount(534, $covered);

        $authenticated = (string) file_get_contents($root.'/tests/integration/authenticated.php');
        preg_match_all('/member:[A-Za-z0-9_]+:[0-9]+:[A-Za-z0-9_]+/', $authenticated, $references);
        foreach ($references[0] as $entry) $covered[$entry] = true;
        preg_match_all("/certificationPropertyCoverage\\('([A-Za-z0-9_]+)'\\)/", $authenticated, $propertySymbols);
        foreach ($propertySymbols[1] as $symbolId) {
            $symbol = $symbols[$symbolId] ?? null;
            if (!is_array($symbol)) continue;
            foreach ($symbol['members'] ?? [] as $member) {
                if (!is_array($member) || ($member['kind'] ?? null) !== 2 || !is_string($member['id'] ?? null)) continue;
                $covered['member:'.$symbolId.':'.$member['id']] = true;
            }
        }

        self::assertCount(751, $known);
        self::assertSame([], array_keys(array_diff_key($known, $covered)));
    }

    /** @throws JsonException */
    public function testSchemaNineteenValidatesConstructorsPropertyUnionsAndPamBrowserEquivalents(): void
    {
        $matrix = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/api-matrix.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($matrix);
        self::assertSame(19, $matrix['schemaVersion'] ?? null);
        self::assertSame([
            '1' => 'unknown',
            '2' => 'void',
            '3' => 'boolean',
            '4' => 'number',
            '5' => 'string',
            '6' => 'array',
            '7' => 'nullable_boolean',
            '8' => 'nullable_number',
            '9' => 'nullable_string',
            '10' => 'nullable_array',
        ], $matrix['returnKinds'] ?? null);
        self::assertSame([
            '1' => 'unknown',
            '2' => 'boolean',
            '3' => 'number',
            '4' => 'string',
            '5' => 'array',
        ], $matrix['parameterKinds'] ?? null);

        $returns = [];
        $returnTypeNames = [];
        $returnCollectionElementTypeNames = [];
        $returnObjectShapes = [];
        $returnObjectTypeOverrides = [];
        $returnIndexSignatures = [];
        $propertyTypeNames = [];
        $propertyTypeOverrides = [];
        $propertyRuntimeNullable = [];
        $propertyCollections = [];
        $propertyObjectShapes = [];
        $propertyIndexSignatures = [];
        $propertyStructureTypeOverrides = [];
        $propertyUnions = [];
        $parameters = [];
        $parameterNames = [];
        $parameterTypeNames = [];
        $runtimeNullable = [];
        $returnTypeOverrides = [];
        $parameterOverrides = [];
        $parameterNameOverrides = [];
        $constructors = [];
        $constructorContractOverrides = [];
        foreach ($matrix['symbols'] ?? [] as $symbol) {
            if (!is_array($symbol)) continue;
            foreach ($symbol['members'] ?? [] as $member) {
                if (!is_array($member) || !is_string($member['phpMember'] ?? null)) continue;
                $returns[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']] = $member['returnKind'] ?? null;
                $returnTypeNames[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']] = $member['returnTypeName'] ?? null;
                $returnCollectionElementTypeNames[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']]
                    = $member['returnCollectionElementTypeName'] ?? null;
                $returnObjectShapes[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']]
                    = $member['returnObjectShape'] ?? null;
                if (($member['returnObjectTypeOverrides'] ?? []) !== []) {
                    $returnObjectTypeOverrides[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']]
                        = $member['returnObjectTypeOverrides'];
                }
                if (($member['returnIndexSignature'] ?? null) !== null) {
                    $returnIndexSignatures[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']]
                        = $member['returnIndexSignature'];
                }
                if (is_string($member['propertyTypeName'] ?? null)) {
                    $propertyTypeNames[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']]
                        = $member['propertyTypeName'];
                }
                if (($member['propertyTypeOverride'] ?? false) === true) {
                    $propertyTypeOverrides[] = ($symbol['phpSymbol'] ?? '').'::'.$member['phpMember'];
                }
                if (($member['propertyRuntimeNullable'] ?? false) === true) {
                    $propertyRuntimeNullable[] = ($symbol['phpSymbol'] ?? '').'::'.$member['phpMember'];
                }
                if (is_string($member['propertyCollectionElementTypeName'] ?? null)) {
                    $propertyCollections[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']]
                        = $member['propertyCollectionElementTypeName'];
                }
                if (is_array($member['propertyObjectShape'] ?? null)) {
                    $propertyObjectShapes[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']]
                        = $member['propertyObjectShape'];
                }
                if (is_array($member['propertyIndexSignature'] ?? null)) {
                    $propertyIndexSignatures[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']]
                        = $member['propertyIndexSignature'];
                }
                if (($member['propertyStructureTypeOverrides'] ?? []) !== []) {
                    $propertyStructureTypeOverrides[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']]
                        = $member['propertyStructureTypeOverrides'];
                }
                if (is_array($member['propertyUnionTypeNames'] ?? null)) {
                    $propertyUnions[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']]
                        = $member['propertyUnionTypeNames'];
                }
                $parameters[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']] = $member['parameterKinds'] ?? null;
                $parameterNames[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']] = $member['parameterNames'] ?? null;
                $parameterTypeNames[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']] = $member['parameterTypeNames'] ?? null;
                if (($member['runtimeNullable'] ?? false) === true) {
                    $runtimeNullable[] = ($symbol['phpSymbol'] ?? '').'::'.$member['phpMember'];
                }
                if (($member['returnTypeOverride'] ?? false) === true) {
                    $returnTypeOverrides[] = ($symbol['phpSymbol'] ?? '').'::'.$member['phpMember'];
                }
                if (($member['parameterTypeOverrides'] ?? []) !== []) {
                    $parameterOverrides[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']] = $member['parameterTypeOverrides'];
                }
                if (($member['parameterNameOverrides'] ?? []) !== []) {
                    $parameterNameOverrides[($symbol['phpSymbol'] ?? '').'::'.$member['phpMember']] = $member['parameterNameOverrides'];
                }
                if (($member['name'] ?? null) === 'constructor') {
                    $constructors[] = ($symbol['phpSymbol'] ?? '').'::'.$member['phpMember'];
                    if (($member['constructorContractOverride'] ?? false) === true) {
                        $constructorContractOverrides[] = ($symbol['phpSymbol'] ?? '').'::'.$member['phpMember'];
                    }
                }
            }
        }

        self::assertSame(2, $returns['Pam\\WhatsApp\\Call::reject'] ?? null);
        self::assertSame('Message', $returnTypeNames['Pam\\WhatsApp\\Message::reply'] ?? null);
        self::assertSame(
            'Chat',
            $returnCollectionElementTypeNames['Pam\\WhatsApp\\Client::getChats'] ?? null,
        );
        self::assertSame(
            'MembershipRequestActionResult',
            $returnCollectionElementTypeNames['Pam\\WhatsApp\\GroupChat::approveGroupMembershipRequests'] ?? null,
        );
        self::assertSame([
            ['name' => 'isMuted', 'type' => 'boolean', 'optional' => false],
            ['name' => 'muteExpiration', 'type' => 'number', 'optional' => false],
        ], $returnObjectShapes['Pam\\WhatsApp\\Chat::mute'] ?? null);
        self::assertSame([
            ['name' => 'contact', 'type' => 'Contact', 'optional' => false],
            ['name' => 'role', 'type' => 'string', 'optional' => false],
        ], $returnObjectShapes['Pam\\WhatsApp\\Channel::getSubscribers'] ?? null);
        self::assertSame([
            'Pam\\WhatsApp\\Channel::getSubscribers' => ['role'],
            'Pam\\WhatsApp\\Chat::getCustomerNote' => ['type'],
            'Pam\\WhatsApp\\Client::getCustomerNote' => ['type'],
        ], $returnObjectTypeOverrides);
        self::assertSame([
            'Pam\\WhatsApp\\GroupChat::addParticipants' => [
                'keyType' => 'string',
                'valueType' => 'AddParticipantsResult',
            ],
        ], $returnIndexSignatures);
        self::assertSame('MessageTypes', $propertyTypeNames['Pam\\WhatsApp\\Message::type'] ?? null);
        self::assertCount(329, $propertyTypeNames);
        self::assertSame([
            'Pam\\WhatsApp\\BusinessHoursOfDay::mode',
            'Pam\\WhatsApp\\Contact::type',
            'Pam\\WhatsApp\\GroupMembershipRequest::requestMethod',
            'Pam\\WhatsApp\\Location::latitude',
            'Pam\\WhatsApp\\Location::longitude',
            'Pam\\WhatsApp\\Message::deviceType',
            'Pam\\WhatsApp\\Message::duration',
            'Pam\\WhatsApp\\Payment::paymentStatus',
            'Pam\\WhatsApp\\Payment::paymentTxnStatus',
            'Pam\\WhatsApp\\Reaction::ack',
            'Pam\\WhatsApp\\ScheduledEventSendOptions::callType',
            'Pam\\WhatsApp\\SearchChannelsOptions::view',
        ], $propertyTypeOverrides);
        self::assertSame([
            'Pam\\WhatsApp\\Chat::lastMessage',
            'Pam\\WhatsApp\\Client::info',
            'Pam\\WhatsApp\\Client::pupBrowser',
            'Pam\\WhatsApp\\Client::pupPage',
            'Pam\\WhatsApp\\ClientInfo::phone',
            'Pam\\WhatsApp\\Contact::sectionHeader',
            'Pam\\WhatsApp\\GroupChat::owner',
            'Pam\\WhatsApp\\Message::eventStartTime',
            'Pam\\WhatsApp\\Message::location',
            'Pam\\WhatsApp\\Message::orderId',
            'Pam\\WhatsApp\\Message::pollName',
            'Pam\\WhatsApp\\Payment::paymentNote',
        ], $propertyRuntimeNullable);
        self::assertCount(24, $propertyCollections);
        self::assertCount(13, $propertyObjectShapes);
        self::assertSame([
            'Pam\\WhatsApp\\CreateGroupResult::participants' => [
                'keyType' => 'string',
                'valueType' => '{ statusCode: number; message: string; isGroupCreator: boolean; isInviteV4Sent: boolean; }',
            ],
        ], $propertyIndexSignatures);
        self::assertSame([
            'Pam\\WhatsApp\\BusinessContact::businessProfile' => ['businessHours'],
            'Pam\\WhatsApp\\ScheduledEvent::eventSendOptions' => ['callType', 'messageSecret'],
        ], $propertyStructureTypeOverrides);
        self::assertCount(18, $propertyUnions);
        self::assertSame(
            ['string', 'MessageMedia'],
            $propertyUnions['Pam\\WhatsApp\\Buttons::body'] ?? null,
        );
        self::assertSame(
            ['Array<string>', 'string', 'null'],
            $propertyUnions['Pam\\WhatsApp\\MembershipRequestActionOptions::requesterIds'] ?? null,
        );
        self::assertSame(
            'puppeteer.PuppeteerNodeLaunchOptions & puppeteer.ConnectOptions',
            $propertyTypeNames['Pam\\WhatsApp\\ClientOptions::puppeteer'] ?? null,
        );
        self::assertSame(2, $returns['Pam\\WhatsApp\\Auth\\AuthStrategy::logout'] ?? null);
        self::assertSame(3, $returns['Pam\\WhatsApp\\Client::sendResponseToScheduledEvent'] ?? null);
        self::assertSame(6, $returns['Pam\\WhatsApp\\Label::getChats'] ?? null);
        self::assertSame(9, $returns['Pam\\WhatsApp\\Contact::getAbout'] ?? null);
        self::assertSame([4], $parameters['Pam\\WhatsApp\\Client::setStatus'] ?? null);
        self::assertSame([2], $parameters['Pam\\WhatsApp\\Client::setAutoDownloadAudio'] ?? null);
        self::assertSame([5], $parameters['Pam\\WhatsApp\\Client::getContactLidAndPhone'] ?? null);
        self::assertSame(['status'], $parameterNames['Pam\\WhatsApp\\Client::setStatus'] ?? null);
        self::assertSame(['string'], $parameterTypeNames['Pam\\WhatsApp\\Client::setStatus'] ?? null);
        self::assertSame(['InviteV4Data'], $parameterTypeNames['Pam\\WhatsApp\\Client::acceptGroupV4Invite'] ?? null);
        self::assertSame(
            ['response', 'eventMessageId'],
            $parameterNames['Pam\\WhatsApp\\Client::sendResponseToScheduledEvent'] ?? null,
        );
        sort($runtimeNullable);
        self::assertSame([
            'Pam\\WhatsApp\\Client::getProfilePicUrl',
            'Pam\\WhatsApp\\Contact::getProfilePicUrl',
            'Pam\\WhatsApp\\GroupChat::getInviteCode',
        ], $runtimeNullable);
        self::assertSame([
            'Pam\\WhatsApp\\Client::getChatById',
            'Pam\\WhatsApp\\Message::getChat',
        ], $returnTypeOverrides);
        self::assertSame([
            'Pam\\WhatsApp\\Buttons::__construct' => [1],
            'Pam\\WhatsApp\\Channel::sendMessage' => [1],
            'Pam\\WhatsApp\\Chat::sendMessage' => [1],
            'Pam\\WhatsApp\\Client::sendMessage' => [2],
            'Pam\\WhatsApp\\Client::sendResponseToScheduledEvent' => [2],
            'Pam\\WhatsApp\\Event\\GroupNotification::reply' => [1],
            'Pam\\WhatsApp\\Message::edit' => [1],
            'Pam\\WhatsApp\\Message::reply' => [1],
        ], $parameterOverrides);
        self::assertSame([
            'Pam\\WhatsApp\\Client::deleteAddressbookContact' => [1],
        ], $parameterNameOverrides);
        self::assertCount(9, $constructors);
        self::assertSame([], $constructorContractOverrides);
        self::assertSame([1], $parameterOverrides['Pam\\WhatsApp\\Buttons::__construct'] ?? null);
    }
}
