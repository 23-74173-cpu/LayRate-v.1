<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'LayRate' }} — LayRate Farm Monitor</title>

    {{-- Tailwind CSS CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Chart.js CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    {{-- Lucide Icons CDN --}}
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: { DEFAULT: '#102A4C', dark: '#001F42', mid: '#1D4E8F' },
                        cream: '#F5F5F0',
                        border: '#D9D9D9',
                        muted: '#6B7280',
                        ink:   '#333333',
                    }
                }
            }
        }
    </script>

    <style>
        body { background-color: #F5F5F0; font-family: ui-sans-serif, system-ui, sans-serif; }
        .nav-active { background: rgba(255,255,255,.2); box-shadow: inset 0 0 0 1px rgba(255,255,255,.25); }
        .scrollbar-thin::-webkit-scrollbar { width: 4px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #D9D9D9; border-radius: 9999px; }
        [x-cloak] { display: none !important; }
    </style>

    @stack('head')
</head>
<body class="flex h-screen overflow-hidden">

{{-- ─── Sidebar ──────────────────────────────────────────────────────────── --}}
<aside id="sidebar" class="w-14 bg-[#102A4C] flex flex-col py-3 shrink-0 justify-between transition-all duration-200 ease-in-out overflow-hidden">

    {{-- Logo --}}
    <div class="flex flex-col gap-1 items-center">
        <div class="flex items-center justify-center mb-4 px-2 w-full">
            <div class="w-9 h-9 rounded-lg bg-[#1F4B7D] flex items-center justify-center shrink-0 border border-white/25">
                <i data-lucide="feather" class="w-5 h-5 text-white"></i>
            </div>
            <div class="sidebar-label ml-2.5 overflow-hidden whitespace-nowrap hidden">
                <div class="text-white text-sm font-semibold">LayRate</div>
                <div class="text-white/75 text-[10px]">Farm Monitor</div>
            </div>
        </div>

        {{-- Nav items --}}
        @php
        $nav = [
            ['icon'=>'home',          'label'=>'Dashboard',       'route'=>'dashboard'],
            ['icon'=>'feather',       'label'=>'Cages',           'route'=>'cages.index'],
            ['icon'=>'egg',           'label'=>'Egg Logging',     'route'=>'egg-logging'],
            ['icon'=>'thermometer',   'label'=>'Environment',     'route'=>'environment'],
            ['icon'=>'leaf',          'label'=>'Feed & Nutrition','route'=>'feed'],
            ['icon'=>'bar-chart-3',   'label'=>'Analytics',       'route'=>'analytics'],
            ['icon'=>'trending-up',   'label'=>'Forecast',        'route'=>'forecast'],
            ['icon'=>'clipboard-list','label'=>'Reports',         'route'=>'reports'],
            ['icon'=>'skull',         'label'=>'Mortality Log',   'route'=>'mortality.index'],
        ];
        @endphp

        @foreach($nav as $item)
        @php $active = request()->routeIs($item['route']); @endphp
        <a href="{{ route($item['route']) }}"
           class="group flex items-center gap-2.5 rounded-lg transition-colors w-10 h-10 justify-center mx-auto
                  {{ $active ? 'nav-active text-white' : 'text-white/85 hover:text-white hover:bg-white/10' }}"
           title="{{ $item['label'] }}">
            <i data-lucide="{{ $item['icon'] }}" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
            <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden hidden">{{ $item['label'] }}</span>
        </a>
        @endforeach
    </div>

    {{-- Bottom items --}}
    <div class="flex flex-col gap-1 items-center">
        <a href="#" class="group flex items-center gap-2.5 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors w-10 h-10 justify-center mx-auto" title="Settings">
            <i data-lucide="settings" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
            <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden hidden">Settings</span>
        </a>
        <a href="#" class="group flex items-center gap-2.5 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors w-10 h-10 justify-center mx-auto" title="Profile">
            <i data-lucide="user" class="w-[19px] h-[19px] shrink-0 transition-transform duration-200 group-hover:-translate-y-0.5 group-hover:scale-110"></i>
            <span class="sidebar-label text-sm font-medium whitespace-nowrap overflow-hidden hidden">Profile</span>
        </a>
    </div>
</aside>

{{-- ─── Main Area ────────────────────────────────────────────────────────── --}}
<div class="flex flex-col flex-1 min-w-0 overflow-hidden">

    {{-- Header --}}
    <header class="bg-[#1A2E1A] h-11 flex items-center justify-between px-4 shrink-0">
        <div class="flex items-center gap-3">
            {{-- Sidebar toggle --}}
            <button id="sidebarToggle" class="text-white/70 hover:text-white transition-colors p-1 rounded">
                <i data-lucide="menu" class="w-4 h-4"></i>
            </button>
            <nav class="text-xs text-white/60">
                <a href="{{ route('dashboard') }}" class="hover:text-white/90 transition-colors">Home</a>
                <span class="mx-1">/</span>
                <span class="text-white">{{ $title ?? 'Dashboard' }}</span>
            </nav>
        </div>

        <div class="flex items-center gap-3">
            <span class="flex items-center gap-1.5 text-[11px] px-2.5 py-1 rounded-full bg-emerald-900/60 text-emerald-300 border border-emerald-600/40">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Offline · Local Network
            </span>
            <a href="#" class="relative text-white/70 hover:text-white transition-colors">
                <i data-lucide="bell" class="w-4 h-4"></i>
                @php $alertCount = \App\Models\Alert::where('is_read',0)->count(); @endphp
                @if($alertCount > 0)
                <span class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-red-500 text-white text-[8px] rounded-full flex items-center justify-center font-bold">{{ $alertCount }}</span>
                @endif
            </a>
            {{-- User + logout --}}
            <div class="flex items-center gap-2 pl-2 border-l border-white/20">
                <div class="text-right hidden sm:block">
                    <div class="text-[11px] text-white/90 leading-tight">{{ auth()->user()->name }}</div>
                    <div class="text-[9px] text-white/50 uppercase tracking-wider">{{ auth()->user()->role }}</div>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" title="Sign out"
                            class="text-white/60 hover:text-white transition-colors p-1 rounded">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="mx-4 mt-3 flex items-center gap-2 bg-green-50 border border-green-200 text-green-800 px-4 py-2.5 rounded-lg text-sm">
        <i data-lucide="check-circle" class="w-4 h-4 text-green-600 shrink-0"></i>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mx-4 mt-3 flex items-center gap-2 bg-red-50 border border-red-200 text-red-800 px-4 py-2.5 rounded-lg text-sm">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-red-600 shrink-0"></i>
        {{ session('error') }}
    </div>
    @endif

    {{-- Page content --}}
    <div class="flex-1 overflow-auto scrollbar-thin">
        @yield('content')
    </div>
</div>

{{-- ─── Scripts ──────────────────────────────────────────────────────────── --}}
<script>
lucide.createIcons();

// Sidebar expand/collapse
const sidebar       = document.getElementById('sidebar');
const toggle        = document.getElementById('sidebarToggle');
const sidebarLabels = document.querySelectorAll('.sidebar-label');
let expanded        = false;

toggle.addEventListener('click', () => {
    expanded = !expanded;
    if (expanded) {
        sidebar.classList.replace('w-14','w-52');
        sidebar.querySelectorAll('a,div').forEach(el => el.classList.remove('justify-center','mx-auto'));
        sidebar.querySelectorAll('a').forEach(el => {
            el.classList.remove('w-10','h-10','justify-center','mx-auto');
            el.classList.add('px-2.5','py-2.5','w-full');
        });
        sidebarLabels.forEach(l => l.classList.remove('hidden'));
        // Fix logo wrapper
        sidebar.querySelector('.flex.items-center.justify-center').classList.replace('justify-center','justify-start');
        sidebar.querySelector('.flex.items-center.justify-center')?.classList.add('px-1.5');
    } else {
        sidebar.classList.replace('w-52','w-14');
        sidebarLabels.forEach(l => l.classList.add('hidden'));
        sidebar.querySelectorAll('a').forEach(el => {
            el.classList.remove('px-2.5','py-2.5','w-full');
            el.classList.add('w-10','h-10','justify-center','mx-auto');
        });
    }
});
</script>

@stack('scripts')
</body>
</html>
