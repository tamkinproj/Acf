<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * Cloudflare's published address ranges (https://www.cloudflare.com/ips/).
     * Behind Cloudflare every request arrives from one of these, so without this the server would see all visitors as a handful
     * of Cloudflare addresses and the per-address limits (sign-in, the public pages) would be shared by everyone and run out.
     */
    public const CLOUDFLARE = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20',
        '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    public function handle(Request $request, Closure $next)
    {
        // The visitor's own address, as Cloudflare reports it. Only believed when the request really comes from Cloudflare:
        // anyone else sending this header directly to the server is ignored, so it cannot be used to dodge the limits.
        $remote = (string) $request->server->get('REMOTE_ADDR', '');
        $visitor = trim((string) $request->headers->get('CF-Connecting-IP', ''));
        if ($remote !== '' && $visitor !== '' && filter_var($visitor, FILTER_VALIDATE_IP) && IpUtils::checkIp($remote, self::CLOUDFLARE)) {
            $request->server->set('REMOTE_ADDR', $visitor);
        }

        return parent::handle($request, $next);
    }
}
