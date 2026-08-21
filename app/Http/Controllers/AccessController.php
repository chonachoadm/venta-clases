<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;

class AccessController extends Controller
{
    public function granted()
    {
        return view('payment-success');
    }

    public function denied()
    {
        return view('payment-failure');
    }
}
