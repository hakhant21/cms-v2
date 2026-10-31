<?php

/**
 * Walks the whole site and reports every link that answers with an error.
 *
 * The public site is data, not templates: a page's address, its menu entry and
 * the links inside its blocks are all typed by an editor. A typo in any of them
 * is invisible in the admin — it only shows up as a 404 for a visitor — so this
 * crawler exists to turn that into a command you can run before a deploy.
 *
 * It starts from the sitemap (which lists every page in every language), then
 * follows the links it finds on those pages, so a broken link in a block or a
 * menu is caught wherever it was written, and it names the page that linked to
 * it. Only this site's own addresses are requested: mail, telephone and other
 * sites are counted and skipped.
 *
 *   php crawl.php [base-url]      (default: http://127.0.0.1:8123)
 *
 * Exit code 0 when every address answered, 1 when at least one did not.
 */
const TIMEOUT = 15;

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');

if (preg_match('#^https?://#', $base) !== 1) {
    fwrite(STDERR, "usage: php crawl.php [http://host:port]\n");
    exit(2);
}

$started = microtime(true);

/** @var array<int, string> addresses still to request */
$queue = ['/'];

/** @var array<string, int> address => the status it answered with */
$pages = [];

/** @var array<string, string> address => the page that linked to it */
$from = ['/' => '(start)'];

/** @var array<string, true> off-site links already counted */
$external = [];

/**
 * Fetch one address. Returns [status, body]; status 0 means the request failed
 * outright (refused, timed out), which is just as broken as a 404.
 */
function fetch(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: ruma-crawler/1.0 (+php crawl.php)\r\nAccept: text/html\r\n",
            'follow_location' => 1,
            'max_redirects' => 8,
            'ignore_errors' => true,
            'timeout' => TIMEOUT,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $body = @file_get_contents($url, false, $context);
    $status = 0;

    // A redirect chain reports every hop; the last one is what the visitor sees.
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $header, $match) === 1) {
            $status = (int) $match[1];
        }
    }

    return [$status, $body === false ? '' : $body];
}

/**
 * The same-site paths a response points at. Off-site links are collected in
 * `$external` so they can be reported as skipped. A sitemap lists absolute
 * URLs, a page lists hrefs, so both are read the same way: keep the path, drop
 * the origin, the query and the fragment.
 *
 * @param  array<string, true>  $external
 * @return list<string>
 */
function links(string $body, string $base, array &$external): array
{
    preg_match_all('#<a\b[^>]*\bhref="([^"]+)"#i', $body, $anchors);
    preg_match_all('#<loc>([^<]+)</loc>#i', $body, $locations);

    $paths = [];

    foreach (array_merge($anchors[1], $locations[1]) as $url) {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5);

        // Nothing a crawler can follow: other schemes, a bare fragment.
        if ($url === '' || str_starts_with($url, '#')
            || preg_match('#^(mailto|tel|javascript|data|sms|whatsapp):#i', $url) === 1) {
            continue;
        }

        if (preg_match('#^https?://#i', $url) === 1 && $url !== $base && ! str_starts_with($url, $base.'/')) {
            $external[$url] = true;

            continue;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = '/'.ltrim((string) ($path === false || $path === null ? '/' : $path), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        // The query is kept: the language switch lives outside the language
        // prefix and takes the page it should stay on as `?path=`, so dropping
        // it turns a working link into a 404.
        $query = parse_url($url, PHP_URL_QUERY);

        $paths[] = is_string($query) && $query !== '' ? $path.'?'.$query : $path;
    }

    return $paths;
}

/**
 * The sitemap is the only place that knows every page in every language, so it
 * seeds the crawl: following links alone would miss a page nothing links to.
 *
 * @param  list<string>  $queue
 * @param  array<string, string>  $from
 */
function seedFromSitemap(string $base, array &$queue, array &$from): void
{
    [$status, $body] = fetch($base.'/sitemap.xml');

    if ($status !== 200 || preg_match_all('#<loc>([^<]+)</loc>#i', $body, $matches) === 0) {
        fwrite(STDERR, "warning: no usable sitemap at {$base}/sitemap.xml\n");

        return;
    }

    foreach ($matches[1] as $location) {
        $path = parse_url(html_entity_decode(trim($location), ENT_QUOTES | ENT_HTML5), PHP_URL_PATH);
        $path = '/'.ltrim((string) ($path === false || $path === null ? '/' : $path), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        if (! isset($from[$path])) {
            $from[$path] = '(sitemap)';
            $queue[] = $path;
        }
    }
}

seedFromSitemap($base, $queue, $from);

while ($queue !== []) {
    $path = array_shift($queue);

    if (isset($pages[$path])) {
        continue;
    }

    [$status, $body] = fetch($base.$path);
    $pages[$path] = $status;

    if ($status < 200 || $status >= 400) {
        continue;
    }

    foreach (links($body, $base, $external) as $target) {
        if (! isset($pages[$target]) && ! isset($from[$target])) {
            $from[$target] = $path;
            $queue[] = $target;
        }
    }
}

ksort($pages);

$answered = 0;
$broken = [];

foreach ($pages as $path => $status) {
    if ($status >= 200 && $status < 400) {
        $answered++;
    } else {
        $broken[$path] = $status;
    }
}

$total = count($pages);

printf(
    "crawled %d address%s from %s in %.1fs\n",
    $total,
    $total === 1 ? '' : 'es',
    $base,
    microtime(true) - $started,
);
printf("  %d answered\n", $answered);

if ($external !== []) {
    printf("  %d off-site links skipped (open them by hand if they matter)\n", count($external));
}

if ($broken === []) {
    echo "  no broken links\n";

    exit(0);
}

printf("  %d broken:\n", count($broken));

foreach ($broken as $path => $status) {
    printf("    %-4s %s  (linked from %s)\n", $status === 0 ? 'ERR' : $status, $path, $from[$path] ?? '?');
}

exit(1);
