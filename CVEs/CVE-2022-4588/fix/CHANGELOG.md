## 84.2.0

### Enhancements
- **Security Changes**
  - Fixed a Cross Site Scripting (XSS) issue

## 84.1.0 (August 7, 2021)

### Enhancements
- **Project Changes**
  - Recent activity now shows activity for the past month
- **Gem Changes**
  - Update to puma 4.3

### Bug Fix
- Fixed a bug that prevented pagination from retaining search filters on
  subjects and sheets pages

## 84.0.0 (April 4, 2021)

### Enhancements
- **Randomization Changes**
  - Randomization schemes can be set to allow tasks to be scheduled on weekends
- **Gem Changes**
  - Update to rails 6.0.3.6
  - Update to carrierwave 2.2.1

## 83.0.1 (March 20, 2021)

### Bug Fix
- Fixed a bug that prevented the calendar from displaying randomizations that
  were part of a "Custom List" randomization scheme

## 83.0.0 (January 24, 2021)

### Enhancements
- **General Changes**
  - Updated support email to support@sliceable.org
- **Adverse Event Changes**
  - Subject code is now listed on Adverse Events index
- **Medication Changes**
  - Created At and Updated At fields are now shown on the medication show page
- **Gem Changes**
  - Update to rails 6.0.3.4
  - Update to pg 1.2.3
  - Update to bootstrap 4.4.1
  - Update to carrierwave 2.1.0
  - Update to figaro 1.2.0
  - Update to font-awesome-sass 5.12.0
  - Update to haml 5.2.1
  - Update to kaminari 1.2.1
  - Update to mini_magick 4.11.0
  - Update to redcarpet 3.5.1

## 82.0.0 (February 1, 2020)

### Enhancements
- **Adverse Event Changes**
  - Adverse event members can now download a PDF dossier for review
- **Gem Changes**
  - Update to rails 6.0.2.1

### Bug Fix
- Fix an issue rendering ≥ and ≤ in PDFs

## 81.0.0 (November 24, 2019)

### Enhancements
- **Adverse Event Changes**
  - Adverse event team managers receive emails when adverse events are assigned
    to their team
  - Adverse event reviewers receive emails when adverse events are assigned to
    them for review
  - Adverse event team managers receive emails when reviewers complete their
    assigned adverse event reviews
  - Opening information requests generates emails
    - to adverse event admins when requested by adverse event team members
    - to the adverse event reporter when requested by adverse event admins
  - Resolving information requests generates emails to the requester
- **Export Changes**
  - Data dictionary exports of branching logic now correctly outputs the
    variable name instead of the internal variable ID
- **Gem Changes**
  - Update to rubyzip 2.0.0

## 80.0.0 (November 17, 2019)

### Enhancements
- **Adverse Event Changes**
  - Adverse event admins can delete closed adverse events
  - Adverse event admins now receive email notifications when adverse events are
    opened, and when adverse events are sent for review
- **Export Changes**
  - Exports can no longer be created without selecting an export option
- **Gem Changes**
  - Update to ruby 2.6.4
  - Update to rails 6.0.1
  - Update to carrierwave 2.0.2
  - Update to devise 4.7.1
  - Update to font-awesome-sass 5.11.2
  - Update to haml 5.1.2
  - Update to jquery-rails 4.3.5

### Bug Fix
- Fix an issue creating AEs with the same number across multiple projects
- Randomizations on the first day of the month are now properly exported in API
  reports when generating the report on the same day

## 79.0.0 (August 11, 2019)

### Enhancements
- **Domain Changes**
  - Site numbers are now displayed on domain options that are scoped by site
- **Sheet Changes**
  - Project editors can now unlock a sheet and edit it in one click
- **Variable Changes**
  - Date variables can now be set to disallow future dates
- **Gem Changes**
  - Update to font-awesome-sass 5.9.0
  - Update to redcarpet 3.5.0

## 78.0.0 (August 3, 2019)

### Enhancements
- **API Changes**
  - Randomization reports now display months in descending format
  - Add support for reports that filter based on an expression, group by a
    specified date, and then list results by month and by site
  - Add support for "Report Card" report
  - Add support for "Data Checks" report
- **Adverse Event Changes**
  - Adverse event sheets can now be printed by reporters, admins, and team
    members
  - Adverse event team reviewers can now submit information requests that get
    sent to the adverse event admin
  - A "History" tab has been added to adverse events to show past events for
    that subject
- **Export Changes**
  - New adverse event module sheets are now exportable
- **Slice Expression Engine Changes**
  - Filtering by randomized subjects is now supported
    - `subject is randomized`
- **Team Changes**
  - Team member roles are now displayed on team page
  - Multiple invites can now be sent for users who are part of multiple sites
  - Team page can be filtered by roles, sites, and AE teams
  - Team member roles can be removed from the main team page

### Refactoring
- The PATS specific data export task has been removed

### Bug Fix
- Fix a visual bug displaying Site Members as Project Members
- Fix team reviewer assignments from pulling previous assignments made on other
  adverse events

## 77.0.0 (June 15, 2019)

### Enhancements
- **Export Changes**
  - Exports are now run on a dedicated worker environment
- **Gem Changes**
  - Add active_elastic_job 2.0.1
  - Add aws-sdk-sqs 1.16.0

## 76.0.0 (May 27, 2019)

### Enhancements
- **API Changes**
  - Added API to get randomization counts by month and by site
- **Gem Changes**
  - Update to haml 5.1.1
  - Update to jbuilder 2.9

### Bug Fix
- File upload names are now displayed properly in AWS environment
- Cronjobs now run correctly

## 75.1.0 (May 11, 2019)

### Enhancements
- **General Changes**
  - Enabled AWS cron jobs to run checks, generate daily digests, send password
    expiration emails, expire exports, and refresh the sitemap

## 75.0.0 (May 10, 2019)

### Enhancements
- **General Changes**
  - Project and subject sidebars are now accessible on smaller devices
- **Site Changes**
  - Subject, sheet, randomization and adverse event index pages now include
    the site number when displaying the site name
- **Gem Changes**
  - Update to devise 4.6.2

## 74.0.1 (May 6, 2019)

### Enhancements
- **General Changes**
  - Decrease log level to `info` for production environment

## 74.0.0 (May 3, 2019)

### Enhancements
- **API Changes**
  - Added API to pull subject counts for reports
- **Export Changes**
  - Exports now expire after 3 months
- **Randomization Changes**
  - Left hand menu no longer changes after failing to randomize a subject
- **Sheet Changes**
  - Improve rendering of section markdown syntax in PDFs
- **Site Changes**
  - Sites are now displayed and ordered by site number and name across various
    forms and dropdowns on a project
- **Gem Changes**
  - Update to ruby 2.6.3
  - Update to coffee-rails 5.0

### Refactoring
- Move export files, adverse event files, and sheet variable files to be stored
  under the corresponding project folder
- Removed support for grids with file attachments

### Bug Fixes
- Fix issue submitting search by clicking on search icon on several index pages
- Project copy now copies site numbers and short names to new project

## 73.0.0 (April 16, 2019)

### Enhancements
- **AWS Changes**
  - Updated configuration to support deployment to AWS
  - Reverted `gems.rb` to `Gemfile`

## 72.0.0 (April 11, 2019)

### Enhancements
- **Design Changes**
  - The design importer has been removed
- **Randomization Changes**
  - Display dropdowns instead of radio buttons for randomization schemes with
    stratification factors with more than ten options
- **Randomization Scheme Changes**
  - Custom lists randomization scheme now creates lists per site based on
    stratification factors similar to permuted block algorithm
  - Publishing a randomization scheme is now its own action in the randomization
    scheme dropdown
- **Sheet Changes**
  - Simplified header on sheet show pages
  - Sheet events can no longer be changed while editing a sheet, but instead
    can be changed by accessing "Change event" in the sheet dropdown menu
  - Made fillable sheets more apparent when adding new data or events for a
    subject
- **Theme Changes**
  - Improved visibility of text in night theme
- **Gem Changes**
  - Update to font-awesome-sass 5.8.1

### Refactoring
- Remove image from sections model
- Remove subject_code_name from projects, subject codes are now consistently
  referenced as Subject ID

### Bug Fixes
- Schemes menu is no longer set as active when failing to randomize subject

## 71.0.0 (March 25, 2019)

### Enhancements
- **General Changes**
  - Breadcrumbs have been added across several pages to improve navigation
  - Blinding icons have been updated to be more distinct
- **Design Changes**
  - Design editing menu items have been simplified, and preview mode has been
    removed
  - Multiple images can now be added to section descriptions
- **Medications Added**
  - Added a project level module to track subject medications
  - Medication start and stop dates allow for fuzzy dates
    - Dates where only the year is known: 1990
    - Dates where only the year and month are known: Jan 1990
  - Project editors can configure additional variables to be collected alongside
    medication name and start and stop dates
  - Medications can be exported to CSV
- **Translation Changes**
  - Editing designs in different languages now highlights untranslated sections
- **Gem Changes**
  - Update to ruby 2.6.2
  - Update to rails 6.0.0.beta3

### Refactoring
- Remove skipping_utf8 in search forms as Rails 6 no longer enforces utf8

## 70.0.0 (February 20, 2019)

### Enhancements
- **Translation Changes**
  - French Canadian language added
  - Project editors can now specify the languages available to the project
- **Variable Changes**
  - Formats for calculated variables are now applied while editing a sheet
- **Gem Changes**
  - Update to ruby 2.6.1
  - Update to rails 6.0.0.beta1
  - Update to pg 1.1.4
  - Update to bootstrap 4.3.1
  - Update to devise 4.6.1
  - Update to sitemap_generator 6.0.2

### Refactoring
- Remove invite columns from project_users and site_users
- Remove deprecated `check_filters` and `check_filter_values`

## 69.0.0 (January 28, 2019)

### Enhancements
- **Adverse Event Reviews Added**
  - A new team-based review process for adverse event is now available
- **Project Changes**
  - Improved display of project modules on the project settings page
  - Improved and unified project invites to incorporate AE review team invites
- **Gem Changes**
  - Update to bootstrap 4.2.1
  - Update to carrierwave 1.3.1
  - Update to font-awesome-sass 5.6.1

## 68.1.0 (January 8, 2019)

### Enhancements
- **General Changes**
  - Improved speed of project copy task
- **Check Changes**
  - Checks now use Slice Engine expressions to identify sheets
- **Sheet Changes**
  - Public surveys now list creator as "Anonymous" on sheets index
- **Gem Changes**
  - Update to ruby 2.6.0
  - Update to rails 5.2.2

### Bug Fix
- Design computed short name no longer uses dynamic translation of design name

## 68.0.1 (November 14, 2018)

### Bug Fix
- Fixed an issue saving the number `0` zero to integer variables

## 68.0.0 (November 14, 2018)

### Enhancements
- **General Changes**
  - Primary and foreign keys have been updated to use `bigint` instead of
    `integer` to adhere to new Rails 5.1 defaults
- **Adverse Event Changes**
  - Sheets linked to deleted adverse events are no longer displayed in sheets
    index or in exports
  - Adverse event sheet update events now include a link to the list of changes
    made and are displayed separately from sheet creation events
- **Transaction Changes**
  - Adverse events are now listed in sheet transactions using their project id
    instead of the internal id

### Bug Fix
- Fixed an issue where saving an integer with leading zeros would convert the
  value to its octal integer representation

## 67.0.0 (November 9, 2018)

### Enhancements
- **General Changes**
  - Add updated Privacy Policy
- **Admin Changes**
  - Admin interface updated to include Slice Engine run statistics
- **Export Changes**
  - Export folder structure has been updated
    - `csv` folder is now named `csvs`
    - `dd` folder is now named `data_dictionary`
  - Exports are now generated in a temporary folder that is deleted when the
    export is complete
- **Slice Expression Engine Changes**
  - Slice Expressions can now filter results using checkbox variables
  - Removed "any" filter and replaced with "present"
    - `entered` All entered values including missing codes (formerly `entered` and `present`)
    - `present` Non-missing code values (formerly `any`)
    - `missing` Missing codes and blank values, and unentered
    - `unentered`, `blank` Blank values, and unentered sheets
  - Searches for "present" no longer include missing codes
    - This makes searches for designs more logical too, as "design is present"
      no longer includes sheets that have been set as missing
- **Settings Changes**
  - Account settings reorganized into categories
- **Gem Changes**
  - Update to ruby 2.5.3
  - Update to font-awesome-sass 5.4.1
  - Remove colorize

## 66.1.0 (November 5, 2018)

### Enhancements
- **API Changes**
  - The `update_survey_response` method call now requires `design_option_id` to
    be specified in the request

## 66.0.1 (October 31, 2018)

### Bug Fixes
- Fix bug that occurred when domain slicer compared non-string values
- Fix bug that occurred in survey API when a request attempted to update a
  response on a non-existent page

## 66.0.0 (October 4, 2018)

### Enhancements
- **General Changes**
  - Update framework files to resemble Rails 5.2 defaults more closely
  - Slice has a brand new logo (created by @mmorrical)
- **Slice Expression Engine Added**
  - The Slice Expression Engine interprets the new Slice Context Free Grammar
    that allows users to build search templates to filter subjects and sheets
    based on one or more conditions
  - Existing data checks can now be updated to use the new Slice Expression
    Engine syntax
  - Admins can view statistics from of Sleep Expression Engine runs, including
    runtime, sheet counts, and subject counts
- **Variable Changes**
  - Numeric, integer, and calculated variables now link sheet values to domains
    when there is a match
- **Gem Changes**
  - Update to rails 5.2.1
  - Update to pg 1.1.3
  - Update to bootstrap 4.1.3
  - Update to carrierwave 1.2.3
  - Update to chunky_png 1.3.10
  - Update to devise 4.5.0
  - Update to font-awesome-sass 5.3.1
  - Update to mini_magick 4.9.2
  - Update to rubyzip 1.2.2
  - Update to sitemap_generator 6.0.1
  - Update to jquery-rails 4.3.3

### Bug Fix
- Numeric and integer variables that have a domain with missing codes now
  correctly associate values to the missing code if both numbers are equal
  regardless of decimal verse integer input

## 65.0.0 (September 10, 2018)

### Enhancements
- **General Changes**
  - Update file inputs to match custom Bootstrap file inputs
- **Export Changes**
  - Initial language used for sheet data entry is now displayed in exports
- **Randomization Schemes**
  - Added the option to choose "Custom List" as the randomization scheme,
    allowing custom randomization lists to be entered that do not use
    "Minimization" or "Permuted Block"
- **Search Changes**
  - Searches now includes better results, specifically for the variables
- **Theme Changes**
  - Improve display of several components in the night theme
- **Gem Changes**
  - Add font-awesome-sass replacing font-awesome-rails

### Bug Fix
- Admins can now search for users by last name on users index
- AM fields are no longer cutoff in time entry fields in certain browsers
- New sheets on events no longer disassociate from the event when the form
  language is changed

## 64.1.1 (June 27, 2018)

### Bug Fix
- Designs that import data now display correctly

## 64.1.0 (June 20, 2018)

### Enhancements
- **Handoff Changes**
  - Handoffs now take into account conditionally required designs on events

## 64.0.1 (June 11, 2018)

### Enhancements
- **General Changes**
  - Enabled "X-Accel-Redirect" for faster file downloads

## 64.0.0 (June 11, 2018)

### Enhancements
- **Check Changes**
  - Checks are now run as scheduled tasks instead of being triggered by sheet or
    subject changes
- **Design Changes**
  - Printed PDFs are now cached for each design by language
- **Export Changes**
  - Improved export page interface
  - Temporarily disabled PDF exports
- **Library Added**
  - Added a public library of forms
  - Users can create a profile and contribute their own forms to the public
    library
- **Randomization Changes**
  - Printed PDFs are now cached for each schedule by language
- **Project Changes**
  - Sheet calculation errors index can now be filtered by keywords
- **Sheet Changes**
  - Printed PDFs are now cached for each sheet by language

### Bug Fix
- Checkboxes with values unassociated to domain options are now included in
  exports
- Fixed a bug that incorrectly renumbered adverse event numbers without taking
  into account deleted adverse events
- Fixed a bug that prevented removing all variables from a grid
- Fixed a bug that prevented the design editor from loading if a scale variable
  was part of a grid on the design

### Refactoring
- Removed underutilized project summary report and advanced design reports

## 63.0.1 (May 25, 2018)

### Bug Fix
- Readded a event param to the Slice API for subject events

## 63.0.0 (May 24, 2018)

### Enhancements
- **General Changes**
  - Dropped checks for unsupported browser types
  - Adjusted color of links in headings
- **API Changes**
  - Changed APIs for subject events and event designs
- **Design Changes**
  - Designs are now searchable by short name and slug on the design index
- **Profile Changes**
  - Users can now directly upload profile pictures
- **Project Changes**
  - Added a task that checks calculated fields and provides a list of sheets
    that have a mismatch between the recomputed and stored value
- **Sheet Changes**
  - "Create another?" link, after creating a sheet on a repeated design, is now
    displayed outside of the flash notice area so that it no longer disappears
    before users can click on it
  - The sheets index now has an Actions column to quickly edit, unlock, or
    delete a sheet
- **Theme Changes**
  - Added a fall theme
- **Translation Changes**
  - Added Spanish translations for variable validation messages
- **Variable Changes**
  - Simplified how the overlap function handles comparisons between strings and
    integers
  - Calculation formats are now applied to sheets, PDFs and exports
  - Switched direction of range mins and maxes for numbers and dates

### Bug Fix
- Fixed a bug that incorrectly displayed default devise messages
- Variable calculations are now displayed in readable format as opposed to
  internal format when editing a variable

## 62.0.2 (May 14, 2018)

### Bug Fix
- Fixed several issues with similar designs slugs across projects

## 62.0.1 (May 9, 2018)

### Bug Fix
- Fixed a bug that prevented the design slugs from being reused across projects
- Fixed a bug that displayed the design public survey URL incorrectly

## 62.0.0 (May 9, 2018)

### Enhancements
- **API Changes**
  - Provided ID lookup for survey events and designs
- **Design Changes**
  - Design slugs have been renamed to survey slugs
    - Survey slugs are unique across Slice and have a unique public URL
  - Designs can now have project specific slugs
  - Improved display of blank grids and grids with empty rows

### Bug Fix
- Fixed a bug that prevented grids from being cleared by removing all rows

## 61.0.0 (May 2, 2018)

### Enhancements
- **General Changes**
  - Removed Google Analytics
  - Improved password autocomplete
- **Design Changes**
  - Variable prefixes, units, and suffixes can now be translated
  - Variables and domains can be translated outside of the design builder
  - Untranslated parts of a design are now muted
  - Default language is now a placeholder instead of the default input when
    translating fields
  - Added Spanish translations for time of day, time duration, imperial weight,
    imperial height, and grids
- **Sheet Changes**
  - Sheet search autocomplete now truncates long domain option names
- **Variable Changes**
  - Variable display names can now be formatted in the following ways:
    - Bold: \*\*text\*\*
    - Underline: \_\_text\_\_
    - Highlight: \=\=text\=\=
    - Italics: \*text\*
- **Gem Changes**
  - Updated to ruby 2.5.1
  - Updated to rails 5.2.0
  - Updated to bootstrap 4.1.1
  - Updated to simplecov 0.16.1
  - Updated to capybara 3.0

### Bug Fix
- Fixed a bug where a user could no longer clear project notifications after
  being removed from a project

## 60.0.0 (April 6, 2018)

### Enhancements
- **Admin Changes**
  - Added user themes to users index
- **Design Changes**
  - Designs can now be translated into different languages
  - Admins can now add Spanish translations to existing designs
  - PDFs should now correctly print Spanish characters
  - Transactions track which language was used during data entry
- **Handoff Changes**
  - Tablet handoffs can now completed in a different language as long as one of
    the designs on the handoff has been translated
- **Project Changes**
  - Added project-level settings to enable design translations
- **Gem Changes**
  - Updated to rails 5.2.0.rc2
  - Updated to devise 4.4.3

### Bug Fix
- Design reorder button is no longer displayed as active while editing a design
- Fixed an issue with URLs in the footer not being clickable
- Fixed a bug that attempted to keep current session active during a handoff

## 59.1.2 (March 23, 2018)

### Bug Fix
- Fixed an issue generating exports for projects with excessively large grids

## 59.1.1 (March 8, 2018)

### Bug Fix
- Variable display names now display properly on overview reports

## 59.1.0 (March 5, 2018)

### Enhancements
- **API Changes**
  - Added the ability to retrieve specific data for a single subject
- **Sheet Changes**
  - Scale column headers should now display over radio buttons and checkboxes
  - Improved navigating dropdowns in grids using the arrow keys
- **Gem Changes**
  - Updated to clipboard.js 2.0.0

## 59.0.0 (February 27, 2018)

### Enhancements
- **General Changes**
  - Updated styling of project sidebar menu
  - First and last name fields have been merged into single full name fields
  - Seasonal themes are now rotated on registration and login pages
- **Adverse Event Changes**
  - Adverse events now indicate if they are open or closed in the subject menu
- **Dashboard Changes**
  - Favorited and current projects have been combined into "active" projects
    that are displayed on the dashboard
  - Projects can now be "active" or "archived"
- **Event Changes**
  - Event completion over 90% now gets a special indicator in the subject menu
  - Event completion of 100% still has the indicator plus the check mark
- **Grid Changes**
  - Radio button variables in grids are now always displayed as dropdowns
  - Made adjustments to navigating through a grid using arrow keys to reduce the
    likelihood of inadvertently changing values
- **Sheet Changes**
  - Updated inputs for date, time, weight, and height variables to use less
    space
  - Time of day variables now validate after navigating off of the AM/PM
    selector
  - Actively entering data on a sheet now delays timeouts due to inactivity
- **Gem Changes**
  - Updated to bootstrap 4.0.0

### Bug Fix
- Fixed a bug that could display the wrong project for a subject

## 58.3.0 (February 9, 2018)

### Enhancements
- **Export Changes**
  - Added sheet coverage count and total to sheet CSV exports and import scripts

## 58.2.0 (February 5, 2018)

### Enhancements
- **API Changes**
  - Added a survey review page customized for a single subject
- **Gem Changes**
  - Updated to rails 5.2.0.rc1
  - Updated to pg 1.0.0

## 58.1.0 (January 25, 2018)

### Enhancements
- **API Changes**
  - The report JSON API now returns reports customized by subject
- **Gem Changes**
  - Updated to carrierwave 1.2.2
  - Updated to devise 4.4.1

## 58.0.0 (January 5, 2018)

### Enhancements
- **API Changes**
  - Added a new reports JSON API that displays aggregate data for a design on an
    event
  - Subject event and sheet coverage is now computed when loading subject events
- **Export Changes**
  - Exporting project data dictionary without setting filters now exports all
    designs, even those without sheets
  - Sheet coverage percent has been added as a column to sheet exports
- **Gem Changes**
  - Updated to rails 5.2.0.beta2
  - Updated to devise 4.4.0
  - Updated to kaminari 1.1.1

## 0.57.1 (November 13, 2017)

### Enhancements
- **API Changes**
  - Subject events now provide the event slug as well as the event name
- **PATS Changes**
  - Added task to recompute date windows for randomization tasks
- **Sheet Changes**
  - Sheets on events now display the event name in the footer of the PDF
- **Gem Changes**
  - Updated `Gemfile` to `gems.rb`
  - Updated to haml 5.0.4

### Tests
- Added tests to assure user passwords can be reset

## 0.57.0 (October 11, 2017)

### Enhancements
- **API Changes**
  - Subject event designs are now only included if they are required or if they
    have associated sheets
- **Domain Changes**
  - Domain options can now be flagged as mutually exclusive
  - Selecting a mutually exclusive option on a group of checkboxes clears any
    other selected checkbox options
- **Grid Changes**
  - Grid table headers now stay on screen while scrolling through a long grid
- **Sheet Changes**
  - Leading and trailing white space is now removed from responses
- **Setting Changes**
  - Users can now enable sound alerts in their settings
    - Sound alerts trigger when a data entry field is flagged as an error
- **Variable Changes**
  - Internal calculated formats have been renamed for clarity
- **Gem Changes**
  - Updated to ruby 2.4.2
  - Updated to rails 5.1.4
  - Updated to carrierwave 1.2.1
  - Updated to haml 5.0.3
  - Updated to sitemap_generator 6.0.0
  - Updated to simplecov 0.15.1

### Bug Fix
- Fixed a timeout issue that occurred while updating domains with large number
  of options

## 0.56.1 (September 6, 2017)

### Enhancements
- **API Changes**
  - Added `show_seconds` for `time_of_day` and `time_duration` variables

### Bug Fix
- Blank dates no longer prevent PATS recruitment export from completing

## 0.56.0 (August 28, 2017)

### Enhancements
- **API Changes**
  - Added API to show and save survey questions one at a time
  - Added API to resume a survey
  - Sheets can now be partially validated to support incremental sheet updates
- **General Changes**
  - Simplified logo for timeout notifications
- **Check Changes**
  - Reduced the number of checks that need to be run when updating sheets
- **Report Changes**
  - Improved computation of column percentages for advanced design reports
  - Date minimum and maximums are now included on design overview reports
- **Sheet Changes**
  - Sheets can now be filtered by year and month
    - `created:2017` Sheets created in 2017
    - `created:<2017` Sheets created before 2017
    - `created:<=2017` Sheets created before or during 2017
    - `created:>2017` Sheets created after 2017
    - `created:>=2017` Sheets created after or during 2017
    - `created:2017-02` Sheets created in February 2017
    - `created:<2017-02` Sheets created before February 2017
    - `created:<=2017-02` Sheets created before or during February 2017
    - `created:>=2017-1 created:<=2017-3` Sheets created Jan through Mar of 2017
- **Survey Changes**
  - Removed excess padding for section descriptions on public surveys
- **Variable Changes**
  - Internal date formats for date variables have been streamlined
  - Imperial height and weight can now have range checks
- **Gem Changes**
  - Updated to rails 5.1.3
  - Updated to haml 5.0.2
  - Updated to simplecov 0.15.0

### Bug Fixes
- Checkbox responses are now sorted properly on sheet show pages
- "N" totals are no longer incorrectly included when computing percent by row on
  advanced design reports

## 0.55.0 (July 31, 2017)

### Enhancements
- **API Changes**
  - Added API to generate and retrieve a project's subjects and events
  - Subject sheets can now be initialized using the API

### Bug Fix
- Subjects with an event but no sheets now correctly display the event

## 0.54.2 (July 24, 2017)

### Bug Fix
- Signatures are no longer output as raw data in CSV exports

## 0.54.1 (July 13, 2017)

### Bug Fix
- Fixed a rare occurence where sheets could have multiple responses saved for
  single questions resulting in coverage over 100%

## 0.54.0 (July 11, 2017)

### Enhancements
- **General Changes**
  - Search boxes no longer try to autocorrect or spellcheck search text
  - Improved variable and value autocomplete in search boxes
- **Sheet Changes**
  - Improved search filter autocomplete on the sheets index
  - Uploaded files counts are now cached
  - Sheet comments counts are now cached
  - Sheets can now be filtered by open and closed adverse events
    - `adverse-events:open`
    - `adverse-events:closed`
    - `has:adverse-events`
    - `no:adverse-events`
- **Design Changes**
  - Design link to "View Sheets" now filters sheets using the new search format
  - Ranges for integers, numerics, and dates are now displayed underneath the
    variables on the design editor
- **Export Changes**
  - Adverse event exports now more closely match the adverse events index
  - CSV export READMEs now more accurately reflect the contents of the exported
    zip file
- **Site Changes**
  - Improved the display of site members on the site show page on mobile
- **Subject Changes**
  - Subject file counts are now computed based on cached sheet file counts
- **Variable Changes**
  - Variables are now restricted to show up once either as part of a design or
    on a grid
    - This change helps eliminate unknowningly changing a variable across
      designs, helps simplify data exports, and allows Slice to more directly
      associate variables to subjects
- **Gem Changes**
  - Updated to rails 5.1.2
  - Updated to pg 0.21.0

### Bug Fix
- Variables that exist on multiple designs no longer produce multiple columns in
  CSV exports
- Blinded project members can no longer filter subjects by adverse events
- Adding and removing variables on grids no longer generates orphaned
  `GridVariable`s

## 0.53.0 (May 25, 2017)

### Enhancements
- **Export Changes**
  - Improved the speed of sheet exports
  - Exports can now be filtered by designs, events, randomization, and adverse
    events
  - Export file sizes are cached to improve loading speed of the exports index
- **Site Changes**
  - White space around site names is now truncated
- **Variable Changes**
  - White space around variable names and display names is now truncated
  - Searching for variables on variables index is now less restrictive
- **Gem Changes**
  - Updated to rails 5.1.1
  - Updated to devise 4.3.0

### Bug Fix
- Fixed a bug that prevented chaining multiple search conditions on the subjects
  index

## 0.52.2 (May 18, 2017)

### Bug Fix
- Fixed a bug that prevented labeled CSV exports from finishing on projects that
  collected imperial height and imperial weight

## 0.52.1 (May 12, 2017)

### Bug Fix
- Fixed an issue exporting grid data for sheets that also had data on deleted
  grids

## 0.52.0 (May 12, 2017)

### Enhancements
- **Adverse Event Changes**
  - Added adverse event descriptions to several subject pages for better context
- **Design Changes**
  - Branching logic on designs now store variable names interally as ids
    allowing referenced variables to be renamed without breaking existing
    branching logic
- **Export Changes**
  - Modified exported SAS script to order output by variable position instead of
    alphabetically, (by @mrueschman)
- **Project Changes**
  - Team invite email is now cleared after inviting a member to a team
- **Timeout Changes**
  - Desktop notifications can be enabled to notify users of an expiring session
- **Stratification Factor Changes**
  - Calculations for stratification factors now store variable names interally
    as ids allowing referenced variables to be renamed without breaking existing
    calculations
- **Subjects Changes**
  - The subjects index can now be filtered by subjects that have entered,
    unentered, and missing designs by event:
    - `EVENT:DESIGN`, ex: `baseline:demographics`
      - Finds subjects who have entered a demographics sheet at baseline
    - `EVENT:DESIGN:unentered`, ex: `baseline:demographics:unentered`
      - Finds subjects who have not entered a demographics sheet at baseline
    - `EVENT:DESIGN:missing`, ex: `baseline:demographics:missing`
      - Finds subjects who have a demographics sheet set as missing at baseline
- **Variable Changes**
  - Increased size of calculation input for calculated variables
  - Time of day format and time duration format can now be changed when editing
    variables
  - Variable calculations now store variable names internally as ids allowing
    referenced variables to be renamed without breaking existing calculations
- **PATS Changes**
  - Added an export of data completion by site and by event
  - Added an export of adverse events, protocol deviations, and unblinding
    events by site by month
  - Added an export providing counts of failing checks
- **Gem Changes**
  - Updated to Ruby 2.4.1
  - Updated to jquery-rails 4.3.1
  - Updated to haml 5.0.1
  - Updated to carrierwave 1.1.0

### Bug Fixes
- `last_edited_at` is now set when a sheets are imported, and when sheets are
  set as shareable
- Clicking "Current Time" at noon for time of day variables using the 12-hour
  clock now properly sets the time to 12pm instead of 00pm
- abbr elements are no longer underlined more than once
- Imperial height and weight variables are now evaluated correctly in
  stratification factor calculations
- Event design tables now link correctly to filtered lists of subjects with
  designs that are entered, unentered, and set as missing
- Setting a sheet as missing on an event now properly refreshes the event
  coverage
- Fixed a bug preventing sorting adverse events by adverse event date

## 0.51.1 (March 21, 2017)

### Bug Fixes
- The spring theme grass now displays correctly across different browsers
- Fixed some minor typos when adding a user to a project (reported by
  @mmorrical)

## 0.51.0 (March 20, 2017)

### Enhancements
- **General Changes**
  - Reduced impact of shadows on containers and buttons
- **Adverse Event Changes**
  - Sheets no longer display a created at and updated at timestamp if the sheet
    was recently created
  - Sheet related to adverse events are now grouped under the adverse event when
    viewing the subject show page
- **Design Changes**
  - Repeated designs on an event are now all created on the same subject event
- **Event Changes**
  - Event completion percentage is now based on total number of questions and
    responses of the event's sheets
  - Designs that are not conditionally required but exist on an event no longer
    display twice and now have a "Not Required" tooltip
- **Notification Changes**
  - Bell icon stops ringing when notifications are cleared
- **Sheet Changes**
  - Improved the load time of sheet show pages
  - Improved the load time of sheet edit pages
- **Site Changes**
  - Sites index now lists subject code format for each site
- **Theme Changes**
  - Adjusted the background gradient for the spring theme
  - Added a sun, clouds, and grass to the spring theme
- **Gem Changes**
  - Updated to devise 4.2.1
  - Updated to simplecov 0.14.1

### Bug Fixes
- Tooltips now properly display for domain option statistics when editing a
  domain in the design editor
- Fixed an issue with units being hidden when an input field had an error

### Refactoring
- Minor code cleanup for generating sheet transactions
- Refactored `DesignOption` requirements
- Improved subject menu rendering by caching blinded and unblinded file counts

## 0.50.2 (March 15, 2017)

### Bug Fixes
- `last_edited_at` is now set when a survey is submitted
- Page navigation is now properly canceled when editing a sheet and clicking
  "Cancel"

## 0.50.1 (March 13, 2017)

### Enhancements
- **General Changes**
  - Improved the display of scale variables hidden by branching logic
  - Minor adjustments to night mode theme on randomizations schemes show page
- **Gem Changes**
  - Updated to pg 0.20.0

### Bug Fix
- Fixed an error that occurred when checking if a question was the last scale
  variable

## 0.50.0 (March 13, 2017)

### Enhancements
- **General Changes**
  - Optimized loading times for a number of index pages
  - Simplified messages when deleting items
- **Design Changes**
  - Scale headers now stick to the top of the screen when scrolling past a set
    of scale variables
  - Project editors can enable notifications allowing project members to receive
    notifications when sheets for that design are created
  - Saving a design automatically removes any extra white space in and around
    the design `name`, `short_name`, and `slug`
- **Domain Changes**
  - Domains can now be sorted by number of variables
- **Email Changes**
  - Improved display of long links in notification emails
- **Event Changes**
  - Saving an event automatically removes any extra white space in and around
    the event `name` and `slug`
  - Designs on events can now be set as "Always Required" and "Conditionally
    Required"
    - "Conditionally Required" designs are required if the subject has a
      matching on a variable value on a previous design and event
  - Designs on events can now be set as "Highlight Duplicates" (default), or
    "Ignore Duplicates"
    - Both settings allow duplicates designs on a subject event, however only
      the first draws attention to the duplicate design
    - Duplicate sheets now also display their creation date on the subject event
- **Export Changes**
  - Adverse Event IDs are now exported as numbers
  - Sites, events, and designs are encoded as numbers in R and SAS exports
- **Handoff Changes**
  - Last edited at time is now set when handoff sheets are saved
- **Randomization Schemes**
  - Lists are now only displayed after they have been generated
  - Distributions are now only displayed after stratification factors are set
- **Report Changes**
  - Improved speed for generating advanced design reports
- **Sheet Changes**
  - Completion percentage of sheets for designs with no questions are now set to
    100% complete as opposed to 0% complete
      - This change helps mark handoff designs complete that only include
        sections with descriptions
  - Improved the autocomplete for variable values on the sheets index
- **Site Changes**
  - Default site short name is now properly removed when the default site is
    renamed during the project setup process
  - Sites can now be assigned a unique number that is used in the raw sheet and
    grid exports
- **Subject Changes**
  - Design and event filters added to subjects index
    - `designs:NAME`
    - `events:NAME`
    - `designs:!NAME`
    - `events:!NAME`
  - Adverse events, comments, and files now have "no" filters on the index
    - `no:adverse-events`
    - `no:comments`
    - `no:files`
  - Projects that randomize subjects without entered sheets are now shown a
    randomization button on the subject page
- **Subject Event Changes**
  - Improved the display of the event completion percentage
- **Theme Changes**
  - Improved consistency of warning and highlight colors across themes
  - Improved the display of signature across themes, particularly in the night
    theme
  - Styling of permuted block randomization schemes is now more consistent
    across themes
- **PATS Changes**
  - Updated "Reasons for Not Interested in Participation" eligibility table
    - The eligibility table is now filtered on the "33. Is the caregiver
      interested in study participation?"
    - "Reason Unknown" no longer includes "Other reason(s)"
    - Denominator for percentage calculation now correctly uses number of
      subjects instead of number of sheets to match number of subjects in
      numerator
- **Gem Changes**
  - Updated to rails 5.0.2
  - Updated to clipboard.js 1.6.0
  - Updated to highcharts 5.0.7
  - Updated to sitemap_generator 5.3.1

### Bug Fixes
- Fixed an issue in the R export script that could create invalid column names
  starting with underscores for domain values
- Fixed a bug that caused JavaScript functions to run twice in browsers that
  supported Turbolinks
- Fixed a bug that prevented stratifying by radio, dropdown, and checkbox
  variables on advanced design reports
- Fixed a visual bug that prevented radio buttons from being selected after
  selecting a subject to randomize

### Refactoring
- Removed unused grid variable validations
- Rewrote dynamic find_by_* methods

## 0.49.0 (February 20, 2017)

### Enhancements
- **General Changes**
  - Improved consistency of dropdown styling
  - Login cookies are now cross subdomain and work between www and non-www URLs
- **Adverse Event Changes**
  - Adverse events index is now sortable and filterable
- **Design Changes**
  - Minor improvement to reordering sections and questions on designs
  - Improved consistency of text spacing in section descriptions
- **Export Changes**
  - Improved display of file sizes on exports index
- **Sheet Changes**
  - Sheet transactions now link back to filtered sheets index
  - Sheets not on an event now have their creation date listed on the subject
    page
- **PATS Changes**
  - Added Signal Quality Grades table and graph to Data Quality export

### Bug Fix
- Fixed a bug that would remove a section image after updating the section in
  the design editor

### Refactoring
- Removed deprecated time parsing code
- Variable type `time` has been updated to `time_of_day` for clarity
- Improved consistency of internal data model, renamed Sheet and Grid `response`
  to `value`
- Refactored sheet model and coverage methods
- Renamed `display_name_visibility` to `display_layout`

## 0.48.1 (February 13, 2017)

### Enhancements
- **Sheet Changes**
  - Sheet index should sort by *Last Edited* by default

### Bug Fix
- Fixed a bug that caused rows to shift in an export if a sheet was created
  during the export

## 0.48.0 (February 10, 2017)

### Enhancements
- **General Changes**
  - Improved display of project drawer when clicking project title
  - Improved styling of blockquotes
  - Highlight color is now more consistent across themes
- **Adverse Event Changes**
  - Adverse event timelines now include more timestamps
  - Forms can no longer be added to closed adverse events
- **Design Changes**
  - Designs can now be set as repeated
    - After creating a sheet for a subject for a repeated design, a prompt is
      displayed allowing a user to jump into the creation of another sheet for
      the same subject using the same design
- **Export Changes**
  - Improved exports index and export show pages
  - Folders in zipped exports are now lowercase
- **Randomization Changes**
  - Randomizations index can now be filtered and sorted
  - Randomizations index now displays site for users on more than one site
  - Stratification criteria are now included in the randomizations export
- **Report Changes**
  - Overview reports now display variable units as well
  - Only sections and subsections are now displayed on overview reports
  - Improved display of imperial height on design overview report
  - Improved display of imperial weight on design overview report
- **Sheet Changes**
  - Sheets with 100% coverage now look visually different from other sheets with
    coverage in the 90% coverage range
  - Sheets index can now be sorted by sheet *Created At* as well as *Last
    Edited*
  - Subject code now remains visible when scrolling through long sheets when
    creating new sheets
- **Theme Changes**
  - Improved night theme display of alternating colors on sheets
- **Variable Changes**
  - Time of day is now stored as total seconds since midnight in database
  - Time of day is now displayed as a bar chart on the design overview report
  - Time of Day variables can now be filtered by entering clock times on the
    sheets index
      - `time_of_day:>1pm`
      - `time_of_day:>13:00`
      - `time_of_day:<=2:05:04pm`
      - `time_of_day:<=14:05:04`
      - `time_of_day:noon`
      - `time_of_day:midnight`
  - Time duration is now displayed in hours, minutes, and seconds on overview
    report
  - Sheets can now be filtered by time durations that contain hours, minutes,
    and seconds, for example:
      - `time_duration:>1h30m`
- **Gem Changes**
  - Updated to simplecov 0.13.0

### Bug Fix
- Fixed an issue saving time durations over an hour for variables formatted to
  only include minutes and seconds

## 0.47.1 (January 25, 2017)

### Enhancements
- **General Changes**
  - Improved chart display in night mode
  - Tooltips on charts now display all values even when series overlap
  - Focused dropdowns now display a border similar to other input elements
- **Sheet Changes**
  - Subject code now remains visible when scrolling through long sheets
- **PATS Changes**
  - Added Previous Upper Airway Surgery to Screen Failures table for consented
    subjects on the Eligibility Status page
- **Gem Changes**
  - Added autoprefixer-rails
  - Updated to kaminari 1.0.1
  - Updated to font-awesome-rails 4.7.0
  - Updated to jquery-rails 4.2.2
  - Updated to bootstrap-sass 3.3.7

### Bug Fix
- Fixed a bug that incorrectly loaded some pages at a lower scroll position

## 0.47.0 (January 5, 2017)

### Enhancements
- **Project Changes**
  - Project editors receive an additional notification that a project invite was
    successfully sent
- **Gem Changes**
  - Updated to Ruby 2.4.0
  - Updated to rails 5.0.1
  - Updated to carrierwave 1.0.0
  - Updated to redcarpet 3.4.0
  - Updated to jquery-ui-rails 6.0.1
  - Updated to chosen 1.6.2

### Refactoring
- Removed deprecated_options from domains

## 0.46.4 (January 3, 2017)

### Enhancements
- **General Changes**
  - Slice now provides better feedback when failing to login
- **Check Changes**
  - Deleted checks are no longer run when resetting sheet checks
- **Project Changes**
  - The default site created for projects now has a short name of "Default Site"
    instead of "DS"

### Bug Fixes
- Fixed an issue where missing project email preferences would cause daily
  digests emails to not be sent out
- Site editors now correctly see all their projects in the project archives
- Site viewers no longer see a create subject prompt after searching for a
  non-existent subject code
- Project invite links are now only displayed to the user who generates the
  invite
- Navigating across index pages no longer resets the search criteria and filters
- Fixed an issue filtering sheets from links on the summary report
- Viewers no longer are shown New Sheet, New Event, and Report Adverse Event
  buttons on subject pages

## 0.46.3 (December 6, 2016)

### Enhancements
- **Project Changes**
  - Simplified actions dropdown on team page
- **Pats Changes**
  - Simplified the PATS Data Quality tables

## 0.46.2 (December 5, 2016)

### Bug Fix
- Fixed an issue selecting radio buttons in Internet Explorer

## 0.46.1 (December 5, 2016)

### Bug Fixes
- Fixed a bug that incorrectly set domains as characters instead of numeric in
  SAS export
- Fixed a bug that occurred when returning an empty set of sheets for projects
  without date variables
- Fixed a bug that prevented sheet editing when comparing multiple checkbox
  responses against archived domain options

## 0.46.0 (December 5, 2016)

### Enhancements
- **Check Changes**
  - Check results are now precomputed to improve the speed of the sheets index
- **Design Changes**
  - Improved filtering designs by name on design index
- **Domain Changes**
  - Improved how domain options are stored and updated
  - Domain options can now be archived
- **Report Changes**
  - Design reports are now grouped and sorted by category
- **Sheet Changes**
  - Improved display of changes to signature variables in sheet transactions
  - Question styling now alternates dynamically based on hidden questions
  - Sheet coverage is now computed asynchronously
  - Sheets can now be sorted and filtered by coverage percentage
    - `coverage:>=80`
    - `coverage:missing`
    - `coverage:100`
- **Variable Changes**
  - Variable layouts have been simplified to "Inline" and "Underneath"
- **Gem Changes**
  - Updated to Ruby 2.3.3

### Bug Fix
- Search filter `has:adverse-events` now correctly returns subjects with any
  adverse event on the subject index
- Deleting an event now correctly removes subject_event associations
- Fixed a bug that incorrectly formatted column names in exports for domains
  with negative values
- Fixed display of designs and sites on project reports
- Subject code error now displays correctly when entering a format invalid for
  the currently selected site

### Refactoring
- Removed deprecated_grid_variables from variables
- Removed description from designs

## 0.45.4 (December 1, 2016)

### Enhancements
- **PATS Changes**
  - Added exports for PSG Overall Study Quality and PSG Study Passed tables

## 0.45.3 (November 29, 2016)

### Enhancements
- **PATS Changes**
  - Overall expected randomizations by month added to randomizations graph

## 0.45.2 (November 29, 2016)

### Enhancements
- **PATS Changes**
  - Race is now exported as Black, White, American Indian, Asian, Native
    Hawaiian, Multiple, and Unknown

## 0.45.1 (November 22, 2016)

### Bug Fix
- Fixed a bug that prevented designs from being edited when a checkbox or radio
  variable was set to display as a scale

## 0.45.0 (November 11, 2016)

### Enhancements
- **General Changes**
  - Dropped support for Ruby 2.2
  - Improved styling of error messages across several pages, error messages now
    appear inline
- **Adverse Event Changes**
  - Improved styling of files attached to adverse events
- **Design Changes**
  - String and numeric inputs without prefixes, suffixes, and units now display
    properly on design builder
  - Time duration, imperial height, and imperial weight variables can now be
    used in branching logic, using total seconds, total inches, and total ounces
    respectively as comparison
  - Time duration, imperial height, and imperial weight variables can now be
    used by calculated variables
  - Design descriptions have been removed and moved into a subsection on the
    design itself
  - Sections are no longer required to have a name and can exist with simply a
    description
  - Designs not on a specific event are now listed when adding a new event to
    an existing subject
- **Randomization Changes**
  - Improved the display of subject randomization on mobile devices
  - Improved documentation on setting up a new randomization scheme
  - The Schedule of Events PDF now includes the subject code
- **Report Changes**
  - The subject report has been removed
  - Design short names are now used on mobile versions of reports
  - Overview reports for designs on multiple events can now be filtered by event
- **Search Changes**
  - Search results now include projects and designs that contain the search term
- **Sheet Changes**
  - Switching the order on the sheets index no longer resets other filters
  - Sheets can now be filtered by event
    - `events:NAME[,NAME]`
  - Sheets can now be filtered by variables that use seconds, minutes, hours for
    time durations, inches and feet for imperial height, and ounces and pounds
    for imperial weight
    - `time:>3600`
    - `time:>3600s`
    - `time:>60m`
    - `time:>1h`
    - `height:>=72`
    - `height:>=72in`
    - `height:>=6ft`
    - `weight:<=64`
    - `weight:<=64oz`
    - `weight:<=4lb`
  - Sheets can now be filtered by responses that include spaces
    - `staff:"Dr John"`
  - Sheets can be filtered by those not associated with an event
    - `events:missing`
- **Subject Changes**
  - Subject index search now autocompletes:
    - Subject Codes
    - `is:randomized`
    - `not:randomized`
    - `adverse-events:open`
    - `adverse-events:closed`
    - `has:comments`
    - `has:files`
  - Filters no longer reset when adding new filters or sorting
  - Entering an exact subject code on the subject index now redirects to the
    subject page
- **Task Changes**
  - Blinding indicator updated to be consistent with designs and events
- **Variable Changes**
  - Variables in grids are now validated when saving a sheet
  - Improved display of validation messages for grids
  - Date range formatting improved on variable show page
  - Time duration is now stored as total seconds in database
    - This change allows Slice to display better graphs and statistics on the
      design overview and advanced design reports as well as provide sheet
      filters based on the total number of seconds, minutes, or hours
- **Theme Changes**
  - Improved hover effect color for night theme on design builder

### Bug Fix
- Missing sheets are no longer included in the daily digest
- Fixed a bug that prevented sheets from being saved after a date field was
  changed to required

### Refactoring
- Grid variables are no longer stored as an array, and are now defined by proper
  database relationships
- Removed deprecated options column from designs

## 0.44.1 (October 31, 2016)

### Enhancements
- **Design Changes**
  - Design abbreviations are now displayed on the design index

### Bug Fix
- Fixed a bug that prevented sheets index from returning results when searching
  for a non-existent variable
- Branching logic is now saved correctly when adding a new section to a design
- Fixed styling of variable preview on variable show page on mobile

## 0.44.0 (October 31, 2016)

### Enhancements
- **General Changes**
  - Added a sitemap to index public documentation on Google and Bing
  - Added Google Analytics for documentation pages
- **Check Changes**
  - Checks can be archived from the check index
  - Sheets that fail checks now have an alert next to them on the sheets index
  - An alert message can be added to a check that describes why the sheet is
    flagged on the sheets index
  - Check names can now be used to filter sheets on the sheet index:
    - `checks:any`
    - `checks:NAME[,NAME]`
- **Design Changes**
  - Designs can now have a short name specified
- **Event Changes**
  - Event slugs now autofill when writing a new event name
  - Events can be archived from the event index
- **Export Changes**
  - Calculated variables are now formatted as numeric in SAS exports
  - `Sheet Created` is now included in exports
  - Missing sheets now appear in exports and are labeled in SAS and R exports
- **Project Changes**
  - Project activity can now be filtered by site and by user
- **Notification Changes**
  - Improved notification bell animation
- **Sheet Changes**
  - Improved the autocomplete capability for search filters
    - Check names will be recommended for autocompletion
    - Variable values will be recommended for autocompletion
  - New sheet search filters added to filter by sheets associated with adverse
    events, that have comments, and that have files
    - `has:adverse-events`
    - `has:comments`
    - `has:files`
- **Variable Changes**
  - Field notes can now be added that are displayed when entering a sheet

### Bug Fix
- Fixed a bug in computing the correct column in the R export script
- Fixed time duration not clearing correctly when formatted with only minutes
  and seconds
- Fixed a bug that created two sheets when the submit button was double-clicked
- Fixed a bug that allowed site members to see tasks from other sites

## 0.43.0 (October 24, 2016)

### Enhancements
- **Adverse Events**
  - Typing a non-existent subject code now indicates that no matching
    subjects were found
  - Subject code is no longer editable when launching an adverse event directly
    from the subject page
- **Blinding Module Changes**
  - Additional indicators added to show designs, events, and sheets that are
    only viewable by unblinded members
  - Unblinded project editors can no longer disable project blinding setting
- **Design Changes**
  - Double-clicking "Add Variable" no longer attempts to add a variable twice
    in the design builder
  - Improved the design builder interface
  - Sections have two new levels, "Warning" and "Alert"
- **Event Changes**
  - When adding a new event to a subject, events that already exist are shown
- **Export Changes**
  - Exports now create an in-app notification when complete, and no longer send
    an email notification
- **Project Changes**
  - A calendar has been added that shows tasks, adverse events, and
    randomizations
    - Task windows are now viewable on calendar
  - URL slugs are now removed when projects are deleted
- **Randomization Changes**
  - Typing a non-existent subject code now indicates that no matching
    subjects were found
  - Subject code is no longer editable when randomizing directly from the
    subject page
- **Report Changes**
  - Report sheet filters now use new sheet search syntax
  - Improved layout of the design overview report
  - Design overview reports can now link to specific variables on the report
- **Sheet Changes**
  - Sheet index filters no longer clear other preset filters
  - Search filters can now find sheets for variables with `entered`, `present`,
    `any`, `missing`, `unentered`, and `blank` values
    - `bmi:any`
    - `bmi:missing`
    - `entered` and `present` are identical aliases
    - `unentered` and `blank` are identical aliases
    - `entered` and `unentered` are opposites
    - `present` and `blank` are opposites
    - `any` and `missing` are opposites
  - Sheet coverage percentage colors now match selected theme
  - Date and time validations changed to occur only after the variable loses
    focus
- **Subject Changes**
  - Sheet transactions and reassigning a sheet now properly highlight the event
    in the subject side navigation
- **PATS Changes**
  - Graphs now contain an overall total that is hidden by default
  - Graphs and tables are now broken down by month instead of by week

### Bug Fixes
- Fixed a bug that caused autocomplete suggestion menu from rendering twice
- Fixed a bug that prevented footer links from being clicked in Internet
  Explorer and Firefox
- Fixed a bug that displayed the wrong number of randomizations by treatment
  arm for individual sites

## 0.42.0 (October 11, 2016)

### Enhancements
- **General Changes**
  - Improved styling on projects and users pages
  - Improved menu and header display on index pages
  - Improved display of pagination on mobile devices
  - Added a simplified search for index pages
- **Adverse Event Changes**
  - Adverse events are now numbered on the project-level
- **Comment Changes**
  - Improved comment and anchor link navigation
- **Check Changes**
  - Checks can now find sheets and subjects that are missing a response for a
    variable
- **Design Changes**
  - Blinding status and category added to design index
  - Design index can be filtered by category
- **Email Changes**
  - Project-level emails now take into account project-specific email
    preferences of the user
- **Project Changes**
  - Improved new project setup workflow
  - Removed `subject_code_name` from project settings to simplify setup
  - Project names no longer save trailing whitespace
  - Project sorting now ignores case when sorting by name
  - Site creation integrated with project setup
  - User invites integrated with project setup
  - Project menu redesigned to adjust to different screen sizes
  - Added an advanced project settings page
  - Collecting emails on surveys has been removed as a project setting
  - Clicking project member invites now copies the URL to the clipboard
  - Site member invites now get directed to the main project page instead of the
    site specific page
- **Sheet Changes**
  - Improved search on sheet index
    - Search by sheet creation date:
      - `created:2016-05-30`
      - `created:>2016-05-30`
      - `created:<2016-05-30`
      - `created:<=2016-05-30`
      - `created:>=2016-05-30`
    - By variable name:
      - `age:>4`
      - `bmi:<5`
      - `consented_yes_no:1`
    - By randomized:
      - `is:randomized`
      - `not:randomized`
  - Clicking the sheet validation message now scrolls to the first field that
    caused the error message to display
  - Sheets no longer display attached files that were removed from the
    corresponding design
- **Subject Changes**
  - Subject menu redesigned to adjust to different screen sizes
  - Adverse events and randomizations now included in subject menu
  - Adverse events are now displayed on the subject timeline
  - Subject index can be filtered by:
    - `is:randomized`
    - `not:randomized`
    - `has:ae`
- **Treatment Arm Changes**
  - Treatment Arms can now have short names
- **PATS Changes**
  - Added "Caregiver not interested - Other Reasons" to PATS tables
- **Gem Changes**
  - Updated to pg 0.19.0
  - Updated to jquery-rails 4.2.1

### Refactoring
- Renamed `project_favorites` to `project_preferences`

### Bug Fix
- Fixed a bug that prevented check filters from correctly checking variables
  that looked like numeric values

## 0.41.3 (September 23, 2016)

### Enhancements
- **PATS Changes**
  - Reduced the number of tables shown on elgibility table for consented
    participants

## 0.41.2 (September 19, 2016)

### Enhancements
- **PATS Changes**
  - Elibility status export added for consented participants

## 0.41.1 (September 13, 2016)

### Enhancements
- **PATS Changes**
  - Updated eligibility status report to include breakdown tables for "ENT
    requirement not met" and "PSG requirement not met"

## 0.41.0 (September 12, 2016)

### Enhancements
- **General Changes**
  - Alerts added to warn a user before a timeout to give a chance to stay active
  - Improved visibility of notifications bell icon
  - Started work on a new landing page
  - Created new visual identity for Slice
  - Added new themes:
    - Enterprise
    - Underwater
    - Spring
    - Fall
  - Minimalist theme is now the new Default theme
  - Users can now update their email and name in the account settings
- **Documentation Changes**
  - Added public facing documentation
  - Documentation currently covers designs, sections, variables, domain, data
    entry, sites, roles, blinding, randomizations, treatment arms,
    stratification factors, adverse events, reports, data quality checks,
    notifications, project setup, and Slice lingo.
- **Data Quality Checks**
  - Project editors can now define data quality checks that are used to identify
    sheets that have inconsistent data
- **Design Changes**
  - Designs can be flagged to ignore project-wide auto-locking
- **Event Changes**
  - Events that contain designs that are for unblinded-only, now properly hide
    these sheets from blinded project members
- **Sheet Changes**
  - Sheet saving errors are now written to a hidden field on the form to allow
    debugging of branching logic mistakes
- **Randomization Changes**
  - Randomization characteristics now always record subject site to help display
    site breakdowns on distribution tables
  - Schemes using minimization algorithm must now have one non-site
    stratification before being published
- **Report Changes**
  - Design options are now sorted correctly when adding a new filter to an
    advanced design report
- **Search Changes**
  - Searching no longer matches the middle of words unless the search query
    string starts with a space
- **Site Changes**
  - Site short names can now be configured
- **Survey Changes**
  - Minor UI changes to survey submit button and associated text
- **Variable Changes**
  - Improved the display of domains when editing variables
  - Editing variables no longer shows an overwhelming amount of additional
    information
  - Imperial height and weight are now marked as integers in the data dictionary
    export as well as listing the appropriate units, inches and ounces
- **PATS Changes**
  - Updated screen failures report to include previous OSA diagnosis
- **Gem Changes**
  - Updated to rails 5.0.0.1

### Refactoring
- Added an index back to correct a reverse migration

### Bug Fixes
- Rails model errors are now again correctly styled using Bootstrap CSS classes
- Fixed a bug that prevented permalinks from generating URL correctly
- Fixed a bug that sent out multiple site invitation emails
- Fixed an issue displaying tooltips in search results
- Fixed a bug that prevented calculated fields from updating correctly when
  depending on the result of another calculated field
- Fixed an issue exporting checkboxes stored in grids

## 0.40.4 (August 4, 2016)

### Bug Fix
- Fixed a bug that caused unintended scrolling while editing a design

## 0.40.3 (August 3, 2016)

### Refactoring
- Updated kaminari pagination views to use haml
- Updated non-turbolinks links to use new attribute syntax

### Bug Fix
- Fixed a bug that prevented users from logging in correctly with an expired
  authenticity token

## 0.40.2 (July 19, 2016)

### Bug Fix
- Fixed emails in forked processes from not being sent

## 0.40.1 (July 14, 2016)

### Bug Fix
- Fixed a bug that prevented files from being attached to sheets

## 0.40.0 (July 12, 2016)

### Enhancements
- **Project Changes**
  - Reduced the number of subjects displayed on the project index to improve
    load time
- **Gem Changes**
  - Updated to rails 5.0.0
  - Updated to devise 4.2.0

## 0.39.0 (July 11, 2016)

### Enhancements
- **General Changes**
  - Updated styling of headings in callouts
- **Event Changes**
  - The event show page now differentiates between entered, unentered, and set
    as missing designs for subjects that have been assigned the event
  - Event index now displays how many events have been launched
  - Dragging a locked sheet to a new event now displays a message to the user
    that the sheet has been locked
- **Notification Changes**
  - Unlocking a sheet after an unlock request will now mark the associated
    notification as read for others notified as well
- **PATS Changes**
  - Added a task to export recruitment data for the PATS project
- **Report Changes**
  - Randomizations graph now only displays ticks at whole numbers intervals
- **Subject Changes**
  - Subject events that fall on the same day are now ordered by event position
- **Variable Changes**
  - Improved the variable type filter on the variables index
  - Clock time variables now let AM or PM to be set as default when using the
    12-hour clock
- **Gem Changes**
  - Updated to carrierwave 0.11.2
  - Updated to colorize 0.8.1
  - Updated to simplecov 0.12.0
  - Updated to turbolinks 5
  - Removed dependency on contour

### Bug Fix
- Fixed a bug that would export zero sheets when exporting Adverse Events
  alongside other CSVs
- Fixed an issue that caused the sheet creator to not be set when a sheet was
  set as missing

### Refactoring
- Removed deprecated `scheduled` column from events
- Removed deprecated `status` column from subjects
- Removed deprecated `status` column from subject events
- Removed deprecated `pagination` column from users

## 0.38.0 (June 8, 2016)

### Enhancements
- **Design Changes**
  - Improved padding between section heading images and surrounding elements
  - Required design options now have an indicator on the design reorder page
  - Complete variable names are now shown on the design reorder page
  - "Required on form?" can now be changed more easily in the design editor
- **Email Changes**
  - Changed Password Reset Email Reminder subject line to prevent email from
    getting marked as spam
- **Export Changes**
  - Data dictionary exports now include all designs on the project regardless of
    whether or not a sheet has been created from the design
  - Variable required status is now included in the data dictionary export
  - Checkbox variables are now split apart into component variables in the data
    dictionary export to better reflect the columns in the sheet data export
  - Adverse Events and Randomizations can be selected added when generating an
    export
  - The project export form now remembers the last export configuration when
    generating a new export
- **Handoff Changes**
  - Updated tablet handoff text with better information on preparing tablet for
    handoff to participant
- **Project Changes**
  - Blinded status can now be updated by unblinded project editors on the
    collaborators page
- **Randomization Changes**
  - Added a message to inform project editors that randomization lists expand
    automatically after each randomization
  - Randomization assignments can now be exported to CSV
- **Report Changes**
  - Integer variables no longer include decimal places on custom reports
- **Sheet Changes**
  - A file attachment indicator has been added to the sheets index
  - Setting a missing sheet as not missing no longer prompts the project editor
    to unlock the sheet first for projects with sheet auto-locking enabled
  - Hitting `Enter` while editing a sheet no longer submits the sheet
- **Subject Changes**
  - The following filters have been added to the subject index:
    - Filter by site
    - Filter by randomization status
    - Filter by open and closed adverse events
  - Sheets on subject page are now listed alphabetically
  - Adverse events on subject page now provide a brief description as well
- **Variable Changes**
  - Updated how variable options are displayed on the variables index
  - Variables marked as integer now display a validation error if the input is
    in decimal format
  - A new variable type, Imperial Height, has been added that allows data entry
    for a height in feet and inches
  - A new variable type, Imperial Weight, has been added that allows data entry
    for a weight in pounds and ounces
- **Gem Changes**
  - Updated to Ruby 2.3.1
  - Updated to kaminari 0.17.0

### Refactoring
- Removed unused project news, contacts, documents, and links

### Bug Fixes
- Fixed a bug that prevented the last created sheet from appearing under Recent
  Activity if the sheet was publicly created
- Fixed a minor layout issue when editing events
- Fixed a bug in Firefox that prevented tabs from being closed using browser
  `Command + w` shortcut
- Fixed a bug that prevented filters from being edited on project reports

## 0.37.1 (March 24, 2016)

### Enhancements
- **Notification Changes**
  - Notifications are now removed if the associated sheet unlock request,
    adverse event, or comment is deleted

### Bug Fix
- Fixed a bug escaping html elements when displaying notifications
- Fixed a bug displaying `<` in sheet comments
- Fixed a bug that prevents project and site editors from editing comments

## 0.37.0 (March 21, 2016)

### Enhancements
- **Project Changes**
  - The project setting for manually locking sheets has been removed
  - A new project setting called "Sheet Auto-locking" has been added
    - Sheets can be set to auto-lock:
      - `Never`
      - `After 24 hours`
      - `After 1 week`
      - `After 1 month`
    - Sheets that have been auto-locked can be unlocked for an additional time
      period by project editors
    - Site editors can make a request to temporarily unlock auto-locked sheets
    - Project editors are notified of sheet unlock requests by email and by
      in-app notifications
    - Project editors can view recent sheet unlock requests for a locked sheet
    - Comments can still be made on sheets that have been locked
    - When a sheet is unlocked, recent site editors who requested an unlock
      receive an email notification that the sheet can be edited
    - Project editors can delete unlock requests, and site editors can delete
      unlock requests they have made themselves
- **Subject Changes**
  - Randomized subjects can no longer be deleted
    - In order to delete a subject, a subject's randomizations need to be undone
- **Subject Event Changes**
  - Setting a sheet as missing will now stay on the page the user was on instead
    of redirecting
- **Email Changes**
  - Removed margins in emails to display better across email clients

## 0.36.2 (March 16, 2016)

### Bug Fix
- Fixed a bug that prevented additional options from being added to domains
  while editing designs (reported by @michellereid)

## 0.36.1 (March 14, 2016)

### Bug Fix
- Fixed a bug that prevented design variables and sections from being updated

## 0.36.0 (March 14, 2016)

### Enhancements
- **Email Changes**
  - Improved the responsiveness and display of emails on smaller devices
- **Gem Changes**
  - Updated to rails 4.2.6
  - Updated simplecov 0.11.2
  - Updated to jquery-rails 4.1.1
- **Sheet Changes**
  - Section warning descriptions are now included on PDFs
- **Mobile Changes**
  - Recruitment table on project reports page now optimized for mobile
  - Project menu is now visible on the bottom of subjects index on mobile
  - Dashboard projects no longer require two clicks to view on mobile

### Bug Fixes
- Fixed a bug that prevented the recruitment chart from displaying at minimum
  six months
- Fixed a bug that prevented designs from being imported correctly
- Fixed a bug that caused advanced design reports from displaying correctly when
  a variable was chosen that didn't have any responses
- Fixed an animation issue for the notification bell on mobile devices

### Refactoring
- Completed cleanup, refactoring, and additional testing of controllers
- Refactored design imports and reports to dedicated controllers
- Removed design JSON exports and imports, and design copying
- Removed saving custom reports

## 0.35.1 (March 1, 2016)

### Enhancements
- **Gem Changes**
  - Updated rails to 4.2.5.2

## 0.35.0 (February 25, 2016)

### Enhancements
- **Randomization Changes**
  - Removed "x of y" information from randomization page to keep the page
    cleaner
  - `Tab` now also prefills stratification factors when entering subject code
    during randomization
  - On randomization index, the link to treatment arm is now only shown for
    project editors
  - Schedule of upcoming tasks can be printed out when a subject is randomized
  - Randomization notifications are now limited to project and site members
- **Report Changes**
  - Cumulative Randomized chart now starts one month before first randomization

### Refactoring
- Removed deprecated `sub_section` attribute from `Section`s
- Started cleanup and refactoring, and additional testing of controllers

### Bug Fix
- Fixed a bug that launched tasks based on incorrect randomization date

## 0.34.1 (February 24, 2016)

### Bug Fix
- Fixed a bug that prevented site editors from seeing a list of events

## 0.34.0 (February 24, 2016)

### Enhancements
- **Adverse Event Changes**
  - Comments are no longer displayed on sheets attached to adverse events
  - Adverse event dates can no longer be set in the future
  - Unblinded project and site members are now notified of changes to adverse events
- **Dashboard Changes**
  - Only favorited projects are now sortable on the dashboard, current projects are listed alphabetically
- **Design Changes**
  - Sections can now be specified as "Main Section", "Subsection", and "Warning"
  - Section names no longer need to be unique on a design
- **Domain Changes**
  - Domain options can be set to be only visible by a certain site editor to limit the number of options for dropdowns, radio inputs, and checkboxes
- **Event Changes**
  - Events can now be set to be only visible by unblinded project and site members
- **Export Changes**
  - Grids are no longer exported if the project does not include grid variables
- **Project Changes**
  - Unused double data entry module has been removed
  - General statistics have been added to the project's reports index
  - Recruitment graph for cumulative randomizations has been added to the project's report index
- **Randomization Scheme Changes**
  - Expected recruitment by month and by site numbers can now be added to a randomization scheme
    - These expected numbers are displayed on the cumulative randomizations and recruitment chart
- **Report Changes**
  - Statistics for uploaded files have been improved in design overview reports
- **Settings Changes**
  - Users can now change their password on the settings page
- **Sheet Changes**
  - Sheets can now be sent to subjects via a shareable link
    - Only design marked as publicly available can be shared in this manner
  - Site column is no longer displayed on sheets index to site members on a single site
- **Site Changes**
  - Subjects on a site are now deleted when the site is deleted
- **Subject Changes**
  - Searching for subjects no longer displays a 'v' for existing subjects
  - Sheets on events can now be marked as missing
- **Gem Changes**
  - Updated rubyzip to 1.2.0

### Bug Fix
- Fixed a bug that prevented email settings from being saved
- Fixed a bug that inserted placeholder text into textarea elements when using Internet Explorer in combination with Turbolinks
- Fixed a bug that caused scroll bars to jump erratically in dropdown variables

### Refactoring
- Variable valuables have been replaced by variable formatters

## 0.33.3 (February 16, 2016)

### Bug Fix
- Fixed a bug that prevented adverse events from being created

## 0.33.2 (February 16, 2016)

### Bug Fix
- Fixed a bug that prevented adverse event emails from being sent

## 0.33.1 (February 15, 2016)

### New Feature
- **Tasks Added**
  - Project tasks have been added to track upcoming todos given a due date and a window
- **Randomization Changes**
  - A series of tasks can be launched when a subject is randomized

### Enhancements
- **Adverse Event Changes**
  - A master list of adverse events and their corresponding sheets is now included in the adverse event export
- **Notification Changes**
  - Notifications that overwrite an existing notification update the time created for better sorting
- **Variable Changes**
  - Time durations now fully spell out hours, minutes, and seconds
  - Time durations can now be set as required or recommended on designs

### Bug Fixes
- Fixed a display bug that incorrectly showed the algorithm used for randomization schemes

## 0.33.0 (February 12, 2016)

### Enhancements
- **Adverse Event Changes**
  - Adverse event notifications are no longer sent out if a user has emails disabled
  - Adverse events now prompt the user to fill out additional forms if one or more exists
  - Adverse Event exports are now bundled with a labeled CSV export of all sheets related to adverse events
- **Export Changes**
  - Rewrote the data exporter to more efficiently export large amounts of data
  - Default exported columns have been simplified to: `Subject`, `Site`, `Event Name`, `Design Name`, `Sheet ID`
- **Design Changes**
  - The menu on the design edit page has been removed to provide more room while editing
- **Variable Changes**
  - A new variable type, Time Duration, has been added that allows data entry for a duration of hours, minutes, and seconds
  - Time variables can now be set as 24-hour (default) or 12-hour for data entry and display
  - Display of seconds on time variables can now be toggled on and off
  - Display format of time durations can be `hh:mm:ss`, `hh:mm`, and `mm:ss`
  - Date variables can now be additionally formatted in dd-MMM-yyyy format, ex: 08-FEB-2016
  - Calculation fields can now be set as hidden during data entry
- **Notification Changes**
  - Adverse event changes, sheet comments, and tablet handoff completions now have in-app notifications
- **Project Changes**
  - Added script to copy projects as templates to allow user testing of subject creation and sheet entry
  - The main project page now displays an improved subjects index
  - Project reports have been moved to a new menu tab
  - Acrostics enabled setting has been removed
    - For projects that require acrostics, these can be added with the subject_code
    - Subject Code can be renamed at the project-level to indicate that they include acrostics as well
- **Randomization Changes**
  - Stratification factors can now have a calculation set that enforces that randomization criteria be selected that match data entered for the subject
- **Site Changes**
  - Site `prefix`, `code_minimum`, and `code_maximum` have been removed in favor of `subject_code_format`
- **Sheet Changes**
  - Sheets now display the event in the breadcrumbs if the sheet is on an event
- **Subject Changes**
  - Subject statuses have been removed
  - Subject acrostics have been removed
- **Category Changes**
  - Designs associated to categories are now listed on the category page

### Bug Fix
- Fixed an issue that prevented the R script from reading grids correctly
- Fixed an issue retrieving the last updated sheet on the subjects index
- Fixed a visual bug that prevented export progress bar from advancing when starting an export
- Fixed a bug that could cause incorrect validation of required fields hidden by branching logic
- Fixed a bug that prevented overlap from working for calculations on calculated variables

## 0.32.3 (February 3, 2016)

### Enhancements
- **Site Changes**
  - Project editors can now enforce subject code formats by site for subjects

## 0.32.2 (January 27, 2016)

### Enhancements
- **General Changes**
  - Made use of Ruby's new Frozen String Literal pragma to reduce string memory allocation
  - Adjusted text for better consistency when deleting items
- **Project Changes**
  - Adjusted width of project left-hand menu to better fit the menu items on smaller screens
  - Improved user interface on project share page
- **Event Changes**
  - Launch Tablet Handoff is now only displayed for events that have a design that is marked for handoff
  - Simplified user interface when launching a new event for a subject
- **Subject Changes**
  - Simplified creation of subjects, and defaulted to "Add Event" or "Data Entry" views for subjects with no data
  - Subjects index has been redesigned, events have been removed, and recent activity, adverse events, and randomizations have been added
  - Adverse Events can now be reported directly from subject pages
  - Started work on removing subject status
- **Registration Changes**
  - Minor grammar fix on sign up page

### Bug Fixes
- Fixed a bug that prevented autocompletes in grids from displaying search results

## 0.32.1 (January 26, 2016)

### Enhancements
- **Adverse Event Changes**
  - Removed "Serious"/"Non-Serious" indicator for adverse events
- **Design Changes**
  - Editing calculation has been removed from numeric and integer variables
  - List of variables is no longer displayed on design show page
  - Ranges can now be edited for numeric and integer variables
- **Randomization Changes**
  - Stratification factors are now ordered by name, and options by value
- **Sharing Changes**
  - List of sites on project share page is now alphabetized
- **Sheet Changes**
  - Simplified sheets UI and removed drop shadow
- **Gem Changes**
  - Updated to rails 4.2.5.1
  - Updated to Ruby 2.3.0
  - Updated to jquery-rails 4.1.0
  - Updated to simplecov 0.11.1
  - Updated to web-console 3.0.0

### Bug Fixes
- Fixed a small issue that caused the menu bar to be misaligned in Firefox
- Fixed a minor UI issue with the comment edit and delete buttons
- Fixed font size of page counter and pagination in Internet Explorer
- Project menu is no longer represented by icons
  - This fixes an issue in Internet Explorer that occassionally prevented font downloads in restrictive security states

## 0.32.0 (November 16, 2015)

### Enhancements
- **Tablet Handoff Module Added**
  - Projects can now enable a new tablet handoff module
  - The tablet handoff module allows a staff member to handoff a tablet and have a subject fill out a series of forums
  - A tablet handoff can be resumed if an interruption occurs while completing the assigned forms
  - Designs must be set as `Handoff Enabled` when assigning designs to events in order for them to be filled out during a tablet handoff
- **Project Changes**
  - Project slugs are now auto-generated when creating new projects
- **Sheet Changes**
  - Improved the styling of section and subsection headers on sheets
- **Comment Changes**
  - Comments can now be edited from sheet page, subject timeline, subject comments index, and recent activity pages
- **General Changes**
  - Improved several error messages to better inform the users on the intent of certain features
- **Gem Changes**
  - Updated to rails 4.2.5
  - Updated to pg 0.18.4
  - Started testing of Ruby 2.3.0-preview1

### Refactoring
- Simplified how surveys, public sheets, handoffs, and internal sheets handle autocomplete fields, formatting numbers, adding rows to grids, and displaying images

### Bug Fix
- Fixed a bug that prevented project viewers from searching for subjects and viewing subject events
- Fixed an issue saving email preferences on user settings page
- Fixed a bug that included test subjects and sheets for deleted subjects when doing an export

## 0.31.1 (November 12, 2015)

### Enhancement
- Temporarily removing allowlist of file uploads to accommodate .ABP files

## 0.31.0 (November 9, 2015)

### Enhancements
- **Comment Changes**
  - Styling of comments is now consistent across sheets, adverse events, and subject timeline
- **Sheet Changes**
  - Simplified colors for sheet coverage
- **Adverse Event Changes**
  - Adverse events can now be exported in CSV format by unblinded project owners and editors
- **Account Changes**
  - Accounts now lock after failed password attempts
  - Implemented a new password policy
  - Session timeouts now navigate back to the login page

### Bug Fix
- Fixed an issue where test subjects were being counted in event reports

### Refactoring
- Started cleanup and reorganization of SCSS stylesheets
- Adjusted line-height on form controls and input boxes
- Removed schedules and subject schedules, these have been replaced by events and subject events

## 0.30.3 (October 22, 2015)

### Bug Fix
- Text areas and scale variables now use full width of window again

## 0.30.2 (October 20, 2015)

### Bug Fix
- Fixed an issue where the generic uploader wasn't allowing RTF files from being attached to sheets (reported by mnicholson)

## 0.30.1 (October 20, 2015)

### Bug Fix
- Fixed an issue displaying design overviews for designs with sections (reported by ekaplan)

## 0.30.0 (October 20, 2015)

### Enhancements
- **General Changes**
  - Updated the menu to make better use of space and added back global search bar
  - Improved project and subject level navigation with better tabbed submenu
  - Disabled request caching to prevent Internet Explorer from displaying outdated pages
- **Sheet Changes**
  - Sheet creation workflow now requires selecting an existing subject and design
- **Design Changes**
  - Improved the method in which variables and sections are linked to designs
  - Designs can now be categorized
    - Categorized designs will be grouped together on the subject data entry page
    - Categories can be linked to the Adverse Event module
- **Export Changes**
  - Data exports have been simplified and now have their own dedicated page
- **Adverse Events Added**
  - Added a project-level adverse events reporting module
  - Project editors can add comments and open and close AEs
  - Files can be attached to an adverse event
  - Sheets can be filled out and added to an adverse event
  - Users receive a notification if they have viewable adverse events that have new updates, comments, files, or forms
  - Unblinded project editors and project owner are notified when an adverse event is reported
- **Blinding Module Added**
  - Projects can now enable blinding and unblinding of project and site members
  - Designs are set to be viewable by all, or only to be viewable by unblinded members
  - Creation of randomizations and adverse event reports is limited to unblinded members
- **Gem Changes**
  - Updated to pg 0.18.3
  - Removed minitest-reporters

### Refactoring
- Updated syntax of JavaScript responses rendered by server
- Updated syntax for calling partials and passing variables
- Rewrote views using haml

### Bug Fix
- Fixed a bug that prevented new variables from being created on projects with slugs
- Fixed a bug that would display alt image text for user gravatars that failed to load
- Fixed an issue with SAS export where project variables could have the same name as sheet meta variables

## 0.29.1 (September 10, 2015)

### Enhancements
- **Validation Changes**
  - Updated rake task to cache sheet validation status

### Bug Fix
- Fixed a bug saving large floating point numbers
- Randomization emails now correctly state who randomized the subject

## 0.29.0 (September 8, 2015)

### Enhancements
- **Randomizations Added**
  - Project owners and editors can now add one or more randomization schemes to a project
  - Randomization Scheme page shows distributions and totals by:
    - Stratification Factors
    - Site
  - Two types of randomization algorithms are available to use
  - Randomization criteria are now stored when subject is randomized
  - When site is used as a stratification factor, subjects can only be stratified to their own site
  - Site editors can create for subjects on their site
  - Site viewers can view all existing randomization for subjects on their site
  - Only project owners and editors can undo randomizations
  - Subject randomization criteria can be set to only allow distinguishing between eligible and ineligible subjects
  - Randomization goal progress bar is displayed for each randomization scheme
  - **Permuted-block Algorithm**
    - Treatment Arms can now be specified along with a weight allocation
    - Different block sizes and allocations can now be specified
    - Stratification Factors with options can now be specified
    - Site can now be a stratification factor
    - Randomization goal can now be specified
  - **Minimization Algorithm**
    - Dynamic randomization can now be done as an alternative to Permuted-block Algorithm
    - Each randomization keeps track of the treatment arm selection process
    - When site is a stratification factor, the minimization randomization scheme creates a list for each site
      - Additionally, when site is a stratification factor, it is not included again when comparing existing weighted ratios of treatment arms by stratification factor
    - Chance of Random Treatment Arm Selection can now be set between 0 (Never Random) and 100 (Always Random), and is set to 30 by default
  - **List Generation**
    - Lists are generated as a product of the stratification factor options
    - Lists are popuplated by block groups based on shuffled block size multipliers and treatment arm allocations
    - Lists are expanded dynamically with new block groups when new subjects are randomized in cases when the lists runs out of available randomizations in previous block groups
    - Lists can be regenerated if they have zero randomized subjects
    - Stratification options can be added, and corresponding lists can be generated after a randomization scheme is published
    - Stratification options cannot be removed after a randomization scheme is published
  - **Subject Randomization**
    - Subjects can now be randomized to lists
    - Randomizations can be undone
    - Randomizations track the user who randomized the subject and the time the subject was randomized
    - Emails are sent when subjects are randomized
- **General Changes**
  - Emails sent by web application are now opt-in by default
- **Admin Changes**
  - Admins can now disable user emails for accounts with invalid emails
- **Design Changes**
  - File type variables can no longer be added to grids
  - Public surveys now adhere to variable validations on form submission
- **Variable Changes**
  - Added better variable validation framework
    - Users are provided more immediate feedback when entering data on sheets
    - Data uses the same validation checks when saving on the server
    - Branching logic and hidden required variables are accounted for during validation
- **Sheet Changes**
  - Removed the Save and Continue button as it does not make as much sense in a subject-centric model
- **Project Changes**
  - The invite process for new project and site, editors and viewers, has been simplified to allow new users to get added after registration to the appropriate site or project
  - Inviting collaborators to a project now also provides search results when typing in an email of an associated user
  - The new randomization module can be enabled in the project settings
- **Gem Changes**
  - Use of Ruby 2.2.3 is now recommended
  - Updated to rails 4.2.4
  - Updated to contour 3.0.1
  - Updated to redcarpet 3.3.2
  - Only run `web-console` in development mode
  - Added differ 0.1.2 gem to better show sheet transactions

### Bug Fixes
- Project list gradients now display properly in Internet Explorer 8
- Fixed color of text in dropdowns in night mode
- Fixed positing of input text in dropdowns
- Various display adjustments for Internet Explorer 8
- Fixed date input consistency across browsers when using two-digit years
- Fixed time input consistency across browsers and compatibility with Internet Explorer 8
- Fixed a bug preventing dates from being entered and edited on subject events
- Fixed ordering of variables across CSVs and generated export scripts
- Deleting a sheet now correctly redirects back to the subject page
- Fixed a bug that prevented large integers from being saved to sheets
- Fixed display of sparkline tooltips
- Fixed an issue where "send emails" was listed twice on the sign up form
- Fixed a bug that removed options when the option name was cleared

## 0.28.1  (June 10, 2015)

### Enhancement
- **Gem Changes**
  - Updated to figaro 1.1.1

### Bug Fix
- Fixed an issue rendering the project menu

## 0.28.0 (June 10, 2015)

### Enhancements
- **General Changes**
  - Several pages have been redesigned to be more subject-centric instead of sheet-centric
  - Streamlined login system by removing alternate logins
  - Removed approval process for new user registration
  - Updated the menu bar and sign up and registration pages
  - Added the new Try Slice logo, along with an animated Try Slice loading animation
  - Added new contact page and new footer
- **Project Changes**
  - Projects can now have a slug specified to support a nicer URL structure
  - The Projects Overview page has been updated to better show projects for users with numerous projects
    - Projects can now be reordered and favorited on the projects overview page
    - Projects can now be sent to the archives from the projects overview page
  - The Project Show page for individual projects will allow users to quickly create subjects and launch events for the selected subject
  - Project show page now has a new left hand navigation
- **Schedule and Event Changes**
  - Schedules have been removed and have been replaced by events
  - Events can be ordered by their position relative to each other
  - Designs can now be directly associated to events
  - Subjects page now doubles as an event completion report
  - Event page provides a table of designs by entered, unentered, and unassigned with links to the filtered subjects
  - Sheets can now be added directly to existing subject events
    - A sheet for a specific design can be added multiple times to the same event
    - In the case were a design exists multiple times, the subject event only counts one of the sheets for event completion
    - The subject event lists designs that have duplicate sheets
    - The subject event also displays designs for filled out sheets that are explicitly listed on the project event
- **Subject Changes**
  - Subject index can now be filtered by event and also by designs on and not on specific events
  - Subjects created from public surveys no longer appear to be created by the project owner
  - New subjects can now be quickly created by searching for them from the main project page
    - Users can then choose the new subject's site on the followup screen
    - Entering an existing subject code redirects to the subject's main page
  - Subject pages now prompt users to select the appropriate study-level event for which they want to enter a subject's sheet
  - Subject page now organizes subject sheets and events in a persistent left hand navigation bar
  - Events assigned to subjects can now be removed
    - Removing an event will not remove sheets that have been added to that event
  - The new subject page now contains a link to subject timeline and settings
    - The subject timeline lists subject events, sheets, and comments
  - The new subject page contains a list of uploaded files across all of the subject's sheets
  - Subject show page now shows all comments for a subject in a new tab
- **Sheet Changes**
  - `last_edited_at` sheet attribute no longer shows detailed comparison for changes in sheet transactions
  - Slight performance improvements in loading large sheets
  - Removed 'Clear' buttons from time and date variables on grids to decrease the overall widths of large grids
  - Sheets can now be transferred between subject in case the subject was entered incorrectly
  - Sheets can now be dragged to a subject event when looking at sheets on a specific subject's page
  - Grids now have a visible indication that they can be reordered
  - Improved the display of grids that wrap off of the page
- **Survey Changes**
  - A second notification email is no longer sent when users edit and resubmit a public survey
- **Export Changes**
  - Improved the speed at which sheet exports are generated
- **Design Changes**
  - Removed underused and confusing cross sheet variables from designs
- **Email Changes**
  - Removed sign up notification emails for admins
  - Removed account approved notification emails
- **Gem Changes**
  - Updated to rails 4.2.1
  - Updated to pg 0.18.2
  - Updated to contour 3.0.0.rc
  - Updated to kaminari 0.16.3
  - Updated to naturalsort 1.2.0
  - Removed dependency on rails-observers
  - Use Haml for new views
  - Updated to jquery-rails 4.0.3
  - Use Figaro to centralize application configuration
  - Removed dependency on ruby-ntlm gem
  - Updated to chunky_png 1.3.4
  - Updated to redcarpet 3.2.3
  - Updated to rubyzip 1.1.7
  - Updated to simplecov 0.10.0
  - Updated to redcarpet 3.3.1
- Use of Ruby 2.2.2 is now recommended

### Bug Fix
- Site editors can now properly add rows to grids
- Reduced the amount of information logged to the log file
- Fixed an issue loading multiple pages on the project summary report
- Adding sections to a design no longer prefill the branching logic as "undefined"
- Numeric and integer values will no longer append zeros after the decimal place when updated on a sheet

### Refactoring
- Simplified how image assets are referenced from SCSS file
- Reduced number of database queries for events and designs indexes
- Improved the load time of the sheets index
- Removed deprecated sheet audit system
- Removed status field from the user model

## 0.27.7 (December 30, 2014)

### Enhancements
- Use of Ruby 2.2.0 is now recommended
- Removed NProgress in favor of Turbolinks progress bar
- **Sheet Changes**
  - Numeric values with valid domain codes outside of the numeric hard min/max range, are now accepted as valid responses
- **Gem Changes**
  - Updated to rails 4.2.0
  - Updated to contour 2.6.0.rc

### Bug Fix
- Fixed a bug that incorrectly output SAS labels for domains

## 0.27.6 (December 12, 2014)

### Bug Fix
- Fixed a bug that prevented new rows from being added to a grid

## 0.27.5 (December 12, 2014)

### Enhancements
- **Gem Changes**
  - Updated to rails 4.2.0.rc2
  - Updated to simplecov 0.9.1

## 0.27.4 (November 25, 2014)

### Bug Fix
- Fixed a bug that prevented multiple rows in a grid from being exported

## 0.27.3 (November 24, 2014)

### Enhancement
- Further memory usage improvements for sheet exports

## 0.27.2 (November 24, 2014)

### Enhancement
- Updated Google Omniauth to no longer write to disk
- Improved memory usage for large sheet exports

## 0.27.1 (November 21, 2014)

### Bug Fix
- Fixed a bug that prevented dates from being saved in Internet Explorer and Firefox

## 0.27.0 (November 21, 2014)

### Enhancements
- **General Changes**
  - Updated the top navigation menu
  - Favoriting a project now puts it in the menu right away
    - A maximum of three projects are displayed in the navigation bar
  - The Night theme has had several visual improvements
  - Added a new experimental Winter theme to user settings
- **Project Changes**
  - Projects can now enable Double Data Entry for sheets
    - A sheet Verification Report now exists to compare responses from the original entry to the double data entry sheets
  - A Data Export link has been added to the projects dashboard
- **Design Changes**
  - Variables can now be set as `Required`, `Recommended`, and `Not Required` on designs
    - `Required` variables need to have a value set for the form to be submitted
    - `Recommended` variables encourage the user to fill them in when saving the survey if they have no value set
    - `Not Required` variables work as variables have in the past
  - The design editor has been redesigned to reduce clutter while generating new forms
- **Variable Changes**
  - Added a new "signature" variable type is available on designs
    - This variable allows users to add signature fields to designs
  - Variable calculations can now exceed the 255 character limit
  - Date variables are now available in three formats, `MM/DD/YYYY`, `YYYY-MM-DD`, and `DD/MM/YYYY`
  - Deleted variables that still exist on designs can be restored in certain circumstances
  - Time inputs have been adjusted to better fit the expected input width
  - Numeric fields with ranges no longer accept numbers with non-numeric components, ex: '100 years'
- **Sheet Changes**
  - Side navigation bar was removed to make better use of available space
  - Missing codes are now colored red for better visibility when selected
  - Variable display names now display consistently in both above and inline formats
  - Choosing a design is now the last step when creating a new sheet
  - Calculations on sheets are now hidden by default
  - File upload button now looks consistent across browsers
- **Gem Changes**
  - Updated to rails 4.2.0.beta4
  - Updated to contour 2.6.0.beta8
  - Updated to redcarpet 3.2.0
- Use of Ruby 2.1.5 is now recommended

### Bug Fixes
- Fixed an issue reordering sections on designs that included subsections
- Fixed an issue selecting radio buttons in Firefox
- Fixed an issue displaying recently entered sheets badge on projects dashboard in Firefox
- Fixed an issue displaying the chozen dropdown sprite
- Fixed a styling issue with autocomplete fields preceded by a prepend string
- Default site is now pre-selected for editors on a single site for projects that have more than one site
- Sections no longer lose the associated image when updated, and now work correctly with branching logic
- Fixed an issue that prevented project-specific emails from being sent

### Refactoring
- Removed dependency on rake and systemu for background tasks

## 0.26.3 (October 23, 2014)

### Enhancements
- **Gem Changes**
  - Updated to rails 4.2.0.beta2
  - Updated to contour 2.6.0.beta7
- Use of Ruby 2.1.3 is now recommended

### Bug Fix
- Removed a trailing comment line in the SAS export script (fix by @mcailler)

## 0.26.2 (September 15, 2014)

### Bug Fix
- Fixed an issue where emails were creating incorrect URLs (reported by @mcailler)

## 0.26.1 (September 8, 2014)

### Configuration
- Updated server configuration to maintain the database schema

## 0.26.0 (September 6, 2014)

### Enhancements
- **Auditing Changes**
  - Full scale replacement of sheet auditing module with transaction-based module
- **Design Changes**
  - Mass importing sheets now notifies the user doing the import instead of the user who created the design
  - Existing variables can now be added to a design without needing to select the type of question first
  - Public surveys have a setting to allow users to select a site while filling out a survey
- **Domain Changes**
  - Option values no longer need to be entered and will fall back to the default placeholder numbers if left blank
- **Export Changes**
  - The SAS export now applies labels and domains to checkbox subvariables
  - An R export option has been added to the statistical export section (thanks to @mcailler for the template R script)
- **Adminstrative Changes**
  - System admins can now see if a user has been invited to a site to aid in the user activation process
- **Setting Changes**
  - An experimental Night Mode theme has been added to user settings
    - Users can opt in to this mode by visiting their settings and selecting the "Night (Experimental)" theme
- **Gem Changes**
  - Updated to rails 4.2.0.beta1
  - Updated to contour 2.6.0.beta6

### Bug Fixes
- Fixed a display issue on the dashboard caused by projects with long names
- Fixed a graphical issue when gravatar failed to load in the navigation bar

## 0.25.1 (August 1, 2014)

### Bug Fix
- Fixed a bug that caused domain values to sometimes be displayed out of order when missing codes were set
- Fixed an issue when using cursor navigation to select an autocomplete suggestion that would cause it to navigate to the next field instead of selecting the appropriate autocomplete suggestion

## 0.25.0 (July 31, 2014)

### Enhancements
- **Design Changes**
  - Added links to all variables that exist on the design
  - Design reports now allow checkbox variables to be selected
  - Design overview now links and filters checkbox variable options
  - When creating grid variables, questions can be specified to prepolutate the grid to avoid needing to all the variables individually
- **Sheet Changes**
  - Sheet completion percentage is now based on visible responses only
  - CSV exports now additionally split checkbox variables into individual columns by domain options
  - SAS export script now auto-detects location of CSV files
  - Arrow keys can now be used to navigate across string, numeric, and text input fields while creating or editing a sheet
  - The current grid row is highlighted when adding values to grids on a sheet
  - Projects with one site will now default to that site when creating new sheets
  - Entering time variables no longer auto skips forward, instead the arrow keys can be used to navigate forward and backwards
- **Variable Changes**
  - A create/update and continue button lets users add multiple variables more quickly when creating many variables at once
- **Domain Changes**
  - A create/update and continue button lets users add multiple domains more quickly when creating many domains at once
  - Option missing codes have been readded when creating and updating domains
- **Gem Changes**
  - Updated to rails 4.1.4
  - Updated to kaminari 0.16.1
  - Updated to simplecov 0.9.0

### Bug Fixes
- Fixed a bug that prevented date hard and soft, minimums and maximums from updating on the design editor
- Fixed a bug that prevented a public survey from being submitted after a logged in user's session timed out
- Fixed a bug that prevented a page navigation confirmation box from appearing when editing a sheet and attempting to leave the page without saving
- Hitting `Enter` when searching on the sheets index no longer generates an export, and now correctly submits the selected search filters

## 0.24.13 (June 16, 2014)

### Bug Fix
- Fixed a bug that didn't add the new `Sheet ID` column to the generated SAS script

## 0.24.12 (June 16, 2014)

### Enhancements
- **Sheet Changes**
  - Added `Sheet ID` to sheet and grid exports to allow for easier merging across records

## 0.24.11 (May 30, 2014)

### Enhancements
- **Sheet Changes**
  - Sheet PDFs now list the first time the sheet was locked, as well as the latest amendment date

## 0.24.10 (May 28, 2014)

### Enhancements
- **Sheet Changes**
  - Removed Amendments from sheet PDFs
  - PDFs now include the word `Page` in front of the `1 of 5` to display as `Page 1 of 5`
  - PDFs now include the word `Printed on` in front of the date on the bottom center of the PDF
- Use of Ruby 2.1.2 is now recommended

## 0.24.9 (May 9, 2014)

### Enhancements
- **Gem Changes**
  - Updated to contour 2.5.0

### Bug Fix
- Fixed a bug that prevented audits from printing correctly on sheet PDFs

## 0.24.8 (May 7, 2014)

### Bug Fix
- Fixed a bug that prevented cross sheet variables from displaying properly

## 0.24.7 (May 7, 2014)

### Bug Fix
- Fixed a bug that prevented newly created sheets from being locked

## 0.24.6 (May 7, 2014)

### Bug Fix
- Fixed an issue preventing site editors from editing sheets

## 0.24.5 (May 7, 2014)

### Enhancements
- **Gem Changes**
  - Updated to rails 4.1.1

### Bug Fix
- Fixed an issue displaying checkbox results on sheets

## 0.24.4 (May 6, 2014)

### Enhancements
- **Project Changes**
  - Added project setting to hide answer values on PDFs
- **Design Changes**
  - Printed PDFs have better styling for sections and subsections
  - Moved project name to top center of PDF
- **Sheet Changes**
  - Printed PDFs have better styling for sections and subsections
  - Moved project name to top center of PDF
  - Split subject name and subject acrostic across two lines on top left of PDF
  - Sheet PDFs now display amendments made after a sheet is initially locked
- **Gem Changes**
  - Updated to minitest-reporters 1.0.4

### Bug Fix
- Fixed a bug that kept sections from being changed to subsections on the design editor
- Clicking on the total links on design overviews now correctly filters to the corresponding sheets on the sheets index
- Uploaded files now count correctly towards the sheet coverage calculation

### Refactoring
- Removed unused `remove_file` JS partials
- Removed duplicatation in `SheetVariable` model, and moved heavy lifting to the `Valuable` concern as initially intended
- Removed redundant `response_file` and `response_file_url` methods from `Design` model
- Removed `response`, `position`, and `sheet_variable` local variables from `sheet_variable` show partials
- Moved common parse date and parse time code to a library module `DateAndTimeParser`
- Refactored variable types into separate classes

### Testing
- Added tests for importing a design from JSON format
- Added tests for setting design survey slug
- Added tests for updating domains and variables when editing the design

## 0.24.3 (April 18, 2014)

### Bug Fix
- Fixed inability to download project exports
- Fixed inability to download project documents

## 0.24.2 (April 18, 2014)

### Enhancements
- Uploaded file storage locations updated

### Refactoring
- Slight refactoring of the design sections and options reorder method
- Slight refactoring of `SheetVariable` model
- Removed unused `remove_file` action from `SheetsController` and `ProjectsController`
- Removed unused sheet partial

## 0.24.1 (April 17, 2014)

### Enhancements
- **General Changes**
  - Typing `w` will toggle between full width mode and fixed width mode
  - Clicking the `Create Domain` on the variable new/edit page now opens a blank browser tab
  - Updated "# sheets created" link color in daily digest email to match styling of other links

## 0.24.0 (April 14, 2014)

### Enhancements
- **Project Changes**
  - Projects have a setting that when enabled allows the locking and unlocking of sheets
    - Project editors can lock and unlock sheets.
    - Locking a sheet is accomplished by checking the lock checkbox at bottom of sheet.
    - Sheets can not be edited until they are unlocked by a project editor
    - Sheet PDFs show who locked the sheet and when.
    - *Caveat:* Disabling this setting while having sheets that are locked, will remove the ability to unlock or edit these sheets, until the project level setting is reenabled
- **Design Changes**
  - Designs can now be copied from one project to another
    - Download the JSON version of the design (or designs) from origin project
    - Use the Import Design from JSON to import the designs
    - Import won't overright existing designs/variables/domains if  design/variable/domain of the same name already exists
  - Design sections can now have an image uploaded to be included underneath the section
    - Requires HTML5 compatible browsers
    - Section images are included on sheet and design PDFs
  - Designs can now include references to variables collected on other sheets
    - The referenced variables are shown when creating, updating, or displaying a sheet
- **Sheet Changes**
  - Small file image uploads on sheets are no longer stretched to fill the width of the page
- **General Changes**
  - Updated email styling template
- **Gem Changes**
  - Updated to rails 4.1.0
  - Updated to contour 2.5.0.beta1
  - Removed turn, and replaced with minitest and minitest-reporters
  - Removed Windows-specific gems

### Bug Fix
- Domain display name field is now available when creating a domain
- Updating branching logic on designs now strips out extra white space
- Date variable fields no longer obscure dropdowns
- Domain values are no longer shown when editing a submitted survey
- Sheets index now properly populates only users who have created a sheet in the creator list
- Designs index now properly populates only users who have created a design in the creator list
- Set a fixed width for `Existing Variable` and `Existing Domain` dropdowns

## 0.23.6 (March 20, 2014)

### Enhancements
- **Gem Changes**
  - Updated to rails 4.0.4
  - Updated to redcarpet 3.1.1
  - Updated to carrierwave 0.10.0

### Bug Fix
- Fixed a bug that caused the top vertical radio or checkbox option to hide the related question in the mobile view

## 0.23.5 (March 13, 2014)

### Enhancements
- **Gem Changes**
  - Updated to contour 2.4.0

### Bug Fix
- Fixed a bug that slowed down the design editor due to multiplying hidden calendar popups

## 0.23.4 (February 27, 2014)

### Enhancements
- Use of Ruby 2.1.1 is now recommended
- **Gem Changes**
  - Updated to rails 4.0.3

## 0.23.3 (February 18, 2014)

### Enhancements
- Project level setting that allows project owners to disable collecting email by default on public surveys

## 0.23.2 (February 17, 2014)

### Enhancements
- **General Changes**
  - Added [NProgress](http://ricostacruz.com/nprogress) to visualize turbolinks transitions
- **Design Changes**
  - Printed designs now include section descriptions and page breaks before each section
- **Sheet Changes**
  - Grid variables with a fixed number of rows no longer show the actions column
- **Gem Changes**
  - Updated to contour 2.4.0.beta3

### Bug Fix
- Leading and trailing white spaces are now removed from subject codes when the record is saved
- Copying designs no longer throws an error due to non-unique slug validation
- Adding grid rows now correctly appends values to dropdowns and radio variables when editing a sheet as an editor
- Subject creation and index pages now correctly use the project setting for the subject code name
- Typing `Enter` when adding a new variable to a design now correctly submits the form to create the variable
- Project page table links no longer redirect twice when clicking `Edit` or `PDF` links

## 0.23.1 (February 3, 2014)

### Bug Fix
- Dropdowns, checkboxes, and radio buttons in grids now correctly show values for non-public designs (reported by ekaplan)
- Sheet responses for full-width questions no longer extend into margin

## 0.23.0 (February 3, 2014)

### Enhancements
- Faster page navigation through the use of turbolinks
- **Project Changes**
  - Project email setting added that disables all daily digest and sheet comment emails for everyone on that project
- **Design Changes**
  - When editing a design, checkbox and radio options in scale format now display how they display on sheets
    - Previously in edit mode, the variables weren't displaying the value along with the name, `Option A` instead of `1: Option A`
  - Reorder mode has been cleaned up and moved to be as an option under Edit Design
  - Variable names can now be edited in addition to the display name when adding variables to a design
- **Sheet Changes**
  - Clicking tilda (~) now clears radio and checkbox selections along with back tick (`)
  - Public surveys now longer display the value of the answer
- **Gem Changes**
  - Updated to contour 2.3.0

### Bug Fix
- Subject codes are no longer case-sensitive

## 0.22.7 (January 10, 2014)

### Enhancements
- **Export Changes**
  - Exports zip files **no longer** include:
      - user's last name
      - spaces between date and time, replaced by underscore
      - time in 12-hour am/pm format, replaced by 24-hour format
  - SAS exports now correctly format time as `time8`

### Bug Fix
- Fixed a bug that prevented designs overview to render if they included a numeric variable with no responses (reported by ekaplan)

## 0.22.6 (January 7, 2014)

### Enhancements
- Use of Ruby 2.1.0 is now recommended
- **Gem Changes**
  - Updated to jbuilder 2.0
  - Updated to contour 2.2.1
  - Updated to systemu 2.6.0

## 0.22.5 (January 6, 2014)

### Enhancements
- **Gem Changes**
  - Updated to contour 2.2.0
  - Updated to pg 0.17.1

### Bug Fix
- Fixed a bug that prevented designs to be shown when a scale variable was included on a grid

## 0.22.4 (December 6, 2013)

### Bug Fix
- Hitting the `Enter` key when adding a new variable to a design now correctly creates the variable

## 0.22.3 (December 5, 2013)

### Enhancements
- Use of Ruby 2.0.0-p353 is now recommended

## 0.22.2 (December 4, 2013)

### Enhancements
- **Gem Changes**
  - Updated to rails 4.0.2
  - Updated to contour 2.2.0.rc2
  - Updated to kaminari 0.15.0
  - Updated to coffee-rails 4.0.1
  - Updated to sass-rails 4.0.1
  - Updated to simplecov 0.8.2

## 0.22.1 (November 25, 2013)

### Bug Fix
- Projects with one site now correctly show site selection when creating sheets and subjects

## 0.22.0 (November 25, 2013)

### Enhancements
- **Project Changes**
  - Project splash updated with cleaner panels between projects
  - Create Project button moved to menu bar if a user is only on one project, and has been removed from project page
  - Minor styling updated on Sharing and Report pages
  - Inviting emails for project editors have been adjusted to be more clear
  - Share page simplified and project editors/viewers and site editors/viewers can now be invited from the same page
  - Site editors can now:
    - Create and edit sheets for subjects on their site
    - Create and edit subjects on their site
- **Sheet Changes**
  - Removed project logo from sheet PDFs
- **Export Changes**
  - CSV and SAS exports now include the sheet's `schedule_name` and `event_name`
- **Design Changes**
  - Removed project logo from design PDFs
  - Design PDFs now have placeholders for subject code and entered date similar to sheet PDFs
  - Simplifications for the design editor
    - Reduced the size of the variable layout options bar
    - Shortcut Key `M` added to toggle between **Edit Mode** and **Preview Mode**
    - Removing variables or sections now prompts the user to make sure that is what is intended
    - Variable and domain creation button layout is more consistent
    - Variable on design editor now display conditional branching logic
  - Surveys can now specify a redirect_url where the user is directed after completing a survey
  - Domain creation simplified to be more in line with variable creation
    - Domain `display_name` field added which autogenerates the correctly formatted `name`
    - Values are not visible when initially creating an answer option, instead options are enumerated starting at one
- **Schedule Changes**
  - Schedules now display the count of subjects that have been assigned the schedule
    - Subjects who have not filled out a schedule can now be identified and filtered
    - Schedules show statistics by event and by design the amount of assigned schedules that have been entered and unentered
- **Subject Changes**
  - Simplified subject status so subjects can either be `valid` or `test`
- **Variable Changes**
  - Minor styling changes for better consistency of radio, checkbox, and scale variables
- **Report Changes**
  - Overview reports that identify mininums, medians, and maximums now provide a link to find the related sheets
- **JSON API Changes**
  - Subject entered and unentered schedules and associated sheets are now part of the JSON object that is returned
- **Gem Changes**
  - Updated to rails 4.0.1
  - Updated to rubyzip 1.1.0
- Removed support for Ruby 1.9.3

### Bug Fix
- Reports now handle large date ranges more robustly
- Unselecting a radio box by clicking on it now properly applies any conditional branching logic
- Hitting the `Enter` key when creating or updating a domain on the design editor now correctly saves the domain
- Changing a subject on a sheet that is part of a schedule now correctly clears the schedule and event

## 0.21.3 (October 28, 2013)

### Bug Fix
- Fixed a bug that prevented users from being added to a project by email

## 0.21.2 (October 15, 2013)

### Enhancements
- Removed autocomplete while filling out domain option values, names, and descriptions

### Bug Fixes
- Removed extraneous information on subject schedules introduced in 0.21.1

## 0.21.1 (October 15, 2013)

### Bug Fixes
- Fixed an issue that occurred when a scheduled event had no designs associated with it
- Dropdowns that aren't in a grid are now the correct full width
- Dropdowns in grids now have a minimum width specified

## 0.21.0 (October 14, 2013)

### Enhancements
- **Project Changes**
  - The project page has been reorganized to highlight different steps
    - **Collect**: Entering new data sheets, subjects, and sites
    - **Explore**: Download collected data for further review or analysis, and view reports
    - **Setup**: Create and edit forms/surveys, events, and schedules
    - **Share**: Add other users to project
    - **About**: Show news, contacts, documents, links and project description
    - **Activity**: Show recent project activity
  - Project ownership can now be transferred by the project owner to another user on the project
- **Schedules**
  - Schedules allow subjects to be assigned groups of designs by date
  - Schedules are comprised of multiple events
    - Each event is specified with a date offset
    - Each event can contain one or more designs
    - An event can only be included once per schedule
    - A design can only be included once per event
  - Creating a sheet from the subject schedule now redirects back to the subject page
- **Design Changes**
  - Editing autocomplete variables now display user submitted variables for reference
  - Adding sections and variables is now more apparent on design editor
- **General Changes**
  - Recent activity now displays a message if there hasn't been any recent activity
- **Gem Changes**
  - Updated to pg 0.17.0
  - Updated to contour 2.2.0.beta2

### Bug Fixes
- Inline checkboxes now wrap text correctly on smaller screens
- Fixed control size dropdown not activating when adding new variables to an existing grid variable

## 0.20.7 (September 3, 2013)

### Enhancements
- **Gem Changes**
  - Updated to contour 2.1.0.rc
  - Updated to mail_view 2.0.1
  - Updated to rubyzip 1.0.0

## 0.20.6 (August 26, 2013)

### Enhancements
- **Sheet Changes**
  - Simplified sheet header on sheet show page, and removed additional information at bottom
  - File attaching and removal now work exclusively through the sheet edit page

### Bug Fix
- PDFs for designs which include file variables now generate correctly
- Project names no longer need to be unique
- Associated users now works correctly on systems that don't set user status
- Selecting radio buttons and checkboxes via the keyboard now properly fires a change event to updated dependent calculated fields

## 0.20.5 (August 20, 2013)

### Enhancements
- **General Changes**
  - The interface now uses [Bootstrap 3](http://getbootstrap.com/)
- **Gem Changes**
  - Updated to contour 2.1.0.beta15

## 0.20.4 (August 19, 2013)

### Enhancements
- Added additional descriptions to clarify the meaning of Subject Code name, and Site name when creating and updating a project

### Bug Fix
- Fixed a bug that caused SAS exports to not match up variables correctly with the CSV

## 0.20.3 (August 15, 2013)

### Enhancements
- **General Changes**
  - The interface now uses [Bootstrap 3 RC2](http://getbootstrap.com/)
- **Gem Changes**
  - Updated to contour 2.1.0.beta13

## 0.20.2 (August 14, 2013)

### Enhancements
- Improved speed of CSV exports
- Updated styling to BS3 for the email settings page
- Increased margin on survey and projects splash page
- Site ranges now only display as a popup box on sheet entry
- Export READMEs now display the number of sheets exported

### Bug Fix
- Sheet date filters on the sheets index now filter sheets now work properly

## 0.20.1 (August 9, 2013)

### Bug Fix
- Fixed a bug that would cause project and recent activity to not display

## 0.20.0 (August 9, 2013)

### Enhancements
- **Project Changes**
  - Project emails field was removed since it is no longer used
  - Project settings page removed and consolidated with project show page
  - Project splash now displays a number of recent valid entries made for a project
  - Projects can be favorited which reorders them on the project splash page, and puts the first three as links into the menu bar
  - Exports are now nested under projects
  - Project activity added that shows recently created comments and sheets
- **General Changes**
  - Users can now view recent comments and sheet creations and updates across all their projects
  - The interface now uses [Bootstrap 3 RC1](http://getbootstrap.com/)
- **Gem Changes**
  - Updated to contour 2.1.0.beta10

## 0.19.0 (August 1, 2013)

### Enhancements
- Updated the login page
- **Sheet Changes**
  - Removed `Clear` button from radio and checkbox groups as it is no longer needed
  - Removed redundant tooltips for integer, numeric, string, and text variables
- **Design Changes**
  - Design editor submit buttons are now disabled when inserting and updating a new variable or domain
  - Surveys can now use slugs to create prettier survey links
    - `/survey/:slug`
  - Variable display names size increased to accommodate long questions on surveys
  - Creating variables with long display names in the design editor now default to the display name visibility: `Above - Indented`
  - Creating text variables now default to display name visibility: `Above - Full`
- **Domain Changes**
  - Removed color from domain options
- **Gem Changes**
  - Updated to contour 2.0.0
  - Updated to pg 0.16.0
  - Updated to redcarpet 3.0.0
  - Updated to carrierwave 0.9.0
  - Updated to rails-observers 0.1.2

### Bug Fix
- SAS export now properly escapes single quotes ``'`` in labels using two single quotes ``''``
- Numeric variables can now be entered without a leading zero without causing a validation error
- Fixed variable display name not showing up on the design editor when set as scale alignment without a domain
- Hitting `Enter` while editing design or variable names on the design editor now correctly updates the name
  - Additionally, hitting `Escape` will close the modal popup
- Fixed an issue creating domains without specifying option values
- Fixed an issue where entering time inputs in a grid would focus on the form submit button instead of the next input in the grid (by EmilyK)

## 0.18.4 (July 15, 2013)

### Enhancements
- **Sheet Changes**
  - Time inputs have been revised into 3 separate text boxes, and enhanced with functionality for quick input
  - Radio buttons and check boxes can be selected by keyboard strokes when in-focus, and can be cleared with `` ` ``
  - Clicking on a selected radio button un-selects the radio button
  - Editing and creating sheets now contain the project name at the top for consistency
- Use of Ruby 2.0.0-p247 is now recommended
- **Gem Changes**
  - Updated to rails 4.0.0

### Bug Fix
- Sheet comments that contain urls with numbers no longer escape the numbers to ASCII representation
- Design overview now properly displays radio and checkbox display names when they specified as scales
- Clicking enter on the design creator no longer pretends to save the design

## 0.18.3 (June 18, 2013)

### Enhancements
- **Design Changes**
  - Variables that have been deleted are kept visible on designs until a user explicitely removes the variable from the design
    - Allows design variable reordering to continue to work since the reordering relies on positioning of deleted variables that exist on the design
    - Maintains design integrity on accidental deletion of variables
  - Design editor now contains a highlight effect to show what area of the design was added or updated
  - Existing variables are now alphabetized on the "Select Existing Variables" list while editing a design
  - Existing domains are now alphabetized on the "Use Existing Domain" list while editing a variable domain on a design
  - Inserting a new section or variable now allows any part of the new variable/section box to be selected
  - Variable display name explanation and example added when creating and editing variables on a design
- **Domain Changes**
  - The list of variables is now alphabetized on the domain show page

## 0.18.2 (June 17, 2013)

### Enhancements
- Minor GUI fix to only show scale headers for the if the variable is the first scale of that type on the sheet

## 0.18.1 (June 17, 2013)

### Bug Fix
- Radio and checkbox variables that are displayed as scales now display the variable display name correctly on filled out sheets

## 0.18.0 (June 14, 2013)

### Enhancements
- Use of Ruby 2.0.0-p195 is now recommended
- **Design Changes**
  - Creating and editing designs has been simplified
    - Variables and domains can now be created while building the design
    - Changing display options for variables and domains is now reflected on the new design builder
    - Designs are saved automatically as changes are made
    - Design editing can be toggled between `Edit Mode` and `Preview Mode`
  - Sections can now be marked as subsections, this change is aimed at removing the header field from variables
- **Variable Changes**
  - Scale is now considered an `alignment` option for `checkbox` and `radio` variables instead of being its own variable type
  - Grid variables with multiple rows can now be reordered by dragging the grid rows
  - Variable headers have been removed in favor of design subsections
  - Calculated numbers are only stored if they represent a real and finite number, otherwise they stay blank
- **Domain Changes**
  - Searches on the domains index now search through domain options as well
- **Email Changes**
  - Project name is now included in the email when a user makes a comment on a sheet
- **Gem Changes**
  - Updated to rails 4.0.0.rc2
  - Updated to redcarpet 2.3.0

### Bug Fix
- Starting a variable header or section with a number followed by a period no longer creates a ordered list that starts at one

## 0.17.0 (May 13, 2013)

### Enhancements
- **Survey Changes**
  - Users can optionally enter their email
  - Completed surveys provide a link in case the user wishes to alter the survey responses
- **Project Link Changes**
  - Project Links must start with either `http`, `https`, `ftp`, or `mailto`
- **Design Changes**
  - Plus/Minus symbols added to StdDevs on Design Overviews
  - The Design Overview now links back directly to the design and to the design's project
  - Improved response time for very large design overviews
- **Gem Changes**
  - Updated to rails 4.0.0.rc1
  - Updated to contour 2.0.0.beta.8

### Refactoring
- Removed SheetEmails in favor of Sheet Comments
- Removed Batch Sheets in favor of Design Surveys
- Removed Site Emails in favor of Site Members who are notified via sheet comments

### Bug Fix
- Fixed Recent Entries with long subject codes not being properly hidden in Firefox

## 0.16.1 (May 8, 2013)

### Bug Fix
- Fixed a bug that occurred when a user viewed a sheet with another user's comment

## 0.16.0 (May 6, 2013)

### Enhancements
- **Export Changes**
  - Exports now include separate folders with a `README` for each export option
  - Data Dictionary exports are now split into three `.csv` files
- **Design Changes**
  - Sheets for specific designs can now be reimported
  - Shareable survey links can now be generated by setting a design to **publicly available**
  - Prototype for design overview of all sheet data collected
- **Report Changes**
  - Report strata for dropdown, radio, and string variables are now only displayed if the underlying sheet scope contains the response
- **Variable Changes**
  - Modifying a domain for a variable now shows the domains labels in addition to the values
- **Gem Changes**
  - Updated to pg 0.15.1
  - Updated to jbuilder 1.4.0
  - Updated to coffee-rails 4.0.0
  - Updated to sass-rails 4.0.0.rc1

### Bug Fix
- SAS exports that contain domains with letters no longer cause errors when loading into SAS
- SAS exports that contain checkboxes no longer attempt to format the imported checkbox values when loading into SAS

### Refactoring
- Removed design exports from designs index
- Removed code for original design and project reports
- Removed multi-page designs
- Fixed instances of lambda scopes mixed with deprecated order options
- Domain max length reduced to 30 to account for SAS export limits

## 0.15.4 (April 16, 2013)

### Bug Fix
- Fixed a bug that prevented setting `additional_text` for survey request emails

## 0.15.3 (April 16, 2013)

### Enhancements
- Survey email bodies can now be partially modified when sending out a batch of sheets by email

## 0.15.2 (April 11, 2013)

### Refactoring
- Removed Microsoft Excel (XLS) export option

### Bug Fix
- Fixed a bug in Bootstrap styling that caused validation errors to force input fields downwards
- Fixed a bug that caused the `next` and `prev` buttons on reports to not use the correct report filters
- Fixed a bug that would overwrite the original creator of a comment, contact, document, domain, link, and post when updated by another user

## 0.15.1 (April 3, 2013)

### Bug Fix
- Fixed an issue that would cause a daily digest not to be sent out if only comments were created the day before and no sheets were entered
- Report PDFs no longer display a plus/minus followed by a dash for blank report standard deviations, and instead just display a dash

## 0.15.0 (April 3, 2013)

### Enhancements
- **Project Changes**
  - Project users now simplified to editors and viewers, formerly librarians and members
    - Viewers (formerly members) can no longer create or edit sheets
  - Documents and Links can now have their categories renamed *en masse*
- **Export Changes**
  - Sheet data can be exported in a SAS-friendly format
  - Sheet exports now display the percentage of the export progress that is complete
- **Variable Changes**
  - Domain names now have the same naming conventions as variable names
    - Maximum length of 31 characters
    - Must start with a letter, followed by letters, numbers, or underscores
- **Email Changes**
  - Daily digests are now emailed out to show what sheets have been recently entered
    - When more than 15 sheets are entered for a specific project, the daily digest displays the number of recently created sheets along with a link to view specifics
  - Project news posts are now emailed to project editors, viewers, and site members when they are created
    - Archived news posts are not sent out via email
- **Sheet Changes**
  - Project Editors, Viewers, and Site Members can now comment on sheets
  - Sheet coverage added to give an approximate of how much of the sheet has been filled out
  - Selected checkbox and radio responses are now highlighted
- **Report Changes**
  - `text` and `time` variables can now be reported on to discover whether the variable has been collected or has been left blank

### Bug Fix
- `Include missing` is now always displayed on the design report
- Saving numeric and integers with domains no longer forces the domain to cover all the captured values
- Design imports now correctly ignore columns of data with blank headers
- Design imports now correctly import already existing variables along with newly created variables

## 0.14.4 (March 20, 2013)

### Enhancements
- **Gem Changes**
  - Updated to Contour 2.0.0.beta.4

## 0.14.3 (March 19, 2013)

### Enhancements
- Use of Ruby 2.0.0-p0 is now recommended

### Bug Fix
- Errors are no longer raised in production if emails fail to be sent

## 0.14.2 (March 11, 2013)

### Bug Fix
- Updated to Contour 2.0.0.beta.3 to address LDAP authentication page showing up as "text/plain" instead of "text/html"

## 0.14.1 (March 5, 2013)

### Bug Fix
- Fixed a missing `enctype="multipart/form-data"` that was causing file uploads to fail when creating/updating sheets

## 0.14.0 (March 5, 2013)

### Enhancements
- **Design Changes**
  - Designs along with data can now be imported from a CSV
    - CSV must contain ONE column header that is called `Subject`
    - CSV may also contain the subject's `Acrostic`
    - Design imports run as background tasks that notify the user by email when the design import is finished
    - Design import progress can be seen on the design page
    - Subjects are created in the following manner on an import
      - If the subject exists, use existing subject
      - Else check if the subject code matches an existing site and create the subject in that site with an appropriate status
      - Else create the subject using the default site and status specified on the design import
- **Domain Changes**
  - Domains index now displays the number of variables associated with the domain
- **Export Changes**
  - Exports can now be filtered by project
- **Project Changes**
  - Saving a project now automatically creates a new site if the project does not have any sites yet
  - Designs are now ordered alphabetically instead of most commonly used on the project page
  - Users with only one project now have a "Create Project" link on their main project page to allow them to create a second project
- **Sheet Changes**
  - Long tables on PDFs now split across pages
  - When adding a new sheet from the subject report, the subject code, acrostic, and site is prefilled and focus is set on the first question on the form
- **Variable Changes**
  - Variable Append and Prepend now display on sheet and design PDFs
  - Domains now show up on the variables index for all variable types
  - Only `dropdown`, `checkbox`, `radio`, `integer`, `numeric`, `scale` variables will save the selected domain, other variable types will clear any selected domain
- **Gem Changes**
  - Updated to Rails 4.0.0.beta1
  - Updated to Contour 2.0.0.beta.1
  - Updated to Spreadsheet 0.8.2

### Bug Fix
- Domain search now filters results when a search term is entered

### Refactoring
- Major rewrite/simplification of existing controllers

## 0.13.1 (February 15, 2013)

### Bug Fix
- Project summary report now generates reports based on sheet creation date

## 0.13.0 (February 14, 2013)

### Security Fix
- Updated Rails to 3.2.12

### Enhancements
- **Sheet Changes**
  - Removed `study_date` as a required field from sheets
    - All existing sheets have had existing study dates created as a new variable that is attached at the top of the design
- **Variable Changes**
  - Domains are now the sole method for adding options to variables
    - Domains allow variables (dropdown, radio, scale, etc) to share common choices
  - Variable domains can now only be changed if the newly selected domain options contain values for each previously captured response
  - Variable index and show page now display the number, and the designs they are on including designs on which they are included through a grid variable
- **Export Changes**
  - Uploaded file exports are now always zipped even if only one file has been uploaded
- **Report Changes**
  - Experimental reporter now formats calculated variables based on the variables' format
- **General Changes**
  - Updated to Contour 1.2.0.pre8
    - Use `bootstrap-datepicker` and `bootstrap-timepicker` provided by Contour
  - Removed references to deprecated `<center>` HTML tag
  - ActionMailer can now also be configured to send email through NTLM
    - `ActionMailer::Base.smtp_settings` now requires an `:email` field
  - Updated to jQuery Sparkline v2.1.1 for jQuery 1.9.1 support

### Bug Fix
- Hitting `p` no longer triggers switching to the global search when focused on a link or a drop down menu
- Fixed a bug that would disable navigation to the project page from the projects splash page on touch devices
- Variables are now grouped correctly in the row and column dropdowns on the design report
- Test subjects no longer have their status changed to valid if a sheet is entered for them and their subject code is in the site's valid range

## 0.12.3 (February 1, 2013)

### Enhancements
- Shortcut to search box simplified to `p`
- Subject Report `+` buttons no longer show up for Site Users
- Subject Report sheet counts now link directly to the sheet if the subject only has one sheet for the design

### Bug Fix
- Fixed a bug that kept users from registering using an alternate login
- Fixed an incorrect site invitation url from being generated when inviting a user on the site show page
- Fixed `last_entry` and `first_entry` SQL expression that caused some bugs

## 0.12.2 (January 31, 2013)

### Enhancements
- Designs on the subject page are now in the same order as they appear on the subject report
- `Ctrl|Command + Shift + P` will set focus on the search box (changed to just `p` in 0.12.3)

### Bug Fix
- Fixed a bug that occured when nil was passed as a search parameter to the Searchable Concern
- Fixed a bug that was resetting time fields when updating a sheet

## 0.12.1 (January 30, 2013)

### Enhancements
- Updated the variable date picker to use bootstrap-datepicker
- Updated the variable time picker to use bootstrap-timepicker
- Today and current time buttons are now ignored when tabbing
- String variables on reports are now sorted alphabetically
- Projects now sorted alphabetically on the projects splash page
- Export configuration is now saved with the export
- Variables and domains now include 5 options by default, and add 3 additional options when more options are requested
- Scale variables with headers now redisplay the option choice table header row
- Experimental reports now correctly display zeros instead of dashes when the zero represents meaningful data, instead of just an empty set
  - Ex: A variable that scores an integer value from 0 to 10 will now show the zero as a minimum value if captured on sheets
- Subject files collected across sheets are now available on the subject page
- Subject page now links back to the subject report
- Subject report is now paginated and has links to create new sheets for subjects with already existing sheets
- Boxplots on reports now use the same minimum and maximum values to scale correctly with each other
- Navigation quick search added that searches across subjects, projects, designs, and variables
- Subject count added to project page

### Bug Fix
- Fixed the affix navigation on sheet show pages
- Fixed a bug that caused last_entry and first_entry to fail to return a result if the latest (or oldest) sheet was deleted

## 0.12.0 (January 25, 2013)

### Breaking Change
- Database default updated to use PostgreSQL
  - Instructions [MIGRATING_TO_POSTGRESQL](https://github.com/sleepepi/slice/blob/0-38-stable/MIGRATING_TO_POSTGRESQL.md)

### Enhancements
- Subject status is now viewable on sheets
- Added Subject status to Sheet XLS exports
- Design Report columns can now also include integer and numeric variables
- Links can now be added to project and can be used to link to custom project reports
- Project settings have been moved to a separate page accessible from the project show page
- Data exports have been improved
  - Data in XLS format
  - Data in CSV Raw or Labeled format
  - Data in single PDF format
  - Uploaded data files in a ZIP format
  - Data dictionary in XLS format
  - Two or more of the above in one combined ZIP file
- Data dictionaries now additionally include variables included via grid variables
- Subject index now contains filters for valid, pending, and test statuses
- Project and Site invites can now be resent
- New experimental Report Builder added for more flexible reports
- Acceptable Use Policy added

### Bug Fix
- Toggleable filter buttons on the variable index now work correctly
- Sheet totals now properly update on the Subject Report
- Deleted subject's sheets are no longer visible on the project dashboard
- Sheet exports no longer include variables that were removed from a design
- Domains are no longer duplicated in data dictionaries when referenced by multiple variables
- Checkbox labels should no longer arbitrarily break on white space
- Grid rows "Remove" button no longer stays visible after mouseing out of the row, resulting in multiple rows showing a "Remove" button
- Fixed a bug rendering design PDFs that contained variables with choices that included commas in the choice description

### Testing
- Added mail_view gem for easier email templating

## 0.11.9 (January 14, 2013)

### Bug Fix
- Sheet PDF Collation link fixed and now correctly creates a combined PDF

## 0.11.8 (January 10, 2013)

### Bug Fix
- Removed a trailing bracket that was causing certain Design reports to fail creating a PDF

## 0.11.7 (January 10, 2013)

### Enhancements
- Variable row and column selections are now grouped by section on design reports
- Design reports are now rendered using LaTeX
- Users can now also be identified by their Gravatar

### Bug Fix
- HTML now displays correctly in tooltips and popovers
- Toggleable filter buttons fixed on sheet audits page
- Temporarily reverted to CarrierWave 0.7.1 until request.script_name bug is fixed in 0.8.+

## 0.11.6 (January 9, 2013)

### Enhancements
- Updated to Contour 1.1.2 and use Contour pagination theme
- Updated Thin Server to 1.5.0
- Updated CarrierWave to 0.8.0

### Refactoring
- Added app/models/concerns
  - Searchable: Allows models to be searched by name and description
  - Deletable: Allows models to be flagged as deleted and scoped by current
  - Latexable: Allows models to escape strings for LaTeX

### Bug Fix
- Tooltips no longer resize the report tables when hovered over
- Toggleable filter buttons on reports fixed
- Subject Acrostic is no longer cleared when changing the sheet date

## 0.11.5 (January 8, 2013)

### Security Fix
- Updated Rails to 3.2.11

## 0.11.4 (January 3, 2013)

### Security Fix
- Updated Rails to 3.2.10

### Bug Fix
- User activation emails are no longer sent out when a user's status is changed from pending to inactive

## 0.11.3 (December 11, 2012)

### Enhancements
- Subject Report can now be filtered by subject status
- Variables of type radio can now be included in calculated variables
- Prepend and Append fields now only show up for calculated, date, time, integer, numeric, and string variables

### Bug Fix
- Fixed some occurences of web page elements appearing underneath others
- PDFs of designs and sheets now correctly include the header and display name of scale variables if both are specified
- Autocomplete strings fields now initialize properly when additional rows are added to the grid
- Calculated variables with blank formats now correctly return the original number that was to be formatted
- Non-standard clicks (ctrl, alt, etc) now open links in a new tab/window
- Design variable can now only be sorted by clicking the variable name, this resolves a few issues when sorting were unintentionally triggered
- Time variables no longer show "Date soft maximum" as an option
- Time and date variables tooltips and popovers no longer cover the current button if it's enabled
- Fixed a Firefox issue where launching a modal would cause the background to jump back to the top of the page
- Entered data on a sheet that fails a subject or date validation no longer resets the new information on the form

## 0.11.2 (November 30, 2012)

### Enhancements
- Variables now only use the variable name as a place holder in the input field if the variable is in a grid

### Bug Fix
- Surveys now properly load branching logic

## 0.11.1 (November 30, 2012)

### Enhancements
- Variable headers can now include simple formatting, i.e. HTML tags `<a>`, `<b>`, `<i>`

### Bug Fix
- Date and time variables with Show Current Button selected no longer create a JavaScript error when a user clicks the button Today button while entering a sheet
- Color Selectors now display properly when editing variable options inside of a design
- Branching logic should now work properly on sheet show pages and sheet PDFs
- Updating a variable while editing a design no longer removes the branching logic for that variable
- Date pickers now display properly when editing date variables on inside a design

## 0.11.0 (November 21, 2012)

### Enhancements
- **Design Changes**
  - Email templates and subject lines can now reference the user sending the email (full name and email)
    - User's Full Name: `#(user)`
    - User's Email: `#(user).email`
  - Designs are now rendered using LaTeX for cleaner PDFs
  - Design (Data Dictionary) exports are now provided as a single XLS file
  - Variables can now be created inline while creating or editing a design
  - Branching logic for checkboxes has been simplified
- **Project Changes**
  - Subjects and Sites are now integrated more closely with their projects
- **Subject Changes**
  - Subjects can now be marked as valid, test, or pending
  - Filters for subject status have been added to reports
- **Sheet Changes**
  - Sheet PDF Collation now rendered using LaTeX for cleaner PDFs
  - Sheets now contain a last edited at variable that updates when a user edits the sheet (as opposed to solely viewing the sheet)
  - Printed sheets now hide variables that have been hidden by branching logic
  - Sheet audits now capture value changes triggered by editing a variable's option value in the design
  - Dates on sheets are now expanded to `mm/dd/yyyy` format if entered with a two-digit year
  - Live validations added to when entering a sheet with variables that have hard and soft ranges
  - "Create and Continue" and "Update and Continue" are now options to allow for entering multiple sheets one after another
  - Valid site ranges are now displayed during sheet entry if the site has a code_minimum, and code_maximum set
  - Sheets are now checked for unique valid design, subject, and study date combination as they are being entered to catch errors more quickly
  - Sheet receipt email TO and CC fields can now be specified as semi-colon-delimited in addition to comma-delimited
  - Sheet PDFs sent using sheet receipt emails are now named in the following manner
    - `[subject_code]_[study_date]_[design_name].pdf`
  - Entering a sheet now provides an autocomplete list of valid subject codes
  - Grids with `default_row_number` set will now show the maximum `default_row_number` when going back to edit a sheet that did not use all of the grid rows
- **Surveys and Questionnaires**
  - Questionnaires and surveys can be generated and sent to a list of emails for external users to complete
  - External users are not required to sign up and can access the survey by clicking the survey link in the generated email
  - Subjects can now also have emails to allow project owners to send out questionnaires or surveys
  - Survey creators receive an email when the external user submits the survey
  - Survey emails are part of the sheet's email history
- **Report Changes**
  - Removed internal site ids from design reports
- **Export Changes**
  - Improved display of grid variable tables in PDF for sheets and designs
  - XLS files are now more compatible, and take up less space than the original format used
  - Exports are now run in the background and users are notified when they can access the file
    - Users can access their past exports and view the status of current exports
    - An export groups multiple files together into a single zip file
    - Users are notified by email, and also see status icons in the menu of pending and newly ready data exports
- Ruby version updated to 1.9.3-p327

### Bug Fix
- Site name and units are now correctly escaped when rendering PDF sheet
- Display names are now hidden on PDFs if the associated variable's display name is marked as gone or invisibile
- Variable Edit/Remove buttons are no longer hidden by the variable preview when editing a design
- Text input fields with autocomplete no longer display the browser default autocomplete list which sometimes caused two lists to appear on top of each other
- Editing inline variables on a design now allow the variables options and grid variables to be dragged and sorted
- Double-clicking "Create" no longer attempts to submit a sheet twice as the button is now disabled as the form is being submitted
- Removed inline JavaScript on the project page that caused errors when hovering over the project logo without the entirety of the JavaScript being loaded
- Audits are now ordered correctly on the sheet audit page
- New sheets now properly load branching logic for preselected designs

## 0.10.3 (November 7, 2012)

### Enhancements
- Sheets are now rendered using LaTeX for cleaner PDFs
- Order of project settings for News, Documents, and Contacts options now consistent
- Checkbox responses are now audited more clearly

### Bug Fix
- Fixed some tooltips not appearing when mousing over

## 0.10.2 (November 5, 2012)

### Bug Fix
- Fixed a bug that caused dropdowns to not display correctly

## 0.10.1 (November 5, 2012)

### Enhancements
- Project logo added to printed designs
- Grid responses now display the associated variable's units

### Bug Fix
- Updating values for existing checkbox variables now correctly updates values in associated sheets
- Switching a checkbox variable with existing data to a radio variable no longer causes the data to be lost
  - NOTE: Data collected as a radio variable is maintained separately from data collected while the variable was a checkbox
- Truncated HTML code in news posts no longer changes layout on the project show page
- Fixed a bug that caused Firefox to be inable to create designs that contained file variables
- Project report and subject reports now display correctly on projects with no sites

## 0.10.0 (November 2, 2012)

### Enhancements
- **Project Changes**
  - Slice root directs a user to either a splash page with projects, or the project they are currently on
  - New Project dashboard added
  - Projects can now have Contacts, Documents, and News Posts
  - Designs, Variables, and Sheets are now integrated more closely with their projects
  - Subject vs Design project report added to view the overall status of subjects
  - Project owners are no longer automatically added to the CC field for sheet receipts
    - To add a project owner back to be CC'd, add the email to the project's email field
- **Sheet Changes**
  - Datepickers now retain focus after a date is selected
  - Design navigation now uses a left menu
  - Tabbing when filling in sheets simplified to tab more quickly between relevant data inputs
- **Variable Changes**
  - Grid variables can now specify the default number of rows that are displayed
  - Added calculation popups for calculated variables
  - Simplified conditional design variable logic by removing Show If and Values section
    - NOTE: Branching Logic should be used to conditionally hide variables
  - Add Scale variable type that allows options to be specified by domains
    - Domains are a set of options that can be associated to multiple variables
  - Calculated variables can now be added to grid variables and do the calculation based on the row they are in
- **General Changes**
  - Removed the global librarian role

### Bug Fix
- Changing the subject code after entering data on the sheet no longer clears the newly entered data
- Setting variable option colors now sets the correct option's color when adding new options in quick succession

## 0.9.1 (October 23, 2012)

### Enhancements
- The project has been renamed to Slice
- **Variable Changes**
  - Added easier variable type selection on variables index
  - Simplified interface for adding variables to a grid variable
  - Simplified adding options to variables
  - Grid variable show pages now include links back to the variables included in the grid
- **Sheet Changes**
  - Projects with one design now load that design by default when creating a new sheet
  - Variable popups now only show up if the variable description is set
    - NOTE: The range description has been moved to the tooltip

### Bug Fix
- Users can now correctly create designs on projects with no sites
- Project reports are no longer duplicated on the report index for projects with more than one site
- Copying a grid variable no longer causes an error
- Copying a variable now correctly says "Create Variable" instead of "Update Variable"

## 0.9.0 (October 19, 2012)

### Enhancements
- **Report Changes**
  - Tooltip added to report permalinks that specify to right click and copy link address
  - Design reports can be exported to PDF in portrait or landscape mode
  - Design reports can now be stratified by column by any date variable captured on the design
  - Design reports can specify to remove duplicate subject sheets and only used the first or last entered sheet for a the subject
  - Custom design reports can now be saved
  - Site members can now see Project and Design report that are filtered to their own site
- **Sheet Changes**
  - Sheet show page displays if a sheet receipt email has been sent
  - Sheets and grids can now be exported as an XLS file with a sheets tab and a grids tab
  - Sheet Receipt emails are tracked and email history is viewable from the sheet show page
  - Basic auditing for sheets now enabled
- **Variable Changes**
  - Date and time variables have the option to show a "Get Current Date/Time" button
  - Radio variables can now be cleared using a new clear button at the bottom of the radio button group
  - Variable display names can be set as:
    - Visible: Shows up to the left of the variable input
    - Invisible: Transparent but still takes up space to the left of the variable input
    - Gone: The variable input shifts to the left and takes up the space the display name would have taken up
      - NOTE: 'Gone' best when used with a Variable Header, and with Grid variables
  - Radio and Checkbox Variables can now be aligned vertically (default) or in a row horizontally
  - Radio and Checkbox choices are now displayed as they appear on the form in the variables list
- **General Changes**
  - Design Library and Variable Library menu items simplified to Designs and Variables
  - Printed PDFs have the PDF creation date in the center footer of the PDF

### Bug Fix
- The popup for design email template options displays correctly again
- Project logos and uploaded images now get properly embedded in Sheet receipt emails with attached PDF
- Variable options color choices are no longer reset to white when updating a variable
- Variable option color selection fixed when attempting to change the color of a newly added option

## 0.8.0 (October 5, 2012)

### Enhancements
- **Design Changes**
  - Email subject lines can be customized per design
  - Email templates and subject lines can now reference project and design name
    - Project Name: `#(project)`
    - Design Name: `#(design)`
  - Simple HTML formatting available for email templates
  - Study Date can be renamed to be design-specific (i.e. Visit Date, etc.)
- **Variable Changes**
  - Autocomplete values can be added to string variables
    - User submitted variables can be tracked when editing the variable and can also be added to the existing autocomplete list
  - Display name can be hidden for variables to avoid redundancy with header
  - Extra strings can be prepended and appended to variables
  - Options for radio, dropdown, and checkboxes can now have a color assigned to the option that is reflected on reports
  - Grid variables can specify the size of inputs in the grid for better formatting
- **Reporting Added**
  - Project Reports
    - Show the overall distribution of sheets by design and study date
    - Drill down added to project reports to access design reports
  - Design Reports
    - Row stratification by Site or any dropdown, radio, or string variable on the design
    - Column stratification by Study Date or any dropdown, radio, or string variable on the design
    - Design reports can be downloaded as CSV
  - Reports include permalinks that allow report configurations to be shared
- **Project Changes**
  - Subject Code can be renamed to be project-specific (i.e. Participant ID, etc.)
  - New users can now be added to projects by email
- **Sheet Changes**
  - Detailed sheet information now available as a popup on the show page

### Bug Fix
- Removing rows from a grid that contains a file upload variable, now correctly shifts existing file uploads to their new position
- Date and Time input fields now display properly in grid views
- Editing Grid variables from design page popup fixed
- Reordering sections and variables on a design fixed when attempting to reorder after saving once

## 0.7.0 (September 10, 2012)

### Enhancements
- **Design Changes**
  - Multi-page designs can be created by setting "break before" to true on sections
- **Sheet Changes**
  - Projects and Sites are now sorted alphabetically when creating a sheet
  - Unsaved changes while editing sheets now asks the user if they want leave the page
  - Add last_emailed_at to sheets
  - Sheets on projects with acrostic enabled now display the subject's acrostic on the PDF
  - Subject acrostic added to data exports
- **Variable Changes**
  - Units now available for numeric, integer, and calculated variables
  - Grid variable added that can display a list of variables in a grid format
    - Variables in a grid can also be repeated if more rows are needed in the grid
    - Variable input control sizes can be defined for grid variables

### Bug Fix
- Updating a subject's acrostic when editing a sheet now correctly updates the subject's acrostic

## 0.6.0 (August 10, 2012)

### Enhancements
- **Project Changes**
  - Sheet and subject counts on the project page now link to the corresponding filtered sheet or subject index pages
  - Project logos can be removed by hovering over the logo on the project show page
  - Hovering over a site user now displays who invited the user to the site
- **Sheet Changes**
  - Sheet index allows sorting by design name, subject code, site name, project name, and creator name
  - Sheets index columns reordered
    - Sheet, Subject, Study Date, Site, Project, Creator, Actions
  - Attaching a PDF to sheet receipt emails is now optional
- **Design Changes**
  - Sections and variables can be reordered on the design show page
  - Section and variable counts are now displayed on the design show page
  - Added more options for email templates
    - Site Name: `#(site)`
    - Study Date: `#(date)`
    - Variable: `$(variable)`,             i.e. `$(scorer_id)       => 1: John Smith`
    - Variable Label: `$(variable).label`, i.e. `$(scorer_id).label => John Smith`
    - Variable Value: `$(variable).value`, i.e. `$(scorer_id).value => 1`
  - Design index allows sorting by project name and creator name
  - Design index displays variable count per design
  - Variables and sections can now be added in the middle of a form when editing a design
  - Variables can now be edited while updating a design
  - Variable branching logic is now limited to variables already on the design
  - Advanced branching logic syntax can now be added to variables and sections
  - Data dictionaries in CSV format can now be downloaded by filtering designs
    - Invidual data dictionaries are also available for designs
- **Variable Changes**
  - Calculated variables can now have a specified format
    - Precision, i.e. `%0.02f`, `4.127 => 4.13`
    - Leading Zeros, i.e. `%04d`, `45 => 0045`
  - Numeric and integer max/min hard/soft range information is now displayed in a table under the variable description
  - Missing codes are now displayed distinctly for check boxes and radio button groups
  - Numeric fields have been changed back to text fields to be consistent across browsers
  - Copying variables retains the original project from which they were copied
- **Miscellaneous**
  - Users can specify how many items are displayed on index pages
  - User show page now displays the sites and projects the user can view
- Updated to Rails 3.2.8

### Bug Fix
- Designs no longer lose temporary modifications when changes aren't saved due to validation errors
- Variables no longer lose temporary modifications when changes aren't saved due to validation errors

## 0.5.0 (July 24, 2012)

### Enhancements
- **Project Changes**
  - Project page now shows more information on associated designs and sites
  - Logos can be added to projects and are displayed throughout the application and on PDFs
  - Projects can now enable acrostic codes for subjects
  - Users can be invited to individual project sites by email
- **Sheet Changes**
  - Printable version of designs and sheets have been improved
  - Sheets can now be exported as labeled or unlabeled CSV files
  - Sheets can be exported as a PDF collation
  - Missing codes for numerics are now displayed as a name and value when viewing sheets
- **Design Changes**
  - Designs can no longer be saved with duplicate variables
  - Each design can have its email template customized
- **Variable Changes**
  - Time variables added
  - File upload variables added
- Updated to Rails 3.2.7.rc1
  - Removed deprecated use of update_attribute for Rails 4.0 compatibility

### Bug Fix
- Designs loading time for editing has been improved
- Scroll-spy now works correctly on designs where variables are dynamically shown and hidden

### Testing
- Use ActionDispatch for Integration tests instead of ActionController

## 0.4.1 (July 11, 2012)

### Testing
- Added test to assure that subjects can't be created without being assigned to a site

## 0.4.0 (July 10, 2012)

### Enhancements
- **Variable Changes**
  - Hard Maximums and Hard Minimums added for date variables
  - Soft Ranges can be added to Integer, Numeric, and Date variables
  - Calculated variables are now supported
  - Missing codes can now be added to numerics and integers
  - Dropdown options are now grouped by missing codes
- **Subject and Site Changes**
  - Sites can now specify valid subject code ranges
  - Subjects created within the valid range are automatically validated
- **Email Changes**
  - Default application name is now added to the `from` field for emails
  - Email subjects no longer include the application name
- Sheet PDFs can be downloaded from Sheets Index page
- About page reformatted to include links to github and contact information

### Refactoring
- Index page ordering and sorting now done consistently across project
- Deleting items from lists uses partial page update to keep selected filters in place

## 0.3.1 (June 22, 2012)

### Bug Fix
- Older designs without condition_values set now load properly

## 0.3.0 (June 22, 2012)

### Enhancements
- Reset Filters added to Designs, Variables, Subjects, and Sites Index pages
- Designs and Sheets can now be printed to PDF
- **Sheet Changes**
  - CSV export now requires at least one sheet to be filtered
  - Sheet emails now also include a PDF attachment
- **Design Changes**
  - Designs can now have section headers
  - Design section names and variable headers are now included in the sheet email template
  - Sections and Variables can now be added to the top in the design builder
  - Conditional logic improved to allow cascading of conditional logic
- **Variable Changes**
  - Variable name field now only allows a maximum length of 32 characters
  - Variable show page now displays all designs that include the variable
- **Site Changes**
  - Sites can now have prefixes
  - Entering new subjects will now attempt to guess the site based on the subject code and site prefixes
- **Subject Changes**
  - Subjects can now be marked as validated to allow detection of erroneously added subject codes
  - Entering a subject code will now inform the user subject code is valid, invalid, or new
- **Project Changes**
  - Project page now lists designs and links to project specific designs
  - Project page now links to project specific sheets

## 0.2.1 (June 13, 2012)

### Enhancements
- **Sheet Changes**
  - Sheets are now updated to reflect changes to the associated design
- **Variable Changes**
  - Variables now display Project Name or Global when adding variables to designs
  - Updating existing variable option values now updates sheets accordingly
  - Removing an existing variable option now resets sheets with that option selected
  - Option values can't contain colons, must be unique and can't be blank
  - Variables are filterable by project, variable type, and creator
- **Design Changes**
  - Designs are filterable by project and creator
  - Selecting variables when designing a form now show a preview of the variable
  - Conditional logic added to hide variables on designs based on values in other variables
- **Project Changes**
  - Projects now link to subjects and sites
- **Subject Changes**
  - Subject's page shows sheets entered for that subject
  - Subjects can be filtered by designs that haven't been filled out
  - Subjects can be filtered by site
- Updated to Rails 3.2.6

### Bug Fix
- Librarians can edit/update all global designs/variables (designs/variables not on projects), or designs/variables that they have created themselves
- Librarians can move designs/variables to and from projects
- Subjects and Sites creation are now limited to projects a user can access

## 0.2.0 (June 8, 2012)

### Enhancements
- **Sheet Changes**
  - Sheets are restricted to one per design_id, project_id, subject_id, and study_date
  - Sheet receipts can now be emailed to the associated subject's site
  - Sheets can now be filtered by study date, project, site, design, and creator
  - Sheets track who last updated the sheet
  - Sheet data can be exported en-masse to CSV
- **Project Changes**
  - Projects can have emails to be cc'd on sheet receipts
  - Projects can now have multiple sites, and each subject is assigned to a specific site
  - Projects can now have
    - Librarians who can modify project designs and variables
    - Members who can modify project sheets, subjects, and sites
- **Design and Variable Changes**
  - Variable creating or updating
     - A confirmation box now displays that warns the user that options with blank names will be removed
     - Hard Minimum and Maximum values can be added for Numeric and Integer variables
  - Variables and Designs in Library can now be copied as templates for new variables or new designs
  - Previews now show for Designs and Variables

### Bug Fix
- Variable options now correctly load when editing a variable
- Subjects now correctly linked to their appropriate sheets

## 0.1.0 (May 29, 2012)

### Enhancements
- Added Design and Variable Libraries
  - Designs are used to create templates of forms for subjects
  - Variables are used to define data collected on the forms
- Added Projects, Subjects, and Sheets
  - Projects group together specific a set of subjects
  - Sheets are filled out forms

## 0.0.0 (May 15, 2012)

- Skeleton files to initialize Rails application with testing framework and continuous integration
