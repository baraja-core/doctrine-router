<?php

declare(strict_types=1);

namespace Baraja\DoctrineRouter;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Nette\SmartObject;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *    name="page__page",
 *    uniqueConstraints={@UniqueConstraint(name="page", columns={"module", "presenter", "action"})},
 *    indexes={
 *       @Index(name="page__full", columns={"id", "module", "presenter", "action"})
 *    }
 * )
 */
class Page
{
	use UuidIdentifier;
	use SmartObject;

	/** @ORM\Column(type="string", length=16) */
	private string $module;

	/** @ORM\Column(type="string", length=128) */
	private string $presenter;

	/** @ORM\Column(type="string", length=128) */
	private string $action;

	/**
	 * @var Uri[]|Collection
	 * @ORM\OneToMany(targetEntity="Uri", mappedBy="page", cascade={"persist"})
	 * @ORM\OrderBy({"insertedDate":"DESC"})
	 */
	private $uris;


	public function __construct(?string $module, string $presenter, ?string $action = null)
	{
		$this->module = $module ?? 'Front';
		$this->presenter = $presenter;
		$this->action = $action ?? 'default';
		$this->uris = new ArrayCollection;
	}


	/**
	 * Real absolute route in format "Module:Presenter:action".
	 */
	public function getRoute(): string
	{
		return $this->getModule() . ':' . $this->getPresenter() . ':' . $this->getAction();
	}


	public function getPresenterRoute(): string
	{
		return $this->getPresenter() . ':' . $this->getAction();
	}


	public function getModule(): string
	{
		return $this->module ?: 'Front';
	}


	public function getPresenter(bool $withModule = false): string
	{
		return $withModule === true
			? $this->getModule() . ':' . $this->presenter
			: $this->presenter;
	}


	public function getAction(): string
	{
		return $this->action;
	}


	/**
	 * @return Uri[]|Collection
	 */
	public function getUris()
	{
		return $this->uris;
	}
}
