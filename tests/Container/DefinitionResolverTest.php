<?php

namespace Assembly\Test\Container;

use Assembly\Container\Container;
use Assembly\Container\DefinitionResolver;
use Assembly\FactoryCallDefinition;
use Assembly\ObjectDefinition;
use Assembly\ParameterDefinition;
use Assembly\Reference;
use Assembly\Test\Container\Fixture\Class1;
use Assembly\Test\Container\Fixture\EmptyClass;

class DefinitionResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function resolves_parameter_definitions()
    {
        $resolver = new DefinitionResolver(new Container([]));

        $this->assertSame('bar', $resolver->resolve(new ParameterDefinition('bar')));
    }

    /**
     * @test
     */
    public function resolves_instance_definitions()
    {
        $definition = new ObjectDefinition('Assembly\Test\Container\Fixture\Class1');
        $definition->addPropertyAssignment('publicField', 'public field');
        $definition->addConstructorArgument('constructor param1');
        $definition->addConstructorArgument('constructor param2');
        $definition->addMethodCall('setSomething', 'setter param1', 'setter param2');

        $resolver = new DefinitionResolver(new Container([]));

        /** @var Class1 $service */
        $service = $resolver->resolve($definition);
        $this->assertInstanceOf('Assembly\Test\Container\Fixture\Class1', $service);
        $this->assertSame('public field', $service->publicField);
        $this->assertSame('constructor param1', $service->constructorParam1);
        $this->assertSame('constructor param2', $service->constructorParam2);
        $this->assertSame('setter param1', $service->setterParam1);
        $this->assertSame('setter param2', $service->setterParam2);
    }

    /**
     * @test
     */
    public function resolves_nested_definitions_in_instance_definitions()
    {
        $definition = new ObjectDefinition('Assembly\Test\Container\Fixture\Class1');
        $definition->addPropertyAssignment('publicField', new Reference('ref1'));
        $definition->addConstructorArgument(new Reference('ref2'));
        $definition->addConstructorArgument(new ObjectDefinition('Assembly\Test\Container\Fixture\EmptyClass'));
        $definition->addMethodCall('setSomething', new Reference('ref3'), new ObjectDefinition('Assembly\Test\Container\Fixture\EmptyClass'));

        $resolver = new DefinitionResolver(new Container([
            'ref1' => 'public field',
            'ref2' => 'constructor param1',
            'ref3' => 'setter param1',
        ]));

        /** @var Class1 $service */
        $service = $resolver->resolve($definition);
        $this->assertInstanceOf('Assembly\Test\Container\Fixture\Class1', $service);
        $this->assertSame('public field', $service->publicField);
        $this->assertSame('constructor param1', $service->constructorParam1);
        $this->assertEquals(new EmptyClass(), $service->constructorParam2);
        $this->assertSame('setter param1', $service->setterParam1);
        $this->assertEquals(new EmptyClass(), $service->setterParam2);
    }

    /**
     * @test
     */
    public function resolves_recursive_definitions_in_instance_definitions()
    {
        $definition = new ObjectDefinition('Assembly\Test\Container\Fixture\Class1');
        $definition->addPropertyAssignment('publicField', [new Reference('ref1')]);
        $definition->addConstructorArgument([new Reference('ref2')]);
        $definition->addConstructorArgument([new ObjectDefinition('Assembly\Test\Container\Fixture\EmptyClass')]);
        $definition->addMethodCall('setSomething', [new Reference('ref3')], [new ObjectDefinition('Assembly\Test\Container\Fixture\EmptyClass')]);

        $resolver = new DefinitionResolver(new Container([
            'ref1' => 'public field',
            'ref2' => 'constructor param1',
            'ref3' => 'setter param1',
        ]));

        /** @var Class1 $service */
        $service = $resolver->resolve($definition);
        $this->assertInstanceOf('Assembly\Test\Container\Fixture\Class1', $service);
        $this->assertSame(['public field'], $service->publicField);
        $this->assertSame(['constructor param1'], $service->constructorParam1);
        $this->assertEquals([new EmptyClass()], $service->constructorParam2);
        $this->assertSame(['setter param1'], $service->setterParam1);
        $this->assertEquals([new EmptyClass()], $service->setterParam2);
    }

    /**
     * @test
     */
    public function resolves_reference_definitions()
    {
        $resolver = new DefinitionResolver(new Container([
            'bar' => 'qux',
        ]));

        $this->assertSame('qux', $resolver->resolve(new Reference('bar')));
    }

    /**
     * @test
     */
    public function resolves_service_factory_definitions()
    {
        $provider = new FakeDefinitionProvider([
            'factory' => new ObjectDefinition('Assembly\Test\Container\Fixture\Factory'),
        ]);
        $resolver = new DefinitionResolver(new Container([], [$provider]));

        $result = $resolver->resolve(new FactoryCallDefinition(new Reference('factory'), 'create'));

        $this->assertSame('Hello', $result);
    }

    /**
     * @test
     */
    public function resolves_static_factory_definitions()
    {
        $resolver = new DefinitionResolver(new Container([]));

        $definition = new FactoryCallDefinition('Assembly\Test\Container\Fixture\Factory', 'staticCreate');

        $this->assertSame('Hello', $resolver->resolve($definition));
    }

    /**
     * @test
     */
    public function passes_the_provided_factory_arguments()
    {
        $provider = new FakeDefinitionProvider([
            'factory' => new ObjectDefinition('Assembly\Test\Container\Fixture\Factory'),
            'bar' => new ParameterDefinition('bar'),
        ]);
        $resolver = new DefinitionResolver(new Container([], [$provider]));

        $definition = (new FactoryCallDefinition(new Reference('factory'), 'returnsParameters'))
            ->setArguments('foo', new Reference('bar'));

        $this->assertSame('foobar', $resolver->resolve($definition));
    }

    /**
     * @test
     */
    public function operates_on_any_object_service()
    {
        $serviceName = 'my_object';
        $serviceClass = 'ArrayObject';
        $serviceDefinition = new FakeObjectDefinition($serviceClass);
        $provider = new FakeDefinitionProvider([
            $serviceName => $serviceDefinition
        ]);

        $resolver = new DefinitionResolver(new Container([], [$provider]));
        $this->assertInstanceOf($serviceClass, $resolver->resolve($serviceDefinition), 'Resolver cannot operate on alternative implementations of `ObjectDefinitionInterface`');
    }
}
