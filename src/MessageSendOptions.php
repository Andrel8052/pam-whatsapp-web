<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class MessageSendOptions
{
    /**
     * @param list<string> $mentions
     * @param list<GroupMentionSend> $groupMentions
     * @param list<string> $stickerCategories
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public bool $sendSeen = true,
        public bool $linkPreview = true,
        public bool $sendAudioAsVoice = false,
        public bool $sendVideoAsGif = false,
        public bool $sendMediaAsSticker = false,
        public bool $sendMediaAsDocument = false,
        public bool $sendMediaAsHd = false,
        public bool $isViewOnce = false,
        public bool $parseVCards = true,
        public bool $ignoreQuoteErrors = true,
        public bool $waitUntilMsgSent = false,
        public ?string $caption = null,
        public ?string $quotedMessageId = null,
        public array $mentions = [],
        public array $groupMentions = [],
        public ?string $invokedBotWid = null,
        public ?MessageMedia $media = null,
        public array $extra = [],
        public ?string $stickerName = null,
        public ?string $stickerAuthor = null,
        public array $stickerCategories = [],
    ) {
        foreach ([...$mentions, ...$stickerCategories] as $value) {
            if ($value === '') {
                throw new \InvalidArgumentException('Mention ids and sticker categories must be non-empty.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toBridge(): array
    {
        return [
            'sendSeen' => $this->sendSeen,
            'linkPreview' => $this->linkPreview,
            'sendAudioAsVoice' => $this->sendAudioAsVoice,
            'sendVideoAsGif' => $this->sendVideoAsGif,
            'sendMediaAsSticker' => $this->sendMediaAsSticker,
            'sendMediaAsDocument' => $this->sendMediaAsDocument,
            'sendMediaAsHd' => $this->sendMediaAsHd,
            'isViewOnce' => $this->isViewOnce,
            'parseVCards' => $this->parseVCards,
            'ignoreQuoteErrors' => $this->ignoreQuoteErrors,
            'waitUntilMsgSent' => $this->waitUntilMsgSent,
            'caption' => $this->caption,
            'quotedMessageId' => $this->quotedMessageId,
            'mentionedJidList' => $this->mentions,
            'groupMentions' => array_map(
                static fn (GroupMentionSend $mention): array => [
                    'subject' => $mention->subject,
                    'id' => $mention->id,
                ],
                $this->groupMentions,
            ),
            'invokedBotWid' => $this->invokedBotWid,
            'media' => $this->media?->toBridge()['media'] ?? null,
            'extraOptions' => $this->extra,
            'stickerName' => $this->stickerName,
            'stickerAuthor' => $this->stickerAuthor,
            'stickerCategories' => $this->stickerCategories,
        ];
    }
}
