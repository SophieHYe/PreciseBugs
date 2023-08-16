<?php

namespace SilverStripe\Assets\Tests\Shortcodes;

use SilverStripe\Assets\File;
use Silverstripe\Assets\Dev\TestAssetStore;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\View\Parsers\ShortcodeParser;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Shortcodes\ImageShortcodeProvider;

/**
 * @skipUpgrade
 */
class ImageShortcodeProviderTest extends SapphireTest
{

    protected static $fixture_file = '../ImageTest.yml';

    protected function setUp(): void
    {
        parent::setUp();

        // Set backend root to /ImageTest
        TestAssetStore::activate('ImageTest');

        // Copy test images for each of the fixture references
        $images = Image::get();
        /** @var Image $image */
        foreach ($images as $image) {
            $sourcePath = __DIR__ . '/../ImageTest/' . $image->Name;
            $image->setFromLocalFile($sourcePath, $image->Filename);
        }
    }

    protected function tearDown(): void
    {
        TestAssetStore::reset();
        parent::tearDown();
    }

    public function testShortcodeHandlerDoesNotFallBackToFileProperties()
    {
        $image = $this->objFromFixture(Image::class, 'imageWithTitle');
        $parser = new ShortcodeParser();
        $parser->register('image', [ImageShortcodeProvider::class, 'handle_shortcode']);

        $this->assertEquals(
            sprintf(
                '<img src="%s" alt="">',
                $image->Link()
            ),
            $parser->parse(sprintf('[image id=%d]', $image->ID))
        );
    }

    public function testShortcodeHandlerUsesShortcodeProperties()
    {
        $image = $this->objFromFixture(Image::class, 'imageWithTitle');
        $parser = new ShortcodeParser();
        $parser->register('image', [ImageShortcodeProvider::class, 'handle_shortcode']);

        $this->assertEquals(
            sprintf(
                '<img src="%s" alt="Alt content" title="Title content">',
                $image->Link()
            ),
            $parser->parse(sprintf(
                '[image id="%d" alt="Alt content" title="Title content"]',
                $image->ID
            ))
        );
    }

    public function testShortcodeHandlerAddsDefaultAttributes()
    {
        $image = $this->objFromFixture(Image::class, 'imageWithoutTitle');
        $parser = new ShortcodeParser();
        $parser->register('image', [ImageShortcodeProvider::class, 'handle_shortcode']);

        $this->assertEquals(
            sprintf(
                '<img src="%s" alt="">',
                $image->Link()
            ),
            $parser->parse(sprintf(
                '[image id="%d"]',
                $image->ID
            ))
        );
    }

    public function testShortcodeHandlerDoesNotResampleToNonIntegerImagesSizes()
    {
        $image = $this->objFromFixture(Image::class, 'imageWithoutTitle');
        $parser = new ShortcodeParser();
        $parser->register('image', [ImageShortcodeProvider::class, 'handle_shortcode']);

        $this->assertEquals(
            sprintf(
                '<img src="%s" alt="" width="50%%" height="auto">',
                $image->Link()
            ),
            $parser->parse(sprintf(
                '[image id="%d" alt="" width="50%%" height="auto"]',
                $image->ID
            ))
        );
    }

    public function testShortcodeHandlerFailsGracefully()
    {
        $parser = new ShortcodeParser();
        $parser->register('image', [ImageShortcodeProvider::class, 'handle_shortcode']);

        $nonExistentImageID = File::get()->max('ID') + 1;
        $expected = '<img alt="Image not found">';
        $shortcodes = [
            '[image id="' . $nonExistentImageID . '"]',
            '[image id="' . $nonExistentImageID . '" alt="my-alt-attr"]',
        ];
        foreach ($shortcodes as $shortcode) {
            $actual = $parser->parse($shortcode);
            $this->assertEquals($expected, $actual);
        }
    }

    public function testMissingImageDoesNotCache()
    {

        $parser = new ShortcodeParser();
        $parser->register('image', [ImageShortcodeProvider::class, 'handle_shortcode']);

        $nonExistentImageID = File::get()->max('ID') + 1;
        $shortcode = '[image id="' . $nonExistentImageID . '"]';

        // make sure cache is not populated from a previous test
        $cache = ImageShortcodeProvider::getCache();
        $cache->clear();

        $args = ['id' => (string)$nonExistentImageID];
        $cacheKey = ImageShortcodeProvider::getCacheKey($args);

        // assert that cache is empty before parsing shortcode
        $this->assertNull($cache->get($cacheKey));

        $parser->parse($shortcode);

        // assert that cache is still empty after parsing shortcode
        $this->assertNull($cache->get($cacheKey));
    }

    public function testLazyLoading()
    {
        $parser = new ShortcodeParser();
        $parser->register('image', [ImageShortcodeProvider::class, 'handle_shortcode']);

        $id = $this->objFromFixture(Image::class, 'imageWithTitle')->ID;

        // regular shortcode
        $shortcode = '[image id="' . $id . '" width="300" height="200"]';
        $this->assertStringContainsString('loading="lazy"', $parser->parse($shortcode));

        // missing width
        $shortcode = '[image id="' . $id . '" height="200"]';
        $this->assertStringNotContainsString('loading="lazy"', $parser->parse($shortcode));

        // missing height
        $shortcode = '[image id="' . $id . '" width="300"]';
        $this->assertStringNotContainsString('loading="lazy"', $parser->parse($shortcode));

        // loading="eager"
        $shortcode = '[image id="' . $id . '" width="300" height="200" loading="eager"]';
        $this->assertStringNotContainsString('loading="lazy"', $parser->parse($shortcode));

        // loading="nonsense"
        $shortcode = '[image id="' . $id . '" width="300" height="200" loading="nonsense"]';
        $this->assertStringContainsString('loading="lazy"', $parser->parse($shortcode));

        // globally disabled
        Config::withConfig(function () use ($id, $parser) {
            Config::modify()->set(Image::class, 'lazy_loading_enabled', false);
            // clear-provider-cache is so that we don't get a cached result from the 'regular shortcode'
            // assertion earlier in this function from ImageShortCodeProvider::handle_shortcode()
            $shortcode = '[image id="' . $id . '" width="300" height="200" clear-provider-cache="1"]';
            $this->assertStringNotContainsString('loading="lazy"', $parser->parse($shortcode));
        });
    }
}
