# Security Policy

Please report suspected vulnerabilities privately to the maintainers through GitHub's private vulnerability reporting feature for this repository.

Do not open a public issue or disclose vulnerability details publicly before a fix is available. Include reproduction steps, affected versions, and potential impact in the private report.

## Custom CSS security model

Custom CSS is disabled by default and intended only for trusted administrators with the separate `manage-theme-studio-custom-css` ability. It is parsed server-side, restricted to a visual-property allowlist, scoped to Filament panel elements, revalidated during compilation, and excluded from authentication pages by default. URLs, remote assets, HTML, JavaScript schemes, dangerous at-rules, and interface-overlay properties are rejected.

These controls reduce risk but cannot make administrator-authored CSS completely safe: permitted CSS can still alter readability or hide ordinary content. Use the emergency `filament-theme-studio:disable-custom-css` command if the interface becomes unusable. Reports involving parser bypasses, selector escapes, external requests, authorization bypasses, preview-token disclosure, or CSS on authentication pages should be submitted privately.
