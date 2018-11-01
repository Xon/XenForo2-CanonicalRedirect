<?php

namespace SV\CanonicalRedirect;

class Listener
{
    public static $redirectedOnce = false;

    public static function controller_pre_dispatch(/** @noinspection PhpUnusedParameterInspection */
        \XF\Mvc\Controller $controller, $action, \XF\Mvc\ParameterBag $params)
    {
        if (self::$redirectedOnce)
        {
            return;
        }

        // only apply to public app (ie not admin)
        if (!(\XF::app() instanceof \XF\Pub\App))
        {
            return;
        }

        $session = \XF::session();
        if (!$session)
        {
            return;
        }

        // not a web request
        $request = \XF::app()->request();
        if (!$request)
        {
            return;
        }

        $visitor = \XF::visitor();

        $isRobot = $session->isStarted() ? $session->get('robot') : true;
        $canRedirect = false;
        $options = \XF::options();
        switch ($options->SV_CanonicalRedirection)
        {
            case 'all':
                $canRedirect = true;
                break;
            case 'nonadmin':
                $canRedirect = !$visitor->is_admin;
                break;
            case 'nonmod':
                $canRedirect = !$visitor->is_admin && !$visitor->is_moderator;
                break;
            case 'guest':
                $canRedirect = !$visitor->user_id;
                break;
            case 'robot':
                $canRedirect = !$visitor->user_id && $isRobot;
                break;
        }
        if (!$canRedirect && $options->SV_CanonicalRedirectionGroups_Blacklist)
        {
            $blacklist = \array_filter(\array_map('\intVal', explode(',', $options->SV_CanonicalRedirectionGroups_Blacklist)));
            $canRedirect = $blacklist && $visitor->isMemberOf($blacklist);
        }
        if (!$canRedirect)
        {
            return;
        }

        $host = @$_SERVER['HTTP_HOST'];
        if (empty($host))
        {
            // bad config configuration
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
                self::$redirectedOnce = true;
                // on non-cloudflare URL, but not using cloudflare!
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

        self::$redirectedOnce = true;
        throw new \XF\Mvc\Reply\Exception($redirectResponse);
    }
}