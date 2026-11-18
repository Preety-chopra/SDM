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
| PENALTY ON M/s JMD (in ₹) | `contractor_penalty` (decimal, INR) |
| C&P No | `cp_number` |
| Negative Points | `negative_points` (decimal) |

The worksheet row number and USN are retained. Blank amounts and points stay null, distinct from zero. Invalid dates stay null with the original cell value retained. Invalid Aadhaar text is retained encrypted and flagged. No master data is created or overwritten from historical snapshots. The `AUTH` staff reference sheet is not imported.

Employee IDs are matched within the selected contractor, including inactive staff. A conflicting Aadhaar leaves the staff link unset. Aadhaar alone is used only when File No is blank and exactly one staff record matches. Stations are matched by station code and line. Direction suffixes such as `8 E` and `8W` normalize to line 8. Explicit interchange codes such as `LJPN-06` normalize only when their suffix matches the source line. Typos and ambiguous matches remain unlinked, with issue codes in `import_issues`.

## Running

Keep the source workbook in private storage, outside Git and the public web root. Use an enabled user with contractor management permission for `--actor`. Confirm the contractor ID from master data.

```bash
./vendor/bin/sail artisan sdm:import-history 'storage/app/private/imports/JMD Staff records.xlsx' --contractor=24 --actor=1 --dry-run
./vendor/bin/sail artisan sdm:import-history 'storage/app/private/imports/JMD Staff records.xlsx' --contractor=24 --actor=1
```

On Windows PowerShell, use `docker compose exec laravel.test php artisan` in place of `./vendor/bin/sail artisan`.

The default worksheet is `previous records of JMD`; `--sheet` selects another worksheet using the same column layout. Malformed layout or financial values abort the whole import. Records and the `historical_sdm.imported` audit event are committed together. The audit contains counts and provenance, without Aadhaar or employee names.

The same file, contractor and worksheet can be rerun without duplicate records or duplicate audits. Repeated memo numbers and even identical source rows are retained as separate historical entries. A modified or resaved workbook has a different checksum and is treated as a new import. Review it before importing to avoid overlapping historical datasets.

To review source issues without reading Aadhaar:

```php
App\Models\HistoricalSdm::query()
    ->where('import_id', $importId)
    ->get(['id', 'source_row', 'source_date', 'source_station', 'employee_number', 'import_issues']);
```

The migration's `down()` removes both history tables and their imported data. Back up historical data before rolling it back.
