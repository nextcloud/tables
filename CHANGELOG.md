# Changelog

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
