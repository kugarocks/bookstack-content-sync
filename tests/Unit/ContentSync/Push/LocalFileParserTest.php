<?php

namespace Tests\Unit\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Push\LocalFileParser;
use Kugarocks\BookStackContentSync\ContentSync\Shared\ContentHashBuilder;
use Kugarocks\BookStackContentSync\ContentSync\Shared\TagNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LocalFileParserTest extends TestCase
{
    public function test_parse_meta_reports_path_for_missing_string_field(): void
    {
        $parser = $this->parser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field [slug] in meta file [content/01-book/_meta.yml] must be a string');

        $parser->parseMeta(<<<YAML
type: "book"
title: "Book"
desc: ""
tags: []
YAML, 'content/01-book/_meta.yml');
    }

    public function test_parse_meta_reports_path_for_invalid_type_value(): void
    {
        $parser = $this->parser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field [type] in meta file [content/01-book/_meta.yml] must be one of [shelf, book, chapter]');

        $parser->parseMeta(<<<YAML
type: "page"
title: "Book"
slug: "book"
desc: ""
tags: []
YAML, 'content/01-book/_meta.yml');
    }

    public function test_parse_page_reports_path_for_invalid_tags_shape(): void
    {
        $parser = $this->parser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field [tags] in page file [content/01-book/01-page.md] must be an array');

        $parser->parsePage(<<<MD
---
title: "Page"
slug: "page"
tags: "bad"
---

Body
MD, 'content/01-book/01-page.md');
    }

    public function test_parse_page_reports_path_for_unsupported_yaml(): void
    {
        $parser = $this->parser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported YAML format in page file [content/01-book/01-page.md]');

        $parser->parsePage(<<<MD
---
title: "Page"
  bad: "value"
slug: "page"
tags: []
---

Body
MD, 'content/01-book/01-page.md');
    }

    public function test_parse_meta_rejects_non_integer_entity_id(): void
    {
        $parser = $this->parser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field [entity_id] must be an integer');

        $parser->parseMeta(<<<YAML
type: "book"
title: "Book"
slug: "book"
desc: ""
tags: []
entity_id: "12"
YAML, 'content/01-book/_meta.yml');
    }

    public function test_parse_page_rejects_legacy_object_style_tags(): void
    {
        $parser = $this->parser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported YAML format in page file [content/01-book/01-page.md]');

        $parser->parsePage(<<<MD
---
title: "Page"
slug: "page"
tags:
  - key: "topic"
    value: "install"
---

Body
MD, 'content/01-book/01-page.md');
    }

    public function test_parse_page_accepts_plain_and_named_value_tags(): void
    {
        $parser = $this->parser();

        $page = $parser->parsePage(<<<MD
---
title: "Page"
slug: "page"
tags:
  - "topic"
  - "series:neovim"
---

Body
MD, 'content/01-book/01-page.md');

        $this->assertSame([
            ['name' => 'topic', 'value' => ''],
            ['name' => 'series', 'value' => 'neovim'],
        ], $page->tags);
    }

    public function test_parse_page_rejects_empty_tag_name(): void
    {
        $parser = $this->parser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag name in page file [content/01-book/01-page.md] must not be empty');

        $parser->parsePage(<<<MD
---
title: "Page"
slug: "page"
tags:
  - ":topic"
---

Body
MD, 'content/01-book/01-page.md');
    }

    public function test_parse_page_rejects_empty_tag_value_in_name_value_format(): void
    {
        $parser = $this->parser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag value in page file [content/01-book/01-page.md] must not be empty when using name:value format');

        $parser->parsePage(<<<MD
---
title: "Page"
slug: "page"
tags:
  - "topic:"
---

Body
MD, 'content/01-book/01-page.md');
    }

    public function test_parse_page_rejects_path_without_order_prefix(): void
    {
        $parser = $this->parser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path [content/01-book/page.md] must start with an order prefix');

        $parser->parsePage(<<<MD
---
title: "Page"
slug: "page"
tags: []
---

Body
MD, 'content/01-book/page.md');
    }

    protected function parser(): LocalFileParser
    {
        return new LocalFileParser(new ContentHashBuilder(new TagNormalizer()));
    }
}
