# FTS Training Venue AI — Knowledge Base Structure

Table `venue_knowledge_items`: `category`, `title`, `body`, `translations` (en / ja), `tags`, `image_url`, `is_active`, `sort_order`.

| Category | Shown in | Typical content |
|---|---|---|
| `general` | Info | About the venue |
| `policies` | Info | Session hours, programme rates, cancellation, house rules |
| `faq` | Info | Layout changes, deposits, outside caterers |
| `facilities` | Facilities | Projectors, sound, Wi-Fi, stationery, on-site crew |
| `catering` | Facilities | Lunch and coffee breaks, nearby accommodation |
| `transport` | Facilities | TransJakarta / MRT, parking, airport transfers |

Rules:
- Facts only, **never price or availability of rooms** (that is `room_inventory`).
- Tags help the AI match questions and pick the card icon (`VenueKnowledgeItem::iconName()`).
- Indonesian is the default text; English and Japanese live in `translations`.
- Inactive items are invisible to guests and to the AI.
