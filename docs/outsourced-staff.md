# Outsourced staff

Open **Administration → Outsourced staff** to search, filter, add, edit, or mark staff inactive. Each contractor also has a **View outsourced staff** link. Access uses the existing `master_data.contractors.manage` permission, assigned to administrators by default. These records are separate from application user accounts.

## Stored details

The `outsourced_staff` table stores the unique employee ID, employee name, father's name, one nullable `date_of_birth` date, Aadhaar number, category, gender, designation, entry permit card number, verification status, contractor foreign key, and active status. Employee IDs and permit numbers are text to preserve leading zeroes and suffixes. Contractors with staff cannot be deleted.

Aadhaar numbers use Laravel's encrypted cast and are hidden from model serialization. The edit screen displays only the last four digits. Leave the replacement field blank to keep the saved number, or select **Clear saved Aadhaar**. Validation failures do not flash Aadhaar input into the session. Back up the application encryption key securely alongside the normal database backup process.

Staff writes and their audit records share a transaction. Audit records include operational fields and changed field names, excluding Aadhaar values, DOB, and other personal detail values.

## Excel imports

The import form accepts the original 13-column XLSX format, up to 10 MB and 10,000 staff rows. Map JMD, NCES, and SKM to the appropriate existing contractors. The importer reads the first worksheet and uses stored cell values. It does not execute workbook formulas or follow external workbook links.

DATE, MONTH, and YEAR are combined into one DOB. Leading and trailing whitespace is trimmed. Other source labels, including spelling inconsistencies in category, gender, designation, and verification status, are preserved for manual review. Blank optional cells become null.

Existing employee IDs are skipped so repeat uploads preserve maintained details. Duplicate IDs within a workbook, unmapped agencies, invalid DOBs, and invalid required fields stop the entire import before writing any staff. The Aadhaar option either stops on malformed values or leaves those values blank and reports worksheet row numbers. No malformed Aadhaar values are included in messages or logs.

The same importer is available through Sail:

```bash
./vendor/bin/sail artisan outsourced-staff:import /path/to/staff.xlsx \
  --actor=ADMIN_USER_ID \
  --agency=JMD=CONTRACTOR_ID \
  --agency=NCES=CONTRACTOR_ID \
  --agency=SKM=CONTRACTOR_ID \
  --invalid-aadhaar=blank \
  --dry-run
```

Use actual contractor IDs from the target environment. Remove `--dry-run` after reviewing validation results. The actor must be enabled and have contractor management permission. The command supports additional agency mappings using repeated `--agency=CODE=ID` options. The workbook is not bundled in the repository.

## Staff lookup while filling an SDM

Select the contractor, then enter an EMP ID or Aadhaar in **Outsourced Staff- Employee number**. The form fills the registered name and makes it read-only when there is one exact match within that agency. Aadhaar matches are replaced with the employee's EMP ID in the form and in the saved SDM. Spaces or hyphens in an Aadhaar are accepted.

When no record matches, enter the staff name manually. An unregistered 12-digit Aadhaar is masked on the saved SDM. Other unknown employee IDs are retained as entered. No staff master record is created by this fallback. If multiple records share the entered Aadhaar, use the employee's EMP ID instead.

Changing the identifier or contractor clears the previous name and ignores stale lookup responses. If lookup is unavailable, the form explains the error and permits manual name entry. The server still resolves registered staff when saving. Existing inactive staff can be identified for incident reporting as well.

The lookup uses authenticated, rate-limited POST requests with CSRF protection. It returns only the registered EMP ID and name. Creating and editing SDMs use their corresponding permissions. The `aadhaar_lookup_hash` is a hidden HMAC index keyed with the application key, while the original Aadhaar remains encrypted. The index is backfilled by migration `2026_11_10_120348_add_aadhaar_lookup_hash_to_outsourced_staff_table.php` and maintained automatically when Aadhaar changes. Rebuild this derived index after rotating the application key.

Lookup verification: 221 related PHP tests (1,709 assertions), all 62 JavaScript tests, Laravel Pint, and the production asset build passed. The local migration indexed all 3,493 populated Aadhaar fields, and both identifier types were verified against existing local staff without printing personal details. The full-suite check stopped after 70 passing tests at the pre-existing missing-title failure for `errors/sdm-action.blade.php`.

## Initial local import — 10 November 2026

All 3,498 source records were imported into local MySQL:

| Agency | Existing contractor | Staff |
| --- | --- | ---: |
| JMD | JMD Consultants Global Pvt. Ltd. | 1,337 |
| NCES | NCES (Nuvision Commercial & Escort Services) | 1,961 |
| SKM | SKM Contractor & Services Pvt. Ltd. | 200 |

Every record has a valid DOB. As requested, the following five malformed Aadhaar fields were left blank. Row numbers include the header row:

| Worksheet row | Employee ID |
| ---: | --- |
| 853 | 901645 |
| 1,117 | 901909 |
| 1,131 | 901923 |
| 1,142 | 901934 |
| 1,187 | 901979 |

Correct these through the staff edit screen after checking the source documents. The original workbook remains unchanged.

## Verification

- Fresh staff feature run: 29 tests passed, with 152 assertions.
- Related contractor and account management run: 42 tests passed.
- Laravel Pint and the frontend production build passed.
- The full suite completed with 671 passing and 47 failing tests while authentication code was changing concurrently. Reported errors included an undefined `UserSessionService::LAST_ACTIVITY_KEY` and a missing browser title in the existing `errors/sdm-action.blade.php` view. The session constant is present in the current code, and a fresh login test passed. The full suite has not been established as passing.
- Local database reconciliation confirmed 3,498 staff, no missing DOBs, five blank Aadhaar fields, encrypted stored Aadhaar values, and 3,498 staff creation audit records. A repeat-import dry run would create zero records and skip all 3,498 existing employee IDs.
