<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

enum PlanAction: string
{
    case Skip = 'skip';
    case Create = 'create';
    case Trash = 'trash';
    case Rename = 'rename';
    case Move = 'move';
    case Update = 'update';
    case SyncMembership = 'sync_membership';
}
