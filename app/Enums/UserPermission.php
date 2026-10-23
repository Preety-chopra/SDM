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
    case AssignCmcStaff = 'sdm_memos.assign_cmc_staff';
    case ManageSdmHierarchy = 'sdm_hierarchy.manage';
    case ManageLines = 'master_data.lines.manage';
    case ManageStations = 'master_data.stations.manage';
    case ManageContractors = 'master_data.contractors.manage';
    case ManageContracts = 'contracts.manage';
    case AssessMemos = 'sdm_memos.assess';
    case ManagePenaltyLetters = 'sdm_memos.penalty_letters.manage';
    case ManageObjections = 'sdm_memos.objections.manage';
    case RecordDeductions = 'sdm_memos.deductions.record';

    public function label(): string
    {
        return match ($this) {
            self::ViewUsers => 'View user accounts',
            self::CreateUsers => 'Create user accounts',
            self::UpdateUsers => 'Edit user accounts',
            self::ChangeUserStatus => 'Enable or disable user accounts',
            self::ManageRoles => 'Manage roles and permissions',
            self::CreateMemos => 'Create SDM memos',
            self::ViewOwnMemos => 'View own SDM memos',
            self::UpdateOwnDraftMemos => 'Edit own draft SDM memos',
            self::SubmitOwnDraftMemos => 'Submit own draft SDM memos',
            self::ViewAllMemos => 'View all SDM memos',
            self::ViewRoutedMemos => 'View assigned SDM memos',
            self::ActOnRoutedMemos => 'Act on assigned SDM memos',
            self::AssignCmcStaff => 'Assign CMC staff',
            self::ManageSdmHierarchy => 'Manage SDM reporting hierarchy',
            self::ManageLines => 'Manage lines',
            self::ManageStations => 'Manage stations',
            self::ManageContractors => 'Manage contractors',
            self::ManageContracts => 'Manage service contracts',
            self::AssessMemos => 'Assess SDM memos',
            self::ManagePenaltyLetters => 'Manage penalty letters',
            self::ManageObjections => 'Manage objections',
            self::RecordDeductions => 'Record deductions',
        };
    }
}
