---
paths:
  - 'app/Http/Controllers/Api/**'
---

# Api

## The API runs in the web middleware group (session + CSRF)
routes/api.php wraps the versioned API in Route::middleware('web'), so requests carry the session cookie and need the CSRF token: the Blade layout exposes it as <meta name="csrf-token"> and resources/js/react/lib/api.js sends it as X-CSRF-TOKEN. Scripts can use the XSRF-TOKEN cookie in the X-XSRF-TOKEN header, but the cookie value must be URL-decoded first. Do not move these routes into the stateless api group without replacing the whole auth mechanism.
