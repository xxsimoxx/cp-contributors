<?php
/**
 * Plugin Name: ClassicPress contributors
 * Plugin URI: https://software.gieffeedizioni.it
 * Description: List ClassicPress contributors between tags.
 * Version: 1.4.2
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

	private $screen = '';
	const SLUG = 'cp-contributors';
	const CONTRIBUTORS = [
		'renovate[bot]'         => 'renovate[bot]',
		'ClassyBot Releases'    => 'ClassyBot Releases',
		'ClassicPress Releases' => 'ClassicPress Releases',
		'mattyrob'              => 'Matt Robinson',
		'xxsimoxx'              => 'Simone Fioravanti',
		'KTS915'                => 'Tim Kaye',
		'elisabettac77'         => 'Elisabetta Carrara',
		'wolffe'                => 'Ciprian Popescu',
		'zcraber'               => 'Joseph',
		'johnbillion'           => 'John Blackbourn',
		'pattonwebz'            => 'William Patton',
		'mwaters'               => 'Mark Waters',
		'stefangabos'           => 'Stefan Gabos',
		'bahiirwa'              => 'Laurence Bahiirwa',
		'ginsterbusch'          => 'Fabian Wolf',
	];

	public function __construct() {
		add_action('admin_menu', [$this, 'create_menu'], 100);
		add_action('admin_enqueue_scripts', [$this, 'scripts']);
		add_action('admin_head', [$this, 'help']);
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

	public function get_cp_contributors() {
		return self::CONTRIBUTORS;
	}

	public function maybe_resolve_github_username($name) {
		$name = trim($name, ' .');
		$cp_contributors = $this->get_cp_contributors();
		if (isset($cp_contributors[$name])) {
			return $cp_contributors[$name];
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
		$text = preg_replace('/`([^`]*)`/', '<code>$1</code>', $text);
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
			$cp_props = [];
			$messages = [];

			foreach ($commits as $commit) {
				$messages[] = strtok($commit['commit']['message'], "\n");
				preg_match_all('/^Co-authored-by: (.*) [\"\<].*$/m', $commit['commit']['message'], $matches);
				$coauthors_usernames = $matches[1];
				$coauthors = array_map([$this, 'maybe_resolve_github_username'], $coauthors_usernames);
				$authors = $this->array_iunique(array_merge($authors, $coauthors, [$this->maybe_resolve_github_username($commit['commit']['author']['name'])]));

				preg_match_all('/^(?:WP:)?Props (.*)$/m', $commit['commit']['message'], $matches);
				$props_usernames = [];
				foreach ($matches[1] as $match) {
					$props_usernames = array_merge($props_usernames, explode(',', $match));
				}
				foreach ($props_usernames as $username) {
					$props = $this->array_iunique(array_merge($props, [$this->maybe_resolve_github_username($username)]));
				}

				preg_match_all('/^CP[\: ]Props\:? (.*)$/m', $commit['commit']['message'], $matches);
				$cp_props_usernames = [];
				foreach ($matches[1] as $match) {
					$cp_props_usernames = array_merge($cp_props_usernames, explode(',', $match));
				}
				foreach ($cp_props_usernames as $username) {
					$cp_props = $this->array_iunique(array_merge($cp_props, [$this->maybe_resolve_github_username($username)]));
				}
			}

			echo '<h2>Committers from '.esc_attr($from_tag).' to '.esc_attr($to_tag).'</h2>';
			echo '<h3>All committers</h3>';
			echo esc_html(implode(', ', $authors)).'.';
			echo '<h3>WordPress committers</h3>';
			echo esc_html(implode(', ', array_diff($authors, $this->get_cp_contributors()))).'.';
			echo '<h3>WordPress props</h3>';
			echo esc_html(implode(', ', $props)).'.';
			echo '<h3>ClassicPress props</h3>';
			echo esc_html(implode(', ', $cp_props)).'.';
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

	public function help() {

		$screen = get_current_screen();
		if (!str_ends_with($screen->{'id'}, self::SLUG)) {
			return;
		}

		$content = '<h1>ClassicPress Contributors</h1>

<p>Extract from GitHub ClassicPress (and WordPress) core contributors and Props usernames between two tags.<p>

<ul>
<li>ClassicPress core contributors identified statically (a new contributor must be hardcoded in this plugin).</li>
<li><code>Props</code> and <code>WP:Props</code> are recognized as WordPress Props.</li>
<li><code>CP:Props</code> and <code>CP Props:</code> are recognized as ClassicPress Props.</li>
</ul>
<i> Add your GitHub access token to <code>wp-config.php</code> to use GitHub API without limitations.<br>
<code>define(&quot;GITHUB_API_TOKEN&quot;, &quot;ghp_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX&quot;);</code></i>
';

		$screen->add_help_tab([
			'id' 		=> self::SLUG.'-help',
			'title' 	=> 'ClassicPress Contributors',
			'content' 	=> wp_kses_post($content),
		]);

	}

}

$cp_contributors = new CpContributors();
