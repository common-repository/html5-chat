<?php
/*
Plugin Name: html5-chat
Plugin URI: https://html5-chat.com/
Description: Plugin to integrate HTML5 chat to you WP blog, including avatar and username auto login.
Version: 1.04
Author: Proxymis
Author URI: contact@proxymis.com
*/

class HtmlChat
{
	private static $scriptUrl = 'https://html5-chat.com/scriptWP.php';
	private static $loginURL = 'https://html5-chat.com/chatadmin/';
	private static $registerAccountUrl = 'https://wp.html5-chat.com/ajax.php';
	private static $noticeName = 'html5chat-notice';

	private static $domain;
	private $countShortcode = 0;
	private $adminPanel;
	private $code;
	private static $genderField;

	/*
	 * init
	 */
	function __construct()
	{
		$this->init();
		$this->setEvents();
	}

	/*
	 * create an account when plugin activated
	 */
	static function pluginActivated()
	{
		if( !ini_get('allow_url_fopen') ) {
			exit ('Error: "allow_url_fopen" is not enabled. "file_get_contents" plugin cannot be activated if allow_url_fopen is not enabled.
			<a target="_blank" href="https://www.php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen">More details</a>');
		}

		$user = wp_get_current_user();
		$roles = $user->roles;
		$isAdmin = (in_array('administrator', $roles));
		$email = $user->user_email;
		$username = $user->user_login;;
		$domain = get_site_url(null, '', '');

		if (!$domain) {
			$domain = get_home_url(null, '', '');
		}
		if (!$domain) {
			$domain = $_SERVER['SERVER_NAME'];
		}
		$domain = parse_url($domain)['host'];
		$wp_register_url = wp_registration_url();
		$wp_login_url = wp_login_url();

		$params = array('a' => 'createAccountWP', 'username' => $username, 'email' => $email, 'isAdmin' => $isAdmin, 'url' => $domain,
			'wp_register_url' => $wp_register_url, 'wp_login_url' => $wp_login_url);
		$query = http_build_query($params);
		$contextData = array(
			'method' => 'POST',
			'header' => "Connection: close\r\n" . "Content-Length: " . strlen($query) . "\r\n",
			'content' => $query
		);
		$context = stream_context_create(array('http' => $contextData));
		$result = file_get_contents(self::$registerAccountUrl, false, $context);
		set_transient(self::$noticeName, $result, 5);
	}

	/*
	 * display notice when account is activated
	 */
	static function display_notice()
	{
		$jsonString = get_transient(self::$noticeName);
		$json = json_decode($jsonString);
		if(isset($json->message)) {
			echo "<div id='message' class='updated notice is-dismissible'>{$json->message}</div>";
		}
		delete_transient(self::$noticeName);
	}

	function init()
	{
		self::$domain = $this->getDomain();
	}

	function setEvents()
	{
		add_action('admin_init', array($this, 'adminInit'));

		add_action('admin_menu', array($this, 'setMenu'));
		add_shortcode('HTML5CHAT', array($this, 'doShortcode'));

		add_filter('the_content', 'do_shortcode');
		add_filter("mce_external_plugins", array($this, 'enqueuePluginScripts'));
		add_filter("mce_buttons", array($this, 'registerButtonEditor'));
	}

	function adminInit()
	{
		wp_register_style('html5-chat-style', plugin_dir_url(__FILE__) . 'css/style.css');
	}

	function styleAdmin()
	{
		wp_enqueue_style('html5-chat-style');
	}
	//-------------------------------------------------------------------------------------------------------------------------------
	/*
	 * shortcode
	 */
	function isSingleShortcode()
	{
		return $this->countShortcode == 0;
	}

	function isLoggedon()
	{
		$current_user = wp_get_current_user();
		return ($current_user instanceof WP_User);
	}

	function getDomain()
	{
		$str = get_site_url(null, '', '');
		$str = parse_url($str)['host'];
		return $str;
	}

	function getCurrentUser()
	{
		$current_user = wp_get_current_user();
		return $current_user->user_login;
	}

	function getSrcScript($width = '100%', $height = 'fullscreen')
	{
		$roles = wp_get_current_user()->roles;
		$role = ($roles) ? $roles[0] : 'user';
		$isAdmin = in_array('administrator', $roles);
		$currentUser = wp_get_current_user();
		$email = $currentUser->user_email;
		$src = self::$scriptUrl;
		$src .= '?url=' . urlencode(self::$domain);
		$cache = time();
		if ($currentUser) {
			$src .= '&userid=' . time();
			$src .= '&username=' . $currentUser->user_login;
			$src .= '&avatar=' . urlencode(get_avatar_url($currentUser->ID));
			// test if buddyPress is installed
			if (function_exists('bp_has_profile')) {
				$src .= '&gender=' . $this->bbGetGenderUser();
			}
		}
		$src .= "&width=$width&height=$height&isAdmin=$isAdmin&email=$email&cache=$cache&role=$role";
		return $src;
	}

	function doShortcode($attributes)
	{
		if (!$this->isSingleShortcode()) {
			return '';
		}
		$this->countShortcode++;
		if (strtolower($attributes['height']) == 'fullscreen') {
			return '<div style="position: fixed;left: 0;width: 100vw;height: 100vh;top: 0;z-index: 99999999;"><script src="' . $this->getSrcScript($attributes['width'], '100vh') . '" ></script></div>';
		} else {
			return '<script src="' . $this->getSrcScript($attributes['width'], $attributes['height']) . '" ></script>';
		}

	}
	//-------------------------------------------------------------------------------------------------------------------------------
	/*
	 * WP admin panel
	 */
	function getIconMenu()
	{
		return plugin_dir_url(__FILE__) . 'images/icon-menu.png';
	}

	function getPageAdmin()
	{

		//$url = get_admin_url(null, 'admin.php?page='.$this->adminPanel['menu_slug']);
		$email = wp_get_current_user()->user_email;
		$url = self::$loginURL . "?email=$email";
		ob_start(); ?>
		<div id="html5chat-help">
			<h1>Insert HTML5 chat</h1>
			<p>
				To add the chat to your post or page, please <b>paste:</b>
			</p>
			<div>
				<input type="text" value="[HTML5CHAT width=100% height=640px]" style="width: 50%;">
				<button id="copyClipBoardHtml5chat1" onclick="copyToClipBoardHtml5(event)">copy</button>
			</div>

			<p>(Specify the width and height of the chat you want)</p>
			<p>
				If you want the chat to be fullScreen, use height=fullscreen ex:
			</p>
			<div>
				<input type="text" value="[HTML5CHAT width=100% height=fullscreen]" style="width: 50%;">
				<button id="copyClipBoardHtml5chat1" onclick="copyToClipBoardHtml5(event)">copy</button>
			</div>
			<div style="margin: 50px"></div>
			<a style="background: #CCC;padding: 10px;color: black;text-decoration: none;cursor: pointer;border: 1px solid #AAA;	border-radius: 5px;font-size: 1.1em;font-weight: bold;" target="_blank" href="<?= $url; ?>">Configure the chat here</a>
			<p>
				<i>(You account password has been emailed you to <b><?= wp_get_current_user()->user_email; ?></b>)</i>
			</p>
		</div>
		<script>
			function copyToClipBoardHtml5(e) {
				jQuery(e.currentTarget).parent().find("input[type='text']").select()
				document.execCommand('copy');
			}
		</script>

		<?php $src = ob_get_clean();
		echo $src;
	}


	function setMenu()
	{
		$parent = array(
			'page_title' => 'HTML5 chat setting',
			'menu_title' => 'HTML5-CHAT',
			'capability' => 'manage_options',
			'menu_slug' => 'html5-chat',
			'function' => array($this, 'getPageAdmin'),
			'icon_url' => $this->getIconMenu()
		);
		$adminPanelTitle = 'Configure chat';
		$this->adminPanel = array(
			'parent_slug' => $parent['menu_slug'],
			'page_title' => $adminPanelTitle,
			'menu_title' => $adminPanelTitle,
			'capability' => $parent['capability'],
			'menu_slug' => $parent['menu_slug'],
			'function' => array($this, 'getPageAdmin')
		);

		add_menu_page($parent['page_title'], $parent['menu_title'], $parent['capability'], $parent['menu_slug'], $parent['function'], $parent['icon_url']);
	}
	//-------------------------------------------------------------------------------------------------------------------------------
	/*
	 * register button in editor
	 */
	function enqueuePluginScripts($plugin_array)
	{
		if ($this->isSingleShortcode()) {
			$plugin_array['button_html5_chat'] = $this->getButtonScript();
		}

		return $plugin_array;
	}

	function registerButtonEditor($buttons)
	{
		if ($this->isSingleShortcode()) {
			array_push($buttons, 'btn_html5_chat');
		}

		return $buttons;
	}

	function getButtonScript()
	{
		$src = plugin_dir_url(__FILE__) . 'js/main.js';

		return $src;
	}

	// buddyPress
	function bbGetGenderUser() 	{
		global $bp;
		$currentUser = wp_get_current_user();
		if (!isset($currentUser->data->ID)) {
			return 'male';
		}
		$userid = ($currentUser->data->ID);
		if ( function_exists( 'xprofile_get_field_data' ) ) {
			$field_id_or_name = 31;
			$gender = xprofile_get_field_data($field_id_or_name, $userid);
			//die($gender);
			return $gender;
		}
		$gender = 'male';

		$possibleSexes = ['Gender', 'gender', 'sex', 'sexe', 'sesso', 'genre', 'genero', 'género', 'sexo', 'seks', 'секс', 'geslacht', 'kind', 'geschlecht', 'płeć', 'sexuellt', 'kön'];
		foreach ($possibleSexes as $possibleSex) {
			$args = array('field' => $possibleSex, 'user_id' => $userid);
			$gender = bp_get_profile_field_data($args);
			if ($gender) {
				exit("DOUNF");
				break;
			}
		}
		return $gender;
	}

	// buddyPress
	function bbGetTypeUser()
	{
		$role = bp_get_member_type(bp_loggedin_user_id(), true);
		return $role;
	}

}

register_activation_hook(__FILE__, array('HtmlChat', 'pluginActivated'));
add_action('admin_notices', array('HtmlChat', 'display_notice'));
$htmlChat = new HtmlChat();