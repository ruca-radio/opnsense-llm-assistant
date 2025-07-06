<?php

namespace CognitiveSecurity\LLMAssistant\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\CognitiveSecurity\LLMAssistant\LLMAssistant';
    protected static $internalModelName = 'llmassistant';
}