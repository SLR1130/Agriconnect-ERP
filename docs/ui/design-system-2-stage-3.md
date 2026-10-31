# AgriConnect Design System 2.0 — Stage 3

This stage extends the farmer-facing service directory and assistance journey. It keeps the existing Laravel routes, shared authentication, program eligibility service, application history, and application POST operation.

## Farmer experience changes

- Explore Services presents six linked agricultural service areas with clear next actions and verification guidance.
- Explore Services and `/farmer/assistance` now call one catalog query service. Both pages use the same published-program scope, search, category filter, application-period filter, pagination, and farmer-specific eligibility checks.
- Program cards lead to a details page; the details page is the single application form location. It explains the open, upcoming, or ended state before displaying an action.
- Application tracking presents the recorded submission state, MAO feedback, status history, eligibility snapshot, and completed distribution details.
- Existing owner-scoped routes and server-side apply restrictions remain authoritative. No migration or live data update was made.

## Date-boundary finding (no policy change in this stage)

`config/app.php` currently sets the application timezone to `UTC`. Program opening and closing values are date-only fields, and the catalog query and `AssistanceProgram::isAvailable()` compare them with Laravel's `today()` value. The Farmer Portal is used in Bongabong (`Asia/Manila`). Between midnight and 07:59 local time, the UTC calendar date is still the previous date; a program opening or closing on the local current date can therefore be presented with an up-to-eight-hour difference from the MAO's local calendar day.

This stage intentionally preserves the existing eligibility and availability rule; it does not silently change a business decision. A separately reviewed backend change should interpret these date-only values using `Asia/Manila`, while preserving inclusive opening and closing dates. Before changing the policy, add isolated tests at local midnight, 07:59, 08:00, open-ended dates, contradictory date input, application POST boundaries, and pagination/filter behavior on both catalog routes. Update the model, catalog query, eligibility service, and displayed date guidance together.
