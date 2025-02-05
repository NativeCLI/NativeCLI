<?php

namespace NativeCLI\Exception;

use NativeCLI\Exception;

class RateLimitedException extends Exception
{
    public static function for(string $url): RateLimitedException
    {
        $url = parse_url($url, PHP_URL_HOST);

        return new self("Rate limited by $url. Wait a while before trying again.");
    }
}