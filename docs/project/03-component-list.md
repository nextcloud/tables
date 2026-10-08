<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Component list

Part of the [buildiq parity project](README.md). Measured on `@conduction/nextcloud-vue` at commit b03e0f8c (8 October 2026) by walking the import graph from the application shell, the page types, the widget registry, the designer dialogs and the form system, with flows, automation, the AI companion and the app pickers for other Nextcloud apps as stop points. Reliance numbers come from the generated reliance score on the docs site.

## Summary

229 components are reached. Four verdicts:

| Verdict | Count | Meaning |
|---|---|---|
| keep | 132 | no OpenRegister reference, or only in comments; copy as is |
| adapt | 75 | a few calls that the data adapter replaces |
| rewrite | 10 | built around the OpenRegister object model; rebuilt on Tables |
| drop | 12 | an OpenRegister-only concept or out of scope |

One finding changes the numbers more than any code: most "heavy" scores come from one import chain. The integration registry statically imports all 28 built-in integrations, including every picker for other apps. Cutting that chain to the seven core integrations (audit trail, files, notes, shares, tags, tasks, version history) removes 23 to 27 out-of-scope entries from the reliance path of every layout component. The scorer also counts comments and UI text, so 15 components are "light" without making any call; they are marked keep.

## Page types

| Component | Type | Verdict | Why |
|---|---|---|---|
| CnIndexPage | index | rewrite the data layer, keep the UI | the list state, saved views, facets, mass actions and import and export call OpenRegister; table, cards, board and timeline rendering do not |
| CnDetailPage | detail | rewrite | built on object fetch, schema, locks, presence, lifecycle and the sidebar |
| CnDashboardPage | dashboard | adapt | no own data calls; per-user layout through preferences; widget references dropped |
| CnLogsPage | logs | replace with an index page on a read-only view | it is a list with pagination |
| CnFormPage | form | adapt | submits to a configured endpoint; only remote visibility rules call OpenRegister |
| CnSettingsPage | settings | adapt | drop the register mapping section |
| CnMapPage | map | adapt | through the map widget |
| CnFilesPage | files | adapt | WebDAV; reliance comes through the data table |
| CnReportsPage, CnLinkCardsPage, CnSearchPage, CnWikiPage | reports, links, search, wiki | keep | no calls |
| CnFeaturesAndRoadmapPage | roadmap | adapt | drop the GitHub proxy tab |
| CnStorePage | store | drop | OpenRegister store plane |

## Layout and shell

| Component | Verdict | What changes |
|---|---|---|
| CnAppRoot | adapt | required apps default, nav count badges and the collection probe go through the adapter; credentials dropped |
| CnPageRenderer | adapt | page context and split view fetch through the adapter |
| CnAppNav, CnWidgetGrid, CnDetailGrid, CnDetailWidgetHost, CnActionsBar, CnTabsWidget | adapt | nothing of their own; reliance through children |
| CnBuildiqEditButton | adapt | remove the flow entry |
| CnObjectSidebar | rewrite | tabs call object sub-resources; rebuilt on Tables rows with files, audit, relations and sharing tabs |
| CnRelatedCollections | rewrite | inverse relation lists |
| CnActionButtons | adapt | schema and save through the adapter; flow run dropped |
| CnDataTable | adapt | self fetch, distinct column values and aggregate columns through the adapter |
| CnCardGrid, CnCellRenderer, CnObjectCard, CnFilesBrowser, CnFkResolveCell | adapt | foreign key cells resolve through `get` |
| CnIndexSidebar, CnBoardView, CnRowActions, CnContextMenu, CnNextStepCard | adapt | reference options and remote visibility rules |
| CnAuditTrailCard, CnFilesCard, CnNotesCard, CnTagsCard, CnTasksCard, CnVersionHistory | adapt | one sub-resource endpoint each |
| CnSummaryAggregates | adapt | aggregate value |
| CnLifecycleActions | drop | lifecycle transitions are out of scope |
| CnDashboardGrid, CnWidgetRenderer, CnPageHeader, CnLeafMountHost, CnFolderSidebar, CnFolderTree, CnWalkthrough, CnDependencyMissing, CnObjectList, CnObjectRow, CnDateAxisView, CnPagination, CnQuickFilterBar, CnWidgetWrapper, CnBodySections, CnCommandPalette, CnSetupWizard, CnSavedViewsControl, CnAppLoading, CnFeaturesAndRoadmapSidebar, CnLeafDependencySettings | keep | no calls |

## Widgets

| Group | Components | Verdict |
|---|---|---|
| Presentational | CnTextWidget, CnTileWidget, CnDashTileWidget, CnLabelWidget, CnHeaderWidget, CnImageWidget, CnLinkButtonWidget, CnDividerWidget, CnVideoWidget, CnLinksWidget, CnQuicklinksWidget, CnMenuWidget, CnContainerWidget, CnNavCardGrid, CnCountdownWidget, CnBannerWidget | keep (banner adapts its remote visibility rule) |
| Nextcloud integrations | CnCalendarWidget, CnPeopleWidget, CnNcWidgetWidget, CnFilesWidget, CnDocumentReviewList, CnConversationThread | keep (files widget adapts its object mode) |
| Data lists | CnWidgetObjectTable (object-table), CnObjectListWidget (table), CnWidgetCardGrid (card-grid), CnWeekStripWidget, CnTimelineWidget, CnMapWidget, CnWidgetMapViewer | adapt |
| Aggregations | CnStatWidget, CnDeltaWidget, CnGaugeWidget, CnChartWidget, CnStatsBlockWidget, CnStackedBarWidget, CnWorkspaceFilterWidget | adapt, need the three aggregate methods |
| Detail | CnObjectDataWidget (data), CnRelatedObjectsWidget (related) | rewrite |
| Detail | CnObjectMetadataWidget, CnObjectGeoWidget, CnAuditTrailWidget, CnStagesWidget, CnInteractionFormWidget, CnWidgetFormRenderer, CnTabsWidget, CnIntegrationWidget | adapt (stages keeps only the field write) |
| Out of scope | CnTasksWidget, CnKbSearchWidget, CnObjectPresenceWidget, CnWidgetRefItem, CnSpendAnalyticsWidget | drop |

Widget forms: 23 keep as is, 11 adapt because they use the register and schema picker or infer fields from the first row, 1 drops with its widget. The picker itself, CnRegisterSchemaSelect, is rewritten as a table and view picker. Form helpers CnFilterRowsEditor, CnFieldPicker, CnMenuItemEditor and CnTextTableEditor keep.

## Dialogs, fields and primitives

| Component | Verdict | Why |
|---|---|---|
| CnFormDialog | adapt | needs a JSON Schema shaped `getSchema`, then form generation works unchanged |
| CnQuickEditDialog | adapt | through CnFormDialog |
| CnObjectMetadataModal | adapt | metadata field mapping |
| CnSchemaFormDialog, CnEditDataModal | rewrite | the data model editor becomes the Tables table and column editors |
| CnRelationLinkModal, CnNoteHistoryDialog | adapt | one call each |
| CnCredentials, CnNotificationPreferences, CnTransitionInputDialog, CnRoadmapTab, CnRegisterMapping | drop | OpenRegister-only |
| CnAddWidgetModal, CnEditPagesModal, CnEditMenuModal, CnEditActionsModal, CnEditSidebarModal, CnEditSettingsModal, CnEditSetupModal, CnEditWalkthroughModal, CnEditSupportModal, CnWidgetStyleEditorModal, CnWidgetVisibilityRulesModal, CnConfirmDialog, CnFilesWidgetDeleteDialog, CnPageConfigModal | keep | the designer dialogs make no calls; they edit the manifest in memory |
| CnTabbedFormDialog, CnWizardDialog, CnCopyDialog, CnDeleteDialog, CnMassCopyDialog, CnMassDeleteDialog, CnMassExportDialog, CnMassImportDialog, CnSaveViewDialog, CnSupportDialog, CnShareCreate, CnAdvancedFormDialog | keep | |
| CnResourceSelect | adapt | relation picker on `list`, `get` and inline `create` |
| CnFileField, CnJsonViewer, CnColorPicker, CnIconPicker, CnIconBrowser, CnDateRangePicker, CnMarkdownEditor, CnFieldHelper, CnFormWidgetBase, CnFormBuilder | keep | |
| 24 primitives (CnBreadcrumbs, CnCard, CnTabs, CnStatusBadge, CnStatsBlock, CnLockIndicator, CnLockedBanner and the rest) | keep | display only |

## Composables, stores and utilities

| Module | Verdict | Note |
|---|---|---|
| useObjectStore | replace by the Tables adapter | the main seam: registerType, fetchSchema, fetchCollection, fetchObject, saveObject, deleteObject, resolveReferences |
| useListView, useSavedViewsApi | adapt | list state and views through the adapter |
| liveUpdates plugin | optional | components no-op without `subscribe`; a Tables event source can come later |
| dashboardLayouts plugin | adapt | per-user layout through Tables preferences |
| useObjectLock, useObjectPresence, useLifecycleTransitions, useBrokeredCall, indexSources, useTaskInboxStore | drop | out of scope or OpenRegister-only |
| useManifestEditor, useWidgetForm, cnFormFieldRenderer, cnRenderMarkdown, useEndpointSource, useSetupStatus, useWalkthrough, useUserPreferences, useScopedTheme | keep | no OpenRegister calls |
| fetchAggregate, fetchFilterCounts, fetchSchemaProperties, visibleWhen (remote branch), schemaApi, indexExportHelpers, actionsDispatcher | adapt | each becomes one adapter method |
| schema.js, facets, auditTrailDiff, objectName, metadata constants and 20 other shape utilities | adapt the shapes | pure, but they read OpenRegister's `@self` and `x-openregister-*` keys; the adapter returns Tables data in that shape or the utilities get a mapping |
| 60 neutral pure utilities | keep | |

## The data adapter contract

Every adapt and rewrite verdict above resolves to one of these methods. This is the interface the Tables adapter implements in the frontend, against Tables endpoints that exist or that the [data model plan](04-data-model-and-gaps.md) adds.

| Method | Tables endpoint | Callers (examples) |
|---|---|---|
| `listSources()` | tables and views the user may see | the table and view picker, the data model editor |
| `getSchema(source)` | table scheme mapped to JSON Schema properties (type, format, title, enum, `$ref`, required, facetable) | detail page, page renderer, form dialog, list widgets, integration widget |
| `listFields(source)` | columns of a table or view | the eleven widget forms |
| `registerType(slug, source)` | menu item or page target | app root, page renderer, index, detail, logs, form dialog |
| `list(source, {filter, search, order, page, limit, facets})` returns `{results, total, page, pages, facets}` | new OCS rows endpoint with filter, sort, search and pagination | index page, data table, pickers, list widgets, map, timeline |
| `count(source, filter)` | the same endpoint with limit 0, or the aggregation endpoint | filter counts, nav badges, aggregate columns, visibility rules |
| `get(source, id)` | row by uuid | detail page, foreign key cells, form dialog, pickers, widgets |
| `create(source, data)`, `update(source, id, data)` | row create and update | detail, index quick edit, form dialog, data widget, inline table edit |
| `remove(source, id)`, `removeMany(source, ids)` | row delete, bulk delete | index mass actions |
| `aggregate.value`, `aggregate.grouped`, `aggregate.timeseries` | new aggregation endpoint | stat, delta, gauge, stats block, chart, stacked bar, workspace filter, summary aggregates |
| `relations(source, id, direction)` | relation column lookups in both directions | related widget, related collections |
| `files.list`, `files.upload`, `files.remove`, `files.downloadUrl` | new attachment column endpoints | files tab, files card, files widget, related widget |
| `audit.list(source, id)` | new row audit endpoint, entries with a per-field change map | audit tab, audit card, version history, timeline |
| `views.list`, `views.create`, `views.remove` | Tables views | index page, saved views control |
| `exportUrl(source, format, filter)`, `import(source, file)` | Tables export and import | index page, mass export and import dialogs |
| `saveSchema`, `deleteSchema`, `createSource`, `updateSource` | Tables table and column endpoints | the data model editor (designer only) |
| `subscribe`, `unsubscribe` | optional, later | detail, index, sidebar |

Optional sub-resources the sidebar tabs would need: notes, tasks, tags and shares per row. Tags map to Nextcloud system tags; shares map to Tables shares; notes and tasks are not planned before 16 February.

## What the list means for the plan

- The 132 keep components are a copy job with tests, no design.
- The 75 adapt components wait on the adapter; most need one method each. The adapter is therefore the first frontend deliverable.
- The 10 rewrite components are the detail page family (detail page, sidebar, data widget, related widget, related collections), the index page data layer, the logs page, and the data model editor and picker. They are planned as their own milestone.
- The 12 drop components cost nothing.
