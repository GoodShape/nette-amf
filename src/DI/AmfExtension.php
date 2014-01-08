<?php

namespace Goodshape\Amf\DI;


use Nette\DI\CompilerExtension;

/**
 * Nette compiler extension
 *
 * @author Jan Langer <jan.langer@goodshape.cz>
 * @package Goodshape\Amf\DI
 */
class AmfExtension extends CompilerExtension {

    private $defaults = [
        'requestNamespaces' => [],
        'mappings' => [],
    ];


    public function loadConfiguration() {

        $builder = $this->getContainerBuilder();
        $config = $this->getConfig($this->defaults);

        $manager = $builder->addDefinition($this->prefix('manager'))
                           ->setClass('Goodshape\Amf\Application\Manager', [$config]
            );

    }


} 