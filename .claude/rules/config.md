---
paths:
  - "app/config/**"
---

# Configuration

- After changing a file of `app/config/`, run `nix develop -c castor cache-clear` (`bin/console cache:clear`
  in the dev shell) before checking the result: it rebuilds the container, and with it
  `config/reference.php` and `config/schema.json`.
