<?php

declare(strict_types=1);

namespace Kuria\Url;

use Kuria\Url\Exception\IncompleteUrlException;
use Kuria\Url\Exception\InvalidUrlException;

class Url
{
    const RELATIVE = 0;
    const ABSOLUTE = 1;

    /** @var string|null */
    private $scheme;

    /** @var string|null */
    private $user;

    /** @var string|null */
    private $password;

    /** @var string|null */
    private $host;

    /** @var int|null */
    private $port;

    /** @var bool  */
    private $alwaysIncludeDefaultPort = true;

    /** @var string */
    private $path;

    /** @var array */
    private $query;

    /** @var string|null */
    private $fragment;

    /** @var int */
    private $preferredFormat;

    /** @var array Standard ports for schemes */
    public static $standardPorts = [
        'http' => 80,           // Hypertext Transfer Protocol
        'https' => 443,         // Hypertext Transfer Protocol Secure
        'ftp' => 21,            // File Transfer Protocol
        'sftp' => 22,           // Secure File Transfer Protocol
        'ftps' => 990,          // Secure File Transfer Protocol
        'smtp' => 25,           // Simple Mail Transfer Protocol
        'pop3' => 110,          // Post Office Protocol v3
        'imap' => 143,          // Internet Message Access Protocol
        'ssh' => 22,            // Secure Shell (same as SFTP)
        'telnet' => 23,         // Telnet
        'ldap' => 389,          // Lightweight Directory Access Protocol
        'mysql' => 3306,        // MySQL Database
        'postgres' => 5432,     // PostgreSQL Database
        'rdp' => 3389,          // Remote Desktop Protocol
    ];

    function __construct(
        ?string $scheme = null,
        ?string $user = null,
        ?string $password = null,
        ?string $host = null,
        ?int $port = null,
        string $path = '',
        array $query = [],
        ?string $fragment = null,
        int $preferredFormat = self::ABSOLUTE,
        bool $alwaysIncludeDefaultPort = true
    ) {
        $this->scheme = $scheme;
        $this->user = $user;
        $this->password = $password;
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
        $this->query = $query;
        $this->fragment = $fragment;
        $this->preferredFormat = $preferredFormat;
        $this->alwaysIncludeDefaultPort = $alwaysIncludeDefaultPort;
    }

    function __toString(): string
    {
        return $this->build();
    }

    /**
     * Parse an URL
     *
     * @return static
     * @throws InvalidUrlException if the URL is invalid
     */
    static function parse(string $url, ?int $preferredFormat = self::ABSOLUTE, bool $alwaysIncludeDefaultPort = true)
    {
        $components = parse_url($url);

        if ($components === false) {
            throw new InvalidUrlException(sprintf('The given URL "%s" is invalid', $url));
        }

        if (isset($components['host'])) {
            if (!self::isValidHost($components['host'])) {
                throw new InvalidUrlException(sprintf('Invalid host in URL "%s"', $url));
            }
        }

        $query = [];

        if (isset($components['query'])) {
            parse_str($components['query'], $query);
        }

        return new static(
            $components['scheme'] ?? null,
            $components['user'] ?? null,
            $components['pass'] ?? null,
            $components['host'] ?? null,
            $components['port'] ?? null,
            $components['path'] ?? '',
            $query,
            $components['fragment'] ?? null,
            $preferredFormat,
            $alwaysIncludeDefaultPort
        );
    }

    function getScheme(): ?string
    {
        return $this->scheme;
    }

    function setScheme(?string $scheme): void
    {
        $this->scheme = $scheme;
    }

    function hasScheme(): bool
    {
        return $this->scheme !== null;
    }

    function getUserInfo(): ?string
    {
        if ($this->user === null) {
            return null;
        }

        $userInfo = $this->user;
        if ($this->password !== null && $this->password !== '') {
            $userInfo .= ':' . $this->password;
        }

        return $userInfo;
    }

    function setUserInfo(?string $user, ?string $password = null): void
    {
        [$this->user, $this->password] = $this->normalizeUserInfo($user, $password);
    }

    /**
     * Get the authority (user:pass@host:port)
     *
     * @param bool|null $includeDefaultPort controls if standard ports (80/443/etc.) are included; null falls back to $alwaysIncludeDefaultPort.
     * @return string
     */
    function getAuthority(?bool $includeDefaultPort = null): string
    {
        $includeDefaultPort = $includeDefaultPort ?? $this->alwaysIncludeDefaultPort;
        return $this->buildAuthority($includeDefaultPort);
    }

    /**
     * @throws InvalidUrlException if the authority is invalid
     */
    function setAuthority(?string $authority): void
    {
        if ($authority === null || $authority === '') {
            $this->user = null;
            $this->password = null;
            $this->host = null;
            $this->port = null;
            return;
        }

        $components = $this->parseAuthority($authority);

        $this->user = $components['user'];
        $this->password = $components['password'];
        $this->host = $components['host'];
        $this->port = $components['port'];
    }

    function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * Get host name, including the port, if defined
     *
     * E.g. example.com:8080
     */
    function getFullHost(): ?string
    {
        $fullHost = $this->host;
        if ($this->port !== null) {
            $fullHost .= ':' . $this->port;
        }
        return $fullHost;
    }

    function setHost(?string $host): void
    {
        $this->host = $host;
    }

    function hasHost(): bool
    {
        return $this->host !== null;
    }

    function getPort(): ?int
    {
        return $this->port;
    }

    function setPort(?int $port): void
    {
        $this->validatePort($port);
        $this->port = $port;
    }

    function hasPort(): bool
    {
        return $this->port !== null;
    }

    function getPath(): string
    {
        return $this->path;
    }

    function setPath(string $path): void
    {
        $this->path = $path;
    }

    function hasPath(): bool
    {
        return $this->path !== '';
    }

    function getQuery(): array
    {
        return $this->query;
    }

    /**
     * Why not http_build_query()?
     *
     * http_build_query() has several issues for URI compliance:
     * 1. Always adds '=' for empty values: "key=" instead of "key"
     * 2. Uses PHP-specific encoding (spaces as '+' instead of '%20')
     * 3. Cannot handle null values properly
     * 4. Array handling creates nested structures incompatible with URI specs
     *
     * Manual building ensures RFC 3986 compliance and matches PSR-7 behavior.
     */
    function getQueryString(): string
    {
        if (!$this->query) return '';

        $pairs = array_map(function($k, $v) {
            $k = rawurlencode((string)$k);
            if (is_array($v)) {
                return http_build_query([$k => $v], '', '&');
            }
            return $v === null || $v === ''
                ? $k
                : $k . '=' . rawurlencode((string)$v);
        }, array_keys($this->query), $this->query);

        return implode('&', $pairs);
    }

    function setQuery(array $query): void
    {
        $this->query = $query;
    }

    function hasQuery(): bool
    {
        return !empty($this->query);
    }

    function getFragment(): ?string
    {
        return $this->fragment;
    }

    function setFragment(?string $fragment): void
    {
        $this->fragment = $fragment;
    }

    function hasFragment(): bool
    {
        return $this->fragment !== null;
    }

    function getPreferredFormat(): int
    {
        return $this->preferredFormat;
    }

    /**
     * Define the preferred URL format to be returned by build()
     *
     * @see Url::RELATIVE
     * @see URL::ABSOLUTE
     */
    function setPreferredFormat(int $preferredFormat): void
    {
        $this->preferredFormat = $preferredFormat;
    }

    function getAlwaysIncludeDefaultPort(): bool
    {
        return $this->alwaysIncludeDefaultPort;
    }

    /**
     * Whether to always include the default port in the authority
     */
    function setAlwaysIncludeDefaultPort(bool $alwaysIncludeDefaultPort): void
    {
        $this->alwaysIncludeDefaultPort = $alwaysIncludeDefaultPort;
    }

    /**
     * See whether a query parameter is defined
     *
     * @param string|int $parameter
     */
    function has($parameter): bool
    {
        return key_exists($parameter, $this->query);
    }

    /**
     * Attempt to retrieve a query parameter value
     *
     * Returns NULL if the query parameter is not defined.
     *
     * @param string|int $parameter
     * @return mixed
     */
    function get($parameter)
    {
        return $this->query[$parameter] ?? null;
    }

    /**
     * Set query parameter
     *
     * @param string|int $parameter
     * @param mixed $value
     */
    function set($parameter, $value): void
    {
        $this->query[$parameter] = $value;
    }

    /**
     * Add multiple query parameters
     *
     * Already defined parameters with the same key will be overriden.
     */
    function add(array $parameters): void
    {
        foreach ($parameters as $parameter => $value) {
            $this->query[$parameter] = $value;
        }
    }

    /**
     * Remove a query parameter
     *
     * @param string|int $parameter
     */
    function remove($parameter): void
    {
        unset($this->query[$parameter]);
    }

    /**
     * Remove all query parameters
     */
    function removeAll(): void
    {
        $this->query = [];
    }

    /**
     * Build an absolute or relative URL
     *
     * - if no host is specified, a relative URL will be returned
     * - if the host is specified, an absolute URL will be returned
     *   (unless the preferred format option is set to relative)
     *
     * @see Url::setPreferredFormat()
     */
    function build(): string
    {
        if ($this->host !== null && $this->preferredFormat === static::ABSOLUTE) {
            return $this->buildAbsolute();
        } else {
            return $this->buildRelative();
        }
    }

    /**
     * Build an absolute URL
     *
     * @throws IncompleteUrlException if no host is specified
     */
    function buildAbsolute(): string
    {
        $output = '';

        if ($this->host === null) {
            throw new IncompleteUrlException('No host specified');
        }

        // scheme
        if ($this->scheme !== null) {
            $output .= $this->scheme;
            $output .= '://';
        } else {
            // protocol-relative
            $output .= '//';
        }

        // authority - host and port
        $output .= $this->buildAuthority($this->alwaysIncludeDefaultPort);;

        // ensure slash between host and path
        if ($this->path !== '' && $this->path[0] !== '/') {
            $output .= '/';
        }

        // path, query, fragment
        $output .= $this->buildRelative();

        return $output;
    }

    /**
     * Build a relative URL
     */
    function buildRelative(): string
    {
        $output = '';

        // path
        $output .= $this->path;

        // query
        if ($this->query) {
            $output .= '?';
            $output .= $this->getQueryString();
        }

        // fragment
        if ($this->fragment !== null && $this->fragment !== '') {
            $output .= '#';
            $output .= $this->fragment;
        }

        return $output;
    }

    private function normalizeUserInfo(?string $user, ?string $password): array
    {
        if ($user === '' || $user === null) {
            return [null, null];
        }
        return [$user, $password];
    }

    private function validatePort(?int $port): void
    {
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidUrlException(sprintf('Port %d is out of range (1-65535)', $port));
        }
    }

    /**
     * Check whether a host is valid
     */
    private static function isValidHost(string $host): bool
    {
        // IPv6 in brackets
        if ((strpos($host, '[') === 0) && (substr($host, -1) === ']')) {
            $ipv6 = substr($host, 1, -1);
            return filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }

        // IPv4
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return true;
        }

        // standard domain (RFC 1123)
        if (strlen($host) > 253) {
            return false;
        }

        $labels = explode('.', $host);
        foreach ($labels as $label) {
            if (strlen($label) === 0 || strlen($label) > 63) return false;
            if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?$/', $label)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse an authority part of an URL (user:pass@host:port)
     * @see parseAuthorityGeneric()
     */
    private function parseAuthority(string $authority): array
    {
        if (strpos($authority, '[') !== false) {
            return $this->parseAuthorityGeneric($authority, true);
        }
        return $this->parseAuthorityGeneric($authority);
    }

    /**
     * Parse an authority part of an URL (user:pass@host:port)
     * @return array{
     *     user: string|null,
     *     password: string|null,
     *     host: string,
     *     port: int|null,
     * }
     */
    private function parseAuthorityGeneric(string $authority, bool $isIPv6 = false): array
    {
        if ($authority === '') {
            throw new InvalidUrlException('Authority cannot be empty');
        }

        // specific chech for "@" only
        if ($authority === '@') {
            throw new InvalidUrlException(sprintf('Empty host in authority "%s"', $authority));
        }

        $pattern = $isIPv6
            ? '/^(?:([^:@]+)(?::([^@]*))?@)?\[([^\]]+)\](?::(.*))?$/'
            : '/^(?:([^:@]+)(?::([^@]*))?@)?([^:]+?)(?::(.*))?$/';

        if (!preg_match($pattern, $authority, $matches)) {
            throw new InvalidUrlException(sprintf('Invalid authority format "%s"', $authority));
        }

        $user = $matches[1] ?? null;
        $password = $matches[2] ?? null;
        $host = $matches[3] ?? '';
        $portStr = $matches[4] ?? null;

        if ($host === '') {
            throw new InvalidUrlException(sprintf('Empty host in authority "%s"', $authority));
        }

        $port = null;
        if ($portStr !== null) {
            if ($portStr === '' || !ctype_digit($portStr) || $portStr[0] === '-') {
                throw new InvalidUrlException(sprintf('Invalid port in authority "%s"', $authority));
            }
            $port = (int)$portStr;
            $this->validatePort($port);
        }

        [$user, $password] = $this->normalizeUserInfo($user, $password);

        if ($isIPv6) {
            $host = '[' . $host . ']';
        }

        return [
            'user' => $user,
            'password' => $password,
            'host' => $host,
            'port' => $port,
        ];
    }

    /**
     *  Build an authority part of an URL (user:pass@host:port)
     */
    private function buildAuthority(bool $includeDefaultPort): string
    {
        if ($this->host === null) {
            return '';
        }

        $authority = '';

        $userInfo = $this->getUserInfo();
        if ($userInfo !== null) {
            $authority .= $userInfo . '@';
        }

        $authority .= $this->host;

        if ($this->port !== null
            && (
                $includeDefaultPort
                || !isset(self::$standardPorts[$this->scheme])
                || self::$standardPorts[$this->scheme] !== $this->port
            )
        ) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }
}
