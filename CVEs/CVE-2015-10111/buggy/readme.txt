=== Watu ===
Contributors: prasunsen
Tags: exam, test, quiz, survey, wpmu, multisite, touch, mobile
Requires at least: 3.3
Tested up to: 4.3.1
Stable tag: trunk
License: GPLv2 or later

Creates exams and quizzes with unlimited number of questions and answers. Assigns grade after the quiz is taken. Moible / touch - friendly.

== License ==

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

== Description ==

Create exams and quizzes and display the result immediately after the user takes the exam. You can assign grades and point levels for every grade in the exam / quiz. Then assign points to every answer to a question and Watu will figure out the grade based on the total number of points collected.

Watu for WordPress is a light version of <a href="http://calendarscripts.info/watupro/" target="_blank">Watu PRO</a>. Check it if you want to run fully featured exams with data exports, student logins, categories etc.

**This plugin is mobile / touch - friendly.** The quizzes will work on mobile devices and phones. 

**Please go to Tools -&gt; Watu Quizzes to start creating quizzes and exams.**

### Features ###

* Creates quizzes and exams
* Use shortcodes to embed quizzes in posts or pages
* Single-choice questions
* Multiple-choice questions
* Open-end questions (essays)
* Required questions
* Grades
* Shows answers at the end of the quiz or immediately after selection
* List of users who took exam along with their results
* Ajax-based loading of the quiz results.
* Facebook sharing
* Mobile / touch - friendly
* Notify admin when someone takes a quiz

### Attention  WordPress Network (Multi Site) Users ###

When activating the plugin do it as blog admin and not as network admin.

### Online Demo ###

Feel free to check the [live demo here](http://demo.pimteam.net/wp/?p=12 "Live demo"). It should answer most "pre-download" questions.
If you have more doubts just download the plugin and check out if it works for you. It's free and takes a few seconds to install and activate.

### Troubleshooting ###

**When opening a support thread please provide URL (link) where we can see your problem.**

A very common problem is not being able to submit the quiz, or the quiz does not displays at all. This is usually a fatal javascript error caused by other plugins or your them. If you are technical you can easily find the error yourself by checking the JavaScript error console in Chrome or Firefox. Disable the offending plugin and everything will start working normally.

### Developers API ###

In order to allow other plugins to integrate better to Watu we have started working on developers API.
The following action calls are currently available:

= do_action('watu_exam_submitted', $taking_id)  
Called when exam is submitted, passes the taken exam ID 

= do_action('watu_exam_saved', $exam_id)
Called when you add or edit exam (after submitting the changes). Passes the changed exam ID. 


== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the entire folder `watu` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to "Watu Settings" to change the default settings (optional)
1. Go to "Watu Quizzes" under "Tools" menu to create your exams, add questions, answers and grades. On the "manage questions" page of the created exam page, above the questions table you will see a green text. It shows you the code you need to enter in a post content where you want the exam to appear.

== Frequently Asked Questions ==

= How are grades calculated? =

Watu computes the number of points in total collected by the answers given by the visitor. Then it finds the grade. For example: If you have 2 questions and the correct answers in them give 5 points each, the visitor will collect either 0, or 5 or 10 points at the end. You may decide to define grades "Failed" for 0 to 4 points and "Passed" for those who collected more than 4 points. In reality you are going to have more questions and answers and some answers may be partly correct which gives you full flexibility in assigning points and managing the grades.

= Can I assign negative points? =

Yes. It's even highly recommended for answers to questions that allow multuple answers. If you just assign 0 points to the wrong answers in such question the visitor could check all the checkboxes and collect all the points to that question.

= How do I show the quiz to the visitors of my blog? =

You need to create a post and embed the exam code. The exam code is shown in the green text above the questions table in "Manage questions" page for that exam.

**Please do not place more than one code in one post or page. Only one exam will be shown at a time. If you wish more exams to be displayed, please give links to them!**

= How does it handle user accounts? =

Watu uses the WordPress user registration / login system. So if you want to allow users to register for quizzes simply select "Require user login" in the Edit Quiz page and make sure "Anyone can register" is selected in your WordPress Settings page.

= Is it compatible with BuddyPress or membership plugins? =

Since it uses the standard WordPress user login system, it is compatible with BuddyPress and all membership plugins that we know about. Of course, some odd membership plugin that uses its own user login system might be incompatible with Watu.

= How to translate the plugin interface in my language =

You can use the standard WordPress way of translating plugins (via Poedit and .po / .mo files) or use plugin like Loco Translate.
If using Poedit, your file names should start with "watu-". For example: watu-de_DE.po / watu-de_DE.mo. They should be placed in wp-content/languages/plugins folder.

= Can I override the templates / views without modifying the plugin code? (For advanced users) =

Yes. You can create a folder called "watu" under your active WP theme folder. Then create a copy of the view file you want to modify, keep the original name, and place it in the "watu" folder there. (Do not create "views" folder). Then the copy of the template will be used instead of the original one.

== Screenshots ==

1. List of your exams with shortcodes for embedding in posts or pages
2. The form for creating and editing an exam/test
3. You can add unlimited number of questions in each exam, and each question can of single-answer, multiple-answer, or open-end type. 

== Changelog ==

= Changes in 2.6.7 =
- Added option to limit the number of logged in user attempts for quizzes that require login.
- You can specify one or more email addresses that will receive user's results (instead of just using the admin email from your WP Settings page)
- You can now specify different content for the email sent to you when someone takes a quiz
- New option lets you to send email to user with their results. If user is not logged in this will generate a required field to enter email on the quiz page.
- You can specify different email contents of the email sent to user than the contents of the email sent to admin
- All views /  templates can now be overriden by placing their copy under a folder called "watu" in your theme folder.
- Added option to switch off Ajax submitting for certain quizzes. This is useful if you are embedding javascript via shortcodes from other plugins in the final screen of the quiz as some javascripts won't work when loaded by Ajax.

= Changes in 2.6 =
- Added optional text-based "captcha" to prevent spam bot submissions on quizzes which do not require user login
- Option to reoder the questions. Of course this takes effect only when you have not chosen "Randomize questions" in the quiz settings.
- Removed the hardcoded text "your answers are shown below" and the setting "display answers at the end of the quiz". Instead of this use the variable %%ANSWERS%% which gives far more flexibility.
- Added option to not store takings in the database. Will be useful to save DB space when you have a quiz whose resutls you don't need to know.
- Added two new variables - %%AVG-POINTS%% and %%BETTER-THAN%% to compare your results to others who completed the quiz
- Added option to enable previous button
- Added option to automatically publish the quiz in a post at the time of saving
- Added question numbers + option to not display them (old quizzes default to it for consistency with the previous behavior)
- Added social sharing option. Currently supports Facebook sharing. You'll need Facebook app ID.
- Added Twitter sharing option. As above, use the shortcode [watushare-buttons] to enable all sharing buttons accordingly to your sharing options.

= Changes in 2.5 =
- Added optional answer explanation / feedback that can be shown along with the correct answers on the quiz 
- Added filter / search on the "view results" page
- Added feature to andomize the answers to the questions. Works together or independent from the question randomization.
- Added compatibility with WP QuickLaTeX
- You can now be notified by email when someone takes a quiz
- Made the quiz more user-friendly by auto-generating a demo quiz for the new users
- Improvements to open end quesitons: now any special characters are handled and matching is case INSENSITIVE
- Moved the grades management out of the main quiz form for better user interface, data integrity and rich text editor for the grade descriptions
- Added "grade" filter in the "View results" page
- Added option to pull number of random questions from the quiz 
- Fixed number of wpautop() issues. Now the filter is applied manually only where it's needed
- Fixed bug with calculating points on open-end question (the bug was caused by the latest "randomize answers" feature)

= Changes in 2.4 =
- Quizzes can now require user login. Depending on whether "Anyone can register" is selected in your main settings page, a register link will also be shown when non-logged in user tries to access such quiz
- You can now use "the_content" filter instead of "watu_content" to handle nasty problems with plugins like qTranslate. It's not recommended to use this setting unless you have experienced such problems.
- The full details of the user answers are now recorded and can be seen via popup in the list of results page
- Added uninstall script and changed the settings regarding deleting data. Now you have to double confirm deleting your exam. This is to avoid accidential data loss.
- Removed wpframe and other obsolete code
- Made small change to the display of radio and checkbox questions to allow easier formatting on one line with CSS
- Fixed for compatibility with 3.8
- Quiz description, if entered, shows up on top of the quiz
- Option to delete single taking and delete all submitted data on a quiz
- Changed current_user_can('administrator') to current_user_can('manage_options') so you can allow a non-administrator role to use the quizzes
- Open-end questions can also have answers and be matched to them
- Replaced wpautop in favor of nl2br to avoid adding <p> tags in unexpected places like hidden fields
- Did some small styling adjustments
- Fixed the %%MAX_POINTS%% calculation to take into account the quesiton type

= Changes in 2.3 =
- Export quiz results as CSV file (semicolon delimited)
- The exam shortcode is now easier to copy 
- Animate back to top when submitting exam, and when clicking "next" after long question. This prevents confusion when user has to see the next screen.
- Fixed bug with "Question X of Y total" showing even for single-page quizzes
- Each exam / quiz has its own setting about how the answers will be shown
- As many themes started showing the choices under radio buttons or checkboxes, added explicit CSS to keep them on the same line
- Fixed new bug with missing answers when adding question
- Fixed bug with skipping "0" answers
- Changed %%TOTAL%% to %%MAX-POINTS%% for clarify and consistency. The old tag will keep working. 
- Further code improvements 
- Tested in multisite
- Fixed "headers already sent" message caused by premature update statement

= Changes in 2.2 = 
- Replaced 'the_content' filter with custom filter to avoid issues with membership plugins
- Cleanup the root folder from show_exam.php
- Another method added to the API, see the new docs
- The answers field changed to TEXT so you can now add long choices/answers to the questions
- Fixed bug in the list of taken exams
- Fixed issues with correct/wrong answer calculation
- Added %%CORRECT%% answers variable to display number of correct answers
- Watu scripts and CSS are now loaded only when you have exams on the page avoiding unnecessary page overload 
- Other code fixes and improvements

= Changes in 2.1 =
- Displaying "Question X of Y" so the user knows where they are
- Fixing incompatibility with Paid Membership PRO
- Shortcodes on the final screen
- Starting API (Not yet documented)
- Code fixes and improvements

= Changes in 2.0 =
- Required questions (optional)
- A list of users who took an exam along with their results
- Localization of the strings in the javascript
- More flexible function to add new DB fields on update
- Code fixes and improvements

= Changes in 1.9 =
- Grade title and description are now separated
- Shortcodes will be executed in questions and final screen
- Code fixes and improvements
- Localization issues fixed

= Changes in 1.8 =
- the exam title links to the post with this exam if exam is already published
- "show all questions on single page" is now configurable for every exam
- Improving code continued (more to come)

= Changes in 1.7 =

- You can now randomize the questions in a quiz
- Fixed issues with the DB tables during upgrade
- Removed more obsolete code, fixed code issues. More on this to come.

= Changes in 1.6 =

- Removed obsolete rich text editor and replaced with wp_editor call
- Added "Essay" (open-end) question 
- Resolved possible Javascript conflicts
- Internationalization ready - find the .pot file in langs/ folder