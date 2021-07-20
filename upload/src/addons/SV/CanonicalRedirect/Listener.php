<?php

namespace SV\CanonicalRedirect;

use XF\Mvc\Controller;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error as ErrorReply;
use XF\Mvc\Reply\Exception as ExceptionReply;
use XF\Mvc\Reply\Redirect as RedirectReply;

class Listener
{
    public static bool $redirectedOnce = false;

    /** @noinspection PhpUnusedParameterInspection */
    public static function controller_pre_dispatch(Controller $controller, $action, ParameterBag $params)
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

        $isRobot = !$session->isStarted() || $session->get('robot');
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

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (\strlen($host) === 0)
        {
            // bad config configuration
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
                self::$redirectedOnce = true;
                // on non-cloudflare URL, but not using cloudflare!
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

        self::$redirectedOnce = true;
        throw new ExceptionReply($redirectResponse);
    }
}