<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Shared;

class ContentHashBuilder
{
    protected PageMarkdownCodec $pageMarkdownCodec;

    public function __construct(
        protected TagNormalizer $tagNormalizer,
        ?PageMarkdownCodec $pageMarkdownCodec = null,
    ) {
        $this->pageMarkdownCodec = $pageMarkdownCodec ?? new PageMarkdownCodec();
    }

    public function build(ContentHashData $data): string
    {
        $payload = [
            'type' => $data->type->value,
            'name' => $data->name,
            'slug' => $data->slug,
            'tags' => $this->tagNormalizer->normalize($data->tags),
        ];

        if ($data->type === NodeType::Page) {
            $payload['markdown'] = $this->pageMarkdownCodec->canonicalizeForHash($data->markdown);
        } else {
            $payload['description'] = $data->description;
        }

        return sha1(json_encode($payload));
    }
}
