# Security policy

## Reporting a vulnerability

Please report security vulnerabilities privately. **Do not open a public issue** for a suspected
vulnerability.

Email **opensource@simtabi.com** with:

- the affected package (`simtabi/sis` or `laranail/sis-wrapper`) and version,
- a description of the vulnerability and its impact,
- steps to reproduce, and a proof of concept if you have one.

You can also use GitHub's private
[security advisory](https://github.com/laranail/sis/security/advisories/new) flow.

We aim to acknowledge a report within three business days and to provide a remediation timeline after
triage. Please give us a reasonable window to release a fix before any public disclosure.

## Scope

SIS is a security-first identifier system. The following are in scope and taken seriously:

- Bypassing the append-only audit trail or the storage-layer immutability triggers.
- Minting, reusing, or reissuing an identifier in violation of the lifecycle guarantees (§6.3).
- Defeating the deny-by-default authorization boundary or the enforced morph map.
- SSRF against the webhook delivery path, or webhook signature forgery.
- Idempotency-key replay across actors.

## Supported versions

While the project is pre-1.0, only the latest `0.1.x` tag is supported. Fixes land on the current tag.
