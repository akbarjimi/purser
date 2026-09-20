# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |
| 0.x     | No        |

Only the latest minor release of the current major version receives security
fixes.

## Reporting a vulnerability

Do not open a public GitHub issue for security reports.

Send an email to `security@akbarjimi.com` with:

- A description of the vulnerability.
- Steps to reproduce.
- The affected version(s).
- Any proof-of-concept code, if applicable.

If you prefer encrypted communication, ask for a PGP key in your first email
and wait for the response before sending details.

## What to expect

- Acknowledgement within 72 hours.
- An assessment of severity and affected versions within 7 days.
- A fix or a mitigation plan within 30 days for confirmed vulnerabilities.
- Public credit in the release notes, unless you ask to remain anonymous.

## Scope

In scope:

- Remote code execution.
- SQL injection in package-owned queries.
- Path traversal via file paths or disk names.
- Deserialization of untrusted data.
- Authentication or authorization bypasses in package-owned console commands.

Out of scope:

- Vulnerabilities in dependencies. Report those upstream.
- Issues that require an attacker to already control the application's
  configuration or database.
- Denial of service via legitimate large uploads. The package is designed to
  handle those; if it does not, that is a bug, not a vulnerability — open a
  normal issue.
- Attacks that require a compromised queue worker.

## Disclosure

We follow coordinated disclosure. Once a fix is released, we will publish an
advisory on GitHub with credit to the reporter. We ask that you do not
disclose the vulnerability publicly until 90 days after the fix is released or
until we confirm a disclosure date, whichever comes first.