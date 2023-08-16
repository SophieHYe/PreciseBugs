## 57.2.0

## 57.1.0 (April 19, 2022)

### Enhancements
- **General Changes**
  - Added Fathom Analytics to website and removed Google Analytics

## 57.0.0 (February 26, 2022)

### Enhancements
- **General Changes**
  - Custom pages are now displayed in the navigation bar
- **Admin Changes**
  - Admins can now group pages under folders
  - Folders can be positioned in the navigation bar
    - Folders are only displayed if they have one or more published pages
  - Pages can be ordered by position in folders
    - Pages are only publicly visible if they're published
- **Dataset Changes**
  - Default ordering on the datasets index updated to "Featured"

## 56.0.0 (February 2, 2022)

### Enhancements
- **Dataset Changes**
  - Default ordering on the datasets index is now "Alphabetical"
- **Gem Changes**
  - Update to ruby 3.0.2
  - Update to rails 6.1.4.4

## 55.0.0 (November 7, 2021)

### Enhancements
- **General Changes**
  - Simplify landing page boxes
  - Added subject count to footer of landing page
- **Dataset Changes**
  - Added schema.org dataset type markup on dataset pages
  - Datasets index now has a link to the cohort matrix
- **Variable Changes**
  - Variables can now link other variables by surrounding the name with colons
    - Example description: "Weight is related to :bmi:"
- **Admin Changes**
  - Admins can now create static pages

## 54.0.0 (August 7, 2021)

### Enhancements
- **General Changes**
  - Disallowed access for SemrushBot and Seekport Crawler robots
- **Dataset Changes**
  - The nsrr gem download command now includes quotes for datasets with spaces
    in file download paths
- **Data Request Changes**
  - The dataset selection step is now displayed before going to the proof step
  - Users receive a data request submitted email after submitting their data
    request
- **Organization Changes**
  - A new data request viewer role has been added to organizations
- **Gem Changes**
  - Update to rails 6.1.4
  - Update to devise 4.8.0
  - Update to pg_search 2.3.5

## 53.0.0 (May 31, 2021)

### Enhancements
- **General Changes**
  - Added link to data security overview from Share page
  - Added twitter link to contact page
- **Search Changes**
  - Search for datasets is now case-insensitive
  - Pagination added back to search page
  - Increased number of search results per page to 25
  - Improved load speed of empty search page
  - Dataset slugs are now indexed with each documentation page to improve search
    results

### Bug Fix
- Fixed a bug that prevented a nested reply from being linked
- Fixed a bug that caused remote forms from correctly rendering the response

## 52.0.0 (April 24, 2021)

### Enhancements
- **General Changes**
  - Added a data security page
  - Added a Question and Answer section to the about page that is configurable
    by admins
  - Dataset documentation now shows up in the site-wide search results
  - Search results can be filtered by where they exist on the site
    - from blog
    - from forum
    - from datasets
    - from variables
- **Gem Changes**
  - Update to ruby 3.0.1
  - Update to rails 6.1.3.1
  - Update to devise 4.7.3
  - Update to haml 5.2.1

## 51.2.0 (April 4, 2021)

### Enhancements
- **Dataset Changes**
  - Increased the number of datasets on the datasets page to 25 and decreased
    the overall size of each information block
- **Gem Changes**
  - Update to rails 6.0.3.6
  - Update to carrierwave 2.2.1

## 51.1.0 (March 20, 2021)

### Enhancements
- **General Changes**
  - Added banner that can be enabled on the staging server to redirect users to
    the main production server

## 51.0.0 (January 16, 2021)

### Enhancements
- **General Changes**
  - Added "Sign up" button to menu bar
  - Added "Share data" button to menu bar
- **Gem Changes**
  - Update to figaro 1.2.0
  - Update to redcarpet 3.5.1

## 50.0.0 (November 2, 2020)

### Enhancements
- **General Changes**
  - Privacy policy now redirects to https://www.massgeneralbrigham.org/notices/web-privacy-policy
  - Added Google Analytics
- **Gem Changes**
  - Update to rails 6.0.3.4

### Fixes
- Fixed display of multi-line announcements

## 49.0.0 (September 19, 2020)

### Enhancements
- **General Changes**
  - Add a new announcement setting to blog categories that allows broadcasts in
    that category to be displayed at the top of the landing page
- **Admin Changes**
  - Users export updated to include username, email confirmation status, and
    login count
- **Review Changes**
  - Review votes and comments are no longer hidden before a reviewer has voted
- **Gem Changes**
  - Update to rails 6.0.3.3

### Bug Fix
- Fix bug preventing correct detection of '@' mentions in review comments

## 48.1.1 (August 26, 2020)

### Bug Fixes
- Fixed a bug that prevented generating an access denied response for altamira
- Fixed a bug that prevented variable indexes from displaying that contained
  variables that were located in the root folder

## 48.1.0 (August 19, 2020)

### Enhancements
- **Report Changes**
  - The approved column now displays the number of the months submitted data
    requests that were approved at any point in time, as opposed to the number
    of data requests that were approved that month
- **Variable Changes**
  - Labels have been renamed to tags

### Bug Fix
- Fixed a bug that grouped variables into the same folder when one folder name
  was the prefix of another folder

## 48.0.0 (August 16, 2020)

### Enhancements
- **General Changes**
  - Organization invite emails are now sent regardless of server setup
- **Admin Changes**
  - Added CreatedAt to forum export for topics and replies
- **Dataset Changes**
  - Organization name is now displayed on the dataset settings and edit pages
- **Dataset Request Changes**
  - Dataset reviewers are now added to newly added dataset request alongside
    organization reviewers
  - Approved datasets can no longer have their status changed to anything other
    than resubmit to allow users to make changes to an existing agreement
  - An approved data request can still be closed/expired early by setting the
    data request expiration date to the current date, or a date in the past
- **Organization Changes**
  - Unreleased datasets are now displayed for organization viewers on the
    organization page
  - Users can now see and accept organization invites on their dashboard
- **Report Changes**
  - The percent column on the organization data requests reports page now
    reflects the percentage of data requests submitted in that month that were
    approved at any point

## 47.0.0 (July 16, 2020)

### Enhancements
- **General Changes**
  - Update text about grant on the about page

## 46.0.0 (June 27, 2020)

### Enhancements
- **General Changes**
  - Welcome emails are now sent regardless of server setup
- **Forum Changes**
  - Searching initiated from the forum search now filters results to the forum
- **Legal Documents Changes**
  - Legal documents can now be published from the legal document page
- **Variable Changes**
  - Clicking the "Variables" tab on dataset pages now always navigates back to
    the variables index

### Bug Fixes
- Fixed minor typo on confirmation email sent page
- Legal document pages now display an error message if content is not provided
- Deleting a legal document page from index no longer deletes the legal document
- Deleted legal document pages no longer display on legal document pages index

## 45.0.0 (May 27, 2020)

### Enhancements
- **General Changes**
  - Updated about pages and added help text for "Core Member", "Contributor",
    and "AUG member" on the user edit page
- **Search Changes**
  - Search results now allow a limited amount of markdown for variable
    descriptions, including bold, italics, code, and links
- **Admin Changes**
  - Added rake task to export topics and replies from the forum
    - `rails forum:export RAILS_ENV=production`
- **Gem Changes**
  - Update to rails 6.0.3.1
  - Update to kaminari 1.2.1

### Bug Fixes
- Expired requests no longer display as approved on user dashboard
- Fixed an issue that caused custom data request variables to be offset in
  organization data request exports
- Improved the detection of "@" mentions, which now properly excludes "@"s that
  occur in the middle of an email address

## 44.0.0 (April 4, 2020)

### Enhancements
- **General Changes**
  - Researchers can now provide their ORCID iD on registration or update the
    ORCID iD on their profile
- **Dataset Changes**
  - Datasets can now be featured to appear at the top of the datasets list
  - Popular datasets are now calculated by the number of data requests made for
    that dataset and are now linked correctly from the landing
- **Variable Changes**
  - Variable display names no longer have a string limit for spout deploys
  - Site wide search now includes variable labels in the search index
  - Adjusted the display of variable information on variable show pages
- **Gem Changes**
  - Update to ruby 2.7.1
  - Update to rails 6.0.2.2
  - Update to pg 1.2.3
  - Update to bootstrap 4.4.1
  - Update to carrierwave 2.1.0
  - Update to font-awesome-sass 5.12.0
  - Update to mini_magick 4.10.1
  - Update to pg_search 2.3.2
  - Update to rubyzip 2.3.0

## 43.0.0 (November 17, 2019)

### Enhancements
- **General Changes**
  - Redirect `community/tools/nsrrspectraltrainfig` to Github
- **Blog Changes**
  - Hide comment indicators and comment section on blog posts
- **Gem Changes**
  - Update to ruby 2.6.4
  - Update to rails 6.0.1
  - Update to carrierwave 2.0.2
  - Update to devise 4.7.1
  - Update to font-awesome-sass 5.11.2
  - Update to haml 5.1.2
  - Update to pg_search 2.3.0
  - Update to rubyzip 2.0.0

## 42.0.2 (May 28, 2019)

### Enhancements
- **Article Changes**
  - Articles in draft mode now display category for editors
- **Gem Changes**
  - Update to haml 5.1.1
  - Update to jbuilder 2.9
  - Update to pg_search 2.2.0
  - Update to rubyzip 1.2.3

### Bug Fix
- Fix an issue displaying topics without last reply at timestamp

## 42.0.1 (May 10, 2019)

### Enhancements
- **General Changes**
  - Removed trailing space on breadcrumb link

## 42.0.0 (May 10, 2019)

### Enhancements
- **Gem Changes**
  - Update to devise 4.6.2

### Bug Fixes
- Paginated breadcrumbs no longer wrap on mobile
- Paginated breadcrumbs no longer lose filters when navigating through pages

### Refactoring
- Remove description from DatasetFile model

## 41.0.0 (May 7, 2019)

### Enhancements
- **API Changes**
  - Datasets API has been deprecated and moved to `api/v1/datasets.json`
- **Tool Changes**
  - The tools index now displays up to three featured tools, (by @dguettler)
- **Gem Changes**
  - Update to ruby 2.6.3

### Bug Fix
- Topics without replies now display properly, (by @dguettler)

## 40.0.1 (April 23, 2019)

### Bug Fix
- Fix a bug where blog cover photo did not display in preview mode for
  non-published articles

## 40.0.0 (April 23, 2019)

### Enhancements
- **Blog Changes**
  - Cover pictures now display in draft mode when editing articles
  - Articles can be marked as featured in their respective category
- **Reviewer Changes**
  - Data request submissions and approvals are now collected in a daily digest
    instead of notifying reviewers for each data request
- **Tool Changes**
  - The tools section has been wrapped into the "tools" blog category to allow
    more up to date information about new tools, and to also emphasize the fact
    that tools should be housed and documented in open source repositories
    similar to GitHub as opposed to having "mirrored" pages on the NSRR
- **Gem Changes**
  - Update to font-awesome-sass 5.8.1
  - Update to pg_search 2.1.6

### Bug Fix
- Dataset documentation now correctly updates even if no folder exists yet for
  the dataset

## 39.0.1 (March 18, 2019)

### Bug Fix
- Fix ordering of JavaScript includes to include jQuery first to avoid CSRF
  issues on AJAX requests

## 39.0.0 (March 18, 2019)

### Enhancements
- **Data Request Changes**
  - Users can now fix their duly authorized representative's email if it was
    incorrectly entered during the data request process
- **Gem Changes**
  - Update to ruby 2.6.2
  - Update to rails 6.0.0.beta3
  - Update to bootstrap 4.3.1

### Bug Fix
- Fix "Write a Reply" redirection on topics with interleaved deleted replies
- Fix a redirect bug that occurs in Microsoft Edge when clicking the
  "Write a Reply" button

## 38.0.0 (February 12, 2019)

### Enhancements
- **API Changes**
  - Redeploying a dataset now correctly updates a variable's display name and
    variable type
- **General Changes**
  - Update FAIR guiding principles
  - Update data sharing language
- **Dataset Changes**
  - Dataset footer now correctly sticks to the bottom of the screen on pages
    without sufficient content
  - Documentation repositories are now automatically loaded when an editor
    specifies or changes the repository URL
- **Organizaton Changes**
  - Improved dataset creation and access by organization editors and viewers
  - Improved organization reports to show breakdowns of submitted and approved
    data requests, as well as overall downloads
  - Data requests can now be exported by organization editors and viewers
- **Variable Changes**
  - Remove extra space in variables index placeholder text
  - Move file download link to display below inline PDF on variable form page
- **Gem Changes**
  - Update to ruby 2.6.1
  - Update to rails 6.0.0.beta1
  - Update to pg 1.1.4
  - Update to bootstrap 4.3.0
  - Update to devise 4.6.1
  - Update to pg_search 2.1.4

### Bug Fix
- Quoted text now displays properly in forum posts and data requests reviews

## 37.0.0 (January 4, 2019)

### Enhancements
- **Gem Changes**
  - Update to ruby 2.6.0
  - Update to bootstrap 4.2.1
  - Update to carrierwave 1.3.1
  - Update to font-awesome-sass 5.6.1
  - Update to hashids 1.0.5
  - Update to pg_search 2.1.3

### Bug Fix
- Dataset indexing now picks up files and folders that include parentheses
- Fixed sorting users by email and logins on the admin users index

## 36.0.0 (December 14, 2018)

### Enhancements
- **General Changes**
  - Improved devise email subjects
- **Data Request Changes**
  - Minor updates to data request notification email subject lines
- **Gem Changes**
  - Update to rails 5.2.2
  - Update to font-awesome-sass 5.5.0

### Bug Fix
- Fix a bug generating graph of data requests for a single dataset

## 35.0.1 (October 16, 2018)

### Bug Fix
- Fix styling of last section in Privacy Policy

## 35.0.0 (October 16, 2018)

### Enhancements
- **General Changes**
  - Add updated Privacy Policy
  - Update file inputs to match custom Bootstrap file inputs
- **Admin Changes**
  - Add admin report for site-wide searches
- **Dataset Changes**
  - Display dataset DOI on dataset documentation pages
  - Dataset cards can now display a single age
- **Gem Changes**
  - Update to rails 5.2.1
  - Update to pg 1.1.3
  - Update to bootstrap 4.1.3
  - Update to carrierwave 1.2.3
  - Update to devise 4.5.0
  - Update to hashids 1.0.4
  - Update to jquery-rails 4.3.3
  - Update to mini_magick 4.9.2
  - Update to rubyzip 1.2.2
  - Update to sitemap_generator 6.0.1

### Bug Fix
- Dataset subjects count, age minimum, and age maximum now properly validate
  while updating datasets

## 34.2.0 (July 31, 2018)

### Enhancements
- **Review Changes**
  - Improved review names on review index
  - Search now includes fields on the data request

## 34.1.0 (July 30, 2018)

### Enhancements
- **Review Changes**
  - Notify organization reviewers when new data requests are submitted

## 34.0.0 (July 24, 2018)

### Enhancements
- **General Changes**
  - Added darker footer to registration and other pages
  - Updated framework files to resemble Rails 5.2 defaults more closely
  - NSRR file downloads now require the nsrr gem version 0.3.0 or higher
    - Versions 0.1.0, 0.1.1, and 0.2.0 are no longer supported
    - Use `nsrr update` to update to the latest version
- **Blog Changes**
  - Simplified navigation between live blog and unpublished (draft) articles
  - Blogs can now have a cover image
- **Dashboard Changes**
  - Improved the dashboard interface to provide better information for new users
- **Dataset Changes**
  - Improved dataset navigation layout between docs, files, and variables
- **Organization Changes**
  - Added organization editor and viewer roles
    - Editors can manage organization membership and datasets
    - Viewers can view organization reports
  - Added organization principal reviewer, and regular reviewer roles
    - Principal reviewers can approve data requests
    - Regular reviewers can comment and vote on data requests
- **Profile Changes**
  - Profile URL now displays on member profile page
- **Review Changes**
  - Minor improvements to how review tags are updated
  - Reduced performance hit of reviewer username autocomplete when making a
    comment mentioning another user

### Refactoring
- Removed typeahead.js library

## 33.0.1 (July 13, 2018)

### Bug Fix
- Cookie is now correctly set when clicking survey link

## 33.0.0 (July 13, 2018)

### Enhancements
- **General Changes**
  - First and last name merged into single full name field
  - Updated the user interface across sign up and sign in pages
- **Admin Changes**
  - Update interface of admin dashboard pages
  - Admins can review user profiles
  - Admins can empty spam folder and view spam report
- **Data Request Changes**
  - Data request expiration dates are now present on the user dashboard and on
    printed data requests
- **Dataset and Tool Review Changes**
  - Reviews now only require a rating to be submitted, review text is optional
- **Email Changes**
  - Email confirmation is now required for all accounts
  - Added detection of disposable email addresses
- **Forum Changes**
  - Navigating to a linked reply now highlights the reply
  - Added spam prevention for the forum
  - Topics now auto-lock after two months of inactivity
- **Profile Changes**
  - Member contributed tools are now listed on the member profile
- **Review Changes**
  - Data request expiration date is displayed on the data request review page
- **Tool Changes**
  - Tools now show up as results in search

### Refactoring
- Removed deprecated challenges
- Merged community tools and tools codebase
- Removed deprecated hosting requests

### Bug Fix
- Fixed an issue displaying variable subfolders for a folder with parentheses

## 32.0.0 (May 2, 2018)

### Enhancements
- **General Changes**
  - Removed Google Analytics
  - Improved password autocomplete
- **Variable Changes**
  - Variable folders can now include parentheses in their name
- **Gem Changes**
  - Updated to ruby 2.5.1
  - Updated to rails 5.2.0
  - Updated to pg 1.0.0
  - Updated to bootstrap 4.1.1
  - Updated to carrierwave 1.2.2
  - Updated to devise 4.4.3
  - Updated to simplecov 0.16.1
  - Updated to capybara 3.0
  - Removed bootsnap

### Bug Fix
- Fixed a bug that occurred when publishing a legal document after removing a
  variable

## 31.0.1 (January 8, 2018)

### Bug Fix
- Fixed a bug that prevented users with approved data access from seeing
  unreleased datasets on the datasets index

## 31.0.0 (January 8, 2018)

### Enhancements
- **General Changes**
  - Updated team page
- **Gem Changes**
  - Updated to ruby 2.5.0
  - Updated to bootsnap 1.1.8
  - Updated to bootstrap 4.0.0.beta3
  - Updated to devise 4.4.0
  - Updated to pg_search 2.1.2

## 30.1.0 (December 14, 2017)

### Enhancements
- **General Changes**
  - The main menu bar has been improved
  - The site-wide search has been improved and now also returns results for
    variable pages
- **Organization Changes**
  - Added three new organization report pages:
    - "This Month" displays the increase or decrease of submitted and approved
      data requests
    - "Data Requests" displays a graph of the past years submitted and approved
      data requests
    - "Data Request Stats" gives a breadown of data requests
- **Reviewer Changes**
  - Approved to rejected review no longer displays to reviewers who have not
    voted
  - Voting on data request now reloads and displays hidden comments and votes
  - Approving or rejecting a data request after writing a comment now also
    submits the comment
- **Tool Changes**
  - Removed tool ratings from tools index
- **Gem Changes**
  - Updated to rails 5.2.0.beta2
  - Updated to bootsnap 1.1.7
  - Updated to sitemap_generator 6.0.0
  - Updated to simplecov 0.15.1

### Bug Fix
- Fixed a bug that prevented default user forum icons from displaying

## 30.0.0 (November 30, 2017)

### Enhancements
- **General Changes**
  - Improved landing page styling
  - Removed expired flow limitation challenges
- **Admin Changes**
  - Improved Downloads by Quarter report loading speed
- **API Changes**
  - Dataset versions now have their release date set on creation
- **Dashboard Changes**
  - Improved user profile and settings management from dashboard
  - Users can now provide profile information about themselves
  - Users can now delete their account
- **Data Request Changes**
  - Data requests now use
    [SignaturePad](https://github.com/szimek/signature_pad)
    for capturing signatures
  - Data requests now follow a simpler path, with a new left hand menu and a
    simpler URL structure
  - Supporting documents can now be attached to data requests
- **Dataset Changes**
  - Dataset status has been simplified:
    - `released`, also the same as `listed`, `public`, and
    - `unreleased`, also the same as `unlisted`, `private`
  - Datasets without documentation now show the dataset menu after creation
- **Organizations Added**
  - Organizations are used to group datasets, legal documents, and reviewers
  - Organization editors can now create legal documents
  - Improved data request spreadsheet export task
- **Legal Documents Added**
  - Legal documents create frameworks for data access and use agreements
  - Organizations can create multiple legal documents for different user types
  - Legal documents are defined by:
    - Commercial Type:
      - `both`, `commerical`, and `noncommercial`
      - Users are asked whether they are commercial or noncommercial unless
        `both` is selected, and this is saved on the per-user level
      - Users can change their commercial type per agreement if needed
    - Data User Type:
      - `both`, `individual`, `organization`
      - Individuals vs organizations may be required to specify different types
        of data in agreements
      - Similar to "Commerical Type", users are asked if they are entering an
        agreement as an individual as an organization, and this setting is also
        saved for future agreements
      - Users can change their data user type per agreement if needed
    - Attestation Type:
      - `none`, `checkbox`, `signature`
      - Legal documents that require no attestation simply store that the user
        has viewed the agreement at a certain timestamp
      - Legal documents that require a checkbox explicitly ask the user to check
        a checkbox to attest that they have read, answered truthfully and to the
        best of their knowledge, and agree to the legal document
      - Legal documents that require a signature require that the user signs
        attesting that they have read, answered truthfully and to the best of
        their knowledge, and agree to the legal document
        - Users who can't sign can have a Duly Authorized Representative sign
          on their behalf for these types of legal documents
      - All three types of legal documents track when a user has attested by
        either submitting, checking, or signing the agreement
    - Approval Process:
      - `immediate` and `committee`
      - Legal documents that have an immediate approval process simply require
        the data user to complete and submit the agreement
      - Immediate approval agreements can still have time limits placed on the
        duration of the agreement after which a renewal is required
      - Legal documents that require committee approval get sent to the
        organization committee for review
      - An organization primary reviewer can approve these agreements, or ask
        users to make changes and resubmit
    - Legal documents that require no attestation or checkbox attestation and
      have and immediate approval process can be filled out directly using the
      NSRR downloader
    - For the NSRR downloader, checkbox attestation is replaced by typing "Yes"
      or "No" instead of checking a checkbox
  - Legal documents consist of one or more pages with content and data collected
    from data users
  - Legal documents can also contain optional riders and supporting
    documentation uploads
  - Organization editors can create and modify legal documents and assign them
    to one or more datasets
  - Multiple legal documents can be assigned to datasets to cover the different
    commerical/noncommercial and individual/organization user types
  - Legal documents are versioned when they are published, and subsequent
    changes to the agreements only affect new users
- **Profile Changes**
  - Users can now delete their account
  - Users can now directly upload profile pictures
- **Reviewer Changes**
  - Simplified voting on agreements as a reviewer
  - Reviewers can upload supporting documents to a data request
  - Reviewers can mark specific data request fields for resubmission
  - Reviewers are now blinded before casting initial vote
  - Reviewers are prompted to vote again after a data request is resubmitted
  - Dataset changes are now tracked in the data request history
- **Variable Changes**
  - Improved display of long variable names
- **Gem Changes**
  - Updated to ruby 2.4.2
  - Updated to rails 5.1.4
  - Updated to bootstrap 4.0.0.beta2
  - Updated to carrierwave 1.2.1
  - Updated to haml 5.0.4
  - Updated to kaminari 1.1.1
  - Added bootsnap 1.1.5

### Tests
- Added tests to assure user passwords can be reset

## 0.29.2 (November 20, 2017)

### Enhancement
- **General Changes**
  - Updated demo page to reference new location of EDFs in SHHS

## 0.29.1 (October 18, 2017)

### Bug Fix
- Fixed a bug that prevented reviewers from viewing IRB files that included
  URL-escaped characters

## 0.29.0 (August 28, 2017)

### Enhancements
- **General Changes**
  - Added page to guide researchers through Data Sharing process
  - Added default ratings for tools and datasets
- **Gem Changes**
  - Updated to rails 5.1.3
  - Updated to pg_search 2.1.0
  - Updated to haml 5.0.2
  - Updated to simplecov 0.15.0

### Bug Fix
- Agreement PDF URLs are no longer stored in friendly forwarding

## 0.28.0 (July 11, 2017)

### Enhancements
- **General Changes**
  - Default ratings are now set at 3 stars for unreviewed datasets and tools
- **Admin Changes**
  - Added a quarterly download report for admins
- **Dataset Changes**
  - `md`, `pdf`, and image files are now displayed in browser when clicked on
    the files index
- **Variable Changes**
  - Improved ranking and display of variables when using search terms
- **Forum Changes**
  - Added time abbreviations for months and years on forum index
- **Gem Changes**
  - Updated to rails 5.1.2
  - Updated to pg 0.21.0
  - Updated to mini_magick 4.8.0

### Bug Fix
- Pagination now works correctly when navigating through variable folders
- Logging in no longer clears the menu header in certain situations

## 0.27.0 (May 16, 2017)

### Enhancements
- **General Changes**
  - Improved loading JavaScript during Turbolinks page transitions
  - Changed human verification from "Invisible" to "I'm not a robot" ReCAPTCHA
  - Added a "Complete a Survey" link to several pages
  - Improved menu on mobile
- **Account Changes**
  - Users can now change their password on the account settings page
- **Agreement Changes**
  - Improved the process of renewing an existing DAUA
- **Dashboard Changes**
  - DAUA Reviewers now have a link to the reviews index on their dashboard
- **Dataset Changes**
  - Datasets that have all files public no longer redirect users to the DAUA
    process, and can no longer be selected during the DAUA process
- **Email Changes**
  - Improved formatting of Data Access and Use Agreement resubmission emails
  - Simplified text for links in emails
- **Forum Changes**
  - Improved forum styling
- **Notification Changes**
  - Minor improvements to marking notifications as read
  - Clicking on notification for forum or blog replies now jumps directly to the
    reply
  - Improved styling of notifications link in menu bar
- **Gem Changes**
  - Updated to Ruby 2.4.1
  - Updated to rails 5.1.1
  - Updated to devise 4.3.0
  - Updated to pg 0.20.0
  - Updated to carrierwave 1.1.0
  - Updated to haml 5.0.1
  - Updated to jquery-rails 4.3.1
  - Updated to mini_magick 4.7.0
  - Updated to sitemap_generator 5.3.1
  - Updated to simplecov 0.14.1

### Bug Fixes
- Page navigation is now properly canceled when navigating away from a form
- Fixed a bug preventing admins from pinning and unpinning forum topics

## 0.26.3 (February 28, 2017)

### Enhancements
- **General Changes**
  - Added a users export task

## 0.26.2 (February 21, 2017)

### Enhancements
- **General Changes**
  - Added link to team page from about page

## 0.26.1 (February 20, 2017)

### Enhancements
- **General Changes**
  - Login cookies are now cross subdomain and work between www and non-www URLs
  - Added a team page
  - Grant number is now on the about page
  - Improved styling of several pages for mobile devices
- **Agreement Changes**
  - Improved display of DAUA review index
  - Improved progress display for DAUAs
- **Variables Changes**
  - Improved search display and results on variables index

### Bug Fix
- Fixed a bug that incorrectly displayed file counts and file sizes for datasets

## 0.26.0 (February 6, 2017)

### Enhancements
- **Admin Changes**
  - Improved user management for admins on user index
  - Dataset hosting requests can now be marked as reviewed
- **Agreement Changes**
  - Removed additional confirmation step when voting for reviewers
  - Added a new admin table displaying approved DAUAs by dataset
  - Voting on a review now updates overall reviewers votes as well
  - Signatures are now displayed with other information at top of review
  - DAUAs can no longer accidentally be submitted twice by double-clicking
- **Blog Changes**
  - Improved layout of administrative blog overview index
  - Added Rich Text Editor buttons
- **Dashboard Changes**
  - Added a quick view of Data Access and Use Agreement submissions
  - Updated layout of dashboard
- **Community Tool Changes**
  - Tools can now be marked as scripts or tutorials
  - Tools can now be reviewed and rated
  - Tool reviews create notifications for tool creators
  - Tool submission process simplified
- **Dataset Changes**
  - Improved layout of the datasets index
  - Datasets can now be reviewed and rated
  - Dataset files can be toggled between private and public more easily
- **General Changes**
  - Improved friendly forwarding when navigating between internal and external
    pages while signing in and signing out
  - Updated the user interface across the site
  - Updated the about page
  - Added an invisible reCAPTCHA to sign up page
  - Improved the display of blockquotes
- **Gem Changes**
  - Updated to Ruby 2.4.0
  - Updated to rails 5.0.1
  - Updated to carrierwave 1.0.0
  - Updated to pg_search 2.0.1
  - Updated to redcarpet 3.4.0
  - Updated to jquery-rails 4.2.2
  - Added autoprefixer-rails
  - Updated to kaminari 1.0.1
  - Updated to simplecov 0.13.0
  - Updated to hashids 1.0.3

### Bug Fix
- Fixed an issue displaying images associated to tools
- Fixed dataset collaborator autocomplete from rendering dropdown multiple times

## 0.25.1 (December 7, 2016)

### Bug Fixes
- Fixed an incorrect redirect that would occur when navigating to a URL of a
  dataset file path that had been deleted
- File checksums are now removed when dataset file references are deleted

## 0.25.0 (December 7, 2016)

### Enhancements
- **Agreement Changes**
  - Made the language for selecting multiple datasets more specific
- **Blog Changes**
  - Limit the number of blog posts shown in the sidebar
- **Gem Changes**
  - Updated to Ruby 2.3.3
  - Updated to rails 5.0.0.1
  - Updated to pg 0.19.0
  - Updated to jquery-rails 4.2.1
  - Updated to font-awesome-rails 4.7.0
  - Updated to mini_magick 4.6.0
  - Updated to sitemap_generator 5.2.0
  - Removed geocoder

### Refactoring
- Updated kaminari pagination views to use haml

### Bug Fixes
- Fixed a bug that prevented users from logging in correctly with an expired
  authenticity token
- Fixed a bug that prevented back navigation after opening a PDF
- Fixed an issue displaying images in documentation
- Fixed an issue loading Google Analytics
- Rails model errors are now again correctly styled using Bootstrap CSS classes
- Fixed a bug that prevented images from being attached to forum replies

## 0.24.2 (July 19, 2016)

### Enhancement
- **Agreement Changes**
  - Emails are now sent in background when submitting a DAUA

### Bug Fix
- Fixed emails in forked processes from not being sent

## 0.24.1 (July 11, 2016)

### Enhancements
- **Configuration Changes**
  - Updated Rails 5 configuration files

## 0.24.0 (July 6, 2016)

### Enhancements
- **Gem Changes**
  - Updated to rails 5.0.0
  - Updated to devise 4.2.0
  - Updated to turbolinks 5
  - Updated to coffee-rails 4.2
  - Updated to jbuilder 2.5
  - Updated to simplecov 0.12.0
  - Updated to carrierwave 0.11.2

### Bug Fix
- Added missing typeahead.js library

### Refactoring
- Removed unused database tables `broadcast_comments` and
  `broadcast_comment_users`
- Prefer use of `after_create_commit :fn` instead of
  `after_commit :fn, on: :create`

## 0.23.0 (June 24, 2016)

### Enhancements
- **Admin Changes**
  - Added admin view for blog post and forum topic replies
  - Improved speed of dataset audits page
  - Admins now receive notifications when a dataset hosting request is submitted
- **Blog Changes**
  - Added view_count column to track blog post views
  - Improved the blog index to show extracts of the blog posts
- **Forum Changes**
  - Forum topics now track views and have better URL structure
  - Users can upvote, downvote, and reply directly to other forum posts
  - Users now receive in-app notifications when a new reply is added to a forum
    topic to which they are subscribed
- **General Changes**
  - Added a sitemap for better indexing on Google and Bing
- **Search Added**
  - A site-wide search has been added that searches through blog posts and forum
    topics
- **Gem Changes**
  - Added the pg_search gem for full-text search
  - Removed dependency on jquery-ui-rails

### Refactoring
- Removed deprecated columns from agreement_events
- Removed deprecated column from variables
- Removed unused controllers and associated views
- Reorganized JavaScript files

## 0.22.0 (June 13, 2016)

### Enhancements
- **Admin Changes**
  - Updated the graph tick interval to be 512MB instead of 500MB
  - Simplified filters on DAUA review index
- **Gem Changes**
  - Updated to kaminari 0.17.0
  - Updated to devise 4.1.1
  - Removed dependency on contour

### Refactoring
- Removed dependency on database serialize for simpler migration to Rails 5

## 0.21.0 (June 9, 2016)

### Enhancements
- **Admin Changes**
  - The y-axis in Downloads by Month admin report now more accurately displays
    sizes over 1TB
- **General Changes**
  - Added Data Sharing Language for use in grant submissions

### Bug Fix
- Fixed an issue that prevented users from requesting datasets due to the
  submission status from being incorrectly set

## 0.20.0 (May 9, 2016)

### Enhancements
- **General Changes**
  - Improved meta tags for sharing a link to the NSRR on Facebook and Google+
- **Agreement Changes**
  - Emails sent to reviewers for resubmitted agreements now indicate that the
    agreement is a resubmission
- **Blog Changes**
  - Admins can now create blog categories
  - Blogs can now be assigned a category
  - Blog posts have a discussion section that is sorted by best or new comments
  - Comments on blog posts can be up- and down-voted
  - Comments can be ordered by highest ranked or newest
- **Gem Changes**
  - Updated to Ruby 2.3.1
  - Added font-awesome-rails

### Bug Fix
- Agreement report tables now correctly shows counts of submissions that have
  been started

### Refactoring
- Removed deprecated code from domain model

## 0.19.2 (April 18, 2016)

### Enhancement
- **Dataset Changes**
  - Reduced the polling speed page refresh while indexing files

### Bug Fix
- Fixed a bug that prevented domain options without missing key from being saved

## 0.19.1 (April 18, 2016)

### Enhancements
- **Gem Changes**
  - Updated to carrierwave 0.11.0
  - Updated to mini_magick 4.5.1

### Bug Fix
- Fixed a bug that prevented the documentation from being synced

## 0.19.0 (April 14, 2016)

### Enhancements
- **Agreement Changes**
  - Principal reviewers can now close agreements
  - Review process now quickly displays past datasets approved for data user
  - IRB approval attachment is now more prominently displayed for reviewers
- **Blog Changes**
  - Community members can now more easily edit blogs
  - Keywords can be added to blog posts to increase visibility on search engines
- **Dataset Changes**
  - Improved browsing file downloads on smaller devices
  - File download commands can now be easily copied to clipboard
  - Dataset file folders can now have individual descriptions
  - Dataset tracking of file information, like MD5 and file size, has been
    improved
  - Requesting a single file through the JSON API now works as well
    - This allows the nsrr gem to download single files
  - Dataset editors can now view and filter agreements for single datasets
  - The dataset files directory is now created on dataset creation
- **Dashboard Changes**
  - Improved display of admin pages linked from the dashboard
- **Forum Changes**
  - Changing subscription preference on forum topics no longer reloads the
    entire page
- **Map Changes**
  - Map location is now pulled using a local database lookup
- **Mobile Changes**
  - Added a link to the user dashboard in mobile navigation menu
- **Email Changes**
  - Removed margins in emails to display better across email clients
- **Variable Changes**
  - Variable labels are now added as meta page keywords to increase visibility
    on search engines
  - Variable forms now display a message if the file is available only viewable
    with a data access and use agreement
  - Variable forms now display a message if the linked file is not found on the
    server
  - Improved how variable domain options are stored
  - Fixed the ordering when transitioning between variables using arrow keys
- **API Changes**
  - Introduced a new API for dataset file downloads that supports both full
    folder and individual file downloads
    - `GET /api/v1/datasets.json`
      - List all viewable datasets
    - `GET /api/v1/datasets/:dataset.json`
      - Displays information on a dataset
    - `GET /api/v1/datasets/:dataset/files.json`
      - Displays a list of files available at the dataset files root directory
    - `GET /api/v1/datasets/:dataset/files.json?path=folder`
      - Displays a list of files available in the dataset `folder` directory
    - `GET /api/v1/datasets/:dataset/files.json?path=folder/file.txt`
      - Returns an array containing information about `folder/file.txt`
  - All of the above commands can also optionally include the `auth_token`
    parameter to authenticate a specific user to view information on private
    datasets and files
  - Added a new API for authenticating account
    - `GET /api/v1/account/profile.json`
      - Returns profile information for authenticated account
- **Gem Changes**
  - Updated to rails 4.2.6
  - Restricted mini_magick to 4.4.0
  - Added maxminddb gem

### Bug Fix
- Fixed documentation links on dataset and tool sync page

### Refactoring
- Started cleanup and refactoring, and additional testing of controllers

## 0.18.4 (March 2, 2016)

### Enhancements
- **General Changes**
  - Added a Past Contributor page
- **Agreement Changes**
  - Reviewers comments and votes on DAUAs now update inline using AJAX
- **Email Changes**
  - Improved the responsiveness and display of emails on smaller devices
- **Gem Changes**
  - Updated to rails 4.2.5.2
  - Updated to geocoder 1.3.1
  - Updated to simplecov 0.11.2

### Bug Fixes
- Fixed a bug that occurred when updating variables without uploading datasets
- Fixed an issue that caused the blog RSS from being cached for friendly
  forwarding
- Fixed a bug that inserted placeholder text into textarea elements when using
  Internet Explorer in combination with Turbolinks
- Fixed an issue where long URLs would break topic views on mobile

## 0.18.3  (January 26, 2016)

### Enhancements
- **Gem Changes**
  - Updated to rails 4.2.5.1
  - Updated to jquery-rails 4.1.0

## 0.18.2 (January 21, 2016)

### Enhancements
- **Agreement Changes**
  - Emails are now sent in background when a principal reviewer approves an
    agreement or asks for an agreement resubmission
  - Improved user interface for the DAUA review process
- **Submission Changes**
  - Improved visibility of "Get Started" button when launching a new DAUA
- **Registration Changes**
  - A welcome email is now generated for new users filling out:
    - dataset access use agreements
    - dataset hosting requests
    - and tool contributions
- **Blog Changes**
  - Added an ATOM feed to allow new blogs posts to be picked up by RSS feed
    readers

## 0.18.1 (January 20, 2016)

### Enhancements
- **Forum Changes**
  - Fixed a minor UI issue with the forum post edit and delete buttons
- **Agreement Changes**
  - Datasets are now ordered properly while reviewing existing DAUAs
- **Contact Changes**
  - Minor update to text on contact page

### Bug Fix
- Fixed an issue approving and setting tags on DAUAs

## 0.18.0 (January 20, 2016)

### Enhancements
- **General Changes**
  - Improved the user interface across the site for easier navigation and a
    cleaner look
  - Started work on a comprehensive Site Map
  - Emails sent from site have been updated to match the new user interface
- **Dataset Changes**
  - Improved dataset access request flow
  - Added call to action for researchers who wish to contribute datasets to the
    NSRR
  - Users can now fill out a dataset hosting request form to host new datasets
    on the NSRR
  - Datasets now track all versions and maintain data dictionary changes and
    history
- **Tool Changes**
  - Users can now submit URLs for tools to be listed on the NSRR
  - GitHub repositories with READMEs and GitHub gists can now be contributed as
    tools
  - Tool descriptions are pulled automatically and can be written and previewed
    using markdown
- **Variable Changes**
  - Added a table of domain options for variables that are linked to domains
  - Variables are now sorted by relevance when searching for key terms
  - Individual variable pages have been redesigned and include additional
    information about known issues and variable history
  - Folder navigation has been simplified on the variable index
  - Embedded PDFs for variables are now displayed directly on the variable page
- **Dashboard Added**
  - Users can now view their personal dashboard that contains updates and links
    to their datasets and other NSRR related activity
- **Blog Added**
  - Community managers can now create new blogs posts that are viewable on the
    NSRR home page
  - Images can be easily added via drag-and-drop while editing blog posts
  - Blogs can be previewed while editing
- **Forum Changes**
  - Images can now be more easily added to forum posts
- **Gem Changes**
  - Updated to Ruby 2.3.0
  - Updated to rails 4.2.5
  - Updated to pg 0.18.4
  - Updated to simplecov 0.11.1
  - Updated to web-console 3.0

### Bug Fix
- Fixed an issue where chart numbers would not show well on charts with dark
  columns
- Fixed various navigation issues in IE caused by caching

## 0.17.3 (November 6, 2015)

### Enhancements
- **Challenge Updates**
  - A new challenge has been added!
    - Flow Limitation - Part 2 is now available for users to fill out
    - The original Flow Limitation challenge has been archived
- **General Changes**
  - Fixed a minor display issue when proofing a DAUA submission in Step 1
  - Updated items on the carousel
  - Added call to action on research showcase pages
  - Updated link to the NSRR Cross Dataset Query Interface
  - Removed swiping left and right on variable pages from mobile view
  - Updated styling on dataset and tool image pages
- **Forum Changes**
  - Large images are now scaled down to fit correctly in forum posts
- **Admin Changes**
  - Improved loading time of overall file download statistics
  - Improved loading time of overall file downloads per month graph
- **Gem Changes**
  - Removed minitest-reporters
  - Updated to pg 0.18.3

### Bug Fix
- Fixed a bug that prevented users with tokens ending in hyphens from
  authenticating correctly while using the NSRR gem

## 0.17.2 (August 25, 2015)

### Enhancements
- **General Changes**
  - Updated styling on documentation sync pages
- **Admin Changes**
  - Agreement reports are now filtered by regular members
- **Gem Changes**
  - Use of Ruby 2.2.3 is now recommended
  - Updated to rails 4.2.4
  - Added web-console

## 0.17.1 (July 8, 2015)

### Enhancements
- **Gem Changes**
  - Updated to contour 3.0.1

## 0.17.0 (June 30, 2015)

### Enhancements
- **General Changes**
  - Added Google Analytics
  - Fixed some minor spacing issues on forum
  - User setting page now lists all active dataset requests for a user
- **Showcase Added**
  - Added a page highlighting Matt Butler's work on Novel Sleep Measures and
    Cardiovascular Risk
  - Highlighted Shaun Purcell's work on the home page carousel
- **Forum Changes**
  - Removed auto-subscribing users to new forum topics
  - Comments on topics are now immediately sent to anyone subscribed to the
    topic
  - Daily forum digests have been removed
  - Forum index font size has been reduced to display more topics
- **Agreement Changes**
  - The DAUA process has been simplified to 6 steps total
  - The agreements administration is being merged into the reviewer page
    - This will help consolidate the DAUA approval process in a single place for
      principal reviewers
  - Users not authorized to sign a DAUA are now provided a link that they can
    send to the authorized user
  - An email is sent to the DAUA user when the authorized user has signed the
    DAUA
  - Appending multiple users to an organization's DAUA has been removed
  - The Duly Authorized Representative can now review the entire agreement
    before signing
  - Agreement changes are now audited so that reviewers can view changes more
    easily between resubmissions
  - Principal reviewers can now modify the requested datasets during the review
    process
  - Principal reviewers can now export information on all agreements as a CSV
  - Clarified reference to the IRB Assistance page
  - Improved the user interface for the DAUA submission process
- **Challenge Changes**
  - Added an export for the Flow Limitation challenge
- **Dataset Changes**
  - The dataset pages have been updated to be easier to navigate
  - Dataset variable and file pages now have a newer interface
  - Visiting a dataset page now better displays a current users process of
    accessing data
  - Simplified specifying user roles for dataset editors
- **Tool Changes**
  - Tool editors can now sync documentation repositories
- **Administrative Changes**
  - Admin location report now better shows unmatched locations
  - The admin dashboard now points to the consolidated reviews path
  - The admin now has a report view that shows user roles across datasets
  - Admins can now see a list of agreements on individual user pages
- **Gem Changes**
  - Use of Ruby 2.2.2 is now recommended
  - Updated to rails 4.2.3
  - Updated to pg 0.18.2
  - Updated to simplecov 0.10.0
  - Updated to chunky_png 1.3.4
  - Updated to contour 3.0.0
  - Added differ 0.1.2 gem to see changes in DAUAs
  - Updated to kaminari 0.16.3
  - Updated to redcarpet 3.3.2
  - Updated to geocoder 1.2.9
  - Updated to figaro 1.1.1

### Bug Fix
- Fixed a bug that prevented reviewers seeing DAUA signature if the user signed
  and checked that they were unauthorized to sign the DAUA
- Fixed an issue where users could post images and links on forum without having
  explicit permission to post links or images
- IRB Assistance Template should now show up properly for regular users
- Fixed map not picking up certain cases of users living in the US
- Fixed an issue on iOS 7 devices that were incorrectly rendering `vh` units
- Fixed a bug rendering previews for new and existing comments
- Posts on forum now correctly display when a user uses the `<` symbol

## 0.16.1 (April 1, 2015)

### Bug Fix
- Fixed incorrectly labeled Signal 13 on the Flow Limitation Challenge

## 0.16.0 (March 23, 2015)

### Enhancements
- **General Changes**
  - Fixed a minor styling issue with pagination on dataset variable indexes
  - Topic tags are now faded using grayscale instead of opacity to maintain
    contrast between text and tag color
  - Streamlined login system by removing alternate logins
  - Updated the design of the NSRR footer
  - Started work on some parallax-enabled pages for future UI updates
  - Redesigned the interface for the NSRR home page
  - Added a new Contact Us page
  - Updated the user sign in and registration page
  - Removed wget documentation in favor of the NSRR Downloader Ruby Gem
  - Added a map page to show NSRR membership
  - The menu has minor UI improvements to scale better across different device
    sizes
- **Showcase Added**
  - A new showcase section has been added that highlights certain areas of
    interest for researchers
  - Added a page highlighting Shaun Purcell's work on Genetics of Sleep Spindles
  - Added a page that covers the purpose of the NSRR in 5 steps
  - Added a new showcase carousel on the home page aimed at highlighting
    showcase items
  - A new page to showcase new tools and datasets on the NSRR website
  - A new demo page is now available that is aimed at getting new users
    started by:
    - downloading data
    - opening CSV datasets and accessing variable information
    - extracting information from EDFs
- **Challenges Added**
  - The flow limitation challenge is now available
- **Forum Changes**
  - The forum user interface has been updated
  - Forum post anchors now correctly offset based on the top navigation bar
  - Users can now post twice in a row on a forum topic
  - Forum markdown has been improved and examples are provided under the Markup
    tab
    - Blockquotes: `> This is quote`
    - Highlight: `==This is highlighted==`
    - Underline: `_This is underlined_`
    - Superscript: `This is the 2^(nd) time`
    - Strikethrough: `This is ~~removed~~`
- **Dataset Changes**
  - Datasets now highlight the ability to:
    - download the full dataset using the NSRR gem
    - browse all covariates online
  - Dataset documentation pages now scale better with screen resolution to make
    text more legible
  - Dataset owners can now highlight key information about their dataset
  - Improved the dataset API to allow the NSRR gem to download data from private
    datasets for authorized users
- **Tool Changes**
  - Titles no longer overlap tools tags on the tools index
- **Agreement Changes**
  - Step 2 now has additional instructions to clarify the importance of
    describing the "Specific Purpose", and selecting an appropriate number of
    datasets
  - The signature step now allows users to opt out of signing the agreement if
    they have institutional requirements forbidding signing of agreements
  - Users opting for NSRR Committee review now need to attest that they have
    received Human Subjects Protections Training
  - Academic agreements no longer include Indemnification Clause (11 and 12),
    and have been renumbered
- **Email Changes**
  - The password reset email and other emails have had their template updated
- **Administrative Changes**
  - Admins can now set the auto-subscribe forum notifications features for users
  - Location statistics now load more quickly
- **Gem Changes**
  - Updated to rails 4.2.1
  - Updated to pg 0.18.1
  - Updated to contour 3.0.0.beta1
  - Updated to kaminari 0.16.2
  - Updated to jquery-rails 4.0.3
  - Use Haml for new views
  - Use Figaro to centralize application configuration
- Use of Ruby 2.2.1 is now recommended

### Bug Fix
- Fixed a bug that caused too much information to be logged to the log file
- Fixed an issue where friendly redirect on sign out was not redirecting to the
  last page the user was on
- Dataset editors can now properly view private datasets on which they are an
  editor

## 0.15.3 (January 6, 2015)

### Bug Fix
- Fixed a bug that would occasionally occur when resetting a dataset folder
  index on reading an uninitialized folder index

## 0.15.2 (January 6, 2015)

### Enhancements
- **General Changes**
  - Added timestamps to comments
- **Administrative Changes**
  - Reviews can now be ordered by the agreement number

### Bug Fix
- Fixed a bug that prevented a dataset's root file index from being generated

### Refactoring
- Use `.scss` instead of `.css.scss` to stay consistent with Rails
  recommendations

## 0.15.1 (December 30, 2014)

### Enhancements
- Use of Ruby 2.2.0 is now recommended
- **Gem Changes**
  - Updated to rails 4.2.0
  - Updated to contour 2.6.0.rc

## 0.15.0 (December 11, 2014)

### Enhancements
- **General Changes**
  - Enabled turbolinks progress bar on page changes
  - Email notifications now provide a link to the user settings page to allow
    users to update their email preferences
  - Removed UserVoice, users tend to contact the NSRR via the support email
    address and the forum
- **Dataset Changes**
  - Dataset owners and editors can now add users as `editors`, `reviewers`, and
    `viewers`
  - Reviewers can now make comments, approve, and reject DAUAs that request
    access to the dataset the reviewer is on
  - Dataset editors can now sync documentation repositories
- **Agreement Changes**
  - New review process for agreements
    - Reviewers can comment and approve or reject agreements
    - Reviewers are specified per dataset and can only review agreements for
      their dataset
  - Reviewers are sent weekly digests of agreements they have not yet reviewed
  - Reviewers are now notified in place of system admins when a DAUA is
    submitted
  - Reviewers now receive a notification email when another reviewer mentions
    them on an agreement
  - Agreements can now be annotated using specific tags
- **Administrative Changes**
  - Administrators can now see a breakdown of agreements by status and by tag
  - Improved speed at which stats page is displayed for administrators
- Updated Google Omniauth to no longer write to disk
- Use of Ruby 2.1.5 is now recommended
- **Gem Changes**
  - Updated to rails 4.2.0.rc2

## 0.14.2 (November 10, 2014)

### Bug Fixes
- Fixed styling of individual users' download statistics
- Fixed hover effect information on yearly download chart

## 0.14.1 (November 4, 2014)

### Enhancements
- **Administrative Changes**
  - Agreements are now filterable by status
  - Agreements are now numbered to help identify them more quickly
  - Download statistics now available on user pages
  - Yearly download chart added to visually see downloads by month, dataset, and
    user type
- **Dataset Changes**
  - Download audits and page view reports are now indexed to load more quickly

### Bug Fix
- Dataset background tasks are now launched using the proper environment

## 0.14.0 (November 3, 2014)

### Enhancements
- **Agreement Changes**
  - Step 8, Intended Use of Data, now requests more specific information to help
    the NSRR Review Committee
- **Dataset Changes**
  - New dataset releases can now be deployed using the Spout data dictionary
    management gem
    - Spout, https://github.com/nsrr/spout, tests and manages data
      dictionaries and datasets
- **Gem Changes**
  - Updated to rails 4.2.0.beta4
  - Updated to contour 2.6.0.beta8
  - Updated to coffee-rails 4.1.0
  - Updated to redcarpet 3.2.0
  - Updated to simplecov 0.9.1
- Use of Ruby 2.1.4 is now recommended

## 0.13.0 (October 14, 2014)

### Enhancements
- **Administrative Changes**
  - Last submitted at column added to allow better sorting on the administrator
    agreement view
  - Minor layout improvements for reviewing submitted agreements
- **Agreement Changes**
  - PDFs of submitted and approved agreements can now be downloaded and printed
- **Dataset Changes**
  - EDFs can now be previewed in the Altamira online EDF browser
- **Forum Changes**
  - Users can now mention other users by username in forum posts
    - Ex: "Please look at this topic @remomueller."
  - Users can set their forum username under their settings
  - Users get notified by email when they are mentioned in a comment
  - Users can turn off receiving mention emails in their settings
  - Typing '@' will allow users to autocomplete usernames while creating new
    topics and posts
  - Core and AUG members can now add tags to topics
- **Gem Changes**
  - Updated to contour 2.6.0.beta7

### Bug Fixes
- Fixed an issue that caused topics to render markdown comments incorrectly

## 0.12.0 (October 1, 2014)

### Enhancements
- **Administrative Changes**
  - Added an administrative dashboard and a link in the dropdown menu for system
    admins
- **Dataset Changes**
  - Dataset file access is now controlled by the new DAUA process
  - Dataset editors can now refresh download folders through the web interface
- **Gem Changes**
  - Updated to rails 4.2.0.beta2

## 0.11.2 (September 26, 2014)

### Bug Fix
- Fixed a bug that prevented emails from being delivered if the logo file was
  unavailable

## 0.11.1 (September 24, 2014)

### Bug Fix
- Fixed a bug that prevented non-admin users from starting a new DAUA submission
  process

## 0.11.0 (September 23, 2014)

### Enhancements
- **General Changes**
  - Added a new online DAUA application process
    - DAUA can be filled out by an individual or an organization
    - Multiple submissions can be created for different datasets
    - Completed submissions can be printed
    - In progress submissions can be deleted
    - Expired submissions can be renewed
- **Forum Changes**
  - Users are now subscribed to new posts by default
    - Users can opt out of this setting under their user settings.
- Use of Ruby 2.1.3 is now recommended

## 0.10.0 (September 8, 2014)

### Enhancements
- **General Changes**
  - The official NSRR ruby gem is now supported, https://rubygems.org/gems/nsrr
    - More information here: https://github.com/nsrr/nsrr-gem
  - The wget download option has been hidden in favor of the NSRR gem command
    - Add `?wget=1` to the download url bar to see the `wget` download command
      syntax
  - Added UserVoice integration to collect better feedback on the NSRR website
  - Restructured the menu bar to provide more space for page content
- **Dataset Changes**
  - Download folders now provide customizable commands using the NSRR gem
- **Variable Changes**
  - Commonly used variables can now be filtered on the variables index
  - Older variable graphs can now be viewed by passing the older version on the
    show page
    - Ex: https://sleepdata.org/datasets/shhs/variables/rdi3p?v=0.2.0
  - Variables index and show page styling updated to be more consistent with the
    rest of the NSRR website
- **Forum Changes**
  - The forum is now linked in the main menu
- **Gem Changes**
  - Updated to rails 4.2.0.beta1
  - Updated to contour 2.6.0.beta6
  - Updated to simplecov 0.9.0

### Bug Fix
- Fixed a bug authenticating some users via their token

## 0.9.6 (July 14, 2014)

### Enhancements
- **Dataset Changes**
  - Increased the number of datasets to 18 to allow all scheduled datasets to be
    shown on a single page
  - Dataset navigation only shows the variables tab if the dataset has variables
- **Variable Changes**
  - The index page can now be viewed as a list or a grid
- **Gem Changes**
  - Updated to kaminari 0.16.1

### Bug Fix
- Fixed a bug that prevented certain dataset variables from including navigation
  to neighboring variables

## 0.9.5 (July 9, 2014)

### Enhancements
- **Dataset Changes**
  - Highlighted files are now downloaded automatically when a user navigates to
    the downloads section
- **Gem Changes**
  - Removed dependency on ruby-ntlm gem

## 0.9.4 (July 9, 2014)

### Bug Fix
- Fixed an issue that prevented certain file indexes from being generated
  properly

## 0.9.3 (July 8, 2014)

### Enhancements
- **Dataset Changes**
  - Files can be individually marked as publicly available
- **Gem Changes**
  - Updated to rails 4.1.4

## 0.9.2 (June 27, 2014)

### Enhancements
- **Variable Changes**
  - Histograms for graphs now include units on the x-axis where appropriate
- **Gem Changes**
  - Updated to rails 4.1.2

## 0.9.1 (June 25, 2014)

### Enhancements
- **General Changes**
  - Added grant number to the About page
- **Forum Changes**
  - Forum uses identicons for users who do not have a gravatar set
- **Variable Changes**
  - Dataset variables index now displays 100 variables per page, up from 50

## 0.9.0 (June 20, 2014)

### Enhancements

- **Forum Added**
  - Added the ability for registered users to create topics on the forum and to
    post comments on other topics
  - Core and AUG members are now highlighted as such on their forum posts
  - Comments can be previewed before being posted to the forum topic
  - System admins can lock, pin, and delete topics
  - System admins can ban users from posting on the forum
  - Users who have commented on now receive daily forum updates to topics where
    they are subscribed
  - Users may not post consecutive comments on the forum
    - This is currently enabled to discourage topic "bumping", multi-comment
      spam, and to encourage clear dialogue between users
- **Variable Changes**
  - Swiping left or right now navigates to next or previous variable on mobile
    devices
- **Administrative Changes**
  - Syncing dataset and tool documentation is now more robust

### Bug Fix
- Fixed an issue where navigating to dataset downloads for a dataset with no
  files could cause a redirect loop

## 0.8.1 (May 28, 2014)

### Enhancement
- **General Changes**
  - Changed the file organization of variable images and graphs
    - The new file structure now includes the data dictionary version of the
      variable

## 0.8.0 (May 22, 2014)

### Enhancements
- **Dataset Changes**
  - The variable index has been changed for better variable navigation
  - Added the ability to view variables on their own unique pages
  - Each variable now includes interactive charts
- **Gem Changes**
  - Updated to contour 2.5.0
  - Updated to geocoder 1.2.1
- Use of Ruby 2.1.2 is now recommended

## 0.7.3 (May 8, 2014)

### Enhancements
- **Gem Changes**
  - Updated to rails 4.1.1

## 0.7.2 (April 15, 2014)

- **Administrative Changes**
  - Added tables of regular member registrations by country and state for
    reporting purposes

## 0.7.1 (April 14, 2014)

### Enhancements
- **Administrative Changes**
  - Large numbers on statistics page now include commas
- **Dataset Changes**
  - Windows wget commands now include `--no-check-certificate`
- **General Changes**
  - `mailto` links in email now use the same color as `href` links

## 0.7.0 (April 9, 2014)

### Enhancements
- **Administrative Changes**
  - Site admins are notified by email when an admin approves or asks a user to
    resubmit a DAUA
  - Added stats overview for system admins to track signups, DAUA submissions,
    and file downloads
- **Dataset Changes**
  - Dataset owners are notified when users make new dataset file access requests
  - Variable popups are now hidden when the `Esc` key is pressed
  - Users are notified by email when a dataset editor approves their file access
    request
- **General Changes**
  - Updated email styling template
  - Minor wording changes on About page and Home page
  - Documentation pages for tools and datasets now include the filename as part
    of the title
- **Gem Changes**
  - Updated to rails 4.1.0
  - Updated to contour 2.5.0.beta1
  - Removed turn, and replaced with minitest and minitest-reporters

## 0.6.2 (April 2, 2014)

### Enhancements
- Added updated Data Access and Use Agreement PDF

## 0.6.1 (April 1, 2014)

### Bug Fix
- Fixed a typo on about page

## 0.6.0 (March 31, 2014)

### Enhancements
- **Dataset Changes**
  - Dataset editors can now approve user access to datasets based on DAUA
    criteria
  - Removed variable lists to clean up interface
    - Variables are now downloaded via the dataset CSV download from the files
      area
  - Datasets can now specify release dates to allow users to see when those
    datasets will have files available
    - Datasets are highlighted when they have data available for download
  - Dataset file downloads display steps the user needs to take to access the
    file download
- **Tool Changes**
  - Tools can be marked as private to allow editors to work on them while they
    are a work in progress
- **Gem Changes**
  - Updated to rails 4.0.4
  - Updated to carrierwave 0.10.0
  - Updated to redcarpet 3.1.1
  - Updated to turn 0.9.7

## 0.5.0 (March 17, 2014)

### Enhancements
- **Administrative Changes**
  - New sign ups can now be reviewed by system administrators
  - DAUA review process put into place for system administrators
    - Emails are sent to system administrators when DAUAs are submitted
    - Users are notified when their DAUAs are approved or sent back for
      resubmission
    - Executed DAUAs can now be uploaded alongside the originally submitted
      DAUAs
  - Added Academic User Group page and added research summary for AUG Members
  - Documentation for repositories can now be synced by system administrators
- **Dataset Changes**
  - Datasets now display a list of the contributors
  - Variables now reference the forms on which they were collected
- **Tool Changes**
  - Tools now display a list of the contributors
- **Gem Changes**
  - Updated to contour 2.4.0
- Use of Ruby 2.1.1 is now recommended

## 0.4.0 (February 20, 2014)

### Enhancements
- Signing in and signing out on the datasets files page now forwards back to the
  last folder location
  - This was disabled in previous versions so that file downloads wouldn't
    trigger after sign in or sign out
- Datasets header tabs now display `Variables` instead of `Collection` and
  `Search` has been removed
- `Variables` section, formerly the `Collection` now displays a note on why
  certain variables have gold borders
- Added more descriptive links for datasets and tools
- Reduced the size of the header bar on smaller screen sizes
- External links now have different styling from internal links
- Markdown no longer escapes numbers to their ASCII representation re-enabling
  ordered lists in Markdown
- Documentation code fences can now be prettified using `<!--?prettify?-->`
  immediately before the code fence
- **Tool Changes**
  - Tools can now specify one of the following tool types:
    - Matlab
    - R Language
    - Utility
    - Java
    - Web
  - Authors can now be specified for tools
- **Gem Changes**
  - Updated to rails 4.0.3
  - Updated to kaminari 0.15.1
  - Updated to contour 2.4.0.beta3

### Bug Fix
- Fixed a bug preventing dataset owners from viewing dataset access requests
- Fixed `add` button floating lower than intended in some browsers

## 0.3.0 (January 17, 2014)

### Enhancements
- Home page is now more dynamic and includes direct links to available
  documentation, data, and tools
- Covariate datasets can now be requested and downloaded if the user has been
  granted access to the dataset
- Added prototype of data use agreement request process
- **Tool Changes**
  - Tools can now have multiple pages of documentation identical to datasets
  - Tool specific paths can now be referenced:
    - `:pages_path:` => `/tools/SLUG/pages`
    - `:images_path:` => `/tools/SLUG/images`

## 0.2.0 (January 13, 2014)

### Enhancements
- Use of Ruby 2.1.0 is now recommended
- **Gem Changes**
  - Updated to pg 0.17.1
  - Updated to jbuilder 2.0
  - Updated to contour 2.2.1

## 0.1.0 (December 20, 2013)

### Enhancements

- **Dataset Changes**
  - Datasets can be added and shared publicly or privately
  - **Documentation**
    - Dataset editors can create, edit, and update documentation pages
    - Datasets can be documented using markdown or plain text across multiple
      pages
      - `:datasets_path:` and `:tools_path:` can now be referenced in
        documentation
    - Dataset specific paths can now be referenced:
      - `:pages_path:` => `/datasets/SLUG/pages`
      - `:images_path:` => `/datasets/SLUG/images`
      - `:files_path:` => `/datasets/SLUG/files`
    - Dataset documentation is now searchable
    - Dataset documentation page views are now audited
    - Documentation pages can now embed images from the dataset images folder
      - Images can be viewed inline
  - **File Downloads**
    - File downloads are now audited and can be reviewed by dataset creators
    - Users can now request access to file downloads for datasets
    - Dataset editors can approve/deny user file access requests
    - Dataset files are indexed to improve viewing folders with 1,000 or more
      files
  - **Collection**
    - Users can search across multiple datasets that have an associated data
      dictionary
      - [Spout](https://github.com/nsrr/spout) helps format and maintain
        JSON data dictionaries
      - An example data dictionary is the
        [We Care Data Dictionary](https://github.com/nsrr/wecare-data-dictionary)
      - Variables with pre-computed charts now display the chart in the
        collection viewer
      - Users can create lists of variables on the collection viewer
      - Variable charts are loaded when the image is placed in the web browser
        viewport
- **Tool Changes**
  - Tools can be added and documented

## 0.0.0 (October 21, 2013)

- Initial prototype of the National Sleep Research Resource splash page
- Skeleton files to initialize Rails application with testing framework and
  continuous integration
- Added the We Care Dataset to prototype bulk file downloads
- Added Windows and macOS/Linux instructions for installing GNU Wget
