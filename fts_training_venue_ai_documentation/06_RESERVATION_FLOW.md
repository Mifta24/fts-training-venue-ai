# FTS Training Venue AI — Reservation Flow

## Principle
A guided booking *request*, not a payment. Nothing is charged; the venue team confirms.

## Steps (wizard)
1. **Dates** — range calendar in the guest's language. Days with no free room are struck through. Pick the first day, then the last day (tap the same day again for a one-day event). Up to 30 days.
2. **Participants** — number of participants and number of rooms.
3. **Room** — rooms that cannot hold the group are disabled. Seating layout (only layouts the room offers, each with its capacity) and optional catering.
4. **Details** — name, company, programme title, WhatsApp / phone / email, special request.
5. **Summary** — dates, group, room, contact, estimated total with programme discount.

## Rules
- `ReservationService::quote()` is the only place prices are computed: sum of day prices × rooms + catering (participants × days × price) − programme discount.
- Capacity: `participants ≤ room capacity (layout or overall) × rooms`.
- Creating a request locks the inventory rows, re-checks availability and increments `booked_rooms` for every day; cancelling releases them.
- Status: `pending → confirmed | cancelled`, `confirmed → cancelled`; cancelled is final.
- Reference format: `TV-XXXXXX`.

## Endpoints
`GET /{venue}/reservation/availability`, `POST /{venue}/reservation/quote`, `POST /{venue}/reservation` (rate limited).

## After submit
Reference, WhatsApp / phone / email links carrying the summary, email acknowledgement if an email was given, staff notification, and the draft is cleared.
