<?php
return [
    'account_suffix' => "@domain.local",
    'domain_controllers' => [
        "dc1.domain.local",
        "dc2.domain.local",
    ], // An array of domains may be provided for load balancing.
    'base_dn' => 'DC=domain,DC=local',

    'admin_username' => 'user',
    'admin_password' => 'password',

    'use_ssl' => true, // If TLS is true this MUST be false.
    'use_tls' => false, // If SSL is true this MUST be false.

    'real_primary_group' => false, // For AD ONLY! - Returns the primary group (an educated guess).
    'recursive_groups' => true,
];