@extends('layouts.app')
@section('title', 'Edit draft invoice')
@section('content')
<header class="page-header"><div><a href="/invoices">← Invoices</a><h1>Edit {{ $invoice->invoice_no }}</h1><p class="subtitle">Review the draft before issuing it. All amounts are in {{ $invoice->currency }}.</p></div></header>
<form method="POST" action="/invoices/{{ $invoice->id }}" class="ds-panel">
    @csrf
    @method('PUT')
    <input type="hidden" name="project_id" value="{{ $invoice->project_id }}">
    <input type="hidden" name="client_id" value="{{ $invoice->client_id }}">
    <input type="hidden" name="department_id" value="{{ $invoice->department_id }}">
    <input type="hidden" name="currency" value="{{ $invoice->currency }}">
    <div class="ds-form-grid">
        <div><label class="form-label" for="invoiceNo">Invoice number</label><input class="form-control" id="invoiceNo" name="invoice_no" value="{{ old('invoice_no',$invoice->invoice_no) }}" required></div>
        <div><label class="form-label" for="issueDate">Issue date</label><input class="form-control" id="issueDate" name="issue_date" type="date" value="{{ old('issue_date',$invoice->issue_date ?? $invoice->invoice_date) }}" required></div>
        <div><label class="form-label" for="dueDate">Due date</label><input class="form-control" id="dueDate" name="due_date" type="date" value="{{ old('due_date',$invoice->due_date) }}"></div>
    </div>
    <h2>Line items</h2>
    <div id="invoiceLines">
    @foreach($items as $item)
        <fieldset class="ds-panel invoice-line"><legend>Line {{ $loop->iteration }}</legend><div class="ds-form-grid">
            <div><label class="form-label">Description</label><input class="form-control" name="item_description[]" value="{{ $item->description }}" required></div>
            <div><label class="form-label">Type</label><select class="form-control" name="item_type_id[]">@foreach($types as $type)<option value="{{ $type->id }}" @selected($item->invoice_type_id == $type->id)>{{ $type->name }}</option>@endforeach</select></div>
            <div><label class="form-label">Quantity</label><input class="form-control" name="item_qty[]" type="number" min="0.01" step="0.01" value="{{ $item->qty }}" required></div>
            <div><label class="form-label">Unit price</label><input class="form-control" name="item_price[]" type="number" min="0" step="0.01" value="{{ $item->unit_price }}" required></div>
        </div><button type="button" class="btn btn-outline" onclick="if(document.querySelectorAll('.invoice-line').length>1)this.closest('fieldset').remove()">Remove line</button></fieldset>
    @endforeach
    </div>
    <button type="button" class="btn btn-outline" onclick="addInvoiceLine()">Add line</button>
    <div class="ds-form-grid" style="margin:24px 0">
        <div><label class="form-label" for="taxType">Tax</label><select id="taxType" class="form-control" name="tax_type_id"><option value="">No tax</option>@foreach($taxTypes as $tax)<option value="{{ $tax->id }}" @selected($invoice->tax_type_id == $tax->id)>{{ $tax->name }} ({{ $tax->rate }}%)</option>@endforeach</select></div>
        <div><label class="form-label" for="discountType">Discount type</label><select id="discountType" class="form-control" name="discount_type"><option value="fixed" @selected($invoice->discount_type === 'fixed')>Fixed amount</option><option value="percentage" @selected($invoice->discount_type === 'percentage')>Percentage</option></select></div>
        <div><label class="form-label" for="discountValue">Discount value</label><input id="discountValue" class="form-control" name="discount_value" type="number" min="0" step="0.01" value="{{ $invoice->discount_value ?? 0 }}"></div>
    </div>
    <button class="btn btn-primary" name="status" value="draft">Save draft</button>
    <button class="btn btn-outline" name="status" value="pending_approval">Submit for approval</button>
    <a class="btn btn-outline" href="/invoices">Cancel</a>
</form>
<script>
function addInvoiceLine(){const row=document.querySelector('.invoice-line').cloneNode(true);row.querySelector('legend').textContent='New line';row.querySelectorAll('input').forEach(input=>{input.removeAttribute('id');input.value=input.name==='item_qty[]'?'1':input.name==='item_price[]'?'0':''});row.querySelectorAll('label').forEach(label=>label.removeAttribute('for'));document.getElementById('invoiceLines').appendChild(row);row.querySelector('input').focus()}
</script>
@endsection
