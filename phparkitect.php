<?php

declare(strict_types=1);

use Arkitect\Analyzer\ClassDependency;
use Arkitect\Analyzer\ClassDescription;
use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\Description;
use Arkitect\Expression\Expression;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;
use Arkitect\Rules\Violation;
use Arkitect\Rules\ViolationMessage;
use Arkitect\Rules\Violations;

/**
 * Domain may use itself and any class outside App (vendor and PHP).
 * Other App layers stay forbidden.
 */
final class DomainDependsOnItselfOrVendor implements Expression
{
    public function describe(ClassDescription $theClass, string $because): Description
    {
        return new Description(
            'should depend only on classes in App\Domain, or on vendor classes',
            $because,
        );
    }

    public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
    {
        foreach ($theClass->getDependencies() as $dependency) {
            $fqcn = $dependency->getFQCN()->toString();

            if (! str_starts_with($fqcn, 'App\\') || str_starts_with($fqcn, 'App\\Domain\\')) {
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
 * Controllers call use-case interfaces and construct commands. They may use Http, Domain, and Models
 * for route binding and resources. They do not call repositories, infrastructure, or concrete use cases.
 */
final class ControllersDependOnUsecaseInterfaces implements Expression
{
    public function describe(ClassDescription $theClass, string $because): Description
    {
        return new Description(
            'should depend on use-case interfaces and commands, not on repositories, infrastructure, or concrete use cases',
            $because,
        );
    }

    public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
    {
        foreach ($theClass->getDependencies() as $dependency) {
            $fqcn = $dependency->getFQCN()->toString();
            $applicationClass = str_starts_with($fqcn, 'App\\Application\\');
            $allowedApplicationClass = str_ends_with($fqcn, 'Interface') || str_ends_with($fqcn, 'Command');
            $forbidden = str_starts_with($fqcn, 'App\\Repositories\\')
                || str_starts_with($fqcn, 'App\\Infrastructure\\')
                || ($applicationClass && ! $allowedApplicationClass);

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

/**
 * Selector: the class short name ends with one of the suffixes, except an optional FQCN.
 * A violation means the class does not match, so rule checkers skip it when this is used as `that()`.
 */
final class ClassNameEndsWith implements Expression
{
    /**
     * @param  list<string>  $suffixes
     */
    public function __construct(private array $suffixes, private ?string $exceptFqcn = null) {}

    public function describe(ClassDescription $theClass, string $because): Description
    {
        $suffixes = implode(' or ', $this->suffixes);

        return new Description("should have a name ending with {$suffixes}", $because);
    }

    public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
    {
        $fqcn = $theClass->getFQCN();

        if ($this->exceptFqcn !== null && $fqcn === $this->exceptFqcn) {
            $this->mismatch($theClass, $violations, $because, $fqcn);

            return;
        }

        foreach ($this->suffixes as $suffix) {
            if (str_ends_with($fqcn, $suffix)) {
                return;
            }
        }

        $this->mismatch($theClass, $violations, $because, $fqcn);
    }

    private function mismatch(ClassDescription $theClass, Violations $violations, string $because, string $fqcn): void
    {
        $violations->add(Violation::createWithErrorLine(
            $theClass->getFQCN(),
            ViolationMessage::withDescription($this->describe($theClass, $because), "class {$fqcn} does not match"),
            1,
            $theClass->getFilePath(),
        ));
    }
}

final class NamespaceEndsWith implements Expression
{
    public function __construct(private string $suffix) {}

    public function describe(ClassDescription $theClass, string $because): Description
    {
        return new Description("should reside in a namespace ending with {$this->suffix}", $because);
    }

    public function evaluate(ClassDescription $theClass, Violations $violations, string $because): void
    {
        $fqcn = $theClass->getFQCN();
        $separator = strrpos($fqcn, '\\');
        $namespace = $separator === false ? '' : substr($fqcn, 0, $separator);

        if (str_ends_with($namespace, $this->suffix)) {
            return;
        }

        $violations->add(Violation::createWithErrorLine(
            $theClass->getFQCN(),
            ViolationMessage::withDescription(
                $this->describe($theClass, $because),
                "resides in {$namespace}",
            ),
            1,
            $theClass->getFilePath(),
        ));
    }
}

/**
 * Commands may use the Domain, other application types, Eloquent models,
 * and any class outside App. Repositories, Http, and Infrastructure stay forbidden.
 */
final class CommandsDependOnDomainApplicationOrModels implements Expression
{
    /** @var list<string> */
    private array $namespaces = ['App\Domain', 'App\Application', 'App\Models'];

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

            if (! str_starts_with($fqcn, 'App\\') || $dependency->matchesOneOf(...$this->namespaces)) {
                continue;
            }

            $violations->add(Violation::createWithErrorLine(
                $theClass->getFQCN(),
                ViolationMessage::withDescription($this->describe($theClass, $because), "depends on {$fqcn}"),
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
        ->should(new DomainDependsOnItselfOrVendor)
        ->because('Domain depends on itself and on vendor classes');

    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('App\Application'))
        ->should(new ApplicationDependsOnDomainRepositoriesOrModels)
        ->because('Application depends on the Domain, repository interfaces, models, and vendor classes');

    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('App\Http\Controllers'))
        ->should(new ControllersDependOnUsecaseInterfaces)
        ->because('Controllers depend on use-case interfaces and commands, not repositories or infrastructure');

    $rules[] = Rule::allClasses()
        ->that(new ClassNameEndsWith(['Entity'], 'App\Domain\Entity'))
        ->should(new NamespaceEndsWith('\\Entities'))
        ->because('Entity classes reside in an Entities namespace');

    $rules[] = Rule::allClasses()
        ->that(new ClassNameEndsWith(['Usecase', 'UsecaseInterface']))
        ->should(new NamespaceEndsWith('\\Usecases'))
        ->because('Use cases reside in a Usecases namespace');

    $rules[] = Rule::allClasses()
        ->that(new ClassNameEndsWith(['Command']))
        ->should(new NamespaceEndsWith('\\Commands'))
        ->because('Commands reside in a Commands namespace');

    $rules[] = Rule::allClasses()
        ->that(new NamespaceEndsWith('\\Commands'))
        ->should(new CommandsDependOnDomainApplicationOrModels)
        ->because('Commands depend on the Domain, other application types, models, and vendor classes');

    $config->add($classSet, ...$rules);
};
