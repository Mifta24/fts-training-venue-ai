# FTS Training Venue AI — Testing Checklist

Automated: `composer test` (167 tests) and `vendor/bin/pint --test`.

## Manual
- [ ] Every page loads in `id`, `en`, `ja`; no page scroll; sheets scroll inside.
- [ ] Opening → Enter → projector screen rolls up; agenda highlights the current session; the counter counts to it.
- [ ] Agenda step on the same page asks the coordinator instead of navigating.
- [ ] Rooms: directory, room detail, gallery thumbnails, layout bars, previous / next wrap around.
- [ ] Booking: pick first and last day (and the same day twice); full days are struck through; rooms too small are disabled; layout pills show capacity; catering price shows; discount appears for 3+ and 5+ days; reference is shown; WhatsApp link carries the summary.
- [ ] Unavailable dates show alternatives that fit the group.
- [ ] Chat: opens / closes, draft dialog, thinking indicator, retry on error, room cards, availability card, booking confirmation, hand-over banner and staff replies.
- [ ] Offensive or off-topic messages get the fixed refusal.
- [ ] Admin: rooms CRUD, availability calendar, booking confirm / cancel, handover reply / resolve, settings (owner only).
- [ ] Phones: agenda scrolls sideways, chat docks full width, buttons ≥ 44 px.
- [ ] `prefers-reduced-motion`: no screen animation, no typing effect.
