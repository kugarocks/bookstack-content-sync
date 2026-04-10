<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

enum ExportFileKind: string
{
    case Meta = 'meta';
    case Page = 'page';
}
