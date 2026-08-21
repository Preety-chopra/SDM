<?php

namespace App\Enums;

enum UserPermission: string
{
    case ViewUsers = 'users.view';
    case CreateUsers = 'users.create';
    case UpdateUsers = 'users.update';
    case ChangeUserStatus = 'users.change_status';
    case ManageRoles = 'roles.manage';
    case CreateMemos = 'sdm_memos.create';
    case ViewOwnMemos = 'sdm_memos.view_own';
    case UpdateOwnDraftMemos = 'sdm_memos.update_own_draft';
    case SubmitOwnDraftMemos = 'sdm_memos.submit_own_draft';
    case ViewAllMemos = 'sdm_memos.view_all';
    case ViewRoutedMemos = 'sdm_memos.view_routed';
    case ActOnRoutedMemos = 'sdm_memos.act_routed';
    case ManageSdmHierarchy = 'sdm_hierarchy.manage';
    case ManageLines = 'master_data.lines.manage';
    case ManageStations = 'master_data.stations.manage';
    case ManageContractors = 'master_data.contractors.manage';
}
