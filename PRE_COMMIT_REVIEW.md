# Pre-Commit Review (Phases 1-4)

## Tests Performed
- **Functional Testing**: End-to-end testing of `index.php` routing, `/auth/register.php` (OTP + form), `/auth/login.php` (PIN + Rate Limits), `/auth/otp.php` (Login with OTP), and `/auth/forgot_pin.php`. Lead Management (CRUD) endpoints were verified directly via cURL payload generation mimicking application JSON interactions.
- **Database Testing**: Evaluated schema definitions and foreign-key constraints for authentication and RBAC components. Validated that duplicate registrations or duplicate lead creation accurately triggers rejections within tenant bounds. All data manipulation routines use PDO prepared statements to defend against SQL injection.
- **Security & Authorization Testing**: Verified tenant isolation mechanisms against IDOR (Insecure Direct Object Reference). Successfully proved that attempting to access a lead record belonging to `Tenant B` while authenticated as `Tenant A` correctly responds with HTTP 404 (Not Found) due to server-side query scoping. Explored RBAC verification through an automated script targeting all edge-case permissions.
- **Security Testing**: Implemented CSRF checks using native PHP `bin2hex(random_bytes())` bound to session. Verified session generation (`session_regenerate_id`) occurs explicitly on authentication. Secured PHP sessions by setting `HttpOnly`, `Secure`, `SameSite=Lax`, and `use_strict_mode`. Validated rate-limiting behaves properly by intentionally triggering the limits locally via curl.
- **Responsive Testing**: Wrote a custom Playwright testing script mapping against different viewport resolutions (Mobile / Desktop) capturing key interface steps. Visually validated CSS rendering to align with modern 2026 Zopa UI goals.

## Tests Passed
- All API/Page syntax validations via `php -l`.
- RBAC granular permission rules validating properly dynamically against the DB.
- IDOR Tenant Boundaries validated natively.
- Mobile routing behavior logic (Existing User -> Login, New -> Register).
- OTP generation, validation, bounds checking (3 attempt limit), and expiration tracking.
- Rate limit mitigation (5 attempt limit per sliding window).
- Responsive design metrics across auth and lead management pages.

## Tests Failed
- None currently.

## Bugs Found & Fixed
- `includes/session.php` initially implemented an unpersistent rate limit checking bound only to `$_SESSION` which could easily be subverted by erasing cookies. *Fix: Refactored `checkRateLimit` to interact persistently with the `rate_limits` schema in the MySQL database mapped to the client IP address.*
- OTP Logic was statically simulated and did not adhere to expiration and attempt limits. *Fix: Built `/includes/otp.php` with robust PDO-backed OTP generation, limit checking, tracking, and successful deletion patterns.*
- Local dev errors and log files generated from server testing (`php_server.log`) were initially tracked in git incorrectly. *Fix: Cleaned up work directory ensuring no extraneous debugging tools or loggers leak.*

## Known Limitations / External Dependencies
- **SMS API**: The logic constructs safe real OTP codes but simulates sending since no credentials or external API endpoints are available. The actual messaging service remains unmapped.

## Recommended Next Step
Proceed to **Phase 5**: Lead Assignment + SLA + Duplicate Detection. The lead management profile and CRUD mechanisms are built securely and robustly on top of Phase 3 Tenant Isolation and RBAC context validation.