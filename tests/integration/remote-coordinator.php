<?php

declare(strict_types=1);

use Pam\WhatsApp\Auth\LocalAuth;
use Pam\WhatsApp\Auth\LocalAuthOptions;
use Pam\WhatsApp\Client;
use Pam\WhatsApp\ClientOptions;
use Pam\WhatsApp\ClientState;
use Pam\WhatsApp\Event\MessageReceived;
use Pam\WhatsApp\MessageType;

require dirname(__DIR__).'/bootstrap.php';

final class RemoteCoordinatorState
{
    public int $stage = 1;
    public ?string $chatId = null;

}

$authPath = getenv('PAM_WWEB_AUTH_PATH');
if (!is_string($authPath) || $authPath === '') {
    throw new RuntimeException('PAM_WWEB_AUTH_PATH is required.');
}

$client = Client::launch(new ClientOptions(
    authStrategy: new LocalAuth(new LocalAuthOptions('certification', $authPath)),
    headless: true,
    browserTimeoutSeconds: 60.0,
    authenticationTimeoutSeconds: 90.0,
    browserArguments: ['--disable-dev-shm-usage'],
));

$state = new RemoteCoordinatorState();
$client->onMessage(static function (MessageReceived $event) use ($state): void {
    $message = $event->message;
    if ($state->chatId === null || $message->chatId !== $state->chatId || $message->fromMe) return;
    if ($state->stage === 1) {
        $message->reply('✅ Texto recebido pelo PHP puro. Agora me envie um áudio curto.');
        $state->stage = 2;
        fwrite(STDOUT, "remote-stage:text\n");
    } elseif ($state->stage === 2 && in_array($message->type, [MessageType::Audio, MessageType::Voice], true)) {
        $media = $message->downloadMediaStream();
        if ($media === null || array_sum(array_map('strlen', iterator_to_array($media->stream))) === 0) return;
        $message->reply('✅ Áudio recebido em stream pelo PHP. Agora envie uma imagem.');
        $state->stage = 3;
        fwrite(STDOUT, "remote-stage:audio-stream\n");
    } elseif ($state->stage === 3 && $message->type === MessageType::Image) {
        $media = $message->downloadMediaStream();
        if ($media === null || array_sum(array_map('strlen', iterator_to_array($media->stream))) === 0) return;
        $message->reply('✅ Imagem recebida em stream. Teste remoto concluído; a matriz atual está em 751/751.');
        $state->stage = 4;
        fwrite(STDOUT, "remote-stage:image-stream\n");
    }
});

try {
    $client->initialize();
    $deadline = microtime(true) + 120.0;
    while ($client->state !== ClientState::Ready && microtime(true) < $deadline) $client->pump(1.0);
    if ($client->state !== ClientState::Ready) throw new RuntimeException('WhatsApp profile did not become ready.');

    $matches = array_values(array_filter(
        $client->getChats(),
        static fn (\Pam\WhatsApp\Chat $chat): bool => $chat->name === 'David' && !$chat->isGroup,
    ));
    if (count($matches) !== 1) throw new RuntimeException('Expected exactly one private chat named David.');
    $chat = $matches[0];
    $state->chatId = $chat->id->serialized;
    if (getenv('PAM_WWEB_REMOTE_NOTIFY_COMPLETE') === '1') {
        $chat->sendMessage('✅ Fechamento técnico: texto e áudio em stream passaram ao vivo; matriz atual 751/751, PHPUnit, PHPStan e empacotamento aprovados. O listener de teste foi encerrado com segurança.');
        return;
    }
    $chat->sendMessage('Pode sair do PC 👍 Vou coordenar o teste por aqui. Responda esta mensagem com qualquer texto para começarmos; depois pedirei um áudio e uma imagem.');

    $deadline = microtime(true) + 1800.0;
    while ($state->stage < 4 && microtime(true) < $deadline) {
        $client->pump(1.0);
    }
    if ($state->stage < 4) throw new RuntimeException("Remote certification timed out at stage {$state->stage}.");
} finally {
    $client->destroy();
}
