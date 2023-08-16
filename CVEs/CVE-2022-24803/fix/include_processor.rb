# frozen_string_literal: true
require 'logger'
require 'open-uri'
require 'uri'

require 'asciidoctor/include_ext/version'
require 'asciidoctor/include_ext/reader_ext'
require 'asciidoctor/include_ext/lineno_lines_selector'
require 'asciidoctor/include_ext/logging'
require 'asciidoctor/include_ext/tag_lines_selector'
require 'asciidoctor'
require 'asciidoctor/extensions'

module Asciidoctor::IncludeExt
  # Asciidoctor preprocessor for processing `include::<target>[]` directives
  # in the source document.
  #
  # @see http://asciidoctor.org/docs/user-manual/#include-directive
  class IncludeProcessor < ::Asciidoctor::Extensions::IncludeProcessor

    # @param selectors [Array<Class>] an array of selectors that can filter
    #   specified portions of the document to include
    #   (see <http://asciidoctor.org/docs/user-manual#include-partial>).
    # @param logger [Logger] the logger to use for logging warning and errors
    #   from this object and selectors.
    def initialize(selectors: [LinenoLinesSelector, TagLinesSelector],
                   logger: Logging.default_logger, **)
      super
      @selectors = selectors.dup.freeze
      @logger = logger
    end

    # @param reader [Asciidoctor::Reader]
    # @param target [String] name of the source file to include as specified
    #   in the target slot of the `include::[]` directive.
    # @param attributes [Hash<String, String>] parsed attributes of the
    #   `include::[]` directive.
    def process(_, reader, target, attributes)
      unless include_allowed? target, reader
        reader.unshift_line("link:#{target}[]")
        return
      end

      if (max_depth = reader.exceeded_max_depth?)
        logger.error "#{reader.line_info}: maximum include depth of #{max_depth} exceeded"
        return
      end

      unless (path = resolve_target_path(target, reader))
        if attributes.key? 'optional-option'
          reader.shift
        else
          logger.error "#{reader.line_info}: include target not found: #{target}"
          unresolved_include!(target, reader)
        end
        return
      end

      selector = lines_selector_for(target, attributes)
      begin
        lines = read_lines(path, selector)
      rescue => e  # rubocop:disable RescueWithoutErrorClass
        logger.error "#{reader.line_info}: failed to read include file: #{path}: #{e}"
        unresolved_include!(target, reader)
        return
      end

      if selector && selector.respond_to?(:first_included_lineno)
        incl_offset = selector.first_included_lineno
      end

      unless lines.empty?
        reader.push_include(lines, path, target, incl_offset || 1, attributes)
      end
    end

    protected

    attr_reader :logger

    # @param target (see #process)
    # @param reader (see #process)
    # @return [Boolean] `true` if it's allowed to include the *target*,
    #   `false` otherwise.
    def include_allowed?(target, reader)
      doc = reader.document

      return false if doc.safe >= ::Asciidoctor::SafeMode::SECURE
      return false if doc.attributes.fetch('max-include-depth', 64).to_i < 1
      return false if target_http?(target) && !doc.attributes.key?('allow-uri-read')
      true
    end

    # @param target (see #process)
    # @param reader (see #process)
    # @return [String, nil] file path or URI of the *target*, or `nil` if not found.
    def resolve_target_path(target, reader)
      return target if target_http? target

      # Include file is resolved relative to dir of the current include,
      # or base_dir if within original docfile.
      path = reader.document.normalize_system_path(target, reader.dir, nil,
                                                   target_name: 'include file')
      path if ::File.file?(path)
    end

    # Reads the specified file as individual lines, filters them using the
    # *selector* (if provided) and returns those lines in an array.
    #
    # @param path [String] URL or path of the file to be read.
    # @param selector [#to_proc, nil] predicate to filter lines that should be
    #   included in the output. It must accept two arguments: line and
    #   the line number. If `nil` is given, all lines are passed.
    # @return [Array<String>] an array of read lines.
    def read_lines(path, selector)
      if selector
        IO.foreach(path).select.with_index(1, &selector)
      else
        URI.open(path, &:read)
      end
    end

    # Finds and initializes a lines selector that can handle the specified include.
    #
    # @param target (see #process)
    # @param attributes (see #process)
    # @return [#to_proc, nil] an instance of lines selector, or `nil` if not found.
    def lines_selector_for(target, attributes)
      if (klass = @selectors.find { |s| s.handles? target, attributes })
        klass.new(target, attributes, logger: logger)
      end
    end

    # Replaces the include directive in ouput with a notice that it has not
    # been resolved.
    #
    # @param target (see #process)
    # @param reader (see #process)
    def unresolved_include!(target, reader)
      reader.unshift_line("Unresolved directive in #{reader.path} - include::#{target}[]")
    end

    private

    # @param target (see #process)
    # @return [Boolean] `true` if the *target* is a valid HTTP(S) URI, `false` otherwise.
    def target_http?(target)
      # First do a fast test, then try to parse it.
      target.downcase.start_with?('http://', 'https://') \
        && URI.parse(target).is_a?(URI::HTTP)
    rescue URI::InvalidURIError
      false
    end
  end
end
