<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app', ['backUrl' => $this->backUrl()]);
    }

    private function backUrl(): string
    {
        return match (request()->route()?->getName()) {
            'sdm-memos.create', 'sdm-memos.show', 'sdm-memos.edit' => route('sdm-memos.index'),
            'roles.create', 'roles.edit' => route('roles.index'),
            'users.create' => route('users.index', request()->route('role')),
            'users.edit' => $this->userDirectoryUrl(),
            default => route('dashboard'),
        };
    }

    private function userDirectoryUrl(): string
    {
        $user = request()->route('user');
        $role = $user instanceof User && $user->relationLoaded('roles')
            ? $user->roles->first()?->name
            : null;

        return $role ? route('users.index', $role) : route('dashboard');
    }
}
