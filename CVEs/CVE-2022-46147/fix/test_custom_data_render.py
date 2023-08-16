from .test_base import BaseIntegrationTest


class TestCustomDataDragAndDropRendering(BaseIntegrationTest):
    PAGE_TITLE = 'Drag and Drop v2'
    PAGE_ID = 'drag_and_drop_v2'

    def setUp(self):
        super(TestCustomDataDragAndDropRendering, self).setUp()

        scenario_xml = self._get_custom_scenario_xml("data/test_html_data.json")
        self._add_scenario(self.PAGE_ID, self.PAGE_TITLE, scenario_xml)

        self._page = self.go_to_page(self.PAGE_TITLE)

        header1 = self.browser.find_element_by_css_selector('h1')
        self.assertEqual(header1.text, 'XBlock: ' + self.PAGE_TITLE)

    def test_items_rendering(self):
        items = self._get_items()

        self.assertEqual(len(items), 3)
        self.assertIn('<b>1</b>', self.get_element_html(items[0]))
        self.assertIn('<i>2</i>', self.get_element_html(items[1]))
        self.assertIn('<span style="color: red;">X</span>', self.get_element_html(items[2]))

    def test_html_title_renders_properly(self):
        """
        Tests HTML titles are rendered properly
        """
        zones = self._get_zones()
        self.assertEqual(u'Zone\ndroppable\nNo items placed here', zones[0].text)
        self.assertNotEqual(u'Zone <sup>-1</sup>\ndroppable\nNo items placed here', zones[0].text)

    def test_background_image(self):
        bg_image = self.browser.find_element_by_css_selector(".xblock--drag-and-drop .target-img")
        custom_image_url = (
            "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciI"
            "HdpZHRoPSI4MDAiIGhlaWdodD0iNjAwIiBzdHlsZT0iYmFja2dyb3VuZDogI2VlZjsiPjwvc3ZnPg=="
        )
        custom_image_description = "This describes the target image"
        self.assertEqual(bg_image.get_attribute("src"), custom_image_url)
        self.assertEqual(bg_image.get_attribute("alt"), custom_image_description)


class TestZoneTitleAsHTML(BaseIntegrationTest):
    PAGE_TITLE = 'Drag and Drop v2'
    PAGE_ID = 'drag_and_drop_v2'

    def setUp(self):
        super(TestZoneTitleAsHTML, self).setUp()

        scenario_xml = self._get_custom_scenario_xml("data/test_html_titles_data.json")
        self._add_scenario(self.PAGE_ID, self.PAGE_TITLE, scenario_xml)

        self._page = self.go_to_page(self.PAGE_TITLE)

        header1 = self.browser.find_element_by_css_selector('h1')
        self.assertEqual(header1.text, 'XBlock: ' + self.PAGE_TITLE)

    def test_html_title_renders_properly(self):
        """
        Tests HTML titles are rendered properly
        """
        zones = self._get_zones()
        self.assertEqual('Zone\ndroppable\nNo items placed here', zones[0].text)
        self.assertNotEqual('Zone <sup>-1</sup>\ndroppable\nNo items placed here', zones[0].text)
