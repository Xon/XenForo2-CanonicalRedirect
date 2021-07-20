<?php

namespace SV\CanonicalRedirect\XF\Pub\Controller;

use SV\CanonicalRedirect\Listener;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error as ErrorReply;
use XF\Mvc\Reply\Exception as ExceptionReply;
use XF\Mvc\Reply\Redirect as RedirectReply;

/**
 * Extends \XF\Pub\Controller\Register
 */
class Register extends XFCP_Register
{
    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);

        if (Listener::$redirectedOnce ?? false)
        {
            return;
        }

        $options = \XF::options();
        if (!($options->SV_CanonicalRedirection_CloudFlareReg ?? false))
        {
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (\strlen($host) === 0)
        {
            // bad config configuration
            return;
        }

        // not a web request, really shouldn't happen here!
        $request = \XF::app()->request();
        if ($request === null)
        {
            return;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $basePath = \rtrim($request->convertToAbsoluteUri($options->boardUrl), '/');
        $boardHost = \parse_url($basePath, PHP_URL_HOST);
        if (\substr($host, 0, \strlen($boardHost)) === $boardHost)
        {
            if (($options->SV_CanonicalRedirection_CloudFlare ?? false) &&
                (empty($_SERVER['HTTP_CF_RAY']) || empty($_SERVER['HTTP_CF_VISITOR'])))
            {
                // on non-cloudflare URL, but not using cloudflare!
                Listener::$redirectedOnce = true;
                throw new ExceptionReply(new ErrorReply("Must use CloudFlare", (int)($options->svNonCanonicalHttpErrorCode ?? 403)));
            }

            return;
        }

        $url = $basePath . $requestUri;
        if ($options->SV_CanonicalRedirection_Perm ?? false)
        {
            $redirectResponse = new RedirectReply($url, 'permanent', "Must use CloudFlare");
        }
        else
        {
            $redirectResponse = new RedirectReply($url, 'temporary', "Must use CloudFlare");
        }
        Listener::$redirectedOnce = true;
        throw new ExceptionReply($redirectResponse);
    }
}