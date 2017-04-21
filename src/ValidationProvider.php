<?php

declare(strict_types=1);

namespace Chubbyphp\Validation;

use Chubbyphp\Validation\Registry\ObjectMappingRegistry;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

final class ValidationProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        $container['validator.objectmappings'] = function () {
            return [];
        };

        $container['validator.objectmappingregistry'] = function () use ($container) {
            return new ObjectMappingRegistry($container['validator.objectmappings']);
        };

        $container['validator'] = function () use ($container) {
            return new Validator($container['validator.objectmappingregistry']);
        };
    }
}