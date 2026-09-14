# Zopa CRM - Multi-Tenant SaaS Real Estate CRM

Zopa CRM is a comprehensive, multi-tenant Customer Relationship Management system explicitly engineered for Property Builders, Developers, Brokers, and Channel Partners.

This CRM handles the full property sales lifecycle natively in Core PHP without relying on external large frameworks (Laravel, Symfony, etc), ensuring strict tenant isolation across databases, robust internal APIs, dynamic concurrency locking, and natively designed modern responsive HTML5 views.

## Architecture

*   **Stack:** Core PHP 8+, MySQL/MariaDB, Vanilla JS, HTML5, CSS3.
*   **Security:** PDO Prepared Statements, CSRF Tokenization per-form, XSS Escaping (`escapeHTML`), Secure Session Configs, Database transactions wrapping critical financial evaluations implicitly blocking Race Conditions (`FOR UPDATE`).
*   **Database:** Extensively normalized database containing explicit relational foreign mappings strictly defining logic paths targeting `tenant_id` IDOR block logic safely separating instances efficiently.

## Modules Included

1.  **Foundation:** Authentication (OTP/PIN, Rate Limiting), Multi-Tenancy (Strict `tenant_id` blocks), Granular RBAC.
2.  **Lead Management:** Source ingestion, Lead Scoring, Tags, SLA Management, Duplicates constraints.
3.  **Property Management:** Complex inventory mapping (Project -> Tower -> Floor -> Unit) checking statuses implicitly natively.
4.  **Sales Engine:** Site Visits, Booking Lifecycle constraints natively isolating Unit capacities via SQL locks dynamically mapping out Cost Sheets strictly evaluating inputs securely on backend logic natively.
5.  **Financials:** Commission Allocations, Broker assignments mapping payments against Subscriptions, Milestone invoicing mappings tracking outstanding limits strictly.
6.  **Analytics:** Marketing Campaigns, Attribution mappings evaluating Webhook IDOR mappings securely creating KPI dashboards visually tracking business inputs securely escaping layouts intelligently.

## Installation & Deployment

### Prerequisites

*   PHP 8.x
*   MySQL/MariaDB Database

### 1. Clone & Setup

Download the core repository and point your standard Web Server document root towards the project root folder.

### 2. Configure Database Environment

Populate MySQL variables inside the server environment scope, or define defaults locally via standard ENV logic evaluated inside `/config/database.php`.

### 3. Initialize Schema

Import the core SQL matrices natively into the schema manually utilizing PHPMyAdmin or standard terminal logic.

```bash
mysql -u root zopacrm < database/schema.sql
mysql -u root zopacrm < database/seed.sql
```

> **Note:** Seed.sql provisions required `permissions`, `roles`, and base testing schema arrays securely to start initial `Tenant Owner` logic executing explicitly correctly.

### 4. Configure Webhooks & Integrations

Webhook endpoints natively evaluate API integration configurations resolving states mapped uniquely across `api_keys` bounding against Webhook deduplication strings inside schemas ensuring Idempotency inherently across configurations. Generate Keys internally over the settings panel mapped statically targeting tenant limits explicitly.

## External Configurations Required

*   **WhatsApp API:** Adapters are provided resolving tracking schemas over providers. Explicit Provider Keys required mapped into API integrations internally to transmit configurations actively without mocking.
*   **Payment Gateways:** Structural components natively track balance calculations seamlessly. Specific Merchant Key generation mappings required securely.

## Production Checklist

- [ ] HTTPS explicitly active across server domains.
- [ ] Database credentials properly evaluated securely checking ENV bounds outside document scope paths.
- [ ] `/uploads/private/` directories implicitly evaluating restrictive `.htaccess` controls actively correctly blocking direct public URLs resolving over HTTP structures manually mapping executions to `/api/documents.php`.
- [ ] Debug parameters evaluating disabled explicitly handling standard PHP errors appropriately routing cleanly into storage strings logging statically securely avoiding traces exposed statically via DOM.

## Deployment Configurations

### Security Settings
Before moving this environment fully to the public network, verify the execution scope handles configurations seamlessly mapping:
- **Session Tokens:** Configured evaluating HTTPs restrictions mapping logic natively.
- **Directories:** Verify the Apache execution paths securely execute mappings protecting `/uploads/private` limits avoiding file bypasses actively securely natively.

### Integrations Required
- Payments Gateway Keys (e.g Stripe, Razorpay) are not seeded. Add execution configurations resolving states.
- Webhook Keys mapped conceptually must be properly exposed evaluating external scopes dynamically resolving states gracefully securely dynamically.

### Production Environment Checklist
1. Export the active DB to external Storage cleanly.
2. Backup `config/database.php`.
3. Check `.env` string limits cleanly properly resolving configuration safely elegantly cleanly successfully evaluating safely mapping bounds stably natively perfectly efficiently cleanly correctly successfully.
