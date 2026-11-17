# AgriConnect Design System 2.0 — Stage 2

This note records the shared UI foundation and the Farmer My Farms implementation. It does not authorize staff-module redesign, deployment, or changes to live data.

## Shared foundation

- Brand tokens are defined in `resources/css/app.css` and retain the existing forest green, emerald, agricultural gold, sage, and off-white palette.
- Farmer pages use Inter with system sans-serif fallbacks. Staff layouts are unchanged.
- Reusable Blade primitives live in `resources/views/components/ui/`: icon, button, field, select, status badge, card, section heading, empty state, and confirmation dialog.
- `blade-ui-kit/blade-heroicons` provides the Farmer Portal SVG icon family. Farmer navigation keeps its existing destinations and active-route logic.
- The local `.vscode/settings.json` selects the Blade formatter and disables Tailwind class sorting. It is ignored by Git and is not part of the project checkpoint.

## My Farms form contract

The wizard is a presentation layer over the existing `farmer.farms.store` POST and `farmer.farms.update` PATCH routes. It uses the existing field names and server validation, posts once at the final step, retains submitted values after validation errors, and relies on the existing owner-scoped controller checks. No migration or new status was added.

The steps are:

1. Farm identity and location
2. Land area and ownership
3. Agricultural activities and categories
4. Review and submit

Farm edit forms remain separate, owner-scoped PATCH forms. A farm submission is not represented as saved until the server returns its success response.

## Program-deadline timezone decision

The current Laravel application timezone in `config/app.php` is `UTC`. This design stage does not change it or alter program eligibility behavior.

For a separately approved implementation, program `opens_on` and `closes_on` values should be interpreted as Bongabong local calendar dates in `Asia/Manila`, with the existing inclusive opening and closing date semantics preserved. Stored date-only values should remain unchanged. Before applying that policy, add tests at both local midnight boundaries, for open-ended programs, and for dates around UTC/Asia-Manila day changes; then update availability queries and any corresponding farmer-facing labels together. No schema change is implied by this decision.
