# Security Policy

## Supported Versions

Only the latest release receives security updates.

| Version        | Supported |
|----------------|-----------|
| Latest release | Yes       |
| Older versions | No        |

## Reporting a Vulnerability

**Please do not open a public issue for security vulnerabilities.**

To report a vulnerability,
use [GitHub Private Vulnerability Reporting](https://github.com/diegovogel/klog/security/advisories/new) on this
repository. This allows us to discuss and resolve the issue privately before any public disclosure.

### What to include

- A description of the vulnerability
- Steps to reproduce or a proof of concept
- The potential impact
- Any suggested fix (optional but appreciated)

### What to expect

- **Acknowledgment** within 7 calendar days
- I will work with you to understand and validate the report
- A fix will be developed privately and released as a patch
- You will be credited in the release notes (unless you prefer otherwise)

## Disclosure Policy

This project follows coordinated disclosure:

1. The reporter submits a vulnerability privately via GitHub
2. The maintainer acknowledges and investigates
3. A fix is developed and tested
4. A new release is published with the fix
5. The vulnerability is disclosed publicly after the fix is available

Please allow reasonable time for a fix before any public disclosure.

## Out of Scope

The following are generally not considered security vulnerabilities:

- Vulnerabilities in dependencies that do not affect Klog directly
- Issues that require physical access to the server
- Automated scanner reports without a demonstrated, exploitable impact
- Denial of service attacks against a self-hosted instance
- Social engineering attacks against the maintainer or users
