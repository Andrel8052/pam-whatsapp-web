<?php

declare(strict_types=1);

namespace Pam\WhatsApp;

final readonly class SearchChannelsOptions
{
    /** @param list<string> $countryCodes */
    public function __construct(
        public string $searchText = '',
        public array $countryCodes = [],
        public bool $skipSubscribedNewsletters = false,
        public ChannelSearchView $view = ChannelSearchView::Recommended,
        public int $limit = 50,
    ) {
        if ($limit < 1) {
            throw new \InvalidArgumentException('Channel search limit must be positive.');
        }
        foreach ($countryCodes as $countryCode) {
            if (preg_match('/^[A-Za-z]{2}$/', $countryCode) !== 1) {
                throw new \InvalidArgumentException('Channel country codes must use ISO 3166-1 alpha-2 format.');
            }
        }
    }

    /** @return array{searchText: string, countryCodes: list<string>, skipSubscribedNewsletters: bool, view: int, limit: int} */
    public function toBridge(): array
    {
        return [
            'searchText' => trim($this->searchText),
            'countryCodes' => array_map(strtoupper(...), $this->countryCodes),
            'skipSubscribedNewsletters' => $this->skipSubscribedNewsletters,
            'view' => $this->view->value,
            'limit' => $this->limit,
        ];
    }
}
