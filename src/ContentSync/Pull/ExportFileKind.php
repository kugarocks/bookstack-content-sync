<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Pull;

enum ExportFileKind: string
{
    case Meta = 'meta';
    case Page = 'page';
}
