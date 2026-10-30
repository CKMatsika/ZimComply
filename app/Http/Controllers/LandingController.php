<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    /**
     * Render the public landing page for prospective customers and visitors.
     * Does not expose protected organisation or regulatory data.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Landing/Index', [
            'auth' => [
                'user' => Auth::check() ? [
                    'id' => Auth::user()->id,
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                ] : null,
            ],
        ]);
    }
}
