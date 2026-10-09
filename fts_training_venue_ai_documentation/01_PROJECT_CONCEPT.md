# FTS Training Venue AI — Project Concept

## 1. Product vision
FTS Training Venue AI is a full-screen, session-based website for a training and meeting venue. Visitors (HR teams, trainers, schools, companies) move through the venue as if they were walking through the agenda of a training day, guided by an **AI Coordinator** who knows every room, seating layout, rate and house rule.

It is not a long scrolling brochure. The viewport is fixed (`100dvh`, no page scroll) and every section is one **session** of the programme.

## 2. Core journey

```text
Opening screen → Welcome (01) → Rooms (02) → Facilities (03) → Venue info (04) → Booking (05) → Team (06)
                         ↑ the AI Coordinator is available in every session
```

| Session | Route | What it shows |
|---|---|---|
| 01 Welcome | `/{venue}` | Title slide, price-from, programme discount, session hours |
| 02 Rooms | `/{venue}/rooms`, `/rooms/{slug}` | Room directory and room detail (capacity per seating layout, equipment, catering, day rate) |
| 03 Facilities | `/{venue}/facilities`, `/facilities/{id}` | Equipment, catering, accommodation and transport cards |
| 04 Info | `/{venue}/info` | About, house rules, FAQ, session hours, map |
| 05 Booking | `/{venue}/reservation` | Five-step booking request wizard |
| 06 Team | `/{venue}/team` | WhatsApp / phone / email hand-over, address |

## 3. What makes it a training-venue product
- **Rooms, booked per day.** A room has a type (classroom, workshop lab, boardroom, hall, creative studio), a size, a floor label and a day rate. Availability and price live in `room_inventory`, one row per room per day.
- **Seating layouts.** Capacity differs per set-up (theatre, classroom, U-shape, boardroom, banquet, cluster). The wizard, the AI and the room page all use the layout capacity.
- **Whole days.** A programme is a run of days; the first and the last day both count, and a one-day event has the same first and last day. Online requests go up to 30 days (`ReservationService::MAX_DAYS`).
- **Programme discounts.** Each venue sets `multiday_discount_percent` (from 3 days) and `intensive_discount_percent` (from 5 days, "bootcamp"). `ReservationService::quote()` applies them, so wizard, AI tools and stored total always agree.
- **Catering add-on.** Priced per participant per day when a room offers it.
- **Session hours.** `opening_time` / `closing_time` replace hotel check-in / check-out.

## 4. AI Coordinator
Answers only from controlled data (knowledge base + inventory tools), refuses off-topic or offensive requests, creates booking requests, and hands the conversation to staff when it cannot help. See `04_AI_BEHAVIOR.md`.

## 5. Languages
Indonesian (`id`), English (`en`) and Japanese (`ja`), selected with `?lang=`.

## 6. V1 scope
In: guest site, AI chat with tool calling, booking requests (no payment), admin panel, email notifications, hand-over to staff.
Out: online payment, PMS/OTA integration, autonomous final confirmation, 3D tours.

## 7. Success criteria
A visitor can open the site, meet the coordinator, compare rooms by capacity and layout, check dates and price, submit a booking request and reach a human — without scrolling a long page.
