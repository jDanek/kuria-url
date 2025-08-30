<?php declare(strict_types=1);

namespace Kuria\Url;

use Kuria\DevMeta\Test;
use Kuria\Url\Exception\IncompleteUrlException;
use Kuria\Url\Exception\InvalidUrlException;

class UrlTest extends Test
{
    /**
     * @dataProvider provideUrls
     */
    function testShouldParseAndRebuild(string $urlToParse, array $expectedMethodResults)
    {
        $expectedMethodResults += [
            'build' => $urlToParse,
            '__toString' => $urlToParse,
        ];

        $url = Url::parse($urlToParse);

        $this->assertInstanceOf(Url::class, $url);
        $this->assertUrlMethodResults($url, $expectedMethodResults);
    }

    function provideUrls()
    {
        return [
            // urlToParse, expectedMethodResults
            'empty string' => [
                '',
                [
                    'getPath' => '',
                ],
            ],
            'relative path only' => [
                'foo',
                [
                    'getPath' => 'foo',
                ],
            ],
            'absolute path only' => [
                '/foo',
                [
                    'getPath' => '/foo',
                ],
            ],
            'fragment only' => [
                '#foo-bar',
                [
                    'getFragment' => 'foo-bar',
                ],
            ],
            'query only' => [
                '?foo=bar&lorem=ipsum',
                [
                    'getQuery' => ['foo' => 'bar', 'lorem' => 'ipsum'],
                ],
            ],
            'protocol-relative host' => [
                '//example.com',
                [
                    'getHost' => 'example.com',
                    'getAuthority' => 'example.com',
                ],
            ],
            'protocol-relative host with port' => [
                '//example.com:80',
                [
                    'getHost' => 'example.com',
                    'getFullHost' => 'example.com:80',
                    'getPort' => 80,
                    'getAuthority' => 'example.com:80',
                    'buildRelative' => '',
                ],
            ],
            'host with protocol' => [
                'http://example.com',
                [
                    'getScheme' => 'http',
                    'getHost' => 'example.com',
                    'getAuthority' => 'example.com',
                    'buildRelative' => '',
                ],
            ],
            'host with protocol and standard port (http)' => [
                'http://example.com:80',
                [
                    'getScheme' => 'http',
                    'getHost' => 'example.com',
                    'getFullHost' => 'example.com:80',
                    'getPort' => 80,
                    'getAuthority' => 'example.com',
                    'buildRelative' => '',
                ],
            ],
            'host with protocol and standard port (https)' => [
                'https://example.com:443',
                [
                    'getScheme' => 'https',
                    'getHost' => 'example.com',
                    'getFullHost' => 'example.com:443',
                    'getPort' => 443,
                    'getAuthority' => 'example.com',
                    'buildRelative' => '',
                ],
            ],
            'absolute url' => [
                'http://www.example.com/foo/bar.html',
                [
                    'getScheme' => 'http',
                    'getHost' => 'www.example.com',
                    'getPath' => '/foo/bar.html',
                    'getAuthority' => 'www.example.com',
                    'buildRelative' => '/foo/bar.html',
                ],
            ],
            'url with all components' => [
                'https://example.com:88/foo/bar.html?foo=bar&baz%5B0%5D=zero&baz%5B1%5D=one#test',
                [
                    'getScheme' => 'https',
                    'getHost' => 'example.com',
                    'getFullHost' => 'example.com:88',
                    'getPort' => 88,
                    'getPath' => '/foo/bar.html',
                    'getQuery' => ['foo' => 'bar', 'baz' => ['zero', 'one']],
                    'getFragment' => 'test',
                    'getAuthority' => 'example.com:88',
                    'buildRelative' => '/foo/bar.html?foo=bar&baz%5B0%5D=zero&baz%5B1%5D=one#test',
                ],
            ],

            'url with user info (username only)' => [
                'https://john@example.com/path',
                [
                    'getScheme' => 'https',
                    'getHost' => 'example.com',
                    'getPath' => '/path',
                    'getUserInfo' => 'john',
                    'getAuthority' => 'john@example.com',
                    'buildRelative' => '/path',
                ],
            ],
            'url with user info (username and password)' => [
                'https://john:secret@example.com/path',
                [
                    'getScheme' => 'https',
                    'getHost' => 'example.com',
                    'getPath' => '/path',
                    'getUserInfo' => 'john:secret',
                    'getAuthority' => 'john:secret@example.com',
                    'buildRelative' => '/path',
                ],
            ],
            'url with user info and port' => [
                'ftp://user:pass@ftp.example.com:21/files',
                [
                    'getScheme' => 'ftp',
                    'getHost' => 'ftp.example.com',
                    'getPort' => 21,
                    'getFullHost' => 'ftp.example.com:21',
                    'getPath' => '/files',
                    'getUserInfo' => 'user:pass',
                    'getAuthority' => 'user:pass@ftp.example.com',
                    'buildRelative' => '/files',
                ],
            ],
            'url with user info and non-standard port' => [
                'http://admin:password@localhost:8080/admin',
                [
                    'getScheme' => 'http',
                    'getHost' => 'localhost',
                    'getPort' => 8080,
                    'getFullHost' => 'localhost:8080',
                    'getPath' => '/admin',
                    'getUserInfo' => 'admin:password',
                    'getAuthority' => 'admin:password@localhost:8080',
                    'buildRelative' => '/admin',
                ],
            ],
            'test url with ipv6 user info port query and fragment' => [
                'ftp://user:pa:ss@[2001:db8:85a3::8a2e:370:7334]:2121/some/../weird/./path/file.txt?empty&flag=true&arr%5B0%5D=1&arr%5B1%5D=2&encoded=%E2%9C%93#frag-ment_part',
                [
                    'getScheme' => 'ftp',
                    'getHost' => '[2001:db8:85a3::8a2e:370:7334]',
                    'getFullHost' => '[2001:db8:85a3::8a2e:370:7334]:2121',
                    'getPort' => 2121,
                    'getPath' => '/some/../weird/./path/file.txt',
                    'getUserInfo' => 'user:pa:ss',
                    'getAuthority' => 'user:pa:ss@[2001:db8:85a3::8a2e:370:7334]:2121',
                    'getQuery' => ['empty' => '', 'flag' => 'true', 'arr' => ['1', '2'], 'encoded' => '✓'],
                    'getFragment' => 'frag-ment_part',
                    'buildRelative' => '/some/../weird/./path/file.txt?empty&flag=true&arr%5B0%5D=1&arr%5B1%5D=2&encoded=%E2%9C%93#frag-ment_part',
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidUrls
     */
    function testShouldThrowExceptionOnInvalidUrl(string $invalidUrl)
    {
        $this->expectException(InvalidUrlException::class);
        Url::parse($invalidUrl);
    }

    function provideInvalidUrls(): array
    {
        return [
            'invalid characters' => ['http://example.com:invalid'],
            'port out of range' => ['http://example.com:70000'],
            'negative port' => ['http://example.com:-1'],
            'invalid IPv6' => ['http://[::1'],
        ];
    }

    function testShouldGetQueryString()
    {
        $url = new Url();
        $url->setQuery(['foo' => 'bar', 'lorem' => 'ipsum']);

        $this->assertSame('foo=bar&lorem=ipsum', $url->getQueryString());
    }

    function testShouldManipulateUrl()
    {
        $url = new Url();
        $step = 0;
        $expectedMethodResults = [];

        // assert initial state
        $this->assertUrlMethodResults($url, $expectedMethodResults);

        // iteratively modify the URL and assert the results
        do {
            switch (++$step) {

                case 1:
                    $url->setHost('localhost');

                    $expectedMethodResults['getHost'] = 'localhost';
                    $expectedMethodResults['build'] = '//localhost';
                    $expectedMethodResults['getAuthority'] = 'localhost';
                    break;

                case 2:
                    $url->setPort(8080);

                    $expectedMethodResults['getPort'] = 8080;
                    $expectedMethodResults['getFullHost'] = 'localhost:8080';
                    $expectedMethodResults['build'] = '//localhost:8080';
                    $expectedMethodResults['getAuthority'] = 'localhost:8080';
                    break;

                case 3:
                    $url->setScheme('ftp');

                    $expectedMethodResults['getScheme'] = 'ftp';
                    $expectedMethodResults['build'] = 'ftp://localhost:8080';
                    break;

                case 4:
                    $url->setPath('foo/bar');

                    $expectedMethodResults['getPath'] = 'foo/bar';
                    $expectedMethodResults['build'] = 'ftp://localhost:8080/foo/bar';
                    $expectedMethodResults['buildRelative'] = 'foo/bar';
                    break;

                case 5:
                    $url->setPreferredFormat(Url::RELATIVE);

                    $expectedMethodResults['getPreferredFormat'] = Url::RELATIVE;
                    $expectedMethodResults['build'] = 'foo/bar';
                    break;

                case 6:
                    $url->setQuery(['param' => 'value']);
                    $url->setPreferredFormat(Url::ABSOLUTE);

                    $expectedMethodResults['getQuery'] = ['param' => 'value'];
                    $expectedMethodResults['build'] = 'ftp://localhost:8080/foo/bar?param=value';
                    $expectedMethodResults['buildRelative'] = 'foo/bar?param=value';
                    unset($expectedMethodResults['getPreferredFormat']);
                    break;

                case 7:
                    $url->set('lorem', ['ipsum', 'dolor']);

                    $expectedMethodResults['getQuery'] = ['param' => 'value', 'lorem' => ['ipsum', 'dolor']];
                    $expectedMethodResults['build'] = 'ftp://localhost:8080/foo/bar'
                        . '?param=value&lorem%5B0%5D=ipsum&lorem%5B1%5D=dolor';
                    $expectedMethodResults['buildRelative'] = 'foo/bar?param=value&lorem%5B0%5D=ipsum&lorem%5B1%5D=dolor';
                    break;

                case 8:
                    $url->remove('lorem');
                    $url->add(['param' => 'new-value']);

                    $expectedMethodResults['getQuery'] = ['param' => 'new-value'];
                    $expectedMethodResults['build'] = 'ftp://localhost:8080/foo/bar?param=new-value';
                    $expectedMethodResults['buildRelative'] = 'foo/bar?param=new-value';
                    break;

                case 9:
                    $url->setFragment('test-fragment');

                    $expectedMethodResults['getFragment'] = 'test-fragment';
                    $expectedMethodResults['build'] = 'ftp://localhost:8080/foo/bar?param=new-value#test-fragment';
                    $expectedMethodResults['buildRelative'] = 'foo/bar?param=new-value#test-fragment';
                    break;

                case 10:
                    $url->removeAll();

                    $expectedMethodResults['getQuery'] = [];
                    $expectedMethodResults['build'] = 'ftp://localhost:8080/foo/bar#test-fragment';
                    $expectedMethodResults['buildRelative'] = 'foo/bar#test-fragment';
                    break;

                case 11:
                    $url->setFragment(null);

                    $expectedMethodResults['getFragment'] = null;
                    $expectedMethodResults['build'] = 'ftp://localhost:8080/foo/bar';
                    $expectedMethodResults['buildRelative'] = 'foo/bar';
                    break;

                case 12:
                    $url->setScheme(null);

                    $expectedMethodResults['getScheme'] = null;
                    $expectedMethodResults['build'] = '//localhost:8080/foo/bar';
                    break;

                case 13:
                    $url->setPort(null);

                    $expectedMethodResults['getPort'] = null;
                    $expectedMethodResults['getFullHost'] = 'localhost';
                    $expectedMethodResults['build'] = '//localhost/foo/bar';
                    $expectedMethodResults['getAuthority'] = 'localhost';
                    break;

                case 14:
                    $url->setPath('');

                    $expectedMethodResults['getPath'] = '';
                    $expectedMethodResults['build'] = '//localhost';
                    $expectedMethodResults['buildRelative'] = '';
                    break;

                case 15:
                    $url->setHost(null);

                    $expectedMethodResults['getHost'] = null;
                    $expectedMethodResults['getFullHost'] = null;
                    $expectedMethodResults['build'] = '';
                    $expectedMethodResults['getAuthority'] = '';
                    break;

                default:
                    break 2;

            }

            $this->assertUrlMethodResults($url, $expectedMethodResults);
        } while (true);
    }

    function testShouldThrowExceptionIfBuildingAbsoluteUrlWithoutHost()
    {
        $this->expectException(IncompleteUrlException::class);

        (new Url())->buildAbsolute();
    }

    function testShouldRetrieveQueryParameters()
    {
        $url = new Url();
        $url->setQuery(['foo' => 'bar', 'lorem' => 'ipsum', 'null-param' => null]);

        $this->assertFalse($url->has('nonexistent'));
        $this->assertTrue($url->has('foo'));
        $this->assertTrue($url->has('lorem'));
        $this->assertTrue($url->has('null-param'));

        $this->assertSame('bar', $url->get('foo'));
        $this->assertSame('ipsum', $url->get('lorem'));
        $this->assertNull($url->get('nonexistent'));
        $this->assertNull($url->get('null-param'));
    }

    function testShouldSetDefaultPreferredFormatToAbsolute()
    {
        $this->assertSame(Url::ABSOLUTE, (new Url())->getPreferredFormat());
        $this->assertSame(Url::ABSOLUTE, Url::parse('foo')->getPreferredFormat());
    }

    function testShouldSetPreferredFormatViaConstructor()
    {
        $url = new Url(null, null, null, null, null, '', [], null, Url::RELATIVE);

        $this->assertSame(Url::RELATIVE, $url->getPreferredFormat());
    }

    function testShouldSetPreferredFormatViaParse()
    {
        $url = Url::parse('foo', Url::RELATIVE);

        $this->assertSame(Url::RELATIVE, $url->getPreferredFormat());
    }

    /**
     * @dataProvider provideAuthorityTestCases
     */
    function testShouldHandleAuthorityParsing(string $authority, array $expectedComponents)
    {
        $url = new Url();
        $url->setAuthority($authority);

        $this->assertSame($expectedComponents['user'] ?? null, $url->getUserInfo());
        $this->assertSame($expectedComponents['host'], $url->getHost());
        $this->assertSame($expectedComponents['port'] ?? null, $url->getPort());
    }

    function provideAuthorityTestCases(): array
    {
        return [
            'simple host' => [
                'example.com',
                ['host' => 'example.com']
            ],
            'host with port' => [
                'example.com:8080',
                ['host' => 'example.com', 'port' => 8080]
            ],
            'user and host' => [
                'user@example.com',
                ['user' => 'user', 'host' => 'example.com']
            ],
            'user, password and host' => [
                'user:pass@example.com',
                ['user' => 'user:pass', 'host' => 'example.com']
            ],
            'complete authority' => [
                'user:pass@example.com:8080',
                ['user' => 'user:pass', 'host' => 'example.com', 'port' => 8080]
            ],
            'IPv6 address' => [
                '[::1]',
                ['host' => '[::1]']
            ],
            'IPv6 with port' => [
                '[2001:db8::1]:8080',
                ['host' => '[2001:db8::1]', 'port' => 8080]
            ],
            'user with IPv6' => [
                'user@[::1]',
                ['user' => 'user', 'host' => '[::1]']
            ],
            'complex IPv6' => [
                'admin:secret@[2001:db8:85a3::8a2e:370:7334]:443',
                ['user' => 'admin:secret', 'host' => '[2001:db8:85a3::8a2e:370:7334]', 'port' => 443]
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidAuthorityTestCases
     */
    function testShouldThrowExceptionOnInvalidAuthority(string $authority)
    {
        $this->expectException(InvalidUrlException::class);
        $url = new Url();
        $url->setAuthority($authority);
    }

    function provideInvalidAuthorityTestCases(): array
    {
        return [
            'invalid port (non-numeric)' => ['example.com:abc'],
            'invalid port (empty)' => ['example.com:'],
            'invalid port (negative)' => ['example.com:-1'],
            'invalid port (too high)' => ['example.com:70000'],
            'invalid IPv6 (no closing bracket)' => ['[::1'],
            'empty host' => ['@'],
            'only port' => [':8080'],
        ];
    }

    /**
     * @dataProvider provideStandardPortsTestCases
     */
    function testShouldHandleStandardPorts(string $scheme, int $port, bool $shouldOmitFromAuthority)
    {
        $url = new Url();
        $url->setScheme($scheme);
        $url->setHost('example.com');
        $url->setPort($port);

        $expectedAuthority = $shouldOmitFromAuthority ? 'example.com' : "example.com:$port";
        $this->assertSame($expectedAuthority, $url->getAuthority());
        $this->assertSame("example.com:$port", $url->getFullHost());
    }

    function provideStandardPortsTestCases(): array
    {
        return [
            'HTTP standard port' => ['http', 80, true],
            'HTTPS standard port' => ['https', 443, true],
            'FTP standard port' => ['ftp', 21, true],
            'FTPS standard port' => ['ftps', 990, true],
            'SSH standard port' => ['ssh', 22, true],
            'HTTP non-standard port' => ['http', 8080, false],
            'HTTPS non-standard port' => ['https', 8443, false],
            'Unknown scheme with common port' => ['custom', 80, false],
        ];
    }

    // port Validation tests

    function testShouldValidatePortRange()
    {
        $url = new Url();

        // valid ports
        $url->setPort(1);
        $this->assertSame(1, $url->getPort());

        $url->setPort(65535);
        $this->assertSame(65535, $url->getPort());

        $url->setPort(null);
        $this->assertNull($url->getPort());
    }

    /**
     * @dataProvider provideInvalidPorts
     */
    function testShouldThrowExceptionOnInvalidPort(int $port)
    {
        $this->expectException(InvalidUrlException::class);

        $url = new Url();
        $url->setPort($port);
    }

    function provideInvalidPorts(): array
    {
        return [
            'negative port' => [-1],
            'port too high' => [65536],
            'very high port' => [100000],
        ];
    }

    function testShouldHandleUserInfoEdgeCases()
    {
        $url = new Url();

        // empty string should clear user info
        $url->setUserInfo('user', 'pass');
        $url->setUserInfo('');
        $this->assertNull($url->getUserInfo());

        // null should clear user info
        $url->setUserInfo('user', 'pass');
        $url->setUserInfo(null);
        $this->assertNull($url->getUserInfo());

        // empty password
        $url->setUserInfo('user', '');
        $this->assertSame('user', $url->getUserInfo());

        // special characters in credentials
        $url->setUserInfo('user@domain.com', 'p@ssw:rd!');
        $this->assertSame('user@domain.com:p@ssw:rd!', $url->getUserInfo());
    }

    function testShouldValidatePreferredFormat()
    {
        $url = new Url();

        $url->setPreferredFormat(Url::RELATIVE);
        $this->assertSame(Url::RELATIVE, $url->getPreferredFormat());

        $url->setPreferredFormat(Url::ABSOLUTE);
        $this->assertSame(Url::ABSOLUTE, $url->getPreferredFormat());
    }

    function testShouldUseArrayKeyExistsForQueryParameters()
    {
        $url = new Url();
        $url->setQuery(['existing' => null, 'another' => false, 'third' => 0]);

        // test with null value
        $this->assertTrue($url->has('existing'));
        $this->assertNull($url->get('existing'));

        // test with false value
        $this->assertTrue($url->has('another'));
        $this->assertFalse($url->get('another'));

        // test with 0 value
        $this->assertTrue($url->has('third'));
        $this->assertSame(0, $url->get('third'));

        // test non-existent
        $this->assertFalse($url->has('nonexistent'));
        $this->assertNull($url->get('nonexistent'));
    }

    // URL Building Edge cases

    function testShouldBuildUrlsWithComplexPaths()
    {
        $url = new Url();
        $url->setScheme('https');
        $url->setHost('example.com');

        // path without leading slash should get one
        $url->setPath('api/v1/users');
        $this->assertSame('https://example.com/api/v1/users', $url->buildAbsolute());

        // path with leading slash should stay as is
        $url->setPath('/api/v1/users');
        $this->assertSame('https://example.com/api/v1/users', $url->buildAbsolute());

        // empty path
        $url->setPath('');
        $this->assertSame('https://example.com', $url->buildAbsolute());

        // root path
        $url->setPath('/');
        $this->assertSame('https://example.com/', $url->buildAbsolute());
    }

    function testShouldBuildProtocolRelativeUrls()
    {
        $url = new Url();
        $url->setHost('example.com');
        // no scheme set

        $this->assertSame('//example.com', $url->buildAbsolute());

        $url->setPath('/path');
        $this->assertSame('//example.com/path', $url->buildAbsolute());

        $url->setQuery(['param' => 'value']);
        $this->assertSame('//example.com/path?param=value', $url->buildAbsolute());
    }

    function testShouldHandleComplexUrlModifications()
    {
        $url = Url::parse('https://user:pass@api.example.com:8443/v1/endpoint?limit=10&offset=0#results');

        // verify initial state
        $this->assertSame('https', $url->getScheme());
        $this->assertSame('user:pass', $url->getUserInfo());
        $this->assertSame('api.example.com', $url->getHost());
        $this->assertSame(8443, $url->getPort());
        $this->assertSame('/v1/endpoint', $url->getPath());
        $this->assertSame(['limit' => '10', 'offset' => '0'], $url->getQuery());
        $this->assertSame('results', $url->getFragment());

        // modify authority
        $url->setAuthority('newuser@newhost.com:9443');
        $this->assertSame('newuser', $url->getUserInfo());
        $this->assertSame('newhost.com', $url->getHost());
        $this->assertSame(9443, $url->getPort());

        // update query parameters
        $url->set('limit', '20');
        $url->add(['sort' => 'name', 'filter' => 'active']);
        $url->remove('offset');

        $expected = 'https://newuser@newhost.com:9443/v1/endpoint?limit=20&sort=name&filter=active#results';
        $this->assertSame($expected, $url->build());
    }

    function testShouldHandleUrlCloning()
    {
        $original = Url::parse('https://user@example.com:8080/path?param=value#fragment');

        // create new URL with same components
        $cloned = new Url(
            $original->getScheme(),
            null, // will be set via setUserInfo
            null,
            $original->getHost(),
            $original->getPort(),
            $original->getPath(),
            $original->getQuery(),
            $original->getFragment(),
            $original->getPreferredFormat()
        );
        $cloned->setUserInfo('user'); // set user info separately

        $this->assertSame($original->build(), $cloned->build());

        // modify cloned should not affect original
        $cloned->setHost('different.com');
        $this->assertNotSame($original->build(), $cloned->build());
        $this->assertSame('example.com', $original->getHost());
        $this->assertSame('different.com', $cloned->getHost());
    }

    function testShouldThrowExceptionWhenBuildingAbsoluteUrlWithoutHost()
    {
        $this->expectException(IncompleteUrlException::class);
        $this->expectExceptionMessage('No host specified');

        $url = new Url();
        $url->setPath('/path');
        $url->buildAbsolute();
    }

    // performance and memory tests

    function testShouldHandleLargeQueryArrays()
    {
        $url = new Url();
        $largeQuery = [];

        for ($i = 0; $i < 1000; $i++) {
            $largeQuery["param_$i"] = "value_$i";
        }

        $url->setQuery($largeQuery);
        $this->assertSame(1000, count($url->getQuery()));
        $this->assertTrue($url->has('param_500'));
        $this->assertSame('value_500', $url->get('param_500'));

        // test query string generation doesn't fail
        $queryString = $url->getQueryString();
        $this->assertIsString($queryString);
        $this->assertGreaterThan(0, strlen($queryString));
    }

    function testShouldHandleUnicodeInUrls()
    {
        $url = new Url();
        $url->setScheme('https');
        $url->setHost('example.com');
        $url->setPath('/example');
        $url->setQuery(['ěšč' => 'value', 'řžý' => '123']);
        $url->setFragment('áíé');

        $built = $url->build();
        $this->assertIsString($built);
        $this->assertStringContainsString('example.com', $built);

        // test that it can be parsed back
        $parsed = Url::parse($built);
        $this->assertSame($url->getHost(), $parsed->getHost());
    }

    // regression tests

    function testShouldHandleEmptyFragmentAndQuery()
    {
        $url = new Url();
        $url->setHost('example.com');

        // empty fragment should not appear in URL
        $url->setFragment('');
        $this->assertSame('//example.com', $url->build());

        // null fragment should not appear
        $url->setFragment(null);
        $this->assertSame('//example.com', $url->build());

        // empty query array should not appear
        $url->setQuery([]);
        $this->assertSame('//example.com', $url->build());
    }

    function testShouldPreserveZeroPort()
    {
        $url = Url::parse('http://example.com:0/');
        $this->assertSame(0, $url->getPort());
        $this->assertSame('example.com:0', $url->getFullHost());
        $this->assertSame('example.com:0', $url->getAuthority());
    }

    function testShouldHandleMultipleColonsInUserInfo()
    {
        $url = new Url();
        $url->setHost('example.com');
        $url->setUserInfo('user:with:colons', 'pass:with:colons');

        $this->assertSame('user:with:colons:pass:with:colons', $url->getUserInfo());
        $this->assertSame('user:with:colons:pass:with:colons@example.com', $url->getAuthority());
    }

    // edge case tests for buildRelative vs buildAbsolute

    function testShouldRespectPreferredFormatSettings()
    {
        $url = new Url();
        $url->setHost('example.com');
        $url->setPath('/test');

        // default should be absolute
        $this->assertSame('//example.com/test', $url->build());

        // set to relative
        $url->setPreferredFormat(Url::RELATIVE);
        $this->assertSame('/test', $url->build());

        // explicit buildAbsolute should still work
        $this->assertSame('//example.com/test', $url->buildAbsolute());

        // buildRelative should always return relative
        $this->assertSame('/test', $url->buildRelative());
    }

    function testShouldHandleUrlsWithoutHost()
    {
        $url = new Url();
        $url->setPath('/relative/path');
        $url->setQuery(['param' => 'value']);
        $url->setFragment('section');

        // should build relative regardless of preferred format
        $this->assertSame('/relative/path?param=value#section', $url->build());

        $url->setPreferredFormat(Url::ABSOLUTE);
        $this->assertSame('/relative/path?param=value#section', $url->build());

        // buildRelative should work
        $this->assertSame('/relative/path?param=value#section', $url->buildRelative());
    }

    // helper method for asserting URL method results
    private function assertUrlMethodResults(Url $url, array $expectedMethodResults)
    {
        $expectedMethodResults += [
            'getScheme' => null,
            'hasScheme' => isset($expectedMethodResults['getScheme']),
            'getUserInfo' => null,
            'getAuthority' => $expectedMethodResults['getAuthority'] ?? '',
            'getHost' => null,
            'hasHost' => isset($expectedMethodResults['getHost']),
            'getFullHost' => $expectedMethodResults['getHost'] ?? null,
            'getPort' => null,
            'hasPort' => isset($expectedMethodResults['getPort']),
            'getPath' => '',
            'hasPath' => ($expectedMethodResults['getPath'] ?? '') !== '',
            'getQuery' => [],
            'hasQuery' => !empty($expectedMethodResults['getQuery']),
            'getFragment' => null,
            'hasFragment' => isset($expectedMethodResults['getFragment']),
            'getPreferredFormat' => Url::ABSOLUTE,
        ];

        foreach ($expectedMethodResults as $method => $expectedValue) {
            $this->assertSame(
                $expectedValue,
                $url->{$method}(),
                sprintf('Expected Url::%s() to yield the expected value', $method)
            );
        }
    }

    // additional test methods for comprehensive coverage

    function testShouldHandleToStringMethod()
    {
        $url = new Url();
        $url->setHost('example.com');
        $url->setPath('/test');

        $this->assertSame('//example.com/test', (string) $url);
        $this->assertSame($url->build(), $url->__toString());
    }

    /**
     * @dataProvider provideQueryStringTestCases
     */
    function testShouldGenerateCorrectQueryStrings(array $query, string $expectedQueryString)
    {
        $url = new Url();
        $url->setQuery($query);

        $this->assertSame($expectedQueryString, $url->getQueryString());
    }

    function provideQueryStringTestCases(): array
    {
        return [
            'simple params' => [
                ['a' => '1', 'b' => '2'],
                'a=1&b=2'
            ],
            'array params' => [
                ['items' => ['apple', 'banana']],
                'items%5B0%5D=apple&items%5B1%5D=banana'
            ],
            'mixed params' => [
                ['simple' => 'value', 'array' => ['a', 'b'], 'number' => 123],
                'simple=value&array%5B0%5D=a&array%5B1%5D=b&number=123'
            ],
            'empty query' => [
                [],
                ''
            ],
            'special characters' => [
                ['key with spaces' => 'value & symbols'],
                'key%20with%20spaces=value%20%26%20symbols'
            ],
        ];
    }

    function testShouldHandlePathHasMethod()
    {
        $url = new Url();

        $this->assertFalse($url->hasPath());

        $url->setPath('');
        $this->assertFalse($url->hasPath());

        $url->setPath('/');
        $this->assertTrue($url->hasPath());

        $url->setPath('relative');
        $this->assertTrue($url->hasPath());
    }
}
