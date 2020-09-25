<?php

declare(strict_types=1);

namespace Baraja\DoctrineRouter;


use Baraja\SmartRouter\SmartRouter;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;

final class DoctrineRouterExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition('barajaDoctrineRewriter')
			->setFactory(DoctrineRewriter::class)
			->setAutowired(DoctrineRewriter::class);

		/** @var ServiceDefinition $smartRouter */
		$smartRouter = $builder->getDefinitionByType(SmartRouter::class);
		$smartRouter->addSetup('?->setRewriter(?)', ['@self', '@' . DoctrineRewriter::class]);
	}
}
