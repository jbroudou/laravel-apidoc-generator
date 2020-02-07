<?php

namespace Mpociot\ApiDoc\Extracting\Strategies\ResponseParameters;

use Dingo\Api\Http\FormRequest as DingoFormRequest;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Illuminate\Routing\Route;
use Mpociot\ApiDoc\Extracting\ParamHelpers;
use Mpociot\ApiDoc\Extracting\RouteDocBlocker;
use Mpociot\ApiDoc\Extracting\Strategies\Strategy;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionMethod;

class GetFromResponseParamTag extends Strategy
{
    use ParamHelpers;

    public function __invoke(Route $route, ReflectionClass $controller, ReflectionMethod $method, array $routeRules, array $context = [])
    {
        foreach ($method->getParameters() as $param) {
            $paramType = $param->getType();
            if ($paramType === null) {
                continue;
            }

            $parameterClassName = version_compare(phpversion(), '7.1.0', '<')
                ? $paramType->__toString()
                : $paramType->getName();

            try {
                $parameterClass = new ReflectionClass($parameterClassName);
            } catch (\ReflectionException $e) {
                continue;
            }

            if (class_exists('\Illuminate\Foundation\Http\FormRequest') && $parameterClass->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class) || class_exists('\Dingo\Api\Http\FormRequest') && $parameterClass->isSubclassOf(\Dingo\Api\Http\FormRequest::class)) {
                $formRequestDocBlock = new DocBlock($parameterClass->getDocComment());
                $bodyParametersFromDocBlock = $this->getResponseParametersFromDocBlock($formRequestDocBlock->getTags());

                if (count($bodyParametersFromDocBlock)) {
                    return $bodyParametersFromDocBlock;
                }
            }
        }

        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($route)['method'];
        return $this->getResponseParametersFromDocBlock($methodDocBlock->getTags());
    }

    /**
     * @param array $tags
     *
     * @return array
     */
    protected function getResponseParametersFromDocBlock(array $tags)
    {
        $parameters = collect($tags)
            ->filter(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'responseProp';
            })
            ->mapWithKeys(function ($tag) {
                preg_match('/(.+?)\s+(.+?)\s+(.*)/', $tag->getContent(), $content);
                if (empty($content)) {
                    // this means only name and type were supplied
                    list($name, $type) = preg_split('/\s+/', $tag->getContent());
                    $description = '';
                } else {
                    list($_, $name, $type, $description) = $content;
                    $description = trim($description);
                }

                $type = $this->normalizeParameterType($type);
                list($description, $example) = $this->parseParamDescription($description, $type);
                $value = is_null($example) ? $this->generateDummyValue($type) : $example;

                return [$name => compact('type', 'description', 'value')];
            })->toArray();

        return $parameters;
    }
}
