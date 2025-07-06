<?php

namespace CognitiveSecurity\LLMAssistant;

use OPNsense\Base\IndexController;
use OPNsense\Core\Config;

class IndexController extends IndexController
{
    public function indexAction()
    {
        // Pick the template
        $this->view->pick('CognitiveSecurity/LLMAssistant/index');
        
        // Create form
        $this->view->generalForm = $this->getForm("general");
    }
}