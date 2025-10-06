<?php
// config/azure.php

return [
    'appId'             => env('OAUTH_APP_ID'),
    'appSecret'         => env('OAUTH_APP_SECRET'),
    'redirectUri'       => env('OAUTH_REDIRECT_URI'),
    'scopes'            => env('OAUTH_SCOPES'),
    'authority'         => env('OAUTH_AUTHORITY'),
    'authorizeEndpoint' => env('OAUTH_AUTHORIZE_ENDPOINT'),
    'tokenEndpoint'     => env('OAUTH_TOKEN_ENDPOINT'),
    'tenantId'          => env('OAUTH_TENANT_ID'),
];