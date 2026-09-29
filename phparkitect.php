<?php

declare(strict_types=1);

use Arkitect\Analyzer\ClassDescription;
use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\Description;
use Arkitect\Expression\Expression;
use Arkitect\Expression\ForClasses\NotHaveDependencyOutsideNamespace;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;
use Arkitect\Rules\Violation;
use Arkitect\Rules\ViolationMessage;
use Arkitect\Rules\Violations;

/**
 * Application may use the Domain, itself, and any class outside App (vendor and PHP).
 * Other App layers stay forbidden.
 */
final class ApplicationDependsOnDomainOrVendor implements Expression
{
    /** @var list<string> */
    private array $namespaces = ['App\Domain', 'App\Application'];

    public function describe(ClassDescription $theClass, string $because): Description
    {
        $namespaces = implode(', ', $this->namespaces);

        return new Description(
            "should depend only on classes in one of these namespaces: {$namespaces}, or on vendor classes",
            $because,
        );
    }

    public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
    {
        foreach ($theClass->getDependencies() as $dependency) {
            $fqcn = $dependency->getFQCN()->toString();

            if (! str_starts_with($fqcn, 'App\\')) {
                continue;
            }

            if ($dependency->matchesOneOf(...$this->namespaces)) {
                continue;
            }

            $violations->add(Violation::createWithErrorLine(
                $theClass->getFQCN(),
                ViolationMessage::withDescription(
                    $this->describe($theClass, $because),
                    "depends on {$fqcn}",
                ),
                $dependency->getLine(),
                $theClass->getFilePath(),
            ));
        }
    }
}

return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__.'/app');

    $rules = [];

    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('App\Domain'))
        ->should(new NotHaveDependencyOutsideNamespace('App\Domain'))
        ->because('Domain depends on nothing outside itself');

    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('App\Application'))
        ->should(new ApplicationDependsOnDomainOrVendor)
        ->because('Application depends on the Domain and on vendor classes');

    $config->add($classSet, ...$rules);
};
