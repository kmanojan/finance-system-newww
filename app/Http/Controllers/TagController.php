<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TagController extends Controller
{
    public function index()
    {
        $data = DB::table('tags')->get();
        return view('masters.tags', compact('data'));
    }

    public function store(Request $request)
    {
        $data = $request->except(['_token']);
        $data['created_at'] = now();

        DB::table('tags')->insert($data);
        return back()->with('success', 'Tag created successfully!');
    }

    public function update(Request $request, $id)
    {
        $data = $request->except(['_token', '_method']);

        DB::table('tags')->where('id', $id)->update($data);
        return back()->with('success', 'Tag updated successfully!');
    }

    public function destroy($id)
    {
        DB::table('tags')->where('id', $id)->delete();
        return back()->with('success', 'Tag deleted successfully!');
    }
}
