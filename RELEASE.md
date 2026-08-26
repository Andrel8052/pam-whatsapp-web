# Release checklist

PAM WhatsApp Web is published independently from the monorepo into
`push-in/pam-whatsapp-web`. Version `1.0.0` targets PHP 8.5+ and the immutable
`whatsapp-web.js` 1.34.7 commit recorded in `api-matrix.json`.

## Before tagging

1. Release `pushinbr/pam-browser` `v1.0.0` first and verify its public Packagist
   installation.
2. Use PHP 8.5, Chrome or Chromium, PAM, and a dedicated WhatsApp test account.
   Enroll its isolated profile before certification:

   ```bash
   PAM_WWEB_AUTH_PATH=/secure/pam-whatsapp-profile \
     pam tests/integration/enroll.php
   ```
3. Generate deterministic evidence:

   ```bash
   PAM_WWEB_CONTRACT_CERTIFICATION_REPORT=/secure/reports/contracts.json \
     pam tests/integration/contracts.php
   ```

4. Run the authenticated suite with every applicable fixture and explicit
   mutation guard documented in `CERTIFICATION.md`:

   ```bash
   PAM_WWEB_CERTIFICATION_REPORT=/secure/reports/authenticated.json \
     pam tests/integration/authenticated.php
   ```

5. Inspect both reports. No required check may be failed or skipped. Promote
   their evidence and require strict 751/751 parity:

   ```bash
   php bin/parity certify /secure/reports/contracts.json
   php bin/parity certify /secure/reports/authenticated.json
   composer parity:gate
   ```

6. Run the complete package gates from a clean commit:

   ```bash
   composer validate --strict
   composer analyse
   composer test
   composer test:legacy
   php tests/integration/contracts.php
   ../../scripts/package-release.sh validate
   ../../scripts/package-release.sh validate-package-tag \
     pushinbr/pam-whatsapp-web v1.0.0
   ```

## Publish

Merge the exact source commit into `main`. Dispatch **Independent Composer
package release** with package `pushinbr/pam-whatsapp-web`, tag `v1.0.0`, that
commit as `source_ref`, and `publish=false`. Inspect the provenance artifact.
Re-run with `publish=true` only after the verification job succeeds.

The workflow publishes the immutable split and tag, waits for Packagist, and
installs the exact public version in a clean PHP 8.5 consumer. Never create a
mirror tag manually or bypass the strict parity gate.
