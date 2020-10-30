<?php

declare(strict_types=1);

namespace Baraja\DoctrineRouter;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Baraja\Localization\Translation;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Nette\SmartObject;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *    name="page__uri",
 *    uniqueConstraints={@UniqueConstraint(name="uri", columns={"slug", "locale"})},
 *    indexes={
 *       @Index(name="uri__order", columns={"priority", "locale", "inserted_date"}),
 *       @Index(name="uri__parameter_id", columns={"parameter_id"}),
 *       @Index(name="uri__page_id_id_active", columns={"page_id", "id", "active"}),
 *       @Index(name="uri__active_oneway", columns={"active", "one_way"}),
 *       @Index(name="uri__seo_manager", columns={"locale", "active", "one_way", "parameter_id"}),
 *       @Index(name="uri__seo_score", columns={"parameter_id", "active", "seo_score", "id"})
 *    }
 * )
 */
class Uri
{
	use UuidIdentifier;
	use SmartObject;

	public const MAX_PRIORITY = 32767;

	/** @ORM\Column(type="string", unique=true) */
	private string $slug;

	/** @ORM\ManyToOne(targetEntity="Page", inversedBy="uris", cascade={"persist"}) */
	private Page $page;

	/** @ORM\Column(type="string", length=36, nullable=true) */
	private ?string $parameterId = null;

	/** @ORM\Column(type="boolean") */
	private bool $active = true;

	/**
	 * Alias for redirect. Only match pattern by router, no use for link generator.
	 *
	 * @ORM\Column(type="boolean")
	 */
	private bool $oneWay = false;

	/** @ORM\Column(type="boolean") */
	private bool $onSitemap = true;

	/**
	 * If ($locale === null) exists only one URI for page.
	 * If ($locale !== null) can be multiple URIs for page.
	 *
	 * @ORM\Column(type="string", length=2, nullable=true)
	 */
	private ?string $locale = null;

	/** @ORM\Column(type="datetime") */
	private \DateTime $insertedDate;

	/** @ORM\Column(type="translate", nullable=true) */
	private ?Translation $metaTitle = null;

	/** @ORM\Column(type="boolean") */
	private bool $keepTitle = false;

	/** @ORM\Column(type="translate", nullable=true) */
	private ?Translation $metaDescription = null;

	/** @ORM\Column(type="translate", nullable=true) */
	private ?Translation $ogTitle = null;

	/** @ORM\Column(type="translate", nullable=true) */
	private ?Translation $ogDescription = null;

	/** @ORM\Column(type="boolean") */
	private bool $noIndex = false;

	/** @ORM\Column(type="boolean") */
	private bool $noFollow = false;

	/** @ORM\Column(type="smallint") */
	private int $priority = 0;

	/**
	 * Real score in compressed form.
	 * Format: [Factor]: [Score <0,100>] | ...
	 * For example: T:34|D:25
	 *
	 * @ORM\Column(type="string", length=50, nullable=true)
	 */
	private ?string $seoScore = null;


	public function __construct(Page $page, string $slug, string $locale)
	{
		$this->page = $page;
		$this->setSlug($slug);
		$this->locale = $locale;
		$this->insertedDate = DateTime::from('now');
	}


	/**
	 * Reformat slug to base format.
	 * If slug is empty (homepage), return "/".
	 */
	public function __toString(): string
	{
		return ($slug = trim($this->getSlug())) === '' ? '/' : $slug;
	}


	public function getSlug(): string
	{
		return $this->slug;
	}


	/**
	 * @param string $slug
	 * @internal
	 */
	public function setSlug(string $slug): void
	{
		$this->slug = ltrim(Strings::webalize($slug, './'), './');
	}


	public function getRoute(): string
	{
		return $this->page->getRoute();
	}


	public function getPage(): Page
	{
		return $this->page;
	}


	/**
	 * @internal
	 */
	final public function setPage(Page $page): void
	{
		$this->page = $page;
	}


	/**
	 * @return string[]
	 */
	public function getParameters(): array
	{
		if ($this->parameterId !== null) {
			return ['id' => $this->parameterId];
		}

		return [];
	}


	public function getParameterId(): ?string
	{
		return $this->parameterId;
	}


	public function setParameterId(?string $parameterId): void
	{
		$this->parameterId = $parameterId;
	}


	public function isActive(): bool
	{
		return $this->active;
	}


	public function setActive(bool $active = true): void
	{
		$this->active = $active;
	}


	public function isOneWay(): bool
	{
		return $this->oneWay;
	}


	public function setOneWay(bool $oneWay = true): void
	{
		$this->oneWay = $oneWay;
	}


	public function isRedirect(): bool
	{
		return $this->oneWay === true;
	}


	public function isCanonical(): bool
	{
		return $this->oneWay === false;
	}


	public function isOnSitemap(): bool
	{
		return $this->onSitemap;
	}


	public function setOnSitemap(bool $onSitemap = true): void
	{
		$this->onSitemap = $onSitemap;
	}


	public function getLocale(): ?string
	{
		return $this->locale;
	}


	public function setLocale(string $locale): void
	{
		$this->locale = $locale;
	}


	public function getInsertedDate(): \DateTime
	{
		return $this->insertedDate;
	}


	/**
	 * @deprecated format in API only!
	 */
	public function getMetaTitle(): ?string
	{
		if ($this->metaTitle === null) {
			return null;
		}

		return ($return = strip_tags((string) $this->metaTitle->getTranslation(null, false))) === '#NO_DATA#' ? null : $return;
	}


	/**
	 * @deprecated format in API only!
	 */
	public function setMetaTitle(?string $metaTitle, string $language = null): void
	{
		if ($this->metaTitle === null) {
			$this->metaTitle = new Translation($metaTitle, $language);
		} else {
			$this->metaTitle->addTranslate($metaTitle ? trim(strip_tags($metaTitle)) : null, $language);
			$this->metaTitle = $this->metaTitle->regenerate();
		}
	}


	public function isKeepTitle(): bool
	{
		return $this->keepTitle ?? false;
	}


	public function setKeepTitle(bool $keepTitle): void
	{
		$this->keepTitle = $keepTitle;
	}


	/**
	 * @deprecated format in API only!
	 */
	public function getMetaDescription(): ?string
	{
		if ($this->metaDescription === null) {
			return null;
		}

		return ($return = strip_tags((string) $this->metaDescription->getTranslation(null, false))) === '#NO_DATA#' ? null : $return;
	}


	/**
	 * @deprecated format in API only!
	 */
	public function setMetaDescription(?string $metaDescription, string $language = null): void
	{
		if ($this->metaDescription === null) {
			$this->metaDescription = new Translation($metaDescription, $language);
		} else {
			$this->metaDescription->addTranslate($metaDescription ? trim(strip_tags($metaDescription)) : null, $language);
			$this->metaDescription = $this->metaDescription->regenerate();
		}
	}


	/**
	 * @deprecated format in API only!
	 */
	public function getOgTitle(): ?string
	{
		if ($this->ogTitle === null) {
			return null;
		}

		return ($return = strip_tags((string) $this->ogTitle->getTranslation(null, false))) === '#NO_DATA#' ? null : $return;
	}


	/**
	 * @deprecated format in API only!
	 */
	public function setOgTitle(?string $ogTitle, string $language = null): void
	{
		if ($ogTitle !== null && $ogTitle === $this->getMetaTitle()) {
			$ogTitle = null;
		}

		if ($this->ogTitle === null) {
			$this->ogTitle = new Translation($ogTitle, $language);
		} else {
			$this->ogTitle->addTranslate($ogTitle ? trim(strip_tags($ogTitle)) : null, $language);
			$this->ogTitle = $this->ogTitle->regenerate();
		}
	}


	/**
	 * @deprecated format in API only!
	 */
	public function getOgDescription(): ?string
	{
		if ($this->ogDescription === null) {
			return null;
		}

		return ($return = strip_tags((string) $this->ogDescription->getTranslation(null, false))) === '#NO_DATA#' ? null : $return;
	}


	/**
	 * @deprecated format in API only!
	 */
	public function setOgDescription(?string $ogDescription, string $language = null): void
	{
		if ($ogDescription !== null && $ogDescription === $this->getMetaDescription()) {
			$ogDescription = null;
		}

		if ($this->ogDescription === null) {
			$this->ogDescription = new Translation($ogDescription, $language);
		} else {
			$this->ogDescription->addTranslate($ogDescription ? trim(strip_tags($ogDescription)) : null, $language);
			$this->ogDescription = $this->ogDescription->regenerate();
		}
	}


	public function isNoIndex(): bool
	{
		return $this->noIndex;
	}


	public function setNoIndex(bool $noIndex): void
	{
		$this->noIndex = $noIndex;
	}


	public function isNoFollow(): bool
	{
		return $this->noFollow;
	}


	public function setNoFollow(bool $noFollow): void
	{
		$this->noFollow = $noFollow;
	}


	public function getPriority(): int
	{
		return $this->priority;
	}


	/**
	 * Set priority and normalize to interval.
	 */
	public function setPriority(int $priority): void
	{
		if ($priority < 0) {
			$priority = 0;
		}
		if ($priority > self::MAX_PRIORITY) {
			$priority = self::MAX_PRIORITY;
		}

		$this->priority = $priority;
	}


	public function getSeoScore(): ?string
	{
		return $this->seoScore;
	}


	public function setSeoScore(?string $seoScore): void
	{
		$this->seoScore = $seoScore;
	}


	/**
	 * @return int[]
	 */
	public function getSeoScoreFormatted(): array
	{
		return Helpers::parseSeoScoreFromString($this->seoScore);
	}
}
