<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectDocumentController extends Controller
{
    public function store(Request $request, $projectId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'source_type' => 'required|in:file,link',
            'document_date' => 'nullable|date',
            'file' => 'required_if:source_type,file|file|max:20480', // 20MB limit
            'url' => 'required_if:source_type,link|nullable|url|max:500',
            'link_label' => 'nullable|string|max:255',
            'change_request_id' => 'nullable|exists:change_requests,id',
            'tags' => 'nullable|string',
            'notes' => 'nullable|string',
            'visible_to_client' => 'nullable|boolean',
        ]);

        $filePath = null;
        if ($request->source_type === 'file' && $request->hasFile('file')) {
            $filePath = $request->file('file')->store('attachments/projects', 'public');
            
            // Reusing polymorphic attachments pattern
            DB::table('attachments')->insert([
                'model_id' => $projectId,
                'model_type' => 'ProjectDocument', // Custom model type to distinguish
                'file_name' => $request->file('file')->getClientOriginalName(),
                'file_path' => $filePath,
                'uploaded_by' => auth()->user() ? auth()->user()->name : null,
                'created_at' => now()
            ]);
        }

        DB::table('project_documents')->insert([
            'project_id' => $projectId,
            'name' => $request->name,
            'type' => $request->type,
            'source_type' => $request->source_type,
            'file_path' => $filePath,
            'url' => $request->url,
            'link_label' => $request->link_label,
            'change_request_id' => $request->change_request_id,
            'document_date' => $request->document_date ?: now(),
            'tags' => $request->tags,
            'notes' => $request->notes,
            'visible_to_client' => $request->boolean('visible_to_client', false),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Document added successfully.');
    }

    public function update(Request $request, $id)
    {
        $document = DB::table('project_documents')->where('id', $id)->first();
        if (!$document) {
            return back()->with('error', 'Document not found.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'source_type' => 'required|in:file,link',
            'document_date' => 'nullable|date',
            'file' => 'nullable|file|max:20480',
            'url' => 'required_if:source_type,link|nullable|url|max:500',
            'link_label' => 'nullable|string|max:255',
            'change_request_id' => 'nullable|exists:change_requests,id',
            'tags' => 'nullable|string',
            'notes' => 'nullable|string',
            'visible_to_client' => 'nullable|boolean',
        ]);

        $data = [
            'name' => $request->name,
            'type' => $request->type,
            'source_type' => $request->source_type,
            'url' => $request->source_type === 'link' ? $request->url : null,
            'link_label' => $request->source_type === 'link' ? $request->link_label : null,
            'change_request_id' => $request->change_request_id,
            'document_date' => $request->document_date ?: now(),
            'tags' => $request->tags,
            'notes' => $request->notes,
            'visible_to_client' => $request->boolean('visible_to_client', false),
            'updated_at' => now(),
        ];

        // Handle file update
        if ($request->source_type === 'file' && $request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('attachments/projects', 'public');
            
            DB::table('attachments')->insert([
                'model_id' => $document->project_id,
                'model_type' => 'ProjectDocument',
                'file_name' => $request->file('file')->getClientOriginalName(),
                'file_path' => $data['file_path'],
                'uploaded_by' => auth()->user() ? auth()->user()->name : null,
                'created_at' => now()
            ]);
        } elseif ($request->source_type === 'link') {
            $data['file_path'] = null; // Clear file path if switched to link
        }

        DB::table('project_documents')->where('id', $id)->update($data);

        return back()->with('success', 'Document updated successfully.');
    }

    public function destroy($id)
    {
        $document = DB::table('project_documents')->where('id', $id)->first();
        if (!$document) {
            return back()->with('error', 'Document not found.');
        }

        DB::table('project_documents')->where('id', $id)->update(['deleted_at' => now()]);

        return back()->with('success', 'Document deleted successfully.');
    }

    public function download($id)
    {
        $document = DB::table('project_documents')->where('id', $id)->first();
        if (!$document || !$document->file_path || $document->deleted_at) {
            abort(404);
        }

        $path = storage_path('app/public/' . $document->file_path);
        
        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path);
    }
}
