<?php

namespace Mpociot\ApiDoc\Extracting\Strategies\Metadata;

use Illuminate\Routing\Route;
use Mpociot\ApiDoc\Extracting\RouteDocBlocker;
use Mpociot\ApiDoc\Extracting\Strategies\Strategy;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionMethod;

class GetCustomMetaFromDocBlocks extends Strategy
{
    public function __invoke(Route $route, ReflectionClass $controller, ReflectionMethod $method, array $routeRules, array $context = [])
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($route);
        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = $docBlocks['method'];

        $customMeta = $this->getCustomMeta($methodDocBlock, $docBlocks['class']);
        return $customMeta;
    }



    /**
     * @param DocBlock $methodDocBlock
     * @param DocBlock $controllerDocBlock
     *
     * @return array The route group name, the group description, ad the route title
     */
    protected function getCustomMeta(DocBlock $methodDocBlock, DocBlock $controllerDocBlock)
    {
        // @group tag on the method overrides that on the controller
        $customMeta = [];
        if (! empty($methodDocBlock->getTags())) {
            foreach ($methodDocBlock->getTags() as $tag) {
                if (strpos($tag->getName(), 'meta') === 0) {
                    $name = lcfirst(substr($tag->getName(), 4));
                    $customMeta[$name] = true;
                }
            }
        }


        return $customMeta;
    }
}
