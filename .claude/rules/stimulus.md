---
paths:
  - "app/assets/controllers/**/*.js"
---

# Stimulus controllers

- Don't assign `this.context`: Stimulus owns this property, and overwriting it breaks the
  controller when it connects.
- `disconnect()` undoes what `connect()` set up: observers, document listeners, animation frames.
