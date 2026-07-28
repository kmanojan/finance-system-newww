<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServerController extends Controller
{
    public function index()
    {
        $data = DB::table('servers')->get();
        return view('masters.servers', compact('data'));
    }

    public function store(Request $request)
    {
        $data = $request->except(['_token']);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('servers')->insert($data);
        return back()->with('success', 'Server created successfully!');
    }

    public function update(Request $request, $id)
    {
        $data = $request->except(['_token', '_method']);
        $data['updated_at'] = now();

        DB::table('servers')->where('id', $id)->update($data);
        return back()->with('success', 'Server updated successfully!');
    }

    public function destroy($id)
    {
        DB::table('servers')->where('id', $id)->delete();
        return back()->with('success', 'Server deleted successfully!');
    }
}
