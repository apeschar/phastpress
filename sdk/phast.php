<?php

namespace Kibo\Phast\Cache;

interface Cache
{
    /**
     * @param string $key
     * @param callable|null $cached
     * @param int $expiresIn
     * @return mixed
     */
    public function get($key, callable $cached = null, $expiresIn = 0);
    /**
     * @param string $key
     * @param mixed $value
     * @param int $expiresIn
     * @return mixed
     */
    public function set($key, $value, $expiresIn = 0);
}
namespace Kibo\Phast\Cache\Sqlite;

class Cache implements \Kibo\Phast\Cache\Cache
{
    private static $managers = [];
    private $cacheRoot;
    private $name;
    private $maxSize;
    private $namespace;
    private $functions;
    public function __construct(array $config, string $namespace, \Kibo\Phast\Common\ObjectifiedFunctions $functions = null)
    {
        $this->cacheRoot = (string) $config['cacheRoot'];
        $this->name = (string) ($config['name'] ?? 'cache');
        $this->maxSize = (int) $config['maxSize'];
        $this->namespace = $namespace;
        $this->functions = $functions ?? new \Kibo\Phast\Common\ObjectifiedFunctions();
    }
    public function get($key, callable $fn = null, $expiresIn = 0)
    {
        return $this->getManager()->get($this->getKey($key), $fn, $expiresIn, $this->functions);
    }
    private function getKey(string $key) : string
    {
        return $this->namespace . "\0" . $key;
    }
    public function set($key, $value, $expiresIn = 0) : void
    {
        $this->getManager()->set($this->getKey($key), $value, $expiresIn, $this->functions);
    }
    public function getManager() : \Kibo\Phast\Cache\Sqlite\Manager
    {
        $key = $this->cacheRoot . '/' . $this->name;
        if (!isset(self::$managers[$key])) {
            self::$managers[$key] = new \Kibo\Phast\Cache\Sqlite\Manager($this->cacheRoot, $this->name, $this->maxSize);
        }
        return self::$managers[$key];
    }
}
namespace Kibo\Phast\Cache\Sqlite;

class Connection extends \PDO
{
    private $statements;
    public function prepare($query, $options = null) : \PDOStatement
    {
        if ($options) {
            return parent::prepare($query, $options);
        }
        if (!isset($this->statements[$query])) {
            $this->statements[$query] = parent::prepare($query);
        }
        return $this->statements[$query];
    }
    public function getPageSize() : int
    {
        return (int) $this->query('PRAGMA page_size')->fetchColumn();
    }
}
namespace Kibo\Phast\Cache\Sqlite;

class Manager
{
    use \Kibo\Phast\Logging\LoggingTrait;
    private $cacheRoot;
    private $name;
    private $maxSize;
    private $database;
    private $autorecover = true;
    public function __construct(string $cacheRoot, string $name, int $maxSize)
    {
        $this->cacheRoot = $cacheRoot;
        $this->name = $name;
        $this->maxSize = $maxSize;
    }
    public function setAutorecover(bool $autorecover) : void
    {
        $this->autorecover = $autorecover;
    }
    public function get(string $key, ?callable $cb, int $expiresIn, \Kibo\Phast\Common\ObjectifiedFunctions $functions)
    {
        return $this->autorecover(function () use($key, $cb, $expiresIn, $functions) {
            $query = $this->getDatabase()->prepare('
                SELECT value
                FROM cache
                WHERE
                    key = :key
                    AND (expires_at IS NULL OR expires_at > :time)
            ');
            $query->execute(['key' => $this->hashKey($key), 'time' => $functions->time()]);
            $row = $query->fetch(\PDO::FETCH_ASSOC);
            if ($row && $this->unserialize($row['value'], $value)) {
                return $value;
            }
            if ($cb === null) {
                return null;
            }
            $value = $cb();
            $this->set($key, $value, $expiresIn, $functions);
            return $value;
        });
    }
    public function set(string $key, $value, int $expiresIn, \Kibo\Phast\Common\ObjectifiedFunctions $functions)
    {
        return $this->autorecover(function () use($key, $value, $expiresIn, $functions) {
            $db = $this->getDatabase();
            $tries = 10;
            while ($tries--) {
                try {
                    $db->prepare('
                        REPLACE INTO cache (key, value, expires_at)
                        VALUES (:key, :value, :expires_at)
                    ')->execute(['key' => $this->hashKey($key), 'value' => $this->serialize($value), 'expires_at' => $expiresIn > 0 ? $functions->time() + $expiresIn : null]);
                    return;
                } catch (\PDOException $e) {
                    if (!$this->isFullException($e)) {
                        throw $e;
                    }
                }
                $this->makeSpace();
            }
        });
    }
    private function isFullException(\PDOException $e) : bool
    {
        return preg_match('~13 database or disk is full~', $e->getMessage());
    }
    private function serialize($value) : string
    {
        // gzcompress is always used since it adds a checksum to the data
        return gzcompress(serialize($value));
    }
    private function unserialize(string $value, &$result) : bool
    {
        $value = @gzuncompress($value);
        if ($value === false) {
            return false;
        }
        if ($value === 'b:0;') {
            $result = false;
            return true;
        }
        $value = @unserialize($value);
        if ($value === false) {
            return false;
        }
        $result = $value;
        return true;
    }
    private function hashKey(string $key) : string
    {
        return sha1($key, true);
    }
    private function randomKey() : string
    {
        return random_bytes(strlen($this->hashKey('')));
    }
    private function getDatabase() : \PDO
    {
        if (!isset($this->database)) {
            @mkdir(dirname($this->getDatabasePath()), 0700, true);
            $this->checkDirOwner(dirname($this->getDatabasePath()));
            $database = new \Kibo\Phast\Cache\Sqlite\Connection('sqlite:' . $this->getDatabasePath());
            $database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $database->exec('PRAGMA journal_mode = TRUNCATE');
            $database->exec('PRAGMA synchronous = OFF');
            if (($maxPageCount = $this->getMaxPageCount($database)) !== null) {
                $database->exec(sprintf('PRAGMA max_page_count = %d', $maxPageCount));
            }
            $this->upgradeDatabase($database);
            $this->database = $database;
        }
        return $this->database;
    }
    private function checkDirOwner(string $dir) : void
    {
        $owner = fileowner($dir);
        if ($owner === false) {
            throw new \RuntimeException('Could not get owner of cache dir');
        }
        if (!function_exists('posix_geteuid')) {
            return;
        }
        if ($owner !== posix_geteuid()) {
            throw new \RuntimeException('Cache dir is owner by another user; this is not secure');
        }
    }
    private function getMaxPageCount(\Kibo\Phast\Cache\Sqlite\Connection $database) : ?int
    {
        return $this->maxSize / $database->getPageSize();
    }
    private function getDatabasePath() : string
    {
        return $this->cacheRoot . '/' . $this->name . '.sqlite3';
    }
    private function upgradeDatabase(\PDO $database) : void
    {
        // If the cache table is already created, there's nothing to do.
        if ($database->query("\n            SELECT 1\n            FROM sqlite_master\n            WHERE\n                type = 'table'\n                AND name = 'cache'\n        ")->fetchColumn()) {
            return;
        }
        try {
            $database->exec('BEGIN EXCLUSIVE');
            // After acquiring an exclusive lock, check for the table again;
            // it may have been created after the last check and before the lock.
            if ($database->query("\n                SELECT 1\n                FROM sqlite_master\n                WHERE\n                    type = 'table'\n                    AND name = 'cache'\n            ")->fetchColumn()) {
                return;
            }
            $database->exec('
                CREATE TABLE cache (
                    key BLOB PRIMARY KEY,
                    value BLOB NOT NULL,
                    expires_at INT
                ) WITHOUT ROWID
            ');
            $database->exec('COMMIT');
        } catch (\Throwable $e) {
            $database->exec('ROLLBACK');
            throw $e;
        }
    }
    private function autorecover(\Closure $fn)
    {
        try {
            return $fn();
        } catch (\PDOException $e) {
            if (!$this->autorecover) {
                throw $e;
            }
        } catch (\RuntimeException $e) {
            if (!$this->autorecover) {
                throw $e;
            }
            $this->logger()->error('Caught {exceptionClass} during cache operation: {message}; ignoring it', ['exceptionClass' => get_class($e), 'message' => $e->getMessage()]);
            return null;
        }
        $this->logger()->error('Caught {exceptionClass} during cache operation: {message}; retrying operation', ['exceptionClass' => get_class($e), 'message' => $e->getMessage()]);
        $this->database = null;
        $this->purge();
        return $fn();
    }
    private function purge() : void
    {
        @unlink($this->getDatabasePath());
    }
    private function makeSpace() : void
    {
        $selectQuery = $this->getDatabase()->prepare('
            SELECT key, LENGTH(key) + LENGTH(value) + LENGTH(expires_at) AS length
            FROM cache
            WHERE key >= :key
            ORDER BY key
            LIMIT 1
        ');
        $deleteQuery = $this->getDatabase()->prepare('
            DELETE FROM cache
            WHERE key = :key
        ');
        $this->getDatabase()->exec('BEGIN IMMEDIATE');
        try {
            for ($i = 0; $i < 100; $i++) {
                $selectQuery->execute(['key' => $this->randomKey()]);
                if (!($row = $selectQuery->fetch(\PDO::FETCH_ASSOC))) {
                    $selectQuery->execute(['key' => '']);
                    if (!($row = $selectQuery->fetch(\PDO::FETCH_ASSOC))) {
                        return;
                    }
                }
                $deleteQuery->execute(['key' => $row['key']]);
            }
            $this->getDatabase()->exec('COMMIT');
        } catch (\Throwable $e) {
            $this->getDatabase()->exec('ROLLBACK');
            throw $e;
        }
    }
}
namespace Kibo\Phast\Common;

class Base64url
{
    public static function encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    public static function decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    public static function shortHash($data)
    {
        return self::encode(substr(sha1($data, true), 0, 8));
    }
}
namespace Kibo\Phast\Common;

class JSON
{
    public static function encode($value)
    {
        return self::_encode($value, 0);
    }
    public static function prettyEncode($value)
    {
        return self::_encode($value, JSON_PRETTY_PRINT);
    }
    private static function _encode($value, $flags)
    {
        $flags |= JSON_UNESCAPED_SLASHES;
        if (version_compare(PHP_VERSION, '7.2.0', '<')) {
            return self::legacyEncode($value, $flags);
        }
        return json_encode($value, $flags | JSON_INVALID_UTF8_IGNORE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    private static function legacyEncode($value, $flags)
    {
        $result = json_encode($value, $flags);
        if ($result !== false || json_last_error() !== JSON_ERROR_UTF8) {
            return $result;
        }
        self::cleanUTF8($value);
        return json_encode($value, $flags | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    private static function cleanUTF8(&$value)
    {
        if (is_array($value)) {
            array_walk_recursive($value, __METHOD__);
        } elseif (is_string($value)) {
            $value = preg_replace_callback('~
                    [\\x00-\\x7F]++                      # ASCII
                  | [\\xC2-\\xDF][\\x80-\\xBF]             # non-overlong 2-byte
                  |  \\xE0[\\xA0-\\xBF][\\x80-\\xBF]        # excluding overlongs
                  | [\\xE1-\\xEC\\xEE\\xEF][\\x80-\\xBF]{2}  # straight 3-byte
                  |  \\xED[\\x80-\\x9F][\\x80-\\xBF]        # excluding surrogates
                  |  \\xF0[\\x90-\\xBF][\\x80-\\xBF]{2}     # planes 1-3
                  | [\\xF1-\\xF3][\\x80-\\xBF]{3}          # planes 4-15
                  |  \\xF4[\\x80-\\x8F][\\x80-\\xBF]{2}     # plane 16
                  | (.)
                ~xs', function ($match) {
                if (isset($match[1]) && strlen($match[1])) {
                    return '';
                }
                return $match[0];
            }, $value);
        }
    }
}
namespace Kibo\Phast\Common;

class ObjectifiedFunctions
{
    /**
     * @param string $name
     * @param array $arguments
     */
    public function __call($name, array $arguments)
    {
        if (isset($this->{$name}) && is_callable($this->{$name})) {
            $fn = $this->{$name};
            return $fn(...$arguments);
        }
        if (function_exists($name)) {
            return $name(...$arguments);
        }
        throw new \Kibo\Phast\Exceptions\UndefinedObjectifiedFunction("Undefined objectified function {$name}");
    }
}
namespace Kibo\Phast\Common;

class OutputBufferHandler
{
    use \Kibo\Phast\Logging\LoggingTrait;
    const START_PATTERN = '~
        (
            \\s*+ <!doctype\\s++html\\b[^<>]*> |
            \\s*+ <html\\b[^<>]*> |
            \\s*+ <head> |
            \\s*+ <!--.*?-->
        )++
    ~xsiA';
    private $filterCb;
    /**
     * @var ?string
     */
    private $buffer = '';
    private $offset = 0;
    /**
     * @var integer
     */
    private $maxBufferSizeToApply;
    private $canceled = false;
    public function __construct($maxBufferSizeToApply, callable $filterCb)
    {
        $this->maxBufferSizeToApply = $maxBufferSizeToApply;
        $this->filterCb = $filterCb;
    }
    public function install()
    {
        $ignoreHandlers = ['default output handler', 'ob_gzhandler'];
        if (!array_diff(ob_list_handlers(), $ignoreHandlers)) {
            while (ob_get_level() && @ob_end_clean()) {
            }
        }
        ob_start([$this, 'handleChunk'], 2);
        ob_implicit_flush(1);
    }
    public function handleChunk($chunk, $phase)
    {
        if ($this->buffer === null) {
            return $chunk;
        }
        $this->buffer .= $chunk;
        if ($this->canceled) {
            return $this->stop();
        }
        if (strlen($this->buffer) > $this->maxBufferSizeToApply) {
            $this->logger()->info('Buffer exceeds max. size ({buffersize} bytes). Not applying', ['buffersize' => $this->maxBufferSizeToApply]);
            return $this->stop();
        }
        $output = '';
        if (preg_match(self::START_PATTERN, $this->buffer, $match, 0, $this->offset)) {
            $this->offset += strlen($match[0]);
            $output .= $match[0];
        }
        if ($phase & PHP_OUTPUT_HANDLER_FINAL) {
            $output .= $this->finalize();
        }
        if ($output !== '') {
            @header_remove('Content-Length');
        }
        return $output;
    }
    private function finalize()
    {
        $input = substr($this->buffer, $this->offset);
        $result = call_user_func($this->filterCb, $input, $this->buffer);
        $this->buffer = null;
        return $result;
    }
    private function stop()
    {
        $output = $this->buffer;
        $this->buffer = null;
        return $output;
    }
    public function cancel()
    {
        $this->canceled = true;
    }
}
namespace Kibo\Phast\Common;

class System
{
    private $functions;
    public function __construct(\Kibo\Phast\Common\ObjectifiedFunctions $functions = null)
    {
        if ($functions === null) {
            $functions = new \Kibo\Phast\Common\ObjectifiedFunctions();
        }
        $this->functions = $functions;
    }
    public function getUserId()
    {
        try {
            return (int) $this->functions->posix_geteuid();
        } catch (\Kibo\Phast\Exceptions\UndefinedObjectifiedFunction $e) {
            return 0;
        }
    }
}
namespace Kibo\Phast\Diagnostics;

interface Diagnostics
{
    /**
     * @param array $config
     */
    public function diagnose(array $config);
}
namespace Kibo\Phast\Diagnostics;

class Status implements \JsonSerializable
{
    /**
     * @var Package
     */
    private $package;
    /**
     * @var bool
     */
    private $available;
    /**
     * @var string
     */
    private $reason;
    /**
     * @var bool
     */
    private $enabled;
    /**
     * Status constructor.
     * @param Package $package
     * @param bool $available
     * @param string $reason
     * @param bool $enabled
     */
    public function __construct(\Kibo\Phast\Environment\Package $package, $available, $reason, $enabled)
    {
        $this->package = $package;
        $this->available = $available;
        $this->reason = $reason;
        $this->enabled = $enabled;
    }
    /**
     * @return Package
     */
    public function getPackage()
    {
        return $this->package;
    }
    /**
     * @return bool
     */
    public function isAvailable()
    {
        return $this->available;
    }
    /**
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }
    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }
    public function toArray() : array
    {
        return ['package' => ['type' => $this->package->getType(), 'name' => $this->package->getNamespace()], 'available' => $this->available, 'reason' => $this->reason, 'enabled' => $this->enabled];
    }
    public function jsonSerialize() : array
    {
        return $this->toArray();
    }
}
namespace Kibo\Phast\Diagnostics;

class SystemDiagnostics
{
    /**
     * @param array $userConfigArr
     * @return Status[]
     */
    public function run(array $userConfigArr)
    {
        $results = [];
        $userConfig = new \Kibo\Phast\Environment\Configuration($userConfigArr);
        $config = \Kibo\Phast\Environment\Configuration::fromDefaults()->withUserConfiguration($userConfig);
        foreach ($this->getExaminedItems($config) as $type => $group) {
            foreach ($group['items'] as $name) {
                $enabled = call_user_func($group['enabled'], $name);
                $package = \Kibo\Phast\Environment\Package::fromPackageClass($name, $type);
                try {
                    $diagnostic = $package->getDiagnostics();
                    $diagnostic->diagnose($config->toArray());
                    $results[] = new \Kibo\Phast\Diagnostics\Status($package, true, '', $enabled);
                } catch (\Kibo\Phast\Environment\Exceptions\PackageHasNoDiagnosticsException $e) {
                    $results[] = new \Kibo\Phast\Diagnostics\Status($package, true, '', $enabled);
                } catch (\Kibo\Phast\Exceptions\RuntimeException $e) {
                    $results[] = new \Kibo\Phast\Diagnostics\Status($package, false, $e->getMessage(), $enabled);
                } catch (\Exception $e) {
                    $results[] = new \Kibo\Phast\Diagnostics\Status($package, false, sprintf('Unknown error: Exception: %s, Message: %s, Code: %s', get_class($e), $e->getMessage(), $e->getCode()), $enabled);
                }
            }
        }
        return $results;
    }
    private function getExaminedItems(\Kibo\Phast\Environment\Configuration $config)
    {
        $runtimeConfig = $config->getRuntimeConfig()->toArray();
        $configArr = $config->toArray();
        return ['HTMLFilter' => ['items' => array_keys($configArr['documents']['filters']), 'enabled' => function ($filter) use($runtimeConfig) {
            return isset($runtimeConfig['documents']['filters'][$filter]);
        }], 'ImageFilter' => ['items' => array_keys($configArr['images']['filters']), 'enabled' => function ($filter) use($runtimeConfig) {
            return isset($runtimeConfig['images']['filters'][$filter]);
        }], 'Cache' => ['items' => [\Kibo\Phast\Cache\Sqlite\Cache::class], 'enabled' => function () {
            return true;
        }]];
    }
}
namespace Kibo\Phast\Environment;

class Configuration
{
    /**
     * @var array
     */
    private $sourceConfig;
    /**
     * @var Switches
     */
    private $switches;
    /**
     * @return Configuration
     */
    public static function fromDefaults()
    {
        return new self(\Kibo\Phast\Environment\DefaultConfiguration::get());
    }
    /**
     * Configuration constructor.
     * @param array $sourceConfig
     */
    public function __construct(array $sourceConfig)
    {
        $this->sourceConfig = $sourceConfig;
        if (!isset($this->sourceConfig['switches'])) {
            $this->switches = new \Kibo\Phast\Environment\Switches();
        } else {
            $this->switches = \Kibo\Phast\Environment\Switches::fromArray($this->sourceConfig['switches']);
        }
    }
    /**
     * @param Configuration $config
     * @return $this
     */
    public function withUserConfiguration(\Kibo\Phast\Environment\Configuration $config)
    {
        $result = $this->recursiveMerge($this->sourceConfig, $config->sourceConfig);
        return new self($result);
    }
    public function withServiceRequest(\Kibo\Phast\Services\ServiceRequest $request)
    {
        $clone = clone $this;
        $clone->switches = $this->switches->merge($request->getSwitches());
        return $clone;
    }
    public function getRuntimeConfig()
    {
        $config = $this->sourceConfig;
        $switchables = [&$config['documents']['filters'], &$config['images']['filters'], &$config['logging']['logWriters'], &$config['styles']['filters']];
        foreach ($switchables as &$switchable) {
            if (!is_array($switchable)) {
                continue;
            }
            $switchable = array_filter($switchable, function ($item) {
                if (!isset($item['enabled'])) {
                    return true;
                }
                if ($item['enabled'] === false) {
                    return false;
                }
                return $this->switches->isOn($item['enabled']);
            });
        }
        if (isset($config['images']['enable-cache']) && is_string($config['images']['enable-cache'])) {
            $config['images']['enable-cache'] = $this->switches->isOn($config['images']['enable-cache']);
        }
        $config['switches'] = $this->switches->toArray();
        return new \Kibo\Phast\Environment\Configuration($config);
    }
    public function toArray()
    {
        return $this->sourceConfig;
    }
    private function recursiveMerge(array $a1, array $a2)
    {
        foreach ($a2 as $key => $value) {
            if (isset($a1[$key]) && is_array($a1[$key]) && is_array($value)) {
                $a1[$key] = $this->recursiveMerge($a1[$key], $value);
            } elseif (is_string($key)) {
                $a1[$key] = $value;
            } else {
                $a1[] = $value;
            }
        }
        return $a1;
    }
}
namespace Kibo\Phast\Environment;

class DefaultConfiguration
{
    public static function get()
    {
        $request = \Kibo\Phast\HTTP\Request::fromGlobals();
        return ['securityToken' => null, 'retrieverMap' => [$request->getHost() => $request->getDocumentRoot()], 'httpClient' => \Kibo\Phast\HTTP\CURLClient::class, 'cache' => ['cacheRoot' => sys_get_temp_dir() . '/phast-cache-' . (new \Kibo\Phast\Common\System())->getUserId(), 'maxSize' => 1024 * 1024 * 1024], 'servicesUrl' => '/phast.php', 'serviceRequestFormat' => \Kibo\Phast\Services\ServiceRequest::FORMAT_PATH, 'compressServiceResponse' => true, 'optimizeHTMLDocumentsOnly' => true, 'optimizeJSONResponses' => false, 'outputServerSideStats' => true, 'documents' => ['maxBufferSizeToApply' => 2 * 1024 * 1024, 'baseUrl' => $request->getAbsoluteURI(), 'filters' => [\Kibo\Phast\Filters\HTML\CommentsRemoval\Filter::class => [], \Kibo\Phast\Filters\HTML\MetaCharset\Filter::class => [], \Kibo\Phast\Filters\HTML\Minify\Filter::class => [], \Kibo\Phast\Filters\HTML\MinifyScripts\Filter::class => [], \Kibo\Phast\Filters\HTML\BaseURLSetter\Filter::class => [], \Kibo\Phast\Filters\HTML\ImagesOptimizationService\Tags\Filter::class => [], \Kibo\Phast\Filters\HTML\LazyImageLoading\Filter::class => [], \Kibo\Phast\Filters\HTML\CSSInlining\Filter::class => ['optimizerSizeDiffThreshold' => 1024, 'whitelist' => ['~^https?://fonts\\.googleapis\\.com/~' => ['ieCompatible' => false], '~^https?://ajax\\.googleapis\\.com/ajax/libs/jqueryui/~', '~^https?://maxcdn\\.bootstrapcdn\\.com/[^?#]*\\.css~', '~^https?://idangero\\.us/~', '~^https?://[^/]*\\.github\\.io/~', '~^https?://\\w+\\.typekit\\.net/~' => ['ieCompatible' => false], '~^https?://stackpath\\.bootstrapcdn\\.com/~', '~^https?://cdnjs\\.cloudflare\\.com/~']], \Kibo\Phast\Filters\HTML\ImagesOptimizationService\CSS\Filter::class => [], \Kibo\Phast\Filters\HTML\DelayedIFrameLoading\Filter::class => [], \Kibo\Phast\Filters\HTML\ScriptsProxyService\Filter::class => ['urlRefreshTime' => 7200], \Kibo\Phast\Filters\HTML\Diagnostics\Filter::class => ['enabled' => 'diagnostics'], \Kibo\Phast\Filters\HTML\ScriptsDeferring\Filter::class => [], \Kibo\Phast\Filters\HTML\PhastScriptsCompiler\Filter::class => []]], 'images' => ['enable-cache' => 'imgcache', 'api-mode' => false, 'factory' => \Kibo\Phast\Filters\Image\ImageFactory::class, 'maxImageInliningSize' => 512, 'whitelist' => ['~^https?://ajax\\.googleapis\\.com/ajax/libs/jqueryui/~'], 'filters' => [\Kibo\Phast\Filters\Image\ImageAPIClient\Filter::class => ['api-url' => 'https://optimize.phast.io/?service=images', 'host-name' => $request->getHost(), 'request-uri' => $request->getURI(), 'plugin-version' => 'phast-core-1.0']]], 'styles' => ['filters' => [\Kibo\Phast\Filters\Text\Decode\Filter::class => [], \Kibo\Phast\Filters\CSS\ImportsStripper\Filter::class => [], \Kibo\Phast\Filters\CSS\CSSMinifier\Filter::class => [], \Kibo\Phast\Filters\CSS\CSSURLRewriter\Filter::class => [], \Kibo\Phast\Filters\CSS\ImageURLRewriter\Filter::class => ['maxImageInliningSize' => 512], \Kibo\Phast\Filters\CSS\FontSwap\Filter::class => []]], 'logging' => ['logWriters' => [['class' => \Kibo\Phast\Logging\LogWriters\PHPError\Writer::class, 'levelMask' => \Kibo\Phast\Logging\LogLevel::EMERGENCY | \Kibo\Phast\Logging\LogLevel::ALERT | \Kibo\Phast\Logging\LogLevel::CRITICAL | \Kibo\Phast\Logging\LogLevel::ERROR | \Kibo\Phast\Logging\LogLevel::WARNING], ['enabled' => 'diagnostics', 'class' => \Kibo\Phast\Logging\LogWriters\JSONLFile\Writer::class, 'logRoot' => sys_get_temp_dir() . '/phast-logs']]], 'switches' => ['phast' => true, 'diagnostics' => false], 'scripts' => ['removeLicenseHeaders' => false, 'whitelist' => ['~^https?://' . preg_quote($request->getHost(), '~') . '/~']], 'csp' => ['nonce' => null, 'reportOnly' => false, 'reportUri' => null]];
    }
}
namespace Kibo\Phast\Environment;

class Package
{
    /**
     * @var string
     */
    protected $type;
    /**
     * @var string
     */
    protected $namespace;
    /**
     * @param $className
     * @param string|null $type
     * @return Package
     */
    public static function fromPackageClass($className, $type = null)
    {
        $instance = new self();
        $lastSeparatorPosition = strrpos($className, '\\');
        $instance->type = empty($type) ? substr($className, $lastSeparatorPosition + 1) : $type;
        $instance->namespace = substr($className, 0, $lastSeparatorPosition);
        return $instance;
    }
    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }
    /**
     * @return bool
     */
    public function hasFactory()
    {
        return $this->classExists($this->getFactoryClassName());
    }
    /**
     * @return mixed
     */
    public function getFactory()
    {
        if ($this->hasFactory()) {
            $class = $this->getFactoryClassName();
            return new $class();
        }
        throw new \Kibo\Phast\Environment\Exceptions\PackageHasNoFactoryException("Package {$this->namespace} has no factory");
    }
    /**
     * @return bool
     */
    public function hasDiagnostics()
    {
        return $this->classExists($this->getDiagnosticsClassName());
    }
    /**
     * @return Diagnostics
     */
    public function getDiagnostics()
    {
        if ($this->hasDiagnostics()) {
            $class = $this->getDiagnosticsClassName();
            return new $class();
        }
        throw new \Kibo\Phast\Environment\Exceptions\PackageHasNoDiagnosticsException("Package {$this->namespace} has no diagnostics");
    }
    private function getFactoryClassName()
    {
        return $this->getClassName('Factory');
    }
    private function getDiagnosticsClassName()
    {
        return $this->getClassName('Diagnostics');
    }
    private function getClassName($class)
    {
        return $this->namespace . '\\' . $class;
    }
    private function classExists($class)
    {
        // Don't trigger any autoloaders if Phast has been compiled into a
        // single file, and avoid triggering Magento code generation.
        $useAutoloader = basename(__FILE__) == 'Package.php';
        return class_exists($class, $useAutoloader);
    }
}
namespace Kibo\Phast\Environment;

class Switches
{
    const SWITCH_PHAST = 'phast';
    const SWITCH_DIAGNOSTICS = 'diagnostics';
    private static $defaults = [self::SWITCH_PHAST => true, self::SWITCH_DIAGNOSTICS => false];
    private $switches = [];
    public static function fromArray(array $switches)
    {
        $instance = new self();
        $instance->switches = array_merge($instance->switches, $switches);
        return $instance;
    }
    public static function fromString($switches)
    {
        $instance = new self();
        if (empty($switches)) {
            return $instance;
        }
        foreach (explode(',', $switches) as $switch) {
            if ($switch[0] == '-') {
                $instance->switches[substr($switch, 1)] = false;
            } else {
                $instance->switches[$switch] = true;
            }
        }
        return $instance;
    }
    public function merge(\Kibo\Phast\Environment\Switches $switches)
    {
        $instance = new self();
        $instance->switches = array_merge($this->switches, $switches->switches);
        return $instance;
    }
    public function isOn($switch)
    {
        if (isset($this->switches[$switch])) {
            return (bool) $this->switches[$switch];
        }
        if (isset(self::$defaults[$switch])) {
            return (bool) self::$defaults[$switch];
        }
        return true;
    }
    public function toArray()
    {
        return array_merge(self::$defaults, $this->switches);
    }
}
namespace Kibo\Phast\Exceptions;

class CachedExceptionException extends \Exception
{
}
namespace Kibo\Phast\Exceptions;

class ItemNotFoundException extends \Exception
{
    /**
     * @var URL
     */
    private $url;
    public function __construct($message = '', $code = 0, \Throwable $previous = null, \Kibo\Phast\ValueObjects\URL $failed = null)
    {
        parent::__construct($message, $code, $previous);
        $this->url = $failed;
    }
    /**
     * @return URL
     */
    public function getUrl()
    {
        return $this->url;
    }
}
namespace Kibo\Phast\Exceptions;

class LogicException extends \LogicException
{
}
namespace Kibo\Phast\Exceptions;

class RuntimeException extends \RuntimeException
{
}
namespace Kibo\Phast\Exceptions;

class UnauthorizedException extends \Exception
{
}
namespace Kibo\Phast\Exceptions;

class UndefinedObjectifiedFunction extends \RuntimeException
{
}
namespace Kibo\Phast\Filters\CSS\CSSMinifier;

class Factory
{
    public function make()
    {
        return new \Kibo\Phast\Filters\CSS\CSSMinifier\Filter();
    }
}
namespace Kibo\Phast\Filters\CSS\CSSURLRewriter;

class Factory
{
    public function make()
    {
        return new \Kibo\Phast\Filters\CSS\CSSURLRewriter\Filter();
    }
}
namespace Kibo\Phast\Filters\CSS\Composite;

class Factory
{
    /**
     * @param array $config
     * @return Filter
     */
    public function make(array $config)
    {
        $class = \Kibo\Phast\Filters\HTML\ImagesOptimizationService\CSS\Filter::class;
        if (isset($config['documents']['filters'][$class]['serviceUrl'])) {
            $serviceUrl = $config['documents']['filters'][$class]['serviceUrl'];
        } else {
            $serviceUrl = $config['servicesUrl'] . '?service=images';
        }
        $filter = new \Kibo\Phast\Filters\CSS\Composite\Filter($serviceUrl);
        foreach (array_keys($config['styles']['filters']) as $filterClass) {
            $filter->addFilter(\Kibo\Phast\Environment\Package::fromPackageClass($filterClass)->getFactory()->make($config));
        }
        return $filter;
    }
}
namespace Kibo\Phast\Filters\CSS\FontSwap;

class Factory
{
    public function make()
    {
        return new \Kibo\Phast\Filters\CSS\FontSwap\Filter();
    }
}
namespace Kibo\Phast\Filters\CSS\ImageURLRewriter;

class Factory
{
    public function make(array $config)
    {
        return new \Kibo\Phast\Filters\CSS\ImageURLRewriter\Filter((new \Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageURLRewriterFactory())->make($config, \Kibo\Phast\Filters\CSS\ImageURLRewriter\Filter::class));
    }
}
namespace Kibo\Phast\Filters\CSS\ImportsStripper;

class Factory
{
    public function make()
    {
        return new \Kibo\Phast\Filters\CSS\ImportsStripper\Filter();
    }
}
namespace Kibo\Phast\Filters\HTML;

interface AMPCompatibleFilter
{
}
namespace Kibo\Phast\Filters\HTML\CSSInlining;

class Optimizer
{
    private $classNamePattern = '-?[_a-zA-Z]++[_a-zA-Z0-9-]*+';
    /**
     * @var array
     */
    private $usedClasses;
    /**
     * @var Cache
     */
    private $cache;
    public function __construct(\Traversable $elements, \Kibo\Phast\Cache\Cache $cache)
    {
        $this->usedClasses = $this->getUsedClasses($elements);
        $this->cache = $cache;
    }
    public function optimizeCSS($css)
    {
        $stylesheet = $this->cache->get(md5($css), function () use($css) {
            return $this->parseCSS($css);
        });
        if ($stylesheet === null) {
            return;
        }
        $output = '';
        $selectors = null;
        foreach ($stylesheet as $element) {
            if (is_array($element)) {
                if ($selectors === null) {
                    $selectors = [];
                }
                foreach ($element as $i => $class) {
                    if ($i !== 0 && !isset($this->usedClasses[$class])) {
                        continue 2;
                    }
                }
                $selectors[] = $element[0];
            } elseif ($selectors !== null) {
                if (isset($selectors[0])) {
                    $output .= implode(',', $selectors) . $element;
                }
                $selectors = null;
            } else {
                $output .= $element;
            }
        }
        $output = $this->removeEmptyMediaQueries($output);
        return trim($output);
    }
    /**
     * Parse a stylesheet into an array of segments
     *
     * Each string segment is preceded by zero or more arrays encoding selectors
     * parsed by parseSelector (see below).
     *
     * @param $css
     * @return array|void
     */
    private function parseCSS($css)
    {
        $re_simple_selector_chars = "[A-Z0-9_.#*:>+\\~\\s-]";
        $re_selector = "(?: {$re_simple_selector_chars} | \\[[a-z]++\\] )++";
        $re_rule = "~\n            (?<= ^ | [;{}] ) \\s*+\n            ( (?: {$re_selector} , )*+ {$re_selector} )\n            ( { [^}]*+ } )\n        ~xi";
        if (preg_match_all($re_rule, $css, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            // This is an error condition
            return;
        }
        $offset = 0;
        $stylesheet = [];
        foreach ($matches as $match) {
            $selectors = $this->parseSelectors($match[1][0]);
            if ($selectors === null) {
                continue;
            }
            if ($match[0][1] > $offset) {
                $stylesheet[] = substr($css, $offset, $match[0][1] - $offset);
            }
            foreach ($selectors as $selector) {
                $stylesheet[] = $selector;
            }
            $stylesheet[] = $match[2][0];
            $offset = $match[0][1] + strlen($match[0][0]);
        }
        if ($offset < strlen($css)) {
            $stylesheet[] = substr($css, $offset);
        }
        return $stylesheet;
    }
    /**
     * Parse the selector part of a CSS rule into an array of selectors.
     *
     * Each selector will be an array with at offset 0, the string contents of
     * the selector. The rest of the array will be the class names (if any) that
     * must be present in the document for this selector to match.
     *
     * Null is returned if none of the selectors use classes, and can therefore
     * not be optimized.
     *
     * @param string $selectors
     * @return array|void
     */
    private function parseSelectors($selectors)
    {
        $newSelectors = [];
        $anyClasses = false;
        foreach (explode(',', $selectors) as $selector) {
            $classes = [$selector];
            if (preg_match_all("~\\.({$this->classNamePattern})~", $selector, $matches)) {
                foreach ($matches[1] as $class) {
                    $classes[] = $class;
                    $anyClasses = true;
                }
            }
            $newSelectors[] = $classes;
        }
        if (!$anyClasses) {
            return;
        }
        return $newSelectors;
    }
    private function getUsedClasses(\Traversable $elements)
    {
        $classes = [];
        /** @var Tag $tag */
        foreach ($elements as $tag) {
            if (!$tag instanceof \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag) {
                continue;
            }
            $classAttr = $tag->getAttribute('class');
            if ($classAttr === null) {
                continue;
            }
            foreach (preg_split('/\\s+/', $classAttr) as $cls) {
                if ($cls != '' && !isset($classes[$cls]) && preg_match("/^{$this->classNamePattern}\$/", $cls)) {
                    $classes[$cls] = true;
                }
            }
        }
        return $classes;
    }
    private function removeEmptyMediaQueries($css)
    {
        return preg_replace('~@media\\s++[A-Z0-9():,\\s-]++\\s*+{}~i', '', $css);
    }
}
namespace Kibo\Phast\Filters\HTML\CSSInlining;

class OptimizerFactory
{
    /**
     * @var Cache
     */
    private $cache;
    public function __construct(array $config)
    {
        $this->cache = new \Kibo\Phast\Cache\Sqlite\Cache($config['cache'], 'css-optimizitor');
    }
    /**
     * @param \Traversable $elements
     * @return Optimizer
     */
    public function makeForElements(\Traversable $elements)
    {
        return new \Kibo\Phast\Filters\HTML\CSSInlining\Optimizer($elements, $this->cache);
    }
}
namespace Kibo\Phast\Filters\HTML\Composite;

class Factory
{
    use \Kibo\Phast\Logging\LoggingTrait;
    public function make(array $config)
    {
        $composite = new \Kibo\Phast\Filters\HTML\Composite\Filter(\Kibo\Phast\ValueObjects\URL::fromString($config['documents']['baseUrl']), $config['outputServerSideStats']);
        foreach (array_keys($config['documents']['filters']) as $class) {
            $package = \Kibo\Phast\Environment\Package::fromPackageClass($class);
            if ($package->hasFactory()) {
                $filter = $package->getFactory()->make($config);
            } elseif (!class_exists($class)) {
                $this->logger(__METHOD__, __LINE__)->error("Skipping non-existent filter class: {$class}");
                continue;
            } else {
                $filter = new $class();
            }
            $composite->addHTMLFilter($filter);
        }
        return $composite;
    }
}
namespace Kibo\Phast\Filters\HTML\Composite;

class Filter
{
    use \Kibo\Phast\Logging\LoggingTrait;
    /**
     * @var URL
     */
    private $baseUrl;
    private $outputStats;
    /**
     * @var HTMLStreamFilter[]
     */
    private $filters = [];
    private $timings = [];
    /**
     * Filter constructor.
     * @param URL $baseUrl
     * @param $outputStats
     */
    public function __construct(\Kibo\Phast\ValueObjects\URL $baseUrl, $outputStats)
    {
        $this->baseUrl = $baseUrl;
        $this->outputStats = $outputStats;
    }
    /**
     * @param string $buffer
     * @return string
     */
    public function apply($buffer)
    {
        $timeStart = microtime(true);
        try {
            return $this->tryToApply($buffer, $timeStart);
        } catch (\Exception $e) {
            $this->logger()->critical('Phast: CompositeHTMLFilter: {exception} Msg: {message}, Code: {code}, File: {file}, Line: {line}', ['exception' => get_class($e), 'message' => $e->getMessage(), 'code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return $buffer;
        }
    }
    public function addHTMLFilter(\Kibo\Phast\Filters\HTML\HTMLStreamFilter $filter)
    {
        $this->filters[] = $filter;
    }
    private function tryToApply($buffer, $timeStart)
    {
        $context = new \Kibo\Phast\Filters\HTML\HTMLPageContext($this->baseUrl);
        $elements = (new \Kibo\Phast\Parsing\HTML\PCRETokenizer())->tokenize($buffer);
        foreach ($this->filters as $filter) {
            $this->logger()->info('Starting {filter}', ['filter' => get_class($filter)]);
            $elements = $filter->transformElements($elements, $context);
        }
        $output = '';
        foreach ($elements as $element) {
            $output .= $element;
        }
        $timeDelta = microtime(true) - $timeStart;
        if ($this->outputStats) {
            $output .= sprintf("\n<!-- [Phast] Document optimized in %dms -->\n", $timeDelta * 1000);
        }
        return $output;
    }
    public function selectFilters($callback)
    {
        $this->filters = array_filter($this->filters, $callback);
    }
}
namespace Kibo\Phast\Filters\HTML;

interface HTMLFilterFactory
{
    /**
     * @param array $config
     * @return HTMLStreamFilter
     */
    public function make(array $config);
}
namespace Kibo\Phast\Filters\HTML;

class HTMLPageContext
{
    /**
     * @var URL
     */
    private $baseUrl;
    /**
     * @var PhastJavaScript[]
     */
    private $phastJavaScripts = [];
    /**
     * HTMLPageContext constructor.
     * @param URL $baseUrl
     */
    public function __construct(\Kibo\Phast\ValueObjects\URL $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }
    /**
     * @param URL $baseUrl
     */
    public function setBaseUrl(\Kibo\Phast\ValueObjects\URL $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }
    /**
     * @return URL
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }
    /**
     * @param PhastJavaScript $script
     */
    public function addPhastJavascript(\Kibo\Phast\ValueObjects\PhastJavaScript $script)
    {
        $this->phastJavaScripts[] = $script;
    }
    /**
     * @return PhastJavaScript[]
     */
    public function getPhastJavaScripts()
    {
        return $this->phastJavaScripts;
    }
}
namespace Kibo\Phast\Filters\HTML;

interface HTMLStreamFilter
{
    /**
     * @param \Traversable $elements
     * @param HTMLPageContext $context
     * @return \Traversable
     */
    public function transformElements(\Traversable $elements, \Kibo\Phast\Filters\HTML\HTMLPageContext $context);
}
namespace Kibo\Phast\Filters\HTML\Helpers;

trait JSDetectorTrait
{
    /**
     * @param Tag $element
     * @return bool
     */
    private function isJSElement(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $element)
    {
        if (!$element->hasAttribute('type')) {
            return true;
        }
        return (bool) preg_match('~^(text|application)/javascript(;|$)~i', $element->getAttribute('type'));
    }
}
namespace Kibo\Phast\Filters\HTML\ImagesOptimizationService\CSS;

class Factory implements \Kibo\Phast\Filters\HTML\HTMLFilterFactory
{
    public function make(array $config)
    {
        return new \Kibo\Phast\Filters\HTML\ImagesOptimizationService\CSS\Filter((new \Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageURLRewriterFactory())->make($config, \Kibo\Phast\Filters\HTML\ImagesOptimizationService\CSS\Filter::class));
    }
}
namespace Kibo\Phast\Filters\HTML\ImagesOptimizationService;

class ImageInliningManager
{
    use \Kibo\Phast\Logging\LoggingTrait;
    /**
     * @var Cache
     */
    private $cache;
    /**
     * @var int
     */
    private $maxImageInliningSize;
    /**
     * ImageInliningManager constructor.
     * @param Cache $cache
     * @param int $maxImageInliningSize
     */
    public function __construct(\Kibo\Phast\Cache\Cache $cache, $maxImageInliningSize)
    {
        $this->cache = $cache;
        $this->maxImageInliningSize = $maxImageInliningSize;
    }
    /**
     * @return int
     */
    public function getMaxImageInliningSize()
    {
        return $this->maxImageInliningSize;
    }
    /**
     * @param Resource $resource
     * @return string|null
     */
    public function getUrlForInlining(\Kibo\Phast\ValueObjects\Resource $resource)
    {
        if ($resource->getMimeType() !== 'image/svg+xml') {
            return $this->cache->get($this->getCacheKey($resource));
        }
        try {
            if ($this->hasSizeForInlining($resource)) {
                return $resource->toDataURL();
            }
        } catch (\Kibo\Phast\Exceptions\ItemNotFoundException $e) {
            $this->logger()->warning('Could not fetch contents for {url}. Message is {message}', ['url' => $resource->getUrl()->toString(), 'message' => $e->getMessage()]);
        }
        return null;
    }
    public function maybeStoreForInlining(\Kibo\Phast\ValueObjects\Resource $resource)
    {
        if ($this->shouldStoreForInlining($resource)) {
            $this->logger()->info('Storing {url} for inlining', ['url' => $resource->getUrl()->toString()]);
            $this->cache->set($this->getCacheKey($resource), $resource->toDataURL());
        } else {
            $this->logger()->info('Not storing {url} for inlining', ['url' => $resource->getUrl()->toString()]);
        }
    }
    private function shouldStoreForInlining(\Kibo\Phast\ValueObjects\Resource $resource)
    {
        return $this->hasSizeForInlining($resource) && strpos($resource->getMimeType(), 'image/') === 0 && $resource->getMimeType() !== 'image/webp';
    }
    private function hasSizeForInlining(\Kibo\Phast\ValueObjects\Resource $resource)
    {
        $size = $resource->getSize();
        return $size !== false && $size <= $this->maxImageInliningSize;
    }
    private function getCacheKey(\Kibo\Phast\ValueObjects\Resource $resource)
    {
        return $resource->getUrl()->toString() . '|' . $resource->getCacheSalt() . '|' . $this->maxImageInliningSize;
    }
}
namespace Kibo\Phast\Filters\HTML\ImagesOptimizationService;

class ImageInliningManagerFactory
{
    public function make(array $config)
    {
        $cache = new \Kibo\Phast\Cache\Sqlite\Cache($config['cache'], 'inline-images-1');
        return new \Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageInliningManager($cache, $config['images']['maxImageInliningSize']);
    }
}
namespace Kibo\Phast\Filters\HTML\ImagesOptimizationService;

class ImageURLRewriter
{
    use \Kibo\Phast\Logging\LoggingTrait;
    /**
     * @var ServiceSignature
     */
    protected $signature;
    /**
     * @var Retriever
     */
    protected $retriever;
    /**
     * @var ImageInliningManager
     */
    protected $inliningManager;
    /**
     * @var URL
     */
    protected $baseUrl;
    /**
     * @var URL
     */
    protected $serviceUrl;
    /**
     * @var string[]
     */
    protected $whitelist;
    /**
     * @var Resource[]
     */
    protected $inlinedResources;
    /**
     * ImageURLRewriter constructor.
     * @param ServiceSignature $signature
     * @param LocalRetriever $retriever
     * @param ImageInliningManager $inliningManager
     * @param URL $baseUrl
     * @param URL $serviceUrl
     * @param array $whitelist
     */
    public function __construct(\Kibo\Phast\Security\ServiceSignature $signature, \Kibo\Phast\Retrievers\LocalRetriever $retriever, \Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageInliningManager $inliningManager, \Kibo\Phast\ValueObjects\URL $baseUrl, \Kibo\Phast\ValueObjects\URL $serviceUrl, array $whitelist)
    {
        $this->signature = $signature;
        $this->retriever = $retriever;
        $this->inliningManager = $inliningManager;
        $this->baseUrl = $baseUrl;
        $this->serviceUrl = $serviceUrl;
        $this->whitelist = $whitelist;
    }
    /**
     * @param string $url
     * @param URL|null $baseUrl
     * @param array $params
     * @param bool $mustExist
     * @return string
     */
    public function rewriteUrl($url, \Kibo\Phast\ValueObjects\URL $baseUrl = null, array $params = [], $mustExist = false)
    {
        if (strpos($url, '#') === 0) {
            return $url;
        }
        $this->inlinedResources = [];
        $absolute = $this->makeURLAbsoluteToBase($url, $baseUrl);
        if (!$this->shouldRewriteUrl($absolute)) {
            return $url;
        }
        $resource = \Kibo\Phast\ValueObjects\Resource::makeWithRetriever($absolute, $this->retriever);
        if ($mustExist && $resource->getSize() === false) {
            return $url;
        }
        $dataUrl = $this->inliningManager->getUrlForInlining($resource);
        if ($dataUrl) {
            $this->inlinedResources = [$resource];
            return $dataUrl;
        }
        $params['src'] = $absolute->toString();
        return $this->makeSignedUrl($params);
    }
    /**
     * @param $styleContent
     * @return string
     */
    public function rewriteStyle($styleContent)
    {
        $allInlined = [];
        $result = preg_replace_callback('~
                (\\b (?: image | background ):)
                ([^;}]*)
            ~xiS', function ($match) use(&$allInlined) {
            return $match[1] . $this->rewriteStyleRule($match[2], $allInlined);
        }, $styleContent);
        $this->inlinedResources = array_values($allInlined);
        return $result;
    }
    private function rewriteStyleRule($ruleContent, &$allInlined)
    {
        return preg_replace_callback('~
                ( \\b url \\( [\'"]? )
                ( [^\'")] ++ )
            ~xiS', function ($match) use(&$allInlined) {
            $url = $match[1] . $this->rewriteUrl($match[2]);
            if (!empty($this->inlinedResources)) {
                $inlined = $this->inlinedResources[0];
                $allInlined[$inlined->getUrl()->toString()] = $inlined;
            }
            return $url;
        }, $ruleContent);
    }
    /**
     * @return Resource[]
     */
    public function getInlinedResources()
    {
        return $this->inlinedResources;
    }
    /**
     * @return string
     */
    public function getCacheSalt()
    {
        $parts = array_merge([$this->signature->getCacheSalt(), $this->baseUrl->toString(), $this->serviceUrl->toString(), $this->inliningManager->getMaxImageInliningSize(), '20180413'], array_keys($this->whitelist), array_values($this->whitelist));
        return join('-', $parts);
    }
    /**
     * @param string $url
     * @param URL|null $baseUrl
     * @return URL|null
     */
    private function makeURLAbsoluteToBase($url, \Kibo\Phast\ValueObjects\URL $baseUrl = null)
    {
        $url = trim($url);
        if (!$url || substr($url, 0, 5) === 'data:') {
            return null;
        }
        $this->logger()->info('Rewriting img {url}', ['url' => $url]);
        $baseUrl = is_null($baseUrl) ? $this->baseUrl : $baseUrl;
        return \Kibo\Phast\ValueObjects\URL::fromString($url)->withBase($baseUrl);
    }
    /**
     * @param string $url
     * @return bool
     */
    private function shouldRewriteUrl($url)
    {
        if (!$url) {
            return false;
        }
        foreach ($this->whitelist as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        $urlObject = \Kibo\Phast\ValueObjects\URL::fromString($url);
        if (preg_match('~\\.(jpe?g|gif|png)$~i', $urlObject->getPath()) && $this->retriever->getCacheSalt($urlObject)) {
            return true;
        }
        return false;
    }
    /**
     * @param array $params
     * @return string
     */
    private function makeSignedUrl(array $params)
    {
        $params['cacheMarker'] = $this->retriever->getCacheSalt(\Kibo\Phast\ValueObjects\URL::fromString($params['src']));
        return (new \Kibo\Phast\Services\ServiceRequest())->withParams($params)->withUrl($this->serviceUrl)->sign($this->signature)->serialize();
    }
}
namespace Kibo\Phast\Filters\HTML\ImagesOptimizationService;

class ImageURLRewriterFactory
{
    public function make(array $config, $filterClass = '')
    {
        $signature = (new \Kibo\Phast\Security\ServiceSignatureFactory())->make($config);
        if (isset($config['documents']['filters'][$filterClass])) {
            $classConfig = $config['documents']['filters'][$filterClass];
        } elseif (isset($config['styles']['filters'][$filterClass])) {
            $classConfig = $config['styles']['filters'][$filterClass];
        } else {
            $classConfig = [];
        }
        if (isset($classConfig['serviceUrl'])) {
            $serviceUrl = $classConfig['serviceUrl'];
        } else {
            $serviceUrl = $config['servicesUrl'] . '?service=images';
        }
        return new \Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageURLRewriter($signature, new \Kibo\Phast\Retrievers\LocalRetriever($config['retrieverMap']), (new \Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageInliningManagerFactory())->make($config), \Kibo\Phast\ValueObjects\URL::fromString($config['documents']['baseUrl']), \Kibo\Phast\ValueObjects\URL::fromString($serviceUrl), $config['images']['whitelist']);
    }
}
namespace Kibo\Phast\Filters\HTML\ImagesOptimizationService\Tags;

class Factory implements \Kibo\Phast\Filters\HTML\HTMLFilterFactory
{
    public function make(array $config)
    {
        return new \Kibo\Phast\Filters\HTML\ImagesOptimizationService\Tags\Filter((new \Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageURLRewriterFactory())->make($config, \Kibo\Phast\Filters\HTML\ImagesOptimizationService\Tags\Filter::class));
    }
}
namespace Kibo\Phast\Filters\HTML\ImagesOptimizationService\Tags;

class Filter implements \Kibo\Phast\Filters\HTML\HTMLStreamFilter, \Kibo\Phast\Filters\HTML\AMPCompatibleFilter
{
    const IMG_SRC_ATTR_PATTERN = '~^(|data-(|lazy-|wood-))src$~i';
    const IMG_SRCSET_ATTR_PATTERN = '~^(|data-(|lazy-|wood-))srcset$~i';
    /**
     * @var ImageURLRewriter
     */
    private $rewriter;
    private $inPictureTag = false;
    private $inBody = false;
    private $imagePathPattern;
    /**
     * Filter constructor.
     * @param ImageURLRewriter $rewriter
     */
    public function __construct(\Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageURLRewriter $rewriter)
    {
        $this->rewriter = $rewriter;
        $this->imagePathPattern = $this->makeImagePathPattern();
    }
    public function transformElements(\Traversable $elements, \Kibo\Phast\Filters\HTML\HTMLPageContext $context)
    {
        foreach ($elements as $element) {
            if ($element instanceof \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag) {
                $this->handleTag($element, $context);
            } elseif ($element instanceof \Kibo\Phast\Parsing\HTML\HTMLStreamElements\ClosingTag) {
                $this->handleClosingTag($element);
            }
            (yield $element);
        }
    }
    private function handleTag(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag, \Kibo\Phast\Filters\HTML\HTMLPageContext $context)
    {
        $isImage = false;
        if ($tag->getTagName() == 'img' || $this->inPictureTag && $tag->getTagName() == 'source' || $tag->getTagName() == 'amp-img') {
            $isImage = true;
        } elseif ($tag->getTagName() == 'picture') {
            $this->inPictureTag = true;
        } elseif ($tag->getTagName() == 'video' || $tag->getTagName() == 'audio') {
            $this->inPictureTag = false;
        } elseif ($tag->getTagName() == 'body') {
            $this->inBody = true;
        } elseif ($tag->getTagName() == 'meta') {
            return;
        }
        foreach ($tag->getAttributes() as $k => $v) {
            if (!$v) {
                continue;
            }
            if ($isImage && preg_match(self::IMG_SRC_ATTR_PATTERN, $k)) {
                $this->rewriteSrc($tag, $context, $k);
            } elseif ($isImage && preg_match(self::IMG_SRCSET_ATTR_PATTERN, $k)) {
                $this->rewriteSrcset($tag, $context, $k);
            } elseif ($this->inBody && is_string($path = parse_url($v, PHP_URL_PATH)) && preg_match($this->imagePathPattern, $path)) {
                $this->rewriteArbitraryAttribute($tag, $context, $k);
            }
        }
    }
    private function makeImagePathPattern()
    {
        $pieces = [];
        foreach (\Kibo\Phast\ValueObjects\Resource::EXTENSION_TO_MIME_TYPE as $ext => $mime) {
            if (strpos($mime, 'image/') === 0) {
                $pieces[] = preg_quote($ext, '~');
            }
        }
        return '~\\.(?:' . implode('|', $pieces) . ')$~';
    }
    private function handleClosingTag(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\ClosingTag $closingTag)
    {
        if ($closingTag->getTagName() == 'picture') {
            $this->inPictureTag = false;
        }
    }
    private function rewriteSrc(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $img, \Kibo\Phast\Filters\HTML\HTMLPageContext $context, $attribute)
    {
        $url = $img->getAttribute($attribute);
        if (preg_match('~(images|assets)/transparent\\.png~', $url) && $img->hasClass('rev-slidebg')) {
            return $url;
        }
        $newURL = $this->rewriter->rewriteUrl($url, $context->getBaseUrl());
        $img->setAttribute($attribute, $newURL);
    }
    private function rewriteSrcset(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $img, \Kibo\Phast\Filters\HTML\HTMLPageContext $context, $attribute)
    {
        $srcset = $img->getAttribute($attribute);
        $rewritten = preg_replace_callback('/([^,\\s]+)(\\s+(?:[^,]+))?/', function ($match) use($context) {
            $url = $this->rewriter->rewriteUrl($match[1], $context->getBaseUrl());
            if (isset($match[2])) {
                return $url . $match[2];
            }
            return $url;
        }, $srcset);
        $img->setAttribute($attribute, $rewritten);
    }
    private function rewriteArbitraryAttribute(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $element, \Kibo\Phast\Filters\HTML\HTMLPageContext $context, $attribute)
    {
        $url = $element->getAttribute($attribute);
        $newUrl = $this->rewriter->rewriteUrl($url, $context->getBaseUrl(), [], true);
        $element->setAttribute($attribute, $newUrl);
    }
}
namespace Kibo\Phast\Filters\HTML\MetaCharset;

class Filter implements \Kibo\Phast\Filters\HTML\HTMLStreamFilter
{
    public function transformElements(\Traversable $elements, \Kibo\Phast\Filters\HTML\HTMLPageContext $context)
    {
        $didYield = false;
        foreach ($elements as $element) {
            if ($element instanceof \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag) {
                if ($element->tagName == 'meta' && !array_diff(array_keys($element->getAttributes()), ['charset', '/'])) {
                    continue;
                }
                if (!$didYield && !in_array($element->tagName, ['html', 'head', '!doctype'])) {
                    (yield new \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag('meta', ['charset' => 'utf-8']));
                    $didYield = true;
                }
            }
            (yield $element);
        }
    }
}
namespace Kibo\Phast\Filters\HTML\Minify;

class Filter implements \Kibo\Phast\Filters\HTML\HTMLStreamFilter
{
    public function transformElements(\Traversable $elements, \Kibo\Phast\Filters\HTML\HTMLPageContext $context)
    {
        $inTags = ['pre' => 0, 'textarea' => 0];
        foreach ($elements as $element) {
            if ($element instanceof \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag && isset($inTags[$element->getTagName()])) {
                $inTags[$element->getTagName()]++;
            } elseif ($element instanceof \Kibo\Phast\Parsing\HTML\HTMLStreamElements\ClosingTag && !empty($inTags[$element->getTagName()])) {
                $inTags[$element->getTagName()]--;
            } elseif ($element instanceof \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Junk && !array_sum($inTags)) {
                $element->originalString = preg_replace_callback('~\\s++~', function ($match) {
                    return strpos($match[0], "\n") === false ? ' ' : "\n";
                }, $element->originalString);
            }
            (yield $element);
        }
    }
}
namespace Kibo\Phast\Filters\HTML\MinifyScripts;

class Factory
{
    public function make(array $config)
    {
        return new \Kibo\Phast\Filters\HTML\MinifyScripts\Filter(new \Kibo\Phast\Cache\Sqlite\Cache($config['cache'], 'minified-inline-scripts'));
    }
}
namespace Kibo\Phast\Filters\HTML\MinifyScripts;

class Filter implements \Kibo\Phast\Filters\HTML\HTMLStreamFilter
{
    use \Kibo\Phast\Filters\HTML\Helpers\JSDetectorTrait;
    private $cache;
    public function __construct(\Kibo\Phast\Cache\Sqlite\Cache $cache)
    {
        $this->cache = $cache;
    }
    public function transformElements(\Traversable $elements, \Kibo\Phast\Filters\HTML\HTMLPageContext $context)
    {
        foreach ($elements as $element) {
            if ($element instanceof \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag && $element->getTagName() === 'script' && ($content = $element->getTextContent()) !== '') {
                $content = trim($content);
                if ($this->isJSElement($element) && preg_match('~[()[\\]{};]\\s~', $content)) {
                    $content = preg_replace('~^\\s*<!--\\s*\\n(.*)\\n\\s*-->\\s*$~s', '$1', $content);
                    $content = $this->cache->get(md5($content), function () use($content) {
                        return (new \Kibo\Phast\Common\JSMinifier($content, true))->min();
                    });
                } elseif (($data = @json_decode($content)) !== null && ($newContent = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) {
                    $content = str_replace('</', '<\\/', $newContent);
                }
                $element->setTextContent($content);
            }
            (yield $element);
        }
    }
}
namespace Kibo\Phast\Filters\HTML\PhastScriptsCompiler;

class Factory implements \Kibo\Phast\Filters\HTML\HTMLFilterFactory
{
    public function make(array $config)
    {
        $cache = new \Kibo\Phast\Cache\Sqlite\Cache($config['cache'], 'phast-scripts');
        $compiler = new \Kibo\Phast\Filters\HTML\PhastScriptsCompiler\PhastJavaScriptCompiler($cache, $config['servicesUrl'], $config['serviceRequestFormat']);
        return new \Kibo\Phast\Filters\HTML\PhastScriptsCompiler\Filter($compiler, $config['csp']['nonce']);
    }
}
namespace Kibo\Phast\Filters\HTML\PhastScriptsCompiler;

class Filter implements \Kibo\Phast\Filters\HTML\HTMLStreamFilter
{
    /**
     * @var PhastJavaScriptCompiler
     */
    private $compiler;
    /**
     * @var ?string
     */
    private $cspNonce;
    /**
     * Filter constructor.
     * @param PhastJavaScriptCompiler $compiler
     * @param ?string $cspNonce
     */
    public function __construct(\Kibo\Phast\Filters\HTML\PhastScriptsCompiler\PhastJavaScriptCompiler $compiler, $cspNonce)
    {
        $this->compiler = $compiler;
        $this->cspNonce = $cspNonce;
    }
    public function transformElements(\Traversable $elements, \Kibo\Phast\Filters\HTML\HTMLPageContext $context)
    {
        $buffer = [];
        $buffering = false;
        foreach ($elements as $element) {
            if ($this->isClosingBodyTag($element)) {
                if ($buffering) {
                    foreach ($buffer as $bufElement) {
                        (yield $bufElement);
                    }
                    $buffer = [];
                }
                $buffering = true;
            }
            if ($buffering) {
                $buffer[] = $element;
            } else {
                (yield $element);
            }
        }
        $scripts = $context->getPhastJavaScripts();
        if (!empty($scripts)) {
            (yield $this->compileScript($scripts));
        }
        foreach ($buffer as $element) {
            (yield $element);
        }
    }
    /**
     * @param PhastJavaScript[] $scripts
     * @return Tag
     */
    private function compileScript(array $scripts)
    {
        $names = array_map(function (\Kibo\Phast\ValueObjects\PhastJavaScript $script) {
            $matches = [];
            preg_match('~[^/]*?\\/?[^/]+$~', $script->getFilename(), $matches);
            return $matches[0];
        }, $scripts);
        $script = new \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag('script');
        $script->setAttribute('data-phast-compiled-js-names', join(',', $names));
        if ($this->cspNonce !== null) {
            $script->setAttribute('nonce', $this->cspNonce);
        }
        $compiled = $this->compiler->compileScriptsWithConfig($scripts);
        $script->setTextContent($compiled);
        return $script;
    }
    private function isClosingBodyTag(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Element $element)
    {
        return $element instanceof \Kibo\Phast\Parsing\HTML\HTMLStreamElements\ClosingTag && $element->getTagName() == 'body';
    }
}
namespace Kibo\Phast\Filters\HTML\PhastScriptsCompiler;

class PhastJavaScriptCompiler
{
    /**
     * @var Cache
     */
    private $cache;
    /**
     * @var string
     */
    private $serviceUrl;
    private $serviceRequestFormat;
    /**
     * @var \stdClass
     */
    private $lastCompiledConfig;
    /**
     * PhastJavaScriptCompiler constructor.
     * @param Cache $cache
     * @param string $serviceUrl
     */
    public function __construct(\Kibo\Phast\Cache\Cache $cache, $serviceUrl, $serviceRequestFormat)
    {
        $this->cache = $cache;
        $this->serviceUrl = (new \Kibo\Phast\Services\ServiceRequest())->withUrl(\Kibo\Phast\ValueObjects\URL::fromString((string) $serviceUrl))->serialize(\Kibo\Phast\Services\ServiceRequest::FORMAT_QUERY);
        $this->serviceRequestFormat = $serviceRequestFormat;
    }
    /**
     * @return \stdClass|null
     */
    public function getLastCompiledConfig()
    {
        return $this->lastCompiledConfig;
    }
    /**
     * @param PhastJavaScript[] $scripts
     * @return string
     */
    public function compileScripts(array $scripts)
    {
        return $this->cache->get($this->getCacheKey($scripts), function () use($scripts) {
            return $this->performCompilation($scripts);
        });
    }
    /**
     * @param PhastJavaScript[] $scripts
     * @return string
     */
    public function compileScriptsWithConfig(array $scripts)
    {
        $bundlerMappings = \Kibo\Phast\Services\Bundler\ShortBundlerParamsParser::getParamsMappings();
        $jsMappings = array_combine(array_values($bundlerMappings), array_keys($bundlerMappings));
        $resourcesLoader = \Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/PhastScriptsCompiler/resources-loader.js', "var Promise=phast.ES6Promise.Promise;phast.ResourceLoader=function(a,b){this.get=function(c){return b.get(c).then(function(d){if(typeof d!==\"string\"){throw new Error(\"response should be string\")}return d}).catch(function(){var e=a.get(c);e.then(function(f){b.set(c,f)});return e})}};phast.ResourceLoader.RequestParams={};phast.ResourceLoader.RequestParams.FaultyParams={};phast.ResourceLoader.RequestParams.fromString=function(g){try{return JSON.parse(g)}catch(h){return phast.ResourceLoader.RequestParams.FaultyParams}};phast.ResourceLoader.BundlerServiceClient=function(i,j,k){var l=phast.ResourceLoader.BundlerServiceClient.RequestsPack;var m=l.PackItem;var n;this.get=function(q){if(q===phast.ResourceLoader.RequestParams.FaultyParams){return Promise.reject(new Error(\"Parameters did not parse as JSON\"))}return new Promise(function(r,s){if(n===undefined){n=new l(j)}n.add(new m({success:r,error:s},q));setTimeout(o);if(n.toQuery().length>4500){console.log(\"[Phast] Resource loader: Pack got too big; flushing early...\");o()}})};function o(){if(n===undefined){return}var t=n;n=undefined;p(t)}function p(u){var v=phast.buildServiceUrl({serviceUrl:i,pathInfo:k},\"service=bundler&\"+u.toQuery());var w=function(){console.error(\"[Phast] Request to bundler failed with status\",y.status);console.log(\"URL:\",v);u.handleError()};var x=function(){if(y.status>=200&&y.status<300){u.handleResponse(y.responseText)}else{u.handleError()}};var y=new XMLHttpRequest;y.open(\"GET\",v);y.addEventListener(\"error\",w);y.addEventListener(\"abort\",w);y.addEventListener(\"load\",x);y.send()}};phast.ResourceLoader.BundlerServiceClient.RequestsPack=function(z){var A={};this.getLength=function(){var F=0;for(var G in A){F++}return F};this.add=function(H){var I;if(H.params.token){I=\"token=\"+H.params.token}else if(H.params.ref){I=\"ref=\"+H.params.ref}else{I=\"\"}if(!A[I]){A[I]={params:H.params,requests:[H.request]}}else{A[I].requests.push(H.request)}};this.toQuery=function(){var J=[],K=[],L=\"\";B().forEach(function(M){var N,O;for(var P in A[M].params){if(P===\"cacheMarker\"){K.push(A[M].params.cacheMarker);continue}N=z[P]?z[P]:P;if(P===\"strip-imports\"){O=encodeURIComponent(N)}else if(P===\"src\"){O=encodeURIComponent(N)+\"=\"+encodeURIComponent(C(A[M].params.src,L));L=A[M].params.src}else{O=encodeURIComponent(N)+\"=\"+encodeURIComponent(A[M].params[P])}J.push(O)}});if(K.length>0){J.unshift(\"c=\"+phast.hash(K.join(\"|\"),23045))}return E(J.join(\"&\"))};function B(){return Object.keys(A).sort(function(R,S){return Q(R,S)?1:Q(S,R)?-1:0});function Q(T,U){if(typeof A[T].params.src!==\"undefined\"&&typeof A[U].params.src!==\"undefined\"){return A[T].params.src>A[U].params.src}return T>U}}function C(V,W){var X=0,Y=Math.pow(36,2)-1;while(X<W.length&&V[X]===W[X]){X++}X=Math.min(X,Y);return D(X)+\"\"+V.substr(X)}function D(Z){var \$=[\"0\",\"1\",\"2\",\"3\",\"4\",\"5\",\"6\",\"7\",\"8\",\"9\",\"a\",\"b\",\"c\",\"d\",\"e\",\"f\",\"g\",\"h\",\"i\",\"j\",\"k\",\"l\",\"m\",\"n\",\"o\",\"p\",\"q\",\"r\",\"s\",\"t\",\"u\",\"v\",\"w\",\"x\",\"y\",\"z\"];var _=Z%36;var aa=Math.floor((Z-_)/36);return \$[aa]+\$[_]}function E(ba){if(!/(^|&)s=/.test(ba)){return ba}return ba.replace(/(%..)|([A-M])|([N-Z])/gi,function(ca,da,ea,fa){if(da){return ca}return String.fromCharCode(ca.charCodeAt(0)+(ea?13:-13))})}this.handleResponse=function(ga){try{var ha=JSON.parse(ga)}catch(ja){this.handleError();return}var ia=B();if(ha.length!==ia.length){console.error(\"[Phast] Requested\",ia.length,\"items from bundler, but got\",ha.length,\"response(s)\");this.handleError();return}ha.forEach(function(ka,la){if(ka.status===200){A[ia[la]].requests.forEach(function(ma){ma.success(ka.content)})}else{A[ia[la]].requests.forEach(function(na){na.error(new Error(\"Got from bundler: \"+JSON.stringify(ka)))})}})}.bind(this);this.handleError=function(){for(var oa in A){A[oa].requests.forEach(function(pa){pa.error()})}}};phast.ResourceLoader.BundlerServiceClient.RequestsPack.PackItem=function(qa,ra){this.request=qa;this.params=ra};phast.ResourceLoader.IndexedDBStorage=function(sa){var ta=phast.ResourceLoader.IndexedDBStorage;var ua=ta.logPrefix;var va=ta.requestToPromise;var wa;Ba();this.get=function(Ca){return xa(\"readonly\").then(function(Da){return va(Da.get(Ca)).catch(ya(\"reading from store\"))})};this.store=function(Ea){return xa(\"readwrite\").then(function(Fa){return va(Fa.put(Ea)).catch(ya(\"writing to store\"))})};this.clear=function(){return xa(\"readwrite\").then(function(Ga){return va(Ga.clear())})};this.iterateOnAll=function(Ha){return xa(\"readonly\").then(function(Ia){return za(Ha,Ia.openCursor()).catch(ya(\"iterating on all\"))})};function xa(Ja){return wa.get().then(function(Ka){try{return Ka.transaction(sa.storeName,Ja).objectStore(sa.storeName)}catch(La){console.error(ua,\"Could not open store; recreating database:\",La);Aa();throw La}})}function ya(Ma){return function(Na){console.error(ua,\"Error \"+Ma+\":\",Na);Aa();throw Na}}function za(Oa,Pa){return new Promise(function(Qa,Ra){Pa.onsuccess=function(Sa){var Ta=Sa.target.result;if(Ta){Oa(Ta.value);Ta.continue()}else{Qa()}};Pa.onerror=Ra})}function Aa(){var Ua=wa.dropDB().then(Ba);wa={get:function(){return Promise.reject(new Error(\"Database is being dropped and recreated\"))},dropDB:function(){return Ua}}}function Ba(){wa=new phast.ResourceLoader.IndexedDBStorage.Connection(sa)}};phast.ResourceLoader.IndexedDBStorage.logPrefix=\"[Phast] Resource loader:\";phast.ResourceLoader.IndexedDBStorage.requestToPromise=function(Va){return new Promise(function(Wa,Xa){Va.onsuccess=function(){Wa(Va.result)};Va.onerror=function(){Xa(Va.error)}})};phast.ResourceLoader.IndexedDBStorage.ConnectionParams=function(){this.dbName=\"phastResourcesCache\";this.dbVersion=1;this.storeName=\"resources\"};phast.ResourceLoader.IndexedDBStorage.StoredResource=function(Ya,Za){this.token=Ya;this.content=Za};phast.ResourceLoader.IndexedDBStorage.Connection=function(\$a){var _a=phast.ResourceLoader.IndexedDBStorage.logPrefix;var a0=phast.ResourceLoader.IndexedDBStorage.requestToPromise;var b0;this.get=c0;this.dropDB=d0;function c0(){if(!b0){b0=e0(\$a)}return b0}function d0(){return c0().then(function(g0){console.error(_a,\"Dropping DB\");g0.close();b0=null;return a0(window.indexedDB.deleteDatabase(\$a.dbName))})}function e0(h0){if(typeof window.indexedDB===\"undefined\"){return Promise.reject(new Error(\"IndexedDB is not available\"))}var i0=window.indexedDB.open(h0.dbName,h0.dbVersion);i0.onupgradeneeded=function(){f0(i0.result,h0)};return a0(i0).then(function(j0){j0.onversionchange=function(){console.debug(_a,\"Closing DB\");j0.close();if(b0){b0=null}};return j0}).catch(function(k0){console.log(_a,\"IndexedDB cache is not available. This is usually due to using private browsing mode.\");throw k0})}function f0(l0,m0){l0.createObjectStore(m0.storeName,{keyPath:\"token\"})}};phast.ResourceLoader.StorageCache=function(n0,o0){var p0=phast.ResourceLoader.IndexedDBStorage.StoredResource;this.get=function(x0){return s0(r0(x0))};this.set=function(y0,z0){return t0(r0(y0),z0,false)};var q0=null;function r0(A0){return JSON.stringify(A0)}function s0(B0){return o0.get(B0).then(function(C0){if(C0){return Promise.resolve(C0.content)}return Promise.resolve()})}function t0(D0,E0,F0){return w0().then(function(G0){var H0=E0.length+G0;if(H0>n0.maxStorageSize){return F0||E0.length>n0.maxStorageSize?Promise.reject(new Error(\"Storage quota will be exceeded\")):u0(D0,E0)}q0=H0;var I0=new p0(D0,E0);return o0.store(I0)})}function u0(J0,K0){return v0().then(function(){return t0(J0,K0,true)})}function v0(){return o0.clear().then(function(){q0=0})}function w0(){if(q0!==null){return Promise.resolve(q0)}var L0=0;return o0.iterateOnAll(function(M0){L0+=M0.content.length}).then(function(){q0=L0;return Promise.resolve(q0)})}};phast.ResourceLoader.StorageCache.StorageCacheParams=function(){this.maxStorageSize=4.5*1024*1024};phast.ResourceLoader.BlackholeCache=function(){this.get=function(){return Promise.reject()};this.set=function(){return Promise.reject()}};phast.ResourceLoader.make=function(N0,O0,P0){var Q0=S0();var R0=new phast.ResourceLoader.BundlerServiceClient(N0,O0,P0);return new phast.ResourceLoader(R0,Q0);function S0(){var T0=window.navigator.userAgent;if(/safari/i.test(T0)&&!/chrome|android/i.test(T0)){console.log(\"[Phast] Not using IndexedDB cache on Safari\");return new phast.ResourceLoader.BlackholeCache}else{var U0=new phast.ResourceLoader.IndexedDBStorage.ConnectionParams;var V0=new phast.ResourceLoader.IndexedDBStorage(U0);var W0=new phast.ResourceLoader.StorageCache.StorageCacheParams;return new phast.ResourceLoader.StorageCache(W0,V0)}}};\n");
        $resourcesLoader->setConfig('resourcesLoader', ['serviceUrl' => (string) $this->serviceUrl, 'shortParamsMappings' => $jsMappings, 'pathInfo' => $this->serviceRequestFormat === \Kibo\Phast\Services\ServiceRequest::FORMAT_PATH]);
        $scripts = array_merge([\Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/PhastScriptsCompiler/runner.js', "phast.config=JSON.parse(atob(phast.config));while(phast.scripts.length){phast.scripts.shift()()}\n"), \Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/PhastScriptsCompiler/es6-promise.js', "(function(a,b){typeof exports===\"object\"&&typeof module!==\"undefined\"?module.exports=b():typeof define===\"function\"&&define.amd?define(b):a.ES6Promise=b()})(phast,function(){\"use strict\";function c(ia){var ja=typeof ia;return ia!==null&&(ja===\"object\"||ja===\"function\")}function d(ka){return typeof ka===\"function\"}var e=void 0;if(Array.isArray){e=Array.isArray}else{e=function(la){return Object.prototype.toString.call(la)===\"[object Array]\"}}var f=e;var g=0;var h=void 0;var i=void 0;var j=function ma(na,oa){w[g]=na;w[g+1]=oa;g+=2;if(g===2){if(i){i(x)}else{z()}}};function k(pa){i=pa}function l(qa){j=qa}var m=typeof window!==\"undefined\"?window:undefined;var n=m||{};var o=n.MutationObserver||n.WebKitMutationObserver;var p=typeof self===\"undefined\"&&typeof process!==\"undefined\"&&{}.toString.call(process)===\"[object process]\";var q=typeof Uint8ClampedArray!==\"undefined\"&&typeof importScripts!==\"undefined\"&&typeof MessageChannel!==\"undefined\";function r(){return function(){return process.nextTick(x)}}function s(){if(typeof h!==\"undefined\"){return function(){h(x)}}return v()}function t(){var ra=0;var sa=new o(x);var ta=document.createTextNode(\"\");sa.observe(ta,{characterData:true});return function(){ta.data=ra=++ra%2}}function u(){var ua=new MessageChannel;ua.port1.onmessage=x;return function(){return ua.port2.postMessage(0)}}function v(){var va=setTimeout;return function(){return va(x,1)}}var w=new Array(1e3);function x(){for(var wa=0;wa<g;wa+=2){var xa=w[wa];var ya=w[wa+1];xa(ya);w[wa]=undefined;w[wa+1]=undefined}g=0}function y(){try{var za=Function(\"return this\")().require(\"vertx\");h=za.runOnLoop||za.runOnContext;return s()}catch(Aa){return v()}}var z=void 0;if(p){z=r()}else if(o){z=t()}else if(q){z=u()}else if(m===undefined&&typeof require===\"function\"){z=y()}else{z=v()}function A(Ba,Ca){var Da=this;var Ea=new this.constructor(D);if(Ea[C]===undefined){\$(Ea)}var Fa=Da._state;if(Fa){var Ga=arguments[Fa-1];j(function(){return W(Fa,Ea,Ga,Da._result)})}else{T(Da,Ea,Ba,Ca)}return Ea}function B(Ha){var Ia=this;if(Ha&&typeof Ha===\"object\"&&Ha.constructor===Ia){return Ha}var Ja=new Ia(D);P(Ja,Ha);return Ja}var C=Math.random().toString(36).substring(2);function D(){}var E=void 0;var F=1;var G=2;var H={error:null};function I(){return new TypeError(\"You cannot resolve a promise with itself\")}function J(){return new TypeError(\"A promises callback cannot return that same promise.\")}function K(Ka){try{return Ka.then}catch(La){H.error=La;return H}}function L(Ma,Na,Oa,Pa){try{Ma.call(Na,Oa,Pa)}catch(Qa){return Qa}}function M(Ra,Sa,Ta){j(function(Ua){var Va=false;var Wa=L(Ta,Sa,function(Xa){if(Va){return}Va=true;if(Sa!==Xa){P(Ua,Xa)}else{R(Ua,Xa)}},function(Ya){if(Va){return}Va=true;S(Ua,Ya)},\"Settle: \"+(Ua._label||\" unknown promise\"));if(!Va&&Wa){Va=true;S(Ua,Wa)}},Ra)}function N(Za,\$a){if(\$a._state===F){R(Za,\$a._result)}else if(\$a._state===G){S(Za,\$a._result)}else{T(\$a,undefined,function(_a){return P(Za,_a)},function(a0){return S(Za,a0)})}}function O(b0,c0,d0){if(c0.constructor===b0.constructor&&d0===A&&c0.constructor.resolve===B){N(b0,c0)}else{if(d0===H){S(b0,H.error);H.error=null}else if(d0===undefined){R(b0,c0)}else if(d(d0)){M(b0,c0,d0)}else{R(b0,c0)}}}function P(e0,f0){if(e0===f0){S(e0,I())}else if(c(f0)){O(e0,f0,K(f0))}else{R(e0,f0)}}function Q(g0){if(g0._onerror){g0._onerror(g0._result)}U(g0)}function R(h0,i0){if(h0._state!==E){return}h0._result=i0;h0._state=F;if(h0._subscribers.length!==0){j(U,h0)}}function S(j0,k0){if(j0._state!==E){return}j0._state=G;j0._result=k0;j(Q,j0)}function T(l0,m0,n0,o0){var p0=l0._subscribers;var q0=p0.length;l0._onerror=null;p0[q0]=m0;p0[q0+F]=n0;p0[q0+G]=o0;if(q0===0&&l0._state){j(U,l0)}}function U(r0){var s0=r0._subscribers;var t0=r0._state;if(s0.length===0){return}var u0=void 0,v0=void 0,w0=r0._result;for(var x0=0;x0<s0.length;x0+=3){u0=s0[x0];v0=s0[x0+t0];if(u0){W(t0,u0,v0,w0)}else{v0(w0)}}r0._subscribers.length=0}function V(y0,z0){try{return y0(z0)}catch(A0){H.error=A0;return H}}function W(B0,C0,D0,E0){var F0=d(D0),G0=void 0,H0=void 0,I0=void 0,J0=void 0;if(F0){G0=V(D0,E0);if(G0===H){J0=true;H0=G0.error;G0.error=null}else{I0=true}if(C0===G0){S(C0,J());return}}else{G0=E0;I0=true}if(C0._state!==E){}else if(F0&&I0){P(C0,G0)}else if(J0){S(C0,H0)}else if(B0===F){R(C0,G0)}else if(B0===G){S(C0,G0)}}function X(K0,L0){try{L0(function M0(N0){P(K0,N0)},function O0(P0){S(K0,P0)})}catch(Q0){S(K0,Q0)}}var Y=0;function Z(){return Y++}function \$(R0){R0[C]=Y++;R0._state=undefined;R0._result=undefined;R0._subscribers=[]}function _(){return new Error(\"Array Methods must be provided an Array\")}var aa=function(){function S0(T0,U0){this._instanceConstructor=T0;this.promise=new T0(D);if(!this.promise[C]){\$(this.promise)}if(f(U0)){this.length=U0.length;this._remaining=U0.length;this._result=new Array(this.length);if(this.length===0){R(this.promise,this._result)}else{this.length=this.length||0;this._enumerate(U0);if(this._remaining===0){R(this.promise,this._result)}}}else{S(this.promise,_())}}S0.prototype._enumerate=function V0(W0){for(var X0=0;this._state===E&&X0<W0.length;X0++){this._eachEntry(W0[X0],X0)}};S0.prototype._eachEntry=function Y0(Z0,\$0){var _0=this._instanceConstructor;var ab=_0.resolve;if(ab===B){var bb=K(Z0);if(bb===A&&Z0._state!==E){this._settledAt(Z0._state,\$0,Z0._result)}else if(typeof bb!==\"function\"){this._remaining--;this._result[\$0]=Z0}else if(_0===ga){var cb=new _0(D);O(cb,Z0,bb);this._willSettleAt(cb,\$0)}else{this._willSettleAt(new _0(function(db){return db(Z0)}),\$0)}}else{this._willSettleAt(ab(Z0),\$0)}};S0.prototype._settledAt=function eb(fb,gb,hb){var ib=this.promise;if(ib._state===E){this._remaining--;if(fb===G){S(ib,hb)}else{this._result[gb]=hb}}if(this._remaining===0){R(ib,this._result)}};S0.prototype._willSettleAt=function jb(kb,lb){var mb=this;T(kb,undefined,function(nb){return mb._settledAt(F,lb,nb)},function(ob){return mb._settledAt(G,lb,ob)})};return S0}();function ba(pb){return new aa(this,pb).promise}function ca(qb){var rb=this;if(!f(qb)){return new rb(function(sb,tb){return tb(new TypeError(\"You must pass an array to race.\"))})}else{return new rb(function(ub,vb){var wb=qb.length;for(var xb=0;xb<wb;xb++){rb.resolve(qb[xb]).then(ub,vb)}})}}function da(yb){var zb=this;var Ab=new zb(D);S(Ab,yb);return Ab}function ea(){throw new TypeError(\"You must pass a resolver function as the first argument to the promise constructor\")}function fa(){throw new TypeError(\"Failed to construct 'Promise': Please use the 'new' operator, this object constructor cannot be called as a function.\")}var ga=function(){function Bb(Cb){this[C]=Z();this._result=this._state=undefined;this._subscribers=[];if(D!==Cb){typeof Cb!==\"function\"&&ea();this instanceof Bb?X(this,Cb):fa()}}Bb.prototype.catch=function Db(Eb){return this.then(null,Eb)};Bb.prototype.finally=function Fb(Gb){var Hb=this;var Ib=Hb.constructor;return Hb.then(function(Jb){return Ib.resolve(Gb()).then(function(){return Jb})},function(Kb){return Ib.resolve(Gb()).then(function(){throw Kb})})};return Bb}();ga.prototype.then=A;ga.all=ba;ga.race=ca;ga.resolve=B;ga.reject=da;ga._setScheduler=k;ga._setAsap=l;ga._asap=j;function ha(){var Lb=void 0;if(typeof global!==\"undefined\"){Lb=global}else if(typeof self!==\"undefined\"){Lb=self}else{try{Lb=Function(\"return this\")()}catch(Ob){throw new Error(\"polyfill failed because global object is unavailable in this environment\")}}var Mb=Lb.Promise;if(Mb){var Nb=null;try{Nb=Object.prototype.toString.call(Mb.resolve())}catch(Pb){}if(Nb===\"[object Promise]\"&&!Mb.cast){return}}Lb.Promise=ga}ga.polyfill=ha;ga.Promise=ga;return ga});\n"), \Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/PhastScriptsCompiler/hash.js', "function murmurhash3_32_gc(a,b){var c,d,e,f,g,h,i,j,k,l;c=a.length&3;d=a.length-c;e=b;g=3432918353;i=461845907;l=0;while(l<d){k=a.charCodeAt(l)&255|(a.charCodeAt(++l)&255)<<8|(a.charCodeAt(++l)&255)<<16|(a.charCodeAt(++l)&255)<<24;++l;k=(k&65535)*g+(((k>>>16)*g&65535)<<16)&4294967295;k=k<<15|k>>>17;k=(k&65535)*i+(((k>>>16)*i&65535)<<16)&4294967295;e^=k;e=e<<13|e>>>19;f=(e&65535)*5+(((e>>>16)*5&65535)<<16)&4294967295;e=(f&65535)+27492+(((f>>>16)+58964&65535)<<16)}k=0;switch(c){case 3:k^=(a.charCodeAt(l+2)&255)<<16;case 2:k^=(a.charCodeAt(l+1)&255)<<8;case 1:k^=a.charCodeAt(l)&255;k=(k&65535)*g+(((k>>>16)*g&65535)<<16)&4294967295;k=k<<15|k>>>17;k=(k&65535)*i+(((k>>>16)*i&65535)<<16)&4294967295;e^=k}e^=a.length;e^=e>>>16;e=(e&65535)*2246822507+(((e>>>16)*2246822507&65535)<<16)&4294967295;e^=e>>>13;e=(e&65535)*3266489909+(((e>>>16)*3266489909&65535)<<16)&4294967295;e^=e>>>16;return e>>>0}phast.hash=murmurhash3_32_gc;\n"), \Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/PhastScriptsCompiler/service-url.js', "phast.buildServiceUrl=function(a,b){if(a.pathInfo){return appendPathInfo(a.serviceUrl,buildQuery(b))}else{return appendQueryString(a.serviceUrl,buildQuery(b))}};function buildQuery(c){if(typeof c===\"string\"){return c}var d=[];for(var e in c){if(c.hasOwnProperty(e)){d.push(encodeURIComponent(e)+\"=\"+encodeURIComponent(c[e]))}}return d.join(\"&\")}function appendPathInfo(f,g){var h=btoa(g).replace(/=/g,\"\").replace(/\\//g,\"_\").replace(/\\+/g,\"-\");var i=j(h+\".q.js\");return f.replace(/\\?.*\$/,\"\").replace(/\\/__p__\\.js\$/,\"\")+\"/\"+i;function j(l){return k(k(l).match(/[\\s\\S]{1,255}/g).join(\"/\"))}function k(m){return m.split(\"\").reverse().join(\"\")}}function appendQueryString(n,o){var p=n.indexOf(\"?\")>-1?\"&\":\"?\";return n+p+o}\n"), $resourcesLoader, \Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/PhastScriptsCompiler/phast.js', "var Promise=phast.ES6Promise;phast.ResourceLoader.instance=phast.ResourceLoader.make(phast.config.resourcesLoader.serviceUrl,phast.config.resourcesLoader.shortParamsMappings,phast.config.resourcesLoader.pathInfo);phast.forEachSelectedElement=function(a,b){Array.prototype.forEach.call(window.document.querySelectorAll(a),b)};phast.once=function(c){var d=false;return function(){if(!d){d=true;c.apply(this,Array.prototype.slice(arguments))}}};phast.on=function(e,f){return new Promise(function(g){e.addEventListener(f,g)})};phast.wait=function(h){return new Promise(function(i){setTimeout(i,h)})};phast.on(document,\"DOMContentLoaded\").then(function(){var j,k;function l(n){return n&&n.nodeType===8&&/^\\s*\\[Phast\\]/.test(n.textContent)}function m(o){while(o){if(l(o)){return o}o=o.nextSibling}return false}k=m(document.documentElement.nextSibling);if(k===false){k=m(document.body.firstChild)}if(k){j=k.textContent.replace(/^\\s+|\\s+\$/g,\"\").split(\"\\n\");console.groupCollapsed(j.shift());console.log(j.join(\"\\n\"));console.groupEnd()}});phast.on(document,\"DOMContentLoaded\").then(function(){var p=performance.timing;var q=[];q.push([\"Downloading phases:\"]);q.push([\"  Look up hostname in DNS            + %s ms\",t(p.domainLookupEnd-p.fetchStart)]);q.push([\"  Establish connection               + %s ms\",t(p.connectEnd-p.domainLookupEnd)]);q.push([\"  Send request                       + %s ms\",t(p.requestStart-p.connectEnd)]);q.push([\"  Receive first byte                 + %s ms\",t(p.responseStart-p.requestStart)]);q.push([\"  Download page                      + %s ms\",t(p.responseEnd-p.responseStart)]);q.push([\"\"]);q.push([\"Totals:\"]);q.push([\"  Time to first byte                   %s ms\",t(p.responseStart-p.fetchStart)]);q.push([\"    (since request start)              %s ms\",t(p.responseStart-p.requestStart)]);q.push([\"  Total request time                   %s ms\",t(p.responseEnd-p.fetchStart)]);q.push([\"    (since request start)              %s ms\",t(p.responseEnd-p.requestStart)]);q.push([\" \"]);var r=[];var s=[];q.forEach(function(u){r.push(u.shift());s=s.concat(u)});console.groupCollapsed(\"[Phast] Client-side performance metrics\");console.log.apply(console,[r.join(\"\\n\")].concat(s));console.groupEnd();function t(v){v=\"\"+v;while(v.length<4){v=\" \"+v}return v}});\n")], $scripts);
        $compiled = $this->compileScripts($scripts);
        return '(' . $compiled . ')(' . $this->compileConfig($scripts) . ');';
    }
    /**
     * @param PhastJavaScript[] $scripts
     * @return string
     */
    private function performCompilation(array $scripts)
    {
        $compiled = implode(',', array_map(function (\Kibo\Phast\ValueObjects\PhastJavaScript $script) {
            return $this->interpolate($script->getContents());
        }, $scripts));
        return 'function phastScripts(phast){phast.scripts=[' . $compiled . '];(phast.scripts.shift())();}';
    }
    /**
     * @param PhastJavaScript[] $scripts
     * @return string
     */
    private function compileConfig(array $scripts)
    {
        $config = new \stdClass();
        foreach ($scripts as $script) {
            if ($script->hasConfig()) {
                $config->{$script->getConfigKey()} = $script->getConfig();
            }
        }
        $this->lastCompiledConfig = $config;
        return \Kibo\Phast\Common\JSON::encode(['config' => base64_encode(\Kibo\Phast\Common\JSON::encode($config))]);
    }
    /**
     * @param string $script
     * @return string
     */
    private function interpolate($script)
    {
        return sprintf('(function(){%s})', $script);
    }
    /**
     * @param PhastJavaScript[] $scripts
     * @return string
     */
    private function getCacheKey(array $scripts)
    {
        return array_reduce($scripts, function ($carry, \Kibo\Phast\ValueObjects\PhastJavaScript $script) {
            $carry .= $script->getFilename() . '-' . $script->getCacheSalt() . "\n";
            return $carry;
        }, '');
    }
}
namespace Kibo\Phast\Filters\HTML\ScriptsDeferring;

class Factory implements \Kibo\Phast\Filters\HTML\HTMLFilterFactory
{
    public function make(array $config)
    {
        return new \Kibo\Phast\Filters\HTML\ScriptsDeferring\Filter($config['csp']);
    }
}
namespace Kibo\Phast\Filters\HTML\ScriptsProxyService;

class Factory implements \Kibo\Phast\Filters\HTML\HTMLFilterFactory
{
    public function make(array $config)
    {
        if (!isset($config['documents']['filters'][\Kibo\Phast\Filters\HTML\ScriptsProxyService\Filter::class]['serviceUrl'])) {
            $config['documents']['filters'][\Kibo\Phast\Filters\HTML\ScriptsProxyService\Filter::class]['serviceUrl'] = $config['servicesUrl'];
        }
        $filterConfig = $config['documents']['filters'][\Kibo\Phast\Filters\HTML\ScriptsProxyService\Filter::class];
        $filterConfig['match'] = $config['scripts']['whitelist'];
        return new \Kibo\Phast\Filters\HTML\ScriptsProxyService\Filter($filterConfig, (new \Kibo\Phast\Security\ServiceSignatureFactory())->make($config), new \Kibo\Phast\Retrievers\LocalRetriever($config['retrieverMap']), (new \Kibo\Phast\Services\Bundler\TokenRefMakerFactory())->make($config));
    }
}
namespace Kibo\Phast\Filters\Image\Composite;

class Factory
{
    /**
     * @var array
     */
    private $config;
    /**
     * CompositeImageFilterFactory constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    public function make()
    {
        $imageFactoryClass = $this->config['images']['factory'];
        if (!class_exists($imageFactoryClass)) {
            throw new \Kibo\Phast\Exceptions\LogicException("No such class: {$imageFactoryClass}");
        }
        $composite = new \Kibo\Phast\Filters\Image\Composite\Filter(new $imageFactoryClass($this->config), (new \Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageInliningManagerFactory())->make($this->config));
        foreach ($this->config['images']['filters'] as $class => $config) {
            if ($config === null) {
                continue;
            }
            $package = \Kibo\Phast\Environment\Package::fromPackageClass($class);
            $filter = $package->getFactory()->make($this->config);
            $composite->addImageFilter($filter);
        }
        if ($this->config['images']['enable-cache']) {
            return new \Kibo\Phast\Filters\Service\CachingServiceFilter(new \Kibo\Phast\Cache\Sqlite\Cache($this->config['cache'], 'images-1'), $composite, new \Kibo\Phast\Retrievers\LocalRetriever($this->config['retrieverMap']));
        }
        return $composite;
    }
}
namespace Kibo\Phast\Filters\Image\Exceptions;

class ImageProcessingException extends \Kibo\Phast\Exceptions\RuntimeException
{
}
namespace Kibo\Phast\Filters\Image;

interface Image
{
    const TYPE_JPEG = 'image/jpeg';
    const TYPE_PNG = 'image/png';
    const TYPE_WEBP = 'image/webp';
    /**
     * @return integer
     */
    public function getWidth();
    /**
     * @return integer
     */
    public function getHeight();
    /**
     * @return string
     */
    public function getType();
    /**
     * @return string
     */
    public function getAsString();
    /**
     * @return integer
     */
    public function getSizeAsString();
    /**
     * @param integer $width
     * @param integer $height
     * @return Image
     */
    public function resize($width, $height);
    /**
     * @param integer $compression
     * @return Image
     */
    public function compress($compression);
    /**
     * @param string $type - One of Image::TYPE_JPEG, Image::TYPE_PNG or Image::TYPE_WEBP
     * @return Image
     */
    public function encodeTo($type);
}
namespace Kibo\Phast\Filters\Image\ImageAPIClient;

class Diagnostics implements \Kibo\Phast\Diagnostics\Diagnostics
{
    public function diagnose(array $config)
    {
        $package = \Kibo\Phast\Environment\Package::fromPackageClass(get_class($this));
        /** @var ImageFilter $filter */
        $filter = $package->getFactory()->make($config);
        $imageData = @"\211PNG\r\n\32\n\0\0\0\rIHDR\0\0\1h\0\0\1h\10\2\0\0\0\365\207\366\202\0\0\0\31tEXtSoftware\0Adobe ImageReadyq\311e<\0\0\3\$iTXtXML:com.adobe.xmp\0\0\0\0\0<?xpacket begin=\"\357\273\277\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?> <x:xmpmeta xmlns:x=\"adobe:ns:meta/\" x:xmptk=\"Adobe XMP Core 5.3-c011 66.145661, 2012/02/06-14:56:27        \"> <rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"> <rdf:Description rdf:about=\"\" xmlns:xmp=\"http://ns.adobe.com/xap/1.0/\" xmlns:xmpMM=\"http://ns.adobe.com/xap/1.0/mm/\" xmlns:stRef=\"http://ns.adobe.com/xap/1.0/sType/ResourceRef#\" xmp:CreatorTool=\"Adobe Photoshop CS6 (Macintosh)\" xmpMM:InstanceID=\"xmp.iid:0E913E46F5A911E5B20EF2CD3E8D574E\" xmpMM:DocumentID=\"xmp.did:0E913E47F5A911E5B20EF2CD3E8D574E\"> <xmpMM:DerivedFrom stRef:instanceID=\"xmp.iid:CCC4537FF57711E5B20EF2CD3E8D574E\" stRef:documentID=\"xmp.did:CCC45380F57711E5B20EF2CD3E8D574E\"/> </rdf:Description> </rdf:RDF> </x:xmpmeta> <?xpacket end=\"r\"?>\10\f\316\"\0\0!^IDATx\332\354\235{lT\327\235\307g\346\316\353\216I\370\3\\\330\215\f!\17\r\201\$\305\20\300\255\275y\264\252]\247\213\332\30\323f\225\332\33\233\0006*\304\260\305P\333\332\222\312\266\10T@q\4\256\223\340\10oTR\273\356\37\220&f\265\$\$8\261\351\202+S\10\243<xX\221\332uF-\221=\343y\334\231=\327\3.\1\0033\366=\257;\337\217PB\22\342\271s\317\275\237\363\373\235s~\347X//^d\1\0\200T\260\341\26\0\0 \16\0\0\304\1\0\2008\0\0\20\7\0\0\342\0\0\0\210\3\0\0q\0\0 \16\0\0\304\1\0\2008\0\0\0\342\0\0@\34\0\0^\330q\v@2(Ks\334?Y\307\370CG^j\322z{p\363\345\26GF\333\353\214/n\270\344\31\223\335nGY\271\363[\337\26\347z\202\365\277\210\371|\311\374I\353\324\251\212\327\313\370\362\310\207\342\25\225^\34\354\237\33\363YC\255\\+\2205\366\355M\322\32\0 U\2015,\361`0P\275\tY\0\2300\30\34\2055\0\2008`\rX\3@\34\260\206\201h\3\3\260\0060\4\214q\244\2235*V\307\7\7\321.\0\21\7\254\1k\0\210\3\326\2005\0\304\1kp'\322}|x\371S\260\6\2008`\215\24\254\21\334P\205F\1\20\7\254\1k\0\376`V\305\234\326\30i;\20n\332\203F\1\20\207\330\367\261x\2058\326\10\356\333\33i\335\217F\1HU\204FY\232\243\256[\17k\0D\34 \5kx\266\357\260\252*\367+\211\7\203#\257\265\302\32\0\342\2005R\260\6\226\223\3\244*\260\6\254\1 \16X\3\326\0\20\7\254!\2105\264\201\201\341\325\317\301\32\2001\30\343\220\333\32(B\1\2108`\rX\3@\34\260\0065\242}}\260\6@\252\2k\244\0\212P\0\"\16X\3\326\0\20\7\254\1k\0\244*@\34k\240\10\5 \342\2005`\r\0q\300\32\260\6@\252\2\4\261F<\30\f6\324G\217t\241E\0\304\1k\$k\r\24\241\0\244*\222\334\21\257\27\326\0\0\342H\1kf\246\332\270\215\2735b~?\254\1\220\252Hc\rOs\213\222\225\305\3672P\204\2\20q\300\32\260\6\2008`\rX\3\0\210C\34kD\272\217\303\32@\26\322}\214C\34k\240\10\5 \342\2005`\r\0q\300\32\324\10uv\302\32\0\251\n\254\221\2(B\1\2108`\rX\3@\34\260\6\254\1\0R\25A\254\201\"\24\200\210\3\326\2005\0\304\1k\300\32\0@\34\342XC_N\16k\0\263`\3761\16Q\254\201\345\344\0\21\7\254\1k\0\210\303\264\360\267\206\317\7k\0\244*2\241\356\332\315\327\32(B\1\2108\344\263\206#7\17\326\0\0\342\2005\0@\252bRk\214\264\35\0107\355\301\263\5 \16X#YP\204\2\220\252\300\32\260\6\0\246\2168\370ZC?\253\261iO\264\243\35\217\24\2008`\215d\255\201\345\344\0\251\212d8\312\312a\r\0 \216\324\254\241V\256\2055\0@\252\"\2075P\204\2\20q\310\207\2624\7\326\0\0\342H\315\32\236\355;`\r\0 \216\324\254aUU.\237\36\355\353\2035@\232#\337\30\20753\323]\275\231\2275P\204\2\200|\21\7\337\215y`\r\0\244\24\207g'\267-6\302G\272`\r\0\344KU\364\215y\274^.\37\35\352\354\fmk\304\343\2\200d\21\207kK\r\307\345\241\316\302B\222%\341q\1@&q8\312\312]EE\34/\300\252\252\236\346\26\270\3\0i\304\301w\241\327?.#+K\255G\266\2\200\f\342\260y\275\34\27z]\207=;\333]\337\200\207\6\0\241\305AR\3\265q\33\257%\33\343\342\314/ y\23\236\33\220\346\10=\253r\307\233o\txU\$o\212\376y\364H\27\236\36\200\210\3\244\342\216\332:ei\16\356\3\2008@*9\224\252\352\313\3361\311\2 \16\220\22JV\226\247\271\5\367\1@\34 ew\250\273v\343>\0\210\3\244\206#7\317\265\245\6\367\1@\34 5\\EE\230\240\5\20\7H\31\367\263e\230d\1\20\7H\r\275\222e\373\16\33\247\312]\0 \16\211\335\241/r\305\4-\2008@J\240\n\16@\34`\"\330\263\2631A\v \16\2202\216\334<L\262\0\210C2\342\301`\250\263\223\374\225\3435\250\225k\355\371\5x\266\0\304!\2155\2\325\233B\333\32\203\r\365|\257\4Up\0\342\220\311\32\211\363\237\243G\272F\332\16p\274\30}\222e\353\v\230d\1\20\207\320h\3\3C\305E\327\236\32\37n\332\23\351>\316\363\316N\233\206mJ\1\304!\2645\306=\2231\270\241\212\374'\216\27\246de\271kj\361\220\1\210C8n}\222\253\376\237\270\16\224\242\n\16@\34\302A\222\221\300\232U\2678\377\231\374\247@\365&\276\356@\25\34\2008\4\"\3113\31\265\336\236\221\327Z\371^\252Z\271\26\223,\0\342\340Op\337\336\221\272d\207\17\"\255\373\303\274\367\26F\25\34\2008\370[\203\270 \245\377\205X&\332\327\307\361\232Q\5\7 \16n\304\203\301\341u?I\325\32WtSW\303}\222\305\263\23\225,\0\342`n\215\261%^\23\371\337\7\7\2035[\370\16\224*^/\252\340\0\304\301\16}\261\306\$\254\221 \346\363q_\215\216*8\0q0\264F\305\352IZ#A\364HWp\337^\276_G\257\202+^\201\207\17@\0344\255\341\363\335b\211\327\4\210\264\356\347\273\32]w\307\272\365\230\240\5\20\7-\310\33>\\\362\214\201\326H\300}5:\252\340\0\304A\321\32\311,\361\232\30\$\212\211\371\375<o=\252\340\0\304A\3z\326\260\$&Y\266\376\234\363\$\v\252\340\0\304!\35ZoO\260i\17\337kp\344\346\271\353\33\360 \2\210C&\242\35\355\241\316N\276\327\340\314/\300\4-\2008\$#\264\255\221\377\$\v\252\340\0\304!\35#\215\r|'Y,\243Upp\7\2008dB\204\325\350VUuWo\306\$\v\2008d\"\346\363\5\2527\361\275\6T\301\1\210C>\364I\26\336\253\321Q\5\7 \16\371\20a5\272#7\317\271n=\332\2@\0342\241\257F\367\371\370^\203\273\244\24Up\0\342\220\214\300\306*\356\223,\250\202\3\20\207d\304\7\7G\266\277\310}\222\305\263}\7&Y\0\304!\23\"\254F\327\335\201*8\0q\310E\264\243\235\357\1\264\26T\301\1\210CF\270\37@kA\25\34\2008dD\204\325\350\250\202\3\20\207d\350\207H\362>\200\326\222\330\2464\277\0\315\1 \16\251\334\301{5\272\356\216\332:L\320\2\210C&DX\215\216*8\0q\310\207\10\7\320\352Up\315-h\v\0q\310\4\367\3h\23\356@\25\34\2008\$\203\373\1\264\26T\301\1\210C:DX\215n\31\255\202\303\4-\2008dB\37(\345}\0\255\356\216g\3130\311\2 \16\231\210\36\351\342\276\32\35Up\0\342\220\17\21V\243\243\n\16@\34\362\301\375\0ZKb\222\245\276\21m\1 \16\231\340~\0-\301\236\235\215*8\0q\310\204\10\7\320ZP\5\7 \16\351\320z{F^k\345~\31\250\202\3\20\207dDZ\367s?\200\326\202*8\0qHGh[#\367\325\350VUU\267\276\200I\26\0q\310\204\10\253\321m\323\246\241\n\16@\0342!\302\1\264\26T\301\1\210C:b>\237\10\253\321\35\271y\256-5h\16\0qHC\364H\27\367-\10\256\242\"L\320\2\210C&D8\200\326\202*8\0qH\207\10\7\320&\252\340l^/\232\3@\34\322\20\330X\305}5\272>A\333\270\r\23\264\0\342\220\6AV\243\243\n\16@\34\222!\302\1\264\226\321*8L\320\2\210C&\242\35\355\"\254Fw\344\346a\222\5@\0342\21\332\326(\302\$\v\252\340\0\304!\31\"\34@kA\25\34\2008\344B\220\3hQ\5\7 \16\t\335!\300\1\264\211*8\270\3@\34\322 \302\1\264\226\321\tZwM-\232\3@\34\322 \302\1\264\26T\301\1\210C:F\352j\271\257F\267\240\n\16@\34\322\21\330X%\304\$K\345ZL\262\0\210C\32\49\200\226\200*8\0q\310\204 \253\321Q\5\7 \16\311\210v\264s?\200\3262:\311\342\331\211J\26\0q\310\203\10\7\320\352\356\360zQ\5\7R\302\216[\300\227\340\206*\333\357~O\272}\276\227\341\310\315\213\226\225GZ\367\337\354\17\304.^`_\255G>\24O\210\364\342\20\241\312\323\224\4*V;W\256\342\37|\316\230y\253w\330\347\vm\303\276\36\340\n\326\313\213\27\341.\0\0R\353fp\v\0\0\20\7\0\0\342\0\0@\34\0\0\210\3\0\0q\0\0\0\304\1\0\2008\0\0\20\7\0\0\342\0\0@\34\0\0000>\334\252c\257=U\314z\327]\327UX)s\346X=\236\361U7k\226UU\223\377\240x0\30\273t\351\306\37=sf\354\367\332'\37[\276\374R\377M\337\251\370\340 \36\v\211\260ff*\331\vo\361\7b\27/\304\4\330\344\325l\267\335\360\"\267+F\270\363N\345\276\373\365\17\230\222\241\314\276{b\357<G\22\373\t'\344\242\235:\31\277|Y\353\355\301\343\302-0\366zm\263\357&\265fL\261\317\237?\261gi\254\v\321.^\210\17\r\243Y9\210\343\332\206\264\315\370\232mz\246\305\343\341\276\251\4mb~?y\362\264\363\347I\204\22;\335\217~\214\252)\224o\346*\367\336Kz\35\205\362\256\250\332\300@\354\322E\355\263\317\264\23'\340\21*\342P\226\346\270\2537\233^\20)y\$\372\347\323x\340\f\224\205#\347\33\312\334\271\34#S\22l\222H3\372\316Q\264\251a\342 i\210\247\276\1wm\33408z\352d\264\277?z\370\20FIR\202\364F\216e\313\224\7\346\211\326!%\3324\322\335\35\355hG3A\34\324\211\366\365Ez>\204An\33_8KJ\355\213\36\261M\233&E\257\20y\353\255\250\0\247\360A\34i\21\203\340i\273\21GY\271\363[\337V\$<\317\205\$\247\221c\307\302\257\276\214.\1\342\240\2373\17\f\204\17\37\272\305>\300\351\362\250ef:W\256r\26\26\3122\263v\v\"\335\307C\315\373\322yt\34\342@g\305\"+qUT:r\363\314\326%\370|\241\266\3\351\31QB\34\254\363\227PG{X\2003\334\240\f\3\3651\362RS\272M\301(?\273\353\237Sx\16\356\275\317\361\255o\343\375\237\270\247\35\16\373\327\277\356(Z\36w:c\3523wb\342Z_\245n\374\17\345\236{M.\307\351\323\235O>i\2337/v\341|\334\357\2078 \16j/\225\307\343X\274\230\350#\26\n\305\316\2365\337\27t\224\225\253u\377\351X\264\210\2102]^\244Y\263\34\205OZg\376\223v\374}\210\3\342\240\254\217\334<\373\243\217ig\317\230\246\247\"\271\211g\367\36\347w\voVjd\362p\362\201\7\354\337-\214\377\375\357\261O?\2058 \16\272\201.\351\251,w\334\241\235\350\225\375\2738\327\255W7\377\3146sfZ7\350\324\251\372;2m\272\271C\17\224\325\v\320S\251\252\273\2444\243\355ukf\246\274\201\6\271~\362-L0\325j\10\256\242\242\214\337\375^Y\232\3q\0\312\261\37y\367\16\374\227\214\217\232\275xEF\313+2.\350\242\333\240YY\236\355;\34e\345HU\220\252P\16=<\36\307\23O\304\206\206\$\0321u\3277\270K\377=}\6ASkP\207\303\261x\261m\336\274h\327\333\2108\0\335\264\305S\275Y\212n\212\$V\$=q^\263!\23\30\27Gn\36I[\344\315C!\16iP+\327\n\356\16\222R\351\211\25\322\223\344\323\226\346\0263\ry@\34\342\272C\330\347\214\\\30\311\336\305\257j\25\316\35\333w\230\306\35\20\207\270\210\371\234\221P\210\\\30fO&\230\207\232\305\35v\271.7\346\367\307\277\370b\354\37\343\201\200v\376\374\355\355\230\330\3340\361{y\366=\325\247i\2537\7*V\213S\27\247/\t\255\\\v\5L\322\35\201\352M\262\327\266\210+\216\304\16n\211\375\307\r\337\250:\261gjbwu\373\374\371\302\332\204\304\267\256\347\253F\352ja\r\270\3\342H\n\252\5\313DCc&\n]U\211\362\315\\\373\303\17+s\37\20*{w\346\27D\337{\217{\3556\254\1wH\234\252PL\202FU\22I\364\363\243\273`\212\263\253\235kM\5_q\330\363\vD\263F\"i\275r~\305\325cqn\214+\307v\341\27m\2Hvw@\34\343eI\275=\211\346\264\27\257p}\377\7\334\2379\222\260\220\16\237\327\36bD\243jm\235@\331\353\251\223\311j\364\253\214|\21e\311\22\373\203\17\361\335E\375Zw\2106\206\225\302\305\v\273\221O\240\256V\220\275\225\310\267v\225\224\362\325\7\351`\207\n9,\265\322\213PZ^\341\373\232\321\330\374\231t\t\216\334\\\373\302E\334\rBl8\\\362\214t\342\20w\311y\344\350QAj\223\311eD~\337\251\375\355o\372s\306im\265\325\343\211E\243\214\367\376\261ffzv\375\212W\276F\\\31~\373\355\340O7F\3368\250\361@\300\310\37~\366l\264\353\355\310\233\207c_~\251\334s\17\307M\0l\323\247[g\317\216\36=*\2278\260\216#\351~\257\243}\250\270H\343\267?\255\223y\225\220\273\246\226\313Y'D\31\301}{I\204\25\332\326H5\214'?\234\$\200\344\203\2\333_\324\6\6\270\265l~\201t\265p\20Gj\317\31\211*#\335\307\371\4\207^\257\215a\272\344\\\267\236\375^\241\372\236\254\235\235\344Mf<\240Cz\205\341\345O\21[\221\v\340\322\270\356g\313lR\255\337\2078R&\270\241\212\227;\354\254*\312\364\263>KJ\331\217e\220\230\216D\31\274ZV\217>\212\213\302<F\326\254\252\2526n\2038\340\16*8\226,e3\264\241n}\201q\240A\222\205\300\232U\334\347\27\310\5\214\324\325\6\352j\331\207\36\372b\277-5\20\207\371\335\301>+f3\263\343\256\251e9 Jn\343\360\352\347\204:\2375z\244\213\313x\226\253\250H\226J\26\210c\342\214l\321|\331\312\350<%\273\241\r\22\270\5*V\vx\$Zb<\213}\332\342\256\336\fq\230\34\255\267\207}\302Bu\10MOR\326\255g\366]\310kI\0027\221\227?\221\264%\270o/\22\26\210\303`B\315\373\30\2425c\n\305P\371\371*f\v\242\310\v)H\361\336mb\242\326\375\214\335\341,,\24\206\5\342\230\24\$\306\216\3661]\224e\237?\237^\22\304l\37@\362*Jt\n7cw\20w\273**!\16\223\23\351\371\320\34_\304\305j\376\225d(\22Y\203\213;\364\223\272\304\336\314\25\342\230,\332\7\335&\370\26\216\262r6S6\221\356\343Rd(\343\272\203\345X\251\213\371:\32\210\203u\266\22\223\377\0G\327\17\304B\262\3\3\301\rU\362\336%\242<f\231)\361\270\310A\7\304a\0\327\356f(\2455\266\3240X\270\21\17\6\2035[do\353`]\r\263\365;\"\7\35\20\207\21\257\204\241\205\233\214\261ff:\v\vY\274rM{\4\\\257\221r[\17\0162[\277#r\320\1q\30\21\201'\261a\262\2608W\256b0\5\33>\322%\324\332\320I5wo\317H\333\2014\17: \16\331\236\332\213\27\f\26\7\375p#\346\367\207~\265\333L\255\20n\332\303fA:\t:\304\\\204\16q\30\321\272s\346\260\v\225\207\206\r\374i\216\262r\6\341\306\310\256\2352\356\216w\233/\365R\23\243\220\360\351\247!\16s\302r\377(\355\324I##a\372\223)\321\276>A\266\2004<a\tuv2\370 Gn\236\200\347\316B\34F\334\304Y\263\330=\257}\247\214\372Q\366\374\2\6\223)#;i\326v\17\277\3722\233\352{\307\323\377\6q\230.OY\232\303\254\276\203\344\325\6\306\374\316\345\305\324_\255#]&\230I\271i\332888\362Z+\vq<\376\4\304a:q,Y\302\354\263\"'z\rkx\257\327\236\235M\367\275\n\6M6&:N\213\264\356g\260\374O\311\312\22m\210\24\342\230t\277\375\344\367\230}\226\201\203\5\f\26\10D\336\317|c\2427\22\372\355\33,\202\216e\313 \16\363\300f\230`,O10\354g\340\2730\253\305\16\351\20t\330\27=\2q\230\7\226\353sB\306\275\207\$\356\245\355\273H\367q\23\217n\\\257\310?\274I\375E\2356M\250l\5\342\230D\247\275n=\263\343\335\264\201\1\3\363\24\6qo\370\340\301\364y\22\"\7\303`zE\250l\5\342\230x\247\355*^\301.\334\370u\263Dq/\321\234\274\347\260O\200\370\340`\324\320\3655\342g+\20\307D\260ff\272\2537\263\234\20550\334`\221\247\274\373N\272=\22\f\",\322j\342l)\10qL\304\32\236\346\26fg#\352\325\350\365\27702V\242?LB\367t{*H\204\305\240\334^\234bY\210Chk\350IJG\273\261\243\214\264W\23E\373\372\322a\26\226K\234e\360!\210C>H\220\317\330\32\344%\f7\3551V|\264\257\3374\233\260\246\334X\364Kr\224\271s!\16\311p\256[\357\331\276\203\2455\364\215\366\352\f>bCy\354q\352\357\317\341C\351\371\204\220\300\220v\266bUUA&e!\216\244\2\215\214\266\327\335%\245\314FC-W7\3323<\346\267/X@[v\351\231\247\\\221\346\37\377H\375\215\2357O\204oj\207\27n\325H^\257\253\242\222\345\221\210c\326\10To\242\261\200Jy`\236\354o\216\320\342x\347\250\253\250\210\356\33\373\360\303\21\210CX\354\371\5\216\302B\366\312\30\263\6\245u\20\264S-\215\376r\6\221!\255F\232\217jdj\233.\304\336\34\20\307\365!\206~\240\331\223\337cy\\;3k0\230\3143\345\236=\251\271\343\3349\252e\307\212\30K9 \216+o\224\375\321GI\30\317r\354s\334\1\202`\315\26z%\36\312\302Et\257?m\212Sn\245\316?\237\246\275_\201\2624\207\373\302\334t\24\207>%\231\275\220\4\27\266\0313\270\313b\f\375\210\263\306\6\252#\213\266\31_\243\373\316\2349\3q0(\355\263\222'\26\342\270U\326`\304\17I\34\357n\235\222\241\314\276[\234H\357\272\364\$\324\321n\354z\215\361\357\306\254\331t#\216O>\2068\364d\255\276\201n\304q\337\375Q\244*7\303-\366\331\231\6\246'#\333_d\23y\322\216\255b\247\373!\216D\233R\275\325\264#G\244*B\303,\320\30K\214i\235\30\3068\22\2\275t\221\2568\4\230X\2018\370\300`D\343\372\304x\352T\312o\313%4\353\225[\361\327\377\243\234r\316\342\376\35\261r\224y\34\353\363\r\225<\23\334P\305x\205%\365)\25\243\217\230\223\270\211)/fa\271\202\31\21\207\20QF\370\340A^\23i\326)\31tS\25C\217\230\223;\t\275|\231z\207\357\365\362M\f!\16\372\217Q0\30y\377\275p\333\1\276-\235\230T\222\267\233\225)\342\240\3377\330f\337\rq\230\226h__\244\347\303H\353~\334\2124\354-DH( \16\371|\21=|H\250:Q\332#j\6\236Mi\2b\227.Q]1d\275\353.\210\303TD\272\217G\373\373\265\17\272E\253.\247\335\1\246s5=\207n`\306L\210\303T8r\363\364\232\332\312\2651\277_;\367\21\221\210h\241\7`\200v\361\202\200k\224!\16\31\372\204i\323lW%\242\371|\221\23\275\372\351\33\234\fB{\365\27\312\333\256\217\277\314>\307\4q0yo\275^\362\313]R\312k\270\224\366\352/\220v\375\"n\1SOgg\253\225k\3578\366\276kK\215\315\324\241,\2008\200\321\375\277\252\272\212\212\246\264\275\256\356\332\r}\0\210\3\244\206#7\17\3720%\246\337a\0\342\20E\37\356\372\6kf\246\244_!\366\5\246\215\276\312\227_B\34\200\5\316\374\202)\35\235\216\262r)\305A\271\36\24@\34\340\246XUU\255\\\233\321\366:2\27\351\233\222\367\312N\210#\355P\274\336\214\226W\354\305+dz\214\4\330\223J\260\0332\323\344_\20m,f\350\341\251\336\354\246\274u\245\221\217\321\364L\264\32R\25 \4\316\374\2\222\266\310;b\nL\214\270+G\3u\265\206\234\3563v\n\21I;\23\1\244}\376|\213\220\333\235\217\233\266x\232[\250\36\266\2\200\251\304a\0247\332'4\226\21\\=`E\271\347\36\333\254\331\202\34\260r\275;\262\2622Z^\241w\274\33\220\221\330_\377\2qp#>8\250k\345\252Yt\217<\366\270}\301\2\373\242Gx\35\19.\372\220\307\366\35B\273\303\343\301\313\374\225\367j4\252\245\370\350~\3769\304!\222G:\332\311/\313\325Cd\35\217?!H\30B\334\341\256\336\34\250X=\261\372Z\332\273`\212\31\254\1z`p\364&\241\240\317\27n\3323\274\374\251\341u?\211t\37\217\7\203\"\344,\236\346\226\211\215\225\"\3151Y\10\306`?d\210cR\220W.\270\241j\250\270(\324\331\311]\37\304\35\356\232ZA\273 ,Zc\30\202q\357\t \216d\263\230\320\266F\21\364\241\35706\241e\351\264/\333Fy\27u\211H\207\31t\210#e}\f\257~.\332\327\307\3612\334\317\226M\240{\247}\322\232\351\27Y\247\20nd/\244\33n\01007\17q\244L\314\347\v\254Y\25\334\267\227W\350\241\227\264\324\375\247p]\220\331\27Y\303\241\20\207\1DZ\367\7\2527\305\374~>}\232\327\233j1K\364\314\31\272\2274g\16\236\n6\16\245\335\224\20\7]\264\336\236\341\322\37k\3\3|\22\226\225\317\211\325\315N\237\216G\"\1\365E\34\303C\20\207\334\304\7\7\3\25\253\271\270\3036mZJ\243\244\264\217h\304R\16f\16\215a\214\3034\356\3402\336\341\372\341\217R\270N\3723\377ceAim\215\314L\332\313\216E84\17\3420\306\35\301\206z.AG\362#\35,NB\306R\16\372S*\244\213\22\341|/\210\303\30\242G\272B\235\235\354?\327\371\235\374\24z*\312)\225\375\301\207\360\$(\v\27\321\315S(O\253C\34\254\t\277\3722\373I\26{vv\362\313\215b\227.\322}\230(\237k-\5\264GFE\230R\2018\fNXB\277}\203\303\223\372\257\313\222\2158>\373\214v\352\204l\205\366>/\202\34\274\0q\30I\244u?\373QRG\3167\222\2158\350\217\306\247\371\370(\203\235bc\247\373!\0163\272\343\375\367Xwqs\347&\33\345\32\261\243\332m\336\234\364\36\346\260/X@\327\32~\277 {\301A\34F\213\343\320!\306\237hU\325\344\23\4\352\343\243\251\214\271\230P\34\213\36\241\233\247\234\373H\220o\nq\30\335\264\275=\354\263\25\333C\17'{y\37\235\245\376\362\$=\346b2\224\2459\264WpD\373\373\5\371\262\20\7\205x\222\371\204\231r\337\375\311>y\372\23\355\213I~\314\305d8\226Q7\246\366A7\304a\336\240\343\342\5\326\255\230\364yH\332\261w\251G\34\351\232\255\320\316S\304\31\340\2008\250\20\37\32f\335\212I\237\207\24\37\34d\260\233C\32f+\366\374\2\352y\312\311\377\25\347\373B\34\24z\6\336[\327\337\232\310\211^\332\37\341L?q8\n\vi\4\2034\23\342\340\32q\360\336\272\3766\317\37\375IY%++\255\26t\220\324\314\221\233G\367\241\n\6\23\373\357C\34\200S@\344\3631\330\7\200A\17,\16\316\225\253\250\353\236\362\256\10\20\7h\2279\31\220\255\274\373\16uq\344\346\245\317\362s\307c\217Qo\262\356n\241\2762\304\221\2160\310V\10\256\212\312\264\260FY9\355a\321\230\337/T\236\2q\320\271\247IO\216\232<[I\217\240#\245\355\224&(z\221\346S \16j\367t\326l\306\237\30\17\4R\375_\302\207Y,\2157}\320\301 \334\320\33\253\355\0\304a~\330\357\276\251\235?\237r'v\370\20\203\245\361\$\3500\361\364\21253\323\375l\31\365\306\365\371\304Y\367\5q\320\202Aa\3658\21G\352\333^\353'l3\31\250w\225\224\232\265\255\235+WYU\225z\270q\364D\f\253\361\252\33\335\307\346\262\377\320\211\365H\341\203\7Y\304_^\257s\335z\23\306\225Ks\\EE\324[\326\357\217\264\356\2078L\16\211]\355<\346b'6K\242\365\366\2609L\320U\274\302|\243\244\356\352\315\f>%\374\2077\305\374\372\20\207|\261\353\365\357\377\$\346GBLF\335\304<\263rR*\334R\303`\$+\36\fF\16\376\6\3420\270\341\344\261\\r2[l\220P\205\315\6\313\$a!/\33\222\224\324\302\215\267\336\22\341\$\4\210\203r/\364|\25\373p\3032\351\332'f\33,\223\227\315\0043,\244{P\267\276\300\340\203H\270\21~\365ea\357\3\304a\f\344\225p\362x+&_\373\24i\335\317\354T\7\265\266N\366\301\16\317\316\335\f\26n\10\36n@\34\306\365B\265u\\>\332\220)\325\221];\31\335(UU\33\267\311\273\315\217\272k\267\302D|D\345\"\207\33\20\2071\326\3604\267pIR,\6M\251F\217ti\254\226\30)YY\372\355\222\320\35\316u\353i\327\316_\233?\212\34n@\34\306X\203\327A\355\344m7\352D\330\221\227\232\230]\266\214\356p\224\225\273Y\255d#\315*\346\332\r\210\303\f\326\260\30\272\246\220\10(\322}\34\356\270\2315\324\312\265\314>\216\245\304!\16\326\330\363\v\246ttr\264\20660`l\2774\322\330\300\362`\7Y\334\341\256o`i\2150I\33\r\212\"!\16\341pm\251\361\3247\360\32\327\270\222\6\377\272\331\330\37H\222\352`\323\36\226_\201\270C\227\357\322\34a#Ju\327n\226\223e1\277?\364\253\335R\274\2\20G\352\201\306[]l\326\377\334\202h_\37\215\315x\242\35\355\32\333BL\"\337\214\246\227\4,f!:#\1\21\263\321\320+A\337\256\235\202\217\211\376\343E\200\v\222W\206\253\244T\21`\31\2I(Fv\376\222\322\17\17l\254\"Q\0\343`\312]Rj\360\241`]\215 \257\r\21\231\253x\5\343\233@\222\0246;\263!\342`\204\243\254<\243\355u\222\233(b,^\nu\264\323\333\240\201}\302r\305\313\331\331DX\334C\17\233\327K\332\232\210\214\2615\264\201\201\221\272Z\231\372Qx\341\26\301\252c\3312\307\277<\312w,\343\306\$%L\371\305&\tK\$7\227q\224\236H[\310\33\353x\374\211\320\257\233\331\367\275\326\314L\327\363U\274\226\377\6k\266H\26\200C\20\327==\312c\217\333\27,\260/z\204\315\312\342\224\210\371\375\$\236g\221l76\3308\3154\353\263-\365\rZIi\250\355\0\33}\350\325\211+W9\v\vy\365\20\$\304\23p\217/\210\343\366\221\205m\336<\345\336{\225\7\346q\234^M\252_\332\372s6\243\0z\302R\263%\243\345\25^\357\22\311\n\211>b\0336\206~\373\206\276\313!\235oM\232\336\371\364\323\354c\253\257\$\236\235\235\242\355`\236\224m//Na\343\31{~\1iN6W\26\351>\256}\366\331\230\211\265\276S\223|zH\372j\233}\267\345\352\271'\366\371\363-\36\217\310\246\270\216@]-\343\0\236es\3376A\213\364|h\224A\22I\250\10A%y\310\203\33\252\244\214\315\205\25G2q{\374\213/n\363\365\246O\0270\343\230H4\273o/\227e\310\214\27M\336\26m`@\373\350\254\366\351\247\261\263g\223_(\245g\240\331\vI\207\241\314\231\243\314\235+\310\240\25\371.\303\313\237\222\364\201\2248U\321\215`\n)\10k\r\313h\321=I\342\234\302\354\243A\"\304k\203\304D\347\21\17\4n\334\347\335:%CI\4\230B\26\362\23k\4*V\313\373Lb\214Ch\364%\33\257\265\362-y\32\251\253\265fd\360\35\10\270m\347a\317\316\226\250Y\23\326\220e\255\327\370w\36/\247\310\326\10To\22\241P\222\344\341,K\340\314\215\t\254\1q\10\375x\r\257~N\234z'\270\3\326\2008D\207\274\242\344\361\22mn\37\356\2005 \16\201\323\223\355/\222WT\314\307\v\356\2300\321\276>\323X\3\342\20\254G\362\371Hz\"\370r \342\216\21\361\316@\226 \204\\\263\3124\326\260`VE\234@#\324\321\36\346Q]6\1\310u\306\207\206\334\317\226\tU\305#\256j\371\315\246C\34&\357\216\364\335\267\244\352\216\364C\25\316\236U\267\276`K\217\2454\23C\257-\332\372s)v\364B\252\"Yn\22\250\253\25vD\3436\27\337\3333\\\372c\222\272\243\35\307\205\334\31rLi\rD\34<\225\301\254\372\223b\20658HRw.\333\336\10\236xr_\266\7q\230\260#\n\377\256Cve\\K\270i\217v\342\204\273z\263D\25\203T\273\204`\375/\244+\223\2078\304\355\205\"\357\277\27n;`\312GJO[\226?\225\346\241G\314\357\37y\365\25\31k\344!\16QC\214\377>\222\16\317\23\t=H\$\345\336\370S\271*G\f!\324\331\31~\365e3M\270B\34\334|a\340\26\22\322\364\272>_`\315*}c\3475\25i\222\271D\272\217\207\232\367\231>7\2018\350\6\253\332\271\217\"\335\335\332\261w\323\312\27\327Kst\303nGY\271\353\207?2\361|mz*\3\3420R\26\321\376~\355\203\356\364|\206n\372^\265\356'\277L\251\217tV\6\3041A\342\301`\354\322\245\350\2313\332'\37\247yd\221\274>\354\305+\\\337\377\201\230{\352\244\324ID\216\35K\253\261\f\210cR\232\320.^\210\17\rk\247NN~\353\3234M^:\332\311/\233\327\353,)\25\355\304\211\244\256?mF\270\223\$\265=G-\243\333\216\352\273\363N\345\276\373\311\337m3\276f\233\256\237\33,\373\356\236c;\230\222P\202\374\2258\"\221\253\343\21\241\322_\25\257p~'_\374\311\227\364\34\341\246\"\216\24\344r\215_\22\214Y\346\37\377f\326,\252\235\317u\33\32'\2\207+\277\37U\3\354\300\363\341\33=\305\306\221\233k_\270H\234\30\204\304\230\321S'\243\375\375\360\5kq\30\205\2624\307:uj2\22\31\204\354\220\266V\226,\261?\370\20\227]\310\211,\264s\347\242>\255\2358a\326\352\2224\22\7H[\211\330\346\315\263\315\230i\237?\237RL\252\r\f\220PT;^\373\344\343\330\351~L\207A\34\300\204\$\222\337\304IZ\312\2349V\217\347\212bn>MC\324`\t\4\256d\243\243\343V\261\277\376%\376\371\347\261\213\27\240\t\210\3\0\300\1\354\307\1\0\2008\0\0\20\7\0\0\342\0\0@\34\0\0\210\3\0\0 \16\0\0\304\1\0\2008\0\0\222\362\377\2\f\0\330R\221^i(\247\250\0\0\0\0IEND\256B`\202";
        if ($imageData === false) {
            throw new \Kibo\Phast\Exceptions\RuntimeException('Could not read testing image for ' . static::class . ' diagnostics.');
        }
        $image = new \Kibo\Phast\Filters\Image\ImageImplementations\DummyImage();
        $image->setImageString($imageData);
        $filter->transformImage($image, []);
    }
}
namespace Kibo\Phast\Filters\Image;

class ImageFactory
{
    private $config;
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    /**
     * @param URL $url
     * @return Image
     */
    public function getForURL(\Kibo\Phast\ValueObjects\URL $url)
    {
        $retriever = new \Kibo\Phast\Retrievers\UniversalRetriever();
        $retriever->addRetriever(new \Kibo\Phast\Retrievers\LocalRetriever($this->config['retrieverMap']));
        $retriever->addRetriever((new \Kibo\Phast\Retrievers\RemoteRetrieverFactory())->make($this->config));
        return new \Kibo\Phast\Filters\Image\ImageImplementations\DefaultImage($url, $retriever);
    }
    /**
     * @param Resource $resource
     * @return Image
     */
    public function getForResource(\Kibo\Phast\ValueObjects\Resource $resource)
    {
        return $this->getForURL($resource->getUrl());
    }
}
namespace Kibo\Phast\Filters\Image;

interface ImageFilter
{
    /**
     * @param array $request
     * @return string
     */
    public function getCacheSalt(array $request);
    /**
     * @param Image $image
     * @param array $request
     * @return Image
     */
    public function transformImage(\Kibo\Phast\Filters\Image\Image $image, array $request);
}
namespace Kibo\Phast\Filters\Image;

interface ImageFilterFactory
{
    /**
     * @param array $config
     * @return ImageFilter
     */
    public function make(array $config);
}
namespace Kibo\Phast\Filters\Image\ImageImplementations;

abstract class BaseImage
{
    /**
     * @var integer
     */
    protected $width;
    /**
     * @var integer
     */
    protected $height;
    /**
     * @var integer
     */
    protected $compression;
    /**
     * @var string
     */
    protected $type;
    /**
     * @return string
     */
    public abstract function getAsString();
    /**
     * @return integer
     */
    public function getSizeAsString()
    {
        return strlen($this->getAsString());
    }
    /**
     * @param $width
     * @param $height
     * @return static
     */
    public function resize($width, $height)
    {
        $im = clone $this;
        $im->width = $width;
        $im->height = $height;
        return $im;
    }
    /**
     * @param $compression
     * @return static
     */
    public function compress($compression)
    {
        $im = clone $this;
        $im->compression = $compression;
        return $im;
    }
    /**
     * @param $type
     * @return static
     */
    public function encodeTo($type)
    {
        $im = clone $this;
        $im->type = $type;
        return $im;
    }
}
namespace Kibo\Phast\Filters\Image\ImageImplementations;

class DefaultImage extends \Kibo\Phast\Filters\Image\ImageImplementations\BaseImage implements \Kibo\Phast\Filters\Image\Image
{
    /**
     * @var URL
     */
    private $imageURL;
    /**
     * @var Retriever
     */
    private $retriever;
    /**
     * @var string
     */
    private $imageString;
    /**
     * @var array
     */
    private $imageInfo;
    /**
     * @var ObjectifiedFunctions
     */
    private $funcs;
    public function __construct(\Kibo\Phast\ValueObjects\URL $imageURL, \Kibo\Phast\Retrievers\Retriever $retriever, \Kibo\Phast\Common\ObjectifiedFunctions $funcs = null)
    {
        $this->imageURL = $imageURL;
        $this->retriever = $retriever;
        $this->funcs = is_null($funcs) ? new \Kibo\Phast\Common\ObjectifiedFunctions() : $funcs;
    }
    public function getWidth()
    {
        return isset($this->width) ? $this->width : $this->getImageInfo()[0];
    }
    public function getHeight()
    {
        return isset($this->height) ? $this->height : $this->getImageInfo()[1];
    }
    public function getType()
    {
        if (isset($this->type)) {
            return $this->type;
        }
        $type = @image_type_to_mime_type($this->getImageInfo()[2]);
        if (!$type) {
            throw new \Kibo\Phast\Filters\Image\Exceptions\ImageProcessingException('Could not determine image type');
        }
        return $type;
    }
    public function getAsString()
    {
        return $this->getImageString();
    }
    private function getImageString()
    {
        if (!isset($this->imageString)) {
            $imageString = $this->retriever->retrieve($this->imageURL);
            if ($imageString === false) {
                throw new \Kibo\Phast\Exceptions\ItemNotFoundException('Could not find image: ' . $this->imageURL, 0, null, $this->imageURL);
            }
            $this->imageString = $imageString;
        }
        return $this->imageString;
    }
    private function getImageInfo()
    {
        if (!isset($this->imageInfo)) {
            if ($this->getImageString() === '') {
                throw new \Kibo\Phast\Filters\Image\Exceptions\ImageProcessingException('Image is empty');
            }
            $imageInfo = @getimagesizefromstring($this->getImageString());
            if ($imageInfo === false) {
                throw new \Kibo\Phast\Filters\Image\Exceptions\ImageProcessingException('Could not read GD image info');
            }
            $this->imageInfo = $imageInfo;
        }
        return $this->imageInfo;
    }
    protected function __clone()
    {
        throw new \Kibo\Phast\Exceptions\LogicException('No operations may be performed on DefaultImage');
    }
}
namespace Kibo\Phast\Filters\Image\ImageImplementations;

class DummyImage extends \Kibo\Phast\Filters\Image\ImageImplementations\BaseImage implements \Kibo\Phast\Filters\Image\Image
{
    /**
     * @var string
     */
    private $imageString;
    private $transformationString;
    /**
     * DummyImage constructor.
     *
     * @param int $width
     * @param int $height
     */
    public function __construct($width = null, $height = null)
    {
        $this->width = $width;
        $this->height = $height;
    }
    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }
    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }
    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }
    /**
     * @return int
     */
    public function getCompression()
    {
        return $this->compression;
    }
    /**
     * @return string
     */
    public function getAsString()
    {
        return $this->imageString;
    }
    /**
     * @param string $imageString
     */
    public function setImageString($imageString)
    {
        $this->imageString = $imageString;
    }
    /**
     * @param mixed $transformationString
     */
    public function setTransformationString($transformationString)
    {
        $this->transformationString = $transformationString;
    }
    protected function __clone()
    {
        $this->imageString = $this->transformationString;
    }
}
namespace Kibo\Phast\Filters\Text\Decode;

class Factory
{
    public function make()
    {
        return new \Kibo\Phast\Filters\Text\Decode\Filter();
    }
}
namespace Kibo\Phast\HTTP;

interface Client
{
    /**
     * Retrieve a URL using the GET HTTP method
     *
     * @param URL $url
     * @param array $headers - headers to send in headerName => headerValue format
     * @return Response
     * @throws \Exception
     */
    public function get(\Kibo\Phast\ValueObjects\URL $url, array $headers = []);
    /**
     * Send data to a URL using the POST HTTP method
     *
     * @param URL $url
     * @param array|string $data - if array, it will be encoded as form data, if string - will be sent as is
     * @param array $headers - headers to send in headerName => headerValue format
     * @return Response
     * @throws \Exception
     */
    public function post(\Kibo\Phast\ValueObjects\URL $url, $data, array $headers = []);
}
namespace Kibo\Phast\HTTP;

class ClientFactory
{
    const CONFIG_KEY = 'httpClient';
    /**
     * @param array $config
     * @return Client
     */
    public function make(array $config)
    {
        $spec = $config[self::CONFIG_KEY];
        if (is_callable($spec)) {
            $client = $spec();
        } elseif (class_exists($spec)) {
            $client = new $spec();
        } else {
            throw new \Kibo\Phast\Exceptions\RuntimeException(self::CONFIG_KEY . ' config value must be either callable or a class name');
        }
        return $client;
    }
}
namespace Kibo\Phast\HTTP\Exceptions;

class HTTPError extends \RuntimeException
{
}
namespace Kibo\Phast\HTTP\Exceptions;

class NetworkError extends \RuntimeException
{
}
namespace Kibo\Phast\HTTP;

class Request
{
    /**
     * @var array
     */
    private $env;
    /**
     * @var array
     */
    private $cookie;
    /**
     * @var string
     */
    private $query;
    private function __construct()
    {
    }
    public static function fromGlobals()
    {
        $instance = new self();
        $instance->env = $_SERVER;
        $instance->cookie = $_COOKIE;
        return $instance;
    }
    public static function fromArray(array $get = [], array $env = [], array $cookie = [])
    {
        if ($get) {
            $url = isset($env['REQUEST_URI']) ? $env['REQUEST_URI'] : '';
            $env['REQUEST_URI'] = \Kibo\Phast\ValueObjects\URL::fromString($url)->withQuery(http_build_query($get))->toString();
        }
        $instance = new self();
        $instance->env = $env;
        $instance->cookie = $cookie;
        return $instance;
    }
    /**
     * @return array
     */
    public function getGet()
    {
        return $this->getQuery()->toAssoc();
    }
    /**
     * @return Query
     */
    public function getQuery()
    {
        return \Kibo\Phast\ValueObjects\Query::fromString((string) $this->getQueryString());
    }
    /**
     * @param $name string
     * @return string|null
     */
    public function getHeader($name)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->getEnvValue($key);
    }
    public function getPathInfo()
    {
        $pathInfo = $this->getEnvValue('PATH_INFO');
        if ($pathInfo) {
            return $pathInfo;
        }
        $path = parse_url($this->getEnvValue('REQUEST_URI'), PHP_URL_PATH);
        if (preg_match('~[^/]\\.php(/.*)~', $path, $match)) {
            return $match[1];
        }
    }
    public function getCookie($name)
    {
        if (isset($this->cookie[$name])) {
            return $this->cookie[$name];
        }
    }
    public function getQueryString()
    {
        $parsed = parse_url($this->getEnvValue('REQUEST_URI'));
        if (isset($parsed['query'])) {
            return $parsed['query'];
        }
    }
    public function getAbsoluteURI()
    {
        return ($this->getEnvValue('HTTPS') ? 'https' : 'http') . '://' . $this->getHost() . $this->getURI();
    }
    public function getHost()
    {
        return $this->getHeader('Host');
    }
    public function getURI()
    {
        return $this->getEnvValue('REQUEST_URI');
    }
    public function getEnvValue($key)
    {
        if (isset($this->env[$key])) {
            return $this->env[$key];
        }
    }
    public function getDocumentRoot()
    {
        $scriptName = (string) $this->getEnvValue('SCRIPT_NAME');
        $scriptFilename = $this->normalizePath((string) $this->getEnvValue('SCRIPT_FILENAME'));
        if (strpos($scriptName, '/') === 0 && $this->isAbsolutePath($scriptFilename) && $this->isSuffix($scriptName, $scriptFilename)) {
            return substr($scriptFilename, 0, strlen($scriptFilename) - strlen($scriptName));
        }
        return $this->getEnvValue('DOCUMENT_ROOT');
    }
    private function normalizePath($path)
    {
        return str_replace('\\', '/', $path);
    }
    private function isAbsolutePath($path)
    {
        return preg_match('~^/|^[a-z]:/~i', $path);
    }
    private function isSuffix($suffix, $string)
    {
        return substr($string, -strlen($suffix)) === $suffix;
    }
    public function isCloudflare()
    {
        return !!$this->getHeader('CF-Ray');
    }
}
namespace Kibo\Phast\HTTP;

class Response
{
    /**
     * @var int
     */
    private $code = 200;
    /**
     * @var array
     */
    private $headers = [];
    /**
     * @var string|iterable
     */
    private $content;
    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }
    /**
     * @param int $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }
    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }
    /**
     * @param string $name
     * @return string|null
     */
    public function getHeader($name)
    {
        foreach ($this->headers as $k => $v) {
            if (strcasecmp($name, $k) === 0) {
                return $v;
            }
        }
        return null;
    }
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }
    /**
     * @param $name
     * @param $value
     */
    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }
    /**
     * @return string|iterable
     */
    public function getContent()
    {
        return $this->content;
    }
    /**
     * @param string|iterable $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }
    public function isCompressible()
    {
        return strpos($this->getHeader('Content-Type'), 'image/') === false;
    }
}
namespace Kibo\Phast\JSMin;

/**
 * JSMin.php - modified PHP implementation of Douglas Crockford's JSMin.
 *
 * <code>
 * $minifiedJs = JSMin::minify($js);
 * </code>
 *
 * This is a modified port of jsmin.c. Improvements:
 *
 * Does not choke on some regexp literals containing quote characters. E.g. /'/
 *
 * Spaces are preserved after some add/sub operators, so they are not mistakenly
 * converted to post-inc/dec. E.g. a + ++b -> a+ ++b
 *
 * Preserves multi-line comments that begin with /*!
 *
 * PHP 5 or higher is required.
 *
 * Permission is hereby granted to use this version of the library under the
 * same terms as jsmin.c, which has the following license:
 *
 * --
 * Copyright (c) 2002 Douglas Crockford  (www.crockford.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * The Software shall be used for Good, not Evil.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * --
 *
 * @package JSMin
 * @author Ryan Grove <ryan@wonko.com> (PHP port)
 * @author Steve Clay <steve@mrclay.org> (modifications + cleanup)
 * @author Andrea Giammarchi <http://www.3site.eu> (spaceBeforeRegExp)
 * @copyright 2002 Douglas Crockford <douglas@crockford.com> (jsmin.c)
 * @copyright 2008 Ryan Grove <ryan@wonko.com> (PHP port)
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @link http://code.google.com/p/jsmin-php/
 */
class JSMin
{
    const ACTION_KEEP_A = 1;
    const ACTION_DELETE_A = 2;
    const ACTION_DELETE_A_B = 3;
    protected $a = "\n";
    protected $b = '';
    protected $input = '';
    protected $inputIndex = 0;
    protected $inputLength = 0;
    protected $lookAhead = null;
    protected $output = '';
    protected $lastByteOut = '';
    protected $keptComment = '';
    /**
     * Minify Javascript.
     *
     * @param string $js Javascript to be minified
     *
     * @return string
     */
    public static function minify($js)
    {
        $jsmin = new \Kibo\Phast\JSMin\JSMin($js);
        return $jsmin->min();
    }
    /**
     * @param string $input
     */
    public function __construct($input)
    {
        $this->input = $input;
    }
    /**
     * Perform minification, return result
     *
     * @return string
     */
    public function min()
    {
        if ($this->output !== '') {
            // min already run
            return $this->output;
        }
        $mbIntEnc = null;
        if (function_exists('mb_strlen') && (int) ini_get('mbstring.func_overload') & 2) {
            $mbIntEnc = mb_internal_encoding();
            mb_internal_encoding('8bit');
        }
        if (isset($this->input[0]) && $this->input[0] === "\357") {
            $this->input = substr($this->input, 3);
        }
        $this->input = str_replace("\r\n", "\n", $this->input);
        $this->inputLength = strlen($this->input);
        $this->action(self::ACTION_DELETE_A_B);
        while ($this->a !== null) {
            // determine next command
            $command = self::ACTION_KEEP_A;
            // default
            if ($this->isWhiteSpace($this->a)) {
                if (($this->lastByteOut === '+' || $this->lastByteOut === '-') && $this->b === $this->lastByteOut) {
                    // Don't delete this space. If we do, the addition/subtraction
                    // could be parsed as a post-increment
                } elseif (!$this->isAlphaNum($this->b)) {
                    $command = self::ACTION_DELETE_A;
                }
            } elseif ($this->isLineTerminator($this->a)) {
                if ($this->isWhiteSpace($this->b)) {
                    $command = self::ACTION_DELETE_A_B;
                    // in case of mbstring.func_overload & 2, must check for null b,
                    // otherwise mb_strpos will give WARNING
                } elseif ($this->b === null || false === strpos('{[(+-!~"\'`', $this->b) && !$this->isAlphaNum($this->b)) {
                    $command = self::ACTION_DELETE_A;
                }
            } elseif (!$this->isAlphaNum($this->a)) {
                if ($this->isWhiteSpace($this->b) || $this->isLineTerminator($this->b) && false === strpos('}])+-"\'', $this->a)) {
                    $command = self::ACTION_DELETE_A_B;
                }
            }
            $this->action($command);
        }
        $this->output = trim($this->output);
        if ($mbIntEnc !== null) {
            mb_internal_encoding($mbIntEnc);
        }
        return $this->output;
    }
    /**
     * ACTION_KEEP_A = Output A. Copy B to A. Get the next B.
     * ACTION_DELETE_A = Copy B to A. Get the next B.
     * ACTION_DELETE_A_B = Get the next B.
     *
     * @param int $command
     * @throws UnterminatedRegExpException|UnterminatedStringException
     */
    protected function action($command)
    {
        // make sure we don't compress "a + ++b" to "a+++b", etc.
        if ($command === self::ACTION_DELETE_A_B && $this->b === ' ' && ($this->a === '+' || $this->a === '-')) {
            // Note: we're at an addition/substraction operator; the inputIndex
            // will certainly be a valid index
            if ($this->input[$this->inputIndex] === $this->a) {
                // This is "+ +" or "- -". Don't delete the space.
                $command = self::ACTION_KEEP_A;
            }
        }
        switch ($command) {
            case self::ACTION_KEEP_A:
                // 1
                $this->output .= $this->a;
                if ($this->keptComment) {
                    $this->output = rtrim($this->output, "\n");
                    $this->output .= $this->keptComment;
                    $this->keptComment = '';
                }
                $this->lastByteOut = $this->a;
            // fallthrough intentional
            case self::ACTION_DELETE_A:
                // 2
                $this->a = $this->b;
                if ($this->a === "'" || $this->a === '"' || $this->a === '`') {
                    // string/template literal
                    $delimiter = $this->a;
                    $str = $this->a;
                    // in case needed for exception
                    for (;;) {
                        $this->output .= $this->a;
                        $this->lastByteOut = $this->a;
                        $this->a = $this->get();
                        if ($this->a === $this->b) {
                            // end quote
                            break;
                        }
                        if ($delimiter === '`' && $this->isLineTerminator($this->a)) {
                            // leave the newline
                        } elseif ($this->isEOF($this->a)) {
                            $byte = $this->inputIndex - 1;
                            throw new \Kibo\Phast\JSMin\UnterminatedStringException("JSMin: Unterminated String at byte {$byte}: {$str}");
                        }
                        $str .= $this->a;
                        if ($this->a === '\\') {
                            $this->output .= $this->a;
                            $this->lastByteOut = $this->a;
                            $this->a = $this->get();
                            $str .= $this->a;
                        }
                    }
                }
            // fallthrough intentional
            case self::ACTION_DELETE_A_B:
                // 3
                $this->b = $this->next();
                if ($this->b === '/' && $this->isRegexpLiteral()) {
                    $this->output .= $this->a . $this->b;
                    $pattern = '/';
                    // keep entire pattern in case we need to report it in the exception
                    for (;;) {
                        $this->a = $this->get();
                        $pattern .= $this->a;
                        if ($this->a === '[') {
                            for (;;) {
                                $this->output .= $this->a;
                                $this->a = $this->get();
                                $pattern .= $this->a;
                                if ($this->a === ']') {
                                    break;
                                }
                                if ($this->a === '\\') {
                                    $this->output .= $this->a;
                                    $this->a = $this->get();
                                    $pattern .= $this->a;
                                }
                                if ($this->isEOF($this->a)) {
                                    throw new \Kibo\Phast\JSMin\UnterminatedRegExpException("JSMin: Unterminated set in RegExp at byte " . $this->inputIndex . ": {$pattern}");
                                }
                            }
                        }
                        if ($this->a === '/') {
                            // end pattern
                            break;
                            // while (true)
                        } elseif ($this->a === '\\') {
                            $this->output .= $this->a;
                            $this->a = $this->get();
                            $pattern .= $this->a;
                        } elseif ($this->isEOF($this->a)) {
                            $byte = $this->inputIndex - 1;
                            throw new \Kibo\Phast\JSMin\UnterminatedRegExpException("JSMin: Unterminated RegExp at byte {$byte}: {$pattern}");
                        }
                        $this->output .= $this->a;
                        $this->lastByteOut = $this->a;
                    }
                    $this->b = $this->next();
                }
        }
    }
    /**
     * @return bool
     */
    protected function isRegexpLiteral()
    {
        if (false !== strpos("(,=:[!&|?+-~*{;", $this->a)) {
            // we can't divide after these tokens
            return true;
        }
        // check if first non-ws token is "/" (see starts-regex.js)
        $length = strlen($this->output);
        if ($this->isWhiteSpace($this->a) || $this->isLineTerminator($this->a)) {
            if ($length < 2) {
                // weird edge case
                return true;
            }
        }
        // if the "/" follows a keyword, it must be a regexp, otherwise it's best to assume division
        $subject = $this->output . trim($this->a);
        if (!preg_match('/(?:case|else|in|return|typeof)$/', $subject, $m)) {
            // not a keyword
            return false;
        }
        // can't be sure it's a keyword yet (see not-regexp.js)
        $charBeforeKeyword = substr($subject, 0 - strlen($m[0]) - 1, 1);
        if ($this->isAlphaNum($charBeforeKeyword)) {
            // this is really an identifier ending in a keyword, e.g. "xreturn"
            return false;
        }
        // it's a regexp. Remove unneeded whitespace after keyword
        if ($this->isWhiteSpace($this->a) || $this->isLineTerminator($this->a)) {
            $this->a = '';
        }
        return true;
    }
    /**
     * Return the next character from stdin. Watch out for lookahead. If the character is a control character,
     * translate it to a space or linefeed.
     *
     * @return string
     */
    protected function get()
    {
        $c = $this->lookAhead;
        $this->lookAhead = null;
        if ($c === null) {
            // getc(stdin)
            if ($this->inputIndex < $this->inputLength) {
                $c = $this->input[$this->inputIndex];
                $this->inputIndex += 1;
            } else {
                $c = null;
            }
        }
        if ($c === "\r") {
            return "\n";
        }
        return $c;
    }
    /**
     * Does $a indicate end of input?
     *
     * @param string $a
     * @return bool
     */
    protected function isEOF($a)
    {
        return $a === null || $this->isLineTerminator($a);
    }
    /**
     * Get next char (without getting it). If is ctrl character, translate to a space or newline.
     *
     * @return string
     */
    protected function peek()
    {
        $this->lookAhead = $this->get();
        return $this->lookAhead;
    }
    /**
     * Return true if the character is a letter, digit, underscore, dollar sign, or non-ASCII character.
     *
     * @param string $c
     *
     * @return bool
     */
    protected function isAlphaNum($c)
    {
        return preg_match('/^[a-z0-9A-Z_\\$\\\\]$/', $c) || ord($c) > 126;
    }
    /**
     * Consume a single line comment from input (possibly retaining it)
     */
    protected function consumeSingleLineComment()
    {
        $comment = '';
        while (true) {
            $get = $this->get();
            $comment .= $get;
            if ($this->isEOF($get)) {
                // if IE conditional comment
                if (preg_match('/^\\/@(?:cc_on|if|elif|else|end)\\b/', $comment)) {
                    $this->keptComment .= "/{$comment}";
                }
                return;
            }
        }
    }
    /**
     * Consume a multiple line comment from input (possibly retaining it)
     *
     * @throws UnterminatedCommentException
     */
    protected function consumeMultipleLineComment()
    {
        $this->get();
        $comment = '';
        for (;;) {
            $get = $this->get();
            if ($get === '*') {
                if ($this->peek() === '/') {
                    // end of comment reached
                    $this->get();
                    if (0 === strpos($comment, '!')) {
                        // preserved by YUI Compressor
                        if (!$this->keptComment) {
                            // don't prepend a newline if two comments right after one another
                            $this->keptComment = "\n";
                        }
                        $this->keptComment .= "/*!" . substr($comment, 1) . "*/\n";
                    } else {
                        if (preg_match('/^@(?:cc_on|if|elif|else|end)\\b/', $comment)) {
                            // IE conditional
                            $this->keptComment .= "/*{$comment}*/";
                        }
                    }
                    return;
                }
            } elseif ($get === null) {
                throw new \Kibo\Phast\JSMin\UnterminatedCommentException("JSMin: Unterminated comment at byte {$this->inputIndex}: /*{$comment}");
            }
            $comment .= $get;
        }
    }
    /**
     * Get the next character, skipping over comments. Some comments may be preserved.
     *
     * @return string
     */
    protected function next()
    {
        $get = $this->get();
        if ($get === '/') {
            switch ($this->peek()) {
                case '/':
                    $this->consumeSingleLineComment();
                    $get = "\n";
                    break;
                case '*':
                    $this->consumeMultipleLineComment();
                    $get = ' ';
                    break;
            }
        }
        return $get;
    }
    protected function isWhiteSpace($s)
    {
        // https://www.ecma-international.org/ecma-262/#sec-white-space
        return $s !== null && strpos(" \t\v\f", $s) !== false;
    }
    protected function isLineTerminator($s)
    {
        // https://www.ecma-international.org/ecma-262/#sec-line-terminators
        return $s !== null && strpos("\n\r", $s) !== false;
    }
}
namespace Kibo\Phast\JSMin;

class UnterminatedCommentException extends \Exception
{
}
namespace Kibo\Phast\JSMin;

class UnterminatedRegExpException extends \Exception
{
}
namespace Kibo\Phast\JSMin;

class UnterminatedStringException extends \Exception
{
}
namespace Kibo\Phast\Logging\Common;

trait JSONLFileLogTrait
{
    /**
     * @var string
     */
    private $dir;
    /**
     * @var string
     */
    private $filename;
    /**
     * JSONLFileLogWriter constructor.
     * @param string $dir
     * @param string $suffix
     */
    public function __construct($dir, $suffix)
    {
        $this->dir = $dir;
        $suffix = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $suffix);
        if (!empty($suffix)) {
            $suffix = '-' . $suffix;
        }
        $this->filename = $this->dir . '/log' . $suffix . '.jsonl';
    }
}
namespace Kibo\Phast\Logging;

class Log
{
    /**
     * @var Logger
     */
    private static $logger;
    public static function setLogger(\Kibo\Phast\Logging\Logger $logger)
    {
        self::$logger = $logger;
    }
    public static function initWithDummy()
    {
        self::$logger = new \Kibo\Phast\Logging\Logger(new \Kibo\Phast\Logging\LogWriters\Dummy\Writer());
    }
    public static function init(array $config, \Kibo\Phast\Services\ServiceRequest $request, $service)
    {
        $writer = (new \Kibo\Phast\Logging\LogWriters\Factory())->make($config, $request);
        $logger = new \Kibo\Phast\Logging\Logger($writer);
        self::$logger = $logger->withContext(['documentRequestId' => $request->getDocumentRequestId(), 'requestId' => mt_rand(0, 99999999), 'service' => $service]);
    }
    /**
     * @return Logger
     */
    public static function get()
    {
        if (!isset(self::$logger)) {
            self::initWithDummy();
        }
        return self::$logger;
    }
    /**
     * @param array $context
     * @return Logger
     */
    public static function context(array $context)
    {
        return self::get()->withContext($context);
    }
    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function emergency($message, array $context = [])
    {
        self::get()->emergency($message, $context);
    }
    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function alert($message, array $context = [])
    {
        self::get()->alert($message, $context);
    }
    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function critical($message, array $context = [])
    {
        self::get()->critical($message, $context);
    }
    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function error($message, array $context = [])
    {
        self::get()->error($message, $context);
    }
    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function warning($message, array $context = [])
    {
        self::get()->warning($message, $context);
    }
    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function notice($message, array $context = [])
    {
        self::get()->notice($message, $context);
    }
    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function info($message, array $context = [])
    {
        self::get()->info($message, $context);
    }
    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function debug($message, array $context = [])
    {
        self::get()->debug($message, $context);
    }
}
namespace Kibo\Phast\Logging;

class LogEntry implements \JsonSerializable
{
    /**
     * @var int
     */
    private $level;
    /**
     * @var string
     */
    private $message;
    /**
     * @var array
     */
    private $context;
    /**
     * LogEntry constructor.
     * @param int $level
     * @param string $message
     * @param array $context
     */
    public function __construct($level, $message, array $context)
    {
        $this->level = (int) $level;
        $this->message = $message;
        $this->context = $context;
    }
    /**
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }
    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
    /**
     * @return array
     */
    public function getContext()
    {
        return $this->context;
    }
    public function toArray() : array
    {
        return ['level' => $this->level, 'message' => $this->message, 'context' => $this->context];
    }
    public function jsonSerialize() : array
    {
        return $this->toArray();
    }
}
namespace Kibo\Phast\Logging;

class LogLevel
{
    const EMERGENCY = 128;
    const ALERT = 64;
    const CRITICAL = 32;
    const ERROR = 16;
    const WARNING = 8;
    const NOTICE = 4;
    const INFO = 2;
    const DEBUG = 1;
    public static function toString($level)
    {
        switch ($level) {
            case self::EMERGENCY:
                return 'EMERGENCY';
            case self::ALERT:
                return 'ALERT';
            case self::CRITICAL:
                return 'CRITICAL';
            case self::ERROR:
                return 'ERROR';
            case self::WARNING:
                return 'WARNING';
            case self::NOTICE:
                return 'NOTICE';
            case self::INFO:
                return 'INFO';
            case self::DEBUG:
                return 'DEBUG';
            default:
                return 'UNKNOWN';
        }
    }
}
namespace Kibo\Phast\Logging;

interface LogReader
{
    /**
     * Reads LogMessage objects
     *
     * @return \Generator
     */
    public function readEntries();
}
namespace Kibo\Phast\Logging\LogReaders\JSONLFile;

class Reader implements \Kibo\Phast\Logging\LogReader
{
    use \Kibo\Phast\Logging\Common\JSONLFileLogTrait;
    public function readEntries()
    {
        $fp = @fopen($this->filename, 'r');
        while ($fp && ($row = @fgets($fp))) {
            $decoded = @json_decode($row, true);
            if (!$decoded) {
                continue;
            }
            (yield new \Kibo\Phast\Logging\LogEntry(@$decoded['level'], @$decoded['message'], @$decoded['context']));
        }
        @fclose($fp);
        @unlink($this->filename);
    }
    public function __destruct()
    {
        if (!($dir = @opendir($this->dir))) {
            return;
        }
        $tenMinutesAgo = time() - 600;
        while ($file = @readdir($dir)) {
            $filename = $this->dir . "/{$file}";
            if (preg_match('/\\.jsonl$/', $file) && @filectime($filename) < $tenMinutesAgo) {
                @unlink($filename);
            }
        }
    }
}
namespace Kibo\Phast\Logging;

interface LogWriter
{
    /**
     * Set a bit-mask to filter entries that are actually written
     *
     * @param int $mask
     * @return void
     */
    public function setLevelMask($mask);
    /**
     * Write an entry to the log
     *
     * @param LogEntry $entry
     * @return void
     */
    public function writeEntry(\Kibo\Phast\Logging\LogEntry $entry);
}
namespace Kibo\Phast\Logging\LogWriters;

abstract class BaseLogWriter implements \Kibo\Phast\Logging\LogWriter
{
    protected $levelMask = ~0;
    protected abstract function doWriteEntry(\Kibo\Phast\Logging\LogEntry $entry);
    public function setLevelMask($mask)
    {
        $this->levelMask = $mask;
    }
    public function writeEntry(\Kibo\Phast\Logging\LogEntry $entry)
    {
        if ($this->levelMask & $entry->getLevel()) {
            $this->doWriteEntry($entry);
        }
    }
}
namespace Kibo\Phast\Logging\LogWriters\Composite;

class Factory
{
    public function make(array $config, \Kibo\Phast\Services\ServiceRequest $request)
    {
        $writer = new \Kibo\Phast\Logging\LogWriters\Composite\Writer();
        $factory = new \Kibo\Phast\Logging\LogWriters\Factory();
        foreach ($config['logWriters'] as $writerConfig) {
            $writer->addWriter($factory->make($writerConfig, $request));
        }
        return $writer;
    }
}
namespace Kibo\Phast\Logging\LogWriters\Composite;

class Writer extends \Kibo\Phast\Logging\LogWriters\BaseLogWriter
{
    /**
     * @var Writer[]
     */
    private $writers = [];
    public function addWriter(\Kibo\Phast\Logging\LogWriter $writer)
    {
        $this->writers[] = $writer;
    }
    protected function doWriteEntry(\Kibo\Phast\Logging\LogEntry $entry)
    {
        foreach ($this->writers as $writer) {
            $writer->writeEntry($entry);
        }
    }
}
namespace Kibo\Phast\Logging\LogWriters\Dummy;

class Writer implements \Kibo\Phast\Logging\LogWriter
{
    public function setLevelMask($mask)
    {
    }
    public function writeEntry(\Kibo\Phast\Logging\LogEntry $entry)
    {
    }
}
namespace Kibo\Phast\Logging\LogWriters;

class Factory
{
    public function make(array $config, \Kibo\Phast\Services\ServiceRequest $request)
    {
        if (isset($config['logWriters']) && count($config['logWriters']) > 1) {
            $class = \Kibo\Phast\Logging\LogWriters\Composite\Writer::class;
        } elseif (isset($config['logWriters'])) {
            $config = array_pop($config['logWriters']);
            $class = $config['class'];
        } else {
            $class = $config['class'];
        }
        $package = \Kibo\Phast\Environment\Package::fromPackageClass($class);
        $writer = $package->getFactory()->make($config, $request);
        if (isset($config['levelMask'])) {
            $writer->setLevelMask($config['levelMask']);
        }
        return $writer;
    }
}
namespace Kibo\Phast\Logging\LogWriters\JSONLFile;

class Factory
{
    public function make(array $config, \Kibo\Phast\Services\ServiceRequest $request)
    {
        return new \Kibo\Phast\Logging\LogWriters\JSONLFile\Writer($config['logRoot'], $request->getDocumentRequestId());
    }
}
namespace Kibo\Phast\Logging\LogWriters\JSONLFile;

class Writer extends \Kibo\Phast\Logging\LogWriters\BaseLogWriter
{
    use \Kibo\Phast\Logging\Common\JSONLFileLogTrait;
    /**
     * @param LogEntry $entry
     */
    protected function doWriteEntry(\Kibo\Phast\Logging\LogEntry $entry)
    {
        $encoded = @\Kibo\Phast\Common\JSON::encode($entry->toArray());
        if ($encoded) {
            $this->makeDirIfNotExists();
            @file_put_contents($this->filename, $encoded . "\n", FILE_APPEND | LOCK_EX);
        }
    }
    private function makeDirIfNotExists()
    {
        if (!@file_exists($this->dir)) {
            @mkdir($this->dir, 0777, true);
        }
    }
}
namespace Kibo\Phast\Logging\LogWriters\PHPError;

class Factory
{
    public function make(array $config, \Kibo\Phast\Services\ServiceRequest $request)
    {
        return new \Kibo\Phast\Logging\LogWriters\PHPError\Writer($config);
    }
}
namespace Kibo\Phast\Logging\LogWriters\PHPError;

class Writer extends \Kibo\Phast\Logging\LogWriters\BaseLogWriter
{
    private $messageType = 0;
    private $destination = null;
    private $extraHeaders = null;
    /**
     * @var ObjectifiedFunctions
     */
    private $funcs;
    /**
     * PHPErrorLogWriter constructor.
     * @param array $config
     * @param ObjectifiedFunctions $funcs
     */
    public function __construct(array $config, \Kibo\Phast\Common\ObjectifiedFunctions $funcs = null)
    {
        foreach (['messageType', 'destination', 'extraHeaders'] as $field) {
            if (isset($config[$field])) {
                $this->{$field} = $config[$field];
            }
        }
        $this->funcs = is_null($funcs) ? new \Kibo\Phast\Common\ObjectifiedFunctions() : $funcs;
    }
    protected function doWriteEntry(\Kibo\Phast\Logging\LogEntry $entry)
    {
        if ($this->levelMask & $entry->getLevel()) {
            $this->funcs->error_log($this->interpolate($entry->getMessage(), $entry->getContext()), $this->messageType, $this->destination, $this->extraHeaders);
        }
    }
    private function interpolate($message, $context)
    {
        $prefix = '';
        $prefixKeys = ['requestId', 'service', 'class', 'method', 'line'];
        foreach ($prefixKeys as $key) {
            if (isset($context[$key])) {
                $prefix .= '{' . $key . "}\t";
            }
        }
        return preg_replace_callback('/{(.+?)}/', function ($match) use($context) {
            return array_key_exists($match[1], $context) ? $context[$match[1]] : $match[0];
        }, $prefix . $message);
    }
}
namespace Kibo\Phast\Logging\LogWriters\RotatingTextFile;

class Factory
{
    public function make(array $config, \Kibo\Phast\Services\ServiceRequest $request)
    {
        return new \Kibo\Phast\Logging\LogWriters\RotatingTextFile\Writer($config);
    }
}
namespace Kibo\Phast\Logging\LogWriters\RotatingTextFile;

class Writer extends \Kibo\Phast\Logging\LogWriters\BaseLogWriter
{
    /** @var string */
    private $path = 'phast.log';
    /** @var int */
    private $maxFiles = 2;
    /** @var int */
    private $maxSize = 10 * 1024 * 1024;
    /** @var ObjectifiedFunctions */
    private $funcs;
    /**
     * @param array $config
     * @param ?ObjectifiedFunctions $funcs
     */
    public function __construct(array $config, \Kibo\Phast\Common\ObjectifiedFunctions $funcs = null)
    {
        if (isset($config['path'])) {
            $this->path = (string) $config['path'];
        }
        if (isset($config['maxFiles'])) {
            $this->maxFiles = (int) $config['maxFiles'];
        }
        if (isset($config['maxSize'])) {
            $this->maxSize = (int) $config['maxSize'];
        }
        $this->funcs = is_null($funcs) ? new \Kibo\Phast\Common\ObjectifiedFunctions() : $funcs;
    }
    protected function doWriteEntry(\Kibo\Phast\Logging\LogEntry $entry)
    {
        if (!($this->levelMask & $entry->getLevel())) {
            return;
        }
        $message = $this->interpolate($entry->getMessage(), $entry->getContext());
        $line = sprintf("%s %s %s\n", gmdate('Y-m-d\\TH:i:s\\Z', $this->funcs->time()), \Kibo\Phast\Logging\LogLevel::toString($entry->getLevel()), $message);
        clearstatcache(true, $this->path);
        $this->rotate(strlen($line));
        file_put_contents($this->path, $line, FILE_APPEND);
    }
    private function interpolate($message, $context)
    {
        $prefix = '';
        $prefixKeys = ['requestId', 'service', 'class', 'method', 'line'];
        foreach ($prefixKeys as $key) {
            if (isset($context[$key])) {
                $prefix .= '{' . $key . "}\t";
            }
        }
        return preg_replace_callback('/{(.+?)}/', function ($match) use($context) {
            return array_key_exists($match[1], $context) ? $context[$match[1]] : $match[0];
        }, $prefix . $message);
    }
    private function rotate($bufferSize)
    {
        if (!$this->shouldRotate($bufferSize)) {
            return;
        }
        if (!($fp = fopen($this->path, 'r+'))) {
            return;
        }
        try {
            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                return;
            }
            if (!$this->shouldRotate($bufferSize)) {
                return;
            }
            for ($i = $this->maxFiles - 1; $i > 0; $i--) {
                @rename($this->getName($i - 1), $this->getName($i));
            }
        } finally {
            fclose($fp);
        }
    }
    private function getName($index)
    {
        if ($index <= 0) {
            return $this->path;
        }
        return $this->path . '.' . $index;
    }
    private function shouldRotate($bufferSize)
    {
        $currentSize = @filesize($this->path);
        if (!$currentSize) {
            return false;
        }
        $newSize = $currentSize + $bufferSize;
        return $newSize > $this->maxSize;
    }
}
namespace Kibo\Phast\Logging;

class Logger
{
    /**
     * @var LogWriter
     */
    private $writer;
    /**
     * @var array
     */
    private $context = [];
    /**
     * @var ObjectifiedFunctions
     */
    private $functions;
    /**
     * Logger constructor.
     * @param LogWriter $writer
     */
    public function __construct(\Kibo\Phast\Logging\LogWriter $writer, \Kibo\Phast\Common\ObjectifiedFunctions $functions = null)
    {
        $this->writer = $writer;
        $this->functions = is_null($functions) ? new \Kibo\Phast\Common\ObjectifiedFunctions() : $functions;
    }
    /**
     * Returns a new logger with default context
     * merged from the current logger and the passed array
     *
     * @param array $context
     * @return Logger
     */
    public function withContext(array $context)
    {
        $logger = clone $this;
        $logger->context = array_merge($this->context, $context);
        return $logger;
    }
    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $this->log(\Kibo\Phast\Logging\LogLevel::EMERGENCY, $message, $context);
    }
    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function alert($message, array $context = [])
    {
        $this->log(\Kibo\Phast\Logging\LogLevel::ALERT, $message, $context);
    }
    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function critical($message, array $context = [])
    {
        $this->log(\Kibo\Phast\Logging\LogLevel::CRITICAL, $message, $context);
    }
    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function error($message, array $context = [])
    {
        $this->log(\Kibo\Phast\Logging\LogLevel::ERROR, $message, $context);
    }
    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $this->log(\Kibo\Phast\Logging\LogLevel::WARNING, $message, $context);
    }
    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function notice($message, array $context = [])
    {
        $this->log(\Kibo\Phast\Logging\LogLevel::NOTICE, $message, $context);
    }
    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function info($message, array $context = [])
    {
        $this->log(\Kibo\Phast\Logging\LogLevel::INFO, $message, $context);
    }
    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $this->log(\Kibo\Phast\Logging\LogLevel::DEBUG, $message, $context);
    }
    protected function log($level, $message, array $context = [])
    {
        $context = array_merge(['timestamp' => $this->functions->microtime(true)], $context);
        $this->writer->writeEntry(new \Kibo\Phast\Logging\LogEntry($level, $message, array_merge($this->context, $context)));
    }
}
namespace Kibo\Phast\Logging;

trait LoggingTrait
{
    protected function logger($method = null, $line = null)
    {
        $context = ['class' => get_class($this)];
        if (!is_null($method)) {
            $context['method'] = $method;
        }
        if (!is_null($line)) {
            $context['line'] = $line;
        }
        return \Kibo\Phast\Logging\Log::context($context);
    }
}
/**
 * Provide general element functions.
 */
namespace Kibo\Phast\Parsing\HTML;

/**
 * This class provides general information about HTML5 elements,
 * including syntactic and semantic issues.
 * Parsers and serializers can
 * use this class as a reference point for information about the rules
 * of various HTML5 elements.
 *
 * @todo consider using a bitmask table lookup. There is enough overlap in
 *       naming that this could significantly shrink the size and maybe make it
 *       faster. See the Go teams implementation at https://code.google.com/p/go/source/browse/html/atom.
 */
class HTMLInfo
{
    /**
     * Indicates an element is described in the specification.
     */
    const KNOWN_ELEMENT = 1;
    // From section 8.1.2: "script", "style"
    // From 8.2.5.4.7 ("in body" insertion mode): "noembed"
    // From 8.4 "style", "xmp", "iframe", "noembed", "noframes"
    /**
     * Indicates the contained text should be processed as raw text.
     */
    const TEXT_RAW = 2;
    // From section 8.1.2: "textarea", "title"
    /**
     * Indicates the contained text should be processed as RCDATA.
     */
    const TEXT_RCDATA = 4;
    /**
     * Indicates the tag cannot have content.
     */
    const VOID_TAG = 8;
    // "address", "article", "aside", "blockquote", "center", "details", "dialog", "dir", "div", "dl",
    // "fieldset", "figcaption", "figure", "footer", "header", "hgroup", "menu",
    // "nav", "ol", "p", "section", "summary", "ul"
    // "h1", "h2", "h3", "h4", "h5", "h6"
    // "pre", "listing"
    // "form"
    // "plaintext"
    /**
     * Indicates that if a previous event is for a P tag, that element
     * should be considered closed.
     */
    const AUTOCLOSE_P = 16;
    /**
     * Indicates that the text inside is plaintext (pre).
     */
    const TEXT_PLAINTEXT = 32;
    // See https://developer.mozilla.org/en-US/docs/HTML/Block-level_elements
    /**
     * Indicates that the tag is a block.
     */
    const BLOCK_TAG = 64;
    /**
     * Indicates that the tag allows only inline elements as child nodes.
     */
    const BLOCK_ONLY_INLINE = 128;
    /**
     * The HTML5 elements as defined in http://dev.w3.org/html5/markup/elements.html.
     *
     * @var array
     */
    public static $html5 = [
        'a' => 1,
        'abbr' => 1,
        'address' => 65,
        // NORMAL | BLOCK_TAG
        'area' => 9,
        // NORMAL | VOID_TAG
        'article' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'aside' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'audio' => 65,
        // NORMAL | BLOCK_TAG
        'b' => 1,
        'base' => 9,
        // NORMAL | VOID_TAG
        'bdi' => 1,
        'bdo' => 1,
        'blockquote' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'body' => 1,
        'br' => 9,
        // NORMAL | VOID_TAG
        'button' => 1,
        'canvas' => 65,
        // NORMAL | BLOCK_TAG
        'caption' => 1,
        'cite' => 1,
        'code' => 1,
        'col' => 9,
        // NORMAL | VOID_TAG
        'colgroup' => 1,
        'command' => 9,
        // NORMAL | VOID_TAG
        // "data" => 1, // This is highly experimental and only part of the whatwg spec (not w3c). See https://developer.mozilla.org/en-US/docs/HTML/Element/data
        'datalist' => 1,
        'dd' => 65,
        // NORMAL | BLOCK_TAG
        'del' => 1,
        'details' => 17,
        // NORMAL | AUTOCLOSE_P,
        'dfn' => 1,
        'dialog' => 17,
        // NORMAL | AUTOCLOSE_P,
        'div' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'dl' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'dt' => 1,
        'em' => 1,
        'embed' => 9,
        // NORMAL | VOID_TAG
        'fieldset' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'figcaption' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'figure' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'footer' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'form' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'h1' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'h2' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'h3' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'h4' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'h5' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'h6' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'head' => 1,
        'header' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'hgroup' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'hr' => 73,
        // NORMAL | VOID_TAG
        'html' => 1,
        'i' => 1,
        'iframe' => 3,
        // NORMAL | TEXT_RAW
        'img' => 9,
        // NORMAL | VOID_TAG
        'input' => 9,
        // NORMAL | VOID_TAG
        'kbd' => 1,
        'ins' => 1,
        'keygen' => 9,
        // NORMAL | VOID_TAG
        'label' => 1,
        'legend' => 1,
        'li' => 1,
        'link' => 9,
        // NORMAL | VOID_TAG
        'map' => 1,
        'mark' => 1,
        'menu' => 17,
        // NORMAL | AUTOCLOSE_P,
        'meta' => 9,
        // NORMAL | VOID_TAG
        'meter' => 1,
        'nav' => 17,
        // NORMAL | AUTOCLOSE_P,
        'noscript' => 65,
        // NORMAL | BLOCK_TAG
        'object' => 1,
        'ol' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'optgroup' => 1,
        'option' => 1,
        'output' => 65,
        // NORMAL | BLOCK_TAG
        'p' => 209,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG | BLOCK_ONLY_INLINE
        'param' => 9,
        // NORMAL | VOID_TAG
        'pre' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'progress' => 1,
        'q' => 1,
        'rp' => 1,
        'rt' => 1,
        'ruby' => 1,
        's' => 1,
        'samp' => 1,
        'script' => 3,
        // NORMAL | TEXT_RAW
        'section' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'select' => 1,
        'small' => 1,
        'source' => 9,
        // NORMAL | VOID_TAG
        'span' => 1,
        'strong' => 1,
        'style' => 3,
        // NORMAL | TEXT_RAW
        'sub' => 1,
        'summary' => 17,
        // NORMAL | AUTOCLOSE_P,
        'sup' => 1,
        'table' => 65,
        // NORMAL | BLOCK_TAG
        'tbody' => 1,
        'td' => 1,
        'textarea' => 5,
        // NORMAL | TEXT_RCDATA
        'tfoot' => 65,
        // NORMAL | BLOCK_TAG
        'th' => 1,
        'thead' => 1,
        'time' => 1,
        'title' => 5,
        // NORMAL | TEXT_RCDATA
        'tr' => 1,
        'track' => 9,
        // NORMAL | VOID_TAG
        'u' => 1,
        'ul' => 81,
        // NORMAL | AUTOCLOSE_P | BLOCK_TAG
        'var' => 1,
        'video' => 65,
        // NORMAL | BLOCK_TAG
        'wbr' => 9,
        // NORMAL | VOID_TAG
        // Legacy?
        'basefont' => 8,
        // VOID_TAG
        'bgsound' => 8,
        // VOID_TAG
        'noframes' => 2,
        // RAW_TEXT
        'frame' => 9,
        // NORMAL | VOID_TAG
        'frameset' => 1,
        'center' => 16,
        'dir' => 16,
        'listing' => 16,
        // AUTOCLOSE_P
        'plaintext' => 48,
        // AUTOCLOSE_P | TEXT_PLAINTEXT
        'applet' => 0,
        'marquee' => 0,
        'isindex' => 8,
        // VOID_TAG
        'xmp' => 20,
        // AUTOCLOSE_P | VOID_TAG | RAW_TEXT
        'noembed' => 2,
    ];
    /**
     * The MathML elements.
     * See http://www.w3.org/wiki/MathML/Elements.
     *
     * In our case we are only concerned with presentation MathML and not content
     * MathML. There is a nice list of this subset at https://developer.mozilla.org/en-US/docs/MathML/Element.
     *
     * @var array
     */
    public static $mathml = ['maction' => 1, 'maligngroup' => 1, 'malignmark' => 1, 'math' => 1, 'menclose' => 1, 'merror' => 1, 'mfenced' => 1, 'mfrac' => 1, 'mglyph' => 1, 'mi' => 1, 'mlabeledtr' => 1, 'mlongdiv' => 1, 'mmultiscripts' => 1, 'mn' => 1, 'mo' => 1, 'mover' => 1, 'mpadded' => 1, 'mphantom' => 1, 'mroot' => 1, 'mrow' => 1, 'ms' => 1, 'mscarries' => 1, 'mscarry' => 1, 'msgroup' => 1, 'msline' => 1, 'mspace' => 1, 'msqrt' => 1, 'msrow' => 1, 'mstack' => 1, 'mstyle' => 1, 'msub' => 1, 'msup' => 1, 'msubsup' => 1, 'mtable' => 1, 'mtd' => 1, 'mtext' => 1, 'mtr' => 1, 'munder' => 1, 'munderover' => 1];
    /**
     * The svg elements.
     *
     * The Mozilla documentation has a good list at https://developer.mozilla.org/en-US/docs/SVG/Element.
     * The w3c list appears to be lacking in some areas like filter effect elements.
     * That list can be found at http://www.w3.org/wiki/SVG/Elements.
     *
     * Note, FireFox appears to do a better job rendering filter effects than chrome.
     * While they are in the spec I'm not sure how widely implemented they are.
     *
     * @var array
     */
    public static $svg = [
        'a' => 1,
        'altGlyph' => 1,
        'altGlyphDef' => 1,
        'altGlyphItem' => 1,
        'animate' => 1,
        'animateColor' => 1,
        'animateMotion' => 1,
        'animateTransform' => 1,
        'circle' => 1,
        'clipPath' => 1,
        'color-profile' => 1,
        'cursor' => 1,
        'defs' => 1,
        'desc' => 1,
        'ellipse' => 1,
        'feBlend' => 1,
        'feColorMatrix' => 1,
        'feComponentTransfer' => 1,
        'feComposite' => 1,
        'feConvolveMatrix' => 1,
        'feDiffuseLighting' => 1,
        'feDisplacementMap' => 1,
        'feDistantLight' => 1,
        'feFlood' => 1,
        'feFuncA' => 1,
        'feFuncB' => 1,
        'feFuncG' => 1,
        'feFuncR' => 1,
        'feGaussianBlur' => 1,
        'feImage' => 1,
        'feMerge' => 1,
        'feMergeNode' => 1,
        'feMorphology' => 1,
        'feOffset' => 1,
        'fePointLight' => 1,
        'feSpecularLighting' => 1,
        'feSpotLight' => 1,
        'feTile' => 1,
        'feTurbulence' => 1,
        'filter' => 1,
        'font' => 1,
        'font-face' => 1,
        'font-face-format' => 1,
        'font-face-name' => 1,
        'font-face-src' => 1,
        'font-face-uri' => 1,
        'foreignObject' => 1,
        'g' => 1,
        'glyph' => 1,
        'glyphRef' => 1,
        'hkern' => 1,
        'image' => 1,
        'line' => 1,
        'linearGradient' => 1,
        'marker' => 1,
        'mask' => 1,
        'metadata' => 1,
        'missing-glyph' => 1,
        'mpath' => 1,
        'path' => 1,
        'pattern' => 1,
        'polygon' => 1,
        'polyline' => 1,
        'radialGradient' => 1,
        'rect' => 1,
        'script' => 3,
        // NORMAL | RAW_TEXT
        'set' => 1,
        'stop' => 1,
        'style' => 3,
        // NORMAL | RAW_TEXT
        'svg' => 1,
        'switch' => 1,
        'symbol' => 1,
        'text' => 1,
        'textPath' => 1,
        'title' => 1,
        'tref' => 1,
        'tspan' => 1,
        'use' => 1,
        'view' => 1,
        'vkern' => 1,
    ];
    /**
     * Some attributes in SVG are case sensetitive.
     *
     * This map contains key/value pairs with the key as the lowercase attribute
     * name and the value with the correct casing.
     */
    public static $svgCaseSensitiveAttributeMap = ['attributename' => 'attributeName', 'attributetype' => 'attributeType', 'basefrequency' => 'baseFrequency', 'baseprofile' => 'baseProfile', 'calcmode' => 'calcMode', 'clippathunits' => 'clipPathUnits', 'contentscripttype' => 'contentScriptType', 'contentstyletype' => 'contentStyleType', 'diffuseconstant' => 'diffuseConstant', 'edgemode' => 'edgeMode', 'externalresourcesrequired' => 'externalResourcesRequired', 'filterres' => 'filterRes', 'filterunits' => 'filterUnits', 'glyphref' => 'glyphRef', 'gradienttransform' => 'gradientTransform', 'gradientunits' => 'gradientUnits', 'kernelmatrix' => 'kernelMatrix', 'kernelunitlength' => 'kernelUnitLength', 'keypoints' => 'keyPoints', 'keysplines' => 'keySplines', 'keytimes' => 'keyTimes', 'lengthadjust' => 'lengthAdjust', 'limitingconeangle' => 'limitingConeAngle', 'markerheight' => 'markerHeight', 'markerunits' => 'markerUnits', 'markerwidth' => 'markerWidth', 'maskcontentunits' => 'maskContentUnits', 'maskunits' => 'maskUnits', 'numoctaves' => 'numOctaves', 'pathlength' => 'pathLength', 'patterncontentunits' => 'patternContentUnits', 'patterntransform' => 'patternTransform', 'patternunits' => 'patternUnits', 'pointsatx' => 'pointsAtX', 'pointsaty' => 'pointsAtY', 'pointsatz' => 'pointsAtZ', 'preservealpha' => 'preserveAlpha', 'preserveaspectratio' => 'preserveAspectRatio', 'primitiveunits' => 'primitiveUnits', 'refx' => 'refX', 'refy' => 'refY', 'repeatcount' => 'repeatCount', 'repeatdur' => 'repeatDur', 'requiredextensions' => 'requiredExtensions', 'requiredfeatures' => 'requiredFeatures', 'specularconstant' => 'specularConstant', 'specularexponent' => 'specularExponent', 'spreadmethod' => 'spreadMethod', 'startoffset' => 'startOffset', 'stddeviation' => 'stdDeviation', 'stitchtiles' => 'stitchTiles', 'surfacescale' => 'surfaceScale', 'systemlanguage' => 'systemLanguage', 'tablevalues' => 'tableValues', 'targetx' => 'targetX', 'targety' => 'targetY', 'textlength' => 'textLength', 'viewbox' => 'viewBox', 'viewtarget' => 'viewTarget', 'xchannelselector' => 'xChannelSelector', 'ychannelselector' => 'yChannelSelector', 'zoomandpan' => 'zoomAndPan'];
    /**
     * Some SVG elements are case sensetitive.
     * This map contains these.
     *
     * The map contains key/value store of the name is lowercase as the keys and
     * the correct casing as the value.
     */
    public static $svgCaseSensitiveElementMap = ['altglyph' => 'altGlyph', 'altglyphdef' => 'altGlyphDef', 'altglyphitem' => 'altGlyphItem', 'animatecolor' => 'animateColor', 'animatemotion' => 'animateMotion', 'animatetransform' => 'animateTransform', 'clippath' => 'clipPath', 'feblend' => 'feBlend', 'fecolormatrix' => 'feColorMatrix', 'fecomponenttransfer' => 'feComponentTransfer', 'fecomposite' => 'feComposite', 'feconvolvematrix' => 'feConvolveMatrix', 'fediffuselighting' => 'feDiffuseLighting', 'fedisplacementmap' => 'feDisplacementMap', 'fedistantlight' => 'feDistantLight', 'feflood' => 'feFlood', 'fefunca' => 'feFuncA', 'fefuncb' => 'feFuncB', 'fefuncg' => 'feFuncG', 'fefuncr' => 'feFuncR', 'fegaussianblur' => 'feGaussianBlur', 'feimage' => 'feImage', 'femerge' => 'feMerge', 'femergenode' => 'feMergeNode', 'femorphology' => 'feMorphology', 'feoffset' => 'feOffset', 'fepointlight' => 'fePointLight', 'fespecularlighting' => 'feSpecularLighting', 'fespotlight' => 'feSpotLight', 'fetile' => 'feTile', 'feturbulence' => 'feTurbulence', 'foreignobject' => 'foreignObject', 'glyphref' => 'glyphRef', 'lineargradient' => 'linearGradient', 'radialgradient' => 'radialGradient', 'textpath' => 'textPath'];
    /**
     * Check whether the given element meets the given criterion.
     *
     * Example:
     *
     * Elements::isA('script', Elements::TEXT_RAW); // Returns true.
     *
     * Elements::isA('script', Elements::TEXT_RCDATA); // Returns false.
     *
     * @param string $name
     *            The element name.
     * @param int $mask
     *            One of the constants on this class.
     * @return boolean true if the element matches the mask, false otherwise.
     */
    public static function isA($name, $mask)
    {
        if (!static::isElement($name)) {
            return false;
        }
        return (static::element($name) & $mask) == $mask;
    }
    /**
     * Test if an element is a valid html5 element.
     *
     * @param string $name
     *            The name of the element.
     *
     * @return bool True if a html5 element and false otherwise.
     */
    public static function isHtml5Element($name)
    {
        // html5 element names are case insensetitive. Forcing lowercase for the check.
        // Do we need this check or will all data passed here already be lowercase?
        return isset(static::$html5[strtolower($name)]);
    }
    /**
     * Test if an element name is a valid MathML presentation element.
     *
     * @param string $name
     *            The name of the element.
     *
     * @return bool True if a MathML name and false otherwise.
     */
    public static function isMathMLElement($name)
    {
        // MathML is case-sensetitive unlike html5 elements.
        return isset(static::$mathml[$name]);
    }
    /**
     * Test if an element is a valid SVG element.
     *
     * @param string $name
     *            The name of the element.
     *
     * @return boolean True if a SVG element and false otherise.
     */
    public static function isSvgElement($name)
    {
        // SVG is case-sensetitive unlike html5 elements.
        return isset(static::$svg[$name]);
    }
    /**
     * Is an element name valid in an html5 document.
     *
     * This includes html5 elements along with other allowed embedded content
     * such as svg and mathml.
     *
     * @param string $name
     *            The name of the element.
     *
     * @return bool True if valid and false otherwise.
     */
    public static function isElement($name)
    {
        return static::isHtml5Element($name) || static::isMathMLElement($name) || static::isSvgElement($name);
    }
    /**
     * Get the element mask for the given element name.
     *
     * @param string $name
     *            The name of the element.
     *
     * @return int|bool The element mask or false if element does not exist.
     */
    public static function element($name)
    {
        if (isset(static::$html5[$name])) {
            return static::$html5[$name];
        }
        if (isset(static::$svg[$name])) {
            return static::$svg[$name];
        }
        if (isset(static::$mathml[$name])) {
            return static::$mathml[$name];
        }
        return false;
    }
    /**
     * Normalize a SVG element name to its proper case and form.
     *
     * @param string $name
     *            The name of the element.
     *
     * @return string The normalized form of the element name.
     */
    public static function normalizeSvgElement($name)
    {
        $name = strtolower($name);
        if (isset(static::$svgCaseSensitiveElementMap[$name])) {
            $name = static::$svgCaseSensitiveElementMap[$name];
        }
        return $name;
    }
    /**
     * Normalize a SVG attribute name to its proper case and form.
     *
     * @param string $name
     *            The name of the attribute.
     *
     * @return string The normalized form of the attribute name.
     */
    public static function normalizeSvgAttribute($name)
    {
        $name = strtolower($name);
        if (isset(static::$svgCaseSensitiveAttributeMap[$name])) {
            $name = static::$svgCaseSensitiveAttributeMap[$name];
        }
        return $name;
    }
    /**
     * Normalize a MathML attribute name to its proper case and form.
     *
     * Note, all MathML element names are lowercase.
     *
     * @param string $name
     *            The name of the attribute.
     *
     * @return string The normalized form of the attribute name.
     */
    public static function normalizeMathMlAttribute($name)
    {
        $name = strtolower($name);
        // Only one attribute has a mixed case form for MathML.
        if ($name == 'definitionurl') {
            $name = 'definitionURL';
        }
        return $name;
    }
}
namespace Kibo\Phast\Parsing\HTML\HTMLStreamElements;

class Element
{
    /**
     * @var string
     */
    public $originalString;
    /**
     * @param string $originalString
     */
    public function setOriginalString($originalString)
    {
        $this->originalString = $originalString;
    }
    public function __get($name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return call_user_func([$this, $method]);
        }
    }
    public function __set($name, $value)
    {
        $method = 'set' . ucfirst($name);
        if (method_exists($this, $method)) {
            return call_user_func([$this, $method], $value);
        }
    }
    public function toString()
    {
        return $this->__toString();
    }
    public function __toString()
    {
        return isset($this->originalString) ? $this->originalString : '';
    }
    public function dump()
    {
        return '<' . preg_replace('~^.*\\\\~', '', get_class($this)) . ' ' . $this->dumpValue() . '>';
    }
    public function dumpValue()
    {
        return \Kibo\Phast\Common\JSON::encode($this->originalString);
    }
}
namespace Kibo\Phast\Parsing\HTML\HTMLStreamElements;

class Junk extends \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Element
{
}
namespace Kibo\Phast\Parsing\HTML\HTMLStreamElements;

class Tag extends \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Element
{
    /**
     * @var string
     */
    private $tagName;
    /**
     * @var array
     */
    private $attributes = [];
    /**
     * @var array
     */
    private $newAttributes = [];
    /**
     * @var \Iterator
     */
    private $attributeReader;
    /**
     * @var string
     */
    private $textContent = '';
    /**
     * @var string
     */
    private $closingTag = '';
    private $dirty = false;
    /**
     * Tag constructor.
     * @param $tagName
     * @param array|\Traversable $attributes
     */
    public function __construct($tagName, $attributes = [])
    {
        $this->tagName = strtolower($tagName);
        if ($attributes instanceof \Iterator) {
            $this->attributeReader = $attributes;
        } elseif (is_array($attributes)) {
            $this->attributeReader = new \ArrayIterator($attributes);
        } else {
            throw new \InvalidArgumentException('Attributes must be array or Iterator');
        }
    }
    /**
     * @return string
     */
    public function getTagName()
    {
        return $this->tagName;
    }
    /**
     * @param string $class
     * @return bool
     */
    public function hasClass($class)
    {
        foreach ($this->getClasses() as $c) {
            if (!strcasecmp($class, $c)) {
                return true;
            }
        }
        return false;
    }
    /**
     * @return string[]
     */
    public function getClasses()
    {
        return array_values(array_filter(preg_split('~\\s+~', $this->getAttribute('class')), 'strlen'));
    }
    /**
     * @param string $attrName
     * @return bool
     */
    public function hasAttribute($attrName)
    {
        return $this->getAttribute($attrName) !== null;
    }
    /**
     * @param string $attrName
     * @return mixed|null
     */
    public function getAttribute($attrName)
    {
        if (array_key_exists($attrName, $this->newAttributes)) {
            return $this->newAttributes[$attrName];
        }
        if (!array_key_exists($attrName, $this->attributes)) {
            $this->readUntilAttribute($attrName);
        }
        if (isset($this->attributes[$attrName])) {
            return $this->attributes[$attrName];
        }
    }
    /** @return string[] */
    public function getAttributes()
    {
        $this->readUntilAttribute(null);
        return array_filter($this->newAttributes + $this->attributes, function ($value) {
            return $value !== null;
        });
    }
    private function readUntilAttribute($attrName)
    {
        if (!$this->attributeReader) {
            return;
        }
        while ($this->attributeReader->valid()) {
            $name = strtolower($this->attributeReader->key());
            $value = $this->attributeReader->current();
            $this->attributeReader->next();
            if (!isset($this->attributes[$name])) {
                $this->attributes[$name] = $value;
            }
            if ($name == $attrName) {
                return;
            }
        }
        $this->attributeReader = null;
    }
    /**
     * @param string $attrName
     * @param string $value
     */
    public function setAttribute($attrName, $value)
    {
        if ($this->getAttribute($attrName) === $value) {
            return;
        }
        $this->dirty = true;
        $this->newAttributes[$attrName] = $value;
    }
    /**
     * @param string $attrName
     */
    public function removeAttribute($attrName)
    {
        $this->dirty = true;
        $this->newAttributes[$attrName] = null;
    }
    /**
     * @return string
     */
    public function getTextContent()
    {
        return $this->textContent;
    }
    /**
     * @param string $textContent
     */
    public function setTextContent($textContent)
    {
        $this->textContent = $textContent;
    }
    /**
     * @param $closingTag
     * @return Tag
     */
    public function withClosingTag($closingTag)
    {
        $new = clone $this;
        $new->closingTag = $closingTag;
        return $new;
    }
    /**
     * @return string
     */
    public function getClosingTag()
    {
        return $this->closingTag;
    }
    public function __toString()
    {
        return $this->getOpening() . $this->textContent . $this->getClosing();
    }
    private function getOpening()
    {
        if ($this->dirty || !isset($this->originalString)) {
            return $this->generateOpeningTag();
        }
        return parent::__toString();
    }
    private function getClosing()
    {
        if ($this->closingTag) {
            return $this->closingTag;
        }
        if ($this->mustHaveClosing() && !$this->isFromParser()) {
            return '</' . $this->tagName . '>';
        }
        return '';
    }
    private function generateOpeningTag()
    {
        $parts = ['<' . $this->tagName];
        foreach ($this->getAttributes() as $name => $value) {
            $parts[] = $this->generateAttribute($name, $value);
        }
        return join(' ', $parts) . '>';
    }
    private function generateAttribute($name, $value)
    {
        $result = $name;
        if ($value != '') {
            $result .= '=' . $this->quoteAttributeValue($value);
        }
        return $result;
    }
    private function quoteAttributeValue($value)
    {
        if (strpos($value, '"') === false) {
            return '"' . htmlspecialchars($value) . '"';
        }
        return "'" . str_replace(['&', "'"], ['&amp;', '&#039;'], $value) . "'";
    }
    private function mustHaveClosing()
    {
        return !\Kibo\Phast\Parsing\HTML\HTMLInfo::isA($this->tagName, \Kibo\Phast\Parsing\HTML\HTMLInfo::VOID_TAG);
    }
    private function isFromParser()
    {
        return isset($this->originalString);
    }
    public function dumpValue()
    {
        $o = $this->tagName;
        foreach ($this->attributes as $name => $_) {
            $o .= " {$name}=\"" . $this->getAttribute($name) . '"';
        }
        if ($this->textContent) {
            $o .= " content=[{$this->textContent}]";
        }
        return $o;
    }
}
namespace Kibo\Phast\Parsing\HTML;

class PCRETokenizer
{
    private $mainPattern = '~
        # Allow duplicate names for subpatterns
        (?J)

        (
            @@COMMENT |
            @@SCRIPT |
            @@STYLE |
            @@CLOSING_TAG |
            @@TAG
        )
    ~Xxsi';
    private $attributePattern = '~
        @attr
    ~Xxsi';
    private $subroutines = ['COMMENT' => '
            <!--.*?-->
        ', 'SCRIPT' => "\n            (?= <script[\\s>]) @@TAG\n            (?'body' .*? )\n            (?'closing_tag' </script/?+(?:\\s[^a-z>]*+)?+> )\n        ", 'STYLE' => "\n            (?= <style[\\s>]) @@TAG\n            (?'body' .*? )\n            (?'closing_tag' </style/?+(?:\\s[^a-z>]*+)?+> )\n        ", 'TAG' => "\n            < @@tag_name \\s*+ @@attrs? @tag_end\n        ", 'tag_name' => "\n            [^\\s>]++\n        ", 'attrs' => '
            (?: @attr )*+
        ', 'attr' => "\n            \\s*+\n            @@attr_name\n            (?: \\s*+ = \\s*+ @attr_value )?\n        ", 'attr_name' => "\n            [^\\s>][^\\s>=]*+\n        ", 'attr_value' => "\n            (?|\n                \"(?'attr_value'[^\"]*+)\" |\n                ' (?'attr_value' [^']*+) ' |\n                (?'attr_value' [^\\s>]*+)\n            )\n        ", 'tag_end' => "\n            \\s*+ >\n        ", 'CLOSING_TAG' => '
            </ @@tag_name [^>]*+ >
        '];
    public function __construct()
    {
        $this->mainPattern = $this->compilePattern($this->mainPattern, $this->subroutines);
        $this->attributePattern = $this->compilePattern($this->attributePattern, $this->subroutines);
    }
    public function tokenize($data)
    {
        $offset = 0;
        while (preg_match($this->mainPattern, $data, $match, PREG_OFFSET_CAPTURE, $offset)) {
            if ($match[0][1] > $offset) {
                $element = new \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Junk();
                $element->originalString = substr($data, $offset, $match[0][1] - $offset);
                (yield $element);
            }
            if (!empty($match['COMMENT'][0])) {
                $element = new \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Comment();
                $element->originalString = $match[0][0];
            } elseif (!empty($match['TAG'][0]) || !empty($match['SCRIPT'][0]) || !empty($match['STYLE'][0])) {
                $attributes = $match['attrs'][0] === '' ? [] : $this->parseAttributes($match['attrs'][0]);
                $element = new \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag($match['tag_name'][0], $attributes);
                $element->originalString = $match['TAG'][0];
                if (isset($match['body'][1]) && $match['body'][1] != -1) {
                    $element->setTextContent($match['body'][0]);
                    $element = $element->withClosingTag($match['closing_tag'][0]);
                }
            } elseif (!empty($match['CLOSING_TAG'][0])) {
                $element = new \Kibo\Phast\Parsing\HTML\HTMLStreamElements\ClosingTag($match['tag_name'][0]);
                $element->originalString = $match[0][0];
            } else {
                throw new \Kibo\Phast\Exceptions\RuntimeException("Unhandled match:\n" . \Kibo\Phast\Common\JSON::prettyEncode($match));
            }
            (yield $element);
            $offset = $match[0][1] + strlen($match[0][0]);
        }
        if ($offset < strlen($data)) {
            $element = new \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Junk();
            $element->originalString = substr($data, $offset);
            (yield $element);
        }
    }
    private function parseAttributes($str)
    {
        $matches = $this->repeatMatch($this->attributePattern, $str);
        foreach ($matches as $match) {
            (yield $match['attr_name'][0] => isset($match['attr_value'][0]) ? html_entity_decode($match['attr_value'][0], ENT_QUOTES, 'UTF-8') : '');
        }
    }
    private function repeatMatch($pattern, $subject)
    {
        $offset = 0;
        while (preg_match($pattern, $subject, $match, PREG_OFFSET_CAPTURE, $offset)) {
            (yield $match);
            $offset = $match[0][1] + strlen($match[0][0]);
        }
        if ($offset < strlen($subject) - 1) {
            throw new \Kibo\Phast\Exceptions\RuntimeException('Unmatched part of subject: ' . substr($subject, $offset));
        }
    }
    /**
     * Replace subroutines in patterns
     */
    private function compilePattern($pattern, array $subroutines)
    {
        return preg_replace_callback('/@(@?)(\\w+)/', function ($match) use($subroutines) {
            $capture = !empty($match[1]);
            $ref = $match[2];
            if (!isset($subroutines[$ref])) {
                throw new \Kibo\Phast\Exceptions\RuntimeException("Unknown pattern '{$ref}' used, or circular reference");
            }
            $subroutine = $subroutines[$ref];
            unset($subroutines[$ref]);
            $replace = $this->compilePattern($subroutine, $subroutines);
            if ($capture) {
                $replace = "(?'{$ref}'{$replace})";
            } else {
                $replace = "(?:{$replace})";
            }
            return $replace;
        }, $pattern);
    }
}
namespace Kibo\Phast;

class PhastDocumentFilters
{
    const DOCUMENT_PATTERN = "~\n        \\s* (<\\?xml[^>]*>)?\n        (\\s* <!--(.*?)-->)*\n        \\s* (<!doctype\\s+html[^>]*>)?\n        (\\s* <!--(.*?)-->)*\n        \\s* <html (?<amp> [^>]* \\s ( amp | \342\232\241 ) [\\s=>] )?\n        .*\n        ( </body> | </html> )\n    ~xsiA";
    /**
     * @return ?OutputBufferHandler
     */
    public static function deploy(array $userConfig = [])
    {
        $runtimeConfig = self::configure($userConfig);
        if (!$runtimeConfig) {
            return null;
        }
        $handler = new \Kibo\Phast\Common\OutputBufferHandler($runtimeConfig['documents']['maxBufferSizeToApply'], function ($html, $applyCheckBuffer) use($runtimeConfig) {
            return self::applyWithRuntimeConfig($html, $runtimeConfig, $applyCheckBuffer);
        });
        $handler->install();
        \Kibo\Phast\Logging\Log::info('Phast deployed!');
        return $handler;
    }
    public static function apply($html, array $userConfig)
    {
        $runtimeConfig = self::configure($userConfig);
        if (!$runtimeConfig) {
            return $html;
        }
        return self::applyWithRuntimeConfig($html, $runtimeConfig);
    }
    private static function configure(array $userConfig)
    {
        $request = \Kibo\Phast\Services\ServiceRequest::fromHTTPRequest(\Kibo\Phast\HTTP\Request::fromGlobals());
        $runtimeConfig = \Kibo\Phast\Environment\Configuration::fromDefaults()->withUserConfiguration(new \Kibo\Phast\Environment\Configuration($userConfig))->withServiceRequest($request)->getRuntimeConfig()->toArray();
        \Kibo\Phast\Logging\Log::init($runtimeConfig['logging'], $request, 'dom-filters');
        \Kibo\Phast\Services\ServiceRequest::setDefaultSerializationMode($runtimeConfig['serviceRequestFormat']);
        if ($request->hasRequestSwitchesSet()) {
            \Kibo\Phast\Logging\Log::info('Request has switches set! Sending "noindex" header!');
            header('X-Robots-Tag: noindex');
        }
        if (!$runtimeConfig['switches']['phast']) {
            \Kibo\Phast\Logging\Log::info('Phast is off. Skipping document filter deployment!');
            return;
        }
        return $runtimeConfig;
    }
    private static function applyWithRuntimeConfig($buffer, $runtimeConfig, $applyCheckBuffer = null)
    {
        if (preg_match('~^\\s*{~', $buffer) && is_object($jsonData = json_decode($buffer))) {
            return self::applyToJson($jsonData, $buffer, $runtimeConfig);
        }
        if (is_null($applyCheckBuffer)) {
            $applyCheckBuffer = $buffer;
        }
        if (!self::shouldApply($applyCheckBuffer, $runtimeConfig)) {
            \Kibo\Phast\Logging\Log::info("Buffer ({bufferSize} bytes) doesn't look like html! Not applying filters", ['bufferSize' => strlen($applyCheckBuffer)]);
            return $buffer;
        }
        $compositeFilter = (new \Kibo\Phast\Filters\HTML\Composite\Factory())->make($runtimeConfig);
        if (self::isAMP($applyCheckBuffer)) {
            $compositeFilter->selectFilters(function ($filter) {
                return $filter instanceof \Kibo\Phast\Filters\HTML\AMPCompatibleFilter;
            });
        }
        return $compositeFilter->apply($buffer);
    }
    private static function applyToJson($jsonData, $buffer, $runtimeConfig)
    {
        if (!$runtimeConfig['optimizeJSONResponses']) {
            return $buffer;
        }
        if (empty($jsonData->html) || !is_string($jsonData->html)) {
            return $buffer;
        }
        $newHtml = self::applyWithRuntimeConfig($jsonData->html, $runtimeConfig);
        if ($newHtml == $jsonData->html) {
            return $buffer;
        }
        $jsonData->html = $newHtml;
        $json = json_encode($jsonData);
        if (!$json) {
            return $buffer;
        }
        return $json;
    }
    private static function shouldApply($buffer, $runtimeConfig)
    {
        if ($runtimeConfig['optimizeHTMLDocumentsOnly']) {
            return preg_match(self::DOCUMENT_PATTERN, $buffer);
        }
        return strpos($buffer, '<') !== false && !preg_match('~^\\s*+{\\s*+"~', $buffer);
    }
    private static function isAMP($buffer)
    {
        return preg_match(self::DOCUMENT_PATTERN, $buffer, $match) && !empty($match['amp']);
    }
}
namespace Kibo\Phast;

class PhastServices
{
    /**
     * @param callable|null $getConfig
     */
    public static function serve(callable $getConfig = null)
    {
        $httpRequest = \Kibo\Phast\HTTP\Request::fromGlobals();
        if ($httpRequest->getHeader('CDN-Loop') && preg_match('~(^|,)\\s*Phast\\b~', $httpRequest->getHeader('CDN-Loop'))) {
            self::exitWithError(508, 'Loop detected', '<p>Phast detected a request loop via the CDN-Loop header.</p>');
        }
        $serviceRequest = \Kibo\Phast\Services\ServiceRequest::fromHTTPRequest($httpRequest);
        $serviceParams = $serviceRequest->getParams();
        if (defined('PHAST_SERVICE')) {
            $service = PHAST_SERVICE;
        } elseif (!isset($serviceParams['service'])) {
            self::exitWithError(404, 'Service parameter absent', '<p>Phast was not able to determine the request parameters. This might be because you are accessing the Phast service file directly without parameters, or because your server configuration causes the PATH_INFO environment variable to be missing.</p>' . '<p>This request has PATH_INFO set to: ' . (isset($_SERVER['PATH_INFO']) ? '"<code>' . self::escape($_SERVER['PATH_INFO']) . '</code>"' : '(none)') . '</p>' . '<p>This request has QUERY_STRING set to: ' . (isset($_SERVER['QUERY_STRING']) ? '"<code>' . self::escape($_SERVER['QUERY_STRING']) . '</code>"' : '(none)') . '</p>' . '<p>Either PATH_INFO or QUERY_STRING must contain the parameters for Phast contained in the URL. If the URL ends with parameters after a <code>/</code> character, those should end up in PATH_INFO. If the URL ends with parameters after a <code>?</code> character, those should end up in QUERY_STRING.</p>' . '<p>If the URL contains parameters, but those are not visible above, your server is misconfigured.</p>');
        } else {
            $service = $serviceParams['service'];
        }
        $redirecting = false;
        if (!headers_sent()) {
            if (isset($serviceParams['src']) && !self::isRewrittenRequest($httpRequest) && self::isSafeRedirectDestination($serviceParams['src'], $httpRequest)) {
                http_response_code(301);
                header('Location: ' . $serviceParams['src']);
                header('Cache-Control: max-age=86400');
                $redirecting = true;
            } else {
                http_response_code(500);
            }
        }
        if ($getConfig === null) {
            $config = [];
        } else {
            $config = $getConfig();
        }
        $userConfig = new \Kibo\Phast\Environment\Configuration($config);
        $runtimeConfig = \Kibo\Phast\Environment\Configuration::fromDefaults()->withUserConfiguration($userConfig)->withServiceRequest($serviceRequest)->getRuntimeConfig()->toArray();
        \Kibo\Phast\Logging\Log::init($runtimeConfig['logging'], $serviceRequest, $service);
        try {
            \Kibo\Phast\Services\ServiceRequest::setDefaultSerializationMode($runtimeConfig['serviceRequestFormat']);
            \Kibo\Phast\Logging\Log::info('Starting service');
            $response = (new \Kibo\Phast\Services\Factory())->make($service, $runtimeConfig)->serve($serviceRequest);
            \Kibo\Phast\Logging\Log::info('Service completed');
        } catch (\Kibo\Phast\Exceptions\UnauthorizedException $e) {
            if (!$redirecting) {
                http_response_code(403);
            }
            echo "Unauthorized\n";
            \Kibo\Phast\Logging\Log::error('Unauthorized exception: {message}', ['message' => $e->getMessage()]);
            exit;
        } catch (\Kibo\Phast\Exceptions\ItemNotFoundException $e) {
            if (!$redirecting) {
                http_response_code(404);
            }
            echo "Item not found\n";
            \Kibo\Phast\Logging\Log::error('Item not found: {message}', ['message' => $e->getMessage()]);
            exit;
        } catch (\Exception $e) {
            echo "Internal error, see logs\n";
            \Kibo\Phast\Logging\Log::critical('Unhandled exception: {type} Message: {message} File: {file} Line: {line}', ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            exit;
        }
        header_remove('Location');
        header_remove('Cache-Control');
        self::output($httpRequest, $response, $runtimeConfig);
    }
    public static function isRewrittenRequest()
    {
        return !!\Kibo\Phast\Services\ServiceRequest::getRewrittenService(\Kibo\Phast\HTTP\Request::fromGlobals());
    }
    public static function output(\Kibo\Phast\HTTP\Request $request, \Kibo\Phast\HTTP\Response $response, array $config, \Kibo\Phast\Common\ObjectifiedFunctions $funcs = null)
    {
        if (is_null($funcs)) {
            $funcs = new \Kibo\Phast\Common\ObjectifiedFunctions();
        }
        $headers = $response->getHeaders();
        $content = $response->getContent();
        if (!self::isIterable($content)) {
            $content = [$content];
        }
        $fp = fopen('php://output', 'wb');
        $zipping = false;
        if ($response->isCompressible() && self::shouldZip($request) && !empty($config['compressServiceResponse'])) {
            $zipping = @$funcs->stream_filter_append($fp, 'zlib.deflate', STREAM_FILTER_WRITE, ['level' => 9, 'window' => 31]);
            if ($zipping) {
                $headers['Content-Encoding'] = 'gzip';
            }
        }
        $maxAge = 86400 * 365;
        $headers += ['Vary' => 'Accept-Encoding', 'Cache-Control' => 'max-age=' . $maxAge, 'Expires' => self::formatHeaderDate(time() + $maxAge), 'X-Accel-Expires' => $maxAge, 'Access-Control-Allow-Origin' => '*', 'ETag' => self::generateETag($headers, $content), 'Last-Modified' => self::formatHeaderDate(time()), 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'"];
        if (is_array($content) && !$zipping) {
            $headers['Content-Length'] = (string) array_sum(array_map('strlen', $content));
        }
        $funcs->http_response_code($response->getCode());
        foreach ($headers as $name => $value) {
            $funcs->header($name . ': ' . $value);
        }
        foreach ($content as $part) {
            fwrite($fp, $part);
        }
        fclose($fp);
    }
    private static function formatHeaderDate($time)
    {
        return gmdate('D, d M Y H:i:s', $time) . ' GMT';
    }
    private static function shouldZip(\Kibo\Phast\HTTP\Request $request)
    {
        return !$request->isCloudflare() && strpos($request->getHeader('Accept-Encoding'), 'gzip') !== false;
    }
    private static function generateETag(array $headers, $content)
    {
        $headersPart = http_build_query($headers);
        $contentPart = self::isIterable($content) ? uniqid() : $content;
        return '"' . md5($headersPart . "\0" . $contentPart) . '"';
    }
    private static function isIterable($thing)
    {
        return is_array($thing) || $thing instanceof \Iterator || $thing instanceof \Generator;
    }
    private static function exitWithError($code, $title, $message)
    {
        http_response_code($code);
        ?>
        <!doctype html>
        <html>
        <head>
        <meta charset="utf-8">
        <title><?php 
        echo self::escape($title);
        ?> &middot; Phast on <?php 
        echo self::escape($_SERVER['SERVER_NAME']);
        ?></title>
        <style>
        html, body { min-height: 100%; }
        body { display: flex; flex-direction: column; align-items: center; }
        .container { max-width: 960px; }
        </style>
        </head>
        <body>
        <div class="container">
        <h1><?php 
        echo self::escape($title);
        ?></h1>
        <?php 
        echo $message;
        ?>
        <hr>
        Phast on <?php 
        echo self::escape($_SERVER['SERVER_NAME']);
        ?>
        </div>
        </body>
        </html>
        <?php 
        exit;
    }
    private static function escape($value)
    {
        return htmlentities((string) $value, ENT_QUOTES, 'UTF-8');
    }
    public static function isSafeRedirectDestination($url, \Kibo\Phast\HTTP\Request $request)
    {
        $url = \Kibo\Phast\ValueObjects\URL::fromString($url);
        if (!in_array($url->getScheme(), ['http', 'https'])) {
            return false;
        }
        $host = $url->getHost();
        if (!$host) {
            return false;
        }
        if ($host === $request->getHeader('Host')) {
            return true;
        }
        if (substr($host, -strlen($request->getHeader('Host')) - 1) === '.' . $request->getHeader('Host')) {
            return true;
        }
        return false;
    }
}
namespace Kibo\Phast\Retrievers;

trait DynamicCacheSaltTrait
{
    public function getCacheSalt(\Kibo\Phast\ValueObjects\URL $url)
    {
        return md5($url->toString()) . '-' . floor(time() / 7200);
    }
}
namespace Kibo\Phast\Retrievers;

class RemoteRetrieverFactory
{
    public function make(array $config)
    {
        return new \Kibo\Phast\Retrievers\RemoteRetriever((new \Kibo\Phast\HTTP\ClientFactory())->make($config));
    }
}
namespace Kibo\Phast\Retrievers;

interface Retriever
{
    /**
     * @param URL $url
     * @return string|bool
     */
    public function retrieve(\Kibo\Phast\ValueObjects\URL $url);
    /**
     * @param URL $url
     * @return integer|bool
     */
    public function getCacheSalt(\Kibo\Phast\ValueObjects\URL $url);
}
namespace Kibo\Phast\Retrievers;

class UniversalRetriever implements \Kibo\Phast\Retrievers\Retriever
{
    /**
     * @var Retriever[]
     */
    private $retrievers = [];
    public function retrieve(\Kibo\Phast\ValueObjects\URL $url)
    {
        return $this->iterateRetrievers(function (\Kibo\Phast\Retrievers\Retriever $retriever) use($url) {
            return $retriever->retrieve($url);
        });
    }
    public function getCacheSalt(\Kibo\Phast\ValueObjects\URL $url)
    {
        return $this->iterateRetrievers(function (\Kibo\Phast\Retrievers\Retriever $retriever) use($url) {
            return $retriever->getCacheSalt($url);
        });
    }
    private function iterateRetrievers(callable $callback)
    {
        foreach ($this->retrievers as $retriever) {
            $result = $callback($retriever);
            if ($result !== false) {
                return $result;
            }
        }
        return false;
    }
    public function addRetriever(\Kibo\Phast\Retrievers\Retriever $retriever)
    {
        $this->retrievers[] = $retriever;
    }
}
namespace Kibo\Phast\Security;

class ServiceSignature
{
    const AUTO_TOKEN_SIZE = 128;
    const SIGNATURE_LENGTH = 16;
    /**
     * @var Cache
     */
    private $cache;
    /**
     * @var array
     */
    private $identities;
    /**
     * ServiceSignature constructor.
     *
     * @param Cache $cache
     */
    public function __construct(\Kibo\Phast\Cache\Cache $cache)
    {
        $this->cache = $cache;
    }
    /**
     * @param string|array $identities
     */
    public function setIdentities($identities)
    {
        if (is_string($identities)) {
            $this->identities = ['' => $identities];
        } else {
            $this->identities = $identities;
        }
    }
    /**
     * @return string
     */
    public function getCacheSalt()
    {
        $identities = $this->getIdentities();
        return md5(join('=>', array_merge(array_keys($identities), array_values($identities))));
    }
    public function sign($value)
    {
        $identities = $this->getIdentities();
        $users = array_keys($identities);
        list($user, $token) = [array_shift($users), array_shift($identities)];
        return $user . substr(md5($token . $value), 0, self::SIGNATURE_LENGTH);
    }
    public function verify($signature, $value)
    {
        $user = substr($signature, 0, -self::SIGNATURE_LENGTH);
        $identities = $this->getIdentities();
        if (!isset($identities[$user])) {
            return false;
        }
        $token = $identities[$user];
        $signer = new self($this->cache);
        $signer->setIdentities([$user => $token]);
        return $signature === $signer->sign($value);
    }
    public static function generateToken()
    {
        $token = '';
        for ($i = 0; $i < self::AUTO_TOKEN_SIZE; $i++) {
            $token .= chr(mt_rand(33, 126));
        }
        return $token;
    }
    private function getIdentities()
    {
        if (!isset($this->identities)) {
            $token = $this->cache->get('security-token', function () {
                return self::generateToken();
            });
            $this->identities = ['' => $token];
        }
        return $this->identities;
    }
}
namespace Kibo\Phast\Security;

class ServiceSignatureFactory
{
    const CACHE_NAMESPACE = 'signature';
    public function make(array $config)
    {
        $cache = new \Kibo\Phast\Cache\Sqlite\Cache(array_merge($config['cache'], ['name' => self::CACHE_NAMESPACE, 'maxSize' => 1024 * 1024]), self::CACHE_NAMESPACE);
        $signature = new \Kibo\Phast\Security\ServiceSignature($cache);
        if (isset($config['securityToken'])) {
            $signature->setIdentities($config['securityToken']);
        }
        return $signature;
    }
}
namespace Kibo\Phast\Services;

abstract class BaseService
{
    use \Kibo\Phast\Logging\LoggingTrait;
    /**
     * @var ServiceSignature
     */
    protected $signature;
    /**
     * @var string[]
     */
    protected $whitelist = [];
    /**
     * @var Retriever
     */
    protected $retriever;
    /**
     * @var ServiceFilter
     */
    protected $filter;
    /**
     * @var array
     */
    protected $config;
    /**
     * BaseService constructor.
     * @param ServiceSignature $signature
     * @param array $whitelist
     * @param Retriever $retriever
     * @param ServiceFilter $filter
     * @param array $config
     */
    public function __construct(\Kibo\Phast\Security\ServiceSignature $signature, array $whitelist, \Kibo\Phast\Retrievers\Retriever $retriever, \Kibo\Phast\Services\ServiceFilter $filter, array $config)
    {
        $this->signature = $signature;
        $this->whitelist = $whitelist;
        $this->retriever = $retriever;
        $this->filter = $filter;
        $this->config = $config;
    }
    /**
     * @param ServiceRequest $request
     * @return Response
     */
    public function serve(\Kibo\Phast\Services\ServiceRequest $request)
    {
        $this->validateRequest($request);
        $request = $this->getParams($request);
        $resource = \Kibo\Phast\ValueObjects\Resource::makeWithRetriever(\Kibo\Phast\ValueObjects\URL::fromString(isset($request['src']) ? $request['src'] : ''), $this->retriever);
        $filtered = $this->filter->apply($resource, $request);
        return $this->makeResponse($filtered, $request);
    }
    /**
     * @param ServiceRequest $request
     * @return array
     */
    protected function getParams(\Kibo\Phast\Services\ServiceRequest $request)
    {
        return $request->getParams();
    }
    /**
     * @param Resource $resource
     * @param array $request
     * @return Response
     */
    protected function makeResponse(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $response = new \Kibo\Phast\HTTP\Response();
        $response->setContent($resource->getContent());
        return $response;
    }
    protected function validateRequest(\Kibo\Phast\Services\ServiceRequest $request)
    {
        $this->validateIntegrity($request);
        try {
            $this->validateToken($request);
        } catch (\Kibo\Phast\Exceptions\UnauthorizedException $e) {
            $this->validateWhitelisted($request);
        }
    }
    protected function validateIntegrity(\Kibo\Phast\Services\ServiceRequest $request)
    {
        $params = $request->getParams();
        if (!isset($params['src'])) {
            throw new \Kibo\Phast\Exceptions\ItemNotFoundException('No source is set!');
        }
    }
    protected function validateToken(\Kibo\Phast\Services\ServiceRequest $request)
    {
        if (!$request->verify($this->signature)) {
            throw new \Kibo\Phast\Exceptions\UnauthorizedException('Invalid token in request: ' . $request->serialize(\Kibo\Phast\Services\ServiceRequest::FORMAT_QUERY));
        }
    }
    protected function validateWhitelisted(\Kibo\Phast\Services\ServiceRequest $request)
    {
        $params = $request->getParams();
        foreach ($this->whitelist as $pattern) {
            if (preg_match($pattern, $params['src'])) {
                return;
            }
        }
        throw new \Kibo\Phast\Exceptions\UnauthorizedException('Not allowed url: ' . $params['src']);
    }
}
namespace Kibo\Phast\Services\Bundler;

class BundlerParamsParser
{
    public function parse(\Kibo\Phast\Services\ServiceRequest $request)
    {
        $result = [];
        foreach ($request->getParams() as $name => $value) {
            if (strpos($name, '_') !== false) {
                list($name, $key) = explode('_', $name, 2);
                $result[$key][$name] = $value;
            }
        }
        return $result;
    }
}
namespace Kibo\Phast\Services\Bundler;

class Factory
{
    use \Kibo\Phast\Services\ServiceFactoryTrait;
    public function make(array $config)
    {
        $cssServiceFactory = new \Kibo\Phast\Services\Css\Factory();
        $jsServiceFactory = new \Kibo\Phast\Services\Scripts\Factory();
        $cssFilter = $this->makeCachingServiceFilter($config, $cssServiceFactory->makeFilter($config), 'bundler-css');
        $jsFilter = $this->makeCachingServiceFilter($config, $jsServiceFactory->makeFilter($config), 'bundler-js');
        return new \Kibo\Phast\Services\Bundler\Service((new \Kibo\Phast\Security\ServiceSignatureFactory())->make($config), $cssServiceFactory->makeRetriever($config), $cssFilter, $jsServiceFactory->makeRetriever($config), $jsFilter, (new \Kibo\Phast\Services\Bundler\TokenRefMakerFactory())->make($config));
    }
}
namespace Kibo\Phast\Services\Bundler;

class Service
{
    use \Kibo\Phast\Logging\LoggingTrait;
    /**
     * @var ServiceSignature
     */
    private $signature;
    /**
     * @var Retriever
     */
    private $cssRetriever;
    /**
     * @var ServiceFilter
     */
    private $cssFilter;
    /**
     * @var Retriever
     */
    private $jsRetriever;
    /**
     * @var ServiceFilter
     */
    private $jsFilter;
    private $tokenRefMaker;
    public function __construct(\Kibo\Phast\Security\ServiceSignature $signature, \Kibo\Phast\Retrievers\Retriever $cssRetriever, \Kibo\Phast\Services\ServiceFilter $cssFilter, \Kibo\Phast\Retrievers\Retriever $jsRetriever, \Kibo\Phast\Services\ServiceFilter $jsFilter, \Kibo\Phast\Services\Bundler\TokenRefMaker $tokenRefMaker)
    {
        $this->signature = $signature;
        $this->cssRetriever = $cssRetriever;
        $this->cssFilter = $cssFilter;
        $this->jsRetriever = $jsRetriever;
        $this->jsFilter = $jsFilter;
        $this->tokenRefMaker = $tokenRefMaker;
    }
    /**
     * @param ServiceRequest $request
     * @return Response
     */
    public function serve(\Kibo\Phast\Services\ServiceRequest $request)
    {
        $response = new \Kibo\Phast\HTTP\Response();
        $response->setHeader('Content-Type', 'application/json');
        $response->setContent($this->streamResponse($request));
        return $response;
    }
    private function streamResponse(\Kibo\Phast\Services\ServiceRequest $request)
    {
        (yield '[');
        $firstRow = true;
        foreach ($this->getParams($request) as $key => $params) {
            if (isset($params['ref'])) {
                $ref = $params['ref'];
                $params = $this->tokenRefMaker->getParams($ref);
                if (!$params) {
                    $this->logger()->error('Could not resolve ref {ref}', ['ref' => $ref]);
                    (yield $this->generateJSONRow(['status' => 404], $firstRow));
                    continue;
                }
            }
            if (!isset($params['src'])) {
                $this->logger()->error('No src found for set {key}', ['key' => $key]);
                (yield $this->generateJSONRow(['status' => 404], $firstRow));
                continue;
            }
            if (!$this->verifyParams($params)) {
                $this->logger()->error('Params verification failed for set {key}', ['key' => $key]);
                (yield $this->generateJSONRow(['status' => 401], $firstRow));
                continue;
            }
            list($retriever, $filter) = $this->getRetrieverAndFilter($params);
            $resource = \Kibo\Phast\ValueObjects\Resource::makeWithRetriever(\Kibo\Phast\ValueObjects\URL::fromString($params['src']), $retriever);
            try {
                $this->logger()->info('Applying for set {key}', ['key' => $key]);
                $filtered = $filter->apply($resource, $params);
                (yield $this->generateJSONRow(['status' => 200, 'content' => $filtered->getContent()], $firstRow));
            } catch (\Kibo\Phast\Exceptions\ItemNotFoundException $e) {
                $this->logger()->error('Could not find {url} for set {key}', ['url' => $params['src'], 'key' => $key]);
                (yield $this->generateJSONRow(['status' => 404], $firstRow));
            } catch (\Exception $e) {
                $this->logger()->critical('Unhandled exception for set {key}: {type} Message: {message} File: {file} Line: {line}', ['key' => $key, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
                (yield $this->generateJSONRow(['status' => 500], $firstRow));
            }
        }
        (yield ']');
    }
    private function getParams(\Kibo\Phast\Services\ServiceRequest $request)
    {
        $params = $request->getParams();
        if (isset($params['src_0'])) {
            return (new \Kibo\Phast\Services\Bundler\BundlerParamsParser())->parse($request);
        }
        return (new \Kibo\Phast\Services\Bundler\ShortBundlerParamsParser())->parse($request);
    }
    private function verifyParams(array $params)
    {
        return \Kibo\Phast\Services\Bundler\ServiceParams::fromArray($params)->verify($this->signature);
    }
    private function getRetrieverAndFilter(array $params)
    {
        if (isset($params['isScript'])) {
            return [$this->jsRetriever, $this->jsFilter];
        }
        return [$this->cssRetriever, $this->cssFilter];
    }
    private function generateJSONRow(array $content, &$firstRow)
    {
        if (!$firstRow) {
            $prepend = ',';
        } else {
            $prepend = '';
            $firstRow = false;
        }
        return $prepend . \Kibo\Phast\Common\JSON::encode($content);
    }
}
namespace Kibo\Phast\Services\Bundler;

class ServiceParams
{
    /**
     * @var string
     */
    private $token;
    /**
     * @var array
     */
    private $params;
    private function __construct()
    {
    }
    /**
     * @param array $params
     * @return ServiceParams
     */
    public static function fromArray(array $params)
    {
        $instance = new self();
        if (isset($params['token'])) {
            $instance->token = $params['token'];
            unset($params['token']);
        }
        $instance->params = $params;
        return $instance;
    }
    /**
     * @param ServiceSignature $signature
     * @return ServiceParams
     */
    public function sign(\Kibo\Phast\Security\ServiceSignature $signature)
    {
        $new = new self();
        $new->token = $this->makeToken($signature);
        $new->params = $this->params;
        return $new;
    }
    /**
     * @param ServiceSignature $signature
     * @return bool
     */
    public function verify(\Kibo\Phast\Security\ServiceSignature $signature)
    {
        if (!isset($this->token)) {
            return false;
        }
        return $this->token == $this->makeToken($signature);
    }
    /**
     * @return mixed
     */
    public function toArray()
    {
        $params = $this->params;
        if ($this->token) {
            $params['token'] = $this->token;
        }
        return $params;
    }
    public function serialize()
    {
        return \Kibo\Phast\Common\JSON::encode($this->toArray());
    }
    private function makeToken(\Kibo\Phast\Security\ServiceSignature $signature)
    {
        $params = $this->params;
        if (isset($params['cacheMarker'])) {
            unset($params['cacheMarker']);
        }
        ksort($params);
        array_walk($params, function (&$item) {
            $item = (string) $item;
        });
        return $signature->sign(json_encode($params));
    }
    public function replaceByTokenRef(\Kibo\Phast\Services\Bundler\TokenRefMaker $maker)
    {
        if (!isset($this->token)) {
            return $this;
        }
        $ref = $maker->getRef($this->token, $this->toArray());
        return $ref ? \Kibo\Phast\Services\Bundler\ServiceParams::fromArray(['ref' => $ref]) : $this;
    }
}
namespace Kibo\Phast\Services\Bundler;

class ShortBundlerParamsParser
{
    public static function getParamsMappings()
    {
        return ['s' => 'src', 'i' => 'strip-imports', 'c' => 'cacheMarker', 't' => 'token', 'j' => 'isScript', 'r' => 'ref'];
    }
    public function parse(\Kibo\Phast\Services\ServiceRequest $request)
    {
        $query_string = $request->getHTTPRequest()->getQueryString();
        if (preg_match('/(^|&)f=/', $query_string)) {
            $query = \Kibo\Phast\ValueObjects\Query::fromString($this->unobfuscateQuery($query_string));
        } else {
            $query = $request->getQuery();
        }
        $query = $this->unshortenParams($query->getIterator());
        $query = $this->uncompressSrcs($query);
        $result = [];
        $current = null;
        foreach ($query as $key => $value) {
            if (in_array($key, ['src', 'ref'])) {
                if ($current) {
                    $result[] = $current;
                }
                $current = [];
            }
            if ($current !== null) {
                $current[$key] = $value;
            }
        }
        if ($current) {
            $result[] = $current;
        }
        return $result;
    }
    private function unobfuscateQuery($query)
    {
        $query = str_rot13($query);
        if (strpos($query, '%2S') !== false) {
            $query = preg_replace_callback('/%../', function ($match) {
                return str_rot13($match[0]);
            }, $query);
        }
        return $query;
    }
    private function unshortenParams(\Generator $query)
    {
        $mappings = self::getParamsMappings();
        foreach ($query as $key => $value) {
            if (isset($mappings[$key])) {
                (yield $mappings[$key] => $value === '' ? '1' : $value);
            } else {
                (yield $key => $value);
            }
        }
    }
    private function uncompressSrcs(\Generator $query)
    {
        $lastUrl = '';
        foreach ($query as $key => $value) {
            if ($key === 'src') {
                $prefixLength = (int) base_convert(substr($value, 0, 2), 36, 10);
                $suffix = substr($value, 2);
                $value = substr($lastUrl, 0, $prefixLength) . $suffix;
                $lastUrl = $value;
            }
            (yield $key => $value);
        }
    }
}
namespace Kibo\Phast\Services\Bundler;

class TokenRefMaker
{
    private $cache;
    public function __construct(\Kibo\Phast\Cache\Cache $cache)
    {
        $this->cache = $cache;
    }
    public function getRef($token, array $params)
    {
        $ref = \Kibo\Phast\Common\Base64url::shortHash(\Kibo\Phast\Common\JSON::encode($params));
        $cachedParams = $this->cache->get($ref);
        if (!$cachedParams) {
            $this->cache->set($ref, $params);
            $cachedParams = $this->cache->get($ref);
        }
        if ($cachedParams === $params) {
            return $ref;
        }
    }
    public function getParams($ref)
    {
        return $this->cache->get($ref);
    }
}
namespace Kibo\Phast\Services\Bundler;

class TokenRefMakerFactory
{
    public function make(array $config)
    {
        $cache = new \Kibo\Phast\Cache\Sqlite\Cache($config['cache'], 'token-refs');
        return new \Kibo\Phast\Services\Bundler\TokenRefMaker($cache);
    }
}
namespace Kibo\Phast\Services\Css;

class Factory
{
    use \Kibo\Phast\Services\ServiceFactoryTrait;
    public function make(array $config)
    {
        $cssComposite = $this->makeFilter($config);
        $composite = $this->makeCachingServiceFilter($config, $cssComposite, 'css-processing-2');
        return new \Kibo\Phast\Services\Css\Service((new \Kibo\Phast\Security\ServiceSignatureFactory())->make($config), [], $this->makeRetriever($config), $composite, $config);
    }
    public function makeRetriever(array $config)
    {
        return $this->makeUniversalCachingRetriever($config, 'css');
    }
    public function makeFilter(array $config)
    {
        return (new \Kibo\Phast\Filters\CSS\Composite\Factory())->make($config);
    }
}
namespace Kibo\Phast\Services\Css;

class Service extends \Kibo\Phast\Services\BaseService
{
    protected function makeResponse(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $response = parent::makeResponse($resource, $request);
        $response->setHeader('Content-Type', 'text/css');
        return $response;
    }
}
namespace Kibo\Phast\Services\Diagnostics;

class Factory
{
    public function make(array $config)
    {
        $logRoot = null;
        foreach ($config['logging']['logWriters'] as $writerConfig) {
            if ($writerConfig['class'] == \Kibo\Phast\Logging\LogWriters\JSONLFile\Writer::class) {
                $logRoot = $writerConfig['logRoot'];
                break;
            }
        }
        return new \Kibo\Phast\Services\Diagnostics\Service($logRoot);
    }
}
namespace Kibo\Phast\Services\Diagnostics;

class Service
{
    private $logRoot;
    public function __construct($logRoot)
    {
        $this->logRoot = $logRoot;
    }
    public function serve(\Kibo\Phast\Services\ServiceRequest $request)
    {
        $params = $request->getParams();
        if (isset($params['documentRequestId'])) {
            $items = $this->getRequestLog($params['documentRequestId']);
        } else {
            $items = $this->getSystemDiagnostics();
        }
        $response = new \Kibo\Phast\HTTP\Response();
        $response->setContent(\Kibo\Phast\Common\JSON::prettyEncode($items));
        $response->setHeader('Content-Type', 'application/json');
        return $response;
    }
    private function getRequestLog($requestId)
    {
        return iterator_to_array((new \Kibo\Phast\Logging\LogReaders\JSONLFile\Reader($this->logRoot, $requestId))->readEntries());
    }
    private function getSystemDiagnostics()
    {
        return (new \Kibo\Phast\Diagnostics\SystemDiagnostics())->run(require PHAST_CONFIG_FILE);
    }
}
namespace Kibo\Phast\Services;

class Factory
{
    /**
     * @param string $service
     * @param array $config
     * @return BaseService
     * @throws ItemNotFoundException
     */
    public function make($service, array $config)
    {
        if (!preg_match('/^[a-z]+$/', $service)) {
            throw new \Kibo\Phast\Exceptions\ItemNotFoundException('Bad service');
        }
        $class = __NAMESPACE__ . '\\' . ucfirst($service) . '\\Factory';
        if (class_exists($class)) {
            return (new $class())->make($config);
        }
        throw new \Kibo\Phast\Exceptions\ItemNotFoundException('Unknown service');
    }
}
namespace Kibo\Phast\Services\Images;

class Factory
{
    public function make(array $config)
    {
        if ($config['images']['api-mode']) {
            $retriever = new \Kibo\Phast\Retrievers\PostDataRetriever();
        } else {
            $retriever = new \Kibo\Phast\Retrievers\UniversalRetriever();
            $retriever->addRetriever(new \Kibo\Phast\Retrievers\LocalRetriever($config['retrieverMap']));
            $retriever->addRetriever((new \Kibo\Phast\Retrievers\RemoteRetrieverFactory())->make($config));
        }
        return new \Kibo\Phast\Services\Images\Service((new \Kibo\Phast\Security\ServiceSignatureFactory())->make($config), $config['images']['whitelist'], $retriever, (new \Kibo\Phast\Filters\Image\Composite\Factory($config))->make(), $config);
    }
}
namespace Kibo\Phast\Services\Images;

class Service extends \Kibo\Phast\Services\BaseService
{
    protected function getParams(\Kibo\Phast\Services\ServiceRequest $request)
    {
        $params = parent::getParams($request);
        if ($this->proxySupportsAccept($request->getHTTPRequest())) {
            $params['varyAccept'] = true;
            if ($this->browserSupportsWebp($request->getHTTPRequest())) {
                $params['preferredType'] = \Kibo\Phast\Filters\Image\Image::TYPE_WEBP;
                \Kibo\Phast\Logging\Log::info('WebP will be served if possible!');
            }
        }
        return $params;
    }
    protected function makeResponse(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $response = parent::makeResponse($resource, $request);
        $srcUrl = $resource->getUrl();
        $response->setHeader('Link', "<{$srcUrl}>; rel=\"canonical\"");
        $response->setHeader('Content-Type', $resource->getMimeType());
        if ($resource->getMimeType() != \Kibo\Phast\Filters\Image\Image::TYPE_PNG && @$request['varyAccept']) {
            $response->setHeader('Vary', 'Accept');
        }
        return $response;
    }
    protected function validateIntegrity(\Kibo\Phast\Services\ServiceRequest $request)
    {
        if (!$this->config['images']['api-mode']) {
            parent::validateIntegrity($request);
        }
    }
    protected function validateWhitelisted(\Kibo\Phast\Services\ServiceRequest $request)
    {
        if (!$this->config['images']['api-mode']) {
            parent::validateWhitelisted($request);
        }
    }
    private function browserSupportsWebp(\Kibo\Phast\HTTP\Request $request)
    {
        return strpos($request->getHeader('accept'), 'image/webp') !== false;
    }
    private function proxySupportsAccept(\Kibo\Phast\HTTP\Request $request)
    {
        return !$request->isCloudflare();
    }
}
namespace Kibo\Phast\Services\Scripts;

class Factory
{
    use \Kibo\Phast\Services\ServiceFactoryTrait;
    public function make(array $config)
    {
        $cachedComposite = $this->makeFilter($config);
        return new \Kibo\Phast\Services\Scripts\Service((new \Kibo\Phast\Security\ServiceSignatureFactory())->make($config), $config['scripts']['whitelist'], $this->makeRetriever($config), $this->makeCachingServiceFilter($config, $cachedComposite, 'scripts-minified'), $config);
    }
    public function makeRetriever(array $config)
    {
        return $this->makeUniversalCachingRetriever($config, 'scripts');
    }
    public function makeFilter(array $config)
    {
        $filter = new \Kibo\Phast\Filters\Service\CompositeFilter();
        $filter->addFilter(new \Kibo\Phast\Filters\Text\Decode\Filter());
        $filter->addFilter(new \Kibo\Phast\Filters\JavaScript\Minification\JSMinifierFilter(@$config['scripts']['removeLicenseHeaders']));
        return $filter;
    }
}
namespace Kibo\Phast\Services\Scripts;

class Service extends \Kibo\Phast\Services\BaseService
{
    protected function makeResponse(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $response = parent::makeResponse($resource, $request);
        $response->setHeader('Content-Type', 'application/javascript');
        return $response;
    }
}
namespace Kibo\Phast\Services;

trait ServiceFactoryTrait
{
    /**
     * @param array $config
     * @param $cacheNamespace
     * @return UniversalRetriever
     */
    public function makeUniversalCachingRetriever(array $config, $cacheNamespace)
    {
        $retriever = new \Kibo\Phast\Retrievers\UniversalRetriever();
        $retriever->addRetriever(new \Kibo\Phast\Retrievers\LocalRetriever($config['retrieverMap']));
        $retriever->addRetriever(new \Kibo\Phast\Retrievers\CachingRetriever(new \Kibo\Phast\Cache\Sqlite\Cache($config['cache'], $cacheNamespace), (new \Kibo\Phast\Retrievers\RemoteRetrieverFactory())->make($config)));
        return $retriever;
    }
    public function makeCachingServiceFilter(array $config, \Kibo\Phast\Filters\Service\CompositeFilter $compositeFilter, $cacheNamespace)
    {
        return new \Kibo\Phast\Filters\Service\CachingServiceFilter(new \Kibo\Phast\Cache\Sqlite\Cache($config['cache'], $cacheNamespace), $compositeFilter, new \Kibo\Phast\Retrievers\LocalRetriever($config['retrieverMap']));
    }
}
namespace Kibo\Phast\Services;

interface ServiceFilter
{
    /**
     * @param Resource $resource
     * @param array $request
     * @return Resource
     */
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request);
}
namespace Kibo\Phast\Services;

class ServiceRequest
{
    const FORMAT_QUERY = 1;
    const FORMAT_PATH = 2;
    private static $defaultSerializationMode = self::FORMAT_PATH;
    /**
     * @var string
     */
    private static $propagatedSwitches = '';
    /**
     * @var Switches
     */
    private static $switches;
    /**
     * @var string
     */
    private static $documentRequestId;
    /**
     * @var Request
     */
    private $httpRequest;
    /**
     * @var ?URL
     */
    private $url;
    /**
     * @var Query
     */
    private $query;
    /**
     * @var string
     */
    private $token;
    /**
     * @var bool
     */
    private $trusted = false;
    public function __construct()
    {
        if (!isset(self::$switches)) {
            self::$switches = new \Kibo\Phast\Environment\Switches();
        }
        $this->query = new \Kibo\Phast\ValueObjects\Query();
    }
    public static function resetRequestState()
    {
        self::$defaultSerializationMode = self::FORMAT_PATH;
        self::$propagatedSwitches = '';
        self::$switches = null;
        self::$documentRequestId = null;
    }
    public static function setDefaultSerializationMode($mode)
    {
        self::$defaultSerializationMode = $mode;
    }
    public static function getDefaultSerializationMode()
    {
        return self::$defaultSerializationMode;
    }
    public static function fromHTTPRequest(\Kibo\Phast\HTTP\Request $request)
    {
        $instance = new self();
        self::$switches = new \Kibo\Phast\Environment\Switches();
        $instance->httpRequest = $request;
        if ($request->getCookie('phast')) {
            self::$switches = \Kibo\Phast\Environment\Switches::fromString($request->getCookie('phast'));
        }
        if ($service = self::getRewrittenService($request)) {
            $instance->query = \Kibo\Phast\ValueObjects\Query::fromAssoc(['service' => $service, 'src' => $request->getAbsoluteURI()]);
            $instance->trusted = true;
        } else {
            $query = $request->getQuery();
            if ($query->get('src')) {
                $query->set('src', preg_replace('~^hxxp(?=s?://)~', 'http', $query->get('src')));
            }
            $pathInfo = $request->getPathInfo();
            if ($pathInfo !== null && ($pathParams = self::parseBase64PathInfo($pathInfo))) {
                $query->update($pathParams);
            } elseif ($pathInfo !== null) {
                $query->update(self::parsePathInfo($pathInfo));
            }
            if ($token = $query->pop('token')) {
                $instance->token = $token;
            }
            $instance->query = $query;
            if ($query->get('phast')) {
                self::$propagatedSwitches = $query->get('phast');
                $paramsSwitches = \Kibo\Phast\Environment\Switches::fromString($query->get('phast'));
                self::$switches = self::$switches->merge($paramsSwitches);
            }
            if ($query->get('documentRequestId')) {
                self::$documentRequestId = $query->get('documentRequestId');
            } else {
                self::$documentRequestId = (string) mt_rand(0, 999999999);
            }
        }
        return $instance;
    }
    public static function getRewrittenService(\Kibo\Phast\HTTP\Request $request)
    {
        if ($service = $request->getEnvValue('REDIRECT_PHAST_SERVICE')) {
            return $service;
        }
        if ($service = $request->getEnvValue('PHAST_SERVICE')) {
            return $service;
        }
        return null;
    }
    public function hasRequestSwitchesSet()
    {
        return !empty(self::$propagatedSwitches);
    }
    /**
     * @return Switches
     */
    public function getSwitches()
    {
        return self::$switches;
    }
    /**
     * @return array
     */
    public function getParams()
    {
        return $this->query->toAssoc();
    }
    /**
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }
    /**
     * @return Request
     */
    public function getHTTPRequest()
    {
        return $this->httpRequest;
    }
    /**
     * @return string
     */
    public function getDocumentRequestId()
    {
        return self::$documentRequestId;
    }
    /**
     * @param array $params
     * @return ServiceRequest
     */
    public function withParams(array $params)
    {
        $result = clone $this;
        $result->query = \Kibo\Phast\ValueObjects\Query::fromAssoc($params);
        return $result;
    }
    /**
     * @param URL $url
     * @return ServiceRequest
     */
    public function withUrl(\Kibo\Phast\ValueObjects\URL $url)
    {
        $result = clone $this;
        $result->url = $url;
        return $result;
    }
    /**
     * @param ServiceSignature $signature
     * @return ServiceRequest
     */
    public function sign(\Kibo\Phast\Security\ServiceSignature $signature)
    {
        $token = $signature->sign($this->getVerificationString());
        $result = clone $this;
        $result->token = $token;
        return $result;
    }
    /**
     * @param ServiceSignature $signature
     * @return bool
     */
    public function verify(\Kibo\Phast\Security\ServiceSignature $signature)
    {
        return $this->trusted || $signature->verify($this->token, $this->getVerificationString()) || $signature->verify($this->token, $this->getVerificationStringWithoutStemSuffix());
    }
    private static function parsePathInfo($string)
    {
        $values = new \Kibo\Phast\ValueObjects\Query();
        $parts = explode('/', $string);
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $pair = explode('=', $part);
            if (isset($pair[1])) {
                $values->set($pair[0], self::decodeSingleValue($pair[1]));
            } elseif (preg_match('/^__p__(@[1-9][0-9]*x)?\\./', $pair[0], $match)) {
                if (!empty($match[1]) && $values->has('src')) {
                    $values->set('src', self::appendStemSuffix($values->get('src'), $match[1]));
                }
                break;
            } else {
                $values->set('src', self::decodeSingleValue($pair[0]));
            }
        }
        return $values;
    }
    private static function decodeSingleValue($value)
    {
        return urldecode(str_replace('-', '%', $value));
    }
    private static function appendStemSuffix($src, $suffix)
    {
        $url = \Kibo\Phast\ValueObjects\URL::fromString($src);
        $path = preg_replace_callback('/\\.\\w+$/', function ($match) use($suffix) {
            return $suffix . $match[0];
        }, $url->getPath());
        return $url->withPath($path)->toString();
    }
    private static function parseBase64PathInfo($string)
    {
        if (!preg_match('~
            (?<query> (?:/[a-z0-9_-]+)+ )
            \\.q
            (?<retina> @[1-9][0-9]*x )?
            \\.[a-z]{2,4} $
        ~ix', $string, $match)) {
            return null;
        }
        $data = str_replace('/', '', $match['query']);
        $values = \Kibo\Phast\ValueObjects\Query::fromString(\Kibo\Phast\Common\Base64url::decode($data));
        if (!empty($match['retina'])) {
            $values->set('src', self::appendStemSuffix($values->get('src'), $match['retina']));
        }
        return $values;
    }
    /**
     * @param callable $paramsFilter
     * @return string
     */
    private function getVerificationString($paramsFilter = null)
    {
        $params = $this->getAllParams();
        if ($paramsFilter) {
            $params = $paramsFilter($params);
        }
        ksort($params);
        return http_build_query($params);
    }
    private function getVerificationStringWithoutStemSuffix()
    {
        return $this->getVerificationString(function ($params) {
            if (isset($params['src'])) {
                $params['src'] = $this->stripStemSuffix($params['src']);
            }
            return $params;
        });
    }
    private function stripStemSuffix($src)
    {
        $url = \Kibo\Phast\ValueObjects\URL::fromString($src);
        $path = preg_replace('/@[1-9][0-9]*x(?=\\.\\w+$)/', '', $url->getPath());
        return $url->withPath($path)->toString();
    }
    public function serialize($format = null)
    {
        $params = $this->getAllParams();
        if ($this->token) {
            $params['token'] = $this->token;
        }
        if (is_null($format)) {
            $format = self::$defaultSerializationMode;
        }
        if ($format == self::FORMAT_PATH) {
            return $this->serializeToPathFormat($params);
        }
        return $this->serializeToQueryFormat($params);
    }
    public function getAllParams()
    {
        $urlParams = [];
        if ($this->url) {
            parse_str((string) $this->url->getQuery(), $urlParams);
        }
        $params = array_merge($urlParams, $this->query->toAssoc());
        if (!empty(self::$propagatedSwitches)) {
            $params['phast'] = self::$propagatedSwitches;
        }
        if (self::$switches->isOn(\Kibo\Phast\Environment\Switches::SWITCH_DIAGNOSTICS)) {
            $params['documentRequestId'] = self::$documentRequestId;
        }
        return $params;
    }
    private function serializeToQueryFormat(array $params)
    {
        $encoded = http_build_query($params);
        if (!isset($this->url)) {
            return $encoded;
        }
        $serialized = preg_replace('~\\?.*~', '', (string) $this->url);
        if (self::$defaultSerializationMode === self::FORMAT_PATH && !preg_match('~/$~', $serialized)) {
            $serialized .= '/' . $this->getDummyFilename($params);
        }
        return $serialized . '?' . $encoded;
    }
    /** @return string */
    private function serializeToPathFormat(array $params)
    {
        $query = \Kibo\Phast\Common\Base64url::encode(http_build_query($params));
        $path = '/' . $this->insertPathSeparators($query . '.q.' . $this->getDummyExtension($params));
        if (isset($this->url)) {
            return preg_replace(['~\\?.*~', '~/$~'], '', $this->url) . $path;
        }
        return $path;
    }
    private function insertPathSeparators($path)
    {
        return strrev(implode('/', str_split(strrev($path), 255)));
    }
    private function getDummyFilename(array $params)
    {
        return '__p__.' . $this->getDummyExtension($params);
    }
    private function getDummyExtension(array $params)
    {
        $default = 'js';
        if (empty($params['src'])) {
            return $default;
        }
        $url = \Kibo\Phast\ValueObjects\URL::fromString($params['src']);
        $ext = strtolower($url->getExtension());
        if (preg_match('/^(jpe?g|gif|png|js|css)$/', $ext)) {
            return $ext;
        }
        return $default;
    }
}
namespace Kibo\Phast\ValueObjects;

class PhastJavaScript
{
    /**
     * @var string
     */
    private $filename;
    /**
     * @var ?string
     */
    private $rawScript;
    /**
     * @var ?string
     */
    private $minifiedScript;
    /**
     * @var string
     */
    private $configKey;
    /**
     * @var mixed
     */
    private $config;
    private function __construct(string $filename, string $script, bool $minified)
    {
        $this->filename = $filename;
        if ($minified) {
            $this->minifiedScript = $script;
        } else {
            $this->rawScript = $script;
        }
    }
    /**
     * @param string $filename
     * @param ObjectifiedFunctions|null $funcs
     */
    public static function fromFile($filename, \Kibo\Phast\Common\ObjectifiedFunctions $funcs = null)
    {
        $funcs = $funcs ? $funcs : new \Kibo\Phast\Common\ObjectifiedFunctions();
        $contents = $funcs->file_get_contents($filename);
        if ($contents === false) {
            throw new \RuntimeException("Could not read script: {$filename}");
        }
        return new self($filename, $contents, false);
    }
    /**
     * @param string $filename
     * @param string $contents
     */
    public static function fromString($filename, $contents)
    {
        return new self($filename, $contents, true);
    }
    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }
    /**
     * @return bool|string
     */
    public function getContents()
    {
        if ($this->minifiedScript === null) {
            $this->minifiedScript = (new \Kibo\Phast\Common\JSMinifier($this->rawScript))->min();
        }
        return $this->minifiedScript;
    }
    /**
     * @return string
     */
    public function getCacheSalt()
    {
        $hash = md5($this->rawScript ?? $this->minifiedScript, true);
        return substr(base64_encode($hash), 0, 16);
    }
    /**
     * @param string $configKey
     * @param mixed $config
     */
    public function setConfig($configKey, $config)
    {
        $this->configKey = $configKey;
        $this->config = $config;
    }
    /**
     * @return bool
     */
    public function hasConfig()
    {
        return isset($this->configKey);
    }
    /**
     * @return string
     */
    public function getConfigKey()
    {
        return $this->configKey;
    }
    /**
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config;
    }
}
namespace Kibo\Phast\ValueObjects;

class Query implements \IteratorAggregate
{
    private $tuples = [];
    /**
     * @param array $assoc
     * @return Query
     */
    public static function fromAssoc($assoc)
    {
        $result = new static();
        foreach ($assoc as $k => $v) {
            $result->add($k, $v);
        }
        return $result;
    }
    /**
     * @param string $string
     * @return Query
     */
    public static function fromString($string)
    {
        $result = new static();
        foreach (explode('&', $string) as $piece) {
            if ($piece === '') {
                continue;
            }
            $parts = array_map('urldecode', explode('=', $piece, 2));
            $result->add($parts[0], isset($parts[1]) ? $parts[1] : '');
        }
        return $result;
    }
    public function add($key, $value)
    {
        $this->tuples[] = [(string) $key, (string) $value];
    }
    public function get($key, $default = null)
    {
        foreach ($this->tuples as $tuple) {
            if ($tuple[0] === (string) $key) {
                return $tuple[1];
            }
        }
        return $default;
    }
    public function delete($key)
    {
        $this->tuples = array_filter($this->tuples, function ($tuple) use($key) {
            return $tuple[0] !== (string) $key;
        });
    }
    public function set($key, $value)
    {
        $this->delete($key);
        $this->add($key, $value);
    }
    public function has($key)
    {
        foreach ($this->tuples as $tuple) {
            if ($tuple[0] === (string) $key) {
                return true;
            }
        }
        return false;
    }
    public function update(\Kibo\Phast\ValueObjects\Query $source)
    {
        foreach ($source as $key => $value) {
            $this->delete($key);
        }
        foreach ($source as $key => $value) {
            $this->add($key, $value);
        }
    }
    public function toAssoc()
    {
        $assoc = [];
        foreach ($this->tuples as $tuple) {
            if (!array_key_exists($tuple[0], $assoc)) {
                $assoc[$tuple[0]] = $tuple[1];
            }
        }
        return $assoc;
    }
    public function getIterator() : \Generator
    {
        foreach ($this->tuples as $tuple) {
            (yield $tuple[0] => $tuple[1]);
        }
    }
    public function pop($key)
    {
        $value = $this->get($key);
        $this->delete($key);
        return $value;
    }
    public function getAll($key)
    {
        $result = [];
        foreach ($this->tuples as $tuple) {
            if ($tuple[0] === (string) $key) {
                $result[] = $tuple[1];
            }
        }
        return $result;
    }
}
namespace Kibo\Phast\ValueObjects;

class Resource
{
    const EXTENSION_TO_MIME_TYPE = ['gif' => 'image/gif', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'bmp' => 'image/bmp', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json'];
    /**
     * @var URL
     */
    private $url;
    /**
     * @var Retriever
     */
    private $retriever;
    /**
     * @var string
     */
    private $content;
    /**
     * @var string
     */
    private $mimeType;
    /**
     * @var Resource[]
     */
    private $dependencies = [];
    private function __construct()
    {
    }
    public static function makeWithContent(\Kibo\Phast\ValueObjects\URL $url, $content, $mimeType = null)
    {
        $instance = new self();
        $instance->url = $url;
        $instance->mimeType = $mimeType;
        $instance->content = $content;
        return $instance;
    }
    public static function makeWithRetriever(\Kibo\Phast\ValueObjects\URL $url, \Kibo\Phast\Retrievers\Retriever $retriever, $mimeType = null)
    {
        $instance = new self();
        $instance->url = $url;
        $instance->mimeType = $mimeType;
        $instance->retriever = $retriever;
        return $instance;
    }
    /**
     * @return URL
     */
    public function getUrl()
    {
        return $this->url;
    }
    /**
     * @return string
     * @throws ItemNotFoundException
     */
    public function getContent()
    {
        if (!isset($this->content)) {
            $this->content = $this->retriever->retrieve($this->url);
            if ($this->content === false) {
                throw new \Kibo\Phast\Exceptions\ItemNotFoundException("Could not get {$this->url}");
            }
        }
        return $this->content;
    }
    /**
     * @return string|null
     */
    public function getMimeType()
    {
        if (!isset($this->mimeType)) {
            $ext = strtolower($this->url->getExtension());
            $ext2mime = self::EXTENSION_TO_MIME_TYPE;
            if (isset($ext2mime[$ext])) {
                $this->mimeType = self::EXTENSION_TO_MIME_TYPE[$ext];
            }
        }
        return $this->mimeType;
    }
    /**
     * @return bool|int
     */
    public function getSize()
    {
        if (isset($this->retriever) && method_exists($this->retriever, 'getSize')) {
            return $this->retriever->getSize($this->url);
        }
        if (isset($this->content)) {
            return strlen($this->content);
        }
        return false;
    }
    public function toDataURL()
    {
        $mime = $this->getMimeType();
        $content = $this->getContent();
        return "data:{$mime};base64," . base64_encode($content);
    }
    /**
     * @return Resource[]
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }
    /**
     * @return bool|int
     */
    public function getCacheSalt()
    {
        return isset($this->retriever) ? $this->retriever->getCacheSalt($this->url) : 0;
    }
    /**
     * @param string $content
     * @param string|null $mimeType
     * @return Resource
     */
    public function withContent($content, $mimeType = null)
    {
        $new = clone $this;
        $new->content = $content;
        if (!is_null($mimeType)) {
            $new->mimeType = $mimeType;
        }
        return $new;
    }
    /**
     * @param Resource[] $dependencies
     * @return Resource
     */
    public function withDependencies(array $dependencies)
    {
        $new = clone $this;
        $new->dependencies = $dependencies;
        return $new;
    }
}
namespace Kibo\Phast\ValueObjects;

class URL
{
    /**
     * @var string
     */
    private $scheme;
    /**
     * @var string
     */
    private $host;
    /**
     * @var string
     */
    private $port;
    /**
     * @var string
     */
    private $user;
    /**
     * @var string
     */
    private $pass;
    /**
     * @var string
     */
    private $path;
    /**
     * @var string
     */
    private $query;
    /**
     * @var string
     */
    private $fragment;
    private function __construct()
    {
    }
    /**
     * @param $string
     * @return URL
     */
    public static function fromString($string)
    {
        $components = parse_url($string);
        if (!$components) {
            return new self();
        }
        return self::fromArray($components);
    }
    /**
     * @param array $arr Should follow the format produced by parse_url()
     * @return URL
     * @see parse_url()
     */
    public static function fromArray(array $arr)
    {
        $url = new self();
        foreach ($arr as $key => $value) {
            $url->{$key} = $key == 'path' ? $url->normalizePath($value) : $value;
        }
        return $url;
    }
    /**
     * If $this can be interpreted as relative to $base,
     * will produce URL that is $base/$this.
     * Otherwise the returned URL will point to the same place as $this
     *
     * @param URL $base
     * @return URL
     *
     * @example this: www/htdocs + base: /var -> /var/www/htdocs
     * @example this: /var + base: http://example.com -> http://example.com/var
     * @example this: /var + base: /www -> /var
     */
    public function withBase(\Kibo\Phast\ValueObjects\URL $base)
    {
        $new = clone $this;
        foreach (['scheme', 'host', 'port', 'user', 'pass', 'path'] as $key) {
            if ($key == 'path') {
                $new->path = $this->resolvePath($base->path, $this->path);
            } elseif (!isset($this->{$key}) && isset($base->{$key})) {
                $new->{$key} = $base->{$key};
            } elseif (isset($this->{$key})) {
                break;
            }
        }
        return $new;
    }
    /**
     * Tells whether $this can be interpreted as at the same host as $url
     *
     * @param URL $url
     * @return bool
     */
    public function isLocalTo(\Kibo\Phast\ValueObjects\URL $url)
    {
        return empty($this->host) || $this->host === $url->host;
    }
    /**
     * @return string
     */
    public function toString()
    {
        $pass = isset($this->pass) ? ':' . $this->pass : '';
        $pass = isset($this->user) || isset($this->pass) ? "{$pass}@" : '';
        return $this->encodeSpecialCharacters(implode('', [isset($this->scheme) ? $this->scheme . '://' : '', $this->user, $pass, $this->host, isset($this->port) ? ':' . $this->port : '', $this->path, isset($this->query) ? '?' . $this->query : '', isset($this->fragment) ? '#' . $this->fragment : '']));
    }
    private function encodeSpecialCharacters($string)
    {
        return preg_replace_callback('~[^' . preg_quote('!#$&\'()*+,/:;=?@[]', '~') . preg_quote('-_.~', '~') . 'A-Za-z0-9%' . ']~', function ($match) {
            return rawurlencode($match[0]);
        }, $string);
    }
    private function normalizePath($path)
    {
        $stack = [];
        $head = null;
        foreach (explode('/', $path) as $part) {
            if ($part == '.' || $part == '') {
                continue;
            }
            if (!is_null($head) && $part == '..' && $head != '..') {
                array_pop($stack);
                $head = empty($stack) ? null : $stack[count($stack) - 1];
            } else {
                $stack[] = $head = $part;
            }
        }
        $normalized = substr($path, 0, 1) == '/' ? '/' : '';
        if (!empty($stack)) {
            $normalized .= join('/', $stack);
            $normalized .= substr($path, -1) == '/' ? '/' : '';
        }
        return $normalized;
    }
    private function resolvePath($base, $requested)
    {
        if (!$requested) {
            return $base;
        }
        if ($requested[0] == '/') {
            return $requested;
        }
        if (substr($base, -1, 1) == '/') {
            $usedBase = $base;
        } else {
            $usedBase = dirname($base);
        }
        return rtrim($usedBase, '/') . '/' . $requested;
    }
    /**
     * @return string
     */
    public function getScheme()
    {
        return $this->scheme;
    }
    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }
    /**
     * @return string
     */
    public function getPort()
    {
        return $this->port;
    }
    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }
    /**
     * @return string
     */
    public function getPass()
    {
        return $this->pass;
    }
    /** @return string */
    public function getDecodedPath()
    {
        return urldecode($this->path);
    }
    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }
    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }
    /**
     * @return string
     */
    public function getExtension()
    {
        $matches = [];
        if (preg_match('/\\.([^.]*)$/', $this->path, $matches)) {
            return $matches[1];
        }
        return '';
    }
    /**
     * @return string
     */
    public function getFragment()
    {
        return $this->fragment;
    }
    /**
     * @param string $path
     * @return self
     */
    public function withPath($path)
    {
        $url = clone $this;
        $url->path = (string) $path;
        return $url;
    }
    /**
     * @param string|null $query
     * @return self
     */
    public function withQuery($query)
    {
        $url = clone $this;
        if ($query === null) {
            $url->query = null;
        } else {
            $url->query = (string) $query;
        }
        return $url;
    }
    /**
     * @return self
     */
    public function withoutQuery()
    {
        return $this->withQuery(null);
    }
    public function __toString()
    {
        return $this->toString();
    }
    public function rewrite(\Kibo\Phast\ValueObjects\URL $from, \Kibo\Phast\ValueObjects\URL $to)
    {
        $str_from = rtrim($from->toString(), '/');
        $str_to = rtrim($to->toString(), '/');
        return \Kibo\Phast\ValueObjects\URL::fromString(preg_replace('~^' . preg_quote($str_from, '~') . '(?=$|/)~', $str_to, $this->toString()));
    }
}
namespace Kibo\PhastPlugins\SDK\AJAX;

/**
 * Handles AJAX requests to the plugin admin.
 * Use this class to handle requests to the plugin's admin AJAX end point
 *
 * Class RequestsDispatcher
 */
class RequestsDispatcher
{
    const KEY_ACTION = 'phast-plugins-action';
    /**
     * @var PhastUser
     */
    private $user;
    /**
     * @var SDK
     */
    private $sdk;
    /**
     * RequestsDispatcher constructor.
     * @param PhastUser $user
     * @param SDK $sdk
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Security\PhastUser $user, \Kibo\PhastPlugins\SDK\SDK $sdk)
    {
        $this->user = $user;
        $this->sdk = $sdk;
    }
    /**
     * Handles ajax requests to plugin's admin AJAX end point
     *
     * @param array $request The $_POST data to the request
     * @return mixed Must be json encoded and returned to the client
     */
    public function dispatch(array $request)
    {
        if (!$this->user->mayModifySettings()) {
            return false;
        }
        $action = isset($request[self::KEY_ACTION]) ? $request[self::KEY_ACTION] : '';
        if ($action == 'save-settings') {
            $this->sdk->getPluginConfiguration()->save($request);
            return $this->makeResponse(true, $this->sdk->getAdminPanelData()->get());
        }
        if ($action == 'dismiss-notice') {
            $this->sdk->getPluginConfiguration()->hideActivationNotification();
            return $this->makeResponse(true);
        }
        return $this->makeResponse(false);
    }
    private function makeResponse($success, $data = null)
    {
        return ['phast-success' => $success, 'phast-data' => $data];
    }
}
namespace Kibo\PhastPlugins\SDK\APIs;

/**
 * Presents convenient methods for common tasks.
 *
 * Class Phast
 */
class Phast
{
    /**
     * @var PhastConfiguration
     */
    private $config;
    /**
     * PhastAPI constructor.
     * @param PhastConfiguration $config
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Configuration\PhastConfiguration $config)
    {
        $this->config = $config;
    }
    /**
     * Applies phast filters to $html
     * with a configuration suited for full documents
     *
     * @param $html
     * @return string
     */
    public function applyFiltersForDocument($html)
    {
        return \Kibo\Phast\PhastDocumentFilters::apply($html, $this->config->getForDocuments());
    }
    /**
     * Applies phast filters to $html
     * with a configuration suited for html snippets
     *
     * @param $html
     * @return string
     */
    public function applyFiltersForSnippets($html)
    {
        return \Kibo\Phast\PhastDocumentFilters::apply($html, $this->config->getForHTMLSnippets());
    }
    /**
     * Deploys phast output buffer filters
     * with a configuration suited for full documents
     */
    public function deployOutputBufferForDocument()
    {
        return \Kibo\Phast\PhastDocumentFilters::deploy($this->config->getForDocuments());
    }
    /**
     * Deploys phast output buffer filters
     * with a configuration suited for html snippets
     */
    public function deployOutputBufferForSnippets()
    {
        return \Kibo\Phast\PhastDocumentFilters::deploy($this->config->getForHTMLSnippets());
    }
}
namespace Kibo\PhastPlugins\SDK\APIs;

/**
 * Presents convenient methods for common tasks.
 *
 * Class Service
 */
class Service
{
    /**
     * @var ServiceConfiguration
     */
    private $config;
    /**
     * Service constructor.
     * @param ServiceConfiguration $config
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Configuration\ServiceConfiguration $config)
    {
        $this->config = $config;
    }
    /**
     * Configures the services and serves the request
     */
    public function serve()
    {
        \Kibo\Phast\PhastServices::serve(function () {
            return $this->config->get();
        });
    }
}
namespace Kibo\PhastPlugins\SDK\AdminPanel;

/**
 * Represents the admin panel used for plugin configuration.
 * Use this class for rendering the admin panel of the plugin.
 *
 * Class AdminPanel
 */
class AdminPanel
{
    /**
     * @var PhastUser
     */
    private $user;
    /**
     * @var URL
     */
    private $ajaxEndPoint;
    /**
     * @var AdminPanelData
     */
    private $data;
    /**
     * @var TranslationsManager
     */
    private $translations;
    private $isDev = 'prod';
    private $scripts = ['prod' => ['main.js'], 'dev' => ['http://localhost:25903/main.js']];
    /**
     * AdminPanel constructor.
     * @param PhastUser $user
     * @param URL $ajaxEndPoint
     * @param AdminPanelData $data
     * @param TranslationsManager $translations
     * @param bool $isDev
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Security\PhastUser $user, \Kibo\Phast\ValueObjects\URL $ajaxEndPoint, \Kibo\PhastPlugins\SDK\AdminPanel\AdminPanelData $data, \Kibo\PhastPlugins\SDK\AdminPanel\TranslationsManager $translations, $isDev)
    {
        $this->user = $user;
        $this->ajaxEndPoint = $ajaxEndPoint;
        $this->data = $data;
        $this->translations = $translations;
        $this->isDev = (bool) $isDev;
    }
    /**
     * Returns the HTML needed to display the admin panel
     *
     * @return string
     */
    public function render()
    {
        if (!$this->user->mayModifySettings()) {
            return '';
        }
        $id = 'phast-plugins-sdk-admin-panel';
        $template = $this->getResourcesString();
        $template .= sprintf('
                <div id="%1$s"></div>
                <script>
                try {
                    window.PHAST_PLUGINS_SDK_ADMIN_PANEL.apply(window, %2$s)
                } catch (e) {
                    document.getElementById("%1$s").innerText = "Error: " + e.message
                    throw e
                }
                </script>
            ', $id, json_encode([$id, $this->ajaxEndPoint->toString(), $this->data->get(), $this->translations->getAll()]));
        return $template;
    }
    private function getResourcesString()
    {
        return $this->isDev ? $this->getDevResourcesString() : $this->getProdResources();
    }
    private function getProdResources()
    {
        $resources = '';
        $base = __DIR__ . '/static/';
        foreach ($this->scripts['prod'] as $script) {
            $resources .= '<script>' . file_get_contents($base . $script) . '</script>';
        }
        return $resources;
    }
    private function getDevResourcesString()
    {
        $resources = '';
        foreach ($this->scripts['dev'] as $src) {
            $resources .= "<script src=\"{$src}\"></script>";
        }
        return $resources;
    }
}
namespace Kibo\PhastPlugins\SDK\AdminPanel;

/**
 * Represents the data that needs to be send
 * to the plugin's admin panel
 *
 * Class AdminPanelData
 */
class AdminPanelData
{
    /**
     * @var PluginConfiguration
     */
    private $pluginConfig;
    /**
     * @var ServiceConfigurationGenerator
     */
    private $serviceConfigGenerator;
    /**
     * @var PhastConfiguration
     */
    private $phastConfig;
    /**
     * @var CacheRootManager
     */
    private $cacheRootManager;
    /**
     * @var PluginHost
     */
    private $host;
    public function __construct(\Kibo\PhastPlugins\SDK\Configuration\PluginConfiguration $pluginConfig, \Kibo\PhastPlugins\SDK\Configuration\ServiceConfigurationGenerator $serviceConfigGenerator, \Kibo\PhastPlugins\SDK\Configuration\PhastConfiguration $phastConfig, \Kibo\PhastPlugins\SDK\Caching\CacheRootManager $cacheRootManager, \Kibo\PhastPlugins\SDK\PluginHost $host)
    {
        $this->pluginConfig = $pluginConfig;
        $this->serviceConfigGenerator = $serviceConfigGenerator;
        $this->phastConfig = $phastConfig;
        $this->cacheRootManager = $cacheRootManager;
        $this->host = $host;
    }
    public function get()
    {
        $siteUrl = $this->host->getHostURLs()->getSiteURL();
        $urlWithPhast = $this->addQueryParam($siteUrl, 'phast', 'phast');
        $urlWithoutPhast = $this->addQueryParam($siteUrl, 'phast', '-phast');
        $pageSpeedToolUrl = 'https://developers.google.com/speed/pagespeed/insights/?url=';
        $errors = [];
        if (!$this->cacheRootManager->hasCacheRoot()) {
            $errors[] = ['type' => 'no-cache-root', 'params' => $this->cacheRootManager->getCacheRootCandidates()];
        }
        if (!$this->serviceConfigGenerator->generateIfNotExists($this->pluginConfig)) {
            $errors[] = ['type' => 'no-service-config', 'params' => $this->cacheRootManager->getCacheRootCandidates()];
        }
        $warnings = [];
        $api_client_warning = [];
        $phast_config = $this->phastConfig->get();
        $diagnostics = new \Kibo\Phast\Diagnostics\SystemDiagnostics();
        foreach ($diagnostics->run($phast_config) as $status) {
            if ($status->isAvailable()) {
                continue;
            }
            $package = $status->getPackage();
            $type = $package->getType();
            if ($type == 'Cache') {
                $errors[] = ['type' => 'cache', 'params' => [$status->getReason()]];
            } elseif ($type == 'ImageFilter') {
                $name = substr($package->getNamespace(), strrpos($package->getNamespace(), '\\') + 1);
                if ($name === 'ImageAPIClient') {
                    $api_client_warning[] = 'Image optimization API error: ' . $status->getReason();
                } else {
                    $warnings[] = $status->getReason();
                }
            }
        }
        $phastpress_config = $this->pluginConfig->get();
        if ($phastpress_config['img-optimization-api']) {
            $warnings = $api_client_warning;
        }
        $nonce = $this->host->getNonce();
        return ['config' => $phastpress_config, 'settingsStrings' => ['urlWithPhast' => $pageSpeedToolUrl . rawurlencode($urlWithPhast), 'urlWithoutPhast' => $pageSpeedToolUrl . rawurlencode($urlWithoutPhast), 'maxImageWidth' => 1920 * 2, 'maxImageHeight' => 1080 * 2], 'errors' => $errors, 'warnings' => $warnings, 'nonce' => $nonce->getValue(), 'nonceName' => $nonce->getFieldName(), 'pluginName' => $this->host->getPluginName(), 'pluginVersion' => $this->host->getPluginHostVersion()];
    }
    private function addQueryParam(\Kibo\Phast\ValueObjects\URL $url, $key, $value)
    {
        // TODO: Move this functionality to URL class
        $urlStr = (string) $url;
        $glue = strpos($urlStr, '?') === false ? '?' : '&';
        return $urlStr . $glue . $key . '=' . $value;
    }
}
namespace Kibo\PhastPlugins\SDK\AdminPanel;

/**
 * Represents an installation notice
 * displayed everywhere in the host system's admin panel
 * upon plugin activation.
 *
 * Class InstallNotice
 */
class InstallNotice
{
    /**
     * @var PluginConfiguration
     */
    private $config;
    /**
     * @var InstallNoticeRenderer
     */
    private $renderer;
    /**
     * @var TranslationsManager
     */
    private $translations;
    /**
     * @var URL
     */
    private $settingsUrl;
    /**
     * @var URL
     */
    private $ajaxEntryPoint;
    /**
     * InstallNotice constructor.
     * @param PluginConfiguration $config
     * @param InstallNoticeRenderer $renderer
     * @param TranslationsManager $translations
     * @param URL $settingsUrl
     * @param URL $ajaxEndPoint
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Configuration\PluginConfiguration $config, \Kibo\PhastPlugins\SDK\AdminPanel\InstallNoticeRenderer $renderer, \Kibo\PhastPlugins\SDK\AdminPanel\TranslationsManager $translations, \Kibo\Phast\ValueObjects\URL $settingsUrl, \Kibo\Phast\ValueObjects\URL $ajaxEndPoint)
    {
        $this->config = $config;
        $this->renderer = $renderer;
        $this->translations = $translations;
        $this->settingsUrl = $settingsUrl;
        $this->ajaxEntryPoint = $ajaxEndPoint;
    }
    /**
     * @return string The HTML to render the notice
     */
    public function render()
    {
        $display_message = $this->config->shouldShowActivationNotification();
        if (!$display_message) {
            return '';
        }
        $config = $this->config->get();
        if ($config['enabled'] && $config['admin-only']) {
            $status = 'Backend.status.admin';
        } elseif ($config['enabled']) {
            $status = 'Backend.status.on';
        } else {
            $status = 'Backend.status.off';
        }
        $message = $this->translations->get('Backend.install-notice', ['pluginState' => $this->translations->get($status), 'settingsUrl' => (string) $this->settingsUrl]);
        $onCloseFunction = "\n            function () {\n              var data = new FormData();\n              data.append('phast-plugins-action', 'dismiss-notice')\n              var xhr = new XMLHttpRequest();\n              xhr.open('POST', '{$this->ajaxEntryPoint}')\n              xhr.send(data)\n            }\n        ";
        return $this->renderer->render($message, $onCloseFunction);
    }
}
namespace Kibo\PhastPlugins\SDK\AdminPanel;

interface InstallNoticeRenderer
{
    /**
     * Renders a system notice.
     *
     * @param string $notice The message to show in the notice
     * @param string $onCloseJSFunction JavaScript function to call on the client when
     *     an event that closes the notice occurs.
     * @return string HTML for the notice
     * @see InstallNotice
     */
    public function render($notice, $onCloseJSFunction);
}
namespace Kibo\PhastPlugins\SDK\AdminPanel;

/**
 * Represents a nonce form field used XSS defense
 *
 * Class Nonce
 */
class Nonce implements \JsonSerializable
{
    /**
     * @var string
     */
    private $fieldName;
    /**
     * @var string
     */
    private $value;
    private function __construct()
    {
    }
    /**
     * @param string $fieldName The name of the field in the form
     * @param string $value The value of the field
     * @return Nonce
     */
    public static function make($fieldName, $value)
    {
        $instance = new self();
        $instance->fieldName = $fieldName;
        $instance->value = $value;
        return $instance;
    }
    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }
    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
    public function jsonSerialize() : array
    {
        return ['fieldName' => $this->fieldName, 'value' => $this->value];
    }
}
namespace Kibo\PhastPlugins\SDK\AdminPanel;

class TranslationsManager
{
    const DEFAULT_LOCALE = 'en';
    /**
     * @var string
     */
    private $locale;
    /**
     * @var string
     */
    private $pluginName;
    /**
     * @var string
     */
    private $languagesDir;
    /**
     * @var array
     */
    private $modules = [];
    public function __construct($locale, $pluginName)
    {
        $data = \Kibo\PhastPlugins\SDK\Generated\Translations::DATA;
        if (isset($data[$this->locale])) {
            $this->locale = $locale;
        } else {
            $this->locale = self::DEFAULT_LOCALE;
        }
        $this->pluginName = $pluginName;
    }
    public function getAll()
    {
        return array_merge(['plugin-name' => $this->pluginName], \Kibo\PhastPlugins\SDK\Generated\Translations::DATA[$this->locale]);
    }
    public function get($key, $interpolationArguments = [])
    {
        $keyParts = explode('.', $key);
        $transArr = \Kibo\PhastPlugins\SDK\Generated\Translations::DATA[$this->locale];
        while (count($keyParts) > 0) {
            $part = array_shift($keyParts);
            if (!isset($transArr[$part])) {
                return $key;
            }
            $transArr = $transArr[$part];
        }
        if (is_string($transArr)) {
            return $this->interpolate($transArr, $interpolationArguments);
        }
        return $key;
    }
    private function interpolate($string, $arguments)
    {
        $keys = array_map(function ($str) {
            return '{' . $str . '}';
        }, array_keys($arguments));
        $params = array_combine($keys, array_values($arguments));
        $params['@:plugin-name'] = $this->pluginName;
        return strtr($string, $params);
    }
}
namespace Kibo\PhastPlugins\SDK;

class Autoloader
{
    private static $instance;
    private $psr4 = [];
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
            self::$instance->install();
        }
        return self::$instance;
    }
    public function install()
    {
        spl_autoload_register(function ($class) {
            $this->autoload($class);
        });
    }
    public function addPSR4($namespace, $dir)
    {
        $this->psr4[] = [$namespace, $dir];
        return $this;
    }
    private function autoload($class)
    {
        foreach ($this->psr4 as $psr4) {
            list($namespace, $dir) = $psr4;
            if (strcasecmp($namespace . '\\', substr($class, 0, strlen($namespace) + 1))) {
                continue;
            }
            $relativeName = substr($class, strlen($namespace) + 1);
            $relativePath = str_replace('\\', '/', $relativeName) . '.php';
            $fullPath = $dir . '/' . $relativePath;
            if (file_exists($fullPath)) {
                include $fullPath;
                return;
            }
        }
    }
}
namespace Kibo\PhastPlugins\SDK\Caching;

interface CacheRootCandidatesProvider
{
    /**
     * Return a list of folders that will potentially be used
     * for storing cache and service configuration files.
     * The directories will be checked for write access
     * in the order they were provided. The first one writable
     * will be used.
     *
     * @return string[]
     */
    public function getCacheRootCandidates();
}
namespace Kibo\PhastPlugins\SDK\Caching;

class CacheRootManager
{
    /**
     * @var CacheRootCandidatesProvider
     */
    private $rootsProvider;
    /**
     * CacheRootManager constructor.
     * @param CacheRootCandidatesProvider $rootsProvider
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Caching\CacheRootCandidatesProvider $rootsProvider)
    {
        $this->rootsProvider = $rootsProvider;
    }
    public function getCacheRootCandidates()
    {
        return $this->rootsProvider->getCacheRootCandidates();
    }
    public function getCacheRoot()
    {
        $key = $this->getKey();
        $candidates = $this->getCacheRootCandidates();
        if ($result = $this->findExistingCacheRoot($key, $candidates)) {
            return $result;
        }
        if ($this->createNewCacheRoot($key, $candidates)) {
            return $this->findExistingCacheRoot($key, $candidates);
        }
        return false;
    }
    public function hasCacheRoot()
    {
        return (bool) $this->getCacheRoot();
    }
    public function getAllCacheRoots()
    {
        return $this->findAllExistingCacheRoots($this->getKey(), $this->getCacheRootCandidates());
    }
    private function getKey()
    {
        return md5(@$_SERVER['DOCUMENT_ROOT']) . '.' . (new \Kibo\Phast\Common\System())->getUserId();
    }
    private function findExistingCacheRoot($key, $candidates)
    {
        foreach ($this->findAllExistingCacheRoots($key, $candidates) as $checkDir) {
            if (!is_writable($checkDir)) {
                continue;
            }
            if (function_exists('posix_geteuid') && fileowner($checkDir) !== posix_geteuid()) {
                continue;
            }
            $this->createIndexFile($checkDir);
            return $checkDir;
        }
        return false;
    }
    private function findAllExistingCacheRoots($key, $candidates)
    {
        foreach ($this->getCacheRootCandidates() as $dir) {
            $checkDirs = ["{$dir}/{$key}", "{$dir}/phastpress.{$key}", "{$dir}/phast.{$key}"];
            foreach ($checkDirs as $checkDir) {
                if (!is_dir($checkDir)) {
                    continue;
                }
                (yield $checkDir);
            }
        }
    }
    private function createNewCacheRoot($key, $candidates)
    {
        foreach ($this->getCacheRootCandidates() as $dir) {
            if (@mkdir("{$dir}/phast.{$key}", 0777, true)) {
                return true;
            }
        }
        return false;
    }
    private function createIndexFile($dir)
    {
        $path = "{$dir}/index.html";
        if (!@file_exists($path)) {
            @touch($path);
        }
    }
}
namespace Kibo\PhastPlugins\SDK\Common;

/**
 * Contains common implementations for methods
 * of the PluginHost interface
 *
 * @see PluginHost
 * Trait PluginHostTrait
 */
trait PluginHostTrait
{
    public function getPluginName()
    {
        return 'Phast';
    }
    public function isDev()
    {
        return $this->getPluginHostVersion() === '$VER' . 'SION$';
    }
    public function onPhastConfigurationLoad(array $config)
    {
        return $config;
    }
    public function getLocale()
    {
        return 'en';
    }
    public function getInstallNoticeRenderer()
    {
        return new \Kibo\PhastPlugins\SDK\AdminPanel\DefaultInstallNoticeRenderer();
    }
}
namespace Kibo\PhastPlugins\SDK\Common;

trait PreviewCookieTrait
{
    public function seesPreviewMode()
    {
        return isset($_COOKIE['PHAST_PREVIEW']) && (bool) $_COOKIE['PHAST_PREVIEW'];
    }
}
namespace Kibo\PhastPlugins\SDK\Common;

trait ServiceHostTrait
{
    public function onServiceConfigurationLoad(array $config)
    {
        return $config;
    }
}
namespace Kibo\PhastPlugins\SDK\Configuration;

/**
 * Represents the javascript used
 * for auto-configuration done
 * immediately after activation of the plugin.
 *
 * Class AutoConfiguration
 */
class AutoConfiguration
{
    /**
     * @var PluginConfiguration
     */
    private $pluginConfig;
    /**
     * @var PhastConfiguration
     */
    private $phastConfig;
    /**
     * @var URL
     */
    private $servicesUrl;
    /**
     * @var URL
     */
    private $testImageUrl;
    /**
     * @var Nonce
     */
    private $nonce;
    /**
     * @var URL
     */
    private $ajaxEndPoint;
    /**
     * AutoConfiguration constructor.
     * @param PluginConfiguration $pluginConfig
     * @param PhastConfiguration $phastConfig
     * @param URL $servicesUrl
     * @param URL $testImageUrl
     * @param Nonce $nonce
     * @param URL $ajaxEndPoint
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Configuration\PluginConfiguration $pluginConfig, \Kibo\PhastPlugins\SDK\Configuration\PhastConfiguration $phastConfig, \Kibo\Phast\ValueObjects\URL $servicesUrl, \Kibo\Phast\ValueObjects\URL $testImageUrl, \Kibo\PhastPlugins\SDK\AdminPanel\Nonce $nonce, \Kibo\Phast\ValueObjects\URL $ajaxEndPoint)
    {
        $this->pluginConfig = $pluginConfig;
        $this->phastConfig = $phastConfig;
        $this->servicesUrl = $servicesUrl;
        $this->testImageUrl = $testImageUrl;
        $this->nonce = $nonce;
        $this->ajaxEndPoint = $ajaxEndPoint;
    }
    /**
     * Returns the script that needs to rendered
     * in order for the script to get executed.
     *
     * @return string
     */
    public function renderScript()
    {
        if (!$this->pluginConfig->shouldAutoConfigure()) {
            return '';
        }
        $config = \Kibo\Phast\Environment\Configuration::fromDefaults()->withUserConfiguration(new \Kibo\Phast\Environment\Configuration($this->phastConfig->get()))->getRuntimeConfig()->toArray();
        $signature = (new \Kibo\Phast\Security\ServiceSignatureFactory())->make($config);
        $service_image_url = (new \Kibo\Phast\Services\ServiceRequest())->withUrl($this->servicesUrl)->withParams(['service' => 'images', 'src' => (string) $this->testImageUrl])->sign($signature)->serialize(\Kibo\Phast\Services\ServiceRequest::FORMAT_PATH);
        $nonce = json_encode($this->nonce);
        return '<script>(function (imageUrl, nonce, ajaxEndPoint) {' . "var logPrefix=\"[Phast autoconfiguration]\";var imageRequest=new XMLHttpRequest;imageRequest.open(\"GET\",imageUrl);imageRequest.onload=function(){var a=imageRequest.status>=200&&imageRequest.status<300;console.log(logPrefix,\"Got status\",imageRequest.status,\"which is\",a?\"successful\":\"unsuccessful\");configureRequestsFormat(a)};imageRequest.onerror=function(){console.log(logPrefix,\"Got error\");configureRequestsFormat(false)};imageRequest.ontimeout=function(){console.log(logPrefix,\"Request timed out\");configureRequestsFormat(false)};console.log(logPrefix,\"Requesting testing image through Phast service\");console.log(logPrefix,\"URL:\",imageUrl);imageRequest.send();function configureRequestsFormat(b){console.log(logPrefix,\"Configuring Phast with path info\",b?\"on\":\"off\");var c=new FormData;c.append(\"phast-plugins-action\",\"save-settings\");c.append(\"phastpress-pathinfo-query-format\",b?\"on\":\"off\");c.append(nonce.fieldName,nonce.value);var d=new XMLHttpRequest;d.open(\"POST\",ajaxEndPoint);d.responseType=\"json\";d.addEventListener(\"load\",function(){var e=d.response;if(typeof e===\"object\"&&e[\"phast-success\"]===true){console.log(logPrefix,\"Successfully autoconfigured! Dispatching event!\");var f=new CustomEvent(\"phast-auto-config\",{detail:e[\"phast-data\"]});window.dispatchEvent(f)}});d.send(c)}\n" . "})('{$service_image_url}', {$nonce}, '{$this->ajaxEndPoint}')</script>";
    }
}
namespace Kibo\PhastPlugins\SDK\Configuration;

class EnvironmentIdentifier
{
    private $value;
    public function __construct()
    {
        $this->value = sprintf('%s://%s%s', empty($_SERVER['HTTPS']) ? 'http' : 'https', empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST'], empty($_SERVER['SERVER_PORT']) ? '' : ":{$_SERVER['SERVER_PORT']}");
    }
    public function getValue()
    {
        return $this->value;
    }
}
namespace Kibo\PhastPlugins\SDK\Configuration;

/**
 * A key-value store for use within the plugin's admin panel
 *
 * Interface KeyValueStore
 */
interface KeyValueStore
{
    /**
     * @param string $key
     * @return string|null The previously stored value or
     *          null if there was no value stored for this key
     */
    public function get($key);
    /**
     * @param string $key
     * @param string $value
     * @return void
     */
    public function set($key, $value);
}
namespace Kibo\PhastPlugins\SDK\Configuration;

/**
 * Represents the configuration
 * that needs to be passed to
 * \Kibo\Phast\PhastDocumentFilters::deploy()
 * and
 * \Kibo\Phast\PhastDocumentFilters::apply()
 *
 * @see \Kibo\Phast\PhastDocumentFilters
 * Class PhastConfiguration
 */
class PhastConfiguration
{
    const SETTINGS_2_FILTERS = ['img-optimization-tags' => [\Kibo\Phast\Filters\HTML\ImagesOptimizationService\Tags\Filter::class], 'img-optimization-css' => [\Kibo\Phast\Filters\HTML\ImagesOptimizationService\CSS\Filter::class, \Kibo\Phast\Filters\CSS\ImageURLRewriter\Filter::class], 'img-lazy' => [\Kibo\Phast\Filters\HTML\LazyImageLoading\Filter::class], 'css-optimization' => [\Kibo\Phast\Filters\HTML\CSSInlining\Filter::class], 'scripts-defer' => [\Kibo\Phast\Filters\HTML\ScriptsDeferring\Filter::class], 'scripts-proxy' => [\Kibo\Phast\Filters\HTML\ScriptsProxyService\Filter::class], 'iframe-defer' => [\Kibo\Phast\Filters\HTML\DelayedIFrameLoading\Filter::class], 'minify-html' => [\Kibo\Phast\Filters\HTML\Minify\Filter::class], 'minify-inline-scripts' => [\Kibo\Phast\Filters\HTML\MinifyScripts\Filter::class]];
    /**
     * @var ServiceConfigurationGenerator
     */
    private $serviceConfigGenerator;
    /**
     * @var ServiceConfiguration
     */
    private $serviceConfig;
    /**
     * @var PluginConfiguration
     */
    private $pluginConfig;
    /**
     * @var callable
     */
    private $onLoadCb;
    /**
     * PhastConfiguration constructor.
     * @param ServiceConfigurationGenerator $serviceConfigGenerator
     * @param ServiceConfiguration $serviceConfig
     * @param PluginConfiguration $pluginConfig
     * @param callable $onLoadCb
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Configuration\ServiceConfigurationGenerator $serviceConfigGenerator, \Kibo\PhastPlugins\SDK\Configuration\ServiceConfiguration $serviceConfig, \Kibo\PhastPlugins\SDK\Configuration\PluginConfiguration $pluginConfig, callable $onLoadCb)
    {
        $this->serviceConfigGenerator = $serviceConfigGenerator;
        $this->serviceConfig = $serviceConfig;
        $this->pluginConfig = $pluginConfig;
        $this->onLoadCb = $onLoadCb;
    }
    /**
     * Returns the configuration to use on full html documents as an array
     *
     * @return array|bool|mixed
     */
    public function getForDocuments()
    {
        list($phastConfig, $pluginConfig) = $this->getPhastAndPluginConfigs();
        foreach (array_keys(self::SETTINGS_2_FILTERS) as $setting) {
            $this->setSettingInPhastConfig($setting, $pluginConfig, $phastConfig);
        }
        return call_user_func($this->onLoadCb, $phastConfig);
    }
    public function getForHTMLSnippets()
    {
        list($phastConfig, $pluginConfig) = $this->getPhastAndPluginConfigs();
        $defaultConfig = \Kibo\Phast\Environment\Configuration::fromDefaults()->toArray();
        $allFilters = array_keys($defaultConfig['documents']['filters']);
        foreach ($allFilters as $filter) {
            $phastConfig['documents']['filters'][$filter]['enabled'] = false;
        }
        $this->setSettingInPhastConfig('img-optimization-tags', $pluginConfig, $phastConfig);
        $this->setSettingInPhastConfig('img-optimization-css', $pluginConfig, $phastConfig);
        $this->setSettingInPhastConfig('img-lazy', $pluginConfig, $phastConfig);
        $phastConfig['optimizeHTMLDocumentsOnly'] = false;
        $phastConfig['optimizeJSONResponses'] = true;
        $phastConfig['outputServerSideStats'] = false;
        return call_user_func($this->onLoadCb, $phastConfig);
    }
    private function getPhastAndPluginConfigs()
    {
        // TODO: Optimize so we do not read from the service config file a bunch of times
        $this->serviceConfigGenerator->generateIfNotExists($this->pluginConfig);
        $pluginConfig = $this->pluginConfig->get();
        $phastConfig = $this->serviceConfig->get();
        $phastConfig['documents']['filters'] = [];
        $phastConfig['switches']['phast'] = $phastConfig && $this->pluginConfig->shouldDeployFilters();
        return [$phastConfig, $pluginConfig];
    }
    private function setSettingInPhastConfig($settingName, $pluginConfig, &$phastConfig)
    {
        foreach (self::SETTINGS_2_FILTERS[$settingName] as $filterClass) {
            if (!class_exists($filterClass)) {
                throw new \LogicException("No such filter: {$filterClass}");
            }
            if (strpos($filterClass, \Kibo\Phast\Filters\HTML::class . '\\') === 0) {
                $object = 'documents';
            } elseif (strpos($filterClass, \Kibo\Phast\Filters\CSS::class . '\\') === 0) {
                $object = 'styles';
            } else {
                throw new \LogicException("Invalid filter namespace: {$filterClass}");
            }
            $phastConfig[$object]['filters'][$filterClass] = ['enabled' => $settingName];
            $phastConfig['switches'][$settingName] = $pluginConfig[$settingName];
        }
    }
    /**
     * Returns the configuration as an array
     *
     * @return array|bool|mixed
     * @deprecated  use PhastConfiguration::getForDocuments()
     */
    public function get()
    {
        return $this->getForDocuments();
    }
}
namespace Kibo\PhastPlugins\SDK\Configuration;

/**
 * Represents the configuration of the plugin
 *
 * Class PluginConfiguration
 */
class PluginConfiguration
{
    const KEY_SETTINGS = 'settings';
    const KEY_ACTIVATION_NOTIFICATION = 'activation-notification';
    /**
     * @var PluginConfigurationRepository
     */
    private $repo;
    /**
     * @var ServiceConfigurationGenerator
     */
    private $serviceConfigGenerator;
    /**
     * @var CacheRootManager
     */
    private $cacheRootManager;
    /**
     * @var PhastUser
     */
    private $user;
    /**
     * @var NonceChecker
     */
    private $nonceChecker;
    /**
     * PluginConfiguration constructor.
     * @param PluginConfigurationRepository $repo
     * @param ServiceConfigurationGenerator $serviceConfigGenerator
     * @param CacheRootManager $cacheRootManager
     * @param PhastUser $user
     * @param NonceChecker $nonceChecker
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Configuration\PluginConfigurationRepository $repo, \Kibo\PhastPlugins\SDK\Configuration\ServiceConfigurationGenerator $serviceConfigGenerator, \Kibo\PhastPlugins\SDK\Caching\CacheRootManager $cacheRootManager, \Kibo\PhastPlugins\SDK\Security\PhastUser $user, \Kibo\PhastPlugins\SDK\Security\NonceChecker $nonceChecker)
    {
        $this->repo = $repo;
        $this->serviceConfigGenerator = $serviceConfigGenerator;
        $this->cacheRootManager = $cacheRootManager;
        $this->user = $user;
        $this->nonceChecker = $nonceChecker;
    }
    public function get()
    {
        $userSettings = $this->repo->get(self::KEY_SETTINGS, []);
        return array_merge($this->getDefaultAdminPanelSettings(), $userSettings);
    }
    public function save(array $newConfig)
    {
        if (!$this->nonceChecker->checkNonce($newConfig)) {
            return;
        }
        $keys = array_keys($this->getDefaultAdminPanelSettings());
        $settings = [];
        foreach ($keys as $key) {
            $newConfigKey = "phastpress-{$key}";
            if (!isset($newConfig[$newConfigKey])) {
                continue;
            }
            if ($newConfig[$newConfigKey] == 'on') {
                $settings[$key] = true;
            } elseif ($newConfig[$newConfigKey] == 'off') {
                $settings[$key] = false;
            }
        }
        $this->update($settings);
    }
    public function update(array $settings)
    {
        $this->repo->set(self::KEY_SETTINGS, array_merge($this->get(), $settings));
        $this->serviceConfigGenerator->generate($this);
    }
    public function shouldShowActivationNotification()
    {
        return $this->repo->get(self::KEY_ACTIVATION_NOTIFICATION, true);
    }
    public function hideActivationNotification()
    {
        $this->repo->set(self::KEY_ACTIVATION_NOTIFICATION, false);
    }
    public function shouldAutoConfigure()
    {
        return !$this->repo->get(self::KEY_SETTINGS);
    }
    public function shouldDeployFilters()
    {
        $plugin_config = $this->get();
        if (!$plugin_config['enabled']) {
            return false;
        }
        if (!$plugin_config['admin-only']) {
            return true;
        }
        return $this->user->seesPreviewMode();
    }
    public function shouldDisplayFooter()
    {
        return $this->get()['footer-link'] && $this->shouldDeployFilters();
    }
    private function getDefaultAdminPanelSettings()
    {
        return ['enabled' => true, 'admin-only' => false, 'pathinfo-query-format' => false, 'footer-link' => false, 'compress-service-response' => true, 'img-optimization-tags' => true, 'img-optimization-css' => true, 'img-optimization-api' => true, 'img-lazy' => true, 'css-optimization' => true, 'scripts-rearrangement' => false, 'scripts-defer' => true, 'scripts-proxy' => true, 'iframe-defer' => true, 'minify-html' => true, 'minify-inline-scripts' => true];
    }
}
namespace Kibo\PhastPlugins\SDK\Configuration;

class PluginConfigurationRepository
{
    /**
     * @var KeyValueStore
     */
    private $store;
    /**
     * JSONKeyValueStore constructor.
     * @param KeyValueStore $store
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Configuration\KeyValueStore $store)
    {
        $this->store = $store;
    }
    /**
     * @param mixed $key
     * @param null $default
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        $value = $this->store->get($key);
        if (!is_string($value) || $value === 'null') {
            return $default;
        }
        $deserialised = @json_decode($value, true);
        if (is_null($deserialised)) {
            return $default;
        }
        return $deserialised;
    }
    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $this->store->set($key, json_encode($value));
    }
}
namespace Kibo\PhastPlugins\SDK\Configuration;

/**
 * Represents the configuration
 * that needs to be passed to be returned
 * by the callback passed to
 * \Kibo\Phast\PhastServices::serve()
 *
 * @see \Kibo\Phast\PhastServices::serve()
 * Class ServiceConfiguration
 */
class ServiceConfiguration
{
    /**
     * @var ServiceConfigurationRepository
     */
    private $repository;
    /**
     * @var EnvironmentIdentifier
     */
    private $environmentIdentifier;
    /**
     * @var CacheRootManager
     */
    private $cacheRootManager;
    /**
     * @var callable
     */
    private $onLoadCb;
    /**
     * ServiceConfiguration constructor.
     * @param ServiceConfigurationRepository $repository
     * @param CacheRootManager $cacheRootManager
     * @param callable $onLoadCb
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Configuration\ServiceConfigurationRepository $repository, \Kibo\PhastPlugins\SDK\Configuration\EnvironmentIdentifier $environmentIdentifier, \Kibo\PhastPlugins\SDK\Caching\CacheRootManager $cacheRootManager, callable $onLoadCb)
    {
        $this->repository = $repository;
        $this->environmentIdentifier = $environmentIdentifier;
        $this->cacheRootManager = $cacheRootManager;
        $this->onLoadCb = $onLoadCb;
    }
    /**
     * Returns the configuration as config
     *
     * @return array|bool|mixed
     */
    public function get()
    {
        $config = $this->repository->get();
        $envId = $this->environmentIdentifier->getValue();
        if (isset($config['alternativeServicesUrls'][$envId])) {
            $config['servicesUrl'] = $config['alternativeServicesUrls'][$envId];
        }
        if (!empty($config['cdnHost'])) {
            $config['retrieverMap'][$config['cdnHost']] = \Kibo\Phast\HTTP\Request::fromGlobals()->getDocumentRoot();
        }
        $config['cache'] = ['cacheRoot' => $this->cacheRootManager->getCacheRoot()];
        $apiFilterName = \Kibo\Phast\Filters\Image\ImageAPIClient\Filter::class;
        if (empty($config['images']['filters'][$apiFilterName]['enabled'])) {
            unset($config['images']['filters'][$apiFilterName]);
            return call_user_func($this->onLoadCb, $config);
        }
        $config['images']['filters'][$apiFilterName]['host-name'] = $_SERVER['HTTP_HOST'];
        $config['images']['filters'][$apiFilterName]['request-uri'] = $_SERVER['REQUEST_URI'];
        $config['images']['filters'][$apiFilterName]['api-url'] = 'https://optimize.phast.io/?service=images';
        return call_user_func($this->onLoadCb, $config);
    }
}
namespace Kibo\PhastPlugins\SDK\Configuration;

class ServiceConfigurationGenerator
{
    /**
     * @var ServiceConfigurationRepository
     */
    private $repository;
    /**
     * @var EnvironmentIdentifier
     */
    private $environmentIdentifier;
    /**
     * @var URL
     */
    private $servicesUrl;
    /**
     * @var URL
     */
    private $cdnServicesUrl;
    /**
     * @var string
     */
    private $pluginVersion;
    /**
     * @var string
     */
    private $cdnHost;
    /**
     * @var ?string
     */
    private $securityTokenRoot;
    public function __construct(\Kibo\PhastPlugins\SDK\Configuration\ServiceConfigurationRepository $repository, \Kibo\PhastPlugins\SDK\Configuration\EnvironmentIdentifier $environmentIdentifier, $pluginVersion, \Kibo\PhastPlugins\SDK\PluginHost $host)
    {
        $this->repository = $repository;
        $this->environmentIdentifier = $environmentIdentifier;
        $this->pluginVersion = $pluginVersion;
        $this->securityTokenRoot = $host->getSecurityTokenRoot();
        $hostUrls = $host->getHostUrls();
        $this->servicesUrl = $hostUrls->getServicesURL();
        $this->cdnServicesUrl = $hostUrls->getCDNURL($hostUrls->getServicesURL());
        $this->cdnHost = $hostUrls->getCDNURL($hostUrls->getSiteURL())->getHost();
    }
    public function generateIfNotExists(\Kibo\PhastPlugins\SDK\Configuration\PluginConfiguration $pluginConfig)
    {
        if (!$this->repository->has()) {
            return $this->generate($pluginConfig);
        }
        $config = $this->repository->get();
        $envId = $this->environmentIdentifier->getValue();
        if (empty($config['plugin_version']) || $config['plugin_version'] != $this->pluginVersion || empty($config['alternativeServicesUrls'][$envId]) || $config['alternativeServicesUrls'][$envId] != $this->getServicesURLString($pluginConfig)) {
            return $this->generate($pluginConfig);
        }
        return true;
    }
    public function generate(\Kibo\PhastPlugins\SDK\Configuration\PluginConfiguration $pluginConfig)
    {
        $previousConfig = $this->repository->get();
        $plugin_config = $pluginConfig->get();
        $plugin_version = $this->pluginVersion;
        $config = ['plugin_version' => $plugin_version, 'servicesUrl' => $this->getServicesURLString($pluginConfig), 'securityToken' => $this->getSecurityToken($previousConfig), 'images' => ['filters' => [\Kibo\Phast\Filters\Image\ImageAPIClient\Filter::class => ['enabled' => $plugin_config['img-optimization-api'], 'plugin-version' => $plugin_version]]], 'styles' => ['filters' => [\Kibo\Phast\Filters\CSS\ImageURLRewriter\Filter::class => ['enabled' => $plugin_config['img-optimization-css']]]], 'serviceRequestFormat' => $plugin_config['pathinfo-query-format'] ? \Kibo\Phast\Services\ServiceRequest::FORMAT_PATH : \Kibo\Phast\Services\ServiceRequest::FORMAT_QUERY, 'compressServiceResponse' => isset($plugin_config['compress-service-response']) ? !!$plugin_config['compress-service-response'] : true, 'cdnHost' => $this->cdnHost];
        if (isset($previousConfig['alternativeServicesUrls'])) {
            $config['alternativeServicesUrls'] = $previousConfig['alternativeServicesUrls'];
        } else {
            $config['alternativeServicesUrls'] = [];
        }
        $id = $this->environmentIdentifier->getValue();
        unset($config['alternativeServicesUrls'][$id]);
        $config['alternativeServicesUrls'][$id] = $this->getServicesURLString($pluginConfig);
        $config['alternativeServicesUrls'] = array_slice($config['alternativeServicesUrls'], -1000);
        return $this->repository->store($config);
    }
    private function getSecurityToken($previousConfig)
    {
        if (!empty($previousConfig['securityToken'])) {
            return $previousConfig['securityToken'];
        }
        if (!empty($this->securityTokenRoot)) {
            return base64_encode(hash_hmac('sha224', 'Phast security token', $this->securityTokenRoot, true));
        }
        return \Kibo\Phast\Security\ServiceSignature::generateToken();
    }
    private function getServicesURLString(\Kibo\PhastPlugins\SDK\Configuration\PluginConfiguration $pluginConfig)
    {
        $plugin_config = $pluginConfig->get();
        if ($plugin_config['pathinfo-query-format']) {
            return (string) $this->cdnServicesUrl;
        }
        return (string) $this->servicesUrl;
    }
}
namespace Kibo\PhastPlugins\SDK\Configuration;

/**
 * Manages serialization of the phast service configuration.
 * Must be as fast as possible, having as little
 * dependency on the host system as possible (ideally - none).
 *
 * Interface ServiceConfigurationRepository
 */
interface ServiceConfigurationRepository
{
    /**
     * Store the given config
     *
     * @param array $config
     * @return bool TRUE on success, FALSE on failure
     */
    public function store(array $config);
    /**
     * Returns the previously stored config
     *
     * @return array|bool - The config on success or
     *      FALSE on failure or if no config has been stored
     */
    public function get();
    /**
     * Tells whether a config has been previously stored
     *
     * @return bool
     */
    public function has();
}
namespace Kibo\PhastPlugins\SDK\Generated;

class Translations
{
    const DATA = array('en' => array('AdminPanel' => array('errors' => array('no-cache-root' => '@:plugin-name can not write to any cache directory! Please, make one of the following directories writable: {params}', 'no-service-config' => '@:plugin-name failed to create a service configuration in any of the following directories: {params}', 'network-error' => 'Failed to connect to plugin server! Please, try again later! {params}', 'cache' => '{params}'), 'warnings' => array('disabled' => '@:plugin-name optimizations are off!', 'admin-only' => '@:plugin-name optimizations will be applied only for logged-in users with the "Administrator" privilege. This is for previewing purposes. Select the "On" setting for "@:plugin-name optimizations" below to activate for all users!
')), 'Backend' => array('install-notice' => 'Thank you for using <b>@:plugin-name</b>. Optimizations are <b>{pluginState}</b>. Go to <b><a href="{settingsUrl}">Settings</a></b> to configure <b>@:plugin-name</b>.
', 'status' => array('on' => 'on', 'off' => 'off', 'admin' => 'on for administrators')), 'Information' => array('additional' => 'Additional information'), 'Notification' => array('error' => 'error', 'warning' => 'warning', 'information' => 'information', 'success' => 'success'), 'OnOffSwitch' => array('on' => 'On', 'off' => 'Off'), 'SavingStatus' => array('saving' => 'Saving', 'saved' => 'Saved'), 'Settings' => array('common' => array('tip' => 'Tip:', 'on' => 'On:', 'off' => 'Off:'), 'sections' => array('plugin' => array('title' => 'Plugin', 'enabled' => array('name' => '@:plugin-name optimizations', 'description' => array('main' => 'Test your site {without} and {with}', 'without' => 'without @:plugin-name', 'with' => 'with @:plugin-name')), 'admin-only' => array('name' => 'Only optimize for administrators', 'description' => array('on' => 'Only privileged users will be served with optimized version', 'off' => 'All users will be served with optimized version', 'tip' => 'Use this to preview your site before launching the optimizations')), 'pathinfo' => array('name' => 'Remove query string from processed resources', 'description' => array('start' => 'Make sure that processed resources don\'t have query strings, for a higher score in GTmetrix.', 'on' => 'Use the path for requests for processed resources. This requires a server that supports "PATH_INFO".', 'off' => 'Use the GET parameters for requests for processed resources.')), 'footer-link' => array('name' => 'Let the world know about @:plugin-name', 'description' => 'Add a "Optimized by @:plugin-name" notice to the footer of your site and help spread the word.'), 'compress-service-response' => array('name' => 'Enable gzip compression on processed resources', 'description' => 'This compresses the optimized and bundled JavaScript and CSS generated by PhastPress. Disable this if your server already compresses PhastPress responses.')), 'images' => array('title' => 'Images', 'tags' => array('name' => 'Optimize images in tags', 'description' => 'Compress images with optimal settings. {newline} Resize images to fit {width}x{height} pixels or to the appropriate size for {imgTag} tags with {widthAttr} or {heightAttr}. {newline} Reload changed images while still leveraging browser caching.
'), 'css' => array('name' => 'Optimize images in CSS', 'description' => array(0 => 'Compress images in stylesheets with optional settings and resizes the to fit {width}x{height} pixels.', 1 => 'Reload changed images while still leveraging browser caching.')), 'api' => array('name' => 'Use the Phast Image Optimization API', 'description' => array(0 => 'Optimize your images on our servers free of charge.', 1 => 'This will give you the best possible results without installing any software and will reduce the load on your hosting.
')), 'lazy' => array('name' => 'Lazy load images', 'description' => array(0 => 'This adds the loading=lazy attribute to img tags so that images are only load once they are visible on the page.', 1 => 'This helps pass the "Defer offscreen images" audit in PageSpeed Insights.'))), 'html-filters' => array('title' => 'HTML, CSS & JS', 'css' => array('name' => 'Optimize CSS', 'description' => array(0 => 'Incline critical styles first and prevent unused styles from blocking the page load.', 1 => 'Minify stylesheets and leverage browser caching.', 2 => 'Inline Google Fonts CSS to speed up font loading.')), 'async-js' => array('name' => 'Load scripts asynchronously', 'description' => 'Allow the page to finish loading before all scripts have been executed.'), 'minify-js' => array('name' => 'Minify scripts and improve caching', 'description' => array(0 => 'Minify scripts and set long cache durations.', 1 => 'Reload changed scripts while still leveraging browser caching.')), 'iframe' => array('name' => 'Lazy load IFrames', 'description' => 'This adds the loading=lazy attribute to iframe tags so that IFrames are only loaded once they are visible on the page.'), 'minify-html' => array('name' => 'Minify HTML', 'description' => 'Remove unnecessary whitespace from the HTML code of the page.'), 'minify-inline-scripts' => array('name' => 'Minify inline scripts and JSON', 'description' => 'Remove unnecessary whitespace from inline scripts and JSON data.'))))));
}
namespace Kibo\PhastPlugins\SDK;

/**
 * Provides commonly needed URLs
 *
 * Interface HostURLs
 * @see URL
 */
interface HostURLs
{
    /**
     * The URL at which static resource (JS, CSS, IMG)
     * optimizations reside
     *
     * @return URL
     */
    public function getServicesURL();
    /**
     * The full URL of the root of the current site
     *
     * @return URL
     */
    public function getSiteURL();
    /**
     * The CDN equivalent of a specified URL
     *
     * @return URL
     */
    public function getCDNURL(\Kibo\Phast\ValueObjects\URL $url);
    /**
     * URL of the admin page at which
     * the plugin's settings are located
     *
     * @return URL
     */
    public function getSettingsURL();
    /**
     * URL for admin panel AJAX communication
     *
     * @return URL
     */
    public function getAJAXEndPoint();
    /**
     * A URL to a publicly available image.
     *
     * @return URL
     */
    public function getTestImageURL();
}
namespace Kibo\PhastPlugins\SDK\Security;

/**
 * Checks whether posted data to the server
 * contains the expected nonce.
 *
 * Interface NonceChecker
 */
interface NonceChecker
{
    /**
     * Performs the check
     *
     * @param array $data The data posted to the server
     * @return bool TRUE if all is well, FALSE otherwise
     */
    public function checkNonce(array $data);
}
namespace Kibo\PhastPlugins\SDK\Security;

/**
 * Represents the user currently viewing the website (either backend or frontend)
 *
 * Interface PhastUser
 */
interface PhastUser
{
    /**
     * Tells whether the user can access and manipulate
     * the plugin's settings
     *
     * @return bool
     */
    public function mayModifySettings();
    /**
     * Tells whether the user can access the website with Phast enabled in preview mode
     *
     * @return bool
     */
    public function seesPreviewMode();
}
namespace Kibo\PhastPlugins\SDK;

interface ServiceHost
{
    /**
     * @return CacheRootCandidatesProvider
     */
    public function getCacheRootCandidatesProvider();
    /**
     * Called right after the service configuration
     * has been loaded. Use it to modify the config
     * and take any other needed action before
     * the service is started.
     *
     * @param array $config - The configuration that has been loaded
     * @return array - The configuration to use for the services
     */
    public function onServiceConfigurationLoad(array $config);
}
namespace Kibo\PhastPlugins\SDK;

/**
 * Services container for the Phast Plugins Services SDK
 *
 * Class SDK
 */
class ServiceSDK
{
    /**
     * @var ServiceHost
     */
    protected $host;
    /**
     * @var EnvironmentIdentifier
     */
    private $environmentIdentifier;
    public function __construct(\Kibo\PhastPlugins\SDK\ServiceHost $host)
    {
        $this->host = $host;
        $this->environmentIdentifier = new \Kibo\PhastPlugins\SDK\Configuration\EnvironmentIdentifier();
    }
    public function getServiceAPI()
    {
        return new \Kibo\PhastPlugins\SDK\APIs\Service($this->getServiceConfiguration());
    }
    public function getServiceConfiguration()
    {
        return new \Kibo\PhastPlugins\SDK\Configuration\ServiceConfiguration($this->getPHPFilesServiceConfigurationRepository(), $this->getEnvironmentIdentifier(), $this->getCacheRootManager(), [$this->host, 'onServiceConfigurationLoad']);
    }
    public function getCacheRootManager()
    {
        return new \Kibo\PhastPlugins\SDK\Caching\CacheRootManager($this->host->getCacheRootCandidatesProvider());
    }
    /**
     * Returns a default implementation of the
     * ServiceConfigurationRepository interface
     *
     * @see ServiceConfigurationRepository
     * @return PHPFilesServiceConfigurationRepository
     */
    public function getPHPFilesServiceConfigurationRepository()
    {
        return new \Kibo\PhastPlugins\SDK\Configuration\PHPFilesServiceConfigurationRepository($this->getCacheRootManager());
    }
    public function getEnvironmentIdentifier()
    {
        return $this->environmentIdentifier;
    }
}
namespace Kibo\Phast\Common;

class JSMinifier extends \Kibo\Phast\JSMin\JSMin
{
    protected $removeLicenseHeaders;
    public function __construct($input, $removeLicenseHeaders = false)
    {
        parent::__construct($input);
        $this->removeLicenseHeaders = $removeLicenseHeaders;
    }
    protected function consumeMultipleLineComment()
    {
        parent::consumeMultipleLineComment();
        if ($this->removeLicenseHeaders) {
            $this->keptComment = preg_replace('~/\\*!.*?\\*/~s', '', $this->keptComment);
        }
    }
}
namespace Kibo\Phast\Environment\Exceptions;

class PackageHasNoDiagnosticsException extends \Kibo\Phast\Exceptions\LogicException
{
}
namespace Kibo\Phast\Environment\Exceptions;

class PackageHasNoFactoryException extends \Kibo\Phast\Exceptions\LogicException
{
}
namespace Kibo\Phast\Filters\CSS\CSSMinifier;

class Filter implements \Kibo\Phast\Services\ServiceFilter
{
    /**
     * @param Resource $resource
     * @param array $request
     * @return Resource
     */
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $content = $resource->getContent();
        // Normalize whitespace
        $content = preg_replace('~\\s+~', ' ', $content);
        // Remove whitespace before and after operators
        $chars = [',', '{', '}', ';'];
        foreach ($chars as $char) {
            $content = str_replace("{$char} ", $char, $content);
            $content = str_replace(" {$char}", $char, $content);
        }
        // Remove whitespace after colons
        $content = str_replace(': ', ':', $content);
        return $resource->withContent(trim($content));
    }
}
namespace Kibo\Phast\Filters\CSS\CSSURLRewriter;

class Filter implements \Kibo\Phast\Services\ServiceFilter
{
    /**
     * @param Resource $resource
     * @param array $request
     * @return Resource
     */
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $baseUrl = $resource->getUrl();
        $callback = function ($match) use($baseUrl) {
            if (preg_match('~^[a-z]+:|^#~i', $match[3])) {
                return $match[0];
            }
            return $match[1] . \Kibo\Phast\ValueObjects\URL::fromString($match[3])->withBase($baseUrl) . $match[4];
        };
        $cssContent = preg_replace_callback('~
                \\b
                ( url\\( \\s*+ ([\'"]?) )
                ([A-Za-z0-9_/.:?&=+%,#@-]+)
                ( \\2 \\s*+ \\) )
            ~x', $callback, $resource->getContent());
        $cssContent = preg_replace_callback('~
                ( @import \\s+ ([\'"]) )
                ([A-Za-z0-9_/.:?&=+%,#@-]+)
                ( \\2 )
            ~x', $callback, $cssContent);
        return $resource->withContent($cssContent);
    }
}
namespace Kibo\Phast\Filters\CSS\CommentsRemoval;

class Filter implements \Kibo\Phast\Services\ServiceFilter
{
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $content = preg_replace('~/\\*[^*]*\\*+([^/*][^*]*\\*+)*/~', '', $resource->getContent());
        return $resource->withContent($content);
    }
}
namespace Kibo\Phast\Filters\CSS\FontSwap;

class Filter implements \Kibo\Phast\Services\ServiceFilter
{
    const FONT_FACE_REGEXP = '/(@font-face\\s*\\{)([^}]*)/i';
    const ICON_FONT_FAMILIES = ['Font Awesome', 'GeneratePress', 'Dashicons', 'Ionicons'];
    private $fontDisplayBlockPattern;
    public function __construct()
    {
        $this->fontDisplayBlockPattern = $this->getFontDisplayBlockPattern();
    }
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $css = $resource->getContent();
        $filtered = preg_replace_callback(self::FONT_FACE_REGEXP, function ($match) {
            list($block, $start, $contents) = $match;
            $mode = preg_match($this->fontDisplayBlockPattern, $contents) ? 'block' : 'swap';
            return $start . 'font-display:' . $mode . ';' . $contents;
        }, $css);
        return $resource->withContent($filtered);
    }
    private function getFontDisplayBlockPattern()
    {
        $patterns = [];
        foreach (self::ICON_FONT_FAMILIES as $family) {
            $chars = str_split($family);
            $chars = array_map(function ($char) {
                return preg_quote($char, '~');
            }, $chars);
            $patterns[] = implode('\\s*', $chars);
        }
        return '~' . implode('|', $patterns) . '~i';
    }
}
namespace Kibo\Phast\Filters\HTML;

abstract class BaseHTMLStreamFilter implements \Kibo\Phast\Filters\HTML\HTMLStreamFilter
{
    /**
     * @var HTMLPageContext
     */
    protected $context;
    /**
     * @var \Traversable
     */
    protected $elements;
    /**
     * @param Tag $tag
     * @return Element[]|\Generator
     */
    protected abstract function handleTag(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag);
    public function transformElements(\Traversable $elements, \Kibo\Phast\Filters\HTML\HTMLPageContext $context)
    {
        $this->context = $context;
        $this->elements = $elements;
        $this->beforeLoop();
        foreach ($this->elements as $element) {
            if ($element instanceof \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag && $this->isTagOfInterest($element)) {
                foreach ($this->handleTag($element) as $item) {
                    (yield $item);
                }
            } else {
                (yield $element);
            }
        }
        $this->afterLoop();
    }
    /**
     * @param Tag $tag
     * @return bool
     */
    protected function isTagOfInterest(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        return true;
    }
    protected function beforeLoop()
    {
    }
    protected function afterLoop()
    {
    }
}
namespace Kibo\Phast\Filters\HTML\BaseURLSetter;

class Filter extends \Kibo\Phast\Filters\HTML\BaseHTMLStreamFilter
{
    protected function isTagOfInterest(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        return $tag->getTagName() == 'base' && $tag->hasAttribute('href');
    }
    protected function handleTag(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        $base = \Kibo\Phast\ValueObjects\URL::fromString($tag->getAttribute('href'));
        $current = $this->context->getBaseUrl();
        $this->context->setBaseUrl($base->withBase($current));
        (yield $tag);
    }
}
namespace Kibo\Phast\Filters\HTML\CSSInlining;

class Factory implements \Kibo\Phast\Filters\HTML\HTMLFilterFactory
{
    public function make(array $config)
    {
        $localRetriever = new \Kibo\Phast\Retrievers\LocalRetriever($config['retrieverMap']);
        $retriever = new \Kibo\Phast\Retrievers\UniversalRetriever();
        $retriever->addRetriever($localRetriever);
        $retriever->addRetriever(new \Kibo\Phast\Retrievers\CachingRetriever(new \Kibo\Phast\Cache\Sqlite\Cache($config['cache'], 'css')));
        if (!isset($config['documents']['filters'][\Kibo\Phast\Filters\HTML\CSSInlining\Filter::class]['serviceUrl'])) {
            $config['documents']['filters'][\Kibo\Phast\Filters\HTML\CSSInlining\Filter::class]['serviceUrl'] = $config['servicesUrl'];
        }
        return new \Kibo\Phast\Filters\HTML\CSSInlining\Filter((new \Kibo\Phast\Security\ServiceSignatureFactory())->make($config), \Kibo\Phast\ValueObjects\URL::fromString($config['documents']['baseUrl']), $config['documents']['filters'][\Kibo\Phast\Filters\HTML\CSSInlining\Filter::class], $localRetriever, $retriever, new \Kibo\Phast\Filters\HTML\CSSInlining\OptimizerFactory($config), (new \Kibo\Phast\Filters\CSS\Composite\Factory())->make($config), (new \Kibo\Phast\Services\Bundler\TokenRefMakerFactory())->make($config), $config['csp']['nonce']);
    }
}
namespace Kibo\Phast\Filters\HTML\CSSInlining;

class Filter extends \Kibo\Phast\Filters\HTML\BaseHTMLStreamFilter
{
    use \Kibo\Phast\Logging\LoggingTrait;
    const CSS_IMPORTS_REGEXP = '~
        @import \\s++
        ( url \\( )?+                # url() is optional
        ( (?(1) ["\']?+ | ["\'] ) ) # without url() a quote is necessary
        \\s*+ (?<url>[A-Za-z0-9_/.:?&=+%,-]++) \\s*+
        \\2                          # match ending quote
        (?(1)\\))                    # match closing paren if url( was used
        \\s*+ ;
    ~xi';
    /**
     * @var ServiceSignature
     */
    private $signature;
    /**
     * @var int
     */
    private $maxInlineDepth = 2;
    /**
     * @var URL
     */
    private $baseURL;
    /**
     * @var string[]
     */
    private $whitelist = [];
    /**
     * @var string
     */
    private $serviceUrl;
    /**
     * @var int
     */
    private $optimizerSizeDiffThreshold;
    /**
     * @var Retriever
     */
    private $localRetriever;
    /**
     * @var Retriever
     */
    private $retriever;
    /**
     * @var OptimizerFactory
     */
    private $optimizerFactory;
    /**
     * @var ServiceFilter
     */
    private $cssFilter;
    /**
     * @var Optimizer
     */
    private $optimizer;
    /**
     * @var TokenRefMaker
     */
    private $tokenRefMaker;
    /**
     * @var string[]
     */
    private $cacheMarkers = [];
    /**
     * @var string
     */
    private $cspNonce;
    public function __construct(\Kibo\Phast\Security\ServiceSignature $signature, \Kibo\Phast\ValueObjects\URL $baseURL, array $config, \Kibo\Phast\Retrievers\Retriever $localRetriever, \Kibo\Phast\Retrievers\Retriever $retriever, \Kibo\Phast\Filters\HTML\CSSInlining\OptimizerFactory $optimizerFactory, \Kibo\Phast\Services\ServiceFilter $cssFilter, \Kibo\Phast\Services\Bundler\TokenRefMaker $tokenRefMaker, $cspNonce)
    {
        $this->signature = $signature;
        $this->baseURL = $baseURL;
        $this->serviceUrl = \Kibo\Phast\ValueObjects\URL::fromString((string) $config['serviceUrl']);
        $this->optimizerSizeDiffThreshold = (int) $config['optimizerSizeDiffThreshold'];
        $this->localRetriever = $localRetriever;
        $this->retriever = $retriever;
        $this->optimizerFactory = $optimizerFactory;
        $this->cssFilter = $cssFilter;
        $this->tokenRefMaker = $tokenRefMaker;
        $this->cspNonce = $cspNonce;
        foreach ($config['whitelist'] as $key => $value) {
            if (!is_array($value)) {
                $this->whitelist[$value] = ['ieCompatible' => true];
                $key = $value;
            } else {
                $this->whitelist[$key] = $value;
            }
        }
    }
    protected function beforeLoop()
    {
        $this->elements = iterator_to_array($this->elements);
        $this->optimizer = $this->optimizerFactory->makeForElements(new \ArrayIterator($this->elements));
    }
    protected function isTagOfInterest(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        return $tag->getTagName() == 'style' || $tag->getTagName() == 'link' && $tag->getAttribute('rel') == 'stylesheet' && $tag->hasAttribute('href');
    }
    protected function handleTag(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        if ($tag->getTagName() == 'link') {
            return $this->inlineLink($tag, $this->context->getBaseUrl());
        }
        return $this->inlineStyle($tag);
    }
    protected function afterLoop()
    {
        $this->addIEFallbackScript();
        $this->addInlinedRetrieverScript();
    }
    private function inlineLink(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $link, \Kibo\Phast\ValueObjects\URL $baseUrl)
    {
        $href = trim($link->getAttribute('href'));
        if (trim($href, '/') == '') {
            return [$link];
        }
        $location = \Kibo\Phast\ValueObjects\URL::fromString($href)->withBase($baseUrl);
        if (!$this->findInWhitelist($location) && !$this->localRetriever->getCacheSalt($location)) {
            return [$link];
        }
        $media = $link->getAttribute('media');
        if (preg_match('~^\\s*(this\\.)?media\\s*=\\s*(?<q>[\'"])(?<m>((?!\\k<q>).)+?)\\k<q>\\s*(;|$)~', (string) $link->getAttribute('onload'), $match)) {
            $media = $match['m'];
        }
        $elements = $this->inlineURL($location, $media);
        return is_null($elements) ? [$link] : $elements;
    }
    private function inlineStyle(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $style)
    {
        $processed = $this->cssFilter->apply(\Kibo\Phast\ValueObjects\Resource::makeWithContent($this->baseURL, $style->textContent), [])->getContent();
        $elements = $this->inlineCSS($this->baseURL, $processed, $style->getAttribute('media'), false);
        if (($id = $style->getAttribute('id')) != '') {
            if (sizeof($elements) == 1) {
                $elements[0]->setAttribute('id', $id);
            } else {
                foreach ($elements as $element) {
                    $element->setAttribute('data-phast-original-id', $id);
                }
            }
        }
        return $elements;
    }
    private function findInWhitelist(\Kibo\Phast\ValueObjects\URL $url)
    {
        $stringUrl = (string) $url;
        foreach ($this->whitelist as $pattern => $settings) {
            if (preg_match($pattern, $stringUrl)) {
                return $settings;
            }
        }
        return false;
    }
    /**
     * @param URL $url
     * @param string $media
     * @param boolean $ieCompatible
     * @param int $currentLevel
     * @param string[] $seen
     * @return Tag[]|null
     * @throws \Kibo\Phast\Exceptions\ItemNotFoundException
     */
    private function inlineURL(\Kibo\Phast\ValueObjects\URL $url, $media, $ieCompatible = true, $currentLevel = 0, $seen = [])
    {
        $whitelistEntry = $this->findInWhitelist($url);
        if (!$whitelistEntry) {
            $whitelistEntry = !!$this->localRetriever->getCacheSalt($url);
        }
        if (!$whitelistEntry) {
            $this->logger()->info('Not inlining {url}. Not in whitelist', ['url' => $url]);
            return [$this->makeLink($url, $media)];
        }
        if (isset($whitelistEntry['ieCompatible']) && !$whitelistEntry['ieCompatible']) {
            $ieFallbackUrl = $ieCompatible ? $url : null;
            $ieCompatible = false;
        } else {
            $ieFallbackUrl = null;
        }
        if (in_array($url, $seen)) {
            return [];
        }
        if ($currentLevel > $this->maxInlineDepth) {
            return $this->addIEFallback($ieFallbackUrl, [$this->makeLink($url, $media)]);
        }
        $seen[] = $url;
        $this->logger()->info('Inlining {url}.', ['url' => (string) $url]);
        $content = $this->retriever->retrieve($url);
        if ($content === false) {
            return $this->addIEFallback($ieFallbackUrl, [$this->makeServiceLink($url, $media)]);
        }
        $content = $this->cssFilter->apply(\Kibo\Phast\ValueObjects\Resource::makeWithContent($url, $content), [])->getContent();
        $this->cacheMarkers[$url->toString()] = \Kibo\Phast\Common\Base64url::shortHash(implode("\0", [$this->retriever->getCacheSalt($url), $content]));
        $optimized = $this->optimizer->optimizeCSS($content);
        if ($optimized === null) {
            $this->logger()->error('CSS optimizer failed for {url}', ['url' => (string) $url]);
            return null;
        }
        $isOptimized = false;
        if (strlen($content) - strlen($optimized) > $this->optimizerSizeDiffThreshold) {
            $content = $optimized;
            $isOptimized = true;
        }
        $elements = $this->inlineCSS($url, $content, $media, $isOptimized, $ieCompatible, $currentLevel, $seen);
        $this->addIEFallback($ieFallbackUrl, $elements);
        return $elements;
    }
    private function inlineCSS(\Kibo\Phast\ValueObjects\URL $url, $content, $media, $optimized, $ieCompatible = true, $currentLevel = 0, $seen = [])
    {
        $urlMatches = $this->getImportedURLs($content);
        $elements = [];
        foreach ($urlMatches as $match) {
            $matchedUrl = \Kibo\Phast\ValueObjects\URL::fromString($match['url'])->withBase($url);
            $replacement = $this->inlineURL($matchedUrl, $media, $ieCompatible, $currentLevel + 1, $seen);
            if ($replacement !== null) {
                $content = str_replace($match[0], '', $content);
                $elements = array_merge($elements, $replacement);
            }
        }
        $elements[] = $this->makeStyle($url, $content, $media, $optimized);
        return $elements;
    }
    private function addIEFallback(\Kibo\Phast\ValueObjects\URL $fallbackUrl = null, array $elements = null)
    {
        if ($fallbackUrl === null || !$elements) {
            return $elements;
        }
        foreach ($elements as $element) {
            $element->setAttribute('data-phast-nested-inlined', '');
        }
        $element->setAttribute('data-phast-ie-fallback-url', (string) $fallbackUrl);
        $element->removeAttribute('data-phast-nested-inlined');
        $this->logger()->info('Set {url} as IE fallback URL', ['url' => (string) $fallbackUrl]);
        return $elements;
    }
    private function addIEFallbackScript()
    {
        $this->logger()->info('Adding IE fallback script');
        $this->context->addPhastJavaScript(\Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/CSSInlining/ie-fallback.js', "(function(){var a=function(){if(!(\"FontFace\"in window)){return false}var b=new FontFace(\"t\",'url( \"data:font/woff2;base64,d09GMgABAAAAAADwAAoAAAAAAiQAAACoAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAABmAALAogOAE2AiQDBgsGAAQgBSAHIBuDAciO1EZ3I/mL5/+5/rfPnTt9/9Qa8H4cUUZxaRbh36LiKJoVh61XGzw6ufkpoeZBW4KphwFYIJGHB4LAY4hby++gW+6N1EN94I49v86yCpUdYgqeZrOWN34CMQg2tAmthdli0eePIwAKNIIRS4AGZFzdX9lbBUAQlm//f262/61o8PlYO/D1/X4FrWFFgdCQD9DpGJSxmFyjOAGUU4P0qigcNb82GAAA\" ) format( \"woff2\" )',{});b.load()[\"catch\"](function(){});return b.status==\"loading\"||b.status==\"loaded\"}();if(a){return}console.log(\"[Phast] Browser does not support WOFF2, falling back to original stylesheets\");Array.prototype.forEach.call(document.querySelectorAll(\"style[data-phast-ie-fallback-url]\"),function(c){var d=document.createElement(\"link\");if(c.hasAttribute(\"media\")){d.setAttribute(\"media\",c.getAttribute(\"media\"))}d.setAttribute(\"rel\",\"stylesheet\");d.setAttribute(\"href\",c.getAttribute(\"data-phast-ie-fallback-url\"));c.parentNode.insertBefore(d,c);c.parentNode.removeChild(c)});Array.prototype.forEach.call(document.querySelectorAll(\"style[data-phast-nested-inlined]\"),function(e){e.parentNode.removeChild(e)})})();\n"));
    }
    private function addInlinedRetrieverScript()
    {
        $this->logger()->info('Adding inlined retriever script');
        $this->context->addPhastJavaScript(\Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/CSSInlining/inlined-css-retriever.js', "phast.stylesLoading=0;var resourceLoader=phast.ResourceLoader.instance;phast.forEachSelectedElement(\"style[data-phast-params]\",function(a){var b=a.getAttribute(\"data-phast-params\");var c=phast.ResourceLoader.RequestParams.fromString(b);phast.stylesLoading++;resourceLoader.get(c).then(function(d){a.textContent=d;a.removeAttribute(\"data-phast-params\")}).catch(function(e){console.warn(\"[Phast] Failed to load CSS\",c,e);var f=a.getAttribute(\"data-phast-original-src\");if(!f){console.error(\"[Phast] No data-phast-original-src on <style>!\",a);return}console.info(\"[Phast] Falling back to <link> element for\",f);var g=document.createElement(\"link\");g.href=f;g.media=a.media;g.rel=\"stylesheet\";g.addEventListener(\"load\",function(){if(a.parentNode){a.parentNode.removeChild(a)}});a.parentNode.insertBefore(g,a.nextSibling)}).finally(function(){phast.stylesLoading--;if(phast.stylesLoading===0&&phast.onStylesLoaded){phast.onStylesLoaded()}})});(function(){var h=[];phast.forEachSelectedElement(\"style[data-phast-original-id]\",function(i){var j=i.getAttribute(\"data-phast-original-id\");if(h[j]){return}h[j]=true;console.warn(\"[Phast] The style element with id\",j,\"has been split into multiple style tags due to @import statements and the id attribute has been removed. Normally, this does not cause any issues.\")})})();\n"));
    }
    private function getImportedURLs($cssContent)
    {
        preg_match_all(self::CSS_IMPORTS_REGEXP, $cssContent, $matches, PREG_SET_ORDER);
        return $matches;
    }
    private function makeStyle(\Kibo\Phast\ValueObjects\URL $url, $content, $media, $optimized, $stripImports = true)
    {
        $style = new \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag('style');
        if ($media !== '' && $media !== 'all') {
            $style->setAttribute('media', $media);
        }
        if ($optimized) {
            $style->setAttribute('data-phast-original-src', $url->toString());
            $style->setAttribute('data-phast-params', $this->makeServiceParams($url, $stripImports));
        }
        if ($this->cspNonce) {
            $style->setAttribute('nonce', $this->cspNonce);
        }
        $content = preg_replace('~(</)(style)~i', '$1 $2', $content);
        $style->setTextContent($content);
        return $style;
    }
    private function makeLink(\Kibo\Phast\ValueObjects\URL $url, $media)
    {
        $link = new \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag('link', ['rel' => 'stylesheet', 'href' => (string) $url]);
        if ($media !== '') {
            $link->setAttribute('media', $media);
        }
        return $link;
    }
    private function makeServiceLink(\Kibo\Phast\ValueObjects\URL $location, $media)
    {
        $url = $this->makeServiceURL($location);
        return $this->makeLink(\Kibo\Phast\ValueObjects\URL::fromString($url), $media);
    }
    protected function makeServiceParams(\Kibo\Phast\ValueObjects\URL $originalLocation, $stripImports = false)
    {
        if (isset($this->cacheMarkers[$originalLocation->toString()])) {
            $cacheMarker = $this->cacheMarkers[$originalLocation->toString()];
        } else {
            $cacheMarker = $this->retriever->getCacheSalt($originalLocation);
        }
        $src = $originalLocation;
        if ($this->localRetriever->getCacheSalt($src)) {
            $src = $originalLocation->withoutQuery();
        }
        $params = ['src' => (string) $src, 'cacheMarker' => $cacheMarker];
        if ($stripImports) {
            $params['strip-imports'] = 1;
        }
        return \Kibo\Phast\Services\Bundler\ServiceParams::fromArray($params)->sign($this->signature)->replaceByTokenRef($this->tokenRefMaker)->serialize();
    }
    protected function makeServiceURL(\Kibo\Phast\ValueObjects\URL $originalLocation)
    {
        $params = ['service' => 'css', 'src' => (string) $originalLocation, 'cacheMarker' => $this->retriever->getCacheSalt($originalLocation)];
        return (new \Kibo\Phast\Services\ServiceRequest())->withUrl($this->serviceUrl)->withParams($params)->sign($this->signature)->serialize();
    }
}
namespace Kibo\Phast\Filters\HTML\CommentsRemoval;

class Filter implements \Kibo\Phast\Filters\HTML\HTMLStreamFilter
{
    public function transformElements(\Traversable $elements, \Kibo\Phast\Filters\HTML\HTMLPageContext $context)
    {
        foreach ($elements as $element) {
            if (!$element instanceof \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Comment || $element->isIEConditional()) {
                (yield $element);
            }
        }
    }
}
namespace Kibo\Phast\Filters\HTML\DelayedIFrameLoading;

class Filter extends \Kibo\Phast\Filters\HTML\BaseHTMLStreamFilter
{
    use \Kibo\Phast\Logging\LoggingTrait;
    protected function isTagOfInterest(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        return $tag->getTagName() == 'iframe' && $tag->hasAttribute('src');
    }
    protected function handleTag(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        if (!$tag->hasAttribute('loading')) {
            $tag->setAttribute('loading', 'lazy');
        }
        (yield $tag);
    }
}
namespace Kibo\Phast\Filters\HTML\Diagnostics;

class Factory implements \Kibo\Phast\Filters\HTML\HTMLFilterFactory
{
    public function make(array $config)
    {
        $url = isset($config['documents']['filters'][\Kibo\Phast\Filters\HTML\Diagnostics\Filter::class]['serviceUrl']) ? $config['documents']['filters'][\Kibo\Phast\Filters\HTML\Diagnostics\Filter::class]['serviceUrl'] : $config['servicesUrl'] . '?service=diagnostics';
        return new \Kibo\Phast\Filters\HTML\Diagnostics\Filter($url);
    }
}
namespace Kibo\Phast\Filters\HTML\Diagnostics;

class Filter implements \Kibo\Phast\Filters\HTML\HTMLStreamFilter
{
    private $serviceUrl;
    public function __construct($serviceUrl)
    {
        $this->serviceUrl = $serviceUrl;
    }
    public function transformElements(\Traversable $elements, \Kibo\Phast\Filters\HTML\HTMLPageContext $context)
    {
        $url = (new \Kibo\Phast\Services\ServiceRequest())->withUrl(\Kibo\Phast\ValueObjects\URL::fromString($this->serviceUrl))->serialize();
        $script = \Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/Diagnostics/diagnostics.js', "window.addEventListener(\"load\",function(){var a=phast.config.diagnostics.serviceUrl;var b=new XMLHttpRequest;b.open(\"GET\",a);b.responseType=\"json\";b.onload=function(){var c=b.response;var d={};var e=[];c.forEach(function(g){var h=g.context.requestId;if(!d[h]){d[h]={title:g.context.service,timestamp:g.context.timestamp,errorsCnt:0,warningsCnt:0,longestPrefixLength:0,entries:[]};e.push(d[h])}if(g.level>8){d[h].errorsCnt++}else if(g.level===8){d[h].warningsCnt++}var i=(g.context.timestamp-d[h].timestamp).toFixed(3);if(g.context.class){i+=\" \"+g.context.class}if(g.context.class&&g.context.method){i+=\"::\"}if(g.context.method){i+=g.context.method+\"()\"}if(g.context.line){i+=\" Line: \"+g.context.line}var j=g.message.replace(/\\{([a-z0-9_.]*)\\}/gi,function(l,m){return g.context[m]});var k;if(g.level>8){k=console.error}else if(g.level===8){k=console.warn}else if(g.level>1){k=console.info}else{k=console.log}d[h].entries.push({prefix:i,message:j,cb:k});if(i.length>d[h].longestPrefixLength){d[h].longestPrefixLength=i.length}});if(e.length===0){return}e.sort(function(n,o){return n.timestamp<o.timestamp?-1:1});var f=e[0].timestamp;console.group(\"Phast diagnostics log\");e.forEach(function(p){var q=(p.timestamp-f).toFixed(3);var r=q+\" - \"+p.title+\" (entries: \"+p.entries.length;if(p.errorsCnt>0){r+=\", errors: \"+p.errorsCnt}if(p.warningsCnt>0){r+=\", warnings: \"+p.warningsCnt}r+=\")\";console.groupCollapsed(r);p.entries.forEach(function(s){var t=s.prefix;var u=p.longestPrefixLength-t.length;for(var v=0;v<u;v++){t+=\" \"}s.cb(t+\" \"+s.message)});console.groupEnd()});console.groupEnd()};b.send()});\n");
        $script->setConfig('diagnostics', ['serviceUrl' => $url]);
        $context->addPhastJavaScript($script);
        foreach ($elements as $element) {
            (yield $element);
        }
    }
}
namespace Kibo\Phast\Filters\HTML\ImagesOptimizationService\CSS;

class Filter extends \Kibo\Phast\Filters\HTML\BaseHTMLStreamFilter
{
    /**
     * @var ImageURLRewriter
     */
    protected $rewriter;
    /**
     * Filter constructor.
     * @param ImageURLRewriter $rewriter
     */
    public function __construct(\Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageURLRewriter $rewriter)
    {
        $this->rewriter = $rewriter;
    }
    protected function handleTag(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        if ($tag->hasAttribute('style')) {
            $tag->setAttribute('style', $this->rewriter->rewriteStyle($tag->getAttribute('style')));
        }
        (yield $tag);
    }
}
namespace Kibo\Phast\Filters\HTML\LazyImageLoading;

class Filter extends \Kibo\Phast\Filters\HTML\BaseHTMLStreamFilter
{
    protected function handleTag(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        if (!$tag->hasAttribute('loading')) {
            $tag->setAttribute('loading', 'lazy');
        }
        (yield $tag);
    }
    protected function isTagOfInterest(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        return $tag->getTagName() == 'img';
    }
}
namespace Kibo\Phast\Filters\HTML\ScriptsDeferring;

class Filter extends \Kibo\Phast\Filters\HTML\BaseHTMLStreamFilter
{
    use \Kibo\Phast\Filters\HTML\Helpers\JSDetectorTrait;
    /** @var array */
    private $csp;
    /**
     * @param array $csp
     */
    public function __construct(array $csp)
    {
        $this->csp = $csp;
    }
    protected function isTagOfInterest(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        return $tag->getTagName() == 'script';
    }
    protected function handleTag(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $script)
    {
        if ($this->isJSElement($script) && !$this->isDeferralDisabled($script)) {
            $this->rewrite($script);
        }
        (yield $script);
    }
    protected function afterLoop()
    {
        $scriptsLoader = \Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/ScriptsDeferring/scripts-loader.js', "var Promise=phast.ES6Promise;var hasCurrentScript=!!document.currentScript;phast.ScriptsLoader={};phast.ScriptsLoader.getScriptsInExecutionOrder=function(a,b){var c=Array.prototype.slice.call(a.querySelectorAll('script[type=\"text/phast\"]')).filter(g);var d=[],e=[];for(var f=0;f<c.length;f++){if(getSrc(c[f])!==undefined&&isDefer(c[f])){e.push(c[f])}else{d.push(c[f])}}return d.concat(e).map(function(j){return b.makeScriptFromElement(j)});function g(k){try{var l=phast.config.scriptsLoader.csp}catch(m){return true}if(l.nonce==null){return true}if(k.nonce===l.nonce){return true}try{h(l,k)}catch(n){console.error(\"Could not send CSP report due to error:\",n)}if(l.reportOnly){console.warn(\"Script with missing or invalid nonce would not be executed (but report-only mode is enabled):\",k);return true}console.warn(\"Script with missing or invalid nonce will not be executed:\",k);return false}function h(o,p){var q={\"blocked-uri\":getSrc(p),disposition:o.reportOnly?\"report\":\"enforce\",\"document-uri\":location.href,referrer:a.referrer,\"script-sample\":i(p),implementation:\"phast\"};try{p.dispatchEvent(new SecurityPolicyViolationEvent(\"securitypolicyviolation\",{blockedURI:q[\"blocked-uri\"],disposition:q[\"disposition\"],documentURI:q[\"document-uri\"],effectiveDirective:\"script-src-elem\",originalPolicy:\"phast\",referrer:q[\"referrer\"],sample:q[\"script-sample\"],statusCode:200,violatedDirective:\"script-src-elem\"}))}catch(s){console.error(\"[Phast] Could not dispatch securitypolicyviolation event\",s)}if(!o.reportUri){return}var r={\"csp-report\":q};fetch(o.reportUri,{method:\"POST\",headers:{\"Content-Type\":\"application/csp-report\"},credentials:\"same-origin\",redirect:\"error\",keepalive:true,body:JSON.stringify(r)})}function i(t){if(!t.hasAttribute(\"src\")){return t.textContent.substr(0,40)}}};phast.ScriptsLoader.executeScripts=function(u){var v=u.map(function(x){return x.init()});var w=Promise.resolve();u.forEach(function(y){w=phast.ScriptsLoader.chainScript(w,y)});return w.then(function(){return Promise.all(v).catch(function(){})})};phast.ScriptsLoader.chainScript=function(z,A){var B;try{if(A.describe){B=A.describe()}else{B=\"unknown script\"}}catch(C){B=\"script.describe() failed\"}return z.then(function(){var D=A.execute();D.then(function(){console.debug(\"\342\234\223\",B)});return D}).catch(function(E){console.error(\"\342\234\230\",B);if(E){console.log(E)}})};var insertBefore=window.Element.prototype.insertBefore;phast.ScriptsLoader.Utilities=function(F){this._document=F;var G=0;function H(R){return new Promise(function(S){var T=\"PhastCompleteScript\"+ ++G;var U=I(R);var V=I(T+\"()\");window[T]=W;F.body.appendChild(U);F.body.appendChild(V);function W(){S();F.body.removeChild(U);F.body.removeChild(V);delete window[T]}})}function I(X){var Y=F.createElement(\"script\");Y.textContent=X;Y.nonce=phast.config.scriptsLoader.csp.nonce;return Y}function J(Z){var \$=F.createElement(Z.nodeName);Array.prototype.forEach.call(Z.attributes,function(_){\$.setAttribute(_.nodeName,_.nodeValue)});return \$}function K(aa){aa.removeAttribute(\"data-phast-params\");var ba={};Array.prototype.map.call(aa.attributes,function(ca){return ca.nodeName}).map(function(da){var ea=da.match(/^data-phast-original-(.*)/i);if(ea){ba[ea[1].toLowerCase()]=aa.getAttribute(da);aa.removeAttribute(da)}});Object.keys(ba).sort().map(function(fa){aa.setAttribute(fa,ba[fa])});if(!(\"type\"in ba)){aa.removeAttribute(\"type\")}}function L(ga,ha){return new Promise(function(ia,ja){var ka=ha.getAttribute(\"src\");ha.addEventListener(\"load\",ia);ha.addEventListener(\"error\",ja);ha.removeAttribute(\"src\");insertBefore.call(ga.parentNode,ha,ga);ga.parentNode.removeChild(ga);if(ka){ha.setAttribute(\"src\",ka)}})}function M(la,ma){return O(la,function(){return P(la,function(){return H(ma)})})}function N(na,oa){return O(oa,function(){return L(na,oa)})}function O(pa,qa){var ra=pa.nextElementSibling;var sa=Promise.resolve();var ta;if(isAsync(pa)){ta=\"async\"}else if(isDefer(pa)){ta=\"defer\"}F.write=function(xa){if(ta){console.warn(\"document.write call from \"+ta+\" script ignored\");return}ua(xa)};F.writeln=function(ya){if(ta){console.warn(\"document.writeln call from \"+ta+\" script ignored\");return}ua(ya+\"\\n\")};function ua(za){var Aa=F.createElement(\"div\");Aa.innerHTML=za;var Ba=va(Aa);if(ra&&ra.parentNode!==pa.parentNode){ra=pa.nextElementSibling}while(Aa.firstChild){pa.parentNode.insertBefore(Aa.firstChild,ra)}Ba.map(wa)}function va(Ca){return Array.prototype.slice.call(Ca.getElementsByTagName(\"script\")).filter(function(Da){var Ea=Da.getAttribute(\"type\");return!Ea||/^(text|application)\\/javascript(;|\$)/i.test(Ea)})}function wa(Fa){var Ga=new phast.ScriptsLoader.Scripts.Factory(F);var Ha=Ga.makeScriptFromElement(Fa);sa=phast.ScriptsLoader.chainScript(sa,Ha)}return qa().then(function(){return sa}).finally(function(){delete F.write;delete F.writeln})}function P(Ia,Ja){if(hasCurrentScript){try{Object.defineProperty(F,\"currentScript\",{configurable:true,get:function(){return Ia}})}catch(Ka){console.error(\"[Phast] Unable to override document.currentScript on this browser: \",Ka)}}return Ja().finally(function(){if(hasCurrentScript){delete F.currentScript}})}function Q(La){var Ma=F.createElement(\"link\");Ma.setAttribute(\"rel\",\"preload\");Ma.setAttribute(\"as\",\"script\");Ma.setAttribute(\"href\",La);F.head.appendChild(Ma)}this.executeString=H;this.copyElement=J;this.restoreOriginals=K;this.replaceElement=L;this.writeProtectAndExecuteString=M;this.writeProtectAndReplaceElement=N;this.addPreload=Q};phast.ScriptsLoader.Scripts={};phast.ScriptsLoader.Scripts.InlineScript=function(Na,Oa){this._utils=Na;this._element=Oa;this.init=function(){return Promise.resolve()};this.execute=function(){var Pa=Oa.textContent.replace(/^\\s*<!--.*\\n/i,\"\");Na.restoreOriginals(Oa);return Na.writeProtectAndExecuteString(Oa,Pa)};this.describe=function(){return\"inline script\"}};phast.ScriptsLoader.Scripts.AsyncBrowserScript=function(Qa,Ra){var Sa;this._utils=Qa;this._element=Ra;this.init=function(){Qa.addPreload(getSrc(Ra));return new Promise(function(Ta){Sa=Ta})};this.execute=function(){var Ua=Qa.copyElement(Ra);Qa.restoreOriginals(Ua);Qa.replaceElement(Ra,Ua).then(Sa).catch(Sa);return Promise.resolve()};this.describe=function(){return\"async script at \"+getSrc(Ra)}};phast.ScriptsLoader.Scripts.SyncBrowserScript=function(Va,Wa){this._utils=Va;this._element=Wa;this.init=function(){Va.addPreload(getSrc(Wa));return Promise.resolve()};this.execute=function(){var Xa=Va.copyElement(Wa);Va.restoreOriginals(Xa);return Va.writeProtectAndReplaceElement(Wa,Xa)};this.describe=function(){return\"sync script at \"+getSrc(Wa)}};phast.ScriptsLoader.Scripts.AsyncAJAXScript=function(Ya,Za,\$a,_a){this._utils=Ya;this._element=Za;this._fetch=\$a;this._fallback=_a;var a0;var b0;this.init=function(){a0=\$a(Za);return new Promise(function(c0){b0=c0})};this.execute=function(){a0.then(function(d0){Ya.restoreOriginals(Za);return Ya.writeProtectAndExecuteString(Za,d0).then(b0)}).catch(function(){_a.init();return _a.execute().then(b0)});return Promise.resolve()};this.describe=function(){return\"bundled async script at \"+Za.getAttribute(\"data-phast-original-src\")}};phast.ScriptsLoader.Scripts.SyncAJAXScript=function(e0,f0,g0,h0){this._utils=e0;this._element=f0;this._fetch=g0;this._fallback=h0;var i0;this.init=function(){i0=g0(f0);return i0};this.execute=function(){return i0.then(function(j0){e0.restoreOriginals(f0);return e0.writeProtectAndExecuteString(f0,j0)}).catch(function(){h0.init();return h0.execute()})};this.describe=function(){return\"bundled sync script at \"+f0.getAttribute(\"data-phast-original-src\")}};phast.ScriptsLoader.Scripts.Factory=function(k0,l0){var m0=phast.ScriptsLoader.Scripts;var n0=new phast.ScriptsLoader.Utilities(k0);this.makeScriptFromElement=function(q0){var r0;if(q0.getAttribute(\"data-phast-debug-force-method\")&&window.location.host.match(/\\.test\$/)){return new m0[q0.getAttribute(\"data-phast-debug-force-method\")](n0,q0)}if(o0(q0)){if(isAsync(q0)){r0=new m0.AsyncBrowserScript(n0,q0);return l0?new m0.AsyncAJAXScript(n0,q0,l0,r0):r0}r0=new m0.SyncBrowserScript(n0,q0);return l0?new m0.SyncAJAXScript(n0,q0,l0,r0):r0}if(p0(q0)){return new m0.InlineScript(n0,q0)}if(isAsync(q0)){return new m0.AsyncBrowserScript(n0,q0)}return new m0.SyncBrowserScript(n0,q0)};function o0(s0){return s0.hasAttribute(\"data-phast-params\")}function p0(t0){return!t0.hasAttribute(\"src\")}};function getSrc(u0){if(u0.hasAttribute(\"data-phast-original-src\")){return u0.getAttribute(\"data-phast-original-src\")}else if(u0.hasAttribute(\"src\")){return u0.getAttribute(\"src\")}}function isAsync(v0){return v0.hasAttribute(\"async\")||v0.hasAttribute(\"data-phast-async\")}function isDefer(w0){return w0.hasAttribute(\"defer\")||w0.hasAttribute(\"data-phast-defer\")}\n");
        $scriptsLoader->setConfig('scriptsLoader', ['csp' => $this->csp]);
        $this->context->addPhastJavaScript($scriptsLoader);
        $this->context->addPhastJavaScript(\Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/ScriptsDeferring/rewrite.js', "var Promise=phast.ES6Promise;var go=phast.once(loadScripts);phast.on(document,\"DOMContentLoaded\").then(function(){if(phast.stylesLoading){phast.onStylesLoaded=go;setTimeout(go,4e3)}else{Promise.resolve().then(go)}});var loadFiltered=false;window.addEventListener(\"load\",function(a){if(!loadFiltered){a.stopImmediatePropagation()}loadFiltered=true});document.addEventListener(\"readystatechange\",function(b){if(document.readyState===\"loading\"){b.stopImmediatePropagation()}});var didSetTimeout=false;var originalSetTimeout=window.setTimeout;window.setTimeout=function(c,d){if(!d||d<0){didSetTimeout=true}return originalSetTimeout.apply(window,arguments)};function loadScripts(){var e=new phast.ScriptsLoader.Scripts.Factory(document,fetchScript);var f=phast.ScriptsLoader.getScriptsInExecutionOrder(document,e);if(f.length===0){return}setReadyState(\"loading\");phast.ScriptsLoader.executeScripts(f).then(restoreReadyState)}function setReadyState(g){try{Object.defineProperty(document,\"readyState\",{configurable:true,get:function(){return g}})}catch(h){console.warn(\"[Phast] Unable to override document.readyState on this browser: \",h)}}function restoreReadyState(){i().then(function(){setReadyState(\"interactive\");triggerEvent(document,\"readystatechange\");return i()}).then(function(){triggerEvent(document,\"DOMContentLoaded\");return i()}).then(function(){delete document[\"readyState\"];triggerEvent(document,\"readystatechange\");if(loadFiltered){triggerEvent(window,\"load\")}loadFiltered=true});function i(){return new Promise(function(j){(function k(l){if(didSetTimeout&&l<10){didSetTimeout=false;originalSetTimeout.call(window,function(){k(l+1)})}else{requestAnimationFrame(j)}})(0)})}}function triggerEvent(m,n){var o=document.createEvent(\"Event\");o.initEvent(n,true,true);m.dispatchEvent(o)}function fetchScript(p){return phast.ResourceLoader.instance.get(phast.ResourceLoader.RequestParams.fromString(p.getAttribute(\"data-phast-params\")))}\n"));
    }
    private function rewrite(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $script)
    {
        if (!$script->hasAttribute('src')) {
            $script->removeAttribute('async');
            $script->removeAttribute('defer');
        }
        if ($script->hasAttribute('type')) {
            $script->setAttribute('data-phast-original-type', $script->getAttribute('type'));
        }
        $script->setAttribute('type', 'text/phast');
        if ($script->hasAttribute('data-phast-params')) {
            $script->removeAttribute('src');
        }
        if (!$script->hasAttribute('src')) {
            $this->convertBooleanAttribute($script, 'async');
            $this->convertBooleanAttribute($script, 'defer');
        }
    }
    private function isDeferralDisabled(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $script)
    {
        return $script->hasAttribute('data-phast-no-defer') || $script->hasAttribute('data-pagespeed-no-defer') || $script->getAttribute('data-cfasync') === 'false' || preg_match('~^\\s*(?<q>[\'"])phast-no-defer\\k<q>~', $script->textContent);
    }
    private function convertBooleanAttribute(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $script, $attr)
    {
        if ($script->hasAttribute($attr)) {
            $script->removeAttribute($attr);
            $script->setAttribute('data-phast-' . $attr, '');
        }
    }
}
namespace Kibo\Phast\Filters\HTML\ScriptsProxyService;

class Filter extends \Kibo\Phast\Filters\HTML\BaseHTMLStreamFilter
{
    use \Kibo\Phast\Filters\HTML\Helpers\JSDetectorTrait, \Kibo\Phast\Logging\LoggingTrait;
    /**
     * @var array
     */
    private $config;
    /**
     * @var ServiceSignature
     */
    private $signature;
    /**
     * @var LocalRetriever
     */
    private $retriever;
    private $tokenRefMaker;
    /**
     * @var ObjectifiedFunctions
     */
    private $functions;
    /**
     * @var bool
     */
    private $didInject = false;
    public function __construct(array $config, \Kibo\Phast\Security\ServiceSignature $signature, \Kibo\Phast\Retrievers\LocalRetriever $retriever, \Kibo\Phast\Services\Bundler\TokenRefMaker $tokenRefMaker, \Kibo\Phast\Common\ObjectifiedFunctions $functions = null)
    {
        $this->config = $config;
        $this->signature = $signature;
        $this->retriever = $retriever;
        $this->tokenRefMaker = $tokenRefMaker;
        $this->functions = is_null($functions) ? new \Kibo\Phast\Common\ObjectifiedFunctions() : $functions;
    }
    protected function isTagOfInterest(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $tag)
    {
        return $tag->getTagName() == 'script' && $this->isJSElement($tag);
    }
    protected function handleTag(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $script)
    {
        $this->rewriteScriptSource($script);
        if (!$this->didInject) {
            $this->addScript();
            $this->didInject = true;
        }
        (yield $script);
    }
    private function rewriteScriptSource(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag $element)
    {
        if (!$element->hasAttribute('src')) {
            return;
        }
        $src = trim($element->getAttribute('src'));
        $url = $this->getAbsoluteURL($src);
        $cacheMarker = $this->retriever->getCacheSalt($url);
        if (!$cacheMarker) {
            return;
        }
        $cacheMarker .= '-' . \Kibo\Phast\Filters\JavaScript\Minification\JSMinifierFilter::VERSION;
        $element->setAttribute('src', $this->makeProxiedURL($url, $cacheMarker));
        $element->setAttribute('data-phast-original-src', (string) $url);
        $element->setAttribute('data-phast-params', $this->makeServiceParams($url, $cacheMarker));
    }
    private function makeProxiedURL(\Kibo\Phast\ValueObjects\URL $url, $cacheMarker)
    {
        $params = ['service' => 'scripts', 'src' => (string) $url->withoutQuery(), 'cacheMarker' => $cacheMarker];
        return (new \Kibo\Phast\Services\ServiceRequest())->withUrl(\Kibo\Phast\ValueObjects\URL::fromString($this->config['serviceUrl']))->withParams($params)->serialize();
    }
    private function makeServiceParams(\Kibo\Phast\ValueObjects\URL $url, $cacheMarker)
    {
        return \Kibo\Phast\Services\Bundler\ServiceParams::fromArray(['src' => (string) $url->withoutQuery(), 'cacheMarker' => $cacheMarker, 'isScript' => '1'])->sign($this->signature)->replaceByTokenRef($this->tokenRefMaker)->serialize();
    }
    private function addScript()
    {
        $config = ['serviceUrl' => $this->config['serviceUrl'], 'pathInfo' => \Kibo\Phast\Services\ServiceRequest::getDefaultSerializationMode() === \Kibo\Phast\Services\ServiceRequest::FORMAT_PATH, 'urlRefreshTime' => $this->config['urlRefreshTime'], 'whitelist' => $this->config['match']];
        $script = \Kibo\Phast\ValueObjects\PhastJavaScript::fromString('/home/albert/code/phast/src/Build/../../src/Filters/HTML/ScriptsProxyService/rewrite-function.js', "var config=phast.config[\"script-proxy-service\"];var urlPattern=/^(https?:)?\\/\\//;var typePattern=/^\\s*(application|text)\\/(x-)?(java|ecma|j|live)script/i;var cacheMarker=Math.floor((new Date).getTime()/1e3/config.urlRefreshTime);var whitelist=compileWhitelistPatterns(config.whitelist);phast.scripts.push(function(){overrideDOMMethod(\"appendChild\");overrideDOMMethod(\"insertBefore\")});function compileWhitelistPatterns(a){var b=/^(.)(.*)\\1([a-z]*)\$/i;var c=[];a.forEach(function(d){var e=b.exec(d);if(!e){window.console&&window.console.log(\"Phast: Not a pattern:\",d);return}try{c.push(new RegExp(e[2],e[3]))}catch(f){window.console&&window.console.log(\"Phast: Failed to compile pattern:\",d)}});return c}function checkWhitelist(g){for(var h=0;h<whitelist.length;h++){if(whitelist[h].exec(g)){return true}}return false}function overrideDOMMethod(i){var j=Element.prototype[i];var k=function(){var l=processNode(arguments[0]);var m=j.apply(this,arguments);l();return m};Element.prototype[i]=k;window.addEventListener(\"load\",function(){if(Element.prototype[i]===k){delete Element.prototype[i]}})}function processNode(n){if(!n||n.nodeType!==Node.ELEMENT_NODE||n.tagName!==\"SCRIPT\"||!urlPattern.test(n.src)||n.type&&!typePattern.test(n.type)||n.src.substr(0,config.serviceUrl.length)===config.serviceUrl||!checkWhitelist(n.src)){return function(){}}var o=n.src;n.src=phast.buildServiceUrl(config,{service:\"scripts\",src:o,cacheMarker:cacheMarker});n.setAttribute(\"data-phast-rewritten\",\"\");return function(){n.src=o}}\n");
        $script->setConfig('script-proxy-service', $config);
        $this->context->addPhastJavaScript($script);
    }
    private function getAbsoluteURL($url)
    {
        return \Kibo\Phast\ValueObjects\URL::fromString($url)->withBase($this->context->getBaseUrl());
    }
}
namespace Kibo\Phast\Filters\Image\CommonDiagnostics;

class DiagnosticsRetriever implements \Kibo\Phast\Retrievers\Retriever
{
    /**
     * @var string
     */
    private $file;
    /**
     * DiagnosticsRetriever constructor.
     * @param string $file
     */
    public function __construct($file)
    {
        $this->file = $file;
    }
    public function retrieve(\Kibo\Phast\ValueObjects\URL $url)
    {
        return file_get_contents($this->file);
    }
    public function getCacheSalt(\Kibo\Phast\ValueObjects\URL $url)
    {
        return '';
    }
}
namespace Kibo\Phast\Filters\Image\ImageAPIClient;

class Factory implements \Kibo\Phast\Filters\Image\ImageFilterFactory
{
    public function make(array $config)
    {
        $signature = new \Kibo\Phast\Security\ServiceSignature(new \Kibo\Phast\Cache\Sqlite\Cache($config['cache'], 'api-service-signature'));
        return new \Kibo\Phast\Filters\Image\ImageAPIClient\Filter($config['images']['filters'][\Kibo\Phast\Filters\Image\ImageAPIClient\Filter::class], $signature, (new \Kibo\Phast\HTTP\ClientFactory())->make($config));
    }
}
namespace Kibo\Phast\Filters\Image\ImageAPIClient;

class Filter implements \Kibo\Phast\Filters\Image\ImageFilter
{
    /**
     * @var array
     */
    private $config;
    /**
     * @var ServiceSignature
     */
    private $signature;
    /**
     * @var Client
     */
    private $client;
    /**
     * Filter constructor.
     * @param array $config
     * @param ServiceSignature $signature
     * @param Client $client
     */
    public function __construct(array $config, \Kibo\Phast\Security\ServiceSignature $signature, \Kibo\Phast\HTTP\Client $client)
    {
        $this->config = $config;
        $this->signature = $signature;
        $this->client = $client;
        $this->signature->setIdentities('');
    }
    public function getCacheSalt(array $request)
    {
        $result = 'api-call';
        foreach (['width', 'height', 'preferredType'] as $key) {
            if (isset($request[$key])) {
                $result .= "-{$key}-{$request[$key]}";
            }
        }
        return $result;
    }
    public function transformImage(\Kibo\Phast\Filters\Image\Image $image, array $request)
    {
        $url = $this->getRequestURL($request);
        $headers = $this->getRequestHeaders($image, $request);
        $data = $image->getAsString();
        try {
            $response = $this->client->post(\Kibo\Phast\ValueObjects\URL::fromString($url), $data, $headers);
        } catch (\Exception $e) {
            throw new \Kibo\Phast\Filters\Image\Exceptions\ImageProcessingException('Request exception: ' . get_class($e) . ' MSG: ' . $e->getMessage() . ' Code: ' . $e->getCode());
        }
        if (strlen($response->getContent()) === 0) {
            throw new \Kibo\Phast\Filters\Image\Exceptions\ImageProcessingException('Image API response is empty');
        }
        $newImage = new \Kibo\Phast\Filters\Image\ImageImplementations\DummyImage();
        $newImage->setImageString($response->getContent());
        $headers = [];
        foreach ($response->getHeaders() as $name => $value) {
            $headers[strtolower($name)] = $value;
        }
        $newImage->setType($headers['content-type']);
        return $newImage;
    }
    private function getRequestURL(array $request)
    {
        $params = [];
        foreach (['width', 'height'] as $key) {
            if (isset($request[$key])) {
                $params[$key] = $request[$key];
            }
        }
        return (new \Kibo\Phast\Services\ServiceRequest())->withUrl(\Kibo\Phast\ValueObjects\URL::fromString($this->config['api-url']))->withParams($params)->sign($this->signature)->serialize(\Kibo\Phast\Services\ServiceRequest::FORMAT_QUERY);
    }
    private function getRequestHeaders(\Kibo\Phast\Filters\Image\Image $image, array $request)
    {
        $headers = ['X-Phast-Image-API-Client' => $this->getRequestToken(), 'Content-Type' => 'application/octet-stream'];
        if (isset($request['preferredType']) && $request['preferredType'] == \Kibo\Phast\Filters\Image\Image::TYPE_WEBP) {
            $headers['Accept'] = 'image/webp';
        }
        return $headers;
    }
    private function getRequestToken()
    {
        $token_parts = [];
        foreach (['host-name', 'request-uri', 'plugin-version'] as $key) {
            $token_parts[$key] = $this->config[$key];
        }
        $token_parts['php'] = PHP_VERSION;
        return json_encode($token_parts);
    }
}
namespace Kibo\Phast\Filters\Service;

interface CachedResultServiceFilter extends \Kibo\Phast\Services\ServiceFilter
{
    /**
     * @param Resource $resource
     * @param array $request
     * @return string
     */
    public function getCacheSalt(\Kibo\Phast\ValueObjects\Resource $resource, array $request);
}
namespace Kibo\Phast\Filters\Service;

class CachingServiceFilter implements \Kibo\Phast\Services\ServiceFilter
{
    use \Kibo\Phast\Logging\LoggingTrait;
    /**
     * @var Cache
     */
    private $cache;
    /**
     * @var CachedResultServiceFilter
     */
    private $cachedFilter;
    /**
     * @var Retriever
     */
    private $retriever;
    /**
     * CachingServiceFilter constructor.
     * @param Cache $cache
     * @param CachedResultServiceFilter $cachedFilter
     * @param Retriever $retriever
     */
    public function __construct(\Kibo\Phast\Cache\Cache $cache, \Kibo\Phast\Filters\Service\CachedResultServiceFilter $cachedFilter, \Kibo\Phast\Retrievers\Retriever $retriever)
    {
        $this->cache = $cache;
        $this->cachedFilter = $cachedFilter;
        $this->retriever = $retriever;
    }
    /**
     * @param Resource $resource
     * @param array $request
     * @return Resource
     * @throws CachedExceptionException
     */
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $key = $this->cachedFilter->getCacheSalt($resource, $request);
        $this->logger()->info('Trying to get {url} from cache', ['url' => (string) $resource->getUrl()]);
        $result = $this->cache->get($key);
        if (isset($result['encoding']) && $result['encoding'] != 'identity') {
            $result = null;
        }
        if ($result && $this->checkDependencies($result)) {
            return $this->deserializeCachedData($result);
        }
        try {
            $result = $this->cachedFilter->apply($resource, $request);
            $this->cache->set($key, $this->serializeResource($result));
            return $result;
        } catch (\Exception $e) {
            $cachingException = $this->serializeException($e);
            $this->cache->set($key, $cachingException);
            throw $this->deserializeException($cachingException);
        }
    }
    private function checkDependencies(array $data)
    {
        foreach ((array) @$data['dependencies'] as $dep) {
            $url = \Kibo\Phast\ValueObjects\URL::fromString($dep['url']);
            if ($this->retriever->getCacheSalt($url) >= $dep['cacheMarker']) {
                return false;
            }
        }
        return true;
    }
    private function deserializeCachedData(array $data)
    {
        if ($data['dataType'] == 'exception') {
            throw $this->deserializeException($data);
        }
        return $this->deserializeResource($data);
    }
    private function serializeResource(\Kibo\Phast\ValueObjects\Resource $resource)
    {
        return ['dataType' => 'resource', 'url' => $resource->getUrl()->toString(), 'mimeType' => $resource->getMimeType(), 'blob' => $resource->getContent(), 'dependencies' => $this->serializeDependencies($resource)];
    }
    private function serializeDependencies(\Kibo\Phast\ValueObjects\Resource $resource)
    {
        return array_map(function (\Kibo\Phast\ValueObjects\Resource $dep) {
            return ['url' => $dep->getUrl()->toString(), 'cacheMarker' => $dep->getCacheSalt()];
        }, $resource->getDependencies());
    }
    private function deserializeResource(array $data)
    {
        $params = [\Kibo\Phast\ValueObjects\URL::fromString($data['url']), $data['blob'], $data['mimeType']];
        return \Kibo\Phast\ValueObjects\Resource::makeWithContent(...$params);
    }
    private function serializeException(\Exception $e)
    {
        return ['dataType' => 'exception', 'class' => get_class($e), 'msg' => $e->getMessage(), 'code' => $e->getCode()];
    }
    private function deserializeException(array $data)
    {
        return new \Kibo\Phast\Exceptions\CachedExceptionException(sprintf('Phast: %s: Type: %s, Msg: %s, Code: %s', static::class, $data['class'], $data['msg'], $data['code']));
    }
}
namespace Kibo\Phast\Filters\Service;

class CompositeFilter implements \Kibo\Phast\Filters\Service\CachedResultServiceFilter
{
    use \Kibo\Phast\Logging\LoggingTrait;
    /**
     * @var ServiceFilter[]
     */
    private $filters = [];
    public function addFilter(\Kibo\Phast\Services\ServiceFilter $filter)
    {
        $this->filters[] = $filter;
    }
    public function getCacheSalt(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $classes = array_map('get_class', $this->filters);
        $cached = array_filter($this->filters, function (\Kibo\Phast\Services\ServiceFilter $filter) {
            return $filter instanceof \Kibo\Phast\Filters\Service\CachedResultServiceFilter;
        });
        $salts = array_map(function (\Kibo\Phast\Filters\Service\CachedResultServiceFilter $filter) use($resource, $request) {
            return $filter->getCacheSalt($resource, $request);
        }, $cached);
        return join("\n", array_merge($classes, $salts, [$resource->getUrl(), $resource->getCacheSalt()]));
    }
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $this->logger()->info('Starting filtering for resource {url}', ['url' => $resource->getUrl()]);
        $result = array_reduce($this->filters, function (\Kibo\Phast\ValueObjects\Resource $resource, \Kibo\Phast\Services\ServiceFilter $filter) use($request) {
            $this->logger()->info('Starting {filter}', ['filter' => get_class($filter)]);
            try {
                return $filter->apply($resource, $request);
            } catch (\Kibo\Phast\Exceptions\RuntimeException $e) {
                $message = 'Phast RuntimeException: Filter: {filter} Exception: {exceptionClass} Msg: {message} Code: {code} File: {file} Line: {line}';
                $this->logger()->critical($message, ['filter' => get_class($filter), 'exceptionClass' => get_class($e), 'message' => $e->getMessage(), 'code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
                return $resource;
            }
        }, $resource);
        $this->logger()->info('Done filtering for resource {url}', ['url' => $resource->getUrl()]);
        return $result;
    }
}
namespace Kibo\Phast\Filters\Text\Decode;

class Filter implements \Kibo\Phast\Services\ServiceFilter
{
    const UTF8_BOM = "\357\273\277";
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request = [])
    {
        $content = $resource->getContent();
        if (substr($content, 0, strlen(self::UTF8_BOM)) == self::UTF8_BOM) {
            $content = substr($content, strlen(self::UTF8_BOM));
        }
        return $resource->withContent($content);
    }
}
namespace Kibo\Phast\HTTP;

class CURLClient implements \Kibo\Phast\HTTP\Client
{
    public function get(\Kibo\Phast\ValueObjects\URL $url, array $headers = [])
    {
        $this->checkCURL();
        return $this->request($url, $headers);
    }
    public function post(\Kibo\Phast\ValueObjects\URL $url, $data, array $headers = [])
    {
        $this->checkCURL();
        return $this->request($url, $headers, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data]);
    }
    private function checkCURL()
    {
        if (!function_exists('curl_init')) {
            throw new \Kibo\Phast\HTTP\Exceptions\NetworkError('cURL is not installed');
        }
    }
    private function request(\Kibo\Phast\ValueObjects\URL $url, array $headers = [], array $opts = [])
    {
        $response = new \Kibo\Phast\HTTP\Response();
        $readHeader = function ($_, $headerLine) use($response) {
            if (strpos($headerLine, 'HTTP/') === 0) {
                $response->setHeaders([]);
            } else {
                list($name, $value) = explode(':', $headerLine, 2);
                if (trim($name) !== '') {
                    $response->setHeader($name, trim($value));
                }
            }
            return strlen($headerLine);
        };
        $ch = curl_init((string) $url);
        curl_setopt_array($ch, $opts + [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $this->makeHeaders($headers), CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_HEADERFUNCTION => $readHeader, CURLOPT_CAINFO => __DIR__ . '/cacert.pem', CURLOPT_ENCODING => '']);
        $responseText = @curl_exec($ch);
        if ($responseText === false) {
            throw new \Kibo\Phast\HTTP\Exceptions\NetworkError(curl_error($ch), curl_errno($ch));
        }
        $info = curl_getinfo($ch);
        if (!preg_match('/^2/', $info['http_code'])) {
            throw new \Kibo\Phast\HTTP\Exceptions\HTTPError($info['http_code']);
        }
        $response->setCode($info['http_code']);
        $response->setContent($responseText);
        return $response;
    }
    private function makeHeaders(array $headers)
    {
        $result = [];
        foreach ($headers as $k => $v) {
            $result[] = "{$k}: {$v}";
        }
        return $result;
    }
}
namespace Kibo\Phast\Parsing\HTML\HTMLStreamElements;

class ClosingTag extends \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Element
{
    /**
     * @var string
     */
    private $tagName;
    /**
     * ClosingTag constructor.
     * @param string $tagName
     */
    public function __construct($tagName)
    {
        $this->tagName = strtolower($tagName);
    }
    /**
     * @return string
     */
    public function getTagName()
    {
        return $this->tagName;
    }
    public function appendChild(\Kibo\Phast\Parsing\HTML\HTMLStreamElements\Element $element)
    {
        $this->stream->insertBeforeElement($this, $element);
    }
    public function dumpValue()
    {
        return $this->tagName;
    }
}
namespace Kibo\Phast\Parsing\HTML\HTMLStreamElements;

class Comment extends \Kibo\Phast\Parsing\HTML\HTMLStreamElements\Element
{
    public function isIEConditional()
    {
        return (bool) preg_match('/^<!--\\[if\\s/', $this->originalString);
    }
}
namespace Kibo\Phast\Retrievers;

class CachingRetriever implements \Kibo\Phast\Retrievers\Retriever
{
    use \Kibo\Phast\Retrievers\DynamicCacheSaltTrait {
        getCacheSalt as getDynamicCacheSalt;
    }
    /**
     * @var Cache
     */
    private $cache;
    /**
     * @var Retriever
     */
    private $retriever;
    /**
     * CachingRetriever constructor.
     *
     * @param Retriever $retriever
     * @param Cache $cache
     * @param int $defaultCacheTime
     */
    public function __construct(\Kibo\Phast\Cache\Cache $cache, \Kibo\Phast\Retrievers\Retriever $retriever = null, $defaultCacheTime = 0)
    {
        $this->cache = $cache;
        $this->retriever = $retriever;
    }
    public function retrieve(\Kibo\Phast\ValueObjects\URL $url)
    {
        if ($this->retriever) {
            return $this->getCachedWithRetriever($url);
        }
        return $this->getFromCacheOnly($url);
    }
    public function getCacheSalt(\Kibo\Phast\ValueObjects\URL $url)
    {
        if ($this->retriever) {
            return $this->retriever->getCacheSalt($url);
        }
        return $this->getDynamicCacheSalt($url);
    }
    private function getCachedWithRetriever(\Kibo\Phast\ValueObjects\URL $url)
    {
        return $this->cache->get($this->getCacheKey($url), function () use($url) {
            return $this->retriever->retrieve($url);
        });
    }
    private function getFromCacheOnly(\Kibo\Phast\ValueObjects\URL $url)
    {
        $cached = $this->cache->get($this->getCacheKey($url));
        if (!$cached) {
            return false;
        }
        return $cached;
    }
    private function getCacheKey(\Kibo\Phast\ValueObjects\URL $url)
    {
        return $url . '-' . $this->getCacheSalt($url);
    }
}
namespace Kibo\Phast\Retrievers;

class LocalRetriever implements \Kibo\Phast\Retrievers\Retriever
{
    /**
     * @var array
     */
    private $map;
    /**
     * @var ObjectifiedFunctions
     */
    private $funcs;
    /**
     * LocalRetriever constructor.
     *
     * @param array $map
     * @param ObjectifiedFunctions|null $functions
     */
    public function __construct(array $map, \Kibo\Phast\Common\ObjectifiedFunctions $functions = null)
    {
        $this->map = $map;
        if ($functions) {
            $this->funcs = $functions;
        } else {
            $this->funcs = new \Kibo\Phast\Common\ObjectifiedFunctions();
        }
    }
    public static function getAllowedExtensions()
    {
        return ['css', 'js', 'bmp', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'svg', 'txt'];
    }
    public function retrieve(\Kibo\Phast\ValueObjects\URL $url)
    {
        return $this->guard($url, function ($file) {
            return @$this->funcs->file_get_contents($file);
        });
    }
    public function getCacheSalt(\Kibo\Phast\ValueObjects\URL $url)
    {
        return $this->guard($url, function ($file) {
            $size = @$this->funcs->filesize($file);
            $mtime = @$this->funcs->filectime($file);
            if ($size === false && $mtime === false) {
                return '';
            }
            return "{$mtime}-{$size}";
        });
    }
    public function getSize(\Kibo\Phast\ValueObjects\URL $url)
    {
        return $this->guard($url, function ($file) {
            return @$this->funcs->filesize($file);
        });
    }
    private function guard(\Kibo\Phast\ValueObjects\URL $url, callable $cb)
    {
        if (!in_array($this->getExtensionForURL($url), self::getAllowedExtensions())) {
            return false;
        }
        $file = $this->getFileForURL($url);
        if ($file === false) {
            return false;
        }
        return $cb($file);
    }
    private function getExtensionForURL(\Kibo\Phast\ValueObjects\URL $url)
    {
        $dotPosition = strrpos($url->getDecodedPath(), '.');
        if ($dotPosition === false) {
            return '';
        }
        return strtolower(substr($url->getDecodedPath(), $dotPosition + 1));
    }
    private function getFileForURL(\Kibo\Phast\ValueObjects\URL $url)
    {
        if (!isset($this->map[$url->getHost()])) {
            return false;
        }
        $submap = $this->map[$url->getHost()];
        if (!is_array($submap)) {
            return $this->appendNormalized($submap, $url->getDecodedPath());
        }
        $selectedPath = null;
        $selectedRoot = null;
        foreach ($submap as $prefix => $root) {
            $pattern = '~^(?=/)/*?(?:' . str_replace('~', '\\~', $prefix) . ')(?<path>/*(?<=/).*)~';
            if (preg_match($pattern, $url->getDecodedPath(), $match) && ($selectedPath === null || strlen($match['path']) < strlen($selectedPath))) {
                $selectedRoot = $root;
                $selectedPath = $match['path'];
            }
        }
        if ($selectedPath === null) {
            return false;
        }
        return $this->appendNormalized($selectedRoot, $selectedPath);
    }
    private function appendNormalized($target, $appended)
    {
        $appended = explode("\0", $appended)[0];
        $appended = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $appended);
        $absolutes = [];
        foreach (explode(DIRECTORY_SEPARATOR, $appended) as $part) {
            if ($part == '' || $part == '.') {
            } elseif ($part == '..') {
                if (array_pop($absolutes) === null) {
                    return false;
                }
            } else {
                $absolutes[] = $part;
            }
        }
        return $target . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $absolutes);
    }
}
namespace Kibo\Phast\Retrievers;

class PostDataRetriever implements \Kibo\Phast\Retrievers\Retriever
{
    /**
     * @var ObjectifiedFunctions
     */
    private $funcs;
    private $content;
    /**
     * PostDataRetriever constructor.
     * @param ObjectifiedFunctions $funcs
     */
    public function __construct(\Kibo\Phast\Common\ObjectifiedFunctions $funcs = null)
    {
        $this->funcs = is_null($funcs) ? new \Kibo\Phast\Common\ObjectifiedFunctions() : $funcs;
    }
    public function retrieve(\Kibo\Phast\ValueObjects\URL $url)
    {
        if (!isset($this->content)) {
            $this->content = $this->funcs->file_get_contents('php://input');
        }
        return $this->content;
    }
    public function getCacheSalt(\Kibo\Phast\ValueObjects\URL $url)
    {
        return md5($this->retrieve($url));
    }
}
namespace Kibo\Phast\Retrievers;

class RemoteRetriever implements \Kibo\Phast\Retrievers\Retriever
{
    use \Kibo\Phast\Retrievers\DynamicCacheSaltTrait;
    use \Kibo\Phast\Logging\LoggingTrait;
    private $client;
    public function __construct(\Kibo\Phast\HTTP\Client $client)
    {
        $this->client = $client;
    }
    public function retrieve(\Kibo\Phast\ValueObjects\URL $url)
    {
        $cdnLoop = ['Phast'];
        if (!empty($_SERVER['HTTP_CDN_LOOP'])) {
            $cdnLoop[] = $_SERVER['HTTP_CDN_LOOP'];
        }
        try {
            $response = $this->client->get($url, ['User-Agent' => 'Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:56.0) Gecko/20100101 Firefox/56.0', 'CDN-Loop' => implode(', ', $cdnLoop)]);
        } catch (\Exception $e) {
            $this->logger()->warning('Caught {cls} while fetching {url}: ({code}) {message}', ['cls' => get_class($e), 'url' => (string) $url, 'code' => $e->getCode(), 'message' => $e->getMessage()]);
            return false;
        }
        return $response->getContent();
    }
}
namespace Kibo\PhastPlugins\SDK\AdminPanel;

class DefaultInstallNoticeRenderer implements \Kibo\PhastPlugins\SDK\AdminPanel\InstallNoticeRenderer
{
    public function render($notice, $onCloseJSFunction)
    {
        return $notice;
    }
}
namespace Kibo\PhastPlugins\SDK\Configuration;

/**
 * A default implementation of the ServiceConfigurationRepository interface
 *
 * Class PHPFilesServiceConfigurationRepository
 */
class PHPFilesServiceConfigurationRepository implements \Kibo\PhastPlugins\SDK\Configuration\ServiceConfigurationRepository
{
    /**
     * @var CacheRootManager
     */
    private $cacheRootManager;
    /**
     * PHPFilesServiceConfigurationRepository constructor.
     * @param CacheRootManager $cacheRootManager
     */
    public function __construct(\Kibo\PhastPlugins\SDK\Caching\CacheRootManager $cacheRootManager)
    {
        $this->cacheRootManager = $cacheRootManager;
    }
    public function store(array $config)
    {
        return $this->storeInPHPFile($this->getServiceConfigurationFilePath(), $config) !== false;
    }
    public function get()
    {
        $json = $this->readFromPHPFile($this->getServiceConfigurationFilePath());
        if (!$json) {
            return false;
        }
        if (strpos($json, 'a:') === 0) {
            $config = unserialize($json);
        } else {
            $config = json_decode($json, true);
        }
        if ($config === null) {
            return false;
        }
        return $config;
    }
    public function has()
    {
        return !!$this->get();
    }
    private function getServiceConfigurationFilePath()
    {
        return $this->getCacheStoredFilePath('service-config');
    }
    private function getCacheStoredFilePath($filename)
    {
        $dir = $this->cacheRootManager->getCacheRoot();
        if (!$dir) {
            return false;
        }
        $legacyName = "{$dir}/{$filename}.php";
        if (@file_exists($legacyName)) {
            return $legacyName;
        }
        foreach (@scandir($dir) as $file) {
            if (!preg_match('~^' . preg_quote($filename, '~') . '-[a-zA-Z0-9]{16}$~', $file)) {
                continue;
            }
            $path = "{$dir}/{$file}";
            if (@is_file($path)) {
                return $path;
            }
        }
        return "{$dir}/service-config-{$this->generateRandomName()}";
    }
    private function generateRandomName()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $o = '';
        for ($i = 0; $i < 16; $i++) {
            $o .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $o;
    }
    private function readFromPHPFile($filename)
    {
        $content = @file_get_contents($filename);
        if (!$content) {
            return false;
        }
        if (!preg_match('/^[^>]*>\\n([a-f0-9]{40})\\n(.*)$/s', $content, $match)) {
            return false;
        }
        if (sha1($match[2]) != $match[1]) {
            return false;
        }
        return $match[2];
    }
    private function storeInPHPFile($filename, $value)
    {
        $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        $content = "<?php exit; ?>\n" . sha1($value) . "\n" . $value;
        return @file_put_contents($filename, $content, LOCK_EX);
    }
}
namespace Kibo\PhastPlugins\SDK;

interface PluginHost extends \Kibo\PhastPlugins\SDK\ServiceHost
{
    /**
     * The name of the plugin used for displaying to the users
     *
     * @return string
     */
    public function getPluginName();
    /**
     * The name of the host system
     *
     * @return string
     */
    public function getPluginHostName();
    /**
     * The version of the plugin
     *
     * @return string
     */
    public function getPluginHostVersion();
    /**
     * Tells whether we are in production or development mode.
     * In development mode static files will be loaded from a dev server.
     * In production mode static files will be loaded from a prebuilt source.
     *
     * @return bool - TRUE for development, FALSE for production
     */
    public function isDev();
    /**
     * @return KeyValueStore
     */
    public function getKeyValueStore();
    /**
     * @return InstallNoticeRenderer
     */
    public function getInstallNoticeRenderer();
    /**
     * @return HostURLs
     */
    public function getHostURLs();
    /**
     * @return Nonce
     */
    public function getNonce();
    /**
     * @return NonceChecker
     */
    public function getNonceChecker();
    /**
     * @return PhastUser
     */
    public function getPhastUser();
    /**
     * Called right after phast's configuration
     * has been loaded. Use it to modify the config
     * and take any other needed action before
     * the filters are applied.
     *
     * @param array $config - The configuration that has been loaded
     * @return array - The configuration to use for phast
     */
    public function onPhastConfigurationLoad(array $config);
    /**
     * Returns the current system locale
     *
     * @return string
     */
    public function getLocale();
    /**
     * @return ?string
     */
    public function getSecurityTokenRoot();
}
namespace Kibo\PhastPlugins\SDK;

/**
 * Services container for the Phast Plugins SDK
 *
 * Class SDK
 */
class SDK extends \Kibo\PhastPlugins\SDK\ServiceSDK
{
    /**
     * @var PluginHost
     */
    protected $host;
    /**
     * SDK constructor.
     * @param PluginHost $host
     */
    public function __construct(\Kibo\PhastPlugins\SDK\PluginHost $host)
    {
        parent::__construct($host);
    }
    /**
     * The current SDK version
     *
     * @return string
     */
    public function getSDKVersion()
    {
        return '8';
    }
    /**
     * The current plugin version.
     * Composed from the host plugin name,
     * the host plugin version
     * and the SDK version
     *
     * @return string
     */
    public function getPluginVersion()
    {
        return join('-', [$this->host->getPluginHostName(), $this->host->getPluginHostVersion(), $this->getSDKVersion()]);
    }
    /**
     * @return Phast
     */
    public function getPhastAPI()
    {
        return new \Kibo\PhastPlugins\SDK\APIs\Phast($this->getPhastConfiguration());
    }
    public function getAdminPanel()
    {
        return new \Kibo\PhastPlugins\SDK\AdminPanel\AdminPanel($this->host->getPhastUser(), $this->host->getHostURLs()->getAJAXEndPoint(), $this->getAdminPanelData(), $this->getTranslationsManager(), $this->host->isDev());
    }
    public function getAJAXRequestsDispatcher()
    {
        return new \Kibo\PhastPlugins\SDK\AJAX\RequestsDispatcher($this->host->getPhastUser(), $this);
    }
    public function getAdminPanelData()
    {
        return new \Kibo\PhastPlugins\SDK\AdminPanel\AdminPanelData($this->getPluginConfiguration(), $this->getServiceConfigurationGenerator(), $this->getPhastConfiguration(), $this->getCacheRootManager(), $this->host);
    }
    public function getInstallNotice()
    {
        return new \Kibo\PhastPlugins\SDK\AdminPanel\InstallNotice($this->getPluginConfiguration(), $this->host->getInstallNoticeRenderer(), $this->getTranslationsManager(), $this->host->getHostURLs()->getSettingsURL(), $this->host->getHostURLs()->getAJAXEndPoint());
    }
    public function getPluginConfiguration()
    {
        return new \Kibo\PhastPlugins\SDK\Configuration\PluginConfiguration($this->getPluginConfigurationRepository(), $this->getServiceConfigurationGenerator(), $this->getCacheRootManager(), $this->host->getPhastUser(), $this->host->getNonceChecker());
    }
    public function getPhastConfiguration()
    {
        return new \Kibo\PhastPlugins\SDK\Configuration\PhastConfiguration($this->getServiceConfigurationGenerator(), $this->getServiceConfiguration(), $this->getPluginConfiguration(), [$this->host, 'onPhastConfigurationLoad']);
    }
    public function getAutoConfiguration()
    {
        return new \Kibo\PhastPlugins\SDK\Configuration\AutoConfiguration($this->getPluginConfiguration(), $this->getPhastConfiguration(), $this->host->getHostURLs()->getServicesURL(), $this->host->getHostURLs()->getTestImageURL(), $this->host->getNonce(), $this->host->getHostURLs()->getAJAXEndPoint());
    }
    public function getTranslationsManager()
    {
        return new \Kibo\PhastPlugins\SDK\AdminPanel\TranslationsManager($this->host->getLocale(), $this->host->getPluginName());
    }
    private function getServiceConfigurationGenerator()
    {
        return new \Kibo\PhastPlugins\SDK\Configuration\ServiceConfigurationGenerator($this->getPHPFilesServiceConfigurationRepository(), $this->getEnvironmentIdentifier(), $this->getPluginVersion(), $this->host);
    }
    public function updatePreviewCookie($enable = true)
    {
        if (headers_sent()) {
            return false;
        }
        $enabled = isset($_COOKIE['PHAST_PREVIEW']) && (bool) $_COOKIE['PHAST_PREVIEW'];
        if (!$enabled && $enable) {
            return setcookie('PHAST_PREVIEW', '1', 0, '/');
        }
        if ($enabled && !$enable) {
            return setcookie('PHAST_PREVIEW', '0', 0, '/');
        }
        return true;
    }
    private function getPluginConfigurationRepository()
    {
        return new \Kibo\PhastPlugins\SDK\Configuration\PluginConfigurationRepository($this->host->getKeyValueStore());
    }
}
namespace Kibo\Phast\Filters\CSS\Composite;

class Filter extends \Kibo\Phast\Filters\Service\CompositeFilter implements \Kibo\Phast\Filters\Service\CachedResultServiceFilter
{
    public function __construct()
    {
        $this->addFilter(new \Kibo\Phast\Filters\CSS\CommentsRemoval\Filter());
    }
}
namespace Kibo\Phast\Filters\CSS\ImageURLRewriter;

class Filter implements \Kibo\Phast\Filters\Service\CachedResultServiceFilter
{
    /**
     * @var ImageURLRewriter
     */
    private $rewriter;
    /**
     * Filter constructor.
     * @param ImageURLRewriter $rewriter
     */
    public function __construct(\Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageURLRewriter $rewriter)
    {
        $this->rewriter = $rewriter;
    }
    public function getCacheSalt(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        return $this->rewriter->getCacheSalt();
    }
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $content = $this->rewriter->rewriteStyle($resource->getContent());
        $dependencies = $this->rewriter->getInlinedResources();
        return $resource->withContent($content)->withDependencies($dependencies);
    }
}
namespace Kibo\Phast\Filters\CSS\ImportsStripper;

class Filter implements \Kibo\Phast\Filters\Service\CachedResultServiceFilter
{
    use \Kibo\Phast\Logging\LoggingTrait;
    public function getCacheSalt(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        return $this->shouldStripImports($request) ? 'strip-imports' : 'no-strip-imports';
    }
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        if (!$this->shouldStripImports($request)) {
            $this->logger()->info('No import stripping requested! Skipping!');
            return $resource;
        }
        $css = $resource->getContent();
        $stripped = preg_replace(\Kibo\Phast\Filters\HTML\CSSInlining\Filter::CSS_IMPORTS_REGEXP, '', $css);
        return $resource->withContent($stripped);
    }
    private function shouldStripImports(array $request)
    {
        return isset($request['strip-imports']);
    }
}
namespace Kibo\Phast\Filters\Image\Composite;

class Filter implements \Kibo\Phast\Filters\Service\CachedResultServiceFilter
{
    use \Kibo\Phast\Logging\LoggingTrait;
    /**
     * @var ImageFactory
     */
    private $imageFactory;
    /**
     * @var ImageInliningManager
     */
    private $inliningManager;
    /**
     * @var ImageFilter[]
     */
    private $filters = [];
    /**
     * Filter constructor.
     * @param ImageFactory $imageFactory
     * @param ImageInliningManager $inliningManager
     */
    public function __construct(\Kibo\Phast\Filters\Image\ImageFactory $imageFactory, \Kibo\Phast\Filters\HTML\ImagesOptimizationService\ImageInliningManager $inliningManager)
    {
        $this->imageFactory = $imageFactory;
        $this->inliningManager = $inliningManager;
    }
    public function addImageFilter(\Kibo\Phast\Filters\Image\ImageFilter $filter)
    {
        $this->filters[] = $filter;
    }
    public function getCacheSalt(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $filters = array_map('get_class', $this->filters);
        $salts = array_map(function (\Kibo\Phast\Filters\Image\ImageFilter $filter) use($request) {
            return $filter->getCacheSalt($request);
        }, $this->filters);
        return implode("\n", array_merge($filters, $salts, [$this->inliningManager->getMaxImageInliningSize(), $resource->getUrl(), $resource->getCacheSalt()]));
    }
    /**
     * @param Resource $resource
     * @param array $request
     * @return Resource
     */
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $image = $this->imageFactory->getForResource($resource);
        $filteredImage = $image;
        foreach ($this->filters as $filter) {
            $this->logger()->info('Applying {filter}', ['filter' => get_class($filter)]);
            try {
                $filteredImage = $filter->transformImage($filteredImage, $request);
            } catch (\Kibo\Phast\Filters\Image\Exceptions\ImageProcessingException $e) {
                $message = 'Image filter exception: Filter: {filter} Exception: {exceptionClass} Msg: {message} Code: {code} File: {file} Line: {line}';
                $this->logger()->critical($message, ['filter' => get_class($filter), 'exceptionClass' => get_class($e), 'message' => $e->getMessage(), 'code' => $e->getCode(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            }
        }
        $sizeBefore = $filteredImage->getSizeAsString();
        $sizeAfter = $image->getSizeAsString();
        $sizeDifference = $sizeBefore - $sizeAfter;
        $this->logger()->info('Image processed. Size before/after: {sizeBefore}/{sizeAfter} ({sizeDifference})', ['sizeBefore' => $sizeBefore, 'sizeAfter' => $sizeAfter, 'sizeDifference' => $sizeDifference < 0 ? $sizeDifference : "+{$sizeDifference}"]);
        if ($sizeDifference < 0) {
            $this->logger()->info('Return filtered image and save {sizeDifference} bytes', ['sizeDifference' => -$sizeDifference]);
            $image = $filteredImage;
        } else {
            $this->logger()->info('Return original image');
        }
        $processedResource = $resource->withContent($image->getAsString(), $image->getType());
        $this->inliningManager->maybeStoreForInlining($processedResource);
        return $processedResource;
    }
}
namespace Kibo\Phast\Filters\JavaScript\Minification;

class JSMinifierFilter implements \Kibo\Phast\Filters\Service\CachedResultServiceFilter
{
    const VERSION = 3;
    private $removeLicenseHeaders = true;
    /**
     * JSMinifierFilter constructor.
     * @param bool $removeLicenseHeaders
     */
    public function __construct($removeLicenseHeaders)
    {
        $this->removeLicenseHeaders = (bool) $removeLicenseHeaders;
    }
    public function getCacheSalt(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        return http_build_query(['v' => self::VERSION, 'removeLicenseHeaders' => $this->removeLicenseHeaders]);
    }
    public function apply(\Kibo\Phast\ValueObjects\Resource $resource, array $request)
    {
        $minified = (new \Kibo\Phast\Common\JSMinifier($resource->getContent(), $this->removeLicenseHeaders))->min();
        return $resource->withContent($minified);
    }
}