from __future__ import absolute_import

from ddt import data, ddt, unpack
from selenium.common.exceptions import NoSuchElementException
from workbench import scenarios

from .test_base import BaseIntegrationTest


@ddt
class TestDragAndDropTitleAndProblem(BaseIntegrationTest):
    @unpack
    @data(
        ('Plain text problem 1, header visible.', True),
        ('Plain text problem 2, header hidden.', False),
        ('Problem/instructions with <i>HTML</i> and header.', True),
        ('<span style="color: red;">Span problem, no header</span>', False),
    )
    def test_problem_parameters(self, problem_text, show_problem_header):
        const_page_name = 'Test title and problem parameters'
        const_page_id = 'test_block_title_and_problem'
        scenario_xml = self._make_scenario_xml(
            display_name="Title",
            show_title=True,
            problem_text=problem_text,
            show_problem_header=show_problem_header,
        )
        scenarios.add_xml_scenario(const_page_id, const_page_name, scenario_xml)
        self.addCleanup(scenarios.remove_scenario, const_page_id)

        page = self.go_to_page(const_page_name)
        is_problem_header_visible = len(page.find_elements_by_css_selector('.problem > h4')) > 0
        self.assertEqual(is_problem_header_visible, show_problem_header)

        problem = page.find_element_by_css_selector('.problem > p')
        self.assertEqual(self.get_element_html(problem), problem_text)

    @unpack
    @data(
        ('plain shown', 'title1', True),
        ('plain hidden', 'title2', False),
        ('html shown', 'title with <i>HTML</i>', True),
        ('html hidden', '<span style="color: red;">Title: HTML?</span>', False)
    )
    def test_title_parameters(self, _, display_name, show_title):
        const_page_name = 'Test show title parameter'
        const_page_id = 'test_block_show_title'
        scenario_xml = self._make_scenario_xml(
            display_name=display_name,
            show_title=show_title,
            problem_text='Generic problem',
        )
        scenarios.add_xml_scenario(const_page_id, const_page_name, scenario_xml)
        self.addCleanup(scenarios.remove_scenario, const_page_id)

        page = self.go_to_page(const_page_name)
        if show_title:
            problem_header = page.find_element_by_css_selector('h3.hd.hd-3.problem-header')
            self.assertEqual(self.get_element_html(problem_header), display_name)
        else:
            with self.assertRaises(NoSuchElementException):
                page.find_element_by_css_selector('h3.hd.hd-3.problem-header')
