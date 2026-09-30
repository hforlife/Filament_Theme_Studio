# Contributing

Contributions are welcome. Before opening a pull request, search existing issues and pull requests, keep each change focused, and add or update tests and documentation for changed behavior.

## Development

```bash
composer install
composer validate --strict
composer test
composer analyse
composer test:lint
composer test:refactor
```

Use PSR-12-compatible formatting and preserve backward compatibility unless a breaking change is explicitly planned and documented.

## Security reports

Do not report vulnerabilities publicly. Follow [SECURITY.md](SECURITY.md).
