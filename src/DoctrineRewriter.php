<?php

declare(strict_types=1);

namespace Baraja\DoctrineRouter;


use Baraja\Doctrine\EntityManager;
use Baraja\Localization\Translation;
use Baraja\SmartRouter\MetaData;
use Baraja\SmartRouter\Rewriter;
use Baraja\SmartRouter\RewriterParametersMatch;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;

final class DoctrineRewriter implements Rewriter
{
	private const ID_TO_SLUG = 'id-to-slug';

	private const ID_TO_ROUTE = 'id-to-route';

	private const SLUG_TO_ID = 'slug-to-id';

	private EntityManager $entityManager;

	private Cache $cache;

	/** @var mixed[]|null */
	private ?array $slugToId = null;

	/** @var mixed[]|null */
	private ?array $idToSlug = null;

	/** @var string[]|null */
	private ?array $idToRoute = null;


	public function __construct(EntityManager $entityManager, IStorage $storage)
	{
		$this->entityManager = $entityManager;
		$this->cache = new Cache($storage, 'router-doctrine-rewriter');
	}


	/**
	 * @return string[]|null
	 */
	public function rewriteByPath(string $path): ?array
	{
		$this->initCache();

		$uri = $this->entityManager->getRepository(Uri::class)
			->createQueryBuilder('uri')
			->select('PARTIAL uri.{id, locale, parameterId}, PARTIAL page.{id, module, presenter, action}')
			->leftJoin('uri.page', 'page')
			->where('uri.slug = :path')
			->andWhere('uri.active = TRUE')
			->setParameter('path', $path)
			->setMaxResults(1)
			->getQuery()
			->getArrayResult();

		if (isset($uri[0]) === false) {
			return null;
		}

		$return = [
			'presenter' => $uri[0]['page']['module'] . ':' . $uri[0]['page']['presenter'],
			'action' => $uri[0]['page']['action'],
			'locale' => $uri[0]['locale'],
		];

		if ($uri[0]['parameterId'] !== null) {
			$return['id'] = $uri[0]['parameterId'];
		}

		return $return;
	}


	/**
	 * This logic try load routing rules in super fast array case.
	 * In case of matching route does not exist, try find new routing rule in real database entity.
	 *
	 * @param string[] $parameters
	 * @return RewriterParametersMatch|null
	 */
	public function rewriteByParameters(array $parameters): ?RewriterParametersMatch
	{
		$this->initCache();
		$locale = $parameters['locale'] ?? null;
		$route = $parameters['presenter'] . ':' . $parameters['action'];

		// Performance match in native array cache
		if (isset($parameters['id'], $this->idToSlug[$parameters['id']], $this->idToRoute[$parameters['id']]) === true && $this->idToRoute[$parameters['id']] === $route) {
			$bestMatch = null;
			$bestMatchLocale = null;
			foreach ($this->idToSlug[$parameters['id']] ?? [] as $cacheLocale => $cacheSlug) {
				if ($bestMatch === null) {
					$bestMatch = $cacheSlug;
					$bestMatchLocale = $cacheLocale;
				}
				if ($cacheLocale === $locale) {
					$bestMatchLocale = $cacheLocale;
					$bestMatch = $cacheSlug;
				}
			}

			return new RewriterParametersMatch($bestMatch ?? '', $bestMatchLocale, [
				'id' => $parameters['id'],
			]);
		}

		// Alternative match strategy by manual SQL query
		$queryBuilder = $this->entityManager->getRepository(Uri::class)
			->createQueryBuilder('uri')
			->select('PARTIAL uri.{id, slug, locale, parameterId}')
			->where('uri.page = :pageId')
			->andWhere('uri.active = TRUE')
			->andWhere('uri.oneWay = FALSE')
			->setParameters([
				'pageId' => $this->getPageIdByRoute('Front:' . $route),
			])
			->orderBy('uri.priority', 'DESC')
			->addOrderBy('uri.insertedDate', 'DESC');
		unset($parameters['presenter'], $parameters['action']);

		if (isset($parameters['id'])) {
			$queryBuilder->andWhere('uri.parameterId = :parameterId')
				->setParameter('parameterId', $parameters['id']);
			unset($parameters['id']);
		}
		if (($bestMatch = $this->findBestMatch($queryBuilder->getQuery()->getArrayResult(), $parameters['locale'] ?? null)) !== null) {
			$returnParameters = [];

			if ($bestMatch['parameterId'] !== null) {
				$returnParameters['id'] = $bestMatch['parameterId'];
			}

			return new RewriterParametersMatch($bestMatch['slug'], $bestMatch['locale'], $returnParameters);
		}

		return null;
	}


	public function clearCache(): void
	{
		$this->cache->clean([Cache::ALL => true]);
	}


	public function getMetaData(string $path, string $locale): MetaData
	{
		static $cache = [];

		if (isset($cache[$key = $path . "\x00" . $locale]) === true) {
			return $cache[$key];
		}

		$uri = $this->entityManager->getRepository(Uri::class)
			->createQueryBuilder('uri')
			->select('PARTIAL uri.{id, metaTitle, metaDescription, ogTitle, ogDescription, noIndex, noFollow, priority, seoScore}')
			->where('uri.slug = :path')
			->andWhere('uri.active = TRUE')
			->andWhere('uri.locale = :locale')
			->setParameter('path', $path)
			->setParameter('locale', $locale)
			->setMaxResults(1)
			->getQuery()
			->getArrayResult();

		$formatter = static function ($haystack) use ($locale): string {
			if ($haystack instanceof Translation) {
				return trim($haystack->getTranslation($locale) ?: '');
			}

			return (string) $haystack;
		};

		return isset($uri[0]) === true ? $cache[$key] = (new MetaData)
			->setId((string) $uri[0]['id'])
			->setMetaTitle($formatter((string) $uri[0]['metaTitle']))
			->setMetaDescription($formatter((string) $uri[0]['metaDescription']))
			->setOgTitle($formatter((string) $uri[0]['ogTitle']))
			->setOgDescription($formatter((string) $uri[0]['ogDescription']))
			->setNoIndex((bool) $uri[0]['noIndex'])
			->setNoFollow((bool) $uri[0]['noFollow'])
			->setPriority((int) $uri[0]['priority'])
			->setSeoScore($uri[0]['seoScore'])
			: new MetaData;
	}


	private function getPageIdByRoute(string $route): ?string
	{
		static $cache;

		if ($cache === null) {
			$cache = [];

			$routes = $this->entityManager->getRepository(Page::class)
				->createQueryBuilder('page')
				->select('page.id')
				->addSelect('CONCAT(page.module, \':\', page.presenter, \':\', page.action) AS route')
				->getQuery()
				->getArrayResult();

			foreach ($routes as $routeItem) {
				$cache[$routeItem['route']] = $routeItem['id'] === null ? null : (string) $routeItem['id'];
			}
		}
		if (isset($cache[$route]) === false) {
			$this->entityManager
				->persist($page = new Page(($routeParser = explode(':', $route))[0], $routeParser[1], $routeParser[2]))
				->flush($page);

			$cache[$route] = $page->getId();
		}

		return $cache[$route] ?? null;
	}


	/**
	 * Find best match by scoring results and return best score.
	 *
	 * @param string[][] $results
	 * @param string|null $locale
	 * @return string[]|null
	 */
	private function findBestMatch(array $results, ?string $locale): ?array
	{
		$topResult = null;
		$topResultScore = 0;
		foreach ($results as $result) {
			$score = 0;
			if ($topResult === null) {
				$score++;
			}
			if ($result['locale'] !== null && $locale === $result['locale']) {
				$score += 5;
			}
			if ($topResultScore < $score) {
				$topResult = $result;
				$topResultScore = $score;
			}
		}

		return $topResult;
	}


	/**
	 * Build cache if does not exist and rewrite native array to Rewriter internal state.
	 *
	 * This function makes routing logic too fast, because it make one performance
	 * array with all basic routes to specific publication-entity ID.
	 */
	private function initCache(): void
	{
		if ($this->idToSlug !== null && $this->slugToId !== null) {
			return;
		}
		if (($idToSlug = $this->cache->load(self::ID_TO_SLUG)) !== null) {
			$this->idToSlug = $idToSlug;
		}
		if (($slugToId = $this->cache->load(self::SLUG_TO_ID)) !== null) {
			$this->slugToId = $slugToId;
		}
		if ($this->idToSlug !== null && $this->slugToId !== null) {
			return;
		}

		$uris = $this->entityManager->getRepository(Uri::class)
			->createQueryBuilder('uri')
			->select('PARTIAL uri.{id, slug, locale, parameterId}')
			->addSelect('PARTIAL page.{id, presenter, action}')
			->leftJoin('uri.page', 'page')
			->andWhere('uri.active = TRUE')
			->andWhere('uri.oneWay = FALSE')
			->andWhere('uri.parameterId IS NOT NULL')
			->orderBy('uri.priority', 'DESC')
			->addOrderBy('uri.insertedDate', 'DESC')
			->getQuery()
			->getArrayResult();

		$idToSlug = [];
		$idToRoute = [];
		$slugToId = [];
		foreach ($uris as $uri) {
			$locale = $uri['locale'] ?? '';
			// 1. ID to slug
			if (isset($idToSlug[$uri['parameterId']]) === false) {
				$idToSlug[$uri['parameterId']] = [];
			}
			if (isset($idToSlug[$uri['parameterId']][$locale]) === false) {
				$idToSlug[$uri['parameterId']][$locale] = $uri['slug'];
			}
			$idToRoute[$uri['parameterId']] = $uri['page']['presenter'] . ':' . $uri['page']['action'];

			// 2. slug to ID
			if (isset($slugToId[$uri['slug']]) === false) {
				$slugToId[$uri['slug']] = [];
			}
			if (isset($slugToId[$uri['slug']][$locale]) === false) {
				$slugToId[$uri['slug']][$locale] = $uri['parameterId'];
			}
		}

		$this->cache->save(self::ID_TO_SLUG, $this->idToSlug = $idToSlug, [
			Cache::EXPIRE => '30 minutes',
		]);
		$this->cache->save(self::ID_TO_ROUTE, $this->idToRoute = $idToRoute, [
			Cache::EXPIRE => '30 minutes',
		]);
		$this->cache->save(self::SLUG_TO_ID, $this->slugToId = $slugToId, [
			Cache::EXPIRE => '30 minutes',
		]);
	}
}
