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

## Existing servers with different line codes

Do not delete lines or stations to make the seeders run. Use the one-time
reconciliation command before rerunning the station and contract seeders:

```bash
php artisan locations:reconcile-lines
```

In the existing Docker Compose deployment, prefix Artisan commands with
`sudo docker compose exec laravel.test`, for example:

```bash
sudo docker compose exec laravel.test php artisan locations:reconcile-lines
```

The default command is a read-only preview. It matches existing stations by
their application `code` to `data/locations.json`, then infers each existing
line's canonical snapshot code from all its matching stations. It never matches
lines by name: separate Blue Line records, for example, have the same name.
Review every mapping; applying it treats the snapshot station identities as
authoritative for line membership, including local stations on the mapped line.

After taking a verified database backup, run during a maintenance window with
web requests and background writers paused:

```bash
sudo docker compose exec laravel.test php artisan locations:reconcile-lines --apply
sudo docker compose exec laravel.test php artisan db:seed --class=StationSeeder --force
sudo docker compose exec laravel.test php artisan db:seed --class=ServiceContractSeeder --force
```

Stop if any command fails. Reconciliation creates missing target lines and
normalizes target line names to the snapshot labels. It updates all stations
on each mapped source line, service contracts' primary `line_id`, and contract
line coverage pivots together. Existing pivot destinations are deduplicated;
swapped source and destination codes are resolved from the original references.
New lines, name corrections, station moves, contract moves, and audit entries
commit in one transaction. Reruns make no additional changes or audit entries.

Station and contract IDs, station source-system fields, user assignments,
station coverage pivots, and historical memo snapshots are preserved. Old line
rows are retained. Lines without usable snapshot evidence are left unchanged;
unknown legacy codes are reported. A known station with no line, or a source
line whose stations imply multiple snapshot lines, aborts the entire operation.
Those cases require correcting the assignments before rerunning; the command
does not guess a mapping or silently bypass the seeder's conflict checks.

Deploy the new command, service, and updated repository files before running
these commands on an existing server. No migration is required. Avoid rerunning
the full `DatabaseSeeder` solely to resume this failure: it also resets the
administrator credentials and role assignments from the server configuration.
