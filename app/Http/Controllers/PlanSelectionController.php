<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PlanSelectionController extends Controller
{
    public function index()
    {
        return view('student.plan_selection.index');
    }
}
