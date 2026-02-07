<?php

namespace App\Http\Controllers;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function dashboard()
    {
        return view('admin.welcome');
    }

    public function services(Request $request)
    {

        return view('admin.services', compact('orders'));
    }
}
