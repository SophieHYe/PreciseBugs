<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project.
 *
 * It is free software; you can redistribute it and/or modify it under the terms
 * of the MIT License (MIT). For the full copyright and license information,
 * please read the LICENSE file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\HtmlSanitizer\Tests;

use PHPUnit\Framework\TestCase;
use TYPO3\HtmlSanitizer\Behavior;
use TYPO3\HtmlSanitizer\Sanitizer;
use TYPO3\HtmlSanitizer\Visitor\CommonVisitor;

class ScenarioTest extends TestCase
{
    public static function tagFlagsAreProcessedDataProvider(): array
    {
        return [
            [
                Behavior\Tag::ALLOW_CHILDREN + Behavior\Tag::PURGE_WITHOUT_CHILDREN,
                implode("\n", [
                    '<div></div><div data-test="test"></div>',
                    '<div>test</div><div data-test="test">test</div>',
                    '<div><!-- --></div><div data-test="test"><!-- --></div>',
                    '<div><!-- test --></div><div data-test="test"><!-- test --></div>',
                    '<div><i></i></div><div data-test="test"><i></i></div>',
                ]),
                implode("\n", [
                    '',
                    '<div>test</div><div data-test="test">test</div>',
                    '<div><!-- --></div><div data-test="test"><!-- --></div>',
                    '<div><!-- test --></div><div data-test="test"><!-- test --></div>',
                    '<div><i></i></div><div data-test="test"><i></i></div>',
                ]),
            ],
            [
                Behavior\Tag::ALLOW_CHILDREN + Behavior\Tag::PURGE_WITHOUT_CHILDREN,
                implode("\n", [
                    '<script></script><script data-test="test"></script>',
                    '<script>test</script><script data-test="test">test</script>',
                    '<script><!-- --></script><script data-test="test"><!-- --></script>',
                    '<script><!-- test --></script><script data-test="test"><!-- test --></script>',
                ]),
                implode("\n", [
                    '',
                    '<script>test</script><script data-test="test">test</script>',
                    '<script><!-- --></script><script data-test="test"><!-- --></script>',
                    '<script><!-- test --></script><script data-test="test"><!-- test --></script>',
                ]),
            ],
        ];
    }

    /**
     * @test
     * @dataProvider tagFlagsAreProcessedDataProvider
     */
    public function tagFlagsAreProcessed(int $flags, string $payload, string $expectation): void
    {
        $behavior = (new Behavior())
            ->withFlags(Behavior::ENCODE_INVALID_TAG + Behavior::REMOVE_UNEXPECTED_CHILDREN)
            ->withName('scenario-test')
            ->withTags(
                (new Behavior\Tag('i')), // just used as DOM child element
                (new Behavior\Tag('div', $flags))->addAttrs((new Behavior\Attr('data-test'))),
                (new Behavior\Tag('script', $flags))->addAttrs((new Behavior\Attr('data-test')))
            );

        $sanitizer = new Sanitizer(
            new CommonVisitor($behavior)
        );
        self::assertSame($expectation, $sanitizer->sanitize($payload));
    }

    /**
     * @test
     */
    public function isJsonLdScriptAllowed(): void
    {
        $payload = implode("\n" , [
            // tag will be removed due to `PURGE_WITHOUT_ATTRS`
            '1:<script>alert(1)</script>',
            // `type` attr will be removed -> no attrs -> tag will be removed due to `PURGE_WITHOUT_ATTRS`
            '2:<script type="application/javascript">alert(2)</script>',
            // `type` attr will be removed -> no attrs -> tag will be removed due to `PURGE_WITHOUT_ATTRS`
            '3:<script type="application/ecmascript">alert(3)</script>',
            // @todo not sanitized by `PURGE_WITHOUT_ATTRS` -> `type` attr value needs to be mandatory
            '4:<script id="identifier">alert(1)</script>',
            // @todo not sanitized by `PURGE_WITHOUT_ATTRS` -> `type` attr value needs to be mandatory
            '5:<script id="identifier" type="application/javascript">alert(2)</script>',
            // tag will be removed due to `PURGE_WITHOUT_CHILDREN`
            '6:<script type="application/ld+json"></script>',
            // rest is keep, since `type` attr value matches and child content is given
            '7:<script type="application/ld+json">alert(4)</script>',
            '8:<script type="application/ld+json">{"@id": "https://github.com/TYPO3/html-sanitizer"}</script>',
        ]);
        $expectation = implode("\n" , [
            '1:',
            '2:',
            '3:',
            '4:<script id="identifier">alert(1)</script>',
            '5:<script id="identifier">alert(2)</script>',
            '6:',
            '7:<script type="application/ld+json">alert(4)</script>',
            '8:<script type="application/ld+json">{"@id": "https://github.com/TYPO3/html-sanitizer"}</script>',
        ]);

        $behavior = (new Behavior())
            ->withFlags(Behavior::ENCODE_INVALID_TAG + Behavior::REMOVE_UNEXPECTED_CHILDREN)
            ->withName('scenario-test')
            ->withTags(
                (new Behavior\Tag(
                    'script',
                    Behavior\Tag::PURGE_WITHOUT_ATTRS + Behavior\Tag::PURGE_WITHOUT_CHILDREN + Behavior\Tag::ALLOW_CHILDREN
                ))->addAttrs(
                    (new Behavior\Attr('id')),
                    (new Behavior\Attr('type'))
                        ->addValues(new Behavior\DatasetAttrValue('application/ld+json'))
                )
            );

        $sanitizer = new Sanitizer(
            new CommonVisitor($behavior)
        );
        self::assertSame($expectation, $sanitizer->sanitize($payload));
    }
}
