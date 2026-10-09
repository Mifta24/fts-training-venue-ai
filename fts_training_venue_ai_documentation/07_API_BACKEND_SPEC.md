# FTS Training Venue AI — API & Backend Specification

Laravel 13, PHP 8.3+, SQLite by default, database queue / cache / session.

## Guest routes
| Method | Path | Controller |
|---|---|---|
| GET | `/` | `VenuePageController@index` |
| GET | `/{venueSlug}`, `/rooms`, `/rooms/{roomSlug}`, `/facilities`, `/facilities/{id}`, `/info`, `/team`, `/reservation` | `VenuePageController` |
| GET | `/{venueSlug}/reservation/availability` | `ReservationController@availability` |
| POST | `/{venueSlug}/reservation/quote`, `/{venueSlug}/reservation` | `ReservationController` |
| POST | `/{venueSlug}/coordinator/start`, `/message` | `CoordinatorChatController` |
| GET | `/{venueSlug}/coordinator/history` | `CoordinatorChatController@history` |

Unpublished venues return 404 everywhere.

## Chat payload (`ui_payload`)
`room_results`, `room_detail`, `availability`, `booking_confirmation`, `handover`. The controller adds `suggested_actions` (`view_room`, `reserve`, `team`).

## Message request
`guest_token`, `message`, optional `scene`, `selected_room`, `selected_facility`, `reservation{start_date,end_date,participants,rooms,room_slug}`.

## Services
`CoordinatorService` (LLM loop), `VenueCoordinatorTools`, `ContentGuard`, `ReservationService`, `ReservationHandover`.

## Admin (`/admin`, session auth)
Dashboard, rooms (+ availability calendar), knowledge items, bookings, handovers, settings (owner only). Login is throttled to 5 attempts per minute.

## LLM
OpenAI-compatible endpoint: `LOCAL_LLM_BASE_URL`, `LOCAL_LLM_API_KEY`, `LOCAL_LLM_MODEL`.
