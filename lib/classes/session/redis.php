<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Redis based session handler.
 *
 * @package    core
 * @copyright  2015 Russell Smith <mr-russ@smith2001.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\session;

use RedisException;
use SessionHandlerInterface;

/**
 * Redis based session handler.
 *
 * The default Redis session handler does not handle locking in 2.2.7, so we have written a php session handler
 * that uses locking.  The places where locking is used was modeled from the memcached code that is used in Moodle
 * https://github.com/php-memcached-dev/php-memcached/blob/master/php_memcached_session.c
 *
 * @package    core
 * @copyright  2016 Russell Smith
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class redis extends handler implements SessionHandlerInterface {
    use \core\session\base_session_store;

    /**
     * Compressor: none.
     */
    const COMPRESSION_NONE      = 'none';
    /**
     * Compressor: PHP GZip.
     */
    const COMPRESSION_GZIP      = 'gzip';
    /**
     * Compressor: PHP Zstandard.
     */
    const COMPRESSION_ZSTD      = 'zstd';

    /** @var string $host save_path string  */
    protected $host = '';
    /** @var int $port The port to connect to */
    protected $port = 6379;
    /** @var array $sslopts SSL options, if applicable */
    protected $sslopts = [];
    /** @var string $auth redis password  */
    protected $auth = '';
    /** @var int $database the Redis database to store sesions in */
    protected $database = 0;
    /** @var array $servers list of servers parsed from save_path */
    protected $prefix = '';

    /** @var string $sessionkeyprefix the prefix for the session key */
    protected $sessionkeyprefix = 'session_';

    /** @var string $userkeyprefix the prefix for the user key */
    protected $userkeyprefix = 'user_';

    /** @var int $acquiretimeout how long to wait for session lock in seconds */
    protected $acquiretimeout = 120;
    /** @var int $acquirewarn how long before warning when waiting for a lock in seconds */
    protected $acquirewarn = null;
    /** @var int $lockretry how long to wait between session lock attempts in ms */
    protected $lockretry = 100;
    /** @var int $serializer The serializer to use */
    protected $serializer = \Redis::SERIALIZER_PHP;
    /** @var int $compressor The compressor to use */
    protected $compressor = self::COMPRESSION_NONE;
    /** @var string $lasthash hash of the session data content */
    protected $lasthash = null;

    /** @var int $gcbatchsize The number of redis keys that will be processed each time the garbage collector is executed. */
    protected $gcbatchsize = 100;

    /**
     * @var int $lockexpire how long to wait in seconds before expiring the lock automatically
     * so that other requests may continue execution, ignored if PECL redis is below version 2.2.0.
     */
    protected $lockexpire;

    /** @var Redis Connection */
    protected $connection = null;

    /** @var array $locks List of currently held locks by this page. */
    protected $locks = array();

    /** @var int $timeout How long sessions live before expiring. */
    protected $timeout;

    /** @var int $firstaccesstimeout The initial timeout (seconds) for the first browser access without login. */
    protected $firstaccesstimeout = 180;

    /**
     * Create new instance of handler.
     */
    public function __construct() {
        global $CFG;

        if (isset($CFG->session_redis_host)) {
            $this->host = $CFG->session_redis_host;
        }

        if (isset($CFG->session_redis_port)) {
            $this->port = (int)$CFG->session_redis_port;
        }

        if (isset($CFG->session_redis_encrypt) && $CFG->session_redis_encrypt) {
            $this->host = 'tls://' . $this->host;
            $this->sslopts = $CFG->session_redis_encrypt;
        }

        if (isset($CFG->session_redis_auth)) {
            $this->auth = $CFG->session_redis_auth;
        }

        if (isset($CFG->session_redis_database)) {
            $this->database = (int)$CFG->session_redis_database;
        }

        if (isset($CFG->session_redis_prefix)) {
            $this->prefix = $CFG->session_redis_prefix;
        }

        if (isset($CFG->session_redis_acquire_lock_timeout)) {
            $this->acquiretimeout = (int)$CFG->session_redis_acquire_lock_timeout;
        }

        if (isset($CFG->session_redis_acquire_lock_warn)) {
            $this->acquirewarn = (int)$CFG->session_redis_acquire_lock_warn;
        }

        if (isset($CFG->session_redis_acquire_lock_retry)) {
            $this->lockretry = (int)$CFG->session_redis_acquire_lock_retry;
        }

        if (!empty($CFG->session_redis_serializer_use_igbinary) && defined('\Redis::SERIALIZER_IGBINARY')) {
            $this->serializer = \Redis::SERIALIZER_IGBINARY; // Set igbinary serializer if phpredis supports it.
        }

        // The following configures the session lifetime in redis to allow some
        // wriggle room in the user noticing they've been booted off and
        // letting them log back in before they lose their session entirely.
        $updatefreq = empty($CFG->session_update_timemodified_frequency) ? 20 : $CFG->session_update_timemodified_frequency;
        $this->timeout = $CFG->sessiontimeout + $updatefreq + MINSECS;

        // This sets the Redis session lock expiry time to whatever is lower, either
        // the PHP execution time `max_execution_time`, if the value was defined in
        // the `php.ini` or the globally configured `sessiontimeout`. Setting it to
        // the lower of the two will not make things worse it if the execution timeout
        // is longer than the session timeout.
        // For the PHP execution time, once the PHP execution time is over, we can be sure
        // that the lock is no longer actively held so that the lock can expire safely.
        // Although at `lib/classes/php_time_limit.php::raise(int)`, Moodle can
        // progressively increase the maximum PHP execution time, this is limited to the
        // `max_execution_time` value defined in the `php.ini`.
        // For the session timeout, we assume it is safe to consider the lock to expire
        // once the session itself expires.
        // If we unnecessarily hold the lock any longer, it blocks other session requests.
        $this->lockexpire = ini_get('max_execution_time');
        if (empty($this->lockexpire) || ($this->lockexpire > (int)$CFG->sessiontimeout)) {
            $this->lockexpire = (int)$CFG->sessiontimeout;
        }
        if (isset($CFG->session_redis_lock_expire)) {
            $this->lockexpire = (int)$CFG->session_redis_lock_expire;
        }

        if (isset($CFG->session_redis_compressor)) {
            $this->compressor = $CFG->session_redis_compressor;
        }
    }

    /**
     * Start the session.
     *
     * @return bool success
     */
    public function start(): bool {
        $result = parent::start();

        return $result;
    }

    /**
     * Init session handler.
     *
     * @return bool
     * @throws RedisException
     * @throws \dml_exception
     * @throws exception
     */
    public function init(): bool {
        if (!extension_loaded('redis')) {
            throw new exception('sessionhandlerproblem', 'error', '', null, 'redis extension is not loaded');
        }

        if (empty($this->host)) {
            throw new exception('sessionhandlerproblem', 'error', '', null,
                    '$CFG->session_redis_host must be specified in config.php');
        }

        // The session handler requires a version of Redis with the SETEX command (at least 2.0).
        $version = phpversion('Redis');
        if (!$version or version_compare($version, '2.0') <= 0) {
            throw new exception('sessionhandlerproblem', 'error', '', null, 'redis extension version must be at least 2.0');
        }

        $this->connection = new \Redis();

        $this->firstaccesstimeout = (int) \get_config('local_redissession', 'firstaccesstimeout');
        $this->gcbatchsize = (int) \get_config('local_redissession', 'gcbatchsize');

        $result = session_set_save_handler($this);

        if (!$result) {
            throw new exception('redissessionhandlerproblem', 'error');
        }

        // MDL-59866: Add retries for connections (up to 5 times) to make sure it goes through.
        $counter = 1;
        $maxnumberofretries = 5;
        $opts = [];
        if ($this->sslopts) {
            // Do not set $opts['stream'] = [], breaks connect().
            $opts['stream'] = $this->sslopts;
        }

        while ($counter <= $maxnumberofretries) {

            try {

                $delay = rand(100, 500);

                // One second timeout was chosen as it is long for connection, but short enough for a user to be patient.
                if (!$this->connection->connect($this->host, $this->port, 1, null, $delay, 1, $opts)) {
                    throw new RedisException('Unable to connect to host.');
                }

                if ($this->auth !== '') {
                    if (!$this->connection->auth($this->auth)) {
                        throw new RedisException('Unable to authenticate.');
                    }
                }

                if (!$this->connection->setOption(\Redis::OPT_SERIALIZER, $this->serializer)) {
                    throw new RedisException('Unable to set Redis PHP Serializer option.');
                }

                if ($this->prefix !== '') {
                    // Use custom prefix on sessions.
                    if (!$this->connection->setOption(\Redis::OPT_PREFIX, $this->prefix)) {
                        throw new RedisException('Unable to set Redis Prefix option.');
                    }
                }

                if ($this->sslopts && !$this->connection->ping()) {
                    /*
                     * In case of a TLS connection, if phpredis client does not
                     * communicate immediately with the server the connection hangs.
                     * See https://github.com/phpredis/phpredis/issues/2332 .
                     */
                    throw new \RedisException("Ping failed");
                }

                if ($this->database !== 0) {
                    if (!$this->connection->select($this->database)) {
                        throw new RedisException('Unable to select Redis database '.$this->database.'.');
                    }
                }
                return true;
            } catch (RedisException $e) {
                $logstring = "Failed to connect (try {$counter} out of {$maxnumberofretries}) to redis ";
                $logstring .= "at {$this->host}:{$this->port}, error returned was: {$e->getMessage()}";

                debugging($logstring);
            }

            $counter++;

            // Introduce a random sleep between 100ms and 500ms.
            usleep(rand(100000, 500000));
        }

        // We have exhausted our retries, time to give up.
        if (isset($logstring)) {
            throw new RedisException($logstring);
        }

        return false;
    }

    /**
     * Update our session search path to include session name when opened.
     *
     * @param string $path  unused session save path. (ignored)
     * @param string $name Session name for this session. (ignored)
     * @return bool true always as we will succeed.
     */
    public function open(string $path, string $name): bool {
        return true;
    }

    /**
     * Close the session completely. We also remove all locks we may have obtained that aren't expired.
     *
     * @return bool true on success.  false on unable to unlock sessions.
     */
    public function close(): bool {
        $this->lasthash = null;
        try {
            foreach ($this->locks as $id => $expirytime) {
                if ($expirytime > $this->time()) {
                    $this->unlock_session($id);
                }
                unset($this->locks[$id]);
            }
        } catch (RedisException $e) {
            error_log('Failed talking to redis: '.$e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Read the session data from storage
     *
     * @param string $id The session id to read from storage.
     * @return string|false The session data for PHP to process or false.
     *
     * @throws RedisException when we are unable to talk to the Redis server.
     */
    public function read(string $id): string|false {
        try {
            if ($this->requires_write_lock()) {
                $this->lock_session($this->sessionkeyprefix . $id);
            }
            $sessiondata = $this->uncompress($this->connection->hget($this->sessionkeyprefix . $id, 'sessdata'));

            if ($sessiondata === false) {
                if ($this->requires_write_lock()) {
                    $this->unlock_session($this->sessionkeyprefix . $id);
                }
                $this->lasthash = sha1('');
                return '';
            }

            // Do not update expiry if non-login user (0). This would affect the first access timeout.
            $userid = $this->connection->hget($this->sessionkeyprefix . $id, 'userid');
            if ($userid != 0) {
                $maxlifetime = $this->get_maxlifetime($userid);
                $this->connection->expire($this->sessionkeyprefix . $id, $maxlifetime);
                // TODO: update user hash expiry.
            }
        } catch (RedisException $e) {
            error_log('Failed talking to redis: '.$e->getMessage());
            throw $e;
        }
        $this->lasthash = sha1(base64_encode($sessiondata));
        return $sessiondata;
    }

    /**
     * Compresses session data.
     *
     * @param mixed $value
     * @return string
     */
    private function compress($value): string {
        switch ($this->compressor) {
            case self::COMPRESSION_NONE:
                return $value;
            case self::COMPRESSION_GZIP:
                return gzencode($value);
            case self::COMPRESSION_ZSTD:
                return zstd_compress($value);
            default:
                debugging("Invalid compressor: {$this->compressor}");
                return $value;
        }
    }

    /**
     * Uncompresses session data.
     *
     * @param string $value
     * @return mixed
     */
    private function uncompress($value) {
        if ($value === false) {
            return false;
        }

        switch ($this->compressor) {
            case self::COMPRESSION_NONE:
                break;
            case self::COMPRESSION_GZIP:
                $value = gzdecode($value);
                break;
            case self::COMPRESSION_ZSTD:
                $value = zstd_uncompress($value);
                break;
            default:
                debugging("Invalid compressor: {$this->compressor}");
        }

        return $value;
    }

    /**
     * Write the serialized session data to our session store.
     *
     * @param string $id session id to write.
     * @param string $data session data
     * @return bool true on write success, false on failure
     */
    public function write(string $id, string $data): bool {

        $hash = sha1(base64_encode($data));

        // If the content has not changed don't bother writing.
        if ($hash === $this->lasthash) {
            return true;
        }

        if (is_null($this->connection)) {
            // The session has already been closed, don't attempt another write.
            error_log('Tried to write session: '.$id.' before open or after close.');
            return false;
        }

        // We do not do locking here because memcached doesn't.  Also
        // PHP does open, read, destroy, write, close. When a session doesn't exist.
        // There can be race conditions on new sessions racing each other but we can
        // address that in the future.
        try {
            $data = $this->compress($data);
            $this->connection->hset($this->sessionkeyprefix . $id, 'sessdata', $data);
            $userid = $this->connection->hget($this->sessionkeyprefix . $id, 'userid');
            $timecreated = $this->connection->hget($this->sessionkeyprefix . $id, 'timecreated');
            $timemodified = $this->connection->hget($this->sessionkeyprefix . $id, 'timemodified');
            // Don't update expiry if still first access.
            if ($timecreated != $timemodified) {
                $maxlifetime = $this->get_maxlifetime($userid);
                $this->connection->expire($this->sessionkeyprefix . $id, $maxlifetime);
                // TODO: update user hash expiry.
            }
        } catch (RedisException $e) {
            error_log('Failed talking to redis: '.$e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * Returns a single session record for this session id.
     *
     * @param string $sid
     * @return \stdClass
     */
    public function get_session_by_sid(string $sid): \stdClass {
        $keys = ["id", "state", "sid", "userid", "sessdata", "timecreated", "timemodified", "firstip", "lastip"];
        $sessiondata = [];
        foreach ($keys as $key) {
            $sessiondata[$key] = $this->connection->hGet($this->sessionkeyprefix . $sid, $key);
        }
        return (object)$sessiondata;
    }

    /**
     * Insert new empty session record.
     *
     * @param int $userid
     * @return \stdClass the new record
     */
    public function add_session(int $userid): \stdClass {
        $timestamp = time();
        $sid = session_id();
        $maxlifetime = $this->get_maxlifetime($userid, true);
        $sessiondata = [
                'id' => $sid,
                'state' => '0',
                'sid' => $sid,
                'userid' => $userid,
                'sessdata' => null,
                'timecreated' => $timestamp,
                'timemodified' => $timestamp,
                'firstip' => getremoteaddr(),
                'lastip' => getremoteaddr(),
        ];

        $userhashkey = $this->userkeyprefix . $userid;
        $this->connection->hSet($userhashkey, $sid, $timestamp);
        $this->connection->expire($userhashkey, $maxlifetime);

        $sessionhashkey = $this->sessionkeyprefix . $sid;
        $this->connection->hmSet($sessionhashkey, $sessiondata);
        $this->connection->expire($sessionhashkey, $maxlifetime);

        return (object)$sessiondata;
    }

    /**
     * Returns all the session records for this user id.
     *
     * @param int $userid
     * @return array
     */
    public function get_sessions_by_userid(int $userid): array {
        $this->init_redis_if_required();

        $userhashkey = $this->userkeyprefix . $userid;
        $sessions = $this->connection->hGetAll($userhashkey);
        $records = [];
        foreach (array_keys($sessions) as $session) {
            $item = $this->connection->hGetAll($this->sessionkeyprefix . $session);
            if (!empty($item)) {
                $records[] = (object) $item;
            }
        }
        return $records;
    }

    /**
     * Update a session record.
     *
     * @param \stdClass $record
     * @return bool
     */
    public function update_session(\stdClass $record): bool {
        if (!isset($record->sid) && isset($record->id)) {
            $record->sid = $record->id;
        }
        $sessionhashkey = $this->sessionkeyprefix . $record->sid;

        foreach ($record as $key => $value) {
            if ($key == 'sid') {
                continue;
            }

            $this->connection->hSet($sessionhashkey, $key, $value);
        }

        // TODO: Update timemodified for user hash.

        return true;
    }


    /**
     * Returns all session records.
     * This includes user and session prefixed records.
     * The session id will also have any prefixes applied because of the way scan works.
     *
     * @return \Iterator
     */
    public function get_all_sessions(): \Iterator {
        $sessions = [];
        $iterator = null;
        while (false !== ($keys = $this->connection->scan($iterator, '*' . $this->sessionkeyprefix . '*'))) {
            foreach ($keys as $key) {
                $sessions[] = $key;
            }
        }
        return new \ArrayIterator($sessions);
    }

    /**
     * Destroy all sessions, and delete all the session data.
     * This includes user and session prefixed records.
     *
     * @return bool
     */
    public function destroy_all(): bool {
        $this->init_redis_if_required();

        $sessions = $this->get_all_sessions();
        foreach ($sessions as $session) {
            // Remove the prefixes from the session id, as destroy expects the raw session id.
            if (str_starts_with($session, $this->prefix . $this->sessionkeyprefix)) {
                $session = substr($session, strlen($this->prefix . $this->sessionkeyprefix));
            }

            $this->destroy($session);
        }
        return true;
    }

    /**
     * Handle destroying a session.
     *
     * @param string $id the session id to destroy.
     * @return bool true if the session was deleted, false otherwise.
     */
    public function destroy(string $id): bool {
        $this->init_redis_if_required();
        $this->lasthash = null;
        try {
            $sessionhashkey = $this->sessionkeyprefix . $id;
            $userid = $this->connection->hget($sessionhashkey, "userid");
            $userhashkey = $this->userkeyprefix . $userid;
            $this->connection->hDel($userhashkey, $id);
            $this->connection->zRem("sessionmap", $userhashkey . "_" . $sessionhashkey);
            $this->connection->unlink($sessionhashkey);
            $this->unlock_session($id);
        } catch (RedisException $e) {
            error_log('Failed talking to redis: '.$e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Garbage collect sessions.
     * Not required for Redis as all hashes have an expiry time.
     *
     * @param integer $max_lifetime All sessions older than this should be removed.
     * @return 0 Redis handles expiry for us.
     */
    // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
    public function gc(int $max_lifetime): int|false {
        return 0;
    }

    /**
     * @param $userid
     * @param $firstbrowseraccess This indicates that this is calculating the expiry when the key is first added.
     *                            The first access made by the browser has a shorter timeout to reduce abandoned sessions.
     * @return float|int|string
     */
    private function get_maxlifetime($userid = null, $firstbrowseraccess = false) {
        global $CFG;

        // Guest user.
        if ($userid == $CFG->siteguest) {
            return $CFG->sessiontimeout * 5;
        }

        // All other users.
        if ($userid == 0 && $firstbrowseraccess) {
            $maxlifetime = $this->firstaccesstimeout;
        } else {
            // As per MDL-56823 - The following configures the session lifetime in redis to allow some
            // wriggle room in the user noticing they've been booted off and
            // letting them log back in before they lose their session entirely.
            $updatefreq = empty($CFG->session_update_timemodified_frequency) ? 20 : $CFG->session_update_timemodified_frequency;
            $maxlifetime = (int) $CFG->sessiontimeout + $updatefreq + MINSECS;
        }

        return $maxlifetime;
    }

    /**
     * Connection will be null if these methods are called from cli or where NO_MOODLE_COOKIES is used.
     * We need to check for this and initialize the connection if required.
     *
     * @return void
     */
    private function init_redis_if_required(): void {

        if (is_null($this->connection)) {
            $this->init();
        }
    }

    /**
     * Unlock a session.
     *
     * @param string $id Session id to be unlocked.
     */
    protected function unlock_session($id) {
        if (isset($this->locks[$id])) {
            $this->connection->del($id.".lock");
            unset($this->locks[$id]);
        }
    }

    /**
     * Obtain a session lock so we are the only one using it at the moment.
     *
     * @param string $id The session id to lock.
     * @return bool true when session was locked, exception otherwise.
     * @throws exception When we are unable to obtain a session lock.
     */
    protected function lock_session($id) {
        $lockkey = $id.".lock";

        $haslock = isset($this->locks[$id]) && $this->time() < $this->locks[$id];
        $startlocktime = $this->time();

        /* To be able to ensure sessions don't write out of order we must obtain an exclusive lock
         * on the session for the entire time it is open.  If another AJAX call, or page is using
         * the session then we just wait until it finishes before we can open the session.
         */

        // Store the current host, process id and the request URI so it's easy to track who has the lock.
        $hostname = gethostname();
        if ($hostname === false) {
            $hostname = 'UNKNOWN HOST';
        }
        $pid = getmypid();
        if ($pid === false) {
            $pid = 'UNKNOWN';
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown uri';

        $whoami = "[pid {$pid}] {$hostname}:$uri";

        $haswarned = false; // Have we logged a lock warning?

        while (!$haslock) {

            $haslock = $this->connection->setnx($lockkey, $whoami);

            if ($haslock) {
                $this->locks[$id] = $this->time() + $this->lockexpire;
                $this->connection->expire($lockkey, $this->lockexpire);
                return true;
            }

            if (!empty($this->acquirewarn) && !$haswarned && $this->time() > $startlocktime + $this->acquirewarn) {
                // This is a warning to better inform users.
                $whohaslock = $this->connection->get($lockkey);
                // phpcs:ignore
                error_log("Warning: Cannot obtain session lock for sid: $id within $this->acquirewarn seconds but will keep trying. " .
                    "It is likely another page ($whohaslock) has a long session lock, or the session lock was never released.");
                $haswarned = true;
            }

            if ($this->time() > $startlocktime + $this->acquiretimeout) {
                // This is a fatal error, better inform users.
                // It should not happen very often - all pages that need long time to execute
                // should close session immediately after access control checks.
                $whohaslock = $this->connection->get($lockkey);
                // phpcs:ignore
                error_log("Error: Cannot obtain session lock for sid: $id within $this->acquiretimeout seconds. " .
                    "It is likely another page ($whohaslock) has a long session lock, or the session lock was never released.");
                $acquiretimeout = format_time($this->acquiretimeout);
                $lockexpire = format_time($this->lockexpire);
                $a = (object)[
                    'id' => substr($id, 0, 10),
                    'acquiretimeout' => $acquiretimeout,
                    'whohaslock' => $whohaslock,
                    'lockexpire' => $lockexpire];
                throw new exception("sessioncannotobtainlock", 'error', '', $a);
            }

            if ($this->time() < $startlocktime + 5) {
                // We want a random delay to stagger the polling load. Ideally
                // this delay should be a fraction of the average response
                // time. If it is too small we will poll too much and if it is
                // too large we will waste time waiting for no reason. 100ms is
                // the default starting point.
                $delay = rand($this->lockretry, (int)($this->lockretry * 1.1));
            } else {
                // If we don't get a lock within 5 seconds then there must be a
                // very long lived process holding the lock so throttle back to
                // just polling roughly once a second.
                $delay = rand(1000, 1100);
            }

            usleep($delay * 1000);
        }
    }

    /**
     * Return the current time.
     *
     * @return int the current time as a unixtimestamp.
     */
    protected function time() {
        return time();
    }

    /**
     * Check the backend contains data for this session id.
     *
     * Note: this is intended to be called from manager::session_exists() only.
     *
     * @param string $sid
     * @return bool true if session found.
     */
    public function session_exists($sid) {
        if (!$this->connection) {
            return false;
        }

        try {
            $sessionhashkey = $this->sessionkeyprefix . $sid;
            return !empty($this->connection->exists($sessionhashkey));
        } catch (RedisException $e) {
            return false;
        }
    }
}
