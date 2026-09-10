<?php

declare(strict_types=1);

// The production release workflow replaces these empty values in the immutable
// release artefact. Only a nonce hash is packaged; the nonce remains in Actions.
return [
    'release' => '',
    'nonce_sha256' => '',
];
