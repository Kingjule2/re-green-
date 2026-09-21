---
paths:
  - 'resources/css/**'
---

# Css

## Scope Tailwind class discovery to resources
Keep Tailwind source discovery rooted at resources (source('../') from resources/css/app.css). Repository-wide scanning includes local ML datasets/training artifacts and caused build timeouts and multi-GB memory use. The scoped production build completed in about one second; include any future UI source outside resources explicitly.
