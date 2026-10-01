<?php

declare(strict_types=1);

use Arkitect\Analyzer\ClassDependency;
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
 * Application may use the Domain, itself, repository interfaces, Eloquent models,
 * and any class outside App (vendor and PHP). Http and Infrastructure stay forbidden.
 * Repository dependencies must be interfaces.
 */
final class ApplicationDependsOnDomainRepositoriesOrModels implements Expression
{
    /** @var list<string> */
    private array $namespaces = ['App\Domain', 'App\Application', 'App\Repositories', 'App\Models'];

    public function describe(ClassDescription $theClass, string $because): Description
    {
        $namespaces = implode(', ', $this->namespaces);

        return new Description(
            "should depend only on classes in one of these namespaces: {$namespaces}, or on vendor classes, and repository dependencies must be interfaces",
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

            if (str_starts_with($fqcn, 'App\\Repositories\\') && ! str_ends_with($fqcn, 'Interface')) {
                $violations->add($this->violation($theClass, $dependency, $because, "depends on concrete repository {$fqcn}"));

                continue;
            }

            if ($dependency->matchesOneOf(...$this->namespaces)) {
                continue;
            }

            $violations->add($this->violation($theClass, $dependency, $because, "depends on {$fqcn}"));
        }
    }

    private function violation(ClassDescription $theClass, ClassDependency $dependency, string $because, string $detail): Violation
    {
        return Violation::createWithErrorLine(
            $theClass->getFQCN(),
            ViolationMessage::withDescription($this->describe($theClass, $because), $detail),
            $dependency->getLine(),
            $theClass->getFilePath(),
        );
    }
}

/**
 * Controllers call use-case interfaces. They may use Http, Domain, and Models
 * for route binding and resources. They do not call repositories, infrastructure, or concrete use cases.
 */
final class ControllersDependOnUsecaseInterfaces implements Expression
{
    public function describe(ClassDescription $theClass, string $because): Description
    {
        return new Description(
            'should depend on use-case interfaces, not on repositories, infrastructure, or concrete use cases',
            $because,
        );
    }

    public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
    {
        foreach ($theClass->getDependencies() as $dependency) {
            $fqcn = $dependency->getFQCN()->toString();
            $forbidden = str_starts_with($fqcn, 'App\\Repositories\\')
                || str_starts_with($fqcn, 'App\\Infrastructure\\')
                || (str_starts_with($fqcn, 'App\\Application\\') && ! str_ends_with($fqcn, 'Interface'));

            if (! $forbidden) {
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
        ->should(new ApplicationDependsOnDomainRepositoriesOrModels)
        ->because('Application depends on the Domain, repository interfaces, models, and vendor classes');

    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('App\Http\Controllers'))
        ->should(new ControllersDependOnUsecaseInterfaces)
        ->because('Controllers depend on use-case interfaces, not repositories or infrastructure');

    $config->add($classSet, ...$rules);
};
