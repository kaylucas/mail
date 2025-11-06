<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * When using ngrok or other reverse proxies, Laravel needs to trust
     * the proxy to properly handle forwarded headers (X-Forwarded-*).
     *
     * Setting this to '*' trusts all proxies, which is acceptable for
     * development with ngrok since the proxy IP can change.
     *
     * For production, you should specify exact proxy IP addresses.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * ngrok forwards these headers to indicate the original request details:
     * - X-Forwarded-For: Original client IP
     * - X-Forwarded-Host: Original hostname (aery.eu.ngrok.io)
     * - X-Forwarded-Proto: Original protocol (https)
     * - X-Forwarded-Port: Original port (443)
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
