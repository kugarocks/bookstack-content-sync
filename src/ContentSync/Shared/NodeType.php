<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Shared;

enum NodeType: string
{
    case Shelf = 'shelf';
    case Book = 'book';
    case Chapter = 'chapter';
    case Page = 'page';
}
