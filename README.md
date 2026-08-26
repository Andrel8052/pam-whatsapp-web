<div align="center">

# PAM WhatsApp Web

### A experiência do `whatsapp-web.js`, agora em PHP puro e persistente.

[![PHP 8.5+](https://img.shields.io/badge/PHP-8.5%2B-777BB4?logo=php&logoColor=white)](https://github.com/push-in/pam)
[![PAM Runtime](https://img.shields.io/badge/runtime-PAM-20C997)](https://github.com/push-in/pam)
[![Packagist](https://img.shields.io/packagist/v/pushinbr/pam-whatsapp-web?color=25c2a0)](https://packagist.org/packages/pushinbr/pam-whatsapp-web)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue)](LICENSE)

**QR no terminal · sessão persistente · eventos em tempo real · mídia · grupos · canais · chamadas**

[Começar agora](#start-here) · [Recursos](#o-que-já-vem-pronto) · [Compatibilidade](#compatibilidade) · [Segurança](#uso-responsável)

</div>

Uma API tipada para controlar o WhatsApp Web diretamente do PHP. O PAM mantém o
processo vivo, o `pam-browser` conversa com Chrome/Chromium por Chrome DevTools
Protocol e esta biblioteca entrega a superfície familiar do `whatsapp-web.js` —
sem Node.js, npm, Puppeteer ou Playwright em produção.

## Start here

### 1. Instale o PAM

```bash
curl --proto '=https' --proto-redir '=https' --tlsv1.2 \
    --connect-timeout 15 --max-time 60 --max-filesize 1048576 -fsSL \
    https://github.com/push-in/pam/releases/latest/download/install.sh | sh

pam doctor
```

### 2. Crie seu projeto e instale a biblioteca

```bash
mkdir meu-whatsapp && cd meu-whatsapp
pam composer init --no-interaction
pam composer require pushinbr/pam-whatsapp-web:^1.0
```

O host precisa ter Chrome ou Chromium instalado. Nenhum runtime JavaScript é
necessário.

### 3. Crie `listen.php`

```php
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use chillerlan\QRCode\Output\QRStringText;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Pam\WhatsApp\Auth\LocalAuth;
use Pam\WhatsApp\Auth\LocalAuthOptions;
use Pam\WhatsApp\Client;
use Pam\WhatsApp\ClientOptions;
use Pam\WhatsApp\Event\MessageReceived;
use Pam\WhatsApp\Event\QrCodeReceived;
use Pam\WhatsApp\Event\Ready;

$client = new Client(new ClientOptions(
    authStrategy: new LocalAuth(new LocalAuthOptions(
        clientId: 'main',
        dataPath: __DIR__.'/.sessions',
    )),
));

$client->onQrCode(static function (QrCodeReceived $event): void {
    $qr = new QRCode(new QROptions([
        'outputInterface' => QRStringText::class,
        'textDark' => "\033[40m  \033[0m",
        'textLight' => "\033[47m  \033[0m",
        'textLineStart' => '  ',
    ]));

    echo "\033[2J\033[H";
    echo "Abra o WhatsApp > Aparelhos conectados > Conectar aparelho\n\n";
    echo $qr->render($event->code), "\n";
});

$client->onReady(static function (Ready $event): void {
    echo "\033[2J\033[H✓ WhatsApp conectado. Aguardando mensagens...\n";
});

$client->onMessage(static function (MessageReceived $event): void {
    $message = $event->message;
    $body = $message->body !== '' ? $message->body : '['.$message->type->name.']';

    printf("[%s] %s: %s\n", date('H:i:s', $message->timestamp), $message->from, $body);
});

$client->initialize();
$client->run();
```

### 4. Rode

```bash
pam listen.php
```

Na primeira execução, um QR escaneável aparece no terminal. Depois do scan, o
cliente conecta sozinho e começa a imprimir mensagens recebidas. A autenticação
fica em `.sessions/`; nas próximas execuções, a conexão é restaurada sem outro
QR. Não publique essa pasta e não compartilhe seu conteúdo.

> O mesmo programa pronto está em [`examples/listen.php`](examples/listen.php).

## O que já vem pronto

| Área | Recursos |
|---|---|
| Sessão | QR, código de pareamento, `LocalAuth`, `RemoteAuth`, reconexão e conflito de sessão |
| Mensagens | texto, resposta, edição, exclusão, encaminhamento, reações, menções e confirmação de leitura |
| Mídia | imagem, áudio, vídeo, documento, sticker, thumbnail e download em streaming |
| Conversas | contatos, chats privados, grupos, participantes, convites, comunidades e solicitações de entrada |
| Conteúdo | localização, enquete, contato, lista de contatos, botões, listas e eventos agendados |
| WhatsApp | presença, estado da conexão, chamadas, canais, labels, perfil comercial, produtos e pedidos |
| API | objetos tipados, enums inteiros, eventos imutáveis, PHPStan nível 9 e matriz automática de paridade |

### Responder a uma mensagem

```php
$client->onMessage(static function (MessageReceived $event): void {
    if ($event->message->body === '!ping') {
        $event->message->reply('pong 🟢');
    }
});
```

### Enviar mensagens e operar chats

```php
$client->sendMessage('5511999999999@c.us', 'Olá direto do PHP!');
$client->sendPresenceAvailable();
$client->archiveChat($chatId);
$client->muteChat($chatId, new DateTimeImmutable('+1 hour'));
$contact = $client->getNumberId('5511999999999');
```

### Baixar mídia grande sem estourar memória

```php
use Pam\WhatsApp\MediaStreamOptions;

$media = $message->downloadMediaStream(new MediaStreamOptions(
    chunkSize: 1024 * 1024,
));

if ($media !== null) {
    foreach ($media->stream as $chunk) {
        // Grave ou encaminhe cada chunk binário.
    }
}
```

## Compatibilidade

A versão `1.0` acompanha o `whatsapp-web.js` `1.34.7` e o commit de referência
`942d236a11ad68807308b058303ba5256915979c`. A cobertura é auditável em
[`api-matrix.json`](api-matrix.json): **81 símbolos + 670 membros, 751/751
contratos estritos**.

```bash
pam composer parity:gate
pam composer test
pam composer analyse
```

A certificação ao vivo é separada entre smoke de QR e suíte autenticada com
proteções explícitas para mutações. Veja [`CERTIFICATION.md`](CERTIFICATION.md).

## Arquitetura

```text
seu código PHP
      │
      ▼
PAM WhatsApp Web ── eventos e objetos tipados
      │
      ▼
PAM Browser ─────── Chrome DevTools Protocol
      │
      ▼
Chrome / Chromium ─ WhatsApp Web
```

## Uso responsável

Esta é uma biblioteca comunitária e não oficial. O WhatsApp pode alterar seus
módulos internos sem aviso e pode limitar ou bloquear contas que violem seus
termos. Evite spam e automação abusiva. Para integrações oficialmente suportadas
e workloads críticos, use a WhatsApp Business Platform da Meta.

## Licença

Código aberto sob a [Apache License 2.0](LICENSE). Pode usar, modificar e
distribuir, inclusive comercialmente.
