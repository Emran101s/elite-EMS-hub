<?php

/*
 * Platform module registry — drives the sidebar, route stubs, and module pages.
 * Order matches the Command Center blueprint (docs/command-center/blueprint.md).
 */
return [

    'nav' => [
        'command-center' => [
            'label' => 'Command Canvas',
            'path' => '/',
            'route' => 'home',
            'icon' => 'home',
            'phase' => 'Phase 2',
            'blurb' => 'Real-time overview of your events ecosystem — KPIs, the Operations Hub map, live alerts, resource utilization and budget.',
        ],
        'events' => [
            'label' => 'Events',
            'path' => '/events',
            'route' => 'events.index',
            'icon' => 'calendar',
            'phase' => 'Phase 1',
            'blurb' => 'Conferences, galas, workshops, expos and private dinners — across Jordan, Bahrain, UAE, Qatar and KSA.',
        ],
        'projects' => [
            'label' => 'Projects',
            'path' => '/projects',
            'route' => 'projects.index',
            'icon' => 'folder',
            'phase' => 'Phase 1',
            'blurb' => 'Project workspaces that tie events, tasks and budgets together.',
        ],
        'tasks' => [
            'label' => 'Tasks',
            'path' => '/tasks',
            'route' => 'tasks.index',
            'icon' => 'clipboard',
            'phase' => 'Phase 1',
            'blurb' => 'Everything on the to-do list — completed, in progress and pending.',
        ],
        'crm' => [
            'label' => 'CRM',
            'path' => '/crm',
            'route' => 'crm.index',
            'icon' => 'identification',
            'phase' => 'Phase 3',
            'blurb' => 'Clients, contacts and relationships across all your events.',
        ],
        'finance' => [
            'label' => 'Finance',
            'path' => '/finance',
            'route' => 'finance.index',
            'icon' => 'currency',
            'phase' => 'Phase 3',
            'blurb' => 'Budgets, spend, commitments and revenue — the money view.',
        ],
        'suppliers' => [
            'label' => 'Suppliers',
            'path' => '/suppliers',
            'route' => 'suppliers.index',
            'icon' => 'truck',
            'phase' => 'Phase 1',
            'blurb' => 'Your supplier network — catering, AV & lighting, production, support — with ratings.',
        ],
        'venues' => [
            'label' => 'Venues',
            'path' => '/venues',
            'route' => 'venues.index',
            'icon' => 'building',
            'phase' => 'Phase 1',
            'blurb' => 'Venues and spaces, availability and utilization.',
        ],
        'team' => [
            'label' => 'Team',
            'path' => '/team',
            'route' => 'team.index',
            'icon' => 'users',
            'phase' => 'Phase 1',
            'blurb' => 'Team members, roles and workload.',
        ],
        'assets' => [
            'label' => 'Assets',
            'path' => '/assets',
            'route' => 'assets.index',
            'icon' => 'archive',
            'phase' => 'Phase 4',
            'blurb' => 'Equipment and asset inventory, allocation and condition.',
        ],
        'reports' => [
            'label' => 'Reports',
            'path' => '/reports',
            'route' => 'reports.index',
            'icon' => 'chart',
            'phase' => 'Phase 4',
            'blurb' => 'Exports and analytics across events, finance and operations.',
        ],
        'ai-assistant' => [
            'label' => 'AI Assistant',
            'path' => '/ai-assistant',
            'route' => 'ai.index',
            'icon' => 'sparkles',
            'phase' => 'Phase 4',
            'blurb' => 'Ask anything about your operations.',
        ],
        'settings' => [
            'label' => 'Settings',
            'path' => '/settings',
            'route' => 'settings.index',
            'icon' => 'cog',
            'phase' => 'Phase 4',
            'blurb' => 'Workspace, profile and platform configuration.',
        ],
    ],

];
