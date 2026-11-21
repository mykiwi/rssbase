---
paths:
  - "app/translations/**/*.yaml"
---

# Translations

- Write messages in the ICU MessageFormat, in `messages+intl-icu.<locale>.yaml`: `{name}` for a
  placeholder, `plural` and `select` for the variants of a message. Syntax:
  https://unicode-org.github.io/icu/userguide/format_parse/messages/
- Write one full key per line (`home.title.reader: ...`), not nested YAML maps, so that a search
  for the key of a template finds its messages. Separate the groups of keys with an empty line.
- Add every key to each language file: English and French.
- Use the `{app_name}` placeholder, a global translation parameter, not the name of the application.
- Use the typographic apostrophe (`’`) in the text of a message: the ASCII one can start a quoted
  section in ICU.
- A new language also needs its host in the `home` route of `app/config/routes.yaml` (and in the
  requirement that excludes language subdomains), its locale in `enabled_locales`
  (`app/config/packages/framework.yaml`), and its entry in the `languages` map of
  `app/templates/home/index.html.twig`.
