<?php

return [
    // Absolute paths to the Node / npm binaries Browsershot should spawn.
    // Herd's node lives under a path with spaces, so it must be set explicitly.
    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
];
