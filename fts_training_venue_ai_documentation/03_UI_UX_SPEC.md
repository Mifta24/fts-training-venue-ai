# FTS Training Venue AI — UI/UX Specification

## 1. Theme: "Campus Programme"
- **Chalkboard green** (`--board #0c2726`) wash over training-room photography, **highlighter yellow** (`--highlight #ffd84a`) as the signal colour, **marker orange** (`--marker #ff7a3d`) for progress and accents, paper (`--paper #fbf9f2`) worksheets on a dotted grid.
- Type: **Bricolage Grotesque** (display), **DM Sans** (text), **DM Mono** (labels).
- Shapes are rounded and friendly: pill buttons and tags, 16–20px panels, a hand-drawn marker underline under key headline words, slightly rotated badges.

## 2. Layout
```text
┌ header: brand · SESSION 02 / 06 · label · sound · language ┐
├ agenda strip: 01 Welcome ─ 02 Rooms ─ 03 Facilities ─ …   ┤
│ stage: photo + coordinator cut-out + sheet / welcome text  │
│ right column: room directory or chat                       │
└ chat launcher (bottom right)                               ┘
```
- Fixed viewport, no page scroll; panels scroll internally.
- Phones: the agenda becomes a horizontal strip of pills, sheets fill the width, chat docks full width.

## 3. Components
`.sheet` (worksheet panel), `.agenda` (session strip), `.session-counter`, `.room-directory`, `.room-scene`, `.layout-bars`, `.facility-card`, `.info-hours`, `.wizard-*`, `.chat-*`, `.narrator`.

## 4. Motion
- **Projector screen**: rolls up when a session is ready, comes down before navigating (`.screen-curtain`), with a session badge and loading text.
- The session counter counts through the sessions between the previous and the current one.
- Pointer-driven depth (photo / coordinator / panels) on fine pointers.
- All motion is disabled under `prefers-reduced-motion`.

## 5. Sound
Optional synthesised sounds (no files): chime when moving on, ding for incoming messages, pop when sending. Muted with the header toggle; never plays on its own.

## 6. Accessibility
Visible focus rings, `aria-current` on the agenda, live regions for chat and calendar, narrator text always in the DOM, buttons ≥ 44px on phones.
