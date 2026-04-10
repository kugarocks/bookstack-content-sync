<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Shared;

class ContentHashBuilder
{
    public function __construct(
        protected TagNormalizer $tagNormalizer,
    ) {
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
            $payload['markdown'] = $data->markdown;
        } else {
            $payload['description'] = $data->description;
        }

        return sha1(json_encode($payload));
    }
}
