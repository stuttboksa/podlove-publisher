<?php 
namespace Podlove\Modules\PodloveWebPlayer\PlayerV4;

use Podlove\Model\Episode;

class Module {
	
	public function load() {

		add_action('wp_enqueue_scripts', [$this, 'register_scripts']);

		if (isset($_GET['podlove_tab']) && $_GET['podlove_tab'] == 'player') {
			add_action('admin_enqueue_scripts', [$this, 'register_scripts']);
		}

		// backward compatible, but only load if no other plugin has registered this shortcode
		if (!shortcode_exists('podlove-web-player'))
			add_shortcode('podlove-web-player', [__CLASS__, 'shortcode']);

		add_shortcode('podlove-episode-web-player', [__CLASS__, 'shortcode']);

		add_filter('podlove_player_form_data', [$this, 'add_player_settings']);
	}

	public static function module() {
		return \Podlove\Modules\PodloveWebPlayer\Podlove_Web_Player::instance();
	}

	public static function use_cdn() {
		return self::module()->get_module_option('use_cdn', true);
	}

	public function register_scripts()
	{
		wp_enqueue_script(
			'podlove-player4-embed',
			self::embed_script_url(self::use_cdn()),
			[],
			\Podlove\get_plugin_header('Version')
		);
	}

	public static function embed_script_url($use_cdn = true)
	{
		if ($use_cdn) {
			return 'https://cdn.podlove.org/web-player/embed.js';
		} else {
			return plugins_url('dist/embed.js', __FILE__);
		}
	}

	public static function shortcode($args = []) {

		if (is_feed())
			return '';

		if (isset($args['post_id'])) {
			$post_id = $args['post_id'];
			unset($args['post_id']);
		} else {
			$post_id = get_the_ID();
		}

		add_filter('podlove_player4_config', function ($config) use ($args) {
			if (!is_array($args))
				return $config;
			
			foreach ($args as $key => $value) {
				$key = str_ireplace("mimetype", "mimeType", $key); // because shortcodes ignore case
				$path = explode('_', $key);

				if (count($path) === 1) {
					$config[$path[0]] = $value;
				}

				if (count($path) === 2) {
					if (!isset($config[$path[0]])) {
						$config[$path[0]] = [];
					}
					$config[$path[0]][$path[1]] = $value;
				}

				if (count($path) === 3) {
					if (!isset($config[$path[0]])) {
						$config[$path[0]] = [];
					}
					if (!isset($config[$path[0]][$path[1]])) {
						$config[$path[0]][$path[1]] = [];
					}
					$config[$path[0]][$path[1]][$path[2]] = $value;
				}
			}

			return $config;
		});

		if (isset($args['mode']) && $args['mode'] == 'live') {
			// live mode has no episode to reference
			$printer = new Html5Printer();
		} else {
			$episode = Episode::find_one_by_post_id($post_id);
			$printer = new Html5Printer($episode);
		}

		return $printer->render(null);
	}


	public static function register_config_url_route() {
		add_action('init', [__CLASS__, 'config_url_route']);
	}

	public static function config_url_route() {

		if (!isset($_GET['podlove_player4']))
			return;

		$episode_id = (int) $_GET['podlove_player4'];

		if (!$episode_id)
			return;

		$episode = Episode::find_by_id($episode_id);

		if (!$episode)
			return;

		// allow CORS
		
		// Allow from any origin
		if (isset($_SERVER['HTTP_ORIGIN'])) {
		    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
		    header('Access-Control-Allow-Credentials: true');
		    header('Access-Control-Max-Age: 86400');    // cache for 1 day
		}

		// Access-Control headers are received during OPTIONS requests
		if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

		    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
		        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         

		    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
		        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

		    exit(0);
		}

		// other headers
		header( 'Content-type: application/json' );

		$config = Html5Printer::config($episode, "embed");
		echo json_encode($config);
		exit;
	}

	public function add_player_settings($form_data) {
		
		$form_data[] = [
			'type' => 'string',
			'key'  => 'playerv4_color_primary',
			'options' => [
				'label' => 'Primary Color',
				'description' => __('Hex, rgb or rgba', 'podlove-podcasting-plugin-for-wordpress')
			],
			'position' => 500
		];

		$form_data[] = [
			'type' => 'string',
			'key'  => 'playerv4_color_secondary',
			'options' => [
				'label' => 'Secondary Color (optional)',
				'description' => __('Hex, rgb or rgba', 'podlove-podcasting-plugin-for-wordpress')
			],
			'position' => 495
		];

		// remove "chapter visibility" setting
		$form_data = array_filter($form_data, function ($entry) {
			return $entry['key'] !== 'chaptersVisible';
		});

		return $form_data;
	}
}
