# FTS Training Venue AI — System Prompt

The prompt is built in `CoordinatorService::buildSystemPrompt()` and has four parts.

1. **Identity** — "You are the AI Coordinator for {venue}, a training and meeting venue in {city}, {country}" and who the guests are (HR teams, trainers, schools, companies).
2. **Nine hard rules** — language, knowledge via `search_knowledge`, prices via `search_rooms` / `check_availability`, tool cards, booking prerequisites, hand-over triggers, tone and layout advice, scope, safety. See `04_AI_BEHAVIOR.md`.
3. **Venue facts that are safe to state** — programme discount thresholds (from `Venue::MULTIDAY_MIN_DAYS`, `INTENSIVE_MIN_DAYS`), session hours, the online limit (`ReservationService::MAX_DAYS`), currency and today's date.
4. **Dynamic UI context** — current scene, selected room, selected facility, booking draft and scene guidance.

Every request additionally carries a *scope reminder* appended to the guest's last message (language, scope, no vulgarity, prices only from tools). It is never stored.
