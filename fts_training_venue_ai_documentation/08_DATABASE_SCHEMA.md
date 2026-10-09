# FTS Training Venue AI — Database Schema

| Table | Key columns |
|---|---|
| `venues` | name, slug, custom_domain, description, translations, address, city, country, lat/long, phone, whatsapp, email, timezone, currency, default_locale, `opening_time`, `closing_time`, `multiday_discount_percent`, `intensive_discount_percent`, public_status |
| `venue_users` | venue_id, user_id, role (`owner`/`staff`), status |
| `rooms` | venue_id, name, slug, description, translations, `room_type`, size_sqm, `floor_label`, `capacity`, `seating_layouts` (json `[{type, capacity}]`), `catering_included`, `catering_available`, `catering_price`, `base_price`, amenities, is_active, sort_order |
| `room_images` | room_id, image_url / image_path, tags, alt_text, sort_order |
| `room_inventory` | room_id, `event_date`, `total_rooms`, `booked_rooms`, `price` — unique (room_id, event_date) |
| `venue_knowledge_items` | venue_id, category, title, body, translations, tags, image_url, is_active, sort_order |
| `conversations` | venue_id, guest_token, locale, status, current_scene, selected_room_id, selected_facility_id, reservation_state, handover_summary |
| `conversation_messages` | conversation_id, role (`guest`/`assistant`/`staff`/`system`), content, ui_payload, tool_calls |
| `bookings` | reference, venue_id, room_id, conversation_id, guest_name/email/phone, contact_type, locale, organization, event_title, `start_date`, `end_date` (inclusive), participants, room_count, seating_layout, catering, total_price, status, notes |
| `handover_requests` | conversation_id, reason, summary, status, assigned_to, resolved_at |

`room_inventory` is the only source of room availability and day prices.
Future: payments, PMS adapters, promotions, analytics.
