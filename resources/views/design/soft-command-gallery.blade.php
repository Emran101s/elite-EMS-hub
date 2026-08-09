<x-layouts.eo-gallery title="Component Gallery">

    {{-- ═══════════════════════════════════════════════════════════
         Visual review board — one isolated specimen per component
         for Phase 1 screenshot validation. Not a product page.
         ═══════════════════════════════════════════════════════════ --}}
    <section id="event-dna" class="space-y-6">
        <x-eo.page-header
            eyebrow="Design language refinements"
            title="Event DNA · Mission Radar · Domain cards"
            subtitle="Identity improvements on top of the approved Phase 1 foundation — event operations, not generic SaaS."
        >
            <x-slot:actions>
                <a href="{{ route('design.soft-command-shell') }}" class="eo-btn-primary eo-btn-sm">Open Phase 2 shell</a>
            </x-slot:actions>
        </x-eo.page-header>

        <div class="grid gap-4 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-eo.soft-card class="flex flex-col items-center p-5">
                    <p class="eo-label mb-3 self-start">Mission Radar</p>
                    <x-eo.mission-radar />
                </x-eo.soft-card>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:col-span-8">
                <x-eo.mission-card title="Arab Public Summit" type="Forum" stage="Production" venue="Amman" dates="8–10 Nov" :readiness="55" />
                <x-eo.operations-card title="Arrivals desk" subtitle="Check-in · transport · badges" :open="9" :due="2" :blocked="0" />
                <x-eo.commercial-card title="Sponsorship package" subtitle="Platinum · 3 open" value="JD 92K" meta="Exhibition + VIP dinner" />
                <x-eo.event-health-card title="Tech Expo 2026" :score="48" status="risk" hint="Booth production behind" />
            </div>
        </div>
    </section>

    <section id="review-board" class="mt-12 space-y-8">
        <x-eo.page-header
            eyebrow="Phase 1 · Visual review board"
            title="Component specimens"
            subtitle="Approved Soft Command foundation. Domain cards and Mission Radar extend the language above."
        />

        {{-- 01 Typography --}}
        <div id="specimen-01-typography" class="space-y-2">
            <p class="eo-label">01 · Typography system</p>
            <x-eo.soft-card>
                <p class="eo-label">Eyebrow / label · 11px · tracking 0.14em</p>
                <p class="eo-display mt-3">Display 32 — Mission command</p>
                <p class="eo-title mt-2">Title 22 — Event detail heading</p>
                <p class="eo-body mt-2 text-eo-muted">Body 14 — Soft Command copy stays calm, short, and operational. Plus Jakarta Sans throughout.</p>
                <p class="eo-metric mt-5">128</p>
                <p class="eo-label mt-1">Metric · tabular nums</p>
            </x-eo.soft-card>
        </div>

        {{-- 02 Color --}}
        <div id="specimen-02-color" class="space-y-2">
            <p class="eo-label">02 · Color system</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Deep Navy', '#0B1322', 'bg-eo-navy-deep text-white'],
                    ['Orbit Navy', '#101A29', 'bg-eo-navy text-white'],
                    ['Teal Action', '#1EACAC', 'bg-eo-teal text-white'],
                    ['Soft Gray', '#E6E9EE', 'bg-eo-bg text-eo-text'],
                    ['Soft White', '#F8F9FA', 'bg-eo-workspace text-eo-text ring-1 ring-eo-line'],
                    ['Card', '#FFFFFF', 'bg-eo-card text-eo-text ring-1 ring-eo-line'],
                    ['Gold Brand', '#D6AE34', 'bg-eo-gold text-eo-navy-deep'],
                    ['Gold Soft', '#F4D76B', 'bg-eo-gold-soft text-eo-navy-deep'],
                ] as [$name, $hex, $class])
                    <div class="eo-soft-card overflow-hidden">
                        <div class="h-20 {{ $class }}"></div>
                        <div class="px-4 py-3">
                            <p class="text-[14px] font-semibold">{{ $name }}</p>
                            <p class="eo-label mt-1">{{ $hex }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 03 Buttons --}}
        <div id="specimen-03-buttons" class="space-y-2">
            <p class="eo-label">03 · Button family</p>
            <x-eo.soft-card class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <x-eo.button>Primary teal</x-eo.button>
                    <x-eo.button variant="secondary">Secondary</x-eo.button>
                    <x-eo.button variant="ghost">Ghost</x-eo.button>
                    <x-eo.button variant="navy">Navy</x-eo.button>
                    <x-eo.button variant="danger">Danger</x-eo.button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-eo.button size="sm" pill>Pill sm</x-eo.button>
                    <x-eo.button variant="secondary" size="sm" pill>Filter</x-eo.button>
                    <x-eo.button size="sm">Primary sm</x-eo.button>
                </div>
                <p class="text-[12px] text-eo-muted">Teal = operational action. Gold is brand-only — never a primary button.</p>
            </x-eo.soft-card>
        </div>

        {{-- 04 Status pills --}}
        <div id="specimen-04-status-pills" class="space-y-2">
            <p class="eo-label">04 · Status pills</p>
            <x-eo.soft-card>
                <div class="flex flex-wrap gap-2">
                    <x-eo.status-pill tone="ok">On track</x-eo.status-pill>
                    <x-eo.status-pill tone="warn">Needs attention</x-eo.status-pill>
                    <x-eo.status-pill tone="risk">At risk</x-eo.status-pill>
                    <x-eo.status-pill tone="live">Live</x-eo.status-pill>
                    <x-eo.status-pill tone="pending">Pending</x-eo.status-pill>
                    <x-eo.status-pill tone="premium">Premium</x-eo.status-pill>
                </div>
            </x-eo.soft-card>
        </div>

        {{-- 05 Metric pills --}}
        <div id="specimen-05-metric-pills" class="space-y-2">
            <p class="eo-label">05 · Metric pills</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-eo.metric-pill label="Readiness" value="72%" tone="warn" hint="3 blockers" />
                <x-eo.metric-pill label="Open tasks" value="14" tone="live" />
                <x-eo.metric-pill label="Confirmed" value="88%" tone="ok" hint="Speakers locked" />
                <x-eo.metric-pill label="Risk score" value="16" tone="risk" hint="Needs owner" />
            </div>
        </div>

        {{-- 06 Soft cards --}}
        <div id="specimen-06-soft-cards" class="space-y-2">
            <p class="eo-label">06 · Soft cards</p>
            <div class="grid gap-4 md:grid-cols-2">
                <x-eo.soft-card>
                    <p class="eo-label">Soft card</p>
                    <p class="eo-title mt-2">Large radius · soft shadow</p>
                    <p class="eo-body mt-2 text-eo-muted">White card on soft gray workspace. No hard border. 24px radius.</p>
                </x-eo.soft-card>
                <x-eo.soft-card>
                    <p class="eo-label">Soft card</p>
                    <p class="eo-title mt-2">Breathing room</p>
                    <p class="eo-body mt-2 text-eo-muted">Padding and spacing stay generous so the OS feels calm, not dense.</p>
                </x-eo.soft-card>
            </div>
        </div>

        {{-- 07 Selected dark card --}}
        <div id="specimen-07-selected-dark" class="space-y-2">
            <p class="eo-label">07 · SelectedDarkCard</p>
            <div class="max-w-md">
                <x-eo.selected-dark-card>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-eo-gold-soft">Selected</p>
                            <p class="mt-1 text-[15px] font-semibold">Gulf Leadership Summit</p>
                            <p class="mt-1 text-[12px] text-white/65">Riyadh · 18–20 Sep</p>
                        </div>
                        <x-eo.status-pill tone="warn" class="bg-eo-warn/20 text-eo-gold-soft">Watch</x-eo.status-pill>
                    </div>
                </x-eo.selected-dark-card>
            </div>
        </div>

        {{-- 08 Queue list --}}
        <div id="specimen-08-queue-list" class="space-y-2">
            <p class="eo-label">08 · Queue list</p>
            <div class="max-w-md">
                <x-eo.queue-list title="Mission queue">
                    <x-eo.selected-dark-card>
                        <p class="text-[15px] font-semibold">Gulf Leadership Summit</p>
                        <p class="mt-1 text-[12px] text-white/65">Riyadh · Selected</p>
                    </x-eo.selected-dark-card>
                    <x-eo.soft-card class="p-4" :padding="false">
                        <p class="text-[14px] font-semibold text-eo-text">Medical Congress</p>
                        <p class="mt-1 text-[12px] text-eo-muted">Dubai · Planning</p>
                    </x-eo.soft-card>
                    <x-eo.soft-card class="p-4" :padding="false">
                        <p class="text-[14px] font-semibold text-eo-text">Expo Partner Day</p>
                        <p class="mt-1 text-[12px] text-eo-muted">Abu Dhabi · Draft</p>
                    </x-eo.soft-card>
                </x-eo.queue-list>
            </div>
        </div>

        {{-- 09 Detail panel --}}
        <div id="specimen-09-detail-panel" class="space-y-2">
            <p class="eo-label">09 · Detail panel</p>
            <x-eo.detail-panel title="Gulf Leadership Summit" subtitle="Commercial mission · Client: Apex Group">
                <x-slot:header>
                    <x-eo.status-pill tone="live">Planning</x-eo.status-pill>
                </x-slot:header>
                <div class="grid grid-cols-2 gap-3">
                    <x-eo.metric-pill label="Readiness" value="72%" tone="warn" hint="3 blockers" />
                    <x-eo.metric-pill label="Open tasks" value="14" tone="live" />
                </div>
                <p class="eo-body mt-4 text-eo-muted">Center column for the selected entity — status, metrics, and context.</p>
            </x-eo.detail-panel>
        </div>

        {{-- 10 Action panel --}}
        <div id="specimen-10-action-panel" class="space-y-2">
            <p class="eo-label">10 · Action panel</p>
            <div class="max-w-xs">
                <x-eo.action-panel title="Next actions">
                    <x-eo.button class="w-full justify-start">Open Event Hub</x-eo.button>
                    <x-eo.button variant="secondary" class="w-full justify-start">Review budget</x-eo.button>
                    <x-eo.button variant="ghost" class="w-full justify-start">Assign owner</x-eo.button>
                    <x-eo.button variant="danger" class="w-full justify-start">Flag risk</x-eo.button>
                </x-eo.action-panel>
            </div>
        </div>

        {{-- 11 Smart table --}}
        <div id="specimen-11-smart-table" class="space-y-2">
            <p class="eo-label">11 · Smart table</p>
            <x-eo.smart-table :headers="['Mission', 'Stage', 'Owner', 'Readiness', 'Status']">
                <tr>
                    <td class="font-semibold">Gulf Leadership Summit</td>
                    <td>Planning</td>
                    <td>Sara</td>
                    <td>72%</td>
                    <td><x-eo.status-pill tone="warn">Watch</x-eo.status-pill></td>
                </tr>
                <tr>
                    <td class="font-semibold">Medical Congress</td>
                    <td>Planning</td>
                    <td>Omar</td>
                    <td>88%</td>
                    <td><x-eo.status-pill tone="ok">Ready</x-eo.status-pill></td>
                </tr>
                <tr>
                    <td class="font-semibold">Expo Partner Day</td>
                    <td>Draft</td>
                    <td>Lina</td>
                    <td>41%</td>
                    <td><x-eo.status-pill tone="risk">Blocked</x-eo.status-pill></td>
                </tr>
            </x-eo.smart-table>
        </div>

        {{-- 12 Filter bar --}}
        <div id="specimen-12-filter-bar" class="space-y-2">
            <p class="eo-label">12 · Filter bar</p>
            <x-eo.filter-bar search-placeholder="Search missions…">
                <x-slot:filters>
                    <select class="eo-select w-auto min-w-[140px] py-2.5">
                        <option>All stages</option>
                        <option>Planning</option>
                        <option>Live</option>
                    </select>
                    <select class="eo-select w-auto min-w-[140px] py-2.5">
                        <option>All owners</option>
                    </select>
                </x-slot:filters>
                <x-slot:actions>
                    <x-eo.button size="sm">New mission</x-eo.button>
                </x-slot:actions>
            </x-eo.filter-bar>
        </div>

        {{-- 13 Form fields --}}
        <div id="specimen-13-form-fields" class="space-y-2">
            <p class="eo-label">13 · Form fields</p>
            <x-eo.soft-card class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="eo-field-label">Event name</label>
                    <input class="eo-input" value="Gulf Leadership Summit">
                </div>
                <div>
                    <label class="eo-field-label">Stage</label>
                    <select class="eo-select">
                        <option>Planning</option>
                        <option>Live</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="eo-field-label">Brief notes</label>
                    <textarea class="eo-textarea">Soft filled inputs — gray fill, teal focus ring. No gold on form chrome.</textarea>
                </div>
            </x-eo.soft-card>
        </div>

        {{-- 14 Readiness --}}
        <div id="specimen-14-readiness" class="space-y-2">
            <p class="eo-label">14 · Readiness cards</p>
            <div class="grid gap-4 md:grid-cols-2">
                <x-eo.readiness-card
                    title="Operations readiness"
                    :percent="72"
                    status="warn"
                    hint="Transport + catering still open"
                >
                    <x-slot:meta>Updated 12 minutes ago</x-slot:meta>
                </x-eo.readiness-card>
                <x-eo.readiness-card
                    title="Speaker lock"
                    :percent="88"
                    status="ok"
                    hint="Confirmations above threshold"
                >
                    <x-slot:meta>Updated 1 hour ago</x-slot:meta>
                </x-eo.readiness-card>
            </div>
        </div>

        {{-- 15 Module cards --}}
        <div id="specimen-15-module-cards" class="space-y-2">
            <p class="eo-label">15 · Module cards</p>
            <div class="grid gap-4 md:grid-cols-2">
                <x-eo.module-card
                    title="Event Hub"
                    description="Mission control for a single event — journey, tasks, builders."
                    icon="calendar"
                    meta="Core module"
                    href="#specimen-09-detail-panel"
                >
                    <x-slot:badge>
                        <x-eo.status-pill tone="live">Core</x-eo.status-pill>
                    </x-slot:badge>
                </x-eo.module-card>
                <x-eo.module-card
                    title="Finance"
                    description="Budgets, invoices, and payments in one commercial lane."
                    icon="currency"
                    meta="Commercial"
                >
                    <x-slot:badge>
                        <x-eo.status-pill tone="premium">Brand</x-eo.status-pill>
                    </x-slot:badge>
                </x-eo.module-card>
            </div>
        </div>

        {{-- 16 Empty state --}}
        <div id="specimen-16-empty-state" class="space-y-2">
            <p class="eo-label">16 · Empty state</p>
            <x-eo.empty-state
                title="No missions in this filter"
                hint="Adjust filters or create a new mission from Event Studio."
                icon="sparkles"
            >
                <x-slot:actions>
                    <x-eo.button>Create mission</x-eo.button>
                    <x-eo.button variant="ghost">Clear filters</x-eo.button>
                </x-slot:actions>
            </x-eo.empty-state>
        </div>

        {{-- 17 Alert card --}}
        <div id="specimen-17-alert-card" class="space-y-2">
            <p class="eo-label">17 · Alert card</p>
            <div class="grid gap-3 md:grid-cols-2">
                <x-eo.alert-card tone="risk" title="Critical path">
                    Speaker confirmations below threshold for day one.
                </x-eo.alert-card>
                <x-eo.alert-card tone="warn" title="Transport lag">
                    Manifest draft still missing for day-two airport runs.
                </x-eo.alert-card>
                <x-eo.alert-card tone="info" title="Next checkpoint">
                    Agenda lock scheduled for Thursday 11:00.
                </x-eo.alert-card>
                <x-eo.alert-card tone="ok" title="Budget synced">
                    Finance lines match the approved proposal total.
                </x-eo.alert-card>
            </div>
        </div>
    </section>

    {{-- Composition proof (MDA together) --}}
    <section id="mda" class="mt-12">
        <x-eo.page-header
            eyebrow="Composition proof"
            title="Master → Detail → Action"
            subtitle="How the specimens compose into the Soft Command operational pattern."
        />

        <div class="grid gap-4 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-eo.queue-list title="Mission queue">
                    <x-eo.selected-dark-card>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-eo-gold-soft">Selected</p>
                                <p class="mt-1 text-[15px] font-semibold">Gulf Leadership Summit</p>
                                <p class="mt-1 text-[12px] text-white/65">Riyadh · 18–20 Sep</p>
                            </div>
                            <x-eo.status-pill tone="warn" class="bg-eo-warn/20 text-eo-gold-soft">Watch</x-eo.status-pill>
                        </div>
                    </x-eo.selected-dark-card>
                    <x-eo.soft-card class="p-4" :padding="false">
                        <p class="text-[14px] font-semibold text-eo-text">Medical Congress</p>
                        <p class="mt-1 text-[12px] text-eo-muted">Dubai · Planning</p>
                    </x-eo.soft-card>
                    <x-eo.soft-card class="p-4" :padding="false">
                        <p class="text-[14px] font-semibold text-eo-text">Expo Partner Day</p>
                        <p class="mt-1 text-[12px] text-eo-muted">Abu Dhabi · Draft</p>
                    </x-eo.soft-card>
                </x-eo.queue-list>
            </div>

            <div class="lg:col-span-5">
                <x-eo.detail-panel title="Gulf Leadership Summit" subtitle="Commercial mission · Client: Apex Group">
                    <x-slot:header>
                        <x-eo.status-pill tone="live">Planning</x-eo.status-pill>
                    </x-slot:header>
                    <div class="grid grid-cols-2 gap-3">
                        <x-eo.metric-pill label="Readiness" value="72%" tone="warn" hint="3 blockers" />
                        <x-eo.metric-pill label="Open tasks" value="14" tone="live" />
                    </div>
                    <div class="mt-5 space-y-2">
                        <x-eo.alert-card tone="warn" title="Transport lag">
                            Manifest draft still missing for day-two airport runs.
                        </x-eo.alert-card>
                        <x-eo.alert-card tone="info" title="Next checkpoint">
                            Agenda lock scheduled for Thursday 11:00.
                        </x-eo.alert-card>
                    </div>
                </x-eo.detail-panel>
            </div>

            <div class="lg:col-span-3">
                <x-eo.action-panel title="Next actions">
                    <x-eo.button class="w-full justify-start">Open Event Hub</x-eo.button>
                    <x-eo.button variant="secondary" class="w-full justify-start">Review budget</x-eo.button>
                    <x-eo.button variant="ghost" class="w-full justify-start">Assign owner</x-eo.button>
                    <x-eo.button variant="danger" class="w-full justify-start">Flag risk</x-eo.button>
                </x-eo.action-panel>
            </div>
        </div>
    </section>

    <section id="index" class="mt-10">
        <x-eo.soft-card>
            <p class="eo-label">Phase 1 component index</p>
            <ul class="mt-3 grid gap-2 text-[13px] text-eo-muted sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'x-eo.soft-card',
                    'x-eo.selected-dark-card',
                    'x-eo.page-header',
                    'x-eo.status-pill',
                    'x-eo.metric-pill',
                    'x-eo.queue-list',
                    'x-eo.detail-panel',
                    'x-eo.action-panel',
                    'x-eo.smart-table',
                    'x-eo.filter-bar',
                    'x-eo.readiness-card',
                    'x-eo.module-card',
                    'x-eo.empty-state',
                    'x-eo.alert-card',
                    'x-eo.button',
                ] as $name)
                    <li class="rounded-xl bg-eo-workspace px-3 py-2 font-medium text-eo-text">{{ $name }}</li>
                @endforeach
            </ul>
            <p class="mt-4 text-[12px] text-eo-muted">
                Awaiting visual approval. Phase 2 (App Shell) will not start until this board is signed off.
            </p>
        </x-eo.soft-card>
    </section>

</x-layouts.eo-gallery>
