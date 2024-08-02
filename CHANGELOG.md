# Changelog

## 0.8.0-beta.2

### Feature highlights

### Added

* feat: add backend for new user/group column @enjeck [#1090](https://github.com/nextcloud/tables/pull/1090)
* Analytics: permission error on shared tables with non-shared views @Rello [#1248](https://github.com/nextcloud/tables/pull/1248)
* feat: User/group column frontend @enjeck [#1113](https://github.com/nextcloud/tables/pull/1113)

### Fixed

* fix: Make user listing in table more consistent by using NcUserBubble @juliushaertl [#1254](https://github.com/nextcloud/tables/pull/1254)
* fix: Navigation table entries UI issues @enjeck [#1195](https://github.com/nextcloud/tables/pull/1195)
* fix(files): fix appstore-build-publish.yml @JuliaKirschenheuter [#1244](https://github.com/nextcloud/tables/pull/1244)

### Dependencies

* fix(deps): update tiptap to ^2.5.7 (main) @renovate [#1245](https://github.com/nextcloud/tables/pull/1245)
* fix(deps): update dependency @nextcloud/vue to ^8.15.0 (main) @renovate [#1246](https://github.com/nextcloud/tables/pull/1246)

## 0.8.0-beta.1

### Feature highlights

### Added

- enh(Contexts): set active navigation entry @blizzz [#1037](https://github.com/nextcloud/tables/pull/1037)
- enh(App): navigation tweaks for Contexts @blizzz [#1080](https://github.com/nextcloud/tables/pull/1080)
- Enhancement/279 pagination for tables ui  @grnd-alt [#972](https://github.com/nextcloud/tables/pull/972)
- feat: submit row details with ctrl + enter @luka-nextcloud [#1112](https://github.com/nextcloud/tables/pull/1112)
- add table_id index to oc_tables_columns @grnd-alt [#1078](https://github.com/nextcloud/tables/pull/1078)
- ci(integration): test against context sharing @blizzz [#1129](https://github.com/nextcloud/tables/pull/1129)
- fix: pass view as prop to EmptyView @enjeck [#1147](https://github.com/nextcloud/tables/pull/1147)
- enh(API): add OCS API to create rows @blizzz [#1161](https://github.com/nextcloud/tables/pull/1161)
- feat: Add events for row added and row updated @come-nc [#1101](https://github.com/nextcloud/tables/pull/1101)
- feat(import): change column format during import @luka-nextcloud [#944](https://github.com/nextcloud/tables/pull/944)
- add scheme import and export @grnd-alt [#1170](https://github.com/nextcloud/tables/pull/1170)
- feat: update error handling during import @luka-nextcloud [#1091](https://github.com/nextcloud/tables/pull/1091)
- enh: gitignore Cypress download folder @enjeck [#1144](https://github.com/nextcloud/tables/pull/1144)
- enh: Consolidate user/group search code @enjeck [#1025](https://github.com/nextcloud/tables/pull/1025)
- enh: add context e2e tests @enjeck [#1149](https://github.com/nextcloud/tables/pull/1149)

### Fixed

- perf: Avoid extra queries to get the view ownership @juliushaertl [#1062](https://github.com/nextcloud/tables/pull/1062)
- fix(DB): update tables_row_sleeves' sequence after migration @blizzz [#1049](https://github.com/nextcloud/tables/pull/1049)
- perf: Make cache usable for unfavorited entries @juliushaertl [#1063](https://github.com/nextcloud/tables/pull/1063)
- enh: Delete Application and its shares @enjeck [#1026](https://github.com/nextcloud/tables/pull/1026)
- fix(DB): fetch pageId as int @blizzz [#1083](https://github.com/nextcloud/tables/pull/1083)
- fix: reduce templates requests @luka-nextcloud [#1098](https://github.com/nextcloud/tables/pull/1098)
- fix #1099 cosmetic bug: 3 typos `throw Error('Form ' + form + ' does no exist')` need correction @kirisakow [#1102](https://github.com/nextcloud/tables/pull/1102)
- fix(Context): do not show hidden columns @blizzz [#1092](https://github.com/nextcloud/tables/pull/1092)
- fix(Controller): remove unneeded endpoints @blizzz [#1130](https://github.com/nextcloud/tables/pull/1130)
- fix(View): 'manageTable' array key is not always set @blizzz [#1136](https://github.com/nextcloud/tables/pull/1136)
- fix: hide Create Column button on empty table if inadequate permissions @enjeck [#1151](https://github.com/nextcloud/tables/pull/1151)
- fix: add aria-label to NcSelect @enjeck [#1148](https://github.com/nextcloud/tables/pull/1148)
- fix(API): declared array shape was not correct @blizzz [#1169](https://github.com/nextcloud/tables/pull/1169)
- Extract selection option labels for Analytics @Rello [#877](https://github.com/nextcloud/tables/pull/877)
- fix(Backend): use object over loose array for permissions @blizzz [#1173](https://github.com/nextcloud/tables/pull/1173)
- fix(api): Fix wrong array type @provokateurin [#1205](https://github.com/nextcloud/tables/pull/1205)
- fix: remove View filter with null column @enjeck [#1199](https://github.com/nextcloud/tables/pull/1199)
- fix(View): column might be saved as null @blizzz [#1196](https://github.com/nextcloud/tables/pull/1196)
- fix(l10n): grammar fixes for table char limits @roliverio [#1084](https://github.com/nextcloud/tables/pull/1084)
- fix: broken sort by date @luka-nextcloud [#1110](https://github.com/nextcloud/tables/pull/1110)
- fix: insert context startpage for proper update @enjeck [#1146](https://github.com/nextcloud/tables/pull/1146)
- fix: only add resource if user can manage resource @enjeck [#1160](https://github.com/nextcloud/tables/pull/1160)
- fix(files): fix width, background of table row and interval between table header @JuliaKirschenheuter [#1220](https://github.com/nextcloud/tables/pull/1220)
- fix(files): align icon to center @JuliaKirschenheuter [#1219](https://github.com/nextcloud/tables/pull/1219)
- fix(files): remove unneeded extra place for the rows @JuliaKirschenheuter [#1234](https://github.com/nextcloud/tables/pull/1234)
- fix: modify context nodes update @enjeck [#1178](https://github.com/nextcloud/tables/pull/1178)
- fix: flaky Cypress tests @enjeck [#1204](https://github.com/nextcloud/tables/pull/1204)
- fix(Capabilities): announce API 2.0 @blizzz [#1215](https://github.com/nextcloud/tables/pull/1215)
- fix(files): remove pagination of there is just one page @JuliaKirschenheuter [#1218](https://github.com/nextcloud/tables/pull/1218)
- Align button to the left side @JuliaKirschenheuter [#1240](https://github.com/nextcloud/tables/pull/1240)
- use a built-in JS function `localeCompare()` to compare strings @kirisakow [#1141](https://github.com/nextcloud/tables/pull/1141)

### Dependencies

- fix(deps): update dependency @nextcloud/axios to ^2.5.0 (main) @renovate[bot] [#1053](https://github.com/nextcloud/tables/pull/1053)
- fix(deps): update dependency @nextcloud/l10n to ^3.0.1 (main) @renovate[bot] [#1057](https://github.com/nextcloud/tables/pull/1057)
- fix(deps): update dependency @nextcloud/l10n to v3 (main) @renovate[bot] [#1055](https://github.com/nextcloud/tables/pull/1055)
- chore(deps): update dependency @nextcloud/stylelint-config to v3 (main) @renovate[bot] [#1054](https://github.com/nextcloud/tables/pull/1054)
- fix(deps): update dependency @nextcloud/l10n to ^3.1.0 (main) @renovate[bot] [#1087](https://github.com/nextcloud/tables/pull/1087)
- fix(deps): update tiptap to ^2.3.2 (main) @renovate[bot] [#1086](https://github.com/nextcloud/tables/pull/1086)
- chore(deps): update dependency @nextcloud/babel-config to ^1.2.0 (main) @renovate[bot] [#1093](https://github.com/nextcloud/tables/pull/1093)
- chore(deps): update dependency @nextcloud/eslint-config to ^8.4.1 (main) @renovate[bot] [#1094](https://github.com/nextcloud/tables/pull/1094)
- fix(deps): update dependency @nextcloud/event-bus to ^3.3.0 (main) @renovate[bot] [#1095](https://github.com/nextcloud/tables/pull/1095)
- fix(deps): update tiptap to ^2.4.0 (main) @renovate[bot] [#1096](https://github.com/nextcloud/tables/pull/1096)
- chore(deps): update dependency openapi-typescript to ^6.7.6 (main) @renovate[bot] [#1106](https://github.com/nextcloud/tables/pull/1106)
- fix(deps): update dependency debounce to ^2.1.0 (main) @renovate[bot] [#1108](https://github.com/nextcloud/tables/pull/1108)
- fix(deps): update dependency @vueuse/core to ^10.10.0 (main) @renovate[bot] [#1115](https://github.com/nextcloud/tables/pull/1115)
- fix(deps): update dependency @nextcloud/event-bus to ^3.3.1 (main) @renovate[bot] [#1114](https://github.com/nextcloud/tables/pull/1114)
- chore(deps): Update openapi-extractor @provokateurin [#1116](https://github.com/nextcloud/tables/pull/1116)
- fix(deps): update dependency @vueuse/core to ^10.11.0 (main) @renovate[bot] [#1142](https://github.com/nextcloud/tables/pull/1142)
- chore(deps): update dependency cypress-downloadfile to ^1.2.4 (main) @renovate[bot] [#1162](https://github.com/nextcloud/tables/pull/1162)
- chore(deps): update dependency openapi-typescript to v7 (main) @renovate[bot] [#1163](https://github.com/nextcloud/tables/pull/1163)
- fix(deps): update dependency @nextcloud/vue to ^8.14.0 (main) @renovate[bot] [#1085](https://github.com/nextcloud/tables/pull/1085)
- chore(deps): update dependency openapi-typescript to ^7.0.2 (main) @renovate[bot] [#1185](https://github.com/nextcloud/tables/pull/1185)
- chore: update workflows from templates @skjnldsv [#1200](https://github.com/nextcloud/tables/pull/1200)
- chore(deps): update dependency openapi-typescript to ^7.1.0 (main) @renovate[bot] [#1222](https://github.com/nextcloud/tables/pull/1222)

### Other

- ci(integration): tests against context deletion @blizzz [#1042](https://github.com/nextcloud/tables/pull/1042)
- test(cypress): fix month names @blizzz [#1119](https://github.com/nextcloud/tables/pull/1119)
- ci(integration): tests against context updates @blizzz [#1072](https://github.com/nextcloud/tables/pull/1072)
- test(integration): cases against context transfer ownership @blizzz [#1124](https://github.com/nextcloud/tables/pull/1124)
- ci(integration): add more share-related tests @blizzz [#1137](https://github.com/nextcloud/tables/pull/1137)
- ci: add missing server branches to matrices @blizzz [#1168](https://github.com/nextcloud/tables/pull/1168)
- chore: Drop Nextcloud 26 for the next release as it is EOL @juliushaertl [#1179](https://github.com/nextcloud/tables/pull/1179)
- build(openapi): fix ms typescript generation command @blizzz [#1221](https://github.com/nextcloud/tables/pull/1221)

## 0.7.1

### Fixed

- Fix filters for meta columns @backportbot[bot] [#1028](https://github.com/nextcloud/tables/pull/1028)
- fix(filter): pass datetime object for PARAM_DATE @backportbot[bot] [#1024](https://github.com/nextcloud/tables/pull/1024)

### Other

- test(Integration): add contexts related tests @backportbot[bot] [#1030](https://github.com/nextcloud/tables/pull/1030)

## 0.7.0

### Feature highlights

- Support for Nextcloud 29
- Applications: Build custom Tables apps
- Navigation improvements: Favorite and archive tables
- Add description to tables
- File action to import to tables
- Import files by upload and improved column detection
- Write critical operations to the audit log
- Add print stylesheets

### Added

- feat(Context): add share logic for contexts @blizzz [#962](https://github.com/nextcloud/tables/pull/962)
- Contexts @blizzz [#848](https://github.com/nextcloud/tables/pull/848)
- feat: Transfer context ownership @enjeck [#945](https://github.com/nextcloud/tables/pull/945)
- changed view filter reset icon (issue #884) @elzody [#889](https://github.com/nextcloud/tables/pull/889)
- feat: Add backend for table archive and favorite flag @juliushaertl [#865](https://github.com/nextcloud/tables/pull/865)
- Import: File action to import from Files to Tables @elzody [#890](https://github.com/nextcloud/tables/pull/890)
- Add text description to tables @grnd-alt [#932](https://github.com/nextcloud/tables/pull/932)
- Add print styles @FahrJo [#931](https://github.com/nextcloud/tables/pull/931)
- Write critical operations to the audit log @hweihwang [#959](https://github.com/nextcloud/tables/pull/959)
- enh: some design review tasks @enjeck [#961](https://github.com/nextcloud/tables/pull/961)
- feat: add vertical column lines to Tables @enjeck [#960](https://github.com/nextcloud/tables/pull/960)

### Fixed

- fix(Permissions): check against user provided in args @blizzz [#885](https://github.com/nextcloud/tables/pull/885)
- Polish table header and text column rendering @juliushaertl [#883](https://github.com/nextcloud/tables/pull/883)
- Match default values when querying views @juliushaertl [#900](https://github.com/nextcloud/tables/pull/900)
- fix: properly indent child views of archived tables @elzody [#909](https://github.com/nextcloud/tables/pull/909)
- fix: views respect sorting @enjeck [#912](https://github.com/nextcloud/tables/pull/912)
- fix: Focus first input when creating  a new table/row/column/view @juliushaertl [#925](https://github.com/nextcloud/tables/pull/925)
- fix: reintroduce content reference provider @elzody [#943](https://github.com/nextcloud/tables/pull/943)
- fix(api): ContextMapper now coerces the row ID to an int for proper comparison @elzody [#954](https://github.com/nextcloud/tables/pull/954)
- fix(Contexts): explicitely state non-int types @blizzz [#964](https://github.com/nextcloud/tables/pull/964)
- fix(OpenApi): resolve errors and generate openapi file @blizzz [#919](https://github.com/nextcloud/tables/pull/919)
- fix: limit min and max numbers @enjeck [#963](https://github.com/nextcloud/tables/pull/963)
- fix(i18n): Fixed grammar @rakekniven [#990](https://github.com/nextcloud/tables/pull/990)
- fix: Vacation Request template @enjeck [#979](https://github.com/nextcloud/tables/pull/979)

### Other

- Dependency updates
- chore: Remove legacy reference provider @juliushaertl [#924](https://github.com/nextcloud/tables/pull/924)

## 0.7.0-beta.3 - 2024-03-27

### Added

- feat: add print styles @FahrJo [#931](https://github.com/nextcloud/tables/pull/931)
- feat: Contexts (Applications) @blizzz @enjeck @elzody [#848](https://github.com/nextcloud/tables/pull/848)

### Updated

- fix: shared views can be favorited @elzody [#921](https://github.com/nextcloud/tables/pull/921)
- fix: views respect sorting @enjeck [#912](https://github.com/nextcloud/tables/pull/912)
- fix: reintroduce content reference provider @elzody [#943](https://github.com/nextcloud/tables/pull/943)
- fix: Focus first input when creating a new table/row/column/view @juliushaertl [#925](https://github.com/nextcloud/tables/pull/925)


## 0.7.0-beta.2

### Added

- enh: Use table id for events @enjeck [#808](https://github.com/nextcloud/tables/pull/808)
- node type constants and convenience methods to check access and manage permissions @blizzz [#878](https://github.com/nextcloud/tables/pull/878)
- feat: implement direct file upload @luka-nextcloud [#845](https://github.com/nextcloud/tables/pull/845)
- feat: autodetect data type during import @luka-nextcloud [#854](https://github.com/nextcloud/tables/pull/854)
- changed view filter reset icon (issue #884) @elzody [#889](https://github.com/nextcloud/tables/pull/889)
- feat: Add backend for table archive and favorite flag @juliushaertl [#865](https://github.com/nextcloud/tables/pull/865)
- Feat: File action to import from Files to Tables @elzody [#890](https://github.com/nextcloud/tables/pull/890)

### Fixed

- fix: properly delete rows in views @enjeck [#826](https://github.com/nextcloud/tables/pull/826)
- fix: Pass all table columns along as we need them for filtering the rows @juliushaertl [#828](https://github.com/nextcloud/tables/pull/828)
- enh: use required name prop for NcAppSettingsSection @enjeck [#840](https://github.com/nextcloud/tables/pull/840)
- fix: Only skip failing table data loading instead of full failure @juliushaertl [#846](https://github.com/nextcloud/tables/pull/846)
- fix: Make table header sticky @juliushaertl [#786](https://github.com/nextcloud/tables/pull/786)
- fix: Apply fill to path for svg app icon @juliushaertl [#851](https://github.com/nextcloud/tables/pull/851)
- Fix hide user status from owner avatar (issue #829) @elzody [#853](https://github.com/nextcloud/tables/pull/853)
- fix: Avoid sql error on postgres @juliushaertl [#859](https://github.com/nextcloud/tables/pull/859)
- fix: Filter returned entity result by view columns @juliushaertl [#881](https://github.com/nextcloud/tables/pull/881)
- fix: Catch any error that may occur during row import @juliushaertl [#882](https://github.com/nextcloud/tables/pull/882)
- fix(Permissions): check against user provided in args @blizzz [#885](https://github.com/nextcloud/tables/pull/885)
- Polish table header and text column rendering @juliushaertl [#883](https://github.com/nextcloud/tables/pull/883)
- Match default values when querying views @juliushaertl [#900](https://github.com/nextcloud/tables/pull/900)
- fix: properly indent child views of archived tables @elzody [#909](https://github.com/nextcloud/tables/pull/909)

### Dependencies

- fix(deps): update dependency @nextcloud/dialogs to ^4.2.5 @renovate[bot] [#834](https://github.com/nextcloud/tables/pull/834)
- fix(deps): update tiptap to ^2.2.2 @renovate[bot] [#835](https://github.com/nextcloud/tables/pull/835)
- fix(deps): update tiptap to ^2.2.3 @renovate[bot] [#856](https://github.com/nextcloud/tables/pull/856)
- fix(deps): update dependency @nextcloud/vue to ^8.6.2 @renovate[bot] [#855](https://github.com/nextcloud/tables/pull/855)
- fix(deps): update dependency @nextcloud/vue to ^8.7.0 @renovate[bot] [#860](https://github.com/nextcloud/tables/pull/860)
- fix(deps): update dependency @nextcloud/vue to ^8.7.1 @renovate[bot] [#874](https://github.com/nextcloud/tables/pull/874)
- fix(deps): update dependency @nextcloud/dialogs to ^4.2.6 @renovate[bot] [#873](https://github.com/nextcloud/tables/pull/873)
- fix(deps): update tiptap to ^2.2.4 @renovate[bot] [#875](https://github.com/nextcloud/tables/pull/875)
- fix(deps): update dependency @vueuse/core to ^10.9.0 @renovate[bot] [#876](https://github.com/nextcloud/tables/pull/876)
- fix(deps): update dependency debounce to v2 @renovate[bot] [#700](https://github.com/nextcloud/tables/pull/700)
- fix(deps): update dependency @nextcloud/vue to ^8.8.1 @renovate[bot] [#891](https://github.com/nextcloud/tables/pull/891)

### Other

- fix: properly delete tables with filtered view @enjeck [#842](https://github.com/nextcloud/tables/pull/842)
- Update PHP options in issue template @blizzz [#850](https://github.com/nextcloud/tables/pull/850)
- perf: Optimize lazy imports for reference widgets @juliushaertl [#825](https://github.com/nextcloud/tables/pull/825)
- fix(meta): drop 25 support @blizzz [#886](https://github.com/nextcloud/tables/pull/886)
- Cypress test: delete table with view @enjeck [#899](https://github.com/nextcloud/tables/pull/899)
- cypress testing for archive/favorite @elzody [#901](https://github.com/nextcloud/tables/pull/901)

## 0.7.0-beta.1 - 2024-01-29
### Added
- New database structure: https://github.com/nextcloud/tables/pull/749
- Filtering in views for multi selection: https://github.com/nextcloud/tables/pull/798
- API v2 and docs in API viewer
- icon replaced
- occ commands for manual data transfer and cleanup

### Updated
- Update software dependencies

## 0.6.6
### Updated
- Fix number column issues [#784](https://github.com/nextcloud/tables/pull/784)
- fix: add display names to all column meta data printouts [#785](https://github.com/nextcloud/tables/pull/785)
- Avoid failures with link column database values from previous versions [#780](https://github.com/nextcloud/tables/pull/780)
- fix(sorting): handle NaN results if the values are empty [#757](https://github.com/nextcloud/tables/pull/757)

## 0.6.5 - 2023-12-18
### Upgraded
- 🏳️ Translations
- 🐞 Bug fixing
  - https://github.com/nextcloud/tables/pull/744
  - https://github.com/nextcloud/tables/pull/735

## 0.6.4 - 2023-11-24
### Updated
- 🐞 Bug fixing

## 0.6.2 - 2023-11-13
### Updated
- 🐞 Bug fixing

## 0.6.1 - 2023-11-07
### Updated
- 🏳️ Translations
- 🐞 Bug fixing
- ✨ Small design adjustments
- 💾 Update software dependencies

## 0.6.0 - 2023-09-15
### Added
- ⚗️ Add views to tables
- 🤝 Share views individually
- 🛠️ Adjust views with filters, sorting and column selection and ordering
- 🔗 Link to any Nextcloud resource like files, pictures, contacts, deck-cards, etc.
- 📇 New smart picker integrations and link previews
- 🤹 Insert dynamic tables directly into any Nextcloud text editor

### Updated
- 🏳️ Translations
- 🐞 Bug fixing
- ✨ Small design adjustments
- 💾 Update software dependencies

## 0.6.0-beta.4 - 2023-09-14
### Added
- ⚗️ Add views to tables
- 🤝 Share views individually
- 🛠️ Adjust views with filters, sorting and column selection and ordering
- 🔗 Link to any Nextcloud resource like files, pictures, contacts, deck-cards, etc.
- 📇 New smart picker integrations and link previews
- 🤹 Insert dynamic tables directly into any Nextcloud text editor

### Updated
- 🏳️ Translations
- 🐞 Bug fixing
- ✨ Small design adjustments
- 💾 Update software dependencies

## 0.6.0-beta.3 - 2023-09-12
### Added
- ⚗️ Add views to tables
- 🤝 Share views individually
- 🛠️ Adjust views with filters, sorting and column selection and ordering
- 🔗 Link to any Nextcloud resource like files, pictures, contacts, deck-cards, etc.
- 📇 New smart picker integrations and link previews
- 🤹 Insert dynamic tables directly into any Nextcloud text editor

### Updated
- 🏳️ Translations
- 🐞 Bug fixing
- ✨ Small design adjustments
- 💾 Update software dependencies

## 0.6.0-beta.2 - 2023-09-08
### Added
- ⚗️ Add views to tables
- 🤝 Share views individually
- 🛠️ Adjust views with filters, sorting and column selection and ordering
- 🔗 Link to any Nextcloud resource like files, pictures, contacts, deck-cards, etc.
- 📇 New smart picker integrations and link previews
- 🤹 Insert dynamic tables directly into any Nextcloud text editor

### Updated
- 🏳️ Translations
- 🐞 Bug fixing
- ✨ Small design adjustments
- 💾 Update software dependencies

## 0.6.0-beta.1 - 2023-08-11
### Added
- ⚗️ Add views to tables
- 🤝 Share views individually
- 🛠️ Adjust views with filters, sorting and column selection and ordering
- 🔗 Link to any Nextcloud resource like files, pictures, contacts, deck-cards, etc.
- 📇 New smart picker integrations and link previews
- 🤹 Insert dynamic tables directly into any Nextcloud text editor

### Updated
- 🏳️ Translations
- 🐞 Bug fixing
- ✨ Small design adjustments
- 💾 Update software dependencies

## 0.5.1 - 2023-06-12
### Added
- 💻 OCC command to clean up row data
- ✨ Filter for empty cells

### Updated
- 🏳️ Translations
- 🐞 A lot of bug fixing
- ✨ Small design adjustments
- 💾 Update software dependencies

## 0.5.0 - 2023-05-14
### Updated
- Fix bugs
- Update translations
- Update software dependencies
- Accessibility improvements

### Added
- 🔍 Search and filter in tables
- ↕️ Sorting
- ✨ New column type "rich text", using Nextclouds default text editor
- ✨ New column type "multi selection" and "selection"
- ⤵️ Import tables
- 🛜 Serve some capabilities information

## 0.4.0

## 0.3.2 - 2023-03-15
### Updated
- Add hints for translation #152
- Fix bugs around show or hide options on shared tables
- Fix and update npm dependencies

## 0.3.1 - 2023-02-28
### Updated
- Clean up navigation UI
- Fix bug that loads wrong template "Customers"
- updated translations

## 0.3.0 - 2023-02-27
### Added
- Read data via API
- Filter tables in navigation
- 2 new templates and template dummy data
- emoji for tables
- render widget links in rich text editor
- search integration
- reference provider for NC26

### Updated
- Table component replaced
- code cleanup
- initial CI setup
- UI and UX improvements
- translations

### Removed
- Some functions are not replaced due to the replacement of the table component (paste data, instant sorting and filtering)

## 0.2.2 - 2023-01-10
### Updated
- translation
- npm dependencies
- some UI fixes for Nextcloud 25 support
- Updated app meta data

## 0.2.1 - 2022-09-27
### Updated
- translation
- npm dependencies
- small UX enhancements

## 0.2.0 - 2022-07-10
### Added
- sharing options for users and groups

### Updated
- translations
- npm dependencies

## 0.1.2 - 2022-04-28
### Updated
- many css fixes
- css cleanup
- minor bug fixes
- translations
- Nextcloud 24 compatible

## 0.1.1 - 2022-04-04
### Updated
- many css fixes
- css cleanup
- minor bug fixes
- translations

## 0.1.0 - 2022-03-26
### Added
- Start page
- translations
- import data from clipboard
- column long text has now a markdown editor
- new template "weight tracking"
- npm updates
- many fixes and small improvements

## 0.0.2 - 2022-03-16
### Added
- this changelog
- screenshots for the App Store
- description texts

## 0.0.1 - 2022-03-16
### Added
- Initial beta version
