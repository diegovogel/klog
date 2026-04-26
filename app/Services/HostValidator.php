<?php

namespace App\Services;

class HostValidator
{
    /**
     * Resolve the host and return its public IP(s), or null if any resolved IP is
     * private, loopback, link-local, or otherwise reserved. Callers should pin the
     * outbound connection to one of the returned IPs to avoid DNS rebinding.
     *
     * @return array<int, string>|null
     */
    public function resolvePublic(string $host): ?array
    {
        $host = trim($host, '[]');

        if ($host === '') {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host) ? [$host] : null;
        }

        $ips = $this->resolve($host);

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return null;
            }
        }

        return $ips;
    }

    private function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    /**
     * @return array<int, string>
     */
    private function resolve(string $host): array
    {
        $ipv4 = @gethostbynamel($host) ?: [];

        $ipv6Records = @dns_get_record($host, DNS_AAAA) ?: [];
        $ipv6 = array_values(array_filter(array_column($ipv6Records, 'ipv6')));

        return array_merge($ipv4, $ipv6);
    }
}
