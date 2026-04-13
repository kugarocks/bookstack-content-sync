<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

enum PushProgressStage: string
{
    case LoadingLocalProjectState = 'loading_local_project_state';
    case BuildingPushPlan = 'building_push_plan';
    case ExecutingRemoteChanges = 'executing_remote_changes';
    case WritingUpdatedLocalMetadata = 'writing_updated_local_metadata';
    case Warning = 'warning';
    case Create = 'create';
    case Update = 'update';
    case SyncShelfMembership = 'sync_shelf_membership';
    case Trash = 'trash';
}
