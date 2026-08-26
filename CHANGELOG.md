# Changelog

All notable changes to PAM WhatsApp Web will be documented here.

## 1.0.2 - 2026-08-25

- Add `TerminalQrCode`, a compact half-block renderer that cuts terminal QR
  output to roughly half the previous width and height.
- Simplify the quick-start example to one QR rendering call with no visible
  ANSI escape sequences.

## 1.0.1 - 2026-08-25

- Translate the complete public documentation and terminal listener example to
  English.
- Simplify terminal output by using ordinary line breaks instead of screen
  clearing control sequences.

## 1.0.0 - 2026-08-25

- Add a pure PHP 8.5+ WhatsApp Web client running on PAM without Node.js,
  Puppeteer, or Playwright.
- Control Chrome and Chromium directly through `pushinbr/pam-browser` and CDP.
- Pin compatibility to `whatsapp-web.js` 1.34.7 main commit
  `942d236a11ad68807308b058303ba5256915979c`.
- Map all 81 public symbols and 670 public members in a machine-readable parity
  matrix.
- Add typed authentication, messages, media, chats, contacts, groups, channels,
  calls, polls, commerce payloads, scheduled events, and all public events.
- Add lazy chunked `downloadMediaStream()` parity and media metadata contracts.
- Support commands issued directly from event callbacks by safely routing
  nested CDP responses.
- Add deterministic and authenticated certification runners whose union covers
  all 751 matrix entries.
- Add PHPStan level 9, PHPUnit, bridge, legacy, parity, and certification gates.
- Add a scan-ready terminal QR example with persistent authentication and live
  inbound message output.
