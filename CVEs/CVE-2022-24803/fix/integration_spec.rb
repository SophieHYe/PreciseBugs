require_relative 'spec_helper'
require 'asciidoctor/include_ext/include_processor'
require 'webrick'

FIXTURES_DIR = File.expand_path('fixtures', __dir__)

describe 'Integration tests' do

  subject(:output) { Asciidoctor.convert(input, options) }

  let(:input) { '' }  # this is modified in #given
  let(:processor) { Asciidoctor::IncludeExt::IncludeProcessor.new }

  let(:options) {
    processor_ = processor
    {
      safe: :safe,
      header_footer: false,
      base_dir: FIXTURES_DIR,
      extensions: proc { include_processor processor_ },
    }
  }

  before do
    # XXX: Ugly hack to get rid of rspec-mocks' warnings about resetting
    # frozen object; https://github.com/rspec/rspec-mocks/issues/1190.
    processor.define_singleton_method(:freeze) { self }

    # Make sure that Asciidoctor really calls our processor.
    expect(processor).to receive(:process).at_least(:once).and_call_original
  end

  describe 'include::[] directive' do

    it 'is replaced by a link when safe mode is default' do
      given 'include::include-file.adoc[]', safe: nil

      should match /<a[^>]+href="include-file.adoc"/
      should_not match /included content/
    end

    it 'is resolved when safe mode is less than SECURE' do
      given 'include::include-file.adoc[]'

      should match /included content/
      should_not match /<a[^>]+href="include-file\.adoc"/
    end

    it 'nested includes are resolved with relative paths' do
      given 'include::a/include-1.adoc[]'

      expect( output.scan(/[^>]*include \w+/) ).to eq [
        'begin of include 1', 'include 2a', 'begin of include 2b', 'include 3',
        'end of include 2b', 'end of include 1'
      ]
    end

    it 'is replaced by a warning when target is not found' do
      given <<~ADOC
        include::no-such-file.adoc[]

        trailing content
      ADOC

      should match /unresolved/i
      should match /trailing content/
    end

    it 'is skipped when target is not found and optional option is set' do
      given <<~ADOC
        include::no-such-file.adoc[opts=optional]

        trailing content
      ADOC

      should match /trailing content/
      should_not match /unresolved/i
    end

    it 'is replaced by a link when target is an URI and attribute allow-uri-read is not set' do
      using_test_webserver do |host, port|
        target = "http://#{host}:#{port}/hello.json"
        given "include::#{target}[]"

        should match /<a[^>]*href="#{target}"/
        should_not match /\{"message": "Hello, world!"\}/
      end
    end

    it 'retrieves content from URI target when allow-uri-read is set' do
      using_test_webserver do |host, port|
        given "include::http://#{host}:#{port}/hello.json[]",
              attributes: { 'allow-uri-read' => '' }

        should match /\{"message": "Hello, world!"\}/
        should_not match /unresolved/i
      end
    end

    it 'supports line selection' do
      given 'include::include-file.adoc[lines=1;3..4;6..-1]'

      %w[1 3 4 6 7 8].each do |n|
        should match /line #{n} of included content/
      end
      should match /last line/

      should_not match /line 2/
      should_not match /line 5/
    end

    it 'supports tagged selection' do
      given 'include::include-file.adoc[tag=snippet-a]'

      should match /snippet-a content/
      should_not match /snippet-b content/
      should_not match /non-tagged content/
      should_not match /included content/
    end

    it 'supports multiple tagged selection' do
      given 'include::include-file.adoc[tags="snippet-a,snippet-b"]'

      should match /snippet-a content/
      should match /snippet-b content/
      should_not match /non-tagged content/
      should_not match /included content/
    end

    it 'supports tagged selection in language that uses circumfix comments' do
      given <<~ADOC
        [source, ml]
        ----
        include::include-file.ml[tag=snippet]
        ----
      ADOC

      should match /let s = SS.empty;;/
      should_not match /(?:tag|end)::snippet\[\]/
    end

    it 'does not allow execution of system command when allow-uri-read is set' do
      options.merge!(attributes: { 'allow-uri-read' => '' })
      given <<~ADOC
        :app-name: |cat LICENSE # + \\
        http://test.com

        include::{app-name}[]
      ADOC

      should match /unresolved/i
      should_not match /The MIT License/
    end

  end


  #----------  Helpers  ----------

  def given(str, opts = {})
    input.replace(str)
    options.merge!(opts)
  end

  def using_test_webserver
    started = false
    server = WEBrick::HTTPServer.new(
      BindAddress: '127.0.0.1',
      Port: 0,
      StartCallback: -> { started = true },
      AccessLog: [],
    )

    server.mount_proc '/hello.json' do |_, res|
      res.body = '{"message": "Hello, world!"}'
    end

    Thread.new { server.start }
    Timeout.timeout(1) { :wait until started }

    begin
      yield server.config[:BindAddress], server.config[:Port]
    ensure
      server.shutdown
    end
  end
end
