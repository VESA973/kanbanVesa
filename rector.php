<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Concat\DirnameDirConcatStringToDirectStringPathRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\TypeDeclaration\Rector\ClassMethod\NarrowObjectReturnTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/config/bundles.php',
        __DIR__.'/config/reference.php',
        // Readability choices: `null === $x` reads better than `!$x instanceof Foo`,
        // controller actions keep the generic Response return type and entities
        // keep their mapping on plain properties rather than in the constructor.
        DirnameDirConcatStringToDirectStringPathRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
        NarrowObjectReturnTypeRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class => [__DIR__.'/src/Entity'],
    ])
    ->withPhpSets(php84: true)
    ->withComposerBased(twig: true, doctrine: true, phpunit: true, symfony: true)
    ->withAttributesSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withSymfonyContainerXml(__DIR__.'/var/cache/dev/App_KernelDevDebugContainer.xml')
;
