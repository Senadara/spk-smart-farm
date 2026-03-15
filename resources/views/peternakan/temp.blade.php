<html lang="en"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livestock Management Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet">
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* slate-50 */
        }
        
        /* Subtle scrollbar for overflow areas */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .chart-gradient {
            background: linear-gradient(180deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0) 100%);
        }
    </style>
</head>
<body class="text-slate-800 antialiased selection:bg-emerald-100 selection:text-emerald-900">

    <!-- Navbar / Header Area (Minimalist Context) -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3 text-emerald-600">
                <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center text-white font-semibold tracking-tighter">
                    AV
                </div>
                <span class="font-medium tracking-tight text-slate-900 hidden sm:block">Avisense Platform</span>
            </div>
            <div class="flex items-center gap-4 text-slate-500">
                <button class="hover:text-slate-900 transition-colors"><iconify-icon icon="solar:bell-linear" class="text-xl"></iconify-icon></button>
                <button class="hover:text-slate-900 transition-colors"><iconify-icon icon="solar:settings-linear" class="text-xl"></iconify-icon></button>
                <div class="w-8 h-8 rounded-full bg-slate-200 border border-slate-300 overflow-hidden">
                    <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&amp;auto=format&amp;fit=facearea&amp;facepad=2&amp;w=256&amp;h=256&amp;q=80" alt="User profile" class="w-full h-full object-cover">
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <nav class="flex items-center text-xs text-slate-500 gap-2 mb-3 font-medium">
                <a href="#" class="hover:text-slate-900 transition-colors">Dashboard</a>
                <iconify-icon icon="solar:alt-arrow-right-linear"></iconify-icon>
                <a href="#" class="hover:text-slate-900 transition-colors">Barns</a>
                <iconify-icon icon="solar:alt-arrow-right-linear"></iconify-icon>
                <span class="text-slate-900">Barn A</span>
            </nav>
            
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-900 flex items-center gap-3">
                        Barn A
                        <span class="text-xl font-normal text-slate-400">Layer Chicken</span>
                    </h1>
                    <div class="flex flex-wrap items-center gap-3 mt-3">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Optimal Status
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 font-medium">
                            <iconify-icon icon="solar:calendar-linear" class="text-slate-400"></iconify-icon>
                            Flock Age: 34 Weeks
                        </span>
                        <span class="text-slate-300 text-xs">|</span>
                        <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 font-medium">
                            <iconify-icon icon="solar:users-group-two-rounded-linear" class="text-slate-400"></iconify-icon>
                            24,850 / 25,000 Birds
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm border border-slate-200 hover:bg-slate-50 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        <iconify-icon icon="solar:printer-linear"></iconify-icon>
                        Report
                    </button>
                    <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        <iconify-icon icon="solar:pen-linear"></iconify-icon>
                        Log Event
                    </button>
                </div>
            </div>
        </div>

        <!-- Barn Overview Card -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8 flex flex-col lg:flex-row">
            <div class="lg:w-1/3 h-48 lg:h-auto relative bg-slate-100">
                <!-- Fallback abstract pattern if image fails, overlaid with subtle gradient -->
                <div class="absolute inset-0 bg-emerald-900/10 mix-blend-multiply z-10"></div>
                <img src="https://images.unsplash.com/photo-1516253593875-bd7ba052fbc5?auto=format&amp;fit=crop&amp;q=80&amp;w=800" alt="Barn exterior" class="w-full h-full object-cover">
                <div class="absolute bottom-4 left-4 z-20">
                    <div class="bg-black/40 backdrop-blur-md text-white text-xs px-2.5 py-1 rounded-md font-medium border border-white/10">
                        Camera Feed: Active
                    </div>
                </div>
            </div>
            <div class="lg:w-2/3 p-6 lg:p-8 flex flex-col justify-center">
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 mb-6">Barn Overview Details</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-y-6 gap-x-8">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">Location / Block</p>
                        <p class="text-sm font-semibold text-slate-900">North Farm, Block B</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">Livestock Type</p>
                        <p class="text-sm font-semibold text-slate-900">Layer (Hy-Line Brown)</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">Flock Housed Date</p>
                        <p class="text-sm font-semibold text-slate-900">Oct 12, 2023</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">Active Birds</p>
                        <p class="text-sm font-semibold text-slate-900">24,850</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">Total Capacity</p>
                        <p class="text-sm font-semibold text-slate-900">25,000 (99.4%)</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">Manager</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[0.65rem] font-semibold">JD</div>
                            <p class="text-sm font-semibold text-slate-900">John Doe</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Production KPI Row -->
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
            <!-- KPI 1 -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 relative overflow-hidden group hover:border-emerald-200 transition-colors">
                <p class="text-xs font-medium text-slate-500 mb-2">Hen-Day Prod. (HDP)</p>
                <div class="flex items-end justify-between">
                    <p class="text-2xl font-semibold tracking-tight text-slate-900">94.2<span class="text-base font-medium text-slate-500">%</span></p>
                </div>
                <div class="mt-2 flex items-center gap-1.5">
                    <span class="inline-flex items-center gap-0.5 text-xs font-medium text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
                        <iconify-icon icon="solar:arrow-right-up-linear"></iconify-icon> 1.2%
                    </span>
                    <span class="text-[0.65rem] text-slate-400">vs yesterday</span>
                </div>
                <!-- Sparkline -->
                <div class="absolute bottom-0 left-0 right-0 h-10 opacity-40">
                    <svg viewBox="0 0 100 30" class="w-full h-full preserve-aspect-ratio-none">
                        <path d="M0,25 L20,15 L40,18 L60,8 L80,12 L100,5" fill="none" stroke="#10b981" stroke-width="2" class="vector-effect-non-scaling-stroke"></path>
                    </svg>
                </div>
            </div>

            <!-- KPI 2 -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 relative overflow-hidden group hover:border-emerald-200 transition-colors">
                <p class="text-xs font-medium text-slate-500 mb-2">Laying Rate</p>
                <div class="flex items-end justify-between">
                    <p class="text-2xl font-semibold tracking-tight text-slate-900">92.8<span class="text-base font-medium text-slate-500">%</span></p>
                </div>
                <div class="mt-2 flex items-center gap-1.5">
                    <span class="inline-flex items-center gap-0.5 text-xs font-medium text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
                        <iconify-icon icon="solar:arrow-right-up-linear"></iconify-icon> 0.5%
                    </span>
                    <span class="text-[0.65rem] text-slate-400">vs yesterday</span>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-10 opacity-40">
                    <svg viewBox="0 0 100 30" class="w-full h-full preserve-aspect-ratio-none">
                        <path d="M0,20 L20,22 L40,15 L60,18 L80,10 L100,8" fill="none" stroke="#10b981" stroke-width="2" class="vector-effect-non-scaling-stroke"></path>
                    </svg>
                </div>
            </div>

            <!-- KPI 3 -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 relative overflow-hidden group hover:border-emerald-200 transition-colors">
                <p class="text-xs font-medium text-slate-500 mb-2">Avg Egg Weight</p>
                <div class="flex items-end justify-between">
                    <p class="text-2xl font-semibold tracking-tight text-slate-900">62.4<span class="text-base font-medium text-slate-500">g</span></p>
                </div>
                <div class="mt-2 flex items-center gap-1.5">
                    <span class="inline-flex items-center gap-0.5 text-xs font-medium text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">
                        <iconify-icon icon="solar:minus-linear"></iconify-icon> 0.0%
                    </span>
                    <span class="text-[0.65rem] text-slate-400">vs yesterday</span>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-10 opacity-40">
                    <svg viewBox="0 0 100 30" class="w-full h-full preserve-aspect-ratio-none">
                        <path d="M0,15 L20,16 L40,15 L60,15 L80,14 L100,15" fill="none" stroke="#64748b" stroke-width="2" class="vector-effect-non-scaling-stroke"></path>
                    </svg>
                </div>
            </div>

            <!-- KPI 4 -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 relative overflow-hidden group hover:border-emerald-200 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs font-medium text-slate-500">Feed Conv. Ratio</p>
                    <div class="w-2 h-2 rounded-full bg-emerald-500" title="Optimal FCR"></div>
                </div>
                <div class="flex items-end justify-between">
                    <p class="text-2xl font-semibold tracking-tight text-slate-900">1.95</p>
                </div>
                <div class="mt-2 flex items-center gap-1.5">
                    <span class="inline-flex items-center gap-0.5 text-xs font-medium text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
                        <iconify-icon icon="solar:arrow-right-down-linear"></iconify-icon> 0.02
                    </span>
                    <span class="text-[0.65rem] text-slate-400">vs target</span>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-10 opacity-40">
                    <svg viewBox="0 0 100 30" class="w-full h-full preserve-aspect-ratio-none">
                        <path d="M0,10 L20,12 L40,15 L60,18 L80,20 L100,22" fill="none" stroke="#10b981" stroke-width="2" class="vector-effect-non-scaling-stroke"></path>
                    </svg>
                </div>
            </div>

            <!-- KPI 5 -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 relative overflow-hidden group hover:border-amber-200 transition-colors">
                <p class="text-xs font-medium text-slate-500 mb-2">Mortality Rate</p>
                <div class="flex items-end justify-between">
                    <p class="text-2xl font-semibold tracking-tight text-slate-900">0.08<span class="text-base font-medium text-slate-500">%</span></p>
                </div>
                <div class="mt-2 flex items-center gap-1.5">
                    <span class="inline-flex items-center gap-0.5 text-xs font-medium text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded">
                        <iconify-icon icon="solar:arrow-right-up-linear"></iconify-icon> 0.01%
                    </span>
                    <span class="text-[0.65rem] text-slate-400">vs yesterday</span>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-10 opacity-40">
                    <svg viewBox="0 0 100 30" class="w-full h-full preserve-aspect-ratio-none">
                        <path d="M0,25 L20,25 L40,24 L60,20 L80,15 L100,10" fill="none" stroke="#f59e0b" stroke-width="2" class="vector-effect-non-scaling-stroke"></path>
                    </svg>
                </div>
            </div>

            <!-- KPI 6 -->
            <div class="bg-emerald-600 rounded-xl border border-emerald-500 shadow-sm p-5 relative overflow-hidden text-white">
                <div class="absolute top-0 right-0 p-4 opacity-20">
                    <iconify-icon icon="solar:egg-linear" class="text-6xl"></iconify-icon>
                </div>
                <p class="text-xs font-medium text-emerald-100 mb-2">Eggs Produced Today</p>
                <div class="flex items-end justify-between relative z-10">
                    <p class="text-2xl font-semibold tracking-tight text-white">23,412</p>
                </div>
                <div class="mt-2 flex items-center gap-1.5 relative z-10">
                    <div class="w-full bg-emerald-800/50 rounded-full h-1.5 mt-2">
                        <div class="bg-white h-1.5 rounded-full" style="width: 94%"></div>
                    </div>
                </div>
                <span class="text-[0.65rem] text-emerald-100 mt-1 block relative z-10">94% of expected</span>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
            
            <!-- Left Column: Charts -->
            <div class="xl:col-span-2 space-y-6">
                
                <!-- Production Trends Chart -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-lg font-semibold tracking-tight text-slate-900">Production Trends</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Hen-Day Production vs Egg Weight (Last 30 Days)</p>
                        </div>
                        <div class="flex bg-slate-100 p-0.5 rounded-lg self-start">
                            <button class="px-3 py-1.5 text-xs font-medium rounded-md text-slate-500 hover:text-slate-900 transition-colors">7d</button>
                            <button class="px-3 py-1.5 text-xs font-medium rounded-md bg-white text-slate-900 shadow-sm">30d</button>
                            <button class="px-3 py-1.5 text-xs font-medium rounded-md text-slate-500 hover:text-slate-900 transition-colors">CTD</button>
                        </div>
                    </div>

                    <!-- Chart Mockup (SVG) -->
                    <div class="relative h-64 w-full mt-4">
                        <!-- Y-Axis Labels Left (HDP %) -->
                        <div class="absolute left-0 top-0 bottom-6 w-8 flex flex-col justify-between text-[0.65rem] text-slate-400 font-medium">
                            <span>100</span>
                            <span>95</span>
                            <span>90</span>
                            <span>85</span>
                            <span>80</span>
                        </div>
                        
                        <!-- Y-Axis Labels Right (Weight g) -->
                        <div class="absolute right-0 top-0 bottom-6 w-8 flex flex-col justify-between text-[0.65rem] text-slate-400 font-medium text-right">
                            <span>65</span>
                            <span>63</span>
                            <span>61</span>
                            <span>59</span>
                            <span>57</span>
                        </div>

                        <!-- Chart Area -->
                        <div class="absolute left-10 right-10 top-2 bottom-6">
                            <!-- Horizontal Grid Lines -->
                            <div class="flex flex-col justify-between h-full w-full absolute inset-0">
                                <div class="border-t border-slate-100 w-full h-0"></div>
                                <div class="border-t border-slate-100 w-full h-0"></div>
                                <div class="border-t border-slate-100 w-full h-0"></div>
                                <div class="border-t border-slate-100 w-full h-0"></div>
                                <div class="border-t border-slate-100 w-full h-0"></div>
                            </div>
                            
                            <!-- SVG Lines -->
                            <svg viewBox="0 0 100 100" class="w-full h-full preserve-aspect-ratio-none relative z-10" preserveAspectRatio="none">
                                <!-- Area Fill HDP -->
                                <path d="M0,80 L10,60 L20,40 L30,25 L40,15 L50,12 L60,15 L70,10 L80,12 L90,15 L100,18 L100,100 L0,100 Z" fill="rgba(16, 185, 129, 0.05)"></path>
                                <!-- HDP Line (Emerald) -->
                                <path d="M0,80 L10,60 L20,40 L30,25 L40,15 L50,12 L60,15 L70,10 L80,12 L90,15 L100,18" fill="none" stroke="#10b981" stroke-width="2.5" class="vector-effect-non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"></path>
                                <!-- Egg Weight Line (Slate) -->
                                <path d="M0,90 L10,85 L20,70 L30,65 L40,55 L50,50 L60,45 L70,40 L80,38 L90,35 L100,35" fill="none" stroke="#64748b" stroke-width="2" class="vector-effect-non-scaling-stroke" stroke-dasharray="4 4" stroke-linecap="round" stroke-linejoin="round"></path>
                                
                                <!-- Hover Point Indicator -->
                                <circle cx="70" cy="10" r="4" fill="#ffffff" stroke="#10b981" stroke-width="2" class="vector-effect-non-scaling-stroke"></circle>
                            </svg>
                            
                            <!-- Tooltip Mock -->
                            <div class="absolute top-[5%] left-[65%] bg-slate-900 text-white text-[0.65rem] px-2 py-1 rounded shadow-lg pointer-events-none transform -translate-x-1/2 -translate-y-full mb-2">
                                <div class="font-medium">Day 240</div>
                                <div>HDP: 94.5%</div>
                            </div>
                        </div>

                        <!-- X-Axis Labels -->
                        <div class="absolute left-10 right-10 bottom-0 flex justify-between text-[0.65rem] text-slate-400 font-medium pt-2 border-t border-slate-200">
                            <span>Week 30</span>
                            <span>Week 31</span>
                            <span>Week 32</span>
                            <span>Week 33</span>
                            <span>Week 34</span>
                        </div>
                    </div>
                    
                    <!-- Legend -->
                    <div class="flex items-center justify-center gap-6 mt-6">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-1 bg-emerald-500 rounded-full"></div>
                            <span class="text-xs text-slate-600 font-medium">HDP (%)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-0.5 border-t-2 border-dashed border-slate-400"></div>
                            <span class="text-xs text-slate-600 font-medium">Egg Weight (g)</span>
                        </div>
                    </div>
                </div>

                <!-- Feed and Efficiency Trends -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold tracking-tight text-slate-900 mb-6">Feed Intake &amp; Efficiency (FCR)</h2>
                    
                    <div class="relative h-48 w-full">
                         <!-- Simplified Bar/Line Combo Chart -->
                         <div class="absolute left-0 top-0 bottom-6 w-8 flex flex-col justify-between text-[0.65rem] text-slate-400 font-medium">
                            <span>120g</span>
                            <span>110g</span>
                            <span>100g</span>
                        </div>

                        <div class="absolute left-10 right-0 top-2 bottom-6 flex items-end justify-between gap-2 border-b border-slate-200">
                            <!-- Bars (Feed Intake) -->
                            <div class="w-full bg-slate-100 rounded-t-sm h-[60%] relative group hover:bg-slate-200 transition-colors"></div>
                            <div class="w-full bg-slate-100 rounded-t-sm h-[65%] relative group hover:bg-slate-200 transition-colors"></div>
                            <div class="w-full bg-slate-100 rounded-t-sm h-[70%] relative group hover:bg-slate-200 transition-colors"></div>
                            <div class="w-full bg-slate-100 rounded-t-sm h-[68%] relative group hover:bg-slate-200 transition-colors"></div>
                            <div class="w-full bg-slate-100 rounded-t-sm h-[72%] relative group hover:bg-slate-200 transition-colors"></div>
                            <div class="w-full bg-emerald-100 rounded-t-sm h-[75%] relative group transition-colors">
                                <!-- FCR Line overlay simulated -->
                                <div class="absolute top-1/2 left-1/2 w-2 h-2 rounded-full bg-slate-800 transform -translate-x-1/2 -translate-y-1/2 border-2 border-white z-10"></div>
                            </div>
                            <div class="w-full bg-slate-100 rounded-t-sm h-[74%] relative group hover:bg-slate-200 transition-colors"></div>
                            
                            <!-- Connecting Line for FCR -->
                            <svg class="absolute inset-0 w-full h-full pointer-events-none" preserveAspectRatio="none">
                                 <path d="M7%,40% L21%,38% L35%,35% L49%,36% L63%,30% L77%,28% L91%,29%" fill="none" stroke="#1e293b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </div>
                        
                        <div class="absolute left-10 right-0 bottom-0 flex justify-between text-[0.65rem] text-slate-400 font-medium pt-2">
                            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Live Barn Conditions -->
            <div class="xl:col-span-1">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 h-full flex flex-col">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-semibold tracking-tight text-slate-900">Live Conditions</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Updated 2 mins ago</p>
                        </div>
                        <span class="flex h-2.5 w-2.5">
                          <span class="animate-ping absolute inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 flex-1">
                        <!-- Sensor 1: Temp -->
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 flex flex-col justify-between hover:border-slate-300 transition-colors">
                            <div class="flex justify-between items-start mb-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                    <iconify-icon icon="solar:thermometer-linear" class="text-lg"></iconify-icon>
                                </div>
                                <span class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[0.65rem] font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Optimal</span>
                            </div>
                            <div>
                                <div class="text-[0.65rem] font-medium text-slate-500 mb-0.5 uppercase tracking-wider">Temperature</div>
                                <div class="text-xl font-semibold tracking-tight text-slate-900">23.4 <span class="text-sm font-normal text-slate-500">°C</span></div>
                            </div>
                        </div>

                        <!-- Sensor 2: Humidity -->
                        <div class="rounded-xl border border-amber-200 bg-amber-50/30 p-4 flex flex-col justify-between hover:border-amber-300 transition-colors shadow-[0_0_15px_rgba(245,158,11,0.05)]">
                            <div class="flex justify-between items-start mb-3">
                                <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center">
                                    <iconify-icon icon="solar:drop-linear" class="text-lg"></iconify-icon>
                                </div>
                                <span class="inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-[0.65rem] font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">Warning</span>
                            </div>
                            <div>
                                <div class="text-[0.65rem] font-medium text-slate-500 mb-0.5 uppercase tracking-wider">Humidity</div>
                                <div class="text-xl font-semibold tracking-tight text-slate-900">72 <span class="text-sm font-normal text-slate-500">%</span></div>
                            </div>
                        </div>

                        <!-- Sensor 3: Ammonia -->
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 flex flex-col justify-between hover:border-slate-300 transition-colors">
                            <div class="flex justify-between items-start mb-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                    <iconify-icon icon="solar:wind-linear" class="text-lg"></iconify-icon>
                                </div>
                                <span class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[0.65rem] font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Optimal</span>
                            </div>
                            <div>
                                <div class="text-[0.65rem] font-medium text-slate-500 mb-0.5 uppercase tracking-wider">Ammonia (NH3)</div>
                                <div class="text-xl font-semibold tracking-tight text-slate-900">12 <span class="text-sm font-normal text-slate-500">ppm</span></div>
                            </div>
                        </div>

                        <!-- Sensor 4: Light -->
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 flex flex-col justify-between hover:border-slate-300 transition-colors">
                            <div class="flex justify-between items-start mb-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                    <iconify-icon icon="solar:sun-2-linear" class="text-lg"></iconify-icon>
                                </div>
                                <span class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[0.65rem] font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">On Schedule</span>
                            </div>
                            <div>
                                <div class="text-[0.65rem] font-medium text-slate-500 mb-0.5 uppercase tracking-wider">Light Duration</div>
                                <div class="text-xl font-semibold tracking-tight text-slate-900">16h <span class="text-sm font-normal text-slate-500">/ 8h</span></div>
                            </div>
                        </div>

                        <!-- Sensor 5: Feed/Bird -->
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 flex flex-col justify-between hover:border-slate-300 transition-colors">
                            <div class="flex justify-between items-start mb-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center">
                                    <iconify-icon icon="solar:box-linear" class="text-lg"></iconify-icon>
                                </div>
                                <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[0.65rem] font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">Normal</span>
                            </div>
                            <div>
                                <div class="text-[0.65rem] font-medium text-slate-500 mb-0.5 uppercase tracking-wider">Feed / Bird</div>
                                <div class="text-xl font-semibold tracking-tight text-slate-900">114 <span class="text-sm font-normal text-slate-500">g</span></div>
                            </div>
                        </div>

                        <!-- Sensor 6: Water/Bird -->
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 flex flex-col justify-between hover:border-slate-300 transition-colors">
                            <div class="flex justify-between items-start mb-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center">
                                    <iconify-icon icon="solar:waterdrop-linear" class="text-lg"></iconify-icon>
                                </div>
                                <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[0.65rem] font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">Normal</span>
                            </div>
                            <div>
                                <div class="text-[0.65rem] font-medium text-slate-500 mb-0.5 uppercase tracking-wider">Water / Bird</div>
                                <div class="text-xl font-semibold tracking-tight text-slate-900">210 <span class="text-sm font-normal text-slate-500">ml</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lower Section: Egg Details & SPK -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- Egg Production Details -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 mb-6">Egg Production Details (Today)</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Size Distribution -->
                    <div class="md:col-span-1">
                        <p class="text-xs font-medium text-slate-500 mb-4 uppercase tracking-wider">Size Distribution</p>
                        
                        <!-- Stacked Bar Horizontal -->
                        <div class="h-4 w-full bg-slate-100 rounded-full overflow-hidden flex mb-3">
                            <div class="bg-slate-300 h-full" style="width: 5%" title="Small"></div>
                            <div class="bg-emerald-400 h-full" style="width: 25%" title="Medium"></div>
                            <div class="bg-emerald-600 h-full" style="width: 55%" title="Large"></div>
                            <div class="bg-emerald-800 h-full" style="width: 15%" title="Extra Large"></div>
                        </div>
                        
                        <div class="space-y-2 mt-4">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-slate-300"></div><span class="text-slate-600">Small (&lt;53g)</span></div>
                                <span class="font-medium text-slate-900">5%</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-emerald-400"></div><span class="text-slate-600">Medium (53-63g)</span></div>
                                <span class="font-medium text-slate-900">25%</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-emerald-600"></div><span class="text-slate-600">Large (63-73g)</span></div>
                                <span class="font-medium text-slate-900">55%</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-emerald-800"></div><span class="text-slate-600">XL (&gt;73g)</span></div>
                                <span class="font-medium text-slate-900">15%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Defect Rates -->
                    <div class="md:col-span-2 grid grid-cols-2 gap-6 border-t md:border-t-0 md:border-l border-slate-100 pt-6 md:pt-0 md:pl-8">
                        <div>
                            <p class="text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">Broken Egg Rate</p>
                            <div class="flex items-end gap-2 mb-3">
                                <p class="text-3xl font-semibold tracking-tight text-slate-900">1.2<span class="text-lg font-normal text-slate-500">%</span></p>
                                <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded mb-1">Optimal</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-1.5">
                                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: 15%"></div>
                            </div>
                            <p class="text-[0.65rem] text-slate-400 mt-2">Target: &lt; 2.0%</p>
                        </div>
                        
                        <div>
                            <p class="text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">Dirty Egg Rate</p>
                            <div class="flex items-end gap-2 mb-3">
                                <p class="text-3xl font-semibold tracking-tight text-slate-900">3.5<span class="text-lg font-normal text-slate-500">%</span></p>
                                <span class="text-xs font-medium text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded mb-1">Warning</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-1.5">
                                <div class="bg-amber-500 h-1.5 rounded-full" style="width: 45%"></div>
                            </div>
                            <p class="text-[0.65rem] text-slate-400 mt-2">Target: &lt; 3.0%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Automated SPK Analysis (AI) -->
            <div class="lg:col-span-1 rounded-2xl border border-emerald-100 bg-gradient-to-b from-emerald-50/80 to-white p-6 relative overflow-hidden flex flex-col h-full shadow-sm">
                <!-- Decorative background elements -->
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-emerald-100 rounded-full blur-2xl opacity-50 pointer-events-none"></div>
                
                <div class="flex items-center gap-2 text-emerald-700 mb-4 relative z-10">
                    <iconify-icon icon="solar:magic-stick-3-linear" class="text-xl"></iconify-icon>
                    <h3 class="font-medium tracking-tight">Automated SPK Analysis</h3>
                </div>
                
                <p class="text-sm text-slate-600 leading-relaxed mb-6 relative z-10 flex-1">
                    <span class="font-medium text-slate-800">Insight:</span> Ammonia (NH3) levels in the south-east corner are approaching upper limits, correlating with a slight uptick in dirty egg rates on tier 1. 
                    <br><br>
                    <span class="font-medium text-slate-800">Recommendation:</span> Increase ventilation fan speed Group B by 15% and schedule a manure belt run within the next 2 hours.
                </p>
                
                <div class="flex flex-col sm:flex-row gap-3 relative z-10">
                    <button class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-colors shadow-sm focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1">
                        Analyze Protocol
                    </button>
                    <button class="flex-1 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-medium py-2 px-4 rounded-lg transition-colors shadow-sm">
                        Create Ticket
                    </button>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold tracking-tight text-slate-900 mb-6">Recent Activity Log</h2>
            
            <div class="relative pl-4 border-l border-slate-200 space-y-8 pb-2">
                
                <!-- Log Item 1 -->
                <div class="relative">
                    <div class="absolute -left-[21px] top-1 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"></div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-1">
                        <h4 class="text-sm font-medium text-slate-900">Afternoon Egg Collection Completed</h4>
                        <span class="text-xs text-slate-500">Today, 02:30 PM</span>
                    </div>
                    <p class="text-sm text-slate-600">Belt collection cycle finished. Total count: 11,402 eggs. Minor jam resolved at row 3.</p>
                </div>

                <!-- Log Item 2 -->
                <div class="relative">
                    <div class="absolute -left-[21px] top-1 h-3 w-3 rounded-full border-2 border-white bg-slate-400"></div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-1">
                        <h4 class="text-sm font-medium text-slate-900">Midday Feeding Sequence</h4>
                        <span class="text-xs text-slate-500">Today, 11:00 AM</span>
                    </div>
                    <p class="text-sm text-slate-600">Silo A dispensed 1.2 tons of Layer Phase 2 feed. Chain feeders ran for 45 minutes.</p>
                </div>

                <!-- Log Item 3 -->
                <div class="relative">
                    <div class="absolute -left-[21px] top-1 h-3 w-3 rounded-full border-2 border-white bg-amber-500"></div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-1">
                        <h4 class="text-sm font-medium text-slate-900">Ventilation Adjustment</h4>
                        <span class="text-xs text-slate-500">Today, 09:15 AM</span>
                    </div>
                    <p class="text-sm text-slate-600">System automatically increased tunnel ventilation by 10% due to rising outside temperature.</p>
                </div>
                
                 <!-- Log Item 4 -->
                 <div class="relative">
                    <div class="absolute -left-[21px] top-1 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"></div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-1">
                        <h4 class="text-sm font-medium text-slate-900">Morning Egg Collection Completed</h4>
                        <span class="text-xs text-slate-500">Today, 08:00 AM</span>
                    </div>
                    <p class="text-sm text-slate-600">Belt collection cycle finished. Total count: 12,010 eggs.</p>
                </div>

            </div>
            
            <button class="mt-4 text-sm font-medium text-emerald-600 hover:text-emerald-700 transition-colors w-full text-center py-2 border border-dashed border-slate-200 rounded-lg hover:bg-slate-50">
                View All Activity
            </button>
        </div>

    </main>


</body></html>