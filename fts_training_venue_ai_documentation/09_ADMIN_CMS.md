# FTS Training Venue AI — Admin CMS (`/admin`)

| Module | What staff do |
|---|---|
| Dashboard | Pending requests (waiting > 24 h flagged), open handovers, programmes starting in 7 days, room utilisation for the next 30 days |
| Rooms | CRUD: type, size, floor, capacity, capacity per seating layout, catering, day rate, equipment, photos by URL, translations |
| Availability | Per room: open a date range, set rooms available and day price; month calendar (tap a day, then a later day). Rooms available cannot drop below what is booked |
| Knowledge base | Entries the AI may answer from (id / en / ja) |
| Bookings | Filter by status; confirm or cancel (cancel returns the rooms to inventory) |
| Handovers | Read the conversation, reply to the guest, mark resolved |
| Settings (owner) | Contact, session hours, language, time zone, multi-day and intensive discounts, draft / published |

Roles per venue: `owner` and `staff`. Staff only ever see their own venue's data.
Demo login (local only): `owner@ftsvenue.test` / `password`.
