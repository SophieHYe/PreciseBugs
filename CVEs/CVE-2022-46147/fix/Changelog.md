Drag and Drop XBlock changelog
==============================

Version 3.0.0 (2022-11-18)
---------------------------

* Sanitize HTML tags to prevent XSS vulnerabilities.  
  BREAKING CHANGE: Disallowed HTML tags (e.g. `<script>`) will no longer be rendered in LMS and Studio. 

Version 2.7.0 (2022-11-15)
---------------------------

* Add option to drop decoy items from scores

Version 2.6.0 (2022-10-24)
---------------------------

* Add package publishing workflow.

Version 2.5.0 (2022-10-13)
---------------------------

* Make the "Show Answer" condition customizable (like in the Problem XBlock). 

Version 2.4.2 (2022-10-13)
---------------------------

* Support HTML in explanations.

Version 2.4.1 (2022-10-06)
---------------------------
* Remove dots on feedback and moves final feedback to end and set bold text on assessment mode.
* Improve CSS styles for popup feedback.
* Remove special popup feedback styles for mobile.
* Improve feedback message when you reach the limit of elements on a zone.
* Fix the issue while dragging an image item on Safari mobile.
* Fix width issue for zone container on Safari mobile
* Update translations.

Version 2.4.0 (2022-09-23)
---------------------------

* Drop the support for Python 3.5 and Ubuntu 18.04.

Version 2.3.11 (2022-07-22)
---------------------------

* Use global (platform-wide) translations as a fallback when XBlock translations are missing. \
  This change also has an intended side effect - it allows overriding English text with comprehensive themes (by patching the default translation catalog).

Version 2.3.10 (2022-07-22)
---------------------------

* Add an optional "Explanation" field.
* Hide feedback div when there is no feedback content to display.
* Update translations.

Version 2.3.9 (2022-06-15)
---------------------------

* Fix draggable item width inconsistency while dragging.
* Do not move correctly placed items when showing the answer.
* Distribute items with multiple correct zones among these zones when showing the answer.

Version 2.3.8 (2022-04-12)
---------------------------

* Add item option to remove padding from images dropped on a zone.
* Add option to show zone border when dragging an item.
* Hides text when a draggable item is placed in a target zone and frontend wait for LMS response, this is done since the size of the item increases when the spinner is visible.
* Change containers and css classes to match those from edx-platform. The problem div now contains the drag container, buttons and feedback, css classes from submit button; submit answer and action buttons were also changed.
* Improve feedback styles, now only icons shows color related with feedback and text color remains the same also each feedback line doesn't show borders.
* Replaced `Travis` with `GitHub CI`.

Version 2.3.7 (2022-02-20)
---------------------------

* Reduce drag delay on mobile devices.

Version 2.3.6 (2021-08-11)
---------------------------

* Add Portuguese translations.

Version 2.3.5 (2021-03-09)
---------------------------

* Updates supported language translations.

Version 2.3.4 (2021-03-09)
---------------------------

* Enable adding weighted problems and overriding the scores for learners. The score can be overridden in LMS using either staff debug information or Student Admin in instructor Tab.

Version 2.3.3 (2021-03-09)
---------------------------

* Fixed drag and drop editor not loading in the studio due to translation issue.

Version 2.3.2 (2021-02-12)
---------------------------

* Fix instance of `DeprecatedEdxPlatformImportWarning`.

Version 2.3.1 (2020-08-04)
---------------------------

* Better JS i18n language mapping.
* i18n source file updated in Transifex.
* i18n updated (not complete) support for languages: ar, de, es_419, fr, he, hi, it, ja, ko, nl, pl, pt_BR, ru, tr and zh_CN

Version 2.3 (2020-08-04)
---------------------------

* Make Drag and Drop Indexable.
* Drop Python 2 support.

Version 2.2.10 (2020-04-14)
---------------------------

* Fix bug where 'edx.drag_and_drop_v2.feedback.opened' event was sent to publish_event endpoint excessively on mouse move.  (PR #238)

Version 2.2.9 (2020-04-03)
--------------------------

* Updated fragment import to remove deprecation warning which was causing extra logs in Splunk etc.

Version 2.2.8 (2020-01-13)
--------------------------

* Updated code to render custom zone title as HTML

Version 2.2.7 (????-??-??)
-------------------

* NOTE: This version was skipped / is not documented.

Version 2.2.6 (2019-10-08)
--------------------------

* Python3 compatible.

Version 2.2.5 (2019-10-04)
--------------------------

* This repository is now python3 compatible.
* Update the xblock-sdk hash which is python3 compatible also.

Version 2.2.4 (2019-07-30)
---------------------------

* Use InheritanceMixin for submission deadline checks (PR #219)
* Submit button behavior will change in Assessment Mode and will now be impacted by subsection due date, grace period and course pacing

Version 2.2.3 (2019-04-05)
---------------------------

* Fix dragging bug in mobile (PR #204)

Version 2.2.2 (2019-03-22)
---------------------------

* Add translation for Reset and Feedback strings (PR #196)
* Fix Loading message translation for Chinese & Spanish (PR #199)

Version 2.2.1 (2018-11-16)
---------------------------

* Fix JavaScript error when the XBlock is in an IFrame (PR #192)
* Revert change to travis.yml so it doesn't need to be updated with every version bump.

Version 2.2.0 (2018-11-15)
---------------------------

* Extend existing APIs to support native mobile (PR #158)

Version 2.1.8 (2018-11-02)
---------------------------

* Implement XBlock JavaScript i18n using i18n_tool and django-statici18n (PR #156)

Version 2.1.7 (2018-10-10)
---------------------------

* Expand static URLs in FeedbackMessage (PR #190)

Version 2.1.6 (2018-05-15)
---------------------------

* Use i18n runtime service to provide translations for HTML templates (PR #154)
* Ensures URLs are utf-8 encoded (PR #150)
* Correct load listener setup (PR #153)
* Improve image handling on mobile (PR #152)
* When dragging, scroll target image as needed.

Version 2.1.5 (2017-11-06)
---------------------------

* Making the code more robust to handle an edge case when a dict does not contain an key (PR #149)

Version 2.1.4 (2017-11-06)
---------------------------

* Make XBlock always emit `progress` event explicitly when it is marked as complete.

Version 2.1.3 (2017-10-05)
---------------------------

* Prevent target image from shrinking in mobile, and make it scrollable (PR #135)

Version 2.1.2 (2017-10-02)
---------------------------

* Ability to generate zones and background image automatically (PR #136)

Version 2.1.1 (2017-09-26)
---------------------------

* Enforce XBlock variable types (PR #104)
* Improvements for mobile (PRs #132, #133, #134)

Version 2.0.14 (2017-01-17)
---------------------------

* Various accessibility improvements (PRs #110, #111, #112)

Version 2.0.13 (2017-01-02)
---------------------------

* i18n improvements (PR #113)

Version 2.0.12 (2016-11-08)
---------------------------

* ([#108](https://github.com/openedx/xblock-drag-and-drop-v2/pull/108)) Grading updates:
    * [SOL-2030][sol-2030] Grade information is now displayed beneath the display name of the problem (similar to CAPA)
    * [SOL-2094][sol-2094] Fixed problem preventing DnDv2 blocks from being scored or graded.
    * Highest grade achieved is now displayed in feedback area
    * Renamed "Maximum score" field to "Problem Weight" to be in line with CAPA problems and avoid confusion.

[sol-2030]: https://openedx.atlassian.net/browse/SOL-2030
[sol-2094]: https://openedx.atlassian.net/browse/SOL-2094

Version 2.0.11 (2016-10-03)
---------------------------

* ([#106](https://github.com/openedx/xblock-drag-and-drop-v2/pull/106)) Don't delete misplaced items on final attempt

Version 2.0.10 (2016-09-22)
---------------------------

* ([#97](https://github.com/openedx/xblock-drag-and-drop-v2/pull/97)) Added "item" field to item.dropped event
* ([#101](https://github.com/openedx/xblock-drag-and-drop-v2/pull/101)) Implement "show answer" button
* ([#103](https://github.com/openedx/xblock-drag-and-drop-v2/pull/103)) Miscellaneous UI fixes
* ([#105](https://github.com/openedx/xblock-drag-and-drop-v2/pull/105)) Correct an issue with background image selection

Version 2.0.9 (2016-09-01)
--------------------------

* ([#88](https://github.com/openedx/xblock-drag-and-drop-v2/pull/88)) Cleaned up studio editor template: styling, help texts, accessibility attributes, classes instead of IDs, etc.
* ([#95](https://github.com/openedx/xblock-drag-and-drop-v2/pull/95)) Fixed flaky selenium tests
* ([#85](https://github.com/openedx/xblock-drag-and-drop-v2/pull/85)) Accessibility improvements for item feedback popup
* ([#73](https://github.com/openedx/xblock-drag-and-drop-v2/pull/73)) Accessibility improvements for item, zone and background image when using keyboard mode
* ([#96](https://github.com/openedx/xblock-drag-and-drop-v2/pull/96)) Decoy items are properly accounted for in grade calculation and problem completion condition
* ([#98](https://github.com/openedx/xblock-drag-and-drop-v2/pull/98)) Support for legacy item state
* ([#92](https://github.com/openedx/xblock-drag-and-drop-v2/pull/92)) Prevented overlapping item placement: option to keep dropped item where learner dropped it was removed; items always use automatic layouts (left/center/right)
* ([#93](https://github.com/openedx/xblock-drag-and-drop-v2/pull/93)) Per-item error feedback is shown in assessment mode when an attempt is submitted

Version 2.0.8 (2016-08-15)
--------------------------

* Numerical input feature removed
* Multiple drop zones per item
* Assessment mode - first version


Version 2.0.7 (2016-06-10)
--------------------------

* Translation fix: removed duplicate entries in translation files

Version 2.0.6 (2016-05-20)
--------------------------

* Accessibility fix: Removes role="application"

Version 2.0.5 (2016-03-30)
--------------------------

* Marked strings for translations (PR #66)
* Added the ability to specify automatic alignment of dropped items. (PR #57)

Version 2.0.4 (2016-03-10)
--------------------------

* Add a placeholder text to the input field to set the item image. (PR #65)

Version 2.0.3 (2016-03-07)
--------------------------

* Bugfix: When omitting the background image description in Studio, the red halo
  indicating the field with the error did not show in Safari. (PR #62)
* Bugfix: Make short URLs of the form /static/... to uploaded images work
  correctly for item background images. (PR #63)

Version 2.0.2 (2016-02-18)
--------------------------

* Bugfix: "Background description" was required, but if you filled it out and pressed "Continue", "Save", it would accept the new description but would not actually save it. (PR #55)
* Bugfix: When configuring the draggable items, the "Image Description" was always required, even if the "Image URL" was blank. (PR #55)
* Bugfix: When clicking certain action links in the dndv2 editor (e.g. "Add a Zone"), the browser would scroll to the top of the page (since the href="#" event was not prevented). (PR #55)
* Bugfix: When changing tabs in the dndv2 editor, the next tab would often be scrolled down halfway. (PR #55)
* Bugfix: In Studio, Newly added drag and drop components did not load properly, due to [a Studio bug](https://github.com/openedx/edx-platform/pull/11433) that affects Cypress and Dogwood. (Fixed in Studio post-Dogwood.) (PR #55)
* Fixed some flaky tests

Version 2.0.1 (2016-02-15)
--------------------------
* Bugfix: If zone labels are numbers, like "1", "2", etc., then the draggables would not match with that zone (you cannot drop the draggables onto that zone) (PR #54)
* Bugfix: If two zones had the same name/label, the block would not work properly. (PR #54)
* Bugfix: If the platform's locale is set to a language like "de" or "eo" that formats numbers like "3,14", then the "Maximum Score" field appears blank when editing a dndv2 exercise in Studio. Attempting to save the exercise with that field blank causes a 500. (PR #54)

Version 2.0.0 (2016-01-29)
------------------------

A brand new release of the Drag and Drop XBlock, featuring major UX improvements, new features, and accessibility enhancements.
