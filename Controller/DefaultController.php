<?php

namespace Mesd\Console\GeneratorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('MesdConsoleGeneratorBundle:Default:index.html.twig', array('name' => $name));
    }
}
