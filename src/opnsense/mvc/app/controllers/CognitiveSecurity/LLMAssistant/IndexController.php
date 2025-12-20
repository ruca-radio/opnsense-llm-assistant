<?php

namespace CognitiveSecurity\LLMAssistant;

use OPNsense\Base\IndexController as BaseIndexController;
use OPNsense\Core\Config;

class IndexController extends BaseIndexController
{
    public function indexAction()
    {
        // Pick the template
        $this->view->pick('CognitiveSecurity/LLMAssistant/index');
        
        // Create form
        $this->view->generalForm = $this->getForm("general");
    }
}