<?php
class PluginHost {
	// TODO: class properties can be switched to PHP typing if/when the minimum PHP_VERSION is raised to 7.4.0+
	/** @var PDO|null */
	private $pdo = null;

	/**
	 * separate handle for plugin data so transaction while saving wouldn't clash with possible main
	 * tt-rss code transactions; only initialized when first needed
	 *
	 * @var PDO|null
	 */
	private $pdo_data = null;

	/** @var array<string, array<int, array<int, Plugin>>> hook types -> priority levels -> Plugins */
	private $hooks = [];

	/** @var array<string, Plugin> */
	private $plugins = [];

	/** @var array<string, array<string, Plugin>> handler type -> method type -> Plugin */
	private $handlers = [];

	/** @var array<string, array{'description': string, 'suffix': string, 'arghelp': string, 'class': Plugin}> command type -> details array */
	private $commands = [];

	/** @var array<string, array<string, mixed>> plugin name -> (potential profile array) -> key -> value  */
	private $storage = [];

	/** @var array<int, array<int, array{'id': int, 'title': string, 'sender': Plugin, 'icon': string}>> */
	private $feeds = [];

	/** @var array<string, Plugin> API method name, Plugin sender */
	private $api_methods = [];

	/** @var array<string, array<int, array{'action': string, 'description': string, 'sender': Plugin}>> */
	private $plugin_actions = [];

	/** @var int|null */
	private $owner_uid = null;

	/** @var bool */
	private $data_loaded = false;

	/** @var PluginHost|null */
	private static $instance = null;

	const API_VERSION = 2;
	const PUBLIC_METHOD_DELIMITER = "--";

	// Hooks marked with *1 are run in global context and available
	// to plugins loaded in config.php only

	/** hook_article_button($line) */
	const HOOK_ARTICLE_BUTTON = "hook_article_button";

	/** hook_article_filter($article) */
	const HOOK_ARTICLE_FILTER = "hook_article_filter";

	/** hook_prefs_tab($tab) */
	const HOOK_PREFS_TAB = "hook_prefs_tab";

	/** hook_prefs_tab_section($section) */
	const HOOK_PREFS_TAB_SECTION = "hook_prefs_tab_section";

	/** hook_prefs_tabs() */
	const HOOK_PREFS_TABS = "hook_prefs_tabs";

	/** hook_feed_parsed($parser, $feed_id) */
	const HOOK_FEED_PARSED = "hook_feed_parsed";

	/** GLOBAL: hook_update_task($cli_options) */
	const HOOK_UPDATE_TASK = "hook_update_task"; //*1

	/** hook_auth_user($login, $password, $service) (byref) */
	const HOOK_AUTH_USER = "hook_auth_user";

	/** hook_hotkey_map($hotkeys) (byref) */
	const HOOK_HOTKEY_MAP = "hook_hotkey_map";

	/** hook_render_article($article) */
	const HOOK_RENDER_ARTICLE = "hook_render_article";

	/** hook_render_article_cdm($article) */
	const HOOK_RENDER_ARTICLE_CDM = "hook_render_article_cdm";

	/** hook_feed_fetched($feed_data, $fetch_url, $owner_uid, $feed) (byref) */
	const HOOK_FEED_FETCHED = "hook_feed_fetched";

	/** hook_sanitize($doc, $site_url, $allowed_elements, $disallowed_attributes, $article_id) (byref) */
	const HOOK_SANITIZE = "hook_sanitize";

	/** hook_render_article_api($params) */
	const HOOK_RENDER_ARTICLE_API = "hook_render_article_api";

	/** hook_toolbar_button() */
	const HOOK_TOOLBAR_BUTTON = "hook_toolbar_button";

	/** hook_action_item() */
	const HOOK_ACTION_ITEM = "hook_action_item";

	/** hook_headline_toolbar_button($feed_id, $is_cat) */
	const HOOK_HEADLINE_TOOLBAR_BUTTON = "hook_headline_toolbar_button";

	/** hook_hotkey_info($hotkeys) (byref) */
	const HOOK_HOTKEY_INFO = "hook_hotkey_info";

	/** hook_article_left_button($row) */
	const HOOK_ARTICLE_LEFT_BUTTON = "hook_article_left_button";

	/** hook_prefs_edit_feed($feed_id) */
	const HOOK_PREFS_EDIT_FEED = "hook_prefs_edit_feed";

	/** hook_prefs_save_feed($feed_id) */
	const HOOK_PREFS_SAVE_FEED = "hook_prefs_save_feed";

	/** hook_fetch_feed($feed_data, $fetch_url, $owner_uid, $feed, $last_article_timestamp, $auth_login, $auth_pass) (byref) */
	const HOOK_FETCH_FEED = "hook_fetch_feed";

	/** hook_query_headlines($row) (byref) */
	const HOOK_QUERY_HEADLINES = "hook_query_headlines";

	/** GLOBAL: hook_house_keeping() */
	const HOOK_HOUSE_KEEPING = "hook_house_keeping"; //*1

	/** hook_search($query) */
	const HOOK_SEARCH = "hook_search";

	/** hook_format_enclosures($rv, $result, $id, $always_display_enclosures, $article_content, $hide_images) (byref) */
	const HOOK_FORMAT_ENCLOSURES = "hook_format_enclosures";

	/** hook_subscribe_feed($contents, $url, $auth_login, $auth_pass) (byref) */
	const HOOK_SUBSCRIBE_FEED = "hook_subscribe_feed";

	/** hook_headlines_before($feed, $is_cat, $qfh_ret) */
	const HOOK_HEADLINES_BEFORE = "hook_headlines_before";

	/** hook_render_enclosure($entry, $id, $rv) */
	const HOOK_RENDER_ENCLOSURE = "hook_render_enclosure";

	/** hook_article_filter_action($article, $action) */
	const HOOK_ARTICLE_FILTER_ACTION = "hook_article_filter_action";

	/** hook_article_export_feed($line, $feed, $is_cat, $owner_uid) (byref) */
	const HOOK_ARTICLE_EXPORT_FEED = "hook_article_export_feed";

	/** hook_main_toolbar_button() */
	const HOOK_MAIN_TOOLBAR_BUTTON = "hook_main_toolbar_button";

	/** hook_enclosure_entry($entry, $id, $rv) (byref) */
	const HOOK_ENCLOSURE_ENTRY = "hook_enclosure_entry";

	/** hook_format_article($html, $row) */
	const HOOK_FORMAT_ARTICLE = "hook_format_article";

	/** @deprecated removed, do not use */
	const HOOK_FORMAT_ARTICLE_CDM = "hook_format_article_cdm";

	/** hook_feed_basic_info($basic_info, $fetch_url, $owner_uid, $feed_id, $auth_login, $auth_pass) (byref) */
	const HOOK_FEED_BASIC_INFO = "hook_feed_basic_info";

	/** hook_send_local_file($filename) */
	const HOOK_SEND_LOCAL_FILE = "hook_send_local_file";

	/** hook_unsubscribe_feed($feed_id, $owner_uid) */
	const HOOK_UNSUBSCRIBE_FEED = "hook_unsubscribe_feed";

	/** hook_send_mail(Mailer $mailer, $params) */
	const HOOK_SEND_MAIL = "hook_send_mail";

	/** hook_filter_triggered($feed_id, $owner_uid, $article, $matched_filters, $matched_rules, $article_filters) */
	const HOOK_FILTER_TRIGGERED = "hook_filter_triggered";

	/** hook_get_full_text($url) */
	const HOOK_GET_FULL_TEXT = "hook_get_full_text";

	/** hook_article_image($enclosures, $content, $site_url) */
	const HOOK_ARTICLE_IMAGE = "hook_article_image";

	/** hook_feed_tree() */
	const HOOK_FEED_TREE = "hook_feed_tree";

	/** hook_iframe_whitelisted($url) */
	const HOOK_IFRAME_WHITELISTED = "hook_iframe_whitelisted";

	/** hook_enclosure_imported($enclosure, $feed) */
	const HOOK_ENCLOSURE_IMPORTED = "hook_enclosure_imported";

	/** hook_headlines_custom_sort_map() */
	const HOOK_HEADLINES_CUSTOM_SORT_MAP = "hook_headlines_custom_sort_map";

	/** hook_headlines_custom_sort_override($order) */
	const HOOK_HEADLINES_CUSTOM_SORT_OVERRIDE = "hook_headlines_custom_sort_override";

	/** hook_headline_toolbar_select_menu_item($feed_id, $is_cat) */
	const HOOK_HEADLINE_TOOLBAR_SELECT_MENU_ITEM = "hook_headline_toolbar_select_menu_item";


	/** hook_pre_subscribe($url, $auth_login, $auth_pass) (byref) */
	const HOOK_PRE_SUBSCRIBE = "hook_pre_subscribe";

	const KIND_ALL = 1;
	const KIND_SYSTEM = 2;
	const KIND_USER = 3;

	static function object_to_domain(Plugin $plugin): string {
		return strtolower(get_class($plugin));
	}

	function __construct() {
		$this->pdo = Db::pdo();
		$this->storage = [];
	}

	private function __clone() {
		//
	}

	public static function getInstance(): PluginHost {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	private function register_plugin(string $name, Plugin $plugin): void {
		//array_push($this->plugins, $plugin);
		$this->plugins[$name] = $plugin;
	}

	/** needed for compatibility with API 1 */
	function get_link(): bool {
		return false;
	}

	/** needed for compatibility with API 2 (?) */
	function get_dbh(): bool {
		return false;
	}

	function get_pdo(): PDO {
		return $this->pdo;
	}

	/**
	 * @return array<int, string>
	 */
	function get_plugin_names(): array {
		$names = [];

		foreach ($this->plugins as $p) {
			array_push($names, get_class($p));
		}

		return $names;
	}

	/**
	 * @return array<Plugin>
	 */
	function get_plugins(): array {
		return $this->plugins;
	}

	function get_plugin(string $name): ?Plugin {
		return $this->plugins[strtolower($name)] ?? null;
	}

	/**
	 * @param mixed $args
	 */
	function run_hooks(string $hook, ...$args): void {
		$method = strtolower($hook);

		foreach ($this->get_hooks($hook) as $plugin) {
			//Debug::log("invoking: " . get_class($plugin) . "->$hook()", Debug::$LOG_VERBOSE);

			try {
				$plugin->$method(...$args);
			} catch (Exception $ex) {
				user_error($ex, E_USER_WARNING);
			} catch (Error $err) {
				user_error($err, E_USER_WARNING);
			}
		}
	}

	/**
	 * @param mixed $args
	 * @param mixed $check
	 */
	function run_hooks_until(string $hook, $check, ...$args): bool {
		$method = strtolower($hook);

		foreach ($this->get_hooks($hook) as $plugin) {
			try {
				$result = $plugin->$method(...$args);

				if ($result == $check)
					return true;

			} catch (Exception $ex) {
				user_error($ex, E_USER_WARNING);
			} catch (Error $err) {
				user_error($err, E_USER_WARNING);
			}
		}

		return false;
	}

	/**
	 * @param mixed $args
	 */
	function run_hooks_callback(string $hook, Closure $callback, ...$args): void {
		$method = strtolower($hook);

		foreach ($this->get_hooks($hook) as $plugin) {
			//Debug::log("invoking: " . get_class($plugin) . "->$hook()", Debug::$LOG_VERBOSE);

			try {
				if ($callback($plugin->$method(...$args), $plugin))
					break;
			} catch (Exception $ex) {
				user_error($ex, E_USER_WARNING);
			} catch (Error $err) {
				user_error($err, E_USER_WARNING);
			}
		}
	}

	/**
	 * @param mixed $args
	 */
	function chain_hooks_callback(string $hook, Closure $callback, &...$args): void {
		$method = strtolower($hook);

		foreach ($this->get_hooks($hook) as $plugin) {
			//Debug::log("invoking: " . get_class($plugin) . "->$hook()", Debug::$LOG_VERBOSE);

			try {
				if ($callback($plugin->$method(...$args), $plugin))
					break;
			} catch (Exception $ex) {
				user_error($ex, E_USER_WARNING);
			} catch (Error $err) {
				user_error($err, E_USER_WARNING);
			}
		}
	}

	function add_hook(string $type, Plugin $sender, int $priority = 50): void {
		$priority = (int) $priority;

		if (!method_exists($sender, strtolower($type))) {
			user_error(
				sprintf("Plugin %s tried to register a hook without implementation: %s",
					get_class($sender), $type),
				E_USER_WARNING
			);
			return;
		}

		if (empty($this->hooks[$type])) {
			$this->hooks[$type] = [];
		}

		if (empty($this->hooks[$type][$priority])) {
			$this->hooks[$type][$priority] = [];
		}

		array_push($this->hooks[$type][$priority], $sender);
		ksort($this->hooks[$type]);
	}

	function del_hook(string $type, Plugin $sender): void {
		if (is_array($this->hooks[$type])) {
			foreach (array_keys($this->hooks[$type]) as $prio) {
				$key = array_search($sender, $this->hooks[$type][$prio]);

				if ($key !== false) {
					unset($this->hooks[$type][$prio][$key]);
				}
			}
		}
	}

	/**
	 * @return array<int, Plugin>
	 */
	function get_hooks(string $type) {
		if (isset($this->hooks[$type])) {
			$tmp = [];

			foreach (array_keys($this->hooks[$type]) as $prio) {
				$tmp = array_merge($tmp, $this->hooks[$type][$prio]);
			}

			return $tmp;
		}
		return [];
	}
	function load_all(int $kind, int $owner_uid = null, bool $skip_init = false): void {

		$plugins = array_merge(glob("plugins/*"), glob("plugins.local/*"));
		$plugins = array_filter($plugins, "is_dir");
		$plugins = array_map("basename", $plugins);

		asort($plugins);

		$this->load(join(",", $plugins), $kind, $owner_uid, $skip_init);
	}

	function load(string $classlist, int $kind, int $owner_uid = null, bool $skip_init = false): void {
		$plugins = explode(",", $classlist);

		$this->owner_uid = (int) $owner_uid;

		foreach ($plugins as $class) {
			$class = trim($class);
			$class_file = strtolower(basename(clean($class)));

			// try system plugin directory first
			$file = dirname(__DIR__) . "/plugins/$class_file/init.php";

			if (!file_exists($file)) {
				$file = dirname(__DIR__) . "/plugins.local/$class_file/init.php";

				if (!file_exists($file))
					continue;
			}

			if (!isset($this->plugins[$class])) {

				// WIP hack
				// we can't catch incompatible method signatures via Throwable
				// maybe also auto-disable user plugin in this situation? idk -fox
				if ($_SESSION["plugin_blacklist.$class"] ?? false) {
					user_error("Plugin $class has caused a PHP Fatal Error so it won't be loaded again in this session.", E_USER_NOTICE);
					continue;
				}

				try {
					$_SESSION["plugin_blacklist.$class"] = true;
					require_once $file;
					$_SESSION["plugin_blacklist.$class"] = false;

				} catch (Error $err) {
					user_error($err, E_USER_WARNING);
					continue;
				}

				if (class_exists($class) && is_subclass_of($class, "Plugin")) {

					$plugin = new $class($this);
					$plugin_api = $plugin->api_version();

					if ($plugin_api < self::API_VERSION) {
						user_error("Plugin $class is not compatible with current API version (need: " . self::API_VERSION . ", got: $plugin_api)", E_USER_WARNING);
						continue;
					}

					if (file_exists(dirname($file) . "/locale")) {
						_bindtextdomain($class, dirname($file) . "/locale");
						_bind_textdomain_codeset($class, "UTF-8");
					}

					try {
						switch ($kind) {
							case $this::KIND_SYSTEM:
								if ($this->is_system($plugin)) {
									if (!$skip_init) $plugin->init($this);
									$this->register_plugin($class, $plugin);
								}
								break;
							case $this::KIND_USER:
								if (!$this->is_system($plugin)) {
									if (!$skip_init) $plugin->init($this);
									$this->register_plugin($class, $plugin);
								}
								break;
							case $this::KIND_ALL:
								if (!$skip_init) $plugin->init($this);
								$this->register_plugin($class, $plugin);
								break;
							}
					} catch (Exception $ex) {
						user_error($ex, E_USER_WARNING);
					} catch (Error $err) {
						user_error($err, E_USER_WARNING);
					}
				}
			}
		}

		$this->load_data();
	}

	function is_system(Plugin $plugin): bool {
		$about = $plugin->about();

		return ($about[3] ?? false) === true;
	}

	// only system plugins are allowed to modify routing
	function add_handler(string $handler, string $method, Plugin $sender): void {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		if ($this->is_system($sender)) {
			if (!isset($this->handlers[$handler])) {
				$this->handlers[$handler] = [];
			}

			$this->handlers[$handler][$method] = $sender;
		}
	}

	function del_handler(string $handler, string $method, Plugin $sender): void {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		if ($this->is_system($sender)) {
			unset($this->handlers[$handler][$method]);
		}
	}

	/**
	 * @return false|Plugin false if the handler couldn't be found, otherwise the Plugin/handler
	 */
	function lookup_handler(string $handler, string $method) {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		if (isset($this->handlers[$handler])) {
			if (isset($this->handlers[$handler]["*"])) {
				return $this->handlers[$handler]["*"];
			} else {
				return $this->handlers[$handler][$method];
			}
		}

		return false;
	}

	function add_command(string $command, string $description, Plugin $sender, string $suffix = "", string $arghelp = ""): void {
		$command = str_replace("-", "_", strtolower($command));

		$this->commands[$command] = array("description" => $description,
			"suffix" => $suffix,
			"arghelp" => $arghelp,
			"class" => $sender);
	}

	function del_command(string $command): void {
		$command = "-" . strtolower($command);

		unset($this->commands[$command]);
	}

	/**
	 * @return false|Plugin false if the command couldn't be found, otherwise the registered Plugin
	 */
	function lookup_command(string $command) {
		$command = "-" . strtolower($command);

		if (array_key_exists($command, $this->commands) && is_array($this->commands[$command])) {
			return $this->commands[$command]["class"];
		} else {
			return false;
		}
	}

	/** @return array<string, array{'description': string, 'suffix': string, 'arghelp': string, 'class': Plugin}>> command type -> details array */
	function get_commands() {
		return $this->commands;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	function run_commands(array $args): void {
		foreach ($this->get_commands() as $command => $data) {
			if (isset($args[$command])) {
				$command = str_replace("-", "", $command);
				$data["class"]->$command($args);
			}
		}
	}

	private function load_data(): void {
		if ($this->owner_uid && !$this->data_loaded && get_schema_version() > 100)  {
			$sth = $this->pdo->prepare("SELECT name, content FROM ttrss_plugin_storage
				WHERE owner_uid = ?");
			$sth->execute([$this->owner_uid]);

			while ($line = $sth->fetch()) {
				$this->storage[$line["name"]] = unserialize($line["content"]);
			}

			$this->data_loaded = true;
		}
	}

	private function save_data(string $plugin): void {
		if ($this->owner_uid) {

			if (!$this->pdo_data)
				$this->pdo_data = Db::instance()->pdo_connect();

			$this->pdo_data->beginTransaction();

			$sth = $this->pdo_data->prepare("SELECT id FROM ttrss_plugin_storage WHERE
				owner_uid= ? AND name = ?");
			$sth->execute([$this->owner_uid, $plugin]);

			if (!isset($this->storage[$plugin]))
				$this->storage[$plugin] = [];

			$content = serialize($this->storage[$plugin]);

			if ($sth->fetch()) {
				$sth = $this->pdo_data->prepare("UPDATE ttrss_plugin_storage SET content = ?
					WHERE owner_uid= ? AND name = ?");
				$sth->execute([$content, $this->owner_uid, $plugin]);

			} else {
				$sth = $this->pdo_data->prepare("INSERT INTO ttrss_plugin_storage
					(name,owner_uid,content) VALUES
					(?, ?, ?)");
				$sth->execute([$plugin, $this->owner_uid, $content]);
			}

			$this->pdo_data->commit();
		}
	}

	/**
	 * same as set(), but sets data to current preference profile
	 *
	 * @param mixed $value
	 */
	function profile_set(Plugin $sender, string $name, $value): void {
		$profile_id = $_SESSION["profile"] ?? null;

		if ($profile_id) {
			$idx = get_class($sender);

			if (!isset($this->storage[$idx])) {
				$this->storage[$idx] = [];
			}

			if (!isset($this->storage[$idx][$profile_id])) {
				$this->storage[$idx][$profile_id] = [];
			}

			$this->storage[$idx][$profile_id][$name] = $value;

			$this->save_data(get_class($sender));
		} else {
			$this->set($sender, $name, $value);
		}
	}

	/**
	 * @param mixed $value
	 */
	function set(Plugin $sender, string $name, $value): void {
		$idx = get_class($sender);

		if (!isset($this->storage[$idx]))
			$this->storage[$idx] = [];

		$this->storage[$idx][$name] = $value;

		$this->save_data(get_class($sender));
	}

	/**
	 * @param array<int|string, mixed> $params
	 */
	function set_array(Plugin $sender, array $params): void {
		$idx = get_class($sender);

		if (!isset($this->storage[$idx]))
			$this->storage[$idx] = [];

		foreach ($params as $name => $value)
			$this->storage[$idx][$name] = $value;

		$this->save_data(get_class($sender));
	}

	/**
	 * same as get(), but sets data to current preference profile
	 *
	 * @param mixed $default_value
	 * @return mixed
	 */
	function profile_get(Plugin $sender, string $name, $default_value = false) {
		$profile_id = $_SESSION["profile"] ?? null;

		if ($profile_id) {
			$idx = get_class($sender);

			$this->load_data();

			if (isset($this->storage[$idx][$profile_id][$name])) {
				return $this->storage[$idx][$profile_id][$name];
			} else {
				return $default_value;
			}

		} else {
			return $this->get($sender, $name, $default_value);
		}
	}

	/**
	 * @param mixed $default_value
	 * @return mixed
	 */
	function get(Plugin $sender, string $name, $default_value = false) {
		$idx = get_class($sender);

		$this->load_data();

		if (isset($this->storage[$idx][$name])) {
			return $this->storage[$idx][$name];
		} else {
			return $default_value;
		}
	}

	/**
	 * @param array<int|string, mixed> $default_value
	 * @return array<int|string, mixed>
	 */
	function get_array(Plugin $sender, string $name, array $default_value = []) {
		$tmp = $this->get($sender, $name);

		if (!is_array($tmp)) $tmp = $default_value;

		return $tmp;
	}

	/**
	 * @return array<string, mixed>
	 */
	function get_all(Plugin $sender) {
		$idx = get_class($sender);

		return $this->storage[$idx] ?? [];
	}

	function clear_data(Plugin $sender): void {
		if ($this->owner_uid) {
			$idx = get_class($sender);

			unset($this->storage[$idx]);

			$sth = $this->pdo->prepare("DELETE FROM ttrss_plugin_storage WHERE name = ?
				AND owner_uid = ?");
			$sth->execute([$idx, $this->owner_uid]);
		}
	}

	// Plugin feed functions are *EXPERIMENTAL*!

	// cat_id: only -1 is supported (Special)
	function add_feed(int $cat_id, string $title, string $icon, Plugin $sender): int {

		if (empty($this->feeds[$cat_id]))
			$this->feeds[$cat_id] = [];

		$id = count($this->feeds[$cat_id]);

		array_push($this->feeds[$cat_id],
			['id' => $id, 'title' => $title, 'sender' => $sender, 'icon' => $icon]);

		return $id;
	}

	/**
	 * @return array<int, array{'id': int, 'title': string, 'sender': Plugin, 'icon': string}>
	 */
	function get_feeds(int $cat_id) {
		return $this->feeds[$cat_id] ?? [];
	}

	// convert feed_id (e.g. -129) to pfeed_id first
	function get_feed_handler(int $pfeed_id): ?Plugin {
		foreach ($this->feeds as $cat) {
			foreach ($cat as $feed) {
				if ($feed['id'] == $pfeed_id) {
					return $feed['sender'];
				}
			}
		}
		return null;
	}

	static function pfeed_to_feed_id(int $pfeed): int {
		return PLUGIN_FEED_BASE_INDEX - 1 - abs($pfeed);
	}

	static function feed_to_pfeed_id(int $feed): int {
		return PLUGIN_FEED_BASE_INDEX - 1 + abs($feed);
	}

	function add_api_method(string $name, Plugin $sender): void {
		if ($this->is_system($sender)) {
			$this->api_methods[strtolower($name)] = $sender;
		}
	}

	function get_api_method(string $name): ?Plugin {
		return $this->api_methods[$name] ?? null;
	}

	function add_filter_action(Plugin $sender, string $action_name, string $action_desc): void {
		$sender_class = get_class($sender);

		if (!isset($this->plugin_actions[$sender_class]))
			$this->plugin_actions[$sender_class] = [];

		array_push($this->plugin_actions[$sender_class],
			array("action" => $action_name, "description" => $action_desc, "sender" => $sender));
	}

	/**
	 * @return array<string, array<int, array{'action': string, 'description': string, 'sender': Plugin}>>
	 */
	function get_filter_actions() {
		return $this->plugin_actions;
	}

	function get_owner_uid(): ?int {
		return $this->owner_uid;
	}

	/**
	 * handled by classes/pluginhandler.php, requires valid session
	 *
	 * @param array<int|string, mixed> $params
	 */
	function get_method_url(Plugin $sender, string $method, array $params = []): string  {
		return Config::get_self_url() . "/backend.php?" .
			http_build_query(
				array_merge(
					[
						"op" => "pluginhandler",
						"plugin" => strtolower(get_class($sender)),
						"method" => $method
					],
					$params));
	}

	// shortcut syntax (disabled for now)
	/* function get_method_url(Plugin $sender, string $method, $params)  {
		return Config::get_self_url() . "/backend.php?" .
			http_build_query(
				array_merge(
					[
						"op" => strtolower(get_class($sender) . self::PUBLIC_METHOD_DELIMITER . $method),
					],
					$params));
	} */

	/**
	 * WARNING: endpoint in public.php, exposed to unauthenticated users
	 *
	 * @param array<int|string, mixed> $params
	 */
	function get_public_method_url(Plugin $sender, string $method, array $params = []): ?string  {
		if ($sender->is_public_method($method)) {
			return Config::get_self_url() . "/public.php?" .
				http_build_query(
					array_merge(
						[
							"op" => strtolower(get_class($sender) . self::PUBLIC_METHOD_DELIMITER . $method),
						],
						$params));
		}
		user_error("get_public_method_url: requested method '$method' of '" . get_class($sender) . "' is private.");
		return null;
	}

	function get_plugin_dir(Plugin $plugin): string {
		$ref = new ReflectionClass(get_class($plugin));
		return dirname($ref->getFileName());
	}

	// TODO: use get_plugin_dir()
	function is_local(Plugin $plugin): bool {
		$ref = new ReflectionClass(get_class($plugin));
		return basename(dirname(dirname($ref->getFileName()))) == "plugins.local";
	}
}
