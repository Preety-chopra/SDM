<?php

namespace App\View\Components;

use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function __construct(public ?string $title = null) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app', [
            'backUrl' => $this->backUrl(),
            'showBackButton' => $this->showBackButton(),
            'showSidebar' => $this->showSidebar(),
        ]);
    }

    private function showBackButton(): bool
    {
        return ! request()->routeIs('dashboard')
            && ! $this->isStationController();
    }

    private function showSidebar(): bool
    {
        return ! $this->isStationController();
    }

    private function isStationController(): bool
    {
        return request()->user()?->isStationController() ?? false;
    }

    private function backUrl(): string
    {
        $fallback = route('dashboard');
        $previous = url()->previous($fallback);
        $applicationOrigin = request()->getSchemeAndHttpHost();
        $isInternal = $previous === $applicationOrigin || Str::startsWith($previous, $applicationOrigin.'/');

        return $isInternal && $previous !== request()->fullUrl() ? $previous : $fallback;
    }
}
