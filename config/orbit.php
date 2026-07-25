<?php

return [
    /*
     * The ORBIT shell — command ribbon, KPI ribbon, orbit navigation, dock.
     * Off by default: the current header keeps working until this is switched
     * on, so the sidebar retirement is reversible in one env var.
     */
    'nav' => env('ORBIT_NAV', false),
];
