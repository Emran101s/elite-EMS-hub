<?php

/*
 * Platform module registry — the built modules, in blueprint order
 * (docs/command-center/blueprint.md). App\Support\NavPanel draws the chrome;
 * this list is what the reachability test measures it against, so a module
 * only belongs here once it has a page of its own.
 */
return [

    'nav' => [
        'command-center' => [
            'primary' => true,
            'label' => 'Command Center',
            'path' => '/',
            'route' => 'home',
            'icon' => 'home',
            'phase' => 'Phase 2',
            'blurb' => 'Real-time overview of your events ecosystem — KPIs, the Operations Hub map, live alerts, resource utilization and budget.',
        ],
        'events' => [
            'primary' => true,
            'label' => 'Events',
            'path' => '/events',
            'route' => 'events.index',
            'icon' => 'calendar',
            'phase' => 'Phase 1',
            'blurb' => 'Conferences, galas, workshops, expos and private dinners — across Jordan, Bahrain, UAE, Qatar and KSA.',
        ],
        'projects' => [
            'primary' => false,
            'label' => 'Projects',
            'path' => '/projects',
            'route' => 'projects.index',
            'icon' => 'folder',
            'phase' => 'Phase 1',
            'blurb' => 'Project workspaces that tie events, tasks and budgets together.',
        ],
        'tasks' => [
            'primary' => true,
            'label' => 'Tasks',
            'path' => '/tasks',
            'route' => 'tasks.index',
            'icon' => 'clipboard',
            'phase' => 'Phase 1',
            'blurb' => 'Everything on the to-do list — completed, in progress and pending.',
        ],
        'crm' => [
            'primary' => true,
            'label' => 'CRM',
            'path' => '/crm',
            'route' => 'crm.index',
            'icon' => 'identification',
            'phase' => 'Phase 3',
            'blurb' => 'Clients, contacts and relationships across all your events.',
        ],
        'finance' => [
            'primary' => true,
            'label' => 'Finance',
            'path' => '/finance',
            'route' => 'finance.index',
            'icon' => 'currency',
            'phase' => 'Phase 3',
            'blurb' => 'Budgets, spend, commitments and revenue — the money view.',
        ],
        'suppliers' => [
            'primary' => false,
            'label' => 'Suppliers',
            'path' => '/suppliers',
            'route' => 'suppliers.index',
            'icon' => 'truck',
            'phase' => 'Phase 1',
            'blurb' => 'Your supplier network — catering, AV & lighting, production, support — with ratings.',
        ],
        'venues' => [
            'primary' => false,
            'label' => 'Venues',
            'path' => '/venues',
            'route' => 'venues.index',
            'icon' => 'building',
            'phase' => 'Phase 1',
            'blurb' => 'Venues and spaces, availability and utilization.',
        ],
        'team' => [
            'primary' => false,
            'label' => 'Team',
            'path' => '/team',
            'route' => 'team.index',
            'icon' => 'users',
            'phase' => 'Phase 1',
            'blurb' => 'Team members, roles and workload.',
        ],
        'reports' => [
            'primary' => false,
            'label' => 'Reports',
            'path' => '/reports',
            'route' => 'reports.index',
            'icon' => 'chart',
            'phase' => 'Phase 4',
            'blurb' => 'Exports and analytics across events, finance and operations.',
        ],
        'ai-assistant' => [
            'primary' => false,
            'label' => 'Command Briefing',
            'path' => '/ai-assistant',
            'route' => 'ai.index',
            'icon' => 'sparkles',
            'phase' => 'Phase 4',
            'blurb' => 'Ask anything about your operations.',
        ],
        'settings' => [
            'primary' => false,
            'label' => 'Settings',
            'path' => '/settings',
            'route' => 'settings.index',
            'icon' => 'cog',
            'phase' => 'Phase 4',
            'blurb' => 'Workspace, profile and platform configuration.',
        ],
    ],

];
