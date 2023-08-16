require_relative "../../test_helper"

describe TextHelpers::Translation do
  before do
    @helper = Object.send(:include, TextHelpers::Translation).new
  end

  describe "given a stored I18n lookup" do
    before do
      @scoped_text = "Scoped lookup"
      @global_text = "Global lookup"
      @single_word_text = "Single"
      @email_address = "user@example.org"
      @multiline_text = <<-MULTI.gsub(/^[ \t]+/, '')
        This is some multiline text.

        It should include multiple paragraphs.
      MULTI

      @nb_scoped_text = "Scoped&nbsp;lookup"

      I18n.exception_handler = nil

      I18n.backend.store_translations :en, {
        test_key: @global_text,
        multiline_key: @multiline_text,
        interpolated_key: "%{interpolate_with}",
        internal_link: "[Internal link](/internal/path)",
        external_link: "[External link](http://external.com)",
        test: {
          email_key:               "<#{@email_address}>",
          test_key:                "*#{@scoped_text}*",
          list_key:                "* #{@scoped_text}",
          single_word_list_key:    "* #{@single_word_text}",
          prerendered_html_key:    "<ul>\n <li> Get everything you ever wanted</li>\n <li> Practically-guaranteed</li>\n </ul>",
          interpolated_key:        "Global? (!test_key!)",
          interpolated_scoped_key: "Global? (!test_scoped_key!)",
          interpol_arg_key:        "Interpolate global? (!interpolated_key!)",
          recursive_key:           "Recursively !test.interpolated_key!",
          quoted_key:              "They're looking for \"#{@global_text}\"--#{@scoped_text}",
          argument_key:            "This is what %{user} said",
          number_key:              "120\"",
          pluralized_key: {
            one:            "A single piece of text",
            other:          "%{count} pieces of text"
          }
        }
      }
    end

    after do
      I18n.backend.reload!
    end

    describe "for a specified scope" do
      before do
        @helper.define_singleton_method :translation_scope do
          'test'
        end
      end

      it "looks up the text for the key in a scope derived from the call stack" do
        assert_equal "*#{@scoped_text}*", @helper.text(:test_key)
      end

      it "converts the text to HTML via Markdown" do
        assert_equal "<p><em>#{@nb_scoped_text}</em></p>\n", @helper.html(:test_key)
      end

      it "handles orphans within HTML list items" do
        expected = <<-EXPECTED.gsub(/^[ \t]+/, '')
        <ul>
        <li>#{@nb_scoped_text}</li>
        </ul>
        EXPECTED

        assert_equal expected, @helper.html(:list_key)
      end

      it "does not inject `&nbsp;` entities in HTML list items unnecessarily" do
        expected = <<-EXPECTED.gsub(/^[ \t]+/, '')
        <ul>
        <li>#{@single_word_text}</li>
        </ul>
        EXPECTED

        assert_equal expected, @helper.html(:single_word_list_key)
      end

      it "correctly handles orphans in HTML with erratic whitespace" do
        expected = "<ul>\n <li> Get everything you ever&nbsp;wanted</li>\n <li> Practically-guaranteed</li>\n </ul>\n"

        assert_equal expected, @helper.html(:prerendered_html_key)
      end

      it "does not modify HTML tags" do
        expected = "<p><a href=\"mailto:#{@email_address}\">#{@email_address}</a></p>\n"
        assert_equal expected, @helper.html(:email_key)
      end

      it "allows orphaned text with :orphans" do
        assert_equal "<p><em>#{@scoped_text}</em></p>\n", @helper.html(:test_key, orphans: true)
      end

      it "correctly eliminates orphans across multiple paragraphs" do
        expected = <<-EXPECTED.gsub(/^[ \t]+/, '')
          <p>This is some multiline&nbsp;text.</p>

          <p>It should include multiple&nbsp;paragraphs.</p>
        EXPECTED
        assert_equal expected, @helper.html(:multiline_key)
      end

      it "removes the enclosing paragraph with :inline" do
        assert_equal "<em>#{@nb_scoped_text}</em>\n", @helper.html(:test_key, inline: true)
      end

      it "correctly combines :orphans and :inline options" do
        assert_equal "<em>#{@scoped_text}</em>\n", @helper.html(:test_key, inline: true, orphans: true)
      end

      it "renders internal links without a target" do
        assert_equal "<a href=\"/internal/path\">Internal&nbsp;link</a>\n", @helper.html(:internal_link, inline: true)
      end

      it "renders external links with target='_blank'" do
        assert_equal "<a href=\"http://external.com\" target=\"_blank\">External&nbsp;link</a>\n", @helper.html(:external_link, inline: true)
      end

      it "interpolates values wrapped in !!" do
        assert_equal "Global? (#{@global_text})", @helper.text(:interpolated_key)
      end

      it "interpolates contents of scopes wrapped in !!" do
        assert_equal "Interpolate global? (My interpolated text)", @helper.text(:interpol_arg_key, interpolate_with: "My interpolated text")
      end

      it "handles recursive interpolation" do
        assert_equal "Recursively Global? (#{@global_text})", @helper.text(:recursive_key)
      end

      it "applies smart quotes to text by default" do
        assert_equal "They&rsquo;re looking for &ldquo;#{@global_text}&rdquo;&ndash;#{@scoped_text}", @helper.text(:quoted_key)
      end

      it "allows smart quoting to be disabled" do
        assert_equal "They're looking for \"#{@global_text}\"--#{@scoped_text}", @helper.text(:quoted_key, smart: false)
      end

      it "automatically converts quotes and dashes to clean HTML replacements" do
        assert_equal "<p>They&rsquo;re looking for &ldquo;#{@global_text}&rdquo;&ndash;#{@nb_scoped_text}</p>\n", @helper.html(:quoted_key)
      end

      it "converts to straight quotes in the general case" do
        assert_equal "120&quot;", @helper.text(:number_key) # 120"
      end

      it "handles i18n arguments" do
        assert_equal "This is what Han Solo said", @helper.text(:argument_key, user: "Han Solo")
      end

      it "handles i18n arguments which are not strings" do
        assert_equal "This is what 1234 said", @helper.text(:argument_key, user: 1234)
      end

      it "handles i18n arguments which are not html-safe" do
        assert_equal "This is what &lt;b&gt;Han&lt;/b&gt; Solo said", @helper.text(:argument_key, user: "<b>Han</b> Solo")
      end

      it "handles i18n arguments which are html-safe" do
        assert_equal "This is what <b>Han</b> Solo said", @helper.text(:argument_key, user: "<b>Han</b> Solo".html_safe)
      end

      it "correctly handles pluralized keys" do
        assert_equal "A single piece of text", @helper.text(:pluralized_key, count: 1)
        assert_equal "2 pieces of text", @helper.text(:pluralized_key, count: 2)
      end

      describe "when the pluralization backend is configured and the exception handler is enabled" do
        before do
          @original_backend = I18n.backend
          new_backend = @original_backend.dup
          new_backend.extend(I18n::Backend::Pluralization)
          I18n.backend = new_backend

          @original_exception_handler = I18n.exception_handler
          I18n.exception_handler = TextHelpers::RaiseExceptionHandler.new
        end

        after do
          I18n.backend = @original_backend
          I18n.exception_handler = @original_exception_handler
        end

        it "correctly handles pluralized keys" do
          assert_equal "A single piece of text", @helper.text(:pluralized_key, count: 1)
          assert_equal "2 pieces of text", @helper.text(:pluralized_key, count: 2)
        end
      end
    end

    describe "when no valid scope is provided" do
      before do
        @helper.define_singleton_method :translation_scope do
          'nonexistent'
        end
      end

      it "defaults to a globally-defined value for the key" do
        assert_equal @global_text, @helper.text(:test_key)
      end
    end

    describe "when a scope is given as an option" do
      before do
        @helper.define_singleton_method :translation_scope do
          'test'
        end
      end

      it "shows translation missing if an interpolated key isn't found at the same scope" do
        expected = "Global? (translation missing: en.test.test_scoped_key)"
        assert_equal expected, @helper.text(:interpolated_scoped_key, scope: "test")
      end

      it "interpolates the key if one is found at the same scope" do
        I18n.backend.store_translations(:en, {
          test: {test_scoped_key: "a translation"}})

        assert_equal "Global? (a translation)", @helper.text(:interpolated_scoped_key, scope: "test")
      end

      describe "with the Cascade backend in place" do
        before do
          @original_backend = I18n.backend
          new_backend = @original_backend.dup
          new_backend.extend(I18n::Backend::Cascade)
          I18n.backend = new_backend
        end

        after do
          I18n.backend = @original_backend
        end

        it "cascades the requested key by default" do
          I18n.backend.store_translations(:en, {test_scoped_key: "a translation"})
          assert_equal "a translation", @helper.text(:test_scoped_key, scope: "some.unnecessary.scope")

          I18n.backend.store_translations(:en, {some: {test_scoped_key: "a scoped translation"}})
          assert_equal "a scoped translation", @helper.text(:test_scoped_key, scope: "some.unnecessary.scope")
        end

        it "cascades the interpolated key by default" do
          I18n.backend.store_translations(:en, {test_scoped_key: "a translation"})

          assert_equal "Global? (a translation)", @helper.text(:interpolated_scoped_key, scope: "test")
        end

        it "doesn't cascade if cascade: false is passed" do
          I18n.backend.store_translations(:en, {test_scoped_key: "a translation"})

          expected = "Global? (translation missing: en.test.test_scoped_key)"
          assert_equal expected, @helper.text(:interpolated_scoped_key, scope: "test", cascade: false)
        end
      end
    end
  end
end
