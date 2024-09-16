<?php
/**
 * Plugin Name: ClassicPress contributors
 * Plugin URI: https://software.gieffeedizioni.it
 * Description: List ClassicPress contributors between tags.
 * Version: 1.1.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: Gieffe edizioni srl
 * Author URI: https://www.gieffeedizioni.it
 * Requires PHP: 7.4
 * Requires CP: 2.0
 */

namespace XXSimoXX\CpContributors;

if (!defined('ABSPATH')) {
	die('-1');
}

class CpContributors {

	public $user_cache = [];
	private $screen = '';
	const SLUG = 'cp-contributors';
	const CONTRIBUTORS = [
		'renovate[bot]',
		'Matt Robinson',
		'Simone Fioravanti',
		'Tim Kaye',
	];

	public function __construct() {
		add_action('admin_menu', [$this, 'create_menu'], 100);
		add_action('admin_enqueue_scripts', [$this, 'scripts']);
		$user_cache = get_transient('cp_contributors_user_cache');
		if ($user_cache === false) {
			return;
		}
		$this->user_cache = $user_cache;
	}

	public function __destruct() {
		if (empty($this->user_cache)) {
			return;
		}
		set_transient('cp_contributors_user_cache', $this->user_cache, 5 * MINUTE_IN_SECONDS);
	}

	private function get_cp_contributors() {
		return apply_filters('cp_contributors_contributors', self::CONTRIBUTORS);
	}

	private function array_iunique($array) {
		return array_intersect_key(
			$array,
			array_unique(array_map('strtolower', $array))
		);
	}

	public function get_github_endpoint($endpoint) {
		$auth = [];
		if (defined('\GITHUB_API_TOKEN')) {
			$auth = [
				'headers' => [
					'Authorization' => 'token '.\GITHUB_API_TOKEN,
				],
			];
		}
		$github_info = wp_remote_get($endpoint, $auth);
		if (is_wp_error($github_info)) {
			return null;
		}
		return json_decode(wp_remote_retrieve_body($github_info), true);
	}

	public function get_github_tags() {
		$r = $this->get_github_endpoint('https://api.github.com/repos/ClassicPress/ClassicPress/tags');
		$tags = [];
		foreach ($r as $tag) {
			$tags[] = $tag['name'];
		}
		return $tags;
	}

	public function get_github_commits($tag1, $tag2) {
		$r = $this->get_github_endpoint('https://api.github.com/repos/ClassicPress/ClassicPress/compare/'.$tag1.'...'.$tag2);
		return $r['commits'];
	}

	public function maybe_resolve_github_username($name) {
		$name = trim($name, ' .');
		if (strpos($name, ' ') !== false) {
			return $name;
		}
		if (array_key_exists($name, $this->user_cache)) {
			return $this->user_cache[$name];
		}
		$r = $this->get_github_endpoint('https://api.github.com/users/'.$name);
		if (isset($r['status']) && $r['status'] === '404') {
			return $name;
		}
		if (array_key_exists('name', $r)) {
			$this->user_cache[$name] = $r['name'] ?? $name;
			return $r['name'] ?? $name;
		}
		return $name;
	}

	public function create_menu() {
		if (!current_user_can('edit_posts')) {
			return;
		}
		$this->screen = add_menu_page(
			'ClassicPress contributors',
			'CP contributors',
			'edit_posts',
			self::SLUG,
			[$this, 'render_page'],
			'dashicons-groups'
		);
	}

	function format_commit($text) {
		// $text = preg_replace('/\x60([^\x60]*)\x60/', '<code>$1</code>', $text);
		// $text = preg_replace('/\'([^\']*)\'/', '<code>$1</code>', $text);
		$text = preg_replace('/#([0-9]*)/', '<a target="_blank" href="https://github.com/ClassicPress/ClassicPress/pull/$1">#$1</a>', $text);
		return $text;
	}

	public function render_page() {
		$tags = $this->get_github_tags();

		echo '<div class="wrap">';
		echo '<h1>ClassicPress Contributors</h1>';
		echo '<form action="'.esc_url_raw(admin_url('admin.php?page='.self::SLUG)).'" method="POST">';
		wp_nonce_field('contributors', '_cpc');

		echo '<select name="from_tag" id="from_tag">';
		foreach ($tags as $tag) {
			echo '<option value="'.esc_attr($tag).'">'.esc_attr($tag).'</option>';
		}
		echo '</select>';
		$tags = array_merge(['develop'], $tags);
		echo '<select name="to_tag" id="to_tag">';
		foreach ($tags as $tag) {
			echo '<option value="'.esc_attr($tag).'">'.esc_attr($tag).'</option>';
		}
		echo '</select>';

		echo '<input type="submit" class="button button-primary" id="submit_button" value="Get contributors"></input>';
		echo '</form>';

		if (isset($_REQUEST['from_tag']) && isset($_REQUEST['to_tag']) && check_admin_referer('contributors', '_cpc')) {

			$from_tag = sanitize_text_field(wp_unslash($_REQUEST['from_tag']));
			$to_tag   = sanitize_text_field(wp_unslash($_REQUEST['to_tag']));
			$commits  = $this->get_github_commits($from_tag, $to_tag);
			$authors  = [];
			$props    = [];
			$messages = [];

			foreach ($commits as $commit) {
				$messages[] = strtok($commit['commit']['message'], "\n");
				preg_match_all('/^Co-authored-by: (.*) [\"\<].*$/m', $commit['commit']['message'], $matches);
				$coauthors_usernames = $matches[1];
				$coauthors = array_map([$this, 'maybe_resolve_github_username'], $coauthors_usernames);
				$authors = $this->array_iunique(array_merge($authors, $coauthors, [$this->maybe_resolve_github_username($commit['commit']['author']['name'])]));
				preg_match_all('/^WP:Props (.*)$/m', $commit['commit']['message'], $matches);
				$props_usernames = [];
				foreach ($matches[1] as $match) {
					$props_usernames = array_merge($props_usernames, explode(',', $match));
				}
				foreach ($props_usernames as $username) {
					$props = $this->array_iunique(array_merge($props, [$this->maybe_resolve_github_username($username)]));
				}
			}

			echo '<h2>Committers from '.esc_attr($from_tag).' to '.esc_attr($to_tag).'</h2>';
			echo '<h3>All committers</h3>';
			echo esc_html(implode(', ', $authors)).'.';
			echo '<h3>All props</h3>';
			echo esc_html(implode(', ', $props)).'.';
			echo '<h3>ClassicPress committers (in random order)</h3>';
			$cp_authors = array_intersect($authors, $this->get_cp_contributors());
			shuffle($cp_authors);
			echo esc_html(implode(', ', $cp_authors)).'.';
			echo '<h3>Commits</h3>';
			echo '<ol>';
			foreach ($messages as $message) {
				echo '<li>'.wp_kses_post($this->format_commit($message)).'</li>';
			}
			echo '</ol>';
		}
		echo '</div>';
	}

	public function scripts($hook) {
		if ($hook !== $this->screen) {
			return;
		}
		wp_enqueue_script(self::SLUG.'-js', plugin_dir_url(__FILE__).'js/'.self::SLUG.'-settings.js', [], '1.0.0', false);
	}

}

$cp_contributors = new CpContributors();
