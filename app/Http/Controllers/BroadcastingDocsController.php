<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BroadcastingDocsController extends Controller
{
    public function __invoke(): View
    {
        return view('docs.broadcasting', [
            'content' => Str::markdown(File::get(base_path('docs/BROADCASTING.md'))),
        ]);
    }
}
