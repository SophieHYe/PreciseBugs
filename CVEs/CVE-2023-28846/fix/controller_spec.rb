describe Unpoly::Rails::Controller, type: :request do

  def controller_eval(headers: {}, &expression)
    BindingTestController.next_eval_proc = expression
    get '/binding_test/eval', params: {}, headers: headers
    if (error = controller.eval_error)
      raise error
    else
      controller.eval_result
    end
  end

  matcher :match_json do |expected|
    match do |actual_json|
      # Convert to JSON to stringify keys in arrays
      expected = expected.to_json unless expected.is_a?(String)
      expected_parsed = JSON.parse(expected)
      expect(actual_json).to be_a(String)
      actual_parsed = JSON.parse(actual_json)
      expect(actual_parsed).to eq(expected_parsed)
    end
  end

  matcher :expose_helper_method do |helper_name|
    match do |controller_class|
      # The helper_method macro defines a method for the controller._helpers module.
      # This module is eventually included in views.
      # https://github.com/rails/rails/blob/157920aead96865e3135f496c09ace607d5620dc/actionpack/lib/abstract_controller/helpers.rb#L60
      helper_module = controller_class._helpers
      view_like_klass = Class.new { include helper_module }
      view_like = view_like_klass.new
      expect(view_like).to respond_to(helper_name)
    end
  end

  shared_examples_for 'time field' do |reader:, header:|
    it "returns the value of the #{header} request header, parsed from epoch seconds to a Time object" do
      result = controller_eval(headers: { header => '1608714891' }, &reader)
      expect(result).to eq(Time.at(1608714891))
    end

    it "returns nil if no #{header} request header is set" do
      result = controller_eval(&reader)
      expect(result).to be_nil
    end
  end

  shared_examples_for 'string field' do |reader:, header:|
    it "returns the value of the #{header} request header" do
      result = controller_eval(headers: { header => 'header value' }, &reader)
      expect(result).to eq('header value')
    end

    it "returns nil if no #{header} request header is set" do
      result = controller_eval(&reader)
      expect(result).to be_nil
    end
  end

  shared_examples_for 'hash field' do |reader:, header:|
    it "returns value of the #{header} request header, parsed as JSON" do
      result = controller_eval(headers: { header => '{ "foo": "bar" }'}, &reader)
      expect(result).to respond_to(:[])
      expect(result['foo']).to eq('bar')
    end

    it "allows to access the hash with symbol keys instead of string keys" do
      result = controller_eval(headers: { header => '{ "foo": "bar" }'}, &reader)
      expect(result[:foo]).to eq('bar')
    end

    it "returns an empty hash if no #{header} request header is set" do
      result = controller_eval(&reader)
      expect(result.to_h).to eq({})
    end
  end

  describe 'up?' do

    it 'is available as a helper method' do
      expect(BindingTestController).to expose_helper_method(:up?)
    end

    it 'returns true if the request has an X-Up-Target header' do
      result = controller_eval(headers: { 'X-Up-Target' => 'body' }) do
        up?
      end
      expect(result).to eq(true)
    end

    it 'returns false if the request has no X-Up-Target header' do
      result = controller_eval do
        up?
      end
      expect(result).to eq(false)
    end

  end

  describe 'up' do

    it 'is available as a helper method' do
      expect(BindingTestController).to expose_helper_method(:up?)
    end

  end

  describe 'up.version' do

    it_behaves_like 'string field',
      header: 'X-Up-Version',
      reader: -> { up.version }

  end

  describe 'up.target' do

    it_behaves_like 'string field',
      header: 'X-Up-Target',
      reader: -> { up.target }

  end

  describe 'up.target=' do

    it 'sends the given target as an X-Up-Target response header' do
      controller_eval(headers: { 'X-Up-Target': '.client' }) do
        up.target = '.server'
      end

      expect(response.headers['X-Up-Target']).to eq('.server')
    end

    it "sends no X-Up-Target header if the target wasn't changed (the client might have something more generic like :main)" do
      controller_eval(headers: { 'X-Up-Target': '.client' }) do
      end

      expect(response.headers['X-Up-Target']).to be_blank
    end

    it 'sends no X-Up-Target header if the target was set to the existing value from the request' do
      controller_eval(headers: { 'X-Up-Target': '.client' }) do
        up.target = '.client'
      end

      expect(response.headers['X-Up-Target']).to be_blank
    end

    it 'returns the given target in subsequent calls to up.target' do
      result = controller_eval(headers: { 'X-Up-Target': '.client' }) do
        up.target = '.server'
        up.target
      end

      expect(result).to eq('.server')
    end

    it 'returns the given target in subsequent calls to up.fail_target' do
      result = controller_eval(headers: { 'X-Up-Target': '.client' }) do
        up.target = '.server'
        up.fail_target
      end

      expect(result).to eq('.server')
    end

  end

  describe 'up.render_nothing' do

    it 'renders an empty response' do
      controller_eval do
        up.render_nothing
      end

      expect(response.body).to be_blank
    end

    it 'sets an X-Up-Target: :none header to prevent matching errors on the client' do
      controller_eval do
        up.render_nothing
      end

      expect(response.headers['X-Up-Target']).to eq(':none')
    end

    it 'responds with a 200 OK status' do
      controller_eval do
        up.render_nothing
      end

      expect(response.status).to eq(200)
    end

    it 'allows to pass a different status code with :status option' do
      controller_eval do
        up.render_nothing(status: :bad_request)
      end

      expect(response.status).to eq(400)
    end

  end

  describe 'up.fail_target=' do

    it 'is not defined, as the target provided through up.target=() is used for all render cases' do
      expect do
        controller_eval(headers: { 'X-Up-Target': '.client' }) do
          up.fail_target = '.server'
        end
      end.to raise_error(NoMethodError)

    end

  end

  describe 'up.fail_target' do

    it_behaves_like 'string field',
      header: 'X-Up-Fail-Target',
      reader: -> { up.fail_target }

  end

  describe 'up.target?' do

    it 'returns true if the tested CSS selector is requested via Unpoly' do
      result = controller_eval(headers: { 'X-Up-Target': '.foo' }) do
        up.target?('.foo')
      end
      expect(result).to eq(true)
    end

    it 'returns false if Unpoly is requesting another CSS selector' do
      result = controller_eval(headers: { 'X-Up-Target': '.bar' }) do
        up.target?('.foo')
      end
      expect(result).to eq(false)
    end

    it 'returns true if the request is not an Unpoly request' do
      result = controller_eval do
        up.target?('.foo')
      end
      expect(result).to eq(true)
    end

    it 'returns true if the request is an Unpoly request, but does not reveal a target for better cacheability' do
      result = controller_eval(headers: { 'X-Up-Version': '1.0.0' }) do
        up.target?('.foo')
      end
      expect(result).to eq(true)
    end

    it 'returns true if testing a custom selector, and Unpoly requests "body"' do
      result = controller_eval(headers: { 'X-Up-Target': 'body' }) do
        up.target?('foo')
      end
      expect(result).to eq(true)
    end

    it 'returns true if testing a custom selector, and Unpoly requests "html"' do
      result = controller_eval(headers: { 'X-Up-Target': 'html' }) do
        up.target?('foo')
      end
      expect(result).to eq(true)
    end

    it 'returns true if testing "body", and Unpoly requests "html"' do
      result = controller_eval(headers: { 'X-Up-Target': 'html' }) do
        up.target?('body')
      end
      expect(result).to eq(true)
    end

    it 'returns true if testing "head", and Unpoly requests "html"' do
      result = controller_eval( headers: { 'X-Up-Target': 'html' }) do
        up.target?('head')
      end
      expect(result).to eq(true)
    end

    it 'returns false if the tested CSS selector is "head" but Unpoly requests "body"' do
      result = controller_eval(headers: { 'X-Up-Target': 'body' }) do
        up.target?('head')
      end
      expect(result).to eq(false)
    end

    it 'returns false if the tested CSS selector is "title" but Unpoly requests "body"' do
      result = controller_eval(headers: { 'X-Up-Target': 'body' }) do
        up.target?('title')
      end
      expect(result).to eq(false)
    end

    it 'returns false if the tested CSS selector is "meta" but Unpoly requests "body"' do
      result = controller_eval(headers: { 'X-Up-Target': 'body' }) do
        up.target?('meta')
      end
      expect(result).to eq(false)
    end

    it 'returns true if the tested CSS selector is "head", and Unpoly requests "html"' do
      result = controller_eval(headers: { 'X-Up-Target': 'html' }) do
        up.target?('head')
      end
      expect(result).to eq(true)
    end

    it 'returns true if the tested CSS selector is "title", Unpoly requests "html"' do
      result = controller_eval(headers: { 'X-Up-Target': 'html' }) do
        up.target?('title')
      end
      expect(result).to eq(true)
    end

    it 'returns true if the tested CSS selector is "meta", and Unpoly requests "html"' do
      result = controller_eval(headers: { 'X-Up-Target': 'html' }) do
        up.target?('meta')
      end
      expect(result).to eq(true)
    end

    it 'returns true if the tested CSS selector is included in a comma-separated group of requested selectors' do
      result = controller_eval(headers: { 'X-Up-Target': '.foo, .bar, .baz' }) do
        up.target?('.bar')
      end
      expect(result).to eq(true)
    end


  end

  describe 'up.fail_target?' do

    it 'returns false if the tested CSS selector only matches the X-Up-Target header' do
      result = controller_eval(headers: { 'X-Up-Target': '.foo', 'X-Up-Fail-Target': '.bar' }) do
        up.fail_target?('.foo')
      end
      expect(result).to eq(false)
    end

    it 'returns true if the tested CSS selector matches the X-Up-Fail-Target header' do
      result = controller_eval(headers: { 'X-Up-Target': '.foo', 'X-Up-Fail-Target': '.bar' }) do
        up.fail_target?('.bar')
      end
      expect(result).to eq(true)
    end

    it 'returns true if the request is not an Unpoly request' do
      result = controller_eval do
        up.fail_target?('.foo')
      end
      expect(result).to eq(true)
    end

    it 'returns true if the request is an Unpoly request, but does not reveal a target for better cacheability' do
      result = controller_eval(headers: { 'X-Up-Version': '1.0.0' }) do
        up.fail_target?('.foo')
      end
      expect(result).to eq(true)
    end

  end

  describe 'up.any_target?' do

    let :headers do
      { 'X-Up-Target' => '.success',
        'X-Up-Fail-Target' => '.failure' }
    end

    it 'returns true if the tested CSS selector is the target for a successful response' do
      result = controller_eval(headers: headers) do
        up.any_target?('.success')
      end
      expect(result).to be(true)
    end

    it 'returns true if the tested CSS selector is the target for a failed response' do
      result = controller_eval(headers: headers) do
        up.any_target?('.failure')
      end
      expect(result).to eq(true)
    end

    it 'returns false if the tested CSS selector is a target for neither successful nor failed response' do
      result = controller_eval(headers: headers) do
        up.any_target?('.other')
      end
      expect(result).to eq(false)
    end

  end

  describe 'up.validate?' do

    it 'returns true the request is an Unpoly validation call' do
      result = controller_eval(headers: { 'X-Up-Validate' => 'user[email]' }) do
        up.validate?
      end
      expect(result).to eq(true)
    end

    it 'returns false if the request is not an Unpoly validation call' do
      result = controller_eval do
        up.validate?
      end
      expect(result).to eq(false)
    end

  end

  describe 'up.validate' do

    it_behaves_like 'string field',
      header: 'X-Up-Validate',
      reader: -> { up.validate }

  end

  describe 'up.mode' do

    it_behaves_like 'string field',
      header: 'X-Up-Mode',
      reader: -> { up.mode }

  end

  describe 'up.fail_mode' do

    it_behaves_like 'string field',
      header: 'X-Up-Fail-Mode',
      reader: -> { up.fail_mode }

  end

  describe 'up.context' do

    it_behaves_like 'hash field',
      header: 'X-Up-Context',
      reader: -> { up.context }

  end

  describe 'up.context[]=' do

    it 'sends a changed context hash as an X-Up-Context response header' do
      controller_eval(headers: { 'X-Up-Context': { 'foo': 'fooValue' }.to_json }) do
        up.context[:bar] = 'barValue'
      end

      expect(response.headers['X-Up-Context']).to match_json(bar: 'barValue')
    end

    it 'escapes high-ASCII characters in the header value, so we can transport it over HTTP' do
      controller_eval(headers: { 'X-Up-Context': { 'foo': 'fooValue' }.to_json }) do
        up.context[:bar] = 'xäy'
      end

      expect(response.headers['X-Up-Context']).to match_json('{"bar": "x\\u00e4y"}')
    end

    it 'changes the value for subsequent calls of up.context[]' do
      value = controller_eval do
        up.context[:bar] = 'barValue'
        up.context[:bar]
      end

      expect(value).to eq('barValue')
    end

    it 'changes the value for subsequent calls of up.fail_context[], since context updates will be applied to whatever layer ends up being updated' do
      value = controller_eval do
        up.context[:bar] = 'barValue'
        up.fail_context[:bar]
      end

      expect(value).to eq('barValue')
    end

    it 'does not send an X-Up-Context response header if the context did not change' do
      controller_eval(headers: { 'X-Up-Context': { foo: 'fooValue' }.to_json }) do
      end

      expect(response.headers['X-Up-Context']).to be_nil
    end

    it 'sends mutated sub-arrays as an X-Up-Context response header' do
      controller_eval(headers: { 'X-Up-Context': { foo: [1, 2, 3] }.to_json }) do
        up.context[:foo] << 4
      end

      expect(response.headers['X-Up-Context']).to match_json(foo: [1, 2, 3, 4])
    end

    it 'sends mutated sub-hashes as an X-Up-Context response header' do
      controller_eval(headers: { 'X-Up-Context': { foo: { bar: 'barValue'} }.to_json }) do
        up.context[:foo][:baz] = 'bazValue'
      end

      expect(response.headers['X-Up-Context']).to match_json(foo: { bar: 'barValue', baz: 'bazValue' })
    end

  end

  describe 'up.context.replace' do

    it 'replaces the entire context on the client' do
      controller_eval do
        up.context.replace(foo: 'fooValue')
      end

      expect(response.headers['X-Up-Context']).to match_json(foo: 'fooValue')
    end

    it 'nilifies keys that no longer exist (since the frontend merges)' do
      controller_eval(headers: { 'X-Up-Context': { 'foo': 'fooValue' }.to_json }) do
        up.context.replace(bar: 'barValue')
      end

      expect(response.headers['X-Up-Context']).to match_json(foo: nil, bar: 'barValue')
    end

  end

  describe 'up.context.delete' do

    it 'updates the key with a null value' do
      controller_eval(headers: { 'X-Up-Context': { 'foo': 'fooValue' }.to_json }) do
        up.context.delete(:foo)
      end

      expect(response.headers['X-Up-Context']).to match_json(foo: nil)
    end

  end

  describe 'up.fail_context' do

    subject { controller.up.fail_context }

    it_behaves_like 'hash field',
      header: 'X-Up-Fail-Context',
      reader: -> { up.fail_context }

  end

  describe 'up.fail_context[]=' do

    it 'sends a changed context hash as an X-Up-Context response header' do
      controller_eval(headers: { 'X-Up-Context': { 'foo': 'fooValue' }.to_json }) do
        up.fail_context[:bar] = 'barValue'
      end

      expect(response.headers['X-Up-Context']).to match_json(
        bar: 'barValue'
      )
    end

    it 'changes the value for subsequent calls of up.fail_context[]' do
      value = controller_eval do
        up.fail_context[:bar] = 'barValue'
        up.fail_context[:bar]
      end

      expect(value).to eq('barValue')
    end

    it 'changes the value for subsequent calls of up.context[], since context updates will be applied to whatever layer ends up being updated' do
      value = controller_eval do
        up.fail_context[:bar] = 'barValue'
        up.context[:bar]
      end

      expect(value).to eq('barValue')
    end

    it 'sends mutated sub-arrays as an X-Up-Context response header' do
      controller_eval(headers: { 'X-Up-Fail-Context': { foo: [1, 2, 3] }.to_json }) do
        up.fail_context[:foo] << 4
      end

      expect(response.headers['X-Up-Context']).to match_json(foo: [1, 2, 3, 4])
    end

    it 'sends mutated sub-hashes as an X-Up-Context response header' do
      controller_eval(headers: { 'X-Up-Fail-Context': { foo: { bar: 'barValue'} }.to_json }) do
        up.fail_context[:foo][:baz] = 'bazValue'
      end

      expect(response.headers['X-Up-Context']).to match_json(foo: { bar: 'barValue', baz: 'bazValue' })
    end

  end

  describe 'up.cache.clear' do

    it 'sets an `X-Up-Clear-Cache: *` header' do
      controller_eval do
        up.cache.clear
      end

      expect(response.headers['X-Up-Clear-Cache']).to eq('*')
    end

    it 'sets an X-Up-Clear-Cache header with the given URL pattern' do
      controller_eval do
        up.cache.clear('/foo/*')
      end

      expect(response.headers['X-Up-Clear-Cache']).to eq('/foo/*')
   end

  end

  describe 'up.cache.keep' do

    it 'sets an `X-Up-Clear-Cache: false` header' do
      controller_eval do
        up.cache.keep
      end

      expect(response.headers['X-Up-Clear-Cache']).to eq('false')
    end

  end

  describe 'up.emit' do

    it 'adds an entry into the X-Up-Events response header' do
      controller_eval do
        up.emit('my:event', { 'foo' => 'bar' })
      end

      expect(response.headers['X-Up-Events']).to match_json([
        { type: 'my:event', foo: 'bar' }
      ])
    end

    it 'adds multiple entries to the X-Up-Events response headers' do
      controller_eval do
        up.emit('my:event', { 'foo' => 'bar' })
        up.emit('other:event', { 'bam' => 'baz' })
      end

      expect(response.headers['X-Up-Events']).to match_json([
        { foo: 'bar', type: 'my:event' },
        { bam: 'baz', type: 'other:event' }
      ])
    end

    it 'escapes high-ASCII characters in the header value, so we can transport it over HTTP' do
      controller_eval(headers: { 'X-Up-Mode': 'modal' }) do
        up.layer.accept('xäy')
      end

      controller_eval do
        up.emit('my:event', { 'foo' => 'xäy' })
      end

      expect(response.headers['X-Up-Events']).to eq('[{"foo":"x\\u00e4y","type":"my:event"}]')
    end

  end

  describe 'up.layer.emit' do

    it 'adds an entry into the X-Up-Events response header with { layer: "current" } option' do
      controller_eval do
        up.layer.emit('my:event', { 'foo' => 'bar' })
      end

      expect(response.headers['X-Up-Events']).to match_json([
        { type: 'my:event', foo: 'bar', layer: 'current' }
      ])
    end

  end

  describe 'up.layer.mode' do

    it 'returns the value of the X-Up-Mode header' do
      result = controller_eval(headers: { 'X-Up-Mode': 'foo' }) do
        up.layer.mode
      end
      expect(result).to eq('foo')
    end

  end

  describe 'up.layer.root?' do

    it 'returns true if the X-Up-Mode header is "root"' do
      result = controller_eval(headers: { 'X-Up-Mode': 'root' }) do
        up.layer.root?
      end
      expect(result).to eq(true)
    end

    it 'returns true if the request is a full page load without Unpoly (which always replaces the entire page)' do
      result = controller_eval do
        up.layer.root?
      end
      expect(result).to eq(true)
    end

    it 'returns true if the frontend does not reveal its mode for better cacheability' do
      result = controller_eval(headers: { 'X-Up-Version': '1.0.0' }) do
        up.layer.root?
      end
      expect(result).to eq(true)
    end

    it 'returns false if the X-Up-Mode header is not "root"' do
      result = controller_eval(headers: { 'X-Up-Mode': 'drawer' }) do
        up.layer.root?
      end
      expect(result).to eq(false)
    end

  end

  describe 'up.layer.overlay?' do

    it 'returns true if the X-Up-Mode header is "overlay"' do
      result = controller_eval(headers: { 'X-Up-Mode': 'overlay' }) do
        up.layer.overlay?
      end
      expect(result).to eq(true)
    end

    it 'returns false if the request is a full page load (which always replaces the entire page)' do
      result = controller_eval do
        up.layer.overlay?
      end
      expect(result).to eq(false)
    end

    it 'returns false if the X-Up-Mode header is "root"' do
      result = controller_eval(headers: { 'X-Up-Mode': 'root' }) do
        up.layer.overlay?
      end
      expect(result).to eq(false)
    end

  end

  describe 'up.layer.context' do

    it 'returns the parsed JSON object from the X-Up-Context header' do
      result = controller_eval(headers: { 'X-Up-Context': { 'foo' => 'bar' }.to_json}) do
        up.layer.context.to_h
      end
      expect(result).to eq('foo' => 'bar')
    end

  end

  describe 'up.layer.accept' do

    it 'sets an X-Up-Accept-Layer response header with the given value' do
      controller_eval(headers: { 'X-Up-Mode': 'modal' }) do
        up.layer.accept('foo')
      end

      expect(response.headers['X-Up-Accept-Layer']).to eq('"foo"')
    end

    it 'sets an X-Up-Accept-Layer response header with a null value if no value is given' do
      controller_eval(headers: { 'X-Up-Mode': 'modal' }) do
        up.layer.accept
      end

      expect(response.headers['X-Up-Accept-Layer']).to eq('null')
    end

    it 'raises an error when updating the root layer' do
      accept_root = lambda do
        controller_eval(headers: { 'X-Up-Mode': 'root' }) do
          up.layer.accept('foo')
        end
      end

      expect(accept_root).to raise_error(/cannot accept/i)
    end

    it 'escapes high-ASCII characters in the header value, so we can transport it over HTTP' do
      controller_eval(headers: { 'X-Up-Mode': 'modal' }) do
        up.layer.accept('xäy')
      end

      expect(response.headers['X-Up-Accept-Layer']).to eq('"x\\u00e4y"')
    end

  end

  describe 'up.layer.dismiss' do

    it 'sets an X-Up-Dismiss-Layer response header with the given value' do
      controller_eval(headers: { 'X-Up-Mode': 'modal' }) do
        up.layer.dismiss('foo')
      end

      expect(response.headers['X-Up-Dismiss-Layer']).to eq('"foo"')
    end

    it 'sets an X-Up-Dismiss-Layer response header with a null value if no value is given' do
      controller_eval(headers: { 'X-Up-Mode': 'modal' }) do
        up.layer.dismiss
      end

      expect(response.headers['X-Up-Dismiss-Layer']).to eq('null')
    end

    it 'raises an error when updating the root layer' do
      dismiss_root = lambda do
        controller_eval(headers: { 'X-Up-Mode': 'root' }) do
          up.layer.dismiss('foo')
        end
      end

      expect(dismiss_root).to raise_error(/cannot dismiss/i)
    end

    it 'escapes high-ASCII characters in the header value, so we can transport it over HTTP' do
      controller_eval(headers: { 'X-Up-Mode': 'modal' }) do
        up.layer.dismiss('xäy')
      end

      expect(response.headers['X-Up-Dismiss-Layer']).to eq('"x\\u00e4y"')
    end

  end

  describe 'up.fail_layer.mode' do

    it 'returns the value of the X-Up-Fail-Mode header' do
      result = controller_eval(headers: { 'X-Up-Fail-Mode': 'foo' }) do
        up.fail_layer.mode
      end
      expect(result).to eq('foo')
    end

  end

  describe 'up.fail_layer.root?' do

    it 'returns true if the X-Up-Fail-Mode header is "root"' do
      result = controller_eval(headers: { 'X-Up-Fail-Mode': 'root' }) do
        up.fail_layer.root?
      end
      expect(result).to eq(true)
    end

    it 'returns true if the request is a full page load (which always replaces the entire page)' do
      result = controller_eval do
        up.fail_layer.root?
      end
      expect(result).to eq(true)
    end

    it 'returns false if the X-Up-Fail-Mode header is not "root"' do
      result = controller_eval(headers: { 'X-Up-Fail-Mode': 'drawer' }) do
        up.fail_layer.root?
      end
      expect(result).to eq(false)
    end

  end

  describe 'up.fail_layer.overlay?' do

    it 'returns true if the X-Up-Fail-Mode header is "overlay"' do
      result = controller_eval(headers: { 'X-Up-Fail-Mode': 'overlay' }) do
        up.fail_layer.overlay?
      end
      expect(result).to eq(true)
    end

    it 'returns false if the request is a full page load (which always replaces the entire page)' do
      result = controller_eval do
        up.fail_layer.overlay?
      end
      expect(result).to eq(false)
    end

    it 'returns false if the X-Up-Fail-Mode header is "root"' do
      result = controller_eval(headers: { 'X-Up-Fail-Mode': 'root' }) do
        up.fail_layer.overlay?
      end
      expect(result).to eq(false)
    end

  end

  describe 'up.fail_layer.context' do

    it 'returns the parsed JSON object from the X-Up-Fail-Context header' do
      result = controller_eval(headers: { 'X-Up-Fail-Context': { 'foo' => 'bar' }.to_json}) do
        up.fail_layer.context.to_h
      end
      expect(result).to eq('foo' => 'bar')
    end

  end

  describe 'up.title=' do

    it 'sets an X-Up-Title header to push a document title to the client' do
      controller_eval do
        up.title = 'Title from controller'
      end
      expect(response.headers['X-Up-Title']).to eq('Title from controller')
    end

  end

  describe 'up.reload_from_time' do

    include_examples 'time field',
      header: 'X-Up-Reload-From-Time',
      reader: -> { up.reload_from_time }

  end

  describe 'up.reload?' do

    it 'returns true if an X-Up-Reload-From-Time header is given' do
      result = controller_eval(headers: { 'X-Up-Reload-From-Time': '1608714891' }) do
        up.reload?
      end

      expect(result).to eq(true)
    end

    it 'returns false if no X-Up-Reload-From-Time header is given' do
      result = controller_eval do
        up.reload?
      end

      expect(result).to eq(false)
    end

  end

  describe 'up.safe_callback' do

    it 'prepends the CSP nonce to the given JavaScript string' do
      result = controller_eval do
        up.safe_callback('alert()')
      end

      expect(result).to eq('nonce-secret alert()')
    end

  end

  describe 'redirect_to' do

    it 'preserves Unpoly-related headers for the redirect' do
      get '/binding_test/redirect1', headers: { 'X-Up-Target' => '.foo' }
      expect(response).to be_redirect
      follow_redirect!
      expect(response.body).to eq('.foo')
      expect(response.headers['X-Up-Events']).to match_json([
        { type: 'event1' }
      ])
      expect(response.headers['X-Up-Clear-Cache']).to eq('*')
    end

    it 'preserves Unpoly-releated headers over multiple redirects' do
      get '/binding_test/redirect0', headers: { 'X-Up-Target' => '.foo' }
      expect(response).to be_redirect
      follow_redirect!
      expect(response).to be_redirect
      follow_redirect!
      expect(response.body).to eq('.foo')
      expect(response.headers['X-Up-Events']).to match_json([
        { type: 'event0' },
        { type: 'event1' },
      ])
    end

    it 'does not change the history' do
      get '/binding_test/redirect1', headers: { 'X-Up-Target' => '.foo' }
      expect(response).to be_redirect
      follow_redirect!
      expect(response.headers['X-Up-Location']).to end_with('/redirect2')
    end

  end

  describe 'echoing of the request location' do

    it 'does not echo the current path in an X-Up-Location response header to prevent the user-controlled request URL from exceeding the maximum response header size' do
      get '/binding_test/text'
      expect(response.headers['X-Up-Location']).to be_nil
    end

    describe 'when the request URL contains query params prefixed with "_up-"' do

      it 'removes params prefixed with "_up-"' do
        get '/binding_test/text?_up_1&_up_2=y'
        expect(response.headers['X-Up-Location']).to end_with('/binding_test/text')
      end

      it 'keeps params not prefixed with "_up-"' do
        get '/binding_test/text?_up_1=x&foo=bar&_up_2=y'
        expect(response.headers['X-Up-Location']).to end_with('/binding_test/text?foo=bar')
      end

      it 'does not mangle array params (BUGFIX)' do
        get '/binding_test/text?_up_1=x&foo%5B%5D=bar&foo%5B%5D=qux&_up_location=up_location'
        expect(response.headers['X-Up-Location']).to end_with('/binding_test/text?foo%5B%5D=bar&foo%5B%5D=qux')
      end

    end

  end

  describe 'echoing of the request method' do

    it 'echoes the current request method in an X-Up-Method response header' do
      get '/binding_test/text'
      expect(response.headers['X-Up-Method']).to eq('GET')
    end

    it 'echoes the current path after a redirect' do
      put '/binding_test/redirect1'
      expect(response).to be_redirect
      follow_redirect!
      expect(response.headers['X-Up-Method']).to eq('GET')
    end

    it 'echoes a non-GET request method' do
      put '/binding_test/text'
      expect(response.headers['X-Up-Method']).to eq('PUT')
    end

  end

  describe 'request method cookie' do

    describe 'if the request is both non-GET and not a fragment update' do

      it 'echoes the request method in an _up_method cookie ' do
        put '/binding_test/text'
        expect(cookies['_up_method']).to eq('PUT')
      end

    end

    describe 'if the request is not a fragment update, but GET' do

      it 'does not set the cookie' do
        get '/binding_test/text'
        expect(cookies['_up_method']).to be_blank
      end

      it 'deletes an existing cookie' do
        cookies['_up_method'] = 'PUT'
        get '/binding_test/text'
        expect(cookies['_up_method']).to be_blank
      end

    end

    describe 'if the request is non-GET but a fragment update' do

      it 'does not set the cookie' do
        get '/binding_test/text', headers: { 'X-Up-Target' => '.target '}
        expect(cookies['_up_method']).to be_blank
      end

      it 'deletes an existing cookie' do
        cookies['_up_method'] = 'PUT'
        get '/binding_test/text', headers: { 'X-Up-Target' => '.target' }
        expect(cookies['_up_method']).to be_blank
      end

    end

  end

end

