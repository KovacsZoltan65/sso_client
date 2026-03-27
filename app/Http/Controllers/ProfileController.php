<?php

namespace App\Http\Controllers;

use App\Services\ProfileService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', $this->profileService->profilePageData($request->user()));
    }
}
