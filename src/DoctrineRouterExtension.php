<?php

declare(strict_types=1);

namespace Baraja\DoctrineRouter;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\SmartRouter\SmartRouter;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\ClassType;

final class DoctrineRouterExtension extends CompilerExtension
{

	/**
	 * @return string[]
	 */
	public static function mustBeDefinedBefore(): array
	{
		return [OrmAnnotationsExtension::class];
	}


	public function beforeCompile(): void
	{
		OrmAnnotationsExtension::addAnnotationPath('Baraja\DoctrineRouter', __DIR__ . '/Entity');
		$builder = $this->getContainerBuilder();

		$builder->addDefinition('barajaDoctrineRewriter')
			->setFactory(DoctrineRewriter::class)
			->setAutowired(DoctrineRewriter::class);
	}


	public function afterCompile(ClassType $class): void
	{
		$builder = $this->getContainerBuilder();

		/** @var ServiceDefinition $smartRouter */
		$smartRouter = $builder->getDefinitionByType(SmartRouter::class);

		/** @var ServiceDefinition $doctrineRewriter */
		$doctrineRewriter = $builder->getDefinitionByType(DoctrineRewriter::class);

		$class->getMethod('initialize')->addBody(
			'// doctrine router.' . "\n"
			. '(function () {' . "\n"
			. "\t" . '$this->getService(?)->setRewriter($this->getService(?));' . "\n"
			. '})();', [
				$smartRouter->getName(),
				$doctrineRewriter->getName(),
			]
		);
	}
}
