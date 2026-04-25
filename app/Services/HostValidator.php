<?php

namespace App\Services;

class HostValidator
{
    public function isPublic(string $host): bool
    {
        $host = trim($host, '[]');

        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        $ips = $this->resolve($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
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
