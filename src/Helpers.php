<?php

declare(strict_types=1);

namespace Baraja\DoctrineRouter;


final class Helpers
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . static::class . ' is static and cannot be instantiated.');
	}


	/**
	 * Parse given string from DB and convert to standard SEO notation.
	 *
	 * @return int[]
	 */
	public static function parseSeoScoreFromString(?string $seoScore): array
	{
		$score = [];
		if ($seoScore !== null) {
			foreach (explode('|', $seoScore) as $factor) {
				if ($factor === '') {
					continue;
				}
				if (preg_match('/^([a-zA-Z]+):(\d+)$/', $factor, $parser)) {
					$score[$parser[1]] = (int) $parser[2];
				} else {
					throw new \InvalidArgumentException('Invalid factor format, because "' . $factor . '" given. Did you mean "Factor:score"?');
				}
			}
		}

		$return = [];
		foreach (['T', 'DG', 'I', 'F', 'A', 'R'] as $key) {
			$return[$key] = $score[$key] ?? 0;
		}

		return $return;
	}
}
