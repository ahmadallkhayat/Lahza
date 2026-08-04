<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View
    {
        // No Template model yet — the archived lux wedding page is still a
        // standalone HTML file. This screen exists so the shell is complete;
        // it lists nothing until the model lands.
        return view('admin.templates.index');
    }
}
