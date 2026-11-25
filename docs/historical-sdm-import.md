# Historical SDM import

Historical records are stored in `historical_sdms`, separately from live `service_deficiency_memos`. They do not generate workflow tasks, approvals, penalty letters or deductions.

`historical_sdm_imports` records the source filename, SHA-256, worksheet, importing user, row count and issue counts. Each historical record links to its import, contractor, and, when reliably matched, `outsourced_staff` and `stations`. Referenced business records cannot be deleted while history points to them. The importing user can be removed without removing the import.

## Columns

| Workbook column | Historical column |
| --- | --- |
| DATE | `incident_date`, with the original cell value in `source_date` |
| MEMO NO / checked from | `memo_number` |
| LINE | `source_line` |
| STATION | `source_station`, matched `station_id` |
| ON DUTY STAFF | `employee_name` |
| FILE NO | `employee_number`, matched `outsourced_staff_id` |
| Aadhar Number | `aadhaar_number` (encrypted and hidden from serialization) |
| BRIEF DETAIL OF VIOLATION | `discrepancies` |
| RECOMMENDED ACTIONS | `recommended_action` |
| PENALTY ON M/s JMD (in ₹) / Penalty on Contractor(in Rs) | `contractor_penalty` (decimal, INR) |
| C&P No | `cp_number` |
| Negative Points | `negative_points` (decimal) |

The worksheet row number and USN are retained. Blank amounts and points stay null, distinct from zero. Invalid dates stay null with the original cell value retained. Invalid Aadhaar text is retained encrypted and flagged. No master data is created or overwritten from historical snapshots. The `AUTH` staff reference sheet is not imported.

NCES worksheet `RECORD(NCES.79)` uses the same historical fields. The importer uses the Aadhaar column beside File No (J), the C&P reference in O, and the negative points in Q. The additional C&P status in P and notes beyond Q remain in the original workbook.

Employee IDs are matched within the selected contractor, including inactive staff. A conflicting Aadhaar leaves the staff link unset. Aadhaar alone is used only when File No is blank and exactly one staff record matches. Stations are matched by station code and line. Direction suffixes such as `8 E` and `8W` normalize to line 8. Explicit interchange codes such as `LJPN-06` normalize only when their suffix matches the source line. Typos and ambiguous matches remain unlinked, with issue codes in `import_issues`.

## Running

Keep the source workbook in private storage, outside Git and the public web root. Use an enabled user with contractor management permission for `--actor`. Confirm the contractor ID from master data.

```bash
./vendor/bin/sail artisan sdm:import-history 'storage/app/private/imports/JMD Staff records.xlsx' --contractor=24 --actor=1 --dry-run
./vendor/bin/sail artisan sdm:import-history 'storage/app/private/imports/JMD Staff records.xlsx' --contractor=24 --actor=1
```

On Windows PowerShell, use `docker compose exec laravel.test php artisan` in place of `./vendor/bin/sail artisan`.

The default worksheet is `previous records of JMD`. For the NCES workbook, pass `--sheet='RECORD(NCES.79)'`. Malformed layouts or financial values abort the whole import. Records and the `historical_sdm.imported` audit event are committed together. The audit contains counts and provenance, without Aadhaar or employee names.

The same file, contractor and worksheet can be rerun without duplicate records or duplicate audits. Repeated memo numbers and even identical source rows are retained as separate historical entries. A modified or resaved workbook has a different checksum and is treated as a new import. Review it before importing to avoid overlapping historical datasets.

To review source issues without reading Aadhaar:

```php
App\Models\HistoricalSdm::query()
    ->where('import_id', $importId)
    ->get(['id', 'source_row', 'source_date', 'source_station', 'employee_number', 'import_issues']);
```

The migration's `down()` removes both history tables and their imported data. Back up historical data before rolling it back.

## Seed the historical data on an existing server

`HistoricalSdmSeeder` imports the prepared historical workbooks through the same
validated importer. The local bundle contains 5,217 JMD and 11,273 NCES historical
SDMs. It matches existing contractors by name and uses the destination application's
encryption key.

Deploy these code files together:

```text
database/seeders/HistoricalSdmSeeder.php
app/Services/HistoricalSdmSeedService.php
app/Services/HistoricalSdmImportService.php
app/Support/HistoricalSdmWorkbookReader.php
```

Separately upload these private files from localhost into the same relative
paths on the server:

```text
storage/app/private/seed-data/historical-sdms.json
storage/app/private/seed-data/JMD Staff records.xlsx
storage/app/private/seed-data/NCES staff records-1.xlsx
```

Use the existing SSH/WinSCP connection. The workbooks contain personal data and
are excluded from Git. Keep the bundle outside `public/`, readable only by the
application's operating-system user. Upload the original workbooks so their
SHA-256 checksums match the manifest. A Git pull does not transfer
these private files. The preparation and verification scripts in the local
directory are not needed on the server.

Ensure the history migration has been applied and the contractor masters exist
under `JMD Consultants Global Pvt. Ltd.` and
`NCES (Nuvision Commercial & Escort Services)`. Import stations and outsourced
staff before history so the importer can link them using the destination's IDs.
If needed, use the standalone station, service contract and outsourced staff
seeders documented in [reference data seeders](../database/seeders/README.md) and
[staff deployment](outsourced-staff.md#seed-an-existing-server-from-localhost).
Existing contractors, stations and staff are not modified by the history seeder.

After taking a database backup, run from the deployed project directory:

```bash
cd /home/administrator/SDM/SDM
sudo docker compose exec laravel.test php artisan db:seed --class=HistoricalSdmSeeder --force
```

For a server without Docker, the equivalent command is:

```bash
php artisan db:seed --class=HistoricalSdmSeeder --force
```

The command reports created and skipped rows, followed by source issue counts.
The first import on a server with no historical data should create 16,490 rows.
If JMD is already present, the first run with the updated bundle should create
11,273 rows and skip 5,217. An identical rerun should skip all 16,490. Avoid
running the full `DatabaseSeeder` for this upload because it
also resets administrator credentials and role assignments.

The manifest has `version: 1` and an `imports` list. Each entry specifies
`contractor_name`, `file` (an XLSX basename in the same private directory),
`sheet`, `sha256` and `row_count`. Multiple workbooks can be listed; the complete
bundle commits in one transaction. Missing or ambiguous contractors, missing or
changed files, unexpected row counts, invalid workbook data, or an audit failure
roll back every new import in that run. The seeder does not create contractors.

Source rows, repeated memo numbers, financial values and original identity
details are preserved. Aadhaar is encrypted with the destination application's
own key. Each new import has a null system actor and the existing
`historical_sdm.imported` audit event, which contains provenance and counts without
personal identity values. Live memos, approvals and penalties are not created.

Matching and source issues are calculated against the target masters, so issue
counts can differ between environments. Reruns leave existing imports and server
edits untouched, including their original importing user and links; they do not
relink history after staff or stations are added later. Do not change the
workbook or checksum to force a rerun, as that creates a separate import.

After verifying the import, remove the uploaded private files from the server if
they are no longer needed.

### Local verification — 23 November 2026

All 977 PHP tests passed (7,614 assertions), including 16 historical seeder
cases. Laravel Pint passed for the changed PHP files. An additional isolated
restoration of the complete private bundle used a fresh encryption key and
compared all 5,217 rows against the previously verified source hashes, with no
mismatches. It reproduced INR 2,728,321.00 in contractor penalties and 21,806.00
negative points. A second seed added no records or audits. These checks did not
modify the development database; no server upload has been performed.
