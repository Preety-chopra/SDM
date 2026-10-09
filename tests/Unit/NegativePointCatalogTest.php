<?php

namespace Tests\Unit;

use App\Enums\DeficiencyType;
use App\Support\NegativePointCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NegativePointCatalogTest extends TestCase
{
    #[DataProvider('fixedNegativePoints')]
    public function test_it_returns_the_negative_points_from_the_supplied_list(
        DeficiencyType $deficiency,
        ?int $expectedPoints,
    ): void {
        $this->assertSame($expectedPoints, NegativePointCatalog::for($deficiency));
    }

    #[DataProvider('cashMismatchNegativePoints')]
    public function test_it_applies_the_supplied_cash_mismatch_thresholds(
        float $differenceAmount,
        int $expectedPoints,
    ): void {
        $this->assertSame($expectedPoints, NegativePointCatalog::forCashMismatch($differenceAmount));
    }

    public function test_it_awards_ten_points_when_hidden_cash_is_recorded(): void
    {
        $this->assertSame(0, NegativePointCatalog::forHiddenCash(null));
        $this->assertSame(0, NegativePointCatalog::forHiddenCash(0));
        $this->assertSame(10, NegativePointCatalog::forHiddenCash(0.01));
        $this->assertSame(10, NegativePointCatalog::forHiddenCash(100));
    }

    public static function fixedNegativePoints(): array
    {
        return [
            'poor dress code' => [DeficiencyType::DressCode, 0],
            'mobile phone on duty' => [DeficiencyType::MobilePhoneOnDuty, 0],
            'improper cleanliness' => [DeficiencyType::ImproperCleanliness, 0],
            'late reporting' => [DeficiencyType::LateReporting, 0],
            'public complaint' => [DeficiencyType::PublicComplaint, 5],
            'loss of login ID or card' => [DeficiencyType::LossOfLoginIdOrCard, 0],
            'misbehaviour and poor customer support' => [DeficiencyType::Misbehavior, 5],
            'breach of instructions' => [DeficiencyType::BreachOfInstructions, 3],
            'undervalued ticket' => [DeficiencyType::UndervaluedTickets, 10],
            'overcharging' => [DeficiencyType::Overcharging, 10],
            'personal cash or malpractice' => [DeficiencyType::PersonalCashOrMalpractice, 5],
            'possession of live ticket or CSC' => [DeficiencyType::PossessionOfLiveTickets, 10],
            'contract terms violation' => [DeficiencyType::ContractTermsViolation, 5],
            'business rules violation' => [DeficiencyType::BusinessRulesViolation, 3],
            'cash check uses its detail fields' => [DeficiencyType::CashCheck, null],
            'unlisted short deployment' => [DeficiencyType::ShortDeployment, null],
            'unlisted other deficiency' => [DeficiencyType::Other, null],
        ];
    }

    public static function cashMismatchNegativePoints(): array
    {
        return [
            'no difference' => [0, 0],
            'negative difference at lower limit' => [-50, 0],
            'positive difference at lower limit' => [50, 0],
            'negative high cash mismatch' => [-51, 3],
            'positive high cash mismatch' => [51, 3],
            'below excess-high threshold' => [99.99, 3],
            'negative excess-high cash mismatch' => [-100, 5],
            'positive excess-high cash mismatch' => [100, 5],
        ];
    }
}
