# Changelog

All Notable changes to `Backpack CRUD` will be documented in this file

## NEXT - YYYY-MM-DD

### Added
- Nothing

### Deprecated
- Nothing

### Fixed
- Nothing

### Removed
- Nothing

### Security
- Nothing
-----------

## [3.4.9] - 2018-05-xx

## Fixed
- #1378 - when a custom default page length is specified, it should show up in the page length menu;


## [3.4.8] - 2018-05-07

## Fixed
- better pt_br translation; merged #1368;
- translated name for File Manager sidebar item; merged #1369;


## [3.4.7] - 2018-05-07

## Fixed
- fixed #1364 merged #1306 - datatables javascript issue in IE11;


## [3.4.6] - 2018-04-23

## Fixed
- added TD around columns in preview, to fix it; merges #1344;
- not showing "Remove filters" button when no filter is applied; merges #1343;

## [3.4.5] - 2018-04-17

## Fixed
- getting the correct current id for nested resources; fixes #1323; fixes #252; merges #1339;
- #1321 - setting locale for traversable items; merges #1330;
- LV translation, thanks to @tomsb; merges #1358;

## [3.4.4] - 2018-03-29

## Fixed
- ckeditor button now showing after js update; merges #1310; fixes #1309;


## [3.4.3] - 2018-03-28

## Fixed
- model_function column HTML was escaped;


## [3.4.2] - 2018-03-23

## Fixed
- CrudPanelCreateTest failing 1 test;


## [3.4.1] - 2018-03-22

## Fixed
- HUGE ERROR whereby entities could not be created if they had zero relationships;


## [3.4.0] - 2018-03-22

## Added
- one-line installation command ```php artisan backpack:crud:install```;
- 1-1 relatiosnhips; merges #865;

## Fixed
- ```checkbox``` field was using the default value over the DB value on edit; merges #1239;
- no longer registering Base, Elfinder and Image service providers and aliases, since they all now use auto-load; merges #1279;
- datatables responsive working with colvis and export buttons;

### Removed
- elFinder is no longer a dependency; users should require it themselves, if they need it;

-----------

## [3.3.17] - 2018-03-21

## Fixed
- changed Sluggable traits declarations to PHP 7+; merges #1084;


## [3.3.16] - 2018-03-21

## Added
- JSON response if the create/update action is triggered through AJAX; merges #1249;
- ```view``` filter type and ```view``` column type;

## Fixed
- Romanian translation;
- image field did not show proper image if validation failed; merges #1294;

## [3.3.15] - 2018-03-21

## Fixed
- ```select2_multiple``` filter triggered an error when the entire selection was removed - merges #824;
- fr translation;
- zh-hant translation;


## [3.3.14] - 2018-03-16

## Added
- ```select_all``` option to the ```select2_multiple``` field - merged #1206;
- ```browse_multiple``` field type, thanks to [chancezeus](https://github.com/chancezeus) - merged #1034;

## Fixed
- ```date_range``` filter methods now have custom names, so that more than one ```date_range``` filter can be included in one CRUD list;
- Romanian translation;
- Create/Update form will not show certain buttons, if that operation is disabled - merged #679;

## [3.3.13] - 2018-03-15

## Fixed
- ```checkbox``` field was using the default value over the DB value on edit; merges #1239;
- CrudTrait uses ```Config``` facade to get DB_CONNECTION instead of ```env()``` helper;
- Fake fields can now be casted, as well as 'extras' - merged #1116;

## [3.3.12] - 2018-03-09

## Fixed
- ```text``` column had a broken ```suffix``` attribute; fixed by merging #1261;
- not calling trans() in the config file; merges #1270;

## [3.3.11] - 2018-02-23

## Added
- ```allows_null``` option to ```datetime_picker``` field type;
- #1099 - added ```$this->crud->setPageLengthMenu();``` API call;
- added ```config('backpack.crud.page_length_menu')``` config variable;
- ```summernote``` field ```options``` parameter, for easy customization;
- probot to automatically invite contributors to the ```Community Members``` team, after their first PR gets merged;
- ```default``` option to ```select_from_array``` and ```select2_from_array``` field types; merges #1168;
- ```disk``` option to ```image``` field type;

## Fixed
- click on a column header now ignores the previous ```orderBy``` rules; fixes #1181; merges #1246;
- ```date_range``` field bug, whereby it threw a ```Cannot redeclare formatDate()``` exception when two fields of this type were present in one form; merges #1240;
- ```image``` column type didn't use the prefix for the image link; merges #1174;
- no broken image on ```image``` field type, when no image is present; merges #444;

## [3.3.10] - 2018-02-21

## Added
- ```number``` column type, with prefix, suffix and decimals options;
- prefix, suffix and limit to ```text``` column type;
- setLabeller($callable) method to change how labels are made; merges #688;
- support Github probot that automatically closes issues tagged ```Ask-It-On-Stack-Overflow```, writes a nice redirect message and gives them the proper link;

## Fixed
- #638 and #1207 - using flexbox for equal height rows for prettier inline errors;


## [3.3.9] - 2018-02-14

### Added
- (Github only) probot auto-replies for first issue, first PR and first PR merged;

## Fixed
- double-click on create form created two entries; fixes #1229;

### Deprecated
- CrudRequest; Since it does nothing, CrudController now extends Illuminate\Http\Request instead; merged #1129; fixes #1119;

## [3.3.8] - 2018-02-08

## Removed
- laravelcollective/html dependecy;


## [3.3.6] - 2018-01-16

## Fixed
- base64_image field triggered an error when using the src parameter - merged #1192;


## [3.3.5] - 2018-01-10

## Added
- custom error message for AJAX datatable errors - merged #1100; 
- 403 error on AccessDeniedException;

### Fixed
- CRUD alias is now loaded using package-autodiscovery instead of manually in CrudServiceProvider;
- datatables ajax loading screen was askew when also using export buttons;


## [3.3.4] - 2017-12-19

## Fixed
- ENUM field - Updated ```getPossibleEnumValues``` to use ```$instance->getConnectionName()``` so that enum values are correctly queried when the Model uses a non-default database connection - merged #650;
- addColumn will not overwrite the searchLogic, orderable and tableColumn attributes if otherwise specified;
- Better sorting effect on "table" fields - merged #466;
- When using the Autoset trait, the getDbColumnTypes() method used many separate queries to get the column type and column default; improved performance by merging #1159;
- fakeFields use array_keys_exists instead of isset - merged #734;
- CrudTrait::addFakes now supports objects - merged #1109;


## [3.3.3] - 2017-12-14

## Fixed
- Chinese translation;
- datetimepicker icon now triggers datetimepicker js - merged #1097;
- columns are now picked up using the database connection on the model - merged #1141; fixes #1136;
- model_function buttons now work for top and bottom stacks too - fixes #713;

## [3.3.2] - 2017-12-12

## Added
- loading image on ajax datatables, with fallback to old "Processing" text;

## Fixed
- answers to hasColumns() are now cached, to minimize number of db queries on list view - merged #1122;
- German translation;


## [3.3.1] - 2017-11-06

## Fixed
- unit tests for column key functionality;


## [3.3.0] - 2017-11-06

## Added
- you can now define a "key" for a column, if you need multiple columns with the same name;

## Fixed
- in create/update, fields without a tab are displayed before all tabs;
- unit tests now use PHPUnit 6;
- completely rewritten AjaxTables functionality;
- fixed all AjaxTables issues - merged #710;

### Deprecated
- ```$this->crud->enableAjaxTable();``` still exists for backwards-compatibility, but has been deprecated and does nothing;

### Removed
- DataTables PHP dependency;
- all tables now use AjaxTables; there is no classic tables anymore; 
- removed all classic table filter fallbacks;

-----------

## [3.2.27] - 2017-11-06

## Fixed
- inline validation on nested attributes - merged #987, fixes #986;
- morphed entities caused records in the pivot table to duplicate - merged #772, fixes #369;
- browse field used slash instead of backslash on windows - fixes #496;
- endless loop when using date_range filter - merged #1092;


## [3.2.26] - 2017-10-25

## Added
- prefix option to upload field type;

## Fixed
- when creating an entry, pivot fields were overwriting the $field variable - merged #1046;
- Italian translation file;
- select fields old data values;
- date_range field triggered error on Create;
- bug where non-translatable columns in translatable models got their $guarded updated - merged #754;


## [3.2.25] - 2017-10-24

## Added
- number of records per page menu now features "All", so people can use it before exporting results when using AjaxDataTables;
- prefix option for the image column (merged #1056; fixes #1054);


## [3.2.24] - 2017-10-23

## Fixed
- daterange field did not use the correct value if the start_date and end_date were not casted in the model - merged #1036;
- PR #1015 - fixes #798 - fixed field order methods;
- PR #1011 - fixes #982 and #971 - fixed column order methods;
- radio column not showing value - PR #1023;

## [3.2.23] - 2017-10-16

## Added
- Added config option to choose if the save actions changed bubble will be shown;

## Fixed
- lv language file spelling error;


## [3.2.22] - 2017-09-30

## Fixed
- date_picker initial display value offset - PR #767, fixes #768;
- unit test badge from Scrutinizer reported a wrong coverage %;


## [3.2.21] - 2017-09-28

## Added
- clear button to select2_from_ajax field type;
- autoSet is now using the database defaults, if they exist;
- cleaner preview page, which shows the db columns using the list columns (big thanks to [AbbyJanke](https://github.com/AbbyJanke));
- if a field has the required attribute, a red start will show up next to its label;
- shorthand method for updating field and column labels - setColumnLabel() and setFieldLabel();
- select_from_array column type;
- image column type;

## Fixed
- bug where you couldn't remove the last row of a table field;
- Switching from using env() call to config() call to avoid issues with cache:config as mentioned in issue #753;


## [3.2.20] - 2017-09-27

## Added
- UNIT TESTS!!! I KNOW, RIGHT?!
- fourth parameter to addFilter method, that accepts a fallback logic closure;
- ability to make columns non-orderable using the DataTables "orderable" parameter;

## Fixed
- zh-cn instead of zh-CN language folder - fixes #849;
- can't move a column before/after an inexisting column;
- can't move a field before/after an inexisting field;
- fixed beforeField() and afterField() methods;
- fixed beforeColumn() and afterColumn() methods;
- calling setModel() more than once now resets the entry;
- you can now store a fake field inside a column with the same name (ex: extras.extras);
- boolean column values can now be HTML;
- select2 filter clear button now works with ajax datatables;
- select2_from_ajax_multiple field old values fix;
- CrudTrait::isColumnNullabel support for json and jsonb columns in postgres;
- form_save_buttons had an untranslated string;
- deprecated unused methods in CrudPanel;


## [3.2.19] - 2017-09-05

## Added
- text filter type;

## Fixed
- date_range field start_name value always falled back to default - #450;
- hidden field types now have no height - fixes #555;
- image field type can now be modified in size - fixes #572;
- we were unable to save model with optional fake fields - fixes #616;

## [3.2.18] - 2017-08-30

## Added
- Package autodiscovery for Laravel 5.5;


## [3.2.17] - 2017-08-22

## Fixed
- SluggableScopeHelpers::scopeWhereSlug() signature, thanks to [Pascal VINEY](https://github.com/shaoshiva);


## [3.2.16] - 2017-08-21

## Added
- translation strings for CRUD export buttons, thanks to [Alashow](https://github.com/alashow);

## Fixed
- you can now skip mentioning the model for relation fields and columns (select, select2, select2multiple, etc) - it will be picked up from the relation automatically;


## [3.2.15] - 2017-08-11

## Added
- Danish (da_DK) language files, thanks to [Frederik Rabøl](https://github.com/Xayer);


## [3.2.14] - 2017-08-04

## Added
- Brasilian Portugese translation, thanks to [Guilherme Augusto Henschel](https://github.com/cenoura);
- $crud parameter to the model function that adds a button;

## Fixed
- setFromDb() now uses the column name as array index - so $this->crud->columns[id] instead of $this->crud->columns[arbitrary_number]; this makes afterColumn() and beforeColumn() work with setFromDb() too - #759;
- radio field type now has customizable attributes - fixes #718;
- model_function column breaking when not naming it - fixes #784;
- video column type uses HTTPs and no longer triggers console error - fixes #735;


## [3.2.13] - 2017-07-07

## Added
- German translation, thanks to [Oliver Ziegler](https://github.com/OliverZiegler);
- PHP 7.1 to TravisCI;

### Fixed
- resources loaded twice on tabbed forms - fixes #509;
- beforeColumn and afterColumn not working after setFromDb();
- afterField() always placing the field on the second position;
- date_range filter - clear button now works;
- select2 variants load the JS and CSS from CDN now to fix styling issues;
- show_fields error when no tabs on CRUD entity;

## [3.2.12] - 2017-05-31

### Added
- Latvian translation files (thanks to [Erik Bonder](https://github.com/erik-ropez));
- Russian translation files (thanks to [Aleksei Budaev](https://a-budaev.ru/));
- Dutch translation files (thanks to [Jelmer Visser](https://github.com/jelmervisser))

### Fixed
- allow for revisions by non-logged-in users; fixes #566;
- upgraded Select2 to the latest version, in all select2 fields;
- fixed select2_from_ajax_multiple;
- translated "edit translations" button;
- localize the filters navbar view;
- inline validation error for array fields;
- moved button initialization to CrudPanel constructor;
- pagelength bug; undoes PR #596;


## [3.2.11] - 2017-04-21

### Removed
- Backpack\CRUD no longer loads translations, as Backpack\Base does it for him.

## [3.2.10] - 2017-04-21

### Added
- prefix feature to the image field;

### Fixed
- select_multiple has allows_null option;
- details_row for AjaxDataTables;


## [3.2.9] - 2017-04-20

### Added
- email column type;

### Fixed
- fewer ajax requests when using detailsRow;
- redirect back to the same entry - fixed by #612;
- use "admin" as default elfinder prefix;
- datepicker error fixed by [Pavol Tanuška](https://github.com/pavoltanuska);
- simplemde field also triggered ckeditor when place before it, because of an extra class;
- details row column can be clicked entirely (thanks to [votintsev](https://github.com/votintsev));
- simpleMDE bug fixes and features #507 (thanks to [MarcosBL](https://github.com/MarcosBL));
- allow for dot notation when specifying the label of a reordered item (thanks to [Adam Kelsven](https://github.com/a2thek26));


## [3.2.8] - 2017-04-03

### Added
- fixed typo in saveAction functionality;
- checklist field had hardcoded primary key names;
- french translation for buttons;

## [3.2.7] - 2017-03-16

### Added
- Simplified Chinese translation - thanks to [Zhongwei Sun](https://github.com/sunzhongwei);
- date and date_range filters - thanks to [adriancaamano](https://github.com/adriancaamano);

### Fixed
- fixed horizontal scrollbar showing on list view;
- fixed edit and create extended CSS and JS files not loading;
- fixed AjaxDataTables + filters bug (encoded URL strings);
- replaced camel_case() with str_slug() in tab ids, to provide multibyte support;


## [3.2.6] - 2017-03-13

### Fixed
- custom created_at and updated_at columns threw errors on PHP 5.6;


## [3.2.5] - 2017-03-12

### Fixed
- SaveActions typo - fixes #504;
- Allow for custom created_at and updated_at db columns - fixes #518;
- base64_image field - preserve the original image format when uploading cropped image;
- fix bug where n-n relationship on CREATE only triggers error - fixes #512;
- reduce the number of queries when using the Tabs feature - fixes #461;


## [3.2.4] - 2017-02-24

### Fixed
- Spanish translation;
- Greek translation;
- select2_from_ajax, thanks to [MarcosBL](https://github.com/MarcosBL);
- Translatable "Add" button in table field view;

## [3.2.3] - 2017-02-14

### Fixed
- Spatie/Translatable fake columns had some slashed added to the json - fixes #442;


## [3.2.2] - 2017-02-13

### Fixed
- CrudTrait::getCastedAttributes();



## [3.2.1] - 2017-02-13

### Fixed
- removed a few PHP7 methods, so that PHP 5.6.x is still supported;


## [3.2.0] - 2017-02-13

### Added
- form save button better UI&UX: they have the options in a dropdown instead of radio buttons and the default behaviour is stored in the session upon change - thanks to [Owen Melbourne](https://github.com/OwenMelbz);
- redirect_after_save button actions;
- filters on list views (deleted the 3.1.41 and 4.1.42 tags because they were breaking changes);
- routes are now abstracted intro CrudRoute, so that new routes can be easily added;
- Greek translation (thanks [Stamatis Katsaounis](https://github.com/skatsaounis));
- tabbed create&update forms - thanks to [Owen Melbourne](https://github.com/OwenMelbz);
- grouped and inline errors - thanks to [Owen Melbourne](https://github.com/OwenMelbz);
- developers can now choose custom views per CRUD panel - thanks to [Owen Melbourne](https://github.com/OwenMelbz);
- select2_ajax and select2_ajax_multiple field types - thanks to [maesklaas](https://github.com/maesklaas);

### Fixed
- excluded _method from massAssignment, so create/update errors will be more useful;

## [3.1.60] - 2017-02-13

### Fixed
- select2_ajax and select2_ajax_multiple field types have been renamed to select2_from_ajax and select2_from_ajax_multiple for field naming consistency;


## [3.1.59] - 2017-02-13

### Added
- date_range field, thanks to [Owen Melbourne](https://github.com/OwenMelbz);
- select2_ajax and select2_ajax_multiple field types - thanks to [maesklaas](https://github.com/maesklaas);

### Fixed
- change the way the CrudPanel class is injected, so it can be overwritten more easily;
- simpleMDE field type - full screen fixed;


## [3.1.58] - 2017-02-10

### Added
- Bulgarian translation, thanks to [Petyo Tsonev](https://github.com/petyots);
- select2_from_array, thanks to [Nick Barrett](https://github.com/njbarrett);

### Fixed
- DateTime Picker error when date deleted after being set - fixes #386;
- Abstracted primary key in select_multiple column - fixes #377 and #412;
- AutoSet methods now using the connection on the model, instead of the default connection; This should allow for CRUDs from multiple databases inside one app; Big thanks to [Hamid Alaei Varnosfaderani](https://github.com/halaei) for this PR;
- Check that the Fake field is included in the request before trying to use it;


## [3.1.57] - 2017-02-03

### Added
- Laravel 5.4 compatibility;

### Fixed
- elfinder redirected to /login instead of /admin, because it used the "auth" middleware instead of "admin";


## [3.1.56] - 2017-02-03

### Fixed
- deleting a CRUD entry showed a warning;


## [3.1.55] - 2017-02-02

### Fixed
- allow custom primary key in field types base64_image and checklist_dependency;
- dropdown filter triggered separator on 0 index;
- make sure model events are triggered when deleting;
- in edit view, use the fields variable passed to the view;
- fix conflict bootstrap-datepicker & jquery-ui;
- fix "undefined index: disk" in upload field type;

## [3.1.54] - 2017-01-19

### Fixed
- revisions;


## [3.1.53] - 2017-01-20

### Fixed
- Revisions: $this->update() removed many to many relations;


## [3.1.52] - 2017-01-18

### Fixed
- revisions are sorted by key, not by date, since they keys are auto-incremented anyway; this should allow for multidimensional arrays;


## [3.1.51] - 2017-01-11

### Fixed
- revisions work when there are hidden (fake) fields present;
- the table in list view is responsive (scrollable horizontally) by default;
- new syntax for details_row URL in javascript;
- new syntax for the current URL in layout.blade.php, for making the current menu items active;

## [3.1.50] - 2017-01-08

### Added
- Chinese (Traditional) translation, thanks to [Isaac Kwan](https://github.com/isaackwan);
- You can now create a CRUD field to overwrite the primary key, thanks to [Isaac Kwan](https://github.com/isaackwan);

### Fixed
- Escaped table name for ENUM column types, so reserved PHP/MySQL names can also be used for table names; Fixes #261;
- CrudTrait's isColumnNullable() should now work for multiple-database systems, by getting the connection type automatically;
- Can use DB prefixed tables in CrudTrait's isColumnNullable(); fixes #300;
- Radio field type could not be used inside Settings; Now it can;


## [3.1.49] - 2017-01-08

### Fixed
- select_from_array field triggered an "Undefined index: value" error; fixes #312 thanks to [Chris Thompson](https://christhompsontldr.com/);


## [3.1.48] - 2016-12-14

### Fixed
- Prevent double-json-encoding on complicated field types, when using attribute casting; Fixes #259;


## [3.1.47] - 2016-12-14

### Fixed
- Don't mutate date/datetime if they are empty. It will default to now;
- select_from_array has a new option: "allows_multiple";
- syncPivot is now done before saving the main entity in Update::edit();
- added beforeColumn(), afterColumn(), beforeField() and afterField() methods to more easily reorder fields and columns - big up to [Ben Sutter](https://github.com/b8ne) for this feature;


## [3.1.46] - 2016-12-13

### Fixed
- a filter will be triggered if the variable exists, wether it's null or not;
- if the elfinder route has not been registered, it will be by the CrudServiceProvider;


## [3.1.45] - 2016-12-02

### Added
- $this->crud->with() method, which allows you to easily eager load relationships;
- auto eager loading relationships that are used in the CRUD columns;

### Fixed
- select and select_multiple columns use a considerably lower number of database queries;


## [3.1.44] - 2016-12-02

### Added
- Better ability to interact with the entity that was just saved, in EntityCrudController::create() and update() [the $this->crud->entry and $this->data['entry'] variables];


## [3.1.43] - 2016-11-29

### Fixed
- Allow mixed simple and complex column definitions (thanks [JamesGuthrie](https://github.com/JamesGuthrie));
- disable default DataTable ordering;


## [3.1.42] - 2016-11-13

### Fixed
- n-n filters prevented CRUD items from being added;


## [3.1.41] - 2016-11-11

### Added
- filters on list view;


## [3.1.40] - 2016-11-06

### Fixed
- fixed video field having an extra input on page;
- fixed hasUploadFields() check for update edit form; fixes #211;


## [3.1.39] - 2016-11-06

### Fixed
- fixed SimpleMDE which was broken by last commit; really fixes #222;


## [3.1.38] - 2016-11-04

### Fixed
- SimpleMDE field type did not allow multiple such field types in one form; fixes #222;


## [3.1.37] - 2016-11-03

### Fixed
- Boolean column type triggered error because of improper use of the trans() helper;


## [3.1.36] - 2016-10-30

### Added
- SimpleMDE field type (simple markdown editor).


## [3.1.35] - 2016-10-30

### Added
- new column type: boolean;
- new field type: color_picker;
- new field type: date_picker;
- new field type: datetime_picker;

### Fixed
- fixed default of 0 for radio field types;
- fixes #187 - can now clear old address entries;
- fixes hiding/showing buttons when the min/max are reached;
- ckeditor field type now has customizable plugins;


## [3.1.34] - 2016-10-22

### Fixed
- Config file is now published in the right folder.


## [3.1.33] - 2016-10-17

### Fixed
- all fields now have hint, default value and customizable wrapper class - thanks to [Owen Melbourne](https://github.com/OwenMelbz); modifications were made in the following fields: base64_image, checklist, checklist_dependecy, image;
- creating/updating elements works with morphable fields too; you need to define "morph" => true on the field for it to work;
- isCollumnNullable is now calculated using Doctrine, so that it works for MySQL, PosgreSQL and SQLite;


## [3.1.32] - 2016-10-17

### Added
- video field type - thanks to [Owen Melbourne](https://github.com/OwenMelbz);


## [3.1.31] - 2016-10-17

### Added
- $this->crud->removeAllButtons() and $this->crud->removeAllButtonsFromStack();


## [3.1.30] - 2016-10-17

### Fixed
- upload_multiple field did not remove the files from disk if no new files were added; solved with a hack - added a hidden input with the same name before it, so it always has a value and the mutator is always triggered;


## [3.1.29] - 2016-10-17

### Fixed
- elFinder height needed a 2px adjustment in javascript; now that's solved using css;


## [3.1.28] - 2016-10-16

### Added
- When elfinder is launched as it's own window, display full-screen;

### Fixed
- Update routes and editor links to follow the route_prefix set in config;
- elFinder iframe now has no white background and uses backpack theme;


## [3.1.27] - 2016-10-7

### Fixed
- 'table' field is properly encapsulated now;


## [3.1.26] - 2016-09-30

### Fixed
- bug fix for 'table' field type - you can now have multiple fields on the same form;


## [3.1.25] - 2016-09-28

### Fixed
- table field JSON bug;


## [3.1.24] - 2016-09-27

### Added
- address field type - thanks to [Owen Melbourne](https://github.com/OwenMelbz);


## [3.1.23] - 2016-09-27

### Added
- autoFocus() and autoFocusOnFirstField() - thanks to [Owen Melbourne](https://github.com/OwenMelbz);


## [3.1.22] - 2016-09-27

### Fixed
- checklist and checklist_dependency fields allow html on labels;


## [3.1.21] - 2016-09-26

### Added
- "table" field type - thanks to [Owen Melbourne](https://github.com/OwenMelbz);
- "multidimensional_array" column type - thanks to [Owen Melbourne](https://github.com/OwenMelbz);


## [3.1.20] - 2016-09-26

### Added
- Non-core CRUD features are now separated into traits;

### Fixed
- The 'password' field is no longer filtered before the create event;
- CrudPanels can now be defined in the new EntityCrudController::setup() method;

## [3.1.19] - 2016-09-26

### Fixed
- AJAX datatables can now have select_multiple columns;


## [3.1.18] - 2016-09-25

### Fixed
- checkbox field has default value;



## [3.1.17] - 2016-09-25

### Fixed
- Raw DB queries did not account for DB prefixes;


## [3.1.16] - 2016-09-22

### Added
- Radio field and column - thanks to [Owen Melbourne](https://github.com/OwenMelbz);


## [3.1.15] - 2016-09-21

### Fixed
- Missing $fillable item in model will now throw correct error, because _token is ignored;
- Correct and complete language files;


## [3.1.14] - 2016-09-19

### Fixed
- Checkbox storing issue in Laravel 5.3 - #115 thanks to [timdiels1](https://github.com/timdiels1);


## [3.1.13] - 2016-09-19

### Added
- Revisions functionality, thanks to [se1exin](https://github.com/se1exin);


## [3.1.12] - 2016-09-19

### Added
- French translation, thanks to [7ute](https://github.com/7ute);


## [3.1.11] - 2016-09-19

### Added
- iconpicker field type;


## [3.1.10] - 2016-09-16

### Fixed
- removeButton and removeButtonFromStack functionality, thanks to [Alexander N](https://github.com/morfin60);


## [3.1.9] - 2016-09-16

### Added
- "prefix" and "suffix" optional attributes on the number and text field types;


## [3.1.8] - 2016-09-15

### Fixed
- upload and upload_multiple can be used for S3 file storage too, by specifying the disk on the field;


## [3.1.7] - 2016-09-15

### Added
- image field type - stores a base64 image from the front-end into a jpg/png file using Intervention/Image;


## [3.1.6] - 2016-09-15

### Added
- upload_multiple field type;


## [3.1.5] - 2016-09-14

### Added
- upload field type;

### Fixed
- setFromDb() no longer creates a field for created_at;


## [3.1.4] - 2016-09-12

### Added
- Export buttons for CRUDs - to PDF, XLS, CSV and Print, thanks to [Nathaniel Kristofer Schweinberg](https://github.com/nathanielks);


## [3.1.3] - 2016-09-12

### Added
- a "view" field type, which loads a custom view from a specified location; thanks to [Nathaniel Kristofer Schweinberg](https://github.com/nathanielks);


## [3.1.2] - 2016-09-12

### Fixed
- save, update and reorder now replace empty inputs with NULL to allow for MySQL strict mode on (a default in Laravel 5.3) (#94)


## [3.1.1] - 2016-09-05

### Added
- Allow HTML in all field labels (#98)


## [3.1.0] - 2016-08-31

### Added
- Laravel 5.3 support;


## [3.0.17] - 2016-08-26

### Fixed
- adding buttons from views did not work; fixes #93;


## [3.0.16] - 2016-08-24

### Fixed
- Removed recurring comment from list view; Fixes #92;
- Added check for permission in the CrudController::search() method for allowing the AJAX table only if list is enabled;


## [3.0.15] - 2016-08-20

### Fixed
- Removed double-token input in Create view; Fixes #89;


## [3.0.14] - 2016-08-20

### Fixed
- Fixed AJAX table view with big data sets - was still selecting all rows from the DB; Fixes #87;


## [3.0.13] - 2016-08-17

### Fixed
- Custom pivot table in select2 and select2_multiple fields; Fixes #75;


## [3.0.12] - 2016-08-17

### Fixed
- Reorder view works with custom primary keys; fixes #85;
- URLs in views now use the backpack.base.route_prefix; fixes #88;


## [3.0.11] - 2016-08-12

### Added
- Spanish translation, thanks to [Rafael Ernesto Ferro González](https://github.com/rafix);


## [3.0.10] - 2016-08-09

### Removed
- PHP dependency, since it's already settled in Backpack\Base, which is a requirement;


## [3.0.9] - 2016-08-06

### Added
- base64_image field type, thanks to [deslittle](https://github.com/deslittle);


## [3.0.8] - 2016-08-05

### Added
- automatic route names for all CRUD::resource() routes;


## [3.0.7] - 2016-08-05

### Added
- PDO Support;

### Removed
- default column values on the setFromDb() function;


## [3.0.6] - 2016-07-31

### Added
- Bogus unit tests. At least we'be able to use travis-ci for requirements errors, until full unit tests are done.


## [3.0.5] - 2016-07-30

### Added
- Auto-registering the Backpack\Base class;
- Improved documentation for those who want to just install Backpack\CRUD;


## [3.0.4] - 2016-07-30

### Added
- Auto-registering the Backpack\Base class;
- Improved documentation for those who want to just install Backpack\CRUD;


## [3.0.3] - 2016-07-25

### Added
- Ctrl+S and Cmd+S submit the form;


## [3.0.2] - 2016-07-24

### Added
- added last parameter to addButton() function which determines wether to add the button to the beginning or end of the stack;


## [3.0.1] - 2016-07-23

### Added
- 'array' column type (stored as JSON in the db); also supports attribute casting;
- support for attribute casting in Date and Datetime field types;


## [3.0.0] - 2016-07-22

### Added
- wrapperAttributes to all field types, for resizing with col-md-6 and such;
- 'default' value for most field types;
- hint to most field types;
- extendable column types (same as field types, each in their own blade file);
- 'date' and 'datetime' column types;
- 'check' column type;
- button stacks;
- custom buttons, as views or model_function;
- registered service providers in order to simplify installation process;
- configurable number of rows in the table view, by giving a custom value in the config file or in the CRUD panel's constructor;

### Removed
- "required" functionality with just added asterisks to the fields;

### Fixed
- renamed the $field_types property to $db_column_types to more accurately describe what it is;
- issue #58 where select_from_array automatically selected an item with value zero;
- custom html attributes are now given to the field in a separate array, 'attributes';


## ----------------------------------------------------------------------------


## [2.0.24] - 2016-07-13

### Added
- model_function_attribute column type (kudos to [rgreer4](https://github.com/rgreer4))


## [2.0.23] - 2016-07-13

### Added
- Support for $primaryKey variable on the model (no longer dependant on ID as primary key).


## [2.0.22] - 2016-06-27

### Fixed
- Fix removeField method
- Improve autoSetFromDB method


## [2.0.21] - 2016-06-21

### Fixed
- Old input value on text fields in the create form;
- "Please fix" lang text.


## [2.0.20] - 2016-06-19

### Fixed
- Translate browse and page_or_link fields


## [2.0.19] - 2016-06-16

### Fixed
- Split the Crud.php class into multiple traits, for legibility;
- Renamed the Crud.php class to CrudPanel;


## [2.0.18] - 2016-06-16

### Removed
- Tone's old field types (were only here for reference);
- Tone's old layouts (were only here for reference);


## [2.0.17] - 2016-06-16

### Added
- $crud->hasAccessToAny($array) method;
- $crud->hasAccessToAll($array) method;


## [2.0.16] - 2016-06-15

### Fixed
- CrudController - use passed request before fallback to global one;


## [2.0.15] - 2016-06-14

### Fixed
- select_multiple worked, select2_multiple did not; #26


## [2.0.14] - 2016-06-13

### Fixed
- Allow HTML in fields help block;


## [2.0.13] - 2016-06-09

### Added
- Italian translation;
- Browse field parameter to disable readonly state;


## [2.0.12] - 2016-06-06

### Fixed
- multiple browse fields on one form did not work;


## [2.0.11] - 2016-06-06

### Fixed
- multiple browse fields on one form did not work;


## [2.0.10] - 2016-06-06

### Fixed
- browse field did not work if Laravel was installed in a subfolder;
- browse field Clear button did not clear the input;
- select_from_array field did not work;
- Crud::setFromDb() now defaults to NULL instead of empty string;


## [2.0.9] - 2016-05-27

### Deprecated
- Route::controller() - it's been deprecated in Laravel 5.2, so we can't use it anymore;


## [2.0.8] - 2016-05-26

### Added
- page_or_link field type now has a 'page_model' attribute in its definition;


## [2.0.7] - 2016-05-25

### Added
- Text columns can now be added with a string $this->crud->addColumn('title');
- Added hint to the 'text' field type;
- Added the 'custom_html' field type;


## [2.0.6] - 2016-05-25

### Fixed
- Elfinder triggered an error on file upload, though uploads were being done fine.


## [2.0.5] - 2016-05-20

### Fixed
- Removing columns was fixed.


## [2.0.4] - 2016-05-20

### Fixed
- Fields with subfields did not work any more (mainly checklist_dependency);


## [2.0.3] - 2016-05-20

### Fixed
- Easier CRUD Field definition - complex fields no longer need a separate .js and .css files; the extra css and js for a field will be defined in the same file, and then pushed to a stack in the form_content.blade.php view, which will put in the proper after_styles or after_scripts section. By default, the styles and scripts will be pushed to the page only once per field type (no need to have select2.js five times onpage if we have 5 select2 inputs)
- Changed existing complex fields (with JS and CSS) to this new definition.


## [2.0.2] - 2016-05-20

### Added
- Working CRUD API functions for adding fields and removing fields.
- Removed deprecated file: ToneCrud.php


## [2.0.1] - 2016-05-19

### Fixed
- Crud.php fixes found out during Backpack\PermissionManager development.
- Added developers to readme file.


## [2.0.0] - 2016-05-18

### Added
- Call-based API.


## ----------------------------------------------------------------------------


## [0.9.10] - 2016-03-17

### Fixed
- Fixed some scrutinizer bugs.


## [0.9.9] - 2016-03-16

### Added
- Added page title.


## [0.9.8] - 2016-03-14

### Added
- Added a custom theme for elfinder, called elfinder.backpack.theme, that gets published with the CRUD public files.


## [0.9.7] - 2016-03-12

### Fixed
- Using LangFileManager for translatable models instead of Dick's old package.


## [0.9.6] - 2016-03-12

### Fixed
- Lang files are pushed in the correct folder now. For realsies.


## [0.9.5] - 2016-03-12

### Fixed
- language files are published in the correct folder, no /vendor/ subfolder


## [0.9.4] - 2016-03-11

### Added
- CRUD::resource() now also acts as an implicit controller too.

### Removed
- firstViewThatExists() method in CrudController - its functionality is already solved by the view() helper, since we now load the views in the correct order in CrudServiceProvider



## [0.9.3] - 2016-03-11

### Fixed
- elFinder erro "Undefined variable: file" is fixed with a composer update. Just make sure you have studio-42/elfinder version 2.1.9 or higher.
- Added authentication middleware to elFinder config.


## [0.9.2] - 2016-03-10

### Fixed
- Fixed ckeditor field type.
- Added menu item instructions in readme.


## [0.9.1] - 2016-03-10

### Fixed
- Changed folder structure (Http is in app folder now).


## [0.9.0] - 2016-03-10

### Fixed
- Changed name from Dick/CRUD to Backpack/CRUD.

### Removed
- Entrust permissions.


## [0.8.17] - 2016-02-23

### Fixed
- two or more select2 or select2_multiple fields in the same form loads the appropriate .js file two times, so error. this fixes it.


## [0.8.13] - 2015-10-07

### Fixed
- CRUD list view bug fixed thanks to Bradis García Labaceno. The DELETE button didn't work for subsequent results pages, now it does.


## [0.8.12] - 2015-10-02

### Fixed
- CrudRequest used classes from the 'App' namespace, which rendered errors when the application namespace had been renamed by the developer;


## [0.8.11] - 2015-10-02

### Fixed
- CrudController used classes from the 'App' namespace, which rendered errors when the application namespace had been renamed by the developer;


## [0.8.9] - 2015-09-22

### Added
- added new column type: "model_function", that runs a certain function on the CRUD model;


## [0.8.8] - 2015-09-17

### Fixed
- bumped version;


## [0.8.7] - 2015-09-17

### Fixed
- update_fields and create_fields were being ignored because of the fake fields; now they're taken into consideration again, to allow different fields on the add/edit forms;

## [0.8.6] - 2015-09-11

### Fixed
- DateTime field type needed some magic to properly use the default value as stored in MySQL.

## [0.8.5] - 2015-09-11

### Fixed
- Fixed bug where reordering multi-language items didn't work through AJAX (route wasn't defined);


## [0.8.4] - 2015-09-10

### Added
- allTranslations() method on CrudTrait, to easily get all connected entities;


## [0.8.3] - 2015-09-10

### Added
- withFakes() method on CrudTrait, to easily get entities with fakes fields;

## [0.8.1] - 2015-09-09

### Added
- CRUD Alias for handling the routes. Now instead of defining a Route::resource() and a bunch of other routes if you need reorder/translations etc, you only define CRUD:resource() instead (same syntax) and the CrudServiceProvider will define all the routes you need. That, of course, if you define 'CRUD' => 'Dick\CRUD\CrudServiceProvider' in your config/app.php file, under 'aliases'.


## [0.8.0] - 2015-09-09

### Added
- CRUD Multi-language editing. If the EntityCrudController's "details_row" is set to true, by default the CRUD will output the translations for that entity's row. Tested and working add, edit, delete and reordering both for original rows and for translation rows.


## [0.7.9] - 2015-09-09

### Added
- CRUD Details Row functionality: if enabled, it will show a + sign for each row. When clicked, an AJAX call will return the showDetailsRow() method on the controller and place it in a row right below the current one; Currently that method just dumps the entry; But hey, it works.


## [0.7.8] - 2015-09-08

### Fixed
- In CRUD reordering, the leaf ID was outputted for debuging.


## [0.7.7] - 2015-09-08

### Added
- New field type: page_or_link; It's used in the MenuManager package, but can be used in any other model;


## [0.7.4] - 2015-09-08

### Added
- Actually started using CHANGELOG.md to track modifications.

### Fixed
- Reordering echo algorithm. It now takes account of leaf order.

