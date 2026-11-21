---
paths:
  - "app/tests/E2E/**/*.php"
---

# Browser tests

- PHPUnit doesn't count the assertions of `expect()`: a test that only uses them is reported as
  risky. End it with a `static::assert*` call.
- The kernel serves `localhost` only. A test that visits the subdomain of a language adds it first:
  `static::getPlaywrightClient()->setInterceptedHosts(['localhost', 'fr.localhost'])`.
