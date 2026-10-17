<?php

declare(strict_types=1);

namespace LittleCanary\ContaoTheme;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Assets liegen in public/ (→ /bundles/littlecanary), Templates in contao/templates.
 */
class LittleCanaryBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }
}
