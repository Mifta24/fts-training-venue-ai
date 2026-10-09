# FTS Training Venue AI — Scene & Asset List

## Scenes
| Scene | Backdrop (Unsplash) | Coordinator pose |
|---|---|---|
| Opening | City skyline | none |
| Welcome | Bright open studio | standing |
| Rooms | Classroom | presenting |
| Room detail | Classroom | avatar only |
| Facilities | Presentation screen | standing |
| Info | Boardroom | standing |
| Booking | Workshop with sticky notes | consulting |
| Team | Team lounge | consulting |

Photos are referenced by URL in `VenuePageController::SCENE_PHOTOS`; room and facility photos are stored per record.

## Local assets
`public/images/character/`: `character avatar.jpg`, `character standing.png`, `character presenting.png`, `character consulting.png` (the coordinator).

## Brand
Mark: whiteboard on an easel with a tick (`components/venue-mark`). Fonts via Bunny Fonts (`vite.config.js`). Icons are inline SVG (`facility-icon`, `info-icon`).

## Guidance
Backdrops 2000 px wide, room photos 1200 px, facility photos 800 px. Keep the left third of a backdrop calm so type stays readable.
