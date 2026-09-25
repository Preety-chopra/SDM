<?php

namespace App\Enums;

enum DeficiencyType: string
{
    case LateReporting = 'late_reporting';
    case DressCode = 'dress_code_or_instructions';
    case MobilePhoneOnDuty = 'mobile_phone_on_duty';
    case ImproperCleanliness = 'improper_cleanliness';
    case Misbehavior = 'misbehavior';
    case PublicComplaint = 'public_complaint';
    case PossessionOfLiveTickets = 'possession_of_live_tickets';
    case BreachOfInstructions = 'breach_of_instructions';
    case ContractTermsViolation = 'contract_terms_violation';
    case ShortDeployment = 'short_deployment';
    case TheftByContractorStaff = 'theft_by_contractor_staff';
    case DamageByContractorStaff = 'damage_by_contractor_staff';
    case BusinessRulesViolation = 'business_rules_violation';
    case LossOfLoginIdOrCard = 'loss_of_login_id_or_card';
    case CashCheck = 'cash_check';
    case UndervaluedTickets = 'undervalued_tickets';
    case Overcharging = 'overcharging';
    case PersonalCashOrMalpractice = 'personal_cash_or_malpractice';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LateReporting => 'Late Reporting',
            self::DressCode => 'Poor Dress Code / Failure to Follow Instructions',
            self::MobilePhoneOnDuty => 'Mobile Phone on Duty',
            self::ImproperCleanliness => 'Improper Cleanliness',
            self::Misbehavior => 'Misbehavior with Customer / Staff',
            self::PublicComplaint => 'Public Complaint',
            self::PossessionOfLiveTickets => 'Possession of Live Tickets / CSCs',
            self::BreachOfInstructions => 'Breach of Instructions',
            self::ContractTermsViolation => 'Violation of Contract Terms and Conditions',
            self::ShortDeployment => 'Short Deployment of Staff',
            self::TheftByContractorStaff => "Theft by Contractor's Staff",
            self::DamageByContractorStaff => "Damage by Contractor's Staff",
            self::BusinessRulesViolation => 'Violation of Business Rules',
            self::LossOfLoginIdOrCard => 'Loss of Login ID or Card',
            self::CashCheck => 'TOM / CCC Inspection / Cash Checking',
            self::UndervaluedTickets => 'Issue of Undervalued Tickets',
            self::Overcharging => 'Overcharging',
            self::PersonalCashOrMalpractice => 'Personal Cash or Malpractice at TOM',
            self::Other => 'Other Discrepancy',
        };
    }
}
