<?php
use PHPUnit\Framework\TestCase;

final class CpContributorsTest extends TestCase {

	/**
     * @dataProvider names
     */
	public function test_maybe_resolve_github_username(string $input, string $expected):void {
		$contributors = new \XXSimoXX\CpContributors\CpContributors();
		$this->assertEquals(
			$expected,
			$contributors->maybe_resolve_github_username($input)
		);
	}

	public function names() {
		return [
			['xxsimoxx', 'Simone Fioravant'],
			['Tim Kaye', 'Tim Kaye'],
			['user0notexists', 'user0notexists'],
			[' user0notexists .', 'user0notexists']
		];
	}

	/**
     * @dataProvider commits
     */
	public function test_format_commit(string $input, string $expected):void {
		$contributors = new \XXSimoXX\CpContributors\CpContributors();
		$this->assertEquals(
			$expected,
			$contributors->format_commit($input)
		);
	}

	public function commits() {
		return [
			['This is `code`', 'This is <code>code</code>'],
			['This is not `code', 'This is not `code'],
			['Nothing to do', 'Nothing to do'],
			['Backport of grouped PHPStan fixes (#1639)', 'Backport of grouped PHPStan fixes (<a target="_blank" href="https://github.com/ClassicPress/ClassicPress/pull/1639">#1639</a>)'],
			['Backport of grouped PHPStan fixes #1639', 'Backport of grouped PHPStan fixes <a target="_blank" href="https://github.com/ClassicPress/ClassicPress/pull/1639">#1639</a>'],
			['A hashtag # 123', 'A hashtag # 123'],
		];
	}

}
