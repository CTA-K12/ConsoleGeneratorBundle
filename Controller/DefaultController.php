<?php

namespace MESD\Console\GeneratorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('MESDConsoleGeneratorBundle:Default:index.html.twig', array('name' => $name));
    }
}
