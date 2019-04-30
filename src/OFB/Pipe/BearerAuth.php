<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Pipe;

use Throwable;
use Dof\Framework\Facade\JWT;
use Dof\Framework\Facade\Response;
use Dof\Framework\ConfigManager;

/**
 * AUTHORIZATION: Bearer {token}
 */
class BearerAuth
{
    /** @var string: The bearer auth token found in request */
    protected $token;

    /** @var string: The token secret used for signature, can be overwrite by subclass */
    protected $__secret = 'bear-auth-token-secret';

    /** @var string: The parameter key to store authenticated user id */
    protected $__authid = 'uid';

    public function pipein($request, $response, $route, $port)
    {
        $header = trim((string) $request->getHeader('AUTHORIZATION'));
        if ($header) {
            $token = mb_substr($header, 7);
        } else {
            $key   = null;
            $token = $request->match(['token', 'bearer_token'], $key);
        }

        if (! $token) {
            $this->abort(401, 'MissingTokenHeaderOrParameter', [], $port, $response);
        }


        $secret = ConfigManager::getDomainFinalEnvByNamespace($route->get('class'), $this->__secret);
        if (! $secret) {
            $this->abort(500, 'TokenSecretMissing', ['key' => $this->__secret], $port, $response);
        }
        $id = $secret[0] ?? null;
        if (is_null($id)) {
            $this->abort(500, 'TokenSecretIdMissing', [], $port, $response);
        }
        $key = $secret[1] ?? null;
        if (is_null($key) || (! $key)) {
            $this->abort(500, 'TokenSecretKeyMissing', [], $port, $response);
        }

        try {
            $uid = JWT::setSecretId($id)->setSecretKey($key)->verify($token);

            $route->params->pipe->set(static::class, collect([
                $this->__authid => $uid,
            ]));
        } catch (Throwable $e) {
            $message = method_exists($e, 'getName') ? $e->getName() : $e->getMessage();
            $context = [];
            $context = parse_throwable($e, $context);

            $this->abort(500, $message, $context, $port, $response);
        }

        $this->token = $token;

        return true;
    }

    protected function abort($status, $message, $context, $port, $response)
    {
        $response->setStatus($status)->setBody($this->wrap($port, [$status, $message, $context]))->send();
    }

    protected function wrap($port, $body)
    {
        $wraperr = $port->get('wraperr');
        $wraperr = $wraperr ? $wraperr : ConfigManager::getFramework('web.error.wrapper', null);
        if (! $wraperr) {
            return $body;
        }

        if (is_array($wraperr)) {
            $body = Response::wraperr($body, $wraperr);
        } elseif (class_exists($wraperr)) {
            try {
                $body = Response::wraperr($body, wrapper($wraperr, 'err'));
            } catch (Throwable $e) {
                // ignore if any exception thrown to avoid empty response
            }
        }

        return $body;
    }
}
