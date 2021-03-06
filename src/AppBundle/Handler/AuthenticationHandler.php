<?php

namespace AppBundle\Handler;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;


class AuthenticationHandler implements AuthenticationSuccessHandlerInterface, LogoutSuccessHandlerInterface
{

    protected $httpUtils;
    protected $options;
    protected $tokenStorage;
    protected $providerKey = 'main';
    protected $defaultOptions = array(
        'always_use_default_target_path' => false,
        'default_target_path' => '/admin/dashboard',
        'login_path' => '/login',
        'target_path_parameter' => '_target_path',
        'use_referer' => false,
    );

    public function __construct(HttpUtils $httpUtils, \Symfony\Component\DependencyInjection\ContainerInterface $cont, TokenStorage $tokenStorage)
    {
        $this->container = $cont;
        $this->httpUtils = $httpUtils;
        $this->tokenStorage = $tokenStorage;
        $this->setOptions($this->defaultOptions);
    }

    /**
     * Gets the options.
     *
     * @return array An array of options
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Sets the options.
     *
     * @param array $options An array of options
     */
    public function setOptions(array $options)
    {
        $this->options = array_merge($this->defaultOptions, $options);
    }

    /**
     * Builds the target URL according to the defined options.
     *
     * @param Request $request
     *
     * @return string
     */
    protected function determineTargetUrl(Request $request)
    {
        if ($this->options['always_use_default_target_path']) {
            return $this->options['default_target_path'];
        }

        if ($targetUrl = $request->get($this->options['target_path_parameter'], null, true)) {
            return $targetUrl;
        }

        if (null !== $this->providerKey && $targetUrl = $request->getSession()->get('_security.' . $this->providerKey . '.target_path')) {
            $request->getSession()->remove('_security.' . $this->providerKey . '.target_path');

            return $targetUrl;
        }

        if ($this->options['use_referer'] && ($targetUrl = $request->headers->get('Referer')) && $targetUrl !== $this->httpUtils->generateUri($request, $this->options['login_path'])) {
            return $targetUrl;
        }

        return $this->options['default_target_path'];
    }

    /**
     * onAuthenticationSuccess
     *
     * @author    Joe Sexton <joe@webtipblog.com>
     * @param    Request $request
     * @param    TokenInterface $token
     * @return    Response
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        $urlRedirect = $this->determineTargetUrl($request);
        if ($this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN', null)) {
            return $this->httpUtils->createRedirectResponse($request, $this->options['default_target_path']);
        }
        if ($urlRedirect == $this->options['default_target_path']) {
            $urlRedirect = $this->container->get('router')->generate('app_switch_user');
        }
//        return $this->httpUtils->createRedirectResponse($request, $urlRedirect); Stupid Mike
        return new RedirectResponse($urlRedirect, 302);
    }

    public function onLogoutSuccess(Request $request)
    {
        if (!$this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN', null)) {
            $em = $this->container->get('doctrine')->getManager();
            $user = $this->tokenStorage->getToken()->getUser();
//            $user->setRoles([]);
//            $user->setCompany(null);
//            $user->setLoginWithPosition(null);
//            $user->setThirdParty(null);
//            $em->persist($user);
//            $em->flush();
        }
        $urlRedirect = $this->container->get('router')->generate('fos_user_security_login', array(), true);
        return new RedirectResponse($urlRedirect);
    }

}
