<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ShareLinkController extends Controller
{
    // ADMIN ROUTES

    public function index()
    {
        $shareLinks = DB::table('share_links')
            ->orderBy('created_at', 'desc')
            ->get();
            
        foreach($shareLinks as $link) {
            $link->visit_count = DB::table('share_link_visits')->where('share_link_id', $link->id)->count();
            $link->last_viewed = DB::table('share_link_visits')->where('share_link_id', $link->id)->max('viewed_at');
            
            if ($link->shareable_type === 'project') {
                $link->shareable_name = DB::table('projects')->where('id', $link->shareable_id)->value('name');
            } else if ($link->shareable_type === 'party') {
                $link->shareable_name = DB::table('parties')->where('id', $link->shareable_id)->value('name');
            }
            
            // Determine Status
            if ($link->revoked_at) {
                $link->status = 'revoked';
            } else if ($link->expires_at && Carbon::parse($link->expires_at)->isPast()) {
                $link->status = 'expired';
            } else {
                $link->status = 'active';
            }
        }
        
        $projects = DB::table('projects')->get();
        $parties = DB::table('parties')->get();
        
        return view('share-links', compact('shareLinks', 'projects', 'parties'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'shareable_type' => 'required|in:project,party',
            'shareable_id' => 'required|integer',
            'audience' => 'required|in:client,partner',
            'expires_at' => 'nullable|date',
            'allow_downloads' => 'boolean',
            'notify_on_view' => 'boolean',
            'password' => 'nullable|string'
        ]);

        $data['token'] = Str::random(40);
        $data['allow_downloads'] = $request->has('allow_downloads') ? 1 : 0;
        $data['notify_on_view'] = $request->has('notify_on_view') ? 1 : 0;
        $data['created_at'] = now();
        $data['created_by'] = 1; // Default Admin user
        
        if (!empty($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
        }
        unset($data['password']);

        DB::table('share_links')->insert($data);
        return back()->with('success', 'Share link generated successfully!')->with('generated_link', url('/share/' . $data['token']));
    }

    public function revoke($id)
    {
        DB::table('share_links')->where('id', $id)->update([
            'revoked_at' => now(),
            'updated_at' => now()
        ]);
        return back()->with('success', 'Link revoked successfully!');
    }

    public function regenerate($id)
    {
        $newToken = Str::random(40);
        DB::table('share_links')->where('id', $id)->update([
            'token' => $newToken,
            'revoked_at' => null, // clear revoked status if regenerating
            'updated_at' => now()
        ]);
        return back()->with('success', 'Link regenerated successfully!');
    }

    // PUBLIC ROUTES

    public function showPublic(Request $request, $token)
    {
        $link = DB::table('share_links')->where('token', $token)->first();

        // Check if exists, is revoked, or is expired
        if (!$link || $link->revoked_at || ($link->expires_at && Carbon::parse($link->expires_at)->isPast())) {
            return view('share.invalid');
        }

        // Check Password if present and not already authenticated in session for this link
        if ($link->password_hash && !$request->session()->has("share_auth_{$link->id}")) {
            return view('share.password', compact('token'));
        }

        // Log Visit
        DB::table('share_link_visits')->insert([
            'share_link_id' => $link->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->headers->get('referer'),
            'viewed_at' => now()
        ]);

        // Load data based on scope
        if ($link->audience === 'client') {
            if ($link->shareable_type === 'project') {
                return $this->renderClientScope($link);
            } elseif ($link->shareable_type === 'party') {
                if ($request->has('project_id')) {
                    $project = DB::table('projects')->where('id', $request->project_id)->first();
                    if ($project) {
                        $isClient = DB::table('project_party')->where('project_id', $project->id)->where('party_id', $link->shareable_id)->where('role', 'client')->exists();
                        if ($isClient) {
                            return $this->renderClientScope($link, $project);
                        }
                    }
                    return view('share.invalid');
                } else {
                    return $this->renderClientDashboard($link);
                }
            }
        } else if ($link->audience === 'partner' && $link->shareable_type === 'party') {
            return $this->renderPartnerScope($link);
        }

        return view('share.invalid');
    }

    public function verifyPassword(Request $request, $token)
    {
        $link = DB::table('share_links')->where('token', $token)->first();
        if (!$link) return redirect()->back()->withErrors(['password' => 'Invalid link']);

        if (Hash::check($request->password, $link->password_hash)) {
            $request->session()->put("share_auth_{$link->id}", true);
            return redirect("/share/{$token}");
        }

        return redirect()->back()->withErrors(['password' => 'Incorrect password']);
    }

    private function renderClientScope($link, $projectOverride = null)
    {
        if ($projectOverride) {
            $project = $projectOverride;
        } else {
            $project = DB::table('projects')->where('id', $link->shareable_id)->first();
        }
        
        if (!$project) return view('share.invalid');

        $invoices = DB::table('invoices')->where('project_id', $project->id)->whereNotIn('status', ['draft', 'pending_approval'])->orderBy('created_at', 'desc')->get();
        $payments = DB::table('payments')->where('project_id', $project->id)->orderBy('payment_date', 'desc')->get();
        $change_requests = DB::table('change_requests')->where('project_id', $project->id)->orderBy('created_at', 'desc')->get();
        foreach ($change_requests as $cr) {
            $cr->attachments = DB::table('attachments')
                ->where('model_type', 'ChangeRequest')
                ->where('model_id', $cr->id)
                ->get();
            $cr->links = !empty($cr->external_links) ? json_decode($cr->external_links, true) : [];
        }

        foreach ($invoices as $inv) {
            $hasCRItem = DB::table('invoice_items')
                ->where('invoice_id', $inv->id)
                ->where(function($q) {
                    $q->where('description', 'LIKE', '%Change Request%')
                      ->orWhere('description', 'LIKE', '%CR%');
                })->exists();
            $inv->is_cr = $hasCRItem || str_contains(strtolower($inv->invoice_no), 'cr');
        }

        $documents = DB::table('project_documents')
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $approvedCR = DB::table('change_requests')
            ->where('project_id', $project->id)
            ->whereIn('status', ['approved', 'invoiced'])
            ->sum('amount');
        $totalProjectBudget = (float)($project->budget_limit ?? 0);
        $totalProjectValue = $totalProjectBudget + (float)$approvedCR;

        $totalInvoiced = $invoices->sum('amount');
        $totalCollected = $payments->sum('total_amount');
        $outstandingBalance = max(0, $totalInvoiced - $totalCollected);
        $unbilledBalance = max(0, $totalProjectValue - $totalInvoiced);

        return view('share.client', compact('project', 'invoices', 'payments', 'change_requests', 'documents', 'totalInvoiced', 'totalCollected', 'outstandingBalance', 'totalProjectBudget', 'totalProjectValue', 'unbilledBalance', 'link'));
    }


    private function renderClientDashboard($link)
    {
        $party = DB::table('parties')->where('id', $link->shareable_id)->first();
        if (!$party) return view('share.invalid');

        $projectIds = DB::table('project_party')->where('party_id', $party->id)->where('role', 'client')->pluck('project_id')->toArray();
        $projects = DB::table('projects')->whereIn('id', $projectIds)->get();

        foreach($projects as $project) {
            $invoices = DB::table('invoices')->where('project_id', $project->id)->whereNotIn('status', ['draft', 'pending_approval'])->get();
            $payments = DB::table('payments')->where('project_id', $project->id)->get();
            
            $project->total_invoiced = $invoices->sum('amount');
            $project->total_collected = $payments->sum('total_amount');
            $project->outstanding_balance = $project->total_invoiced - $project->total_collected;
        }

        return view('share.client-dashboard', compact('party', 'projects', 'link'));
    }

    private function renderPartnerScope($link)
    {
        $party = DB::table('parties')->where('id', $link->shareable_id)->first();
        if (!$party) return view('share.invalid');

        // Get projects linked as partner or via commissions
        $projectIds = DB::table('project_party')->where('party_id', $party->id)->where('role', 'partner')->pluck('project_id')->toArray();
        $commProjectIds = DB::table('project_commissions')->where('party_id', $party->id)->pluck('project_id')->toArray();
        
        $allProjectIds = array_unique(array_merge($projectIds, $commProjectIds));
        
        $projects = DB::table('projects')->whereIn('id', $allProjectIds)->get();
        
        foreach($projects as $project) {
            $project->invoices = DB::table('invoices')->where('project_id', $project->id)->whereNotIn('status', ['draft', 'pending_approval'])->orderBy('created_at', 'desc')->get();
            $project->payments = DB::table('payments')->where('project_id', $project->id)->orderBy('payment_date', 'desc')->get();
            
            // Partner Commissions for this project
            $commissions = DB::table('project_commissions')
                ->where('project_id', $project->id)
                ->where('party_id', $party->id)
                ->get();
                
            $totalInvoiced = $project->invoices->sum('amount');
            $totalCollected = $project->payments->sum('total_amount');
            
            foreach ($commissions as $comm) {
                $totalComm = 0;
                if ($comm->commission_type === 'percentage') {
                    $percentage = $comm->percentage_value ?? 0;
                    if ($comm->calculation_basis === 'invoiced') {
                        $totalComm = $totalInvoiced * ($percentage / 100);
                    } elseif ($comm->calculation_basis === 'collected') {
                        $totalComm = $totalCollected * ($percentage / 100);
                    } elseif ($comm->calculation_basis === 'budget') {
                        $totalComm = ($project->budget_limit ?? 0) * ($percentage / 100);
                    }
                } else {
                    $fixed = $comm->fixed_amount ?? 0;
                    if ($comm->trigger_type === 'invoice') {
                        $totalComm = $fixed * $project->invoices->count();
                    } elseif ($comm->trigger_type === 'milestone') {
                        $totalComm = $fixed * $project->payments->count();
                    } else {
                        $totalComm = $fixed;
                    }
                }
                
                $paid = DB::table('commission_payments')->where('project_commission_id', $comm->id)->sum('amount');
                $comm->total_commission = $totalComm;
                $comm->paid = $paid;
                $comm->payable = max(0, $totalComm - $paid);
            }
            $project->commissions = $commissions;
        }

        return view('share.partner', compact('party', 'projects', 'link'));
    }
}
