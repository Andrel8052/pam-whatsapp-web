<?php

declare(strict_types=1);

namespace Pam\WhatsApp\Tests\Unit;

use Pam\WhatsApp\Bridge\BridgeEvent;
use Pam\WhatsApp\Bridge\BridgeScript;
use Pam\WhatsApp\EventType;
use Pam\WhatsApp\Exception\BridgeException;
use PHPUnit\Framework\TestCase;

final class BridgeContractTest extends TestCase
{
    public function testItDecodesIntegerBackedEvents(): void
    {
        $event = BridgeEvent::fromValue([
            'type' => EventType::QrCode->value,
            'payload' => ['code' => 'qr'],
        ]);

        self::assertSame(EventType::QrCode, $event->type);
        self::assertSame('qr', $event->payload['code']);
    }

    public function testItRejectsStringEventTypes(): void
    {
        $this->expectException(BridgeException::class);
        $this->expectExceptionMessage('integer type');

        BridgeEvent::fromValue(['type' => 'qr', 'payload' => []]);
    }

    public function testBridgeDefinesThePinnedVerticalSlice(): void
    {
        $source = BridgeScript::source();

        self::assertStringContainsString('QR_CODE: 1', $source);
        self::assertStringContainsString('MESSAGE_RECEIVED: 4', $source);
        self::assertStringContainsString("'openMessageMediaStream'", $source);
        self::assertStringContainsString("'readMessageMediaStream'", $source);
        self::assertStringContainsString("'closeMessageMediaStream'", $source);
        self::assertStringContainsString('WAWebSendMsgChatAction', $source);
        self::assertStringContainsString('WAWebSignalStoreApi', $source);
        self::assertStringContainsString("method === 'getMessageOrder'", $source);
        self::assertStringContainsString("method === 'getMessagePayment'", $source);
        self::assertStringContainsString("method === 'getMessageReactions'", $source);
        self::assertStringContainsString("method === 'getPollVotes'", $source);
        self::assertStringContainsString('WAWebPollsVotesSchema', $source);
        self::assertStringContainsString("method === 'getChannelSubscribers'", $source);
        self::assertStringContainsString('WAWebEditNewsletterMetadataAction', $source);
        self::assertStringContainsString('WAWebChangeNewsletterOwnerAction', $source);
        self::assertStringContainsString("method === 'requestPairingCode'", $source);
        self::assertStringContainsString('WAWebGenerateEventCallLink', $source);
        self::assertStringContainsString('WAWebSaveContactAction', $source);
        self::assertStringContainsString('scheduled_event_creation: 32', $source);
        self::assertStringContainsString('type: messageType(message.type)', $source);
        self::assertStringContainsString('latestEditMsgKey: messageIdData(message.latestEditMsgKey)', $source);
        self::assertStringContainsString('msgId: messageIdData(sender.parentMsgKey)', $source);
        self::assertStringContainsString("method === 'getChats'", $source);
        self::assertStringContainsString("Object.defineProperty(key, '_serialized'", $source);
        self::assertStringContainsString('model.lastMessage = messageData(model.lastMessage)', $source);
        self::assertStringContainsString('countryNames.countryCodesIso ?? countryNames', $source);
        self::assertStringContainsString("method === 'getChatLabels'", $source);
        self::assertStringContainsString("typeof gating?.smbNotesV1Enabled !== 'function'", $source);
        self::assertStringContainsString("if (!sent) throw new Error('Sent message was not found", $source);
    }

    public function testBridgeDefinesAndEmitsEveryInternalEventCode(): void
    {
        $source = BridgeScript::source();
        self::assertSame(1, preg_match('/const EVENT = Object\.freeze\(\{(.*?)\}\);/s', $source, $match));
        self::assertSame(
            count(EventType::cases()),
            preg_match_all('/^\s*([A-Z_]+):\s*(\d+),$/m', $match[1], $members, PREG_SET_ORDER),
        );
        self::assertCount(count(EventType::cases()), $members);
        self::assertSame(
            range(1, count(EventType::cases())),
            array_map(static fn (array $member): int => (int) $member[2], $members),
        );

        foreach ($members as $member) {
            $name = $member[1];
            $occurrences = substr_count($source, 'EVENT.'.$name);
            if ($name === 'REMOTE_SESSION_SAVED') {
                self::assertSame(0, $occurrences, 'Remote-session saves must originate in the PHP auth lifecycle.');
            } else {
                self::assertGreaterThan(0, $occurrences, "Bridge event {$name} has no emission path.");
            }
        }
    }

    public function testEveryPhpInvocationHasARealBridgeCapability(): void
    {
        $source = BridgeScript::source();
        self::assertSame(1, preg_match('/const allowed = new Set\(\[(.*?)\]\);/s', $source, $allowedMatch));
        preg_match_all("/'([A-Za-z][A-Za-z0-9]*)'/", $allowedMatch[1], $allowedMatches);
        $allowed = array_values(array_unique($allowedMatches[1]));
        sort($allowed);

        $invoked = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            dirname(__DIR__, 2).'/src',
            \FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') continue;
            $php = file_get_contents($file->getPathname());
            if (!is_string($php)) continue;
            preg_match_all("/->invoke\('([A-Za-z][A-Za-z0-9]*)'/", $php, $matches);
            array_push($invoked, ...$matches[1]);
        }
        $invoked = array_values(array_unique($invoked));
        sort($invoked);
        self::assertSame($invoked, $allowed, 'PHP invocations and the bridge allowlist diverged.');

        preg_match_all("/method === '([A-Za-z][A-Za-z0-9]*)'/", $source, $branchMatches);
        $direct = array_fill_keys($branchMatches[1], true);
        self::assertSame(1, preg_match('/const aliases = Object\.freeze\(\{(.*?)\}\);/s', $source, $aliasMatch));
        preg_match_all("/([A-Za-z][A-Za-z0-9]*): '([A-Za-z][A-Za-z0-9]*)'/", $aliasMatch[1], $aliasMatches, PREG_SET_ORDER);
        $aliases = [];
        foreach ($aliasMatches as $alias) $aliases[$alias[1]] = $alias[2];

        $utilities = dirname(__DIR__, 2).'/resources/upstream/Utils.js';
        $utilitySource = file_get_contents($utilities);
        self::assertIsString($utilitySource);
        foreach ($allowed as $method) {
            if (isset($direct[$method])) continue;
            $target = $aliases[$method] ?? $method;
            self::assertMatchesRegularExpression(
                '/(?:window\.)?WWebJS\.'.preg_quote($target, '/').'\s*=/',
                $utilitySource,
                "Fallback bridge method {$method} has no pinned WWebJS utility.",
            );
        }
    }
}
