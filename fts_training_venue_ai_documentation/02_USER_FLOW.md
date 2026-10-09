# FTS Training Venue AI — User Flow

## 1. Global flow
```text
Opening screen → Enter venue → Welcome
   ├─ See the rooms → room detail → Request this room → Booking wizard
   ├─ Facilities → facility detail → Ask the coordinator
   ├─ Venue info → house rules / FAQ
   ├─ Plan an event → Booking wizard
   └─ Team → WhatsApp / phone / email, or chat hand-over
```
The agenda strip is visible in every session. Choosing a step pulls the projector screen down, shows the coordinator's line ("On to the training rooms."), then opens the next session.

## 2. Menu topics
Each agenda step carries a *topic* (a question). If the guest is already in that session the coordinator answers it in place; otherwise the topic is raised once the next session has loaded.

## 3. Rooms
1. The coordinator introduces the rooms from stored data (count, day rate from).
2. The directory lists type, capacity, size and day rate.
3. A room page shows photos, rate, a 5-day programme estimate, facts, capacity per layout (bar chart) and equipment.
4. Actions: *Request this room*, *Ask about this room*, previous / next.

## 4. Booking request
Dates → Participants → Room (layout, catering) → Your details → Summary. See `06_RESERVATION_FLOW.md`.

## 5. Chat
Guests ask in natural language. The coordinator calls tools (`search_rooms`, `check_availability`, …); room cards, quotes and booking confirmations render as UI inside the chat. Action chips jump to the matching session.

## 6. Hand-over
Special requests, complaints, group or corporate programmes, negotiated rates, programmes over 30 days, unusual cancellations and payment problems create a `HandoverRequest`. Staff reply from the admin panel; resolving it returns the conversation to the AI.
