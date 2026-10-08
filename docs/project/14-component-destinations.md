<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Component destinations

Part of the [buildiq parity project](README.md). Every component from the [component list](03-component-list.md) with two decisions: where it goes, and in which week we bring it there. The same data sits in [components.json](components.json), and the filterable page built from it is linked from the pull request.

## The two destinations

- **nc-vue** is Nextcloud's own Vue library, `@nextcloud/vue`. A component goes there when it knows nothing about tables, rows, views, applications or widgets, and fills a gap Nextcloud does not cover yet. We propose it upstream; Nextcloud decides. 45 components.
- **tables** is the Tables app, the new open builder. Everything that knows about the builder goes there. 143 components.
- **none** is the drop verdict from the component list. 17 components.

## Weeks

Weeks are ISO weeks and follow the [project plan](06-project-plan.md): sprint 1 starts in week 42, then 44, 46, 48, 50, 52 and week 2 of 2027. The week is when we bring the component to its destination, not when it is finished. For Tables that means the copy or rewrite lands on the fork. For nc-vue it means the upstream pull request opens.

| Destination | Week 44 | Week 46 | Week 48 | Week 50 | Week 2 |
|---|---|---|---|---|---|
| tables | 40 | 53 | 31 | 19 | 0 |
| nc-vue | 0 | 21 | 6 | 11 | 7 |

## Screenshots

Every component should have a screenshot, the way the docs show one for a component with a styleguide example. Today the styleguide renders 62 components, and 45 of those are in this list. We captured them from the live styleguide and they are on the filterable page. The other 145 components have a docs page with props but no rendered example, so they have nothing to screenshot yet. The fix is one styleguide example per component in nextcloud-vue, written when the component is copied or proposed. Thijn checks the screenshot exists before a component counts as landed.

## The list

| Component | Group | Verdict | Destination | Week | Screenshot |
|---|---|---|---|---|---|
| cnRenderMarkdown | composable or store | keep | nc-vue | 46 | needs an example |
| useEndpointSource | composable or store | keep | nc-vue | 46 | needs an example |
| useScopedTheme | composable or store | keep | nc-vue | 46 | needs an example |
| useUserPreferences | composable or store | keep | nc-vue | 46 | needs an example |
| CnBreadcrumbs | dialog, field or primitive | keep | nc-vue | 46 | needs an example |
| CnCard | dialog, field or primitive | keep | nc-vue | 46 | captured |
| CnLockIndicator | dialog, field or primitive | keep | nc-vue | 46 | needs an example |
| CnLockedBanner | dialog, field or primitive | keep | nc-vue | 46 | needs an example |
| CnStatsBlock | dialog, field or primitive | keep | nc-vue | 46 | captured |
| CnStatusBadge | dialog, field or primitive | keep | nc-vue | 46 | captured |
| CnTabs | dialog, field or primitive | keep | nc-vue | 46 | needs an example |
| CnBodySections | layout and shell | keep | nc-vue | 46 | needs an example |
| CnCommandPalette | layout and shell | keep | nc-vue | 46 | needs an example |
| CnDateAxisView | layout and shell | keep | nc-vue | 46 | needs an example |
| CnFolderSidebar | layout and shell | keep | nc-vue | 46 | needs an example |
| CnFolderTree | layout and shell | keep | nc-vue | 46 | needs an example |
| CnPageHeader | layout and shell | keep | nc-vue | 46 | captured |
| CnPagination | layout and shell | keep | nc-vue | 46 | captured |
| CnQuickFilterBar | layout and shell | keep | nc-vue | 46 | needs an example |
| CnCountdownWidget | widget | keep | nc-vue | 46 | needs an example |
| CnNavCardGrid | widget | keep | nc-vue | 46 | needs an example |
| CnAdvancedFormDialog | dialog, field or primitive | keep | nc-vue | 48 | captured |
| CnConfirmDialog | dialog, field or primitive | keep | nc-vue | 48 | needs an example |
| CnCopyDialog | dialog, field or primitive | keep | nc-vue | 48 | captured |
| CnDeleteDialog | dialog, field or primitive | keep | nc-vue | 48 | captured |
| CnTabbedFormDialog | dialog, field or primitive | keep | nc-vue | 48 | needs an example |
| CnWizardDialog | dialog, field or primitive | keep | nc-vue | 48 | needs an example |
| cnFormFieldRenderer | composable or store | keep | nc-vue | 50 | needs an example |
| CnColorPicker | dialog, field or primitive | keep | nc-vue | 50 | captured |
| CnDateRangePicker | dialog, field or primitive | keep | nc-vue | 50 | needs an example |
| CnFieldHelper | dialog, field or primitive | keep | nc-vue | 50 | needs an example |
| CnFileField | dialog, field or primitive | keep | nc-vue | 50 | needs an example |
| CnFormBuilder | dialog, field or primitive | keep | nc-vue | 50 | needs an example |
| CnFormWidgetBase | dialog, field or primitive | keep | nc-vue | 50 | needs an example |
| CnIconBrowser | dialog, field or primitive | keep | nc-vue | 50 | needs an example |
| CnIconPicker | dialog, field or primitive | keep | nc-vue | 50 | needs an example |
| CnJsonViewer | dialog, field or primitive | keep | nc-vue | 50 | captured |
| CnMarkdownEditor | dialog, field or primitive | keep | nc-vue | 50 | needs an example |
| useSetupStatus | composable or store | keep | nc-vue | 2 | needs an example |
| useWalkthrough | composable or store | keep | nc-vue | 2 | needs an example |
| CnSupportDialog | dialog, field or primitive | keep | nc-vue | 2 | needs an example |
| CnAppLoading | layout and shell | keep | nc-vue | 2 | captured |
| CnDependencyMissing | layout and shell | keep | nc-vue | 2 | captured |
| CnSetupWizard | layout and shell | keep | nc-vue | 2 | needs an example |
| CnWalkthrough | layout and shell | keep | nc-vue | 2 | needs an example |
| useBrokeredCall | composable or store | drop | none |  | not needed |
| useLifecycleTransitions | composable or store | drop | none |  | not needed |
| useObjectLock | composable or store | drop | none |  | not needed |
| useObjectPresence | composable or store | drop | none |  | not needed |
| useTaskInboxStore | composable or store | drop | none |  | not needed |
| CnCredentials | dialog, field or primitive | drop | none |  | not needed |
| CnNotificationPreferences | dialog, field or primitive | drop | none |  | not needed |
| CnRegisterMapping | dialog, field or primitive | drop | none |  | captured |
| CnRoadmapTab | dialog, field or primitive | drop | none |  | not needed |
| CnTransitionInputDialog | dialog, field or primitive | drop | none |  | not needed |
| CnLifecycleActions | layout and shell | drop | none |  | not needed |
| CnStorePage | page type | drop | none |  | not needed |
| CnKbSearchWidget | widget | drop | none |  | not needed |
| CnObjectPresenceWidget | widget | drop | none |  | not needed |
| CnSpendAnalyticsWidget | widget | drop | none |  | not needed |
| CnTasksWidget | widget | drop | none |  | not needed |
| CnWidgetRefItem | widget | drop | none |  | captured |
| useListView | composable or store | adapt | tables | 44 | needs an example |
| useObjectStore | composable or store | rewrite | tables | 44 | needs an example |
| useSavedViewsApi | composable or store | adapt | tables | 44 | needs an example |
| CnDashboardGrid | layout and shell | keep | tables | 44 | captured |
| CnFeaturesAndRoadmapSidebar | layout and shell | keep | tables | 44 | needs an example |
| CnLeafDependencySettings | layout and shell | keep | tables | 44 | needs an example |
| CnLeafMountHost | layout and shell | keep | tables | 44 | needs an example |
| CnObjectList | layout and shell | keep | tables | 44 | needs an example |
| CnObjectRow | layout and shell | keep | tables | 44 | needs an example |
| CnSavedViewsControl | layout and shell | keep | tables | 44 | needs an example |
| CnWidgetRenderer | layout and shell | keep | tables | 44 | captured |
| CnWidgetWrapper | layout and shell | keep | tables | 44 | captured |
| CnDashboardPage | page type | adapt | tables | 44 | captured |
| CnBannerWidget | widget | keep | tables | 44 | needs an example |
| CnCalendarWidget | widget | keep | tables | 44 | needs an example |
| CnChartWidget | widget | adapt | tables | 44 | captured |
| CnContainerWidget | widget | keep | tables | 44 | needs an example |
| CnConversationThread | widget | keep | tables | 44 | needs an example |
| CnDashTileWidget | widget | keep | tables | 44 | needs an example |
| CnDeltaWidget | widget | adapt | tables | 44 | needs an example |
| CnDividerWidget | widget | keep | tables | 44 | needs an example |
| CnDocumentReviewList | widget | keep | tables | 44 | needs an example |
| CnFilesWidget | widget | keep | tables | 44 | needs an example |
| CnGaugeWidget | widget | adapt | tables | 44 | needs an example |
| CnHeaderWidget | widget | keep | tables | 44 | needs an example |
| CnImageWidget | widget | keep | tables | 44 | needs an example |
| CnLabelWidget | widget | keep | tables | 44 | needs an example |
| CnLinkButtonWidget | widget | keep | tables | 44 | needs an example |
| CnLinksWidget | widget | keep | tables | 44 | needs an example |
| CnMenuWidget | widget | keep | tables | 44 | needs an example |
| CnNcWidgetWidget | widget | keep | tables | 44 | needs an example |
| CnPeopleWidget | widget | keep | tables | 44 | needs an example |
| CnQuicklinksWidget | widget | keep | tables | 44 | needs an example |
| CnStackedBarWidget | widget | adapt | tables | 44 | captured |
| CnStatWidget | widget | adapt | tables | 44 | needs an example |
| CnStatsBlockWidget | widget | adapt | tables | 44 | needs an example |
| CnTextWidget | widget | keep | tables | 44 | needs an example |
| CnTileWidget | widget | keep | tables | 44 | captured |
| CnVideoWidget | widget | keep | tables | 44 | needs an example |
| CnWorkspaceFilterWidget | widget | adapt | tables | 44 | needs an example |
| actionsDispatcher | composable or store | adapt | tables | 46 | needs an example |
| auditTrailDiff | composable or store | adapt | tables | 46 | needs an example |
| facets | composable or store | adapt | tables | 46 | needs an example |
| fetchAggregate | composable or store | adapt | tables | 46 | needs an example |
| fetchFilterCounts | composable or store | adapt | tables | 46 | needs an example |
| fetchSchemaProperties | composable or store | adapt | tables | 46 | needs an example |
| indexExportHelpers | composable or store | adapt | tables | 46 | needs an example |
| objectName | composable or store | adapt | tables | 46 | needs an example |
| schema.js | composable or store | adapt | tables | 46 | needs an example |
| schemaApi | composable or store | adapt | tables | 46 | needs an example |
| visibleWhen | composable or store | adapt | tables | 46 | needs an example |
| CnEditDataModal | dialog, field or primitive | rewrite | tables | 46 | needs an example |
| CnFieldPicker | dialog, field or primitive | keep | tables | 46 | needs an example |
| CnFilesWidgetDeleteDialog | dialog, field or primitive | keep | tables | 46 | needs an example |
| CnFilterRowsEditor | dialog, field or primitive | keep | tables | 46 | needs an example |
| CnFormDialog | dialog, field or primitive | adapt | tables | 46 | captured |
| CnMassCopyDialog | dialog, field or primitive | keep | tables | 46 | captured |
| CnMassDeleteDialog | dialog, field or primitive | keep | tables | 46 | captured |
| CnMassExportDialog | dialog, field or primitive | keep | tables | 46 | captured |
| CnMassImportDialog | dialog, field or primitive | keep | tables | 46 | captured |
| CnMenuItemEditor | dialog, field or primitive | keep | tables | 46 | needs an example |
| CnNoteHistoryDialog | dialog, field or primitive | adapt | tables | 46 | needs an example |
| CnObjectMetadataModal | dialog, field or primitive | adapt | tables | 46 | needs an example |
| CnQuickEditDialog | dialog, field or primitive | adapt | tables | 46 | needs an example |
| CnRegisterSchemaSelect | dialog, field or primitive | rewrite | tables | 46 | needs an example |
| CnRelationLinkModal | dialog, field or primitive | adapt | tables | 46 | needs an example |
| CnResourceSelect | dialog, field or primitive | adapt | tables | 46 | needs an example |
| CnSaveViewDialog | dialog, field or primitive | keep | tables | 46 | needs an example |
| CnSchemaFormDialog | dialog, field or primitive | rewrite | tables | 46 | captured |
| CnShareCreate | dialog, field or primitive | keep | tables | 46 | needs an example |
| CnTextTableEditor | dialog, field or primitive | keep | tables | 46 | needs an example |
| CnActionButtons | layout and shell | adapt | tables | 46 | needs an example |
| CnActionsBar | layout and shell | adapt | tables | 46 | captured |
| CnAppNav | layout and shell | adapt | tables | 46 | captured |
| CnAppRoot | layout and shell | adapt | tables | 46 | captured |
| CnBoardView | layout and shell | adapt | tables | 46 | needs an example |
| CnBuildiqEditButton | layout and shell | adapt | tables | 46 | needs an example |
| CnCellRenderer | layout and shell | adapt | tables | 46 | captured |
| CnContextMenu | layout and shell | adapt | tables | 46 | captured |
| CnDataTable | layout and shell | adapt | tables | 46 | captured |
| CnDetailGrid | layout and shell | adapt | tables | 46 | captured |
| CnDetailWidgetHost | layout and shell | adapt | tables | 46 | needs an example |
| CnFilesBrowser | layout and shell | adapt | tables | 46 | needs an example |
| CnFkResolveCell | layout and shell | adapt | tables | 46 | needs an example |
| CnIndexSidebar | layout and shell | adapt | tables | 46 | captured |
| CnPageRenderer | layout and shell | adapt | tables | 46 | captured |
| CnRowActions | layout and shell | adapt | tables | 46 | captured |
| CnTabsWidget | layout and shell | adapt | tables | 46 | needs an example |
| CnWidgetGrid | layout and shell | adapt | tables | 46 | needs an example |
| CnDetailPage | page type | rewrite | tables | 46 | captured |
| CnFormPage | page type | adapt | tables | 46 | needs an example |
| CnIndexPage | page type | rewrite | tables | 46 | captured |
| CnLogsPage | page type | rewrite | tables | 46 | needs an example |
| CnAuditTrailCard | layout and shell | adapt | tables | 48 | needs an example |
| CnCardGrid | layout and shell | adapt | tables | 48 | captured |
| CnFilesCard | layout and shell | adapt | tables | 48 | needs an example |
| CnNextStepCard | layout and shell | adapt | tables | 48 | needs an example |
| CnNotesCard | layout and shell | adapt | tables | 48 | needs an example |
| CnObjectCard | layout and shell | adapt | tables | 48 | captured |
| CnObjectSidebar | layout and shell | rewrite | tables | 48 | captured |
| CnRelatedCollections | layout and shell | rewrite | tables | 48 | needs an example |
| CnSummaryAggregates | layout and shell | adapt | tables | 48 | needs an example |
| CnTagsCard | layout and shell | adapt | tables | 48 | needs an example |
| CnTasksCard | layout and shell | adapt | tables | 48 | needs an example |
| CnVersionHistory | layout and shell | adapt | tables | 48 | needs an example |
| CnFilesPage | page type | adapt | tables | 48 | needs an example |
| CnMapPage | page type | adapt | tables | 48 | needs an example |
| CnSettingsPage | page type | adapt | tables | 48 | needs an example |
| CnAuditTrailWidget | widget | adapt | tables | 48 | needs an example |
| CnIntegrationWidget | widget | adapt | tables | 48 | needs an example |
| CnInteractionFormWidget | widget | adapt | tables | 48 | needs an example |
| CnMapWidget | widget | adapt | tables | 48 | needs an example |
| CnObjectDataWidget | widget | rewrite | tables | 48 | captured |
| CnObjectGeoWidget | widget | adapt | tables | 48 | needs an example |
| CnObjectListWidget | widget | adapt | tables | 48 | needs an example |
| CnObjectMetadataWidget | widget | adapt | tables | 48 | captured |
| CnRelatedObjectsWidget | widget | rewrite | tables | 48 | needs an example |
| CnStagesWidget | widget | adapt | tables | 48 | needs an example |
| CnTimelineWidget | widget | adapt | tables | 48 | needs an example |
| CnWeekStripWidget | widget | adapt | tables | 48 | captured |
| CnWidgetCardGrid | widget | adapt | tables | 48 | needs an example |
| CnWidgetFormRenderer | widget | adapt | tables | 48 | needs an example |
| CnWidgetMapViewer | widget | adapt | tables | 48 | needs an example |
| CnWidgetObjectTable | widget | adapt | tables | 48 | needs an example |
| useManifestEditor | composable or store | keep | tables | 50 | needs an example |
| useWidgetForm | composable or store | keep | tables | 50 | needs an example |
| CnAddWidgetModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnEditActionsModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnEditMenuModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnEditPagesModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnEditSettingsModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnEditSetupModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnEditSidebarModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnEditSupportModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnEditWalkthroughModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnPageConfigModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnWidgetStyleEditorModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnWidgetVisibilityRulesModal | dialog, field or primitive | keep | tables | 50 | needs an example |
| CnFeaturesAndRoadmapPage | page type | adapt | tables | 50 | needs an example |
| CnLinkCardsPage | page type | keep | tables | 50 | needs an example |
| CnReportsPage | page type | keep | tables | 50 | needs an example |
| CnSearchPage | page type | keep | tables | 50 | needs an example |
| CnWikiPage | page type | keep | tables | 50 | needs an example |

If you take a component, check its destination here first. If it says nc-vue, start a pull request on nextcloud/nextcloud-vue with a styleguide example and a screenshot in the description. If it says tables, copy or rewrite it on the fork in its week, with the Playwright spec and screenshot that [testing and acceptance](08-testing-and-acceptance.md) asks for.
