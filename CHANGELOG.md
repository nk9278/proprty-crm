
## Phase 10 - Site Visits
- Added complete Site Visits module backend schema, permissions, API and User Interface.
- Created robust concurrency models for check-in / check-out capabilities.
- Fixed an open Reflected XSS vulnerability on the Customer matching links.

## Phase 11 - Sales Pipeline
- Added dynamic, drag-and-drop enabled pipeline Kanban board mapping `leads` to customizable `pipeline_stages`.
- Introduced Pipeline Stage definitions securely isolated by `tenant_id` context requirements.

## Phase 12 - Booking & Cost Sheets
- Created authoritative Booking creation logic driven off concurrent Inventory constraints. Double booking is defended by structural `FOR UPDATE` read locks natively at the API tier.
- Migrated calculations off floating UI elements into standard math calculations encapsulated directly inside the `api/bookings.php` backend mapping.

## Phase 13 - Payments & Collections
- Attached payment structures inside `bookings/view.php` allowing manual ledger tracking entries inside the Sales Pipeline structures. Payments gracefully decrease outstanding ledger volumes without breaching absolute minimums structurally mapped at `DECIMAL(15,2)` capacities.
