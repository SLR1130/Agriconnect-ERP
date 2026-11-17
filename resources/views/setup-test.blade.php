@php
    $framework = $status['framework'];
    $directories = $status['directories'];
    $database = $status['database'];
    $migrations = $status['migrations'];
    $modules = $status['modules'];
    $frontendReady = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
@endphp
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>AgriConnect ERP - Setup Status</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #EAF2E8 0%, #F8FAF7 100%); min-height: 100vh; padding: 20px; color: #26352D; }
        .container { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(35, 122, 82, 0.1); max-width: 900px; width: 100%; margin: 0 auto; overflow: hidden; }
        .header { background: linear-gradient(135deg, #163D2A 0%, #237A52 100%); color: white; padding: 40px; text-align: center; }
        .logo { width: 80px; height: 80px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .logo svg { width: 40px; height: 40px; }
        h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .subtitle { font-size: 1.1rem; opacity: 0.9; }
        .content { padding: 40px; }
        h2 { color: #163D2A; font-size: 1.5rem; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #EAF2E8; }
        .status-grid, .modules-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .status-card, .module-card { background: #F8FAF7; border-radius: 12px; padding: 20px; border: 1px solid #EAF2E8; }
        .status-title, .module-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-weight: 600; color: #26352D; margin-bottom: 10px; }
        .status-title { justify-content: flex-start; }
        .status-icon { width: 24px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; }
        .icon-success { background: #D4EDDA; color: #155724; }
        .icon-warning { background: #FFF3CD; color: #856404; }
        .status-value { font-size: 1.45rem; font-weight: 700; color: #237A52; margin-bottom: 5px; }
        .status-description, .module-description { font-size: 0.9rem; color: #666; line-height: 1.5; }
        .module-name { color: #163D2A; font-size: 1.05rem; }
        .module-status { padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
        .status-active { background: #D4EDDA; color: #155724; }
        .status-development { background: #FFF3CD; color: #856404; }
        .details { background: #F8FAF7; border: 1px solid #EAF2E8; border-radius: 10px; padding: 16px; margin-bottom: 25px; }
        .details dl { display: grid; grid-template-columns: 150px 1fr; gap: 8px 15px; font-size: 0.9rem; }
        .details dt { font-weight: 600; color: #163D2A; }
        .details dd { overflow-wrap: anywhere; }
        .actions { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 30px; padding-top: 30px; border-top: 2px solid #EAF2E8; }
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; background: #237A52; color: white; }
        .btn.secondary { background: #163D2A; }
        .footer { background: #F8FAF7; padding: 20px 40px; text-align: center; color: #666; font-size: 0.9rem; }
        @media (max-width: 600px) { .content, .header { padding: 25px; } .details dl { grid-template-columns: 1fr; gap: 3px; } }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <div class='logo'>
                <svg fill='currentColor' viewBox='0 0 20 20'>
                    <path fill-rule='evenodd' d='M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z' clip-rule='evenodd' />
                </svg>
            </div>
            <h1>AgriConnect ERP</h1>
            <p class='subtitle'>Live application and database health status</p>
        </div>

        <div class='content'>
            <h2>System Status</h2>
            <div class='status-grid'>
                <div class='status-card'>
                    <div class='status-title'><span class='status-icon icon-success'>OK</span>Laravel Project</div>
                    <div class='status-value'>Ready</div>
                    <div class='status-description'>{{ $framework['description'] }}</div>
                </div>
                <div class='status-card'>
                    <div class='status-title'><span class='status-icon {{ $database['connected'] ? 'icon-success' : 'icon-warning' }}'>{{ $database['connected'] ? 'OK' : '!' }}</span>Database</div>
                    <div class='status-value'>{{ $database['connected'] ? 'Connected' : 'Unavailable' }}</div>
                    <div class='status-description'>{{ $database['description'] }}</div>
                </div>
                <div class='status-card'>
                    <div class='status-title'><span class='status-icon {{ $frontendReady ? 'icon-success' : 'icon-warning' }}'>{{ $frontendReady ? 'OK' : '!' }}</span>Frontend</div>
                    <div class='status-value'>{{ $frontendReady ? 'Ready' : 'Missing' }}</div>
                    <div class='status-description'>{{ $frontendReady ? 'Compiled frontend assets are available' : 'Compiled frontend assets were not found' }}</div>
                </div>
                <div class='status-card'>
                    <div class='status-title'><span class='status-icon {{ $directories['ready'] ? 'icon-success' : 'icon-warning' }}'>{{ $directories['ready'] ? 'OK' : '!' }}</span>Structure</div>
                    <div class='status-value'>{{ $directories['ready'] ? 'Complete' : 'Incomplete' }}</div>
                    <div class='status-description'>{{ $directories['description'] }}</div>
                </div>
                <div class='status-card'>
                    <div class='status-title'><span class='status-icon {{ $migrations['ready'] ? 'icon-success' : 'icon-warning' }}'>{{ $migrations['ready'] ? 'OK' : '!' }}</span>Migrations</div>
                    <div class='status-value'>{{ $migrations['applied'] }}/{{ $migrations['total'] }}</div>
                    <div class='status-description'>{{ $migrations['description'] }}</div>
                </div>
                <div class='status-card'>
                    <div class='status-title'><span class='status-icon {{ $modules['ready'] ? 'icon-success' : 'icon-warning' }}'>{{ $modules['ready'] ? 'OK' : '!' }}</span>ERP Modules</div>
                    <div class='status-value'>{{ $modules['registered'] }}/{{ $modules['expected'] }}</div>
                    <div class='status-description'>{{ $modules['description'] }}</div>
                </div>
            </div>

            <h2>Agricultural Modules</h2>
            <div class='modules-grid'>
                @foreach($modules['modules'] as $module)
                    <div class='module-card'>
                        <div class='module-header'>
                            <div class='module-name'>{{ strtoupper($module['slug']) }}</div>
                            <div class='module-status {{ $module['registered'] && $module['enabled'] ? 'status-active' : 'status-development' }}'>{{ $module['registered'] ? ($module['enabled'] ? 'Registered' : 'Disabled') : 'Missing' }}</div>
                        </div>
                        <div class='module-description'>{{ $module['name'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class='details'>
                <strong>Authorized diagnostics</strong>
                <dl>
                    <dt>Framework</dt><dd>{{ $framework['version'] }}</dd>
                    @if(isset($database['details']))
                        <dt>Database driver</dt><dd>{{ $database['details']['driver'] }}</dd>
                        <dt>Database name</dt><dd>{{ $database['details']['database'] }}</dd>
                        <dt>Database host</dt><dd>{{ $database['details']['host'] }}:{{ $database['details']['port'] }}</dd>
                        <dt>Database user</dt><dd>{{ $database['details']['username'] }}</dd>
                    @endif
                </dl>
            </div>

            <div class='actions'>
                <a href='{{ route('home') }}' class='btn secondary'>Back to Homepage</a>
                <a href='{{ route('dashboard') }}' class='btn'>Open Dashboard</a>
            </div>
        </div>

        <div class='footer'>
            <p>AgriConnect ERP Core v{{ config('agriconnect.version', '1.0.0') }} &bull; Bongabong Municipal Agriculture Office</p>
            <p style='margin-top: 5px;'>Status generated from the current application runtime.</p>
        </div>
    </div>
</body>
</html>
