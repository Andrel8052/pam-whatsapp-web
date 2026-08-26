<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

use Pam\WhatsApp\Contract\MessageContent;

final readonly class Poll implements MessageContent
{
    /** @var list<PollOption> */
    public array $pollOptions;
    public bool $allowMultipleAnswers;

    /** @var list<int>|null */
    public ?array $messageSecret;
    public PollSendOptions $options;

    /**
     * @param list<string> $pollOptions
     */
    public function __construct(
        public string $pollName,
        array $pollOptions,
        ?PollSendOptions $options = null,
    ) {
        if ($pollName === '' || count($pollOptions) < 2) {
            throw new \InvalidArgumentException('Poll name and at least two options are required.');
        }
        foreach ($pollOptions as $option) {
            if ($option === '') {
                throw new \InvalidArgumentException('Poll options cannot be empty.');
            }
        }
        $options ??= new PollSendOptions();
        if ($options->messageSecret !== null && count($options->messageSecret) !== 32) {
            throw new \InvalidArgumentException('Poll message secret must contain 32 bytes.');
        }
        $this->pollOptions = array_map(
            static fn (string $name, int $localId): PollOption => new PollOption($name, $localId),
            $pollOptions,
            array_keys($pollOptions),
        );
        $this->allowMultipleAnswers = $options->allowMultipleAnswers;
        $this->messageSecret = $options->messageSecret;
        $this->options = $options;
    }

    public function toBridge(): array
    {
        return [
            'kind' => ContentKind::Poll->value,
            'pollName' => $this->pollName,
            'pollOptions' => array_map(
                static fn (PollOption $option): string => $option->name,
                $this->pollOptions,
            ),
            'allowMultipleAnswers' => $this->allowMultipleAnswers,
            'messageSecret' => $this->messageSecret,
        ];
    }
}
