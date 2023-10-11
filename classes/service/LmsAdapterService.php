<?php

namespace tool_ltiextensions\service;

// Сервис, получающий данные из LmsAdapter
class LmsAdapterService
{
    private LmsAdapterHttpClient $lmsHttpClient;

    function __construct()
    {
        $this->lmsHttpClient = new LmsAdapterHttpClient();
        $this->lmsHttpClient->initialize();
    }
}
