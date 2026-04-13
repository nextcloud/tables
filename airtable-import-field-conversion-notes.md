# Airtable Import — Field Conversion Notes

## Lossy fields

A Tables column **is** created, but some information is discarded.
One entry is added to the import report table for each lossy field.

| Airtable type | Tables column | What is lost |
|---|---|---|
| `duration` | `number` (suffix `s`) | `[h]:mm:ss` display format; stored as raw seconds |
| `singleCollaborator` | `text/line` | User identity; collaborator display name stored as plain text |
| `multipleCollaborators` | `text/line` | User identities; all display names joined with `, ` |
| `barcode` | `text/line` | Barcode type (e.g. `upce`, `ean13`) and rendered image; string value only |
| `createdBy` | `text/line` | Row `created_by` meta will hold the importing user, not the original creator; original name stored in column |
| `lastModifiedBy` | `text/line` | Row `last_edit_by` meta will hold the importing user; original editor name stored in column |

## Skipped fields

**No** Tables column is created and no cell values are written.
One entry is added to the import report table for each skipped field.

| Airtable type | Reason |
|---|---|
| `autoNumber` | Tables rows have their own row ID; a dedicated autoNumber column type is planned for Phase 3 |
| `formula` | Formula columns planned for Phase 4; original formula expression preserved in the report reason |
| `foreignKey` | Linked record columns require the Phase 2 `reference` column type |
| `lookup` | Depends on Phase 2 linked records |
| `rollup` | Depends on Phase 2 linked records |
| `count` | Depends on Phase 2 linked records |
| `button` | No equivalent action-trigger column type in Nextcloud Tables |
| `multipleAttachments` | Attachment import requires the Phase 1 `files` column type |
| `aiText` | No equivalent AI-generated text column type |
