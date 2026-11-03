<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListUserActivityRequest;
use App\Services\UserActivityService;
use Illuminate\View\View;

class UserActivityController extends Controller
{
    public function __construct(private UserActivityService $activities) {}

    public function index(ListUserActivityRequest $request): View
    {
        return view('user-activity.index', $this->activities->listing($request->validated()));
    }
}
