<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Client Portal')</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('styles.css') }}" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <style>
        :root {
            --font-family: 'Plus Jakarta Sans', sans-serif;
        }
        body { 
            font-family: var(--font-family); 
            background: var(--bg-page, #0f172a); 
            color: var(--text-main, #334155); 
            margin: 0; 
            padding: 0;
            min-height: 100vh;
            line-height: 1.6;
        }
        .share-banner { 
            background: linear-gradient(90deg, #4f46e5 0%, #7c3aed 50%, #2563eb 100%); 
            color: white; 
            padding: 0.65rem 1.5rem; 
            font-size: 0.85rem; 
            font-weight: 600; 
            letter-spacing: 0.4px; 
            position: sticky; 
            top: 0; 
            z-index: 1000; 
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .share-container { 
            max-width: 1140px; 
            margin: 2.5rem auto; 
            padding: 0 1.5rem 4rem 1.5rem; 
        }
        .portal-card { 
            background: var(--bg-card, #ffffff); 
            border-radius: 16px; 
            border: 1px solid var(--border, #e2e8f0); 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); 
            padding: 1.75rem; 
            margin-bottom: 1.75rem; 
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .portal-card:hover {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        }
        .glass-header {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(124, 58, 237, 0.04) 100%);
            border: 1px solid rgba(79, 70, 229, 0.15);
            border-radius: 16px;
            padding: 2.25rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
        }
        .metric-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .interactive-tab-btn {
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            border: none;
            background: transparent;
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .interactive-tab-btn.active {
            background: var(--primary, #4f46e5);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        .theme-toggle-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: background 0.2s ease;
        }
        .theme-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.35);
        }
        @media (max-width: 768px) {
            .share-banner {
                padding: 0.65rem 1rem;
                font-size: 0.75rem;
                flex-direction: row;
                gap: 0.5rem;
            }
            .share-container {
                margin: 1rem auto;
                padding: 0 0.75rem 2rem 0.75rem;
            }
            .glass-header {
                padding: 1.25rem 1rem;
                border-radius: 12px;
                margin-bottom: 1.25rem;
            }
            .portal-card {
                padding: 1.15rem 1rem;
                border-radius: 12px;
                margin-bottom: 1.25rem;
            }
            .interactive-tab-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body data-theme="light">
    <!-- Top Security Banner -->
    <div class="share-banner">
        <div style="display:flex; align-items:center; gap:0.5rem;">
            <ion-icon name="shield-checkmark-outline" style="font-size:1.2rem;"></ion-icon> 
            <span>Verified Client Portal &mdash; Secure Live Share</span>
        </div>
        <button type="button" class="theme-toggle-btn" onclick="togglePortalTheme()">
            <ion-icon name="moon-outline" id="portalThemeIcon"></ion-icon> <span id="portalThemeText">Dark Mode</span>
        </button>
    </div>
    
    <div class="share-container">
        @yield('content')
    </div>

    <script>
    function togglePortalTheme() {
        const body = document.body;
        const current = body.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        body.setAttribute('data-theme', next);
        document.getElementById('portalThemeIcon').setAttribute('name', next === 'dark' ? 'sunny-outline' : 'moon-outline');
        document.getElementById('portalThemeText').innerText = next === 'dark' ? 'Light Mode' : 'Dark Mode';
    }
    </script>
</body>
</html>
