# Lines and sections

Administrators manage sections from **Lines → Manage sections**. A physical line keeps its identity while its operational sections change, for example line `6` with sections `6N` / `Line-6N` and `6S` / `Line-6S`.

Add a section, enter its code and display name, then choose its stations. Selecting **From station** and **To station** checks the inclusive range in station sequence order. Individual checkboxes can adjust that selection. Stations without a sequence number must be selected individually. Every station, including inactive stations, must belong to exactly one section when sections are configured. Overlaps and missing stations are rejected by the server.

Saving replaces the current section definitions and retains the previous definitions with their station IDs and start/end timestamps. To merge particular sections, remove their definitions and add a combined section containing their stations. To return to an undivided line, select **Merge all sections into whole line**, then save.

The **Merge lines** form combines two or more active physical lines into one selected line. Station IDs, user station assignments, contract station coverage, existing SDMs, imported historical SDMs, and existing cash-check records are preserved. Source lines are retained as inactive records. All current sections on the selected lines end, and new sections can be configured on the resulting line.

New SDMs and new cash-check entries capture the current section code. Existing SDMs retain their stored line details, including when edited for the same station. SDM line filters include previous section codes. A merge freezes existing contract location summaries before station assignments change, preserving contract timestamps and original line references. New contract revisions use the stations' current physical lines.

Section and merge changes and preserved contract locations are audited within the same transaction. Failed validation or audit storage rolls back the operation. Lines and stations referenced by section history cannot be deleted; merged source lines cannot be edited or reactivated.

The migration only adds nullable fields and section tables. It does not configure sections or merge any development data. Rollback is permitted before section or merge history exists; afterward it refuses to discard that history. Use a forward correction after live changes.
