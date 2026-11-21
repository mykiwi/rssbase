---
paths:
  - "app/templates/**/*.twig"
---

# Templates

- Every text shown or read to a user goes through the translator, attributes such as `aria-label`
  and `placeholder` included: `{{ 'home.start'|trans }}`. The names of the languages in the
  language menu stay in their own language.
- Use the `app_name` variable, not the name of the application. The logo is the exception: its two
  halves have different colors.
- In `base.html.twig`, keep every `<script type="module">` after the import map: Firefox ignores an
  import map that comes after a module script.
