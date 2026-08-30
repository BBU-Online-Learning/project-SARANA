<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withTrashed()->orderBy('name')->get();

        return view('roles.index', compact('roles'));
    }
}
