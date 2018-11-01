<?php

namespace SV\CanonicalRedirect\XF\Pub\Controller;

use SV\CanonicalRedirect\Listener;
use XF\Mvc\ParameterBag;

/**
 * Extends \XF\Pub\Controller\Register
 */
class Register extends XFCP_Register
{
    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);

        if (Listener::$redirectedOnce)
        {
            return;
        }

        $options = \XF::options();
        if (!$options->SV_CanonicalRedirection_CloudFlareReg)
        {
            return;
        }

        $host = @$_SERVER['HTTP_HOST'];
        if (empty($host))
        {
            // bad config configuration
            return;
        }

        // not a web request, really shouldn't happen here!
        $request = \XF::app()->request();
        if (!$request)
        {
            return;
        }

        $requestUri = @$_SERVER['REQUEST_URI'];
        $basePath = rtrim($request->convertToAbsoluteUri($options->boardUrl), '/');
        $boardHost = parse_url($basePath, PHP_URL_HOST);
        if (substr($host, 0, strlen($boardHost)) === $boardHost)
        {
            if ($options->SV_CanonicalRedirection_CloudFlare &&
                (empty($_SERVER['HTTP_CF_RAY']) || empty($_SERVER['HTTP_CF_VISITOR']) || empty($_SERVER['HTTP_CF_CONNECTING_IP'])))
            {
                // on non-cloudflare URL, but not using cloudflare!
                Listener::$redirectedOnce = true;
                throw new \XF\Mvc\Reply\Exception(new \XF\Mvc\Reply\Error("Must use CloudFlare", 444));
            }

            return;
        }

        $url = $basePath . $requestUri;
        if ($options->SV_CanonicalRedirection_Perm)
        {
            $redirectResponse = new \XF\Mvc\Reply\Redirect($url, 'permanent', "Must use CloudFlare");
        }
        else
        {
            $redirectResponse = new \XF\Mvc\Reply\Redirect($url, 'temporary', "Must use CloudFlare");
        }
        Listener::$redirectedOnce = true;
        throw new \XF\Mvc\Reply\Exception($redirectResponse);
    }
}