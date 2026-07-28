<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentTemplateController extends Controller
{
    public function index()
    {
        $data = DB::table('document_templates')->get();
        $departments = DB::table('departments')->get();
        $companies = DB::table('companies')->get();
        return view('masters.document_templates', compact('data', 'departments', 'companies'));
    }

    public function store(Request $request)
    {
        $data = $request->except(['_token']);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        if (!isset($data['company_id'])) {
            $company = DB::table('companies')->first();
            $data['company_id'] = $company ? $company->id : 1;
        }

        if ($request->hasFile('header_image')) {
            $file = $request->file('header_image');
            $filename = time() . '_header_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/templates'), $filename);
            $data['header_image_url'] = '/uploads/templates/' . $filename;
        }
        if ($request->hasFile('footer_image')) {
            $file = $request->file('footer_image');
            $filename = time() . '_footer_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/templates'), $filename);
            $data['footer_image_url'] = '/uploads/templates/' . $filename;
        }
        if ($request->hasFile('background_image')) {
            $file = $request->file('background_image');
            $filename = time() . '_bg_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/templates'), $filename);
            $data['background_image_url'] = '/uploads/templates/' . $filename;
        }
        unset($data['header_image'], $data['footer_image'], $data['background_image']);

        DB::table('document_templates')->insert($data);
        return back()->with('success', 'Document template created successfully!');
    }

    public function update(Request $request, $id)
    {
        $data = $request->except(['_token', '_method']);
        $data['updated_at'] = now();

        if ($request->hasFile('header_image')) {
            $file = $request->file('header_image');
            $filename = time() . '_header_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/templates'), $filename);
            $data['header_image_url'] = '/uploads/templates/' . $filename;
        }
        if ($request->hasFile('footer_image')) {
            $file = $request->file('footer_image');
            $filename = time() . '_footer_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/templates'), $filename);
            $data['footer_image_url'] = '/uploads/templates/' . $filename;
        }
        if ($request->hasFile('background_image')) {
            $file = $request->file('background_image');
            $filename = time() . '_bg_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/templates'), $filename);
            $data['background_image_url'] = '/uploads/templates/' . $filename;
        }
        unset($data['header_image'], $data['footer_image'], $data['background_image']);

        DB::table('document_templates')->where('id', $id)->update($data);
        return back()->with('success', 'Document template updated successfully!');
    }

    public function destroy($id)
    {
        DB::table('document_templates')->where('id', $id)->delete();
        return back()->with('success', 'Document template deleted successfully!');
    }
}
