<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RichReferenceSearch
{
    public static function search(string $type, string $text, int $limit): array
    {
        $isPayment = $type === 'payment';
        $query = $isPayment
            ? DB::table('payments as p')->leftJoin('invoices as i', 'i.id', '=', 'p.invoice_id')->leftJoin('projects as r', 'r.id', '=', DB::raw('COALESCE(p.project_id, i.project_id)'))
                ->select('p.id', 'p.total_amount as amount', 'p.currency', 'p.payment_date as date', 'r.name as project_name', 'i.invoice_no')
                ->selectSub(DB::table('payment_modes as m')->whereColumn('m.payment_id', 'p.id')->whereNotNull('m.reference_no')->orderBy('m.id')->limit(1)->select('m.reference_no'), 'reference')
            : DB::table('projects as p')->join('companies as c', 'c.id', '=', 'p.company_id')
                ->select('p.id', 'p.name', 'p.currency', 'p.status', 'p.budget_limit as amount', 'c.name as company_name');
        $fields = $isPayment ? ['p.id', 'p.total_amount', 'p.currency', 'p.payment_date', 'r.name', 'i.invoice_no'] : ['p.id', 'p.name', 'p.currency', 'p.status', 'p.budget_limit', 'c.name'];
        if (preg_match($isPayment ? '/^PAY-0*([1-9][0-9]*)$/i' : '/^PRJ-0*([1-9][0-9]*)$/i', $text, $match)) {
            $query->where('p.id', $match[1]);
        } else {
            foreach (preg_split('/\s+/u', LoanMentionSearch::normalize($text), -1, PREG_SPLIT_NO_EMPTY) as $term) {
                // Bind literal search terms, including user-entered percent/underscore characters.
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
                $query->where(function ($q) use ($fields, $like, $isPayment, $term) {
                    foreach ($fields as $field) {
                        $q->orWhereRaw("LOWER(COALESCE(CAST({$field} AS TEXT), '')) LIKE ? ESCAPE '!'", [$like]);
                    }
                    if (strlen($term) <= 18 && preg_match('/^\d+(?:\.\d{1,2})?$/', $term)) {
                        $q->orWhere($isPayment ? 'p.total_amount' : 'p.budget_limit', '=', $term);
                    }
                    if ($isPayment) {
                        $q->orWhereExists(fn ($m) => $m->selectRaw('1')->from('payment_modes as m')->whereColumn('m.payment_id', 'p.id')
                            ->where(fn ($m) => $m->whereRaw("LOWER(COALESCE(m.reference_no, '')) LIKE ? ESCAPE '!'", [$like])->orWhereRaw("LOWER(COALESCE(m.bank_name, '')) LIKE ? ESCAPE '!'", [$like])->orWhereRaw("LOWER(COALESCE(m.cheque_no, '')) LIKE ? ESCAPE '!'", [$like])));
                        $q->orWhereExists(fn ($a) => $a->selectRaw('1')->from('payment_allocations as a')->join('invoices as ai', 'ai.id', '=', 'a.invoice_id')->whereColumn('a.payment_id', 'p.id')->whereRaw("LOWER(ai.invoice_no) LIKE ? ESCAPE '!'", [$like]));
                    } else {
                        $q->orWhereExists(fn ($party) => $party->selectRaw('1')->from('project_party as pp')->join('parties as party', 'party.id', '=', 'pp.party_id')->whereColumn('pp.project_id', 'p.id')->whereRaw("LOWER(party.name) LIKE ? ESCAPE '!'", [$like]));
                    }
                });
            }
        }

        return $query->orderByDesc('p.id')->limit($limit)->get()->map(function ($row) use ($isPayment) {
            return (array) $row + ['code' => ($isPayment ? 'PAY-' : 'PRJ-').$row->id, 'url' => '/'.($isPayment ? 'payments' : 'projects').'/'.$row->id];
        })->all();
    }
}
