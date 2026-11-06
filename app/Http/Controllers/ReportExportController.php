<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportExportController extends Controller
{
    public function export(Request $request, string $report, string $format)
    {
        $reports = ['pnl' => 'pnl', 'balance-sheet' => 'balanceSheet', 'cash-flow' => 'cashFlow', 'expenses' => 'expenses', 'expense-trend' => 'expenseTrend', 'commissions' => 'commissions', 'cost-allocations' => 'costAllocations', 'bank-reconciliation' => 'bankReconciliation', 'project-status' => 'projectStatus', 'project-profitability' => 'projectProfitability', 'client-health' => 'clientHealth', 'ar-aging' => 'arAging', 'client-statement' => 'clientStatement', 'party-ledger' => 'partyLedger'];
        abort_unless(isset($reports[$report]) && in_array($format, ['xlsx', 'pdf']), 404);
        $view = app(ReportController::class)->{$reports[$report]}($request);
        $html = $view->render();
        $dom = new \DOMDocument;
        $old = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($old);
        $xpath = new \DOMXPath($dom);
        $rows = [];
        $title = trim($xpath->query('//main//h1')->item(0)?->textContent ?? Str::headline($report));
        $rows[] = [$title];
        $rows[] = ['From', $request->input('from', date('Y-m-01')), 'To', $request->input('to', date('Y-m-t'))];
        foreach ($xpath->query('//main//div[contains(@class,"ds-notice")]') as $notice) {
            $rows[] = [trim($notice->textContent)];
        }
        foreach ($xpath->query('//main//table') as $table) {
            $rows[] = [];
            foreach ($xpath->query('.//tr', $table) as $tr) {
                $cells = [];
                foreach ($xpath->query('./th|./td', $tr) as $cell) {
                    $cells[] = preg_replace('/\s+/u', ' ', trim($cell->textContent));
                }
                if ($cells) {
                    $rows[] = $cells;
                }
            }
        }
        // Some financial summaries are key/value rows rather than tables.
        foreach ($xpath->query('//main//*[contains(@class,"work-row")]') as $row) {
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if ($cell instanceof \DOMElement) {
                    $cells[] = trim($cell->textContent);
                }
            }
            if ($cells) {
                $rows[] = $cells;
            }
        }
        if ($format === 'pdf') {
            return Pdf::loadView('reports.export-pdf', compact('title', 'rows'))->setPaper('a4', 'landscape')->download($report.'.pdf');
        }

        return $this->xlsx($report, $rows);
    }

    private function xlsx(string $name, array $rows)
    {
        $path = tempnam(sys_get_temp_dir(), 'finance-export-');
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create workbook.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="40" customWidth="1"/><col min="2" max="20" width="24" customWidth="1"/></cols><sheetData>';
        foreach ($rows as $index => $row) {
            $xml .= '<row r="'.($index + 1).'">';
            foreach ($row as $value) {
                $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                // Inline strings prevent untrusted labels from becoming formulas.
                $xml .= '<c t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
        $zip->close();

        return response()->download($path, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }
}
