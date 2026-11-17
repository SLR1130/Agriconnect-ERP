# Standalone Agricultural Equipment and Asset Management System

The contribution in [`standalone/aeams`](../standalone/aeams/README.md) provides
the complete AEAMS application for review as an independent service. It contains
the React/TypeScript frontend, Express/TypeScript API, Prisma migrations,
PostgreSQL setup, Docker configuration, operating documentation, and tests.

Source: [jayliecalido9-netizen/Agricultural-Equipment](https://github.com/jayliecalido9-netizen/Agricultural-Equipment),
commit [`25798a4`](https://github.com/jayliecalido9-netizen/Agricultural-Equipment/commit/25798a4).

## Included functionality

- Equipment inventory, availability, reservations, borrowing, and inspected returns.
- Maintenance work orders, recurring plans, utilization, reports, and audit history.
- Admin and Employee permissions, notifications, and integration configuration.
- Agricultural login and dashboard design, responsive navigation, and plain
  operational page backgrounds. The login title retains its two-second animation.
- Dashboard overdue maintenance alerts and monthly reporting in Asia/Manila time.

## Run locally

Use Docker Desktop with its Linux engine and start commands from the application
directory, rather than the ERP repository root:

```powershell
Set-Location standalone/aeams
$env:COMPOSE_PROJECT_NAME = 'agriconnect-aeams-review'
.\scripts\Start-Application.ps1 -Demo
```

The startup script generates a local `.env`, builds the services, applies AEAMS
migrations, and installs fictional demonstration fixtures when `-Demo` is supplied.
Omit `-Demo` for an empty installation and follow the application's
[administrator provisioning guide](../standalone/aeams/docs/deployment.md).

The default application port is 3000, API port is 5000, and PostgreSQL port is
5432. Review local port availability first. Configure `FRONTEND_PORT` and
`FRONTEND_ORIGIN` together in the standalone `.env`; `POSTGRES_PORT` controls the
published database port. The API's published port is defined in the standalone
Compose file. Follow the application's README for connection URL configuration.

Use a distinct Compose project name as above to keep review containers, networks,
and volumes separate from other Compose installations. Existing services using
the same host ports need different published ports before running both copies.
All Compose and npm commands for AEAMS run within `standalone/aeams`.

Development passwords are generated/configured in the local `.env`; no private
environment file, database snapshot, uploaded equipment photo, or credential is
included in this contribution.

## Integration status

The ERP uses Laravel, MySQL, and Laravel session authentication. This application
uses Express, PostgreSQL with the `aeams` schema, and its own authenticated API.
The existing Laravel routes, models, migrations, views, authentication, and
equipment workflows are retained. Copying this application does not register it
as an embedded ERP module or establish single sign-on.

The standalone integration screens support configuration and mock adapters; live
farmer verification, procurement handover, shared identity, and production data
synchronization require agreed API contracts and further implementation. Do not
point the PostgreSQL connection URLs at the ERP's MySQL database or treat the two
equipment inventories as a synchronized production ledger.

## Validation

Validated against the contributed application source:

- TypeScript checking passed: `npm run typecheck`.
- Frontend production build passed in Docker.
- All 19 isolated API checks passed: `npm run test:api`, including overdue
  recurring plans and Philippine month boundary regressions.
- Browser checks covered all 18 workspace routes, plain operational backgrounds,
  desktop/mobile layout, and the preserved login title animation.

API tests require local PostgreSQL configuration and create a separate temporary
test database. The ERP's Laravel test suite was not run for this additive source
contribution; no Laravel application code is changed.
