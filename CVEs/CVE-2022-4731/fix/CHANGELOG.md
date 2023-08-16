## 29.1.0

### Enhancements
- **Security Changes**
  - Fixed a Cross Site Scripting (XSS) issue
- **Gem Changes**
  - Update to Ruby 3.1.2

## 29.0.0 (April 19, 2022)

### Enhancements
- **General Changes**
  - Updated landing page based on new design
  - The research page is now fixed with for the public facing view
  - Improved consistency of styling across various public pages
  - Added Fathom Analytics to website and removed Google Analytics
  - Updated internal dashboard pages to match new theme
- **Resource Page Changes**
  - Resources now have a slug that can act as a short link to the resource
  - Resources can now be configured to open in a new tab
- **Gem Changes**
  - Update to ruby 3.0.2

## 28.0.0 (November 7, 2021)

### Enhancements
- **Gem Changes**
  - Update to rails 6.1.4.1

### Bug Fix
- Fixed a bug that prevented posts from posting correctly
- Fixed an issue where mobile safari would play the globe video when clicking
  on the MyApnea menu item
- Fixed a bug that would generate excessive notifications for users who chose to
  be auto-subscribed to new forum posts

## 27.0.0 (October 9, 2021)

### Enhancements
- **Forum Changes**
  - Email notifications for replies to forum topics have been re-added
    - Notifications are only sent to users who have subscribed to the forum
      topic and who have emails enabled
  - Old topics are now auto-locked after 100 years (and not 2 months)

### Bug Fix
- Fixed a bug that prevented a nested reply from being linked
- Fixed a bug that caused remote forms from correctly rendering the response
- Fixed video not playing on landing page

## 26.0.0 (April 18, 2021)

### Enhancements
- **General Changes**
  - Privacy policy now redirects to https://www.massgeneralbrigham.org/notices/web-privacy-policy
  - Added Google Analytics
- **Resource Page Changes**
  - Images on resources now also link to the resource URL

### Bug Fix
- Fixed a bug that prevented new articles from reusing a URL slug from a
  previously deleted article

## 25.0.0 (April 11, 2021)

### Enhancements
- **Admin Changes**
  - Add a Slice Subject ID export task
  - Topics marked as spam are no longer listed on the index for admins
- **General Changes**
  - A new resources page was added that allows admins to create and list
    resources for general visitors of the website
  - Added note to the MyApnea contact page that MyApnea is not affiliated with
    MyAir or Resmed
- **Forum Changes**
  - Existing replies to locked topics now be edited or deleted after the topic
    has been locked
- **Gem Changes**
  - Update to ruby 3.0.1
  - Update to rails 6.1.3.1
  - Update to pg 1.2.3
  - Update to bootstrap 4.4.1
  - Update to carrierwave 2.2.1
  - Update to devise 4.7.3
  - Update to figaro 1.2.0
  - Update to font-awesome-sass 5.12.0
  - Update to haml 5.2.1
  - Update to kaminari 1.2.1
  - Update to pg_search 2.3.2
  - Update to redcarpet 3.5.1
  - Update to rubyzip 2.3.0
  - Update to coffee-rails 5.0
  - Update to jquery-rails 4.3.5

### Bug Fixes
- Dashboard forum activity no longer displays replies to deleted topics

## 24.0.1 (May 28, 2019)

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

## 24.0.0 (May 10, 2019)

### Enhancements
- **Admin Changes**
  - Include "Email confirmed" in user export
- **Gem Changes**
  - Update to ruby 2.6.3
  - Update to devise 4.6.2

## 23.0.0 (April 10, 2019)

### Enhancements
- **Gem Changes**
  - Update to ruby 2.6.2
  - Update to rails 6.0.0.beta3
  - Update to font-awesome-sass 5.8.1

## 22.0.0 (February 26, 2019)

### Enhancements
- **General Changes**
  - Landing page survey count now includes newly completed surveys from Slice
    along with original survey count
  - Tabs now display better on smaller screens
- **Project Changes**
  - A primary project can now be specified that determines the consent displayed
    on the root consent URL
- **Survey Changes**
  - Dashboard now displays next survey question
- **Gem Changes**
  - Update to ruby 2.6.1
  - Update to rails 6.0.0.beta1
  - Update to pg 1.1.4
  - Update to bootstrap 4.3.1
  - Update to carrierwave 1.3.1
  - Update to devise 4.6.1
  - Update to hashids 1.0.5
  - Update to mini_magick 4.9.3
  - Update to pg_search 2.1.4
  - Update to sitemap_generator 6.0.2

## 21.1.0 (January 2, 2019)

### Enhancements
- **General Changes**
  - Contact page support email information moved above support email
- **Gem Changes**
  - Update to ruby 2.6.0
  - Update to rails 5.2.2
  - Update to bootstrap 4.2.1
  - Update to font-awesome-sass 5.6.1

### Bug Fix
- Fixed styling of sunset decoration on admin pages
- Fixed issue caused by replying to restored forum topics that had no slug

## 21.0.0 (November 29, 2018)

### Enhancements
- **Forum Changes**
  - Improved spam filters
- **Survey Changes**
  - Improved process of leaving and rejoining a research study
  - Overview reports and project consents are now generated in temporary
    folders that are deleted after generating the PDFs
- **Gem Changes**
  - Update to rails 5.2.1.1
  - Update to font-awesome-sass 5.5.0

### Bug Fix
- Fixed missing gradient on landing page "Community Power" section

## 20.0.0 (November 5, 2018)

### Enhancements
- **Survey Changes**
  - Improve survey stability and submission of responses to Slice API

## 19.0.0 (October 25, 2018)

### Enhancements
- **General Changes**
  - Add updated Privacy Policy
  - Clarify purpose of support email on contact page
- **Forum Changes**
  - Locked topics now provide information on why they are locked
  - Forum badges are now more visible
  - Original Poster added as a more prominent forum badge
  - Add customizable forum badges
    - Examples: Support Team, Researcher, and Sleep Professional
  - Generic "Secret Man" forum placeholder photo replaced with more
    gender-neutral user icon
- **Gem Changes**
  - Update to ruby 2.5.3
  - Update to font-awesome-sass 5.4.1

### Refactoring
- Remove legacy JavaScript polyfills and code

### Bug Fix
- Fixed "Write a Reply" redirection on topics with interleaved deleted replies

## 18.1.0 (October 10, 2018)

### Enhancements
- **General Changes**
  - External research projects can be added to research page
- **Admin Changes**
  - Paginated results are no longer counted in admin report for Help Center
    searches
- **Gem Changes**
  - Update to rails 5.2.1
  - Update to pg 1.1.3
  - Update to bootstrap 4.1.3
  - Update to carrierwave 1.2.3
  - Update to devise 4.5.0
  - Update to jquery-rails 4.3.3
  - Update to mini_magick 4.9.2
  - Update to rubyzip 1.2.2
  - Update to sitemap_generator 6.0.1

## 18.0.1 (September 4, 2018)

### Bug Fix
- Admin exports now complete successfully

## 18.0.0 (August 7, 2018)

### Enhancements
- **General Changes**
  - Added tagline to forum and research pages to better describe the website for
    users visiting from search engine results
  - Updated the team page
  - Added terms and conditions and privacy policy links to footer
  - Added cookie notification to footer
  - Updated framework files to resemble Rails 5.2 defaults more closely
- **Admin Changes**
  - Team members can now be reordered using drag-and-drop
  - Updated exports interface
  - Improved navigation between admin pages
  - Added admin report for Help Center searches
- **Blog Changes**
  - Simplified navigation between live blog and unpublished (draft) articles
  - Blogs can now have a cover image
- **Dashboard Changes**
  - Added navigational tabs to dashboard to simplify navigating between
    dashboard, profile, research, and the forum
- **Email Changes**
  - Account confirmation and password reset emails now address the recipient by
    first name if present instead of using username to reduce confusion for
    users revisiting the site after several years
- **Export Changes**
  - Simplified user export
- **Forum Changes**
  - Added a prominent forum search to the forum index
  - Topics now auto-lock after two months of inactivity
  - Improved bolding and italicizing selected text
- **Search Changes**
  - Member profile previews are displayed when searching usernames
  - Added customized search results
- **Survey Changes**
  - Updated events and designs to match new Slice survey API format
  - Consent flow has been improved for logged out new and existing users
- **Report Changes**
  - Reports now correctly render markdown in graph titles
  - Graphs that display time of day that default to PM are now shifted by 12
    hours to better display responses over the midnight time range

### Testing
- Removed dependency on rails-controller-testing gem
- Added tests to check friendly forwarding

## 17.5.2 (June 12, 2018)

### Bug Fix
- Fixed an issue sorting forum topics by number of replies

## 17.5.1 (May 18, 2018)

### Bug Fix
- Fixed an issue downloading consent PDFs and survey overview report PDFs

## 17.5.0 (May 15, 2018)

### Enhancements
- **Survey Changes**
  - Survey questions can now include markup for underline, italic, highlight, as
    well as bold

## 17.4.0 (May 9, 2018)

### Enhancements
- **Admin Changes**
  - Admins can now review profile changes
- **Survey Changes**
  - Survey completeness is no longer affected by changes to survey event and
    design slug changes

## 17.3.0 (May 3, 2018)

### Enhancements
- **General Changes**
  - Removed Google Analytics
  - Improved password autocomplete
- **Forum Changes**
  - Improved spam filters
- **Gem Changes**
  - Updated to bootstrap 4.1.1

## 17.2.0 (April 19, 2018)

### Enhancments
- **Survey Changes**
  - Updated text on the MyApnea core survey report
- **Gem Changes**
  - Updated to ruby 2.5.1
  - Updated to rails 5.2.0
  - Updated to bootstrap 4.1.0
  - Updated to devise 4.4.3
  - Updated to simplecov 0.16.1
  - Updated to capybara 3.0

### Bug Fix
- Fixed a bug that prevented study overview report from loading when referencing
  a subject that no longer existed

## 17.1.0 (March 14, 2018)

### Enhancements
- **Admin Changes**
  - Added an admin spam report
  - Admins can flag accounts as spam from users index
- **Email Changes**
  - Email confirmation is now required for all accounts
  - Added detection of disposable email addresses
- **Survey Changes**
  - Updated "Insomnia Scale" text on summary report

## 17.0.0 (March 8, 2018)

### Enhancements
- **General Changes**
  - Updated styling of Terms of Access and Terms and Conditions
- **Admin Changes**
  - Admins can now perma-delete replies on blog posts and forum topics
- **Blog Changes**
  - Reduced display of deleted replies without replies of their own on the blog
- **Email Changes**
  - Started testing conditional email confirmation requirements
  - Changing account email now requires reconfirming the change via email
- **Forum Changes**
  - Forum posts now show link text instead of URL in preview on dashboard
- **Survey Changes**
  - Added the MyApnea core survey report
  - Updated interface to account for surveys that were completed with skipped
    questions
  - Users can now review their survey responses before advancing to the next
    survey
- **Gem Changes**
  - Updated to rails 5.2.0.rc1
  - Updated to pg 1.0.0

### Refactoring
- Removed deprecated survey module
- Removed deprecated broadcast comments

### Bug Fix
- Fixed a bug that prevented blog post and forum topic previews and image
  uploads from working correctly

## 16.1.2 (February 13, 2018)

### Bug Fix
- Fixed error loading notifications when an associated topic had been deleted

## 16.1.1 (January 31, 2018)

### Bug Fix
- Fixed `sitemap.xml.gz` file not being accessible

## 16.1.0 (January 30, 2018)

### Enhancements
- **Dashboard Changes**
  - Decreased size of user profile pictgure on the dashboard on mobile
- **Forum Changes**
  - Users can disable being auto-subscribed after replying to forum topics in
    their settings
  - Improved contrast of usernames on forum index
  - Replies now link the bio for users that have completed a public profile
  - Improved loading speed of the forum index
  - Adjusted spacing of "Back to Forum" button on mobile
- **Research Changes**
  - Minor update to "surveys being updated" text

### Bug Fix
- Education articles are now correctly listed in the Help Center

## 16.0.0 (January 29, 2018)

### Enhancements
- **General Changes**
  - Improved the user interface to better focus on the forum, research studies
    and surveys, and articles/blog
  - The landing page has been simplified to create a better starting point for
    future updates
  - The help center has been added which integrates site FAQs and site-wide
    search
  - Registration and signing in now makes better use of friendly forwarding
  - The dashboard now shows user replies from the blog and from the forum
  - The research page has been redesigned to support multiple research studies
    and also highlights recent research articles
- **Profile Changes**
  - Updated design of member profiles
  - A user's "Top" and "Recent" topics are now listed on the member profile
  - A user can provide a optional bio and location that displays on the member
    profile
  - Simplified the registration process
    - Users are now asked to provide their own username as opposed to an
      automatically generated one
    - Users are no longer asked for age and full name on registration, this
      has been moved to the research portal of the site when a user goes through
      the consent process for a research study
    - This allows users to more easily sign up and get started on the forum
- **Admin Changes**
  - Major revamp of the administrative interface
  - Admin image pages list replies and broadcasts that reference the image
  - Added a report manager role
  - Admins can manage projects that link to Slice research studies
- **Blog Changes**
  - Added content manager role to streamline creation of articles for
    "Education", "FAQs", and other static pages
  - FAQs now exist as blog pages, however the "FAQs" category does not show up
    on the blog roll, and is styled differently than regular blog posts
  - FAQs can be rated and ranked in terms of helpfulness
  - FAQs will also show up in search results on the help center
- **Forum Changes**
  - Adjusted how the forum index displays on mobile devices
  - Forum usernames are now set at sign up by the user, however a pre-generated
    username is provided as an example to the user
  - Improved the display of images in posts on the forum
  - Users can now subscribe to forum topics
    - Creating a new topic automatically subscribes the author to the topic
    - Replying to a topic automatically subscribes a user to the topic, unless
      the user has previously unsubscribed from the topic
    - In-app notifications are sent to topic subscribers
  - Users can now auto-subscribe to new forum topics in their settings
- **Projects Added**
  - A project represents a research study in MyApnea, and provides a link to
    the associated Slice project surveys and database records
  - Sets of surveys are now grouped by project, the initial set of surveys on
    MyApnea are going into the MyApnea Core project
  - Users now have to go through the consent process for each research study
    they wish to join
- **Survey Changes**
  - Surveys now leverage the Slice API
  - The internal survey data model for MyApnea has been removed and replaced
    with the more robust Slice data model
  - The Slice API provides study event timelines, conditional surveys, survey
    completion, branching logic, data validation, and a number of other
    features: https://tryslice.io/docs
- **Gem Changes**
  - Updated to ruby 2.5.0
  - Updated to rails 5.2.0.beta2
  - Updated to bootstrap 4.0.0
  - Updated to carrierwave 1.2.2
  - Updated to devise 4.4.1
  - Updated to hashids 1.0.4
  - Updated to pg_search 2.1.2

### Tests
- Added tests to assure user passwords can be reset
- Added system tests to track changes to user interface

### Bug Fix
- Welcome email should no longer be sent twice

### Refactoring
- Removed typeahead library
- Combined blog post replies and forum topic replies into one model
- Renamed internal forum chapters to topics

## 15.1.2 (December 18, 2017)

### Bug Fix
- Added missing page title to HTML head

## 15.1.1 (December 15, 2017)

### Enhancements
- **General Changes**
  - Minor changes to wording on about page

## 15.1.0 (December 15, 2017)

### Enhancements
- **Forum Changes**
  - Improved management of spam accounts
- **Gem Changes**
  - Updated to ruby 2.4.3
  - Updated `Gemfile` to `gems.rb`
  - Updated to rails 5.1.4
  - Updated to carrierwave 1.2.1
  - Updated to devise 4.3.0
  - Updated to haml 5.0.4
  - Updated to jquery-rails 4.3.1
  - Updated to kaminari 1.1.1
  - Updated to pg_search 2.1.1
  - Updated to rubyzip 1.2.1
  - Updated to sitemap_generator 6.0.0
  - Updated to simplecov 0.15.1

## 15.0.5 (November 27, 2017)

### Enhancements
- **Forum Changes**
  - Improvements to spam detection

## 15.0.4 (November 16, 2017)

### Enhancements
- **Admin Changes**
  - Admins can now empty spam from the spam inbox
- **Forum Changes**
  - Improved spam detection heuristics
  - Spammers no longer affect topic view counts
- **Gem Changes**
  - Updated to pg 0.21.0

## 15.0.3 (May 11, 2017)

### Enhancements
- **General Changes**
  - Login cookies are now cross subdomain and work between www and non-www URLs
  - Changed human verification from "Invisible" to "I'm not a robot" ReCAPTCHA
- **Gem Changes**
  - Updated to Ruby 2.4.1
  - Updated to rails 5.0.2
  - Updated to pg 0.20.0
  - Updated to carrierwave 1.1.0
  - Updated to haml 5.0.1
  - Updated to simplecov 0.14.1
  - Updated to sitemap_generator 5.3.1

## 15.0.2 (January 23, 2017)

### Enhancements
- **Admin Changes**
  - Improved user management for admins on user index

## 15.0.1 (January 23, 2017)

### Enhancements
- **General Changes**
  - Added an invisible reCAPTCHA to sign up page
  - Contact Us link is now visible in footer on mobile devices
- **Blog Changes**
  - Improved language when creating and publishing blog posts
- **Forum Changes**
  - Badges for engaged forum users have been added:
    - Sleep Commentator: Over 100 posts
    - Sleep Enthusiast: Over 500 posts
    - Sleep Innovater: Over 1,000 posts
    - Sleep Patron: Over 2,000 posts
    - Sleep Champion: Over 5,000 posts
- **Profile Changes**
  - Improved photo caching to correctly update photo on settings page when using
    drag-and-drop
- **Gem Changes**
  - Added autoprefixer-rails
  - Updated to kaminari 1.0.1
  - Updated to hashids 1.0.3

### Bug Fix
- Fixed maps not displaying on community page
- Fixed a bug that incorrectly loaded some pages at a lower scroll position

## 15.0.0 (January 9, 2017)

### Enhancements
- **Gem Changes**
  - Updated to Ruby 2.4.0
  - Updated to rails 5.0.1
  - Updated to carrierwave 1.0.0
  - Updated to jquery-rails 4.2.2
  - Updated to redcarpet 3.4.0
  - Updated to pg_search 2.0.1
  - Updated to jquery-ui-rails 6.0.1

## 14.2.5 (November 30, 2016)

### Enhancements
- **Gem Changes**
  - Dropped support for Ruby 2.2
  - Updated to Ruby 2.3.3
  - Updated to pg 0.19.0
  - Updated to jquery-rails 4.2.1
  - Updated to sitemap_generator 5.2.0

### Bug Fix
- Fixed a bug that prevented "Skip this question" from being selected when
  filling out About Me survey during the sign up process

## 14.2.4 (September 6, 2016)

### Bug Fix
- Fixed an issue clicking "I acknowledge these changes" after privacy policy and
  consent updates that would make the button appear unresponsive

## 14.2.3 (August 17, 2016)

### Bug Fix
- The admin user index now displays the correct total number of users

## 14.2.2 (August 16, 2016)

### Enhancements
- **Admin Changes**
  - Linking exports is now more consistent

## 14.2.1 (August 16, 2016)

### Enhancements
- **Admin Changes**
  - Change default sort direction to descending for user reply and sign in count

## 14.2.0 (August 16, 2016)

### Enhancements
- **Admin Changes**
  - Added another column to user export
  - Added sortable columns to users index

## 14.1.0 (August 16, 2016)

### Enhancements
- **Admin Changes**
  - Improved information provided in user exports

## 14.0.1 (August 12, 2016)

### Enhancements
- **Gem Changes**
  - Updated to rails 5.0.0.1

### Bug Fix
- Mobile devices no longer need to click twice to vote
- Rails model errors are now again correctly styled using Bootstrap CSS classes

## 14.0.0 (August 9, 2016)

### Enhancements
- **General Changes**
  - Updated styling across several pages to be more consistent
  - Simplified styling on consent and terms and condition pages
  - Users can now permanently delete their account from the settings page
- **Admin Changes**
  - Added a link to Admin Dashboard in mobile dropdown menu
  - Improved forum post management
  - Removed extraneous icon from CSV export button
- **Blog Changes**
  - Rich text editor buttons have been added to the blog editor
- **Forum Changes**
  - Upvote and downvote buttons have changed and also provide a link to learn
    how to vote
- **Gem Changes**
  - Updated to rails 5.0.0
  - Updated to coffee-rails 4.2
  - Updated to jbuilder 2.5
  - Updated to jquery-rails 4.1.1
  - Updated to turbolinks 5
  - Added rails-controller-testing

### Refactoring
- **General Cleanup**
  - Rank the Research functionality has been removed
    - Accepted research articles are available on the blog, and suggested research
      topics can now be discussed on the forum
  - Removed unused enagements functionality
  - Removed old lottery code
  - All emails are now sent in a background process
- **Configuration Cleanup**
  - Simplified configuration options
  - Updated configuration of google analytics file
  - Cleaned up existing migration files
- **Forum Cleanup**
  - Removed old forum backend files
  - Removed forum name autocomplete as users are now emailed if they are
    subscribed to a topic instead of sending emails for mentions
- **Registration Cleanup**
  - Started work on simplifying registration process
  - Removed unused user contact information
- **Survey Cleanup**
  - Removed deprecated `display_type` column from questions
  - Removed deprecated `data_type column` from answer_templates
  - Removed deprecated `allow_multiple column` from answer_templates
  - Removed Groupable concern as it is no longer need with PG 9.5
  - Cleaned up unused survey JavaScript

### Bug Fix
- Fixed a bug that allowed users to be assigned the same survey multiple times
  after switching roles
- Child age range restriction now matches age listed in consent
- Fixed a bug sorting replies on a member's profile page

## 13.2.1 (July 26, 2016)

### Bug Fix
- Fixed an issue rendering an incorrect layout on the dashbaord

## 13.2.0 (July 26, 2016)

### Enhancements
- **Survey Changes**
  - The survey interface has been slightly adjusted to better match other pages
    across the website
  - The child survey interface dashboard has been improved
- **Forum Changes**
  - Users are now redirected back to the forum if signing in after visiting a
    forum page
- **Gem Changes**
  - Updated to devise 4.2.0
  - Updated to simplecov 0.12.0
  - Updated to colorize 0.8.1

## 13.1.0 (July 20, 2016)

### Enhancements
- **Blog Changes**
  - Added menu item to allow community contributors to view blog drafts
- **Forum Changes**
  - Made changes to reduce the overall number of spam posts

## 13.0.3 (July 1, 2016)

### Bug Fix
- Restricted version of Turbolinks to 2.5.3

## 13.0.2 (June 24, 2016)

### Enhancements
- **Forum Changes**
  - Replies can no longer be nested under the first comment in a topic
  - The Rich Text Editor "Link" button now provides better details on formatting
    a link with corresponding link text
  - Added a unique index to chapter slugs
  - Posts on member profile page are now sorted chronologically
  - Posts on deleted topics are no longer counted towards a member's total post
    count
  - Deleted users posts no longer appear on the dashboard
  - Forum topic views is now displayed using abbreviations for large numbers

### Refactoring
- Simplified group by clause for replies and topics for newer PG 9.5

### Bug Fixes
- The site now properly redirects to the forum index when a deleted reply is
  visited

## 13.0.1 (June 6, 2016)

### Bug Fixes
- Fixed location of sitemap reported to Bing and Google
- Fixed an issue loading forum caused by missing group by clause required by PG
- Fixed an issue loading topic replies and blog comments for admins

## 13.0.0 (June 6, 2016)

### Enhancements
- **Admin Changes**
  - Admins can now review blog comments and forum replies and sort by points and
    creation time
- **Forum Changes**
  - The forums have been simplified into a single forum
  - Users can jump to the last post on a topic from the forum index
  - Forum post and reply interface has been updated
  - Search now also includes forum topics and replies
  - Topics now track how far a user has read, and will display and link to new
    unread replies to the topic from the forum index
  - Rich text editing buttons added to forum replies and blog comments
- **General Changes**
  - Simplified user interface and styling across site
  - Site-wide search is now available in the top navigation bar
  - Removed integration with UserVoice in favor of our support email address
  - Improved generation of the sitemap
- **Menu Changes**
  - The menu has been slimmed down to take up less space
- **Notifications Added**
  - In-app notifications are now generated when someone comments on a blog post
    or replies to another user's comment
- **Research Topic Changes**
  - Reduced emphasis across site on submitting research topics
  - Accepted research topics now redirect to the associated blog post
- **Gem Changes**
  - Updated to kaminari 0.17.0
  - Removed better_errors

## 12.0.0 (May 12, 2016)

### Enhancements
- **Admin Changes**
  - Removed highlights as these have been replaced by the community-driven blog
  - Added a new page that allows admins to review all blog comments
- **Blog Changes**
  - Improved how blog posts are displayed on the blog index
  - Improved URL structure for filtering blog posts by category and by author
  - Added better HTML page titles for blog index and blog posts
  - A count of total blog posts, and blog posts by category is now displayed in
    the blog menu
  - The blog is now searchable using full text search
  - Links to blog posts now also display the first image present from the blog
    post if one exists
- **Landing Page Changes**
  - Blog posts are now viewable on the landing page
- **Research Topics**
  - Removed requirement to do a research tutorial before casting votes
- **Survey Changes**
  - Detailed survey report now correctly sorts answer options by position
  - Minor UI fix to speech bubbles on detailed survey report
- **Gem Changes**
  - Updated to Ruby 2.3.1
  - Updated to devise 4.1.0

### Bug Fixes
- Fixed a bug that prevented the Submit Survey button from responding for
  certain browsers
- Provider infinite scroll no longer activates on every page

## 11.0.0 (April 29, 2016)

### Enhancements
- **Blog Added**
  - A new blog has been added to allow community contributors and researchers to
    discuss sleep apnea and other topics related to MyApnea.Org
  - Blog posts can be filtered by category
  - Blog posts have a discussion section that is sorted by best or new comments
  - Comments on blog posts can be up- and down-voted
  - Comments can be ordered by highest ranked or newest

## 10.4.0 (April 25, 2016)

### Enhancements
- **Admin Changes**
  - Survey completion totals no longer count users excluded from exports and
    reports
- **Survey Builder Changes**
  - Improved survey builder user interface
  - Question slugs are now generated automatically
  - Answer template names must now follow variable naming conventions


### Refactoring
- Cleaned up how survey questions, answer templates, and answer options are
  ordered on surveys

### Bug Fix
- Fixed a bug computing the percentage "Percentage of Members who think they
  have sleep apnea for over 2 years:"
- Fixed a bug that prevented cancel button from working when adding a survey
  editor
- Fixed a bug that displayed duplicate surveys to survey builders

## 10.3.0 (April 21, 2016)

### Enhancements
- **Email Changes**
  - Unlock instructions are no longer sent to deleted accounts
- **General Changes**
  - Improved the styling for the footer to make it stick to the bottom of the
    page
- **Survey Builder Changes**
  - Survey builders can now invite other builders to view and build surveys
    together
  - Survey questions, answer templates, and answer options can now be reordered
    using drag and drop in the survey builder
  - Survey builders can now preview surveys as they are being built
- **Gem Changes**
  - Updated to rails 4.2.6
  - Updated to carrierwave 0.11.0
  - Updated to devise 4.0.0

### Bug Fix
- Fixed a bug that prevented My Sleep Apnea custom report from generating
  correct percentage for "Percentage of Members who think they have sleep apnea
  for over 2 years:"

## 10.2.0 (March 15, 2016)

### Enhancements
- **Map Changes**
  - Map location is now pulled using a local database lookup
- **Gem Changes**
  - Added maxminddb gem

## 10.1.1 (March 3, 2016)

### Enhancements
- **Email Changes**
  - Improved the responsiveness and display of emails on smaller devices
- **Gem Changes**
  - Updated to rails 4.2.5.2
  - Updated to simplecov 0.11.2

## 10.1.0 (February 29, 2016)

### Enhancements
- **Dashboard Changes**
  - Renamed "Research Highlights" to "Highlights"
- **Learn Page Changes**
  - Added Complex Sleep Apnea to the learn page
  - Added better navigation between learn pages
  - Updated styling of PAP device pages
- **Password Reset Changes**
  - Password reset emails are no longer sent to deleted users
  - Provided better message if the email address was not found
- **Survey Changes**
  - Updated pediatric consent language
- **Research Article Changes**
  - Research articles can now be edited to not include a link to a research topic
- **About Page Changes**
  - Updated to indicate the international scope of the research project
- **Gem Changes**
  - Updated rubyzip to 1.2.0

### Bug Fixes
- Fixed a bug that prevented answering questions if a previous question with the same name had been deleted

## 10.0.1 (January 26, 2016)

### Enhancements
- **Co-enrollment Changes**
  - Updated styling on "Welcome Health eHeart Members" page

## 10.0.0 (January 26, 2016)

### Enhancements
- **Co-enrollment Changes**
  - MyApnea.Org members can now coenroll with Health eHeart and vice versa
- **Research Article Changes**
  - Admins can now associate a research article to any proposed research topic
  - Admins can now set SEO keywords for research articles
- **Gem Changes**
  - Updated to rails 4.2.5.1

### Refactoring
- Cleaned up references to page partials

## 9.2.3 (January 13, 2016)

### Bug Fix
- Fixed a bug that allowed users to set their usernames to existing usernames by changing the letter casing

## 9.2.2 (January 11, 2016)

### Enhancements
- **Marketing Materials**
  - Added a marketing materials page with a list of flyers and cards in English and Spanish

## 9.2.1 (January 6, 2016)

### Enhancements
- **Survey Changes**
  - New "CPAP Adherence" survey added
  - "My Risk Profile" survey has been updated
  - "My Sleep Apnea" survey has been updated
  - "My Sleep Apnea Treatment" survey has been updated
  - Changed report for sleep apnea symptom stats to match new answer options
- **Gem Changes**
  - Updated to Ruby 2.3.0
  - Updated to web-console 3.0

## 9.2.0 (December 17, 2015)

### Enhancements
- **Admin Changes**
  - Added ability to flag industry sponsored clinical trials from admin interface
  - Added order option for clinical trials
  - Added interface to create new research articles
    - Added ability to link approved research topic to research article
  - Improved dynamic social media content creation
- **Survey Changes**
  - Fixed bug where dropdowns were showing two arrow options
  - Fixed bug that only allowed surveys with <10 questions
  - Birthdays can be listed as after 1997 to account for children
  - Improved layout of radio style questions
  - Added flow for completing multiple pediatric surveys
  - Pediatric surveys can now be assigned specifically to diagnosed children
  - Minor wording changes to custom report for sleep apnea symptoms
  - **Builder Changes**
    - Added ability to add/edit survey description
    - Added ability to archive questions
- **Provider Changes**
  - Updated and simplified loading of provider index
  - Added membership count and callout to dashboard
- **Other Changes**
  - Added PEP corner
  - Improved display and usability of risk assessment tool
  - Added address to user settings

### Refactoring
- Cleaned out unused javascript files
- Removed deprecated research topic columns

## 9.1.0 (December 7, 2015)

### Enhancements
- **Forum Changes**
  - Improved layout of forum index
    - Mobile view includes more information
    - View count is visible to all members
    - Recent activity is included
  - Reintroduced easy moderation links for forum posts
- **Research Changes**
  - Reintroduced links to propose new research topics and contextual overview to research topic index
  - Included more information about clinical trials
    - Added industry sponsor flag
  - Increase number of highlights displayed on dashboard and research index
    - Added quick link to view all
- **Admin Changes**
  - Improved usability of admin content organization
    - Can more simply reorder team member list
    - Able to delete team members from admin dashboard
  - Added provider column to data export
- **Survey Changes**
  - Added ability to archive answer templates in surveys
- **SEO Improvements**
  - Included expansive sitemap in txt/xml formats
- **Gem Changes**
  - Started testing Ruby 2.3.0-preview1
  - Updated to simplecov 0.11.1

## 9.0.1 (November 23, 2015)

### Bug Fixes
- Fixed dashboard to welcome current user by correct name
- Fixed forum show page to highlight popular topics

## 9.0.0 (November 23, 2015)

### Enhancements
- **Design Changes**
  - Switched to fixed-width version of site
  - Minor updates to landing page
- **Engagment Changes**
  - More dynamic widgets located on the dashboard
    - Update social profile field
    - Vote on new research topics
  - Module added to ask free response questions to different user groups
    - For example, ask diagnosed patients about their diagnostic experience
  - Users are able to like posts
  - Users are able to request expert advice for posts
  - Users are able to comment on individual posts
- **Provider Changes**
  - Provider dashboard shows posts sorted by advice requests
  - Fixed issue with possible redirect loop during consent procedure
- **Research Changes**
  - Research topic restructuring for enhanced usability
- **Admin Changes**
  - Replaced engagement report with reaction heatmap
  - Heatmaps added to individual engagements
  - Moderators receive alert emails for new forum replies
- **Other**
  - Added Groupable concern for compatibility with PostgreSQL 8.3
  - Added list of clinical trials
  - Improved purpose and navigation between invites

### Refactoring
- Refactored stylesheets to make better use of SCSS variables
- Simplified forum views and actions

## 8.5.0 (November 18, 2015)

### Enhancement
- **Admin Changes**
  - Updated the location report to provide a summary of the previous week's signups
- **Gem Changes**
  - Updated to rails 4.2.5
  - Updated to pg 0.18.4

## 8.4.1 (October 22, 2015)

### Bug Fixes
- Removed deprecated calls to `AnswerValue.current` that caused the 'My Sleep Pattern' report to error

## 8.4.0 (October 19, 2015)

### Enhancements
- **Admin Changes**
  - Added a data dictionary and data export task for admins
- **General Changes**
  - Improved display of uploaded user, highlight, partner, and team member photos
  - Members page now redirects to forums

## 8.3.0 (October 13, 2015)

### Enhancements
- **General Changes**
  - Fixed some minor typos across the website
  - Added article on sleep apnea and atrial fibrillation
- **Admin Changes**
  - Team members can be modified from the admin dashboard
  - Partners can be modified from the admin dashboard
  - Clinical trials can be modified from the admin dashboard
- **Gem Changes**
  - Added better_errors

## 8.2.2 (September 28, 2015)

### Enhancements
- **General Changes**
  - Updated professional titles on research pages
  - Corrected style of login button to match style used in 8.2.0
- **Research Highlight Changes**
  - Improved visibility of accepted research topics link on main Research page
  - Fixed layout of multiple research highlights on research index
  - Accepted research highlights are now arranged with the most recent at the top

## 8.2.1 (September 23, 2015)

### Enhancements
- **Forum Changes**
  - Temporarily disabled automatic forum reply emails
- **General Changes**
  - Updated and added Forgot my Email and Login links
- **Gem Changes**
  - Removed minitest-reporters

## 8.2.0 (September 15, 2015)

### Enhancements
- **General Changes**
  - Added PEP Chair to Steering Committee list
  - Temporarily removed advisory council
  - Added UW to Partners page
- **Gem Changes**
  - Updated to pg 0.18.3

## 8.1.0 (September 11, 2015)

### Enhancements
- **General Changes**
  - Minor spelling fixes
  - Added a Code Climate configuration file
  - Removed lottery language
  - Fixed changing color of navigation bar
  - Updated partnerships and relationships
  - Added hypoglossal nerve stimulation research highlight
  - Added women comorbidity (sleep apnea/heart disease) research highlight
- **Gem Changes**
  - Updated to rails 4.2.4
  - Set minitest-reporters to use '~> 1.0.20'

### Refactoring
- Removed helper methods used to transition answer_template to newer format
- Removed deleted columns from answers and answer_values
- Removed dependency on report view
- Improved test coverage for surveys controller
- Improved test coverage for account controller
- Improved test coverage for invites controller
- Improved test coverage for research topics controller
- Removed deprecated JavaScript and associated views

### API Development
- Surveys
  - Now limit survey show response to questions that haven't been answered
  - Survey description loading from database
- Old, test version of API removed

## 8.0.0 (August 31, 2015)

### Enhancements
- **Survey Changes**
  - Longitudinal surveys can be launched and assigned to users, ex:
    - `s = Survey.find_by(slug: 'about-me')`
    - `s.launch_single(user, '6month')`
  - Followup surveys are launched automatically based on encounter conditions
    - Email are sent to alert users of new available surveys
  - Pediatric survey functionality has been added
    - Caregivers of children can fill out surveys about their children
    - Baseline surveys are immediately assigned when a child in the proper age range is added by a caregiver of a child
    - Data export excludes pediatric surveys
  - Reports are now additionally scoped by encounter (ex: baseline, followup)
  - Reports that do not have custom reports now redirect to the detailed report view
  - Minor UI updates to completion display
- **Online Survey Builder**
  - Started work on an online survey builder for researchers and team members
  - The online survey builder will let survey builders specify the following:
    - The target audience (ex: diagnosed, at risk)
    - The age range for pediatric surveys (ex: 2..8, 4..10)
    - The number of encounters and spacing between surveys (ex: baseline, 6 month followup)
  - Questions can be added to surveys in online builder
    - Question text and slug can be modified
    - Answer Templates can be added to questions
    - Answer Options can be added to answer templates
    - Question `display_type` and AnswerTemplate `data_type` and `allow_multiple` are no longer used to determine how to display questions on surveys
    - AnswerTemplates now allow a `template_name` to be specified of the following types:
      - "date", "radio", "checkbox", "string", "height", "number"
    - Conditional AnswerTemplates now require a valid parent AnswerTemplate as well as a valid value
  - Encounters can be added to surveys in online builder
    - An encounter specifies when the survey is launched, in days after sign up
- **Onboarding Changes**
  - Reduced consent and privacy into one step in the process
  - Removed progress indicators (due to shorter process) and switched to simple layout (without sidebar)
- **General Changes**
  - Minor updates to layout of forums, specifically in the headers
  - Minor updates to layout of highlights to keep consistency with other internal links
  - Account settings layout updated with improved, less intrusive navigation
  - Terms and conditions forums displayed in scroll container
- **Admin Changes**
  - Administrators can now unlock surveys for users from the user show page
  - Updated the Version Stats report to be monthly, and renamed to it to the Timeline Report
  - Admins receive survey followup digest emails when new surveys are assigned to users
  - All admin pages now use fullscreen layout
- **Gem Changes**
  - Updated to Ruby 2.2.3
  - Only run `web-console` in development mode

### API Development
- **API**
  - Users
    - Added capability to create new users and login existing users via JSON request
      - Cookies are passed back to allow use of devise user authentication
      - Home added to test for current session
    - Photo URL passed for created posts and research topics, if available
    - Allows capturing of information during onboarding process
      - Includes consent, user_type, and basic demographic information
  - Research Topics
    - Added capability to create new research topics as user
    - Added capability to cast vote as user
    - Index page lists all posts
  - Votes
    - Added votes, scopable by relevant fields and ratings
  - Forums
    - Added capability to get viewable topic index, ordered by activity, and topic show data
    - Added capability to create new forum topics
    - Added capability to create new posts
  - Surveys
    - Added getter for user's answer sessions
    - Added survey show page to allow dynamic building on app side
    - Survey submission will automatically lock surveys if complete

### Bug Fix
- Fixed and simplified date input parsing to better handle consistency issues across browsers and devices
- Surveys are now correctly reassigned when a user changes their user type
  - Unstarted surveys that are no longer applicable are discarded, and started surveys are kept
- Fixed a bug that could cause users to have above 100% completion on surveys
- Fixed a bug where new users weren't always marked as ready for research due to consent update dates
- Flash notices are now being rendered on every page, to ensure they appear at the proper time
- Fixed an issue displaying pie charts for My Quality of Life survey report

### Refactoring
- Simplified processing single answers for surveys
- Reduced JavaScript footprint by removing unused JavaScript files
- Overwriting views are no longer stored in myapnea subfolder
- Restructured tests for surveys and improved overall test speed
- Refactored Survey class in favor of an AnswerSession-centric model
- Removed unused methods from the User model
- Removed unused views and partials
- Health Conditions report no longer relies on extra server JSON request
- Removed old registration views and methods
- Removed unused attributes from AnswerSession model
- Removed unused attributes from Survey model

## 7.5.1 (August 20, 2015)

### Enhancements
- Changed the copy for the join message on the landing page

## 7.5.0 (August 14, 2015)

### Enhancements
- **Team Page Changes**
  - Added additional bios and images to team page
- **Research Page**
  - Updated layout to allow for more space
  - Added current clinical studies
  - Added display of all highlights listed as research topics

### Bug Fix
- Removed deprecated configuration option previously used by devise

## 7.4.0 (August 7, 2015)

### Enhancements
- **Learn Page Changes**
  - Added educational pages
    - What is Sleep Apnea
    - Obstructive Sleep Apnea
    - Central Sleep Apnea
    - Causes
    - Symptoms
    - Risk Factors
    - Diagnosis
    - Treatment
  - Updated About PAP Therapy section
- **Landing Page Changes**
  - Reduced font weight of subheaders
  - Made white space more consistent
- **Team Page Changes**
  - Added first group of PEP members to the team page
  - Added first round of internal team members to the team page
  - Autoscrolling used to accomodate for longer list
- **Minor Changes**
  - Altered color of navigation bar and removed its drop shadow

## 7.3.2 (July 7, 2015)

### Bug Fix
- Restructured logic syntax for loading google analytics A/B testing script

## 7.3.1 (July 7, 2015)

### Bug Fix
- Relocated of google analytics A/B testing script to top of landing page header

## 7.3.0 (July 7, 2015)

### Enhancements
- **Landing Page Changes**
  - Implemented framework for A/B testing
  - Testing video above the fold versus subtitle
  - Add event tracking for video views
  - Removed privacy blurb from bottom of landing page, since it's in the privacy policy
- **General Changes**
  - Updated styling on unlock account page
  - Fixed styling of error message on login page when entering an incorrect password
  - Added json index of research topics for integration with mobile application
  - Academics are properly forwarded to terms of access when trying to review survey data
  - Minor stlyistic changes to dashboard layout, including headers for widgets
  - Added image of didgeridoos to didgeridoo research highlight
  - Centered waveform image on Learn page
  - Added bottom padding to sidebar to look more balanced
- **Admin Changes**
  - Admins can now disable user emails when deactivating accounts
  - Fixed daily/weekly count from user index
  - Added display tooltips for daily engagement points
  - Added demographic breakdown by time period
    - Daily engagement report now only available to admins
  - User sign in count and last session are visible from admin view
- **Gem Changes**
  - Updated to redcarpet 3.3.2

### Bug Fix
- Fixed average satisfaction percentage calculation on My Sleep Apnea Treatment report
- Adjusted size of large image in about me report that caused screen to stretch
- Fixed OpenSans font not loading in Internet Explorer by adding `woff` format

## 7.2.2 (June 25, 2015)

### Enhancements
- **Gem Changes**
  - Updated to rails 4.2.3

### Bug Fix
- Posts on forum now correctly display when a user uses the `<` symbol

## 7.2.1 (June 24, 2015)

### Enhancements
- **Copy Changes**
  - Changed word 'awesome' to 'dynamic' on landing page
  - Clarified possible treatment outcomes on learn page

## 7.2.0 (June 24, 2015)

### Enhancements
- **Landing Page Changes**
  - Sign up form above the fold
  - Better use of white space throughout
  - Allows for easy integration of other elements in the future
  - Minor updates to styling of provider landing page, including new video
  - Link to risk assessment added above the fold
- **Layout Changes**
  - Navigation has been greatly simplified, and dropdowns mostly removed
  - Mobile navigation uses modern dropdown with basic navigation
  - Sidebar includes next steps, to help new members integrate with the community
  - Public sidebar better explains the features gained as a member
  - Extra content (including learn pages) are available in the footer
  - All major pages now have a callout encouraging people to join the site
    - Tracking established to see which area drives the most conversions
    - Custom messages developed for different sections of the site
  - Learn page now acts as a landing page for other content
    - Infographic type display, with quick links to other learn content
  - Increased font size for forum index pages
  - Minor spacing updates for bmi tool on mobile devices
- **General Changes**
  - Minor changes to display of large numbers on Community page
  - Survey index is viewable for logged out users
  - Successful password change styled to clearly be a success
  - Added custom page titles and descriptions for search engine optimization
  - Moved ISSS from learn page to partners page
  - Updated link to MyApnea.Org welcome video
  - Added didgeridoo research highlight
  - Added ability for users to invite new members via email
  - Added ability for users to invite their personal care provider via email
  - FAQ research links now link to surveys index, which is publicly viewable
  - Updates to Privacy Policy to include section on cookies and retargeting
- **Survey Exports**
  - Added a data dictionary export task to export all surveys into CSV format
  - Added a data export task that matches the data dictionary format and is exported into CSV format
- **Administrative Changes**
  - Research topic admin dashboard shows total topic and vote counts
  - Users index can easily highlight who has joined recently, along with daily counts
  - Added engagement report, to track how many members are completing the sidebar next steps
  - Changed date format to "%-m/%-d/%Y" for submitted research topics in admin view
  - Added a daily engagement tracker
    - See post creation, user registration, and survey completion over time
    - See all activity over the past week
- **Forum Changes**
  - Posts that are deleted by users are now marked as hidden, and also track who deleted the post
- **Gem Changes**
  - Updated to rails 4.2.3.rc1
  - Updated to redcarpet 3.3.1
  - Updated to kaminari 0.16.3

### Refactoring
- Simplified get started process
  - Allows the process to easily integrate new user roles in the future
  - Gives a unified three step process, regardless of consent status
- Unified code for consent process

### Bug Fix
- Fixed a bug on the forums that caused email links to be formatted incorrectly
- Fixed a bug where users editing their comments on research topics redirected them to the forums terms and conditions

## 7.1.4 (June 19, 2015)

### Bug Fix
- Fixed unlock URL generated by Devise mailer email

## 7.1.3 (June 9, 2015)

### Bug Fix
- Fixed an issue displaying the landing page on iOS 7 devices

## 7.1.2 (June 8, 2015)

### Bug Fix
- The landing page now displays better on iPhones in landscape mode

## 7.1.1 (June 4, 2015)

### Bug Fixes
- Fixed issue with large play video overspilling on mobile landing page
- The home page no longer shows posts that have been deleted

## 7.1.0 (June 3, 2015)

### Enhancements
- **Landing Page Changes**
  - Added the new MyApnea.Org video to the landing page
  - Minor stylistic changes and user interface enhancements
  - Videos on landing and learns page now have controls enabled
  - Shortened and enhanced the intro text above the fold
  - Better explained our mission below the fold
  - Added indicator to encourage users to scroll and learn more about MyApnea
  - Moved community counter closer to the testimonial and signup form
  - Updated testimonial
- **Profile Changes**
  - Posts on member profiles now link to the exact post instead of just the topic
- **Forum Moderation Changes**
  - Forum moderators can no longer delete topics and posts
  - Spam and hidden posts now are displayed in a more consistent way for moderators
  - Site admins can now delete topics and posts
  - Moderators can now see the total number of spam topics and posts on the member's profile
- **Dashboard Changes**
  - Posts on home page now link directly to post instead of just to the topic
  - Posts on home page and on member profile pages are shown in full
  - Posts by current user will now appear on the dashboard
- **Learn Page Changes**
  - Hovering over the carousel pictures now changes the mouse pointer to a cursor to indicate that they are clickable
  - Updated YouTube link to BWH video
- **Administrative Changes**
  - Cross tabs report now displays percentages in a cleaner manner
  - Improved links back to main admin dashboard
  - Easily access social profile from user profile
  - Research topics now include links and vote count
  - Research topics can be edited
- **General Changes**
  - Updated styling for flash notification messages
  - Added standard "Open Sans" font family to ensure font consistency across devices and operating systems
- **Rank the Research Changes**
  - Voting from member profiles will not dynamically update
  - Rank the research is now publicly viewable
  - Research Highlights are now publicly viewable

### Bug Fixes
- Fixed an issue getting counts for age categories on administrative progress report
- Users cannot vote from member pages unless they have already completed the Rank the Research tutorial
- Fixed an issue with styling that allowed unwanted left/right scrolling on landing pages
- Fixed a bug where topic update notifications were sent even after a user had unsubscribed from a topic

### Refactoring
- Simplified internal role structure for setting admins and moderators

## 7.0.2 (June 2, 2015)

### Bug Fix
- Fixed URL links in password reset and account unlock emails

## 7.0.1 (June 1, 2015)

### Bug Fix
- Fixed an issue with the URL structure for Facebook and Twitter share links

## 7.0.0 (June 1, 2015)

### New Features
- **Rank the Research Overhaul**
  - Requires users to go through an intro process
    - Users will see a dedicated page that introduces and explains the feature
    - Users will then go through ten dedicated questions, one at a time
    - Users will finally be able to see the full index, which has been restyled
      - The most popular topic will be pulled above the fold to encourage further engagement
      - Endorsed questions will be shown as visual links as a proof of deliverable
      - Questions are binned by category (newest, most discussed)
      - Users can submit a question on the index page
  - Show page for each research topic includes the discussion
    - Leverages backend functionality of forums, but with custom styling and unique feel
    - Users are able to vote, and easily change their vote, from this page
  - Added a 'My Research Topics' page
    - Shows each research topic, along with its
      - Status (approved, pending review, rejected)
      - Endorsement rating
  - Added Research Highlights
    - Shows a list of approved research topics from the community
    - Each accepted question has a write up on its own separate page with unique styling, as needed
- **Dashboard**
  - Added ability for admins to dynamically update dashboard highlights
  - Added a stream of recent posts to the dashboard (continously loads all posts)
- **Profile Changes**
  - Members can drag and drop a new photo on the settings page
  - Uploaded photos are now renamed to obscure any original filename
  - Members can generate a random forum name, generated names are in the following format:
    - `AdjectiveColorAnimal####`
  - Each member has their own unique 'social profile' scoped by their forum name
    - Displays their image and overall participation stats
    - Displays all posts on the forum and approved research topics, chronologically

### Enhancements
- **Social Sharing**
  - Forum topics can now be easily shared
    - Each topic can auto generate a tweet, facebook post, and email for sharing
  - Members are able to share that they have just completed a survey after submission
- **Forum Changes**
  - Unmoderated posts are visible to users with a visible flag that they are under review
  - A disclaimer is included at the bottom of a topic with unmoderated posts
  - Members are now assigned a forum name from a list of more than 14 billion possibilities
    - Members may change their forum name in their account settings if they choose
  - Topic subscribers are now notified immediately when a new post is created
  - Editing a post now sets it back to under review for regular forum members
  - Individual posts can now have links enabled
    - Link become clickable once the post is approved
    - Members editing their post sets the post back to under review, hence removing the clickable link
  - Community member profiles have been added that show a member's popular posts and forum contributions
  - Logging in while viewing a forum or topic page now keeps the member on that page and no longer redirects to the dashboard
- **Administrative Changes**
  - Forum moderators can now get in touch with forum participants to discuss posts that may require moderation or editing
  - Added Provider Report
    - Shows the number of members who have signed up with each provider
    - Highlights the provider with the most members
  - Added Progress Report
  - Admin location report now better shows unmatched locations
- **Provider Changes**
  - Providers now receive an informational email after updating a completed provider profile
  - Member welcome emails now list their provider if they signed up through a provider page
  - The providers index can now be searched by provider name
- **General Changes**
  - Added a friendlier 404 page in case a member tries to visit a page that does not exist
  - Added retargeting pixel from Connect360 to landing page
  - Added 'Remember me' to login process to reduce landing page hits for already registered members
- **Consent Changes**
  - Updated the consent to latest approved revision
- **User Interface**
  - Updated to remove preset padding around each page
    - Maximizes screen real estate
    - Allows for more dynamic pages in the future
  - Survey module still uses interface with padding, as the surveys were optimized for this
  - Cleaned up the account page, and added easier jump-navigation
  - Cleaned up admin dashboard

### Refactoring
- Trackable links on landing page now default to local paths if the trackable link is not present
  - This allows better navigation in local and staging environments

### Bug Fixes
- Welcome modal that introduces research will only appear for nonacademic users
- Answer session is no longer required to view survey reports
- Login dropdown on top navigation optimized for internet explorer (text fields no longer spill over)
- Fixed an issue for forum moderators where topics with hidden posts would no longer be viewable on forum index
- Videos on learn page no longer cover the navigation bar when using Internet Explorer
- Improved legibility of call-out text on the landing page

## 6.1.3 (May 26, 2015)

### Bug Fix
- Fixed an issue where members could post images and links on forum without having explicit permission to post links or images

## 6.1.2 (May 12, 2015)

### Bug Fix
- Added support for JavaScript functions that were not available in Firefox and Internet Explorer

## 6.1.1 (May 1, 2015)

### Enhancements
- Temporarily removed the video from the landing page and moved to learn page
- Updated a link on the learn page
- Minor website copy changes
- Email field now gets focus when clicking the Login button in the top navigation bar
- Added bitly links to landing page for engagement tracking

### Bug Fix
- Fixed the Sleep Apnea and BMI tool not loading correctly in certain instances
- About My Family Report now properly displays the member's selected country of birth

## 6.1.0 (April 27, 2015)

### New Features
- New landing page redesign
  - Removes 'walled garden' feel by showing top navigation immediately
  - Better highlights 4 key values for possible MyApnea members

### Enhancements
- **Layout**
  - PAP devices added to learn panel
    - Minor updates to styling
    - Improved navigation between these pages
  - Navigation expanded to include distinct 'Learn' and 'Research' tabs
- **Tool Updates**
  - Updated layout of BMI calculation and graph for `Sleep Apnea and BMI` tool
  - Minor edits to wording of results on `Risk Assessment` tool
  - Minor edits to wording of results on `Sleep Apnea and BMI` tool
  - Cleaned up layout on `Risk Assessment` tool
  - Fixed minor issue with display labels for height on `Sleep Apnea and BMI` tool
- **Survey Changes**
  - Clarified wording of `family diagnostic` question in `About My Family` survey
  - Fixed a typo in the `income` question in `About Me` survey
  - Admins can now specify members to be excluded from data exports and reports
- **Forum Changes**
  - Posting on a multi-page topic now correctly loads the last topic page and post
  - Non-square member photos now display better on the forums
- **General Changes**
  - Standardized text sizes on partners page
  - ASA correctly listed as promotional partner
  - Minor copy changes throughout the site
  - Mailings that reference https://myapnea.org/bwh now forward to the BWH provider page
  - Fixed margin and spacing on FAQs
  - Privacy Policy URL now uses hyphen to be consistent with other links
- **Gem Changes**
  - Updated to Ruby 2.2.2
  - Removed slow capybara test framework
    - Tests will be handled by integration tests

### Bug Fixes
- Fixed issue with academic users not being able to see surveys
- Fixed styling on button to accept the consent and privacy policy updates

## 6.0.0 (April 15, 2015)

### New Features
- **Survey Updates**
  - Survey reports restyled to be more easily digested, and appear as infographics
    - All surveys except for 'My Interest in Research'
    - Users are able to toggle and view the original detailed reports
    - Researchers and providers are immediately shown the original reports, with option to see new reports
    - Formatting and content based on feedback sourced from members via the forums
  - System for adding custom validations for specific Survey questions
    - Date of birth validation
- **Administrative Changes**
  - Cross-Tabs admin panel added
    - Lists information about demographics of enrollment per type of referral source
  - Location breakdown admin panel added
    - Shows breakdown of member location by state and country
- **Survey Exports**
  - Common Data Model Version 2.0 exports implemented
  - ESS export task added for reporting purposes
  - PROMIS export task added for reporting purposes
  - Location Report added that shows membership by country and state
- **Public facing tools**
  - Sleep apnea risk assessment tool added
    - Based on the STOPBang survey
    - Social sharing of results added for social media
  - Sleep apnea and BMI assessment tool added
    - Transformed from BMI/AHI calculator
    - Relates sleep apnea severity to BMI category

### Enhancements
- **Gem Changes**
  - Updated to rails 4.2.1
  - Updated to redcarpet 3.2.3
- **Documentation Changes**
  - Consent and Privacy Policy updated based on need in order to do coenrollment
  - Method for users to accept this update added
    - This acceptance is required to for users to complete new surveys
    - New users will automatically accept the update when they accept the consent
  - Governance policies added to footer
- Added American Sleep Association as a partner

### Bug Fixes
- Date of birth bug fix addressed now from the backend for better handling
- Partner ISSS link updated

## 5.2.0 (March 25, 2015)

### Enhancements
- **Forum Changes**
  - Submitting a post on a topic now disables the button to prevent double-posting
  - Images in forum posts now scale correctly
- **Survey Changes**
  - Users can now toggle a simple survey display if they have trouble inputting answers

## 5.1.0 (March 13, 2015)

### New Features
- **Administrative Changes**
  - A user registration and survey completion report added
    - The report provides comparison across different MyApnea.Org releases
- **Forum Changes**
  - Posts can include mentions of other users by their social profile names
  - Topics can be searched by authors of posts within that forum
  - Moderators can now move topics between forums
- **Content Additions**
  - About Sleep Apnea
  - About PAP Therapy
  - PAP Quick Setup Guide
  - PAP Troubleshooting Guide
  - PAP Care and Maintenance
  - PAP Masks and Equipment
  - Traveling with PAP
  - Side Effects of PAP
  - Sleep Tips

### Enhancements
- Fixed some minor typos
- Social profile information is now automatically displayed after being added by member
- Providers index now properly paginates, with 12 providers listed on each page
- Forums slightly restructured
  - Introductions -> General
  - Rank the Research -> Research
  - Removed Daytime Sleepiness and Performance Outcomes
  - Removed Network Member Feedback Regarding Proposed Studies and Clinical Trials

### Bug Fixes
- Fixed a survey bug where users had incomplete multiple radio button questions locked
- Fixed a minor error generating a lottery winner
- Added redirect for deprecated 'research_surveys' to 'surveys'
- Fixed survey error when all options for a checkbox question were unselected
- Fixed incorrect answer option migration mapping for the `my-sleep-apnea` survey
- Hotkeys disabled for locked questions
- Topics with slug `new` cannot be created
- Google Analytics compatibility with Turbolinks

## 5.0.0 (March 4, 2015)

### New Features
- **Major Survey Updates**
  - Surveys have received a major update and have been restructured.
    - The three existing surveys have been split across 11 smaller surveys
  - New surveys have an exciting new interface!
    - Users are able to scroll through survey by using keystrokes
    - Answer options now have hotkeys and values
    - Animated scrolling now used to move between questions
    - Survey urls have been simplified
    - Surveys can display nested questions
    - On submission, surveys are locked, but can be reviewed when revisited
    - Surveys are assigned based on user role selected during the registration process
- **Member Roles**
  - Members are able to define one or more of the following roles:
    - Adult who has been diagnosed with sleep apnea
    - Adult who is at-risk of sleep apnea
    - Caregiver of adult diagnosed with or at-risk of sleep apnea
    - Caregiver of child(ren) diagnosed with or at-risk of sleep apnea
    - Professional care provider
    - Research professional
- **Registration Process**
  - Upon registration, users are asked to describe their role on the site
  - Upon registration, users are automatically sent through consent process
    - For providers:
      1. Privacy Policy
      2. Terms of Access
      3. Provider Profile
    - For researchers who only identify as a researcher:
      1. Privacy Policy
      2. Terms of Access
      3. Social Profile
    - For all other members:
      1. Privacy Policy
      2. Consent
      3. About Me Survey
- **Terms of Access (ToA)**
  - ToA will now be shown to the following groups, in place of the consent:
    - Members who identify as a provider, but not any patient or caregiver role
    - Members who identify as a researcher, but not any patient or caregiver role
    - Still visible by other members of the community for transparency

### Enhancements
- **Administrative Changes**
  - Added an admin dashboard to provide a central place to reach reports and research topic moderation
- **Research Study Changes**
  - Clicking "Leave Research Study" on the Consent or Privacy Policy pages now removes the member from the study
    - In the past, the member would be redirected to the account page where this question would be asked one more time
- **Forum Changes**
  - Added indication of additional posts on forum index and pagination on individual forums
  - Forum post anchors now correctly offset based on the top navigation bar
  - Forum markdown has been improved and examples are provided under the Markup tab
    - Blockquotes: `> This is quote`
    - Highlight: `==This is highlighted==`
    - Underline: `_This is underlined_`
    - Superscript: `This is the 2^(nd) time`
    - Strikethrough: `This is ~~removed~~`
- **Community Page Changes**
  - Removed state labels from USA map to provide cleaner overview
- **Lottery Updates**
  - Lottery random drawing code has been added, and can be run using `Lottery.draw_winner`
- **Search Engine Optimization**
  - Added `sitemap_generator` gem for dynamic SEO via sitemap creation
  - Added unique meta descriptions to several key pages
  - Added unique page titles to several key pages
- **Gem Changes**
  - Updated to Ruby 2.2.1

### Bug Fixes
- Password fields now display correctly in IE9
- Topic slugs are now generated correctly for topics with titles that start with numbers
- User dashboard displays correctly even without the presence of the forum

### Refactoring
- Centralized application configuration further by using figaro environment variables
- Beta UI pages are now set as the default
- Several OpenPPRN features have been disabled or removed
  - Removed OODT and Validic integration
  - Removed blog controller and views, this functionality is currently being handled by the "News Forum"
  - Removed unused `pprn.rb` initializer file
- Several survey model simplifications have been made:
  - Renamed `QuestionFlow` to `Survey`
  - Simplified survey load files
  - Answer sessions are only created when surveys are launched, and encounter identifiers
- The Forums Terms and Conditions now uses the new layout
- Reduced dependency on `authority` gem
- Cleaned up the account controller and updated tests
- Cleaned up the static controller and added appropriate tests
  - Moved `views/myapnea/static` files into `views/static` folder

## 4.2.0 (January 29, 2015)

### Enhancements
- **Provider Changes**
  - The provider sign up page now better indicates what users can do who are already signed in
    - Allows users to contact support to become a provider
    - Allows providers a link to their account settings to set up their custom pages
- **Rank the Research**
  - New UI/UX built and is now enabled for all members regardless of beta opt in status
  - Statistic shown is now percentage of voters, rather than vote count
  - Reduced complexity by displaying all data on one page (with possible need for pagination)
  - Users are able to see statistic for any questions, but will be alerted if they have used all of their votes already
- **General Changes**
  - The Privacy Policy popup now uses the identical text to the Privacy Policy page
  - Shortened the length of news posts description shown in the right hand side bar
  - Added The Health eHeart Study under Promotional Partners
  - The Partners page now uses the new layout exclusively

### Bug Fixes
- Fixed the providers "Create Your Page" button not working for logged in users
- Fixed a bug that prevented linking a provider to a newly registered user if the user ran into other registration validation errors during the sign up process
- Fixed link to CMB Solutions
- Removed link from Recent News in old layout as the page no longer exists
- Advisory member bios now open correctly on mobile devices

## 4.1.0 (January 21, 2015)

### Enhancements
- **Consent and Privacy Policy Changes**
  - **The Consent and Privacy Policy were updated to reflect the latest IRB-approved documents**
- **General Changes**
  - Minor changes to UI (links, text, sizing)
  - Fixed display of personal provider page to have fullscreen/landing layout
  - Fixed display of video and added autoplay/autopause features
  - Recent News posts now link directly back to the topics
  - Started work on allowing members to identify themselves as one of the following:
    - Diagnosed With Sleep Apnea
    - Concern That I May Have Sleep Apnea
    - Family Member of an Adult with Sleep Apnea
    - Family Member of a Child with Sleep Apnea
    - Provider
    - Researcher
- **Forum Changes**
  - Topics with posts that are pending review now show up as well when a moderator filters by topics that are pending review
  - Topics are now sorted by their last visible post, and the last post by field is also filtered by the last visible post for the user
    - Moderators will see the last post made that's pending moderation
    - Regular members will see the last approved post in the forums index
  - Moderator posts are now automatically approved
  - Post links in emails now directly reference the post and are the server will redirect these links to the correct topic page
  - Post Approval and Reply emails now reference the title of the topic so that email clients can group these together more naturally
  - Topic and Post status has been simplified to include `hidden` status, along with `approved`, `pending_review`, and `spam`
- **Community Page**
  - Added quick link to change your account settings to be included in the map
  - Community map now displays US and World membership counts
- **User Interface Changes**
  - Added user stats to side navigation bar (beta version)
  - Administrative link added into the new UI menu (beta version)
  - The terms of service, privacy policy, and consent pages have been updated to match the new UI (beta version)
  - Learn page added as a quick link beneath the 'About Us' dropdown, and updated to fit new UI (beta version)
    - Added 'additional resources' to learn page
  - SAPCON Advisory Council added as a separate page
  - Partners and promotional partners added as a separate page (removed from Our Team)
    - Industry relation text added
- **Email Changes**
  - Updated styling on Password Reset and Account Unlock emails to match new MyApnea.Org themed email layout
  - Added a new member welcome email
- **Administrative Changes**
  - Member management page has been updated and now proplery paginates members
  - Moderators can now create approved Research Topics
- **Provider Changes**
  - Providers are able to sign up with minimal information
    - Only require name, email, and password to create account
    - Will input slug, provider name, location, etc. in provider_profile page

### Bug Fixes
- Reformatted beta alerts so they would be dismissable on mobile devices
- Fixed sidebar on mobile devices
  - Now only relying on JavaScript for class changes (instead of framed animations)
  - Only using fixed-positioning for the user sidebar on medium and large screens
- Removed provider name as a field on the member registration page
- Fixed a bug that prevented topic replies from being sent out to email subscribers
- Clicking Leave the Research Study now properly reflects the member's choice on the account page

### Refactoring
- Removed all remaining `forem` gem code

## 4.0.0 (January 15, 2015)

### Enhancements
- **PCORNET Updates**
  - Added script that extracts data into the PCORNET Common Data Model 2.0
- **Forum Updates**
  - Redesigned internal forums engine
  - Posts can now be previewed before being submitted
  - Added a forum digest email
  - Members can now subscribe and unsubscribe to forum topics
    - Members receive email when a reply is made to one of their subscribed topics
  - Members can opt out of receiving forum emails in their account settings
- **General Changes**
  - Changed image sizes to speed up loading on mobile browsers
  - Members of MyApnea.Org may opt into upcoming design changes and provide feedback directly to the MyApnea.Org team
  - Readded the MyApnea.Org favicon to quickly identify the website when it is pinned in the browser
  - The new UI better handles text sizes on mobile devices
  - Links are now easier to distinguish in the new UI
- **Administrative Changes**
  - Admin Survey overview now correctly shows the number of completed surveys
  - Website roles have been simplified
- **Provider Updates**
  - Providers can now sign up and create a unique URL that they can share with their members
  - Members of MyApnea.Org can visit existing provider pages and add themselves as one of the provider members
- **Survey Changes**
  - The new UI redesign includes a new survey report overview to better display a member's survey answers compared with others

### Bug Fixes
- Confirmation boxes now properly display when deleting or removing posts

### Refactoring
- Internal `post` model changed to `notification`
  - Old `Post` class has to do with **Site Notifications** and **Blog Posts**
  - New `Post` class will be specific to **Forum Topics**

## 3.2.1 (January 8, 2015)

### Bug Fixes
- Fixed report view bug for reports with no answers
- Removed dependency on schema_plus gem

## 3.2.0 (January 8, 2015)

### Enhancements
- **Survey Changes**
  - Improved the performance and speed of surveys and survey reports

### Bug Fixes
- Fixed a bug that prevented users from progressing past question 12 in the About Me survey
- Fixed a bug preventing users from entering a date using older browsers in the About Me survey

## 3.1.0 (January 2, 2015)

### Enhancements
- **Home Page Changes**
  - New landing page added that shows total member count
    - The new landing page is the first in a series of parts of the website that will receive a user interface update
    - Landing page now loads for non-logged in users
  - Surveys linked on the home page have been updated to better show a member's progress through the available surveys
- **Registration Changes**
  - Year of Birth is now a drop down list to avoid confusion between entering Birth Date instead of Year of Birth
- **Forum Changes**
  - Forum index no longer shows quoted text in forum replies
  - Improved the user interface for the forum index and the forum widget on the home page
  - Forum topics now have an updated interface that focuses on easier readability
- **General Changes**
  - Minor text and content updates throughout the site
  - Fixed an issue where long links and title would run into the page from the Recent News bar
  - Updated timezone for forum to use Eastern Time Zone
  - Session timeout was increased to allow members to be logged out less frequently
- **Administrative Changes**
  - Administrators can export users to update MailChimp lists and segments
  - Blog posts link now correctly goes to the news forum
- **Gem Changes**
  - Updated to rails 4.2.0
  - Updated to Ruby 2.2.0

### Upcoming Changes
- Added redesign preview of the following pages:
  - Forum index page
  - About Team page
  - Survey Report page
  - Rank the Research page
  - Community Map page
  - Providers Sign Up page

### Bug Fixes
- Fixed a survey question being select one, instead of select any
- Fixed some minor spelling errors in survey questions

## 3.0.0 (December 16, 2014)

### Enhancements
- **General Changes**
  - Added lottery language to the Informed Consent to Question 14.
  - Split existing survey into three smaller surveys
  - Added new landing page prototype

## 2.1.0 (December 10, 2014)

### Enhancements
- **General Changes**
  - **In the News** integrates Facebook and forum news posts
  - Some minor text and content changes
  - Reduced size of header image to load more quickly

- **Gem Changes**
  - Updated to rails 4.2.0.rc1
  - Updated to Ruby 2.1.5

### Refactoring
- Updated production environment initialization, including integration with Figaro gem.
- The forem gem now uses the default configured email address

### Bug Fixes
- Surveys cannot be reset now without explicit permission from user.
- Added fix for Google Analytics to correctly track page views.
- Surveys with 0 answers completed can be resumed.

## 2.0.0 (November 14, 2014)

### Enhancements
- **General Changes**
  - Revamped the navigation flow.
  - Synchronized vision with OpenPPRN.
  - Cleaned up overall style.
  - Added community and personal contributions
  - Revamped forums
  - Improved mobile navigation design
  - Added new landing page based of introduction page

## 1.1.0 (October 17, 2014)

### Enhancements
- **Research Surveys**
  - Added survey question about sleep care institutions
  - Added ASAA as a choice for how a user heard about MyApnea.
  - Implemented new question type, based on `typeahead.js`.

### Bug Fixes
- Fixed research topic voting problems encountered on Firefox browsers.
- Fixed erroneous 'true' flash message after session expiration.

## 1.0.1 (October 15, 2014)

### Enhancements
- **Sidebar Navigation**
  - Implemented collapsing sidebar for mobile users.

### Bug Fixes
- **Social Profile**
  - Fixed problem where social profile update did not save all fields.
  - Added validation for negative age values.
  - Fixed crash when uploading photo on production.
- **Research Surveys**
  - Fixed survey stability issues.
  - Cleaned up and fixed issues with survey completion report.
- **Forum**
  - Made entry into forums more obvious for users.

## 1.0.0 (October 3, 2014)

### Major Features

- **Social Profile**
  - Users can create a social profile to brand themselves on MyApnea.Org
    - Users can choose a nickname and profile picture, and choose to share their sex and age
  - Users can interact on the MyApnea.Org forums by creating new topics and posting to other users topics
  - Users can see a map of fellow users who have shared their city location

- **Research Survey**
  - Users who fill out the Research Consent form are able to fill out "About Me and My Sleep"
  - Users are provided a survey report that shows them aggregate results from others who have taken the survey

- **Rating Research Questions**
  - Users who register are able to cast votes on prominent research questions
  - Users can submit their own research questions

- **Learning about Sleep Apnea**
  - MyApnea.Org provides a "Sleep In the News" corner that allows users to read more about sleep apnea and related subjects
  - MyApnea.Org links the American Sleep Apnea Association Facebook feed to provide another resource for learning about sleep apnea

- **Administrative**
  - Administrators can assign roles to other users
  - Administrators can moderate forum posts
  - Administrators can add new blog posts for the "Sleep In the News" corner
