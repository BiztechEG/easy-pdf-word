# Security Policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 1.x     | Yes       |

## Reporting a vulnerability

Please don't open a public issue. Report it privately from the repository's
**Security** tab with **Report a vulnerability**, and include the steps or the
data that trigger it.

Once a fix is released, the advisory is published with credit to you, unless
you'd rather stay anonymous.

## What counts

Anything that lets document data or a template reach more than it should, for
example:

- reading local files or internal URLs through images, fonts or locales,
- running code or injecting markup through values passed to a template.

The package's defaults are written to keep these closed. Turning on remote
images or Chromium JavaScript opens them on purpose; see the Security section
of the README.
