# Contributing

Thanks for helping build KAIWU.

1. Open an issue describing the adapter, protocol, bug, or UI change.
2. Keep vendor-specific logic behind an adapter.
3. Do not add real credentials, private paths, production payloads, or customer data.
4. Preserve the human-approval invariant for write-capable Quests.
5. Add or update tests.

Run before submitting:

```bash
vendor/bin/pint --test
php artisan test
npm run build
```
