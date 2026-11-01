@php
    $submittedAt = \Illuminate\Support\Carbon::parse(data_get($snapshot, 'submission.submitted_at_utc'))->timezone(config('agriculture.business_timezone'));
    $documentRequirements = data_get($snapshot, 'requirements_version.documents', []);
    $collectionEnabled = data_get($snapshot, 'document_receipt.collection_status') === 'enabled';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Application acknowledgment · {{ data_get($snapshot, 'application.reference') }}</title>
    <style>
        :root{color-scheme:light;--forest:#153d2b;--emerald:#26865c;--gold:#d5ad55;--sage:#e7f0e8;--ink:#24342b;--muted:#5e6b63;--line:#d8e1da}
        *{box-sizing:border-box}body{margin:0;background:#edf2ed;color:var(--ink);font:15px/1.55 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}
        .toolbar{display:flex;flex-wrap:wrap;justify-content:center;gap:.75rem;padding:1rem}.toolbar a,.toolbar button{display:inline-flex;min-height:44px;align-items:center;justify-content:center;border:1px solid var(--forest);border-radius:8px;padding:.65rem 1rem;background:var(--forest);color:#fff;font:700 .9rem inherit;text-decoration:none;cursor:pointer}.toolbar .secondary{background:white;color:var(--forest)}
        .paper{width:min(100% - 2rem,850px);margin:0 auto 2rem;background:#fff;border:1px solid var(--line);box-shadow:0 12px 35px #153d2b12;padding:clamp(1.25rem,4vw,3rem)}
        .masthead{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;border-bottom:3px solid var(--gold);padding-bottom:1.25rem}.brand{display:flex;gap:.85rem;align-items:center}.brand-mark{display:grid;width:48px;height:48px;place-items:center;border-radius:14px;background:var(--forest);color:#fff;font-weight:900;letter-spacing:.03em}.brand h1{margin:0;color:var(--forest);font-size:1.05rem;line-height:1.2}.brand p,.office{text-align:right;margin:0;color:var(--muted);font-size:.8rem}.office strong{display:block;color:var(--forest);font-size:.9rem}
        .title{margin:2rem 0 1.25rem;text-align:center}.eyebrow{color:var(--emerald);font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.title h2{margin:.3rem 0;color:var(--forest);font-size:clamp(1.45rem,4vw,2rem);letter-spacing:.02em}.reference{display:inline-block;margin-top:.45rem;border:1px solid #c9ddce;border-radius:999px;background:var(--sage);padding:.35rem .8rem;color:var(--forest);font-size:.9rem;font-weight:800;letter-spacing:.06em;overflow-wrap:anywhere}
        .received-banner{margin:1.25rem 0;border-left:4px solid var(--emerald);background:#f3f8f4;padding:1rem}.received-banner strong{color:var(--forest)}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.fact{border:1px solid var(--line);border-radius:10px;padding:.85rem}.fact dt{color:var(--muted);font-size:.7rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.fact dd{margin:.25rem 0 0;font-weight:650;overflow-wrap:anywhere}
        section{margin-top:1.4rem}section h3{margin:0 0 .5rem;color:var(--forest);font-size:1rem}ul{margin:.4rem 0;padding-left:1.25rem}li{margin:.3rem 0}.notice{border:1px solid #e5d6a6;border-radius:10px;background:#fff9e6;padding:.9rem;color:#67521b;font-size:.86rem}.declaration{margin-top:1.5rem;border:2px solid var(--forest);padding:1rem;text-align:center;color:var(--forest);font-size:.86rem;font-weight:900;letter-spacing:.035em}.footer{margin-top:1.25rem;border-top:1px solid var(--line);padding-top:.75rem;color:var(--muted);font-size:.77rem}.small{font-size:.8rem;color:var(--muted)}
        @media(max-width:560px){.masthead{flex-direction:column}.office{text-align:left}.grid{grid-template-columns:1fr}.toolbar a,.toolbar button{flex:1 1 100%}.paper{width:100%;margin:0;padding:1.1rem;border-left:0;border-right:0;box-shadow:none}}
        @media print{@page{size:A4;margin:16mm}body{background:white;font-size:11pt}.toolbar{display:none}.paper{width:100%;max-width:none;margin:0;padding:0;border:0;box-shadow:none}.masthead{break-inside:avoid}.received-banner,.fact,.notice,.declaration{break-inside:avoid;-webkit-print-color-adjust:exact;print-color-adjust:exact}.title{margin-top:1.3rem}}
    </style>
</head>
<body>
    @unless($download)
        <nav class="toolbar" aria-label="Acknowledgment actions">
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
            <a class="secondary" href="{{ request()->fullUrlWithQuery(['download' => 1]) }}">Download acknowledgment</a>
        </nav>
    @endunless
    <main class="paper">
        <header class="masthead">
            <div class="brand"><div class="brand-mark" aria-hidden="true">AC</div><div><h1>AgriConnect ERP</h1><p>Agricultural services application record</p></div></div>
            <p class="office"><strong>{{ data_get($snapshot, 'office.name', 'Municipal Agriculture Office') }}</strong>{{ data_get($snapshot, 'office.municipality') }}@if(data_get($snapshot, 'office.contact'))<br>{{ data_get($snapshot, 'office.contact') }}@endif</p>
        </header>

        <div class="title">
            <span class="eyebrow">Submission acknowledgment</span>
            <h2>Application received</h2>
            <span class="reference">{{ data_get($snapshot, 'application.reference') }}</span>
        </div>

        <div class="received-banner"><strong>Application status when received:</strong> {{ str(data_get($snapshot, 'application.status_at_submission', 'submitted'))->replace('_', ' ')->headline() }}. This confirms receipt of the application data shown below.</div>

        <dl class="grid">
            <div class="fact"><dt>Applicant</dt><dd>{{ data_get($snapshot, 'applicant.name', 'Not recorded') }}</dd></div>
            <div class="fact"><dt>Farmer reference</dt><dd>{{ data_get($snapshot, 'applicant.farmer_code', 'Not recorded') }}</dd></div>
            <div class="fact"><dt>Barangay on FFRMS record</dt><dd>{{ data_get($snapshot, 'applicant.barangay', 'Not recorded') }}</dd></div>
            <div class="fact"><dt>Program</dt><dd>{{ data_get($snapshot, 'program.name', 'Not recorded') }}</dd></div>
            <div class="fact"><dt>Received</dt><dd>{{ $submittedAt->format('F j, Y · g:i A') }} Philippine time</dd></div>
            <div class="fact"><dt>Submission channel</dt><dd>{{ str(data_get($snapshot, 'application.channel', 'farmer_portal'))->replace('_', ' ')->headline() }}</dd></div>
        </dl>

        <section aria-labelledby="requirements-heading">
            <h3 id="requirements-heading">Requirements in the applicable version</h3>
            @if(count($documentRequirements))
                <ul>@foreach($documentRequirements as $requirement)<li><strong>{{ $requirement['name'] }}</strong> — {{ $requirement['required'] ? 'Required' : 'Optional' }}@if($requirement['description']) · {{ $requirement['description'] }}@endif</li>@endforeach</ul>
            @else
                <p class="small">No digitized document checklist was recorded for this application version.</p>
            @endif
            @if(!$collectionEnabled)
                <div class="notice"><strong>Supporting-document receipt:</strong> Online document upload was not enabled when this application was submitted. No supporting files were received through this online application. This acknowledgment does not list requirements as missing or claim that documents were received. Follow the published MAO instructions if supporting documents are required.</div>
            @else
                <p class="small">Received and missing items below reflect the saved submission snapshot.</p>
                <ul>@foreach(data_get($snapshot, 'document_receipt.received', []) as $item)<li>Received: {{ $item['name'] ?? $item }}</li>@endforeach</ul>
                @if(is_array(data_get($snapshot, 'document_receipt.missing')))<ul>@foreach(data_get($snapshot, 'document_receipt.missing') as $item)<li>Not received: {{ $item['name'] ?? $item }}</li>@endforeach</ul>@endif
            @endif
        </section>

        <section aria-labelledby="tracking-heading"><h3 id="tracking-heading">Tracking your application</h3><p>Sign in to the AgriConnect Farmer Portal and open My applications to view status updates and MAO feedback. Keep this reference when contacting the Municipal Agriculture Office.</p></section>

        <p class="declaration">THIS DOCUMENT ACKNOWLEDGES RECEIPT OF AN APPLICATION. IT DOES NOT GUARANTEE APPROVAL OR ENTITLEMENT TO GOVERNMENT ASSISTANCE.</p>
        <footer class="footer"><p>Application reference: <strong>{{ data_get($snapshot, 'application.reference') }}</strong></p><p>This is an application acknowledgment. It is not an Official Receipt, claim ticket, proof of approval, or proof of distribution.</p><p>Snapshot hash: {{ data_get($snapshot, 'snapshot_sha256_display', '') ?: 'Record retained by AgriConnect' }}</p></footer>
    </main>
</body>
</html>
