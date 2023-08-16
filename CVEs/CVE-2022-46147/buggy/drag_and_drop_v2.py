# -*- coding: utf-8 -*- # pylint: disable=too-many-lines
#
""" Drag and Drop v2 XBlock """

# Imports ###########################################################

from __future__ import absolute_import
from collections import Counter

import copy
import json
import logging

import six.moves.urllib.error  # pylint: disable=import-error
import six.moves.urllib.parse  # pylint: disable=import-error
import six.moves.urllib.request  # pylint: disable=import-error
import six
import pkg_resources
import webob

from django.utils import translation
from xblock.core import XBlock
from xblock.exceptions import JsonHandlerError
from xblock.fields import Boolean, Dict, Float, Integer, Scope, String
from xblock.scorable import ScorableXBlockMixin, Score
from web_fragments.fragment import Fragment
from xblockutils.resources import ResourceLoader
from xblockutils.settings import ThemableXBlockMixin, XBlockWithSettingsMixin

from .compat import get_grading_ignore_decoys_waffle_flag
from .default_data import DEFAULT_DATA
from .utils import (
    Constants, SHOWANSWER, DummyTranslationService, FeedbackMessage,
    FeedbackMessages, ItemStats, StateMigration, _clean_data, _
)

# Globals ###########################################################

loader = ResourceLoader(__name__)
logger = logging.getLogger(__name__)


# Classes ###########################################################


# pylint: disable=bad-continuation
@XBlock.wants('settings')
@XBlock.wants('replace_urls')
@XBlock.wants('user')  # Using `needs` breaks the Course Outline page in Maple.
@XBlock.needs('i18n')
class DragAndDropBlock(
    ScorableXBlockMixin,
    XBlock,
    XBlockWithSettingsMixin,
    ThemableXBlockMixin
):
    """
    XBlock that implements a friendly Drag-and-Drop problem
    """

    CATEGORY = "drag-and-drop-v2"

    SOLUTION_CORRECT = "correct"
    SOLUTION_PARTIAL = "partial"
    SOLUTION_INCORRECT = "incorrect"

    GRADE_FEEDBACK_CLASSES = {
        SOLUTION_CORRECT: FeedbackMessages.MessageClasses.CORRECT_SOLUTION,
        SOLUTION_PARTIAL: FeedbackMessages.MessageClasses.PARTIAL_SOLUTION,
        SOLUTION_INCORRECT: FeedbackMessages.MessageClasses.INCORRECT_SOLUTION,
    }

    PROBLEM_FEEDBACK_CLASSES = {
        SOLUTION_CORRECT: FeedbackMessages.MessageClasses.CORRECT_SOLUTION,
        SOLUTION_PARTIAL: None,
        SOLUTION_INCORRECT: None
    }

    display_name = String(
        display_name=_("Title"),
        help=_("The title of the drag and drop problem. The title is displayed to learners."),
        scope=Scope.settings,
        default=_("Drag and Drop"),
        enforce_type=True,
    )

    mode = String(
        display_name=_("Mode"),
        help=_(
            "Standard mode: the problem provides immediate feedback each time "
            "a learner drops an item on a target zone. "
            "Assessment mode: the problem provides feedback only after "
            "a learner drops all available items on target zones."
        ),
        scope=Scope.settings,
        values=[
            {"display_name": _("Standard"), "value": Constants.STANDARD_MODE},
            {"display_name": _("Assessment"), "value": Constants.ASSESSMENT_MODE},
        ],
        default=Constants.STANDARD_MODE,
        enforce_type=True,
    )

    max_attempts = Integer(
        display_name=_("Maximum attempts"),
        help=_(
            "Defines the number of times a student can try to answer this problem. "
            "If the value is not set, infinite attempts are allowed."
        ),
        scope=Scope.settings,
        default=None,
        enforce_type=True,
    )

    show_title = Boolean(
        display_name=_("Show title"),
        help=_("Display the title to the learner?"),
        scope=Scope.settings,
        default=True,
        enforce_type=True,
    )

    question_text = String(
        display_name=_("Problem text"),
        help=_("The description of the problem or instructions shown to the learner."),
        scope=Scope.settings,
        default="",
        enforce_type=True,
    )

    show_question_header = Boolean(
        display_name=_('Show "Problem" heading'),
        help=_('Display the heading "Problem" above the problem text?'),
        scope=Scope.settings,
        default=True,
        enforce_type=True,
    )
    showanswer = String(
        display_name=_("Show answer"),
        help=_("Defines when to show the answer to the problem. "
               "A default value can be set in Advanced Settings. "
               "To revert setting a custom value, choose the 'Default' option."),
        scope=Scope.settings,
        default=SHOWANSWER.FINISHED,
        values=[
            {"display_name": _("Default"), "value": SHOWANSWER.DEFAULT},
            {"display_name": _("Always"), "value": SHOWANSWER.ALWAYS},
            {"display_name": _("Answered"), "value": SHOWANSWER.ANSWERED},
            {"display_name": _("Attempted or Past Due"), "value": SHOWANSWER.ATTEMPTED},
            {"display_name": _("Closed"), "value": SHOWANSWER.CLOSED},
            {"display_name": _("Finished"), "value": SHOWANSWER.FINISHED},
            {"display_name": _("Correct or Past Due"), "value": SHOWANSWER.CORRECT_OR_PAST_DUE},
            {"display_name": _("Past Due"), "value": SHOWANSWER.PAST_DUE},
            {"display_name": _("Never"), "value": SHOWANSWER.NEVER},
            {"display_name": _("After All Attempts"), "value": SHOWANSWER.AFTER_ALL_ATTEMPTS},
            {"display_name": _("After All Attempts or Correct"), "value": SHOWANSWER.AFTER_ALL_ATTEMPTS_OR_CORRECT},
            {"display_name": _("Attempted"), "value": SHOWANSWER.ATTEMPTED_NO_PAST_DUE},
        ],
        enforce_type=True,
    )

    weight = Float(
        display_name=_("Problem Weight"),
        help=_("Defines the number of points the problem is worth."),
        scope=Scope.settings,
        default=1,
        enforce_type=True,
    )

    item_background_color = String(
        display_name=_("Item background color"),
        help=_("The background color of draggable items in the problem (example: 'blue' or '#0000ff')."),
        scope=Scope.settings,
        default="",
        enforce_type=True,
    )

    item_text_color = String(
        display_name=_("Item text color"),
        help=_("Text color to use for draggable items (example: 'white' or '#ffffff')."),
        scope=Scope.settings,
        default="",
        enforce_type=True,
    )

    max_items_per_zone = Integer(
        display_name=_("Maximum items per zone"),
        help=_("This setting limits the number of items that can be dropped into a single zone."),
        scope=Scope.settings,
        default=None,
        enforce_type=True,
    )

    data = Dict(
        display_name=_("Problem data"),
        help=_(
            "Information about zones, items, feedback, explanation and background image for this problem. "
            "This information is derived from the input that a course author provides via the interactive editor "
            "when configuring the problem."
        ),
        scope=Scope.content,
        default=DEFAULT_DATA,
        enforce_type=True,
    )

    item_state = Dict(
        help=_("Information about current positions of items that a learner has dropped on the target image."),
        scope=Scope.user_state,
        default={},
        enforce_type=True,
    )

    attempts = Integer(
        help=_("Number of attempts learner used"),
        scope=Scope.user_state,
        default=0,
        enforce_type=True,
    )

    completed = Boolean(
        help=_("Indicates whether a learner has completed the problem at least once"),
        scope=Scope.user_state,
        default=False,
        enforce_type=True,
    )

    grade = Float(
        help=_("DEPRECATED. Keeps maximum score achieved by student as a weighted value."),
        scope=Scope.user_state,
        default=0
    )

    raw_earned = Float(
        help=_("Keeps maximum score achieved by student as a raw value between 0 and 1."),
        scope=Scope.user_state,
        default=0,
        enforce_type=True,
    )

    raw_possible = Float(
        help=_("Maximum score available of the problem as a raw value between 0 and 1."),
        scope=Scope.user_state,
        default=1,
        enforce_type=True,
    )

    block_settings_key = 'drag-and-drop-v2'

    @property
    def score(self):
        """
        Returns learners saved score.
        """
        return Score(self.raw_earned, self.raw_possible)

    def max_score(self):  # pylint: disable=no-self-use
        """
        Return the problem's max score, which for DnDv2 always equals 1.
        Required by the grading system in the LMS.
        """
        return 1

    def get_score(self):
        """
        Returns user's current (saved) score for the problem as raw values.
        """
        if self._get_raw_earned_if_set() is None:
            self.raw_earned = self._learner_raw_score()
        return Score(self.raw_earned, self.raw_possible)

    def set_score(self, score):
        """
        Sets the score on this block.
        Takes a Score namedtuple containing a raw
        score and possible max (for this block, we expect that this will
        always be 1).
        """
        self.raw_earned = score.raw_earned
        self.raw_possible = score.raw_possible

    def calculate_score(self):
        """
        Returns a newly-calculated raw score on the problem for the learner
        based on the learner's current state.
        """
        return Score(self._learner_raw_score(), self.max_score())

    def has_submitted_answer(self):
        """
        Returns True if the user has made a submission.
        """
        return self.fields['raw_earned'].is_set_on(self) or self.fields['grade'].is_set_on(self)

    def weighted_grade(self):
        """
        Returns the block's current saved grade multiplied by the block's
        weight- the number of points earned by the learner.
        """
        if self.raw_possible <= 0:
            return None

        if self.weight is None:
            return self.raw_earned

        weighted_earned = self.raw_earned * self.weight / self.raw_possible
        return weighted_earned

    def _learner_raw_score(self):
        """
        Calculate raw score for learner submission.

        As it is calculated as ratio of correctly placed (or left in bank in case of decoys) items to
        total number of items, it lays in interval [0..1]
        """
        correct_count, total_count = self._get_item_stats()
        return correct_count / float(total_count)

    @staticmethod
    def _get_statici18n_js_url():
        """
        Returns the Javascript translation file for the currently selected language, if any found by
        `pkg_resources`
        """
        lang_code = translation.get_language()
        if not lang_code:
            return None
        text_js = 'public/js/translations/{lang_code}/text.js'
        country_code = lang_code.split('-')[0]
        for code in (translation.to_locale(lang_code), lang_code, country_code):
            if pkg_resources.resource_exists(loader.module_name, text_js.format(lang_code=code)):
                return text_js.format(lang_code=code)
        return None

    @XBlock.supports("multi_device")  # Enable this block for use in the mobile app via webview
    def student_view(self, context):
        """
        Player view, displayed to the student
        """

        fragment = Fragment()
        fragment.add_content(loader.render_django_template('/templates/html/drag_and_drop.html',
                                                           i18n_service=self.i18n_service))
        css_urls = (
            'public/css/drag_and_drop.css',
        )
        js_urls = [
            'public/js/vendor/virtual-dom-1.3.0.min.js',
            'public/js/drag_and_drop.js',
        ]

        statici18n_js_url = self._get_statici18n_js_url()
        if statici18n_js_url:
            js_urls.append(statici18n_js_url)

        for css_url in css_urls:
            fragment.add_css_url(self.runtime.local_resource_url(self, css_url))
        for js_url in js_urls:
            fragment.add_javascript_url(self.runtime.local_resource_url(self, js_url))

        self.include_theme_files(fragment)

        fragment.initialize_js('DragAndDropBlock', self.student_view_data())

        return fragment

    def student_view_data(self, context=None):
        """
        Get the configuration data for the student_view.
        The configuration is all the settings defined by the author, except for correct answers
        and feedback.
        """

        def items_without_answers():
            """
            Removes feedback and answer from items
            """
            items = copy.deepcopy(self.data.get('items', ''))
            for item in items:
                del item['feedback']
                # Use item.pop to remove both `item['zone']` and `item['zones']`; we don't have
                # a guarantee that either will be present, so we can't use `del`. Legacy instances
                # will have `item['zone']`, while current versions will have `item['zones']`.
                item.pop('zone', None)
                item.pop('zones', None)
                # Fall back on "backgroundImage" to be backward-compatible.
                image_url = item.get('imageURL') or item.get('backgroundImage')
                if image_url:
                    item['expandedImageURL'] = self._expand_static_url(image_url)
                else:
                    item['expandedImageURL'] = ''
            return items

        return {
            "block_id": six.text_type(self.scope_ids.usage_id),
            "display_name": self.display_name,
            "type": self.CATEGORY,
            "weight": self.weight,
            "mode": self.mode,
            "zones": self.zones,
            "max_attempts": self.max_attempts,
            "graded": getattr(self, 'graded', False),
            "weighted_max_score": self.max_score() * self.weight,
            "max_items_per_zone": self.max_items_per_zone,
            # SDK doesn't supply url_name.
            "url_name": getattr(self, 'url_name', ''),
            "display_zone_labels": self.data.get('displayLabels', False),
            "display_zone_borders": self.data.get('displayBorders', False),
            "display_zone_borders_dragging": self.data.get('displayBordersDragging', False),
            "items": items_without_answers(),
            "title": self.display_name,
            "show_title": self.show_title,
            "problem_text": self.question_text,
            "show_problem_header": self.show_question_header,
            "target_img_expanded_url": self.target_img_expanded_url,
            "target_img_description": self.target_img_description,
            "item_background_color": self.item_background_color or None,
            "item_text_color": self.item_text_color or None,
            "has_deadline_passed": self.has_submission_deadline_passed,
            "answer_available": self.is_answer_available,
            # final feedback (data.feedback.finish) is not included - it may give away answers.
        }

    def studio_view(self, context):
        """
        Editing view in Studio
        """
        js_templates = loader.load_unicode('/templates/html/js_templates.html')
        # Get an 'id_suffix' string that is unique for this block.
        # We append it to HTML element ID attributes to ensure multiple instances of the DnDv2 block
        # on the same page don't share the same ID value.
        # We avoid using ID attributes in preference to classes, but sometimes we still need IDs to
        # connect 'for' and 'aria-describedby' attributes to the associated elements.
        id_suffix = self._get_block_id()
        js_templates = js_templates.replace('{{id_suffix}}', id_suffix)
        context = {
            'js_templates': js_templates,
            'id_suffix': id_suffix,
            'fields': self.fields,
            'showanswer_set': self._field_data.has(self, 'showanswer'),  # If false, we're using an inherited value.
            'self': self,
            'data': six.moves.urllib.parse.quote(json.dumps(self.data)),
        }

        fragment = Fragment()
        fragment.add_content(loader.render_django_template('/templates/html/drag_and_drop_edit.html',
                                                           context=context,
                                                           i18n_service=self.i18n_service))
        css_urls = (
            'public/css/drag_and_drop_edit.css',
        )
        js_urls = [
            'public/js/vendor/handlebars-v1.1.2.js',
            'public/js/drag_and_drop_edit.js',
        ]

        statici18n_js_url = self._get_statici18n_js_url()
        if statici18n_js_url:
            js_urls.append(statici18n_js_url)

        for css_url in css_urls:
            fragment.add_css_url(self.runtime.local_resource_url(self, css_url))
        for js_url in js_urls:
            fragment.add_javascript_url(self.runtime.local_resource_url(self, js_url))

        # Do a bit of manipulation so we get the appearance of a list of zone options on
        # items that still have just a single zone stored

        items = self.data.get('items', [])

        for item in items:
            zones = self.get_item_zones(item['id'])
            # Note that we appear to be mutating the state of the XBlock here, but because
            # the change won't be committed, we're actually just affecting the data that
            # we're going to send to the client, not what's saved in the backing store.
            item['zones'] = zones
            item.pop('zone', None)

        fragment.initialize_js('DragAndDropEditBlock', {
            'data': self.data,
            'target_img_expanded_url': self.target_img_expanded_url,
            'default_background_image_url': self.default_background_image_url,
        })

        return fragment

    @XBlock.json_handler
    def studio_submit(self, submissions, suffix=''):
        """
        Handles studio save.
        """
        self.display_name = submissions['display_name']
        self.mode = submissions['mode']
        self.max_attempts = submissions['max_attempts']
        if (showanswer := submissions['showanswer']) != self.showanswer:
            self.showanswer = showanswer
        if showanswer == SHOWANSWER.DEFAULT:
            del self.showanswer
        self.show_title = submissions['show_title']
        self.question_text = submissions['problem_text']
        self.show_question_header = submissions['show_problem_header']
        self.weight = float(submissions['weight'])
        self.item_background_color = submissions['item_background_color']
        self.item_text_color = submissions['item_text_color']
        self.max_items_per_zone = self._get_max_items_per_zone(submissions)
        self.data = submissions['data']

        return {
            'result': 'success',
        }

    def _get_block_id(self):
        """
        Return unique ID of this block. Useful for HTML ID attributes.
        Works both in LMS/Studio and workbench runtimes:
        - In LMS/Studio, use the location.html_id method.
        - In the workbench, use the usage_id.
        """
        if hasattr(self, 'location'):
            return self.location.html_id()  # pylint: disable=no-member
        else:
            return six.text_type(self.scope_ids.usage_id)

    @staticmethod
    def _get_max_items_per_zone(submissions):
        """
        Parses Max items per zone value coming from editor.

        Returns:
            * None if invalid value is passed (i.e. not an integer)
            * None if value is parsed into zero or negative integer
            * Positive integer otherwise.

        Examples:
            * _get_max_items_per_zone(None) -> None
            * _get_max_items_per_zone('string') -> None
            * _get_max_items_per_zone('-1') -> None
            * _get_max_items_per_zone(-1) -> None
            * _get_max_items_per_zone('0') -> None
            * _get_max_items_per_zone('') -> None
            * _get_max_items_per_zone('42') -> 42
            * _get_max_items_per_zone(42) -> 42
        """
        raw_max_items_per_zone = submissions.get('max_items_per_zone', None)

        # Entries that aren't numbers should be treated as null. We assume that if we can
        # turn it into an int, a number was submitted.
        try:
            max_attempts = int(raw_max_items_per_zone)
            if max_attempts > 0:
                return max_attempts
            else:
                return None
        except (ValueError, TypeError):
            return None

    @XBlock.json_handler
    def drop_item(self, item_attempt, suffix=''):
        """
        Handles dropping item into a zone.
        """
        self._validate_drop_item(item_attempt)

        if self.mode == Constants.ASSESSMENT_MODE:
            return self._drop_item_assessment(item_attempt)
        elif self.mode == Constants.STANDARD_MODE:
            return self._drop_item_standard(item_attempt)
        else:
            raise JsonHandlerError(
                500,
                self.i18n_service.gettext("Unknown DnDv2 mode {mode} - course is misconfigured").format(self.mode)
            )

    @XBlock.json_handler
    def do_attempt(self, data, suffix=''):
        """
        Checks submitted solution and returns feedback.

        Raises:
             * JsonHandlerError with 400 error code in standard mode.
             * JsonHandlerError with 409 error code if no more attempts left
        """
        self._validate_do_attempt()

        self.attempts += 1
        # pylint: disable=fixme
        # TODO: Refactor this method to "freeze" item_state and pass it to methods that need access to it.
        # These implicit dependencies between methods exist because most of them use `item_state` or other
        # fields, either as an "input" (i.e. read value) or as output (i.e. set value) or both. As a result,
        # incorrect order of invocation causes issues:
        self._mark_complete_and_publish_grade()  # must happen before _get_feedback - sets grade
        correct = self.is_correct  # must happen before manipulating item_state - reads item_state

        overall_feedback_msgs, misplaced_ids = self._get_feedback(include_item_feedback=True)

        misplaced_items = []
        for item_id in misplaced_ids:
            # Don't delete misplaced item states on the final attempt.
            if self.attempts_remain:
                del self.item_state[item_id]
            misplaced_items.append(self._get_item_definition(int(item_id)))

        feedback_msgs = [FeedbackMessage(item['feedback']['incorrect'], None) for item in misplaced_items]
        return {
            'correct': correct,
            'attempts': self.attempts,
            'grade': self._get_weighted_earned_if_set(),
            'misplaced_items': list(misplaced_ids),
            'feedback': self._present_feedback(feedback_msgs),
            'overall_feedback': self._present_feedback(overall_feedback_msgs),
            "answer_available": self.is_answer_available,
        }

    @XBlock.json_handler
    def publish_event(self, data, suffix=''):
        """
        Handler to publish XBlock event from frontend
        """
        try:
            event_type = data.pop('event_type')
        except KeyError:
            return {'result': 'error', 'message': 'Missing event_type in JSON data'}

        self.runtime.publish(self, event_type, data)
        return {'result': 'success'}

    @XBlock.json_handler
    def reset(self, data, suffix=''):
        """
        Resets problem to initial state
        """
        self.item_state = {}
        return self._get_user_state()

    @XBlock.json_handler
    def show_answer(self, data, suffix=''):
        """
        Returns correct answer in assessment mode.

        Raises:
             * JsonHandlerError with 400 error code in standard mode.
             * JsonHandlerError with 409 error code if the answer is unavailable.
        """
        if self.mode != Constants.ASSESSMENT_MODE:
            raise JsonHandlerError(
                400,
                self.i18n_service.gettext("show_answer handler should only be called for assessment mode")
            )
        if not self.is_answer_available:
            raise JsonHandlerError(
                409,
                self.i18n_service.gettext("The answer is unavailable")
            )

        answer = self._get_correct_state()

        if explanation := self.data.get('explanation', '').strip():
            if replace_urls_service := self.runtime.service(self, 'replace_urls'):
                explanation = replace_urls_service.replace_urls(explanation)

            # pylint: disable=fixme
            # TODO: No longer needed after Maple.
            else:
                try:
                    explanation = self.runtime.replace_urls(explanation)
                    explanation = self.runtime.replace_course_urls(explanation)
                    explanation = self.runtime.replace_jump_to_id_urls(explanation)
                except (TypeError, AttributeError):
                    logger.debug('Unable to perform URL substitution on the explanation: %s', explanation)

            answer['explanation'] = explanation
        return answer

    @XBlock.json_handler
    def expand_static_url(self, url, suffix=''):
        """ AJAX-accessible handler for expanding URLs to static [image] files """
        return {'url': self._expand_static_url(url)}

    @property
    def i18n_service(self):
        """ Obtains translation service """
        i18n_service = self.runtime.service(self, "i18n")
        if i18n_service:
            return i18n_service
        else:
            return DummyTranslationService()

    @property
    def target_img_expanded_url(self):
        """ Get the expanded URL to the target image (the image items are dragged onto). """
        if self.data.get("targetImg"):
            return self._expand_static_url(self.data["targetImg"])
        else:
            return self.default_background_image_url

    @property
    def target_img_description(self):
        """ Get the description for the target image (the image items are dragged onto). """
        return self.data.get("targetImgDescription", "")

    @property
    def default_background_image_url(self):
        """ The URL to the default background image, shown when no custom background is used """
        return self.runtime.local_resource_url(self, "public/img/triangle.png")

    @property
    def attempts_remain(self):
        """
        Checks if current student still have more attempts.
        """
        return self.max_attempts is None or self.max_attempts == 0 or self.attempts < self.max_attempts

    @property
    def has_submission_deadline_passed(self):
        """
        Returns a boolean indicating if the submission is past its deadline.

        Using the `has_deadline_passed` method from InheritanceMixin which gets
        added on the LMS/Studio, return if the submission is past its due date.
        If the method not found, which happens for pure DragAndDropXblock,
        return False which makes sure submission checks don't affect other
        functionality.
        """
        if hasattr(self, "has_deadline_passed"):
            return self.has_deadline_passed()  # pylint: disable=no-member
        else:
            return False

    @property
    def closed(self):
        """
        Is the student still allowed to submit answers?
        """
        if not self.attempts_remain:
            return True
        if self.has_submission_deadline_passed:
            return True

        return False

    @property
    def is_attempted(self):
        """
        Has the problem been attempted?
        """
        return self.attempts > 0

    @property
    def is_finished(self):
        """
        Returns True if answer is closed or answer is correct.
        """
        return self.closed or self.is_correct

    @property
    def is_answer_available(self):
        """
        Is student allowed to see an answer?
        """
        permission_functions = {
            SHOWANSWER.NEVER: lambda: False,
            SHOWANSWER.ATTEMPTED: lambda: self.is_attempted or self.has_submission_deadline_passed,
            SHOWANSWER.ANSWERED: lambda: self.is_correct,
            SHOWANSWER.CLOSED: lambda: self.closed,
            SHOWANSWER.FINISHED: lambda: self.is_finished,
            SHOWANSWER.CORRECT_OR_PAST_DUE: lambda: self.is_correct or self.has_submission_deadline_passed,
            SHOWANSWER.PAST_DUE: lambda: self.has_submission_deadline_passed,
            SHOWANSWER.ALWAYS: lambda: True,
            SHOWANSWER.AFTER_ALL_ATTEMPTS: lambda: not self.attempts_remain,
            SHOWANSWER.AFTER_ALL_ATTEMPTS_OR_CORRECT: lambda: not self.attempts_remain or self.is_correct,
            SHOWANSWER.ATTEMPTED_NO_PAST_DUE: lambda: self.is_attempted,
        }

        if self.mode != Constants.ASSESSMENT_MODE:
            return False

        user_is_staff = False
        if user_service := self.runtime.service(self, 'user'):
            user_is_staff = user_service.get_current_user().opt_attrs.get(Constants.ATTR_KEY_USER_IS_STAFF)

        if self.showanswer not in [SHOWANSWER.NEVER, ''] and user_is_staff:
            # Staff users can see the answer unless the problem explicitly prevents it.
            return True

        check_permissions_function = permission_functions.get(self.showanswer, lambda: False)
        return check_permissions_function()

    @XBlock.handler
    def student_view_user_state(self, request, suffix=''):
        """ GET all user-specific data, and any applicable feedback """
        data = self._get_user_state()
        return webob.Response(body=json.dumps(data).encode('utf-8'), content_type='application/json')

    def _validate_do_attempt(self):
        """
        Validates if `do_attempt` handler should be executed
        """
        if self.mode != Constants.ASSESSMENT_MODE:
            raise JsonHandlerError(
                400,
                self.i18n_service.gettext("do_attempt handler should only be called for assessment mode")
            )
        if not self.attempts_remain:
            raise JsonHandlerError(
                409,
                self.i18n_service.gettext("Max number of attempts reached")
            )
        if self.has_submission_deadline_passed:
            raise JsonHandlerError(
                409,
                self.i18n_service.gettext("Submission deadline has passed.")
            )

    def _get_feedback(self, include_item_feedback=False):
        """
        Builds overall feedback for both standard and assessment modes
        """
        answer_correctness = self._answer_correctness()

        if self.mode == Constants.STANDARD_MODE or not self.attempts:
            if answer_correctness == self.SOLUTION_CORRECT:
                feedback_key = 'finish'
                message_class = FeedbackMessages.MessageClasses.FINAL_FEEDBACK
            else:
                feedback_key = 'start'
                message_class = FeedbackMessages.MessageClasses.INITIAL_FEEDBACK
            return [FeedbackMessage(self.data['feedback'][feedback_key], message_class)], set()

        items = self._get_item_raw_stats()
        missing_ids = items.required - items.placed
        misplaced_ids = items.placed - items.correctly_placed

        feedback_msgs = []

        def _add_msg_if_exists(ids_list, message_template, message_class):
            """ Adds message to feedback messages if corresponding items list is not empty """
            if ids_list:
                message = message_template(len(ids_list), self.i18n_service.ngettext)
                feedback_msgs.append(FeedbackMessage(message, message_class))

        if self.item_state or include_item_feedback:
            _add_msg_if_exists(
                items.correctly_placed,
                FeedbackMessages.correctly_placed,
                FeedbackMessages.MessageClasses.CORRECTLY_PLACED
            )

            # Misplaced items are not returned to the bank on the final attempt.
            if self.attempts_remain:
                misplaced_template = FeedbackMessages.misplaced_returned
            else:
                misplaced_template = FeedbackMessages.misplaced

            _add_msg_if_exists(misplaced_ids, misplaced_template, FeedbackMessages.MessageClasses.MISPLACED)
            _add_msg_if_exists(missing_ids, FeedbackMessages.not_placed, FeedbackMessages.MessageClasses.NOT_PLACED)

        grade_feedback_class = self.GRADE_FEEDBACK_CLASSES.get(answer_correctness, None)

        if self.weight > 0:
            if self.attempts_remain:
                grade_feedback_template = FeedbackMessages.GRADE_FEEDBACK_TPL
            else:
                grade_feedback_template = FeedbackMessages.FINAL_ATTEMPT_TPL

            feedback_msgs.append(FeedbackMessage(
                self.i18n_service.gettext(grade_feedback_template).format(score=self.weighted_grade()),
                grade_feedback_class)
            )

        problem_feedback_class = None

        if self.attempts_remain and (misplaced_ids or missing_ids):
            problem_feedback_message = self.data['feedback']['start']
            problem_feedback_class = FeedbackMessages.MessageClasses.INITIAL_FEEDBACK
        else:
            problem_feedback_message = self.data['feedback']['finish']
            problem_feedback_class = FeedbackMessages.MessageClasses.FINAL_FEEDBACK

        feedback_msgs.append(FeedbackMessage(problem_feedback_message, problem_feedback_class))

        return feedback_msgs, misplaced_ids

    @staticmethod
    def _present_feedback(feedback_messages):
        """
        Transforms feedback messages into format expected by frontend code
        """
        return [
            {"message": msg.message, "message_class": msg.message_class}
            for msg in feedback_messages
            if msg.message
        ]

    def _drop_item_standard(self, item_attempt):
        """
        Handles dropping item to a zone in standard mode.
        """
        item = self._get_item_definition(item_attempt['val'])

        is_correct = self._is_attempt_correct(item_attempt)  # Student placed item in a correct zone
        if is_correct:  # In standard mode state is only updated when attempt is correct
            self.item_state[str(item['id'])] = self._make_state_from_attempt(item_attempt, is_correct)

        self._mark_complete_and_publish_grade()  # must happen before _get_feedback
        self._publish_item_dropped_event(item_attempt, is_correct)

        item_feedback_key = 'correct' if is_correct else 'incorrect'
        item_feedback = FeedbackMessage(self._expand_static_url(item['feedback'][item_feedback_key]), None)
        overall_feedback, __ = self._get_feedback()

        return {
            'correct': is_correct,
            'grade': self._get_weighted_earned_if_set(),
            'finished': self.is_correct,
            'overall_feedback': self._present_feedback(overall_feedback),
            'feedback': self._present_feedback([item_feedback])
        }

    def _drop_item_assessment(self, item_attempt):
        """
        Handles dropping item into a zone in assessment mode
        """
        if not self.attempts_remain:
            raise JsonHandlerError(409, self.i18n_service.gettext("Max number of attempts reached"))

        item = self._get_item_definition(item_attempt['val'])
        is_correct = self._is_attempt_correct(item_attempt)
        if item_attempt['zone'] is None:
            self.item_state.pop(str(item['id']), None)
            self._publish_item_to_bank_event(item['id'], is_correct)
        else:
            # State is always updated in assessment mode to store intermediate item positions
            self.item_state[str(item['id'])] = self._make_state_from_attempt(item_attempt, is_correct)
            self._publish_item_dropped_event(item_attempt, is_correct)

        return {}

    def _validate_drop_item(self, item):
        """
        Validates `drop_item` parameters. Assessment mode allows returning
        items to the bank, so validation is unnecessary.
        """
        if self.mode != Constants.ASSESSMENT_MODE:
            zone = self._get_zone_by_uid(item['zone'])
            if not zone:
                raise JsonHandlerError(400, "Item zone data invalid.")

    @staticmethod
    def _make_state_from_attempt(attempt, correct):
        """
        Converts "attempt" data coming from browser into "state" entry stored in item_state
        """
        return {
            'zone': attempt['zone'],
            'correct': correct
        }

    def _mark_complete_and_publish_grade(self):
        """
        Helper method to update `self.completed` and submit grade event if appropriate conditions met.
        """
        # pylint: disable=fixme
        # TODO: (arguable) split this method into "clean" functions (with no side effects and implicit state)
        # This method implicitly depends on self.item_state (via is_correct and _learner_raw_score)
        # and also updates self.raw_earned if some conditions are met. As a result this method implies some order of
        # invocation:
        # * it should be called after learner-caused updates to self.item_state is applied
        # * it should be called before self.item_state cleanup is applied (i.e. returning misplaced items to item bank)
        # * it should be called before any method that depends on self.raw_earned (i.e. self._get_feedback)

        # Splitting it into a "clean" functions will allow to capture this implicit invocation order in caller method
        # and help avoid bugs caused by invocation order violation in future.

        # There's no going back from "completed" status to "incomplete"
        self.completed = self.completed or self.is_correct or not self.attempts_remain

        current_raw_earned = self._learner_raw_score()
        # ... and from higher grade to lower
        # if we have an old-style (i.e. unreliable) grade, override no matter what
        saved_raw_earned = self._get_raw_earned_if_set()

        current_raw_earned_is_greater = False
        if current_raw_earned is None or saved_raw_earned is None:
            current_raw_earned_is_greater = True

        if current_raw_earned is not None and saved_raw_earned is not None and current_raw_earned > saved_raw_earned:
            current_raw_earned_is_greater = True

        if current_raw_earned is None or current_raw_earned_is_greater:
            self.set_score(Score(current_raw_earned, self.max_score()))
            self.publish_grade(self.score)

        # and no matter what - emit progress event for current user
        self.runtime.publish(self, "progress", {})

    def _publish_item_dropped_event(self, attempt, is_correct):
        """
        Publishes item dropped event.
        """
        item = self._get_item_definition(attempt['val'])
        # attempt should already be validated here - not doing the check for existing zone again
        zone = self._get_zone_by_uid(attempt['zone'])

        item_label = item.get("displayName")
        if not item_label:
            item_label = item.get("imageURL")

        self.runtime.publish(self, 'edx.drag_and_drop_v2.item.dropped', {
            'item': item_label,
            'item_id': item['id'],
            'location': zone.get("title"),
            'location_id': zone.get("uid"),
            'is_correct': is_correct,
        })

    def _publish_item_to_bank_event(self, item_id, is_correct):
        """
        Publishes event when item moved back to the bank in assessment mode.
        """
        item = self._get_item_definition(item_id)

        item_label = item.get("displayName")
        if not item_label:
            item_label = item.get("imageURL")

        self.runtime.publish(self, 'edx.drag_and_drop_v2.item.dropped', {
            'item': item_label,
            'item_id': item['id'],
            'location': 'item bank',
            'location_id': -1,
            'is_correct': is_correct,
        })

    def publish_grade(self, score=None, only_if_higher=None):
        """
        Publishes the student's current grade to the system as an event
        """
        if not score:
            score = self.score
        self._publish_grade(score, only_if_higher)
        return {'grade': self.score.raw_earned, 'max_grade': self.score.raw_possible}

    def _is_attempt_correct(self, attempt):
        """
        Check if the item was placed correctly.
        """
        correct_zones = self.get_item_zones(attempt['val'])
        if correct_zones == [] and attempt['zone'] is None and self.mode == Constants.ASSESSMENT_MODE:
            return True
        return attempt['zone'] in correct_zones

    def _expand_static_url(self, url):
        """
        This is required to make URLs like '/static/dnd-test-image.png' work (note: that is the
        only portable URL format for static files that works across export/import and reruns).
        This method is unfortunately a bit hackish since XBlock does not provide a low-level API
        for this.
        """
        if hasattr(self.runtime, 'replace_urls'):
            url = self.runtime.replace_urls(u'"{}"'.format(url))[1:-1]
        elif hasattr(self.runtime, 'course_id'):
            # edX Studio uses a different runtime for 'studio_view' than 'student_view',
            # and the 'studio_view' runtime doesn't provide the replace_urls API.
            try:
                from common.djangoapps.static_replace import replace_static_urls  # pylint: disable=import-error
                url = replace_static_urls(u'"{}"'.format(url), None, course_id=self.runtime.course_id)[1:-1]
            except ImportError:
                pass
        return url

    def _get_user_state(self):
        """ Get all user-specific data, and any applicable feedback """
        item_state = self._get_item_state()
        # In assessment mode, we do not want to leak the correctness info for individual items to the frontend,
        # so we remove "correct" from all items when in assessment mode.
        if self.mode == Constants.ASSESSMENT_MODE:
            for item in item_state.values():
                del item["correct"]

        overall_feedback_msgs, __ = self._get_feedback()
        if self.mode == Constants.STANDARD_MODE:
            is_finished = self.is_correct
        else:
            is_finished = not self.attempts_remain

        return {
            'items': item_state,
            'finished': is_finished,
            'attempts': self.attempts,
            'grade': self._get_weighted_earned_if_set(),
            'overall_feedback': self._present_feedback(overall_feedback_msgs)
        }

    def _get_correct_state(self):
        """
        Returns one of the possible correct states for the configured data.

        Items dropped by user in the correct zones are retained in those zones.
        Incorrect items are equally distributed among their correct zones (if more than one zone is correct).
        """
        state = {}
        items = copy.deepcopy(self.data.get('items', []))

        zone_count = Counter()
        correct_items = set()

        def _get_preferred_zone(zone_count, zones):
            """
            Returns the zone with the least number of items.
            """
            preferred_zone = None
            for zone in zones:
                # Check if the current zone has the least number of items till now
                if not preferred_zone or zone_count[preferred_zone] > zone_count[zone]:
                    preferred_zone = zone

            zone_count[preferred_zone] += 1
            return preferred_zone

        # Set states of all items dropped in correct zones
        for item_id in self.item_state:
            if self.item_state[item_id]['correct']:
                state[item_id] = self.item_state[item_id]
                correct_items.add(item_id)

                zone = self.item_state[item_id]['zone']
                zone_count[zone] += 1

        # Set states of rest of the items
        for item in items:
            item_id = str(item['id'])
            if item_id not in correct_items:
                zones = item.get('zones')

                # For backwards compatibility
                if zones is None:
                    zones = []
                    zone = item.get('zone')
                    if zone is not None and zone != 'none':
                        zones.append(zone)

                if zones:
                    zone = _get_preferred_zone(zone_count, zones)
                    state[item_id] = {
                        'zone': zone,
                        'correct': True,
                    }

        return {'items': state}

    def _get_item_state(self):
        """
        Returns a copy of the user item state.
        Converts to a dict if data is stored in legacy tuple form.
        """

        # IMPORTANT: this method should always return a COPY of self.item_state - it is called from
        # student_view_user_state handler and the data it returns is manipulated there to hide
        # correctness of items placed.
        state = {}
        migrator = StateMigration(self)

        for item_id, item in six.iteritems(self.item_state):
            state[item_id] = migrator.apply_item_state_migrations(item_id, item)

        return state

    def _get_item_definition(self, item_id):
        """
        Returns definition (settings) for item identified by `item_id`.
        """
        return next(i for i in self.data['items'] if i['id'] == item_id)

    def get_item_zones(self, item_id):
        """
        Returns a list of the zones that are valid options for the item.

        If the item is configured with a list of zones, return that list. If
        the item is configured with a single zone, encapsulate that zone's
        ID in a list and return the list. If the item is not configured with
        any zones, or if it's configured explicitly with no zones, return an
        empty list.
        """
        item = self._get_item_definition(item_id)
        if item.get('zones') is not None:
            return item.get('zones')
        elif item.get('zone') is not None and item.get('zone') != 'none':
            return [item.get('zone')]
        else:
            return []

    @property
    def zones(self):
        """
        Get drop zone data, defined by the author.
        """
        # Convert zone data from old to new format if necessary
        migrator = StateMigration(self)
        return [migrator.apply_zone_migrations(zone) for zone in self.data.get('zones', [])]

    def _get_zone_by_uid(self, uid):
        """
        Given a zone UID, return that zone, or None.
        """
        for zone in self.zones:
            if zone["uid"] == uid:
                return zone
        return None

    def _get_item_stats(self):
        """
        Returns a tuple representing the number of correctly placed items,
        and the total number of items required (including decoy items).
        """
        items = self._get_item_raw_stats()

        correct_count = len(items.correctly_placed)
        total_count = len(items.required)

        if hasattr(self.runtime, 'course_id') and \
                get_grading_ignore_decoys_waffle_flag().is_enabled(self.runtime.course_id):
            return correct_count, total_count

        correct_count += len(items.decoy_in_bank)
        total_count += len(items.decoy)

        return correct_count, total_count

    def _get_item_raw_stats(self):
        """
        Returns a named tuple containing required, decoy, placed, correctly
        placed, and correctly unplaced decoy items.

        Returns:
            namedtuple: (required, placed, correctly_placed, decoy, decoy_in_bank)
                * required - IDs of items that must be placed on the board
                * placed - IDs of items actually placed on the board
                * correctly_placed - IDs of items that were placed correctly
                * decoy - IDs of decoy items
                * decoy_in_bank - IDs of decoy items that were unplaced
        """
        item_state = self._get_item_state()

        all_items = set(str(item['id']) for item in self.data['items'])
        required = set(item_id for item_id in all_items if self.get_item_zones(int(item_id)) != [])
        placed = set(item_id for item_id in all_items if item_id in item_state)
        correctly_placed = set(item_id for item_id in placed if item_state[item_id]['correct'])
        decoy = all_items - required
        decoy_in_bank = set(item_id for item_id in decoy if item_id not in item_state)

        return ItemStats(required, placed, correctly_placed, decoy, decoy_in_bank)

    def _get_raw_earned_if_set(self):
        """
        Returns student's grade if already explicitly set, otherwise returns None.
        This is different from self.raw_earned which returns 0 by default.
        """
        if self.fields['raw_earned'].is_set_on(self):
            return self.raw_earned
        else:
            return None

    def _get_weighted_earned_if_set(self):
        """
        Returns student's grade with the problem weight applied if set, otherwise
        None.
        """
        if self.fields['raw_earned'].is_set_on(self):
            return self.weighted_grade()
        else:
            return None

    def _answer_correctness(self):
        """
        Checks answer correctness:

        Returns:
            string: Correct/Incorrect/Partial
                * Correct: All items are at their correct place.
                * Partial: Some items are at their correct place.
                * Incorrect: None items are at their correct place.
        """
        correct_count, total_count = self._get_item_stats()
        if correct_count == total_count:
            return self.SOLUTION_CORRECT
        elif correct_count == 0:
            return self.SOLUTION_INCORRECT
        else:
            return self.SOLUTION_PARTIAL

    @property
    def is_correct(self):
        """
        Helper - checks if answer is correct

        Returns:
            bool: True if current answer is correct
        """
        return self._answer_correctness() == self.SOLUTION_CORRECT

    @staticmethod
    def workbench_scenarios():
        """
        A canned scenario for display in the workbench.
        """
        return [
            (
                "Drag-and-drop-v2 standard",
                "<vertical_demo><drag-and-drop-v2/></vertical_demo>"
            ),
            (
                "Drag-and-drop-v2 assessment",
                "<vertical_demo><drag-and-drop-v2 mode='assessment' max_attempts='3'/></vertical_demo>"
            ),
        ]

    def index_dictionary(self):
        """
        Return dictionary prepared with module content and type for indexing.
        """
        # return key/value fields in a Python dict object
        # values may be numeric / string or dict
        # default implementation is an empty dict

        xblock_body = super(DragAndDropBlock, self).index_dictionary()

        zones_display_names = {
            "zone_{}_display_name".format(zone_i):
                _clean_data(zone.get("title", ""))
            for zone_i, zone in enumerate(self.data.get("zones", []))
        }

        zones_description = {
            "zone_{}_description".format(zone_i):
                _clean_data(zone.get("description", ""))
            for zone_i, zone in enumerate(self.data.get("zones", []))
        }

        items_display_names = {
            "item_{}_display_name".format(item_i):
                _clean_data(item.get("displayName", ""))
            for item_i, item in enumerate(self.data.get("items", []))
        }

        items_image_description = {
            "item_{}_image_description".format(item_i):
                _clean_data(item.get("imageDescription", ""))
            for item_i, item in enumerate(self.data.get("items", []))
        }

        index_body = {
            "display_name": self.display_name,
            "question_text": _clean_data(self.question_text),
            "background_image_description": self.data.get("targetImgDescription", ""),
        }
        index_body.update(items_display_names)
        index_body.update(items_image_description)
        index_body.update(zones_display_names)
        index_body.update(zones_description)

        if "content" in xblock_body:
            xblock_body["content"].update(index_body)
        else:
            xblock_body["content"] = index_body

        xblock_body["content_type"] = "Drag and Drop"

        return xblock_body
