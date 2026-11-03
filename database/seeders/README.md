# Reference data seeders

`LineSeeder` recreates all 16 existing lines. `StationSeeder` recreates all 289
stations and automatically creates missing lines. Both use the real database
snapshot in `data/locations.json` from 31 October 2026.

Run either independently in Sail:

```bash
./vendor/bin/sail artisan db:seed --class=LineSeeder
./vendor/bin/sail artisan db:seed --class=StationSeeder
```

From Windows PowerShell:

```powershell
docker compose exec laravel.test php artisan db:seed --class=LineSeeder
docker compose exec laravel.test php artisan db:seed --class=StationSeeder
```

Station data preserves the source reference fields, sequence numbers, active
status, and separate records for interchange stations. Line colors follow the
existing model's color palette. Existing lines and stations are reused by their
application codes and are never overwritten. Reruns create no duplicate records
or audit entries. A station already assigned to a different line causes the
station seeder to roll back its writes, including any newly created lines.

`DatabaseSeeder` runs lines, stations, then contracts after the existing role,
designation, and administrator seeders. Only the default database seeder needs
the configured administrator credentials.

## Real contract seed data

`ServiceContractSeeder` recreates the latest versions of the 10 real contracts
in the local database as of 31 October 2026. It includes their 10 contractors,
coverage mappings and 126 saved penalty clauses. Its location dependencies use
the shared snapshot of all 16 lines and 289 stations.
The original source document is `Contract Details_As on 01.10.2026.xlsx`;
the snapshot includes subsequent coverage and contract edits saved in the app.

Run it independently in Sail:

```bash
./vendor/bin/sail artisan db:seed --class=ServiceContractSeeder
```

From Windows PowerShell, use the same Sail application container:

```powershell
docker compose exec laravel.test php artisan db:seed --class=ServiceContractSeeder
```

The checked-in `data/service-contracts.json` snapshot preserves contract groups,
version numbers, effective dates, publication timestamps, and current statuses
(8 published, 2 draft). Superseded versions and demo contracts are excluded.
The BHS contract has no penalty clauses in the source database.

Database IDs are resolved using line and station codes and contractor names.
The station `station_id` field is the existing source-system station identifier,
not a database foreign key. User assignments, user IDs, document attachments,
and previous audit history are excluded.

Rerunning the seeder leaves existing master records and any existing version of
the same contractor/service/contract reference unchanged. It preserves local
edits, coverage, and penalties. Conflicting station-to-line mappings cause the
standalone contract seeder to roll back. Each seeder creates its new records and
audit entries in the same transaction with no user actor.
