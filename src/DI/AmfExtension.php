<?php

namespace Goodshape\Amf\DI;


use Goodshape\Amf\Helpers\CustomClassConvertor;
use Goodshape\Amf\Http\AMFRequest;
use Goodshape\Amf\Http\AMFRequestFactory;
use Goodshape\Amf\Http\HttpRequestFactory;
use Nette\DI\CompilerExtension;

/**
 * Nette compiler extension
 *
 * @author Jan Langer <jan.langer@goodshape.cz>
 * @package Goodshape\Amf\DI
 */
class AmfExtension extends CompilerExtension
{

    private $defaults = [
        'requestNamespaces' => [],
        'mappings' => [],
        'module' => 'Api',
    ];


    public function loadConfiguration()
    {

        $builder = $this->getContainerBuilder();
        $config = $this->getConfig($this->defaults);

        $manager = $builder->addDefinition($this->prefix('manager'))
                           ->setClass('Goodshape\Amf\Application\Manager', [$config]
            );

        $customClassConvertor = $builder->addDefinition($this->prefix('classConvertor'))
            ->setClass(CustomClassConvertor::class, [$config['requestNamespaces']])
            ->setInject(FALSE)->setAutowired(FALSE);

        $factory = $builder->addDefinition($this->prefix('factory'))
            ->setClass(AMFRequestFactory::class, [$customClassConvertor])
            ->setInject(FALSE);


        $builder->getDefinition('nette.httpRequestFactory')
                ->setClass(HttpRequestFactory::class);




    }


} 